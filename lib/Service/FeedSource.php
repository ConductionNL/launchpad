<?php

/**
 * FeedSource
 *
 * The URL a news feed was fetched from and the display name its items
 * carry (REQ-NEWS-003).
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

use SimpleXMLElement;

/**
 * Feed URL plus the display name stamped on every item parsed from it.
 *
 * @spec openspec/specs/news-widget/spec.md
 */
final class FeedSource {
	/**
	 * Constructor
	 *
	 * @param string $url   URL the payload was fetched from.
	 * @param string $title Display name for the feed.
	 */
	public function __construct(
		public readonly string $url,
		public readonly string $title,
	) {
	}//end __construct()

	/**
	 * This source renamed after the `<title>` child of a feed or channel
	 * node, or this source unchanged when the node has no title.
	 *
	 * @param SimpleXMLElement $node The Atom `<feed>` or RSS `<channel>` node.
	 *
	 * @return self
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function titledBy(SimpleXMLElement $node): self {
		if (isset($node->title) === true) {
			return new self(url: $this->url, title: (string)$node->title);
		}

		return $this;
	}//end titledBy()

	/**
	 * The canonical news item for fields read from one entry of this feed:
	 * the entry's fields in schema order, with this source's URL and title
	 * between `pubDate` and `thumbnailUrl`.
	 *
	 * @param array<string, string|null> $fields Final guid, title, summary, link, pubDate (strings) and
	 *                                           thumbnailUrl (string or null).
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/news-widget/spec.md
	 */
	public function item(array $fields): array {
		return [
			'guid' => $fields['guid'],
			'title' => $fields['title'],
			'summary' => $fields['summary'],
			'link' => $fields['link'],
			'pubDate' => $fields['pubDate'],
			'sourceUrl' => $this->url,
			'sourceTitle' => $this->title,
			'thumbnailUrl' => $fields['thumbnailUrl'],
		];
	}//end item()
}//end class
