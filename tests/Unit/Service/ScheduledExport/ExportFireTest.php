<?php

/**
 * ExportFireTest
 *
 * One fire of a scheduled export (tasks.md task 25, REQ-SCH-002/004/006/008/009):
 * overlap and disabled-creator skips, one render per distinct filter
 * context, a render failure failing only its recipients, the run-row
 * written after every attempt, nextRunAt moved on only by a scheduled fire,
 * due retries with the stored artefacts, and a manual retry.
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
use OCA\LaunchPad\Service\ScheduledExport\AuditLog;
use OCA\LaunchPad\Service\ScheduledExport\DeliveryContext;
use OCA\LaunchPad\Service\ScheduledExport\DeliveryDispatcher;
use OCA\LaunchPad\Service\ScheduledExport\ExportFire;
use OCA\LaunchPad\Service\ScheduledExport\ExportNotifier;
use OCA\LaunchPad\Service\ScheduledExport\FireSupport;
use OCA\LaunchPad\Service\ScheduledExport\FireTrigger;
use OCA\LaunchPad\Service\ScheduledExport\InvalidFilterContextException;
use OCA\LaunchPad\Service\ScheduledExport\RenderPipeline;
use OCA\LaunchPad\Service\ScheduledExport\RenderResult;
use OCA\LaunchPad\Service\ScheduledExport\ScheduledExportService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\ExportFire
 * @covers \OCA\LaunchPad\Service\ScheduledExport\FireSupport
 * @uses   \OCA\LaunchPad\Service\RetryHandler
 * @uses   \OCA\LaunchPad\Service\ScheduledExport\FireTrigger
 * @uses   \OCA\LaunchPad\Service\ScheduledExport\RenderResult
 * @uses   \OCA\LaunchPad\Service\ScheduledExport\DeliveryContext
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-25
 */
class ExportFireTest extends TestCase {

	private ScheduledExportService&MockObject $exports;

	private AuditLog&MockObject $log;

	private RenderPipeline&MockObject $render;

	private DeliveryDispatcher&MockObject $dispatch;

	private ExportNotifier&MockObject $notifier;

	private IUserManager&MockObject $users;

	private IRootFolder&MockObject $root;

	private ExportFire $fire;

	/**
	 * Alice is enabled; there are no earlier runs; it is Monday 09:00.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->exports  = $this->createMock(ScheduledExportService::class);
		$this->log      = $this->createMock(AuditLog::class);
		$this->render   = $this->createMock(RenderPipeline::class);
		$this->dispatch = $this->createMock(DeliveryDispatcher::class);
		$this->notifier = $this->createMock(ExportNotifier::class);
		$this->users    = $this->createMock(IUserManager::class);
		$this->root     = $this->createMock(IRootFolder::class);
		$time           = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable('2026-11-16T09:00:00+01:00'));

		$alice = $this->createMock(IUser::class);
		$alice->method('isEnabled')->willReturn(true);
		$this->users->method('get')->willReturnCallback(static fn (string $uid): ?IUser => $uid === 'alice' ? $alice : null);
		$this->log->method('start')->willReturn(['id' => 'r1', 'exportId' => 'e1', 'startedAt' => '2026-11-16T09:00:00+01:00', 'status' => 'running']);
		$this->log->method('record')->willReturnCallback(static fn (array $run, array $a, array $d): array => array_merge($run, ['renderArtefacts' => $a, 'deliveries' => $d, 'status' => (new RetryHandler())->rollUp(deliveries: $d)]));
		$this->exports->method('nextRunAfter')->willReturn('2026-11-23T09:00:00+01:00');

		$this->fire = new ExportFire($this->exports, $this->log, $this->render, $this->dispatch, new FireSupport(new RetryHandler(), $this->notifier, $this->users, $this->root, $time));
	}//end setUp()

	/**
	 * An export with three recipients: two share a filter context, one has another.
	 *
	 * @return array<string, mixed>
	 */
	private function export(): array {
		return ['id' => 'e1', 'name' => 'Weekly', 'createdBy' => 'alice', 'recipients' => [
			['channel' => 'email', 'filterContext' => ['gemeenteCode' => 'GM0355']],
			['id' => 'sftp-1', 'channel' => 'sftp', 'filterContext' => ['gemeenteCode' => 'GM0355']],
			['channel' => 'webhook', 'filterContext' => ['gemeenteCode' => 'GM0344']],
			['channel' => 'email', 'enabled' => false],
		]];
	}//end export()

	/**
	 * Identical contexts share one render, distinct ones get their own; every artefact and delivery lands on the row.
	 *
	 * @return void
	 */
	public function testOneRenderPerDistinctFilterContext(): void {
		$this->exports->method('forDispatch')->willReturn($this->export());
		$contexts = [];
		$this->render->expects($this->exactly(2))->method('render')->willReturnCallback(static function (array $export, array $context) use (&$contexts): array {
			$contexts[] = $context;
			return [new RenderResult(format: 'pdf', filename: 'w.pdf', content: $context['gemeenteCode'], storagePath: '/alice/files/w.pdf')];
		});
		$batches = [];
		$this->dispatch->expects($this->exactly(2))->method('dispatch')->willReturnCallback(static function (DeliveryContext $c, array $recipients, callable $after) use (&$batches): array {
			$batches[] = [array_keys($recipients), $c->artefacts[0]->content];
			$out = array_map(static fn ($id): array => ['recipientId' => (string)$id, 'status' => 'success'], array_keys($recipients));
			$after($out);
			return $out;
		});
		$this->log->expects($this->exactly(2))->method('update');
		$this->exports->expects($this->once())->method('recordFire')->with($this->anything(), ['lastRunAt' => '2026-11-16T09:00:00+01:00', 'lastRunStatus' => 'success', 'nextRunAt' => '2026-11-23T09:00:00+01:00']);

		$run = $this->fire->fire(export: $this->export(), by: FireTrigger::schedule());

		$this->assertSame([['gemeenteCode' => 'GM0355'], ['gemeenteCode' => 'GM0344']], $contexts);
		$this->assertSame([[[0, 'sftp-1'], 'GM0355'], [[2], 'GM0344']], $batches);
		$this->assertCount(2, $run['renderArtefacts']);
		$this->assertSame([[0], [0], [1]], array_column($run['deliveries'], 'artefactIndexes'));
		$this->assertSame('success', $run['status']);
	}//end testOneRenderPerDistinctFilterContext()

	/**
	 * An unknown filter field fails that context's recipient with the reason; the others are delivered and an admin hears about it.
	 *
	 * @return void
	 */
	public function testInvalidFilterContextFailsOnlyItsRecipients(): void {
		$this->exports->method('forDispatch')->willReturn($this->export());
		$this->render->method('render')->willReturnCallback(static function (array $e, array $context): array {
			if ($context['gemeenteCode'] === 'GM0344') {
				throw new InvalidFilterContextException('Unknown filter field: gemeenteCode');
			}

			return [new RenderResult(format: 'csv', filename: 'w.csv', content: 'x')];
		});
		$this->dispatch->method('dispatch')->willReturnCallback(static fn (DeliveryContext $c, array $r): array => array_map(static fn ($id): array => ['recipientId' => (string)$id, 'status' => 'success'], array_keys($r)));
		$this->notifier->expects($this->once())->method('failed')->with($this->anything(), 'webhook: Unknown filter field: gemeenteCode');

		$run = $this->fire->fire(export: $this->export(), by: FireTrigger::manual(userId: 'alice'));

		$this->assertSame('partial', $run['status']);
		$this->assertSame(['recipientId' => '2', 'channel' => 'webhook', 'attemptCount' => 0, 'status' => 'failed-permanent', 'error' => 'Unknown filter field: gemeenteCode'], $run['deliveries'][2]);
	}//end testInvalidFilterContextFailsOnlyItsRecipients()

	/**
	 * A manual run does not move nextRunAt.
	 *
	 * @return void
	 */
	public function testManualRunKeepsTheSchedule(): void {
		$this->exports->method('forDispatch')->willReturn(['id' => 'e1', 'createdBy' => 'alice', 'recipients' => []]);
		$this->exports->expects($this->never())->method('nextRunAfter');
		$this->exports->expects($this->once())->method('recordFire')->with($this->anything(), ['lastRunAt' => '2026-11-16T09:00:00+01:00', 'lastRunStatus' => 'failed']);

		$this->fire->fire(export: ['id' => 'e1', 'createdBy' => 'alice'], by: FireTrigger::manual(userId: 'alice'));
	}//end testManualRunKeepsTheSchedule()

	/**
	 * A previous run still rendering skips the fire with a partial row; one only waiting for retries does not.
	 *
	 * @return void
	 */
	public function testOverlapIsSkipped(): void {
		$this->log->method('runsOf')->willReturnOnConsecutiveCalls(
			[['id' => 'r0', 'status' => 'running', 'deliveries' => []]],
			[['id' => 'r0', 'status' => 'running', 'deliveries' => [['status' => 'retrying']]]]
		);
		$this->log->expects($this->once())->method('skipped')->with($this->anything(), $this->anything(), 'partial', ExportFire::REASON_OVERLAP)->willReturn(['status' => 'partial', 'startedAt' => 'T']);
		$this->log->expects($this->once())->method('start');
		$this->render->expects($this->never())->method('render');

		$this->assertSame('partial', $this->fire->fire(export: ['id' => 'e1', 'createdBy' => 'alice'], by: FireTrigger::schedule())['status']);
		$this->fire->fire(export: ['id' => 'e1', 'createdBy' => 'alice'], by: FireTrigger::schedule());
	}//end testOverlapIsSkipped()

	/**
	 * A disabled or deleted creator fails the fire without rendering, notifies admins, and still moves the schedule on.
	 *
	 * @return void
	 */
	public function testDisabledCreatorIsSkipped(): void {
		$this->log->expects($this->once())->method('skipped')->with($this->anything(), $this->anything(), 'failed', ExportFire::REASON_CREATOR_DISABLED)->willReturn(['status' => 'failed', 'startedAt' => 'T']);
		$this->notifier->expects($this->once())->method('failed')->with($this->callback(static fn (array $e): bool => $e['creatorEnabled'] === false), ExportFire::REASON_CREATOR_DISABLED);
		$this->exports->expects($this->once())->method('recordFire')->with($this->anything(), ['lastRunAt' => 'T', 'lastRunStatus' => 'failed', 'nextRunAt' => '2026-11-23T09:00:00+01:00']);
		$this->render->expects($this->never())->method('render');

		$this->fire->fire(export: ['id' => 'e1', 'createdBy' => 'mallory'], by: FireTrigger::schedule());
	}//end testDisabledCreatorIsSkipped()

	/**
	 * A due retry resends the delivery's stored artefacts; deliveries not due are left alone.
	 *
	 * @return void
	 */
	public function testRetryDueResendsStoredArtefacts(): void {
		$this->log->method('allRuns')->willReturn([
			['id' => 'r1', 'exportId' => 'e1', 'status' => 'running', 'renderArtefacts' => [['format' => 'pdf', 'storagePath' => '/alice/files/w.pdf']], 'deliveries' => [
				['recipientId' => '0', 'channel' => 'webhook', 'status' => 'retrying', 'attemptCount' => 1, 'nextAttemptAt' => '2026-11-16T08:59:00+01:00', 'artefactIndexes' => [0]],
				['recipientId' => '1', 'channel' => 'email', 'status' => 'retrying', 'attemptCount' => 1, 'nextAttemptAt' => '2026-11-16T10:00:00+01:00'],
			]],
			['id' => 'r2', 'exportId' => 'e1', 'status' => 'success', 'deliveries' => [['status' => 'success']]],
		]);
		$this->exports->method('forDispatch')->willReturn(['id' => 'e1', 'recipients' => [['channel' => 'webhook', 'channelConfig' => ['signingSecret' => 'k']]]]);
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('w.pdf');
		$file->method('getContent')->willReturn('%PDF');
		$this->root->method('get')->with('/alice/files/w.pdf')->willReturn($file);
		$this->dispatch->expects($this->once())->method('deliver')
			->willReturnCallback(function (array $delivery, array $recipient, DeliveryContext $context): array {
				$this->assertSame('k', $recipient['channelConfig']['signingSecret']);
				$this->assertSame('%PDF', $context->artefacts[0]->content);
				return ['status' => 'success', 'attemptCount' => 2] + $delivery;
			});

		$this->assertSame(1, $this->fire->retryDue());
	}//end testRetryDueResendsStoredArtefacts()

	/**
	 * A manual retry rearms only the failed-permanent deliveries and records the settled status on the export.
	 *
	 * @return void
	 */
	public function testRetryRunRearmsFailedDeliveries(): void {
		$this->exports->method('forDispatch')->willReturn(['id' => 'e1', 'recipients' => [['channel' => 'email'], ['channel' => 'webhook']]]);
		$this->dispatch->expects($this->once())->method('deliver')
			->with($this->callback(static fn (array $d): bool => $d['status'] === 'retrying' && $d['attemptCount'] === 0))
			->willReturnCallback(static fn (array $d): array => ['status' => 'success', 'attemptCount' => 1] + $d);
		$this->exports->expects($this->once())->method('recordFire')->with($this->anything(), ['lastRunStatus' => 'success']);

		$run = $this->fire->retryRun(run: ['id' => 'r1', 'exportId' => 'e1', 'deliveries' => [
			['recipientId' => '0', 'status' => 'success'],
			['recipientId' => '1', 'status' => 'failed-permanent', 'attemptCount' => 5],
		]]);

		$this->assertSame('success', $run['status']);
		$this->assertSame(['status' => 'success', 'attemptCount' => 1], ['status' => $run['deliveries'][1]['status'], 'attemptCount' => $run['deliveries'][1]['attemptCount']]);
		$this->assertSame(['id' => 'r9', 'deliveries' => [['status' => 'success']]], $this->fire->retryRun(run: ['id' => 'r9', 'deliveries' => [['status' => 'success']]]));
	}//end testRetryRunRearmsFailedDeliveries()
}//end class
