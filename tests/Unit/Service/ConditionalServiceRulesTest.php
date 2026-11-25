<?php

/**
 * Unit tests for ConditionalService's rule evaluation and rule CRUD.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\RuleSetContext;
use DateTimeImmutable;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Db\ConditionalRuleMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\ConditionalService;
use OCA\LaunchPad\Service\RuleEvaluatorService;
use OCA\LaunchPad\Service\VisibilityChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * How a placement's visibility rules are evaluated, previewed and stored.
 *
 * @covers \OCA\LaunchPad\Service\ConditionalService
 *
 * @uses \OCA\LaunchPad\Db\ConditionalRule
 * @uses \OCA\LaunchPad\Db\WidgetPlacement
 */
class ConditionalServiceRulesTest extends TestCase {

	/**
	 * The rule mapper double.
	 *
	 * @var ConditionalRuleMapper&MockObject
	 */
	private ConditionalRuleMapper $rules;

	/**
	 * The rule evaluator double.
	 *
	 * @var RuleEvaluatorService&MockObject
	 */
	private RuleEvaluatorService $evaluator;

	/**
	 * The visibility checker double.
	 *
	 * @var VisibilityChecker&MockObject
	 */
	private VisibilityChecker $visibility;

	/**
	 * The placement mapper double.
	 *
	 * @var WidgetPlacementMapper&MockObject
	 */
	private WidgetPlacementMapper $placements;

	/**
	 * The service under test.
	 *
	 * @var ConditionalService
	 */
	private ConditionalService $service;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->rules = $this->createMock(originalClassName: ConditionalRuleMapper::class);
		$this->evaluator = $this->createMock(originalClassName: RuleEvaluatorService::class);
		$this->visibility = $this->createMock(originalClassName: VisibilityChecker::class);
		$this->placements = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$this->service = new ConditionalService(
			ruleMapper: $this->rules,
			ruleEvaluator: $this->evaluator,
			visibilityChecker: $this->visibility,
			placementMapper: $this->placements,
			dashboardMapper: $this->createMock(originalClassName: DashboardMapper::class),
		);
	}//end setUp()

	/**
	 * A stored rule.
	 *
	 * @param string               $type      The rule type.
	 * @param array<string, mixed> $config    The rule config.
	 * @param bool                 $isInclude Whether it includes.
	 *
	 * @return ConditionalRule
	 */
	private static function rule(string $type, array $config, bool $isInclude): ConditionalRule {
		$rule = new ConditionalRule();
		$rule->setWidgetPlacementId(5);
		$rule->setRuleType($type);
		$rule->setRuleConfigArray($config);
		$rule->setIsInclude($isInclude);

		return $rule;
	}//end rule()

	/**
	 * One rule is evaluated by the rule evaluator for the user.
	 *
	 * @return void
	 */
	public function testARuleIsEvaluatedByTheEvaluator(): void {
		$rule = self::rule(type: 'group', config: ['group' => 'staff'], isInclude: true);
		$this->evaluator->expects(self::once())->method('evaluateRule')->with($rule, 'alice')->willReturn(false);

		self::assertFalse(condition: $this->service->evaluateRule($rule, 'alice'));
	}//end testARuleIsEvaluatedByTheEvaluator()

	/**
	 * A placement without rules is visible without asking the checker; one with rules is decided by it.
	 *
	 * @return void
	 */
	public function testAPlacementIsVisibleWithoutRulesOtherwiseTheCheckerDecides(): void {
		$stored = [self::rule(type: 'group', config: ['group' => 'staff'], isInclude: true)];
		$this->rules->method('findByPlacementId')->willReturnMap([[1, []], [2, $stored]]);
		$this->visibility->expects(self::once())->method('checkRules')->with($stored, 'alice')->willReturn(false);

		self::assertTrue(condition: $this->service->checkRulesForPlacement(1, 'alice'));
		self::assertFalse(condition: $this->service->checkRulesForPlacement(2, 'alice'));
		self::assertSame(expected: $stored, actual: $this->service->getRules(2));
	}//end testAPlacementIsVisibleWithoutRulesOtherwiseTheCheckerDecides()

	/**
	 * A placement with isVisible 0 is hidden without evaluating its rules.
	 *
	 * @return void
	 */
	public function testAPlacementSwitchedOffIsHiddenWithoutEvaluatingRules(): void {
		$placement = new WidgetPlacement();
		$placement->setIsVisible(0);
		$this->placements->method('find')->with(5)->willReturn($placement);
		$this->rules->expects(self::never())->method('findByPlacementId');
		$this->visibility->expects(self::never())->method('checkRules');

		self::assertFalse(condition: $this->service->checkRulesForPlacement(placementId: 5, userId: 'alice'));
	}//end testAPlacementSwitchedOffIsHiddenWithoutEvaluatingRules()

	/**
	 * A preview passes the overrides through and answers the checker's verdict.
	 *
	 * @return void
	 */
	public function testAPreviewPassesTheOverridesThrough(): void {
		$rules = [self::rule(type: 'time', config: ['from' => '09:00'], isInclude: true)];
		$now = new DateTimeImmutable('2026-05-04T10:00:00Z');
		$verdict = ['visible' => true, 'reasons' => ['time window']];
		$context = new RuleSetContext(options: ['groups' => ['admin'], 'now' => $now, 'match' => 'all']);
		$this->visibility->expects(self::once())->method('evaluateRuleSet')->with($rules, 'alice', $context)->willReturn($verdict);

		self::assertSame(expected: $verdict, actual: $this->service->previewRules(rules: $rules, userId: 'alice', context: $context));
	}//end testAPreviewPassesTheOverridesThrough()

	/**
	 * A new rule is stored with its placement, type, config, include flag and a creation time
	 * in the SQL DATETIME form MySQL and MariaDB accept.
	 *
	 * @return void
	 */
	public function testANewRuleIsStoredWithItsFields(): void {
		$this->rules->expects(self::once())->method('insert')->willReturnArgument(0);

		$rule = $this->service->addRule(7, 'group', ['group' => 'staff'], false);

		self::assertSame(expected: 7, actual: $rule->getWidgetPlacementId());
		self::assertSame(expected: 'group', actual: $rule->getRuleType());
		self::assertSame(expected: ['group' => 'staff'], actual: $rule->getRuleConfigArray());
		self::assertFalse(condition: $rule->getIsInclude());
		self::assertNotFalse(condition: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string)$rule->getCreatedAt()));
	}//end testANewRuleIsStoredWithItsFields()

	/**
	 * An update changes only the fields it is given.
	 *
	 * @return void
	 */
	public function testAnUpdateChangesOnlyTheGivenFields(): void {
		$stored = self::rule(type: 'group', config: ['group' => 'staff'], isInclude: true);
		$this->rules->method('find')->with(3)->willReturn($stored);
		$this->rules->expects(self::exactly(count: 2))->method('update')->willReturnArgument(0);

		$updated = $this->service->updateRule(3, ['isInclude' => false]);
		self::assertSame(
			expected: ['group', ['group' => 'staff'], false],
			actual: [$updated->getRuleType(), $updated->getRuleConfigArray(), $updated->getIsInclude()]
		);

		$updated = $this->service->updateRule(3, ['ruleType' => 'time', 'ruleConfig' => ['from' => '08:00']]);
		self::assertSame(
			expected: ['time', ['from' => '08:00'], false],
			actual: [$updated->getRuleType(), $updated->getRuleConfigArray(), $updated->getIsInclude()]
		);
	}//end testAnUpdateChangesOnlyTheGivenFields()

	/**
	 * A rule is found and deleted by id.
	 *
	 * @return void
	 */
	public function testARuleIsFoundAndDeletedById(): void {
		$stored = self::rule(type: 'group', config: [], isInclude: true);
		$this->rules->method('find')->with(9)->willReturn($stored);
		$this->rules->expects(self::once())->method('delete')->with($stored);

		self::assertSame(expected: $stored, actual: $this->service->findRule(9));
		$this->service->deleteRule(9);
	}//end testARuleIsFoundAndDeletedById()
}//end class
