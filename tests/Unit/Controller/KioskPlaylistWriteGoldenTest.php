<?php

/**
 * Kiosk playlist write golden test
 *
 * Pins what creating and updating a kiosk playlist does for each caller:
 * the response, the stored row and every authorization lookup, with the
 * share rule answering yes only for the dashboard owner or the admin,
 * against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\KioskController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\KioskStores;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Playlist writes, caller by caller.
 *
 * @covers \OCA\LaunchPad\Controller\KioskController
 * @covers \OCA\LaunchPad\Service\KioskService
 * @uses   \OCA\LaunchPad\Service\KioskStores
 * @uses   \OCA\LaunchPad\Service\KioskPlaylistDraft
 * @uses   \OCA\LaunchPad\Db\KioskPlaylist
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Exception\PlaylistNotFoundException
 */
class KioskPlaylistWriteGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/kiosk-playlist-write.json';

	/**
	 * Every write matches the recorded fixture.
	 *
	 * @return void
	 */
	public function testWritesMatchTheRecordedFixture(): void {
		$own = [['dashboardUuid' => 'd-alice', 'dwellSeconds' => 30]];
		$entrySets = [
			'own' => $own,
			'own clamped' => [
				['dashboardUuid' => 'd-alice', 'dwellSeconds' => 1],
				['dashboardUuid' => 'd-alice', 'dwellSeconds' => 99999],
				['dashboardUuid' => 'd-alice'],
			],
			'foreign' => [['dashboardUuid' => 'd-alice'], ['dashboardUuid' => 'd-bob']],
			'missing' => [['dashboardUuid' => 'd-missing']],
			'invalid' => [['dwellSeconds' => 5]],
			'none' => null,
		];

		$actual = [];
		foreach (['alice', 'bob', 'admin'] as $caller) {
			foreach ($entrySets as $setName => $entries) {
				foreach ([null, 0, 45, 999999] as $refresh) {
					$actual["create $caller $setName $refresh"] = $this->write(caller: $caller, call: ['create', null, 'Lobby', $entries, $refresh]);
				}

				foreach ([7, 8] as $id) {
					$actual["update $caller $setName playlist $id"] = $this->write(caller: $caller, call: ['update', $id, 'Hall', $entries, 60]);
				}
			}
		}

		$actual['update without name'] = $this->write(caller: 'alice', call: ['update', 7, null, $own, null]);
		foreach ([0, 5, 999999] as $refresh) {
			$actual["update refresh $refresh"] = $this->write(caller: 'alice', call: ['update', 7, 'Hall', $own, $refresh]);
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
			$this->markTestIncomplete(message: 'Fixture recorded.');
		}

		$this->assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: $actual);
	}

	/**
	 * Run one controller write for a session caller and record it.
	 *
	 * @param string            $caller The session user.
	 * @param array<int, mixed> $call   Action, playlist id, name, entries, refresh.
	 *
	 * @return array<string, mixed>
	 */
	private function write(string $caller, array $call): array {
		$log = [];
		[$action, $id, $name, $entries, $refresh] = $call;

		$dashboards = $this->getMockBuilder(className: DashboardMapper::class)->disableOriginalConstructor()->getMock();
		$dashboards->method('findByUuid')->willReturnCallback(static function (string $uuid) use (&$log): Dashboard {
			$log[] = 'findByUuid ' . $uuid;
			if ($uuid === 'd-missing') {
				throw new DoesNotExistException('none');
			}

			$dashboard = new Dashboard();
			$dashboard->setUuid($uuid);
			$dashboard->setUserId(substr($uuid, 2));
			return $dashboard;
		});

		$admins = $this->getMockBuilder(className: IGroupManager::class)->getMock();
		$admins->method('isAdmin')->willReturnCallback(static function (string $userId) use (&$log): bool {
			$log[] = 'isAdmin ' . $userId;
			return $userId === 'admin';
		});

		$shares = $this->getMockBuilder(className: PublicShareService::class)->disableOriginalConstructor()->getMock();
		$shares->method('authorizeShareMutation')->willReturnCallback(static function (Dashboard $dashboard, string $userId) use (&$log): void {
			$log[] = 'authorizeShareMutation ' . $dashboard->getUuid() . ' ' . $userId;
			if ($dashboard->getUserId() !== $userId && $userId !== 'admin') {
				throw new OCSForbiddenException('Not authorized');
			}
		});

		$playlists = $this->getMockBuilder(className: KioskPlaylistMapper::class)->disableOriginalConstructor()->getMock();
		$stored = static function (string $op) use (&$log): \Closure {
			return static function (KioskPlaylist $playlist) use ($op, &$log): KioskPlaylist {
				$row = $playlist->jsonSerialize();
				unset($row['createdAt'], $row['url']);
				$log[] = $op . ' ' . json_encode($row);
				$playlist->setId(7);
				return $playlist;
			};
		};
		$playlists->method('insert')->willReturnCallback($stored('insert'));
		$playlists->method('update')->willReturnCallback($stored('update'));
		$playlists->method('findById')->willReturnCallback(static function (int $playlistId) use (&$log): KioskPlaylist {
			$log[] = 'findById ' . $playlistId;
			if ($playlistId !== 7) {
				throw new DoesNotExistException('none');
			}

			$playlist = new KioskPlaylist();
			$playlist->setId(7);
			$playlist->setName('Old');
			$playlist->setToken('t-7');
			$playlist->setEntries('[]');
			$playlist->setRefreshSeconds(30);
			$playlist->setCreatedBy('alice');
			return $playlist;
		});

		$random = $this->getMockBuilder(className: ISecureRandom::class)->getMock();
		$random->method('generate')->willReturn('token');

		$service = new KioskService(
			stores: new KioskStores(
				playlistMapper: $playlists,
				dashMapper: $dashboards,
				shareService: $shares,
				placementMapper: $this->getMockBuilder(className: WidgetPlacementMapper::class)->disableOriginalConstructor()->getMock()
			),
			groupManager: $admins,
			secureRandom: $random,
			logger: $this->getMockBuilder(className: LoggerInterface::class)->getMock()
		);
		$controller = new KioskController(
			request: $this->getMockBuilder(className: IRequest::class)->getMock(),
			kioskService: $service,
			shareContext: $this->getMockBuilder(className: PublicShareContext::class)->disableOriginalConstructor()->getMock(),
			logger: $this->getMockBuilder(className: LoggerInterface::class)->getMock(),
			userId: $caller
		);

		if ($action === 'create') {
			$response = $controller->create(name: $name, entries: $entries, refreshSeconds: $refresh);
		} else {
			$response = $controller->update(id: $id, name: $name, entries: $entries, refreshSeconds: $refresh);
		}

		$data = $response->getData();
		unset($data['createdAt'], $data['url']);

		return ['status' => $response->getStatus(), 'data' => $data, 'calls' => $log];
	}
}
