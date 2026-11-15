<?php

/**
 * ServiceDiscoveryServiceTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\ServiceDiscoveryService;
use OCP\INavigationManager;
use PHPUnit\Framework\TestCase;

/**
 * The apps this server runs for the user, read from their own app menu.
 */
class ServiceDiscoveryServiceTest extends TestCase {
	/**
	 * Build the service over a navigation manager answering $entries.
	 *
	 * @param array<int|string, array<string, mixed>> $entries The app menu entries.
	 *
	 * @return ServiceDiscoveryService
	 */
	private function service(array $entries): ServiceDiscoveryService {
		$navigation = $this->createMock(INavigationManager::class);
		$navigation->expects($this->once())
			->method('getAll')
			->with('link')
			->willReturn($entries);

		return new ServiceDiscoveryService(navigationManager: $navigation);
	}//end service()

	/**
	 * Every app in the user's menu except LaunchPad, with name, address and icon.
	 *
	 * @return void
	 */
	public function testListsTheMenuAppsWithoutLaunchPad(): void {
		$apps = $this->service(entries: [
			'files'     => ['id' => 'files', 'app' => 'files', 'name' => 'Files', 'href' => '/apps/files/', 'icon' => '/apps/files/img/app.svg'],
			'launchpad' => ['id' => 'launchpad', 'app' => 'launchpad', 'name' => 'LaunchPad', 'href' => '/apps/launchpad/', 'icon' => '/apps/launchpad/img/app.svg'],
			'deck'      => ['id' => 'deck', 'app' => 'deck', 'name' => 'Deck', 'href' => '/apps/deck/', 'icon' => '/apps/deck/img/deck.svg'],
		])->discover();

		$this->assertSame(
			[
				['appId' => 'files', 'name' => 'Files', 'href' => '/apps/files/', 'icon' => '/apps/files/img/app.svg'],
				['appId' => 'deck', 'name' => 'Deck', 'href' => '/apps/deck/', 'icon' => '/apps/deck/img/deck.svg'],
			],
			$apps
		);
	}//end testListsTheMenuAppsWithoutLaunchPad()

	/**
	 * An AppAPI external app with a page is in the menu, so it is discovered.
	 *
	 * @return void
	 */
	public function testKeepsAnExternalApp(): void {
		$apps = $this->service(entries: [
			['id' => 'context_chat_ui', 'app' => 'app_api', 'name' => 'Context Chat', 'href' => '/apps/app_api/embedded/context_chat_ui/', 'icon' => '/apps/app_api/img/app.svg'],
		])->discover();

		$this->assertSame('context_chat_ui', $apps[0]['appId']);
		$this->assertSame('/apps/app_api/embedded/context_chat_ui/', $apps[0]['href']);
	}//end testKeepsAnExternalApp()

	/**
	 * A user whose menu holds nothing discovers nothing.
	 *
	 * @return void
	 */
	public function testEmptyMenuDiscoversNothing(): void {
		$this->assertSame([], $this->service(entries: [])->discover());
	}//end testEmptyMenuDiscoversNothing()
}//end class
