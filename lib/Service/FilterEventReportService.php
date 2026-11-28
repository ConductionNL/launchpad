<?php

/**
 * FilterEventReportService
 *
 * The aggregate "most-used drill-down paths" report over a dashboard's
 * `filter_event` objects (drill-down-cross-widget-filter REQ-CWF-010). The
 * rows hold dimensions and operators, never filter values, and the report
 * only counts paths, so it names no value either.
 *
 * Who sees what: the dashboard's owner and administrators get the paths of
 * every user; anyone else who may view the dashboard gets only the paths of
 * their own events; anyone else gets nothing (the dashboard reads as absent).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-010-telemetry-of-filter-usage-is-opt-in-and-aggregated
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\DashboardMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Builds the drill-down path report of one dashboard.
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
 */
class FilterEventReportService {

	/**
	 * The register the events live in.
	 */
	public const REGISTER = 'launchpad';

	/**
	 * The schema of the events.
	 */
	public const SCHEMA = 'filter_event';

	/**
	 * The most events one report reads.
	 */
	public const MAX_EVENTS = 5000;

	/**
	 * The most paths one report returns.
	 */
	public const MAX_PATHS = 100;

	/**
	 * The separator between the steps of a path.
	 */
	public const STEP_SEPARATOR = ' → ';

	/**
	 * The OpenRegister service the events are read through.
	 */
	private const OBJECT_SERVICE = 'OCA\OpenRegister\Service\ObjectService';

	/**
	 * Constructor.
	 *
	 * @param DashboardMapper    $dashboardMapper   Finds the dashboard.
	 * @param PermissionService  $permissionService Whether the caller may view it.
	 * @param RoleService        $roleService       LaunchPad's admin role.
	 * @param IGroupManager      $groupManager      Nextcloud's admin group.
	 * @param ContainerInterface $container         Loads OpenRegister's ObjectService lazily.
	 * @param LoggerInterface    $logger            Logs an unreadable register.
	 */
	public function __construct(
		private readonly DashboardMapper $dashboardMapper,
		private readonly PermissionService $permissionService,
		private readonly RoleService $roleService,
		private readonly IGroupManager $groupManager,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The report of one dashboard for one caller.
	 *
	 * @param string $dashboardUuid The dashboard's uuid.
	 * @param string $userId        The caller.
	 * @param int    $limit         The most paths returned (1 to MAX_PATHS).
	 *
	 * @return array{dashboardId: string, scope: string, events: int, paths: list<array{path: string, count: int}>}
	 *
	 * @throws DoesNotExistException When the dashboard does not exist or the caller may not view it.
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
	 */
	public function report(string $dashboardUuid, string $userId, int $limit=20): array {
		$dashboard = $this->dashboardMapper->findByUuid(uuid: $dashboardUuid);

		$everyone = $dashboard->getUserId() === $userId || $this->isAdmin(userId: $userId) === true;
		if ($everyone === false
			&& $this->permissionService->canViewDashboard(userId: $userId, dashboardId: (int) $dashboard->getId()) === false
		) {
			throw new DoesNotExistException('Dashboard not found');
		}

		$onlyUser = null;
		$scope    = 'all';
		if ($everyone === false) {
			$onlyUser = $userId;
			$scope    = 'own';
		}

		$events = $this->loadEvents(dashboardUuid: $dashboardUuid, onlyUser: $onlyUser);
		$paths  = self::aggregatePaths(events: $events);

		return [
			'dashboardId' => $dashboardUuid,
			'scope' => $scope,
			'events' => count($events),
			'paths' => array_slice($paths, 0, max(1, min(self::MAX_PATHS, $limit))),
		];
	}//end report()

	/**
	 * Count drill-down paths in a set of events.
	 *
	 * Per user, in time order, `apply` events extend the current path with
	 * the dimensions of their delta (a dimension already last on the path is
	 * not repeated); a `reset` ends the path; a `bookmark` ends it and starts
	 * a new one from the bookmarked dimensions. A `share` changes nothing.
	 * Every ended path with at least one step is counted once. The result is
	 * ordered by count, most used first, then by path.
	 *
	 * @param array<int, array<string, mixed>> $events The `filter_event` rows.
	 *
	 * @return list<array{path: string, count: int}>
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
	 */
	public static function aggregatePaths(array $events): array {
		$counts = [];
		foreach (self::groupByUser(events: $events) as $userEvents) {
			self::countUserPaths(counts: $counts, events: $userEvents);
		}

		$paths = [];
		foreach ($counts as $path => $count) {
			$paths[] = ['path' => (string) $path, 'count' => $count];
		}

		usort(
			$paths,
			static fn (array $a, array $b): int => [$b['count'], $a['path']] <=> [$a['count'], $b['path']]
		);

		return $paths;
	}//end aggregatePaths()

	/**
	 * The events with an event type, grouped by user.
	 *
	 * @param array<int, mixed> $events The `filter_event` rows.
	 *
	 * @return array<string, list<array<string, mixed>>>
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
	 */
	private static function groupByUser(array $events): array {
		$byUser = [];
		foreach ($events as $event) {
			if (is_array($event) === true && is_string($event['eventType'] ?? null) === true) {
				$byUser[(string) ($event['userId'] ?? '')][] = $event;
			}
		}

		return $byUser;
	}//end groupByUser()

	/**
	 * Count the paths of one user's events.
	 *
	 * @param array<string, int>         $counts The counts so far, by path.
	 * @param list<array<string, mixed>> $events One user's events.
	 *
	 * @return void
	 */
	private static function countUserPaths(array &$counts, array $events): void {
		usort(
			$events,
			static fn (array $a, array $b): int => strcmp((string) ($a['timestamp'] ?? ''), (string) ($b['timestamp'] ?? ''))
		);

		$steps = [];
		foreach ($events as $event) {
			$type = $event['eventType'];
			if (in_array($type, ['reset', 'bookmark'], true) === true) {
				self::countPath(counts: $counts, steps: $steps);
				$steps = [];
			}

			if (in_array($type, ['apply', 'bookmark'], true) === true) {
				$steps = self::extend(steps: $steps, delta: $event['filterDelta'] ?? []);
			}
		}

		self::countPath(counts: $counts, steps: $steps);
	}//end countUserPaths()

	/**
	 * The path's steps extended with a delta's dimensions.
	 *
	 * @param list<string> $steps The path so far.
	 * @param mixed        $delta The event's `filterDelta`.
	 *
	 * @return list<string>
	 */
	private static function extend(array $steps, mixed $delta): array {
		$items = [];
		if (is_array($delta) === true) {
			$items = $delta;
		}

		foreach ($items as $item) {
			$dimension = self::dimensionOf(item: $item);
			if ($dimension !== null && end($steps) !== $dimension) {
				$steps[] = $dimension;
			}
		}

		return $steps;
	}//end extend()

	/**
	 * The dimension a delta item names.
	 *
	 * @param mixed $item One item of a `filterDelta`.
	 *
	 * @return string|null The dimension, or null when the item names none.
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
	 */
	private static function dimensionOf(mixed $item): ?string {
		$dimension = null;
		if (is_array($item) === true) {
			$dimension = $item['dimension'] ?? null;
		}

		if (is_string($dimension) === false || $dimension === '') {
			return null;
		}

		return $dimension;
	}//end dimensionOf()

	/**
	 * Count one ended path.
	 *
	 * @param array<string, int> $counts The counts so far, by path.
	 * @param list<string>       $steps  The ended path.
	 *
	 * @return void
	 */
	private static function countPath(array &$counts, array $steps): void {
		if ($steps === []) {
			return;
		}

		$path          = implode(self::STEP_SEPARATOR, $steps);
		$counts[$path] = ($counts[$path] ?? 0) + 1;
	}//end countPath()

	/**
	 * Whether the caller is a Nextcloud or LaunchPad administrator.
	 *
	 * @param string $userId The caller.
	 *
	 * @return bool
	 */
	private function isAdmin(string $userId): bool {
		return $this->groupManager->isAdmin(userId: $userId) === true
			|| $this->roleService->isAdmin(userId: $userId) === true;
	}//end isAdmin()

	/**
	 * The dashboard's events, optionally of one user only.
	 *
	 * Access was decided by report() before this runs, so the read skips
	 * OpenRegister's per-object RBAC: an owner must count the events other
	 * users wrote. An unreadable register (OpenRegister off, schema not yet
	 * imported) reads as no events.
	 *
	 * @param string      $dashboardUuid The dashboard's uuid.
	 * @param string|null $onlyUser      Only this user's events, or everyone's.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function loadEvents(string $dashboardUuid, ?string $onlyUser): array {
		$filters = [
			'register' => self::REGISTER,
			'schema' => self::SCHEMA,
			'dashboardId' => $dashboardUuid,
		];
		if ($onlyUser !== null) {
			$filters['userId'] = $onlyUser;
		}

		$events = [];
		foreach ($this->findEvents(filters: $filters) as $item) {
			$data = self::extractData(item: $item);
			// The filter is the store's; the check here keeps the report
			// right whatever the store matched.
			if (self::belongs(data: $data, dashboardUuid: $dashboardUuid, onlyUser: $onlyUser) === true) {
				$events[] = $data;
			}
		}

		return $events;
	}//end loadEvents()

	/**
	 * The register's rows for a filter, or none when it cannot be read.
	 *
	 * @param array<string, string> $filters The OpenRegister filters.
	 *
	 * @return array<int|string, mixed>
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
	 */
	private function findEvents(array $filters): array {
		try {
			$objectService = $this->container->get(self::OBJECT_SERVICE);
			$results       = $objectService->findAll(
				config: ['filters' => $filters, 'limit' => self::MAX_EVENTS],
				_rbac: false
			);
		} catch (Throwable $e) {
			$this->logger->warning(
				message: 'LaunchPad: the filter events could not be read: ' . $e->getMessage(),
				context: ['app' => 'launchpad']
			);
			return [];
		}

		if (is_array($results) === false) {
			return [];
		}

		return $results;
	}//end findEvents()

	/**
	 * Whether a row is of the dashboard, and of the one user when asked.
	 *
	 * @param array<string, mixed> $data          The row.
	 * @param string               $dashboardUuid The dashboard's uuid.
	 * @param string|null          $onlyUser      Only this user's rows, or everyone's.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
	 */
	private static function belongs(array $data, string $dashboardUuid, ?string $onlyUser): bool {
		if (($data['dashboardId'] ?? null) !== $dashboardUuid) {
			return false;
		}

		return $onlyUser === null || ($data['userId'] ?? null) === $onlyUser;
	}//end belongs()

	/**
	 * A plain array from one OpenRegister result.
	 *
	 * @param mixed $item An array, or an entity with getObject() or jsonSerialize().
	 *
	 * @return array<string, mixed>
	 */
	private static function extractData(mixed $item): array {
		$data = $item;
		if (is_object($item) === true) {
			$data = self::objectData(item: $item);
		}

		if (is_array($data) === false) {
			return [];
		}

		return $data;
	}//end extractData()

	/**
	 * The data of an OpenRegister entity.
	 *
	 * @param object $item An entity with getObject() or jsonSerialize().
	 *
	 * @return mixed The data, or null for another object.
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
	 */
	private static function objectData(object $item): mixed {
		if (method_exists($item, 'getObject') === true) {
			return $item->getObject();
		}

		if (method_exists($item, 'jsonSerialize') === true) {
			return $item->jsonSerialize();
		}

		return null;
	}//end objectData()
}//end class
