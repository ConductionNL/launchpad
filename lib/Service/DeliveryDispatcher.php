<?php

/**
 * DeliveryDispatcher
 *
 * Delivers the artefacts of one fire to its recipients (Task 5,
 * REQ-SCH-005): `email` through the Nextcloud mailer, `nextcloudFiles` into
 * a user's Files area, `webhook` as a signed multipart POST and `sftp`
 * through {@see ScheduledExportSftpConnector}.
 *
 * One delivery is one recipient on one channel (design D4) and carries the
 * artefacts rendered for that recipient's filter context (REQ-SCH-004). A
 * recipient whose filter context could not be rendered fails on its own;
 * the others are still delivered. Every attempt is counted by the
 * {@see RetryHandler} and written to the run-row at once
 * ({@see ScheduledExportAuditLog}).
 *
 * A recipient with an `openconnectorSourceId` must be sent through that
 * OpenConnector source (REQ-SCH-010). That route is not built yet, so such a
 * delivery fails with a clear error instead of falling back to local
 * credentials.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-5
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeInterface;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\Mail\IMailer;
use RuntimeException;
use Throwable;

/**
 * Sends rendered artefacts to each recipient and records every attempt.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
 */
class DeliveryDispatcher {

	public const SIGNATURE_HEADER = 'X-LaunchPad-Signature';

	private const CONTENT_TYPES = [
		'pdf' => 'application/pdf',
		'png' => 'image/png',
		'csv' => 'text/csv',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	];

	/**
	 * Constructor.
	 *
	 * @param IMailer                        $mailer     The Nextcloud mailer.
	 * @param IRootFolder                    $rootFolder Nextcloud's file tree.
	 * @param IClientService                 $http       Nextcloud's HTTP client.
	 * @param ScheduledExportSftpConnector   $sftp       SFTP uploads.
	 * @param ScheduledExportArtefactStorage $artefacts  Reads artefact bytes back for a retry.
	 * @param RetryHandler                   $retry      Counts attempts and decides the next one.
	 * @param ScheduledExportAuditLog        $auditLog   Writes each attempt to the run-row.
	 */
	public function __construct(
		private readonly IMailer $mailer,
		private readonly IRootFolder $rootFolder,
		private readonly IClientService $http,
		private readonly ScheduledExportSftpConnector $sftp,
		private readonly ScheduledExportArtefactStorage $artefacts,
		private readonly RetryHandler $retry,
		private readonly ScheduledExportAuditLog $auditLog,
	) {
	}//end __construct()

	/**
	 * The id a recipient's deliveries are recorded under: its own `id`, else
	 * its position on the export.
	 *
	 * @param array<string, mixed> $recipient The recipient.
	 * @param int                  $index     Its position on the export.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-5
	 */
	public static function recipientId(array $recipient, int $index): string {
		$id = $recipient['id'] ?? null;
		if (is_string($id) === true && $id !== '') {
			return $id;
		}

		return 'recipient-' . $index;
	}//end recipientId()

	/**
	 * The key that groups recipients whose filter contexts are identical
	 * (REQ-SCH-004): the same filters in any order give the same key.
	 *
	 * @param mixed $filterContext The recipient's `filterContext`.
	 *
	 * @return string `all` without a filter, else a hash of the sorted filters.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-004-per-recipient-filter-context
	 */
	public static function filterKey(mixed $filterContext): string {
		if (is_array($filterContext) === false || $filterContext === []) {
			return 'all';
		}

		ksort($filterContext);
		return substr(hash('sha256', (string)json_encode($filterContext)), 0, 16);
	}//end filterKey()

	/**
	 * Deliver one fire's artefacts to every enabled recipient, writing each
	 * attempt to the run-row.
	 *
	 * @param array<string, mixed>       $export    The export, with its secrets.
	 * @param list<array<string, mixed>> $artefacts The artefacts, each with `filterKey`, `filename`, `format` and `content`
	 *                                              (or a `storagePath`), or an `error` when its filter context failed.
	 * @param array<string, mixed>       $run       The open run-row.
	 * @param DateTimeInterface          $now       The moment of the attempt.
	 *
	 * @return array<string, mixed> The run-row with every delivery.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
	 */
	public function dispatch(array $export, array $artefacts, array $run, DateTimeInterface $now): array {
		foreach (array_values((array)($export['recipients'] ?? [])) as $index => $recipient) {
			if (is_array($recipient) === false || ($recipient['enabled'] ?? true) === false) {
				continue;
			}

			$delivery = [
				'recipientId' => self::recipientId(recipient: $recipient, index: $index),
				'channel' => (string)($recipient['channel'] ?? ''),
				'status' => RetryHandler::STATUS_PENDING,
				'attemptCount' => 0,
			];
			$run      = $this->attempt(export: $export, recipient: $recipient, delivery: $delivery, artefacts: $artefacts, run: $run, now: $now);
		}

		return $run;
	}//end dispatch()

	/**
	 * Attempt every delivery of a run-row that is due for a retry.
	 *
	 * @param array<string, mixed> $export The export, with its secrets.
	 * @param array<string, mixed> $run    The run-row.
	 * @param DateTimeInterface    $now    The moment of the attempt.
	 *
	 * @return array<string, mixed> The run-row with the attempts recorded.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function retryDue(array $export, array $run, DateTimeInterface $now): array {
		$recipients = [];
		foreach (array_values((array)($export['recipients'] ?? [])) as $index => $recipient) {
			if (is_array($recipient) === true) {
				$recipients[self::recipientId(recipient: $recipient, index: $index)] = $recipient;
			}
		}

		$owner     = (string)($export['@self']['owner'] ?? '');
		$artefacts = [];
		foreach ((array)($run['renderArtefacts'] ?? []) as $artefact) {
			if (is_array($artefact) === false) {
				continue;
			}

			$path = $artefact['storagePath'] ?? null;
			if (is_string($path) === true && $path !== '') {
				$artefact['content'] = $this->artefacts->read(owner: $owner, path: $path);
			}

			$artefacts[] = $artefact;
		}

		foreach ((array)($run['deliveries'] ?? []) as $delivery) {
			if (is_array($delivery) === false || $this->retry->isDue(delivery: $delivery, now: $now) === false) {
				continue;
			}

			$recipient = $recipients[(string)($delivery['recipientId'] ?? '')] ?? null;
			if ($recipient === null) {
				$attempt = new RetryAttempt(
					succeeded: false,
					now: $now,
					policy: ['maxAttempts' => 1] + RetryHandler::DEFAULT_POLICY,
					error: 'The recipient no longer exists on the export.'
				);
				$failed  = $this->retry->afterAttempt(delivery: $delivery, attempt: $attempt);
				$run    = $this->auditLog->recordDelivery(run: $run, delivery: $failed);
				continue;
			}

			$run = $this->attempt(export: $export, recipient: $recipient, delivery: $delivery, artefacts: $artefacts, run: $run, now: $now);
		}//end foreach

		return $run;
	}//end retryDue()

	/**
	 * One attempt of one delivery, counted and written to the run-row.
	 *
	 * @param array<string, mixed>       $export    The export.
	 * @param array<string, mixed>       $recipient The recipient.
	 * @param array<string, mixed>       $delivery  The delivery so far.
	 * @param list<array<string, mixed>> $artefacts The fire's artefacts.
	 * @param array<string, mixed>       $run       The run-row.
	 * @param DateTimeInterface          $now       The moment of the attempt.
	 *
	 * @return array<string, mixed> The saved run-row.
	 */
	private function attempt(array $export, array $recipient, array $delivery, array $artefacts, array $run, DateTimeInterface $now): array {
		$override = null;
		if (is_array($recipient['retryPolicy'] ?? null) === true) {
			$override = $recipient['retryPolicy'];
		}

		$policy = $this->retry->policy(override: $override);
		$mine   = array_values(
			array_filter(
				$artefacts,
				static fn (array $a): bool => ($a['filterKey'] ?? 'all') === self::filterKey(filterContext: $recipient['filterContext'] ?? null)
			)
		);

		try {
			$files = self::filesOf(artefacts: $mine);
			$delivery['providerMessageId'] = $this->send(export: $export, recipient: $recipient, files: $files, run: $run);
			$attempt = new RetryAttempt(succeeded: true, now: $now, policy: $policy);
		} catch (Throwable $e) {
			$attempt = new RetryAttempt(succeeded: false, now: $now, policy: $policy, error: $e->getMessage());
			// A broken filter context or a refused route is not transient: no retries.
			if ($e instanceof DeliveryRefusedException) {
				$attempt = new RetryAttempt(succeeded: false, now: $now, policy: ['maxAttempts' => 1] + $policy, error: $e->getMessage());
			}
		}

		return $this->auditLog->recordDelivery(run: $run, delivery: $this->retry->afterAttempt(delivery: $delivery, attempt: $attempt));
	}//end attempt()

	/**
	 * Send the files on the recipient's channel.
	 *
	 * @param array<string, mixed>                                              $export    The export.
	 * @param array<string, mixed>                                              $recipient The recipient.
	 * @param list<array{name: string, bytes: string, contentType: string}>     $files     The files.
	 * @param array<string, mixed>                                              $run       The run-row.
	 *
	 * @return string The provider's id of this delivery.
	 *
	 * @throws RuntimeException When the channel refuses or fails.
	 */
	private function send(array $export, array $recipient, array $files, array $run): string {
		$config = [];
		if (is_array($recipient['channelConfig'] ?? null) === true) {
			$config = $recipient['channelConfig'];
		}

		if (is_string($recipient['openconnectorSourceId'] ?? null) === true && $recipient['openconnectorSourceId'] !== '') {
			throw new DeliveryRefusedException('Delivery through an OpenConnector source is not available yet; the recipient was not sent its export.');
		}

		return match ($recipient['channel'] ?? null) {
			'email' => $this->sendEmail(export: $export, config: $config, files: $files, run: $run),
			'nextcloudFiles' => $this->writeFiles(export: $export, config: $config, files: $files),
			'webhook' => $this->postWebhook(export: $export, config: $config, files: $files, run: $run),
			'sftp' => $this->uploadSftp(config: $config, files: $files),
			default => throw new DeliveryRefusedException('Unknown delivery channel.'),
		};
	}//end send()

	/**
	 * One email to all addresses, every file attached.
	 *
	 * The Nextcloud mailer does not expose the message-id it assigns, so the
	 * id recorded is LaunchPad's own for this delivery.
	 *
	 * @param array<string, mixed>                                          $export The export.
	 * @param array<string, mixed>                                          $config The channel config: `to` (list or comma string), `subject`.
	 * @param list<array{name: string, bytes: string, contentType: string}> $files  The files.
	 * @param array<string, mixed>                                          $run    The run-row.
	 *
	 * @return string
	 *
	 * @throws RuntimeException When there is no valid address or the mailer refuses one.
	 */
	private function sendEmail(array $export, array $config, array $files, array $run): string {
		$to = $config['to'] ?? $config['addresses'] ?? [];
		if (is_string($to) === true) {
			$to = array_map('trim', explode(',', $to));
		}

		$to = array_values(
			array_filter(
				(array)$to,
				fn (mixed $a): bool => is_string($a) === true && $this->mailer->validateMailAddress($a) === true
			)
		);
		if ($to === []) {
			throw new DeliveryRefusedException('The email recipient has no valid address.');
		}

		$name    = (string)($export['name'] ?? 'Scheduled export');
		$message = $this->mailer->createMessage();
		$message->setTo($to);
		$message->setSubject((string)($config['subject'] ?? $name));
		$message->setPlainBody((string)($config['body'] ?? $name));
		$message->setAutoSubmitted('auto-generated');
		foreach ($files as $file) {
			$message->attach($this->mailer->createAttachment($file['bytes'], $file['name'], $file['contentType']));
		}

		$refused = $this->mailer->send($message);
		if ($refused !== []) {
			throw new RuntimeException('The mail server refused ' . implode(', ', $refused) . '.');
		}

		return 'launchpad-' . (string)($run['correlationId'] ?? '') . '@' . (string)($run['id'] ?? 'run');
	}//end sendEmail()

	/**
	 * Write the files into a user's Files area.
	 *
	 * @param array<string, mixed>                                          $export The export.
	 * @param array<string, mixed>                                          $config The channel config: `targetPath`, `overwrite`, `userId`.
	 * @param list<array{name: string, bytes: string, contentType: string}> $files  The files.
	 *
	 * @return string The file id of the last file written.
	 *
	 * @throws RuntimeException When a file exists and overwrite is off, or the path is not a folder.
	 */
	private function writeFiles(array $export, array $config, array $files): string {
		$user = (string)($config['userId'] ?? $export['@self']['owner'] ?? '');
		if ($user === '') {
			throw new DeliveryRefusedException('The Files recipient names no user.');
		}

		$parts = array_values(array_filter(explode('/', (string)($config['targetPath'] ?? '')), static fn (string $part): bool => $part !== ''));
		if (array_intersect($parts, ['.', '..']) !== []) {
			throw new DeliveryRefusedException('The Files target path must not contain "." or "..".');
		}

		$folder = $this->rootFolder->getUserFolder($user);
		foreach ($parts as $part) {
			if ($folder->nodeExists($part) === true) {
				$next = $folder->get($part);
			} else {
				$next = $folder->newFolder($part);
			}

			if ($next instanceof Folder === false) {
				throw new RuntimeException('The Files target path is not a folder.');
			}

			$folder = $next;
		}

		$id = '';
		foreach ($files as $file) {
			if ($folder->nodeExists($file['name']) === true) {
				$existing = $folder->get($file['name']);
				if (($config['overwrite'] ?? false) !== true || $existing instanceof File === false) {
					throw new DeliveryRefusedException($file['name'] . ' already exists and overwrite is off.');
				}

				$existing->putContent($file['bytes']);
				$id = (string)$existing->getId();
				continue;
			}

			$id = (string)$folder->newFile($file['name'], $file['bytes'])->getId();
		}

		return $id;
	}//end writeFiles()

	/**
	 * POST the files as multipart/form-data, signed over the exact body.
	 *
	 * @param array<string, mixed>                                          $export The export.
	 * @param array<string, mixed>                                          $config The channel config: `url`, `signingSecret`.
	 * @param list<array{name: string, bytes: string, contentType: string}> $files  The files.
	 * @param array<string, mixed>                                          $run    The run-row.
	 *
	 * @return string The receiver's request id, else its HTTP status.
	 *
	 * @throws RuntimeException When the receiver answers an error.
	 */
	private function postWebhook(array $export, array $config, array $files, array $run): string {
		$url = (string)($config['url'] ?? '');
		if (preg_match('#^https?://#i', $url) !== 1) {
			throw new DeliveryRefusedException('The webhook recipient has no http(s) URL.');
		}

		[$body, $boundary] = self::multipart(
			fields: [
				'export' => (string)($export['name'] ?? ''),
				'exportId' => (string)($export['id'] ?? ''),
				'runId' => (string)($run['id'] ?? ''),
				'correlationId' => (string)($run['correlationId'] ?? ''),
			],
			files: $files
		);
		$headers = ['Content-Type' => 'multipart/form-data; boundary=' . $boundary];
		$secret  = (string)($config['signingSecret'] ?? '');
		if ($secret !== '') {
			$headers[self::SIGNATURE_HEADER] = self::signature(body: $body, secret: $secret);
		}

		$response = $this->http->newClient()->post($url, ['body' => $body, 'headers' => $headers, 'http_errors' => false, 'timeout' => 30]);
		$status   = $response->getStatusCode();
		if ($status >= 400) {
			throw new RuntimeException('The webhook answered HTTP ' . $status . '.');
		}

		$requestId = $response->getHeader('X-Request-Id');
		if ($requestId !== '') {
			return $requestId;
		}

		return 'http-' . $status;
	}//end postWebhook()

	/**
	 * Upload the files over SFTP.
	 *
	 * @param array<string, mixed>                                          $config The channel config.
	 * @param list<array{name: string, bytes: string, contentType: string}> $files  The files.
	 *
	 * @return string The server's confirmed sizes, e.g. `report.xlsx:2048`.
	 */
	private function uploadSftp(array $config, array $files): string {
		$named = [];
		foreach ($files as $file) {
			$named[$file['name']] = $file['bytes'];
		}

		$sizes = $this->sftp->upload(config: $config, files: $named);
		return implode(
			',',
			array_map(static fn (string $name, int $size): string => $name . ':' . $size, array_keys($sizes), $sizes)
		);
	}//end uploadSftp()

	/**
	 * The `X-LaunchPad-Signature` value for a body (REQ-SCH-010).
	 *
	 * @param string $body   The exact request body.
	 * @param string $secret The recipient's signing secret.
	 *
	 * @return string `sha256=<hex>`.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
	 */
	public static function signature(string $body, string $secret): string {
		return 'sha256=' . hash_hmac('sha256', $body, $secret);
	}//end signature()

	/**
	 * The files of a recipient's artefacts; refuses when one failed to render.
	 *
	 * @param list<array<string, mixed>> $artefacts The recipient's artefacts.
	 *
	 * @return list<array{name: string, bytes: string, contentType: string}>
	 *
	 * @throws DeliveryRefusedException When an artefact carries a render error, has no bytes, or there are none.
	 */
	private static function filesOf(array $artefacts): array {
		if ($artefacts === []) {
			throw new DeliveryRefusedException('Nothing was rendered for this recipient.');
		}

		$files = [];
		foreach ($artefacts as $artefact) {
			if (is_string($artefact['error'] ?? null) === true) {
				throw new DeliveryRefusedException($artefact['error']);
			}

			if (is_string($artefact['content'] ?? null) === false) {
				throw new DeliveryRefusedException('The artefact ' . (string)($artefact['filename'] ?? '') . ' is no longer stored.');
			}

			$format  = (string)($artefact['format'] ?? '');
			$files[] = [
				'name' => (string)($artefact['filename'] ?? ('export.' . $format)),
				'bytes' => $artefact['content'],
				'contentType' => self::CONTENT_TYPES[$format] ?? 'application/octet-stream',
			];
		}

		return $files;
	}//end filesOf()

	/**
	 * A multipart/form-data body and its boundary.
	 *
	 * @param array<string, string>                                         $fields Plain fields.
	 * @param list<array{name: string, bytes: string, contentType: string}> $files  The files, as `files[]`.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function multipart(array $fields, array $files): array {
		$boundary = 'launchpad-' . bin2hex(random_bytes(12));
		$body     = '';
		foreach ($fields as $name => $value) {
			$body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . $name . "\"\r\n\r\n" . $value . "\r\n";
		}

		foreach ($files as $file) {
			$body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"files[]\"; filename=\"" . addcslashes($file['name'], '"\\') . "\"\r\n"
				. 'Content-Type: ' . $file['contentType'] . "\r\n\r\n" . $file['bytes'] . "\r\n";
		}

		return [$body . '--' . $boundary . "--\r\n", $boundary];
	}//end multipart()
}//end class
