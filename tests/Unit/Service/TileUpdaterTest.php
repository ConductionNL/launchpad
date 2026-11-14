<?php

/**
 * Unit tests for TileUpdater: defaults on create, partial field updates on edit.
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
use OCA\LaunchPad\Service\TileUpdater;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\TileUpdater
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 */
class TileUpdaterTest extends TestCase {
	public function testApplyTileConfigFillsDefaultsForAnEmptyPayload(): void {
		$placement = new WidgetPlacement();

		(new TileUpdater())->applyTileConfig(placement: $placement, tileData: []);

		$this->assertSame('custom', $placement->getTileType());
		$this->assertSame('New Tile', $placement->getTileTitle());
		$this->assertSame('icon-link', $placement->getTileIcon());
		$this->assertSame('class', $placement->getTileIconType());
		$this->assertSame('#0082c9', $placement->getTileBackgroundColor());
		$this->assertSame('#ffffff', $placement->getTileTextColor());
		$this->assertSame('app', $placement->getTileLinkType());
		$this->assertSame('', $placement->getTileLinkValue());
	}

	public function testApplyTileConfigMapsTheShortPayloadKeys(): void {
		$placement = new WidgetPlacement();

		(new TileUpdater())->applyTileConfig(
			placement: $placement,
			tileData: [
				'title' => 'Wiki',
				'icon' => 'mdi-book',
				'iconType' => 'mdi',
				'bgColor' => '#112233',
				'txtColor' => '#000000',
				'linkType' => 'url',
				'linkVal' => 'https://wiki.example.org',
			]
		);

		$this->assertSame('custom', $placement->getTileType());
		$this->assertSame('Wiki', $placement->getTileTitle());
		$this->assertSame('mdi-book', $placement->getTileIcon());
		$this->assertSame('mdi', $placement->getTileIconType());
		$this->assertSame('#112233', $placement->getTileBackgroundColor());
		$this->assertSame('#000000', $placement->getTileTextColor());
		$this->assertSame('url', $placement->getTileLinkType());
		$this->assertSame('https://wiki.example.org', $placement->getTileLinkValue());
	}

	public function testApplyTileUpdatesChangesOnlyTheKeysPresent(): void {
		$placement = new WidgetPlacement();
		$updater = new TileUpdater();
		$updater->applyTileConfig(placement: $placement, tileData: []);

		$updater->applyTileUpdates(
			placement: $placement,
			data: ['tileTitle' => 'Renamed', 'tileLinkValue' => 'files']
		);

		$this->assertSame('Renamed', $placement->getTileTitle());
		$this->assertSame('files', $placement->getTileLinkValue());
		$this->assertSame('icon-link', $placement->getTileIcon());
		$this->assertSame('#0082c9', $placement->getTileBackgroundColor());
		$this->assertSame('app', $placement->getTileLinkType());
	}

	public function testApplyTileUpdatesAppliesEveryTileField(): void {
		$placement = new WidgetPlacement();

		(new TileUpdater())->applyTileUpdates(
			placement: $placement,
			data: [
				'tileTitle' => 'T',
				'tileIcon' => 'I',
				'tileIconType' => 'svg',
				'tileBackgroundColor' => '#aaaaaa',
				'tileTextColor' => '#bbbbbb',
				'tileLinkType' => 'url',
				'tileLinkValue' => '/x',
			]
		);

		$this->assertSame('T', $placement->getTileTitle());
		$this->assertSame('I', $placement->getTileIcon());
		$this->assertSame('svg', $placement->getTileIconType());
		$this->assertSame('#aaaaaa', $placement->getTileBackgroundColor());
		$this->assertSame('#bbbbbb', $placement->getTileTextColor());
		$this->assertSame('url', $placement->getTileLinkType());
		$this->assertSame('/x', $placement->getTileLinkValue());
	}

	public function testApplyTileUpdatesIgnoresNullValues(): void {
		$placement = new WidgetPlacement();
		$updater = new TileUpdater();
		$updater->applyTileConfig(placement: $placement, tileData: ['title' => 'Keep']);

		$updater->applyTileUpdates(placement: $placement, data: ['tileTitle' => null]);

		$this->assertSame('Keep', $placement->getTileTitle());
	}

	/**
	 * A new tile stores each link target in its content; none leaves content alone.
	 *
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function testApplyTileConfigStoresTheLinkTarget(): void {
		$stored = [];
		foreach (['same-tab', 'new-tab', 'side-panel', null, ''] as $target) {
			$placement = new WidgetPlacement();
			(new TileUpdater())->applyTileConfig(placement: $placement, tileData: ['title' => 'T', 'linkTarget' => $target]);
			$stored[] = $placement->getContentArray();
		}

		$this->assertSame(
			[['linkTarget' => 'same-tab'], ['linkTarget' => 'new-tab'], ['linkTarget' => 'side-panel'], [], []],
			$stored
		);
	}

	/**
	 * An unknown link target on create is refused.
	 *
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function testApplyTileConfigRefusesAnUnknownLinkTarget(): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('linkTarget must be one of same-tab, new-tab, side-panel');

		(new TileUpdater())->applyTileConfig(placement: new WidgetPlacement(), tileData: ['linkTarget' => '_blank']);
	}

	/**
	 * On update a valid or absent content.linkTarget passes; any other value is refused.
	 *
	 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
	 */
	public function testApplyTileUpdatesChecksTheContentLinkTarget(): void {
		$updater = new TileUpdater();
		$placement = new WidgetPlacement();
		$updater->applyTileUpdates(placement: $placement, data: ['content' => ['linkTarget' => 'side-panel', 'healthPing' => []]]);
		$updater->applyTileUpdates(placement: $placement, data: ['content' => ['linkTarget' => null]]);
		$updater->applyTileUpdates(placement: $placement, data: ['content' => ['healthPing' => []]]);

		$refused = [];
		foreach (['popup', 1, ['side-panel']] as $bad) {
			try {
				$updater->applyTileUpdates(placement: $placement, data: ['content' => ['linkTarget' => $bad]]);
				$refused[] = false;
			} catch (\InvalidArgumentException $e) {
				$refused[] = $e->getMessage();
			}
		}

		$this->assertSame(array_fill(0, 3, 'content.linkTarget must be one of same-tab, new-tab, side-panel'), $refused);
	}
}
