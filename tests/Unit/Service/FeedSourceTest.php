<?php

/**
 * FeedSourceTest
 *
 * Unit tests for FeedSource: renaming after a feed node's title and the
 * canonical item shape (REQ-NEWS-003).
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

use OCA\LaunchPad\Service\FeedSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Tests for FeedSource.
 */
#[CoversClass(FeedSource::class)]
class FeedSourceTest extends TestCase {
	/**
	 * A node with a title yields a new source with that title and the same URL.
	 *
	 * @return void
	 */
	public function testTitledByUsesTheNodeTitle(): void {
		$source = new FeedSource(url: 'https://f.example/rss', title: 'f.example');

		$renamed = $source->titledBy(node: new SimpleXMLElement('<channel><title>Town News</title></channel>'));

		$this->assertNotSame($source, $renamed);
		$this->assertSame('https://f.example/rss', $renamed->url);
		$this->assertSame('Town News', $renamed->title);
		$this->assertSame('f.example', $source->title);
	}//end testTitledByUsesTheNodeTitle()

	/**
	 * A node without a title returns the same source.
	 *
	 * @return void
	 */
	public function testTitledByWithoutTitleKeepsTheSource(): void {
		$source = new FeedSource(url: 'https://f.example/rss', title: 'Keep');

		$this->assertSame($source, $source->titledBy(node: new SimpleXMLElement('<channel><item/></channel>')));
	}//end testTitledByWithoutTitleKeepsTheSource()

	/**
	 * The item carries the fields in schema order with the source between
	 * pubDate and thumbnailUrl, whatever order the fields came in.
	 *
	 * @return void
	 */
	public function testItemOrdersFieldsAndAddsTheSource(): void {
		$source = new FeedSource(url: 'https://f.example/rss', title: 'News');

		$item = $source->item(
			fields: [
				'thumbnailUrl' => 'https://f.example/a.png',
				'pubDate' => 'D',
				'link' => 'L',
				'summary' => 'S',
				'title' => 'T',
				'guid' => 'G',
			]
		);

		$this->assertSame(
			[
				'guid' => 'G',
				'title' => 'T',
				'summary' => 'S',
				'link' => 'L',
				'pubDate' => 'D',
				'sourceUrl' => 'https://f.example/rss',
				'sourceTitle' => 'News',
				'thumbnailUrl' => 'https://f.example/a.png',
			],
			$item
		);
	}//end testItemOrdersFieldsAndAddsTheSource()
}//end class
