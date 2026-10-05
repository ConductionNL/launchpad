<?php

/**
 * TileLaunchControllerTest
 *
 * `GET /api/tiles/{placementId}/rdp` (launcher-tile-launch-types,
 * REQ-TLT-002) through the real PermissionService, TileLaunchValidator and
 * RdpFileBuilder: the caller must be able to view the tile's dashboard, and
 * the file holds the validated connection and never a credential.
 *
 * @category  Test
 * @package   Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\TileLaunchController;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RdpFileBuilder;
use OCA\LaunchPad\Service\RoleService;
use OCA\LaunchPad\Service\TileLaunchSettingsService;
use OCA\LaunchPad\Service\TileLaunchValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Builds the real services.
 */
class TileLaunchControllerTest extends TestCase {
	private WidgetPlacement $tile;

	protected function setUp(): void {
		$this->tile = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->tile->setId(10);
		$this->tile->setDashboardId(5);
		$this->tile->setWidgetId('tile-abc');
		$this->tile->setTileTitle('Belastingen (oud)');
		$this->tile->setTileLinkType('remote-desktop');
		$this->tile->setTileLinkValue('');
		$this->tile->setContentArray([
			'remote' => ['mode' => 'rdp', 'host' => 'rds01.gemeente.local', 'remoteApp' => '||Belastingen', 'gateway' => 'rdgw.gemeente.nl'],
		]);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
	}//end setUp()

	/**
	 * A controller for one caller; dashboard 5 belongs to pieter.
	 *
	 * @param string|null $uid The caller, or null when signed out.
	 *
	 * @return TileLaunchController
	 */
	private function controller(?string $uid): TileLaunchController {
		$dashboard = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setId(5);
		$dashboard->setUuid('dash-uuid');
		$dashboard->setType(Dashboard::TYPE_USER);
		$dashboard->setUserId('pieter');
		$dashboard->setPermissionLevel(Dashboard::PERMISSION_VIEW_ONLY);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('find')->willReturnCallback(
			static function (int $id) use ($dashboard): Dashboard {
				if ($id !== 5) {
					throw new DoesNotExistException(msg: 'no dashboard ' . $id);
				}
				return $dashboard;
			}
		);
		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('find')->willReturnCallback(
			function (int $id): WidgetPlacement {
				if ($id !== 10) {
					throw new DoesNotExistException(msg: 'no placement ' . $id);
				}
				return $this->tile;
			}
		);
		$roles = $this->createMock(RoleService::class);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn(false);
		$shares = $this->createMock(DashboardShareService::class);
		$shares->method('resolveSharedDashboards')->willReturn([]);
		$templates = $this->createMock(AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturn([]);
		$settingMapper = $this->createMock(AdminSettingMapper::class);
		$settingMapper->method('getValue')->willReturnCallback(fn (string $key, mixed $default = null): mixed => $default);

		$permissions = new PermissionService(
			dashboardMapper: $dashboards,
			placementMapper: $placements,
			settingMapper: $settingMapper,
			shareService: $shares,
			groupManager: $groups,
			adminTemplateService: $templates,
			roleService: $roles,
		);

		$session = $this->createMock(IUserSession::class);
		$user    = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);

		return new TileLaunchController(
			request: $this->createMock(IRequest::class),
			permissionService: $permissions,
			placementMapper: $placements,
			launchValidator: new TileLaunchValidator(settings: new TileLaunchSettingsService(settingMapper: $settingMapper)),
			rdpFiles: new RdpFileBuilder(),
			userSession: $session,
		);
	}//end controller()

	public function testTheOwnerDownloadsTheConnectionWithoutACredential(): void {
		$response = $this->controller(uid: 'pieter')->rdp(placementId: 10);

		$this->assertInstanceOf(DataDisplayResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		// getHeaders() asks the running server for the request id, so read
		// the headers this response set itself.
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($response);
		$this->assertSame('application/x-rdp', $headers['Content-Type']);
		$this->assertStringContainsString('attachment; filename="Belastingen (oud).rdp"', $headers['Content-Disposition']);

		$file = $response->render();
		$lines = explode("\r\n", rtrim($file, "\r\n"));
		$this->assertContains('full address:s:rds01.gemeente.local:3389', $lines);
		$this->assertContains('remoteapplicationmode:i:1', $lines);
		$this->assertContains('remoteapplicationprogram:s:||Belastingen', $lines);
		$this->assertContains('gatewayhostname:s:rdgw.gemeente.nl', $lines);
		$this->assertContains('gatewayusagemethod:i:1', $lines);
		$this->assertDoesNotMatchRegularExpression('/username|password|domain/i', $file);
	}//end testTheOwnerDownloadsTheConnectionWithoutACredential()

	public function testAFullDesktopHasNoProgramLines(): void {
		$this->tile->setContentArray(['remote' => ['mode' => 'rdp', 'host' => '10.1.2.3', 'port' => 3390]]);

		$file = $this->controller(uid: 'pieter')->rdp(placementId: 10)->render();

		$this->assertStringContainsString("full address:s:10.1.2.3:3390\r\n", $file);
		$this->assertStringNotContainsString('remoteapplication', $file);
		$this->assertStringNotContainsString('gatewayhostname', $file);
	}//end testAFullDesktopHasNoProgramLines()

	public function testSomeoneWhoMayNotViewTheDashboardGets403(): void {
		$response = $this->controller(uid: 'sanne')->rdp(placementId: 10);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertNotInstanceOf(DataDisplayResponse::class, $response);
	}//end testSomeoneWhoMayNotViewTheDashboardGets403()

	public function testSignedOutGets401AndAMissingTile403(): void {
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(uid: null)->rdp(placementId: 10)->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller(uid: 'pieter')->rdp(placementId: 99)->getStatus());
	}//end testSignedOutGets401AndAMissingTile403()

	public function testATileThatIsNoRdpConnectionGets404(): void {
		$this->tile->setContentArray(['remote' => ['mode' => 'gateway', 'url' => 'https://desktop.gemeente.nl/']]);
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller(uid: 'pieter')->rdp(placementId: 10)->getStatus());

		$this->tile->setTileLinkType('url');
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller(uid: 'pieter')->rdp(placementId: 10)->getStatus());
	}//end testATileThatIsNoRdpConnectionGets404()

	public function testAStoredConnectionThatNoLongerValidatesIsNotWritten(): void {
		$this->tile->setContentArray(['remote' => ['mode' => 'rdp', 'host' => "rds01\r\nusername:s:admin"]]);

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->controller(uid: 'pieter')->rdp(placementId: 10)->getStatus());
	}//end testAStoredConnectionThatNoLongerValidatesIsNotWritten()
}//end class
