<?php

/**
 * Unit tests for the MCP share tools.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-8
 */

declare(strict_types=1);

namespace Unit\Mcp;

use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Mcp\McpSharingTools;
use OCA\LaunchPad\Service\DashboardShareService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * listShares answers what the share service gives the caller,
 * and nothing when the caller is refused.
 *
 * @covers \OCA\LaunchPad\Mcp\McpSharingTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 */
class McpSharingToolsTest extends TestCase {

	/**
	 * @var McpDashboardGate&MockObject
	 */
	private McpDashboardGate $gate;

	/**
	 * @var DashboardShareService&MockObject
	 */
	private DashboardShareService $shares;

	/**
	 * Build the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->gate = $this->createMock(originalClassName: McpDashboardGate::class);
		$this->gate->method('currentUserId')->willReturn('alice');
		$this->shares = $this->createMock(originalClassName: DashboardShareService::class);
	}//end setUp()

	/**
	 * The tools under test.
	 *
	 * @return McpSharingTools
	 */
	private function tools(): McpSharingTools {
		return new McpSharingTools(
			gate: $this->gate,
			validator: new McpArgumentValidator(),
			shares: $this->shares
		);
	}//end tools()

	/**
	 * The shares are those the service lists for the caller and dashboard.
	 *
	 * @return void
	 */
	public function testListSharesIsTheServicesList(): void {
		$this->gate->method('requireUser')->willReturn(null);
		$share = new DashboardShare();
		$share->setDashboardId(4);
		$share->setShareType('group');
		$share->setShareWith('sales');
		$this->shares->expects($this->once())->method('listShares')->willReturnCallback(
			callback: static fn (int $dashboardId, string $userId): array => match (true) {
				$dashboardId === 4 && $userId === 'alice' => [$share],
				default => [],
			}
		);

		$this->assertSame(expected: ['items' => [$share->jsonSerialize()]], actual: $this->tools()->listShares(args: ['dashboardId' => 4]));
	}//end testListSharesIsTheServicesList()

	/**
	 * A bad id or a refused caller never reaches the service.
	 *
	 * @return void
	 */
	public function testRefusalsDoNotCallTheService(): void {
		$this->shares->expects($this->never())->method('listShares');
		$this->gate->method('requireUser')->willReturn(McpArgumentValidator::error(code: 'unauthenticated', message: 'x'));

		$this->assertSame(expected: 'invalid_arguments', actual: $this->tools()->listShares(args: ['dashboardId' => 'four'])['error']);
		$this->assertSame(expected: 'unauthenticated', actual: $this->tools()->listShares(args: ['dashboardId' => 4])['error']);
	}//end testRefusalsDoNotCallTheService()

	/**
	 * A non-owner is refused as the route refuses them.
	 *
	 * @return void
	 */
	public function testANonOwnerIsForbidden(): void {
		$this->gate->method('requireUser')->willReturn(null);
		$this->shares->method('listShares')->willThrowException(exception: new ForbiddenException('not yours'));

		$this->assertSame(expected: 'forbidden', actual: $this->tools()->listShares(args: ['dashboardId' => 4])['error']);
	}//end testANonOwnerIsForbidden()
}//end class
