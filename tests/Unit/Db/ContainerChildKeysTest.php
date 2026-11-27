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
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */
#[CoversClass(ContainerChildKeys::class)]
#[CoversClass(WidgetPlacement::class)]
class ContainerChildKeysTest extends TestCase {

	public function testAWellsChildrenGetTheKeyTheWellAlreadyCountedWithAndKeepTheirOwn(): void {
		$content = ContainerChildKeys::stamp(content: [
			'title' => 'Apps',
			'placements' => [
				['type' => 'tile', 'id' => 5],
				['type' => 'tile'],
				['type' => 'text', 'uuid' => 'keep'],
			],
		], ownerId: 12);

		$this->assertSame('Apps', $content['title']);
		$this->assertSame(5, $content['placements'][0]['id']);
		$this->assertArrayNotHasKey('uuid', $content['placements'][0]);
		$this->assertSame('12-1', $content['placements'][1]['uuid']);
		$this->assertSame('keep', $content['placements'][2]['uuid']);
	}//end testAWellsChildrenGetTheKeyTheWellAlreadyCountedWithAndKeepTheirOwn()

	public function testAKeyAnotherChildHoldsIsNotHandedOutTwice(): void {
		$content = ContainerChildKeys::stamp(content: ['placements' => [['type' => 'tile', 'uuid' => '12-1'], ['type' => 'tile']]], ownerId: 12);

		$this->assertSame('12-1', $content['placements'][0]['uuid']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $content['placements'][1]['uuid']);
	}//end testAKeyAnotherChildHoldsIsNotHandedOutTwice()

	public function testTabsAndNestedChildrenGetRandomKeysAndStampingTwiceChangesNothing(): void {
		$content = ContainerChildKeys::stamp(content: [
			'tabs' => [
				['id' => 't1', 'placements' => [['type' => 'container', 'content' => ['placements' => [['type' => 'clock']]]]]],
				'not a tab',
			],
		], ownerId: 12);

		$outer = $content['tabs'][0]['placements'][0];
		$this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $outer['uuid']);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $outer['content']['placements'][0]['uuid']);
		$this->assertSame('not a tab', $content['tabs'][1]);
		$this->assertSame($content, ContainerChildKeys::stamp(content: $content, ownerId: 12));
	}//end testTabsAndNestedChildrenGetRandomKeysAndStampingTwiceChangesNothing()

	public function testContentWithoutChildrenIsUnchanged(): void {
		$this->assertSame(['text' => 'Hallo'], ContainerChildKeys::stamp(content: ['text' => 'Hallo'], ownerId: 3));
	}//end testContentWithoutChildrenIsUnchanged()

	public function testAChildIsFoundByItsKeyAtAnyDepth(): void {
		$content = [
			'placements' => [['type' => 'tile', 'id' => 5]],
			'tabs' => [['placements' => [['type' => 'container', 'uuid' => 'w', 'content' => ['placements' => [['type' => 'clock', 'uuid' => 'deep']]]]]]],
		];

		$this->assertSame(['type' => 'tile', 'id' => 5], ContainerChildKeys::find(content: $content, key: '5'));
		$this->assertSame(['type' => 'clock', 'uuid' => 'deep'], ContainerChildKeys::find(content: $content, key: 'deep'));
		$this->assertNull(ContainerChildKeys::find(content: $content, key: 'missing'));
		$this->assertNull(ContainerChildKeys::find(content: $content, key: ''));
	}//end testAChildIsFoundByItsKeyAtAnyDepth()

	public function testAnEmbedSubjectNamesAChildOnlyAsPlacementDotKey(): void {
		$this->assertSame([12, '12-0'], ContainerChildKeys::splitSubject(subjectId: '12.12-0'));
		$this->assertSame([7, 'ab_CD'], ContainerChildKeys::splitSubject(subjectId: '7.ab_CD'));
		$this->assertNull(ContainerChildKeys::splitSubject(subjectId: '12'));
		$this->assertNull(ContainerChildKeys::splitSubject(subjectId: '012.a'));
		$this->assertNull(ContainerChildKeys::splitSubject(subjectId: '12.a/b'));
		$this->assertNull(ContainerChildKeys::splitSubject(subjectId: '12.'));
		$this->assertNull(ContainerChildKeys::splitSubject(subjectId: 'x.a'));
	}//end testAnEmbedSubjectNamesAChildOnlyAsPlacementDotKey()

	public function testThePlacementStampsItsChildrenWhenItsContentIsSet(): void {
		$placement = new WidgetPlacement();
		$placement->setId(9);
		$placement->setContentArray(['placements' => [['type' => 'tile']]]);
		$this->assertSame('9-0', $placement->getContentArray()['placements'][0]['uuid']);

		$fresh = new WidgetPlacement();
		$fresh->setContentArray(['placements' => [['type' => 'tile']]]);
		$this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $fresh->getContentArray()['placements'][0]['uuid']);
	}//end testThePlacementStampsItsChildrenWhenItsContentIsSet()
}//end class
