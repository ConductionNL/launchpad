<?php

/**
 * DashboardTreeServiceGoldenTest
 *
 * Records the mapper, transaction and event calls and the outcome of the
 * tree service over a fixed in-memory tree: parent validation (cycles on
 * both sides, depth cap), subtree delete (order, rollback) and the full
 * and filtered trees, so the split of those methods keeps them identical.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class DashboardTreeServiceGoldenTest extends TestCase {

	/**
	 * Parent UUID by dashboard UUID. `r` → `a` → `b` → `c` → `d` is five
	 * deep; `x` is a root with child `y` and a child without a UUID.
	 */
	private const TREE = [
		'r' => null,
		'a' => 'r',
		'b' => 'a',
		'c' => 'b',
		'd' => 'c',
		'x' => null,
		'y' => 'x',
		''  => 'x',
	];

	/** @var list<string> */
	private array $log = [];

	private DashboardTreeService $service;

	private string $failDeleteOf = 'none';

	protected function setUp(): void {
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(function (string $uuid): Dashboard {
			$this->log[] = 'findByUuid ' . $uuid;
			if (array_key_exists($uuid, self::TREE) === false || $uuid === '') {
				throw new DoesNotExistException('missing');
			}

			return $this->node($uuid);
		});
		$mapper->method('findAncestors')->willReturnCallback(function (string $uuid): array {
			$this->log[] = 'findAncestors ' . $uuid;
			$chain = [];
			$cursor = self::TREE[$uuid] ?? null;
			while ($cursor !== null) {
				array_unshift($chain, $this->node($cursor));
				$cursor = self::TREE[$cursor];
			}

			return $chain;
		});
		$mapper->method('findDescendants')->willReturnCallback(function (string $ancestorUuid): array {
			$this->log[] = 'findDescendants ' . $ancestorUuid;
			$out = [];
			$frontier = [$ancestorUuid];
			while ($frontier !== []) {
				$next = [];
				foreach ($frontier as $parent) {
					foreach (self::TREE as $uuid => $p) {
						if ($p === $parent && $uuid !== '') {
							$out[] = $this->node((string)$uuid);
							$next[] = (string)$uuid;
						}
					}
				}

				$frontier = $next;
			}

			return $out;
		});
		$mapper->method('findByParent')->willReturnCallback(function (?string $parentUuid): array {
			$this->log[] = 'findByParent ' . ($parentUuid ?? 'null');
			$out = [];
			foreach (self::TREE as $uuid => $p) {
				if ($p === $parentUuid) {
					$out[] = $this->node((string)$uuid);
				}
			}

			return $out;
		});
		$mapper->method('delete')->willReturnCallback(function (Dashboard $entity): Dashboard {
			$this->log[] = 'delete ' . $entity->getUuid();
			if ($entity->getUuid() === $this->failDeleteOf) {
				throw new \RuntimeException('delete failed');
			}

			return $entity;
		});

		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('deleteByDashboardId')->willReturnCallback(function (int $dashboardId): void {
			$this->log[] = 'deletePlacements ' . $dashboardId;
		});

		$db = $this->createMock(IDBConnection::class);
		foreach (['beginTransaction', 'commit', 'rollBack'] as $call) {
			$db->method($call)->willReturnCallback(function () use ($call): void {
				$this->log[] = $call;
			});
		}

		$events = $this->createMock(IEventDispatcher::class);
		$events->method('dispatchTyped')->willReturnCallback(function (DashboardDeletedEvent $event): void {
			$this->log[] = 'event ' . $event->getDashboardUuid() . ' ' . $event->getOwnerUserId() . ' ' . $event->getType();
		});

		$this->service = new DashboardTreeService(
			dashboardMapper: $mapper,
			placementMapper: $placements,
			db: $db,
			eventDispatcher: $events
		);
	}

	private function node(string $uuid): Dashboard {
		$dash = new Dashboard();
		$dash->setUuid($uuid);
		$dash->setName('N' . $uuid);
		$dash->setSlug('s' . $uuid);
		$dash->setSortOrder(strlen($uuid));
		if ($uuid === 'a') {
			$dash->setId(11);
			$dash->setUserId('alice');
			$dash->setType('group_shared');
		}

		return $dash;
	}

	/**
	 * The outcome of a call and the calls it made.
	 *
	 * @param callable $call The call.
	 *
	 * @return array{0: mixed, 1: list<string>}
	 */
	private function record(callable $call): array {
		$this->log = [];
		try {
			$outcome = $call();
		} catch (\Throwable $e) {
			$outcome = $e::class . ': ' . $e->getMessage();
		}

		return [$outcome, $this->log];
	}

	public function testValidateParent(): void {
		$validate = fn (?string $moving, ?string $parent) => $this->record(
			fn () => $this->service->validateParent(movingUuid: $moving, newParentUuid: $parent)
		);

		$this->assertSame([null, []], $validate('a', null));
		$this->assertSame([null, []], $validate('a', ''));
		$this->assertSame(['InvalidArgumentException: ' . DashboardTreeService::ERR_CYCLE_DETECTED, []], $validate('a', 'a'));
		$this->assertSame(
			['InvalidArgumentException: ' . DashboardTreeService::ERR_PARENT_NOT_FOUND, ['findByUuid nope']],
			$validate('a', 'nope')
		);
		$this->assertSame(
			['InvalidArgumentException: ' . DashboardTreeService::ERR_CYCLE_DETECTED, ['findByUuid c', 'findAncestors c']],
			$validate('a', 'c')
		);
		$this->assertSame(
			['InvalidArgumentException: ' . DashboardTreeService::ERR_MAX_DEPTH, [
				'findByUuid d', 'findAncestors d', 'findDescendants x', 'findAncestors d', 'findByParent x', 'findByParent y',
			]],
			$validate('x', 'd')
		);
		$this->assertSame(
			[null, ['findByUuid r', 'findAncestors r', 'findDescendants x', 'findAncestors r', 'findByParent x', 'findByParent y']],
			$validate('x', 'r')
		);
		$this->assertSame(
			['InvalidArgumentException: ' . DashboardTreeService::ERR_MAX_DEPTH, [
				'findByUuid x', 'findAncestors x', 'findDescendants r', 'findAncestors x',
				'findByParent r', 'findByParent a', 'findByParent b', 'findByParent c', 'findByParent d',
			]],
			$validate('r', 'x')
		);
		$this->assertSame([null, ['findByUuid c', 'findAncestors c']], $validate(null, 'c'));
		$this->assertSame(['InvalidArgumentException: ' . DashboardTreeService::ERR_MAX_DEPTH, ['findByUuid d', 'findAncestors d']], $validate(null, 'd'));
	}

	public function testDeleteSubtree(): void {
		$this->assertSame(
			[4, [
				'beginTransaction', 'findDescendants a',
				'delete d', 'event d alice user',
				'delete c', 'event c alice user',
				'delete b', 'event b alice user',
				'deletePlacements 11', 'delete a', 'event a alice group_shared',
				'commit',
			]],
			$this->record(fn () => $this->service->deleteSubtree(dashboard: $this->node('a')))
		);

		$this->failDeleteOf = 'c';
		$this->assertSame(
			['RuntimeException: delete failed', [
				'beginTransaction', 'findDescendants a', 'delete d', 'event d alice user', 'delete c', 'rollBack',
			]],
			$this->record(fn () => $this->service->deleteSubtree(dashboard: $this->node('a')))
		);

		$this->failDeleteOf = 'none';
		$orphan = new Dashboard();
		$this->assertSame(
			[1, ['beginTransaction', 'delete ', 'commit']],
			$this->record(fn () => $this->service->deleteSubtree(dashboard: $orphan))
		);
	}

	public function testTrees(): void {
		[$full, $calls] = $this->record(fn () => $this->service->getFullTree());
		$this->assertSame(
			['findByParent null', 'findByParent r', 'findByParent a', 'findByParent b', 'findByParent c', 'findByParent x', 'findByParent y'],
			$calls
		);
		$this->assertSame(
			'[{"uuid":"r","name":"Nr","slug":"sr","sortOrder":1,"children":[{"uuid":"a","name":"Na","slug":"sa","sortOrder":1,'
			. '"children":[{"uuid":"b","name":"Nb","slug":"sb","sortOrder":1,"children":[{"uuid":"c","name":"Nc","slug":"sc",'
			. '"sortOrder":1,"children":[{"uuid":"d","name":"Nd","slug":"sd","sortOrder":1,"children":[]}]}]}]}]},'
			. '{"uuid":"x","name":"Nx","slug":"sx","sortOrder":1,"children":[{"uuid":"y","name":"Ny","slug":"sy","sortOrder":1,'
			. '"children":[]},{"uuid":"","name":"N","slug":"s","sortOrder":0,"children":[]}]}]',
			json_encode($full)
		);

		[$filtered, $calls] = $this->record(
			fn () => $this->service->getFilteredTree(visibleUuids: ['r' => true, 'b' => true, 'x' => true, 'y' => true, '' => true])
		);
		$this->assertSame(['findByParent null', 'findByParent r', 'findByParent x', 'findByParent y'], $calls);
		$this->assertSame(
			'[{"uuid":"r","name":"Nr","slug":"sr","sortOrder":1,"children":[]},'
			. '{"uuid":"x","name":"Nx","slug":"sx","sortOrder":1,"children":[{"uuid":"y","name":"Ny","slug":"sy","sortOrder":1,"children":[]}]}]',
			json_encode($filtered)
		);

		[$deep, $calls] = $this->record(
			fn () => $this->service->getFilteredTree(visibleUuids: array_fill_keys(['r', 'a', 'b', 'c', 'd'], true))
		);
		$this->assertSame(
			['findByParent null', 'findByParent r', 'findByParent a', 'findByParent b', 'findByParent c'],
			$calls
		);
	}
}
