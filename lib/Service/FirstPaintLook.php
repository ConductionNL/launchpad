<?php

/**
 * FirstPaintLook
 *
 * The dashboard's own look (REQ-THM-001) on the first-paint shell's region
 * (first-paint-skeleton D3): the same declarations src/utils/dashboardLook.js
 * `lookStyle` draws on the mounted region: the accent as Nextcloud's primary
 * colour variables, the background colour with black or white text, and the
 * background image of a LaunchPad resource. Only normalised colours and
 * checked resource names go in, so nothing else can reach the attribute.
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
 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Builds the inline style of the shell's region.
 *
 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
 */
class FirstPaintLook {

	/**
	 * Constructor.
	 *
	 * @param DashboardLook $look The contrast rules the server enforces.
	 */
	public function __construct(
		private readonly DashboardLook $look=new DashboardLook(),
	) {
	}//end __construct()

	/**
	 * The inline style: '' for a dashboard that follows the theme.
	 *
	 * @param array<string, mixed> $dashboard   The dashboard (themeAccent, themeBackgroundColor, themeBackgroundResource).
	 * @param string               $resourceUrl The resource route without the file name, ending in `/`.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
	 */
	public function style(array $dashboard, string $resourceUrl): string {
		$style  = '';
		$accent = $this->hexOrNull(colour: $dashboard['themeAccent'] ?? null);
		if ($accent !== null) {
			$style .= '--color-primary-element:'.$accent.';--color-primary-element-text:'.$this->look->textColorFor(background: $accent).';';
		}

		$background = $this->hexOrNull(colour: $dashboard['themeBackgroundColor'] ?? null);
		if ($background !== null) {
			$text   = $this->look->textColorFor(background: $background);
			$style .= 'background-color:'.$background.';color:'.$text.';--color-main-text:'.$text.';';
		}

		return $style.$this->imageStyle(resource: (string) ($dashboard['themeBackgroundResource'] ?? ''), resourceUrl: $resourceUrl);
	}//end style()

	/**
	 * The background image of a LaunchPad resource, '' for none or another name.
	 *
	 * @param string $resource    The resource file name.
	 * @param string $resourceUrl The resource route without the file name.
	 *
	 * @return string
	 */
	private function imageStyle(string $resource, string $resourceUrl): string {
		if ($resource === '' || $resourceUrl === '' || $this->look->isResourceName(name: $resource) === false) {
			return '';
		}

		return 'background-image:url("'.$resourceUrl.rawurlencode($resource).'");background-size:cover;background-position:center;';
	}//end imageStyle()

	/**
	 * A colour as lower-case `#rrggbb`, or null when it is not a hex colour.
	 *
	 * @param mixed $colour The stored colour.
	 *
	 * @return string|null
	 */
	private function hexOrNull(mixed $colour): ?string {
		if (is_string($colour) === false || trim($colour) === '') {
			return null;
		}

		try {
			return $this->look->normaliseHex(colour: $colour, field: 'colour');
		} catch (InvalidArgumentException) {
			return null;
		}
	}//end hexOrNull()
}//end class
