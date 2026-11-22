<?php

/**
 * DemoSeedContent
 *
 * The organisation-wide content of a demo seed: the app catalogue, the
 * announcements and the allowed sites. Entries are matched by name or UUID
 * and brought to the seeded values, so a re-run adds nothing.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;

/**
 * Seed the app catalogue, announcements and allowed sites.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedContent {
	/**
	 * Constructor.
	 *
	 * @param CatalogueService   $catalogue     The app catalogue.
	 * @param AnnouncementMapper $announcements Announcements and banners.
	 * @param IAppConfig         $appConfig     The allowed-site lists.
	 */
	public function __construct(
		private readonly CatalogueService $catalogue,
		private readonly AnnouncementMapper $announcements,
		private readonly IAppConfig $appConfig,
	) {
	}//end __construct()

	/**
	 * Add the seeded hosts to each JSON host list in app config, keeping
	 * the hosts an administrator added.
	 *
	 * @param array<string, mixed> $values Config key to list of hosts.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function mergeAppConfig(array $values): void {
		foreach ($values as $key => $hosts) {
			$current = json_decode(
				json: $this->appConfig->getValueString(Application::APP_ID, (string)$key, '[]'),
				associative: true
			);
			if (is_array($current) === false) {
				$current = [];
			}

			$merged = array_values(
				array: array_unique(array: [...array_map(callback: 'strval', array: $current), ...array_map(callback: 'strval', array: (array)$hosts)])
			);
			$this->appConfig->setValueString(Application::APP_ID, (string)$key, (string)json_encode(value: $merged));
		}
	}//end mergeAppConfig()

	/**
	 * Add or update one catalogue entry per seeded tile, matched by name.
	 *
	 * @param array<string, array<string, mixed>> $tiles The seed's tile catalogue.
	 *
	 * @return array{created: int, updated: int} What this run changed.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function seedCatalogue(array $tiles): array {
		$existing = [];
		foreach ($this->catalogue->listAll() as $app) {
			$existing[(string)$app->getName()] = (string)$app->getUuid();
		}

		$counts   = ['created' => 0, 'updated' => 0];
		$position = 0;
		foreach ($tiles as $tile) {
			$input = $this->catalogueInput(tile: $tile, position: $position++);
			if (isset($existing[$input['name']]) === true) {
				$this->catalogue->update(uuid: $existing[$input['name']], input: $input);
				$counts['updated']++;
				continue;
			}

			$this->catalogue->create(input: $input);
			$counts['created']++;
		}

		return $counts;
	}//end seedCatalogue()

	/**
	 * The catalogue input of a seeded tile: a Nextcloud app link when the
	 * tile names an app, its web address otherwise.
	 *
	 * @param array<string, mixed> $tile     The tile.
	 * @param integer              $position Its sort order.
	 *
	 * @return array<string, mixed> The catalogue input.
	 */
	private function catalogueInput(array $tile, int $position): array {
		$appId    = (string)($tile['appId'] ?? '');
		$linkType = 'url';
		$link     = (string)($tile['url'] ?? '');
		if ($appId !== '') {
			$linkType = 'app';
			$link     = $appId;
		}

		return [
			'name'         => (string)$tile['name'],
			'description'  => (string)($tile['description'] ?? ''),
			'icon'         => 'icon-category-office',
			'iconType'     => 'class',
			'linkType'     => $linkType,
			'linkValue'    => $link,
			'targetGroups' => [],
			'sortOrder'    => $position,
		];
	}//end catalogueInput()

	/**
	 * Add or update the seeded announcements, matched by UUID.
	 *
	 * @param array<int, array<string, mixed>> $entries The seed announcements.
	 *
	 * @return integer The number of announcements written.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function seedAnnouncements(array $entries): int {
		$now = new DateTime();
		foreach ($entries as $entry) {
			try {
				$announcement = $this->announcements->findByUuid(uuid: (string)$entry['uuid']);
				$this->announcements->update(entity: $this->applyAnnouncement(announcement: $announcement, entry: $entry));
			} catch (DoesNotExistException) {
				$announcement = $this->applyAnnouncement(announcement: new Announcement(), entry: $entry);
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				$announcement->setCreatedAt($now);
				$this->announcements->insert(entity: $announcement);
			}
		}

		return count($entries);
	}//end seedAnnouncements()

	/**
	 * Write a seeded announcement's fields, published from now without expiry.
	 *
	 * @param Announcement         $announcement The announcement.
	 * @param array<string, mixed> $entry        The seed announcement.
	 *
	 * @return Announcement The announcement.
	 */
	private function applyAnnouncement(Announcement $announcement, array $entry): Announcement {
		$now = new DateTime();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$announcement->setUuid((string)$entry['uuid']);
		$announcement->setKind((string)($entry['kind'] ?? Announcement::KIND_NEWS));
		$announcement->setTitle((string)$entry['title']);
		$announcement->setBody((string)($entry['body'] ?? ''));
		$announcement->setCategory((string)($entry['category'] ?? ''));
		$announcement->setLevel((string)($entry['level'] ?? 'info'));
		$announcement->setDismissible((int)($entry['dismissible'] ?? 1));
		$announcement->setTargetGroups((string)json_encode(value: array_values(array: (array)($entry['targetGroups'] ?? []))));
		$announcement->setStatus(Announcement::STATUS_PUBLISHED);
		$announcement->setPublishAt($now);
		$announcement->setExpiresAt(null);
		$announcement->setAllowComments(0);
		$announcement->setAuthorId((string)($entry['author'] ?? 'admin'));
		$announcement->setUpdatedAt($now);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $announcement;
	}//end applyAnnouncement()
}//end class
