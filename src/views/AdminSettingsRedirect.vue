<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="admin-settings-redirect" data-testid="AdminSettingsRedirect">
		<p>{{ t('launchpad', 'Opening the administration settings…') }}</p>
		<a :href="target">{{
			t('launchpad', 'Continue to the administration settings')
		}}</a>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { adminPageUrl } from '../utils/adminPage.js'

/**
 * Loads the administration page from the server when it was reached inside
 * the app, from another page (`AdminPage.vue`).
 *
 * The administration is a Launchpad page at `/apps/launchpad/admin/<tab>`
 * (REQ-ADM-030). The server renders it only for a (delegated) administrator,
 * and only then with the administration's initial state; a route change
 * inside the app has neither. So the browser goes to that address, on the
 * tab the route names.
 *
 * The visible anchor is not decoration: a redirect that only runs in `mounted`
 * leaves a blank page for anyone whose navigation is blocked or slow, and gives
 * a keyboard user nothing to act on.
 */
export default {
	name: 'AdminSettingsRedirect',

	props: {
		/** The tab to open. */
		tab: {
			type: String,
			default: '',
		},
	},

	computed: {
		/**
		 * The administration page on the tab.
		 *
		 * @return {string} The page's URL.
		 *
		 * @spec openspec/changes/launchpad-manifest-tier-3/specs/manifest-routing/spec.md#requirement-req-route-006-a-declared-page-names-a-component-that-exists
		 * @spec openspec/changes/archive/2026-11-24-admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
		 */
		target() {
			return adminPageUrl(this.tab)
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
