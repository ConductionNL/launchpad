<?php

/**
 * ImportBatchGoldenTest
 *
 * Characterisation test pinning the per-dashboard import loop of
 * ImportService: the exact result, the transaction calls per dashboard,
 * which widget entries become placements, and the log line of a failed
 * dashboard, so the loop can be split into named steps without changing
 * behaviour.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\ImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use ZipArchive;

class ImportBatchGoldenTest extends TestCase {
	public function testEveryOutcomeOfTheBatchLoop(): void {
		$path = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-import-golden-');
		$zip = new ZipArchive();
		$zip->open(filename: $path, flags: ZipArchive::OVERWRITE);
		$entries = [
			'manifest.json' => ['schemaVersion' => 1, 'scope' => 'site'],
			'dashboards/a.json' => ['uuid' => 'uuid-a', 'name' => 'A', 'widgets' => ['x', ['widgetId' => 'text', 'gridX' => 1], 5, ['widgetId' => 'label']]],
			'dashboards/b.json' => ['uuid' => 'uuid-b', 'name' => 'B', 'widgets' => 'not a list'],
			'dashboards/c.json' => ['uuid' => 'uuid-c'],
			'dashboards/d.json' => ['uuid' => 'uuid-d', 'name' => 'D', 'type' => Dashboard::TYPE_ADMIN_TEMPLATE, 'widgets' => [['widgetId' => 'text', 'isCompulsory' => 1]]],
			'dashboards/e.json' => ['uuid' => 'uuid-e', 'name' => 'E', 'widgets' => []],
		];
		foreach ($entries as $name => $content) {
			$zip->addFromString(name: $name, content: (string)json_encode(value: $content));
		}
		$zip->close();

		$dashboards = $this->createMock(originalClassName: DashboardMapper::class);
		$dashboards->method('findByUuid')->willThrowException(exception: new DoesNotExistException(msg: 'no'));
		$nextId = 10;
		$dashboards->method('insert')->willReturnCallback(
			static function (Dashboard $dashboard) use (&$nextId): Dashboard {
				if ($dashboard->getName() === 'E') {
					throw new RuntimeException(message: 'boom');
				}
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				$dashboard->setId($nextId++);
				return $dashboard;
			}
		);

		$inserted = [];
		$placements = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$placements->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$inserted): WidgetPlacement {
				$inserted[] = [$placement->getDashboardId(), $placement->getWidgetId(), $placement->getIsCompulsory()];
				return $placement;
			}
		);

		$calls = [];
		$db = $this->createMock(originalClassName: IDBConnection::class);
		foreach (['beginTransaction', 'commit', 'rollBack'] as $method) {
			$db->method($method)->willReturnCallback(
				static function () use (&$calls, $method): bool {
					$calls[] = $method;
					return true;
				}
			);
		}

		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with(
			'Skipped dashboard during import',
			$this->callback(static fn (array $context): bool => $context['uuid'] === 'uuid-e' && $context['exception'] instanceof RuntimeException)
		);

		$service = new ImportService(dashboardMapper: $dashboards, placementMapper: $placements, db: $db, logger: $logger);
		$result = $service->import(zipPath: $path, preserveUuids: true, currentUserId: 'admin');
		@unlink(filename: $path);

		$this->assertSame('ok', $result['status']);
		$this->assertSame(3, $result['importedDashboardCount']);
		$this->assertSame(2, $result['skippedDashboardCount']);
		$this->assertSame(
			[
				['sourceUuid' => 'uuid-a', 'uuid' => 'uuid-a', 'id' => 10],
				['sourceUuid' => 'uuid-b', 'uuid' => 'uuid-b', 'id' => 11],
				['sourceUuid' => 'uuid-d', 'uuid' => 'uuid-d', 'id' => 12],
			],
			$result['dashboards']
		);
		$this->assertCount(2, $result['errors']);
		$this->assertSame('uuid-c', $result['errors'][0]['uuid']);
		$this->assertSame(
			['type' => $result['errors'][1]['type'], 'uuid' => 'uuid-e', 'message' => 'Failed to import dashboard: boom'],
			$result['errors'][1]
		);
		$this->assertSame($result['errors'][0]['type'], $result['errors'][1]['type']);
		$this->assertSame(
			['beginTransaction', 'commit', 'beginTransaction', 'commit', 'beginTransaction', 'commit', 'beginTransaction', 'rollBack'],
			$calls
		);
		$this->assertSame([[10, 'text', 0], [10, 'label', 0], [12, 'text', 1]], $inserted);
	}

	/**
	 * @return array<string, array{0: string|null, 1: string}>
	 */
	public static function badArchives(): array {
		return [
			'manifest not json' => ['{', 'manifest.json is not valid JSON'],
			'manifest scalar' => ['"x"', 'manifest.json is not valid JSON'],
			'both fields missing' => ['{}', 'Manifest missing required field(s): schemaVersion, scope'],
			'scope missing' => ['{"schemaVersion":1}', 'Manifest missing required field(s): scope'],
			'version as string' => ['{"schemaVersion":"1","scope":"site"}', 'Unsupported manifest schema version: 1. Only version 1 is supported.'],
			'version zero' => ['{"schemaVersion":0,"scope":"site"}', 'Unsupported manifest schema version: 0. Only version 1 is supported.'],
			'newer version' => ['{"schemaVersion":3,"scope":"site"}', 'Unsupported manifest schema version: 3. Only version 1 is supported. Upgrade LaunchPad to import archives of version 3.'],
			'no manifest' => [null, 'manifest.json not found in archive'],
		];
	}

	/**
	 * @dataProvider badArchives
	 */
	public function testManifestRefusals(?string $manifest, string $message): void {
		$path = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-import-golden-');
		$zip = new ZipArchive();
		$zip->open(filename: $path, flags: ZipArchive::OVERWRITE);
		$zip->addFromString(name: ($manifest === null ? 'other.json' : 'manifest.json'), content: (string)$manifest);
		$zip->close();

		$service = new ImportService(
			dashboardMapper: $this->createMock(originalClassName: DashboardMapper::class),
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			db: $this->createMock(originalClassName: IDBConnection::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		try {
			$service->import(zipPath: $path, preserveUuids: false, currentUserId: 'admin');
			$this->fail('expected a refusal');
		} catch (\InvalidArgumentException $e) {
			$this->assertSame($message, $e->getMessage());
		} finally {
			@unlink(filename: $path);
		}
	}

	public function testMissingAndNonZipFilesAreRefused(): void {
		$service = new ImportService(
			dashboardMapper: $this->createMock(originalClassName: DashboardMapper::class),
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			db: $this->createMock(originalClassName: IDBConnection::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$text = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-import-golden-');
		file_put_contents($text, 'not a zip');

		foreach (['/nonexistent/launchpad.zip', $text] as $path) {
			try {
				$service->import(zipPath: $path, preserveUuids: false, currentUserId: 'admin');
				$this->fail('expected a refusal');
			} catch (\InvalidArgumentException $e) {
				$this->assertSame('Uploaded file is not a valid ZIP archive', $e->getMessage());
			}
		}

		@unlink(filename: $text);
	}

	public function testCollisionResultShape(): void {
		$path = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-import-golden-');
		$zip = new ZipArchive();
		$zip->open(filename: $path, flags: ZipArchive::OVERWRITE);
		$zip->addFromString(name: 'manifest.json', content: '{"schemaVersion":1,"scope":"site"}');
		$zip->addFromString(name: 'dashboards/a.json', content: '{"uuid":"uuid-a","name":"A","widgets":[]}');
		$zip->close();

		$dashboards = $this->createMock(originalClassName: DashboardMapper::class);
		$dashboards->method('findByUuid')->willReturn(new Dashboard());
		$dashboards->expects($this->never())->method('insert');
		$service = new ImportService(
			dashboardMapper: $dashboards,
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			db: $this->createMock(originalClassName: IDBConnection::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);

		$result = $service->import(zipPath: $path, preserveUuids: true, currentUserId: 'admin');
		@unlink(filename: $path);

		$this->assertSame(['status', 'manifest', 'importedDashboardCount', 'skippedDashboardCount', 'errors', 'dashboards'], array_keys($result));
		$this->assertSame(['schemaVersion' => 1, 'scope' => 'site'], $result['manifest']);
		$this->assertSame([0, 0, []], [$result['importedDashboardCount'], $result['skippedDashboardCount'], $result['dashboards']]);
		$this->assertCount(1, $result['errors']);
		$this->assertSame($result['status'], $result['errors'][0]['type'] ?? $result['status']);
	}
}
