<?php

/**
 * ConvertMultiTilesToWells Repair Step
 *
 * The multi-tile widget is retired (layout-widgets-replace-multi-tile D2b):
 * every stored multi-tile placement, also one nested in a well or a tab and
 * in templates and their copies, becomes a well that lists each viewer's
 * apps with the same settings. Each placement is updated in place, so its
 * id, cell, size, order, rules and style stay. Idempotent.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-5
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\MultiTileNode;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Turn every multi-tile placement into a well of the viewer's apps.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-5
 */
class ConvertMultiTilesToWells implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper $placements The placement store.
	 * @param MultiTileNode         $multiTile  Converts a placement.
	 * @param LoggerInterface       $logger     PSR-3 logger.
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placements,
		private readonly MultiTileNode $multiTile,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-5
	 */
	public function getName(): string {
		return 'Turn multi-tile widgets into wells of each viewer\'s apps';
	}//end getName()

	/**
	 * Run the step. A failure is a warning, never a failed upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-5
	 */
	public function run(IOutput $output): void {
		$converted = 0;
		try {
			foreach ($this->placements->findAll() as $placement) {
				if ($this->multiTile->convertPlacement(placement: $placement) === 0) {
					continue;
				}

				$this->placements->update(entity: $placement);
				$converted++;
			}
		} catch (Throwable $e) {
			$this->logger->warning(message: '[ConvertMultiTilesToWells] could not convert the multi-tiles: ' . $e->getMessage());
			$output->warning(message: 'The multi-tile widgets were not turned into wells: ' . $e->getMessage());
			return;
		}

		$output->info(message: sprintf('%d placement(s) with multi-tile widgets became wells.', $converted));
	}//end run()
}//end class
