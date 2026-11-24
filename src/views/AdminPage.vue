<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="launchpad-admin-page" data-testid="AdminPage">
		<AdminSettings
			v-if="servedHere"
			:tab="currentTab"
			@tabChange="openTab" />
		<AdminSettingsRedirect v-else :tab="currentTab" />
	</div>
</template>

<script>
import { provide } from 'vue'
import AdminSettings from '../components/admin/AdminSettings.vue'
import AdminSettingsRedirect from './AdminSettingsRedirect.vue'
import { adminTab, isAdminPagePath } from '../utils/adminPage.js'
import { bootPath } from '../utils/bootPath.js'
import { loadInitialState } from '../utils/loadInitialState.js'

/**
 * Launchpad's administration as a page of Launchpad: the shell's top bar and
 * More menu, no settings menu, the tabs over the full width (admin-settings
 * REQ-ADM-030). The tab is the route's (`/admin/:tab`); opening another tab
 * pushes its address, so a link or Back opens that tab.
 *
 * The server renders `/apps/launchpad/admin/<tab>` only for a (delegated)
 * administrator, with the administration's initial state. A page reached
 * inside the app from another page has neither, so it loads the address from
 * the server instead ({@link AdminSettingsRedirect}).
 */
export default {
	name: 'AdminPage',

	components: {
		AdminSettings,
		AdminSettingsRedirect,
	},

	props: {
		/**
		 * The tab the route names; the `admin-templates-index` page passes
		 * `templates` from its config.
		 */
		tab: {
			type: String,
			default: '',
		},
	},

	/**
	 * Hand the administration's initial state to the tabs, as the
	 * settings page's own entry did.
	 *
	 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
	 */
	setup() {
		for (const [key, value] of Object.entries(loadInitialState('admin'))) {
			provide(key, value)
		}
	},

	computed: {
		/**
		 * Whether the server rendered this page, with the administration's
		 * initial state.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
		 */
		servedHere() {
			return isAdminPagePath(bootPath())
		},

		/**
		 * The open tab: the route's, or General.
		 *
		 * @return {string}
		 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
		 */
		currentTab() {
			return adminTab(this.tab)
		},
	},

	methods: {
		/**
		 * Put the opened tab in the address, as a new history entry.
		 *
		 * @param {string} id The tab's id.
		 * @return {void}
		 * @spec openspec/changes/admin-full-width-page/specs/admin-settings/spec.md#requirement-the-administration-is-a-launchpad-page-over-the-full-width-req-adm-030
		 */
		openTab(id) {
			if (id === this.currentTab || !this.$router) {
				return
			}
			this.$router.push({ name: 'admin-settings', params: { tab: id } })
		},
	},
}
</script>

<style scoped>
.launchpad-admin-page {
	width: 100%;
}
</style>
