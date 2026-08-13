<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="sharing-policy" data-test="dashboard-sharing-policy">
		<h3>{{ t('launchpad', 'Organisation sharing defaults') }}</h3>
		<p class="sharing-policy__hint">
			{{
				t(
					'launchpad',
					'Set the default permission level applied to new shares and the groups every dashboard is automatically shared with.',
				)
			}}
		</p>

		<div class="sharing-policy__field">
			<NcSelect
				v-model="defaultPermission"
				:input-label="t('launchpad', 'Default share permission level')"
				:options="permissionOptions"
				label="label"
				track-by="id"
				:clearable="false"
				data-test="sharing-policy-permission"
				@update:modelValue="save" />
		</div>

		<div class="sharing-policy__field">
			<label class="sharing-policy__label">
				{{ t('launchpad', 'Forced share groups') }}
			</label>
			<NcSelectTags
				v-model="forcedGroups"
				:options="groups"
				:multiple="true"
				:aria-label-combobox="t('launchpad', 'Forced share groups')"
				:placeholder="t('launchpad', 'Select groups (leave empty for none)')"
				data-test="sharing-policy-forced-groups"
				@update:modelValue="save" />
			<p class="sharing-policy__hint">
				{{
					t(
						'launchpad',
						'Members of these groups always receive every newly created dashboard.',
					)
				}}
			</p>
		</div>
	</div>
</template>

<script>
import { NcSelect, NcSelectTags } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'

const PERMISSION_OPTIONS = [
	{ id: 'view_only', label: 'View only' },
	{ id: 'add_only', label: 'Add only' },
	{ id: 'full', label: 'Full customization' },
]

/**
 * DashboardSharingPolicy — org-wide sharing defaults for the admin
 * Beheer ▸ Sharing tab (dashboard-sharing spec). Reads / writes
 * `defaultSharePermissionLevel` and `forcedShareGroups` via the admin
 * settings API.
 */
export default {
	name: 'DashboardSharingPolicy',

	components: {
		NcSelect,
		NcSelectTags,
	},

	props: {
		/** Group ids offered in the forced-share-group picker. */
		groups: {
			type: Array,
			default: () => [],
		},
	},

	data() {
		return {
			permissionOptions: PERMISSION_OPTIONS.map((o) => ({
				id: o.id,
				label: t('launchpad', o.label),
			})),
			defaultPermission: null,
			forcedGroups: [],
		}
	},

	/** @spec openspec/specs/dashboard-sharing/spec.md */
	created() {
		this.defaultPermission = this.permissionOptions[1]
		this.load()
	},

	methods: {
		t,

		/** @spec openspec/specs/dashboard-sharing/spec.md */
		async load() {
			try {
				const { data } = await api.getAdminSettings()
				const settings = data?.data ?? data ?? {}
				const level = settings.defaultSharePermissionLevel
				this.defaultPermission =
					this.permissionOptions.find((o) => o.id === level)
					|| this.permissionOptions[1]
				this.forcedGroups = Array.isArray(settings.forcedShareGroups)
					? settings.forcedShareGroups
					: []
			} catch (error) {
				console.error('Failed to load sharing policy:', error)
			}
		},

		/** @spec openspec/specs/dashboard-sharing/spec.md */
		async save() {
			try {
				await api.updateAdminSettings({
					defaultSharePermissionLevel: this.defaultPermission?.id,
					forcedShareGroups: this.forcedGroups,
				})
			} catch (error) {
				console.error('Failed to save sharing policy:', error)
			}
		},
	},
}
</script>

<style scoped>
.sharing-policy__hint {
	color: var(--color-text-maxcontrast);
	margin-bottom: 16px;
}

.sharing-policy__field {
	margin-bottom: 16px;
}

.sharing-policy__label {
	display: block;
	margin-bottom: 4px;
	font-weight: 500;
}
</style>
