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

use InvalidArgumentException;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\TileLinkValidator;
use OCA\LaunchPad\Service\TileUpdater;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\TileUpdater
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Service\TileLinkValidator
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
	 * A new sso tile stores the address the validator returns, from the content it was created with.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
	 */
	public function testANewLaunchTileStoresTheCheckedLink(): void {
		$content = ['sso' => ['template' => 'entra', 'appId' => 'a1b2c3']];
		$links = $this->createMock(TileLinkValidator::class);
		$links->expects($this->once())->method('check')
			->with('sso', 'https://typed.example/', $content)
			->willReturn('https://launcher.myapps.microsoft.com/api/signin/a1b2c3');
		$placement = new WidgetPlacement();

		(new TileUpdater(links: $links))->applyTileConfig(
			placement: $placement,
			tileData: ['linkType' => 'sso', 'linkVal' => 'https://typed.example/', 'content' => $content]
		);

		$this->assertSame('https://launcher.myapps.microsoft.com/api/signin/a1b2c3', $placement->getTileLinkValue());
		$this->assertSame($content, $placement->getContentArray());
	}

	/**
	 * A refused check leaves the save failing (HTTP 400 at the API).
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
	 */
	public function testARefusedLaunchTileThrows(): void {
		$links = $this->createMock(TileLinkValidator::class);
		$links->method('check')->willThrowException(new InvalidArgumentException('The scheme "javascript" is never allowed on a tile.'));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('javascript');
		(new TileUpdater(links: $links))->applyTileConfig(
			placement: new WidgetPlacement(),
			tileData: ['linkType' => 'program', 'linkVal' => 'javascript:alert(1)']
		);
	}

	/**
	 * An update that changes only the content of a launch tile is checked too.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
	 */
	public function testAnUpdateIsCheckedOnTheFinalState(): void {
		$placement = new WidgetPlacement();
		$placement->setTileLinkType('remote-desktop');
		$placement->setTileLinkValue('');
		$placement->setContentArray(['remote' => ['mode' => 'gateway', 'url' => 'https://desktop.gemeente.nl/']]);
		$links = $this->createMock(TileLinkValidator::class);
		$links->expects($this->once())->method('check')
			->with('remote-desktop', '', ['remote' => ['mode' => 'gateway', 'url' => 'https://desktop.gemeente.nl/']])
			->willReturn('https://desktop.gemeente.nl/');

		(new TileUpdater(links: $links))->applyTileUpdates(placement: $placement, data: ['tileTitle' => 'Werkplek']);

		$this->assertSame('https://desktop.gemeente.nl/', $placement->getTileLinkValue());
	}

	/**
	 * Without a validator a launch type is refused rather than stored unchecked.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
	 */
	public function testALaunchTypeWithoutAValidatorIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		(new TileUpdater())->applyTileUpdates(placement: new WidgetPlacement(), data: ['tileLinkType' => 'program', 'tileLinkValue' => 'ms-word:x']);
	}

	/**
	 * Other link types are not checked and keep their value.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
	 */
	public function testOtherLinkTypesAreNotChecked(): void {
		$links = $this->createMock(TileLinkValidator::class);
		$links->expects($this->never())->method('check');
		$placement = new WidgetPlacement();

		(new TileUpdater(links: $links))->applyTileConfig(placement: $placement, tileData: ['linkType' => 'url', 'linkVal' => 'https://wiki.example.org']);

		$this->assertSame('https://wiki.example.org', $placement->getTileLinkValue());
	}

	/**
	 * An office "New ..." tile keeps its `create` link type and creator id
	 * (integrations-office-tiles REQ-OFT-002).
	 */
	public function testACreateTileKeepsItsLinkTypeAndCreator(): void {
		$placement = new WidgetPlacement();
		$updater   = new TileUpdater();

		$updater->applyTileConfig(
			placement: $placement,
			tileData: ['title' => 'New document', 'linkType' => 'create', 'linkVal' => 'richdocuments:.docx']
		);

		$this->assertSame('create', $placement->getTileLinkType());
		$this->assertSame('richdocuments:.docx', $placement->getTileLinkValue());

		$updater->applyTileUpdates(placement: $placement, data: ['tileLinkValue' => 'onlyoffice:.xlsx']);

		$this->assertSame('create', $placement->getTileLinkType());
		$this->assertSame('onlyoffice:.xlsx', $placement->getTileLinkValue());
	}
}
