<?php

/**
 * DashboardDeleteCommandTest
 *
 * Pins what `launchpad:dashboard:delete` deletes and prints for a leaf
 * dashboard, a parent with and without --cascade, and in JSON mode.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\LaunchPad\Command\DashboardDeleteCommand;
use OCA\LaunchPad\Command\DeleteCommandStores;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\CommandService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The delete command's successful paths.
 */
class DashboardDeleteCommandTest extends TestCase {

	/**
	 * Every case exits, deletes and prints as recorded.
	 *
	 * @return void
	 */
	public function testDeletionsMatchTheRecording(): void {
		$cases = [
			'leaf' => ['leaf', []],
			'leaf json' => ['leaf', ['--json' => true]],
			'parent cascade' => ['parent', ['--cascade' => true]],
			'parent cascade json' => ['parent', ['--cascade' => true, '--json' => true]],
			'parent without cascade' => ['parent', []],
		];

		$actual = [];
		foreach ($cases as $name => [$uuid, $flags]) {
			$writes = [];
			$mapper = $this->createMock(DashboardMapper::class);
			$mapper->method('findByUuid')->willReturnCallback(
				static function (string $uuid): Dashboard {
					$dashboard = new Dashboard();
					$dashboard->setId(4);
					$dashboard->setUuid($uuid);
					$dashboard->setName('Board');
					return $dashboard;
				}
			);
			$mapper->method('countChildrenByParent')->willReturnCallback(static fn (string $uuid): int => ($uuid === 'parent') ? 2 : 0);
			$mapper->method('delete')->willReturnCallback(
				static function (Dashboard $dashboard) use (&$writes): Dashboard {
					$writes[] = 'delete '.$dashboard->getUuid();
					return $dashboard;
				}
			);
			$placements = $this->createMock(WidgetPlacementMapper::class);
			$placements->method('deleteByDashboardId')->willReturnCallback(
				static function (int $id) use (&$writes): int {
					$writes[] = 'placements '.$id;
					return 1;
				}
			);
			$tree = $this->createMock(DashboardTreeService::class);
			$tree->method('deleteSubtree')->willReturnCallback(
				static function (Dashboard $dashboard) use (&$writes): int {
					$writes[] = 'subtree '.$dashboard->getUuid();
					return 3;
				}
			);

			$command = new DashboardDeleteCommand(
				commandService: new CommandService(new NullLogger()),
				userSession: $this->createMock(IUserSession::class),
				stores: new DeleteCommandStores(dashboardMapper: $mapper, placementMapper: $placements, treeService: $tree)
			);
			$tester = new CommandTester($command);
			$exit   = $tester->execute(['uuid' => $uuid, '--no-interaction' => true] + $flags, ['interactive' => false]);

			$actual[$name] = ['exit' => $exit, 'writes' => $writes, 'output' => trim($tester->getDisplay())];
		}

		$fixture = __DIR__.'/fixtures/dashboard-delete-command.json';
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents($fixture, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(json_decode((string) file_get_contents($fixture), true), $actual);
	}//end testDeletionsMatchTheRecording()
}//end class
