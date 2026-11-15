<?php

/**
 * ServiceDiscoveryService
 *
 * The apps this Nextcloud runs for the current user, read from their own app
 * menu (`INavigationManager::getAll('link')`), so a person can turn one into
 * a tile in one click (REQ-DISC-001). The menu is already filtered by the
 * apps' enabled state and group restrictions, so discovery never shows an app
 * the user may not open. AppAPI external apps with a page are menu entries
 * too. Nothing lower is read: no Docker socket, no container labels, no
 * AppAPI deployment data (REQ-DISC-003).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCP\INavigationManager;

/**
 * Lists the apps in the current user's Nextcloud app menu.
 */
class ServiceDiscoveryService {
	/**
	 * Constructor
	 *
	 * @param INavigationManager $navigationManager The current user's app menu.
	 */
	public function __construct(
		private readonly INavigationManager $navigationManager,
	) {
	}//end __construct()

	/**
	 * The apps in the current user's app menu, LaunchPad itself left out.
	 *
	 * @return array<int, array{appId: string, name: string, href: string, icon: string}> The apps, in menu order.
	 *
	 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md#requirement-the-add-flow-lists-the-apps-this-server-runs-for-the-user-req-disc-001
	 */
	public function discover(): array {
		$apps = [];
		foreach ($this->navigationManager->getAll(type: 'link') as $entry) {
			$appId = (string)($entry['id'] ?? '');
			if ($appId === '' || $appId === Application::APP_ID) {
				continue;
			}

			$apps[] = [
				'appId' => $appId,
				'name'  => (string)($entry['name'] ?? $appId),
				'href'  => (string)($entry['href'] ?? ''),
				'icon'  => (string)($entry['icon'] ?? ''),
			];
		}

		return $apps;
	}//end discover()
}//end class
