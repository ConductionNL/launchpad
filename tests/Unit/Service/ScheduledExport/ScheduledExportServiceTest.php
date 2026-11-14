<?php

/**
 * ScheduledExportServiceTest
 *
 * CRUD of scheduled exports (tasks.md task 21): only the owner or an
 * administrator sees an export, validation refuses incomplete exports,
 * `nextRunAt` is computed on create and on a recurrence change, the runner
 * fields cannot be set by a user, soft delete and enable/disable, and
 * channel secrets are encrypted at rest and redacted on read (REQ-SCH-010).
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
use DateTimeZone;
use InvalidArgumentException;
use OCA\LaunchPad\Service\RecurrenceResolver;
use OCA\LaunchPad\Service\ScheduledExport\ExportObjectStore;
use OCA\LaunchPad\Service\ScheduledExport\ScheduledExportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\Security\ICrypto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\ScheduledExportService
 * @uses   \OCA\LaunchPad\Service\RecurrenceResolver
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-21
 */
class ScheduledExportServiceTest extends TestCase {

	private ExportObjectStore&MockObject $store;

	private IGroupManager&MockObject $groups;

	private ScheduledExportService $service;

	/**
	 * Friday 13 November 2026, 12:00 in Amsterdam; nobody is an administrator
	 * unless a test says so; encryption is reversible base64.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->store  = $this->createMock(ExportObjectStore::class);
		$this->groups = $this->createMock(IGroupManager::class);
		$this->groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin');

		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(static fn (string $v): string => base64_encode($v));
		$crypto->method('decrypt')->willReturnCallback(static fn (string $v): string => (string)base64_decode($v));

		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable('2026-11-13 12:00:00', new DateTimeZone('Europe/Amsterdam')));

		$this->service = new ScheduledExportService($this->store, new RecurrenceResolver(), $crypto, $this->groups, $time);
	}//end setUp()

	/**
	 * A valid weekly export with one webhook recipient carrying a secret.
	 *
	 * @return array<string, mixed>
	 */
	private function export(): array {
		return [
			'name' => 'Weekly bezwaren',
			'subjectType' => 'dashboard',
			'subjectId' => '0b9c4d1e-0000-4000-8000-000000000001',
			'recurrence' => ['type' => 'weekly', 'daysOfWeek' => ['MO'], 'timeOfDay' => '09:00'],
			'renderTargets' => [['format' => 'pdf']],
			'recipients' => [['channel' => 'webhook', 'channelConfig' => ['url' => 'https://example.org/h', 'signingSecret' => 's3cret']]],
		];
	}//end export()

	/**
	 * A create stamps the creator, encrypts the secret, schedules the next Monday 09:00 and redacts on the way out.
	 *
	 * @return void
	 */
	public function testCreateSchedulesEncryptsAndRedacts(): void {
		$saved = null;
		$this->store->expects($this->once())->method('save')
			->with(ExportObjectStore::SCHEMA_EXPORT, $this->anything(), true)
			->willReturnCallback(static function (string $schema, array $data) use (&$saved): array {
				$saved = $data;
				return $data + ['id' => 'e1'];
			});

		$result = $this->service->save(export: $this->export() + ['lastRunStatus' => 'success', 'createdBy' => 'mallory'], userId: 'alice');

		$this->assertSame('alice', $saved['createdBy']);
		$this->assertArrayNotHasKey('lastRunStatus', $saved);
		$this->assertSame('Europe/Amsterdam', $saved['runTimezone']);
		$this->assertTrue($saved['enabled']);
		$this->assertSame('2026-11-16T09:00:00+01:00', $saved['nextRunAt']);
		$this->assertSame('enc:' . base64_encode('s3cret'), $saved['recipients'][0]['channelConfig']['signingSecret']);
		$this->assertSame('********', $result['recipients'][0]['channelConfig']['signingSecret']);
		$this->assertSame('https://example.org/h', $result['recipients'][0]['channelConfig']['url']);
	}//end testCreateSchedulesEncryptsAndRedacts()

	/**
	 * An invalid export is refused with every reason, and nothing is written.
	 *
	 * @return void
	 */
	public function testInvalidExportIsRefused(): void {
		$this->store->expects($this->never())->method('save');
		$export = ['name' => '', 'subjectType' => 'page', 'recurrence' => ['type' => 'hourly'], 'renderTargets' => [['format' => 'gif']], 'recipients' => []];

		try {
			$this->service->save(export: $export, userId: 'alice');
			$this->fail('expected a refusal');
		} catch (InvalidArgumentException $e) {
			$this->assertStringContainsString('The name is required', $e->getMessage());
			$this->assertStringContainsString('subject type must be dashboard or widget', $e->getMessage());
			$this->assertStringContainsString('The subject is required', $e->getMessage());
			$this->assertStringContainsString('Every render target needs a format', $e->getMessage());
			$this->assertStringContainsString('At least one recipient is required', $e->getMessage());
			$this->assertStringContainsString('recurrence type must be weekly, monthly, daily or cron', $e->getMessage());
		}
	}//end testInvalidExportIsRefused()

	/**
	 * A time zone that is not IANA is refused.
	 *
	 * @return void
	 */
	public function testUnknownTimezoneIsRefused(): void {
		$this->assertSame(['The time zone is not a valid IANA time zone.'], $this->service->validate(export: $this->export() + ['runTimezone' => 'Mars/Olympus']));
	}//end testUnknownTimezoneIsRefused()

	/**
	 * Another user's export is not found, for reading, saving, deleting and toggling alike.
	 *
	 * @return void
	 */
	public function testAnotherUsersExportIsNotFound(): void {
		$this->store->method('find')->willReturn($this->export() + ['id' => 'e1', 'owner' => 'alice', 'createdBy' => 'alice']);
		$this->store->expects($this->never())->method('save');
		$this->store->expects($this->never())->method('delete');

		foreach ([
			fn () => $this->service->findOne(id: 'e1', userId: 'bob'),
			fn () => $this->service->save(export: ['id' => 'e1', 'name' => 'x'], userId: 'bob'),
			fn () => $this->service->delete(id: 'e1', userId: 'bob'),
			fn () => $this->service->setEnabled(id: 'e1', enabled: false, userId: 'bob'),
		] as $call) {
			try {
				$call();
				$this->fail('expected not found');
			} catch (DoesNotExistException $e) {
				$this->assertSame('Scheduled export not found', $e->getMessage());
			}
		}
	}//end testAnotherUsersExportIsNotFound()

	/**
	 * A missing export is not found either.
	 *
	 * @return void
	 */
	public function testMissingExportIsNotFound(): void {
		$this->store->method('find')->willReturn(null);
		$this->expectException(DoesNotExistException::class);
		$this->service->findOne(id: 'nope', userId: 'admin');
	}//end testMissingExportIsNotFound()

	/**
	 * A user's list holds only their exports; an administrator's is unfiltered.
	 *
	 * @return void
	 */
	public function testFindVisibleFiltersByOwnerUnlessAdmin(): void {
		$rows = [
			$this->export() + ['id' => 'e1', 'owner' => 'alice'],
			$this->export() + ['id' => 'e2', 'owner' => 'bob'],
		];
		$this->store->expects($this->exactly(2))->method('findAll')
			->willReturnCallback(function (string $schema, array $filters) use ($rows): array {
				$this->assertSame(ExportObjectStore::SCHEMA_EXPORT, $schema);
				return $rows;
			});

		$alice = $this->service->findVisible(userId: 'alice');
		$this->assertSame(['e1'], array_column($alice, 'id'));
		$this->assertSame('********', $alice[0]['recipients'][0]['channelConfig']['signingSecret']);

		$this->assertSame(['e1', 'e2'], array_column($this->service->findVisible(userId: 'admin'), 'id'));
	}//end testFindVisibleFiltersByOwnerUnlessAdmin()

	/**
	 * An update merges onto the stored export, keeps a redacted secret and does not reschedule an unchanged recurrence.
	 *
	 * @return void
	 */
	public function testUpdateKeepsRedactedSecretAndSchedule(): void {
		$stored = $this->export();
		$stored['recipients'][0]['channelConfig']['signingSecret'] = 'enc:' . base64_encode('s3cret');
		$stored += ['id' => 'e1', 'owner' => 'alice', 'createdBy' => 'alice', 'nextRunAt' => '2026-11-16T09:00:00+01:00', 'runTimezone' => 'Europe/Amsterdam'];
		$this->store->method('find')->willReturn($stored);

		$saved = null;
		$this->store->method('save')->willReturnCallback(static function (string $schema, array $data) use (&$saved): array {
			$saved = $data;
			return $data;
		});

		$update = ['id' => 'e1', 'name' => 'Renamed', 'recipients' => [['channel' => 'webhook', 'channelConfig' => ['url' => 'https://example.org/h', 'signingSecret' => '********']]]];
		$this->service->save(export: $update, userId: 'alice');

		$this->assertSame('Renamed', $saved['name']);
		$this->assertSame('dashboard', $saved['subjectType']);
		$this->assertSame('enc:' . base64_encode('s3cret'), $saved['recipients'][0]['channelConfig']['signingSecret']);
		$this->assertSame('2026-11-16T09:00:00+01:00', $saved['nextRunAt']);
	}//end testUpdateKeepsRedactedSecretAndSchedule()

	/**
	 * A changed recurrence is rescheduled from now.
	 *
	 * @return void
	 */
	public function testChangedRecurrenceIsRescheduled(): void {
		$this->store->method('find')->willReturn($this->export() + ['id' => 'e1', 'owner' => 'alice', 'nextRunAt' => '2026-11-16T09:00:00+01:00']);
		$this->store->method('save')->willReturnArgument(1);

		$result = $this->service->save(export: ['id' => 'e1', 'recurrence' => ['type' => 'daily', 'timeOfDay' => '17:30']], userId: 'alice');
		$this->assertSame('2026-11-13T17:30:00+01:00', $result['nextRunAt']);
	}//end testChangedRecurrenceIsRescheduled()

	/**
	 * Disabling saves enabled=false; deleting soft-deletes through the store.
	 *
	 * @return void
	 */
	public function testSetEnabledAndDelete(): void {
		$this->store->method('find')->willReturn($this->export() + ['id' => 'e1', 'owner' => 'alice', 'nextRunAt' => '2026-11-16T09:00:00+01:00']);
		$this->store->method('save')->willReturnArgument(1);
		$this->store->expects($this->once())->method('delete')->with('e1', true)->willReturn(true);

		$this->assertFalse($this->service->setEnabled(id: 'e1', enabled: false, userId: 'alice')['enabled']);
		$this->service->delete(id: 'e1', userId: 'alice');
	}//end testSetEnabledAndDelete()

	/**
	 * The dispatcher gets the decrypted secret; a plain stored value passes through.
	 *
	 * @return void
	 */
	public function testForDispatchRevealsSecrets(): void {
		$stored = $this->export();
		$stored['recipients'][0]['channelConfig']['signingSecret'] = 'enc:' . base64_encode('s3cret');
		$stored['recipients'][] = ['channel' => 'sftp', 'channelConfig' => ['password' => 'plain']];
		$this->store->method('find')->willReturnOnConsecutiveCalls($stored, null);

		$export = $this->service->forDispatch(id: 'e1');
		$this->assertSame('s3cret', $export['recipients'][0]['channelConfig']['signingSecret']);
		$this->assertSame('plain', $export['recipients'][1]['channelConfig']['password']);
		$this->assertNull($this->service->forDispatch(id: 'e2'));
	}//end testForDispatchRevealsSecrets()

	/**
	 * Only enabled exports whose next run has passed are due.
	 *
	 * @return void
	 */
	public function testDueSelectsPassedEnabledExports(): void {
		$this->store->method('findAll')->willReturn([
			['id' => 'past', 'enabled' => true, 'nextRunAt' => '2026-11-13T11:00:00+01:00'],
			['id' => 'future', 'enabled' => true, 'nextRunAt' => '2026-11-13T13:00:00+01:00'],
			['id' => 'off', 'enabled' => false, 'nextRunAt' => '2026-11-13T11:00:00+01:00'],
			['id' => 'never'],
		]);

		$due = $this->service->due(now: new DateTimeImmutable('2026-11-13T12:00:00+01:00'));
		$this->assertSame(['past'], array_column($due, 'id'));
	}//end testDueSelectsPassedEnabledExports()

	/**
	 * Recording a fire writes only the runner fields onto the stored export.
	 *
	 * @return void
	 */
	public function testRecordFireWritesOnlyRunnerFields(): void {
		$this->store->method('find')->willReturn(['id' => 'e1', 'name' => 'Stored']);
		$this->store->expects($this->once())->method('save')
			->with(ExportObjectStore::SCHEMA_EXPORT, ['id' => 'e1', 'name' => 'Stored', 'lastRunAt' => 'T', 'lastRunStatus' => 'success'], true)
			->willReturnArgument(1);

		$this->service->recordFire(export: ['id' => 'e1'], fields: ['lastRunAt' => 'T', 'lastRunStatus' => 'success', 'name' => 'Hacked']);
	}//end testRecordFireWritesOnlyRunnerFields()
}//end class
