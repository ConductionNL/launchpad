<?php

/**
 * DemoSeedService
 *
 * Loads the LaunchPad demo environment: the users, groups, app catalogue,
 * announcements, team folder and dashboards that reproduce every LaunchPad
 * board on identity.conduction.nl/screens. The seed is data, in
 * `data/demo-seed/<id>.json`; this service writes it. It extends the demo
 * showcases (same placement hydrator, same rule for which widget types can
 * render, same catalogue seed), but where a showcase is one dashboard for
 * everyone, the seed sets up a whole cast of people with their own and
 * shared dashboards.
 *
 * Idempotent: users, groups, catalogue entries and announcements are
 * matched by id, name or UUID and brought to the seeded values, and every
 * dashboard has a fixed UUID whose placements and shares are replaced.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCP\App\IAppManager;
use RuntimeException;

/**
 * Load the LaunchPad demo environment.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedService {
	/**
	 * The seeds that ship with LaunchPad.
	 *
	 * @var array<int, string>
	 */
	public const SEED_IDS = ['zuiddrecht-boards'];

	/**
	 * The password a demo user gets when the seed creates it. Demo only;
	 * documented in docs/features/demo-seed.md.
	 *
	 * @var string
	 */
	public const DEFAULT_PASSWORD = 'Zuiddrecht-demo-2026!';

	/**
	 * Constructor.
	 *
	 * @param DemoSeedParts        $parts      Accounts, dashboards, the first visit and the team folder.
	 * @param DemoSeedContent      $content    App catalogue, announcements and allowed sites.
	 * @param DemoShowcasesService $showcases  Which widget types render; the bundled showcases.
	 * @param IAppManager          $appManager Which apps are enabled.
	 * @param DemoSeedPublishing   $publishing Administrator settings, the public start page and kiosk playlists.
	 */
	public function __construct(
		private readonly DemoSeedParts $parts,
		private readonly DemoSeedContent $content,
		private readonly DemoShowcasesService $showcases,
		private readonly IAppManager $appManager,
		private readonly DemoSeedPublishing $publishing,
	) {
	}//end __construct()

	/**
	 * Read and decode a seed definition, in the requested language when the
	 * seed has a translation for it (`<seed>.<lang>.json`), as written otherwise.
	 *
	 * @param string $seedId   The seed id.
	 * @param string $language The language, such as `en` or `en_GB`; '' for the seed as written.
	 *
	 * @return array<string, mixed> The definition.
	 *
	 * @throws InvalidArgumentException When the seed is unknown or unreadable.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function definition(string $seedId, string $language = ''): array {
		if (in_array(needle: $seedId, haystack: self::SEED_IDS, strict: true) === false) {
			throw new InvalidArgumentException(
				message: 'Unknown demo seed ' . $seedId . '. Known: ' . implode(separator: ', ', array: self::SEED_IDS)
			);
		}

		$decoded = self::readJson(path: self::seedDirectory() . $seedId . '.json');
		if ($decoded === null) {
			throw new InvalidArgumentException(message: 'Demo seed ' . $seedId . ' is missing or not valid JSON');
		}

		$translation = $this->translation(seedId: $seedId, language: $language);
		return (new DemoSeedTranslation())->apply(seed: $decoded, strings: $translation['strings']);
	}//end definition()

	/**
	 * The translation of a seed for a language: the most specific translation
	 * file that exists, or none.
	 *
	 * @param string $seedId   The seed id.
	 * @param string $language The requested language, '' for none.
	 *
	 * @return array{language: string, strings: array<string, string>} The language applied ('' for none) and its texts.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function translation(string $seedId, string $language): array {
		foreach ((new DemoSeedTranslation())->candidates(language: $language) as $candidate) {
			$decoded = self::readJson(path: self::seedDirectory() . $seedId . '.' . $candidate . '.json');
			if ($decoded !== null && is_array($decoded['strings'] ?? null) === true) {
				return [
					'language' => $candidate,
					'strings'  => array_map(callback: 'strval', array: $decoded['strings']),
				];
			}
		}

		return ['language' => '', 'strings' => []];
	}//end translation()

	/**
	 * The directory that holds the seed files, with a trailing slash.
	 *
	 * @return string The directory.
	 */
	private static function seedDirectory(): string {
		return dirname(path: __DIR__, levels: 2) . '/data/demo-seed/';
	}//end seedDirectory()

	/**
	 * Decode a JSON file into an array.
	 *
	 * @param string $path The file.
	 *
	 * @return array<string, mixed>|null The decoded array, null when missing or not a JSON object.
	 */
	private static function readJson(string $path): array|null {
		if (is_readable(filename: $path) === false) {
			return null;
		}

		$decoded = json_decode(json: (string)file_get_contents(filename: $path), associative: true);
		if (is_array($decoded) === false) {
			return null;
		}

		return $decoded;
	}//end readJson()

	/**
	 * Load a seed.
	 *
	 * @param string $seedId   The seed id.
	 * @param string $password The password for users this run creates.
	 * @param string $language The language of the texts, '' for the seed as written.
	 *
	 * @return array<string, mixed> What the run did: the language applied,
	 *                              created users and groups, and per dashboard
	 *                              its UUID, placed widgets, skipped widget
	 *                              types and fallbacks used.
	 *
	 * @throws InvalidArgumentException When the seed is unknown.
	 * @throws RuntimeException         When a user or dashboard cannot be written.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function load(string $seedId, string $password = self::DEFAULT_PASSWORD, string $language = ''): array {
		$seed   = $this->definition(seedId: $seedId, language: $language);
		$report = $this->seedAccounts(seed: $seed, password: $password);

		$report['language'] = $this->translation(seedId: $seedId, language: $language)['language'];
		// REQ-PPL-014: the new colleagues' start dates, counted from today.
		$report['startDates'] = $this->parts->accounts->ensureStartDates(seed: $seed, today: gmdate(format: 'Y-m-d'));

		$this->content->mergeAppConfig(values: (array)($seed['appConfig'] ?? []));
		$report['catalogue']     = $this->content->seedCatalogue(tiles: (array)($seed['tiles'] ?? []));
		$report['announcements'] = $this->content->seedAnnouncements(entries: (array)($seed['announcements'] ?? []));
		$report['files']         = $this->parts->files->ensureSafely(definition: $seed['files'] ?? null);
		$report['dashboards']    = $this->seedDashboards(seed: $seed);
		$this->publishing->mergeAdminSettings(values: (array)($seed['adminSettings'] ?? []));
		$report['published'] = $this->publish(definition: (array)($seed['publishing'] ?? []));
		$report['firstRun']      = $this->resetFirstRunUsers(
			users: (array)($seed['users'] ?? []),
			tourVersion: (string)($seed['walkthroughSeenVersion'] ?? '')
		);

		return $report;
	}//end load()

	/**
	 * Publish the seed's public start page and kiosk playlists as its
	 * publisher, an administrator account of the seed.
	 *
	 * @param array<string, mixed> $definition `{publisher, startPage, enableStartPage, kioskPlaylists}`.
	 *
	 * @return array{startPage: string, kioskPlaylists: array<string, string>} The link and playlist tokens.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	private function publish(array $definition): array {
		$publisher = (string)($definition['publisher'] ?? '');
		$published = ['startPage' => '', 'kioskPlaylists' => []];
		if ($publisher === '') {
			return $published;
		}

		$startPage = (string)($definition['startPage'] ?? '');
		if ($startPage !== '') {
			$published['startPage'] = $this->publishing->publishStartPage(dashboardUuid: $startPage, publisher: $publisher);
			if (($definition['enableStartPage'] ?? false) === true) {
				$this->publishing->enableStartPage();
			}
		}

		foreach ((array)($definition['kioskPlaylists'] ?? []) as $playlist) {
			$name = (string)($playlist['name'] ?? '');
			$published['kioskPlaylists'][$name] = $this->publishing->ensurePlaylist(playlist: (array)$playlist, publisher: $publisher);
		}

		return $published;
	}//end publish()

	/**
	 * Ensure the seed's groups and users.
	 *
	 * @param array<string, mixed> $seed     The seed.
	 * @param string               $password The password for users this run creates.
	 *
	 * @return array{createdGroups: array<int, string>, createdUsers: array<int, string>} What this run created.
	 */
	private function seedAccounts(array $seed, string $password): array {
		return [
			'createdGroups' => $this->parts->accounts->ensureGroups(groups: (array)($seed['groups'] ?? [])),
			'createdUsers'  => $this->parts->accounts->ensureUsers(seed: $seed, password: $password),
		];
	}//end seedAccounts()

	/**
	 * Uninstall every installed bundled showcase. Showcases are visible to
	 * everyone, so while one is installed no user ever sees the empty state
	 * of a first visit.
	 *
	 * @return array<int, string> The ids of the showcases uninstalled.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function retireShowcases(): array {
		$retired = [];
		foreach (DemoShowcasesService::BUNDLED_IDS as $showcaseId) {
			if ($this->showcases->getInstalledUuid(showcaseId: $showcaseId) === '') {
				continue;
			}

			$this->showcases->uninstallShowcase(showcaseId: $showcaseId);
			$retired[] = $showcaseId;
		}

		return $retired;
	}//end retireShowcases()

	/**
	 * Write every seeded dashboard.
	 *
	 * @param array<string, mixed> $seed The seed.
	 *
	 * @return array<int, array<string, mixed>> Per dashboard: key, board, name, uuid, created, placed, skipped, fallbacks.
	 */
	private function seedDashboards(array $seed): array {
		$expander = new DemoSeedWidgets(
			tiles: (array)($seed['tiles'] ?? []),
			knownTypes: $this->showcases->renderableWidgetTypes(),
			enabledApps: array_fill_keys(keys: $this->appManager->getEnabledApps(), value: true)
		);

		$result = [];
		foreach ((array)($seed['dashboards'] ?? []) as $definition) {
			$expanded = $expander->expand(entries: (array)($definition['widgets'] ?? []));
			$written  = $this->parts->dashboards->write(definition: $definition, payloads: $expanded['placed']);
			$result[] = [
				'key'       => (string)$definition['key'],
				'board'     => (string)($definition['board'] ?? ''),
				'name'      => (string)$definition['name'],
				...$written,
				'placed'    => count($expanded['placed']),
				'skipped'   => $expanded['skipped'],
				'fallbacks' => $expanded['fallbacks'],
			];
		}

		return $result;
	}//end seedDashboards()

	/**
	 * Reset every first-run user to a first visit, and mark the tour as seen
	 * for every other demo user.
	 *
	 * @param array<int, array<string, mixed>> $users       The seed users.
	 * @param string                           $tourVersion The tour version to mark as seen, '' for none.
	 *
	 * @return array<string, int> Per first-run user, the dashboards removed.
	 */
	private function resetFirstRunUsers(array $users, string $tourVersion): array {
		$removed = [];
		foreach ($users as $user) {
			$userId = (string)$user['id'];
			if (($user['firstRun'] ?? false) === true) {
				$removed[$userId] = $this->parts->firstRun->resetToFirstRun(userId: $userId);
				continue;
			}

			if ($tourVersion !== '') {
				$this->parts->firstRun->markTourSeen(userId: $userId, version: $tourVersion);
			}
		}

		return $removed;
	}//end resetFirstRunUsers()
}//end class
