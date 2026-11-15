<?php

/**
 * TreeListenerGoldenTest
 *
 * Records what the tree listener does on a dashboard delete: one cascade
 * event per child with a UUID (owner and type falling back to the deleted
 * parent's), the debug line, the warning when the lookup fails, and
 * nothing for other events.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Listener;

use DateTimeImmutable;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\TreeListener;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventDispatcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TreeListenerGoldenTest extends TestCase {

	/** @var list<string> */
	private array $log = [];

	private function listener(bool $failing): TreeListener {
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByParent')->willReturnCallback(function (?string $parentUuid) use ($failing): array {
			$this->log[] = 'findByParent ' . $parentUuid;
			if ($failing === true) {
				throw new \RuntimeException('db down');
			}

			$own = new Dashboard();
			$own->setId(7);
			$own->setUuid('c1');
			$own->setUserId('carol');
			$own->setType('group_shared');
			$inherit = new Dashboard();
			$inherit->setId(8);
			$inherit->setUuid('c2');
			$nameless = new Dashboard();
			$nameless->setId(9);
			return [$own, $nameless, $inherit];
		});

		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willReturnCallback(function (DashboardDeletedEvent $event): void {
			$this->log[] = 'event ' . $event->getDashboardUuid() . ' ' . $event->getOwnerUserId() . ' ' . $event->getType()
				. ' ' . $event->getDeletedAt()->format('Y-m-d') . ' ' . (string)$event->getDashboardId();
		});

		$logger = $this->createMock(LoggerInterface::class);
		foreach (['debug', 'warning'] as $level) {
			$logger->method($level)->willReturnCallback(function (string $message, array $context) use ($level): void {
				$this->log[] = $level . ' ' . $message . ' ' . json_encode($context);
			});
		}

		return new TreeListener(dashboardMapper: $mapper, dispatcher: $dispatcher, logger: $logger);
	}

	private static function deleted(): DashboardDeletedEvent {
		return new DashboardDeletedEvent(
			dashboardUuid: 'p1',
			ownerUserId: 'alice',
			type: 'user',
			deletedAt: new DateTimeImmutable('2026-03-04 05:06:07'),
			dashboardId: 1
		);
	}

	public function testCascade(): void {
		$this->listener(false)->handle(self::deleted());
		$this->assertSame(
			[
				'findByParent p1',
				'event c1 carol group_shared 2026-03-04 7',
				'event c2 alice user 2026-03-04 8',
				'debug launchpad TreeListener: dispatched delete for 3 children of dashboard p1 {"app":"launchpad"}',
			],
			$this->log
		);
	}

	public function testFailureAndOtherEvents(): void {
		$this->listener(true)->handle(self::deleted());
		$this->listener(false)->handle(new Event());
		$this->assertSame(
			['findByParent p1', 'warning launchpad TreeListener: failed for dashboard p1: db down {"app":"launchpad"}'],
			$this->log
		);
	}
}
