<?php

/**
 * VisibilityChecker
 *
 * Service for checking widget visibility based on conditional rules.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use Closure;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Service for checking widget visibility based on conditional rules.
 *
 * @spec openspec/specs/conditional-visibility/spec.md
 */
class VisibilityChecker {
	/**
	 * Constructor
	 *
	 * @param RuleEvaluatorService $ruleEvaluator The rule evaluator service.
	 */
	public function __construct(
		private readonly RuleEvaluatorService $ruleEvaluator,
	) {
	}//end __construct()

	/**
	 * Check rules to determine visibility.
	 *
	 * Include rules use OR logic (at least one must match), or with
	 * `$match = 'all'` every include rule must match (REQ-VIS-014).
	 * Exclude rules use AND logic (any match hides the widget).
	 *
	 * Thin boolean-only wrapper around {@see self::evaluateRuleSet()} — the
	 * ONLY place the OR/exclude-AND combination logic lives. Render-time
	 * callers (`ConditionalService::checkRulesForPlacement()`) keep calling
	 * this method unchanged.
	 *
	 * @param ConditionalRule[] $rules The rules to check.
	 * @param string $userId The user ID.
	 * @param string $match `any` (one include rule is enough) or `all`.
	 *
	 * @return bool Whether the widget should be visible.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-13
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	public function checkRules(array $rules, string $userId, string $match = WidgetPlacement::RULE_MATCH_ANY): bool {
		return $this->evaluateRuleSet(
			rules: $rules,
			userId: $userId,
			context: new RuleSetContext(options: ['match' => $match])
		)['visible'];
	}//end checkRules()

	/**
	 * Evaluate a rule set and report which rules matched, in addition to the
	 * final visibility verdict.
	 *
	 * Reuses the exact same include=OR / exclude=AND combination logic as
	 * {@see self::checkRules()} (both call this method — `checkRules()` just
	 * discards the matched-id detail) so the preview path
	 * (conditional-visibility-editor spec, REQ-CVUI-005) can never diverge
	 * from render-time visibility. The context's groups and moment are
	 * forwarded to {@see RuleEvaluatorService::evaluateRule()} and are only
	 * ever set by the preview; its match mode says whether all include rules
	 * must match (REQ-VIS-014).
	 *
	 * @param ConditionalRule[]   $rules   The rules to check.
	 * @param string              $userId  The user ID.
	 * @param RuleSetContext|null $context Groups, moment and match mode;
	 *                                     null for the user's own groups,
	 *                                     now, and `any`.
	 *
	 * @return array{visible: bool, matchedIncludeRuleIds: int[], matchedExcludeRuleIds: int[]}
	 *
	 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-005-preview-endpoint-reuses-the-render-time-evaluation-path-and-never-persists
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	public function evaluateRuleSet(array $rules, string $userId, ?RuleSetContext $context = null): array {
		$context ??= new RuleSetContext();
		$matches = fn (ConditionalRule $rule): bool => $this->ruleEvaluator->evaluateRule(
			rule: $rule,
			userId: $userId,
			groupsOverride: $context->groups,
			nowOverride: $context->now
		) === true;

		return $this->evaluateWith(rules: $rules, how: ['matches' => $matches, 'match' => $context->match]);
	}//end evaluateRuleSet()

	/**
	 * Evaluate a rule set with a match predicate: include rules OR-ed, or
	 * all required with `$match = 'all'` (an empty include group passes),
	 * exclude rules AND-ed.
	 *
	 * @param ConditionalRule[]                                         $rules The rules to evaluate.
	 * @param array{matches: Closure(ConditionalRule): bool, match: string} $how   Whether one rule
	 *                                                                             matches, and `any`
	 *                                                                             or `all`.
	 *
	 * @return array{visible: bool, matchedIncludeRuleIds: int[], matchedExcludeRuleIds: int[]}
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	private function evaluateWith(array $rules, array $how): array {
		$matches = $how['matches'];
		$includeRules = $this->filterByType(
			rules: $rules,
			isInclude: true
		);
		$excludeRules = $this->filterByType(
			rules: $rules,
			isInclude: false
		);

		// Include rules: an empty group passes, otherwise one match (OR) or,
		// in `all` mode, every rule must match.
		$includeIds = $this->matchingRuleIds(rules: $includeRules, matches: $matches);
		if (count($includeIds) < self::neededMatches(includeCount: count($includeRules), match: $how['match'])) {
			return [
				'visible' => false,
				'matchedIncludeRuleIds' => $includeIds,
				'matchedExcludeRuleIds' => [],
			];
		}

		// Exclude rules: any match hides the widget (AND).
		$excludeIds = $this->matchingRuleIds(rules: $excludeRules, matches: $matches);

		return [
			'visible' => $excludeIds === [],
			'matchedIncludeRuleIds' => $includeIds,
			'matchedExcludeRuleIds' => $excludeIds,
		];
	}//end evaluateWith()

	/**
	 * How many include rules must match for the widget to show: none when
	 * there are no include rules, all of them in `all` mode, else one.
	 *
	 * @param int    $includeCount The number of include rules.
	 * @param string $match        `any` or `all`.
	 *
	 * @return int The number that must match.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	private static function neededMatches(int $includeCount, string $match): int {
		if ($match === WidgetPlacement::RULE_MATCH_ALL) {
			return $includeCount;
		}

		return min($includeCount, 1);
	}//end neededMatches()

	/**
	 * Filter rules by include/exclude type.
	 *
	 * @param ConditionalRule[] $rules The rules to filter.
	 * @param bool $isInclude Whether to get include rules.
	 *
	 * @return ConditionalRule[] The filtered rules.
	 */
	private function filterByType(array $rules, bool $isInclude): array {
		$filtered = [];
		foreach ($rules as $rule) {
			if ($rule->getIsInclude() === $isInclude) {
				$filtered[] = $rule;
			}
		}

		return $filtered;
	}//end filterByType()

	/**
	 * The ids of the rules that match, in rule order. Backs both
	 * `checkRules()` (via `evaluateRuleSet()`) and the preview path, so the
	 * include and exclude groups are evaluated in exactly one place.
	 *
	 * @param ConditionalRule[] $rules   The rules of one include or exclude group.
	 * @param callable          $matches Whether one rule matches the user.
	 *
	 * @return int[] The ids of the matching rules.
	 */
	private function matchingRuleIds(array $rules, callable $matches): array {
		$matchedIds = [];
		foreach ($rules as $rule) {
			if ($matches($rule) === true) {
				$matchedIds[] = $rule->getId();
			}
		}

		return $matchedIds;
	}//end matchingRuleIds()
}//end class
