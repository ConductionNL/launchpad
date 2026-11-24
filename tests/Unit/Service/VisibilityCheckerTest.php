<?php

/**
 * VisibilityChecker Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Service\RuleEvaluatorService;
use OCA\LaunchPad\Service\VisibilityChecker;
use PHPUnit\Framework\TestCase;

class VisibilityCheckerTest extends TestCase {
	private VisibilityChecker $checker;
	private RuleEvaluatorService $ruleEvaluator;

	protected function setUp(): void {
		$this->ruleEvaluator = $this->createMock(RuleEvaluatorService::class);
		$this->checker = new VisibilityChecker(
			ruleEvaluator: $this->ruleEvaluator,
		);
	}

	private function createRule(bool $isInclude): ConditionalRule {
		$rule = new ConditionalRule();
		$rule->setIsInclude($isInclude);
		$rule->setRuleType('group');
		return $rule;
	}

	private function createRuleWithId(bool $isInclude, int $id): ConditionalRule {
		$rule = $this->createRule($isInclude);
		$rule->setId($id);
		return $rule;
	}

	public function testNoRulesReturnsVisible(): void {
		$this->assertTrue(
			$this->checker->checkRules(rules: [], userId: 'alice')
		);
	}

	public function testSingleIncludeRuleMatching(): void {
		$rule = $this->createRule(true);
		$this->ruleEvaluator->method('evaluateRule')->willReturn(true);

		$this->assertTrue(
			$this->checker->checkRules(rules: [$rule], userId: 'alice')
		);
	}

	public function testSingleIncludeRuleNotMatching(): void {
		$rule = $this->createRule(true);
		$this->ruleEvaluator->method('evaluateRule')->willReturn(false);

		$this->assertFalse(
			$this->checker->checkRules(rules: [$rule], userId: 'alice')
		);
	}

	public function testIncludeRulesOrLogicOneMatches(): void {
		$rule1 = $this->createRule(true);
		$rule2 = $this->createRule(true);

		$this->ruleEvaluator->method('evaluateRule')
			->willReturnOnConsecutiveCalls(false, true);

		$this->assertTrue(
			$this->checker->checkRules(
				rules: [$rule1, $rule2],
				userId: 'alice'
			)
		);
	}

	public function testIncludeRulesOrLogicNoneMatch(): void {
		$rule1 = $this->createRule(true);
		$rule2 = $this->createRule(true);

		$this->ruleEvaluator->method('evaluateRule')->willReturn(false);

		$this->assertFalse(
			$this->checker->checkRules(
				rules: [$rule1, $rule2],
				userId: 'alice'
			)
		);
	}

	public function testSingleExcludeRuleMatching(): void {
		$rule = $this->createRule(false);
		$this->ruleEvaluator->method('evaluateRule')->willReturn(true);

		$this->assertFalse(
			$this->checker->checkRules(rules: [$rule], userId: 'alice')
		);
	}

	public function testSingleExcludeRuleNotMatching(): void {
		$rule = $this->createRule(false);
		$this->ruleEvaluator->method('evaluateRule')->willReturn(false);

		$this->assertTrue(
			$this->checker->checkRules(rules: [$rule], userId: 'alice')
		);
	}

	public function testExcludeRulesAndLogicAnyMatchHides(): void {
		$rule1 = $this->createRule(false);
		$rule2 = $this->createRule(false);

		$this->ruleEvaluator->method('evaluateRule')
			->willReturnOnConsecutiveCalls(true, false);

		$this->assertFalse(
			$this->checker->checkRules(
				rules: [$rule1, $rule2],
				userId: 'alice'
			)
		);
	}

	public function testMixedRulesIncludePassesExcludeFails(): void {
		$include = $this->createRule(true);
		$exclude = $this->createRule(false);

		$this->ruleEvaluator->method('evaluateRule')
			->willReturnCallback(function ($rule) {
				// Include rule matches, exclude rule also matches
				return true;
			});

		// Include passes (OR: one matches), but exclude fails (match = hide)
		$this->assertFalse(
			$this->checker->checkRules(
				rules: [$include, $exclude],
				userId: 'alice'
			)
		);
	}

	public function testMixedRulesIncludePassesExcludePasses(): void {
		$include = $this->createRule(true);
		$exclude = $this->createRule(false);

		$this->ruleEvaluator->method('evaluateRule')
			->willReturnCallback(function ($rule) {
				// Include matches, exclude does NOT match
				return $rule->getIsInclude();
			});

		$this->assertTrue(
			$this->checker->checkRules(
				rules: [$include, $exclude],
				userId: 'alice'
			)
		);
	}

	// -----------------------------------------------------------------------
	// evaluateRuleSet() — conditional-visibility-editor REQ-CVUI-005.
	// checkRules() above proves the boolean is unchanged; these prove the
	// SAME method also reports which rules matched, and that checkRules()
	// is a thin wrapper over it (no forked combination logic).
	// -----------------------------------------------------------------------

	public function testEvaluateRuleSetReportsMatchedIncludeRuleId(): void {
		$rule = $this->createRuleWithId(true, 1);
		$this->ruleEvaluator->method('evaluateRule')->willReturn(true);

		$result = $this->checker->evaluateRuleSet(rules: [$rule], userId: 'alice');

		$this->assertTrue($result['visible']);
		$this->assertSame([1], $result['matchedIncludeRuleIds']);
		$this->assertSame([], $result['matchedExcludeRuleIds']);
	}

	public function testEvaluateRuleSetReportsMatchedExcludeRuleIdAsOverrideReason(): void {
		$include = $this->createRuleWithId(true, 1);
		$exclude = $this->createRuleWithId(false, 2);
		$this->ruleEvaluator->method('evaluateRule')->willReturn(true);

		$result = $this->checker->evaluateRuleSet(
			rules: [$include, $exclude],
			userId: 'alice'
		);

		$this->assertFalse($result['visible']);
		$this->assertSame([1], $result['matchedIncludeRuleIds']);
		$this->assertSame([2], $result['matchedExcludeRuleIds']);
	}

	public function testCheckRulesIsAThinWrapperOverEvaluateRuleSet(): void {
		$rule = $this->createRuleWithId(true, 1);
		$this->ruleEvaluator->method('evaluateRule')->willReturn(true);

		$bool = $this->checker->checkRules(rules: [$rule], userId: 'alice');
		$set = $this->checker->evaluateRuleSet(rules: [$rule], userId: 'alice');

		$this->assertSame($set['visible'], $bool);
	}

	public function testEvaluateRuleSetForwardsGroupsOverrideToRuleEvaluator(): void {
		$rule = $this->createRuleWithId(true, 1);
		$this->ruleEvaluator->expects($this->once())
			->method('evaluateRule')
			->with(
				rule: $rule,
				userId: 'alice',
				groupsOverride: ['marketing'],
				nowOverride: null
			)
			->willReturn(true);

		$result = $this->checker->evaluateRuleSet(
			rules: [$rule],
			userId: 'alice',
			groupsOverride: ['marketing']
		);

		$this->assertTrue($result['visible']);
	}

	/**
	 * In `all` mode every include rule must match; in `any` mode one does.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	public function testAllModeNeedsEveryIncludeRule(): void {
		$rules = [$this->createRuleWithId(true, 1), $this->createRuleWithId(true, 2)];
		$this->ruleEvaluator->method('evaluateRule')
			->willReturnCallback(static fn (ConditionalRule $rule): bool => $rule->getId() === 1);

		$all = $this->checker->evaluateRuleSet(rules: $rules, userId: 'alice', match: 'all');
		$any = $this->checker->evaluateRuleSet(rules: $rules, userId: 'alice', match: 'any');

		$this->assertFalse($all['visible']);
		$this->assertSame([1], $all['matchedIncludeRuleIds']);
		$this->assertTrue($any['visible']);
		$this->assertFalse($this->checker->checkRules(rules: $rules, userId: 'alice', match: 'all'));
		$this->assertTrue($this->checker->checkRules(rules: $rules, userId: 'alice'));
	}

	/**
	 * In `all` mode a widget shows when every include rule matches, and an
	 * exclude rule that matches still hides it.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	public function testAllModeKeepsExcludeRulesHiding(): void {
		$include = [$this->createRuleWithId(true, 1), $this->createRuleWithId(true, 2)];
		$exclude = $this->createRuleWithId(false, 3);
		$this->ruleEvaluator->method('evaluateRule')->willReturn(true);

		$this->assertFalse($this->checker->checkRules(rules: [...$include, $exclude], userId: 'alice', match: 'all'));
		$this->assertTrue($this->checker->checkRules(rules: $include, userId: 'alice', match: 'all'));
	}

	/**
	 * In `all` mode no include rules still means no restriction.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	public function testAllModeWithoutIncludeRulesIsVisible(): void {
		$this->ruleEvaluator->method('evaluateRule')->willReturn(false);

		$this->assertTrue($this->checker->checkRules(rules: [$this->createRuleWithId(false, 3)], userId: 'alice', match: 'all'));
		$this->assertTrue($this->checker->checkRules(rules: [], userId: 'alice', match: 'all'));
	}
}
