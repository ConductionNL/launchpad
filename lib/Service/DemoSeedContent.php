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
		private readonly ?ProfileFieldService $profileFields = null,
	) {
	}//end __construct()

	/**
	 * Give the seed's new colleagues a start date (REQ-PPL-014): the field the
	 * administrator marked as the start date, or a new self-filled date field
	 * marked so when there is none. Dates are days from today, so the demo's
	 * "New colleagues" stays current whenever it is loaded.
	 *
	 * @param array<string, mixed> $entry `{field: {key, label}, offsets: {uid: days}}`.
	 * @param string               $today Today, `YYYY-MM-DD`.
	 *
	 * @return array<string, string> The start date written per person.
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 */
	public function seedStartDates(array $entry, string $today): array {
		$offsets = (array)($entry['offsets'] ?? []);
		if ($this->profileFields === null || $offsets === []) {
			return [];
		}

		$key = $this->startDateKey(field: (array)($entry['field'] ?? []));
		$base = strtotime(datetime: $today.' 12:00:00 UTC');
		$written = [];
		foreach ($offsets as $userId => $days) {
			$date = gmdate(format: 'Y-m-d', timestamp: $base + ((int)$days * 86400));
			$this->profileFields->saveOwnValues(userId: (string)$userId, values: [$key => $date]);
			$written[(string)$userId] = $date;
		}

		return $written;
	}//end seedStartDates()

	/**
	 * The key of the start-date field, adding the seed's field when none is marked.
	 *
	 * @param array<string, mixed> $field `{key, label}` of the seed's field.
	 *
	 * @return string The key.
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 */
	private function startDateKey(array $field): string {
		$definitions = $this->profileFields->getDefinitions();
		foreach ($definitions as $definition) {
			if (($definition['startDate'] ?? false) === true) {
				return (string)$definition['key'];
			}
		}

		$key = (string)($field['key'] ?? 'start_date');
		$definitions[] = [
			'key' => $key,
			'label' => (string)($field['label'] ?? 'Start date'),
			'type' => 'date',
			'source' => 'self',
			'searchable' => false,
			'shownInWidget' => false,
			'startDate' => true,
		];
		$this->profileFields->saveDefinitions(raw: $definitions);

		return $key;
	}//end startDateKey()

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
	 * Add or update one catalogue entry per seeded tile, matched by name, or
	 * by the app or address it opens (so a seed loaded in another language
	 * renames its entries instead of adding a second set).
	 *
	 * @param array<string, array<string, mixed>> $tiles The seed's tile catalogue.
	 *
	 * @return array{created: int, updated: int} What this run changed.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function seedCatalogue(array $tiles): array {
		$byName = [];
		$byLink = [];
		foreach ($this->catalogue->listAll() as $app) {
			$byName[(string)$app->getName()] = (string)$app->getUuid();
			$byLink[(string)$app->getLinkType() . ':' . (string)$app->getLinkValue()] ??= (string)$app->getUuid();
		}

		$counts   = ['created' => 0, 'updated' => 0];
		$position = 0;
		foreach ($tiles as $tile) {
			$input = $this->catalogueInput(tile: $tile, position: $position++);
			$uuid  = ($byName[$input['name']] ?? $byLink[$input['linkType'] . ':' . $input['linkValue']] ?? null);
			if ($uuid !== null) {
				$this->catalogue->update(uuid: $uuid, input: $input);
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
