<?php

/**
 * RepairStepReportsTest
 *
 * Pins the names the repair steps report to `occ upgrade`, how the action
 * matrix seeding reads a malformed or incomplete seed, and what the baseline
 * step reports for an unreadable seed.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Db\RoleFeaturePermissionMapper;
use OCA\LaunchPad\Db\RoleLayoutDefaultMapper;
use OCA\LaunchPad\Repair\ApplyActionBaseline;
use OCA\LaunchPad\Repair\ImportLaunchpadRegister;
use OCA\LaunchPad\Repair\InitializeActions;
use OCA\LaunchPad\Repair\PurgeOrphanedCascadeData;
use OCA\LaunchPad\Repair\SeedRolePermissions;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Repair-step names, seed decoding and the unreadable-seed report.
 *
 * @covers \OCA\LaunchPad\Repair\ApplyActionBaseline
 * @covers \OCA\LaunchPad\Repair\ImportLaunchpadRegister
 * @covers \OCA\LaunchPad\Repair\InitializeActions
 * @covers \OCA\LaunchPad\Repair\PurgeOrphanedCascadeData
 * @covers \OCA\LaunchPad\Repair\SeedRolePermissions
 */
class RepairStepReportsTest extends TestCase {

	/**
	 * Calls made in the current test.
	 *
	 * @var array<int, array<int, string>>
	 */
	private array $calls = [];

	/**
	 * Every step reports the name `occ upgrade` prints for it.
	 *
	 * @return void
	 */
	public function testEveryStepReportsItsName(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$auth   = $this->createMock(ActionAuthService::class);

		$names = [
			(new ImportLaunchpadRegister(
				container: $this->createMock(ContainerInterface::class),
				appManager: $this->createMock(IAppManager::class),
				logger: $logger
			))->getName(),
			(new PurgeOrphanedCascadeData(db: $this->createMock(IDBConnection::class), logger: $logger))->getName(),
			(new SeedRolePermissions(
				permMapper: $this->createMock(RoleFeaturePermissionMapper::class),
				defMapper: $this->createMock(RoleLayoutDefaultMapper::class),
				logger: $logger
			))->getName(),
			(new InitializeActions(actionAuth: $auth, logger: $logger))->getName(),
		];

		$this->assertSame(
			[
				'Import the LaunchPad register (launchpad register + dashboard schema)',
				'Purge orphaned cascade data from deleted dashboards (C4/REQ-CSC-003)',
				'Seed default role-feature permissions and role-layout defaults',
				'Initialize action-authorization matrix (ADR-023)',
			],
			$names
		);
	}//end testEveryStepReportsItsName()

	/**
	 * A seed that is not JSON is reported, logged and yields no actions.
	 *
	 * @return void
	 */
	public function testMalformedSeedIsReportedAndLogged(): void {
		$result = $this->decodeSeed(raw: '{not json');

		$this->assertNull($result);
		$this->assertSame(
			[
				['warning', 'actions.seed.json invalid JSON: Syntax error'],
				['error', '[launchpad] ADR-023 seed malformed: Syntax error'],
			],
			$this->calls
		);
	}//end testMalformedSeedIsReportedAndLogged()

	/**
	 * A seed without an `actions` object is reported and yields no actions.
	 *
	 * @return void
	 */
	public function testSeedWithoutActionsObjectIsReported(): void {
		$missing = $this->decodeSeed(raw: '{"version": 1}');
		$this->assertNull($missing);

		$scalar = $this->decodeSeed(raw: '{"actions": "admin"}');
		$this->assertNull($scalar);

		$warning = ['warning', 'actions.seed.json missing `actions` object — matrix left empty.'];
		$this->assertSame([$warning, $warning], $this->calls);
	}//end testSeedWithoutActionsObjectIsReported()

	/**
	 * A well-formed seed yields its `actions` object and reports nothing.
	 *
	 * @return void
	 */
	public function testWellFormedSeedYieldsItsActions(): void {
		$result = $this->decodeSeed(raw: '{"actions": {"a.read": ["admin", "@all"]}}');

		$this->assertSame(['a.read' => ['admin', '@all']], $result);
		$this->assertSame([], $this->calls);
	}//end testWellFormedSeedYieldsItsActions()

	/**
	 * The seeded line counts actions in the singular for exactly one.
	 *
	 * @return void
	 */
	public function testSeededLineUsesTheSingularForOneAction(): void {
		$step   = new InitializeActions(
			actionAuth: $this->createMock(ActionAuthService::class),
			logger: $this->createMock(LoggerInterface::class)
		);
		$method = new ReflectionMethod(objectOrMethod: $step, method: 'seededLine');

		$this->assertSame(
			'Seeded action matrix with 1 action (default: admin-only).',
			$method->invokeArgs(object: $step, args: [1])
		);
		$this->assertSame(
			'Seeded action matrix with 0 actions (default: admin-only).',
			$method->invokeArgs(object: $step, args: [0])
		);
	}//end testSeededLineUsesTheSingularForOneAction()

	/**
	 * An unreadable baseline seed is reported to the output and logged.
	 *
	 * @return void
	 */
	public function testUnreadableBaselineSeedIsReportedAndLogged(): void {
		$step = new ApplyActionBaseline(
			actionAuth: $this->createMock(ActionAuthService::class),
			appConfig: $this->createMock(IAppConfig::class),
			logger: $this->recordingLogger()
		);

		(new ReflectionMethod(objectOrMethod: $step, method: 'reportUnreadableSeed'))->invokeArgs(object: $step, args: [$this->recordingOutput()]);

		$this->assertCount(2, $this->calls);
		$this->assertSame(
			['warning', 'actions.seed.json unreadable or declares no baseline actions — matrix left unchanged.'],
			$this->calls[0]
		);
		$this->assertSame('warning', $this->calls[1][0]);
		$this->assertStringStartsWith('[launchpad] ADR-023 baseline seed unreadable at ', $this->calls[1][1]);
		$this->assertStringEndsWith('/actions.seed.json', $this->calls[1][1]);
	}//end testUnreadableBaselineSeedIsReportedAndLogged()

	/**
	 * Decode a seed through the matrix seeding step.
	 *
	 * @param string $raw The seed file contents.
	 *
	 * @return array<string, mixed>|null The decoded actions.
	 */
	private function decodeSeed(string $raw): array|null {
		$step = new InitializeActions(
			actionAuth: $this->createMock(ActionAuthService::class),
			logger: $this->recordingLogger()
		);

		$method = new ReflectionMethod(objectOrMethod: $step, method: 'decodeSeedActions');

		return $method->invokeArgs(object: $step, args: [$this->recordingOutput(), $raw]);
	}//end decodeSeed()

	/**
	 * An output that records its messages.
	 *
	 * @return IOutput
	 */
	private function recordingOutput(): IOutput {
		$output = $this->createMock(IOutput::class);
		foreach (['info', 'warning'] as $method) {
			$output->method($method)->willReturnCallback(
				function (string $message) use ($method): void {
					$this->calls[] = [$method, $message];
				}
			);
		}

		return $output;
	}//end recordingOutput()

	/**
	 * A logger that records its warnings and errors.
	 *
	 * @return LoggerInterface
	 */
	private function recordingLogger(): LoggerInterface {
		$logger = $this->createMock(LoggerInterface::class);
		foreach (['warning', 'error'] as $method) {
			$logger->method($method)->willReturnCallback(
				function (string $message) use ($method): void {
					$this->calls[] = [$method, $message];
				}
			);
		}

		return $logger;
	}//end recordingLogger()
}//end class
