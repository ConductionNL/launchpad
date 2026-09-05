<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="admin-settings-redirect">
		<p>{{ t('launchpad', 'Opening the administration settings…') }}</p>
		<a :href="target">{{
			t('launchpad', 'Continue to the administration settings')
		}}</a>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

/**
 * Sends the two admin manifest pages to where the admin surface actually is.
 *
 * 🔴 LAUNCHPAD'S ADMINISTRATION IS A NEXTCLOUD SETTINGS SECTION, not an in-app
 * page. `lib/Settings/LaunchPadAdmin.php` registers it and `src/admin.js`
 * mounts `AdminSettings.vue` there. The manifest declared `/admin/settings` and
 * `/admin/templates` as pages of this app anyway, naming a component
 * (`AdminSettingsPage`) that has never existed, and `TemplatesPage` — which is
 * a TAB inside that settings section rather than a page.
 *
 * Routing them to half an admin surface beside the real one would be worse than
 * the dead links they were. This resolves the route, so the menu entries work,
 * and lands the operator where the functionality lives.
 *
 * The visible anchor is not decoration: a redirect that only runs in `mounted`
 * leaves a blank page for anyone whose navigation is blocked or slow, and gives
 * a keyboard user nothing to act on.
 */
export default {
	name: 'AdminSettingsRedirect',

	computed: {
		/**
		 * The Nextcloud admin section for this app.
		 *
		 * @return {string} The settings URL.
		 */
		target() {
			return generateUrl('/settings/admin/launchpad')
		},
	},

	mounted() {
		window.location.href = this.target
	},

	methods: { t },
}
</script>

<style scoped>
.admin-settings-redirect {
	padding: 2rem;
}
</style>
