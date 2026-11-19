<?php

/**
 * TileLaunchController Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-3
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\SessionLog;
use OCA\LaunchPad\Controller\TileLaunchController;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RdpFileWriter;
use OCA\LaunchPad\Service\TileLinkValidator;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;

/**
 * The RDP file of a remote-desktop tile: content, view check and refusals.
 */
#[CoversClass(TileLaunchController::class)]
#[UsesClass(RdpFileWriter::class)]
#[UsesClass(TileLinkValidator::class)]
#[UsesClass(SessionLog::class)]
#[UsesClass(WidgetPlacement::class)]
class TileLaunchControllerTest extends TestCase {

	/**
	 * Decides who may view the tile's dashboard.
	 *
	 * @var PermissionService&MockObject
	 */
	private PermissionService&MockObject $permissions;

	/**
	 * Reads the tile.
	 *
	 * @var WidgetPlacementMapper&MockObject
	 */
	private WidgetPlacementMapper&MockObject $placements;

	/**
	 * The signed-in user, or null.
	 *
	 * @var IUserSession&MockObject
	 */
	private IUserSession&MockObject $userSession;

	/**
	 * Build the mocks with Pieter signed in.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->permissions = $this->createMock(PermissionService::class);
		$this->placements = $this->createMock(WidgetPlacementMapper::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('pieter');
		$this->userSession->method('getUser')->willReturn($user);
	}//end setUp()

	/**
	 * The controller under test.
	 *
	 * @return TileLaunchController
	 */
	private function controller(): TileLaunchController {
		return new TileLaunchController(
			request: $this->createMock(IRequest::class),
			permissions: $this->permissions,
			session: new SessionLog(userSession: $this->userSession, logger: $this->createMock(LoggerInterface::class)),
			placements: $this->placements,
			links: new TileLinkValidator(settings: $this->createMock(AdminSettingsService::class)),
			rdpFiles: new RdpFileWriter()
		);
	}//end controller()

	/**
	 * A tile of the given type and content.
	 *
	 * @param string               $type    The link type.
	 * @param array<string, mixed> $content The content.
	 *
	 * @return WidgetPlacement
	 */
	private static function tile(string $type, array $content): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setTileTitle('Belastingen (oud)');
		$placement->setTileLinkType($type);
		$placement->setContentArray($content);
		return $placement;
	}//end tile()

	/**
	 * The published program through a gateway (the spec's scenario).
	 *
	 * @return void
	 */
	public function testAViewerDownloadsTheFile(): void {
		$this->permissions->expects($this->once())->method('canViewPlacement')->with('pieter', 7)->willReturn(true);
		$this->placements->method('find')->with(7)->willReturn(self::tile(
			type: 'remote-desktop',
			content: ['remote' => ['mode' => 'rdp', 'host' => 'rds01.gemeente.local', 'remoteApp' => '||Belastingen', 'gateway' => 'rdgw.gemeente.nl']]
		));

		$response = $this->controller()->rdp(placementId: 7);

		$this->assertInstanceOf(DataDisplayResponse::class, $response);
		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$headers = (new ReflectionProperty(Response::class, 'headers'))->getValue($response);
		$this->assertSame('application/x-rdp', $headers['Content-Type']);
		$this->assertSame(
			'attachment; filename="Belastingen (oud).rdp"; filename*=UTF-8\'\'' . rawurlencode('Belastingen (oud).rdp'),
			$headers['Content-Disposition']
		);
		$file = $response->render();
		$this->assertStringContainsString("full address:s:rds01.gemeente.local\r\n", $file);
		$this->assertStringContainsString("remoteapplicationprogram:s:||Belastingen\r\n", $file);
		$this->assertStringContainsString("gatewayhostname:s:rdgw.gemeente.nl\r\n", $file);
		$this->assertStringNotContainsStringIgnoringCase('username', $file);
		$this->assertStringNotContainsStringIgnoringCase('password', $file);
	}//end testAViewerDownloadsTheFile()

	/**
	 * Someone without access gets 403 and the tile is never read.
	 *
	 * @return void
	 */
	public function testSomeoneWhoMayNotViewTheDashboardGets403(): void {
		$this->permissions->method('canViewPlacement')->willReturn(false);
		$this->placements->expects($this->never())->method('find');

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->rdp(placementId: 7)->getStatus());
	}//end testSomeoneWhoMayNotViewTheDashboardGets403()

	/**
	 * Signed out gets 401.
	 *
	 * @return void
	 */
	public function testSignedOutGets401(): void {
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userSession->method('getUser')->willReturn(null);
		$this->permissions->expects($this->never())->method('canViewPlacement');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller()->rdp(placementId: 7)->getStatus());
	}//end testSignedOutGets401()

	/**
	 * A tile that is not an RDP tile, a stored value that no longer validates
	 * and a missing placement all answer 404.
	 *
	 * @return void
	 */
	public function testATileWithoutAnRdpConnectionGets404(): void {
		$this->permissions->method('canViewPlacement')->willReturn(true);
		$this->placements->method('find')->willReturnOnConsecutiveCalls(
			self::tile(type: 'url', content: []),
			self::tile(type: 'remote-desktop', content: ['remote' => ['mode' => 'gateway', 'url' => 'https://desktop.gemeente.nl/']]),
			self::tile(type: 'remote-desktop', content: ['remote' => ['mode' => 'rdp', 'host' => "pc12\r\nusername:s:admin"]]),
			$this->throwException(new DoesNotExistException('gone'))
		);
		$controller = $this->controller();

		for ($i = 0; $i < 4; $i++) {
			$this->assertSame(Http::STATUS_NOT_FOUND, $controller->rdp(placementId: 7)->getStatus());
		}
	}//end testATileWithoutAnRdpConnectionGets404()
}//end class
