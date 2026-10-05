<?php

/**
 * TileLaunchTypesTest
 *
 * The program, remote desktop and single sign-on link types
 * (launcher-tile-launch-types, REQ-TLT-001 to REQ-TLT-003) through the real
 * PlacementService, PlacementUpdater, TileUpdater, TileLaunchValidator and
 * TileLaunchSettingsService: a valid tile is written, anything else refuses
 * the whole create or update before anything is written.
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
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\TileLaunchSettingsService;
use OCA\LaunchPad\Service\TileLaunchValidator;
use OCA\LaunchPad\Service\TileUpdater;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods) One test per rule.
 */
class TileLaunchTypesTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [
		'tile_allowed_schemes' => ['ms-word', 'vscode'],
		'sso_launch_templates' => [
			['key' => 'entra', 'name' => 'Microsoft Entra ID', 'urlTemplate' => 'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso'],
		],
	];

	private int $writes = 0;

	private WidgetPlacement $tile;

	private function service(): PlacementService {
		$this->tile = new WidgetPlacement();
		$this->tile->setId(5);
		$this->tile->setDashboardId(3);
		$this->tile->setWidgetId('tile-abc');
		$this->tile->setTileLinkType('url');
		$this->tile->setTileLinkValue('https://zaken.gemeente.nl/');

		$settings = $this->createMock(AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default
		);
		$settings->method('setSetting')->willReturn(new AdminSetting());

		$mapper = $this->createMock(WidgetPlacementMapper::class);
		$mapper->method('find')->willReturnCallback(fn (): WidgetPlacement => $this->tile);
		$write = function (WidgetPlacement $placement): WidgetPlacement {
			$this->writes++;
			return $placement;
		};
		$mapper->method('update')->willReturnCallback($write);
		$mapper->method('insert')->willReturnCallback($write);

		$validator = new TileLaunchValidator(settings: new TileLaunchSettingsService(settingMapper: $settings));

		return new PlacementService(
			placementMapper: $mapper,
			tileUpdater: new TileUpdater(launchValidator: $validator),
			placementUpdater: new PlacementUpdater(),
		);
	}//end service()

	public function testAProgramTileOnAnAllowedSchemeIsCreated(): void {
		$placement = $this->service()->addTileFromArray(
			dashboardId: 3,
			tileData: ['title' => 'Word', 'linkType' => 'program', 'linkVal' => 'ms-word:ofe|u|https://docs.example.nl/sjabloon.docx']
		);

		$this->assertSame('program', $placement->getTileLinkType());
		$this->assertSame(1, $this->writes);
	}//end testAProgramTileOnAnAllowedSchemeIsCreated()

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function refusedProgramAddresses(): array {
		return [
			'script' => ['javascript:alert(1)'],
			'script in capitals' => ['JavaScript:alert(1)'],
			'data' => ['data:text/html,<script>alert(1)</script>'],
			'not allowed by the administrator' => ['ms-excel:ofe|u|https://x.nl/a.xlsx'],
			'no scheme' => ['word'],
		];
	}//end refusedProgramAddresses()

	/**
	 * @dataProvider refusedProgramAddresses
	 */
	public function testAProgramTileOutsideTheAllowedSchemesIsRefusedOnCreate(string $address): void {
		try {
			$this->service()->addTileFromArray(dashboardId: 3, tileData: ['linkType' => 'program', 'linkVal' => $address]);
			$this->fail('accepted ' . $address);
		} catch (InvalidArgumentException) {
			$this->assertSame(0, $this->writes);
		}
	}//end testAProgramTileOutsideTheAllowedSchemesIsRefusedOnCreate()

	public function testAnAllowedSchemeListedForbiddenByHandStillRefusesAScript(): void {
		$this->settings['tile_allowed_schemes'] = ['javascript'];

		$this->expectException(InvalidArgumentException::class);
		$this->service()->updatePlacement(placementId: 5, data: ['tileLinkType' => 'program', 'tileLinkValue' => 'javascript:alert(1)']);
	}//end testAnAllowedSchemeListedForbiddenByHandStillRefusesAScript()

	public function testAWebTileWithAScriptAddressIsRefused(): void {
		try {
			$this->service()->updatePlacement(placementId: 5, data: ['tileLinkValue' => ' javascript:alert(1)']);
			$this->fail('accepted a script address on a web tile');
		} catch (InvalidArgumentException) {
			$this->assertSame(0, $this->writes);
		}
	}//end testAWebTileWithAScriptAddressIsRefused()

	public function testAnUnknownLinkTypeIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service()->addTileFromArray(dashboardId: 3, tileData: ['linkType' => 'shell', 'linkVal' => 'rm -rf /']);
	}//end testAnUnknownLinkTypeIsRefused()

	public function testExistingAppAndWebTilesStillSave(): void {
		$service = $this->service();
		$service->addTileFromArray(dashboardId: 3, tileData: ['linkType' => 'app', 'linkVal' => 'files']);
		$service->addTileFromArray(dashboardId: 3, tileData: []);
		$service->updatePlacement(placementId: 5, data: ['tileLinkValue' => 'mailto:servicedesk@gemeente.nl']);
		$service->updatePlacement(placementId: 5, data: ['gridX' => 4]);

		$this->assertSame(4, $this->writes);
	}//end testExistingAppAndWebTilesStillSave()

	public function testAnRdpTileStoresItsConnection(): void {
		$remote = ['mode' => 'rdp', 'host' => 'rds01.gemeente.local', 'port' => 3389, 'remoteApp' => '||Belastingen', 'gateway' => 'rdgw.gemeente.nl'];
		$placement = $this->service()->updatePlacement(
			placementId: 5,
			data: ['tileLinkType' => 'remote-desktop', 'tileLinkValue' => '', 'content' => ['remote' => $remote]]
		);

		$this->assertSame('remote-desktop', $placement->getTileLinkType());
		$this->assertSame($remote, $placement->getContentArray()['remote']);
		$this->assertSame(1, $this->writes);
	}//end testAnRdpTileStoresItsConnection()

	public function testAGatewayTileStoresItsAddress(): void {
		$remote = ['mode' => 'gateway', 'url' => 'https://desktop.gemeente.nl/guacamole/#/client/werkplek'];
		$placement = $this->service()->updatePlacement(placementId: 5, data: ['tileLinkType' => 'remote-desktop', 'content' => ['remote' => $remote]]);

		$this->assertSame($remote, $placement->getContentArray()['remote']);
	}//end testAGatewayTileStoresItsAddress()

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function refusedRemotes(): array {
		return [
			'missing' => [null],
			'unknown mode' => [['mode' => 'vnc', 'host' => 'a.nl']],
			'gateway over http' => [['mode' => 'gateway', 'url' => 'http://desktop.gemeente.nl/']],
			'gateway script' => [['mode' => 'gateway', 'url' => 'javascript:alert(1)']],
			'host with a newline' => [['mode' => 'rdp', 'host' => "rds01\nusername:s:admin"]],
			'host with a space' => [['mode' => 'rdp', 'host' => 'rds 01']],
			'port out of range' => [['mode' => 'rdp', 'host' => 'rds01', 'port' => 70000]],
			'port as words' => [['mode' => 'rdp', 'host' => 'rds01', 'port' => 'drie']],
			'program with a newline' => [['mode' => 'rdp', 'host' => 'rds01', 'remoteApp' => "||Belastingen\r\npassword 51:b:00"]],
			'gateway host with a path' => [['mode' => 'rdp', 'host' => 'rds01', 'gateway' => 'rdgw.nl/evil']],
		];
	}//end refusedRemotes()

	/**
	 * @dataProvider refusedRemotes
	 */
	public function testAnInvalidRemoteDesktopIsRefused(mixed $remote): void {
		try {
			$this->service()->updatePlacement(placementId: 5, data: ['tileLinkType' => 'remote-desktop', 'content' => ['remote' => $remote]]);
			$this->fail('accepted an invalid remote desktop');
		} catch (InvalidArgumentException) {
			$this->assertSame(0, $this->writes);
		}
	}//end testAnInvalidRemoteDesktopIsRefused()

	public function testASingleSignOnTileOnAKnownTemplateIsStored(): void {
		$placement = $this->service()->updatePlacement(
			placementId: 5,
			data: ['tileLinkType' => 'sso', 'tileLinkValue' => '', 'content' => ['sso' => ['template' => 'entra', 'appId' => 'a1b2c3']]]
		);

		$this->assertSame(['template' => 'entra', 'appId' => 'a1b2c3'], $placement->getContentArray()['sso']);
	}//end testASingleSignOnTileOnAKnownTemplateIsStored()

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function refusedSso(): array {
		return [
			'missing' => [null],
			'unknown template' => [['template' => 'okta', 'appId' => 'a1b2c3']],
			'app id with a slash' => [['template' => 'entra', 'appId' => '../admin']],
			'app id too long' => [['template' => 'entra', 'appId' => str_repeat('a', 129)]],
			'empty app id' => [['template' => 'entra', 'appId' => '']],
		];
	}//end refusedSso()

	/**
	 * @dataProvider refusedSso
	 */
	public function testAnInvalidSingleSignOnTileIsRefused(mixed $sso): void {
		try {
			$this->service()->updatePlacement(placementId: 5, data: ['tileLinkType' => 'sso', 'content' => ['sso' => $sso]]);
			$this->fail('accepted an invalid single sign-on tile');
		} catch (InvalidArgumentException) {
			$this->assertSame(0, $this->writes);
		}
	}//end testAnInvalidSingleSignOnTileIsRefused()

	public function testASingleSignOnTileCreatedEmptyWaitsForItsContent(): void {
		$placement = $this->service()->addTileFromArray(dashboardId: 3, tileData: ['linkType' => 'sso', 'linkVal' => '']);

		$this->assertSame('sso', $placement->getTileLinkType());
		$this->assertSame(1, $this->writes);
	}//end testASingleSignOnTileCreatedEmptyWaitsForItsContent()
}//end class
