<?php

/**
 * DemoSeedServiceTest
 *
 * Loads the shipped seed twice against in-memory stores and checks it
 * creates the expected dashboards once and leaves the same state the
 * second time.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
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

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCA\LaunchPad\Db\CatalogueApp;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\CatalogueService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\DemoSeedAccounts;
use OCA\LaunchPad\Service\DemoSeedDashboards;
use OCA\LaunchPad\Service\DemoSeedContent;
use OCA\LaunchPad\Service\DemoSeedFiles;
use OCA\LaunchPad\Service\DemoSeedFirstRun;
use OCA\LaunchPad\Service\DemoSeedParts;
use OCA\LaunchPad\Service\DemoSeedPreferences;
use OCA\LaunchPad\Service\DemoSeedPublishing;
use OCA\LaunchPad\Service\DemoSeedService;
use OCA\LaunchPad\Service\DemoSeedStartDates;
use OCA\LaunchPad\Service\DemoShowcasesService;
use OCA\LaunchPad\Service\ShowcasePlatform;
use OCA\LaunchPad\Service\ShowcaseStores;
use OCA\LaunchPad\Support\WidgetTypeList;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Dashboard\IManager;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;

/**
 * The demo seed is idempotent and creates the board dashboards.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DemoSeedServiceTest extends TestCase {
	/** @var array<int, Dashboard> Dashboards by id. */
	private array $dashboards = [];

	/** @var array<int, WidgetPlacement> Placements by id. */
	private array $placements = [];

	/** @var array<int, DashboardShare> Shares by id. */
	private array $shares = [];

	/** @var array<string, string> User preferences, `user/key` => value. */
	private array $prefs = [];

	/** @var array<string, string> App config. */
	private array $appValues = [];

	/** @var array<string, CatalogueApp> Catalogue entries by name. */
	private array $catalogue = [];

	/** @var array<string, Announcement> Announcement rows by UUID. */
	private array $announcementRows = [];

	/** @var integer The next fake database id. */
	private int $nextId = 1;

	/** @var DemoSeedService The service under test. */
	private DemoSeedService $service;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$stores = new ShowcaseStores(
			dashboardMapper: $this->dashboardMapper(),
			placementMapper: $this->placementMapper(),
			db: $this->createMock(originalClassName: IDBConnection::class),
			lockingProvider: $this->createMock(originalClassName: ILockingProvider::class)
		);
		$preferences = new DemoSeedPreferences(config: $this->config());

		$this->service = new DemoSeedService(
			parts: new DemoSeedParts(
				accounts: $this->createMock(originalClassName: DemoSeedAccounts::class),
				dashboards: new DemoSeedDashboards(
					stores: $stores,
					shares: $this->shareService(),
					preferences: $preferences
				),
				firstRun: new DemoSeedFirstRun(
					stores: $stores,
					preferences: $preferences,
					platform: new ShowcasePlatform(
						appConfig: $this->createMock(originalClassName: IAppConfig::class),
						dashboardManager: $this->createMock(originalClassName: IManager::class),
						urlGenerator: $this->createMock(originalClassName: IURLGenerator::class)
					)
				),
				files: $this->createMock(originalClassName: DemoSeedFiles::class),
				startDates: $this->createMock(originalClassName: DemoSeedStartDates::class)
			),
			content: new DemoSeedContent(
				catalogue: $this->catalogue(),
				announcements: $this->announcements(),
				appConfig: $this->appConfig()
			),
			showcases: $this->showcases(),
			appManager: $this->appManager(),
			publishing: $this->createMock(originalClassName: DemoSeedPublishing::class)
		);
	}//end setUp()

	/**
	 * First run creates the board dashboards.
	 *
	 * @return void
	 */
	public function testFirstRunCreatesTheBoardDashboards(): void {
		$report = $this->service->load(seedId: 'zuiddrecht-boards');

		$this->assertSame(
			expected: [
				'Vandaag', 'Mijn werkplek', 'Project Havenpark', 'Havenpark: planning',
				'Intranet Zuiddrecht', 'Team Vergunningen', 'Managementinformatie', 'Openbare startpagina', 'Vandaag in Zuiddrecht',
			],
			actual: array_column(array: $report['dashboards'], column_key: 'name')
		);
		$created = array_column(array: $report['dashboards'], column_key: 'created');
		$this->assertSame(expected: array_fill(start_index: 0, count: 9, value: true), actual: $created);
		$this->assertCount(expectedCount: 9, haystack: $this->dashboards);

		$byName = $this->dashboardsByName();
		$this->assertSame(expected: 'pieter', actual: $byName['Vandaag']->getUserId());
		$this->assertSame(expected: 'medewerkers', actual: $byName['Intranet Zuiddrecht']->getGroupId());
		$this->assertSame(expected: Dashboard::TYPE_GROUP_SHARED, actual: $byName['Managementinformatie']->getType());
		$this->assertSame(expected: 'view_only', actual: $byName['Managementinformatie']->getPermissionLevel());
		$this->assertSame(expected: 'marieke', actual: $byName['Team Vergunningen']->getUserId());
		$this->assertSame(
			expected: (string)$byName['Vandaag']->getUuid(),
			actual: $this->prefs['pieter/' . DemoSeedPreferences::DEFAULT_DASHBOARD_PREF_KEY]
		);

		$teamShares = array_values(array: array_filter(
			array: $this->shares,
			callback: fn (DashboardShare $s): bool => $s->getDashboardId() === $byName['Team Vergunningen']->getId()
		));
		$this->assertCount(expectedCount: 1, haystack: $teamShares);
		$share = $teamShares[0];
		$this->assertSame(
			expected: ['group', 'vergunningen', 'add_only'],
			actual: [$share->getShareType(), $share->getShareWith(), $share->getPermissionLevel()]
		);

		$this->assertSame(expected: '2', actual: $this->prefs['pieter/' . DemoSeedPreferences::WALKTHROUGH_PREF_KEY]);
		$this->assertArrayNotHasKey(key: 'nieuw/' . DemoSeedPreferences::WALKTHROUGH_PREF_KEY, array: $this->prefs);
		$this->assertCount(expectedCount: 13, haystack: $this->catalogue);
		$this->assertContains(needle: 'www.zuiddrecht.nl', haystack: json_decode(json: $this->appValues['iframe_allowed_hosts'], associative: true));
	}//end testFirstRunCreatesTheBoardDashboards()

	/**
	 * Second run leaves the same state.
	 *
	 * @return void
	 */
	public function testSecondRunLeavesTheSameState(): void {
		$this->service->load(seedId: 'zuiddrecht-boards');
		$first = $this->snapshot();

		$report = $this->service->load(seedId: 'zuiddrecht-boards');

		$created = array_column(array: $report['dashboards'], column_key: 'created');
		$this->assertSame(expected: array_fill(start_index: 0, count: 9, value: false), actual: $created);
		$this->assertSame(expected: $first, actual: $this->snapshot());
		$this->assertSame(expected: ['created' => 0, 'updated' => 13], actual: $report['catalogue']);
	}//end testSecondRunLeavesTheSameState()

	/**
	 * A seed switches the public start page on only when it asks to.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function testStartPageIsSwitchedOnOnlyWhenTheSeedAsks(): void {
		foreach ([true => 1, false => 0] as $asks => $calls) {
			$publishing = $this->createMock(originalClassName: DemoSeedPublishing::class);
			$publishing->expects($this->once())->method('publishStartPage')->willReturn('token');
			$publishing->expects($this->exactly(count: $calls))->method('enableStartPage');

			$service = new DemoSeedService(
				parts: $this->createMock(originalClassName: DemoSeedParts::class),
				content: $this->createMock(originalClassName: DemoSeedContent::class),
				showcases: $this->createMock(originalClassName: DemoShowcasesService::class),
				appManager: $this->createMock(originalClassName: IAppManager::class),
				publishing: $publishing
			);
			$publish = new \ReflectionMethod(objectOrMethod: $service, method: 'publish');
			$result  = $publish->invoke($service, ['publisher' => 'beheer', 'startPage' => 'd8', 'enableStartPage' => (bool)$asks]);

			$this->assertSame(expected: 'token', actual: $result['startPage']);
		}
	}//end testStartPageIsSwitchedOnOnlyWhenTheSeedAsks()

	/**
	 * Unregistered widgets are skipped or fall back.
	 *
	 * @return void
	 */
	public function testUnregisteredWidgetsAreSkippedOrFallBack(): void {
		$report = $this->service->load(seedId: 'zuiddrecht-boards');
		$werkplek = $report['dashboards'][1];

		$this->assertSame(expected: [], actual: $werkplek['fallbacks']);

		$types = array_map(callback: fn (WidgetPlacement $p): string => (string)$p->getWidgetId(), array: $this->placements);
		$this->assertContains(needle: 'wiki-pages', haystack: $types);
		$this->assertContains(needle: 'assigned-cases', haystack: $types);
		$this->assertContains(needle: 'app-tile', haystack: $types);
		$this->assertContains(needle: 'tile', haystack: $types);
	}//end testUnregisteredWidgetsAreSkippedOrFallBack()

	/**
	 * Loading in English after Dutch renames the dashboards, the catalogue
	 * and the announcements in place, and keeps identifiers and data.
	 *
	 * @return void
	 */
	public function testEnglishRunTranslatesInPlace(): void {
		$this->service->load(seedId: 'zuiddrecht-boards');
		$report = $this->service->load(seedId: 'zuiddrecht-boards', language: 'en_GB');

		$this->assertSame(expected: 'en', actual: $report['language']);
		$this->assertSame(
			expected: [
				'Today', 'My workspace', 'Project Havenpark', 'Havenpark: planning', 'Zuiddrecht intranet', 'Permits team',
				'Management information', 'Public start page', 'Today in Zuiddrecht',
			],
			actual: array_column(array: $report['dashboards'], column_key: 'name')
		);
		$created = array_column(array: $report['dashboards'], column_key: 'created');
		$this->assertSame(expected: array_fill(start_index: 0, count: 9, value: false), actual: $created);
		$this->assertCount(expectedCount: 9, haystack: $this->dashboards);

		$names = array_map(callback: fn (CatalogueApp $app): string => (string)$app->getName(), array: array_values(array: $this->catalogue));
		$this->assertCount(expectedCount: 13, haystack: $names);
		$this->assertContains(needle: 'Case system', haystack: $names);
		$this->assertNotContains(needle: 'Zaaksysteem', haystack: $names);

		$titles = array_map(callback: fn (Announcement $row): string => (string)$row->getTitle(), array: array_values(array: $this->announcementRows));
		$this->assertContains(needle: 'Case system maintenance on Saturday, 08:00 to 12:00', haystack: $titles);

		$contents = array_map(callback: fn (WidgetPlacement $p): string => (string)$p->getContent(), array: $this->placements);
		$joined   = implode(separator: "\n", array: $contents);
		$this->assertStringContainsString(needle: '"title":"Today"', haystack: $joined);
		$this->assertStringContainsString(needle: '"assignee":"@me"', haystack: $joined);
		$this->assertStringContainsString(needle: '"statusRole":"in-progress"', haystack: $joined);
	}//end testEnglishRunTranslatesInPlace()

	/**
	 * A language without a translation loads the seed as written.
	 *
	 * @return void
	 */
	public function testUntranslatedLanguageLoadsTheSeedAsWritten(): void {
		$report = $this->service->load(seedId: 'zuiddrecht-boards', language: 'fr');

		$this->assertSame(expected: '', actual: $report['language']);
		$this->assertSame(expected: 'Vandaag', actual: $report['dashboards'][0]['name']);
	}//end testUntranslatedLanguageLoadsTheSeedAsWritten()

	/**
	 * Unknown seed is refused.
	 *
	 * @return void
	 */
	public function testUnknownSeedIsRefused(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->service->load(seedId: 'nope');
	}//end testUnknownSeedIsRefused()

	/**
	 * Every seeded row with its placements and shares, without database ids.
	 *
	 * @return array<string, mixed> The state.
	 */
	private function snapshot(): array {
		$state = [];
		foreach ($this->dashboards as $dashboard) {
			$id = $dashboard->getId();
			$placements = array_values(array: array_map(
				callback: fn (WidgetPlacement $p): array => [
					$p->getWidgetId(), $p->getGridX(), $p->getGridY(), $p->getGridWidth(),
					$p->getGridHeight(), $p->getSortOrder(), $p->getContent(), $p->getTileTitle(),
				],
				array: array_filter(array: $this->placements, callback: fn (WidgetPlacement $p): bool => $p->getDashboardId() === $id)
			));
			$shares = array_values(array: array_map(
				callback: fn (DashboardShare $s): array => [$s->getShareType(), $s->getShareWith(), $s->getPermissionLevel()],
				array: array_filter(array: $this->shares, callback: fn (DashboardShare $s): bool => $s->getDashboardId() === $id)
			));
			$state[(string)$dashboard->getUuid()] = [
				$dashboard->getName(), $dashboard->getType(), $dashboard->getUserId(), $dashboard->getGroupId(), $placements, $shares,
			];
		}

		ksort(array: $state);
		return [$state, $this->prefs, $this->appValues, array_keys(array: $this->catalogue)];
	}//end snapshot()

	/** @return array<string, Dashboard> */
	private function dashboardsByName(): array {
		$byName = [];
		foreach ($this->dashboards as $dashboard) {
			$byName[(string)$dashboard->getName()] = $dashboard;
		}

		return $byName;
	}//end dashboardsByName()

	/**
	 * Dashboard mapper.
	 *
	 * @return DashboardMapper
	 */
	private function dashboardMapper(): DashboardMapper {
		$mapper = $this->createMock(originalClassName: DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(function (string $uuid): Dashboard {
			foreach ($this->dashboards as $dashboard) {
				if ($dashboard->getUuid() === $uuid) {
					return $dashboard;
				}
			}

			throw new DoesNotExistException('no ' . $uuid);
		});
		$mapper->method('insert')->willReturnCallback(function (Dashboard $dashboard): Dashboard {
			$dashboard->setId($this->nextId++);
			$this->dashboards[$dashboard->getId()] = $dashboard;
			return $dashboard;
		});
		$mapper->method('update')->willReturnArgument(0);
		$mapper->method('findByUserId')->willReturnCallback(fn (string $userId): array => array_values(array: array_filter(
			array: $this->dashboards,
			callback: fn (Dashboard $d): bool => $d->getUserId() === $userId
		)));
		return $mapper;
	}//end dashboardMapper()

	/**
	 * Placement mapper.
	 *
	 * @return WidgetPlacementMapper
	 */
	private function placementMapper(): WidgetPlacementMapper {
		$mapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$mapper->method('insert')->willReturnCallback(function (WidgetPlacement $placement): WidgetPlacement {
			$placement->setId($this->nextId++);
			$this->placements[$placement->getId()] = $placement;
			return $placement;
		});
		$mapper->method('deleteByDashboardId')->willReturnCallback(function (int $dashboardId): void {
			$this->placements = array_filter(array: $this->placements, callback: fn (WidgetPlacement $p): bool => $p->getDashboardId() !== $dashboardId);
		});
		return $mapper;
	}//end placementMapper()

	/**
	 * Share service.
	 *
	 * @return DashboardShareService
	 */
	private function shareService(): DashboardShareService {
		$service = $this->createMock(originalClassName: DashboardShareService::class);
		$service->method('replaceShares')->willReturnCallback(function (int $dashboardId, array $shares, string $userId): array {
			$this->shares = array_filter(array: $this->shares, callback: fn (DashboardShare $s): bool => $s->getDashboardId() !== $dashboardId);
			foreach ($shares as $grant) {
				$share = new DashboardShare();
				$share->setId($this->nextId++);
				$share->setDashboardId($dashboardId);
				$share->setShareType($grant['shareType']);
				$share->setShareWith($grant['shareWith']);
				$share->setPermissionLevel($grant['permissionLevel']);
				$this->shares[$share->getId()] = $share;
			}

			return array_values(array: $this->shares);
		});
		return $service;
	}//end shareService()

	/**
	 * Config.
	 *
	 * @return IConfig
	 */
	private function config(): IConfig {
		$config = $this->createMock(originalClassName: IConfig::class);
		$config->method('setUserValue')->willReturnCallback(function (string $user, string $app, string $key, string $value): void {
			$this->prefs[$user . '/' . $key] = $value;
		});
		$config->method('deleteUserValue')->willReturnCallback(function (string $user, string $app, string $key): void {
			unset($this->prefs[$user . '/' . $key]);
		});
		return $config;
	}//end config()

	/**
	 * App config.
	 *
	 * @return IAppConfig
	 */
	private function appConfig(): IAppConfig {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default=''): string => $this->appValues[$key] ?? $default
		);
		$config->method('setValueString')->willReturnCallback(function (string $app, string $key, string $value): bool {
			$this->appValues[$key] = $value;
			return true;
		});
		return $config;
	}//end appConfig()

	/**
	 * Showcases.
	 *
	 * @return DemoShowcasesService
	 */
	private function showcases(): DemoShowcasesService {
		$showcases = $this->createMock(originalClassName: DemoShowcasesService::class);
		$showcases->method('renderableWidgetTypes')->willReturn(array_fill_keys(keys: (new WidgetTypeList())->types(), value: true));
		return $showcases;
	}//end showcases()

	/**
	 * Catalogue.
	 *
	 * @return CatalogueService
	 */
	private function catalogue(): CatalogueService {
		$catalogue = $this->createMock(originalClassName: CatalogueService::class);
		$catalogue->method('listAll')->willReturnCallback(fn (): array => array_values(array: $this->catalogue));
		$catalogue->method('create')->willReturnCallback(function (array $input): CatalogueApp {
			$app = new CatalogueApp();
			$app->setName($input['name']);
			$app->setLinkType($input['linkType']);
			$app->setLinkValue($input['linkValue']);
			$app->setUuid('uuid-' . count($this->catalogue));
			$this->catalogue[$app->getUuid()] = $app;
			return $app;
		});
		$catalogue->method('update')->willReturnCallback(function (string $uuid, array $input): CatalogueApp {
			$this->catalogue[$uuid]->setName($input['name']);
			return $this->catalogue[$uuid];
		});
		return $catalogue;
	}//end catalogue()

	/**
	 * Announcements.
	 *
	 * @return AnnouncementMapper
	 */
	private function announcements(): AnnouncementMapper {
		$mapper = $this->createMock(originalClassName: AnnouncementMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(function (string $uuid): Announcement {
			if (isset($this->announcementRows[$uuid]) === false) {
				throw new DoesNotExistException('none');
			}

			return $this->announcementRows[$uuid];
		});
		$mapper->method('insert')->willReturnCallback(function (Announcement $row): Announcement {
			$this->announcementRows[(string)$row->getUuid()] = $row;
			return $row;
		});
		$mapper->method('update')->willReturnCallback(fn (Announcement $row): Announcement => $row);
		return $mapper;
	}//end announcements()

	/**
	 * App manager.
	 *
	 * @return IAppManager
	 */
	private function appManager(): IAppManager {
		$appManager = $this->createMock(originalClassName: IAppManager::class);
		$appManager->method('getEnabledApps')->willReturn(['files', 'launchpad']);
		return $appManager;
	}//end appManager()
}//end class
