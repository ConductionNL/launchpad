<?php

/**
 * TileInternalAddressTest
 *
 * The office network address of a tile (launcher-tile-internal-address,
 * REQ-TIA-002) through the real PlacementService, PlacementUpdater and
 * TileUpdater: a valid address is stored in the tile's content, anything
 * else refuses the whole update before anything is written.
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\TileUpdater;
use OCA\LaunchPad\Service\TileLaunchSettingsService;
use OCA\LaunchPad\Service\TileLaunchValidator;
use OCA\LaunchPad\Db\AdminSettingMapper;
use PHPUnit\Framework\TestCase;

class TileInternalAddressTest extends TestCase {
	private int $updates = 0;

	private function service(): PlacementService {
		$tile = new WidgetPlacement();
		$tile->setId(5);
		$tile->setWidgetId('tile-abc');
		$tile->setTileLinkType('url');
		$tile->setTileLinkValue('https://zaken.gemeente.nl/');

		$mapper = $this->createMock(WidgetPlacementMapper::class);
		$mapper->method('find')->willReturn($tile);
		$mapper->method('update')->willReturnCallback(
			function (WidgetPlacement $placement): WidgetPlacement {
				$this->updates++;
				return $placement;
			}
		);

		return new PlacementService(
			placementMapper: $mapper,
			tileUpdater: new TileUpdater(launchValidator: new TileLaunchValidator(settings: new TileLaunchSettingsService(settingMapper: $this->createMock(AdminSettingMapper::class)))),
			placementUpdater: new PlacementUpdater(),
		);
	}//end service()

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function validAddresses(): array {
		return [
			'intranet http' => ['http://zaken.intern.gemeente.local/'],
			'https' => ['https://10.1.2.3:8443/start'],
			'path on this Nextcloud' => ['/apps/files'],
			'cleared' => [''],
		];
	}//end validAddresses()

	/**
	 * @dataProvider validAddresses
	 */
	public function testAValidOfficeAddressIsStoredInTheContent(string $address): void {
		$placement = $this->service()->updatePlacement(placementId: 5, data: ['content' => ['internalUrl' => $address, 'healthPingEnabled' => false]]);

		$this->assertSame($address, $placement->getContentArray()['internalUrl']);
		$this->assertSame(1, $this->updates);
	}//end testAValidOfficeAddressIsStoredInTheContent()

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function invalidAddresses(): array {
		return [
			'script' => ['javascript:alert(1)'],
			'protocol relative' => ['//evil.example/'],
			'file' => ['file:///etc/passwd'],
			'no host' => ['https://'],
			'not text' => [['https://x.nl']],
		];
	}//end invalidAddresses()

	/**
	 * @dataProvider invalidAddresses
	 */
	public function testAnInvalidOfficeAddressRefusesTheUpdate(mixed $address): void {
		try {
			$this->service()->updatePlacement(placementId: 5, data: ['content' => ['internalUrl' => $address]]);
			$this->fail('accepted an invalid office address');
		} catch (InvalidArgumentException) {
			$this->assertSame(0, $this->updates, 'nothing may be written');
		}
	}//end testAnInvalidOfficeAddressRefusesTheUpdate()
}//end class
