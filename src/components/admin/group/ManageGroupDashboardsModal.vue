<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		data-test="manage-group-dashboards-modal"
		:name="t('launchpad', 'Manage dashboards for {group}', { group: group.displayName })"
		:open="true"
		size="large"
		@update:open="onClose">
		<div class="mgd">
			<NcEmptyContent
				v-if="loading"
				:name="t('launchpad', 'Loading dashboards')">
				<template #icon>
					<NcLoadingIcon />
				</template>
			</NcEmptyContent>

			<NcEmptyContent
				v-else-if="dashboards.length === 0"
				data-test="manage-group-dashboards-empty"
				:name="t('launchpad', 'No dashboards in this group yet')">
				<template #icon>
					<ViewDashboardIcon :size="48" />
				</template>
			</NcEmptyContent>

			<ul v-else class="mgd__list" data-test="manage-group-dashboards-list">
				<li
					v-for="dashboard in dashboards"
					:key="dashboard.uuid"
					class="mgd__row"
					:data-test="`mgd-row-${dashboard.uuid}`">
					<div class="mgd__row-main">
						<ViewDashboardIcon :size="20" />
						<span class="mgd__row-name">{{ dashboard.name }}</span>
						<span
							v-if="dashboard.isDefault"
							class="mgd__row-badge"
							data-test="mgd-default-badge">
							{{ t('launchpad', 'Default') }}
						</span>
					</div>
					<div class="mgd__row-actions">
						<NcButton
							v-if="!dashboard.isDefault"
							type="tertiary"
							:data-test="`mgd-set-default-${dashboard.uuid}`"
							@click="onSetDefault(dashboard)">
							{{ t('launchpad', 'Set as default') }}
						</NcButton>
						<NcButton
							type="tertiary"
							:data-test="`mgd-rename-${dashboard.uuid}`"
							@click="onRename(dashboard)">
							{{ t('launchpad', 'Rename') }}
						</NcButton>
						<NcButton
							type="tertiary-no-background"
							:disabled="deleting === dashboard.uuid"
							:data-test="`mgd-delete-${dashboard.uuid}`"
							@click="onDelete(dashboard)">
							{{ t('launchpad', 'Delete') }}
						</NcButton>
					</div>
				</li>
			</ul>
		</div>

		<template #actions>
			<NcButton
				data-test="mgd-close"
				type="primary"
				@click="onClose">
				{{ t('launchpad', 'Close') }}
			</NcButton>
		</template>

		<GroupDashboardRenameDialog
			:open="renameTarget !== null"
			:current-name="renameTarget ? renameTarget.name : ''"
			@update:open="renameTarget = null"
			@confirm="onRenameConfirm" />

		<GroupDashboardDeleteDialog
			:open="deleteTarget !== null"
			@update:open="deleteTarget = null"
			@confirm="onDeleteConfirm" />
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcDialog,
	NcEmptyContent,
	NcLoadingIcon,
} from '@nextcloud/vue'
import ViewDashboardIcon from 'vue-material-design-icons/ViewDashboard.vue'

import GroupDashboardDeleteDialog from '../../../dialogs/GroupDashboardDeleteDialog.vue'
import GroupDashboardRenameDialog from '../../../dialogs/GroupDashboardRenameDialog.vue'
import { useGroupDashboardsStore } from '../../../stores/groupDashboards.js'

/**
 * ManageGroupDashboardsModal — per-group list of dashboards with
 * set-default, rename, delete actions (admin-group-management Task 5).
 * Respects the last-in-group delete guard surfaced by the store as a
 * localized toast; deletion is then aborted by re-throwing.
 *
 * @spec openspec/changes/admin-group-management/tasks.md#task-5
 */
export default {
	name: 'ManageGroupDashboardsModal',

	components: {
		GroupDashboardDeleteDialog,
		GroupDashboardRenameDialog,
		NcButton,
		NcDialog,
		NcEmptyContent,
		NcLoadingIcon,
		ViewDashboardIcon,
	},

	props: {
		group: {
			type: Object,
			required: true,
			validator: (g) => typeof g.id === 'string' && typeof g.displayName === 'string',
		},
	},

	emits: ['close'],

	data() {
		return {
			store: useGroupDashboardsStore(),
			loading: false,
			deleting: null,
			// The row a confirmation dialog is currently open for, or null.
			// These replace the return values of the removed window.prompt()
			// and window.confirm(): the native calls were synchronous, so the
			// target only had to live for the duration of one expression;
			// a component dialog resolves on a later tick, so the target has
			// to be held somewhere until the user answers.
			renameTarget: null,
			deleteTarget: null,
		}
	},

	computed: {
		dashboards() {
			return this.store.dashboardsFor(this.group.id)
		},
	},

	async created() {
		this.loading = true
		try {
			await this.store.fetchGroupDashboards(this.group.id)
		} finally {
			this.loading = false
		}
	},

	methods: {
		onClose() {
			this.$emit('close')
		},

		async onSetDefault(dashboard) {
			await this.store.setDefault(this.group.id, dashboard.uuid)
		},

		/**
		 * Open the rename dialog for a row. The full edit surface lives in
		 * the dashboard editor view (deep-linked via `View`); this is the
		 * lightweight name-only path.
		 *
		 * @param {object} dashboard the row to rename.
		 * @return {void}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		onRename(dashboard) {
			this.renameTarget = dashboard
		},

		/**
		 * Apply a rename the dialog has validated and confirmed.
		 *
		 * The empty / whitespace / unchanged rules that used to sit here,
		 * after `window.prompt()` returned, now live in the dialog's
		 * `canSubmit` — so a name that reaches this method is already
		 * trimmed and known to differ from the current one.
		 *
		 * @param {string} nextName the confirmed, trimmed name.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onRenameConfirm(nextName) {
			const dashboard = this.renameTarget
			this.renameTarget = null
			if (dashboard === null) {
				return
			}
			await this.store.update(this.group.id, dashboard.uuid, { name: nextName })
		},

		/**
		 * Open the delete confirmation for a row.
		 *
		 * @param {object} dashboard the row to delete.
		 * @return {void}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		onDelete(dashboard) {
			this.deleteTarget = dashboard
		},

		/**
		 * Delete the confirmed row. The last-in-group guard is enforced by
		 * the store, which toasts and re-throws; the modal stays open.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onDeleteConfirm() {
			const dashboard = this.deleteTarget
			this.deleteTarget = null
			if (dashboard === null) {
				return
			}
			this.deleting = dashboard.uuid
			try {
				await this.store.delete(this.group.id, dashboard.uuid)
			} catch (e) {
				// store.delete already toasted; modal stays open.
			} finally {
				this.deleting = null
			}
		},
	},
}
</script>

<style scoped>
.mgd {
	min-width: 480px;
	min-height: 200px;
}

.mgd__list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.mgd__row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 8px 12px;
	border-radius: var(--border-radius);
}

.mgd__row:hover {
	background-color: var(--color-background-hover);
}

.mgd__row-main {
	display: flex;
	align-items: center;
	gap: 8px;
	min-width: 0;
	flex: 1;
}

.mgd__row-name {
	font-weight: 500;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.mgd__row-badge {
	display: inline-flex;
	align-items: center;
	padding: 2px 8px;
	background-color: var(--color-success);
	color: var(--color-primary-element-text);
	border-radius: 12px;
	font-size: 11px;
	font-weight: 600;
}

.mgd__row-actions {
	display: flex;
	gap: 4px;
	flex-shrink: 0;
}
</style>
