<?php

/**
 * CalculatedFieldCycleError
 *
 * Calculated fields that depend on each other in a cycle (REQ-CALC-006). The
 * detail is the same `cycleDetected` envelope the client-side cycle detector
 * reports, so a save refused on either side reads alike.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\CalculatedFields
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\CalculatedFields;

use RuntimeException;

/**
 * A dependency cycle among calculated fields.
 *
 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
 */
class CalculatedFieldCycleError extends RuntimeException {

	/**
	 * The widgets in the cycle, each named once, in cycle order.
	 *
	 * @var array<int,string>
	 */
	private readonly array $widgets;

	/**
	 * Constructor.
	 *
	 * @param array<int,string> $cycle   The cycle, first node repeated at the end.
	 * @param array<int,string> $widgets The widgets of the cycle's nodes.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
	 */
	public function __construct(
		private readonly array $cycle,
		array $widgets,
	) {
		$this->widgets = array_values(array_unique($widgets));
		parent::__construct(message: 'Calculated fields depend on each other in a cycle: '.implode(' -> ', $cycle));
	}//end __construct()

	/**
	 * The `cycleDetected` envelope.
	 *
	 * @return array{error: string, message: string, cycle: array<int,string>, widgets: array<int,string>} The detail.
	 *
	 * @spec openspec/changes/widget-calculated-fields/specs/calculated-fields/spec.md#requirement-req-calc-006-cross-widget-references-with-cycle-detection
	 */
	public function getDetail(): array {
		return [
			'error'   => 'cycleDetected',
			'message' => $this->getMessage(),
			'cycle'   => $this->cycle,
			'widgets' => $this->widgets,
		];
	}//end getDetail()
}//end class
