<?php
/**
 * Launchpad's entry under Nextcloud's administration settings: one line and
 * a link to the administration page (REQ-ADM-031).
 *
 * SPDX-FileCopyrightText: 2024 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @var array $_ Template parameters: `adminPageUrl`.
 * @var \OCP\IL10N $l The app's translations.
 */

// Nextcloud hands a template its parameters as `$_`.
$adminPageUrl = (string) $_['adminPageUrl']; // phpcs:ignore Squiz.NamingConventions.ValidVariableName.NotCamelCaps
?>

<div id="launchpad-admin-settings" class="section" data-testid="launchpad-admin-settings-link">
	<p><?php p($l->t('The Launchpad administration is on a page of its own.')); ?></p>
	<p>
		<a class="button primary" href="<?php p($adminPageUrl); ?>"><?php p($l->t('Open Launchpad administration')); ?></a>
	</p>
</div>
