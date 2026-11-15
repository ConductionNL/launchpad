<?php

/**
 * DashboardDeleteCommandConfirmTest
 *
 * Pins the interactive confirmation of `launchpad:dashboard:delete` and the
 * DashboardDeletedEvent it dispatches on a non-cascading delete.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\LaunchPad\Command\DashboardDeleteCommand;
use OCA\LaunchPad\Command\DeleteCommandStores;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Service\CommandService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Confirmation and event dispatch of the dashboard delete command.
 *
 * @covers \OCA\LaunchPad\Command\DashboardDeleteCommand
 * @uses   \OCA\LaunchPad\Command\CommandBase
 * @uses   \OCA\LaunchPad\Command\CommandStreams
 * @uses   \OCA\LaunchPad\Command\DeleteCommandStores
 * @uses   \OCA\LaunchPad\Service\CommandService
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Event\DashboardDeletedEvent
 */
class DashboardDeleteCommandConfirmTest extends TestCase {

	/**
	 * Ordered record of writes and dispatches.
	 *
	 * @var list<string>
	 */
	private array $writes = [];

	/**
	 * Events dispatched.
	 *
	 * @var list<DashboardDeletedEvent>
	 */
	private array $events = [];

	/**
	 * The command over recording stores, for a leaf dashboard.
	 *
	 * @param string|null $type       The dashboard type.
	 * @param bool        $dispatcher Whether an event dispatcher is wired.
	 *
	 * @return CommandTester
	 */
	private function tester(?string $type = Dashboard::TYPE_GROUP_SHARED, bool $dispatcher = true): CommandTester {
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($type): Dashboard {
				$dashboard = new Dashboard();
				$dashboard->setId(12);
				$dashboard->setUuid($uuid);
				$dashboard->setName('Quarterly');
				$dashboard->setUserId('carol');
				$dashboard->setType($type);
				return $dashboard;
			}
		);
		$mapper->method('countChildrenByParent')->willReturn(0);
		$mapper->method('delete')->willReturnCallback(
			function (Dashboard $dashboard): Dashboard {
				$this->writes[] = 'delete ' . $dashboard->getUuid();
				return $dashboard;
			}
		);
		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('deleteByDashboardId')->willReturnCallback(
			function (int $id): int {
				$this->writes[] = 'placements ' . $id;
				return 0;
			}
		);

		$events = $this->createMock(IEventDispatcher::class);
		$events->method('dispatchTyped')->willReturnCallback(
			function (DashboardDeletedEvent $event): void {
				$this->writes[] = 'event';
				$this->events[] = $event;
			}
		);

		return new CommandTester(
			new DashboardDeleteCommand(
				commandService: new CommandService(new NullLogger()),
				userSession: $this->createMock(IUserSession::class),
				stores: new DeleteCommandStores(
					dashboardMapper: $mapper,
					placementMapper: $placements,
					treeService: $this->createMock(DashboardTreeService::class)
				),
				eventDispatcher: $dispatcher === true ? $events : null,
			)
		);
	}//end tester()

	/**
	 * Answering no aborts with exit 2 and deletes nothing.
	 *
	 * @return void
	 */
	public function testDecliningTheConfirmationAborts(): void {
		$tester = $this->tester();
		$tester->setInputs(['n']);

		$exit = $tester->execute(['uuid' => 'q-1'], ['interactive' => true, 'capture_stderr_separately' => true]);

		$this->assertSame(CommandService::EXIT_INVALID_ARGS, $exit);
		$this->assertSame('', $tester->getDisplay());
		$this->assertSame(
			'Delete dashboard "Quarterly" (q-1)? [y/N] Deletion aborted by user.',
			trim($tester->getErrorOutput())
		);
		$this->assertSame([], $this->writes);
	}//end testDecliningTheConfirmationAborts()

	/**
	 * An empty answer takes the default, which is no.
	 *
	 * @return void
	 */
	public function testTheDefaultAnswerIsNo(): void {
		$tester = $this->tester();
		$tester->setInputs(['']);

		$exit = $tester->execute(['uuid' => 'q-1'], ['interactive' => true]);

		$this->assertSame(CommandService::EXIT_INVALID_ARGS, $exit);
		$this->assertSame([], $this->writes);
	}//end testTheDefaultAnswerIsNo()

	/**
	 * Confirming deletes placements then the row, then announces the deletion
	 * with the dashboard's identity.
	 *
	 * @return void
	 */
	public function testConfirmingDeletesAndAnnouncesTheDashboard(): void {
		$tester = $this->tester();
		$tester->setInputs(['y']);

		$exit = $tester->execute(['uuid' => 'q-1'], ['interactive' => true]);

		$this->assertSame(CommandService::EXIT_SUCCESS, $exit);
		$this->assertSame(['placements 12', 'delete q-1', 'event'], $this->writes);
		$event = $this->events[0];
		$this->assertSame(
			['q-1', 'carol', Dashboard::TYPE_GROUP_SHARED, 12],
			[$event->getDashboardUuid(), $event->getOwnerUserId(), $event->getType(), $event->getDashboardId()]
		);
	}//end testConfirmingDeletesAndAnnouncesTheDashboard()

	/**
	 * An untyped dashboard is announced as a user dashboard; without a
	 * dispatcher the delete still happens and nothing is announced.
	 *
	 * @return void
	 */
	public function testEventTypeFallbackAndMissingDispatcher(): void {
		$this->tester(type: null)->execute(['uuid' => 'q-2', '--no-interaction' => true], ['interactive' => false]);
		$this->assertSame(Dashboard::TYPE_USER, $this->events[0]->getType());

		$this->writes = [];
		$this->events = [];
		$exit = $this->tester(dispatcher: false)->execute(['uuid' => 'q-3', '--no-interaction' => true], ['interactive' => false]);

		$this->assertSame(CommandService::EXIT_SUCCESS, $exit);
		$this->assertSame(['placements 12', 'delete q-3'], $this->writes);
		$this->assertSame([], $this->events);
	}//end testEventTypeFallbackAndMissingDispatcher()
}//end class
