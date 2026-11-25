<?php

/**
 * StorePublishService
 *
 * An administrator publishes one dashboard to the connected register
 * (REQ-STORE-013). The body is the dashboard's export in the same shape an
 * install imports, without anything that names a person or this instance's
 * groups, and it travels through OpenRegister's store plane: LaunchPad never
 * talks to the register itself. A slug that was published before needs a
 * higher version. What this instance published is remembered, so its card on
 * the marketplace reads "Own publication".
 *
 * Implements the `dashboard-store` capability (REQ-STORE-013).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCP\IAppConfig;
use OCP\IL10N;

/**
 * Publish a dashboard to the connected register.
 *
 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
 */
class StorePublishService {
	/**
	 * App config key holding the slugs this instance published (JSON list).
	 */
	public const CONFIG_KEY = 'store_published';

	/**
	 * The schema name the publish dialog sends for a dashboard.
	 */
	public const SCHEMA = 'dashboard';

	/**
	 * Outcome for a request this server refuses before the register is asked.
	 */
	public const OUTCOME_INVALID_REQUEST = 'invalid_request';

	/**
	 * Outcome for a version that is not higher than the published one.
	 */
	public const OUTCOME_VERSION_NOT_HIGHER = 'version_not_higher';

	/**
	 * Dashboard fields that name a person or this instance's groups and rows.
	 *
	 * The owner, the group it was shared with, the groups it targets and the
	 * template it was copied from mean nothing on another server, and the
	 * first three are personal data.
	 *
	 * @var list<string>
	 */
	public const PRIVATE_DASHBOARD_FIELDS = ['userId', 'groupId', 'targetGroups', 'basedOnTemplate', 'isActive', 'isDefault'];

	/**
	 * Widget fields that point at rows of this instance.
	 *
	 * @var list<string>
	 */
	public const PRIVATE_WIDGET_FIELDS = ['templatePlacementId', 'createdAt', 'updatedAt'];

	/**
	 * A version the register can order: major.minor.patch.
	 */
	private const VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';

	/**
	 * Constructor.
	 *
	 * @param StoreService    $store      The store plane delegation.
	 * @param DashboardMapper $dashboards Reads the dashboard to publish.
	 * @param ExportService   $export     Serialises it the way an export does.
	 * @param IAppConfig      $appConfig  Holds the slugs this instance published.
	 * @param IL10N           $l10n       The administrator's language.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly StoreService $store,
		private readonly DashboardMapper $dashboards,
		private readonly ExportService $export,
		private readonly IAppConfig $appConfig,
		private readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * The dashboards an administrator may publish: their own and the admin templates.
	 *
	 * Another person's dashboard is never offered, because it is theirs.
	 *
	 * @param string $userId The administrator.
	 *
	 * @return list<array{schema: string, id: string, title: string}>
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 */
	public function publishable(string $userId): array {
		$objects = [];
		foreach ($this->candidates(userId: $userId) as $uuid => $dashboard) {
			$objects[] = ['schema' => self::SCHEMA, 'id' => $uuid, 'title' => (string)$dashboard->getName()];
		}

		return $objects;
	}//end publishable()

	/**
	 * Publish one dashboard as a register item.
	 *
	 * @param array<string, mixed>       $item    The item the dialog filled in: slug, title,
	 *                                            description, category, version, publisher.
	 * @param array<int, mixed>          $objects The chosen dashboards; exactly one.
	 * @param string                     $userId  The publishing administrator.
	 *
	 * @return array{outcome: string, slug: string, message?: string}
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 */
	public function publish(array $item, array $objects, string $userId): array {
		$slug = trim((string)($item['slug'] ?? ''));
		$version = trim((string)($item['version'] ?? ''));

		$refusal = $this->refusal(item: $item, objects: $objects);
		if ($refusal !== null) {
			return $this->refused(outcome: self::OUTCOME_INVALID_REQUEST, message: $refusal);
		}

		$dashboard = ($this->candidates(userId: $userId)[(string)$objects[0]['id']] ?? null);
		if ($dashboard === null) {
			return $this->refused(outcome: self::OUTCOME_INVALID_REQUEST, message: 'That dashboard cannot be published.');
		}

		$published = $this->publishedVersion(slug: $slug);
		if ($published !== null && version_compare($version, $published, '<=') === true) {
			return $this->refused(
				outcome: self::OUTCOME_VERSION_NOT_HIGHER,
				message: 'Version ' . $published . ' of this dashboard was published before. Publish it with a higher version.'
			);
		}

		$payload = $this->exportWithoutPersonalData(dashboard: $dashboard);
		$result = $this->store->publish(
			item: [
				'slug' => $slug,
				'title' => trim((string)$item['title']),
				'description' => trim((string)($item['description'] ?? '')),
				'category' => trim((string)($item['category'] ?? '')),
				'version' => $version,
				'publisher' => trim((string)($item['publisher'] ?? '')),
				'widgets' => count((array)$payload['widgets']),
				'dashboards' => [$payload],
			]
		);

		if ($result['outcome'] === 'ok') {
			$this->remember(slug: $slug);
		}

		return $result;
	}//end publish()

	/**
	 * Mark the cards this instance published, so they read "Own publication".
	 *
	 * @param array<int, array<string, mixed>> $cards The search's cards.
	 *
	 * @return array<int, array<string, mixed>>
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 */
	public function annotate(array $cards): array {
		$own = array_flip($this->published());
		if ($own === []) {
			return $cards;
		}

		foreach ($cards as $index => $card) {
			if (is_array($card) === true && isset($own[(string)($card['slug'] ?? '')]) === true) {
				$cards[$index]['ownPublication'] = true;
				$cards[$index]['typeName'] = $this->l10n->t('Own publication');
			}
		}

		return $cards;
	}//end annotate()

	/**
	 * The dashboard's export with every person and local row reference removed.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-013-an-administrator-publishes-a-dashboard-to-the-connected-register
	 */
	public function exportWithoutPersonalData(Dashboard $dashboard): array {
		$payload = $this->export->serializeDashboard(dashboard: $dashboard);
		foreach (self::PRIVATE_DASHBOARD_FIELDS as $field) {
			unset($payload[$field]);
		}

		$widgets = [];
		foreach ((array)($payload['widgets'] ?? []) as $widget) {
			if (is_array($widget) === false) {
				continue;
			}

			foreach (self::PRIVATE_WIDGET_FIELDS as $field) {
				unset($widget[$field]);
			}

			$widgets[] = $widget;
		}

		$payload['widgets'] = $widgets;
		$payload['metadataFieldAssignments'] = [];

		return $payload;
	}//end exportWithoutPersonalData()

	/**
	 * Why the request cannot be published, or null when it can.
	 *
	 * @param array<string, mixed> $item    The item.
	 * @param array<int, mixed>    $objects The chosen dashboards.
	 *
	 * @return string|null
	 */
	private function refusal(array $item, array $objects): ?string {
		if (count($objects) !== 1 || is_array($objects[0] ?? null) === false
			|| (string)($objects[0]['schema'] ?? '') !== self::SCHEMA
		) {
			return 'Choose one dashboard to publish.';
		}

		if (preg_match(pattern: '/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', subject: trim((string)($item['slug'] ?? ''))) !== 1
			|| trim((string)($item['title'] ?? '')) === ''
		) {
			return 'Give the dashboard a title.';
		}

		if (preg_match(pattern: self::VERSION_PATTERN, subject: trim((string)($item['version'] ?? ''))) !== 1) {
			return 'Give a version as three numbers, such as 1.0.0.';
		}

		return null;
	}//end refusal()

	/**
	 * The version the register holds under this slug, or null when it holds none.
	 *
	 * @param string $slug The item slug.
	 *
	 * @return string|null
	 */
	private function publishedVersion(string $slug): ?string {
		$resolved = $this->store->resolveItem(slug: $slug);
		$version = trim((string)($resolved['version'] ?? ''));
		if ($version === '') {
			return null;
		}

		return $version;
	}//end publishedVersion()

	/**
	 * The administrator's own dashboards and the admin templates, by uuid.
	 *
	 * @param string $userId The administrator.
	 *
	 * @return array<string, Dashboard>
	 */
	private function candidates(string $userId): array {
		$candidates = [];
		foreach ([$this->dashboards->findByUserId(userId: $userId), $this->dashboards->findAdminTemplates()] as $list) {
			foreach ($list as $dashboard) {
				$uuid = (string)$dashboard->getUuid();
				if ($uuid !== '' && isset($candidates[$uuid]) === false) {
					$candidates[$uuid] = $dashboard;
				}
			}
		}

		return $candidates;
	}//end candidates()

	/**
	 * The slugs this instance published.
	 *
	 * @return list<string>
	 */
	private function published(): array {
		$decoded = json_decode(
			json: $this->appConfig->getValueString(Application::APP_ID, self::CONFIG_KEY, '[]'),
			associative: true
		);
		if (is_array($decoded) === false) {
			return [];
		}

		return array_values(array_filter(array_map('strval', $decoded), static fn (string $slug): bool => $slug !== ''));
	}//end published()

	/**
	 * Remember a slug this instance published.
	 *
	 * @param string $slug The published slug.
	 *
	 * @return void
	 */
	private function remember(string $slug): void {
		$published = $this->published();
		if (in_array($slug, $published, true) === true) {
			return;
		}

		$published[] = $slug;
		$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_KEY, (string)json_encode($published));
	}//end remember()

	/**
	 * A refusal the administrator reads.
	 *
	 * @param string $outcome The outcome.
	 * @param string $message The sentence.
	 *
	 * @return array{outcome: string, slug: string, message: string}
	 */
	private function refused(string $outcome, string $message): array {
		return ['outcome' => $outcome, 'slug' => '', 'message' => $message];
	}//end refused()
}//end class
