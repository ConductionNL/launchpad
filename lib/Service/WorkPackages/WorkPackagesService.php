<?php

/**
 * WorkPackagesService
 *
 * The OpenProject work packages of the work packages widget, read through the
 * official Nextcloud OpenProject integration (`integration_openproject`). The
 * integration holds the OpenProject address and each person's own OAuth or
 * OIDC token; launchpad asks it for the work packages as the signed-in person
 * and never sees a credential.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\WorkPackages
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\WorkPackages;

use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IUserManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reads a person's open work packages from OpenProject.
 */
class WorkPackagesService {
	/**
	 * The integration app.
	 *
	 * @var string
	 */
	public const INTEGRATION_APP = 'integration_openproject';

	/**
	 * The integration's API service, looked up at runtime so launchpad works
	 * without the app.
	 *
	 * @var string
	 */
	private const API_SERVICE = 'OCA\\OpenProject\\Service\\OpenProjectAPIService';

	/**
	 * The most work packages one read lists.
	 *
	 * @var int
	 */
	public const MAX_LIMIT = 25;

	/**
	 * Constructor.
	 *
	 * @param IAppManager        $appManager  Whether the integration is enabled.
	 * @param IAppConfig         $appConfig   The integration's OpenProject address.
	 * @param IUserManager       $userManager The signed-in person.
	 * @param ContainerInterface $container   The integration's API service.
	 * @param LoggerInterface    $logger      Failures of the integration.
	 */
	public function __construct(
		private readonly IAppManager $appManager,
		private readonly IAppConfig $appConfig,
		private readonly IUserManager $userManager,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The open work packages for a person, due date first.
	 *
	 * `scope` `mine` lists the ones assigned to the person; `project` lists every
	 * open one of the project named by `project` (its identifier or id).
	 *
	 * @param string $userId  The signed-in person.
	 * @param string $scope   `mine` or `project`.
	 * @param string $project The project identifier for scope `project`.
	 * @param int    $limit   How many to list, 1 to {@see self::MAX_LIMIT}.
	 *
	 * @return array{state: string, workPackages: array<int, array<string, mixed>>, connectUrl?: string}
	 *   `state` is `ok`, `not-installed`, `not-connected` (the person has not
	 *   connected their OpenProject account) or `error`.
	 *
	 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md#requirement-the-widget-lists-open-work-packages-due-date-first-req-wp-001
	 */
	public function openWorkPackages(string $userId, string $scope, string $project, int $limit): array {
		$service = $this->apiService(userId: $userId);
		if ($service === null || is_callable([$service, 'getAccessToken']) === false || is_callable([$service, 'request']) === false) {
			return ['state' => 'not-installed', 'workPackages' => []];
		}

		try {
			if ((string)$service->getAccessToken($userId) === '') {
				return self::notConnected();
			}

			$result = $service->request($userId, $this->endpoint(scope: $scope, project: $project), self::query(scope: $scope, limit: $limit));
		} catch (Throwable $exception) {
			$this->logger->warning('OpenProject work packages could not be read: ' . $exception->getMessage(), ['app' => 'launchpad']);
			return ['state' => 'error', 'workPackages' => []];
		}

		return $this->answer(result: $result);
	}//end openWorkPackages()

	/**
	 * The widget's answer for what the integration returned: the work
	 * packages, the not-connected state for a refused token, or an error.
	 *
	 * @param mixed $result What `request()` returned.
	 *
	 * @return array{state: string, workPackages: array<int, array<string, mixed>>, connectUrl?: string}
	 */
	private function answer(mixed $result): array {
		if (is_array($result) === false) {
			return ['state' => 'error', 'workPackages' => []];
		}

		if (isset($result['error']) === true) {
			if ((int)($result['statusCode'] ?? 0) === 401) {
				return self::notConnected();
			}

			return ['state' => 'error', 'workPackages' => []];
		}

		$elements = $result['_embedded']['elements'] ?? [];
		$base     = rtrim($this->appConfig->getValueString(app: self::INTEGRATION_APP, key: 'openproject_instance_url'), '/');

		$workPackages = [];
		foreach ((array)$elements as $element) {
			if (is_array($element) === true && isset($element['id']) === true) {
				$workPackages[] = self::workPackage(element: $element, base: $base);
			}
		}

		return ['state' => 'ok', 'workPackages' => $workPackages];
	}//end answer()

	/**
	 * The answer for a person who has not connected their OpenProject account.
	 *
	 * @return array{state: string, workPackages: array<int, mixed>, connectUrl: string}
	 */
	private static function notConnected(): array {
		return ['state' => 'not-connected', 'workPackages' => [], 'connectUrl' => self::connectPath()];
	}//end notConnected()

	/**
	 * The integration's API service, or null when the app is not enabled for
	 * the person or its service cannot be built.
	 *
	 * @param string $userId The person.
	 *
	 * @return object|null
	 */
	private function apiService(string $userId): ?object {
		try {
			$user = $this->userManager->get($userId);
			if ($user === null || $this->appManager->isEnabledForUser(appId: self::INTEGRATION_APP, user: $user) === false) {
				return null;
			}

			if (class_exists(self::API_SERVICE) === false) {
				return null;
			}

			$service = $this->container->get(self::API_SERVICE);
			if (is_object($service) === false) {
				return null;
			}

			return $service;
		} catch (Throwable $exception) {
			$this->logger->warning('The OpenProject integration could not be loaded: ' . $exception->getMessage(), ['app' => 'launchpad']);
			return null;
		}
	}//end apiService()

	/**
	 * The OpenProject API path for the scope.
	 *
	 * @param string $scope   `mine` or `project`.
	 * @param string $project The project identifier.
	 *
	 * @return string
	 */
	private function endpoint(string $scope, string $project): string {
		$project = trim($project);
		if ($scope === 'project' && $project !== '') {
			return 'projects/' . rawurlencode($project) . '/work_packages';
		}

		return 'work_packages';
	}//end endpoint()

	/**
	 * The OpenProject query: open work packages, the person's own for scope
	 * `mine`, due date first.
	 *
	 * @param string $scope `mine` or `project`.
	 * @param int    $limit How many.
	 *
	 * @return array<string, string|int>
	 */
	public static function query(string $scope, int $limit): array {
		$filters = [['status' => ['operator' => 'o', 'values' => []]]];
		if ($scope !== 'project') {
			$filters[] = ['assignee' => ['operator' => '=', 'values' => ['me']]];
		}

		return [
			'filters'  => (string)json_encode($filters),
			'sortBy'   => (string)json_encode([['dueDate', 'asc']]),
			'pageSize' => max(1, min(self::MAX_LIMIT, $limit)),
		];
	}//end query()

	/**
	 * One OpenProject work package as the widget shows it.
	 *
	 * @param array<string, mixed> $element The HAL element.
	 * @param string               $base    The OpenProject address.
	 *
	 * @return array{id: int, subject: string, status: string, project: string, assignee: string, dueDate: string, url: string}
	 */
	public static function workPackage(array $element, string $base): array {
		$links = $element['_links'] ?? [];
		$title = static fn (string $key): string => (string)($links[$key]['title'] ?? '');
		$id    = (int)$element['id'];
		$url   = '';
		if (preg_match('#^https?://#i', $base) === 1) {
			$url = $base . '/work_packages/' . $id;
		}

		return [
			'id'       => $id,
			'subject'  => (string)($element['subject'] ?? ''),
			'status'   => $title('status'),
			'project'  => $title('project'),
			'assignee' => $title('assignee'),
			'dueDate'  => (string)($element['dueDate'] ?? ''),
			'url'      => $url,
		];
	}//end workPackage()

	/**
	 * Where a person connects their OpenProject account.
	 *
	 * @return string
	 */
	private static function connectPath(): string {
		return '/settings/user/openproject';
	}//end connectPath()
}//end class
