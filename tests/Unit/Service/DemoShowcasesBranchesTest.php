<?php

/**
 * DemoShowcasesService branch characterisation test
 *
 * Pins the showcase descriptor (unreadable archives, manifest defaults,
 * install state), the widget partition (non-array entries, tiles,
 * LaunchPad and registered Nextcloud types, unnamed unknowns) and the
 * dashboard payload lookup inside a showcase archive, so these methods
 * can be split into named steps without changing their answers.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DemoShowcasesService;
use OCA\LaunchPad\Service\ShowcasePlatform;
use OCA\LaunchPad\Service\ShowcaseStores;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionMethod;
use RuntimeException;
use ZipArchive;

class DemoShowcasesBranchesTest extends TestCase {
	private IAppConfig $appConfig;
	private IManager $dashboardManager;
	private DemoShowcasesService $service;
	private string $fixtureDir;

	protected function setUp(): void {
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->dashboardManager = $this->createMock(originalClassName: IManager::class);
		$urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);
		$urlGenerator->method('imagePath')->willReturnCallback(
			static fn (string $app, string $file): string => 'img:' . $file
		);

		$this->service = new DemoShowcasesService(
			new ShowcaseStores(
				$this->createMock(originalClassName: DashboardMapper::class),
				$this->createMock(originalClassName: WidgetPlacementMapper::class),
				$this->createMock(originalClassName: IDBConnection::class),
				$this->createMock(originalClassName: ILockingProvider::class),
			),
			new ShowcasePlatform($this->appConfig, $this->dashboardManager, $urlGenerator),
			new NullLogger(),
		);

		$this->fixtureDir = sys_get_temp_dir() . '/launchpad-showcase-branches-' . uniqid();
		mkdir($this->fixtureDir, 0o755, true);
		$this->service->setDataDirForTesting(path: $this->fixtureDir);
	}

	protected function tearDown(): void {
		foreach (glob($this->fixtureDir . '/*/*') ?: [] as $file) {
			unlink($file);
		}

		foreach (glob($this->fixtureDir . '/*') ?: [] as $dir) {
			rmdir($dir);
		}

		rmdir($this->fixtureDir);
	}

	/**
	 * Write a showcase archive with the given entries.
	 *
	 * @param string               $showcaseId The showcase id.
	 * @param array<string,string> $entries    Entry name => contents.
	 *
	 * @return string The archive path.
	 */
	private function archive(string $showcaseId, array $entries): string {
		$dir = $this->fixtureDir . '/' . $showcaseId;
		mkdir($dir, 0o755, true);
		$path = $dir . '/' . $showcaseId . '.zip';
		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::CREATE);
		foreach ($entries as $name => $contents) {
			$zip->addFromString($name, $contents);
		}

		$zip->close();

		return $path;
	}

	/**
	 * Call the private payload loader.
	 *
	 * @param string $path The archive path.
	 *
	 * @return array<string,mixed>
	 */
	private function loadPayload(string $path): array {
		$method = new ReflectionMethod(DemoShowcasesService::class, 'loadDashboardPayload');

		return $method->invoke($this->service, $path);
	}

	public function testCorruptArchiveHasNoDescriptor(): void {
		mkdir($this->fixtureDir . '/x', 0o755, true);
		file_put_contents($this->fixtureDir . '/x/x.zip', 'not a zip');

		$this->assertNull($this->service->describeShowcase(showcaseId: 'x'));
	}

	public function testArchiveWithoutManifestHasNoDescriptor(): void {
		$this->archive('x', ['other.json' => '{}']);

		$this->assertNull($this->service->describeShowcase(showcaseId: 'x'));
	}

	public function testArchiveWithScalarManifestHasNoDescriptor(): void {
		$this->archive('x', ['manifest.json' => '"text"']);

		$this->assertNull($this->service->describeShowcase(showcaseId: 'x'));
	}

	public function testDescriptorDefaultsAndInstallState(): void {
		$this->archive('x', ['manifest.json' => '{}']);
		$this->appConfig->method('getValueString')->willReturn('uuid-1');

		$this->assertSame(
			[
				'id' => 'x',
				'name' => 'x',
				'description' => '',
				'language' => 'nl',
				'thumbnailUrl' => 'img:showcases/x.png',
				'isInstalled' => true,
				'installedDashboardUuid' => 'uuid-1',
			],
			$this->service->describeShowcase(showcaseId: 'x')
		);
	}

	public function testDescriptorFromManifestNotInstalled(): void {
		$this->archive('x', ['manifest.json' => '{"showcaseName":"X","showcaseDescription":"D","showcaseLanguage":"en"}']);
		$this->appConfig->method('getValueString')->willReturn('');

		$result = $this->service->describeShowcase(showcaseId: 'x');

		$this->assertSame(['X', 'D', 'en', false, null], [
			$result['name'],
			$result['description'],
			$result['language'],
			$result['isInstalled'],
			$result['installedDashboardUuid'],
		]);
	}

	public function testPartitionKeepsTilesOwnAndRegisteredTypesInOrder(): void {
		$registered = $this->createMock(originalClassName: IWidget::class);
		$registered->method('getId')->willReturn('nc-registered');
		$this->dashboardManager->method('getWidgets')->willReturn([$registered]);

		[$valid, $skipped] = $this->service->partitionWidgets(widgets: [
			'not-an-array',
			['widgetId' => 'nope-1'],
			['widgetId' => 'nc-registered', 'x' => 1],
			['widgetId' => 'nope-2', 'tileType' => 'shortcut'],
			['widgetId' => 'object-list'],
			['tileType' => ''],
			['widgetId' => ''],
			['widgetId' => 'nope-3', 'tileType' => ''],
		]);

		$this->assertSame(
			[
				['widgetId' => 'nc-registered', 'x' => 1],
				['widgetId' => 'nope-2', 'tileType' => 'shortcut'],
				['widgetId' => 'object-list'],
			],
			$valid
		);
		$this->assertSame(['nope-1', 'nope-3'], $skipped);
	}

	public function testPayloadIsTheFirstDecodableDashboardEntry(): void {
		$path = $this->archive('x', [
			'manifest.json' => '{"a":1}',
			'dashboards/readme.txt' => '{"b":1}',
			'other/d.json' => '{"c":1}',
			'dashboards/broken.json' => 'nope',
			'dashboards/scalar.json' => '5',
			'dashboards/first.json' => '{"name":"first"}',
			'dashboards/second.json' => '{"name":"second"}',
		]);

		$this->assertSame(['name' => 'first'], $this->loadPayload($path));
	}

	public function testArchiveWithoutDashboardEntryIsRefused(): void {
		$path = $this->archive('x', ['manifest.json' => '{}', 'dashboards/x.txt' => '{}']);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Showcase archive contained no dashboard JSON entry.');

		$this->loadPayload($path);
	}

	public function testUnopenableArchiveIsRefused(): void {
		$path = $this->fixtureDir . '/missing.zip';

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Could not open showcase archive: ' . $path);

		$this->loadPayload($path);
	}
}//end class
