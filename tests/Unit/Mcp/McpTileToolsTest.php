<?php

/**
 * Unit tests for the MCP tile read tools.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/specs/mcp-tools/spec.md
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Db\Tile;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Mcp\McpTileTools;
use OCA\LaunchPad\Service\TileService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * listTiles is the caller's own tiles, behind the `tile.index` action.
 *
 * @covers \OCA\LaunchPad\Mcp\McpTileTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 * @uses   \OCA\LaunchPad\Db\Tile
 */
class McpTileToolsTest extends TestCase {

	private McpDashboardGate&MockObject $gate;

	private TileService&MockObject $tiles;

	protected function setUp(): void {
		$this->gate = $this->createMock(McpDashboardGate::class);
		$this->gate->method('currentUserId')->willReturn('alice');
		$this->tiles = $this->createMock(TileService::class);
	}//end setUp()

	/**
	 * The tool lists exactly the tiles the service returns for the caller.
	 *
	 * @return void
	 */
	public function testListTilesIsTheCallersTiles(): void {
		$this->gate->expects($this->once())->method('requireAction')->with('tile.index')->willReturn(null);
		$tile = new Tile();
		$tile->setId(3);
		$tile->setTitle('Intranet');
		$this->tiles->expects($this->once())->method('getUserTiles')->with('alice')->willReturn([$tile]);

		$result = (new McpTileTools($this->gate, $this->tiles))->listTiles([]);

		$this->assertCount(1, $result['items']);
		$this->assertSame($tile->jsonSerialize(), $result['items'][0]);
		$this->assertSame('Intranet', $result['items'][0]['title']);
	}//end testListTilesIsTheCallersTiles()

	/**
	 * A refused action reads no tiles.
	 *
	 * @return void
	 */
	public function testARefusedActionReadsNothing(): void {
		$refusal = McpArgumentValidator::error(code: 'forbidden', message: 'no');
		$this->gate->method('requireAction')->willReturn($refusal);
		$this->tiles->expects($this->never())->method('getUserTiles');

		$this->assertSame($refusal, (new McpTileTools($this->gate, $this->tiles))->listTiles([]));
	}//end testARefusedActionReadsNothing()
}//end class
