<?php

/**
 * Unit tests for ConditionalService's rule evaluation and rule CRUD.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTimeImmutable;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Db\ConditionalRuleMapper;
use OCA\LaunchPad\Db\DashboardMapper;
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
		$this->rules = $this->createMock(ConditionalRuleMapper::class);
		$this->evaluator = $this->createMock(RuleEvaluatorService::class);
		$this->visibility = $this->createMock(VisibilityChecker::class);
		$this->service = new ConditionalService(
			ruleMapper: $this->rules,
			ruleEvaluator: $this->evaluator,
			visibilityChecker: $this->visibility,
			placementMapper: $this->createMock(WidgetPlacementMapper::class),
			dashboardMapper: $this->createMock(DashboardMapper::class),
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
		$rule = self::rule('group', ['group' => 'staff'], true);
		$this->evaluator->expects(self::once())->method('evaluateRule')->with($rule, 'alice')->willReturn(false);

		self::assertFalse($this->service->evaluateRule($rule, 'alice'));
	}//end testARuleIsEvaluatedByTheEvaluator()

	/**
	 * A placement without rules is visible without asking the checker; one with rules is decided by it.
	 *
	 * @return void
	 */
	public function testAPlacementIsVisibleWithoutRulesOtherwiseTheCheckerDecides(): void {
		$stored = [self::rule('group', ['group' => 'staff'], true)];
		$this->rules->method('findByPlacementId')->willReturnMap([[1, []], [2, $stored]]);
		$this->visibility->expects(self::once())->method('checkRules')->with($stored, 'alice')->willReturn(false);

		self::assertTrue($this->service->checkRulesForPlacement(1, 'alice'));
		self::assertFalse($this->service->checkRulesForPlacement(2, 'alice'));
		self::assertSame($stored, $this->service->getRules(2));
	}//end testAPlacementIsVisibleWithoutRulesOtherwiseTheCheckerDecides()

	/**
	 * A preview passes the overrides through and answers the checker's verdict.
	 *
	 * @return void
	 */
	public function testAPreviewPassesTheOverridesThrough(): void {
		$rules = [self::rule('time', ['from' => '09:00'], true)];
		$now = new DateTimeImmutable('2026-05-04T10:00:00Z');
		$verdict = ['visible' => true, 'reasons' => ['time window']];
		$this->visibility->expects(self::once())->method('evaluateRuleSet')->with($rules, 'alice', ['admin'], $now)->willReturn($verdict);

		self::assertSame($verdict, $this->service->previewRules($rules, 'alice', ['admin'], $now));
	}//end testAPreviewPassesTheOverridesThrough()

	/**
	 * A new rule is stored with its placement, type, config, include flag and a creation time.
	 *
	 * @return void
	 */
	public function testANewRuleIsStoredWithItsFields(): void {
		$this->rules->expects(self::once())->method('insert')->willReturnArgument(0);

		$rule = $this->service->addRule(7, 'group', ['group' => 'staff'], false);

		self::assertSame(7, $rule->getWidgetPlacementId());
		self::assertSame('group', $rule->getRuleType());
		self::assertSame(['group' => 'staff'], $rule->getRuleConfigArray());
		self::assertFalse($rule->getIsInclude());
		self::assertNotFalse(DateTimeImmutable::createFromFormat(DATE_ATOM, (string)$rule->getCreatedAt()));
	}//end testANewRuleIsStoredWithItsFields()

	/**
	 * An update changes only the fields it is given.
	 *
	 * @return void
	 */
	public function testAnUpdateChangesOnlyTheGivenFields(): void {
		$stored = self::rule('group', ['group' => 'staff'], true);
		$this->rules->method('find')->with(3)->willReturn($stored);
		$this->rules->expects(self::exactly(2))->method('update')->willReturnArgument(0);

		$updated = $this->service->updateRule(3, ['isInclude' => false]);
		self::assertSame(['group', ['group' => 'staff'], false], [$updated->getRuleType(), $updated->getRuleConfigArray(), $updated->getIsInclude()]);

		$updated = $this->service->updateRule(3, ['ruleType' => 'time', 'ruleConfig' => ['from' => '08:00']]);
		self::assertSame(['time', ['from' => '08:00'], false], [$updated->getRuleType(), $updated->getRuleConfigArray(), $updated->getIsInclude()]);
	}//end testAnUpdateChangesOnlyTheGivenFields()

	/**
	 * A rule is found and deleted by id.
	 *
	 * @return void
	 */
	public function testARuleIsFoundAndDeletedById(): void {
		$stored = self::rule('group', [], true);
		$this->rules->method('find')->with(9)->willReturn($stored);
		$this->rules->expects(self::once())->method('delete')->with($stored);

		self::assertSame($stored, $this->service->findRule(9));
		$this->service->deleteRule(9);
	}//end testARuleIsFoundAndDeletedById()
}//end class
