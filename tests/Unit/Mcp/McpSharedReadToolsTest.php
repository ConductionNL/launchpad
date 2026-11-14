<?php

/**
 * Unit tests for the MCP tree, share, template, acknowledgement and kiosk read tools.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Mcp\McpDashboardTreeTools;
use OCA\LaunchPad\Mcp\McpPersonalTools;
use OCA\LaunchPad\Mcp\McpShareTools;
use OCA\LaunchPad\Service\AcknowledgementService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\KioskService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Each tool answers what the service its HTTP route calls answers for the
 * caller, and reads nothing when the caller is refused.
 *
 * @covers \OCA\LaunchPad\Mcp\McpDashboardTreeTools
 * @covers \OCA\LaunchPad\Mcp\McpShareTools
 * @covers \OCA\LaunchPad\Mcp\McpPersonalTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\DashboardShare
 * @uses   \OCA\LaunchPad\Db\KioskPlaylist
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 */
class McpSharedReadToolsTest extends TestCase {

	private McpDashboardGate&MockObject $gate;

	protected function setUp(): void {
		$this->gate = $this->createMock(McpDashboardGate::class);
		$this->gate->method('currentUserId')->willReturn('alice');
	}//end setUp()

	/**
	 * The tree is asked for exactly the dashboards the caller can see.
	 *
	 * @return void
	 */
	public function testTheTreeIsFilteredToTheCallersVisibleDashboards(): void {
		$this->gate->expects($this->once())->method('requireAction')->with('dashboard.tree')->willReturn(null);
		$own = new Dashboard();
		$own->setUuid('uuid-own');
		$noUuid = new Dashboard();
		$dashboards = $this->createMock(DashboardService::class);
		$dashboards->expects($this->once())->method('getVisibleToUser')->with('alice')
			->willReturn([['dashboard' => $own, 'source' => 'own'], ['dashboard' => $noUuid, 'source' => 'group']]);
		$tree = $this->createMock(DashboardTreeService::class);
		$tree->expects($this->once())->method('getFilteredTree')->with(['uuid-own' => true])->willReturn([['uuid' => 'uuid-own', 'children' => []]]);

		$result = (new McpDashboardTreeTools($this->gate, $dashboards, $tree))->getDashboardTree([]);

		$this->assertSame(['items' => [['uuid' => 'uuid-own', 'children' => []]]], $result);
	}//end testTheTreeIsFilteredToTheCallersVisibleDashboards()

	/**
	 * A refused tree action reads no dashboards.
	 *
	 * @return void
	 */
	public function testARefusedTreeReadsNothing(): void {
		$refusal = McpArgumentValidator::error(code: 'forbidden', message: 'no');
		$this->gate->method('requireAction')->willReturn($refusal);
		$dashboards = $this->createMock(DashboardService::class);
		$dashboards->expects($this->never())->method('getVisibleToUser');
		$tree = $this->createMock(DashboardTreeService::class);
		$tree->expects($this->never())->method('getFilteredTree');

		$this->assertSame($refusal, (new McpDashboardTreeTools($this->gate, $dashboards, $tree))->getDashboardTree([]));
	}//end testARefusedTreeReadsNothing()

	/**
	 * The owner gets the dashboard's shares as the service lists them.
	 *
	 * @return void
	 */
	public function testTheOwnerListsTheShares(): void {
		$this->gate->method('requireSignedIn')->willReturn(null);
		$share = new DashboardShare();
		$share->setDashboardId(7);
		$share->setShareType('group');
		$share->setShareWith('sales');
		$shares = $this->createMock(DashboardShareService::class);
		$shares->expects($this->once())->method('listShares')->with(7, 'alice')->willReturn([$share]);

		$result = (new McpShareTools($this->gate, new McpArgumentValidator(), $shares))->listShares(['dashboardId' => 7]);

		$this->assertSame([$share->jsonSerialize()], $result['items']);
	}//end testTheOwnerListsTheShares()

	/**
	 * Someone who is not the owner is refused, and a missing dashboard is not found.
	 *
	 * @return void
	 */
	public function testANonOwnerAndAMissingDashboardAreRefused(): void {
		$this->gate->method('requireSignedIn')->willReturn(null);
		$shares = $this->createMock(DashboardShareService::class);
		$shares->method('listShares')->willReturnOnConsecutiveCalls(
			$this->throwException(new ForbiddenException('not yours')),
			$this->throwException(new DoesNotExistException('gone'))
		);
		$tools = new McpShareTools($this->gate, new McpArgumentValidator(), $shares);

		$this->assertSame('forbidden', $tools->listShares(['dashboardId' => 7])['error']);
		$this->assertSame('not_found', $tools->listShares(['dashboardId' => 8])['error']);
	}//end testANonOwnerAndAMissingDashboardAreRefused()

	/**
	 * A malformed dashboard id is refused before anything is read.
	 *
	 * @return void
	 */
	public function testAMalformedDashboardIdReadsNothing(): void {
		$this->gate->expects($this->never())->method('requireSignedIn');
		$shares = $this->createMock(DashboardShareService::class);
		$shares->expects($this->never())->method('listShares');

		$result = (new McpShareTools($this->gate, new McpArgumentValidator(), $shares))->listShares(['dashboardId' => '7']);

		$this->assertSame('invalid_arguments', $result['error']);
	}//end testAMalformedDashboardIdReadsNothing()

	/**
	 * The gallery is read with the category and sort given, after the action check.
	 *
	 * @return void
	 */
	public function testTheTemplateGalleryPassesItsFilters(): void {
		$this->gate->expects($this->exactly(2))->method('requireAction')->with('template.gallery')->willReturn(null);
		$templates = $this->createMock(AdminTemplateService::class);
		$templates->expects($this->exactly(2))->method('getGallery')
			->willReturnCallback(static fn (?string $category, string $sortBy): array => [['category' => $category, 'sort' => $sortBy]]);
		$tools = new McpPersonalTools($this->gate, $templates, $this->createMock(AcknowledgementService::class), $this->createMock(KioskService::class));

		$this->assertSame(['items' => [['category' => 'hr', 'sort' => 'updatedAt']]], $tools->listTemplates(['category' => 'hr', 'sort' => 'updatedAt']));
		$this->assertSame(['items' => [['category' => null, 'sort' => 'name']]], $tools->listTemplates(['category' => 3, 'sort' => []]));
	}//end testTheTemplateGalleryPassesItsFilters()

	/**
	 * The pending acknowledgements are the caller's own, as the service answers them.
	 *
	 * @return void
	 */
	public function testPendingAcknowledgementsAreTheCallers(): void {
		$this->gate->method('requireSignedIn')->willReturn(null);
		$acks = $this->createMock(AcknowledgementService::class);
		$acks->expects($this->once())->method('getPending')->with('alice')->willReturn(['count' => 1, 'items' => [['key' => 'a']]]);
		$tools = new McpPersonalTools($this->gate, $this->createMock(AdminTemplateService::class), $acks, $this->createMock(KioskService::class));

		$this->assertSame(['count' => 1, 'items' => [['key' => 'a']]], $tools->listPendingAcknowledgements([]));
	}//end testPendingAcknowledgementsAreTheCallers()

	/**
	 * The playlists are the ones the service lets the caller see.
	 *
	 * @return void
	 */
	public function testKioskPlaylistsAreTheOnesTheCallerMaySee(): void {
		$this->gate->method('requireSignedIn')->willReturn(null);
		$playlist = new KioskPlaylist();
		$playlist->setName('Lobby');
		$kiosk = $this->createMock(KioskService::class);
		$kiosk->expects($this->once())->method('listPlaylists')->with('alice')->willReturn([$playlist]);
		$tools = new McpPersonalTools($this->gate, $this->createMock(AdminTemplateService::class), $this->createMock(AcknowledgementService::class), $kiosk);

		$this->assertSame(['items' => [$playlist->jsonSerialize()]], $tools->listKioskPlaylists([]));
	}//end testKioskPlaylistsAreTheOnesTheCallerMaySee()

	/**
	 * Without a signed-in caller the session tools read nothing.
	 *
	 * @return void
	 */
	public function testWithoutASessionNothingIsRead(): void {
		$refusal = McpArgumentValidator::error(code: 'unauthenticated', message: 'no');
		$this->gate->method('requireSignedIn')->willReturn($refusal);
		$acks = $this->createMock(AcknowledgementService::class);
		$acks->expects($this->never())->method('getPending');
		$kiosk = $this->createMock(KioskService::class);
		$kiosk->expects($this->never())->method('listPlaylists');
		$tools = new McpPersonalTools($this->gate, $this->createMock(AdminTemplateService::class), $acks, $kiosk);

		$this->assertSame($refusal, $tools->listPendingAcknowledgements([]));
		$this->assertSame($refusal, $tools->listKioskPlaylists([]));
	}//end testWithoutASessionNothingIsRead()
}//end class
