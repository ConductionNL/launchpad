<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - DashboardMoreMenu: the dashboard view's More menu (board LpMeer), the
  - only control in the page header. The same menu as on every other page
  - (StartPageMenu), plus the This dashboard group for the open dashboard.
  - Each action keeps the permission rule it had.
-->
<template>
	<LaunchpadMoreMenu
		:dashboards="dashboards"
		:settings="settings"
		:actions="actions"
		class="launchpad-more-menu"
		data-testid="launchpad-more-menu"
		@open="foldDashboards" />
</template>

<script>
import { t } from '@nextcloud/l10n'
import { markRaw } from 'vue'
import CheckDecagramOutline from 'vue-material-design-icons/CheckDecagramOutline.vue'
import LaunchpadMoreMenu from './LaunchpadMoreMenu.vue'
import { favouriteDashboardsMixin } from '../../services/favouriteDashboards.js'
import { isOwnedDashboard } from '../../utils/dashboardRows.js'
import {
	dashboardActionItems,
	dashboardItems,
	readChrome,
	settingsMenuItems,
	startPageSections,
} from './moreMenuItems.js'

export default {
	name: 'DashboardMoreMenu',

	components: {
		LaunchpadMoreMenu,
	},

	mixins: [favouriteDashboardsMixin],

	inject: {
		/** What `App.vue` decided about the navigation panel (see StartPageMenu). */
		launchpadChrome: {
			from: 'launchpadChrome',
			default: null,
		},

		/** The shared library's opener for the personal settings dialog. */
		cnOpenUserSettings: {
			from: 'cnOpenUserSettings',
			default: null,
		},
	},

	props: {
		/** The active dashboard, or null. */
		dashboard: {
			type: Object,
			default: null,
		},

		/** The active dashboard's section: 'user', 'group', 'default' or 'shared'. */
		source: {
			type: String,
			default: 'user',
		},

		/** The person's own dashboards. */
		userDashboards: {
			type: Array,
			default: () => [],
		},

		/** The group, default and shared dashboards. */
		groupDashboards: {
			type: Array,
			default: () => [],
		},

		/** Which dashboards came through a share, to mark them. */
		sharedDashboards: {
			type: Array,
			default: () => [],
		},

		/** The uuid of the person's default dashboard. */
		defaultUuid: {
			type: String,
			default: '',
		},

		/** Whether the person may edit the active dashboard. */
		canEdit: {
			type: Boolean,
			default: false,
		},

		/** Whether the person may share the active dashboard. */
		canShare: {
			type: Boolean,
			default: false,
		},

		/** Whether the person may make a personal copy of it. */
		canDuplicate: {
			type: Boolean,
			default: false,
		},

		/**
		 * Whether the expanded Dashboards list offers New dashboard: personal
		 * dashboards allowed and the quota not reached (REQ-SHELL-015).
		 */
		canCreate: {
			type: Boolean,
			default: false,
		},

		/** Whether the read receipts of the active dashboard are offered. */
		canViewReadReceipts: {
			type: Boolean,
			default: false,
		},

		/** Whether the dashboard is being edited. */
		isEditMode: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'switch',
		'download',
		'toggleEdit',
		'addWidget',
		'share',
		'copyLink',
		'duplicate',
		'setDefault',
		'readReceipts',
		'delete',
		'newDashboard',
	],

	computed: {
		/**
		 * The Dashboards group, the open one checked: the favourites, or
		 * every dashboard with a star each once unfolded.
		 *
		 * @return {object[]}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 * @spec openspec/changes/more-menu-favourites/tasks.md#task-3
		 */
		dashboards() {
			return dashboardItems({
				userDashboards: this.userDashboards,
				groupDashboards: this.groupDashboards,
				sharedDashboards: this.sharedDashboards,
				activeId: this.dashboard?.id ?? null,
				defaultUuid: this.defaultUuid,
				favourites: this.favourites,
				expanded: this.dashboardsExpanded,
				onSwitch: (id, source) => this.$emit('switch', id, source),
				onToggleExpanded: () => this.toggleDashboardsExpanded(),
				onToggleFavourite: (uuid) => this.toggleDashboardFavourite(uuid),
				openTrees: this.openTrees,
				onToggleTree: (uuid) => this.toggleTree(uuid),
				onNewDashboard: this.canCreate ? () => this.$emit('newDashboard') : null,
			})
		},

		/**
		 * The items under Settings, while the navigation panel is left out.
		 *
		 * @return {object[]}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		settings() {
			const chrome = readChrome(this.launchpadChrome)
			if (!chrome.railSuppressed) {
				return []
			}
			return settingsMenuItems(startPageSections(chrome, this.cnOpenUserSettings))
		},

		/**
		 * The This dashboard group, Download included. Read receipts, when
		 * the dashboard asks for them, sit before Delete, which stays last.
		 *
		 * @return {object[]}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 * @spec openspec/changes/more-menu-favourites/tasks.md#task-5
		 */
		actions() {
			const items = dashboardActionItems({
				dashboard: this.dashboard,
				isOwner: isOwnedDashboard(this.dashboard, this.source),
				isDefault: Boolean(this.dashboard?.uuid) && this.dashboard.uuid === this.defaultUuid,
				isEditMode: this.isEditMode,
				canEdit: this.canEdit,
				canShare: this.canShare,
				canDuplicate: this.canDuplicate,
				emit: (event) => this.$emit(event),
			})
			if (this.dashboard && this.canViewReadReceipts) {
				const at = items.findIndex((item) => item.destructive === true)
				items.splice(at === -1 ? items.length : at, 0, {
					id: 'read-receipts',
					label: t('launchpad', 'Read receipts'),
					icon: markRaw(CheckDecagramOutline),
					onClick: () => this.$emit('readReceipts'),
				})
			}
			return items
		},
	},
}
</script>
