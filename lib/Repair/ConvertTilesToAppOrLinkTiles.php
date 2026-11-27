<?php
/**
 * ConvertTilesToAppOrLinkTiles
 *
 * Repair step: every app tile becomes an App or link tile in app mode, and
 * every tile without a mode a link tile, on every dashboard, template and
 * member's copy, groups and tabs included (REQ-APPTILE-006).
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @version GIT: <git-id>
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\TileModeNode;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Override;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Convert every stored placement to the App or link tile. Idempotent: a
 * placement with nothing left to convert is not written. Each placement is
 * updated in place, so its id, position, size, rules, style and personal
 * state stay as they were.
 *
 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
 */
class ConvertTilesToAppOrLinkTiles implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper $placements The placement store.
	 * @param TileModeNode          $tileMode   Converts a placement.
	 * @param LoggerInterface       $logger     PSR-3 logger.
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placements,
		private readonly TileModeNode $tileMode,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
	 */
	#[Override]
	public function getName(): string {
		return 'Turn app tiles and link tiles into App or link tiles';
	}//end getName()

	/**
	 * Run the step. A failure is a warning, never a failed upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-existing-app-tiles-and-link-tiles-become-app-or-link-tiles-req-apptile-006
	 */
	#[Override]
	public function run(IOutput $output): void {
		$placements = 0;
		$tiles      = 0;
		try {
			foreach ($this->placements->findAll() as $placement) {
				$count = $this->tileMode->convertPlacement(placement: $placement);
				if ($count === 0) {
					continue;
				}

				$this->placements->update(entity: $placement);
				$placements++;
				$tiles += $count;
			}
		} catch (Throwable $e) {
			$this->logger->warning(message: '[ConvertTilesToAppOrLinkTiles] could not convert the tiles: ' . $e->getMessage());
			$output->warning(message: 'The tiles were not turned into App or link tiles: ' . $e->getMessage());
			return;
		}

		$output->info(message: sprintf('%d tile(s) in %d placement(s) became App or link tiles.', $tiles, $placements));
	}//end run()
}//end class
