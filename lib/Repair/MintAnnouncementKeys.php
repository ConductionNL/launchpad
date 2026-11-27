<?php

/**
 * MintAnnouncementKeys
 *
 * Repair step: every placement that asks for confirmation gets a stable
 * announcement key, so Read receipts shows for it (REQ-ACK-009).
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Give the placements written before keys were given on every save their
 * announcement key. Idempotent: a placement with a key is never touched.
 *
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-a-placement-that-asks-for-confirmation-has-a-key-req-ack-009
 */
class MintAnnouncementKeys implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper $placements The placement store.
	 * @param LoggerInterface       $logger     PSR-3 logger.
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placements,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-a-placement-that-asks-for-confirmation-has-a-key-req-ack-009
	 */
	public function getName(): string {
		return 'Give every widget that asks for a read confirmation its announcement key';
	}//end getName()

	/**
	 * Run the step. A failure is a warning, never a failed upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-a-placement-that-asks-for-confirmation-has-a-key-req-ack-009
	 */
	public function run(IOutput $output): void {
		try {
			$given = 0;
			foreach ($this->placements->findAcknowledged(withKey: false) as $placement) {
				// The mapper's update gives the key.
				$this->placements->update(entity: $placement);
				$given++;
			}
		} catch (Throwable $e) {
			$this->logger->warning(message: '[MintAnnouncementKeys] could not give the announcement keys: ' . $e->getMessage());
			$output->warning(message: 'The announcement keys were not given: ' . $e->getMessage());
			return;
		}

		$output->info(message: sprintf('%d widget(s) got an announcement key.', $given));
	}//end run()
}//end class
