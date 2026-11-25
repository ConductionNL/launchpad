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

		<!-- REQ-SHARE-015 (board LpBeheerDelen): Everyone, Only these groups or Nobody. -->
		<fieldset class="sharing-policy__field sharing-policy__choices" data-test="sharing-policy-allowed">
			<legend class="sharing-policy__label">
				{{ t('launchpad', 'Sharing allowed for') }}
			</legend>
			<NcCheckboxRadioSwitch
				v-for="choice in allowedChoices"
				:key="choice.id"
				:modelValue="sharingAllowed"
				:value="choice.id"
				type="radio"
				name="launchpad-sharing-allowed"
				:data-test="`sharing-policy-allowed-choice-${choice.id}`"
				@update:modelValue="onAllowedChange">
				{{ choice.label }}
			</NcCheckboxRadioSwitch>
		</fieldset>

		<div v-if="sharingAllowed === 'groups'" class="sharing-policy__field">
			<label class="sharing-policy__label">
				{{ t('launchpad', 'Groups that may share') }}
			</label>
			<NcSelectTags
				v-model="allowedGroups"
				:options="groups"
				:multiple="true"
				:aria-label-combobox="t('launchpad', 'Groups that may share')"
				:placeholder="t('launchpad', 'Select groups')"
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

		<p
			v-if="sharingAllowed === 'none'"
			class="sharing-policy__hint"
			data-test="sharing-policy-nobody-hint">
			{{ t('launchpad', 'Only administrators can share. Existing shares stay.') }}
		</p>

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

		<div class="sharing-policy__field">
			<NcCheckboxRadioSwitch
				:modelValue="publicLinksAllowed"
				type="switch"
				data-test="sharing-policy-public-links"
				@update:modelValue="onPublicLinksChange">
				{{ t('launchpad', 'Allow public links') }}
			</NcCheckboxRadioSwitch>
			<p class="sharing-policy__hint">
				{{
					t(
						'launchpad',
						'Only for people who may share dashboards. A link always has an expiry date.',
					)
				}}
			</p>
		</div>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch, NcSelect, NcSelectTags } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

const SCOPE_OPTIONS = [
	{ id: 'everyone', label: 'Everyone' },
	{ id: 'shared-groups', label: 'Only people who share a group with the sharer' },
]

const ALLOWED_CHOICES = [
	{ id: 'everyone', label: 'Everyone' },
	{ id: 'groups', label: 'Only these groups' },
	{ id: 'none', label: 'Nobody' },
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
		NcCheckboxRadioSwitch,
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

			allowedChoices: ALLOWED_CHOICES.map((o) => ({
				id: o.id,
				label: t('launchpad', o.label),
			})),

			defaultPermission: null,
			forcedGroups: [],
			allowedGroups: [],
			recipientScope: null,
			sharingAllowed: 'everyone',
			publicLinksAllowed: true,
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
				this.sharingAllowed = ALLOWED_CHOICES.some((o) => o.id === settings.sharingAllowed)
					? settings.sharingAllowed
					: (this.allowedGroups.length > 0 ? 'groups' : 'everyone')
				this.publicLinksAllowed = settings.publicLinksAllowed !== false
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

		/**
		 * "Sharing allowed for" changed (REQ-SHARE-015). Only Nobody is stored
		 * as an override; Everyone clears the group list, which is what lets
		 * everyone share (REQ-SHARE-014), and Only these groups uses it.
		 *
		 * @param {string} value `everyone`, `groups` or `none`.
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
		 */
		async onAllowedChange(value) {
			this.sharingAllowed = value
			try {
				await api.updateSharingPolicy({ sharingAllowed: value })
				if (value === 'everyone' && this.allowedGroups.length > 0) {
					this.allowedGroups = []
					await this.saveLimits()
				}
			} catch (error) {
				logger.error('Failed to save who may share:', error)
			}
		},

		/**
		 * The public-link switch (REQ-SHARE-015).
		 *
		 * @param {boolean} value Whether public links may be created.
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
		 */
		async onPublicLinksChange(value) {
			this.publicLinksAllowed = value === true
			try {
				await api.updateSharingPolicy({ publicLinksAllowed: this.publicLinksAllowed })
			} catch (error) {
				logger.error('Failed to save the public link setting:', error)
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

.sharing-policy__choices {
	border: none;
	padding: 0;
}

.sharing-policy__label {
	display: block;
	margin-bottom: 4px;
	font-weight: 500;
}
</style>
