<?php

/**
 * DemoSeedTileGaps
 *
 * Closes the holes that left-out tiles leave in the rows of a seeded
 * demo dashboard.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Move tiles left over the cells of left-out tiles.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedTileGaps
{
	/**
	 * Move the tiles right of a left-out tile one place left, so a row of
	 * tiles has no hole. A tile only moves within its section (the columns
	 * of the section heading above it) and never past another widget.
	 *
	 * @param array<int, array<string, mixed>> $placed The placed payloads.
	 * @param array<int, int>                  $tiles  The indexes of the tile payloads in $placed.
	 * @param array<int, array<string, int>>   $gaps   The cells of the left-out tiles, `{x, y, w}`.
	 *
	 * @return array<int, array<string, mixed>> The payloads with the gaps closed.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function close(array $placed, array $tiles, array $gaps): array {
		usort($gaps, static fn (array $a, array $b): int => $b['x'] <=> $a['x']);
		foreach ($gaps as $gap) {
			foreach ($tiles as $index) {
				$tileX = (int)$placed[$index]['gridX'];
				if ((int)$placed[$index]['gridY'] !== $gap['y'] || $tileX <= $gap['x']) {
					continue;
				}

				if ($this->sectionOf(placed: $placed, column: $tileX) !== $this->sectionOf(placed: $placed, column: $gap['x'])
					|| $this->widgetBetween(placed: $placed, tiles: $tiles, gap: $gap, toX: $tileX) === true
				) {
					continue;
				}

				$placed[$index]['gridX'] = $tileX - $gap['w'];
			}
		}

		return $placed;
	}//end close()

	/**
	 * The index of the section heading whose columns hold $column, -1 for none.
	 *
	 * @param array<int, array<string, mixed>> $placed The placed payloads.
	 * @param int                              $column The column.
	 *
	 * @return int The heading's index.
	 */
	private function sectionOf(array $placed, int $column): int {
		foreach ($placed as $index => $payload) {
			$left = (int)($payload['gridX'] ?? 0);
			if (($payload['widgetId'] ?? '') === 'section-heading' && $column >= $left && $column < $left + (int)($payload['gridWidth'] ?? 1)) {
				return $index;
			}
		}

		return -1;
	}//end sectionOf()

	/**
	 * Whether a widget other than a tile covers a cell of the gap's row between the gap and column $toX.
	 *
	 * @param array<int, array<string, mixed>> $placed The placed payloads.
	 * @param array<int, int>                  $tiles  The indexes of the tile payloads.
	 * @param array<string, int>               $gap    The gap, `{x, y, w}`.
	 * @param int                              $toX    The tile's column.
	 *
	 * @return bool True when another widget sits in between.
	 */
	private function widgetBetween(array $placed, array $tiles, array $gap, int $toX): bool {
		foreach ($placed as $index => $payload) {
			if (in_array($index, $tiles, true) === true) {
				continue;
			}

			$left = (int)($payload['gridX'] ?? 0);
			$top  = (int)($payload['gridY'] ?? 0);
			$overlapsColumns = $left < $toX && $left + (int)($payload['gridWidth'] ?? 1) > $gap['x'];
			$overlapsRow     = $gap['y'] >= $top && $gap['y'] < $top + (int)($payload['gridHeight'] ?? 1);
			if ($overlapsColumns === true && $overlapsRow === true) {
				return true;
			}
		}

		return false;
	}//end widgetBetween()
}//end class
