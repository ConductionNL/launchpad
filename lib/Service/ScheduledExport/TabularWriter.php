<?php

/**
 * TabularWriter
 *
 * The tabular formats of a render (REQ-SCH-003): CSV of the subject's
 * tables, and an XLSX workbook with a cover sheet and, with
 * `sheetPerWidget`, one sheet per widget. Written without a spreadsheet
 * library: the workbook is the minimal SpreadsheetML package.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

use ZipArchive;

/**
 * CSV and XLSX from `{title, headers, rows}` tables.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-003-multi-format-render-from-a-single-fire
 */
class TabularWriter {

	private const MIME_SHEET = 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml';

	private const MIME_WORKBOOK = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml';

	/**
	 * CSV: one block per table, a blank line between blocks; with more than
	 * one table each block starts with the table's title.
	 *
	 * @param list<array{title: string, headers: list<string>, rows: list<list<mixed>>}> $tables  The tables.
	 * @param array<string, mixed>                                                       $options `delimiter`, `quoteChar`.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-003-multi-format-render-from-a-single-fire
	 */
	public function csv(array $tables, array $options = []): string {
		$delimiter = $this->char(value: $options['delimiter'] ?? null, default: ',');
		$quote     = $this->char(value: $options['quoteChar'] ?? null, default: '"');
		$handle    = fopen('php://temp', 'r+');
		foreach ($tables as $index => $table) {
			if ($index > 0) {
				fwrite($handle, "\n");
			}

			if (count($tables) > 1) {
				fputcsv($handle, [$table['title']], $delimiter, $quote, '');
			}

			fputcsv($handle, $table['headers'], $delimiter, $quote, '');
			foreach ($table['rows'] as $row) {
				fputcsv($handle, array_map(fn ($cell): string => $this->text(value: $cell), $row), $delimiter, $quote, '');
			}
		}

		rewind($handle);
		$csv = (string)stream_get_contents($handle);
		fclose($handle);

		return $csv;
	}//end csv()

	/**
	 * XLSX: a `Cover` sheet with the export name and run time, then one sheet
	 * per table (`sheetPerWidget`, the default) or one `Data` sheet holding
	 * every table.
	 *
	 * @param list<array{title: string, headers: list<string>, rows: list<list<mixed>>}> $tables  The tables.
	 * @param array{name: string, runAt: string, sheetPerWidget: bool}                  $cover   The cover and the sheet layout.
	 *
	 * @return string The workbook bytes.
	 *
	 * @throws RenderException When the package cannot be written.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-003-multi-format-render-from-a-single-fire
	 */
	public function xlsx(array $tables, array $cover): string {
		$sheets = [['name' => 'Cover', 'rows' => [['Export', $cover['name']], ['Run at', $cover['runAt']]]]];
		if ($cover['sheetPerWidget'] === true) {
			foreach ($tables as $table) {
				$sheets[] = ['name' => $table['title'], 'rows' => array_merge([$table['headers']], $table['rows'])];
			}
		} else {
			$rows = [];
			foreach ($tables as $table) {
				$rows = array_merge($rows, [[$table['title']], $table['headers']], $table['rows'], [[]]);
			}

			$sheets[] = ['name' => 'Data', 'rows' => $rows];
		}

		return $this->package(sheets: $this->uniqueNames(sheets: $sheets));
	}//end xlsx()

	/**
	 * The SpreadsheetML zip of the sheets.
	 *
	 * @param list<array{name: string, rows: list<list<mixed>>}> $sheets The sheets, names already valid.
	 *
	 * @return string
	 *
	 * @throws RenderException When the package cannot be written.
	 */
	private function package(array $sheets): string {
		$path = tempnam(sys_get_temp_dir(), 'lpx');
		$zip  = new ZipArchive();
		if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
			throw new RenderException('The workbook could not be written');
		}

		$ns        = 'http://schemas.openxmlformats.org/';
		$rel       = $ns . 'officeDocument/2006/relationships';
		$head      = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
		$overrides = '';
		$entries   = '';
		$rels      = '';
		foreach ($sheets as $index => $sheet) {
			$n          = $index + 1;
			$part       = 'worksheets/sheet' . $n . '.xml';
			$overrides .= '<Override PartName="/xl/' . $part . '" ContentType="' . self::MIME_SHEET . '"/>';
			$entries   .= '<sheet name="' . $this->xml(value: $sheet['name']) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$rels      .= '<Relationship Id="rId' . $n . '" Type="' . $rel . '/worksheet" Target="' . $part . '"/>';
			$zip->addFromString('xl/' . $part, $this->sheetXml(rows: $sheet['rows']));
		}

		$types = '<Types xmlns="' . $ns . 'package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="' . self::MIME_WORKBOOK . '"/>' . $overrides . '</Types>';
		$zip->addFromString('[Content_Types].xml', $head . $types);
		$zip->addFromString(
			'_rels/.rels',
			$head . '<Relationships xmlns="' . $ns . 'package/2006/relationships">'
				. '<Relationship Id="rId1" Type="' . $rel . '/officeDocument" Target="xl/workbook.xml"/></Relationships>'
		);
		$zip->addFromString(
			'xl/workbook.xml',
			$head . '<workbook xmlns="' . $ns . 'spreadsheetml/2006/main" xmlns:r="' . $rel . '"><sheets>' . $entries . '</sheets></workbook>'
		);
		$zip->addFromString(
			'xl/_rels/workbook.xml.rels',
			$head . '<Relationships xmlns="' . $ns . 'package/2006/relationships">' . $rels . '</Relationships>'
		);
		$zip->close();

		$bytes = (string)file_get_contents($path);
		unlink($path);

		return $bytes;
	}//end package()

	/**
	 * One worksheet: numbers as numbers, everything else as inline strings.
	 *
	 * @param list<list<mixed>> $rows The rows.
	 *
	 * @return string
	 */
	private function sheetXml(array $rows): string {
		$xml = '';
		foreach ($rows as $r => $row) {
			$cells = '';
			foreach (array_values($row) as $c => $value) {
				$ref = $this->column(index: $c) . ($r + 1);
				if (is_int($value) === true || is_float($value) === true) {
					$cells .= '<c r="' . $ref . '"><v>' . $value . '</v></c>';
					continue;
				}

				$text   = $this->xml(value: $this->text(value: $value));
				$cells .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
			}

			$xml .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
		}

		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $xml . '</sheetData></worksheet>';
	}//end sheetXml()

	/**
	 * Sheet names Excel accepts: no `[]:*?/\`, at most 31 characters, unique.
	 *
	 * @param list<array{name: string, rows: list<list<mixed>>}> $sheets The sheets.
	 *
	 * @return list<array{name: string, rows: list<list<mixed>>}>
	 */
	private function uniqueNames(array $sheets): array {
		$seen = [];
		foreach ($sheets as $index => $sheet) {
			$base = mb_substr(trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $sheet['name'])), 0, 31);
			if ($base === '') {
				$base = 'Sheet';
			}

			$name = $base;
			for ($k = 2; isset($seen[mb_strtolower($name)]) === true; $k++) {
				$name = mb_substr($base, 0, 31 - strlen((string)$k) - 1) . ' ' . $k;
			}

			$seen[mb_strtolower($name)] = true;
			$sheets[$index]['name']     = $name;
		}

		return $sheets;
	}//end uniqueNames()

	/**
	 * The spreadsheet column letters of a zero-based index.
	 *
	 * @param int $index The index.
	 *
	 * @return string
	 */
	private function column(int $index): string {
		$letters = '';
		for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
			$letters = chr(65 + (($n - 1) % 26)) . $letters;
		}

		return $letters;
	}//end column()

	/**
	 * A cell as text: scalars as is, booleans as true/false, the rest as JSON.
	 *
	 * @param mixed $value The cell.
	 *
	 * @return string
	 */
	private function text(mixed $value): string {
		if ($value === true) {
			return 'true';
		}

		if ($value === false) {
			return 'false';
		}

		if (is_scalar($value) === true || $value === null) {
			return (string)$value;
		}

		return (string)json_encode($value);
	}//end text()

	/**
	 * XML-escaped text.
	 *
	 * @param string $value The text.
	 *
	 * @return string
	 */
	private function xml(string $value): string {
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}//end xml()

	/**
	 * A one-character CSV option, or the default.
	 *
	 * @param mixed  $value   The option.
	 * @param string $default The default.
	 *
	 * @return string
	 */
	private function char(mixed $value, string $default): string {
		if (is_string($value) === true && strlen($value) === 1) {
			return $value;
		}

		return $default;
	}//end char()
}//end class
