<?php

/**
 * DemoSeedPublishingTest
 *
 * The demo seed publishes administrator settings, the public start page and
 * kiosk playlists, and a re-run keeps the same link and playlist token.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Service\DemoSeedPublishing;
use OCA\LaunchPad\Service\KioskPlaylistDraft;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareService;
use PHPUnit\Framework\TestCase;

/**
 * Checks what the seed publishes and that a re-run changes nothing.
 */
class DemoSeedPublishingTest extends TestCase {
	/**
	 * The settings written, by key.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	/**
	 * An administrator settings mapper that keeps values in memory.
	 *
	 * @return AdminSettingMapper
	 */
	private function settingsMapper(): AdminSettingMapper {
		$mapper = $this->createMock(originalClassName: AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value) {
			$this->settings[$key] = $value;
			return new \OCA\LaunchPad\Db\AdminSetting();
		});

		return $mapper;
	}//end settingsMapper()

	/**
	 * A list setting is merged with what an administrator keeps.
	 *
	 * @return void
	 */
	public function testListSettingsAreMerged(): void {
		$this->settings = ['announcement_editor_groups' => ['redactie']];
		$publishing     = new DemoSeedPublishing(
			settings: $this->settingsMapper(),
			shares: $this->createMock(originalClassName: PublicShareService::class),
			kiosk: $this->createMock(originalClassName: KioskService::class)
		);

		$publishing->mergeAdminSettings(values: ['announcement_editor_groups' => ['communicatie', 'redactie']]);

		$this->assertSame(expected: ['redactie', 'communicatie'], actual: $this->settings['announcement_editor_groups']);
	}//end testListSettingsAreMerged()

	/**
	 * The start page reuses an open link and is switched on.
	 *
	 * @return void
	 */
	public function testStartPageReusesAnOpenLink(): void {
		$open = new PublicShare();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$open->setToken('open-token');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$shares = $this->createMock(originalClassName: PublicShareService::class);
		$shares->method('listActiveShares')->willReturn([$open]);
		$shares->expects($this->never())->method('createPublicShare');

		$publishing = new DemoSeedPublishing(
			settings: $this->settingsMapper(),
			shares: $shares,
			kiosk: $this->createMock(originalClassName: KioskService::class)
		);

		$this->assertSame(expected: 'open-token', actual: $publishing->publishStartPage(dashboardUuid: 'd8', publisher: 'beheer'));
		$this->assertSame(expected: 'open-token', actual: $this->settings['public_start_share_token']);
		$this->assertTrue(condition: $this->settings['public_start_enabled']);
	}//end testStartPageReusesAnOpenLink()

	/**
	 * The start page creates a link when there is no open one.
	 *
	 * @return void
	 */
	public function testStartPageCreatesALinkWhenNoneIsOpen(): void {
		$created = new PublicShare();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$created->setToken('new-token');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$shares = $this->createMock(originalClassName: PublicShareService::class);
		$shares->method('listActiveShares')->willReturn([]);
		$shares->expects($this->once())->method('createPublicShare')->willReturn($created);

		$publishing = new DemoSeedPublishing(
			settings: $this->settingsMapper(),
			shares: $shares,
			kiosk: $this->createMock(originalClassName: KioskService::class)
		);

		$this->assertSame(expected: 'new-token', actual: $publishing->publishStartPage(dashboardUuid: 'd8', publisher: 'beheer'));
	}//end testStartPageCreatesALinkWhenNoneIsOpen()

	/**
	 * A playlist of the same name is updated in place and keeps its token.
	 *
	 * @return void
	 */
	public function testPlaylistOfTheSameNameIsUpdated(): void {
		$existing = new KioskPlaylist();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$existing->setId(4);
		$existing->setName('Kantine');
		$existing->setToken('kiosk-token');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$kiosk = $this->createMock(originalClassName: KioskService::class);
		$kiosk->method('listPlaylists')->willReturn([$existing]);
		$kiosk->expects($this->never())->method('createPlaylist');
		$kiosk->expects($this->once())->method('updatePlaylist')
			->with(4, $this->isInstanceOf(className: KioskPlaylistDraft::class), 'beheer')
			->willReturn($existing);

		$publishing = new DemoSeedPublishing(
			settings: $this->settingsMapper(),
			shares: $this->createMock(originalClassName: PublicShareService::class),
			kiosk: $kiosk
		);

		$token = $publishing->ensurePlaylist(
			playlist: ['name' => 'Kantine', 'entries' => [['dashboardUuid' => 'd9', 'dwellSeconds' => 30]]],
			publisher: 'beheer'
		);
		$this->assertSame(expected: 'kiosk-token', actual: $token);
	}//end testPlaylistOfTheSameNameIsUpdated()
}//end class
