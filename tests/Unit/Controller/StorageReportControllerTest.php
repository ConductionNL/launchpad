<?php

/**
 * StorageReportController Test
 *
 * REQ-DWMS-010: the report and its CSV for an administrator, 403 for anybody else.
 *
 * @category  Test
 * @package   Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\StorageReportController;
use OCA\LaunchPad\Service\StorageReport\StorageReportRefusedException;
use OCA\LaunchPad\Service\StorageReport\StorageReportService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StorageReportController::class)]
#[UsesClass(StorageReportService::class)]
#[UsesClass(StorageReportRefusedException::class)]
class StorageReportControllerTest extends TestCase {

	private const REPORT = [
		'date'       => '2026-11-15',
		'total'      => 10,
		'series'     => ['2026-11' => 10],
		'workspaces' => [],
		'services'   => [['name' => 'files', 'bytes' => 10, 'series' => ['2026-11' => 10]]],
		'registers'  => null,
		'removable'  => ['total' => 0, 'pastRetention' => ['bytes' => 0], 'orphanedUploads' => ['bytes' => 0]],
	];

	private function controller(?string $userId, StorageReportService $storage): StorageReportController {
		return new StorageReportController($this->createMock(IRequest::class), $storage, $userId);
	}

	public function testAnAdministratorGetsTheReport(): void {
		$storage = $this->createMock(StorageReportService::class);
		$storage->expects($this->once())->method('report')->with('admin')->willReturn(self::REPORT);

		$response = $this->controller('admin', $storage)->report();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(self::REPORT, $response->getData());
	}

	public function testAnybodyElseIsRefusedWith403OnTheReportAndTheExport(): void {
		$storage = $this->createMock(StorageReportService::class);
		$storage->method('report')->willThrowException(new StorageReportRefusedException('Only an administrator can read the storage report.'));
		$controller = $this->controller('anna', $storage);

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->report()->getStatus());
		$export = $controller->export();
		$this->assertNotInstanceOf(DataDisplayResponse::class, $export);
		$this->assertSame(Http::STATUS_FORBIDDEN, $export->getStatus());
	}

	public function testWithoutASignedInUserTheServiceIsAskedForNobody(): void {
		$storage = $this->createMock(StorageReportService::class);
		$storage->expects($this->once())->method('report')->with('')->willThrowException(new StorageReportRefusedException('no'));

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller(null, $storage)->report()->getStatus());
	}

	public function testTheExportIsTheReportAsCsv(): void {
		$storage = $this->createMock(StorageReportService::class);
		$storage->method('report')->willReturn(self::REPORT);

		$response = $this->controller('admin', $storage)->export();

		$this->assertInstanceOf(DataDisplayResponse::class, $response);
		$this->assertSame(StorageReportService::csv(self::REPORT), $response->getData());
		// getHeaders() asks the server for its security headers; the set headers are read directly.
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($response);
		$this->assertSame('attachment; filename="storage-2026-11-15.csv"', $headers['Content-Disposition']);
		$this->assertSame('text/csv; charset=utf-8', $headers['Content-Type']);
	}
}
