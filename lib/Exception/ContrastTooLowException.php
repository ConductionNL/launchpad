<?php

/**
 * ContrastTooLowException
 *
 * Raised when a dashboard's accent colour would be hard to see against its
 * background (REQ-THM-002). Maps to HTTP 400 `{error: "contrast_too_low", ratio}`.
 *
 * @category  Exception
 * @package   OCA\LaunchPad\Exception
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

use InvalidArgumentException;

/**
 * The accent is under 3:1 against the background; carries the measured ratio.
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */
class ContrastTooLowException extends InvalidArgumentException {

	/**
	 * The measured ratio, rounded to one decimal.
	 *
	 * @var float
	 */
	private float $ratio;

	/**
	 * Constructor.
	 *
	 * @param float $ratio The measured contrast ratio.
	 *
	 * @return void
	 */
	public function __construct(float $ratio) {
		$this->ratio = round($ratio, 1);
		parent::__construct(
			message: sprintf('Buttons in this colour are hard to see (contrast %s to 1, needs 3 to 1)', number_format($this->ratio, 1, '.', ''))
		);
	}//end __construct()

	/**
	 * The measured ratio, rounded to one decimal.
	 *
	 * @return float
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
	 */
	public function getRatio(): float {
		return $this->ratio;
	}//end getRatio()
}//end class
