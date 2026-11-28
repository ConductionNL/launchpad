<?php

/**
 * SeedTileBrands: the brand tiles LaunchPad seeds, in one place.
 *
 * A default tile is the organisation's logo as a white SVG, without the
 * payoff, on the organisation's primary colour (REQ-DWB-007). The seed table
 * in DashboardService and the upgrade restyle both read it, so a seeded tile
 * and a restyled one cannot drift apart.
 *
 * @category  Support
 * @package   OCA\LaunchPad\Support
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

namespace OCA\LaunchPad\Support;

/**
 * The seeded brand tiles and what older versions seeded for them.
 *
 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-007-a-default-tile-is-the-brands-white-logo-on-the-brands-colour
 */
final class SeedTileBrands {
	/**
	 * The text and icon colour of every brand tile.
	 *
	 * @var string
	 */
	public const TEXT_COLOR = '#ffffff';

	/**
	 * Per tile title: the white logo under `img/` and the brand colour.
	 *
	 * @var array<string, array{icon: string, backgroundColor: string}>
	 */
	public const BRANDS = [
		'Conduction' => ['icon' => 'conduction-white.svg', 'backgroundColor' => '#21468B'],
		'Sendent'    => ['icon' => 'sendent-white.svg', 'backgroundColor' => '#141B62'],
		'Nextcloud'  => ['icon' => 'nextcloud-white.svg', 'backgroundColor' => '#0082C9'],
	];

	/**
	 * Per tile title: the icon older versions seeded, as a SQL LIKE pattern.
	 * A tile that still carries it was never changed by its owner.
	 *
	 * @var array<string, string>
	 */
	public const LEGACY_ICONS = [
		'Conduction' => '%conduction-logo.png',
		'Sendent'    => '%sendent-logo.png',
		'Nextcloud'  => 'icon-nextcloud',
	];
}//end class
