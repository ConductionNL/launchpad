<?php

/**
 * PublicWidgetReadService
 *
 * The data a live widget shows on a public link or a kiosk screen: the
 * published announcements for everyone, a weather reading or a live data
 * value, read for one placement and only when that placement is on a
 * dashboard the link or playlist shows (REQ-PSHR-011).
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
 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Reads a live widget's data for an anonymous visitor of a shared dashboard.
 *
 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */
class PublicWidgetReadService {

	/**
	 * The widget type of the weather widget.
	 *
	 * @var string
	 */
	public const WIDGET_WEATHER = 'weather';

	/**
	 * The widget type of the live data widget.
	 *
	 * @var string
	 */
	public const WIDGET_LIVE_DATA = 'livetile';

	/**
	 * The widget type of the news widget that lists announcements.
	 *
	 * @var string
	 */
	public const WIDGET_NEWS = 'announcements';

	/**
	 * How many announcements the news widget lists when its content sets no limit.
	 *
	 * @var integer
	 */
	public const DEFAULT_NEWS_LIMIT = 10;

	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper            $placementMapper Loads the placement asked for.
	 * @param PublicWidgetSources              $sources         The weather, live data and announcements.
	 * @param ConditionalServiceInterface|null $visibility      Applies the widget visibility rules.
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly PublicWidgetSources $sources,
		private readonly ?ConditionalServiceInterface $visibility = null,
	) {
	}//end __construct()

	/**
	 * The data of one live widget on a shared dashboard.
	 *
	 * The placement must be on one of the dashboards the link or playlist
	 * shows, visible to a visitor who cannot edit, and a news, weather or
	 * live data widget; anything else is refused as not found, so a token
	 * never reads a widget of another dashboard.
	 *
	 * @param list<int> $dashboardIds The dashboards the token shows.
	 * @param integer   $placementId  The placement asked for.
	 *
	 * @return array{widget: string, data: array<string, mixed>} The widget type and its data.
	 *
	 * @throws DoesNotExistException When the placement is not a live widget on a shared dashboard.
	 *
	 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
	 */
	public function read(array $dashboardIds, int $placementId): array {
		$placement = $this->sharedPlacement(dashboardIds: $dashboardIds, placementId: $placementId);
		$widget    = $placement->getWidgetId();

		return match ($widget) {
			self::WIDGET_WEATHER => [
				'widget' => $widget,
				'data' => $this->sources->weather->resolveForPlacement(placementId: $placementId, userId: ''),
			],
			self::WIDGET_LIVE_DATA => [
				'widget' => $widget,
				'data' => $this->sources->liveData->resolveForPlacement(placementId: $placementId),
			],
			self::WIDGET_NEWS => [
				'widget' => $widget,
				'data' => ['announcements' => $this->news(content: $placement->getContentArray())],
			],
			default => throw new DoesNotExistException(msg: 'Not a live widget'),
		};
	}//end read()

	/**
	 * The placement asked for, when it is on a shared dashboard and a
	 * visitor who cannot edit sees it.
	 *
	 * @param list<int> $dashboardIds The dashboards the token shows.
	 * @param integer   $placementId  The placement asked for.
	 *
	 * @return WidgetPlacement The placement.
	 *
	 * @throws DoesNotExistException When it is not on a shared dashboard or the rules hide it.
	 *
	 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
	 */
	private function sharedPlacement(array $dashboardIds, int $placementId): WidgetPlacement {
		$placement = $this->placementMapper->find(id: $placementId);
		if (in_array($placement->getDashboardId(), $dashboardIds, true) === false) {
			throw new DoesNotExistException(msg: 'Not on a shared dashboard');
		}

		if ($this->visibility !== null
			&& $this->visibility->visiblePlacements(placements: [$placement->jsonSerialize()], userId: '', canEdit: false) === []
		) {
			throw new DoesNotExistException(msg: 'Hidden by the visibility rules');
		}

		return $placement;
	}//end sharedPlacement()

	/**
	 * The news items for everyone, newest first, in the widget's category
	 * and up to its limit; notices show as banners, not here. Only what the
	 * card shows leaves the server: no author, no target groups.
	 *
	 * @param array<string, mixed> $content The placement's content: `category` and `limit`.
	 *
	 * @return list<array<string, mixed>> The news items.
	 *
	 * @spec openspec/changes/archive/2026-11-24-public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
	 */
	private function news(array $content): array {
		$category = (string)($content['category'] ?? '');
		$limit    = (int)($content['limit'] ?? 0);
		if ($limit <= 0) {
			$limit = self::DEFAULT_NEWS_LIMIT;
		}

		// No reader: only what is targeted at everyone; the target check is
		// repeated so a group's news can never reach an anonymous visitor.
		$items = array_filter(
			$this->sources->announcements->visibleTo(userId: ''),
			static fn (Announcement $item): bool => $item->getTargetGroupList() === []
				&& $item->getKind() !== Announcement::KIND_NOTICE
				&& ($category === '' || $item->getCategory() === $category)
		);

		return array_map(
			static fn (Announcement $item): array => array_intersect_key(
				$item->jsonSerialize(),
				array_flip(['uuid', 'kind', 'title', 'body', 'category', 'level', 'publishAt'])
			),
			array_slice(array_values($items), 0, $limit)
		);
	}//end news()
}//end class
