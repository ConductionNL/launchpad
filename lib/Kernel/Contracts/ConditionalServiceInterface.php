<?php

/**
 * Contract of ConditionalService
 *
 * The public surface of ConditionalService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

use DateTimeInterface;
use OCA\LaunchPad\Db\ConditionalRule;

/**
 * Contract of ConditionalService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ConditionalServiceInterface {
	/**
	 * Apply the visibility rules to the placements a dashboard renders: a
	 * hidden placement is left out, or kept with `hiddenForViewers: true`
	 * for a viewer who may edit the dashboard.
	 *
	 * @param array<int, array<string, mixed>> $placements The serialised placements.
	 * @param string                           $userId     The viewing user, '' for a visitor without an account.
	 * @param bool                             $canEdit    Whether the viewer may edit the dashboard.
	 *
	 * @return array<int, array<string, mixed>> The placements to render, in order.
	 *
	 * @spec openspec/specs/conditional-visibility/spec.md
	 */
	public function visiblePlacements(array $placements, string $userId, bool $canEdit): array;

	/**
	 * Check whether all rules for a placement allow visibility for a user.
	 *
	 * Fetches the rules for the placement and delegates to
	 * {@see VisibilityChecker::checkRules()} for include/exclude
	 * aggregation logic (REQ-VIS-003). Returns `true` when no rules
	 * are configured (no restriction = visible).
	 *
	 * @param int $placementId The widget placement ID.
	 * @param string $userId The acting user's UID.
	 *
	 * @return bool `true` when the placement is visible for the user.
	 *
	 * @spec openspec/specs/conditional-visibility/spec.md
	 */
	public function checkRulesForPlacement(int $placementId, string $userId): bool;

	/**
	 * Evaluate an in-memory (not persisted) rule set against a supplied
	 * `(groups, datetime)` context.
	 *
	 * Backs `POST /api/visibility/preview`
	 * (conditional-visibility-editor spec, REQ-CVUI-005). Delegates to
	 * {@see VisibilityChecker::evaluateRuleSet()} — the SAME method
	 * `checkRulesForPlacement()` uses at render time (via `checkRules()`) —
	 * so a preview verdict can never diverge from the visibility the
	 * dashboard will actually produce for that context. Does not touch the
	 * database: `$rules` are transient `ConditionalRule` entities built by
	 * the controller from the request body, never loaded from or written to
	 * `ConditionalRuleMapper`.
	 *
	 * @param \OCA\LaunchPad\Db\ConditionalRule[] $rules The candidate rule set
	 *                                 (not persisted).
	 * @param string $userId The previewing user's
	 *                       UID (used only for
	 *                       `attribute` rules,
	 *                       which have no override
	 *                       in the preview
	 *                       context).
	 * @param string[]|null $groupsOverride The audience groups to
	 *                                      test `group` rules
	 *                                      against; null tests the
	 *                                      user's own groups.
	 * @param DateTimeInterface|null $nowOverride The moment to test
	 *                                            `time` / `date` rules
	 *                                            against.
	 * @param string $match `any` or `all` (REQ-VIS-014).
	 *
	 * @return array{visible: bool, matchedIncludeRuleIds: int[], matchedExcludeRuleIds: int[]}
	 *
	 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-005-preview-endpoint-reuses-the-render-time-evaluation-path-and-never-persists
	 */
	public function previewRules(
		array $rules,
		string $userId,
		?array $groupsOverride,
		?DateTimeInterface $nowOverride,
		string $match = 'any',
	): array;

	/**
	 * Get rules for a placement.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return \OCA\LaunchPad\Db\ConditionalRule[] The list of rules.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-9
	 */
	public function getRules(int $placementId): array;

	/**
	 * Build the admin Versioning & Audit overview of every placement that
	 * carries at least one conditional rule (conditional-visibility spec).
	 *
	 * Each row aggregates the placement's rule count plus include / exclude
	 * breakdown and resolves the owning dashboard's id, name, and widget
	 * type. Placements or dashboards that no longer exist (orphaned rules)
	 * are skipped defensively so a dangling row never breaks the overview.
	 *
	 * @return array<int, array{placementId:int, dashboardId:(int|null),
	 *               dashboardName:(string|null), widgetType:string,
	 *               ruleCount:int, includeCount:int, excludeCount:int}>
	 *               One entry per rule-bearing placement.
	 *
	 * @spec openspec/specs/conditional-visibility/spec.md
	 */
	public function listAllRules(): array;

	/**
	 * Fetch a single rule by its primary key.
	 *
	 * Used by the controller before calling updateRule/deleteRule so that
	 * ownership of the associated placement can be verified first
	 * (C4 fix: REQ-PERM-001).
	 *
	 * @param int $ruleId The rule primary key.
	 *
	 * @return ConditionalRule The found rule.
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the rule does
	 *                                                    not exist.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-10
	 */
	public function findRule(int $ruleId): ConditionalRule;

	/**
	 * Add a rule to a placement.
	 *
	 * @param int $placementId The placement ID.
	 * @param string $ruleType The rule type.
	 * @param array $ruleConfig The rule configuration.
	 * @param bool $isInclude Whether this is an include rule.
	 *
	 * @return ConditionalRule The created rule.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-8
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag) The contract mirrors the existing signature.
	 */
	public function addRule(
		int $placementId,
		string $ruleType,
		array $ruleConfig,
		bool $isInclude = true,
	): ConditionalRule;

	/**
	 * Update a rule.
	 *
	 * @param int $ruleId The rule ID.
	 * @param array $data The data to update.
	 *
	 * @return ConditionalRule The updated rule.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-10
	 */
	public function updateRule(int $ruleId, array $data): ConditionalRule;

	/**
	 * Delete a rule.
	 *
	 * @param int $ruleId The rule ID.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-11
	 */
	public function deleteRule(int $ruleId): void;
}//end interface
