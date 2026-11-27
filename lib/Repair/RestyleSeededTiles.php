<?php

/**
 * RestyleSeededTiles Repair Step
 *
 * Older versions seeded the Conduction and Sendent tiles with a white PNG on a
 * white tile and the Nextcloud tile with a CSS class no stylesheet defines, so
 * none of the three showed a logo. A seeded brand tile is now its white SVG
 * logo on the brand colour (REQ-DWB-007). This step gives every placement that
 * still carries the old seed the new look; a tile whose owner changed its
 * icon is kept.
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

use OCA\LaunchPad\Support\SeedTileBrands;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Restyle untouched seeded brand tiles to the white logo on the brand colour.
 *
 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-007-a-default-tile-is-the-brands-white-logo-on-the-brands-colour
 */
class RestyleSeededTiles implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param IDBConnection   $db     The database connection.
	 * @param IURLGenerator   $urls   Resolves the app's image paths.
	 * @param LoggerInterface $logger PSR-3 logger.
	 */
	public function __construct(
		private readonly IDBConnection $db,
		private readonly IURLGenerator $urls,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-007-a-default-tile-is-the-brands-white-logo-on-the-brands-colour
	 */
	public function getName(): string {
		return 'Show the seeded brand tiles as a white logo on the brand colour';
	}//end getName()

	/**
	 * Run the step. A failure is a warning, never a failed upgrade.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-007-a-default-tile-is-the-brands-white-logo-on-the-brands-colour
	 */
	public function run(IOutput $output): void {
		$restyled = 0;
		try {
			foreach (SeedTileBrands::BRANDS as $title => $brand) {
				$restyled += $this->restyle(
					title: $title,
					legacyIcon: SeedTileBrands::LEGACY_ICONS[$title],
					brand: $brand
				);
			}
		} catch (Throwable $e) {
			$this->logger->warning(message: '[RestyleSeededTiles] could not restyle the seeded tiles: ' . $e->getMessage());
			$output->warning(message: 'The seeded brand tiles were not restyled: ' . $e->getMessage());
			return;
		}

		$output->info(message: 'Restyled ' . $restyled . ' seeded brand tile(s).');
	}//end run()

	/**
	 * Restyle the placements of one brand that still carry the old seed icon.
	 *
	 * @param string                                       $title      The tile title.
	 * @param string                                       $legacyIcon The old icon, as a LIKE pattern.
	 * @param array{icon: string, backgroundColor: string} $brand      The new look.
	 *
	 * @return int The number of placements changed.
	 */
	private function restyle(string $title, string $legacyIcon, array $brand): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update(update: 'launchpad_widget_placements')
			->set(key: 'tile_icon', value: $qb->createNamedParameter(value: $this->urls->imagePath(appName: 'launchpad', file: $brand['icon'])))
			->set(key: 'tile_icon_type', value: $qb->createNamedParameter(value: 'url'))
			->set(key: 'tile_background_color', value: $qb->createNamedParameter(value: $brand['backgroundColor']))
			->set(key: 'tile_text_color', value: $qb->createNamedParameter(value: SeedTileBrands::TEXT_COLOR))
			->where($qb->expr()->eq(x: 'tile_type', y: $qb->createNamedParameter(value: 'preset')))
			->andWhere($qb->expr()->eq(x: 'tile_title', y: $qb->createNamedParameter(value: $title)))
			->andWhere($qb->expr()->like(x: 'tile_icon', y: $qb->createNamedParameter(value: $legacyIcon)));

		return $qb->executeStatement();
	}//end restyle()
}//end class
