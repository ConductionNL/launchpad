<?php

/**
 * TextTableValidator
 *
 * Server-side grid check for the `text` widget's table mode, the same rules
 * the shared editor applies before save (nextcloud-vue `validateTable`).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/text-display-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Validates `text` widget table content before a placement is saved.
 *
 * A table is a full grid: every row holds one cell per column (a cell
 * covered by a neighbour's span is an empty placeholder), the column count
 * is `columnAlignments`' length, and no span reaches past the grid.
 *
 * @spec openspec/specs/text-display-widget/spec.md
 */
class TextTableValidator {

	/**
	 * Validate a text placement's table, when it is in table mode.
	 *
	 * @param array $content The placement content.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the grid is not rectangular or a span leaves it.
	 *
	 * @spec openspec/specs/text-display-widget/spec.md
	 */
	public function validate(array $content): void {
		if (($content['tableMode'] ?? false) !== true) {
			return;
		}

		$table = ($content['tableData'] ?? null);
		if (is_array($table) === false) {
			$table = [];
		}

		$rows = ($table['rows'] ?? null);
		if (is_array($rows) === false || $rows === []) {
			throw new InvalidArgumentException(message: 'Table must have at least one row');
		}

		$alignments = ($table['columnAlignments'] ?? null);
		if (is_array($alignments) === false || $alignments === []) {
			throw new InvalidArgumentException(message: 'Table must have at least one column');
		}

		$columns = count($alignments);
		$rowCount = count($rows);
		foreach (array_values($rows) as $rowIndex => $row) {
			if (is_array($row) === false || count($row) !== $columns) {
				throw new InvalidArgumentException(message: 'Grid is not rectangular');
			}

			foreach (array_values($row) as $colIndex => $cell) {
				if ($rowIndex + $this->span(cell: $cell, key: 'rowSpan') > $rowCount
					|| $colIndex + $this->span(cell: $cell, key: 'colSpan') > $columns
				) {
					throw new InvalidArgumentException(message: 'Cell span exceeds grid bounds');
				}
			}
		}
	}//end validate()

	/**
	 * A cell's row or column span, 1 when absent or not a positive integer.
	 *
	 * @param mixed  $cell The cell.
	 * @param string $key  `rowSpan` or `colSpan`.
	 *
	 * @return int The span.
	 *
	 * @spec openspec/specs/text-display-widget/spec.md
	 */
	private function span(mixed $cell, string $key): int {
		$span = 1;
		if (is_array($cell) === true) {
			$span = ($cell[$key] ?? 1);
		}

		if (is_int($span) === false || $span < 1) {
			return 1;
		}

		return $span;
	}//end span()
}//end class
