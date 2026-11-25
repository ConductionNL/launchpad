<?php

/**
 * GroupTileNodeTest
 *
 * REQ-TILE-041: an address link inside a group becomes a full tile node that
 * keeps its layout and other keys; an action link, a link list and every other
 * child stay as they are; nested groups are converted too.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\GroupTileNode;
use PHPUnit\Framework\TestCase;

class GroupTileNodeTest extends TestCase {

	/**
	 * A bookmark link becomes a tile node with its layout and other keys.
	 *
	 * @return void
	 */
	public function testAnAddressLinkBecomesATileNode(): void {
		$link = [
			'type' => 'link',
			'uuid' => 'c-1',
			'gridX' => 2,
			'gridY' => 0,
			'content' => [
				'label' => ' Intranet ',
				'url' => 'https://intranet.example.org',
				'actionType' => 'external',
				'icon' => 'web',
				'backgroundColor' => '#123456',
			],
		];
		$this->assertSame(
			expected: [
				'type' => 'tile',
				'uuid' => 'c-1',
				'gridX' => 2,
				'gridY' => 0,
				'content' => [
					'backgroundColor' => '#123456',
					'title' => 'Intranet',
					'iconType' => 'svg',
					'linkType' => 'url',
					'linkValue' => 'https://intranet.example.org',
				],
			],
			actual: (new GroupTileNode())->fromLink(child: $link)
		);
	}//end testAnAddressLinkBecomesATileNode()

	/**
	 * Action links, link lists, empty addresses and other widgets stay.
	 *
	 * @return void
	 */
	public function testOtherChildrenStay(): void {
		$this->assertNull(actual: (new GroupTileNode())->fromLink(child: ['type' => 'link', 'content' => ['url' => 'x', 'actionType' => 'createFile']]));
		$this->assertNull(actual: (new GroupTileNode())->fromLink(child: ['type' => 'link', 'content' => ['url' => 'x', 'links' => [['url' => 'y']]]]));
		$this->assertNull(actual: (new GroupTileNode())->fromLink(child: ['type' => 'link', 'content' => ['url' => '  ']]));
		$this->assertNull(actual: (new GroupTileNode())->fromLink(child: ['type' => 'link', 'content' => 'broken']));
		$this->assertNull(actual: (new GroupTileNode())->fromLink(child: ['type' => 'tile', 'content' => ['linkValue' => 'x']]));
		$this->assertNull(actual: (new GroupTileNode())->fromLink(child: ['type' => 'label', 'content' => ['url' => 'x']]));
	}//end testOtherChildrenStay()

	/**
	 * A group is converted with the groups nested in it, and says how many.
	 *
	 * @return void
	 */
	public function testAGroupIsConvertedWithItsNestedGroups(): void {
		$content = [
			'title' => 'Werk',
			'sortBy' => 'alphabetical',
			'placements' => [
				['type' => 'link', 'content' => ['label' => 'A', 'url' => 'https://a.example.org']],
				['type' => 'label', 'content' => ['text' => 'Hi']],
				'broken',
				['type' => 'container', 'content' => ['placements' => [
					['type' => 'link', 'content' => ['label' => 'B', 'url' => '/apps/files', 'actionType' => 'internal']],
				]]],
			],
		];
		$result  = (new GroupTileNode())->convertGroup(content: $content);
		$this->assertSame(expected: 2, actual: $result['changed']);
		$this->assertSame(expected: 'alphabetical', actual: $result['content']['sortBy']);
		$this->assertSame(expected: 'tile', actual: $result['content']['placements'][0]['type']);
		$this->assertSame(expected: ['type' => 'label', 'content' => ['text' => 'Hi']], actual: $result['content']['placements'][1]);
		$this->assertSame(expected: 'broken', actual: $result['content']['placements'][2]);
		$this->assertSame(expected: '/apps/files', actual: $result['content']['placements'][3]['content']['placements'][0]['content']['linkValue']);
	}//end testAGroupIsConvertedWithItsNestedGroups()

	/**
	 * Content without children, and a converted group, come back unchanged.
	 *
	 * @return void
	 */
	public function testNothingToConvertIsUnchanged(): void {
		$node = new GroupTileNode();
		$this->assertSame(expected: ['content' => ['text' => 'Hi'], 'changed' => 0], actual: $node->convertGroup(content: ['text' => 'Hi']));
		$link = ['type' => 'link', 'content' => ['label' => 'A', 'url' => 'https://a.example.org']];
		$once = $node->convertGroup(content: ['placements' => [$link]]);
		$this->assertSame(expected: 0, actual: $node->convertGroup(content: $once['content'])['changed']);
	}//end testNothingToConvertIsUnchanged()
}//end class
