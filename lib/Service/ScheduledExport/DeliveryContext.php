<?php

/**
 * DeliveryContext
 *
 * What one delivery attempt sends: the export, the artefacts rendered for
 * the recipient's filter context, and the moment of the attempt.
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

use DateTimeInterface;

/**
 * The export, its artefacts and the moment of a delivery.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-5
 */
final class DeliveryContext {

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $export    The export.
	 * @param list<RenderResult>   $artefacts The artefacts to send.
	 * @param DateTimeInterface    $now       The moment of the attempt.
	 */
	public function __construct(
		public readonly array $export,
		public readonly array $artefacts,
		public readonly DateTimeInterface $now,
	) {
	}//end __construct()
}//end class
