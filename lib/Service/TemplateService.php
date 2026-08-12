<?php

/**
 * TemplateService
 *
 * Service for managing admin dashboard templates.
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

use DateTime;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Service for managing admin dashboard templates.
 */
class TemplateService {
	/**
	 * Constructor
	 *
	 * @param DashboardMapper $dashboardMapper Dashboard mapper.
	 * @param WidgetPlacementMapper $placementMapper Widget placement mapper.
	 * @param AdminTemplateService $adminTemplateService Routing resolver —
	 *                                                   single source of truth
	 *                                                   for
	 *                                                   `IGroupManager::getUserGroupIds`
	 *                                                   (REQ-TMPL-013).
	 */
	public function __construct(
		private readonly DashboardMapper $dashboardMapper,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly AdminTemplateService $adminTemplateService,
	) {
	}//end __construct()

	/**
	 * Get the applicable admin template for a user.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return Dashboard|null The applicable template or null.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-7
	 */
	public function getApplicableTemplate(string $userId): ?Dashboard {
		$templates = $this->dashboardMapper->findAdminTemplates();

		// Group memberships are read through the routing resolver so the
		// single-source-of-truth invariant (REQ-TMPL-013) holds. An empty
		// result means either an unknown user OR a known user with no
		// group memberships — in both cases we skip the per-template
		// intersection scan and fall through to the default template
		// lookup at the end of the method (preserving legacy behaviour).
		$userGroups = $this->adminTemplateService->getUserGroupIdsFor(
			userId: $userId
		);

		// Find template that matches user's groups.
		foreach ($templates as $template) {
			$targetGroups = $template->getTargetGroupsArray();

			// Empty target groups means applies to all users.
			if (empty($targetGroups) === true) {
				continue;
				// Check for more specific templates first.
			}

			// Check if user is in any target group.
			if (empty(array_intersect($userGroups, $targetGroups)) === false) {
				return $template;
			}
		}

		// Return default template if exists.
		try {
			return $this->dashboardMapper->findDefaultTemplate();
		} catch (DoesNotExistException) {
			return null;
		}
	}//end getApplicableTemplate()

	/**
	 * Create a user dashboard based on an admin template.
	 *
	 * @param string $userId The user ID.
	 * @param Dashboard $template The admin template.
	 *
	 * @return Dashboard The created dashboard.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-7
	 */
	public function createDashboardFromTemplate(
		string $userId,
		Dashboard $template,
	): Dashboard {
		// Create user dashboard.
		$dashboard = $this->buildDashboardFromTemplate(
			userId: $userId,
			template: $template
		);

		// Deactivate other dashboards.
		$this->dashboardMapper->deactivateAllForUser(userId: $userId);

		$dashboard = $this->dashboardMapper->insert(entity: $dashboard);

		// Copy widget placements from template.
		$this->copyTemplatePlacements(
			templateId: $template->getId(),
			dashboardId: $dashboard->getId()
		);

		return $dashboard;
	}//end createDashboardFromTemplate()

	/**
	 * Build a dashboard entity from a template.
	 *
	 * @param string $userId The user ID.
	 * @param Dashboard $template The admin template.
	 *
	 * @return Dashboard The built dashboard entity.
	 */
	private function buildDashboardFromTemplate(
		string $userId,
		Dashboard $template,
	): Dashboard {
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$dashboard = new Dashboard();
		$dashboard->setUuid($this->generateUuid());
		$dashboard->setName($template->getName());
		$dashboard->setDescription(
			$template->getDescription()
		);
		$dashboard->setType(Dashboard::TYPE_USER);
		$dashboard->setUserId($userId);
		$dashboard->setBasedOnTemplate(
			$template->getId()
		);
		$dashboard->setGridColumns(
			$template->getGridColumns()
		);
		$dashboard->setPermissionLevel(
			$template->getPermissionLevel()
		);
		$dashboard->setIsActive(1);
		$dashboard->setCreatedAt($now);
		$dashboard->setUpdatedAt($now);

		return $dashboard;
	}//end buildDashboardFromTemplate()

	/**
	 * Generate a v4 UUID using random_bytes (no external dependency).
	 *
	 * @return string A v4 UUID.
	 */
	private function generateUuid(): string {
		$data = random_bytes(length: 16);
		$data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3F) | 0x80);
		return vsprintf(
			format: '%s%s-%s-%s-%s-%s%s%s',
			values: str_split(string: bin2hex(string: $data), length: 4)
		);
	}//end generateUuid()

	/**
	 * Copy widget placements from a template to a new dashboard.
	 *
	 * @param int $templateId The template dashboard ID.
	 * @param int $dashboardId The target dashboard ID.
	 *
	 * @return void
	 */
	private function copyTemplatePlacements(
		int $templateId,
		int $dashboardId,
	): void {
		$templatePlacements = $this->placementMapper->findByDashboardId(
			dashboardId: $templateId
		);

		foreach ($templatePlacements as $templatePlacement) {
			$placement = $this->clonePlacement(
				source: $templatePlacement,
				dashboardId: $dashboardId
			);
			$this->placementMapper->insert(entity: $placement);
		}
	}//end copyTemplatePlacements()

	/**
	 * Clone a widget placement for a new dashboard.
	 *
	 * Copies every widget-, tile-, style- and grid-field byte-for-byte
	 * (mirrors {@see \OCA\LaunchPad\Db\WidgetPlacementMapper::cloneToDashboard()}
	 * so a first-access template copy and an owner-fork clone the exact
	 * same field set — a pre-existing gap where `content`, `customIcon`,
	 * and the `tile*` fields were silently dropped on template
	 * distribution, fixed while touching this method for
	 * `admin-template-resync`).
	 *
	 * Also stamps {@see WidgetPlacement::setTemplatePlacementId()} with the
	 * source template placement's `id` — the origin key
	 * {@see \OCA\LaunchPad\Service\TemplateResyncService} uses to tell
	 * template-origin placements apart from placements the user adds to
	 * their copy afterward (REQ-RESYNC-003 / REQ-RESYNC-004).
	 *
	 * @param WidgetPlacement $source The source placement.
	 * @param int $dashboardId The target dashboard ID.
	 *
	 * @return WidgetPlacement The cloned placement entity.
	 */
	private function clonePlacement(
		WidgetPlacement $source,
		int $dashboardId,
	): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId($source->getWidgetId());
		$placement->setGridX($source->getGridX());
		$placement->setGridY($source->getGridY());
		$placement->setGridWidth($source->getGridWidth());
		$placement->setGridHeight(
			$source->getGridHeight()
		);
		$placement->setIsCompulsory(
			$source->getIsCompulsory()
		);
		$placement->setIsVisible($source->getIsVisible());
		$placement->setStyleConfig(
			$source->getStyleConfig()
		);
		$placement->setCustomTitle(
			$source->getCustomTitle()
		);
		$placement->setCustomIcon($source->getCustomIcon());
		$placement->setShowTitle($source->getShowTitle());
		$placement->setSortOrder($source->getSortOrder());
		// REQ-DASH-020: `content` carries the widget configuration — for
		// `nc-widget` rows the `{"widgetId": ...}` JSON that tells the
		// renderer what to load. Dropping it distributes widgets into a
		// sourceless "No items available" state.
		$placement->setContent($source->getContent());
		// Tile fields — a template placement may be a tile (REQ-TMPL-007
		// "Template placements include tile data"); tileType is the
		// discriminator gating jsonSerialize()'s tile block, so all tile
		// columns must travel together.
		$placement->setTileType($source->getTileType());
		$placement->setTileTitle($source->getTileTitle());
		$placement->setTileIcon($source->getTileIcon());
		$placement->setTileIconType($source->getTileIconType());
		$placement->setTileBackgroundColor($source->getTileBackgroundColor());
		$placement->setTileTextColor($source->getTileTextColor());
		$placement->setTileLinkType($source->getTileLinkType());
		$placement->setTileLinkValue($source->getTileLinkValue());
		// REQ-ACK-001: copy the acknowledgement requirement and the stable
		// `announcementKey` so every recipient cloned from this template
		// placement shares one announcement identity (design D2).
		$placement->setRequiresAcknowledgement($source->getRequiresAcknowledgement());
		$placement->setAcknowledgementPrompt($source->getAcknowledgementPrompt());
		$placement->setAcknowledgementDeadline($source->getAcknowledgementDeadline());
		$placement->setReacknowledgeOnChange($source->getReacknowledgeOnChange());
		$placement->setAcknowledgementContentVersion($source->getAcknowledgementContentVersion());
		$placement->setAnnouncementKey($source->getAnnouncementKey());
		// REQ-RESYNC-003/004: stamp the template-origin key so a later
		// admin re-sync can reconcile this placement against the template
		// while leaving genuinely user-added placements alone.
		$placement->setTemplatePlacementId($source->getId());
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$placement->setCreatedAt($now);
		$placement->setUpdatedAt($now);

		return $placement;
	}//end clonePlacement()
}//end class
