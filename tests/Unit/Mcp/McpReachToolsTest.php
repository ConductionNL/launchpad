<?php

/**
 * Unit tests for the MCP user, external and instance reach tools.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-10
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Controller\AdminTemplateTools;
use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\AnalyticsServiceInterface;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Mcp\McpDashboardWriteTools;
use OCA\LaunchPad\Mcp\McpInstanceTools;
use OCA\LaunchPad\Mcp\McpSharingTools;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Kernel\Contracts\ExportServiceInterface;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\KioskPlaylistDraft;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareService;
use OCA\LaunchPad\Service\ShareGrant;
use OCA\LaunchPad\Kernel\Contracts\TemplateResyncServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use OCA\LaunchPad\Mcp\McpExternalSources;
use OCA\LaunchPad\Mcp\McpExternalTools;

/**
 * The sharing, publication, public-link, kiosk, group-dashboard and template
 * tools call the services their routes call, for the caller, and the
 * instance-wide ones only for an administrator.
 *
 * @covers \OCA\LaunchPad\Mcp\McpSharingTools
 * @covers \OCA\LaunchPad\Mcp\McpExternalTools
 * @covers \OCA\LaunchPad\Mcp\McpInstanceTools
 * @covers \OCA\LaunchPad\Mcp\McpDashboardWriteTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 */
class McpReachToolsTest extends TestCase {

	private const UUID = '0b1e8a52-6c1f-4c3a-9d55-1a2b3c4d5e6f';

	/**
	 * @var McpDashboardGate&MockObject
	 */
	private McpDashboardGate $gate;

	/**
	 * Build the gate double: signed in as alice, every action allowed.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->gate = $this->createMock(originalClassName: McpDashboardGate::class);
		$this->gate->method('currentUserId')->willReturn('alice');
		$this->gate->method('requireUser')->willReturn(null);
		$this->gate->method('requireAction')->willReturn(null);
	}//end setUp()

	/**
	 * Sharing goes through DashboardShareService for the caller; a non-owner is forbidden.
	 *
	 * @return void
	 */
	public function testSharing(): void {
		$shares = $this->createMock(originalClassName: DashboardShareService::class);
		$share  = new DashboardShare();
		$share->setShareWith('sales');
		$shares->method('addShare')->willReturnCallback(
			callback: static fn (int $dashboardId, ShareGrant $grant, string $callerId): DashboardShare => match (true) {
				$grant->shareType === 'group' && $grant->shareWith === 'sales' && $callerId === 'alice' => $share,
			}
		);
		$shares->method('replaceShares')->willReturn([$share]);
		$shares->method('removeShare')->willThrowException(exception: new ForbiddenException('not yours'));
		$tools = new McpSharingTools(
			gate: $this->gate,
			validator: new McpArgumentValidator(),
			shares: $shares
		);

		$grant = ['dashboardId' => 4, 'shareType' => 'group', 'shareWith' => 'sales', 'permissionLevel' => 'view_only'];
		$this->assertSame(expected: $share->jsonSerialize(), actual: $tools->shareDashboard(args: $grant));
		$this->assertSame(expected: ['items' => [$share->jsonSerialize()]], actual: $tools->replaceShares(args: ['dashboardId' => 4, 'shares' => []]));
		$this->assertSame(expected: 'forbidden', actual: $tools->unshareDashboard(args: ['shareId' => 2])['error']);
		$this->assertSame(expected: 'invalid_arguments', actual: $tools->shareDashboard(args: ['permissionLevel' => 'owner'] + $grant)['error']);
	}//end testSharing()

	/**
	 * Kiosk playlists are created, changed and revoked through KioskService for the caller.
	 *
	 * @return void
	 */
	public function testKioskPlaylists(): void {
		$kiosk    = $this->createMock(originalClassName: KioskService::class);
		$playlist = new KioskPlaylist();
		$playlist->setName('Lobby');
		$kiosk->method('createPlaylist')->willReturnCallback(
			callback: static fn (KioskPlaylistDraft $draft, string $callerId): KioskPlaylist => match (true) {
				$draft->name === 'Lobby' && $draft->refresh === 60 && $callerId === 'alice' => $playlist,
			}
		);
		$kiosk->method('updatePlaylist')->willReturn($playlist);
		$kiosk->expects($this->once())->method('revokePlaylist');
		$kiosk->method('listPlaylists')->willReturnCallback(
			callback: static fn (string $callerId): array => match ($callerId) {
				'alice' => [$playlist],
			}
		);
		$tools = new McpExternalTools(
			gate: $this->gate,
			validator: new McpArgumentValidator(),
			external: new McpExternalSources(kiosk: $kiosk, links: $this->createMock(originalClassName: PublicShareService::class))
		);

		$draft = ['name' => 'Lobby', 'entries' => [], 'refreshSeconds' => 60];
		$this->assertSame(expected: $playlist->jsonSerialize(), actual: $tools->createKioskPlaylist(args: $draft));
		$this->assertSame(expected: $playlist->jsonSerialize(), actual: $tools->updateKioskPlaylist(args: ['playlistId' => 3] + $draft));
		$this->assertSame(expected: ['revoked' => true], actual: $tools->deleteKioskPlaylist(args: ['playlistId' => 3]));
		$this->assertSame(expected: ['items' => [$playlist->jsonSerialize()]], actual: $tools->listKioskPlaylists(args: []));
		$this->assertSame(expected: 'invalid_arguments', actual: $tools->createKioskPlaylist(args: ['name' => 'Lobby'])['error']);
	}//end testKioskPlaylists()

	/**
	 * A public link is minted and revoked through PublicShareService for the caller.
	 *
	 * @return void
	 */
	public function testPublicLinks(): void {
		$links = $this->createMock(originalClassName: PublicShareService::class);
		$link  = new PublicShare();
		$links->method('createPublicShare')->willReturnCallback(
			callback: static fn (string $dashboardUuid, string $callerId, ?string $password, ?string $expiresAt): PublicShare => match (true) {
				$dashboardUuid === self::UUID && $callerId === 'alice' && $password === null && $expiresAt === '2027-01-01' => $link,
			}
		);
		$links->expects($this->once())->method('revokeShare');
		$tools = new McpExternalTools(
			gate: $this->gate,
			validator: new McpArgumentValidator(),
			external: new McpExternalSources(kiosk: $this->createMock(originalClassName: KioskService::class), links: $links)
		);

		$minted = $tools->createPublicShareLink(args: ['dashboardUuid' => self::UUID, 'expiresAt' => '2027-01-01']);
		$this->assertSame(expected: $link->jsonSerialize(), actual: $minted);
		$this->assertSame(expected: ['revoked' => true], actual: $tools->revokePublicShareLink(args: ['dashboardUuid' => self::UUID, 'shareId' => 8]));
	}//end testPublicLinks()

	/**
	 * The publication state picks publish, unpublish or schedule, behind the matching action.
	 *
	 * @return void
	 */
	public function testSetPublicationState(): void {
		$dashboards = $this->createMock(originalClassName: DashboardService::class);
		$dashboard  = new Dashboard();
		$dashboards->expects($this->once())->method('publishDashboard')->willReturn($dashboard);
		$dashboards->method('schedule')->willThrowException(exception: new \Exception(DashboardService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN));
		$gate = $this->createMock(originalClassName: McpDashboardGate::class);
		$gate->method('currentUserId')->willReturn('alice');
		$asked = [];
		$gate->method('requireAction')->willReturnCallback(
			callback: static function (string $action) use (&$asked): ?array {
				$asked[] = $action;
				return null;
			}
		);
		$tools = new McpDashboardWriteTools(
			gate: $gate,
			validator: new McpArgumentValidator(),
			services: new DashboardApiServices(dashboardService: $dashboards),
			history: new DashboardApiHistory(
				treeService: $this->createMock(originalClassName: DashboardTreeService::class),
				versionService: $this->createMock(originalClassName: DashboardVersionService::class),
				analyticsService: $this->createMock(originalClassName: AnalyticsServiceInterface::class),
				logger: new NullLogger()
			)
		);

		$state = static fn (array $more): array => ['dashboardUuid' => self::UUID] + $more;
		$published = $tools->setPublicationState(args: $state(['state' => 'published']));
		$this->assertSame(expected: ['dashboard' => $dashboard->jsonSerialize()], actual: $published);
		$scheduled = $tools->setPublicationState(args: ['dashboardUuid' => self::UUID, 'state' => 'scheduled', 'publishAt' => '2027-01-01T08:00:00Z']);
		$this->assertSame(expected: 'forbidden', actual: $scheduled['error']);
		$this->assertSame(expected: 'invalid_arguments', actual: $tools->setPublicationState(args: $state(['state' => 'scheduled']))['error']);
		$this->assertSame(expected: ['dashboard.publish', 'dashboard.schedule'], actual: $asked);
	}//end testSetPublicationState()

	/**
	 * The instance tools refuse a non-administrator before any service call,
	 * and call the services for an administrator.
	 *
	 * @return void
	 */
	public function testInstanceToolsAreForAdministrators(): void {
		$dashboards = $this->createMock(originalClassName: DashboardService::class);
		$templates  = $this->createMock(originalClassName: AdminTemplateService::class);
		$resync     = $this->createMock(originalClassName: TemplateResyncServiceInterface::class);
		$build      = fn (McpDashboardGate $gate): McpInstanceTools => new McpInstanceTools(
			gate: $gate,
			validator: new McpArgumentValidator(),
			services: new DashboardApiServices(dashboardService: $dashboards),
			templates: new AdminTemplateTools(
				templateService: $templates,
				resyncService: $resync,
				exportService: $this->createMock(originalClassName: ExportServiceInterface::class),
				importService: $this->createMock(originalClassName: ImportService::class)
			)
		);

		$member = $this->createMock(originalClassName: McpDashboardGate::class);
		$member->method('requireAdmin')->willReturn(McpArgumentValidator::error(code: 'forbidden', message: 'Admins only.'));
		foreach (['createGroupShared', 'updateGroupShared', 'deleteGroupShared', 'setGroupDefault'] as $method) {
			$dashboards->expects($this->never())->method($method);
		}

		foreach (['createTemplate', 'updateTemplate', 'deleteTemplate'] as $method) {
			$templates->expects($this->never())->method($method);
		}
		$memberTools = $build($member);
		$args        = ['groupId' => 'sales', 'name' => 'Sales', 'dashboardUuid' => self::UUID, 'templateId' => 3, 'strategy' => 'merge'];
		$instanceTools = ['createGroupDashboard', 'updateGroupDashboard', 'deleteGroupDashboard', 'setGroupDefault'];
		$instanceTools = [...$instanceTools, 'createTemplate', 'updateTemplate', 'deleteTemplate', 'resyncTemplate'];
		foreach ($instanceTools as $tool) {
			$this->assertSame(expected: 'forbidden', actual: $memberTools->$tool($args)['error'], message: $tool);
		}

		$admin = $this->createMock(originalClassName: McpDashboardGate::class);
		$admin->method('requireAdmin')->willReturn(null);
		$admin->method('currentUserId')->willReturn('root');
		$resync->expects($this->once())->method('resync')->willReturnCallback(
			callback: static fn (int $templateId, string $strategy, bool $dryRun, string $actingAdminId): array => [
				'dryRun' => $dryRun,
				'by' => $actingAdminId,
			]
		);
		$this->assertSame(
			expected: ['dryRun' => true, 'by' => 'root'],
			actual: $build($admin)->resyncTemplate(args: ['templateId' => 3, 'strategy' => 'merge'])
		);
	}//end testInstanceToolsAreForAdministrators()
}//end class
