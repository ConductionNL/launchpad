<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Command\ExportCommand;
use OCA\LaunchPad\Command\ImportCommand;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\ShippedTemplateService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The shipped templates: REQ-TMPL-018, and the round trip REQ-EXIM-012.
 *
 * Nothing between the definition file and the stored rows is mocked. The real
 * importer builds the entities and the real exporter serialises them; only the
 * two mappers are replaced, by an in-memory store, because there is no
 * database in a unit run.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Wires the real services.
 */
class ShippedTemplateServiceTest extends TestCase {
	/** @var array<string, string|int> App config, in memory. */
	private array $config = [];

	/** @var array<int, string> Groups that exist. */
	private array $groups = ['medewerkers'];

	/** @var array<int, string> Nextcloud widget ids registered "here". */
	private array $registeredWidgets = [];

	/**
	 * One instance: an in-memory store behind real services.
	 *
	 * @return array{dashboards: \ArrayObject, placements: \ArrayObject, dashboardMapper: DashboardMapper,
	 *               placementMapper: WidgetPlacementMapper, import: ImportService, export: ExportService}
	 */
	private function makeInstance(): array {
		$dashboards = new \ArrayObject();
		$placements = new \ArrayObject();

		$dashboardMapper = $this->createMock(DashboardMapper::class);
		$dashboardMapper->method('insert')->willReturnCallback(
			static function (Dashboard $dashboard) use ($dashboards): Dashboard {
				$dashboard->setId(count($dashboards) + 1);
				$dashboards[] = $dashboard;
				return $dashboard;
			}
		);
		$dashboardMapper->method('update')->willReturnArgument(0);
		$dashboardMapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($dashboards): Dashboard {
				foreach ($dashboards as $dashboard) {
					if ($dashboard->getUuid() === $uuid) {
						return $dashboard;
					}
				}
				throw new DoesNotExistException('no dashboard ' . $uuid);
			}
		);
		$dashboardMapper->method('find')->willReturnCallback(
			static function (int $id) use ($dashboards): Dashboard {
				foreach ($dashboards as $dashboard) {
					if ($dashboard->getId() === $id) {
						return $dashboard;
					}
				}
				throw new DoesNotExistException('no dashboard ' . $id);
			}
		);
		$dashboardMapper->method('clearDefaultTemplates')->willReturnCallback(
			static function () use ($dashboards): void {
				foreach ($dashboards as $dashboard) {
					$dashboard->setIsDefault(0);
				}
			}
		);

		$placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$placementMapper->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use ($placements): WidgetPlacement {
				$placement->setId(count($placements) + 1);
				$placements[] = $placement;
				return $placement;
			}
		);
		$placementMapper->method('findByDashboardId')->willReturnCallback(
			static fn (int $dashboardId): array => array_values(array_filter(
				$placements->getArrayCopy(),
				static fn (WidgetPlacement $p): bool => $p->getDashboardId() === $dashboardId
			))
		);

		return [
			'dashboards' => $dashboards,
			'placements' => $placements,
			'dashboardMapper' => $dashboardMapper,
			'placementMapper' => $placementMapper,
			'import' => new ImportService(
				dashboardMapper: $dashboardMapper,
				placementMapper: $placementMapper,
				db: $this->createMock(IDBConnection::class),
				logger: new NullLogger(),
			),
			'export' => new ExportService(
				dashboardMapper: $dashboardMapper,
				placementMapper: $placementMapper,
				groupManager: $this->createMock(IGroupManager::class),
				logger: new NullLogger(),
			),
		];
	}

	/**
	 * The service under test on one instance.
	 *
	 * @param array<string, mixed> $instance From makeInstance().
	 * @param string|null $dataDir Definitions directory override.
	 *
	 * @return ShippedTemplateService
	 */
	private function makeService(array $instance, ?string $dataDir = null): ShippedTemplateService {
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
		$groupManager->method('groupExists')->willReturnCallback(
			fn (string $gid): bool => in_array($gid, $this->groups, true)
		);

		$dashboardManager = $this->createMock(IManager::class);
		$dashboardManager->method('getWidgets')->willReturnCallback(
			function (): array {
				$widgets = [];
				foreach ($this->registeredWidgets as $id) {
					$widget = $this->createMock(IWidget::class);
					$widget->method('getId')->willReturn($id);
					$widgets[$id] = $widget;
				}
				return $widgets;
			}
		);

		$service = new ShippedTemplateService(
			importService: $instance['import'],
			templateService: new AdminTemplateService(
				dashboardMapper: $instance['dashboardMapper'],
				placementMapper: $instance['placementMapper'],
				settingsService: $this->createMock(AdminSettingsService::class),
				groupManager: $groupManager,
				userManager: $this->createMock(IUserManager::class),
			),
			dashboardMapper: $instance['dashboardMapper'],
			appConfig: $appConfig,
			dashboardMgr: $dashboardManager,
			groupManager: $groupManager,
		);
		if ($dataDir !== null) {
			$service->setDataDirForTesting(path: $dataDir);
		}

		return $service;
	}

	/**
	 * What a template IS, as REQ-EXIM-012 lists it: the fields that must be
	 * equal on both sides of a round trip.
	 *
	 * @param array<string, mixed> $dashboard A dashboard payload.
	 *
	 * @return array<string, mixed>
	 */
	private static function definitionOf(array $dashboard): array {
		$widgets = [];
		foreach ($dashboard['widgets'] as $widget) {
			$widgets[] = [
				'widgetId' => $widget['widgetId'],
				'gridX' => $widget['gridX'],
				'gridY' => $widget['gridY'],
				'gridWidth' => $widget['gridWidth'],
				'gridHeight' => $widget['gridHeight'],
				'isCompulsory' => $widget['isCompulsory'],
				'isVisible' => $widget['isVisible'],
				'showTitle' => $widget['showTitle'],
				'sortOrder' => $widget['sortOrder'],
				'customTitle' => $widget['customTitle'],
				'customIcon' => $widget['customIcon'],
				// An empty blob is `{}` on one side and `[]` on the other.
				'styleConfig' => (array)$widget['styleConfig'],
				'content' => (array)$widget['content'],
			];
		}

		usort($widgets, static fn (array $a, array $b): int => $a['sortOrder'] <=> $b['sortOrder']);

		return [
			'name' => $dashboard['name'],
			'description' => $dashboard['description'],
			'icon' => $dashboard['icon'],
			'type' => $dashboard['type'],
			'userId' => $dashboard['userId'],
			'gridColumns' => $dashboard['gridColumns'],
			'permissionLevel' => $dashboard['permissionLevel'],
			'targetGroups' => $dashboard['targetGroups'],
			'publicationStatus' => $dashboard['publicationStatus'],
			'templateCategory' => $dashboard['templateCategory'],
			'templateDescription' => $dashboard['templateDescription'],
			'widgets' => $widgets,
		];
	}

	/**
	 * REQ-TMPL-018: the install lands the definition, compulsory flags
	 * included, as an ownerless admin template that targets nobody.
	 */
	public function testInstallAddsTheTemplateThroughTheImporter(): void {
		$instance = $this->makeInstance();
		$service = $this->makeService($instance);
		$shipped = $service->readDefinition(templateId: 'mijn-werkdag');

		$result = $service->install(templateId: 'mijn-werkdag');

		self::assertFalse($result['alreadyInstalled']);
		self::assertSame($shipped['templateVersion'], $result['version']);
		self::assertCount(1, $instance['dashboards']);

		$template = $instance['dashboards'][0];
		self::assertSame(Dashboard::TYPE_ADMIN_TEMPLATE, $template->getType());
		self::assertNull($template->getUserId());
		self::assertSame(0, $template->getIsDefault());
		self::assertSame([], $template->getTargetGroupsArray());
		// A fresh UUID: the shipped one is the file's, not the instance's.
		self::assertNotSame($shipped['dashboard']['uuid'], $template->getUuid());
		self::assertSame($template->getUuid(), $result['uuid']);
		self::assertSame($template->getUuid(), $this->config['shipped_template_mijn-werkdag']);
		self::assertSame($shipped['templateVersion'], $this->config['shipped_template_version_mijn-werkdag']);

		self::assertSame(
			self::definitionOf($shipped['dashboard']),
			self::definitionOf($instance['export']->serializeDashboard(dashboard: $template))
		);
		$compulsory = array_filter(
			$instance['placements']->getArrayCopy(),
			static fn (WidgetPlacement $p): bool => $p->getIsCompulsory() === 1
		);
		self::assertCount(2, $compulsory, 'the header and the First today list are compulsory');
	}

	/**
	 * REQ-EXIM-012: shipped definition -> instance A -> `launchpad:export`
	 * -> archive -> `launchpad:import` on instance B reproduces the template.
	 */
	public function testTheShippedTemplateSurvivesExportAndImport(): void {
		$a = $this->makeInstance();
		$service = $this->makeService($a);
		$installed = $service->install(templateId: 'mijn-werkdag', targetGroups: ['medewerkers']);
		$onA = $a['export']->serializeDashboard(dashboard: $a['dashboards'][0]);
		self::assertSame(['medewerkers'], $onA['targetGroups']);

		$archive = (string)tempnam(sys_get_temp_dir(), 'launchpad-roundtrip-') . '.zip';
		try {
			$export = new CommandTester(new ExportCommand($a['export'], $a['dashboardMapper']));
			$exit = $export->execute([
				'--scope' => 'dashboard',
				'--dashboard-uuid' => $installed['uuid'],
				'--output' => $archive,
			]);
			self::assertSame(0, $exit, $export->getDisplay());

			$b = $this->makeInstance();
			$import = new CommandTester(new ImportCommand($b['import']));
			$exit = $import->execute(['--file' => $archive]);
			self::assertSame(0, $exit, $import->getDisplay());
			self::assertStringContainsString('Imported 1 dashboards, skipped 0, errors: 0', $import->getDisplay());
		} finally {
			@unlink($archive);
			@unlink(substr($archive, 0, -4));
		}

		self::assertCount(1, $b['dashboards']);
		$onB = $b['export']->serializeDashboard(dashboard: $b['dashboards'][0]);

		self::assertSame(self::definitionOf($onA), self::definitionOf($onB));
		// A against B alone would pass if the install had already lost a field
		// on A, so B is also held against the shipped file.
		$shipped = $service->readDefinition(templateId: 'mijn-werkdag')['dashboard'];
		$shipped['targetGroups'] = ['medewerkers'];
		self::assertSame(self::definitionOf($shipped), self::definitionOf($onB));
		self::assertNotSame($onA['uuid'], $onB['uuid']);
		self::assertSame(0, $onB['isDefault']);
		self::assertNull($onB['userId'], 'a template has no owner, the importer included');
		// The control: the comparison would also pass if both sides were
		// empty, so pin that the compulsory flags really crossed.
		self::assertSame(
			[1, 1, 0, 0, 0, 0, 0],
			array_column(self::definitionOf($onB)['widgets'], 'isCompulsory')
		);
	}

	/**
	 * REQ-TMPL-018: a second install adds nothing and still applies options.
	 */
	public function testASecondInstallAddsNothing(): void {
		$instance = $this->makeInstance();
		$service = $this->makeService($instance);
		$first = $service->install(templateId: 'mijn-werkdag');

		$second = $service->install(templateId: 'mijn-werkdag', targetGroups: ['medewerkers'], makeDefault: true);

		self::assertTrue($second['alreadyInstalled']);
		self::assertSame($first['uuid'], $second['uuid']);
		self::assertCount(1, $instance['dashboards']);
		self::assertSame(['medewerkers'], $second['targetGroups']);
		self::assertTrue($second['isDefault']);
	}

	/**
	 * REQ-TMPL-018: forcing adds a fresh copy and keeps the earlier one.
	 */
	public function testForceAddsAFreshCopyAndKeepsTheEarlierOne(): void {
		$instance = $this->makeInstance();
		$service = $this->makeService($instance);
		$first = $service->install(templateId: 'mijn-werkdag');

		$second = $service->install(templateId: 'mijn-werkdag', force: true);

		self::assertFalse($second['alreadyInstalled']);
		self::assertNotSame($first['uuid'], $second['uuid']);
		self::assertCount(2, $instance['dashboards']);
		self::assertSame($second['uuid'], $this->config['shipped_template_mijn-werkdag']);
	}

	/**
	 * REQ-TMPL-018: a recorded install whose template is gone is no install.
	 */
	public function testADeletedTemplateCountsAsNotInstalled(): void {
		$instance = $this->makeInstance();
		$service = $this->makeService($instance);
		$this->config['shipped_template_mijn-werkdag'] = 'gone-uuid';

		self::assertFalse($service->listTemplates()[0]['isInstalled']);
		$result = $service->install(templateId: 'mijn-werkdag');

		self::assertFalse($result['alreadyInstalled']);
		self::assertCount(1, $instance['dashboards']);
	}

	/**
	 * REQ-CLI-012: a mistyped group fails before anything is written.
	 */
	public function testAnUnknownGroupStopsTheInstall(): void {
		$instance = $this->makeInstance();
		$service = $this->makeService($instance);

		try {
			$service->install(templateId: 'mijn-werkdag', targetGroups: ['medewerkerz']);
			self::fail('an unknown group must throw');
		} catch (InvalidArgumentException $e) {
			self::assertStringContainsString('medewerkerz', $e->getMessage());
		}

		self::assertCount(0, $instance['dashboards']);
		self::assertSame([], $this->config);
	}

	/**
	 * REQ-TMPL-018: an unknown id names the shipped ones.
	 */
	public function testAnUnknownTemplateNamesTheShippedOnes(): void {
		$service = $this->makeService($this->makeInstance());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('mijn-werkdag');
		$service->install(templateId: 'does-not-exist');
	}

	/**
	 * REQ-TMPL-018: a definition with no widgets is an error, not an empty template.
	 */
	public function testAMalformedDefinitionFails(): void {
		$dir = sys_get_temp_dir() . '/launchpad-templates-' . bin2hex(random_bytes(4));
		mkdir($dir);
		file_put_contents(
			$dir . '/mijn-werkdag.json',
			json_encode(['templateVersion' => 1, 'dashboard' => ['uuid' => 'x', 'name' => 'x', 'widgets' => []]])
		);
		$instance = $this->makeInstance();
		$service = $this->makeService($instance, $dir);

		try {
			$service->install(templateId: 'mijn-werkdag');
			self::fail('a definition without widgets must throw');
		} catch (RuntimeException $e) {
			self::assertStringContainsString('mijn-werkdag.json', $e->getMessage());
		} finally {
			unlink($dir . '/mijn-werkdag.json');
			rmdir($dir);
		}

		self::assertCount(0, $instance['dashboards']);
	}

	/**
	 * REQ-TMPL-018: the listing names proxied widgets nothing registers.
	 */
	public function testTheListingNamesWidgetsNothingRegisters(): void {
		// Nothing registers `activity` here, the one Nextcloud widget the template proxies.
		$this->registeredWidgets = ['files-favorites'];
		$service = $this->makeService($this->makeInstance());

		$listing = $service->listTemplates();

		self::assertCount(1, $listing);
		self::assertSame('mijn-werkdag', $listing[0]['id']);
		self::assertSame('Mijn werkdag', $listing[0]['name']);
		self::assertSame('nl', $listing[0]['language']);
		self::assertSame(10, $listing[0]['widgetCount']);
		self::assertSame(['dossiq', 'pipelinq', 'decidiq'], $listing[0]['registers']);
		self::assertFalse($listing[0]['isInstalled']);
		self::assertNull($listing[0]['installedVersion']);
		self::assertSame(['activity'], $listing[0]['missingWidgets']);
	}

	/**
	 * REQ-TMPL-020: the listing says when a newer version ships than the
	 * one installed, and only then.
	 */
	public function testTheListingSaysWhenAnUpdateIsAvailable(): void {
		$service = $this->makeService($this->makeInstance());
		self::assertFalse($service->listTemplates()[0]['updateAvailable'], 'not installed: nothing to update');

		$service->install(templateId: 'mijn-werkdag');
		self::assertFalse($service->listTemplates()[0]['updateAvailable']);

		$this->config['shipped_template_version_mijn-werkdag'] = 1;
		$listing = $service->listTemplates()[0];
		self::assertTrue($listing['updateAvailable']);
		self::assertSame(1, $listing['installedVersion']);
		self::assertNotNull($service->findInstalled(templateId: 'mijn-werkdag'));
	}

	/**
	 * REQ-TMPL-018: a shipped template renders as installed.
	 *
	 * Two live defects, both invisible to every other test here:
	 *  - four widgets proxied Nextcloud dashboard widgets that only paint
	 *    through their own script (`IWidget` without an items API). LaunchPad
	 *    loads those scripts only when the legacy widget bridge is switched
	 *    on, so as installed they showed one sentence and their raw id;
	 *  - a list sorted on `plannedEndDate` and showed it as a column, while a
	 *    dossiq case carries `deadline`. The column was empty for every case.
	 *
	 * So: a proxied Nextcloud widget must be on the short list of widgets
	 * known to answer the items API, and every field a list names on a known
	 * register must exist in that register's schema.
	 */
	public function testAShippedTemplateRendersAsInstalled(): void {
		$service = $this->makeService($this->makeInstance());
		// Nextcloud widgets whose provider implements IAPIWidget(V2), measured
		// on Nextcloud 35 via /ocs/v2.php/apps/dashboard/api/v1/widgets
		// (`item_api_versions` not empty). Add one only after measuring it.
		$itemsApiWidgets = ['activity', 'recommendations', 'files-favorites', 'user_status'];
		$schemas = [];
		$enums = [];
		foreach (glob(dirname(__DIR__, 2) . '/fixtures/registers/*.json') ?: [] as $file) {
			$fixture = json_decode((string)file_get_contents($file), true);
			$schemas[$fixture['register'] . '/' . $fixture['schema']] = $fixture['properties'];
			$enums[$fixture['register'] . '/' . $fixture['schema']] = ($fixture['enums'] ?? []);
		}
		self::assertArrayHasKey('dossiq/case', $schemas);
		self::assertArrayHasKey('pipelinq/ticket', $schemas);
		self::assertArrayHasKey('decidiq/decision', $schemas);

		$listsChecked = 0;
		foreach (ShippedTemplateService::SHIPPED_IDS as $id) {
			foreach ($service->readDefinition(templateId: $id)['dashboard']['widgets'] as $widget) {
				$content = $widget['content'];
				if ($widget['widgetId'] === 'nc-widget') {
					self::assertContains($content['widgetId'], $itemsApiWidgets, 'this widget cannot paint without the legacy bridge');
				}
				if ($widget['widgetId'] !== 'object-list') {
					continue;
				}

				$key = $content['register'] . '/' . $content['schema'];
				self::assertArrayHasKey($key, $schemas, 'no field list for ' . $key . ': add tests/fixtures/registers');
				$named = array_column($content['columns'], 'key');
				$named[] = $content['sort']['field'];
				foreach (array_keys($content['filter']) as $filterKey) {
					// `deadline[lt]` names the field `deadline`.
					$named[] = preg_replace('/\[.*$/', '', (string)$filterKey);
				}
				foreach ($named as $field) {
					self::assertContains($field, $schemas[$key], $widget['customTitle'] . ' names a field ' . $key . ' does not have');
				}
				foreach ($content['filter'] as $filterKey => $value) {
					// A list of values is "any of these" and travels as
					// `status[0]=..&status[1]=..`, which OpenRegister honours
					// (measured 5 October 2026). An operator goes in the key.
					if (is_array($value) === true) {
						self::assertTrue(array_is_list($value), 'an operator goes in the key, as in "deadline[lt]"');
					}
					$values = (is_array($value) === true ? $value : [$value]);
					$field = preg_replace('/\[.*$/', '', (string)$filterKey);
					if (isset($enums[$key][$field]) === true) {
						foreach ($values as $one) {
							self::assertContains($one, $enums[$key][$field], $widget['customTitle'] . ' filters ' . $field . ' on a value the schema does not know');
						}
					}
				}
				// REQ-TMPL-022: a list of another app is hidden where that app
				// is not installed, instead of showing an error line.
				self::assertTrue($content['hideWhenUnavailable'] ?? false, $widget['customTitle'] . ' must hide when its register is not here');
				$listsChecked++;
			}
		}

		self::assertSame(4, $listsChecked, 'two dossiq lists, the pipelinq list and the decidiq list');
	}

	/**
	 * REQ-TMPL-018: the shipped data itself. Every widget is a type LaunchPad
	 * renders, sits inside the grid and overlaps no other widget.
	 */
	public function testTheShippedDefinitionIsWellFormed(): void {
		$service = $this->makeService($this->makeInstance());
		$types = json_decode(
			(string)file_get_contents(dirname(__DIR__, 3) . '/lib/widget-types.json'),
			true
		)['types'];

		foreach (ShippedTemplateService::SHIPPED_IDS as $id) {
			$definition = $service->readDefinition(templateId: $id);
			$dashboard = $definition['dashboard'];
			self::assertSame($id, $definition['templateId']);
			self::assertSame(Dashboard::TYPE_ADMIN_TEMPLATE, $dashboard['type']);
			self::assertSame([], $dashboard['targetGroups'], 'a shipped template targets nobody');
			self::assertArrayNotHasKey('isDefault', $dashboard);
			self::assertStringNotContainsString("\u{2014}", json_encode($dashboard, JSON_UNESCAPED_UNICODE));

			$cells = [];
			foreach ($dashboard['widgets'] as $widget) {
				self::assertContains($widget['widgetId'], $types);
				self::assertLessThanOrEqual($dashboard['gridColumns'], $widget['gridX'] + $widget['gridWidth']);
				if ($widget['widgetId'] === 'nc-widget') {
					self::assertNotSame('', $widget['content']['widgetId'] ?? '');
				}
				for ($x = $widget['gridX']; $x < $widget['gridX'] + $widget['gridWidth']; $x++) {
					for ($y = $widget['gridY']; $y < $widget['gridY'] + $widget['gridHeight']; $y++) {
						self::assertArrayNotHasKey($x . ':' . $y, $cells, 'two widgets share cell ' . $x . ':' . $y);
						$cells[$x . ':' . $y] = true;
					}
				}
			}
		}
	}
}
