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
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserManager;

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
	use RespondsWithErrors;

	/**
	 * Constructor
	 *
	 * @param IRequest $request The request.
	 * @param ConditionalServiceInterface $conditionalService The conditional service
	 *                                               (shared evaluation
	 *                                               pipeline).
	 * @param string|null $userId The authenticated user
	 *                            ID.
	 * @param PermissionService|null $permissionService Who may edit a
	 *                                                  placement, to view
	 *                                                  as another person.
	 * @param IUserManager|null $userManager Whether the person
	 *                                       viewed as exists.
	 */
	public function __construct(
		IRequest $request,
		private readonly ConditionalServiceInterface $conditionalService,
		private readonly ?string $userId,
		private readonly ?PermissionService $permissionService = null,
		private readonly ?IUserManager $userManager = null,
	) {
		parent::__construct(appName: Application::APP_ID, request: $request);
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
	 * With `context.userId` the rules are evaluated as that person: their
	 * own groups and profile values, resolved server-side ("View as",
	 * REQ-CVUI-006). Viewing as someone other than yourself needs a
	 * `placementId` you may edit the rules of.
	 *
	 * @param array|null  $rules       The candidate rule set:
	 *                                 `[{id?, ruleType, ruleConfig, isInclude}, …]`.
	 * @param array|null  $context     `{groups?: string[], datetime?: string, userId?: string}`.
	 * @param string|null $match       `any` (default) or `all` (REQ-VIS-014).
	 * @param int|null    $placementId The placement whose rules are edited.
	 *
	 * @return JSONResponse `{visible, matchedIncludeRuleIds, matchedExcludeRuleIds}`
	 *                      on success, HTTP 400 when the input fails
	 *                      validation, HTTP 403 when viewing as another
	 *                      person is not allowed.
	 *
	 * @spec openspec/specs/conditional-visibility-editor/spec.md#requirement-req-cvui-005-preview-endpoint-reuses-the-render-time-evaluation-path-and-never-persists
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
	 *
	 * @no-admin-idor-exempt no object is addressed. Both parameters are the
	 * candidate rule set and an evaluation context taken from the request
	 * body; the method loads nothing by id, persists nothing, and evaluates
	 * against `$this->userId` — which it null-checks — so there is no other
	 * user's object for a caller to reach. An IDOR needs an attacker-supplied
	 * identifier, and this endpoint accepts none. Viewing as another person
	 * names a user and a placement; both are checked in
	 * `refuseViewingAs()` (placement rules editable by the caller, user
	 * exists) before anything is evaluated.
	 */
	#[NoAdminRequired]
	public function preview(
		array|null $rules = null,
		array|null $context = null,
		string|null $match = null,
		int|null $placementId = null,
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

		$subject = self::viewedAs(context: $context ?? []);
		$refusal = $this->refuseViewingAs(subject: $subject, placementId: $placementId);
		if ($refusal !== null) {
			return $refusal;
		}

		try {
			$entities = $this->buildRuleEntities(rules: $rules ?? []);
			$groups = $this->groupsFor(context: $context ?? [], subject: $subject);
			$now = $this->buildDatetimeOverride(context: $context ?? []);
			$mode = self::matchMode(match: $match);
		} catch (InvalidArgumentException $e) {
			return $this->failure(message: $e->getMessage(), statusCode: Http::STATUS_BAD_REQUEST);
		}

		try {
			$result = $this->conditionalService->previewRules(
				rules: $entities,
				userId: ($subject ?? $this->userId),
				groupsOverride: $groups,
				nowOverride: $now,
				match: $mode
			);

			return new JSONResponse($result, Http::STATUS_OK);
		} catch (Exception $e) {
			return $this->failure(message: 'Preview failed', statusCode: Http::STATUS_BAD_REQUEST);
		}
	}//end preview()

	/**
	 * The person the preview views as: `context.userId` when given, else
	 * null (the caller, with the groups the context names).
	 *
	 * @param array $context The raw `context` object.
	 *
	 * @return string|null The user id to evaluate as, null for none.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
	 */
	private static function viewedAs(array $context): ?string {
		$userId = $context['userId'] ?? null;
		if (is_string($userId) === false || trim($userId) === '') {
			return null;
		}

		return trim($userId);
	}//end viewedAs()

	/**
	 * Refuse viewing as another person unless the caller may edit the
	 * placement's rules and the person exists. Viewing as yourself, or
	 * as no one, is always allowed.
	 *
	 * @param string|null $subject     The person viewed as.
	 * @param int|null    $placementId The placement whose rules are edited.
	 *
	 * @return JSONResponse|null The refusal, or null to go on.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
	 */
	private function refuseViewingAs(?string $subject, ?int $placementId): ?JSONResponse {
		if ($subject === null || $subject === $this->userId) {
			return null;
		}

		if ($placementId === null || $this->mayEditRules(placementId: $placementId) === false) {
			return $this->failure(message: 'You may not view this widget as another person', statusCode: Http::STATUS_FORBIDDEN);
		}

		if ($this->userManager === null || $this->userManager->userExists($subject) === false) {
			return $this->failure(message: 'Unknown person to view as', statusCode: Http::STATUS_BAD_REQUEST);
		}

		return null;
	}//end refuseViewingAs()

	/**
	 * Whether the caller may edit the rules of a placement: its dashboard's
	 * owner or an administrator, the same check the rule endpoints make.
	 *
	 * @param int $placementId The placement.
	 *
	 * @return bool True when allowed.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
	 */
	private function mayEditRules(int $placementId): bool {
		if ($this->permissionService === null) {
			return false;
		}

		try {
			$this->permissionService->verifyPlacementOwnership(userId: (string)$this->userId, placementId: $placementId);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}//end mayEditRules()

	/**
	 * The groups to test `group` rules against: none to override when
	 * viewing as a person (their own groups count), else the context's.
	 *
	 * @param array       $context The raw `context` object.
	 * @param string|null $subject The person viewed as.
	 *
	 * @return string[]|null The groups, or null for the person's own.
	 *
	 * @throws InvalidArgumentException When `groups` is malformed.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
	 */
	private function groupsFor(array $context, ?string $subject): ?array {
		if ($subject !== null) {
			return null;
		}

		return $this->buildGroupsOverride(context: $context);
	}//end groupsFor()

	/**
	 * The match mode of the request: `any` when absent.
	 *
	 * @param string|null $match The raw `match` parameter.
	 *
	 * @return string `any` or `all`.
	 *
	 * @throws InvalidArgumentException On any other value.
	 *
	 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 */
	private static function matchMode(?string $match): string {
		if ($match === null) {
			return WidgetPlacement::RULE_MATCH_ANY;
		}

		if (in_array($match, WidgetPlacement::RULE_MATCHES, true) === false) {
			throw new InvalidArgumentException('match must be any or all');
		}

		return $match;
	}//end matchMode()

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

			$entities[] = self::ruleEntity(entry: $entry, index: $index);
		}

		return $entities;
	}//end buildRuleEntities()

	/**
	 * An unsaved rule from one preview entry: its correlation id, type,
	 * config and include flag (default include).
	 *
	 * @param array      $entry The preview entry.
	 * @param int|string $index The entry's position, the id when it has none.
	 *
	 * @return ConditionalRule The rule.
	 *
	 * @throws InvalidArgumentException On an unknown type or a config that is not an object.
	 */
	private static function ruleEntity(array $entry, int|string $index): ConditionalRule {
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
		return $rule;
	}//end ruleEntity()

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
