<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\TemplateNotInstalledException;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\PlacementPayloadHydrator;
use OCA\LaunchPad\Service\ShippedTemplateService;
use OCA\LaunchPad\Service\ShippedTemplateUpdateService;
use OCA\LaunchPad\Service\TemplateResyncService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\BackgroundJob\IJobList;
use OCP\Dashboard\IManager;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Updating an installed shipped template in place: REQ-TMPL-020.
 *
 * Nothing between the definition file and the stored rows is mocked. The real
 * importer installs, the real update service pairs and writes, the real
 * {@see TemplateService} makes a member's copy the way a first visit does,
 * and the real {@see TemplateResyncService} brings that copy along. Only the
 * two mappers are replaced, by an in-memory store, because a unit run has no
 * database.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Wires the real services.
 */
class ShippedTemplateUpdateServiceTest extends TestCase {
	/** @var array<string, string|int> App config, in memory. */
	private array $config = [];

	/** @var array<int, Dashboard> Stored dashboards by id. */
	private array $dashboards = [];

	/** @var array<int, WidgetPlacement> Stored widgets by id. */
	private array $placements = [];

	private int $nextId = 100;

	private string $dataDir = '';

	/** @var IDBConnection&MockObject */
	private $db;

	private DashboardMapper $dashboardMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	private ShippedTemplateService $shipped;

	private ShippedTemplateUpdateService $updates;

	private TemplateService $templateService;

	protected function setUp(): void {
		parent::setUp();
		$this->dataDir = sys_get_temp_dir() . '/launchpad-update-' . bin2hex(random_bytes(4));
		mkdir($this->dataDir);

		$this->dashboardMapper = $this->makeDashboardMapper();
		$this->placementMapper = $this->makePlacementMapper();
		$this->db = $this->createMock(IDBConnection::class);

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			fn (string $app, string $key, string $default = ''): string => (string)($this->config[$key] ?? $default)
		);
		$appConfig->method('getValueInt')->willReturnCallback(
			fn (string $app, string $key, int $default = 0): int => (int)($this->config[$key] ?? $default)
		);
		$appConfig->method('setValueString')->willReturnCallback(
			function (string $app, string $key, string $value): bool {
				$this->config[$key] = $value;
				return true;
			}
		);
		$appConfig->method('setValueInt')->willReturnCallback(
			function (string $app, string $key, int $value): bool {
				$this->config[$key] = $value;
				return true;
			}
		);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('groupExists')->willReturn(true);
		$groupManager->method('getUserGroupIds')->willReturn(['medewerkers']);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($this->createMock(IUser::class));
		$adminTemplates = new AdminTemplateService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			settingsService: $this->createMock(AdminSettingsService::class),
			groupManager: $groupManager,
			userManager: $userManager,
		);

		$this->shipped = new ShippedTemplateService(
			importService: new ImportService(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->placementMapper,
				db: $this->db,
				logger: new NullLogger(),
			),
			templateService: $adminTemplates,
			dashboardMapper: $this->dashboardMapper,
			appConfig: $appConfig,
			dashboardMgr: $this->createMock(IManager::class),
			groupManager: $groupManager,
		);
		$this->shipped->setDataDirForTesting(path: $this->dataDir);

		$this->templateService = new TemplateService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			adminTemplateService: $adminTemplates,
			appConfig: $appConfig,
		);

		$this->updates = new ShippedTemplateUpdateService(
			shipped: $this->shipped,
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			hydrator: new PlacementPayloadHydrator(),
			resyncService: new TemplateResyncService(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->placementMapper,
				db: $this->db,
				activityPublisher: $this->createMock(ActivityPublisher::class),
				notificationManager: $this->createMock(INotificationManager::class),
				jobList: $this->createMock(IJobList::class),
				logger: new NullLogger(),
			),
			appConfig: $appConfig,
			db: $this->db,
		);
	}

	protected function tearDown(): void {
		@unlink($this->dataDir . '/mijn-werkdag.json');
		@rmdir($this->dataDir);
		parent::tearDown();
	}

	/**
	 * @return DashboardMapper&MockObject
	 */
	private function makeDashboardMapper() {
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('insert')->willReturnCallback(
			function (Dashboard $dashboard): Dashboard {
				$dashboard->setId($this->nextId++);
				$this->dashboards[$dashboard->getId()] = $dashboard;
				return $dashboard;
			}
		);
		$mapper->method('update')->willReturnArgument(0);
		$mapper->method('find')->willReturnCallback(
			fn (int $id): Dashboard => $this->dashboards[$id] ?? throw new DoesNotExistException('no dashboard ' . $id)
		);
		$mapper->method('findByUuid')->willReturnCallback(
			function (string $uuid): Dashboard {
				foreach ($this->dashboards as $dashboard) {
					if ($dashboard->getUuid() === $uuid) {
						return $dashboard;
					}
				}
				throw new DoesNotExistException('no dashboard ' . $uuid);
			}
		);
		$mapper->method('findByBasedOnTemplate')->willReturnCallback(
			fn (int $templateId): array => array_values(array_filter(
				$this->dashboards,
				static fn (Dashboard $d): bool => $d->getBasedOnTemplate() === $templateId
			))
		);
		$mapper->method('findDefaultTemplate')->willThrowException(new DoesNotExistException('no default'));
		$mapper->method('findAdminTemplates')->willReturnCallback(
			fn (): array => array_values(array_filter(
				$this->dashboards,
				static fn (Dashboard $d): bool => $d->getType() === Dashboard::TYPE_ADMIN_TEMPLATE
			))
		);

		return $mapper;
	}

	/**
	 * @return WidgetPlacementMapper&MockObject
	 */
	private function makePlacementMapper() {
		$mapper = $this->createMock(WidgetPlacementMapper::class);
		$mapper->method('insert')->willReturnCallback(
			function (WidgetPlacement $placement): WidgetPlacement {
				$placement->setId($this->nextId++);
				$this->placements[$placement->getId()] = $placement;
				return $placement;
			}
		);
		$mapper->method('update')->willReturnArgument(0);
		$mapper->method('delete')->willReturnCallback(
			function (WidgetPlacement $placement): WidgetPlacement {
				unset($this->placements[$placement->getId()]);
				return $placement;
			}
		);
		$mapper->method('findByDashboardId')->willReturnCallback(
			fn (int $dashboardId): array => array_values(array_filter(
				$this->placements,
				static fn (WidgetPlacement $p): bool => $p->getDashboardId() === $dashboardId
			))
		);

		return $mapper;
	}

	/**
	 * One widget of a definition.
	 *
	 * @param array<string, mixed> $content Its settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function widget(string $type, string $title, int $y, bool $compulsory, array $content): array {
		return [
			'widgetId' => $type,
			'gridX' => 0,
			'gridY' => $y,
			'gridWidth' => 12,
			'gridHeight' => 4,
			'isCompulsory' => (int)$compulsory,
			'isVisible' => 1,
			'styleConfig' => [],
			'customTitle' => $title,
			'customIcon' => null,
			'content' => $content,
			'showTitle' => 1,
			'sortOrder' => intdiv($y, 4),
		];
	}

	/**
	 * Ship a version of `mijn-werkdag` in the test's definitions directory.
	 *
	 * @param array<int, array<string, mixed>> $widgets The version's widgets.
	 */
	private function ship(int $version, array $widgets): void {
		file_put_contents(
			$this->dataDir . '/mijn-werkdag.json',
			json_encode([
				'templateId' => 'mijn-werkdag',
				'templateVersion' => $version,
				'language' => 'nl',
				'dashboard' => [
					'uuid' => '6f0d1c1e-5b0a-4c56-9d55-0a1e6d2f7c01',
					'name' => 'Mijn werkdag',
					'description' => 'Startpagina',
					'type' => 'admin_template',
					'gridColumns' => 12,
					'permissionLevel' => 'add_only',
					'targetGroups' => [],
					'publicationStatus' => 'published',
					'widgets' => $widgets,
				],
			])
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function versionOne(): array {
		return [
			self::widget('header', 'Kop', 0, true, ['title' => 'Mijn werkdag', 'subtitle' => 'Oud']),
			self::widget('object-list', 'Mijn zaken', 4, false, ['register' => 'dossiq', 'schema' => 'case', 'limit' => 5]),
			self::widget('nc-widget', 'Verder waar u was', 8, false, ['widgetId' => 'activity']),
		];
	}

	/**
	 * Version two: the header's subtitle changes, the case list shows more
	 * rows, the activity widget goes, a compulsory list across apps and a
	 * ticket list arrive.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function versionTwo(): array {
		return [
			self::widget('header', 'Kop', 0, true, ['title' => 'Mijn werkdag', 'subtitle' => 'Nieuw']),
			self::widget('attention', 'Vandaag eerst', 4, true, ['limit' => 5]),
			// Same settings as before but for `limit`, written in another key order.
			self::widget('object-list', 'Mijn zaken', 8, false, ['limit' => 10, 'schema' => 'case', 'register' => 'dossiq']),
			self::widget('object-list', 'Mijn tickets', 12, false, ['register' => 'pipelinq', 'schema' => 'ticket']),
		];
	}

	/**
	 * The widgets of a dashboard, by title.
	 *
	 * @return array<string, WidgetPlacement>
	 */
	private function byTitle(int $dashboardId): array {
		$result = [];
		foreach ($this->placements as $placement) {
			if ($placement->getDashboardId() === $dashboardId) {
				$result[(string)$placement->getCustomTitle()] = $placement;
			}
		}

		return $result;
	}

	/**
	 * REQ-TMPL-020: the template keeps what makes it this template, its
	 * widgets become the new version's, and a widget in both versions keeps
	 * its row.
	 */
	public function testTheUpdateReplacesTheWidgetsAndKeepsTheTemplate(): void {
		$this->ship(1, self::versionOne());
		$installed = $this->shipped->install(templateId: 'mijn-werkdag', targetGroups: ['medewerkers'], makeDefault: true);
		$before = $this->byTitle($installed['id']);
		$this->ship(2, self::versionTwo());

		$result = $this->updates->update(templateId: 'mijn-werkdag', userId: 'beheer');

		self::assertTrue($result['applied']);
		self::assertFalse($result['upToDate']);
		self::assertSame(1, $result['installedVersion']);
		self::assertSame(2, $result['version']);
		self::assertSame(['Vandaag eerst (attention)', 'Mijn tickets (object-list)'], $result['added']);
		self::assertSame(['Verder waar u was (nc-widget)'], $result['removed']);
		self::assertSame(
			[
				['widget' => 'Kop (header)', 'fields' => ['settings']],
				['widget' => 'Mijn zaken (object-list)', 'fields' => ['position', 'order', 'settings']],
			],
			$result['changed']
		);
		self::assertSame(0, $result['unchanged']);
		self::assertSame(2, $this->config['shipped_template_version_mijn-werkdag']);

		$template = $this->dashboards[$installed['id']];
		self::assertSame($installed['uuid'], $template->getUuid());
		self::assertSame('Mijn werkdag', $template->getName());
		self::assertSame(['medewerkers'], $template->getTargetGroupsArray());
		self::assertSame(1, $template->getIsDefault());
		self::assertCount(1, $this->dashboards, 'no second template');

		$after = $this->byTitle($installed['id']);
		self::assertSame(['Kop', 'Mijn zaken', 'Vandaag eerst', 'Mijn tickets'], array_keys($after));
		self::assertSame($before['Kop']->getId(), $after['Kop']->getId(), 'a widget in both versions keeps its row');
		self::assertSame($before['Mijn zaken']->getId(), $after['Mijn zaken']->getId());
		self::assertSame('Nieuw', $after['Kop']->getContentArray()['subtitle']);
		self::assertSame(10, $after['Mijn zaken']->getContentArray()['limit']);
		self::assertSame(8, $after['Mijn zaken']->getGridY());
		self::assertSame(1, $after['Vandaag eerst']->getIsCompulsory());
	}

	/**
	 * REQ-TMPL-020 with REQ-RESYNC-003 and -004: a member's copy, made the
	 * way a first visit makes it, receives the new compulsory widget, loses
	 * the widget the template dropped and keeps the widget the member added.
	 */
	public function testAMembersCopyFollowsAndKeepsTheirOwnWidget(): void {
		$this->ship(1, self::versionOne());
		$installed = $this->shipped->install(templateId: 'mijn-werkdag', targetGroups: ['medewerkers']);
		$template = $this->templateService->getApplicableTemplate(userId: 'pieter');
		self::assertNotNull($template);
		self::assertSame($installed['id'], $template->getId());
		$copy = $this->templateService->createDashboardFromTemplate(userId: 'pieter', template: $template);

		$own = new WidgetPlacement();
		$own->setDashboardId($copy->getId());
		$own->setWidgetId('text');
		$own->setCustomTitle('Mijn notitie');
		$this->placementMapper->insert($own);
		self::assertSame(
			['Kop', 'Mijn zaken', 'Verder waar u was', 'Mijn notitie'],
			array_keys($this->byTitle($copy->getId()))
		);

		$this->ship(2, self::versionTwo());
		$result = $this->updates->update(templateId: 'mijn-werkdag', userId: 'beheer');

		self::assertSame(1, $result['copies']);
		self::assertSame(['async' => false, 'affectedCount' => 1, 'totalCopies' => 1], $result['resync']);
		$widgets = $this->byTitle($copy->getId());
		self::assertEqualsCanonicalizing(
			['Kop', 'Mijn zaken', 'Mijn notitie', 'Vandaag eerst', 'Mijn tickets'],
			array_keys($widgets)
		);
		self::assertSame(1, $widgets['Vandaag eerst']->getIsCompulsory(), 'the new compulsory widget arrived');
		self::assertSame('Nieuw', $widgets['Kop']->getContentArray()['subtitle']);
		self::assertSame(10, $widgets['Mijn zaken']->getContentArray()['limit']);
		self::assertNull($widgets['Mijn notitie']->getTemplatePlacementId());

		// A second run finds the shipped version installed and touches nothing.
		$again = $this->updates->update(templateId: 'mijn-werkdag', userId: 'beheer');
		self::assertTrue($again['upToDate']);
		self::assertFalse($again['applied']);
		self::assertNull($again['resync']);
	}

	/**
	 * REQ-TMPL-020: a dry run names the same changes and writes nothing.
	 */
	public function testADryRunWritesNothing(): void {
		$this->ship(1, self::versionOne());
		$installed = $this->shipped->install(templateId: 'mijn-werkdag');
		$this->ship(2, self::versionTwo());
		$stored = serialize(array_map(static fn (WidgetPlacement $p): array => $p->jsonSerialize(), $this->placements));
		$this->db->expects(self::never())->method('beginTransaction');

		$result = $this->updates->update(templateId: 'mijn-werkdag', dryRun: true);

		self::assertTrue($result['dryRun']);
		self::assertFalse($result['applied']);
		self::assertNull($result['resync']);
		self::assertCount(2, $result['added']);
		self::assertCount(1, $result['removed']);
		self::assertCount(2, $result['changed']);
		self::assertSame(1, $this->config['shipped_template_version_mijn-werkdag']);
		self::assertSame(
			$stored,
			serialize(array_map(static fn (WidgetPlacement $p): array => $p->jsonSerialize(), $this->placements))
		);
		self::assertCount(3, $this->byTitle($installed['id']));
	}

	/**
	 * REQ-TMPL-020: an update that does not change a widget leaves its row
	 * alone. The control for the pairing: a definition compared with its own
	 * install must come out all unchanged.
	 */
	public function testTheSameWidgetsInANewerVersionAreAllUnchanged(): void {
		$this->ship(1, self::versionOne());
		$this->shipped->install(templateId: 'mijn-werkdag');
		$this->ship(2, self::versionOne());
		$this->placementMapper->expects(self::never())->method('delete');

		$result = $this->updates->update(templateId: 'mijn-werkdag');

		self::assertSame([], $result['added']);
		self::assertSame([], $result['removed']);
		self::assertSame([], $result['changed']);
		self::assertSame(3, $result['unchanged']);
		self::assertSame(2, $this->config['shipped_template_version_mijn-werkdag']);
	}

	/**
	 * REQ-TMPL-020: the shipped file itself, installed and then compared
	 * with itself, pairs every widget. Holds the real data to the pairing,
	 * two same-type lists included.
	 */
	public function testTheShippedDefinitionPairsWithItsOwnInstall(): void {
		$this->shipped->setDataDirForTesting(path: dirname(__DIR__, 3) . '/data/templates');
		$definition = $this->shipped->readDefinition(templateId: 'mijn-werkdag');
		$this->shipped->install(templateId: 'mijn-werkdag');
		$this->config['shipped_template_version_mijn-werkdag'] = $definition['templateVersion'] - 1;

		$result = $this->updates->update(templateId: 'mijn-werkdag', dryRun: true);

		self::assertFalse($result['upToDate']);
		self::assertSame([], $result['added']);
		self::assertSame([], $result['removed']);
		self::assertSame([], $result['changed']);
		self::assertSame(count($definition['dashboard']['widgets']), $result['unchanged']);
	}

	/**
	 * REQ-TMPL-020: nothing to update when the template was never installed.
	 */
	public function testATemplateThatIsNotInstalledCannotBeUpdated(): void {
		$this->ship(2, self::versionTwo());

		$this->expectException(TemplateNotInstalledException::class);
		$this->expectExceptionMessage('mijn-werkdag is not installed');
		$this->updates->update(templateId: 'mijn-werkdag');
	}

	/**
	 * REQ-TMPL-020: a failed write is rolled back and the recorded version
	 * stays, so the next run tries again.
	 */
	public function testAFailedWriteKeepsTheRecordedVersion(): void {
		$this->ship(1, self::versionOne());
		$this->shipped->install(templateId: 'mijn-werkdag');
		$this->ship(2, self::versionTwo());
		$this->placementMapper->method('delete')->willThrowException(new RuntimeException('database gone'));
		$this->db->expects(self::once())->method('rollBack');
		$this->db->expects(self::never())->method('commit');

		try {
			$this->updates->update(templateId: 'mijn-werkdag');
			self::fail('a failed write must throw');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('Template not updated: database gone', $e->getMessage());
		}

		self::assertSame(1, $this->config['shipped_template_version_mijn-werkdag']);
	}
}
