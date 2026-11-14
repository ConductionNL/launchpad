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

/**
 * Service backing the setup wizard state + completion endpoints.
 *
 * Status values follow the spec (REQ-WIZ-008): `'done'`, `'skipped'`,
 * `'pending'`. The wizard "pending" state means a step has not been
 * traversed yet (no sibling setting written). `'skipped'` is reserved for
 * the optional steps 4/5 — when their guarded capability isn't available,
 * the heuristic returns `'skipped'` so the UI can collapse the step.
 */
class SetupWizardService {
	/**
	 * Total number of wizard steps (REQ-WIZ-002).
	 *
	 * @var integer
	 */
	public const STEP_COUNT = 6;

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
	 * `'done'`; Step 6 stays `'pending'` until the admin clicks Finish.
	 *
	 * @return array{complete: bool, currentRecommendedStep: int, stepStatuses: array<int,string>}
	 *                                                                                             The wizard state payload.
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	public function getWizardState(): array {
		$complete = $this->isWizardComplete();
		$stepStatuses = $this->computeStepStatuses(complete: $complete);

		return [
			'complete' => $complete,
			'currentRecommendedStep' => $this->resolveRecommendedStep(
				stepStatuses: $stepStatuses
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
	 */
	private function computeStepStatuses(bool $complete): array {
		$settings = $this->settingMapper->getAllAsArray();

		$groupOrder = ($settings[AdminSetting::KEY_GROUP_ORDER] ?? null);
		$hasGroup = (is_array($groupOrder) === true && count($groupOrder) > 0);
		$hasFooter = isset($settings[AdminSetting::KEY_FOOTER_CONFIG]);

		// Steps 3 (demos) and 4 (admin-roles) live in sibling capabilities
		// not yet implemented in this branch; surface them as `'skipped'`
		// so the wizard advances cleanly when the embed component is a
		// local stub. They flip to `'done'` once the sibling capability
		// ships and writes its own settings.
		$statusGroup = 'pending';
		if ($hasGroup === true) {
			$statusGroup = 'done';
		}

		$statusFooter = 'skipped';
		if ($hasFooter === true) {
			$statusFooter = 'done';
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
			'5' => $statusFooter,
			'6' => $statusDone,
		];
	}//end computeStepStatuses()

	/**
	 * The first step whose status is not `'done'`, defaulting to Step 1.
	 *
	 * @param array<int,string> $stepStatuses Heuristic per-step statuses.
	 *
	 * @return integer The recommended next step index (1..STEP_COUNT).
	 */
	private function resolveRecommendedStep(array $stepStatuses): int {
		for ($step = 1; $step <= self::STEP_COUNT; $step++) {
			$status = ($stepStatuses[$step] ?? 'pending');
			if ($status !== 'done') {
				return $step;
			}
		}

		return 1;
	}//end resolveRecommendedStep()
}//end class
