<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="assigned-cases-widget-form">
		<p class="assigned-cases-widget-form__hint">
			{{ t('launchpad', 'Shows the open cases assigned to the person viewing, from the register below. Each person sees what OpenRegister lets them see.') }}
		</p>
		<NcTextField
			:modelValue="register"
			:label="t('launchpad', 'Register')"
			data-testid="assigned-cases-form-register"
			@update:modelValue="update({ register: String($event) })" />
		<NcTextField
			:modelValue="schema"
			:label="t('launchpad', 'Schema')"
			data-testid="assigned-cases-form-schema"
			@update:modelValue="update({ schema: String($event) })" />
		<NcTextField
			:modelValue="String(limit)"
			type="number"
			min="1"
			max="20"
			:label="t('launchpad', 'Number of cases')"
			data-testid="assigned-cases-form-limit"
			@update:modelValue="update({ limit: Number($event) })" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcTextField } from '@nextcloud/vue'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'

/**
 * AssignedCasesWidgetForm: the register and schema the `assigned-cases`
 * widget reads (dossiq's `dossiq` and `case` by default) and how many cases
 * it lists.
 *
 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-names-its-data-source-req-cases-003
 */
export default {
	name: 'AssignedCasesWidgetForm',

	components: { NcTextField },

	props: {
		/**
		 * The placement being edited, or `null` in create mode.
		 *
		 * @type {{content: object}|null}
		 */
		editingWidget: NULLABLE_OBJECT_PROP,

		/**
		 * Initial content values, used when not editing.
		 *
		 * @type {object}
		 */
		value: objectProp({ register: 'dossiq', schema: 'case', limit: 4 }),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			register: String(initial.register || 'dossiq'),
			schema: String(initial.schema || 'case'),
			limit: Number.isInteger(initial.limit) && initial.limit > 0 ? initial.limit : 4,
		}
	},

	methods: {
		t,

		/**
		 * Keep a change and tell the modal the whole content.
		 *
		 * @param {object} change The changed keys.
		 * @return {void}
		 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-names-its-data-source-req-cases-003
		 */
		update(change) {
			Object.assign(this, change)
			this.$emit('update:content', { register: this.register.trim(), schema: this.schema.trim(), limit: this.limit })
		},

		/**
		 * Validate: a register and a schema, and 1 to 20 cases.
		 *
		 * @return {string[]} The validation errors.
		 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md#requirement-the-widget-names-its-data-source-req-cases-003
		 */
		validate() {
			const errors = []
			if (this.register.trim() === '' || this.schema.trim() === '') {
				errors.push(t('launchpad', 'Name the register and the schema'))
			}
			if (!Number.isInteger(this.limit) || this.limit < 1 || this.limit > 20) {
				errors.push(t('launchpad', 'Show 1 to 20 cases'))
			}
			return errors
		},
	},
}
</script>

<style scoped>
.assigned-cases-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.assigned-cases-widget-form__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
