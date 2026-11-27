<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="launchpad-admin-root" data-testid="AdminRoot">
		<AdminSettings
			:tab="currentTab"
			:section="currentSection"
			:heldTabs="tabs"
			@tabChange="openTab" />
	</div>
</template>

<script>
import AdminSettings from '../components/admin/AdminSettings.vue'
import { heldTab, heldTabs, parseAdminFragment } from '../utils/adminPage.js'

/**
 * Launchpad's admin page inside Nextcloud's settings framework,
 * `/settings/admin/launchpad` (admin-settings REQ-ADM-032). Nextcloud draws
 * its settings shell around it, with its admin menu on the left. The open
 * tab is the URL fragment (`#templates`): a link opens that tab, opening
 * another tab adds a history entry, and Back opens the previous one, without
 * a router (REQ-ADM-035). A former tab's fragment (`#embeds`) opens the tab
 * that holds it at that section. The page shows only the tabs the viewer
 * holds (REQ-ADM-034).
 */
export default {
	name: 'AdminRoot',

	components: {
		AdminSettings,
	},

	inject: {
		adminTabs: { from: 'adminTabs', default: () => [] },
	},

	/** @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032 */
	data() {
		return {
			hash: globalThis.location?.hash ?? '',
		}
	},

	computed: {
		/**
		 * The tabs the viewer holds, in the page's order.
		 *
		 * @return {string[]}
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-delegated-admin-settings-per-area-req-adm-034
		 */
		tabs() {
			return heldTabs(this.adminTabs)
		},

		/**
		 * The open tab: the fragment's, when the viewer holds it, else the
		 * first held tab.
		 *
		 * @return {string}
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		currentTab() {
			return heldTab(parseAdminFragment(this.hash).tab, this.tabs)
		},

		/**
		 * The former tab the fragment names (`#embeds`), or ''.
		 *
		 * @return {string}
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		currentSection() {
			return parseAdminFragment(this.hash).section
		},
	},

	mounted() {
		globalThis.addEventListener?.('hashchange', this.onHashChange)
	},

	beforeUnmount() {
		globalThis.removeEventListener?.('hashchange', this.onHashChange)
	},

	methods: {
		/**
		 * Follow the fragment when it changes (a link, Back, Forward).
		 *
		 * @return {void}
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		onHashChange() {
			this.hash = globalThis.location?.hash ?? ''
		},

		/**
		 * Put the opened tab in the fragment, as a new history entry.
		 *
		 * @param {string} id The tab's id.
		 * @return {void}
		 * @spec openspec/changes/admin-page-in-nextcloud-settings/specs/admin-settings/spec.md#requirement-the-admin-page-lives-in-nextclouds-settings-framework-req-adm-032
		 */
		openTab(id) {
			if (id === this.currentTab && this.currentSection === '') {
				return
			}
			this.hash = `#${id}`
			if (globalThis.history?.pushState) {
				globalThis.history.pushState(null, '', `#${id}`)
			}
		},
	},
}
</script>

<style scoped>
.launchpad-admin-root {
	width: 100%;
}
</style>
