<?php

/**
 * TabularWriterTest
 *
 * CSV and XLSX of scheduled exports (REQ-SCH-003): headers and rows,
 * delimiter options, and a workbook with a cover sheet plus one sheet per
 * widget.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
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

namespace Unit\Service\ScheduledExport;

use OCA\LaunchPad\Service\ScheduledExport\TabularWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\TabularWriter
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-22
 */
class TabularWriterTest extends TestCase {

	private const CHART = ['title' => 'Bezwaren per maand', 'headers' => ['maand', 'aantal'], 'rows' => [['jan', 12], ['feb', 9]]];

	/**
	 * One table: headers then rows, no title line.
	 *
	 * @return void
	 */
	public function testCsvOfOneTableHasHeadersAndRows(): void {
		$this->assertSame("maand,aantal\njan,12\nfeb,9\n", (new TabularWriter())->csv(tables: [self::CHART]));
	}//end testCsvOfOneTableHasHeadersAndRows()

	/**
	 * Two tables: each block titled, a blank line between; the delimiter and quote options apply.
	 *
	 * @return void
	 */
	public function testCsvOfTwoTablesWithOptions(): void {
		$other = ['title' => 'Status', 'headers' => ['naam', 'open'], 'rows' => [['a;b', true]]];
		$csv   = (new TabularWriter())->csv(tables: [self::CHART, $other], options: ['delimiter' => ';', 'quoteChar' => "'"]);

		$this->assertSame("'Bezwaren per maand'\nmaand;aantal\njan;12\nfeb;9\n\nStatus\nnaam;open\n'a;b';true\n", $csv);
	}//end testCsvOfTwoTablesWithOptions()

	/**
	 * The workbook has a cover sheet and one sheet per widget, named after it, with the cells.
	 *
	 * @return void
	 */
	public function testXlsxHasCoverAndOneSheetPerWidget(): void {
		$widgets = [];
		for ($i = 1; $i <= 5; $i++) {
			$widgets[] = ['title' => 'Widget ' . $i, 'headers' => ['k'], 'rows' => [[$i]]];
		}

		$widgets[4]['title'] = 'Widget 1';
		$sheets = $this->sheets(bytes: (new TabularWriter())->xlsx(tables: $widgets, cover: ['name' => 'CBS & co', 'runAt' => '2026-12-01T08:00:00+01:00', 'sheetPerWidget' => true]));

		$this->assertSame(['Cover', 'Widget 1', 'Widget 2', 'Widget 3', 'Widget 4', 'Widget 1 2'], array_keys($sheets));
		$this->assertStringContainsString('<t xml:space="preserve">CBS &amp; co</t>', $sheets['Cover']);
		$this->assertStringContainsString('2026-12-01T08:00:00+01:00', $sheets['Cover']);
		$this->assertStringContainsString('<c r="A2"><v>3</v></c>', $sheets['Widget 3']);
	}//end testXlsxHasCoverAndOneSheetPerWidget()

	/**
	 * Without sheetPerWidget every table lands on one Data sheet.
	 *
	 * @return void
	 */
	public function testXlsxWithoutSheetPerWidgetHasOneDataSheet(): void {
		$sheets = $this->sheets(bytes: (new TabularWriter())->xlsx(tables: [self::CHART], cover: ['name' => 'x', 'runAt' => 't', 'sheetPerWidget' => false]));

		$this->assertSame(['Cover', 'Data'], array_keys($sheets));
		$this->assertStringContainsString('Bezwaren per maand', $sheets['Data']);
		$this->assertStringContainsString('<c r="B4"><v>9</v></c>', $sheets['Data']);
	}//end testXlsxWithoutSheetPerWidgetHasOneDataSheet()

	/**
	 * The sheets of a workbook by name.
	 *
	 * @param string $bytes The workbook.
	 *
	 * @return array<string, string> Sheet name to its XML.
	 */
	private function sheets(string $bytes): array {
		$path = tempnam(sys_get_temp_dir(), 'lpt');
		file_put_contents($path, $bytes);
		$zip = new ZipArchive();
		$this->assertTrue($zip->open($path));
		preg_match_all('/<sheet name="([^"]+)" sheetId="(\d+)"/', (string)$zip->getFromName('xl/workbook.xml'), $m, PREG_SET_ORDER);
		$this->assertNotFalse($zip->getFromName('[Content_Types].xml'));

		$sheets = [];
		foreach ($m as [$all, $name, $id]) {
			$sheets[html_entity_decode($name)] = (string)$zip->getFromName('xl/worksheets/sheet' . $id . '.xml');
		}

		$zip->close();
		unlink($path);
		return $sheets;
	}//end sheets()
}//end class
