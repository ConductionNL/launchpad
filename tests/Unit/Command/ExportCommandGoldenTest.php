<?php

/**
 * Export command golden test
 *
 * Pins what `launchpad:export` prints, returns and writes on every branch:
 * option refusals, an unknown dashboard, a failing export, a single
 * dashboard and the site (templates, roots and their descendants).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\LaunchPad\Command\ExportCommand;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\ExportService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use ZipArchive;

/**
 * The printed output and archive of the export command, case by case.
 */
class ExportCommandGoldenTest extends TestCase {

	private string $zipPath = '';

	protected function setUp(): void {
		$this->zipPath = sys_get_temp_dir() . '/launchpad-export-golden-' . getmypid() . '.zip';
	}

	protected function tearDown(): void {
		if (file_exists($this->zipPath) === true) {
			unlink($this->zipPath);
		}
	}

	private static function dashboard(string $uuid): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUuid($uuid);
		return $dashboard;
	}

	/**
	 * Run the export command.
	 *
	 * @param array<string, mixed> $input The input.
	 *
	 * @return array{0: int, 1: string}
	 */
	private function runExport(array $input): array {
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid): Dashboard {
				if ($uuid === 'missing') {
					throw new DoesNotExistException('missing');
				}

				return self::dashboard($uuid);
			}
		);
		$mapper->method('findAdminTemplates')->willReturn([self::dashboard('tpl-1')]);
		$mapper->method('findByParent')->with(null)->willReturn([self::dashboard('root-1'), self::dashboard('')]);
		$mapper->method('findDescendants')->with('root-1')->willReturn([self::dashboard('child-1'), self::dashboard('')]);

		$service = $this->createMock(ExportService::class);
		$service->method('buildManifest')->willReturnCallback(
			static fn (string $scope, int $dashboardCount, string $currentUserId): array => [
				'scope' => $scope,
				'count' => $dashboardCount,
				'by' => $currentUserId,
			]
		);
		$service->method('serializeDashboard')->willReturnCallback(
			static function (Dashboard $dashboard): array {
				if ($dashboard->getUuid() === 'boom') {
					throw new \RuntimeException('cannot serialise');
				}

				return ['uuid' => $dashboard->getUuid(), 'url' => 'a/b'];
			}
		);

		$tester = new CommandTester(new ExportCommand($service, $mapper));
		$code = $tester->execute($input);
		return [$code, $tester->getDisplay()];
	}//end runExport()

	/**
	 * The entries of the written archive with their contents.
	 *
	 * @return array<string, string>
	 */
	private function entries(): array {
		$zip = new ZipArchive();
		$zip->open($this->zipPath);
		$entries = [];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = (string)$zip->getNameIndex($i);
			$entries[$name] = (string)$zip->getFromIndex($i);
		}

		$zip->close();
		return $entries;
	}

	public function testRefusals(): void {
		$this->assertSame([1, "--output parameter is required\n"], $this->runExport([]));
		$this->assertSame(
			[1, "Unsupported scope: x. Use \"site\" or \"dashboard\".\n"],
			$this->runExport(['--output' => $this->zipPath, '--scope' => 'x'])
		);
		$this->assertSame(
			[1, "--dashboard-uuid is required when --scope=dashboard\n"],
			$this->runExport(['--output' => $this->zipPath, '--scope' => 'dashboard'])
		);
		$this->assertSame(
			[1, "Dashboard not found: missing\n"],
			$this->runExport(['--output' => $this->zipPath, '--scope' => 'dashboard', '--dashboard-uuid' => 'missing'])
		);
		$this->assertSame(
			[1, "Export failed: cannot serialise\n"],
			$this->runExport(['--output' => $this->zipPath, '--scope' => 'dashboard', '--dashboard-uuid' => 'boom'])
		);
	}

	public function testSingleDashboardArchive(): void {
		$this->assertSame(
			[0, 'Exported 1 dashboard to ' . $this->zipPath . "\n"],
			$this->runExport(['--output' => $this->zipPath, '--scope' => 'dashboard', '--dashboard-uuid' => 'd-1'])
		);
		$this->assertSame(
			[
				'manifest.json' => "{\n    \"scope\": \"dashboard\",\n    \"count\": 1,\n    \"by\": \"cli\"\n}",
				'dashboards/d-1.json' => "{\n    \"uuid\": \"d-1\",\n    \"url\": \"a/b\"\n}",
				'assets/widgets/d-1/' => '',
				'metadata-fields.json' => '[]',
				'assets/icons/' => '',
			],
			$this->entries()
		);
	}

	public function testSiteArchive(): void {
		$this->assertSame(
			[0, 'Exported 5 dashboards to ' . $this->zipPath . "\n"],
			$this->runExport(['--output' => $this->zipPath])
		);
		$this->assertSame(
			[
				'manifest.json',
				'dashboards/tpl-1.json',
				'assets/widgets/tpl-1/',
				'dashboards/root-1.json',
				'assets/widgets/root-1/',
				'dashboards/child-1.json',
				'assets/widgets/child-1/',
				'metadata-fields.json',
				'assets/icons/',
			],
			array_keys($this->entries())
		);
	}
}
