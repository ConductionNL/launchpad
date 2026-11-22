<?php

/**
 * LinksWidget
 *
 * Nextcloud dashboard widget `launchpad_links`: the links of the links
 * widgets on the dashboards the user owns.
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

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * The user's links as a Nextcloud dashboard widget.
 *
 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-001-widget-registration
 */
class LinksWidget extends LaunchPadApiWidget {
	/**
	 * The id Nextcloud's dashboard registry knows this widget by.
	 */
	public const ID = 'launchpad_links';

	/**
	 * The LaunchPad widget type whose placements are this widget's sources.
	 */
	public const SOURCE_TYPE = 'links';

	/**
	 * Constructor.
	 *
	 * @param IL10N                 $l10n            Translates the title and empty states.
	 * @param IURLGenerator         $urlGenerator    Builds the icon, app and item URLs.
	 * @param WidgetPlacementMapper $placementMapper Finds the user's links placements.
	 */
	public function __construct(
		IL10N $l10n,
		IURLGenerator $urlGenerator,
		private readonly WidgetPlacementMapper $placementMapper,
	) {
		parent::__construct(l10n: $l10n, urlGenerator: $urlGenerator);
	}//end __construct()

	/**
	 * The translated title.
	 *
	 * @return string
	 *
	 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-001-widget-registration
	 */
	public function getTitle(): string {
		return $this->l10n->t('Links');
	}//end getTitle()

	/**
	 * Position among the other dashboard widgets.
	 *
	 * @return integer
	 *
	 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-001-widget-registration
	 */
	public function getOrder(): int {
		return 61;
	}//end getOrder()

	/**
	 * The icon's file name in the app's img folder.
	 *
	 * @return string
	 */
	protected function iconFile(): string {
		return 'links-dark.svg';
	}//end iconFile()

	/**
	 * The links of the links widgets on the dashboards the user owns, in
	 * dashboard, section and link order, each URL once.
	 *
	 * @param string      $userId The user the dashboard is rendered for.
	 * @param string|null $since  Unused: links have no time order.
	 * @param integer     $limit  The most items to return.
	 *
	 * @return WidgetItems
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) Required by the interface.
	 *
	 * @spec openspec/specs/links-widget/spec.md#requirement-req-lnks-001-widget-registration
	 */
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$items = [];
		$placements = $this->placementMapper->findOwnedByUserAndWidgetId(userId: $userId, widgetId: self::SOURCE_TYPE);
		foreach ($placements as $placement) {
			foreach (self::linksOf(content: $placement->getContentArray()) as $link) {
				$item = $this->toItem(link: $link['link'], section: $link['section']);
				if ($item === null || isset($items[$item->getLink()]) === true) {
					continue;
				}

				$items[$item->getLink()] = $item;
				if (count($items) >= max(1, $limit)) {
					break 2;
				}
			}
		}

		return new WidgetItems(
			items: array_values($items),
			emptyContentMessage: $this->l10n->t('Add a links widget to one of your dashboards to see its links here.')
		);
	}//end getItemsV2()

	/**
	 * Every link of a links placement's content with the title of its section.
	 *
	 * @param array<string, mixed> $content The placement content.
	 *
	 * @return array<int, array{link: array<string, mixed>, section: string}>
	 */
	private static function linksOf(array $content): array {
		$out = [];
		$sections = ($content['sections'] ?? []);
		if (is_array($sections) === false) {
			return [];
		}

		foreach ($sections as $section) {
			if (is_array($section) === false || is_array($section['links'] ?? null) === false) {
				continue;
			}

			foreach ($section['links'] as $link) {
				if (is_array($link) === true) {
					$out[] = ['link' => $link, 'section' => (string)($section['title'] ?? '')];
				}
			}
		}

		return $out;
	}//end linksOf()

	/**
	 * One link as a dashboard item, or null when its URL is not a web or
	 * Nextcloud URL.
	 *
	 * @param array<string, mixed> $link    The stored link.
	 * @param string               $section The title of its section.
	 *
	 * @return WidgetItem|null
	 */
	private function toItem(array $link, string $section): ?WidgetItem {
		$url = $this->absoluteUrl(url: trim((string)($link['url'] ?? '')));
		if ($url === null) {
			return null;
		}

		$label = trim((string)($link['label'] ?? ''));
		$subtitle = trim((string)($link['description'] ?? ''));
		if ($subtitle === '') {
			$subtitle = $section;
		}

		$icon = $this->absoluteUrl(url: trim((string)($link['icon'] ?? '')));
		if ($icon === null) {
			$icon = $this->getIconUrl();
		}

		if ($label === '') {
			$label = $url;
		}

		return new WidgetItem(
			title: $label,
			subtitle: $subtitle,
			link: $url,
			iconUrl: $icon
		);
	}//end toItem()

	/**
	 * An http(s) URL as is, a path on this Nextcloud made absolute, null for
	 * anything else (icon names, other schemes, protocol-relative URLs).
	 *
	 * @param string $url The candidate.
	 *
	 * @return string|null
	 */
	private function absoluteUrl(string $url): ?string {
		if (preg_match(pattern: '#^https?://[^\s/]#i', subject: $url) === 1) {
			return $url;
		}

		if (preg_match(pattern: '#^/(?![/\\\\])#', subject: $url) === 1 && str_contains($url, '..') === false) {
			return $this->urlGenerator->getAbsoluteURL(url: $url);
		}

		return null;
	}//end absoluteUrl()
}//end class
