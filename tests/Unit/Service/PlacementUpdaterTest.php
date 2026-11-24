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
 * @uses   \OCA\LaunchPad\Service\VideoContent
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

		$this->assertSame(
			expected: [5, 6, 7, 8],
			actual: [$placement->getGridX(), $placement->getGridY(), $placement->getGridWidth(), $placement->getGridHeight()]
		);
	}

	public function testGridUpdatesLeaveAbsentAndNullKeysAlone(): void {
		$placement = $this->placement();

		(new PlacementUpdater())->applyGridUpdates(placement: $placement, data: ['gridY' => 9, 'gridWidth' => null]);

		$this->assertSame(
			expected: [1, 9, 3, 4],
			actual: [$placement->getGridX(), $placement->getGridY(), $placement->getGridWidth(), $placement->getGridHeight()]
		);
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

		$this->assertSame(expected: 0, actual: $placement->getIsVisible());
		$this->assertSame(expected: 0, actual: $placement->getShowTitle());
		$this->assertSame(expected: 'Mine', actual: $placement->getCustomTitle());
		$this->assertSame(expected: 'icon-star', actual: $placement->getCustomIcon());
		$this->assertSame(expected: ['background' => '#ffffff'], actual: $placement->getStyleConfigArray());
		$this->assertSame(expected: ['text' => 'hello'], actual: $placement->getContentArray());
	}

	public function testVideoContentUpdateKeepsAutoplayMutedAndFillsDefaults(): void {
		$placement = $this->placement();
		$placement->setWidgetId('video');

		(new PlacementUpdater())->applyDisplayUpdates(
			placement: $placement,
			data: ['content' => ['sourceType' => 'youtube', 'videoUrl' => 'https://www.youtube.com/embed/ABC123', 'autoplay' => true, 'muted' => false]]
		);

		$content = $placement->getContentArray();
		$this->assertTrue(condition: $content['muted']);
		$this->assertTrue(condition: $content['controls']);
		$this->assertSame(expected: '16:9', actual: $content['aspectRatio']);
	}

	public function testDisplayUpdatesIgnoreNonArrayContent(): void {
		$placement = $this->placement();
		$placement->setContentArray(['text' => 'keep']);

		(new PlacementUpdater())->applyDisplayUpdates(placement: $placement, data: ['content' => 'not-an-array']);

		$this->assertSame(expected: ['text' => 'keep'], actual: $placement->getContentArray());
	}

	public function testEmptyDeadlineIsStoredAsNoDeadlineAndEmptyPromptKept(): void {
		$placement = $this->placement();
		$placement->setAcknowledgementDeadline('2026-01-01');

		(new PlacementUpdater())->applyAcknowledgementUpdates(
			placement: $placement,
			data: ['acknowledgementPrompt' => '', 'acknowledgementDeadline' => '']
		);

		$this->assertSame(expected: '', actual: $placement->getAcknowledgementPrompt());
		$this->assertNull(actual: $placement->getAcknowledgementDeadline());
	}

	public function testNullPromptClearsTheStoredPrompt(): void {
		$placement = $this->placement();
		$placement->setAcknowledgementPrompt('Old');

		(new PlacementUpdater())->applyAcknowledgementUpdates(placement: $placement, data: ['acknowledgementPrompt' => null]);

		$this->assertNull(actual: $placement->getAcknowledgementPrompt());
	}

	public function testCadenceCoercesTheFlagAndAcceptsPositiveVersions(): void {
		$placement = $this->placement();

		(new PlacementUpdater())->applyAcknowledgementUpdates(
			placement: $placement,
			data: ['reacknowledgeOnChange' => '1', 'acknowledgementContentVersion' => '3']
		);

		$this->assertSame(expected: 1, actual: $placement->getReacknowledgeOnChange());
		$this->assertSame(expected: 3, actual: $placement->getAcknowledgementContentVersion());
	}

	public function testNonPositiveVersionNeverRewindsTheStoredVersion(): void {
		$placement = $this->placement();
		$placement->setAcknowledgementContentVersion(4);
		$updater = new PlacementUpdater();

		$updater->applyAcknowledgementUpdates(placement: $placement, data: ['acknowledgementContentVersion' => 0]);
		$updater->applyAcknowledgementUpdates(placement: $placement, data: ['acknowledgementContentVersion' => -2]);
		$updater->applyAcknowledgementUpdates(placement: $placement, data: ['acknowledgementContentVersion' => 'abc']);

		$this->assertSame(expected: 4, actual: $placement->getAcknowledgementContentVersion());
	}

	public function testExistingAnnouncementKeyIsNotRotated(): void {
		$placement = $this->placement();
		$placement->setAnnouncementKey('fixed-key');

		(new PlacementUpdater())->applyAcknowledgementUpdates(placement: $placement, data: ['requiresAcknowledgement' => true]);

		$this->assertSame(expected: 1, actual: $placement->getRequiresAcknowledgement());
		$this->assertSame(expected: 'fixed-key', actual: $placement->getAnnouncementKey());
	}

	public function testEmptyAnnouncementKeyIsMintedAsUuidV4(): void {
		$placement = $this->placement();
		$placement->setAnnouncementKey('');

		(new PlacementUpdater())->applyAcknowledgementUpdates(placement: $placement, data: ['requiresAcknowledgement' => 1]);

		$this->assertMatchesRegularExpression(
			pattern: '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
			string: $placement->getAnnouncementKey()
		);
	}

	public function testNoKeyIsMintedWhileAcknowledgementIsNotRequired(): void {
		$placement = $this->placement();

		(new PlacementUpdater())->applyAcknowledgementUpdates(placement: $placement, data: ['requiresAcknowledgement' => 0]);

		$this->assertNull(actual: $placement->getAnnouncementKey());
	}

	/**
	 * The match mode is set when given, `all` kept and anything else stored
	 * as `any`; absent, it is left as it is (REQ-VIS-014).
	 *
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 *
	 * @return void
	 */
	public function testTheRuleMatchIsApplied(): void {
		$placement = $this->placement();
		$updater = new PlacementUpdater();

		$this->assertSame(expected: 'any', actual: $placement->getRuleMatch());
		$updater->applyRuleMatch(placement: $placement, data: ['ruleMatch' => 'all']);
		$this->assertSame(expected: 'all', actual: $placement->getRuleMatch());
		$updater->applyRuleMatch(placement: $placement, data: ['gridX' => 1]);
		$this->assertSame(expected: 'all', actual: $placement->getRuleMatch());
		$updater->applyRuleMatch(placement: $placement, data: ['ruleMatch' => 'most']);
		$this->assertSame(expected: 'any', actual: $placement->getRuleMatch());
	}

	/**
	 * Export carries the mode and import reads it back, defaulting to `any`.
	 *
	 * @spec openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility/spec.md#requirement-all-or-one-rule-must-match-req-vis-014
	 *
	 * @return void
	 */
	public function testTheRuleMatchTravelsThroughExportAndImport(): void {
		$placement = $this->placement();
		$placement->setRuleMatch('all');
		$hydrator = new \OCA\LaunchPad\Service\PlacementPayloadHydrator();

		$this->assertSame(expected: 'all', actual: $hydrator->hydrate(dashboardId: 2, payload: $placement->jsonSerialize())->getRuleMatch());
		$this->assertSame(expected: 'any', actual: $hydrator->hydrate(dashboardId: 2, payload: ['widgetId' => 'news'])->getRuleMatch());
	}
}
