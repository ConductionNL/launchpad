<?php

/**
 * The exact payload the read-confirmation dialog sends
 * (src/dialogs/ReadConfirmationDialog.vue) is what PlacementUpdater applies
 * (engagement-acknowledgement-toggle REQ-ACK-007).
 *
 * @category Test
 * @package  Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\PlacementUpdater;
use PHPUnit\Framework\TestCase;

class PlacementUpdaterReadConfirmationTest extends TestCase {
	public function testTheDialogPayloadAsksForConfirmation(): void {
		$placement = new WidgetPlacement();

		(new PlacementUpdater())->applyAcknowledgementUpdates(
			placement: $placement,
			data: [
				'requiresAcknowledgement' => 1,
				'acknowledgementPrompt' => 'I have read the new expense rules',
				'acknowledgementDeadline' => '2026-10-15',
			]
		);

		$this->assertSame(1, (int)$placement->getRequiresAcknowledgement());
		$this->assertSame('I have read the new expense rules', $placement->getAcknowledgementPrompt());
		$this->assertSame('2026-10-15', $placement->getAcknowledgementDeadline());
		$this->assertNotEmpty($placement->getAnnouncementKey());
	}

	public function testUntickingStopsAskingAndKeepsTheKey(): void {
		$placement = new WidgetPlacement();
		$updater = new PlacementUpdater();
		$updater->applyAcknowledgementUpdates(placement: $placement, data: ['requiresAcknowledgement' => 1, 'acknowledgementPrompt' => null, 'acknowledgementDeadline' => null]);
		$key = $placement->getAnnouncementKey();

		$updater->applyAcknowledgementUpdates(placement: $placement, data: ['requiresAcknowledgement' => 0, 'acknowledgementPrompt' => null, 'acknowledgementDeadline' => null]);

		$this->assertSame(0, (int)$placement->getRequiresAcknowledgement());
		$this->assertSame($key, $placement->getAnnouncementKey());
	}
}
