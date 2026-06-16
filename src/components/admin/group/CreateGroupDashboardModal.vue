<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog
		data-test="create-group-dashboard-modal"
		:name="t('launchpad', 'Create group dashboard')"
		:open="true"
		@update:open="onClose">
		<form class="cgd-form" @submit.prevent="onSubmit">
			<div class="cgd-form__field">
				<NcTextField
					:value.sync="form.name"
					:label="t('launchpad', 'Name')"
					:error="!!nameError"
					:helper-text="nameError"
					data-test="create-group-dashboard-name"
					required />
			</div>

			<div class="cgd-form__field">
				<NcTextField
					:value.sync="form.icon"
					:label="t('launchpad', 'Icon (Material Design name)')"
					data-test="create-group-dashboard-icon" />
			</div>

			<div class="cgd-form__field">
				<label class="cgd-form__label">{{ t('launchpad', 'Layout template') }}</label>
				<NcSelect
					v-model="form.layoutTemplate"
					:options="layoutTemplates"
					:input-label="t('launchpad', 'Layout template')"
					:reduce="opt => opt.id"
					label="label"
					data-test="create-group-dashboard-layout" />
			</div>

			<NcCheckboxRadioSwitch
				:checked.sync="form.isDefault"
				data-test="create-group-dashboard-default">
				{{ t('launchpad', 'Set as the group default dashboard') }}
			</NcCheckboxRadioSwitch>
		</form>

		<template #actions>
			<NcButton
				data-test="create-group-dashboard-cancel"
				type="tertiary"
				@click="onClose">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				data-test="create-group-dashboard-submit"
				type="primary"
				:disabled="submitting || !canSubmit"
				@click="onSubmit">
				{{ submitting ? t('launchpad', 'Creating…') : t('launchpad', 'Create') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcDialog,
	NcSelect,
	NcTextField,
} from '@nextcloud/vue'

import { useGroupDashboardsStore } from '../../../stores/groupDashboards.js'

/**
 * CreateGroupDashboardModal — NcDialog-based create form for group-shared
 * dashboards (admin-group-management Task 4). POSTs to
 * `/api/dashboards/group/{groupId}` via `groupDashboardsStore.create`.
 *
 * @spec openspec/changes/admin-group-management/tasks.md#task-4
 */
export default {
	name: 'CreateGroupDashboardModal',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcSelect,
		NcTextField,
	},

	props: {
		group: {
			type: Object,
			required: true,
			validator: (g) => typeof g.id === 'string' && typeof g.displayName === 'string',
		},
	},

	emits: ['close', 'created'],

	data() {
		return {
			store: useGroupDashboardsStore(),
			form: {
				name: '',
				icon: 'view-dashboard',
				layoutTemplate: 'blank',
				isDefault: false,
			},
			submitting: false,
		}
	},

	computed: {
		layoutTemplates() {
			// Mirrors the layout templates exposed by the admin templates
			// catalogue; pinned here to avoid an extra round-trip during
			// dialog mount.
			return [
				{ id: 'blank', label: this.t('launchpad', 'Blank dashboard') },
				{ id: 'kpi', label: this.t('launchpad', 'KPI overview') },
				{ id: 'people', label: this.t('launchpad', 'People & teams') },
			]
		},

		nameError() {
			if (this.form.name === '') {
				return ''
			}
			if (this.form.name.length < 2) {
				return this.t('launchpad', 'Name must be at least 2 characters')
			}
			if (this.form.name.length > 64) {
				return this.t('launchpad', 'Name must be at most 64 characters')
			}
			return ''
		},

		canSubmit() {
			return this.form.name.length >= 2
				&& this.form.name.length <= 64
		},
	},

	methods: {
		onClose() {
			this.$emit('close')
		},

		async onSubmit() {
			if (!this.canSubmit || this.submitting === true) {
				return
			}
			this.submitting = true
			try {
				await this.store.create(this.group.id, {
					name: this.form.name,
					icon: this.form.icon,
					layout: { template: this.form.layoutTemplate },
					isDefault: this.form.isDefault,
				})
				this.$emit('created')
			} catch (e) {
				// store.create surfaces toast on failure; keep modal open
				// so the admin can retry.
			} finally {
				this.submitting = false
			}
		},
	},
}
</script>

<style scoped>
.cgd-form {
	display: flex;
	flex-direction: column;
	gap: 16px;
	padding: 8px 0;
	min-width: 360px;
}

.cgd-form__field {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.cgd-form__label {
	font-weight: 500;
	color: var(--color-main-text);
}
</style>
