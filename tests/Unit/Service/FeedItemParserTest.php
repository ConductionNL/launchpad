<?php

/**
 * FeedItemParserTest
 *
 * Unit tests for FeedItemParser: RSS 2.0 and Atom 1.0 normalisation, the
 * feed-title fallbacks, the generated guid, image enclosures, newest-first
 * ordering and the malformed-XML failure (REQ-FRJ-005).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\FeedItemParser;
use OCA\LaunchPad\Service\FeedSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for FeedItemParser.
 */
#[CoversClass(FeedItemParser::class)]
#[UsesClass(FeedSource::class)]
class FeedItemParserTest extends TestCase {
	/**
	 * The parser under test.
	 *
	 * @var FeedItemParser
	 */
	private FeedItemParser $parser;

	/**
	 * Build the parser.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->parser = new FeedItemParser();
	}//end setUp()

	/**
	 * An RSS item maps onto the news-widget schema with the channel title.
	 *
	 * @return void
	 */
	public function testRssItemIsNormalisedWithChannelTitle(): void {
		$body = '<?xml version="1.0"?><rss version="2.0"><channel><title> Town News </title>'
			. '<item><guid> g-1 </guid><title> Hello </title><description> Body </description>'
			. '<link> https://news.example/1 </link><pubDate>Mon, 02 Jan 2026 10:00:00 +0000</pubDate></item>'
			. '</channel></rss>';

		$items = $this->parser->parse(body: $body, feedUrl: 'https://news.example/feed');

		$this->assertSame(
			[
				[
					'guid' => 'g-1',
					'title' => 'Hello',
					'summary' => 'Body',
					'link' => 'https://news.example/1',
					'pubDate' => 'Mon, 02 Jan 2026 10:00:00 +0000',
					'sourceUrl' => 'https://news.example/feed',
					'sourceTitle' => 'Town News',
					'thumbnailUrl' => null,
				],
			],
			$items
		);
	}//end testRssItemIsNormalisedWithChannelTitle()

	/**
	 * An RSS item without a guid gets sha256(title . pubDate . feedUrl).
	 *
	 * @return void
	 */
	public function testMissingGuidIsDerivedFromTitleDateAndUrl(): void {
		$body = '<rss><channel><title>T</title><item><title>A</title><pubDate>Tue, 03 Jan 2026 00:00:00 +0000</pubDate></item></channel></rss>';

		$items = $this->parser->parse(body: $body, feedUrl: 'https://f.example/rss');

		$this->assertSame(
			hash('sha256', 'ATue, 03 Jan 2026 00:00:00 +0000https://f.example/rss'),
			$items[0]['guid']
		);
	}//end testMissingGuidIsDerivedFromTitleDateAndUrl()

	/**
	 * Only an `image/*` enclosure becomes the thumbnail.
	 *
	 * @return void
	 */
	public function testOnlyImageEnclosureBecomesThumbnail(): void {
		$body = '<rss><channel><title>T</title>'
			. '<item><guid>img</guid><pubDate>Wed, 04 Jan 2026 00:00:00 +0000</pubDate><enclosure url="https://f.example/a.png" type="image/png"/></item>'
			. '<item><guid>audio</guid><pubDate>Tue, 03 Jan 2026 00:00:00 +0000</pubDate><enclosure url="https://f.example/a.mp3" type="audio/mpeg"/></item>'
			. '<item><guid>untyped</guid><pubDate>Mon, 02 Jan 2026 00:00:00 +0000</pubDate><enclosure url="https://f.example/x"/></item>'
			. '</channel></rss>';

		$items = $this->parser->parse(body: $body, feedUrl: 'https://f.example/rss');

		$this->assertSame(['img', 'audio', 'untyped'], array_column($items, 'guid'));
		$this->assertSame(['https://f.example/a.png', null, null], array_column($items, 'thumbnailUrl'));
	}//end testOnlyImageEnclosureBecomesThumbnail()

	/**
	 * Items are sorted newest-first; an unparseable date sorts last.
	 *
	 * @return void
	 */
	public function testItemsAreSortedNewestFirstWithUndatedLast(): void {
		$body = '<rss><channel><title>T</title>'
			. '<item><guid>old</guid><pubDate>Mon, 02 Jan 2026 00:00:00 +0000</pubDate></item>'
			. '<item><guid>undated</guid><pubDate>not a date</pubDate></item>'
			. '<item><guid>new</guid><pubDate>Fri, 06 Mar 2026 00:00:00 +0000</pubDate></item>'
			. '</channel></rss>';

		$items = $this->parser->parse(body: $body, feedUrl: 'https://f.example/rss');

		$this->assertSame(['new', 'old', 'undated'], array_column($items, 'guid'));
	}//end testItemsAreSortedNewestFirstWithUndatedLast()

	/**
	 * An Atom entry uses id, summary, published and the alternate link.
	 *
	 * @return void
	 */
	public function testAtomEntryIsNormalised(): void {
		$body = '<feed xmlns="http://www.w3.org/2005/Atom"><title> Atom Feed </title>'
			. '<entry><id>urn:1</id><title>Entry</title><summary> Sum </summary><content>Full</content>'
			. '<published>2026-02-01T00:00:00Z</published><updated>2026-02-05T00:00:00Z</updated>'
			. '<link rel="self" href="https://a.example/self"/><link rel="alternate" href="https://a.example/1"/></entry>'
			. '</feed>';

		$items = $this->parser->parse(body: $body, feedUrl: 'https://a.example/atom');

		$this->assertSame(
			[
				'guid' => 'urn:1',
				'title' => 'Entry',
				'summary' => 'Sum',
				'link' => 'https://a.example/1',
				'pubDate' => '2026-02-01T00:00:00Z',
				'sourceUrl' => 'https://a.example/atom',
				'sourceTitle' => 'Atom Feed',
				'thumbnailUrl' => null,
			],
			$items[0]
		);
	}//end testAtomEntryIsNormalised()

	/**
	 * An Atom entry falls back to content, updated and a rel-less link.
	 *
	 * @return void
	 */
	public function testAtomEntryFallsBackToContentUpdatedAndRellessLink(): void {
		$body = '<feed xmlns="http://www.w3.org/2005/Atom"><title>F</title>'
			. '<entry><id>urn:2</id><title>E</title><summary>  </summary><content>Full</content>'
			. '<updated>2026-02-05T00:00:00Z</updated><link href="https://a.example/2"/></entry>'
			. '</feed>';

		$item = $this->parser->parse(body: $body, feedUrl: 'https://a.example/atom')[0];

		$this->assertSame('Full', $item['summary']);
		$this->assertSame('2026-02-05T00:00:00Z', $item['pubDate']);
		$this->assertSame('https://a.example/2', $item['link']);
	}//end testAtomEntryFallsBackToContentUpdatedAndRellessLink()

	/**
	 * Without a feed title the source title is the URL's host.
	 *
	 * @return void
	 */
	public function testMissingTitleFallsBackToHost(): void {
		$body = '<rss><channel><title>   </title><item><guid>x</guid></item></channel></rss>';

		$items = $this->parser->parse(body: $body, feedUrl: 'https://host.example/path/feed.xml');

		$this->assertSame('host.example', $items[0]['sourceTitle']);
	}//end testMissingTitleFallsBackToHost()

	/**
	 * Without a title or a host the source title is the raw feed URL.
	 *
	 * @return void
	 */
	public function testMissingTitleAndHostFallsBackToUrl(): void {
		$body = '<rss><channel><item><guid>x</guid></item></channel></rss>';

		$items = $this->parser->parse(body: $body, feedUrl: 'feed.xml');

		$this->assertSame('feed.xml', $items[0]['sourceTitle']);
	}//end testMissingTitleAndHostFallsBackToUrl()

	/**
	 * A well-formed document with neither shape yields no items.
	 *
	 * @return void
	 */
	public function testUnknownDocumentYieldsNoItems(): void {
		$this->assertSame([], $this->parser->parse(body: '<html><body/></html>', feedUrl: 'https://x.example/'));
	}//end testUnknownDocumentYieldsNoItems()

	/**
	 * Malformed XML throws with libxml's first error message.
	 *
	 * @return void
	 */
	public function testMalformedXmlThrows(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('/\S/');

		$this->parser->parse(body: '<rss><channel>', feedUrl: 'https://x.example/');
	}//end testMalformedXmlThrows()

	/**
	 * A failed parse leaves libxml's error setting as it found it.
	 *
	 * @return void
	 */
	public function testMalformedXmlRestoresLibxmlErrorSetting(): void {
		$previous = libxml_use_internal_errors(false);
		try {
			$this->parser->parse(body: 'not xml', feedUrl: 'https://x.example/');
			$this->fail('Expected a RuntimeException');
		} catch (RuntimeException $e) {
			$this->assertFalse(libxml_use_internal_errors(false));
			$this->assertSame([], libxml_get_errors());
		} finally {
			libxml_use_internal_errors($previous);
		}
	}//end testMalformedXmlRestoresLibxmlErrorSetting()
}//end class
