<?php

/**
 * ScheduledExportServiceTest
 *
 * Scheduled-export CRUD (Task 3, Task 21): visible exports only, a computed
 * next run, validation, redacted secrets that survive an edit, soft delete
 * and enable/disable.
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
use InvalidArgumentException;
use OCA\LaunchPad\Service\RecurrenceResolver;
use OCA\LaunchPad\Service\ScheduledExportService;
use OCA\LaunchPad\Service\ScheduledExportStore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExportService
 * @uses   \OCA\LaunchPad\Service\RecurrenceResolver
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-21
 */
class ScheduledExportServiceTest extends TestCase {

	/**
	 * The OpenRegister adapter.
	 *
	 * @var ScheduledExportStore&MockObject
	 */
	private ScheduledExportStore&MockObject $store;

	/**
	 * The service under test.
	 *
	 * @var ScheduledExportService
	 */
	private ScheduledExportService $service;

	/**
	 * Friday 2026-11-13 12:00 in Amsterdam.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $friday;

	/**
	 * Build the service over a mocked store and the real recurrence resolver.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store   = $this->createMock(ScheduledExportStore::class);
		$this->service = new ScheduledExportService($this->store, new RecurrenceResolver());
		$this->friday  = new DateTimeImmutable('2026-11-13T12:00:00+01:00');
	}//end setUp()

	/**
	 * A valid weekly export with an SFTP recipient.
	 *
	 * @return array<string, mixed>
	 */
	private static function export(): array {
		return [
			'name' => 'Weekly bezwaren',
			'subjectType' => 'dashboard',
			'subjectId' => 'dash-1',
			'recurrence' => ['type' => 'weekly', 'daysOfWeek' => ['MO'], 'timeOfDay' => '09:00'],
			'renderTargets' => [['format' => 'pdf']],
			'recipients' => [
				['channel' => 'sftp', 'channelConfig' => ['host' => 'sftp.example.nl', 'password' => 's3cret']],
			],
		];
	}//end export()

	/**
	 * Creating an export computes its next run, defaults enabled and the time zone, and answers redacted.
	 *
	 * @return void
	 */
	public function testCreateComputesTheNextRunAndRedactsTheSecret(): void {
		$this->store->expects($this->once())->method('save')
			->with(
				'scheduled_export',
				$this->callback(function (array $data): bool {
					$this->assertSame('2026-11-16T09:00:00+01:00', $data['nextRunAt']);
					$this->assertTrue($data['enabled']);
					$this->assertSame('Europe/Amsterdam', $data['runTimezone']);
					$this->assertSame('s3cret', $data['recipients'][0]['channelConfig']['password']);
					return true;
				}),
				null
			)
			->willReturnCallback(static fn (string $schema, array $data): array => $data + ['id' => 'new-1']);

		$saved = $this->service->save(export: self::export(), id: null, now: $this->friday);

		$this->assertSame('new-1', $saved['id']);
		$this->assertSame('********', $saved['recipients'][0]['channelConfig']['password']);
		$this->assertSame('sftp.example.nl', $saved['recipients'][0]['channelConfig']['host']);
	}//end testCreateComputesTheNextRunAndRedactsTheSecret()

	/**
	 * A caller cannot set the next run or the last run status; both come from the server.
	 *
	 * @return void
	 */
	public function testTheCallerCannotSetTheRunTimestamps(): void {
		$this->store->method('save')->willReturnArgument(1);

		$saved = $this->service->save(
			export: self::export() + ['nextRunAt' => '2000-01-01T00:00:00+00:00', 'lastRunStatus' => 'success'],
			id: null,
			now: $this->friday
		);

		$this->assertSame('2026-11-16T09:00:00+01:00', $saved['nextRunAt']);
		$this->assertArrayNotHasKey('lastRunStatus', $saved);
	}//end testTheCallerCannotSetTheRunTimestamps()

	/**
	 * An export without the required parts or with a bad recurrence is refused with every problem, and nothing is saved.
	 *
	 * @return void
	 */
	public function testAnInvalidExportIsRefused(): void {
		$this->store->expects($this->never())->method('save');

		try {
			$this->service->save(
				export: ['name' => '', 'subjectType' => 'page', 'recurrence' => ['type' => 'hourly'], 'renderTargets' => [], 'recipients' => []],
				id: null,
				now: $this->friday
			);
			$this->fail('An invalid export was saved.');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('An export needs a name.', $e->getMessage());
			$this->assertStringContainsString('The subject must be a dashboard or a widget.', $e->getMessage());
			$this->assertStringContainsString('An export needs a subject.', $e->getMessage());
			$this->assertStringContainsString('at least one render target', $e->getMessage());
			$this->assertStringContainsString('at least one recipient', $e->getMessage());
			$this->assertStringContainsString('The recurrence type must be weekly, monthly, daily or cron.', $e->getMessage());
		}
	}//end testAnInvalidExportIsRefused()

	/**
	 * An edit that sends the placeholder back keeps the stored secret and the properties it left out.
	 *
	 * @return void
	 */
	public function testAnEditKeepsTheStoredSecretAndUnsentProperties(): void {
		$stored = self::export() + ['id' => 'e-1', 'lastRunStatus' => 'partial', 'description' => 'Voor de wethouder'];
		$this->store->method('find')->with('scheduled_export', 'e-1')->willReturn($stored);
		$this->store->expects($this->once())->method('save')
			->with(
				'scheduled_export',
				$this->callback(function (array $data): bool {
					$this->assertSame('s3cret', $data['recipients'][0]['channelConfig']['password']);
					$this->assertSame('Voor de wethouder', $data['description']);
					$this->assertSame('partial', $data['lastRunStatus']);
					$this->assertSame('Weekly v2', $data['name']);
					$this->assertArrayNotHasKey('id', $data);
					return true;
				}),
				'e-1'
			)
			->willReturnArgument(1);

		$this->service->save(
			export: [
				'name' => 'Weekly v2',
				'recipients' => [['channel' => 'sftp', 'channelConfig' => ['host' => 'sftp.example.nl', 'password' => '********']]],
			],
			id: 'e-1',
			now: $this->friday
		);
	}//end testAnEditKeepsTheStoredSecretAndUnsentProperties()

	/**
	 * A placeholder on a recipient whose channel changed has no secret to keep and is dropped, never stored literally.
	 *
	 * @return void
	 */
	public function testAPlaceholderOnAnotherChannelIsDropped(): void {
		$this->store->method('find')->willReturn(self::export() + ['id' => 'e-1']);
		$this->store->expects($this->once())->method('save')
			->with(
				'scheduled_export',
				$this->callback(function (array $data): bool {
					$this->assertArrayNotHasKey('signingSecret', $data['recipients'][0]['channelConfig']);
					return true;
				}),
				'e-1'
			)
			->willReturnArgument(1);

		$this->service->save(
			export: ['recipients' => [['channel' => 'webhook', 'channelConfig' => ['url' => 'https://example.nl/hook', 'signingSecret' => '********']]]],
			id: 'e-1',
			now: $this->friday
		);
	}//end testAPlaceholderOnAnotherChannelIsDropped()

	/**
	 * Updating an export the user cannot see is refused.
	 *
	 * @return void
	 */
	public function testUpdatingAnInvisibleExportIsRefused(): void {
		$this->store->method('find')->willReturn(null);
		$this->store->expects($this->never())->method('save');

		$this->expectException(InvalidArgumentException::class);
		$this->service->save(export: ['name' => 'x'], id: 'hidden', now: $this->friday);
	}//end testUpdatingAnInvisibleExportIsRefused()

	/**
	 * The list and the single read go through the user's access rules and answer secrets redacted.
	 *
	 * @return void
	 */
	public function testReadsAreVisibleOnlyAndRedacted(): void {
		$this->store->expects($this->once())->method('list')->with('scheduled_export')->willReturn([self::export()]);
		$this->store->method('find')->willReturnMap([
			['scheduled_export', 'e-1', true, self::export()],
			['scheduled_export', 'hidden', true, null],
		]);

		$this->assertSame('********', $this->service->findVisible(userId: 'anna')[0]['recipients'][0]['channelConfig']['password']);
		$this->assertSame('********', $this->service->findOne(id: 'e-1', userId: 'anna')['recipients'][0]['channelConfig']['password']);
		$this->assertNull($this->service->findOne(id: 'hidden', userId: 'anna'));
	}//end testReadsAreVisibleOnlyAndRedacted()

	/**
	 * The runner's read skips the user's access rules and keeps the secrets.
	 *
	 * @return void
	 */
	public function testTheRunnerReadKeepsTheSecrets(): void {
		$this->store->expects($this->once())->method('find')->with('scheduled_export', 'e-1', false)->willReturn(self::export());

		$this->assertSame('s3cret', $this->service->findForRun(id: 'e-1')['recipients'][0]['channelConfig']['password']);
	}//end testTheRunnerReadKeepsTheSecrets()

	/**
	 * Only enabled exports whose next run has passed are due.
	 *
	 * @return void
	 */
	public function testOnlyEnabledPastExportsAreDue(): void {
		$this->store->expects($this->once())->method('list')
			->with('scheduled_export', ['enabled' => true], false)
			->willReturn([
				['id' => 'past', 'enabled' => true, 'nextRunAt' => '2026-11-13T11:59:00+01:00'],
				['id' => 'now', 'enabled' => true, 'nextRunAt' => '2026-11-13T12:00:00+01:00'],
				['id' => 'future', 'enabled' => true, 'nextRunAt' => '2026-11-13T12:01:00+01:00'],
				['id' => 'off', 'enabled' => false, 'nextRunAt' => '2026-11-01T00:00:00+01:00'],
				['id' => 'never', 'enabled' => true, 'nextRunAt' => null],
			]);

		$due = array_column($this->service->findDue(now: $this->friday), 'id');

		$this->assertSame(['past', 'now'], $due);
	}//end testOnlyEnabledPastExportsAreDue()

	/**
	 * A delete of a visible export is a soft delete; an invisible one is not touched.
	 *
	 * @return void
	 */
	public function testDeleteOnlyTouchesAVisibleExport(): void {
		$this->store->method('find')->willReturnMap([
			['scheduled_export', 'e-1', true, self::export()],
			['scheduled_export', 'hidden', true, null],
		]);
		$this->store->expects($this->once())->method('delete')->with('scheduled_export', 'e-1')->willReturn(true);

		$this->assertTrue($this->service->delete(id: 'e-1', userId: 'anna'));
		$this->assertFalse($this->service->delete(id: 'hidden', userId: 'anna'));
	}//end testDeleteOnlyTouchesAVisibleExport()

	/**
	 * Disabling clears the next run; enabling computes it from now, not from the missed windows.
	 *
	 * @return void
	 */
	public function testEnableAndDisableSetTheNextRun(): void {
		$this->store->method('find')->willReturn(self::export() + ['id' => 'e-1', 'enabled' => true, 'nextRunAt' => '2026-01-05T09:00:00+01:00']);
		$saved = [];
		$this->store->method('save')->willReturnCallback(
			static function (string $schema, array $data) use (&$saved): array {
				$saved[] = $data;
				return $data;
			}
		);

		$off = $this->service->setEnabled(id: 'e-1', enabled: false, now: $this->friday);
		$on  = $this->service->setEnabled(id: 'e-1', enabled: true, now: $this->friday);

		$this->assertFalse($off['enabled']);
		$this->assertNull($off['nextRunAt']);
		$this->assertTrue($on['enabled']);
		$this->assertSame('2026-11-16T09:00:00+01:00', $on['nextRunAt']);
		$this->assertSame('********', $on['recipients'][0]['channelConfig']['password']);
		$this->assertSame('s3cret', $saved[1]['recipients'][0]['channelConfig']['password']);
	}//end testEnableAndDisableSetTheNextRun()

	/**
	 * Enabling an export the user cannot see answers null and saves nothing.
	 *
	 * @return void
	 */
	public function testEnablingAnInvisibleExportAnswersNull(): void {
		$this->store->method('find')->willReturn(null);
		$this->store->expects($this->never())->method('save');

		$this->assertNull($this->service->setEnabled(id: 'hidden', enabled: true, now: $this->friday));
	}//end testEnablingAnInvisibleExportAnswersNull()

	/**
	 * A fire records the last run and moves the next run past it, without the user's access rules.
	 *
	 * @return void
	 */
	public function testRecordFireMovesTheNextRun(): void {
		$this->store->expects($this->once())->method('save')
			->with('scheduled_export', $this->anything(), 'e-1', false)
			->willReturnArgument(1);

		$monday = new DateTimeImmutable('2026-11-16T09:00:00+01:00');
		$saved  = $this->service->recordFire(export: self::export() + ['id' => 'e-1', 'enabled' => true], status: 'partial', now: $monday);

		$this->assertSame('2026-11-16T09:00:00+01:00', $saved['lastRunAt']);
		$this->assertSame('partial', $saved['lastRunStatus']);
		$this->assertSame('2026-11-23T09:00:00+01:00', $saved['nextRunAt']);
	}//end testRecordFireMovesTheNextRun()
}//end class
