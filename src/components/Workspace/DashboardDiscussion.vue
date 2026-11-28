<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section
		v-if="shown"
		class="dashboard-discussion"
		:aria-label="t('launchpad', 'Discussion')"
		data-testid="dashboard-discussion">
		<h3 class="dashboard-discussion__title">
			{{ t('launchpad', 'Discussion') }}
		</h3>
		<CnIntegrationTab
			integrationId="talk"
			register="launchpad"
			schema="dashboard"
			:objectId="dashboard.uuid"
			:emptyLabel="t('launchpad', 'No conversation linked to this dashboard yet')" />
	</section>
</template>

<script>
import { CnIntegrationTab } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { NULLABLE_OBJECT_PROP } from '../../constants/propDefinitions.js'
import { api } from '../../services/api.js'
import { conversationEnabled, discussionAvailable, isCollaborative } from '../../utils/dashboardDiscussion.js'

/**
 * The Talk discussion of a shared or group dashboard
 * (openregister-leaf-integrations, REQ-LEAF-002, -003, -004).
 *
 * Access chain: the leaf reads `/apps/openregister/api/objects/launchpad/
 * dashboard/{uuid}/integrations/talk`, which resolves the dashboard's
 * discussion object under OpenRegister's read rule. LaunchPad writes that
 * object (`DashboardDiscussionMirror`) with the dashboard's audience as
 * `PermissionService::canViewDashboard()` derives it (owner, user and group
 * shares, the group of a group dashboard), and rewrites it on every share
 * change, so a revoked user is refused by OpenRegister and not only hidden
 * here. Nothing renders, and nothing is requested, on an unshared personal
 * dashboard or without OpenRegister and Talk.
 */
export default {
	name: 'DashboardDiscussion',

	components: { CnIntegrationTab },

	props: {
		/** The open dashboard. */
		dashboard: NULLABLE_OBJECT_PROP,

		/** The dashboards shared with this user. */
		sharedWithMe: {
			type: Array,
			default: () => [],
		},

		/** This user's id, to tell whether they own the dashboard. */
		currentUserId: {
			type: String,
			default: '',
		},
	},

	data() {
		return {
			ownShares: [],
		}
	},

	computed: {
		/**
		 * Whether the discussion shows: a collaborative dashboard with OpenRegister and Talk installed.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-5
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006
		 */
		shown() {
			return discussionAvailable()
				&& conversationEnabled(this.dashboard)
				&& isCollaborative({ dashboard: this.dashboard, sharedWithMe: this.sharedWithMe, ownShares: this.ownShares })
		},

		/**
		 * Whether this user owns a personal dashboard, whose shares only they may list.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
		 */
		ownsPersonal() {
			const dashboard = this.dashboard
			return Boolean(dashboard?.id) && dashboard.type === 'user' && this.currentUserId !== '' && dashboard.userId === this.currentUserId
		},
	},

	watch: {
		'dashboard.id': {
			immediate: true,
			/**
			 * Read the owner's shares of the open dashboard.
			 *
			 * @return {void}
			 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
			 */
			handler() {
				this.loadOwnShares()
			},
		},
	},

	methods: {
		t,

		/**
		 * Read the shares of a dashboard this user owns; any failure reads as none.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
		 */
		async loadOwnShares() {
			this.ownShares = []
			if (!this.ownsPersonal || !discussionAvailable()) {
				return
			}
			const id = this.dashboard.id
			try {
				const response = await api.listShares(id)
				if (this.dashboard?.id === id) {
					this.ownShares = Array.isArray(response?.data) ? response.data : []
				}
			} catch {
				this.ownShares = []
			}
		},
	},
}
</script>

<style scoped>
.dashboard-discussion {
	margin: 8px 0;
	padding: 8px 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.dashboard-discussion__title {
	margin: 0 0 4px;
	font-size: 1em;
}
</style>
