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
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-010-telemetry-of-filter-usage-is-opt-in-and-aggregated
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
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-16
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
	 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-16
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
	 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-16
	 */
	public static function aggregatePaths(array $events): array {
		$byUser = [];
		foreach ($events as $event) {
			if (is_array($event) === false || is_string($event['eventType'] ?? null) === false) {
				continue;
			}

			$byUser[(string) ($event['userId'] ?? '')][] = $event;
		}

		$counts = [];
		foreach ($byUser as $userEvents) {
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
			if ($type === 'reset' || $type === 'bookmark') {
				self::countPath(counts: $counts, steps: $steps);
				$steps = [];
			}

			if ($type === 'apply' || $type === 'bookmark') {
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
		if (is_array($delta) === false) {
			return $steps;
		}

		foreach ($delta as $item) {
			if (is_array($item) === false) {
				continue;
			}

			$dimension = $item['dimension'] ?? null;
			if (is_string($dimension) === false || $dimension === '') {
				continue;
			}

			if (end($steps) !== $dimension) {
				$steps[] = $dimension;
			}
		}

		return $steps;
	}//end extend()

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

		$events = [];
		foreach ($results as $item) {
			$data = self::extractData(item: $item);
			// The filter above is the store's; the checks here keep the report
			// right whatever the store matched.
			if (($data['dashboardId'] ?? null) !== $dashboardUuid
				|| ($onlyUser !== null && ($data['userId'] ?? null) !== $onlyUser)
			) {
				continue;
			}

			$events[] = $data;
		}

		return $events;
	}//end loadEvents()

	/**
	 * A plain array from one OpenRegister result.
	 *
	 * @param mixed $item An array, or an entity with getObject() or jsonSerialize().
	 *
	 * @return array<string, mixed>
	 */
	private static function extractData(mixed $item): array {
		if (is_array($item) === true) {
			return $item;
		}

		$data = null;
		if (is_object($item) === true && method_exists($item, 'getObject') === true) {
			$data = $item->getObject();
		} else if (is_object($item) === true && method_exists($item, 'jsonSerialize') === true) {
			$data = $item->jsonSerialize();
		}

		if (is_array($data) === false) {
			return [];
		}

		return $data;
	}//end extractData()
}//end class
