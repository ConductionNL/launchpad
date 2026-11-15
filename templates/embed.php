<?php
/**
 * Embed page template (embedded-analytics task 9).
 *
 * The bare page an iframe shows for an embed token: the tenant theme's :root
 * block and the mount point `src/embed-entry.js` renders the subject into;
 * the entry reads the subject from the page's own path. The subject is fetched from `/embed/{subjectType}/{subjectId}/data` with the
 * same token; nothing here depends on a Nextcloud login.
 *
 * @category Template
 * @package  OCA\LaunchPad
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @var array{subjectType: string, subjectId: string, themeCss: string} $_
 */

\OCP\Util::addScript('launchpad', 'launchpad-embed');
\OCP\Util::addStyle('launchpad', 'launchpad');
?>
<?php if ($_['themeCss'] !== '') : ?>
<style><?php print_unescaped(str_ireplace('</style', '<\/style', $_['themeCss'])); ?></style>
<?php endif; ?>
<div id="app-embed" class="launchpad-embed">
	<div id="embed-vue"></div>
</div>
