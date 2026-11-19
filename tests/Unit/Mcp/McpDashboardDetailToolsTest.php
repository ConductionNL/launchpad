<?php

/**
 * Unit tests for the MCP version, metadata and engagement read tools.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-8
 * @spec openspec/changes/mcp-tool-surface/tasks.md#task-9
 */

declare(strict_types=1);

namespace Unit\Mcp;

use Exception;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Exception\InvalidMetadataFieldException;
use OCA\LaunchPad\Mcp\McpArgumentValidator;
use OCA\LaunchPad\Mcp\McpDashboardGate;
use OCA\LaunchPad\Mcp\McpEngagementTools;
use OCA\LaunchPad\Mcp\McpHistoryTools;
use OCA\LaunchPad\Mcp\McpMetadataTools;
use OCA\LaunchPad\Service\AcknowledgementService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\MetadataService;
use OCA\LaunchPad\Service\ReactionService;
use OCA\LaunchPad\Service\ReactionsDisabledException;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * listVersions, getDashboardMetadata, getReactions and
 * listPendingAcknowledgements answer what their services give the caller, and
 * refuse as their routes refuse.
 *
 * @covers \OCA\LaunchPad\Mcp\McpHistoryTools
 * @covers \OCA\LaunchPad\Mcp\McpMetadataTools
 * @covers \OCA\LaunchPad\Mcp\McpEngagementTools
 * @uses   \OCA\LaunchPad\Mcp\McpArgumentValidator
 */
class McpDashboardDetailToolsTest extends TestCase {

	private const UUID = '0b1e8a52-6c1f-4c3a-9d55-1a2b3c4d5e6f';

	/**
	 * @var McpDashboardGate&MockObject
	 */
	private McpDashboardGate $gate;

	/**
	 * @var DashboardMapper&MockObject
	 */
	private DashboardMapper $dashboards;

	/**
	 * Build the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->gate = $this->createMock(originalClassName: McpDashboardGate::class);
		$this->gate->method('currentUserId')->willReturn('alice');
		$this->dashboards = $this->createMock(originalClassName: DashboardMapper::class);
	}//end setUp()

	/**
	 * Let the gate pass only the given action.
	 *
	 * @param string $allowed The action.
	 *
	 * @return void
	 */
	private function allow(string $allowed): void {
		$this->gate->method('requireAction')->willReturnCallback(
			callback: static fn (string $action): ?array => match ($action) {
				$allowed => null,
				default => McpArgumentValidator::error(code: 'forbidden', message: $action),
			}
		);
	}//end allow()

	/**
	 * The dashboard the mapper finds for the UUID.
	 *
	 * @return Dashboard
	 */
	private function found(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(9);
		$dashboard->setUuid(self::UUID);
		$this->dashboards->method('findByUuid')->willReturnCallback(
			callback: static fn (string $uuid): Dashboard => match ($uuid) {
				self::UUID => $dashboard,
				default => throw new DoesNotExistException('none'),
			}
		);
		return $dashboard;
	}//end found()

	/**
	 * The versions are the service's envelope for the caller.
	 *
	 * @return void
	 */
	public function testListVersionsIsTheServicesEnvelope(): void {
		$this->allow(allowed: 'dashboard-version.list-versions');
		$dashboard = $this->found();
		$versions  = $this->createMock(originalClassName: DashboardVersionService::class);
		$versions->method('listVersions')->willReturnCallback(
			callback: static fn (Dashboard $asked, string $requestingUser): array => match (true) {
				$asked === $dashboard && $requestingUser === 'alice' => ['versions' => [1]],
				default => [],
			}
		);
		$tools = new McpHistoryTools(gate: $this->gate, validator: new McpArgumentValidator(), dashboards: $this->dashboards, versions: $versions);

		$this->assertSame(expected: ['versions' => [1]], actual: $tools->listVersions(args: ['dashboardUuid' => self::UUID]));
	}//end testListVersionsIsTheServicesEnvelope()

	/**
	 * A non-owner is forbidden and an unknown dashboard is not found.
	 *
	 * @return void
	 */
	public function testListVersionsRefusesAsTheRouteDoes(): void {
		$this->allow(allowed: 'dashboard-version.list-versions');
		$this->found();
		$versions = $this->createMock(originalClassName: DashboardVersionService::class);
		$versions->method('listVersions')->willThrowException(exception: new Exception(DashboardVersionService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN));
		$tools = new McpHistoryTools(gate: $this->gate, validator: new McpArgumentValidator(), dashboards: $this->dashboards, versions: $versions);

		$this->assertSame(expected: 'forbidden', actual: $tools->listVersions(args: ['dashboardUuid' => self::UUID])['error']);
		$this->assertSame(expected: 'not_found', actual: $tools->listVersions(args: ['dashboardUuid' => 'ffffffff-ffff-4fff-8fff-ffffffffffff'])['error']);
		$this->assertSame(expected: 'invalid_arguments', actual: $tools->listVersions(args: ['dashboardUuid' => 'nope'])['error']);
	}//end testListVersionsRefusesAsTheRouteDoes()

	/**
	 * The metadata of a dashboard the caller can see is the service's values.
	 *
	 * @return void
	 */
	public function testGetDashboardMetadataForAVisibleDashboard(): void {
		$this->allow(allowed: 'dashboard-metadata.get-metadata');
		$this->found();
		$this->gate->method('requireView')->willReturnCallback(
			callback: static fn (int $dashboardId): ?array => match ($dashboardId) {
				9 => null,
				default => ['isError' => true],
			}
		);
		$metadata = $this->createMock(originalClassName: MetadataService::class);
		$metadata->method('getMetadataForDashboard')->willReturn(['owner' => 'Team A']);
		$tools = new McpMetadataTools(gate: $this->gate, validator: new McpArgumentValidator(), dashboards: $this->dashboards, metadata: $metadata);

		$this->assertSame(expected: ['metadata' => ['owner' => 'Team A']], actual: $tools->getDashboardMetadata(args: ['dashboardUuid' => self::UUID]));
	}//end testGetDashboardMetadataForAVisibleDashboard()

	/**
	 * A dashboard the caller cannot see yields no metadata.
	 *
	 * @return void
	 */
	public function testGetDashboardMetadataRefusesAnInvisibleDashboard(): void {
		$this->allow(allowed: 'dashboard-metadata.get-metadata');
		$this->found();
		$this->gate->method('requireView')->willReturn(McpArgumentValidator::error(code: 'forbidden', message: 'x'));
		$metadata = $this->createMock(originalClassName: MetadataService::class);
		$metadata->expects($this->never())->method('getMetadataForDashboard');
		$tools = new McpMetadataTools(gate: $this->gate, validator: new McpArgumentValidator(), dashboards: $this->dashboards, metadata: $metadata);

		$this->assertSame(expected: 'forbidden', actual: $tools->getDashboardMetadata(args: ['dashboardUuid' => self::UUID])['error']);
	}//end testGetDashboardMetadataRefusesAnInvisibleDashboard()

	/**
	 * Reactions and pending acknowledgements are the services' answers for the caller.
	 *
	 * @return void
	 */
	public function testEngagementReadsAreTheServicesAnswers(): void {
		$this->allow(allowed: 'dashboard-reaction.get-reactions');
		$this->gate->method('requireUser')->willReturn(null);
		$reactions = $this->createMock(originalClassName: ReactionService::class);
		$reactions->method('getReactionsSummary')->willReturnCallback(
			callback: static fn (string $dashboardUuid, string $userId): array => ['counts' => ['👍' => 2], 'mine' => [$userId], 'for' => $dashboardUuid]
		);
		$acknowledgements = $this->createMock(originalClassName: AcknowledgementService::class);
		$acknowledgements->method('getPending')->willReturnCallback(callback: static fn (string $userId): array => ['items' => [$userId]]);
		$tools = new McpEngagementTools(
			gate: $this->gate,
			validator: new McpArgumentValidator(),
			reactions: $reactions,
			acknowledgements: $acknowledgements
		);

		$this->assertSame(
			expected: ['counts' => ['👍' => 2], 'mine' => ['alice'], 'for' => self::UUID],
			actual: $tools->getReactions(args: ['dashboardUuid' => self::UUID])
		);
		$this->assertSame(expected: ['items' => ['alice']], actual: $tools->listPendingAcknowledgements(args: []));
	}//end testEngagementReadsAreTheServicesAnswers()

	/**
	 * A failing service answers a generic error, without its message.
	 *
	 * @return void
	 */
	public function testAFailingServiceStaysGeneric(): void {
		$this->gate->method('requireUser')->willReturn(null);
		$acknowledgements = $this->createMock(originalClassName: AcknowledgementService::class);
		$acknowledgements->method('getPending')->willThrowException(exception: new \RuntimeException('SQLSTATE secret'));
		$tools = new McpEngagementTools(
			gate: $this->gate,
			validator: new McpArgumentValidator(),
			reactions: $this->createMock(originalClassName: ReactionService::class),
			acknowledgements: $acknowledgements
		);

		$this->assertSame(
			expected: ['isError' => true, 'error' => 'failed', 'message' => 'The operation failed.'],
			actual: $tools->listPendingAcknowledgements(args: [])
		);
	}//end testAFailingServiceStaysGeneric()

	/**
	 * A version is created and restored through the service, for the caller.
	 *
	 * @return void
	 */
	public function testVersionWritesCallTheService(): void {
		$this->gate->method('requireAction')->willReturn(null);
		$dashboard = $this->found();
		$version   = new DashboardVersion();
		$version->setVersionNumber(3);
		$versions = $this->createMock(originalClassName: DashboardVersionService::class);
		$versions->expects($this->once())->method('createExplicitSnapshot')->willReturnCallback(
			callback: static fn (Dashboard $asked, string $requestingUser, ?string $note): DashboardVersion => match (true) {
				$asked === $dashboard && $requestingUser === 'alice' && $note === 'before the demo' => $version,
			}
		);
		$versions->expects($this->once())->method('restoreVersion')->willReturnCallback(
			callback: static fn (Dashboard $asked, int $versionNumber, string $restoringUser): array => match (true) {
				$asked === $dashboard && $versionNumber === 3 && $restoringUser === 'alice' => ['version' => $version, 'snapshot' => ['a' => 1]],
			}
		);
		$tools = new McpHistoryTools(gate: $this->gate, validator: new McpArgumentValidator(), dashboards: $this->dashboards, versions: $versions);

		$this->assertSame(
			expected: ['version' => $version->jsonSerialize()],
			actual: $tools->createDashboardVersion(args: ['dashboardUuid' => self::UUID, 'note' => 'before the demo'])
		);
		$this->assertSame(
			expected: ['version' => $version->jsonSerialize(), 'snapshot' => ['a' => 1]],
			actual: $tools->restoreDashboardVersion(args: ['dashboardUuid' => self::UUID, 'versionNumber' => 3])
		);
	}//end testVersionWritesCallTheService()

	/**
	 * A bad version number or a non-owner changes nothing.
	 *
	 * @return void
	 */
	public function testVersionWritesRefuse(): void {
		$this->allow(allowed: 'dashboard-version.restore-version');
		$this->found();
		$versions = $this->createMock(originalClassName: DashboardVersionService::class);
		$versions->method('restoreVersion')->willThrowException(exception: new Exception(DashboardVersionService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN));
		$versions->expects($this->never())->method('createExplicitSnapshot');
		$tools = new McpHistoryTools(gate: $this->gate, validator: new McpArgumentValidator(), dashboards: $this->dashboards, versions: $versions);

		$restore = static fn (int $versionNumber): array => ['dashboardUuid' => self::UUID, 'versionNumber' => $versionNumber];
		$this->assertSame(expected: 'invalid_arguments', actual: $tools->restoreDashboardVersion(args: $restore(0))['error']);
		$this->assertSame(expected: 'forbidden', actual: $tools->restoreDashboardVersion(args: $restore(2))['error']);
		$this->assertSame(expected: 'forbidden', actual: $tools->createDashboardVersion(args: ['dashboardUuid' => self::UUID])['error']);
	}//end testVersionWritesRefuse()

	/**
	 * Metadata is set only when the caller may edit it, and a bad field is refused.
	 *
	 * @return void
	 */
	public function testSetDashboardMetadata(): void {
		$this->allow(allowed: 'dashboard-metadata.set-metadata');
		$this->found();
		$this->gate->method('requireMetadataEdit')->willReturnCallback(
			callback: static fn (int $dashboardId): ?array => match ($dashboardId) {
				9 => null,
				default => ['isError' => true],
			}
		);
		$metadata = $this->createMock(originalClassName: MetadataService::class);
		$metadata->method('setMetadataForDashboard')->willReturnCallback(
			callback: static fn (string $dashboardUuid, array $keyValues): array => match ($keyValues) {
				['owner' => 'Team B'] => ['owner' => 'Team B'],
				default => throw new InvalidMetadataFieldException('Unknown field color'),
			}
		);
		$tools = new McpMetadataTools(gate: $this->gate, validator: new McpArgumentValidator(), dashboards: $this->dashboards, metadata: $metadata);

		$this->assertSame(
			expected: ['metadata' => ['owner' => 'Team B']],
			actual: $tools->setDashboardMetadata(args: ['dashboardUuid' => self::UUID, 'metadata' => ['owner' => 'Team B']])
		);
		$this->assertSame(
			expected: ['isError' => true, 'error' => 'invalid_arguments', 'message' => 'Unknown field color'],
			actual: $tools->setDashboardMetadata(args: ['dashboardUuid' => self::UUID, 'metadata' => ['color' => 'red']])
		);
		$this->assertSame(expected: 'invalid_arguments', actual: $tools->setDashboardMetadata(args: ['dashboardUuid' => self::UUID])['error']);
	}//end testSetDashboardMetadata()

	/**
	 * A caller who may not edit the metadata changes nothing.
	 *
	 * @return void
	 */
	public function testSetDashboardMetadataRefusesANonEditor(): void {
		$this->allow(allowed: 'dashboard-metadata.set-metadata');
		$this->found();
		$this->gate->method('requireMetadataEdit')->willReturn(McpArgumentValidator::error(code: 'forbidden', message: 'x'));
		$metadata = $this->createMock(originalClassName: MetadataService::class);
		$metadata->expects($this->never())->method('setMetadataForDashboard');
		$tools = new McpMetadataTools(gate: $this->gate, validator: new McpArgumentValidator(), dashboards: $this->dashboards, metadata: $metadata);

		$this->assertSame(
			expected: 'forbidden',
			actual: $tools->setDashboardMetadata(args: ['dashboardUuid' => self::UUID, 'metadata' => ['owner' => 'Team B']])['error']
		);
	}//end testSetDashboardMetadataRefusesANonEditor()

	/**
	 * Reactions are added and removed through the service; a refusal of the
	 * service is a forbidden answer.
	 *
	 * @return void
	 */
	public function testReactionWrites(): void {
		$this->gate->method('requireAction')->willReturn(null);
		$reactions = $this->createMock(originalClassName: ReactionService::class);
		$reactions->method('addReaction')->willReturnCallback(
			callback: static fn (string $dashboardUuid, string $userId, string $emoji): array => ['counts' => [$emoji => 1], 'by' => $userId]
		);
		$reactions->method('removeReaction')->willThrowException(exception: new ReactionsDisabledException('Reactions are off'));
		$tools = new McpEngagementTools(
			gate: $this->gate,
			validator: new McpArgumentValidator(),
			reactions: $reactions,
			acknowledgements: $this->createMock(originalClassName: AcknowledgementService::class)
		);

		$this->assertSame(
			expected: ['counts' => ['x' => 1], 'by' => 'alice'],
			actual: $tools->addReaction(args: ['dashboardUuid' => self::UUID, 'emoji' => 'x'])
		);
		$this->assertSame(
			expected: ['isError' => true, 'error' => 'forbidden', 'message' => 'Reactions are off'],
			actual: $tools->removeReaction(args: ['dashboardUuid' => self::UUID, 'emoji' => 'x'])
		);
		$this->assertSame(expected: 'invalid_arguments', actual: $tools->addReaction(args: ['dashboardUuid' => self::UUID, 'emoji' => ''])['error']);
	}//end testReactionWrites()
}//end class
