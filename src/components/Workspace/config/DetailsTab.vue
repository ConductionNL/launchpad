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
import { REQUIRED_TEXT_PROP } from '../../../constants/propDefinitions.js'
import { api } from '../../../services/api.js'

/**
 * Whether a stored value is absent.
 *
 * @param {unknown} value The stored value.
 * @return {boolean} true for undefined, null or ''.
 */
function isUnset(value) {
	return value === undefined || value === null || value === ''
}

/**
 * A stored multi-select value (a JSON-encoded list, or a list) as a list.
 *
 * @param {unknown} value The stored value.
 * @return {Array} the selected options, [] when unreadable.
 */
function storedList(value) {
	try {
		const list = typeof value === 'string' ? JSON.parse(value) : value
		return Array.isArray(list) ? list : []
	} catch {
		return []
	}
}

/**
 * A stored boolean value: true, '1' or 1 read as on.
 *
 * @param {unknown} value The stored value.
 * @return {boolean} the toggle.
 */
function storedBoolean(value) {
	return value === true || value === '1' || value === 1
}

/** Stored-value readers per field type; other types read as a string. */
const FROM_STORED = Object.freeze({
	'multi-select': storedList,
	boolean: storedBoolean,
})

/** Payload writers per field type, in the shape the service validates. */
const TO_PAYLOAD = Object.freeze({
	number: (value) => (value === null || value === '' ? '' : Number(value)),
	'multi-select': (value) => (Array.isArray(value) ? value : []),
	boolean: (value) => value === true,
})

/**
 * A control value in the shape the service accepts for its field.
 *
 * @param {object} field Field definition.
 * @param {unknown} value The control value.
 * @return {unknown} the payload value.
 */
function toPayload(field, value) {
	if (Object.hasOwn(TO_PAYLOAD, field.type)) {
		return TO_PAYLOAD[field.type](value)
	}
	return value ?? ''
}

/**
 * The field definitions of a definitions response, [] when malformed.
 *
 * @param {object} defs The definitions response.
 * @return {Array<object>} the fields.
 */
function fieldList(defs) {
	return Array.isArray(defs?.data?.fields) ? defs.data.fields : []
}

/**
 * The stored values of a metadata response, {} when malformed.
 *
 * @param {object} stored The metadata response.
 * @return {object} the stored values keyed by field key.
 */
function storedValues(stored) {
	return stored?.data && typeof stored.data === 'object' ? stored.data : {}
}

/**
 * The message to show for a failed save: the server's, or the fallback.
 *
 * @param {object} e The request error.
 * @return {string} the message.
 */
function saveErrorMessage(e) {
	return e?.response?.data?.message
		|| t('launchpad', 'The details could not be saved.')
}

/**
 * DetailsTab: one control per detail field for this dashboard, saved with
 * the dashboard metadata endpoint. Field definitions come from the
 * read-only `/api/metadata-fields`, so owners who are not administrators
 * can fill them in. Values follow MetadataValidationService: text and
 * select as strings, number, date as YYYY-MM-DD, multi-select as a list,
 * boolean as true or false.
 *
 * @spec openspec/specs/dashboard-metadata-fields/spec.md
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
		dashboardUuid: REQUIRED_TEXT_PROP,
	},

	/** @spec openspec/specs/dashboard-metadata-fields/spec.md */
	data() {
		return { fields: [], values: {}, loading: false, error: '', saved: false }
	},

	watch: {
		dashboardUuid: {
			immediate: true,
			/**
			 * Load fields and values for the dashboard being edited.
			 *
			 * @spec openspec/specs/dashboard-metadata-fields/spec.md
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
		 * @spec openspec/specs/dashboard-metadata-fields/spec.md
		 */
		async load() {
			this.loading = true
			this.error = ''
			try {
				const [defs, stored] = await this.fetchDetails()
				this.fields = fieldList(defs)
				this.values = this.valuesFrom(storedValues(stored))
			} catch {
				this.error = t('launchpad', 'The details could not be loaded.')
			} finally {
				this.loading = false
			}
		},

		/**
		 * Fetch the field definitions and this dashboard's stored values.
		 *
		 * @return {Promise<Array<object>>} the definitions and values responses.
		 */
		fetchDetails() {
			return Promise.all([
				api.getMetadataFieldDefinitions(),
				api.getDashboardMetadata(this.dashboardUuid),
			])
		},

		/**
		 * One control value per field, read from the stored values.
		 *
		 * @param {object} raw The stored values keyed by field key.
		 * @return {object} the control values.
		 */
		valuesFrom(raw) {
			const values = {}
			for (const field of this.fields) {
				values[field.key] = this.fromStored(field, raw[field.key])
			}
			return values
		},

		/**
		 * Turn a stored value into the control's value.
		 *
		 * @param {object} field Field definition.
		 * @param {*} value Stored value (a string on the server).
		 * @return {*} Control value.
		 * @spec openspec/specs/dashboard-metadata-fields/spec.md
		 */
		fromStored(field, value) {
			if (isUnset(value)) {
				return field.type === 'multi-select' ? [] : null
			}
			const convert = Object.hasOwn(FROM_STORED, field.type)
				? FROM_STORED[field.type]
				: String
			return convert(value)
		},

		/**
		 * @param {string} key Field key.
		 * @param {*} value New control value.
		 * @spec openspec/specs/dashboard-metadata-fields/spec.md
		 */
		set(key, value) {
			this.values = { ...this.values, [key]: value }
			this.saved = false
		},

		/**
		 * Save every value; the server validates each against its field.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-metadata-fields/spec.md
		 */
		async save() {
			this.error = ''
			const payload = {}
			for (const field of this.fields) {
				payload[field.key] = toPayload(field, this.values[field.key])
			}
			try {
				await api.updateDashboardMetadata(this.dashboardUuid, payload)
				this.saved = true
			} catch (e) {
				this.error = saveErrorMessage(e)
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
