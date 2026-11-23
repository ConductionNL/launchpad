<?php

/**
 * WidgetService
 *
 * Service for discovering and querying Nextcloud dashboard widgets.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\InvalidWidgetContentException;
use InvalidArgumentException;

/**
 * Service for discovering and querying Nextcloud dashboard widgets.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) One validator per widget type, each a small injected or inline class.
 */
class WidgetService {
	/**
	 * Constructor
	 *
	 * @param WidgetPlatform   $platform The widget manager and session.
	 * @param PlacementService $placementService Placement service for CRUD.
	 * @param WidgetRendering  $rendering The formatter and item loader.
	 * @param MenuService      $menuService Validator for `menu` widgets (REQ-MENU-002).
	 * @param IframeService|null $iframeService Allow-list check for `iframe` widgets (REQ-IFRAME-002).
	 * @param HealthPingService|null $healthPing Checks a health-ping config against the allow-list (REQ-HPING-001).
	 */
	public function __construct(
		private readonly WidgetPlatform $platform,
		private readonly PlacementService $placementService,
		private readonly WidgetRendering $rendering,
		private readonly MenuService $menuService = new MenuService(),
		private readonly ?IframeService $iframeService = null,
		private readonly ?HealthPingService $healthPing = null,
	) {
	}//end __construct()

	/**
	 * Validate the `content` blob of a widget placement before save.
	 *
	 * REQ-MENU-002 server-side hook. The `menu`, `map`, `links` and `text`
	 * widget types have a server-side validator; any future widget that
	 * needs save-time validation should add its own branch here so the
	 * dispatcher stays single-responsibility. A health-ping config is checked
	 * on every type, since any tile may carry one (REQ-HPING-001).
	 *
	 * @param string $widgetType Widget type identifier (e.g. `menu`).
	 * @param array $content Widget content blob.
	 *
	 * @return void
	 * @throws InvalidWidgetContentException When the content blob is invalid.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	public function validateWidgetContent(string $widgetType, array $content): void {
		try {
			$this->validateContentOfType(widgetType: $widgetType, content: $content);
		} catch (\InvalidArgumentException $e) {
			throw new InvalidWidgetContentException(message: $e->getMessage(), previous: $e);
		}
	}//end validateWidgetContent()

	/**
	 * Run the validator of a widget type over its content blob.
	 *
	 * @param string $widgetType Widget type identifier.
	 * @param array $content Widget content blob.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When the content blob is invalid.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	private function validateContentOfType(string $widgetType, array $content): void {
		$pingErrors = ($this->healthPing?->validateConfig(config: $content) ?? []);
		if ($pingErrors !== []) {
			throw new InvalidArgumentException(message: 'Invalid health check: ' . implode(separator: ', ', array: $pingErrors));
		}

		if ($widgetType === 'map') {
			(new MapContentValidator())->validate(content: $content);
			return;
		}

		if ($widgetType === 'links') {
			(new LinksContentValidator())->validate(content: $content);
			return;
		}

		if ($widgetType === 'text') {
			(new TextTableValidator())->validate(content: $content);
			return;
		}

		if ($widgetType === 'iframe') {
			$this->validateIframeHost(content: $content);
			return;
		}

		if ($widgetType !== 'menu') {
			return;
		}

		$items = [];
		if (isset($content['items']) === true && is_array($content['items']) === true) {
			$items = $content['items'];
		}

		$this->menuService->validateMenuConfig(content: $content);
		$this->menuService->validateMenuItems(items: $items);
	}//end validateContentOfType()

	/**
	 * Refuse an `iframe` placement whose URL host is not on the allow-list,
	 * naming the host (REQ-IFRAME-002 "rejected at save time").
	 *
	 * @param array $content Widget content blob.
	 *
	 * @return void
	 * @throws \InvalidArgumentException When the host is not allow-listed.
	 *
	 * @spec openspec/specs/iframe-embed-widget/spec.md
	 */
	private function validateIframeHost(array $content): void {
		$url = trim(string: (string)($content['url'] ?? ''));
		if ($this->iframeService === null
			|| in_array(needle: 'host_not_allowed', haystack: $this->iframeService->validateConfig(config: $content), strict: true) === false
		) {
			return;
		}

		$host = (string)parse_url(url: $url, component: PHP_URL_HOST);
		throw new InvalidArgumentException(message: sprintf('The host %s is not on the allow-list.', $host));
	}//end validateIframeHost()

	/**
	 * Get all available widgets from Nextcloud.
	 *
	 * @return array The list of available widgets.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-32
	 */
	public function getAvailableWidgets(): array {
		return $this->describe(widgets: $this->platform->dashboardManager->getWidgets());
	}//end getAvailableWidgets()

	/**
	 * The widgets getAvailableWidgets() describes, read without running any
	 * widget's `load()`, so the workspace page can carry them in its initial
	 * state without injecting every widget's scripts (REQ-RFP-010).
	 *
	 * @return array|null The widget descriptors, or null when the registry cannot be read that way.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#requirement-req-rfp-010-initial-state-widget-allowlist
	 */
	public function getWidgetCatalogue(): ?array {
		$widgets = $this->platform->registry?->widgetsWithoutLoading();
		if ($widgets === null) {
			return null;
		}

		return $this->describe(widgets: $widgets);
	}//end getWidgetCatalogue()

	/**
	 * The descriptors of the given widgets for the current user, in widget order.
	 *
	 * @param array $widgets The widgets.
	 *
	 * @return array The widget descriptors.
	 */
	private function describe(array $widgets): array {
		$result = [];
		foreach ($widgets as $widget) {
			$user = $this->platform->userSession->getUser();
			$userId = '';
			if ($user !== null) {
				$userId = $user->getUID();
			}

			$result[] = $this->rendering->widgetFormatter->format(
				widget: $widget,
				userId: $userId
			);
		}

		usort(
			array: $result,
			callback: function ($a, $b) {
				return $a['order'] - $b['order'];
			}
		);

		return $result;
	}//end describe()

	/**
	 * Get widget items for multiple widgets.
	 *
	 * @param string $userId The user ID.
	 * @param array $widgetIds The widget IDs.
	 * @param int $limit Maximum number of items per widget.
	 *
	 * @return array The widget items.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-33
	 */
	public function getWidgetItems(
		string $userId,
		array $widgetIds,
		int $limit = 7,
	): array {
		$widgets = $this->platform->dashboardManager->getWidgets();

		return $this->rendering->widgetItemLoader->loadItems(
			widgets: $widgets,
			userId: $userId,
			widgetIds: $widgetIds,
			limit: $limit
		);
	}//end getWidgetItems()

	/**
	 * Add a widget to a dashboard.
	 *
	 * @param int $dashboardId Dashboard ID.
	 * @param string $widgetId Widget ID.
	 * @param array $layout Grid position and size (`gridX`, `gridY`,
	 *                      `gridWidth`, `gridHeight`) and the optional
	 *                      per-type `content` payload for registry-driven
	 *                      custom widgets.
	 *
	 * @return WidgetPlacement The created widget placement.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	public function addWidget(int $dashboardId, string $widgetId, array $layout=[]): WidgetPlacement {
		if (isset($layout['content']) === true) {
			$this->validateWidgetContent(
				widgetType: $widgetId,
				content: $layout['content']
			);
		}

		return $this->placementService->addWidget(
			dashboardId: $dashboardId,
			widgetId: $widgetId,
			layout: $layout
		);
	}//end addWidget()

	/**
	 * Add a tile to a dashboard using an array of tile data.
	 *
	 * @param int $dashboardId Dashboard ID.
	 * @param array $tileData Tile configuration data array.
	 *
	 * @return WidgetPlacement The created tile placement.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	public function addTileFromArray(
		int $dashboardId,
		array $tileData,
	): WidgetPlacement {
		return $this->placementService->addTileFromArray(
			dashboardId: $dashboardId,
			tileData: $tileData
		);
	}//end addTileFromArray()

	/**
	 * Import chosen bookmarks onto a dashboard as containers and tiles.
	 *
	 * @param int                $dashboardId The dashboard.
	 * @param BookmarkImportPlan $plan        What to create.
	 *
	 * @return list<WidgetPlacement> The created placements.
	 *
	 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-users-can-import-a-browser-bookmarks-file-as-tiles-req-bmi-001
	 */
	public function importBookmarks(int $dashboardId, BookmarkImportPlan $plan): array {
		return $this->placementService->importBookmarks(dashboardId: $dashboardId, plan: $plan);
	}//end importBookmarks()

	/**
	 * Update a widget placement.
	 *
	 * When the update carries a `content` payload, run the same save-time
	 * content validation as the create path ({@see self::addWidget()}) —
	 * the placement-update route became a content-write path once `content`
	 * was added to the extracted fields, and without this it would bypass
	 * the menu depth / required-field checks (REQ-MENU-002) that the create
	 * path enforces. The widget type is read from the stored placement.
	 *
	 * @param int $placementId The placement ID.
	 * @param array $data The data to update.
	 *
	 * @return WidgetPlacement The updated widget placement.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	public function updatePlacement(
		int $placementId,
		array $data,
	): WidgetPlacement {
		if (isset($data['content']) === true && is_array($data['content']) === true) {
			$placement = $this->placementService->getPlacement(
				placementId: $placementId
			);
			$this->validateWidgetContent(
				widgetType: $placement->getWidgetId(),
				content: $data['content']
			);
		}

		return $this->placementService->updatePlacement(
			placementId: $placementId,
			data: $data
		);
	}//end updatePlacement()

	/**
	 * Remove a widget placement.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	public function removePlacement(int $placementId): void {
		$this->placementService->removePlacement(
			placementId: $placementId
		);
	}//end removePlacement()

	/**
	 * Get placement by ID.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return WidgetPlacement The widget placement.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	public function getPlacement(int $placementId): WidgetPlacement {
		return $this->placementService->getPlacement(
			placementId: $placementId
		);
	}//end getPlacement()

	/**
	 * Get all placements for a dashboard.
	 *
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return WidgetPlacement[] The list of placements.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	public function getDashboardPlacements(int $dashboardId): array {
		return $this->placementService->getDashboardPlacements(
			dashboardId: $dashboardId
		);
	}//end getDashboardPlacements()
}//end class
