<?php

/**
 * DeliveryDispatcher
 *
 * Delivery of a fire's artefacts (tasks.md task 5, REQ-SCH-005): one
 * delivery per recipient on its channel — email through the Nextcloud
 * mailer, a file in the creator's Files, a signed multipart POST to a
 * webhook, an SFTP upload — or through the credential vault when the
 * recipient names a connector source (REQ-SCH-010). Each attempt goes
 * through the retry policy (REQ-SCH-006) and is reported at once, so the
 * run-row is written after every attempt. Webhook and SFTP hosts must be on
 * the admin's `export_allowed_hosts` list (fail-closed).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

use DateTimeInterface;
use OCA\LaunchPad\Service\HostAllowList;
use OCA\LaunchPad\Service\RetryAttempt;
use OCA\LaunchPad\Service\RetryHandler;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;
use Throwable;

/**
 * Sends artefacts to recipients.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-5
 */
class DeliveryDispatcher {

	public const CONFIG_ALLOWED_HOSTS = 'export_allowed_hosts';

	public const SIGNATURE_HEADER = 'X-LaunchPad-Signature';

	/**
	 * Constructor.
	 *
	 * @param DeliveryChannels $nc    Mailer, HTTP, Files and config.
	 * @param SftpUploader     $sftp  The SFTP channel.
	 * @param ConnectorVault   $vault The credential vault route.
	 * @param RetryHandler     $retry The retry policy.
	 */
	public function __construct(
		private readonly DeliveryChannels $nc,
		private readonly SftpUploader $sftp,
		private readonly ConnectorVault $vault,
		private readonly RetryHandler $retry,
	) {
	}//end __construct()

	/**
	 * First attempt for every recipient.
	 *
	 * @param DeliveryContext                     $context    The export, its artefacts and the moment.
	 * @param array<string, array<string, mixed>> $recipients Recipient id to recipient.
	 * @param callable|null                       $afterEach  Called with the deliveries so far after each attempt.
	 *
	 * @return list<array<string, mixed>> One delivery per recipient.
	 *
	 * @spec openspec/changes/scheduled-exports/tasks.md#task-5
	 */
	public function dispatch(DeliveryContext $context, array $recipients, ?callable $afterEach = null): array {
		$deliveries = [];
		foreach ($recipients as $id => $recipient) {
			$fresh = [
				'recipientId' => (string)$id,
				'channel' => (string)($recipient['channel'] ?? ''),
				'attemptCount' => 0,
				'status' => RetryHandler::STATUS_PENDING,
			];
			$deliveries[] = $this->deliver(delivery: $fresh, recipient: $recipient, context: $context);
			if ($afterEach !== null) {
				$afterEach($deliveries);
			}
		}

		return $deliveries;
	}//end dispatch()

	/**
	 * One attempt of one delivery, through the retry policy.
	 *
	 * @param array<string, mixed> $delivery  The delivery as the run-row holds it.
	 * @param array<string, mixed> $recipient The recipient, secrets revealed.
	 * @param DeliveryContext      $context   The export, its artefacts and the moment.
	 *
	 * @return array<string, mixed> The updated delivery.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function deliver(array $delivery, array $recipient, DeliveryContext $context): array {
		$override = null;
		if (is_array($recipient['retryPolicy'] ?? null) === true) {
			$override = $recipient['retryPolicy'];
		}

		$policy = $this->retry->policy(override: $override);
		try {
			$messageId = $this->attempt(recipient: $recipient, context: $context);
			$delivery  = $this->retry->afterAttempt(delivery: $delivery, attempt: new RetryAttempt(succeeded: true, now: $context->now, policy: $policy));
			$delivery['providerMessageId'] = $messageId;
		} catch (Throwable $e) {
			$error = 'Delivery failed: ' . $e->getMessage();
			if ($e instanceof DeliveryException || $e instanceof RenderException) {
				$error = $e->getMessage();
			}

			$failed   = new RetryAttempt(succeeded: false, now: $context->now, policy: $policy, error: $error);
			$delivery = $this->retry->afterAttempt(delivery: $delivery, attempt: $failed);
		}

		return $delivery;
	}//end deliver()

	/**
	 * Send once on the recipient's channel.
	 *
	 * @param array<string, mixed> $recipient The recipient, secrets revealed.
	 * @param DeliveryContext      $context   The export, its artefacts and the moment.
	 *
	 * @return string The provider's id of what was sent.
	 *
	 * @throws DeliveryException When it was not delivered.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-005-email-nextcloud-files-webhook-and-sftp-delivery-channels
	 */
	public function attempt(array $recipient, DeliveryContext $context): string {
		if ($context->artefacts === []) {
			throw new DeliveryException('Nothing was rendered for this recipient');
		}

		$config  = (array)($recipient['channelConfig'] ?? []);
		$channel = (string)($recipient['channel'] ?? '');
		$source  = (string)($recipient['openconnectorSourceId'] ?? $config['openconnectorSourceId'] ?? '');
		if ($source !== '' && in_array($channel, ['webhook', 'sftp'], true) === true) {
			$payload = $this->multipart(context: $context);
			return $this->vault->send(sourceId: $source, request: [
				'endpoint' => (string)($config['endpoint'] ?? $config['remotePath'] ?? ''),
				'method' => 'POST',
				'body' => $payload['body'],
				'headers' => ['Content-Type' => $payload['contentType']],
			]);
		}

		return match ($channel) {
			'email' => $this->email(config: $config, context: $context),
			'nextcloudFiles' => $this->files(config: $config, context: $context),
			'webhook' => $this->webhook(config: $config, context: $context),
			'sftp' => $this->upload(config: $config, context: $context),
			default => throw new DeliveryException('Unknown channel: ' . $channel),
		};
	}//end attempt()

	/**
	 * Email: one message to every address, the artefacts attached.
	 *
	 * @param array<string, mixed> $config  `addresses` (list) or `to` (comma-separated).
	 * @param DeliveryContext      $context The export and its artefacts.
	 *
	 * @return string An id of the message: the mailer exposes none, so LaunchPad derives one.
	 *
	 * @throws DeliveryException When an address is invalid or the mailer refuses.
	 */
	private function email(array $config, DeliveryContext $context): string {
		$addresses = $config['addresses'] ?? array_map('trim', explode(',', (string)($config['to'] ?? '')));
		$addresses = array_values(array_filter(array_map('strval', (array)$addresses), static fn (string $a): bool => $a !== ''));
		if ($addresses === []) {
			throw new DeliveryException('The email recipient has no addresses');
		}

		foreach ($addresses as $address) {
			if ($this->nc->mailer->validateMailAddress($address) === false) {
				throw new DeliveryException('Not a valid email address: ' . $address);
			}
		}

		$name    = (string)($context->export['name'] ?? 'Scheduled export');
		$message = $this->nc->mailer->createMessage();
		$message->setTo($addresses);
		$message->setSubject($name);
		$message->setPlainBody((string)($context->export['description'] ?? $name));
		$message->setAutoSubmitted('auto-generated');
		foreach ($context->artefacts as $artefact) {
			$message->attach($this->nc->mailer->createAttachment($artefact->content, $artefact->filename, $artefact->mimeType()));
		}

		$failed = $this->nc->mailer->send($message);
		if ($failed !== []) {
			throw new DeliveryException('The mailer refused: ' . implode(', ', $failed));
		}

		$seed = array_merge($addresses, $this->hashes(context: $context), [$context->now->format(DateTimeInterface::ATOM)]);
		return 'mail-' . substr(hash('sha256', implode('|', $seed)), 0, 32);
	}//end email()

	/**
	 * Files: every artefact into a folder of the export creator's Files;
	 * an existing file is replaced only with `overwrite: true`.
	 *
	 * @param array<string, mixed> $config  `targetPath`, `overwrite`, and an optional `user` that must be the creator.
	 * @param DeliveryContext      $context The export and its artefacts.
	 *
	 * @return string The file ids written.
	 *
	 * @throws DeliveryException When the folder cannot be written.
	 */
	private function files(array $config, DeliveryContext $context): string {
		$owner = (string)($context->export['createdBy'] ?? '');
		$user  = (string)($config['user'] ?? $owner);
		if ($user !== $owner) {
			throw new DeliveryException('An export may only write into the Files of its creator');
		}

		try {
			$folder = $this->nc->rootFolder->getUserFolder($owner);
			$parts  = array_filter(explode('/', (string)($config['targetPath'] ?? 'Exports')), static fn (string $s): bool => $s !== '');
			foreach ($parts as $part) {
				if ($part === '..') {
					throw new DeliveryException('The target path may not contain ..');
				}

				$folder = $this->child(folder: $folder, name: $part);
			}

			$ids = [];
			foreach ($context->artefacts as $artefact) {
				$ids[] = (string)$this->writeFile(folder: $folder, artefact: $artefact, overwrite: ($config['overwrite'] ?? false) === true)->getId();
			}
		} catch (DeliveryException $e) {
			throw $e;
		} catch (Throwable) {
			throw new DeliveryException('The target folder could not be written in Files');
		}

		return implode(',', $ids);
	}//end files()

	/**
	 * Webhook: a multipart POST, HMAC-SHA256-signed over the body when a secret is set.
	 *
	 * @param array<string, mixed> $config  `url` (https, allow-listed host), `signingSecret`.
	 * @param DeliveryContext      $context The export and its artefacts.
	 *
	 * @return string The receiver's request id, or `http-<status>`.
	 *
	 * @throws DeliveryException When the URL is not allowed or the receiver does not answer 2xx.
	 */
	private function webhook(array $config, DeliveryContext $context): string {
		$url = (string)($config['url'] ?? '');
		if (strtolower((string)parse_url($url, PHP_URL_SCHEME)) !== 'https' || $this->allowList()->permits(url: $url) === false) {
			throw new DeliveryException('The webhook URL is not an allowed https address');
		}

		$payload = $this->multipart(context: $context);
		$headers = ['Content-Type' => $payload['contentType']];
		if (is_string($config['signingSecret'] ?? null) === true && $config['signingSecret'] !== '') {
			$headers[self::SIGNATURE_HEADER] = self::signature(body: $payload['body'], secret: $config['signingSecret']);
		}

		try {
			$options  = ['body' => $payload['body'], 'headers' => $headers, 'timeout' => 30, 'http_errors' => false];
			$response = $this->nc->http->newClient()->post($url, $options);
		} catch (Throwable) {
			throw new DeliveryException('The webhook could not be reached');
		}

		$status = $response->getStatusCode();
		if ($status < 200 || $status >= 300) {
			throw new DeliveryException('HTTP ' . $status);
		}

		$requestId = $response->getHeader('X-Request-Id');
		if ($requestId === '') {
			return 'http-' . $status;
		}

		return $requestId;
	}//end webhook()

	/**
	 * SFTP: every artefact uploaded, its size confirmed.
	 *
	 * @param array<string, mixed> $config  The SFTP configuration.
	 * @param DeliveryContext      $context The artefacts.
	 *
	 * @return string The confirmed sizes, per file.
	 *
	 * @throws DeliveryException When the host is not allowed or an upload fails.
	 */
	private function upload(array $config, DeliveryContext $context): string {
		if ($this->allowList()->permits(url: 'https://' . (string)($config['host'] ?? '')) === false) {
			throw new DeliveryException('The SFTP host is not allowed');
		}

		$confirmed = [];
		foreach ($context->artefacts as $artefact) {
			$confirmed[] = $artefact->filename . ':' . $this->sftp->upload(config: $config, artefact: $artefact);
		}

		return 'sftp-size ' . implode(',', $confirmed);
	}//end upload()

	/**
	 * The `sha256=<hex>` signature of a body.
	 *
	 * @param string $body   The exact bytes sent.
	 * @param string $secret The recipient's secret.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
	 */
	public static function signature(string $body, string $secret): string {
		return 'sha256=' . hash_hmac('sha256', $body, $secret);
	}//end signature()

	/**
	 * The multipart body: a `metadata` JSON part, then one part per artefact.
	 *
	 * @param DeliveryContext $context The export and its artefacts.
	 *
	 * @return array{body: string, contentType: string}
	 */
	private function multipart(DeliveryContext $context): array {
		$boundary = 'launchpad-' . substr(hash('sha256', implode('|', $this->hashes(context: $context))), 0, 24);
		$meta     = [
			'exportId' => (string)($context->export['id'] ?? ''),
			'name' => (string)($context->export['name'] ?? ''),
			'sentAt' => $context->now->format(DateTimeInterface::ATOM),
			'artefacts' => array_map(static fn (RenderResult $a): array => ['filename' => $a->filename] + $a->toAudit(), $context->artefacts),
		];

		$body = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"metadata\"\r\n"
			. "Content-Type: application/json\r\n\r\n" . json_encode($meta) . "\r\n";
		foreach ($context->artefacts as $i => $artefact) {
			$name  = addcslashes($artefact->filename, '"\\');
			$body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"file" . $i . '"; filename="' . $name
				. "\"\r\nContent-Type: " . $artefact->mimeType() . "\r\n\r\n" . $artefact->content . "\r\n";
		}

		return ['body' => $body . '--' . $boundary . "--\r\n", 'contentType' => 'multipart/form-data; boundary=' . $boundary];
	}//end multipart()

	/**
	 * Write one artefact, replacing an existing file only when asked.
	 *
	 * @param Folder       $folder    The target folder.
	 * @param RenderResult $artefact  The artefact.
	 * @param bool         $overwrite Whether an existing file is replaced.
	 *
	 * @return File
	 */
	private function writeFile(Folder $folder, RenderResult $artefact, bool $overwrite): File {
		$name = $artefact->filename;
		if ($folder->nodeExists($name) === true) {
			$existing = $folder->get($name);
			if ($overwrite === true && $existing instanceof File) {
				$existing->putContent($artefact->content);
				return $existing;
			}

			$dot = strrpos($name, '.');
			$ext = '';
			$stem = $name;
			if ($dot !== false) {
				$stem = substr($name, 0, $dot);
				$ext  = substr($name, $dot);
			}

			for ($n = 2; $folder->nodeExists($name) === true; $n++) {
				$name = $stem . ' (' . $n . ')' . $ext;
			}
		}

		return $folder->newFile($name, $artefact->content);
	}//end writeFile()

	/**
	 * A child folder, created when missing.
	 *
	 * @param Folder $folder The parent.
	 * @param string $name   The child.
	 *
	 * @return Folder
	 *
	 * @throws DeliveryException When the child is a file.
	 */
	private function child(Folder $folder, string $name): Folder {
		try {
			$node = $folder->get($name);
		} catch (NotFoundException) {
			return $folder->newFolder($name);
		}

		if (($node instanceof Folder) === false) {
			throw new DeliveryException('The target path is not a folder');
		}

		return $node;
	}//end child()

	/**
	 * The sha256s of the artefacts.
	 *
	 * @param DeliveryContext $context The artefacts.
	 *
	 * @return list<string>
	 */
	private function hashes(DeliveryContext $context): array {
		return array_map(static fn (RenderResult $a): string => $a->sha256(), $context->artefacts);
	}//end hashes()

	/**
	 * The admin's outbound host allow-list for webhook and SFTP hosts.
	 *
	 * @return HostAllowList
	 */
	private function allowList(): HostAllowList {
		return new HostAllowList(appConfig: $this->nc->appConfig, configKey: self::CONFIG_ALLOWED_HOSTS);
	}//end allowList()
}//end class
