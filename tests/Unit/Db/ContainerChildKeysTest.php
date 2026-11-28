<?php

/**
 * ContainerChildKeys unit tests: every child of a well or a tabs widget gets
 * a key of its own, so an embed token can name it (REQ-WDG-050).
 *
 * @category  Test
 * @package   Unit\Db
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

namespace Unit\Db;

use OCA\LaunchPad\Db\ContainerChildKeys;
use OCA\LaunchPad\Db\WidgetPlacement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The keys of the children of wells and tabs widgets.
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
#[CoversClass(ContainerChildKeys::class)]
#[CoversClass(WidgetPlacement::class)]
class ContainerChildKeysTest extends TestCase {

	/**
	 * A well's direct children get the key the well already counted tile
	 * use with; a child with an id or uuid keeps it.
	 *
	 * @return void
	 */
	public function testAWellsChildrenGetTheKeyTheWellAlreadyCountedWithAndKeepTheirOwn(): void {
		$content = (new ContainerChildKeys())->stamp(
			content: [
				'title'      => 'Apps',
				'placements' => [
					['type' => 'tile', 'id' => 5],
					['type' => 'tile'],
					['type' => 'text', 'uuid' => 'keep'],
				],
			],
			ownerId: 12
		);

		$this->assertSame(expected: 'Apps', actual: $content['title']);
		$this->assertSame(expected: 5, actual: $content['placements'][0]['id']);
		$this->assertArrayNotHasKey(key: 'uuid', array: $content['placements'][0]);
		$this->assertSame(expected: '12-1', actual: $content['placements'][1]['uuid']);
		$this->assertSame(expected: 'keep', actual: $content['placements'][2]['uuid']);
	}//end testAWellsChildrenGetTheKeyTheWellAlreadyCountedWithAndKeepTheirOwn()

	/**
	 * A key another child holds is not handed out twice.
	 *
	 * @return void
	 */
	public function testAKeyAnotherChildHoldsIsNotHandedOutTwice(): void {
		$content = (new ContainerChildKeys())->stamp(
			content: ['placements' => [['type' => 'tile', 'uuid' => '12-1'], ['type' => 'tile']]],
			ownerId: 12
		);

		$this->assertSame(expected: '12-1', actual: $content['placements'][0]['uuid']);
		$this->assertMatchesRegularExpression(pattern: '/^[0-9a-f]{16}$/', string: $content['placements'][1]['uuid']);
	}//end testAKeyAnotherChildHoldsIsNotHandedOutTwice()

	/**
	 * Children inside tabs and nested wells get derived keys; stamping again
	 * changes nothing.
	 *
	 * @return void
	 */
	public function testTabsAndNestedChildrenGetDerivedKeysAndStampingTwiceChangesNothing(): void {
		$keys    = new ContainerChildKeys();
		$content = $keys->stamp(
			content: [
				'tabs' => [
					['id' => 't1', 'placements' => [['type' => 'container', 'content' => ['placements' => [['type' => 'clock']]]]]],
					'not a tab',
				],
			],
			ownerId: 12
		);

		$outer = $content['tabs'][0]['placements'][0];
		$this->assertMatchesRegularExpression(pattern: '/^[0-9a-f]{16}$/', string: $outer['uuid']);
		$this->assertMatchesRegularExpression(pattern: '/^[0-9a-f]{16}$/', string: $outer['content']['placements'][0]['uuid']);
		$this->assertSame(expected: 'not a tab', actual: $content['tabs'][1]);
		$this->assertSame(expected: $content, actual: $keys->stamp(content: $content, ownerId: 12));
	}//end testTabsAndNestedChildrenGetDerivedKeysAndStampingTwiceChangesNothing()

	/**
	 * The same content written twice gets the same keys, and two equal
	 * children still get different keys.
	 *
	 * @return void
	 */
	public function testTheSameContentWrittenTwiceGetsTheSameKeysAndTwinsDiffer(): void {
		$keys    = new ContainerChildKeys();
		$content = ['tabs' => [['placements' => [['type' => 'clock'], ['type' => 'clock']]]]];
		$first   = $keys->stamp(content: $content);

		$this->assertSame(expected: $first, actual: $keys->stamp(content: $content));
		$twins = $first['tabs'][0]['placements'];
		$this->assertNotSame(expected: $twins[0]['uuid'], actual: $twins[1]['uuid']);
	}//end testTheSameContentWrittenTwiceGetsTheSameKeysAndTwinsDiffer()

	/**
	 * Content without children is unchanged.
	 *
	 * @return void
	 */
	public function testContentWithoutChildrenIsUnchanged(): void {
		$this->assertSame(expected: ['text' => 'Hallo'], actual: (new ContainerChildKeys())->stamp(content: ['text' => 'Hallo'], ownerId: 3));
	}//end testContentWithoutChildrenIsUnchanged()

	/**
	 * A child is found by its key at any depth.
	 *
	 * @return void
	 */
	public function testAChildIsFoundByItsKeyAtAnyDepth(): void {
		$keys    = new ContainerChildKeys();
		$content = [
			'placements' => [['type' => 'tile', 'id' => 5]],
			'tabs'       => [
				['placements' => [['type' => 'container', 'uuid' => 'w', 'content' => ['placements' => [['type' => 'clock', 'uuid' => 'deep']]]]]],
			],
		];

		$this->assertSame(expected: ['type' => 'tile', 'id' => 5], actual: $keys->find(content: $content, key: '5'));
		$this->assertSame(expected: ['type' => 'clock', 'uuid' => 'deep'], actual: $keys->find(content: $content, key: 'deep'));
		$this->assertNull(actual: $keys->find(content: $content, key: 'missing'));
		$this->assertNull(actual: $keys->find(content: $content, key: ''));
	}//end testAChildIsFoundByItsKeyAtAnyDepth()

	/**
	 * An embed subject names a child only as `<placement id>.<key>`.
	 *
	 * @return void
	 */
	public function testAnEmbedSubjectNamesAChildOnlyAsPlacementDotKey(): void {
		$keys = new ContainerChildKeys();

		$this->assertSame(expected: [12, '12-0'], actual: $keys->splitSubject(subjectId: '12.12-0'));
		$this->assertSame(expected: [7, 'ab_CD'], actual: $keys->splitSubject(subjectId: '7.ab_CD'));
		$this->assertNull(actual: $keys->splitSubject(subjectId: '12'));
		$this->assertNull(actual: $keys->splitSubject(subjectId: '012.a'));
		$this->assertNull(actual: $keys->splitSubject(subjectId: '12.a/b'));
		$this->assertNull(actual: $keys->splitSubject(subjectId: '12.'));
		$this->assertNull(actual: $keys->splitSubject(subjectId: 'x.a'));
	}//end testAnEmbedSubjectNamesAChildOnlyAsPlacementDotKey()

	/**
	 * The placement stamps its children whenever its content is set.
	 *
	 * @return void
	 */
	public function testThePlacementStampsItsChildrenWhenItsContentIsSet(): void {
		$placement = new WidgetPlacement();
		$placement->setId(9);
		$placement->setContentArray(content: ['placements' => [['type' => 'tile']]]);
		$this->assertSame(expected: '9-0', actual: $placement->getContentArray()['placements'][0]['uuid']);

		$fresh = new WidgetPlacement();
		$fresh->setContentArray(content: ['placements' => [['type' => 'tile']]]);
		$this->assertMatchesRegularExpression(pattern: '/^[0-9a-f]{16}$/', string: $fresh->getContentArray()['placements'][0]['uuid']);
	}//end testThePlacementStampsItsChildrenWhenItsContentIsSet()
}//end class
