<?php

/**
 * FirstPaintFill
 *
 * A dashboard that fills the height below the top bar (LpVandaag) in the
 * first-paint shell (first-paint-skeleton D3): whether the shown placements
 * ask for it, and the row tracks nextcloud-vue's CnDashboardGrid draws for
 * it in view mode (`fillRowTracks`): `auto` for a row whose frames all fit
 * their rows, or that no frame covers, and an equal share of the free height
 * for every other row. A row a fit-row frame covers is also `auto` when each
 * other frame on it reaches into a row no fit-row frame covers. One fixture
 * (tests/fixtures/first-paint-fill-tracks.json) runs against both.
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
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The fill-height twin of CnDashboardGrid's `fillRowTracks`.
 *
 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
 */
class FirstPaintFill {

	/**
	 * A row that takes the height of its content.
	 */
	public const FIT = 'auto';

	/**
	 * A row that takes an equal share of the free height.
	 */
	public const SHARE = 'minmax(0,1fr)';

	/**
	 * Whether a placement asks the dashboard to fill the height (`content.fillHeight`).
	 *
	 * @param list<array<string, mixed>> $placements The shown placements.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
	 */
	public function fills(array $placements): bool {
		foreach ($placements as $placement) {
			if (($placement['content']['fillHeight'] ?? false) === true) {
				return true;
			}
		}

		return false;
	}//end fills()

	/**
	 * One track per row, as the filled grid draws them.
	 *
	 * @param list<array<string, mixed>> $frames The frames, each with `cell` (x, y, w, h) and `fitRows`.
	 *
	 * @return list<string> FIT or SHARE per row.
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
	 */
	public function tracks(array $frames): array {
		$rows = 0;
		foreach ($frames as $frame) {
			$rows = max($rows, ($frame['cell']['y'] + $frame['cell']['h']));
		}

		$fitRow = [];
		for ($row = 0; $row < $rows; $row++) {
			$fitRow[$row] = $this->coveredByFit(frames: $frames, row: $row);
		}

		$tracks = [];
		for ($row = 0; $row < $rows; $row++) {
			$tracks[] = $this->track(frames: $frames, row: $row, fitRow: $fitRow);
		}

		return $tracks;
	}//end tracks()

	/**
	 * The track of one row: FIT when every other frame on it is absent, or
	 * when it is a fit row and each other frame takes its share elsewhere.
	 *
	 * @param list<array<string, mixed>> $frames The frames.
	 * @param int                        $row    The row.
	 * @param array<int, bool>           $fitRow Per row, whether a fit-row frame covers it.
	 *
	 * @return string
	 */
	private function track(array $frames, int $row, array $fitRow): string {
		foreach ($frames as $frame) {
			if ($frame['fitRows'] === true || $this->covers(frame: $frame, row: $row) === false) {
				continue;
			}

			if ($fitRow[$row] === false || $this->stretchesElsewhere(frame: $frame, fitRow: $fitRow) === false) {
				return self::SHARE;
			}
		}

		return self::FIT;
	}//end track()

	/**
	 * Whether a fit-row frame covers the row.
	 *
	 * @param list<array<string, mixed>> $frames The frames.
	 * @param int                        $row    The row.
	 *
	 * @return bool
	 */
	private function coveredByFit(array $frames, int $row): bool {
		foreach ($frames as $frame) {
			if ($frame['fitRows'] === true && $this->covers(frame: $frame, row: $row) === true) {
				return true;
			}
		}

		return false;
	}//end coveredByFit()

	/**
	 * Whether a frame covers the row.
	 *
	 * @param array<string, mixed> $frame The frame.
	 * @param int                  $row   The row.
	 *
	 * @return bool
	 */
	private function covers(array $frame, int $row): bool {
		return $row >= $frame['cell']['y'] && $row < ($frame['cell']['y'] + $frame['cell']['h']);
	}//end covers()

	/**
	 * Whether a frame reaches into a row that no fit-row frame covers.
	 *
	 * @param array<string, mixed> $frame  The frame.
	 * @param array<int, bool>     $fitRow Per row, whether a fit-row frame covers it.
	 *
	 * @return bool
	 */
	private function stretchesElsewhere(array $frame, array $fitRow): bool {
		for ($row = $frame['cell']['y']; $row < ($frame['cell']['y'] + $frame['cell']['h']); $row++) {
			if (($fitRow[$row] ?? false) === false) {
				return true;
			}
		}

		return false;
	}//end stretchesElsewhere()
}//end class
