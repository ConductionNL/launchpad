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
}
