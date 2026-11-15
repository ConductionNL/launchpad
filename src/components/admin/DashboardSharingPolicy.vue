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
				:inputLabel="t('launchpad', 'Default share permission level')"
				:options="permissionOptions"
				label="label"
				trackBy="id"
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

		<div class="sharing-policy__field">
			<label class="sharing-policy__label">
				{{ t('launchpad', 'Groups that may share') }}
			</label>
			<NcSelectTags
				v-model="allowedGroups"
				:options="groups"
				:multiple="true"
				:aria-label-combobox="t('launchpad', 'Groups that may share')"
				:placeholder="t('launchpad', 'Everyone')"
				data-test="sharing-policy-allowed-groups"
				@update:modelValue="saveLimits" />
			<p class="sharing-policy__hint">
				{{
					t(
						'launchpad',
						'Only members of these groups can share their dashboards. Leave empty to let everyone share.',
					)
				}}
			</p>
		</div>

		<div class="sharing-policy__field">
			<NcSelect
				v-model="recipientScope"
				:inputLabel="t('launchpad', 'Who can receive a share')"
				:options="scopeOptions"
				label="label"
				trackBy="id"
				:clearable="false"
				data-test="sharing-policy-recipient-scope"
				@update:modelValue="saveLimits" />
		</div>
	</div>
</template>

<script>
import { NcSelect, NcSelectTags } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

const SCOPE_OPTIONS = [
	{ id: 'everyone', label: 'Everyone' },
	{ id: 'shared-groups', label: 'Only people who share a group with the sharer' },
]

const PERMISSION_OPTIONS = [
	{ id: 'view_only', label: 'View only' },
	{ id: 'add_only', label: 'Add only' },
	{ id: 'full', label: 'Full customization' },
]

/**
 * DashboardSharingPolicy — org-wide sharing defaults for the admin
 * Beheer ▸ Sharing tab (dashboard-sharing spec). Reads / writes
 * `defaultSharePermissionLevel` and `forcedShareGroups` via the admin
 * settings API, and who may share and with whom: `sharingAllowedGroups`
 * (empty = everyone) and `sharingRecipientScope` (REQ-SHARE-014).
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

			scopeOptions: SCOPE_OPTIONS.map((o) => ({
				id: o.id,
				label: t('launchpad', o.label),
			})),

			defaultPermission: null,
			forcedGroups: [],
			allowedGroups: [],
			recipientScope: null,
		}
	},

	/** @spec openspec/specs/dashboard-sharing/spec.md */
	created() {
		this.defaultPermission = this.permissionOptions[1]
		this.recipientScope = this.scopeOptions[0]
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
				this.allowedGroups = Array.isArray(settings.sharingAllowedGroups)
					? settings.sharingAllowedGroups
					: []
				this.recipientScope =
					this.scopeOptions.find((o) => o.id === settings.sharingRecipientScope)
					|| this.scopeOptions[0]
			} catch (error) {
				logger.error('Failed to load sharing policy:', error)
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
				logger.error('Failed to save sharing policy:', error)
			}
		},

		/**
		 * Save who may share and with whom (REQ-SHARE-014). A partial patch:
		 * the defaults above are left as they are.
		 *
		 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md#requirement-administrators-decide-who-may-share-and-with-whom-req-share-014
		 */
		async saveLimits() {
			try {
				await api.updateAdminSettings({
					sharingAllowedGroups: this.allowedGroups,
					sharingRecipientScope: this.recipientScope?.id,
				})
			} catch (error) {
				logger.error('Failed to save sharing limits:', error)
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
