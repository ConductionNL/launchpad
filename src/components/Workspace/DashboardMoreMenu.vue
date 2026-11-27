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
		data-testid="launchpad-more-menu" />
</template>

<script>
import { t } from '@nextcloud/l10n'
import { isOwnedDashboard } from './DashboardRowActions.vue'
import LaunchpadMoreMenu from './LaunchpadMoreMenu.vue'
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

		/** Whether the dashboard is being edited. */
		isEditMode: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'switch',
		'openAll',
		'toggleEdit',
		'addWidget',
		'share',
		'copyLink',
		'duplicate',
		'setDefault',
		'delete',
	],

	computed: {
		/**
		 * The Dashboards group, the open one checked.
		 *
		 * @return {object[]}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		dashboards() {
			return dashboardItems({
				userDashboards: this.userDashboards,
				groupDashboards: this.groupDashboards,
				sharedDashboards: this.sharedDashboards,
				activeId: this.dashboard?.id ?? null,
				defaultUuid: this.defaultUuid,
				onSwitch: (id, source) => this.$emit('switch', id, source),
				onOpenAll: () => this.$emit('openAll'),
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
		 * The This dashboard group; Delete stays last. Read receipts are not
		 * offered here: they are read on the Announcements tab of the admin
		 * settings page (REQ-ACK-008).
		 *
		 * @return {object[]}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
		 */
		actions() {
			return dashboardActionItems({
				dashboard: this.dashboard,
				isOwner: isOwnedDashboard(this.dashboard, this.source),
				isDefault: Boolean(this.dashboard?.uuid) && this.dashboard.uuid === this.defaultUuid,
				isEditMode: this.isEditMode,
				canEdit: this.canEdit,
				canShare: this.canShare,
				canDuplicate: this.canDuplicate,
				emit: (event) => this.$emit(event),
			})
		},
	},
}
</script>
