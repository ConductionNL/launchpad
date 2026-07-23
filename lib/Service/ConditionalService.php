<?php

/**
 * ConditionalService
 *
 * Service for managing conditional rules on widget placements.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use DateTimeInterface;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Db\ConditionalRuleMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Service for managing conditional rules on widget placements.
 *
 * @spec openspec/specs/conditional-visibility/spec.md
 */
class ConditionalService
{
    /**
     * Constructor
     *
     * @param ConditionalRuleMapper $ruleMapper        The conditional rule mapper.
     * @param RuleEvaluatorService  $ruleEvaluator     The rule evaluator service.
     * @param VisibilityChecker     $visibilityChecker The visibility checker.
     * @param WidgetPlacementMapper $placementMapper   The widget placement mapper.
     * @param DashboardMapper       $dashboardMapper   The dashboard mapper.
     */
    public function __construct(
        private readonly ConditionalRuleMapper $ruleMapper,
        private readonly RuleEvaluatorService $ruleEvaluator,
        private readonly VisibilityChecker $visibilityChecker,
        private readonly WidgetPlacementMapper $placementMapper,
        private readonly DashboardMapper $dashboardMapper,
    ) {
    }//end __construct()

    /**
     * Evaluate a single rule.
     *
     * @param ConditionalRule $rule   The rule to evaluate.
     * @param string          $userId The user ID.
     *
     * @return bool Whether the rule matches.
     *
     * @spec openspec/specs/conditional-visibility/spec.md
     */
    public function evaluateRule(
        ConditionalRule $rule,
        string $userId
    ): bool {
        return $this->ruleEvaluator->evaluateRule(
            rule: $rule,
            userId: $userId
        );
    }//end evaluateRule()

    /**
     * Check whether all rules for a placement allow visibility for a user.
     *
     * Fetches the rules for the placement and delegates to
     * {@see VisibilityChecker::checkRules()} for include/exclude
     * aggregation logic (REQ-VIS-003). Returns `true` when no rules
     * are configured (no restriction = visible).
     *
     * @param int    $placementId The widget placement ID.
     * @param string $userId      The acting user's UID.
     *
     * @return bool `true` when the placement is visible for the user.
     *
     * @spec openspec/specs/conditional-visibility/spec.md
     */
    public function checkRulesForPlacement(int $placementId, string $userId): bool
    {
        $rules = $this->ruleMapper->findByPlacementId(
            placementId: $placementId
        );

        if (empty($rules) === true) {
            return true;
        }

        return $this->visibilityChecker->checkRules(
            rules: $rules,
            userId: $userId
        );
    }//end checkRulesForPlacement()

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
     * @param ConditionalRule[]      $rules          The candidate rule set
     *                                                (not persisted).
     * @param string                 $userId         The previewing user's
     *                                                UID (used only for
     *                                                `attribute` rules,
     *                                                which have no override
     *                                                in the preview
     *                                                context).
     * @param string[]               $groupsOverride The audience groups to
     *                                                test `group` rules
     *                                                against.
     * @param DateTimeInterface|null $nowOverride    The moment to test
     *                                                `time` / `date` rules
     *                                                against.
     *
     * @return array{visible: bool, matchedIncludeRuleIds: int[], matchedExcludeRuleIds: int[]}
     *
     * @spec openspec/changes/conditional-visibility-editor/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-005-preview-endpoint-reuses-the-render-time-evaluation-path-and-never-persists
     */
    public function previewRules(
        array $rules,
        string $userId,
        array $groupsOverride,
        ?DateTimeInterface $nowOverride
    ): array {
        return $this->visibilityChecker->evaluateRuleSet(
            rules: $rules,
            userId: $userId,
            groupsOverride: $groupsOverride,
            nowOverride: $nowOverride
        );
    }//end previewRules()

    /**
     * Get rules for a placement.
     *
     * @param int $placementId The placement ID.
     *
     * @return ConditionalRule[] The list of rules.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-9
     */
    public function getRules(int $placementId): array
    {
        return $this->ruleMapper->findByPlacementId(
            placementId: $placementId
        );
    }//end getRules()

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
    public function listAllRules(): array
    {
        $rules = $this->ruleMapper->findAll();

        // Aggregate per placement id.
        $byPlacement = [];
        foreach ($rules as $rule) {
            $placementId = $rule->getWidgetPlacementId();
            if (isset($byPlacement[$placementId]) === false) {
                $byPlacement[$placementId] = [
                    'placementId'  => $placementId,
                    'ruleCount'    => 0,
                    'includeCount' => 0,
                    'excludeCount' => 0,
                ];
            }

            $byPlacement[$placementId]['ruleCount']++;
            $counterKey = 'excludeCount';
            if ($rule->getIsInclude() === true) {
                $counterKey = 'includeCount';
            }

            $byPlacement[$placementId][$counterKey]++;
        }

        $rows = [];
        foreach ($byPlacement as $placementId => $agg) {
            try {
                $placement = $this->placementMapper->find(id: $placementId);
            } catch (DoesNotExistException) {
                // Orphaned rule — placement gone. Skip so the overview only
                // lists live placements.
                continue;
            }

            $dashboardId   = $placement->getDashboardId();
            $dashboardName = null;
            try {
                $dashboard     = $this->dashboardMapper->find(id: $dashboardId);
                $dashboardName = $dashboard->getName();
            } catch (DoesNotExistException) {
                // Dashboard gone but placement somehow remains — still list
                // the placement, just without a resolved name.
                $dashboardId = null;
            }

            $rows[] = [
                'placementId'   => $placementId,
                'dashboardId'   => $dashboardId,
                'dashboardName' => $dashboardName,
                'widgetType'    => $placement->getWidgetId(),
                'ruleCount'     => $agg['ruleCount'],
                'includeCount'  => $agg['includeCount'],
                'excludeCount'  => $agg['excludeCount'],
            ];
        }//end foreach

        return $rows;
    }//end listAllRules()

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
     *                                                     not exist.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-10
     */
    public function findRule(int $ruleId): ConditionalRule
    {
        return $this->ruleMapper->find(id: $ruleId);
    }//end findRule()

    /**
     * Add a rule to a placement.
     *
     * @param int    $placementId The placement ID.
     * @param string $ruleType    The rule type.
     * @param array  $ruleConfig  The rule configuration.
     * @param bool   $isInclude   Whether this is an include rule.
     *
     * @return ConditionalRule The created rule.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-8
     */
    public function addRule(
        int $placementId,
        string $ruleType,
        array $ruleConfig,
        bool $isInclude=true
    ): ConditionalRule {
        $rule = new ConditionalRule();
        $rule->setWidgetPlacementId(
            $placementId
        );
        $rule->setRuleType($ruleType);
        $rule->setRuleConfigArray($ruleConfig);
        $rule->setIsInclude($isInclude);
        $rule->setCreatedAt((new DateTime())->format(format: 'c'));

        return $this->ruleMapper->insert(entity: $rule);
    }//end addRule()

    /**
     * Update a rule.
     *
     * @param int   $ruleId The rule ID.
     * @param array $data   The data to update.
     *
     * @return ConditionalRule The updated rule.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-10
     */
    public function updateRule(int $ruleId, array $data): ConditionalRule
    {
        $rule = $this->ruleMapper->find(id: $ruleId);

        if (isset($data['ruleType']) === true) {
            $rule->setRuleType($data['ruleType']);
        }

        if (isset($data['ruleConfig']) === true) {
            $rule->setRuleConfigArray($data['ruleConfig']);
        }

        if (isset($data['isInclude']) === true) {
            $rule->setIsInclude($data['isInclude']);
        }

        return $this->ruleMapper->update(entity: $rule);
    }//end updateRule()

    /**
     * Delete a rule.
     *
     * @param int $ruleId The rule ID.
     *
     * @return void
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-11
     */
    public function deleteRule(int $ruleId): void
    {
        $rule = $this->ruleMapper->find(id: $ruleId);
        $this->ruleMapper->delete(entity: $rule);
    }//end deleteRule()
}//end class
