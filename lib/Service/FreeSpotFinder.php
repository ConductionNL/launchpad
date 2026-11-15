<?php

/**
 * FreeSpotFinder
 *
 * Finds where a new widget goes on a dashboard grid: the spot asked for when
 * it is free, else the first free spot, scanning row by row from the top
 * left, so a new widget never lands over another one
 * (building-edit-a-dashboard-together, task 1.3).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Places a rectangle on a grid without overlapping the occupied ones.
 *
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */
class FreeSpotFinder {

	/**
	 * The spot for a widget of a given size.
	 *
	 * @param array<int,array{gridX:int,gridY:int,gridWidth:int,gridHeight:int}> $occupied The saved widgets.
	 * @param int                                                                $columns  The grid's column count.
	 * @param array{gridX:int,gridY:int,gridWidth:int,gridHeight:int}            $wanted   The spot asked for and the size.
	 *
	 * @return array{gridX:int,gridY:int,gridWidth:int,gridHeight:int} The spot, the width kept within the grid.
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
	 */
	public function place(array $occupied, int $columns, array $wanted): array {
		$columns = max(1, $columns);
		$width   = min(max(1, $wanted['gridWidth']), $columns);
		$height  = max(1, $wanted['gridHeight']);
		$spot    = ['gridX' => max(0, $wanted['gridX']), 'gridY' => max(0, $wanted['gridY']), 'gridWidth' => $width, 'gridHeight' => $height];

		if ($spot['gridX'] + $width <= $columns && $this->isFree(occupied: $occupied, spot: $spot) === true) {
			return $spot;
		}

		$bottom = 0;
		foreach ($occupied as $rect) {
			$bottom = max($bottom, $rect['gridY'] + $rect['gridHeight']);
		}

		// Every row up to the bottom of the lowest widget is tried; the row
		// just below it is always free.
		for ($y = 0; $y <= $bottom; $y++) {
			for ($x = 0; $x + $width <= $columns; $x++) {
				$candidate = ['gridX' => $x, 'gridY' => $y, 'gridWidth' => $width, 'gridHeight' => $height];
				if ($this->isFree(occupied: $occupied, spot: $candidate) === true) {
					return $candidate;
				}
			}
		}

		return ['gridX' => 0, 'gridY' => $bottom, 'gridWidth' => $width, 'gridHeight' => $height];
	}//end place()

	/**
	 * Whether a spot overlaps none of the occupied rectangles.
	 *
	 * @param array<int,array{gridX:int,gridY:int,gridWidth:int,gridHeight:int}> $occupied The saved widgets.
	 * @param array{gridX:int,gridY:int,gridWidth:int,gridHeight:int}            $spot     The spot.
	 *
	 * @return bool
	 */
	private function isFree(array $occupied, array $spot): bool {
		foreach ($occupied as $rect) {
			if ($spot['gridX'] < $rect['gridX'] + $rect['gridWidth']
				&& $rect['gridX'] < $spot['gridX'] + $spot['gridWidth']
				&& $spot['gridY'] < $rect['gridY'] + $rect['gridHeight']
				&& $rect['gridY'] < $spot['gridY'] + $spot['gridHeight']
			) {
				return false;
			}
		}

		return true;
	}//end isFree()
}//end class
