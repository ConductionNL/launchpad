<?php

/**
 * RuleEvaluatorService
 *
 * Service for evaluating conditional rules against user context.
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

use DateTime;
use DateTimeInterface;
use OCA\LaunchPad\Db\ConditionalRule;

/**
 * Service for evaluating conditional rules against user context.
 */
class RuleEvaluatorService
{
    /**
     * Constructor
     *
     * @param AdminTemplateService  $adminTemplateService Routing resolver — single
     *                                                    source of truth for
     *                                                    `IGroupManager::getUserGroupIds`
     *                                                    (REQ-TMPL-013).
     * @param UserAttributeResolver $attrResolver         The attribute resolver.
     */
    public function __construct(
        private readonly AdminTemplateService $adminTemplateService,
        private readonly UserAttributeResolver $attrResolver,
    ) {
    }//end __construct()

    /**
     * Evaluate a single rule.
     *
     * Dispatcher for all rule types — group (REQ-VIS-005), time
     * (REQ-VIS-006), date (REQ-VIS-007) and attribute (REQ-VIS-008) rules
     * are all evaluated through private helpers below. Public surface is
     * tagged against the dispatch Requirement (REQ-VIS-010).
     *
     * `$groupsOverride` / `$nowOverride` are optional context injections
     * consumed ONLY by the read-only preview path
     * (conditional-visibility-editor spec, REQ-CVUI-005 —
     * `VisibilityPreviewController` via `ConditionalService::previewRules()`
     * / `VisibilityChecker::evaluateRuleSet()`). Render-time callers
     * (`ConditionalService::checkRulesForPlacement()`) never pass them, so
     * behaviour for the existing call sites is byte-for-byte unchanged:
     * group rules keep resolving the live user's group memberships and
     * time/date rules keep using the server clock.
     *
     * @param ConditionalRule        $rule           The rule to evaluate.
     * @param string                 $userId         The user ID.
     * @param string[]|null          $groupsOverride When non-null, used
     *                                                instead of the live
     *                                                user's group
     *                                                memberships for
     *                                                `group` rules.
     * @param DateTimeInterface|null $nowOverride    When non-null, used
     *                                                instead of the server
     *                                                clock for `time` /
     *                                                `date` rules.
     *
     * @return bool Whether the rule matches.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-14
     * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-005-preview-endpoint-reuses-the-render-time-evaluation-path-and-never-persists
     */
    public function evaluateRule(
        ConditionalRule $rule,
        string $userId,
        ?array $groupsOverride=null,
        ?DateTimeInterface $nowOverride=null
    ): bool {
        return match ($rule->getRuleType()) {
            ConditionalRule::TYPE_GROUP => $this->evaluateGroupRule(
                rule: $rule,
                userId: $userId,
                groupsOverride: $groupsOverride
            ),
            ConditionalRule::TYPE_TIME => $this->evaluateTimeRule(
                rule: $rule,
                nowOverride: $nowOverride
            ),
            ConditionalRule::TYPE_DATE => $this->evaluateDateRule(
                rule: $rule,
                nowOverride: $nowOverride
            ),
            ConditionalRule::TYPE_ATTRIBUTE => $this->evaluateAttributeRule(
                rule: $rule,
                userId: $userId
            ),
            default => false,
        };
    }//end evaluateRule()

    /**
     * Evaluate a group-based rule.
     * Config: { "groups": ["admin", "editors"] }.
     *
     * @param ConditionalRule $rule           The rule to evaluate.
     * @param string          $userId         The user ID.
     * @param string[]|null   $groupsOverride When non-null, the group set to
     *                                        test instead of the live user's
     *                                        memberships (preview only).
     *
     * @return bool Whether the rule matches.
     */
    private function evaluateGroupRule(
        ConditionalRule $rule,
        string $userId,
        ?array $groupsOverride=null
    ): bool {
        $config       = $rule->getRuleConfigArray();
        $targetGroups = $config['groups'] ?? [];

        if (empty($targetGroups) === true) {
            return false;
        }

        // Group memberships are read through the routing resolver so the
        // single-source-of-truth invariant (REQ-TMPL-013) holds, UNLESS a
        // preview context supplied an explicit group set to test.
        $userGroups = $groupsOverride;
        if ($userGroups === null) {
            $userGroups = $this->adminTemplateService->getUserGroupIdsFor(
                userId: $userId
            );
        }

        if ($userGroups === []) {
            return false;
        }

        return empty(array_intersect($userGroups, $targetGroups)) === false;
    }//end evaluateGroupRule()

    /**
     * Evaluate a time-based rule.
     * Config: { "startTime": "09:00", "endTime": "17:00", "days": ["mon"] }.
     *
     * @param ConditionalRule        $rule        The rule to evaluate.
     * @param DateTimeInterface|null $nowOverride When non-null, the moment to
     *                                            test instead of the server
     *                                            clock (preview only).
     *
     * @return bool Whether the rule matches.
     */
    private function evaluateTimeRule(
        ConditionalRule $rule,
        ?DateTimeInterface $nowOverride=null
    ): bool {
        $config = $rule->getRuleConfigArray();

        $now         = $nowOverride ?? new DateTime();
        $currentTime = $now->format(format: 'H:i');
        $currentDay  = strtolower(string: $now->format(format: 'D'));

        // Check day of week.
        if (isset($config['days']) === true
            && is_array($config['days']) === true
        ) {
            if (in_array(
                needle: $currentDay,
                haystack: $config['days']
            ) === false
            ) {
                return false;
            }
        }

        // Check time range.
        $startTime = $config['startTime'] ?? '00:00';
        $endTime   = $config['endTime'] ?? '23:59';

        return $currentTime >= $startTime && $currentTime <= $endTime;
    }//end evaluateTimeRule()

    /**
     * Evaluate a date-based rule.
     * Config: { "startDate": "2024-01-01", "endDate": "2024-12-31" }.
     *
     * @param ConditionalRule        $rule        The rule to evaluate.
     * @param DateTimeInterface|null $nowOverride When non-null, the moment to
     *                                            test instead of the server
     *                                            clock (preview only).
     *
     * @return bool Whether the rule matches.
     */
    private function evaluateDateRule(
        ConditionalRule $rule,
        ?DateTimeInterface $nowOverride=null
    ): bool {
        $config = $rule->getRuleConfigArray();

        $now         = $nowOverride ?? new DateTime();
        $currentDate = $now->format(format: 'Y-m-d');

        $startDate = $config['startDate'] ?? null;
        $endDate   = $config['endDate'] ?? null;

        if ($startDate !== null && $currentDate < $startDate) {
            return false;
        }

        if ($endDate !== null && $currentDate > $endDate) {
            return false;
        }

        return true;
    }//end evaluateDateRule()

    /**
     * Evaluate an attribute-based rule.
     * Config: { "attribute": "locale", "operator": "equals", "value": "nl" }.
     *
     * @param ConditionalRule $rule   The rule to evaluate.
     * @param string          $userId The user ID.
     *
     * @return bool Whether the rule matches.
     */
    private function evaluateAttributeRule(
        ConditionalRule $rule,
        string $userId
    ): bool {
        $config = $rule->getRuleConfigArray();

        $attribute = $config['attribute'] ?? null;
        $operator  = $config['operator'] ?? 'equals';
        $value     = $config['value'] ?? null;

        if ($attribute === null) {
            return false;
        }

        $userValue = $this->attrResolver->getUserAttributeValue(
            userId: $userId,
            attribute: $attribute
        );

        if ($userValue === null) {
            return false;
        }

        return $this->attrResolver->evaluateOperator(
            userValue: $userValue,
            operator: $operator,
            value: $value
        );
    }//end evaluateAttributeRule()
}//end class
