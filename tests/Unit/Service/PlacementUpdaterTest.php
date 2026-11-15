<?php

/**
 * Unit tests for PlacementUpdater grid, display and acknowledgement-cadence updates.
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

/**
 * @covers \OCA\LaunchPad\Service\PlacementUpdater
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Service\UuidV4
 */
class PlacementUpdaterTest extends TestCase {
	private function placement(): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setGridX(1);
		$placement->setGridY(2);
		$placement->setGridWidth(3);
		$placement->setGridHeight(4);
		return $placement;
	}

	public function testGridUpdatesApplyEveryPresentKey(): void {
		$placement = $this->placement();

		(new PlacementUpdater())->applyGridUpdates(
			placement: $placement,
			data: ['gridX' => 5, 'gridY' => 6, 'gridWidth' => 7, 'gridHeight' => 8]
		);

		$this->assertSame([5, 6, 7, 8], [$placement->getGridX(), $placement->getGridY(), $placement->getGridWidth(), $placement->getGridHeight()]);
	}

	public function testGridUpdatesLeaveAbsentAndNullKeysAlone(): void {
		$placement = $this->placement();

		(new PlacementUpdater())->applyGridUpdates(placement: $placement, data: ['gridY' => 9, 'gridWidth' => null]);

		$this->assertSame([1, 9, 3, 4], [$placement->getGridX(), $placement->getGridY(), $placement->getGridWidth(), $placement->getGridHeight()]);
	}

	public function testDisplayUpdatesApplyEveryField(): void {
		$placement = $this->placement();

		(new PlacementUpdater())->applyDisplayUpdates(
			placement: $placement,
			data: [
				'isVisible' => 0,
				'showTitle' => 0,
				'customTitle' => 'Mine',
				'customIcon' => 'icon-star',
				'styleConfig' => ['background' => '#ffffff'],
				'content' => ['text' => 'hello'],
			]
		);

		$this->assertSame(0, $placement->getIsVisible());
		$this->assertSame(0, $placement->getShowTitle());
		$this->assertSame('Mine', $placement->getCustomTitle());
		$this->assertSame('icon-star', $placement->getCustomIcon());
		$this->assertSame(['background' => '#ffffff'], $placement->getStyleConfigArray());
		$this->assertSame(['text' => 'hello'], $placement->getContentArray());
	}

	public function testDisplayUpdatesIgnoreNonArrayContent(): void {
		$placement = $this->placement();
		$placement->setContentArray(['text' => 'keep']);

		(new PlacementUpdater())->applyDisplayUpdates(placement: $placement, data: ['content' => 'not-an-array']);

		$this->assertSame(['text' => 'keep'], $placement->getContentArray());
	}

	public function testEmptyDeadlineIsStoredAsNoDeadlineAndEmptyPromptKept(): void {
		$placement = $this->placement();
		$placement->setAcknowledgementDeadline('2026-01-01');

		(new PlacementUpdater())->applyAcknowledgementUpdates(
			placement: $placement,
			data: ['acknowledgementPrompt' => '', 'acknowledgementDeadline' => '']
		);

		$this->assertSame('', $placement->getAcknowledgementPrompt());
		$this->assertNull($placement->getAcknowledgementDeadline());
	}

	public function testNullPromptClearsTheStoredPrompt(): void {
		$placement = $this->placement();
		$placement->setAcknowledgementPrompt('Old');

		(new PlacementUpdater())->applyAcknowledgementUpdates(placement: $placement, data: ['acknowledgementPrompt' => null]);

		$this->assertNull($placement->getAcknowledgementPrompt());
	}

	public function testCadenceCoercesTheFlagAndAcceptsPositiveVersions(): void {
		$placement = $this->placement();

		(new PlacementUpdater())->applyAcknowledgementUpdates(
			placement: $placement,
			data: ['reacknowledgeOnChange' => '1', 'acknowledgementContentVersion' => '3']
		);

		$this->assertSame(1, $placement->getReacknowledgeOnChange());
		$this->assertSame(3, $placement->getAcknowledgementContentVersion());
	}

	public function testNonPositiveVersionNeverRewindsTheStoredVersion(): void {
		$placement = $this->placement();
		$placement->setAcknowledgementContentVersion(4);
		$updater = new PlacementUpdater();

		$updater->applyAcknowledgementUpdates(placement: $placement, data: ['acknowledgementContentVersion' => 0]);
		$updater->applyAcknowledgementUpdates(placement: $placement, data: ['acknowledgementContentVersion' => -2]);
		$updater->applyAcknowledgementUpdates(placement: $placement, data: ['acknowledgementContentVersion' => 'abc']);

		$this->assertSame(4, $placement->getAcknowledgementContentVersion());
	}

	public function testExistingAnnouncementKeyIsNotRotated(): void {
		$placement = $this->placement();
		$placement->setAnnouncementKey('fixed-key');

		(new PlacementUpdater())->applyAcknowledgementUpdates(placement: $placement, data: ['requiresAcknowledgement' => true]);

		$this->assertSame(1, $placement->getRequiresAcknowledgement());
		$this->assertSame('fixed-key', $placement->getAnnouncementKey());
	}

	public function testEmptyAnnouncementKeyIsMintedAsUuidV4(): void {
		$placement = $this->placement();
		$placement->setAnnouncementKey('');

		(new PlacementUpdater())->applyAcknowledgementUpdates(placement: $placement, data: ['requiresAcknowledgement' => 1]);

		$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $placement->getAnnouncementKey());
	}

	public function testNoKeyIsMintedWhileAcknowledgementIsNotRequired(): void {
		$placement = $this->placement();

		(new PlacementUpdater())->applyAcknowledgementUpdates(placement: $placement, data: ['requiresAcknowledgement' => 0]);

		$this->assertNull($placement->getAnnouncementKey());
	}
}
