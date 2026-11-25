<?php

/**
 * LaunchPad Store Registry Banner.
 *
 * The connected register as the marketplace banner names it (REQ-STORE-010):
 * its name and its address, never its token.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Name and address of the configured register, for the marketplace banner.
 *
 * The address is the configured base URL without credentials, query or
 * fragment; the name is the one the administrator gave, else the host. The
 * token is never an input, so it can never ride along.
 */
final class StoreRegistryBanner {
	/**
	 * Build the banner from the stored URL and name.
	 *
	 * @param string $url  The configured register base URL.
	 * @param string $name The configured display name, may be empty.
	 *
	 * @return array{name: string, url: string}|null Null when no usable URL is configured.
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-010-the-marketplace-shows-dashboard-templates-as-cards
	 */
	public function from(string $url, string $name): ?array {
		$parts = parse_url(url: trim($url));
		if (is_array(value: $parts) === false || isset($parts['host']) === false) {
			return null;
		}

		$address = ($parts['scheme'] ?? 'https').'://'.$parts['host'];
		if (isset($parts['port']) === true) {
			$address .= ':'.$parts['port'];
		}

		$address .= ($parts['path'] ?? '');

		$name = trim($name);
		if ($name === '') {
			$name = $parts['host'];
		}

		return ['name' => $name, 'url' => $address];
	}//end from()
}//end class
