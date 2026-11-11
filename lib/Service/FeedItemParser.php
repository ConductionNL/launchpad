<?php

/**
 * FeedItemParser
 *
 * Parses an RSS 2.0 / Atom 1.0 feed body into news-widget items for the
 * background feed refresh (REQ-FRJ-005).
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

use RuntimeException;
use SimpleXMLElement;

/**
 * Parses and normalises the feed bodies FeedRefreshService fetches.
 *
 * @spec openspec/specs/background-job-feed-refresh/spec.md
 */
final class FeedItemParser {

	/**
	 * Parse an RSS 2.0 / Atom 1.0 feed body and return normalised items
	 * sorted newest-first (REQ-FRJ-005).
	 *
	 * @param string $body The XML body.
	 * @param string $feedUrl The feed URL (for sourceUrl/sourceTitle).
	 *
	 * @return array<int, array<string, mixed>> The normalised items.
	 *
	 * @throws RuntimeException When parsing fails.
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public function parse(string $body, string $feedUrl): array {
		$xml = $this->loadFeedXml(body: $body);
		$source = new FeedSource(url: $feedUrl, title: $this->extractFeedTitle(xml: $xml, feedUrl: $feedUrl));
		$items = $this->extractItems(xml: $xml, source: $source);

		// Sort newest-first by pubDate.
		usort(
			array: $items,
			callback: static fn (array $left, array $right): int => self::compareByPubDate(
				left: $left,
				right: $right
			)
		);

		return $items;
	}//end parse()

	/**
	 * Parse the raw XML body, converting libxml's error channel into an
	 * exception.
	 *
	 * C2: LIBXML_NOENT is deliberately NOT passed — it resolves (not
	 * disables) entities, enabling XXE; LIBXML_NONET blocks external
	 * DTD/entity fetches. L1: LIBXML_NOCDATA is deliberately NOT passed —
	 * it folds CDATA into text nodes, masking downstream sanitisation.
	 *
	 * @param string $body The raw XML body.
	 *
	 * @return SimpleXMLElement The parsed document.
	 *
	 * @throws RuntimeException When the body is not well-formed XML; the
	 *                          message carries the first libxml error.
	 */
	private function loadFeedXml(string $body): SimpleXMLElement {
		$previous = libxml_use_internal_errors(use_errors: true);
		try {
			$xml = simplexml_load_string(
				data: $body,
				class_name: 'SimpleXMLElement',
				options: LIBXML_NONET
			);
			if ($xml === false) {
				$errors = libxml_get_errors();
				libxml_clear_errors();
				$message = 'malformed XML';
				if (isset($errors[0]) === true) {
					$message = trim(string: $errors[0]->message);
				}

				throw new RuntimeException(message: $message);
			}
		} finally {
			libxml_clear_errors();
			libxml_use_internal_errors(use_errors: $previous);
		}//end try

		return $xml;
	}//end loadFeedXml()

	/**
	 * Collect the normalised entries from an RSS 2.0 or Atom 1.0 document.
	 *
	 * RSS is probed first; a document carrying neither shape yields an
	 * empty list, so an empty feed is not a parse failure.
	 *
	 * @param SimpleXMLElement $xml The parsed feed document.
	 * @param FeedSource $source The feed URL and resolved channel/feed title.
	 *
	 * @return array<int, array<string, mixed>> Normalised items, unsorted.
	 */
	private function extractItems(SimpleXMLElement $xml, FeedSource $source): array {
		$items = [];

		// RSS 2.0 — <channel><item>...
		if (isset($xml->channel) === true && isset($xml->channel->item) === true) {
			foreach ($xml->channel->item as $item) {
				$items[] = $this->normaliseRssItem(item: $item, source: $source);
			}
		} elseif (isset($xml->entry) === true) {
			// Atom 1.0 — <feed><entry>...
			foreach ($xml->entry as $entry) {
				$items[] = $this->normaliseAtomEntry(entry: $entry, source: $source);
			}
		}

		return $items;
	}//end extractItems()

	/**
	 * Newest-first comparator over the normalised `pubDate` field.
	 *
	 * A missing or unparseable date sorts as epoch 0, i.e. last.
	 *
	 * @param array $left Left-hand normalised item.
	 * @param array $right Right-hand normalised item.
	 *
	 * @return int Negative, zero or positive per the usort contract.
	 */
	private static function compareByPubDate(array $left, array $right): int {
		$leftRaw = strtotime(datetime: (string)($left['pubDate'] ?? ''));
		$rightRaw = strtotime(datetime: (string)($right['pubDate'] ?? ''));
		$leftTs = 0;
		if ($leftRaw !== false) {
			$leftTs = $leftRaw;
		}

		$rightTs = 0;
		if ($rightRaw !== false) {
			$rightTs = $rightRaw;
		}

		return ($rightTs <=> $leftTs);
	}//end compareByPubDate()

	/**
	 * Extract the channel-level title (RSS) or feed-level title (Atom),
	 * falling back to the URL hostname.
	 *
	 * @param SimpleXMLElement $xml The parsed feed.
	 * @param string $feedUrl The feed URL.
	 *
	 * @return string The source title.
	 */
	private function extractFeedTitle(
		SimpleXMLElement $xml,
		string $feedUrl,
	): string {
		$title = self::declaredTitle(xml: $xml);
		if ($title !== '') {
			return $title;
		}

		$host = (string)parse_url(url: $feedUrl, component: PHP_URL_HOST);
		if ($host !== '') {
			return $host;
		}

		return $feedUrl;
	}//end extractFeedTitle()

	/**
	 * The trimmed channel title (RSS), else the trimmed feed title (Atom),
	 * or '' when neither is present and non-blank.
	 *
	 * @param SimpleXMLElement $xml The parsed feed.
	 *
	 * @return string
	 */
	private static function declaredTitle(SimpleXMLElement $xml): string {
		if (isset($xml->channel) === true && isset($xml->channel->title) === true) {
			$title = trim(string: (string)$xml->channel->title);
			if ($title !== '') {
				return $title;
			}
		}

		if (isset($xml->title) === true) {
			return trim(string: (string)$xml->title);
		}

		return '';
	}//end declaredTitle()

	/**
	 * Normalise a single RSS 2.0 item to the news-widget schema.
	 *
	 * @param SimpleXMLElement $item The RSS item element.
	 * @param FeedSource $source The feed URL and channel title.
	 *
	 * @return array<string, mixed>
	 */
	private function normaliseRssItem(SimpleXMLElement $item, FeedSource $source): array {
		return self::itemRecord(
			fields: [
				'id' => trim(string: (string)($item->guid ?? '')),
				'title' => trim(string: (string)($item->title ?? '')),
				'summary' => trim(string: (string)($item->description ?? '')),
				'link' => trim(string: (string)($item->link ?? '')),
				'pubDate' => trim(string: (string)($item->pubDate ?? '')),
				'thumbnailUrl' => self::rssThumbnail(item: $item),
			],
			source: $source
		);
	}//end normaliseRssItem()

	/**
	 * The URL of an RSS item's enclosure when its type is `image/*`, else null.
	 *
	 * @param SimpleXMLElement $item The RSS item element.
	 *
	 * @return string|null
	 */
	private static function rssThumbnail(SimpleXMLElement $item): ?string {
		if (isset($item->enclosure) === false) {
			return null;
		}

		$attrs = $item->enclosure->attributes();
		if ($attrs === null || isset($attrs['type']) === false) {
			return null;
		}

		if (str_starts_with(haystack: (string)$attrs['type'], needle: 'image/') === true) {
			return (string)$attrs['url'];
		}

		return null;
	}//end rssThumbnail()

	/**
	 * Normalise a single Atom 1.0 entry to the news-widget schema.
	 *
	 * @param SimpleXMLElement $entry The Atom entry element.
	 * @param FeedSource $source The feed URL and feed title.
	 *
	 * @return array<string, mixed>
	 */
	private function normaliseAtomEntry(SimpleXMLElement $entry, FeedSource $source): array {
		return self::itemRecord(
			fields: [
				'id' => trim(string: (string)($entry->id ?? '')),
				'title' => trim(string: (string)($entry->title ?? '')),
				'summary' => self::firstNonBlank(node: $entry, names: ['summary', 'content']),
				'link' => self::atomAlternateLink(entry: $entry),
				'pubDate' => self::firstNonBlank(node: $entry, names: ['published', 'updated']),
				'thumbnailUrl' => null,
			],
			source: $source
		);
	}//end normaliseAtomEntry()

	/**
	 * The first child, out of `$names`, whose trimmed text is not empty;
	 * '' when none is.
	 *
	 * @param SimpleXMLElement $node Parent element.
	 * @param string[] $names Child names, tried in order.
	 *
	 * @return string
	 */
	private static function firstNonBlank(SimpleXMLElement $node, array $names): string {
		foreach ($names as $name) {
			$text = trim(string: (string)($node->$name ?? ''));
			if ($text !== '') {
				return $text;
			}
		}

		return '';
	}//end firstNonBlank()

	/**
	 * The href of an Atom entry's first non-empty `alternate` link (a link
	 * without `rel` counts as alternate), or '' without one.
	 *
	 * @param SimpleXMLElement $entry The Atom entry element.
	 *
	 * @return string
	 */
	private static function atomAlternateLink(SimpleXMLElement $entry): string {
		if (isset($entry->link) === false) {
			return '';
		}

		foreach ($entry->link as $linkElement) {
			$attrs = $linkElement->attributes();
			if ($attrs === null) {
				continue;
			}

			$rel = (string)($attrs['rel'] ?? 'alternate');
			$href = (string)($attrs['href'] ?? '');
			if ($rel === 'alternate' && $href !== '') {
				return $href;
			}
		}

		return '';
	}//end atomAlternateLink()

	/**
	 * Complete the fields read from one entry into the news-widget item:
	 * the entry id, or `sha256(title . pubDate . feedUrl)` when it has
	 * none, plus the feed source.
	 *
	 * @param array<string, string|null> $fields Fields read from the entry: id, title, summary, link, pubDate
	 *                                           (strings) and thumbnailUrl (string or null).
	 * @param FeedSource $source The feed URL and title.
	 *
	 * @return array<string, mixed>
	 */
	private static function itemRecord(array $fields, FeedSource $source): array {
		$guid = $fields['id'];
		if ($guid === '') {
			$guid = hash(algo: 'sha256', data: ($fields['title'] . $fields['pubDate'] . $source->url));
		}

		return [
			'guid' => $guid,
			'title' => $fields['title'],
			'summary' => $fields['summary'],
			'link' => $fields['link'],
			'pubDate' => $fields['pubDate'],
			'sourceUrl' => $source->url,
			'sourceTitle' => $source->title,
			'thumbnailUrl' => $fields['thumbnailUrl'],
		];
	}//end itemRecord()
}//end class
