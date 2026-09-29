<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="details-tab" data-testid="details-tab">
		<p v-if="!loading && fields.length === 0" class="details-tab__empty">
			{{
				t(
					'launchpad',
					'No detail fields are defined yet. An administrator adds them in the LaunchPad settings.',
				)
			}}
		</p>
		<div
			v-for="field in fields"
			:key="field.key"
			class="details-tab__field"
			:data-testid="`detail-${field.key}`">
			<NcTextField
				v-if="field.type === 'text'"
				:modelValue="values[field.key] ?? ''"
				:label="field.label"
				@update:modelValue="set(field.key, $event)" />
			<label
				v-else-if="field.type === 'number' || field.type === 'date'"
				class="details-tab__native">
				{{ field.label }}
				<input
					:type="field.type"
					:value="values[field.key] ?? ''"
					@input="set(field.key, $event.target.value)" />
			</label>
			<NcSelect
				v-else-if="field.type === 'select' || field.type === 'multi-select'"
				:modelValue="
					values[field.key] ?? (field.type === 'multi-select' ? [] : null)
				"
				:inputLabel="field.label"
				:options="field.options || []"
				:multiple="field.type === 'multi-select'"
				@update:modelValue="set(field.key, $event)" />
			<NcCheckboxRadioSwitch
				v-else-if="field.type === 'boolean'"
				:modelValue="values[field.key] === true"
				@update:modelValue="set(field.key, $event)">
				{{ field.label }}
			</NcCheckboxRadioSwitch>
		</div>
		<p
			v-if="error"
			class="details-tab__error"
			role="alert"
			data-testid="details-error">
			{{ error }}
		</p>
		<p v-if="saved" role="status" data-testid="details-saved">
			{{ t('launchpad', 'Details saved.') }}
		</p>
		<NcButton
			v-if="fields.length > 0"
			variant="primary"
			data-testid="details-save"
			@click="save">
			{{ t('launchpad', 'Save details') }}
		</NcButton>
	</div>
</template>

<script>
import {
	NcButton,
	NcCheckboxRadioSwitch,
	NcSelect,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../../services/api.js'

/**
 * DetailsTab: one control per detail field for this dashboard, saved with
 * the dashboard metadata endpoint. Field definitions come from the
 * read-only `/api/metadata-fields`, so owners who are not administrators
 * can fill them in. Values follow MetadataValidationService: text and
 * select as strings, number, date as YYYY-MM-DD, multi-select as a list,
 * boolean as true or false.
 *
 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
 */
export default {
	name: 'DetailsTab',

	components: {
		NcButton,
		NcCheckboxRadioSwitch,
		NcSelect,
		NcTextField,
	},

	props: {
		dashboardUuid: {
			type: String,
			required: true,
		},
	},

	/** @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md */
	data() {
		return { fields: [], values: {}, loading: false, error: '', saved: false }
	},

	watch: {
		dashboardUuid: {
			immediate: true,
			/**
			 * Load fields and values for the dashboard being edited.
			 *
			 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		t,

		/**
		 * Read the field definitions and this dashboard's values.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		async load() {
			this.loading = true
			this.error = ''
			try {
				const [defs, stored] = await Promise.all([
					api.getMetadataFieldDefinitions(),
					api.getDashboardMetadata(this.dashboardUuid),
				])
				this.fields = Array.isArray(defs?.data?.fields)
					? defs.data.fields
					: []
				const raw =
					stored?.data && typeof stored.data === 'object'
						? stored.data
						: {}
				const values = {}
				for (const field of this.fields) {
					values[field.key] = this.fromStored(field, raw[field.key])
				}
				this.values = values
			} catch {
				this.error = t('launchpad', 'The details could not be loaded.')
			} finally {
				this.loading = false
			}
		},

		/**
		 * Turn a stored value into the control's value.
		 *
		 * @param {object} field Field definition.
		 * @param {*} value Stored value (a string on the server).
		 * @return {*} Control value.
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		fromStored(field, value) {
			if (value === undefined || value === null || value === '') {
				return field.type === 'multi-select' ? [] : null
			}
			if (field.type === 'multi-select') {
				try {
					const list =
						typeof value === 'string' ? JSON.parse(value) : value
					return Array.isArray(list) ? list : []
				} catch {
					return []
				}
			}
			if (field.type === 'boolean') {
				return value === true || value === '1' || value === 1
			}
			return String(value)
		},

		/**
		 * @param {string} key Field key.
		 * @param {*} value New control value.
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		set(key, value) {
			this.values = { ...this.values, [key]: value }
			this.saved = false
		},

		/**
		 * Save every value; the server validates each against its field.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		async save() {
			this.error = ''
			const payload = {}
			for (const field of this.fields) {
				const value = this.values[field.key]
				if (field.type === 'number') {
					payload[field.key] =
						value === null || value === '' ? '' : Number(value)
				} else if (field.type === 'multi-select') {
					payload[field.key] = Array.isArray(value) ? value : []
				} else if (field.type === 'boolean') {
					payload[field.key] = value === true
				} else {
					payload[field.key] = value ?? ''
				}
			}
			try {
				await api.updateDashboardMetadata(this.dashboardUuid, payload)
				this.saved = true
			} catch (e) {
				this.error =
					e?.response?.data?.message
					|| t('launchpad', 'The details could not be saved.')
			}
		},
	},
}
</script>

<style scoped>
.details-tab__field {
	margin-bottom: 12px;
}

.details-tab__native {
	display: flex;
	flex-direction: column;
}

.details-tab__error {
	color: var(--color-error-text);
}
</style>
