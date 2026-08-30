<?php
/**
 * Anonymous public-share page template.
 *
 * Renders the Vue mount point consumed by `src/public.js`, which boots the
 * read-only `DashboardPublicShareView`. No Nextcloud login is required (the
 * page is served renderAs public); the SPA fetches the shared dashboard from
 * `/s/{token}/data` using the token in the URL.
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
?>

<div id="app-public-share" class="launchpad-public-share">
    <div id="public-share-vue"></div>
</div>
