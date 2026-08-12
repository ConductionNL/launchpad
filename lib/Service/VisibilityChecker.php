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

use DateTimeInterface;
use OCA\LaunchPad\Db\ConditionalRule;

/**
 * Service for checking widget visibility based on conditional rules.
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
	 * Include rules use OR logic (at least one must match).
	 * Exclude rules use AND logic (any match hides the widget).
	 *
	 * Thin boolean-only wrapper around {@see self::evaluateRuleSet()} — the
	 * ONLY place the OR/exclude-AND combination logic lives. Render-time
	 * callers (`ConditionalService::checkRulesForPlacement()`) keep calling
	 * this method unchanged.
	 *
	 * @param ConditionalRule[] $rules The rules to check.
	 * @param string $userId The user ID.
	 *
	 * @return bool Whether the widget should be visible.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-13
	 */
	public function checkRules(array $rules, string $userId): bool {
		return $this->evaluateRuleSet(
			rules: $rules,
			userId: $userId
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
	 * from render-time visibility. `$groupsOverride` / `$nowOverride` are
	 * forwarded to {@see RuleEvaluatorService::evaluateRule()} and are only
	 * ever non-null when called from the preview controller.
	 *
	 * @param ConditionalRule[] $rules The rules to check.
	 * @param string $userId The user ID.
	 * @param string[]|null $groupsOverride Preview-only group
	 *                                      override.
	 * @param DateTimeInterface|null $nowOverride Preview-only clock
	 *                                            override.
	 *
	 * @return array{visible: bool, matchedIncludeRuleIds: int[], matchedExcludeRuleIds: int[]}
	 *
	 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-005-preview-endpoint-reuses-the-render-time-evaluation-path-and-never-persists
	 */
	public function evaluateRuleSet(
		array $rules,
		string $userId,
		?array $groupsOverride = null,
		?DateTimeInterface $nowOverride = null,
	): array {
		$includeRules = $this->filterByType(
			rules: $rules,
			isInclude: true
		);
		$excludeRules = $this->filterByType(
			rules: $rules,
			isInclude: false
		);

		$include = $this->evaluateGroup(
			rules: $includeRules,
			userId: $userId,
			groupsOverride: $groupsOverride,
			nowOverride: $nowOverride,
			isIncludeGroup: true
		);

		if ($include['passed'] === false) {
			return [
				'visible' => false,
				'matchedIncludeRuleIds' => $include['matchedIds'],
				'matchedExcludeRuleIds' => [],
			];
		}

		$exclude = $this->evaluateGroup(
			rules: $excludeRules,
			userId: $userId,
			groupsOverride: $groupsOverride,
			nowOverride: $nowOverride,
			isIncludeGroup: false
		);

		return [
			'visible' => $exclude['passed'],
			'matchedIncludeRuleIds' => $include['matchedIds'],
			'matchedExcludeRuleIds' => $exclude['matchedIds'],
		];
	}//end evaluateRuleSet()

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
	 * Evaluate one include/exclude group of rules, collecting the ids of
	 * every rule that matched.
	 *
	 * Include group: passes when empty OR at least one rule matched (OR).
	 * Exclude group: passes when NO rule matched (AND — any match fails
	 * it). This single helper backs both `checkRules()` (via
	 * `evaluateRuleSet()`, which discards `matchedIds`) and the preview
	 * path, so the OR/AND semantics exist in exactly one place.
	 *
	 * @param ConditionalRule[] $rules The rules in this group.
	 * @param string $userId The user ID.
	 * @param string[]|null $groupsOverride Preview-only group
	 *                                      override.
	 * @param DateTimeInterface|null $nowOverride Preview-only clock
	 *                                            override.
	 * @param bool $isIncludeGroup True for the include
	 *                             group, false for
	 *                             exclude.
	 *
	 * @return array{passed: bool, matchedIds: int[]}
	 */
	private function evaluateGroup(
		array $rules,
		string $userId,
		?array $groupsOverride,
		?DateTimeInterface $nowOverride,
		bool $isIncludeGroup,
	): array {
		$matchedIds = [];
		foreach ($rules as $rule) {
			if ($this->ruleEvaluator->evaluateRule(
				rule: $rule,
				userId: $userId,
				groupsOverride: $groupsOverride,
				nowOverride: $nowOverride
			) === true
			) {
				$matchedIds[] = $rule->getId();
			}
		}

		$passed = empty($matchedIds) === true;
		if ($isIncludeGroup === true) {
			$passed = empty($rules) === true || empty($matchedIds) === false;
		}

		return [
			'passed' => $passed,
			'matchedIds' => $matchedIds,
		];
	}//end evaluateGroup()
}//end class
