<?php

/**
 * DeliveryDispatcherTest
 *
 * Delivery channels (tasks.md task 23, REQ-SCH-005/006/010): email to all
 * addresses with the artefacts attached, Files only into the creator's
 * area with overwrite opt-in, a signed multipart webhook, allow-listed
 * hosts only, the credential-vault route, and every outcome through the
 * retry policy.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
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

namespace Unit\Service\ScheduledExport;

use DateTimeImmutable;
use OCA\LaunchPad\Service\RetryHandler;
use OCA\LaunchPad\Service\ScheduledExport\ConnectorVault;
use OCA\LaunchPad\Service\ScheduledExport\DeliveryChannels;
use OCA\LaunchPad\Service\ScheduledExport\DeliveryContext;
use OCA\LaunchPad\Service\ScheduledExport\DeliveryDispatcher;
use OCA\LaunchPad\Service\ScheduledExport\RenderResult;
use OCA\LaunchPad\Service\ScheduledExport\SftpUploader;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IAppConfig;
use OCP\Mail\IAttachment;
use OCP\Mail\IMailer;
use OCP\Mail\IMessage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\DeliveryDispatcher
 * @covers \OCA\LaunchPad\Service\ScheduledExport\DeliveryContext
 * @covers \OCA\LaunchPad\Service\ScheduledExport\DeliveryChannels
 * @uses   \OCA\LaunchPad\Service\RetryHandler
 * @uses   \OCA\LaunchPad\Service\RetryAttempt
 * @uses   \OCA\LaunchPad\Service\HostAllowList
 * @uses   \OCA\LaunchPad\Service\ScheduledExport\RenderResult
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-23
 */
class DeliveryDispatcherTest extends TestCase {

	private IMailer&MockObject $mailer;

	private IClient&MockObject $client;

	private IRootFolder&MockObject $root;

	private SftpUploader&MockObject $sftp;

	private ConnectorVault&MockObject $vault;

	private DeliveryDispatcher $dispatcher;

	private DeliveryContext $context;

	/**
	 * Only hooks.example.org and sftp.cbs.nl are allow-listed.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mailer = $this->createMock(IMailer::class);
		$this->client = $this->createMock(IClient::class);
		$http         = $this->createMock(IClientService::class);
		$http->method('newClient')->willReturn($this->client);
		$this->root = $this->createMock(IRootFolder::class);
		$config     = $this->createMock(IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(static fn (string $app, string $key): string => $key === 'export_allowed_hosts' ? '["hooks.example.org","sftp.cbs.nl"]' : '');
		$this->sftp  = $this->createMock(SftpUploader::class);
		$this->vault = $this->createMock(ConnectorVault::class);

		$this->dispatcher = new DeliveryDispatcher(new DeliveryChannels($this->mailer, $http, $this->root, $config), $this->sftp, $this->vault, new RetryHandler());
		$this->context    = new DeliveryContext(
			export: ['id' => 'e1', 'name' => 'Weekly', 'description' => 'See attached.', 'createdBy' => 'alice'],
			artefacts: [new RenderResult(format: 'pdf', filename: 'Weekly.pdf', content: '%PDF')],
			now: new DateTimeImmutable('2026-11-16T09:00:00+01:00')
		);
	}//end setUp()

	/**
	 * One email to all three addresses with the PDF attached; the delivery succeeds with an id.
	 *
	 * @return void
	 */
	public function testEmailGoesToAllAddressesWithTheAttachment(): void {
		$message = $this->createMock(IMessage::class);
		$message->expects($this->once())->method('setTo')->with(['a@gemeente.nl', 'b@gemeente.nl', 'c@gemeente.nl']);
		$message->expects($this->once())->method('setSubject')->with('Weekly');
		$message->expects($this->once())->method('attach');
		$this->mailer->method('createMessage')->willReturn($message);
		$this->mailer->method('validateMailAddress')->willReturn(true);
		$this->mailer->expects($this->once())->method('createAttachment')->with('%PDF', 'Weekly.pdf', 'application/pdf')->willReturn($this->createMock(IAttachment::class));
		$this->mailer->expects($this->once())->method('send')->with($message)->willReturn([]);

		$deliveries = $this->dispatcher->dispatch(context: $this->context, recipients: ['r1' => ['channel' => 'email', 'channelConfig' => ['addresses' => ['a@gemeente.nl', 'b@gemeente.nl', 'c@gemeente.nl']]]]);

		$this->assertSame('success', $deliveries[0]['status']);
		$this->assertSame(1, $deliveries[0]['attemptCount']);
		$this->assertSame('r1', $deliveries[0]['recipientId']);
		$this->assertMatchesRegularExpression('/^mail-[0-9a-f]{32}$/', $deliveries[0]['providerMessageId']);
	}//end testEmailGoesToAllAddressesWithTheAttachment()

	/**
	 * An invalid address fails the attempt with the address, and it is scheduled for a retry.
	 *
	 * @return void
	 */
	public function testInvalidAddressFailsAndRetries(): void {
		$this->mailer->method('validateMailAddress')->willReturn(false);
		$this->mailer->expects($this->never())->method('send');

		$delivery = $this->dispatcher->deliver(delivery: ['recipientId' => 'r1', 'attemptCount' => 0], recipient: ['channel' => 'email', 'channelConfig' => ['to' => 'nope']], context: $this->context);

		$this->assertSame('retrying', $delivery['status']);
		$this->assertSame('Not a valid email address: nope', $delivery['error']);
		$this->assertNotNull($delivery['nextAttemptAt']);
	}//end testInvalidAddressFailsAndRetries()

	/**
	 * The webhook body carries the metadata and the file, signed with HMAC-SHA256 over the exact body.
	 *
	 * @return void
	 */
	public function testWebhookIsSignedOverTheBody(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(202);
		$response->method('getHeader')->with('X-Request-Id')->willReturn('req-7');
		$this->client->expects($this->once())->method('post')
			->willReturnCallback(function (string $url, array $options) use ($response): IResponse {
				$this->assertSame('https://hooks.example.org/in', $url);
				$this->assertSame('sha256=' . hash_hmac('sha256', $options['body'], 'k3y'), $options['headers']['X-LaunchPad-Signature']);
				$this->assertStringStartsWith('multipart/form-data; boundary=launchpad-', $options['headers']['Content-Type']);
				$this->assertStringContainsString('name="metadata"', $options['body']);
				$this->assertStringContainsString('"exportId":"e1"', $options['body']);
				$this->assertStringContainsString("filename=\"Weekly.pdf\"\r\nContent-Type: application/pdf\r\n\r\n%PDF\r\n", $options['body']);
				$this->assertFalse($options['http_errors']);
				return $response;
			});

		$delivery = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'webhook', 'channelConfig' => ['url' => 'https://hooks.example.org/in', 'signingSecret' => 'k3y']], context: $this->context);

		$this->assertSame('success', $delivery['status']);
		$this->assertSame('req-7', $delivery['providerMessageId']);
	}//end testWebhookIsSignedOverTheBody()

	/**
	 * A 503 is a failed attempt; a host that is not allow-listed, or plain http, is refused without a request.
	 *
	 * @return void
	 */
	public function testWebhookFailuresAndRefusals(): void {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(503);
		$this->client->expects($this->once())->method('post')->willReturn($response);

		$failed = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'webhook', 'channelConfig' => ['url' => 'https://hooks.example.org/in']], context: $this->context);
		$this->assertSame('HTTP 503', $failed['error']);
		$this->assertSame('retrying', $failed['status']);

		foreach (['https://evil.example.com/x', 'http://hooks.example.org/in'] as $url) {
			$refused = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'webhook', 'channelConfig' => ['url' => $url]], context: $this->context);
			$this->assertSame('The webhook URL is not an allowed https address', $refused['error']);
		}
	}//end testWebhookFailuresAndRefusals()

	/**
	 * The last allowed attempt that fails is failed-permanent.
	 *
	 * @return void
	 */
	public function testLastAttemptIsFailedPermanent(): void {
		$delivery = $this->dispatcher->deliver(
			delivery: ['attemptCount' => 2],
			recipient: ['channel' => 'fax', 'retryPolicy' => ['maxAttempts' => 3]],
			context: $this->context
		);

		$this->assertSame('failed-permanent', $delivery['status']);
		$this->assertSame(3, $delivery['attemptCount']);
		$this->assertSame('Unknown channel: fax', $delivery['error']);
	}//end testLastAttemptIsFailedPermanent()

	/**
	 * Files go into the creator's target folder, created when missing; a taken name gets a suffix unless overwrite is set.
	 *
	 * @return void
	 */
	public function testFilesWritesIntoTheCreatorsFolder(): void {
		$target = $this->createMock(Folder::class);
		$target->method('nodeExists')->willReturnCallback(static fn (string $n): bool => $n === 'Weekly.pdf');
		$existing = $this->createMock(File::class);
		$existing->method('getId')->willReturn(41);
		$target->method('get')->with('Weekly.pdf')->willReturn($existing);
		$written = $this->createMock(File::class);
		$written->method('getId')->willReturn(42);
		$target->expects($this->once())->method('newFile')->with('Weekly (2).pdf', '%PDF')->willReturn($written);

		$reports = $this->createMock(Folder::class);
		$reports->method('get')->with('Weekly')->willThrowException(new NotFoundException());
		$reports->method('newFolder')->with('Weekly')->willReturn($target);
		$home = $this->createMock(Folder::class);
		$home->method('get')->with('Reports')->willReturn($reports);
		$this->root->method('getUserFolder')->with('alice')->willReturn($home);

		$kept = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'nextcloudFiles', 'channelConfig' => ['targetPath' => '/Reports/Weekly']], context: $this->context);
		$this->assertSame('42', $kept['providerMessageId']);

		$existing->expects($this->once())->method('putContent')->with('%PDF');
		$over = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'nextcloudFiles', 'channelConfig' => ['targetPath' => '/Reports/Weekly', 'overwrite' => true]], context: $this->context);
		$this->assertSame('41', $over['providerMessageId']);
	}//end testFilesWritesIntoTheCreatorsFolder()

	/**
	 * Another user's Files and a path with .. are refused.
	 *
	 * @return void
	 */
	public function testFilesRefusesOtherUsersAndTraversal(): void {
		$this->root->method('getUserFolder')->willReturn($this->createMock(Folder::class));

		$other = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'nextcloudFiles', 'channelConfig' => ['user' => 'bob']], context: $this->context);
		$this->assertSame('An export may only write into the Files of its creator', $other['error']);

		$dots = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'nextcloudFiles', 'channelConfig' => ['targetPath' => '../x']], context: $this->context);
		$this->assertSame('The target path may not contain ..', $dots['error']);
	}//end testFilesRefusesOtherUsersAndTraversal()

	/**
	 * SFTP uploads to an allow-listed host and records the confirmed size; another host is refused.
	 *
	 * @return void
	 */
	public function testSftpUploadsToAllowedHostsOnly(): void {
		$this->sftp->expects($this->once())->method('upload')->with(['host' => 'sftp.cbs.nl'], $this->context->artefacts[0])->willReturn(4);

		$ok = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'sftp', 'channelConfig' => ['host' => 'sftp.cbs.nl']], context: $this->context);
		$this->assertSame('sftp-size Weekly.pdf:4', $ok['providerMessageId']);

		$refused = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'sftp', 'channelConfig' => ['host' => 'other.example']], context: $this->context);
		$this->assertSame('The SFTP host is not allowed', $refused['error']);
	}//end testSftpUploadsToAllowedHostsOnly()

	/**
	 * A recipient with a connector source goes through the vault, not through local credentials.
	 *
	 * @return void
	 */
	public function testVaultRouteIsUsedForASource(): void {
		$this->client->expects($this->never())->method('post');
		$this->vault->expects($this->once())->method('send')
			->with('src-1', $this->callback(static fn (array $r): bool => $r['endpoint'] === '/drop' && $r['method'] === 'POST' && str_contains($r['body'], '%PDF')))
			->willReturn('log-9');

		$delivery = $this->dispatcher->deliver(delivery: [], recipient: ['channel' => 'webhook', 'openconnectorSourceId' => 'src-1', 'channelConfig' => ['endpoint' => '/drop', 'url' => 'https://evil.example.com']], context: $this->context);
		$this->assertSame('log-9', $delivery['providerMessageId']);
	}//end testVaultRouteIsUsedForASource()

	/**
	 * After each attempt the callback gets the deliveries so far; with nothing rendered the attempt fails.
	 *
	 * @return void
	 */
	public function testAfterEachAndNothingRendered(): void {
		$seen    = [];
		$context = new DeliveryContext(export: $this->context->export, artefacts: [], now: $this->context->now);
		$this->dispatcher->dispatch(context: $context, recipients: ['a' => ['channel' => 'email'], 'b' => ['channel' => 'email']], afterEach: static function (array $d) use (&$seen): void {
			$seen[] = count($d);
		});

		$this->assertSame([1, 2], $seen);
		$this->assertSame('sha256=' . hash_hmac('sha256', 'x', 'y'), DeliveryDispatcher::signature(body: 'x', secret: 'y'));
	}//end testAfterEachAndNothingRendered()
}//end class
