<?php

/**
 * BookmarkImportControllerTest
 *
 * launcher-bookmark-import REQ-BMI-001..003 from the endpoint: every outcome
 * the controller maps (401, 403 from the action matrix, 403 from the
 * dashboard's permission, 201, 409 for a full dashboard, 400 for a file over
 * the limit, 500 when the store fails part way) through the real
 * BookmarkImportService, PlacementService and QuotaService.
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

use OCA\LaunchPad\Controller\BookmarkImportController;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\BookmarkImportService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\QuotaService;
use OCA\LaunchPad\Service\TileUpdater;
use OCA\LaunchPad\Service\TileLaunchSettingsService;
use OCA\LaunchPad\Service\TileLaunchValidator;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IDBConnection;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for BookmarkImportController.
 */
class BookmarkImportControllerTest extends TestCase {
	/**
	 * Placements stored.
	 *
	 * @var WidgetPlacement[]
	 */
	private array $inserted = [];

	private int $limit = 0;

	private int $existing = 0;

	private ?int $failOnInsert = null;

	/**
	 * A controller for a caller.
	 *
	 * @param boolean $signedIn Whether someone is signed in.
	 * @param boolean $allowed Whether the action matrix lets them through.
	 * @param boolean $canAdd Whether they may add widgets to the dashboard.
	 *
	 * @return BookmarkImportController
	 */
	private function controller(bool $signedIn = true, bool $allowed = true, bool $canAdd = true): BookmarkImportController {
		$mapper = $this->createMock(WidgetPlacementMapper::class);
		$mapper->method('insert')->willReturnCallback(function (WidgetPlacement $placement): WidgetPlacement {
			if ($this->failOnInsert !== null && count($this->inserted) === $this->failOnInsert) {
				throw new RuntimeException('database went away');
			}

			$placement->setId(100 + count($this->inserted));
			$this->inserted[] = $placement;
			return $placement;
		});
		$mapper->method('countByDashboardId')->willReturnCallback(fn (): int => $this->existing);
		$mapper->method('findByDashboardId')->willReturn([]);
		$settings = $this->createMock(AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $key === 'max_widgets_per_dashboard' ? $this->limit : $default
		);
		$quota      = new QuotaService(settingMapper: $settings, dashboardMapper: $this->createMock(DashboardMapper::class), placementMapper: $mapper);
		$placements = new PlacementService(placementMapper: $mapper, tileUpdater: new TileUpdater(launchValidator: new TileLaunchValidator(settings: new TileLaunchSettingsService(settingMapper: $this->createMock(AdminSettingMapper::class)))), placementUpdater: $this->createMock(PlacementUpdater::class), quotaService: $quota);
		$importer   = new BookmarkImportService(placements: $placements, placementMapper: $mapper, quota: $quota, db: $this->createMock(IDBConnection::class));

		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($signedIn === true) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn('pieter');
		}

		$session->method('getUser')->willReturn($user);
		$auth = $this->createMock(ActionAuthService::class);
		if ($allowed === false) {
			$auth->method('requireAction')->willThrowException(new OCSForbiddenException('no'));
		}

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canAddWidget')->willReturn($canAdd);

		return new BookmarkImportController(
			request: $this->createMock(IRequest::class),
			importer: $importer,
			permissionService: $permissions,
			actionAuth: $auth,
			userSession: $session,
			logger: $this->createMock(LoggerInterface::class),
		);
	}//end controller()

	/**
	 * Bookmarks.
	 *
	 * @param integer $count How many.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function bookmarks(int $count): array {
		$list = [];
		for ($i = 1; $i <= $count; $i++) {
			$list[] = ['title' => 'Werk ' . $i, 'url' => 'https://example.nl/werk/' . $i];
		}

		return $list;
	}//end bookmarks()

	/**
	 * A folder becomes tiles: 201.
	 *
	 * @return void
	 */
	public function testAnImportIsCreated(): void {
		$response = $this->controller()->import(dashboardId: 7, folders: [['name' => 'Werk', 'bookmarks' => self::bookmarks(2)]]);
		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertCount(1, $this->inserted);
	}//end testAnImportIsCreated()

	/**
	 * Signed out, refused by the matrix, or not allowed on the dashboard: nothing is stored.
	 *
	 * @return void
	 */
	public function testRefusalsStoreNothing(): void {
		$folders = [['name' => 'Werk', 'bookmarks' => self::bookmarks(1)]];
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(signedIn: false)->import(dashboardId: 7, folders: $folders)->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller(allowed: false)->import(dashboardId: 7, folders: $folders)->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller(canAdd: false)->import(dashboardId: 7, folders: $folders)->getStatus());
		$this->assertSame([], $this->inserted);
	}//end testRefusalsStoreNothing()

	/**
	 * A full dashboard is 409 with the quota body.
	 *
	 * @return void
	 */
	public function testAFullDashboardIs409(): void {
		$this->limit    = 20;
		$this->existing = 20;
		$response       = $this->controller()->import(dashboardId: 7, folders: [['name' => 'A', 'bookmarks' => self::bookmarks(1)]]);
		$this->assertSame(Http::STATUS_CONFLICT, $response->getStatus());
		$this->assertSame(20, $response->getData()['limit']);
	}//end testAFullDashboardIs409()

	/**
	 * More than the limit is 400; a store failure is 500 with a plain message.
	 *
	 * @return void
	 */
	public function testTooManyIs400AndAFailureIs500(): void {
		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller()->import(dashboardId: 7, bookmarks: self::bookmarks(2001))->getStatus());

		$this->failOnInsert = 0;
		$response           = $this->controller()->import(dashboardId: 7, bookmarks: self::bookmarks(1));
		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame('The import failed; nothing was added.', $response->getData()['error']);
	}//end testTooManyIs400AndAFailureIs500()
}//end class
