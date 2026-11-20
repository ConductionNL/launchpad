<?php

/**
 * SetDefaultApp Repair Step
 *
 * On install and on upgrade, makes LaunchPad the instance's default app, so
 * signing in lands on the Workspace page (REQ-PSP-003). Only when no default
 * app is configured at all, and only while the admin toggle is on (it is on by
 * default); {@see DefaultAppSetting} holds the rules.
 *
 * Like {@see SeedDefaultDashboard} the step never lets an exception escape: a
 * default app that could not be set is a warning, not a failed install.
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\Service\DefaultAppSetting;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Make LaunchPad the default app on install and upgrade.
 *
 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
 */
class SetDefaultApp implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param DefaultAppSetting $defaultApp The default app rules.
	 * @param LoggerInterface   $logger     PSR-3 logger.
	 */
	public function __construct(
		private readonly DefaultAppSetting $defaultApp,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
	 */
	public function getName(): string {
		return 'Make LaunchPad the default app';
	}//end getName()

	/**
	 * Run the step.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-public-share/spec.md#requirement-the-start-page-offers-a-way-in-for-staff-req-psp-003
	 */
	public function run(IOutput $output): void {
		try {
			$outcome = $this->defaultApp->claim();
		} catch (Throwable $e) {
			$this->logger->warning(message: '[SetDefaultApp] could not set the default app: ' . $e->getMessage());
			$output->warning(message: 'LaunchPad was not made the default app: ' . $e->getMessage());
			return;
		}

		$messages = [
			DefaultAppSetting::SET       => 'LaunchPad is now the default app.',
			DefaultAppSetting::KEPT      => 'A default app is already set — keeping it.',
			DefaultAppSetting::DISABLED  => 'Making LaunchPad the default app is switched off — skipping.',
			DefaultAppSetting::UNCHANGED => 'LaunchPad already is the default app — skipping.',
		];
		$output->info(message: $messages[$outcome] ?? $outcome);
	}//end run()
}//end class
