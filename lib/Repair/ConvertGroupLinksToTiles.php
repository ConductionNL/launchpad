<?php
/**
 * ConvertGroupLinksToTiles
 *
 * Repair step: the address links stored inside groups become tile nodes, so
 * every tile in a group is a full tile the tile form edits (REQ-TILE-041).
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
use OCA\LaunchPad\Service\GroupTileNode;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Turn the address links of every stored group into tile nodes. Idempotent:
 * a group with no address links left is not written again.
 *
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */
class ConvertGroupLinksToTiles implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper $placements The placement store.
	 * @param GroupTileNode         $tileNode   Turns a group's address links into tiles.
	 * @param LoggerInterface       $logger     PSR-3 logger.
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placements,
		private readonly GroupTileNode $tileNode,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
	 */
	public function getName(): string {
		return 'Turn the links inside tile groups into tiles';
	}//end getName()

	/**
	 * Run the step. A failure is a warning, never a failed upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
	 */
	public function run(IOutput $output): void {
		$groups = 0;
		$tiles  = 0;
		try {
			foreach ($this->placements->findByWidgetId(widgetId: 'container') as $placement) {
				$result = $this->tileNode->convertGroup(content: $placement->getContentArray());
				if ($result['changed'] === 0) {
					continue;
				}

				$placement->setContentArray(content: $result['content']);
				$this->placements->update(entity: $placement);
				$groups++;
				$tiles += $result['changed'];
			}
		} catch (Throwable $e) {
			$this->logger->warning(message: '[ConvertGroupLinksToTiles] could not turn the group links into tiles: ' . $e->getMessage());
			$output->warning(message: 'The links inside tile groups were not turned into tiles: ' . $e->getMessage());
			return;
		}

		$output->info(message: sprintf('%d link(s) in %d group(s) became tiles.', $tiles, $groups));
	}//end run()
}//end class
