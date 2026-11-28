<?php

/**
 * RuleSetContext
 *
 * The context a rule set is evaluated in: the groups to test `group` rules
 * against (null: the user's own), the moment to test `time` / `date` rules
 * against (null: now), and how the include rules combine (`any` or `all`,
 * REQ-VIS-014). Render time uses the defaults; the preview fills it in.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeInterface;
use OCA\LaunchPad\Db\WidgetPlacement;

/**
 * Groups, moment and match mode for one evaluation of a rule set.
 *
 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
 */
final class RuleSetContext {
	/**
	 * The groups to test `group` rules against; null for the user's own.
	 *
	 * @var string[]|null
	 */
	public readonly ?array $groups;

	/**
	 * The moment to test `time` / `date` rules against; null for now.
	 *
	 * @var DateTimeInterface|null
	 */
	public readonly ?DateTimeInterface $now;

	/**
	 * `any` (one include rule is enough) or `all`.
	 *
	 * @var string
	 */
	public readonly string $match;

	/**
	 * Constructor
	 *
	 * @param array<string, mixed> $options `groups` (string[]|null), `now`
	 *                                      (DateTimeInterface|null) and `match`
	 *                                      (string); each optional.
	 */
	public function __construct(array $options = []) {
		$this->groups = ($options['groups'] ?? null);
		$this->now = ($options['now'] ?? null);
		$this->match = ($options['match'] ?? WidgetPlacement::RULE_MATCH_ANY);
	}//end __construct()
}//end class
