<?php

/**
 * NewsWidget
 *
 * Nextcloud dashboard widget `launchpad_news`: the newest articles from the
 * feeds of the news widgets on the dashboards the user owns.
 *
 * @category  Dashboard
 * @package   OCA\LaunchPad\Dashboard
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

namespace OCA\LaunchPad\Dashboard;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * The user's news feeds as a Nextcloud dashboard widget.
 *
 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
 */
class NewsWidget implements IAPIWidgetV2, IIconWidget {
	/**
	 * The id Nextcloud's dashboard registry knows this widget by.
	 */
	public const ID = 'launchpad_news';

	/**
	 * The LaunchPad widget type whose placements are this widget's sources.
	 */
	public const SOURCE_TYPE = 'news';

	/**
	 * The most items one request returns.
	 */
	private const MAX_ITEMS = 50;

	/**
	 * Constructor.
	 *
	 * @param IL10N                 $l10n            Translates the title and empty states.
	 * @param IURLGenerator         $urlGenerator    Builds the icon and app URLs.
	 * @param WidgetPlacementMapper $placementMapper Finds the user's news placements.
	 * @param NewsWidgetService     $news            Reads placement config and fetches the feeds.
	 */
	public function __construct(
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly NewsWidgetService $news,
	) {
	}//end __construct()

	/**
	 * The registry id.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getId(): string {
		return self::ID;
	}//end getId()

	/**
	 * The translated title.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getTitle(): string {
		return $this->l10n->t('News');
	}//end getTitle()

	/**
	 * Position among the other dashboard widgets.
	 *
	 * @return integer
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getOrder(): int {
		return 60;
	}//end getOrder()

	/**
	 * No icon class: the widget ships an icon URL.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getIconClass(): string {
		return '';
	}//end getIconClass()

	/**
	 * The newspaper icon.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			url: $this->urlGenerator->imagePath(appName: Application::APP_ID, file: 'news-dark.svg')
		);
	}//end getIconUrl()

	/**
	 * LaunchPad, where the feeds are configured.
	 *
	 * @return string|null
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getUrl(): ?string {
		return $this->urlGenerator->linkToRouteAbsolute(routeName: Application::APP_ID . '.page.index');
	}//end getUrl()

	/**
	 * Nothing to load: the dashboard renders the items itself.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function load(): void {
	}//end load()

	/**
	 * The newest articles across the feeds of the news widgets on the
	 * dashboards the user owns, each feed fetched once.
	 *
	 * @param string      $userId The user the dashboard is rendered for.
	 * @param string|null $since  Unused: the list is always the newest items.
	 * @param integer     $limit  The most items to return.
	 *
	 * @return WidgetItems
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by the interface.
	 *
	 * @spec openspec/specs/news-widget/spec.md#requirement-req-news-001-widget-registration
	 */
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$feedUrls = $this->feedUrlsOf(userId: $userId);
		if ($feedUrls === []) {
			return new WidgetItems(
				items: [],
				emptyContentMessage: $this->l10n->t('Add a news widget with a feed to one of your dashboards to see its articles here.')
			);
		}

		$limit  = max(1, min(self::MAX_ITEMS, $limit));
		$merged = $this->news->fetchAndMergeFeeds(feedUrls: $feedUrls, limit: $limit);
		$items  = array_map(
			fn (array $article): WidgetItem => $this->toItem(article: $article),
			$merged['items']
		);

		return new WidgetItems(
			items: $items,
			emptyContentMessage: $this->l10n->t('No news articles right now.')
		);
	}//end getItemsV2()

	/**
	 * The distinct feed URLs of the user's news placements, skipping a
	 * placement whose metadata filter does not match its dashboard.
	 *
	 * @param string $userId The dashboard owner.
	 *
	 * @return array<int, string>
	 */
	private function feedUrlsOf(string $userId): array {
		$urls = [];
		$placements = $this->placementMapper->findOwnedByUserAndWidgetId(userId: $userId, widgetId: self::SOURCE_TYPE);
		foreach ($placements as $placement) {
			$config = $this->news->extractNewsConfig(placement: $placement);
			if ($config['metadataFilter'] !== null
				&& $this->news->checkMetadataFilter(
					dashboardId: (int)$placement->getDashboardId(),
					metadataFilter: $config['metadataFilter']
				) === false
			) {
				continue;
			}

			foreach ($config['feedUrls'] as $url) {
				$urls[$url] = true;
			}
		}

		return array_keys($urls);
	}//end feedUrlsOf()

	/**
	 * One article as a dashboard item: its title, its feed as subtitle, its
	 * link when it is a web link, its thumbnail or the news icon.
	 *
	 * @param array<string, mixed> $article A canonical news item.
	 *
	 * @return WidgetItem
	 */
	private function toItem(array $article): WidgetItem {
		$link = (string)($article['link'] ?? '');
		if (self::isWebUrl(url: $link) === false) {
			$link = (string)($article['sourceUrl'] ?? '');
		}

		$thumbnail = (string)($article['thumbnailUrl'] ?? '');
		if (self::isWebUrl(url: $thumbnail) === false) {
			$thumbnail = $this->getIconUrl();
		}

		return new WidgetItem(
			title: (string)($article['title'] ?? ''),
			subtitle: (string)($article['sourceTitle'] ?? ''),
			link: $link,
			iconUrl: $thumbnail,
			sinceId: (string)($article['guid'] ?? '')
		);
	}//end toItem()

	/**
	 * Whether a URL is an absolute http(s) URL.
	 *
	 * @param string $url The candidate.
	 *
	 * @return boolean
	 */
	private static function isWebUrl(string $url): bool {
		return preg_match(pattern: '#^https?://#i', subject: $url) === 1;
	}//end isWebUrl()
}//end class
