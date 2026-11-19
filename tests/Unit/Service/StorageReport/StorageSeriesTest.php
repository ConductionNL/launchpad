<?php

/**
 * StorageSeries Test
 *
 * REQ-DWMS-010: one snapshot per day, kept in app data, oldest dropped.
 *
 * @category  Test
 * @package   Unit\Service\StorageReport
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

namespace Unit\Service\StorageReport;
use OCA\LaunchPad\Service\StorageReport\StorageSeries;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StorageSeries::class)]
class StorageSeriesTest extends TestCase {

	public function testWithoutAFileThereIsNoSeries(): void {
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willThrowException(new NotFoundException());

		$this->assertSame([], (new StorageSeries($appData))->all());
	}

	public function testTheSeriesIsReadOldestFirstAndUnreadableContentIsNone(): void {
		$file = $this->createMock(ISimpleFile::class);
		$file->method('getContent')->willReturnOnConsecutiveCalls('{"2026-11-02":{"total":2},"2026-11-01":{"total":1}}', 'not json');
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->with('series.json')->willReturn($file);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->with('storage-report')->willReturn($folder);
		$series = new StorageSeries($appData);

		$this->assertSame(['2026-11-01' => ['total' => 1], '2026-11-02' => ['total' => 2]], $series->all());
		$this->assertSame([], $series->all());
	}

	public function testTheFirstRecordMakesTheFolderAndTheFile(): void {
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->willThrowException(new NotFoundException());
		$folder->expects($this->once())->method('newFile')->with('series.json', '{"2026-11-15":{"total":5}}');
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willThrowException(new NotFoundException());
		$appData->expects($this->once())->method('newFolder')->with('storage-report')->willReturn($folder);

		$this->assertSame(['2026-11-15' => ['total' => 5]], (new StorageSeries($appData))->record('2026-11-15', ['total' => 5]));
	}

	public function testARecordReplacesThatDayAndKeepsAtMostTheNewestDays(): void {
		$days = [];
		for ($i = 0; $i < StorageSeries::DAYS; $i++) {
			$days[date('Y-m-d', 1_700_000_000 + ($i * 86400))] = ['total' => $i];
		}

		$last    = array_key_last($days);
		$written = null;
		$file    = $this->createMock(ISimpleFile::class);
		$file->method('getContent')->willReturn(json_encode($days));
		$file->expects($this->exactly(2))->method('putContent')->willReturnCallback(static function (string $content) use (&$written): void {
			$written = json_decode($content, true);
		});
		$folder = $this->createMock(ISimpleFolder::class);
		$folder->method('getFile')->willReturn($file);
		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->willReturn($folder);
		$series = new StorageSeries($appData);

		$series->record($last, ['total' => -1]);
		$this->assertCount(StorageSeries::DAYS, $written);
		$this->assertSame(['total' => -1], $written[$last]);

		$series->record('2099-01-01', ['total' => 7]);
		$this->assertCount(StorageSeries::DAYS, $written);
		$this->assertArrayNotHasKey(array_key_first($days), $written);
		$this->assertSame(['total' => 7], $written['2099-01-01']);
	}
}
