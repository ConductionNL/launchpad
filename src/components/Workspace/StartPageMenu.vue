<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - StartPageMenu: the More menu on every page but the dashboard view, and in
  - the workspace's empty state (runtime-shell REQ-SHELL-009, REQ-SHELL-013).
  - It is the same menu as the dashboard view's (board LpMeer): the
  - dashboards, then Settings unfolding in place. There is no open dashboard
  - here, so there is no This dashboard group. Choosing a dashboard opens it.
-->

<template>
	<LaunchpadMoreMenu
		v-if="chrome.railSuppressed"
		:dashboards="dashboards"
		:settings="settings"
		class="launchpad-start-page-menu"
		data-walkthrough-id="launchpad-more-menu"
		data-testid="launchpad-more-menu"
		@open="onOpen" />
</template>

<script>
import { mapState } from 'pinia'
import LaunchpadMoreMenu from './LaunchpadMoreMenu.vue'
import { api } from '../../services/api.js'
import { favouriteDashboardsMixin } from '../../services/favouriteDashboards.js'
import { useDashboardStore } from '../../stores/dashboard.js'
import { logger } from '../../utils/logger.js'
import {
	dashboardItems,
	readChrome,
	settingsMenuItems,
	startPageSections,
} from './moreMenuItems.js'

export default {
	name: 'StartPageMenu',

	components: {
		LaunchpadMoreMenu,
	},

	mixins: [favouriteDashboardsMixin],

	inject: {
		/**
		 * What `App.vue` decided about the panel: whether it is left out on
		 * this route, the entries it would have shown, and who is looking.
		 *
		 * @type {{ value: { railSuppressed: boolean, entries: object[], isAdmin: boolean, includePersonalSettings: boolean } }}
		 */
		launchpadChrome: {
			from: 'launchpadChrome',
			default: null,
		},

		/**
		 * The shared library's opener for the per-user settings dialog,
		 * provided by `CnAppRoot`. Absent outside the shell (tests).
		 *
		 * @type {(() => void)|null}
		 */
		cnOpenUserSettings: {
			from: 'cnOpenUserSettings',
			default: null,
		},
	},

	data() {
		return {
			/** The uuid of the person's default dashboard, read on first open. */
			defaultUuid: '',
			defaultRead: false,
		}
	},

	computed: {
		...mapState(useDashboardStore, [
			'userDashboards',
			'groupSharedDashboards',
			'defaultGroupDashboards',
			'sharedWithMeDashboards',
			'activeDashboardId',
			'loadedOnce',
		]),

		/**
		 * The chrome decision, unwrapped. Defaults keep the panel.
		 *
		 * @return {{ railSuppressed: boolean, entries: object[], isAdmin: boolean, includePersonalSettings: boolean }}
		 * @spec openspec/specs/runtime-shell/spec.md#req-shell-009
		 */
		chrome() {
			return readChrome(this.launchpadChrome)
		},

		/**
		 * The Dashboards group, as on the dashboard view.
		 *
		 * @return {object[]}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		dashboards() {
			return dashboardItems({
				userDashboards: this.userDashboards,
				groupDashboards: [
					...this.groupSharedDashboards,
					...this.defaultGroupDashboards,
					...this.sharedWithMeDashboards,
				],
				sharedDashboards: this.sharedWithMeDashboards,
				activeId: null,
				defaultUuid: this.defaultUuid,
				favourites: this.favourites,
				expanded: this.dashboardsExpanded,
				onSwitch: (id) => this.openDashboard(id),
				onToggleExpanded: () => this.toggleDashboardsExpanded(),
				onToggleFavourite: (uuid) => this.toggleDashboardFavourite(uuid),
			})
		},

		/**
		 * The items under Settings.
		 *
		 * @return {object[]}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		settings() {
			return settingsMenuItems(startPageSections(this.chrome, this.cnOpenUserSettings))
		},
	},

	methods: {
		/**
		 * Read the dashboards and the default one the first time the menu
		 * opens, when the page has not read them yet.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		async onOpen() {
			this.foldDashboards()
			if (!this.loadedOnce) {
				await useDashboardStore().loadDashboards()
			}
			if (this.defaultRead) {
				return
			}
			this.defaultRead = true
			try {
				const res = await api.getDefaultDashboardPreference()
				this.defaultUuid = res?.data?.uuid ?? ''
			} catch (error) {
				logger.warn('[StartPageMenu] could not read the default dashboard:', error)
			}
		},

		/**
		 * Open a dashboard on the dashboard view.
		 *
		 * @param {string|number} id The dashboard's id.
		 * @return {void}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		openDashboard(id) {
			this.$router?.push({ name: 'dashboard-detail', params: { id: String(id) } })
		},
	},
}
</script>
