<?php

/**
 * KioskPlaylistDraft
 *
 * The caller-supplied content of a kiosk playlist: its name, its raw
 * entries and the requested refresh interval (REQ-KIOSK-002).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
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

namespace OCA\LaunchPad\Service;

/**
 * Name, raw entries and refresh interval of a playlist being created or
 * updated. The entries are validated and the interval clamped by
 * KioskService.
 */
final class KioskPlaylistDraft {
	/**
	 * Constructor
	 *
	 * @param string $name    The playlist name.
	 * @param array  $entries Raw entries [{dashboardUuid, dwellSeconds}, ...].
	 * @param int    $refresh Requested refresh interval in seconds.
	 */
	public function __construct(
		public readonly string $name,
		public readonly array $entries,
		public readonly int $refresh,
	) {
	}//end __construct()
}//end class
