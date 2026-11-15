<?php

/**
 * Dashboard dump commands golden test
 *
 * Pins what `launchpad:dashboard:show` and `launchpad:dashboard:debug-share`
 * print and return for every branch: invalid UUID, unknown dashboard, the
 * JSON envelope, the pretty-printed payload and `--quiet`.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\LaunchPad\Command\DashboardDebugShareCommand;
use OCA\LaunchPad\Command\DashboardShowCommand;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\CommandService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The printed output of the two dump commands, case by case.
 */
class DashboardDumpCommandsGoldenTest extends TestCase {

	private const UUID = 'a1b2c3d4-e5f6-4789-abcd-ef1234567890';

	/**
	 * A dashboard mapper that knows one dashboard.
	 *
	 * @return DashboardMapper
	 */
	private function dashboards(): DashboardMapper {
		$dashboard = $this->getMockBuilder(Dashboard::class)->onlyMethods(['jsonSerialize'])->getMock();
		$dashboard->setId(7);
		$dashboard->method('jsonSerialize')->willReturn(['id' => 7, 'name' => 'Home']);

		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($dashboard): Dashboard {
				if ($uuid !== self::UUID) {
					throw new DoesNotExistException('missing');
				}

				return $dashboard;
			}
		);

		return $mapper;
	}//end dashboards()

	/**
	 * Run a command and capture exit code and display.
	 *
	 * @param object               $command The command.
	 * @param array<string, mixed> $input   The input.
	 *
	 * @return array{0: int, 1: string}
	 */
	private static function runCommand(object $command, array $input): array {
		$tester = new CommandTester($command);
		$code = $tester->execute($input);
		return [$code, $tester->getDisplay()];
	}//end runCommand()

	public function testShowCommandOutput(): void {
		$placement = $this->createMock(WidgetPlacement::class);
		$placement->method('jsonSerialize')->willReturn(['id' => 1, 'widgetId' => 'w/x']);
		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('findByDashboardId')->with(7)->willReturn([$placement]);

		$command = new DashboardShowCommand(
			new CommandService(new NullLogger()),
			$this->createMock(IUserSession::class),
			$this->dashboards(),
			$placements
		);

		$pretty = "{\n    \"dashboard\": {\n        \"id\": 7,\n        \"name\": \"Home\"\n    },\n"
			."    \"widgets\": [\n        {\n            \"id\": 1,\n            \"widgetId\": \"w/x\"\n        }\n    ]\n}\n";
		$this->assertSame([0, $pretty], self::runCommand($command, ['uuid' => self::UUID]));
		$this->assertSame([0, ''], self::runCommand($command, ['uuid' => self::UUID, '--quiet' => true]));

		[$code, $json] = self::runCommand($command, ['uuid' => self::UUID, '--json' => true]);
		$this->assertSame(0, $code);
		$this->assertSame(
			['data' => ['dashboard' => ['id' => 7, 'name' => 'Home'], 'widgets' => [['id' => 1, 'widgetId' => 'w/x']]]],
			array_intersect_key(json_decode($json, true), ['data' => 1])
		);

		[$code, $invalid] = self::runCommand($command, ['uuid' => 'nope', '--json' => true]);
		$this->assertSame(CommandService::EXIT_INVALID_ARGS, $code);
		$this->assertStringContainsString('INVALID_ARGUMENT', $invalid);
		$this->assertStringContainsString("Invalid UUID format: 'nope'", $invalid);
		$this->assertStringContainsString('"providedValue":"nope"', $invalid);

		[$code, $missing] = self::runCommand($command, ['uuid' => 'b1b2c3d4-e5f6-4789-abcd-ef1234567890', '--json' => true]);
		$this->assertNotSame(0, $code);
		$this->assertStringContainsString('b1b2c3d4-e5f6-4789-abcd-ef1234567890', $missing);
	}

	public function testDebugShareCommandOutput(): void {
		$share = $this->createMock(DashboardShare::class);
		$share->method('jsonSerialize')->willReturn(['shareType' => 'user', 'shareWith' => 'bob']);
		$shares = $this->createMock(DashboardShareMapper::class);
		$shares->method('findByDashboardId')->with(7)->willReturn([$share]);

		$command = new DashboardDebugShareCommand(
			new CommandService(new NullLogger()),
			$this->createMock(IUserSession::class),
			$this->dashboards(),
			$shares
		);

		$pretty = "{\n    \"uuid\": \"".self::UUID."\",\n    \"shares\": [\n        {\n            \"shareType\": \"user\",\n"
			."            \"shareWith\": \"bob\"\n        }\n    ],\n    \"locked\": false,\n    \"lockedBy\": null,\n"
			."    \"lockedAt\": null,\n    \"versionCount\": 0,\n    \"viewCount\": 0\n}\n";
		$this->assertSame([0, $pretty], self::runCommand($command, ['uuid' => self::UUID]));
		$this->assertSame([0, ''], self::runCommand($command, ['uuid' => self::UUID, '--quiet' => true]));

		[$code, $json] = self::runCommand($command, ['uuid' => self::UUID, '--json' => true]);
		$this->assertSame(0, $code);
		$this->assertSame(['shareType' => 'user', 'shareWith' => 'bob'], json_decode($json, true)['data']['shares'][0]);

		[$code, $missing] = self::runCommand($command, ['uuid' => 'other', '--json' => true]);
		$this->assertNotSame(0, $code);
		$this->assertStringContainsString('other', $missing);
	}
}
