<?php

/**
 * DeliveryDispatcherTest
 *
 * Delivery of scheduled-export artefacts (Task 5, Task 23): an email with
 * every address and the attachment, Files with the overwrite rule, a webhook
 * signed over its exact body, SFTP through its connector, per-recipient
 * filter contexts, a failed render that does not stop the others, an
 * OpenConnector route that is refused rather than bypassed, and a retry that
 * reads the stored artefact back.
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Service\DeliveryDispatcher;
use OCA\LaunchPad\Service\RetryHandler;
use OCA\LaunchPad\Service\ScheduledExportArtefactStorage;
use OCA\LaunchPad\Service\ScheduledExportAuditLog;
use OCA\LaunchPad\Service\ScheduledExportSftpConnector;
use OCA\LaunchPad\Service\ScheduledExportStore;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\Mail\IAttachment;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\DeliveryDispatcher
 * @uses   \OCA\LaunchPad\Service\RetryHandler
 * @uses   \OCA\LaunchPad\Service\RetryAttempt
 * @uses   \OCA\LaunchPad\Service\ScheduledExportAuditLog
 * @uses   \OCA\LaunchPad\Service\DeliveryRefusedException
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-23
 */
class DeliveryDispatcherTest extends TestCase {

	private IMailer&MockObject $mailer;

	private IRootFolder&MockObject $rootFolder;

	private IClient&MockObject $client;

	private ScheduledExportSftpConnector&MockObject $sftp;

	private ScheduledExportArtefactStorage&MockObject $storage;

	private DeliveryDispatcher $dispatcher;

	private DateTimeImmutable $now;

	/**
	 * Wire the dispatcher to mocked Nextcloud boundaries and a store that answers what it was given.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mailer     = $this->createMock(IMailer::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->client     = $this->createMock(IClient::class);
		$http             = $this->createMock(IClientService::class);
		$http->method('newClient')->willReturn($this->client);
		$this->sftp    = $this->createMock(ScheduledExportSftpConnector::class);
		$this->storage = $this->createMock(ScheduledExportArtefactStorage::class);
		$store         = $this->createMock(ScheduledExportStore::class);
		$store->method('save')->willReturnArgument(1);
		$this->now = new DateTimeImmutable('2026-11-16T09:00:00+01:00');

		$this->dispatcher = new DeliveryDispatcher(
			$this->mailer,
			$this->rootFolder,
			$http,
			$this->sftp,
			$this->storage,
			new RetryHandler(),
			new ScheduledExportAuditLog($store)
		);
	}//end setUp()

	/**
	 * A rendered PDF for everyone.
	 *
	 * @param string $key The filter key.
	 *
	 * @return array<string, mixed>
	 */
	private static function pdf(string $key='all'): array {
		return ['format' => 'pdf', 'filename' => 'report-' . $key . '.pdf', 'filterKey' => $key, 'content' => '%PDF-' . $key];
	}//end pdf()

	/**
	 * Dispatch one export with the given recipients and artefacts.
	 *
	 * @param list<array<string, mixed>> $recipients The recipients.
	 * @param list<array<string, mixed>> $artefacts  The artefacts.
	 *
	 * @return list<array<string, mixed>> The recorded deliveries.
	 */
	private function dispatch(array $recipients, array $artefacts): array {
		$export = ['id' => 'e-1', 'name' => 'Weekly bezwaren', '@self' => ['owner' => 'anna'], 'recipients' => $recipients];
		$run    = ['id' => 'run-1', 'correlationId' => 'corr-1', 'deliveries' => []];
		return $this->dispatcher->dispatch(export: $export, artefacts: $artefacts, run: $run, now: $this->now)['deliveries'];
	}//end dispatch()

	/**
	 * One email goes to all three addresses with the PDF attached, and the delivery records an id.
	 *
	 * @return void
	 */
	public function testAnEmailGoesToEveryAddressWithTheAttachment(): void {
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$message = $this->createMock(IMessage::class);
		$this->mailer->method('createMessage')->willReturn($message);
		$message->expects($this->once())->method('setTo')->with(['a@gemeente.nl', 'b@gemeente.nl', 'c@gemeente.nl']);
		$message->expects($this->once())->method('setSubject')->with('Weekly bezwaren');
		$attachment = $this->createMock(IAttachment::class);
		$this->mailer->expects($this->once())->method('createAttachment')
			->with('%PDF-all', 'report-all.pdf', 'application/pdf')
			->willReturn($attachment);
		$message->expects($this->once())->method('attach')->with($attachment);
		$this->mailer->expects($this->once())->method('send')->with($message)->willReturn([]);

		$deliveries = $this->dispatch(
			recipients: [['channel' => 'email', 'channelConfig' => ['to' => 'a@gemeente.nl, b@gemeente.nl,c@gemeente.nl']]],
			artefacts: [self::pdf()]
		);

		$this->assertSame('success', $deliveries[0]['status']);
		$this->assertSame(1, $deliveries[0]['attemptCount']);
		$this->assertSame('recipient-0', $deliveries[0]['recipientId']);
		$this->assertSame('launchpad-corr-1@run-1', $deliveries[0]['providerMessageId']);
	}//end testAnEmailGoesToEveryAddressWithTheAttachment()

	/**
	 * A webhook is signed with HMAC-SHA256 over the exact body it sends.
	 *
	 * @return void
	 */
	public function testAWebhookIsSignedOverItsExactBody(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(202);
		$response->method('getHeader')->with('X-Request-Id')->willReturn('req-42');
		$sent = null;
		$this->client->expects($this->once())->method('post')
			->with('https://hooks.example.nl/in', $this->anything())
			->willReturnCallback(
				static function (string $url, array $options) use (&$sent, $response): IResponse {
					$sent = $options;
					return $response;
				}
			);

		$deliveries = $this->dispatch(
			recipients: [['id' => 'teams', 'channel' => 'webhook', 'channelConfig' => ['url' => 'https://hooks.example.nl/in', 'signingSecret' => 'k3y']]],
			artefacts: [['format' => 'png', 'filename' => 'kpi.png', 'filterKey' => 'all', 'content' => 'PNGBYTES']]
		);

		$this->assertSame(['success', 'req-42', 'teams'], [$deliveries[0]['status'], $deliveries[0]['providerMessageId'], $deliveries[0]['recipientId']]);
		$this->assertSame('sha256=' . hash_hmac('sha256', $sent['body'], 'k3y'), $sent['headers']['X-LaunchPad-Signature']);
		$this->assertStringStartsWith('multipart/form-data; boundary=', $sent['headers']['Content-Type']);
		$this->assertStringContainsString("filename=\"kpi.png\"\r\nContent-Type: image/png\r\n\r\nPNGBYTES", $sent['body']);
		$this->assertStringContainsString("name=\"runId\"\r\n\r\nrun-1", $sent['body']);
	}//end testAWebhookIsSignedOverItsExactBody()

	/**
	 * A 503 from the webhook is a retry after the initial backoff, not a final failure.
	 *
	 * @return void
	 */
	public function testAWebhook503IsRetriedAfterTheBackoff(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(503);
		$this->client->method('post')->willReturn($response);

		$deliveries = $this->dispatch(
			recipients: [[
				'channel' => 'webhook',
				'channelConfig' => ['url' => 'https://hooks.example.nl/in'],
				'retryPolicy' => ['initialBackoffSeconds' => 120, 'jitter' => false],
			]],
			artefacts: [self::pdf()]
		);

		$this->assertSame('retrying', $deliveries[0]['status']);
		$this->assertSame('The webhook answered HTTP 503.', $deliveries[0]['error']);
		$this->assertSame('2026-11-16T09:02:00+01:00', $deliveries[0]['nextAttemptAt']);
	}//end testAWebhook503IsRetriedAfterTheBackoff()

	/**
	 * Files delivery writes into the target folder and refuses to replace a file unless overwrite is on.
	 *
	 * @return void
	 */
	public function testFilesDeliveryRespectsOverwrite(): void {
		$target   = $this->createMock(Folder::class);
		$existing = $this->createMock(File::class);
		$existing->method('getId')->willReturn(77);
		$home = $this->createMock(Folder::class);
		$home->method('nodeExists')->with('Reports')->willReturn(true);
		$home->method('get')->with('Reports')->willReturn($target);
		$this->rootFolder->method('getUserFolder')->with('bert')->willReturn($home);
		$target->method('nodeExists')->with('report-all.pdf')->willReturn(true);
		$target->method('get')->with('report-all.pdf')->willReturn($existing);
		$existing->expects($this->once())->method('putContent')->with('%PDF-all');

		$deliveries = $this->dispatch(
			recipients: [
				['channel' => 'nextcloudFiles', 'channelConfig' => ['userId' => 'bert', 'targetPath' => '/Reports']],
				['channel' => 'nextcloudFiles', 'channelConfig' => ['userId' => 'bert', 'targetPath' => '/Reports', 'overwrite' => true]],
			],
			artefacts: [self::pdf()]
		);

		$this->assertSame('failed-permanent', $deliveries[0]['status']);
		$this->assertSame('report-all.pdf already exists and overwrite is off.', $deliveries[0]['error']);
		$this->assertSame(['success', '77'], [$deliveries[1]['status'], $deliveries[1]['providerMessageId']]);
	}//end testFilesDeliveryRespectsOverwrite()

	/**
	 * A Files target path with ".." is refused before anything is written.
	 *
	 * @return void
	 */
	public function testAFilesPathCannotClimbOut(): void {
		$this->rootFolder->expects($this->never())->method('getUserFolder');

		$deliveries = $this->dispatch(
			recipients: [['channel' => 'nextcloudFiles', 'channelConfig' => ['userId' => 'bert', 'targetPath' => '/Reports/../../etc']]],
			artefacts: [self::pdf()]
		);

		$this->assertSame('failed-permanent', $deliveries[0]['status']);
		$this->assertSame('The Files target path must not contain "." or "..".', $deliveries[0]['error']);
	}//end testAFilesPathCannotClimbOut()

	/**
	 * SFTP hands the files to the connector and records the sizes the server confirmed.
	 *
	 * @return void
	 */
	public function testSftpRecordsTheConfirmedSize(): void {
		$config = ['host' => 'sftp.example.nl', 'username' => 'gm0355', 'password' => 's3cret', 'remotePath' => '/dropbox'];
		$this->sftp->expects($this->once())->method('upload')
			->with($config, ['report-all.pdf' => '%PDF-all'])
			->willReturn(['report-all.pdf' => 8]);

		$deliveries = $this->dispatch(recipients: [['channel' => 'sftp', 'channelConfig' => $config]], artefacts: [self::pdf()]);

		$this->assertSame(['success', 'report-all.pdf:8'], [$deliveries[0]['status'], $deliveries[0]['providerMessageId']]);
	}//end testSftpRecordsTheConfirmedSize()

	/**
	 * Each recipient gets the artefact of its own filter context; a context that failed to render fails only that recipient.
	 *
	 * @return void
	 */
	public function testEachRecipientGetsItsOwnFilteredArtefact(): void {
		$a = DeliveryDispatcher::filterKey(filterContext: ['gemeenteCode' => 'GM0355']);
		$b = DeliveryDispatcher::filterKey(filterContext: ['gemeenteCode' => 'GM0344']);
		$c = DeliveryDispatcher::filterKey(filterContext: ['onbekend' => 'x']);
		$uploaded = [];
		$this->sftp->method('upload')->willReturnCallback(
			static function (array $config, array $files) use (&$uploaded): array {
				$uploaded[$config['host']] = array_keys($files);
				return array_map('strlen', $files);
			}
		);

		$deliveries = $this->dispatch(
			recipients: [
				['channel' => 'sftp', 'channelConfig' => ['host' => 'a'], 'filterContext' => ['gemeenteCode' => 'GM0355']],
				['channel' => 'sftp', 'channelConfig' => ['host' => 'b'], 'filterContext' => ['gemeenteCode' => 'GM0344']],
				['channel' => 'sftp', 'channelConfig' => ['host' => 'c'], 'filterContext' => ['onbekend' => 'x']],
			],
			artefacts: [self::pdf($a), self::pdf($b), ['format' => 'pdf', 'filterKey' => $c, 'error' => 'Unknown filter field onbekend.']]
		);

		$this->assertSame(['a' => ['report-' . $a . '.pdf'], 'b' => ['report-' . $b . '.pdf']], $uploaded);
		$this->assertSame(['success', 'success', 'failed-permanent'], array_column($deliveries, 'status'));
		$this->assertSame('Unknown filter field onbekend.', $deliveries[2]['error']);
	}//end testEachRecipientGetsItsOwnFilteredArtefact()

	/**
	 * The same filters in another order are the same filter context; none at all is `all`.
	 *
	 * @return void
	 */
	public function testTheFilterKeyIgnoresOrder(): void {
		$this->assertSame(
			DeliveryDispatcher::filterKey(filterContext: ['a' => 1, 'b' => 2]),
			DeliveryDispatcher::filterKey(filterContext: ['b' => 2, 'a' => 1])
		);
		$this->assertNotSame(DeliveryDispatcher::filterKey(filterContext: ['a' => 1]), DeliveryDispatcher::filterKey(filterContext: ['a' => 2]));
		$this->assertSame('all', DeliveryDispatcher::filterKey(filterContext: null));
		$this->assertSame('all', DeliveryDispatcher::filterKey(filterContext: []));
	}//end testTheFilterKeyIgnoresOrder()

	/**
	 * A recipient routed through OpenConnector is refused, never sent with local credentials; a disabled recipient is skipped.
	 *
	 * @return void
	 */
	public function testAnOpenConnectorRouteIsRefusedAndADisabledRecipientSkipped(): void {
		$this->sftp->expects($this->never())->method('upload');

		$deliveries = $this->dispatch(
			recipients: [
				['channel' => 'sftp', 'channelConfig' => ['host' => 'a', 'password' => 'x'], 'openconnectorSourceId' => 'src-1'],
				['channel' => 'sftp', 'channelConfig' => ['host' => 'b'], 'enabled' => false],
			],
			artefacts: [self::pdf()]
		);

		$this->assertCount(1, $deliveries);
		$this->assertSame('failed-permanent', $deliveries[0]['status']);
		$this->assertStringContainsString('OpenConnector', $deliveries[0]['error']);
	}//end testAnOpenConnectorRouteIsRefusedAndADisabledRecipientSkipped()

	/**
	 * A due retry reads the stored artefact back from Files and succeeds on its next attempt.
	 *
	 * @return void
	 */
	public function testADueRetryReadsTheStoredArtefact(): void {
		$this->storage->expects($this->once())->method('read')
			->with('anna', 'Apps/LaunchPad/Exports/e-1/report.pdf')
			->willReturn('%PDF-stored');
		$this->sftp->expects($this->once())->method('upload')
			->with(['host' => 'a'], ['report.pdf' => '%PDF-stored'])
			->willReturn(['report.pdf' => 11]);

		$export = ['id' => 'e-1', '@self' => ['owner' => 'anna'], 'recipients' => [['channel' => 'sftp', 'channelConfig' => ['host' => 'a']]]];
		$run    = [
			'id' => 'run-1',
			'renderArtefacts' => [['format' => 'pdf', 'filename' => 'report.pdf', 'filterKey' => 'all', 'storagePath' => 'Apps/LaunchPad/Exports/e-1/report.pdf']],
			'deliveries' => [
				['recipientId' => 'recipient-0', 'channel' => 'sftp', 'status' => 'retrying', 'attemptCount' => 2, 'nextAttemptAt' => '2026-11-16T08:59:00+01:00'],
			],
		];

		$after = $this->dispatcher->retryDue(export: $export, run: $run, now: $this->now);

		$this->assertSame(['success', 3], [$after['deliveries'][0]['status'], $after['deliveries'][0]['attemptCount']]);
		$this->assertSame('success', (new RetryHandler())->rollUp($after['deliveries']));
	}//end testADueRetryReadsTheStoredArtefact()

	/**
	 * A retry not yet due is left alone.
	 *
	 * @return void
	 */
	public function testARetryNotYetDueIsLeftAlone(): void {
		$this->sftp->expects($this->never())->method('upload');
		$delivery = ['recipientId' => 'recipient-0', 'channel' => 'sftp', 'status' => 'retrying', 'attemptCount' => 1, 'nextAttemptAt' => '2026-11-16T09:30:00+01:00'];

		$after = $this->dispatcher->retryDue(
			export: ['recipients' => [['channel' => 'sftp']]],
			run: ['id' => 'run-1', 'deliveries' => [$delivery]],
			now: $this->now
		);

		$this->assertSame([$delivery], $after['deliveries']);
	}//end testARetryNotYetDueIsLeftAlone()
}//end class
