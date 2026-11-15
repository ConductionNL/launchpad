<?php

/**
 * FireTrigger
 *
 * Who or what fired a scheduled export: the schedule, or a user's "Run now"
 * (REQ-SCH-009 records `triggeredBy=manual` and `triggeredByUser`).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

/**
 * The trigger of one fire.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-009-manual-run-now-and-preview-actions
 */
final class FireTrigger {

	/**
	 * Constructor.
	 *
	 * @param string      $kind   `schedule` or `manual`.
	 * @param string|null $userId The user who pressed "Run now", for a manual fire.
	 */
	private function __construct(
		public readonly string $kind,
		public readonly ?string $userId,
	) {
	}//end __construct()

	/**
	 * A fire by the background runner.
	 *
	 * @return self
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	public static function schedule(): self {
		return new self(kind: 'schedule', userId: null);
	}//end schedule()

	/**
	 * A "Run now" by a user.
	 *
	 * @param string $userId The user.
	 *
	 * @return self
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-009-manual-run-now-and-preview-actions
	 */
	public static function manual(string $userId): self {
		return new self(kind: 'manual', userId: $userId);
	}//end manual()

	/**
	 * Whether this is a scheduled fire.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-009-manual-run-now-and-preview-actions
	 */
	public function isScheduled(): bool {
		return $this->kind === 'schedule';
	}//end isScheduled()
}//end class
