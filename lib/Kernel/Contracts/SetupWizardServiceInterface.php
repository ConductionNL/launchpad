<?php

/**
 * Contract of SetupWizardService
 *
 * The public surface of SetupWizardService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

/**
 * Contract of SetupWizardService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface SetupWizardServiceInterface {
	/**
	 * Return the wizard state payload (REQ-WIZ-008).
	 *
	 * Shape: `{complete: bool, currentRecommendedStep: int,
	 *         stepStatuses: array<string,string>}`. Step 1 is always
	 * `'done'`; Step 5 stays `'pending'` until the admin clicks Finish.
	 *
	 * @return array{complete: bool, currentRecommendedStep: int, stepStatuses: array<int,string>}
	 *                                                                                             The wizard state payload.
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	public function getWizardState(): array;

	/**
	 * Mark the wizard complete (REQ-WIZ-009). Idempotent — calling on a
	 * completed instance is a no-op that still returns the current state.
	 *
	 * @return array{complete: bool, currentRecommendedStep: int, stepStatuses: array<int,string>}
	 *                                                                                             The updated wizard state payload.
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	public function markWizardComplete(): array;
}//end interface
