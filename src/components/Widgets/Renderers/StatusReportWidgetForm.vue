<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="status-report-form">
		<p class="status-report-form__hint">
			{{
				t(
					'launchpad',
					'Reports on the items of one schema in one register: how many are open, closed and overdue, and a health rating. You can change the rating and the summary before keeping a report.',
				)
			}}
		</p>

		<NcTextField
			v-for="field in fields"
			:key="field.key"
			:modelValue="values[field.key]"
			:label="field.label"
			:type="field.type"
			:helperText="field.helper"
			:data-testid="`status-report-form-${field.key}`"
			@update:modelValue="set(field.key, $event)" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcTextField } from '@nextcloud/vue'
import {
	NULLABLE_OBJECT_PROP,
	objectProp,
} from '../../../constants/propDefinitions.js'

const KEYS = ['title', 'register', 'schema', 'deadline', 'statusProperty', 'closedValues', 'dueProperty']

/**
 * StatusReportWidgetForm: the body of work a status report is about
 * (dashboards-and-who-may-see-them REQ-DWMS-004). Register and schema are
 * required; the rest narrows how items count as closed or overdue.
 */
export default {
	name: 'StatusReportWidgetForm',

	components: { NcTextField },

	props: {
		/** The placement being edited, when there is one. */
		editingWidget: NULLABLE_OBJECT_PROP,

		/** The content to start from. */
		value: objectProp({}),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		const values = {}
		for (const key of KEYS) {
			const raw = initial[key]
			values[key] = Array.isArray(raw) ? raw.join(', ') : String(raw ?? '')
		}
		return { values }
	},

	computed: {
		/** @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-3.3 */
		fields() {
			return [
				{ key: 'title', label: t('launchpad', 'Name of the body of work'), type: 'text' },
				{ key: 'register', label: t('launchpad', 'Register'), type: 'text' },
				{ key: 'schema', label: t('launchpad', 'Schema'), type: 'text' },
				{ key: 'deadline', label: t('launchpad', 'Deadline'), type: 'date' },
				{ key: 'statusProperty', label: t('launchpad', 'Status property'), type: 'text', helper: t('launchpad', 'The property that says whether an item is done.') },
				{ key: 'closedValues', label: t('launchpad', 'Values that mean closed'), type: 'text', helper: t('launchpad', 'Separate several values with commas.') },
				{ key: 'dueProperty', label: t('launchpad', 'Due date property'), type: 'text', helper: t('launchpad', 'An open item past this date counts as overdue.') },
			]
		},
	},

	methods: {
		t,

		/**
		 * @param {string} key the field
		 * @param {string} value the typed value
		 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-3.3
		 */
		set(key, value) {
			this.values = { ...this.values, [key]: String(value ?? '') }
			const content = {}
			for (const name of KEYS) {
				content[name] = this.values[name].trim()
			}
			content.closedValues = content.closedValues.split(',').map((part) => part.trim()).filter((part) => part !== '')
			this.$emit('update:content', content)
		},

		/**
		 * @return {Array<string>} what keeps the widget from being saved
		 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-3.3
		 */
		validate() {
			const errors = []
			if (this.values.register.trim() === '' || this.values.schema.trim() === '') {
				errors.push(t('launchpad', 'A status report needs the register and the schema of the body of work.'))
			}
			return errors
		},
	},
}
</script>

<style scoped>
.status-report-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.status-report-form__hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}
</style>
