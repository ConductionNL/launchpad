<?php
/**
 * DraftRules
 *
 * The visibility rules an edit-mode draft carries (REQ-EDIT-001): the rules
 * dialog changes the draft only, and Save replaces each changed widget's
 * rules in the same transaction as the rest of the draft.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\ConditionalRule;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;

/**
 * Checks and stores the rules of a draft: only the dashboard's owner or an
 * administrator may change who sees a widget (the rule routes' check), each
 * rule has an allowed type and a configuration, and a Save replaces the
 * widget's rules with the draft's.
 *
 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */
class DraftRules {
	/**
	 * Constructor.
	 *
	 * @param ConditionalServiceInterface $rules       Reads, adds and deletes rules.
	 * @param PermissionService           $permissions Who owns a widget's dashboard.
	 */
	public function __construct(
		private readonly ConditionalServiceInterface $rules,
		private readonly PermissionService $permissions,
	) {
	}//end __construct()

	/**
	 * The rules a draft placement carries, normalised, or null when it carries none.
	 *
	 * @param array<string, mixed> $item The draft placement.
	 *
	 * @return list<array{ruleType: string, ruleConfig: array<string, mixed>, isInclude: bool}>|null
	 *
	 * @throws InvalidArgumentException When a rule is malformed.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
	 */
	public function fromItem(array $item): ?array {
		if (array_key_exists('rules', $item) === false) {
			return null;
		}

		if (is_array($item['rules']) === false) {
			throw new InvalidArgumentException(message: 'rules must be a list');
		}

		return array_values(array_map(fn (mixed $rule): array => $this->normal(rule: $rule), $item['rules']));
	}//end fromItem()

	/**
	 * Refuse a rule change by someone who does not own the widget's dashboard.
	 *
	 * @param string $userId      The person saving.
	 * @param int    $placementId The widget.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException When they may not change who sees it.
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function assertMayReplace(string $userId, int $placementId): void {
		try {
			$this->permissions->verifyPlacementOwnership(userId: $userId, placementId: $placementId);
		} catch (\Exception) {
			throw new ForbiddenException(message: 'Only the dashboard owner or an admin may change who sees this widget');
		}
	}//end assertMayReplace()

	/**
	 * Replace a widget's rules with the draft's.
	 *
	 * @param int                                                                            $placementId The widget.
	 * @param list<array{ruleType: string, ruleConfig: array<string, mixed>, isInclude: bool}> $rules       The draft's rules.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	 */
	public function replace(int $placementId, array $rules): void {
		foreach ($this->rules->getRules(placementId: $placementId) as $stored) {
			$this->rules->deleteRule(ruleId: (int)$stored->getId());
		}

		foreach ($rules as $rule) {
			$this->rules->addRule(
				placementId: $placementId,
				ruleType: $rule['ruleType'],
				ruleConfig: $rule['ruleConfig'],
				isInclude: $rule['isInclude']
			);
		}
	}//end replace()

	/**
	 * One rule as stored: an allowed type, a configuration and include or exclude.
	 *
	 * @param mixed $rule The draft's rule.
	 *
	 * @return array{ruleType: string, ruleConfig: array<string, mixed>, isInclude: bool}
	 *
	 * @throws InvalidArgumentException When it is malformed.
	 */
	private function normal(mixed $rule): array {
		$type = ((array)$rule)['ruleType'] ?? null;
		if (in_array($type, ConditionalRule::ALLOWED_TYPES, true) === false) {
			throw new InvalidArgumentException(message: 'A rule needs a ruleType of '.implode(', ', ConditionalRule::ALLOWED_TYPES));
		}

		$config = ((array)$rule)['ruleConfig'] ?? null;
		if (is_array($config) === false) {
			throw new InvalidArgumentException(message: 'A rule needs a ruleConfig');
		}

		return ['ruleType' => (string)$type, 'ruleConfig' => $config, 'isInclude' => (((array)$rule)['isInclude'] ?? true) !== false];
	}//end normal()
}//end class
