<?php

/**
 * SetupWizardService
 *
 * Orchestrator for the first-run setup wizard (REQ-WIZ-001..011). The
 * service owns the `setup_wizard_complete` admin-setting flag and derives
 * per-step statuses heuristically from the underlying admin settings —
 * the wizard never duplicates state that lives on a sibling capability.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Kernel\Contracts\SetupWizardServiceInterface;

/**
 * Service backing the setup wizard state + completion endpoints.
 *
 * Status values follow the spec (REQ-WIZ-008): `'done'`, `'skipped'`,
 * `'pending'`. The wizard "pending" state means a step has not been
 * traversed yet (no sibling setting written). `'skipped'` marks the
 * optional steps 3/4 whose sibling capability writes no setting the
 * heuristic can read. Five steps: Welcome, Groups, Examples, Roles, Done
 * (REQ-WIZ-012); the footer is set on its own admin tab, not here.
 *
 * @spec openspec/changes/archive/2026-11-24-setup-five-steps-and-admin-tour/specs/setup-wizard/spec.md#requirement-req-wiz-012-multi-step-wizard-flow-with-5-steps
 */
class SetupWizardService implements SetupWizardServiceInterface {
	/**
	 * Total number of wizard steps (REQ-WIZ-012).
	 *
	 * @var integer
	 */
	public const STEP_COUNT = 5;

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settingMapper Admin-setting persistence.
	 */
	public function __construct(
		private readonly AdminSettingMapper $settingMapper,
	) {
	}//end __construct()

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
	 * @spec openspec/changes/archive/2026-11-24-setup-five-steps-and-admin-tour/specs/setup-wizard/spec.md#requirement-req-wiz-012-multi-step-wizard-flow-with-5-steps
	 */
	public function getWizardState(): array {
		$complete = $this->isWizardComplete();
		$stepStatuses = $this->computeStepStatuses(complete: $complete);

		return [
			'complete' => $complete,
			'currentRecommendedStep' => $this->resolveRecommendedStep(
				stepStatuses: $stepStatuses,
				complete: $complete
			),
			'stepStatuses' => $stepStatuses,
		];
	}//end getWizardState()

	/**
	 * Mark the wizard complete (REQ-WIZ-009). Idempotent — calling on a
	 * completed instance is a no-op that still returns the current state.
	 *
	 * @return array{complete: bool, currentRecommendedStep: int, stepStatuses: array<int,string>}
	 *                                                                                             The updated wizard state payload.
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	public function markWizardComplete(): array {
		$this->settingMapper->setSetting(
			key: AdminSetting::KEY_SETUP_WIZARD_COMPLETE,
			value: true
		);

		return $this->getWizardState();
	}//end markWizardComplete()

	/**
	 * Whether the wizard has been completed at least once.
	 *
	 * @return boolean True when the flag is JSON `true`.
	 */
	private function isWizardComplete(): bool {
		$value = $this->settingMapper->getValue(
			key: AdminSetting::KEY_SETUP_WIZARD_COMPLETE,
			default: false
		);
		return ($value === true);
	}//end isWizardComplete()

	/**
	 * Heuristic step-status table per REQ-WIZ-008's Data Model.
	 *
	 * The returned keys are numeric strings 1..STEP_COUNT — PHP coerces
	 * numeric string keys to integers internally, but JSON serialisation
	 * emits them as numeric properties matching the spec example.
	 *
	 * @param boolean $complete Whether the wizard has been completed.
	 *
	 * @return array<int,string> Step number → status.
	 *
	 * @spec openspec/changes/archive/2026-11-24-setup-five-steps-and-admin-tour/specs/setup-wizard/spec.md#requirement-req-wiz-012-multi-step-wizard-flow-with-5-steps
	 */
	private function computeStepStatuses(bool $complete): array {
		$settings = $this->settingMapper->getAllAsArray();

		$groupOrder = ($settings[AdminSetting::KEY_GROUP_ORDER] ?? null);
		$hasGroup = (is_array($groupOrder) === true && count($groupOrder) > 0);

		// Steps 3 (demos) and 4 (admin-roles) live in sibling capabilities
		// not yet implemented in this branch; surface them as `'skipped'`
		// so the wizard advances cleanly when the embed component is a
		// local stub. They flip to `'done'` once the sibling capability
		// ships and writes its own settings.
		$statusGroup = 'pending';
		if ($hasGroup === true) {
			$statusGroup = 'done';
		}

		$statusDone = 'pending';
		if ($complete === true) {
			$statusDone = 'done';
		}

		return [
			'1' => 'done',
			'2' => $statusGroup,
			'3' => 'skipped',
			'4' => 'skipped',
			'5' => $statusDone,
		];
	}//end computeStepStatuses()

	/**
	 * The step the wizard opens at. A first run (nothing after Step 1
	 * done and the wizard not finished) starts at Step 1, Welcome; a
	 * re-run opens at the first step whose status is not `'done'`
	 * (REQ-WIZ-008: Step 1 on a fresh instance, the first open step on
	 * a re-run; "Wizard starts at Step 1").
	 *
	 * @param array<int,string> $stepStatuses Heuristic per-step statuses.
	 * @param bool              $complete     Whether the wizard was finished.
	 *
	 * @return integer The recommended next step index (1..STEP_COUNT).
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	private function resolveRecommendedStep(array $stepStatuses, bool $complete): int {
		$firstOpen = 1;
		$anyDone   = false;
		for ($step = self::STEP_COUNT; $step >= 2; $step--) {
			$status = ($stepStatuses[$step] ?? 'pending');
			if ($status === 'done') {
				$anyDone = true;
				continue;
			}

			$firstOpen = $step;
		}

		if ($complete === false && $anyDone === false) {
			return 1;
		}

		return $firstOpen;
	}//end resolveRecommendedStep()
}//end class
