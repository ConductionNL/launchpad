<?php

/**
 * Unit tests for ConditionalService::visiblePlacements(), the visibility
 * rules applied when a dashboard renders (REQ-VIS-013).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Db\ConditionalRuleMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ConditionalService;
use OCA\LaunchPad\Service\RuleEvaluatorService;
use OCA\LaunchPad\Service\UserAttributeResolver;
use OCA\LaunchPad\Service\VisibilityChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Which placements a viewer and an editor get when a dashboard renders.
 *
 * @covers \OCA\LaunchPad\Service\ConditionalService
 *
 * @uses \OCA\LaunchPad\Db\ConditionalRule
 * @uses \OCA\LaunchPad\Service\VisibilityChecker
 * @uses \OCA\LaunchPad\Service\RuleEvaluatorService
 */
class ConditionalServiceRenderTest extends TestCase {

	/**
	 * The rule mapper double.
	 *
	 * @var ConditionalRuleMapper&MockObject
	 */
	private ConditionalRuleMapper $rules;

	/**
	 * The service under test, over the real checker and evaluator.
	 *
	 * @var ConditionalService
	 */
	private ConditionalService $service;

	/**
	 * Set up: alice is in group "staff" only.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->rules = $this->createMock(originalClassName: ConditionalRuleMapper::class);
		$templates = $this->createMock(originalClassName: AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnMap(
			valueMap: [['alice', ['staff']], ['bob', []], ['', []]]
		);
		$evaluator = new RuleEvaluatorService(
			adminTemplateService: $templates,
			attrResolver: $this->createMock(originalClassName: UserAttributeResolver::class)
		);

		$this->service = new ConditionalService(
			ruleMapper: $this->rules,
			ruleEvaluator: $evaluator,
			visibilityChecker: new VisibilityChecker(ruleEvaluator: $evaluator),
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			dashboardMapper: $this->createMock(originalClassName: DashboardMapper::class),
		);
	}//end setUp()

	/**
	 * A group rule on one placement.
	 *
	 * @param int    $placementId The placement it belongs to.
	 * @param string $group       The group it names.
	 * @param bool   $isInclude   Whether it shows (true) or hides (false) on a match.
	 *
	 * @return ConditionalRule The rule.
	 */
	private static function groupRule(int $placementId, string $group, bool $isInclude): ConditionalRule {
		$rule = new ConditionalRule();
		$rule->setId($placementId * 10);
		$rule->setWidgetPlacementId($placementId);
		$rule->setRuleType(ConditionalRule::TYPE_GROUP);
		$rule->setRuleConfig(json_encode(value: ['groups' => [$group]]));
		$rule->setIsInclude($isInclude);

		return $rule;
	}//end groupRule()

	/**
	 * Three placements: 1 without rules, 2 only for "staff", 3 hidden from "staff".
	 *
	 * @return array<int, array<string, mixed>> The serialised placements.
	 */
	private function placements(): array {
		$this->rules->expects($this->once())
			->method('findByPlacementIds')
			->with([1, 2, 3])
			->willReturn(
				[
					self::groupRule(placementId: 2, group: 'staff', isInclude: true),
					self::groupRule(placementId: 3, group: 'staff', isInclude: false),
				]
			);

		return [
			['id' => 1, 'widgetId' => 'text'],
			['id' => 2, 'widgetId' => 'news'],
			['id' => 3, 'widgetId' => 'links'],
		];
	}//end placements()

	/**
	 * A viewer does not get a widget the rules hide for them.
	 *
	 * @return void
	 */
	public function testAViewerDoesNotGetAHiddenWidget(): void {
		$rendered = $this->service->visiblePlacements(placements: $this->placements(), userId: 'bob', canEdit: false);

		$this->assertSame(
			expected: [
				['id' => 1, 'widgetId' => 'text', 'hiddenForViewers' => false],
				['id' => 3, 'widgetId' => 'links', 'hiddenForViewers' => false],
			],
			actual: $rendered
		);
	}//end testAViewerDoesNotGetAHiddenWidget()

	/**
	 * The rules are evaluated for the viewer: alice in "staff" gets the other set.
	 *
	 * @return void
	 */
	public function testTheRulesAreEvaluatedForTheViewer(): void {
		$rendered = $this->service->visiblePlacements(placements: $this->placements(), userId: 'alice', canEdit: false);

		$this->assertSame(expected: [1, 2], actual: array_column(array: $rendered, column_key: 'id'));
	}//end testTheRulesAreEvaluatedForTheViewer()

	/**
	 * An editor keeps the hidden widget, marked hiddenForViewers.
	 *
	 * @return void
	 */
	public function testAnEditorKeepsTheHiddenWidgetMarked(): void {
		$rendered = $this->service->visiblePlacements(placements: $this->placements(), userId: 'bob', canEdit: true);

		$this->assertSame(
			expected: [1 => false, 2 => true, 3 => false],
			actual: array_column(array: $rendered, column_key: 'hiddenForViewers', index_key: 'id')
		);
	}//end testAnEditorKeepsTheHiddenWidgetMarked()

	/**
	 * A visitor without an account matches no group rule.
	 *
	 * @return void
	 */
	public function testAVisitorWithoutAnAccountMatchesNoGroup(): void {
		$rendered = $this->service->visiblePlacements(placements: $this->placements(), userId: '', canEdit: false);

		$this->assertSame(expected: [1, 3], actual: array_column(array: $rendered, column_key: 'id'));
	}//end testAVisitorWithoutAnAccountMatchesNoGroup()

	/**
	 * A placement switched off with isVisible 0 is hidden whatever its rules say.
	 *
	 * @return void
	 */
	public function testAPlacementSwitchedOffIsHidden(): void {
		$this->rules->method('findByPlacementIds')->willReturn([self::groupRule(placementId: 2, group: 'staff', isInclude: true)]);
		$placements = [['id' => 1, 'isVisible' => 0], ['id' => 2, 'isVisible' => 0], ['id' => 3, 'isVisible' => 1]];

		$this->assertSame(
			expected: [3],
			actual: array_column(array: $this->service->visiblePlacements(placements: $placements, userId: 'alice', canEdit: false), column_key: 'id')
		);
		$this->assertSame(
			expected: [1 => true, 2 => true, 3 => false],
			actual: array_column(
				array: $this->service->visiblePlacements(placements: $placements, userId: 'alice', canEdit: true),
				column_key: 'hiddenForViewers',
				index_key: 'id'
			)
		);
	}//end testAPlacementSwitchedOffIsHidden()

	/**
	 * No placements read no rules.
	 *
	 * @return void
	 */
	public function testNoPlacementsReadNothing(): void {
		$this->rules->expects($this->once())->method('findByPlacementIds')->with([])->willReturn([]);

		$this->assertSame(expected: [], actual: $this->service->visiblePlacements(placements: [], userId: 'bob', canEdit: false));
	}//end testNoPlacementsReadNothing()

	/**
	 * A placement whose two include rules must both match is hidden when
	 * only one matches, and shown when it needs only one (REQ-VIS-014).
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	public function testThePlacementsMatchModeIsUsed(): void {
		$this->rules->method('findByPlacementIds')->willReturn(
			[
				self::groupRule(placementId: 4, group: 'staff', isInclude: true),
				self::groupRule(placementId: 4, group: 'board', isInclude: true),
				self::groupRule(placementId: 5, group: 'staff', isInclude: true),
				self::groupRule(placementId: 5, group: 'board', isInclude: true),
			]
		);
		$placements = [
			['id' => 4, 'widgetId' => 'news', 'ruleMatch' => 'all'],
			['id' => 5, 'widgetId' => 'news', 'ruleMatch' => 'any'],
		];

		$this->assertSame(
			expected: [5],
			actual: array_column(array: $this->service->visiblePlacements(placements: $placements, userId: 'alice', canEdit: false), column_key: 'id')
		);
	}//end testThePlacementsMatchModeIsUsed()
}//end class
