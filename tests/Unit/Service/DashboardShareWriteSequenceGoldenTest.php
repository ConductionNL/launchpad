<?php

/**
 * DashboardShareWriteSequenceGoldenTest
 *
 * Records the mapper, transaction and notification calls of every share
 * write path against a fixture, so a refactor of the write path cannot
 * change which rows are written or who is notified.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\ShareGrant;
use OCA\LaunchPad\Service\ShareNotifier;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Golden call sequences of addShare and replaceShares.
 */
class DashboardShareWriteSequenceGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/share-write-sequence.json';

	/**
	 * Recorded calls.
	 *
	 * @var list<string>
	 */
	private array $log = [];

	/**
	 * Existing shares keyed by "type:with" => level.
	 *
	 * @var array<string, string>
	 */
	private array $existing = [];

	/**
	 * Recipient whose insert fails, or ''.
	 *
	 * @var string
	 */
	private string $failOn = '';

	/**
	 * Build the service over recording collaborators.
	 *
	 * @return DashboardShareService
	 */
	private function service(): DashboardShareService {
		$dashboard = new Dashboard();
		$dashboard->setUserId('owner');
		$dashboard->setName('Team');
		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('find')->willReturn($dashboard);

		$shares = $this->createMock(DashboardShareMapper::class);
		$shares->method('findShare')->willReturnCallback(
			function (int $dashboardId, string $shareType, string $shareWith): ?DashboardShare {
				$this->log[] = "findShare $dashboardId $shareType:$shareWith";
				$level = ($this->existing["$shareType:$shareWith"] ?? null);
				if ($level === null) {
					return null;
				}

				$share = new DashboardShare();
				$share->setDashboardId($dashboardId);
				$share->setShareType($shareType);
				$share->setShareWith($shareWith);
				$share->setPermissionLevel($level);
				$share->setUpdatedAt('old');
				$share->resetUpdatedFields();
				return $share;
			}
		);
		$shares->method('insert')->willReturnCallback(
			function (DashboardShare $share): DashboardShare {
				if ($share->getShareWith() === $this->failOn) {
					throw new RuntimeException('insert failed');
				}

				$this->log[] = 'insert '.$this->describe(share: $share);
				return $share;
			}
		);
		$shares->method('update')->willReturnCallback(
			function (DashboardShare $share): DashboardShare {
				$this->log[] = 'update '.$this->describe(share: $share);
				return $share;
			}
		);
		$shares->method('deleteNotIn')->willReturnCallback(
			function (int $dashboardId, array $keepKeys): int {
				$this->log[] = "deleteNotIn $dashboardId ".implode(',', $keepKeys);
				return 0;
			}
		);
		$shares->method('findByDashboardId')->willReturnCallback(
			function (int $dashboardId): array {
				$this->log[] = "findByDashboardId $dashboardId";
				return [];
			}
		);

		$db = $this->createMock(IDBConnection::class);
		foreach (['beginTransaction', 'commit', 'rollBack'] as $method) {
			$db->method($method)->willReturnCallback(
				function () use ($method): bool {
					$this->log[] = $method;
					return true;
				}
			);
		}

		$notifier = $this->createMock(ShareNotifier::class);
		$notifier->method('shared')->willReturnCallback(
			function (DashboardShare $share, string $sharerUserId, Dashboard $dashboard): void {
				$this->log[] = "notify $sharerUserId ".$dashboard->getName().' '.$share->getShareWith().' '.$share->getPermissionLevel();
			}
		);

		return new DashboardShareService(
			shareMapper: $shares,
			dashboardMapper: $dashboards,
			db: $db,
			notifier: $notifier
		);
	}//end service()

	/**
	 * Describe a written share.
	 *
	 * @param DashboardShare $share The share.
	 *
	 * @return string
	 */
	private function describe(DashboardShare $share): string {
		$fields = array_keys($share->getUpdatedFields());
		$stamps = ($share->getCreatedAt() === $share->getUpdatedAt()) ? 'same-stamps' : 'kept-created';
		return $share->getDashboardId().' '.$share->getShareType().':'.$share->getShareWith().' '.$share->getPermissionLevel().' ['.implode(',', $fields).'] '.$stamps;
	}//end describe()

	/**
	 * Run one scenario and return its log and outcome.
	 *
	 * @param \Closure $run The scenario.
	 *
	 * @return array
	 */
	private function record(\Closure $run): array {
		$this->log = [];
		try {
			$result = $run($this->service());
			$outcome = is_array($result) ? 'returned list' : 'returned '.$result->getShareWith().' '.$result->getPermissionLevel();
		} catch (Throwable $e) {
			$outcome = 'threw '.$e::class.': '.$e->getMessage();
		}

		return ['calls' => $this->log, 'outcome' => $outcome];
	}//end record()

	/**
	 * Every write path matches the recorded fixture.
	 *
	 * @return void
	 */
	public function testShareWritesMatchTheRecordedFixture(): void {
		$entries = [
			['shareType' => 'user', 'shareWith' => 'new', 'permissionLevel' => 'full'],
			['shareType' => 'user', 'shareWith' => 'up', 'permissionLevel' => 'full'],
			['shareType' => 'user', 'shareWith' => 'down', 'permissionLevel' => 'view_only'],
			['shareType' => 'group', 'shareWith' => 'same', 'permissionLevel' => 'add_only'],
			['shareType' => 'user', 'shareWith' => 'mid', 'permissionLevel' => 'add_only'],
		];
		$this->existing = ['user:up' => 'view_only', 'user:down' => 'full', 'group:same' => 'add_only', 'user:mid' => 'view_only'];

		$actual = [];
		$actual['replace mixed'] = $this->record(fn (DashboardShareService $s) => $s->replaceShares(dashboardId: 5, shares: $entries, userId: 'owner'));
		$actual['replace empty'] = $this->record(fn (DashboardShareService $s) => $s->replaceShares(dashboardId: 5, shares: [], userId: 'owner'));
		$actual['replace not owner'] = $this->record(fn (DashboardShareService $s) => $s->replaceShares(dashboardId: 5, shares: $entries, userId: 'mallory'));
		$actual['replace invalid entry'] = $this->record(
			fn (DashboardShareService $s) => $s->replaceShares(dashboardId: 5, shares: [$entries[0], ['shareType' => 'robot', 'shareWith' => 'x', 'permissionLevel' => 'full']], userId: 'owner')
		);
		$actual['replace unknown level'] = $this->record(
			fn (DashboardShareService $s) => $s->replaceShares(dashboardId: 5, shares: [['shareType' => 'user', 'shareWith' => 'up', 'permissionLevel' => 'root']], userId: 'owner')
		);
		$this->failOn = 'mid';
		$this->existing = ['user:up' => 'view_only', 'user:down' => 'full', 'group:same' => 'add_only'];
		$actual['replace failing insert'] = $this->record(
			fn (DashboardShareService $s) => $s->replaceShares(dashboardId: 5, shares: [$entries[0], $entries[1], $entries[4]], userId: 'owner')
		);
		$this->failOn = '';
		foreach ($entries as $entry) {
			$grant = new ShareGrant(shareType: $entry['shareType'], shareWith: $entry['shareWith'], permissionLevel: $entry['permissionLevel']);
			$actual['add '.$entry['shareWith']] = $this->record(fn (DashboardShareService $s) => $s->addShare(dashboardId: 9, grant: $grant, callerId: 'owner'));
		}

		$actual['add not owner'] = $this->record(
			fn (DashboardShareService $s) => $s->addShare(dashboardId: 9, grant: new ShareGrant(shareType: 'user', shareWith: 'new', permissionLevel: 'full'), callerId: 'mallory')
		);

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		foreach ($actual as $case => $record) {
			$this->assertSame($expected[$case] ?? null, $record, $case);
		}

		$this->assertSame(array_keys($expected), array_keys($actual));
	}//end testShareWritesMatchTheRecordedFixture()
}//end class
