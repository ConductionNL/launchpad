<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="group-dashboards-tab" data-test="tab-group-dashboards">
		<div class="group-dashboards-tab__header">
			<h3 class="group-dashboards-tab__title">
				{{ t('launchpad', 'Group dashboards') }}
			</h3>
			<p class="group-dashboards-tab__lead">
				{{
					t(
						'launchpad',
						'Manage dashboards shared with a Nextcloud group. The “Default group” row controls the org-wide default dashboard set.',
					)
				}}
			</p>
		</div>

		<NcEmptyContent
			v-if="loading"
			data-test="group-dashboards-loading"
			:name="t('launchpad', 'Loading groups')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<NcEmptyContent
			v-else-if="groups.length === 0"
			data-test="group-dashboards-empty"
			:name="t('launchpad', 'No groups configured')"
			:description="
				t(
					'launchpad',
					'Add Nextcloud groups via the Sharing tab to start managing per-group dashboards.',
				)
			">
			<template #icon>
				<AccountMultipleIcon :size="48" />
			</template>
		</NcEmptyContent>

		<ul
			v-else
			class="group-dashboards-tab__list"
			data-test="group-dashboards-list">
			<li
				v-for="group in groups"
				:key="group.id"
				class="group-dashboards-tab__row"
				:data-test="`group-dashboards-row-${group.id}`">
				<div class="group-dashboards-tab__row-main">
					<AccountMultipleIcon
						class="group-dashboards-tab__row-icon"
						:size="24" />
					<span class="group-dashboards-tab__row-name">
						{{ group.displayName }}
					</span>
					<span
						class="group-dashboards-tab__row-badge"
						:data-test="`group-dashboards-count-${group.id}`">
						{{ countFor(group.id) }}
					</span>
				</div>
				<div class="group-dashboards-tab__row-actions">
					<NcButton
						type="tertiary"
						:data-test="`group-dashboards-view-${group.id}`"
						@click="onView(group)">
						{{ t('launchpad', 'View') }}
					</NcButton>
					<NcButton
						type="secondary"
						:data-test="`group-dashboards-create-${group.id}`"
						@click="onCreate(group)">
						{{ t('launchpad', 'Create group dashboard') }}
					</NcButton>
					<NcButton
						type="primary"
						:data-test="`group-dashboards-manage-${group.id}`"
						@click="onManage(group)">
						{{ t('launchpad', 'Manage') }}
					</NcButton>
				</div>
			</li>
		</ul>

		<CreateGroupDashboardModal
			v-if="createModalGroup"
			:group="createModalGroup"
			@close="createModalGroup = null"
			@created="onCreated" />

		<ManageGroupDashboardsModal
			v-if="manageModalGroup"
			:group="manageModalGroup"
			@close="manageModalGroup = null" />
	</div>
</template>

<script>
import { NcButton, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import AccountMultipleIcon from 'vue-material-design-icons/AccountMultiple.vue'
import CreateGroupDashboardModal from '../../../dialogs/CreateGroupDashboardModal.vue'
import ManageGroupDashboardsModal from '../../../dialogs/ManageGroupDashboardsModal.vue'
import { useGroupDashboardsStore } from '../../../stores/groupDashboards.js'

/**
 * GroupDashboardsTab — Beheer ▸ Group dashboards (admin-group-management
 * spec, Task 1+3). Lists every NC group plus the synthetic `default`
 * sentinel, with a count badge of group-shared dashboards and per-row
 * View / Create / Manage actions. Modals are owned by this component so
 * the tab is the single mount point for the slice.
 *
 * @spec openspec/changes/admin-group-management/tasks.md#task-1
 * @spec openspec/changes/admin-group-management/tasks.md#task-3
 */
export default {
	name: 'GroupDashboardsTab',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		AccountMultipleIcon,
		CreateGroupDashboardModal,
		ManageGroupDashboardsModal,
	},

	data() {
		return {
			store: useGroupDashboardsStore(),
			createModalGroup: null,
			manageModalGroup: null,
		}
	},

	computed: {
		groups() {
			return this.store.groups
		},

		loading() {
			return this.store.loading
		},
	},

	/**
	 * Load the group list and their dashboard counts on mount, so the tab's
	 * badges are populated on first paint.
	 *
	 * @spec openspec/specs/dashboards/spec.md#req-dash-015-admin-group-management-ui
	 * @return {Promise<void>}
	 */
	async created() {
		await this.store.fetchGroups()
		// Eagerly fetch dashboard counts so the badges render on first
		// paint. Per-group requests run in parallel; the per-group endpoint
		// is already paginated server side so this stays cheap.
		await Promise.all(
			this.groups.map((g) =>
				this.store.fetchGroupDashboards(g.id).catch(() => null),
			),
		)
	},

	methods: {
		countFor(groupId) {
			return this.store.countFor(groupId)
		},

		onView(group) {
			// Deep-link into the visible dashboards filtered by group id.
			// Falls back to the manage modal when no route is wired yet
			// (handled gracefully by the modal's empty state).
			this.manageModalGroup = group
		},

		onCreate(group) {
			this.createModalGroup = group
		},

		onManage(group) {
			this.manageModalGroup = group
		},

		async onCreated() {
			// The store already optimistically inserted the row; refresh
			// counts in case the backend mutated server-side defaults.
			if (this.createModalGroup) {
				await this.store.fetchGroupDashboards(this.createModalGroup.id)
			}
			this.createModalGroup = null
		},
	},
}
</script>

<style scoped>
.group-dashboards-tab__header {
	margin-bottom: 16px;
}

.group-dashboards-tab__title {
	margin: 0 0 4px 0;
	font-weight: 600;
}

.group-dashboards-tab__lead {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.group-dashboards-tab__list {
	list-style: none;
	margin: 0;
	padding: 0;
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.group-dashboards-tab__row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 12px 16px;
	background-color: var(--color-background-hover);
	border-radius: var(--border-radius-large);
}

.group-dashboards-tab__row-main {
	display: flex;
	align-items: center;
	gap: 12px;
	min-width: 0;
	flex: 1;
}

.group-dashboards-tab__row-name {
	font-weight: 500;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.group-dashboards-tab__row-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 24px;
	height: 24px;
	padding: 0 8px;
	background-color: var(--color-primary-element);
	color: var(--color-primary-element-text);
	border-radius: 12px;
	font-size: 12px;
	font-weight: 600;
}

.group-dashboards-tab__row-actions {
	display: flex;
	gap: 8px;
}
</style>
