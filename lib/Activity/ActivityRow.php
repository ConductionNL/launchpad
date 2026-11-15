<?php

/**
 * ActivityRow
 *
 * What one LaunchPad activity says, before it is addressed: the event
 * type, the acting user, the dashboard and the extra subject parameters.
 * The publisher writes it once per recipient.
 *
 * @category  Activity
 * @package   OCA\LaunchPad\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/activity-feed-integration/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Activity;

/**
 * One activity, ready to be written to any number of recipients.
 */
class ActivityRow {
	/**
	 * Constructor.
	 *
	 * @param string               $type        The event-type constant value.
	 * @param string               $actorUserId The acting NC user ID.
	 * @param ActivitySubject      $dashboard   The dashboard the row is about.
	 * @param array<string, mixed> $extraParams Extra subject params (e.g. `recipient`, `role`, `target`, `message`).
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $actorUserId,
		public readonly ActivitySubject $dashboard,
		public readonly array $extraParams=[],
	) {
	}//end __construct()

	/**
	 * The same row with the `self` flag set for one recipient.
	 *
	 * @param string $recipientUserId The recipient NC user ID.
	 *
	 * @return self The row addressed to that recipient.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function forRecipient(string $recipientUserId): self {
		$params         = $this->extraParams;
		$params['self'] = ($recipientUserId === $this->actorUserId);

		return new self(
			type: $this->type,
			actorUserId: $this->actorUserId,
			dashboard: $this->dashboard,
			extraParams: $params
		);
	}//end forRecipient()
}//end class
