<?php
/**
 * Kiosk player page template.
 *
 * Renders the mount point consumed by `src/kiosk.js`, which boots the
 * full-screen `KioskPlayerView`. No login is required; the player reads the
 * playlist token from its URL and fetches the playlist as JSON from the same
 * `/kiosk/{token}` route.
 *
 * @category Template
 * @package  OCA\LaunchPad
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

\OCP\Util::addScript('launchpad', 'launchpad-kiosk');
\OCP\Util::addStyle('launchpad', 'launchpad');
?>

<div id="app-kiosk" class="launchpad-kiosk">
    <div id="kiosk-vue"></div>
</div>
