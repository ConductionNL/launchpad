<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="export-option-fields">
		<template v-for="field in fields" :key="field.key">
			<NcCheckboxRadioSwitch
				v-if="field.kind === 'switch'"
				type="switch"
				:modelValue="values[field.key] === true"
				:data-testid="`field-${field.key}`"
				@update:modelValue="$emit('update', field.key, $event)">
				{{ field.label }}
			</NcCheckboxRadioSwitch>
			<NcSelect
				v-else-if="field.kind === 'select'"
				:inputLabel="field.label"
				:options="field.options"
				:modelValue="selectedOption(field)"
				label="label"
				:clearable="false"
				:data-testid="`field-${field.key}`"
				@update:modelValue="$emit('update', field.key, $event?.id)" />
			<NcPasswordField
				v-else-if="field.kind === 'secret'"
				:label="field.label"
				:modelValue="values[field.key] ?? ''"
				:helperText="secretHelp(field)"
				autocomplete="new-password"
				:data-testid="`field-${field.key}`"
				@update:modelValue="$emit('update', field.key, $event)" />
			<NcTextArea
				v-else-if="field.kind === 'textarea'"
				:label="field.label"
				:modelValue="values[field.key] ?? ''"
				:helperText="field.help ?? ''"
				:data-testid="`field-${field.key}`"
				@update:modelValue="$emit('update', field.key, $event)" />
			<NcTextField
				v-else
				:label="field.label"
				:type="field.type ?? 'text'"
				:modelValue="
					values[field.key] === undefined || values[field.key] === null
						? ''
						: String(values[field.key])
				"
				:helperText="field.help ?? ''"
				:data-testid="`field-${field.key}`"
				@update:modelValue="
					$emit('update', field.key, toValue(field, $event))
				" />
		</template>
	</div>
</template>

<script>
import {
	NcCheckboxRadioSwitch,
	NcPasswordField,
	NcSelect,
	NcTextArea,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { LIST_PROP, OBJECT_PROP } from '../../constants/propDefinitions.js'

/** What a stored secret reads as through the export API. */
const REDACTED = '********'

/**
 * ExportOptionFields: a column of form fields described by data, for the
 * format options of a render target and the channel settings of a
 * recipient. Each field is `{key, label, kind, type?, options?, help?}`
 * with kind `text`, `switch`, `select`, `secret` or `textarea`; a change
 * is emitted as `update(key, value)` so the parent owns the values.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
 */
export default {
	name: 'ExportOptionFields',

	components: {
		NcCheckboxRadioSwitch,
		NcPasswordField,
		NcSelect,
		NcTextArea,
		NcTextField,
	},

	props: {
		/** The fields, in order. */
		fields: LIST_PROP,

		/** The current values by field key. */
		values: OBJECT_PROP,
	},

	emits: ['update'],

	methods: {
		t,

		/**
		 * The option of a select field that holds the current value.
		 *
		 * @param {object} field The field.
		 * @return {object|null} The option, or null.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		selectedOption(field) {
			return (
				(field.options ?? []).find(
					(option) => option.id === this.values[field.key],
				) ?? null
			)
		},

		/**
		 * A typed value: a number field gives a number (or null when empty).
		 *
		 * @param {object} field The field.
		 * @param {string} value What was typed.
		 * @return {string|number|null} The value to store.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		toValue(field, value) {
			if (field.type !== 'number') {
				return value
			}
			const number = Number.parseInt(value, 10)
			return Number.isNaN(number) ? null : number
		},

		/**
		 * The helper line of a secret: a stored secret is kept when left as is.
		 *
		 * @param {object} field The field.
		 * @return {string} The helper text.
		 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
		 */
		secretHelp(field) {
			if (this.values[field.key] === REDACTED) {
				return t(
					'launchpad',
					'Saved. Leave it as it is to keep it, or type a new one.',
				)
			}
			return field.help ?? ''
		},
	},
}
</script>

<style scoped>
.export-option-fields {
	display: flex;
	flex-direction: column;
	gap: 8px;
}
</style>
