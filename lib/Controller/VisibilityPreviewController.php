<?php

/**
 * VisibilityPreviewController
 *
 * Read-only, non-persisting "preview as audience / date" endpoint for the
 * conditional-visibility rule editor.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
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

namespace OCA\LaunchPad\Controller;

use DateTime;
use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Service\ConditionalService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * `POST /api/visibility/preview` — evaluates a caller-supplied conditional
 * rule set against a caller-supplied `(groups, datetime)` context and
 * returns the effective visibility, WITHOUT persisting anything.
 *
 * This is a UI concern of the `conditional-visibility-editor` change, not a
 * new evaluation engine: it exists solely so the "preview as audience/date"
 * affordance can never diverge from what the dashboard will actually render
 * for that context. It delegates every evaluation decision to
 * {@see ConditionalService::previewRules()}, which in turn calls the exact
 * same {@see \OCA\LaunchPad\Service\VisibilityChecker} /
 * {@see \OCA\LaunchPad\Service\RuleEvaluatorService} pipeline used at
 * render time — no forked or re-implemented include/exclude logic.
 *
 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-005-preview-endpoint-reuses-the-render-time-evaluation-path-and-never-persists
 */
class VisibilityPreviewController extends Controller {
	/**
	 * Constructor
	 *
	 * @param IRequest $request The request.
	 * @param ConditionalService $conditionalService The conditional service
	 *                                               (shared evaluation
	 *                                               pipeline).
	 * @param string|null $userId The authenticated user
	 *                            ID.
	 */
	public function __construct(
		IRequest $request,
		private readonly ConditionalService $conditionalService,
		private readonly ?string $userId,
	) {
		parent::__construct(
			appName: Application::APP_ID,
			request: $request
		);
	}//end __construct()

	/**
	 * Preview the effective visibility of a supplied rule set for a
	 * supplied audience/date context.
	 *
	 * Never writes to `oc_launchpad_conditional_rules` — the rules array in
	 * the request body is only ever materialised as transient, in-memory
	 * `ConditionalRule` entities passed straight into the evaluation
	 * pipeline and discarded at the end of the request.
	 *
	 * @param array|null $rules The candidate rule set:
	 *                          `[{id?, ruleType, ruleConfig, isInclude}, …]`.
	 * @param array|null $context `{groups?: string[], datetime?: string}`.
	 *
	 * @return JSONResponse `{visible, matchedIncludeRuleIds, matchedExcludeRuleIds}`
	 *                      on success, or HTTP 400 when `rules`/`context`
	 *                      fail validation.
	 *
	 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-005-preview-endpoint-reuses-the-render-time-evaluation-path-and-never-persists
	 *
	 * @no-admin-idor-exempt no object is addressed. Both parameters are the
	 * candidate rule set and an evaluation context taken from the request
	 * body; the method loads nothing by id, persists nothing, and evaluates
	 * against `$this->userId` — which it null-checks — so there is no other
	 * user's object for a caller to reach. An IDOR needs an attacker-supplied
	 * identifier, and this endpoint accepts none.
	 */
	#[NoAdminRequired]
	public function preview(
		?array $rules = null,
		?array $context = null,
	): JSONResponse {
		// Nextcloud rejects unauthenticated requests to a #[NoAdminRequired]
		// route before dispatch, but the DI-injected userId is still
		// guarded defensively — mirrors RuleApiController's sibling
		// endpoints.
		if ($this->userId === null) {
			return new JSONResponse(
				['error' => 'Not authenticated'],
				Http::STATUS_UNAUTHORIZED
			);
		}

		try {
			$entities = $this->buildRuleEntities(rules: $rules ?? []);
			$groups = $this->buildGroupsOverride(context: $context ?? []);
			$now = $this->buildDatetimeOverride(context: $context ?? []);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(
				['error' => $e->getMessage()],
				Http::STATUS_BAD_REQUEST
			);
		}

		try {
			$result = $this->conditionalService->previewRules(
				rules: $entities,
				userId: $this->userId,
				groupsOverride: $groups,
				nowOverride: $now
			);

			return new JSONResponse($result, Http::STATUS_OK);
		} catch (Exception $e) {
			return new JSONResponse(
				['error' => 'Preview failed'],
				Http::STATUS_BAD_REQUEST
			);
		}
	}//end preview()

	/**
	 * Validate and build the transient (never persisted) rule entities from
	 * the request body.
	 *
	 * Rejects any `ruleType` outside {@see ConditionalRule::ALLOWED_TYPES}
	 * and any non-array `ruleConfig` with HTTP 400 (via the thrown
	 * exception, mapped by the caller).
	 *
	 * @param array $rules The raw `rules` array from the request body.
	 *
	 * @return ConditionalRule[] The transient entities, id-tagged so the
	 *                           response's `matchedIncludeRuleIds` /
	 *                           `matchedExcludeRuleIds` can be correlated
	 *                           back to the caller's rows (falls back to
	 *                           the array index when a row has no `id`
	 *                           yet, e.g. an unsaved draft row).
	 *
	 * @throws InvalidArgumentException When a rule entry is malformed.
	 */
	private function buildRuleEntities(array $rules): array {
		$entities = [];
		foreach ($rules as $index => $entry) {
			if (is_array($entry) === false) {
				throw new InvalidArgumentException(
					'Each entry in rules must be an object'
				);
			}

			$ruleType = $entry['ruleType'] ?? null;
			if (is_string($ruleType) === false
				|| in_array(
					$ruleType,
					ConditionalRule::ALLOWED_TYPES,
					true
				) === false
			) {
				throw new InvalidArgumentException(
					'Invalid ruleType: only group, time, date and attribute are accepted'
				);
			}

			$ruleConfig = $entry['ruleConfig'] ?? null;
			if (is_array($ruleConfig) === false) {
				throw new InvalidArgumentException(
					'ruleConfig must be an object'
				);
			}

			$rule = new ConditionalRule();
			// Preview rules are never persisted, so `id` here is purely a
			// correlation token echoed back in matchedIncludeRuleIds /
			// matchedExcludeRuleIds — it is NOT a database primary key.
			$ruleId = $entry['id'] ?? $index;
			$rule->setId((int)$ruleId);
			$rule->setRuleType($ruleType);
			$rule->setRuleConfigArray($ruleConfig);
			$rule->setIsInclude((bool)($entry['isInclude'] ?? true));

			$entities[] = $rule;
		}//end foreach

		return $entities;
	}//end buildRuleEntities()

	/**
	 * Build the group-membership override from the request's `context`.
	 *
	 * @param array $context The raw `context` object from the request body.
	 *
	 * @return string[] The groups to test `group` rules against (empty
	 *                  array when the caller omitted `groups`, matching "no
	 *                  group memberships").
	 *
	 * @throws InvalidArgumentException When `groups` is present but not an
	 *                                  array of strings.
	 */
	private function buildGroupsOverride(array $context): array {
		$groups = $context['groups'] ?? [];
		if (is_array($groups) === false) {
			throw new InvalidArgumentException(
				'context.groups must be an array of group ids'
			);
		}

		foreach ($groups as $group) {
			if (is_string($group) === false) {
				throw new InvalidArgumentException(
					'context.groups must be an array of group ids'
				);
			}
		}

		return array_values($groups);
	}//end buildGroupsOverride()

	/**
	 * Build the clock override from the request's `context`.
	 *
	 * Mirrors the engine's server-timezone behaviour (`conditional-visibility`
	 * spec, REQ-VIS-006): the supplied `datetime` is parsed as a plain PHP
	 * `DateTime`, i.e. interpreted in the server's default timezone, exactly
	 * like the render-time `new DateTime()` it stands in for.
	 *
	 * @param array $context The raw `context` object from the request body.
	 *
	 * @return DateTime The moment to test `time` / `date` rules against —
	 *                  defaults to "now" when `datetime` is omitted.
	 *
	 * @throws InvalidArgumentException When `datetime` is present but not a
	 *                                  parseable date/time string.
	 */
	private function buildDatetimeOverride(array $context): DateTime {
		$datetime = $context['datetime'] ?? null;
		if ($datetime === null) {
			return new DateTime();
		}

		if (is_string($datetime) === false) {
			throw new InvalidArgumentException(
				'context.datetime must be a date/time string'
			);
		}

		try {
			return new DateTime($datetime);
		} catch (Exception $e) {
			throw new InvalidArgumentException(
				'context.datetime is not a valid date/time'
			);
		}
	}//end buildDatetimeOverride()
}//end class
