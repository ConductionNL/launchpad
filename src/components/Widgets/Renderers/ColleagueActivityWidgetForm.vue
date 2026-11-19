<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="colleague-activity-form">
		<p class="colleague-activity-form__hint">
			{{
				t(
					'launchpad',
					'Shows what colleagues did recently. Only what you are allowed to see is listed or counted.',
				)
			}}
		</p>

		<NcSelect
			:modelValue="limit"
			:options="limitOptions"
			:inputLabel="t('launchpad', 'Lines to show')"
			:clearable="false"
			@update:modelValue="updateLimit" />

		<NcSelect
			:modelValue="days"
			:options="dayOptions"
			:inputLabel="t('launchpad', 'Days back')"
			:clearable="false"
			@update:modelValue="updateDays" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcSelect } from '@nextcloud/vue'
import {
	NULLABLE_OBJECT_PROP,
	objectProp,
} from '../../../constants/propDefinitions.js'

const DEFAULT_LIMIT = 10
const DEFAULT_DAYS = 7
const LIMIT_OPTIONS = [5, 10, 20, 50]
const DAY_OPTIONS = [1, 7, 14, 30, 90]

/**
 * ColleagueActivityWidgetForm — the two settings of the colleague activity
 * widget: how many lines it lists and how many days back it looks
 * (REQ-DWMS-007). What may be seen is decided by the server.
 */
export default {
	name: 'ColleagueActivityWidgetForm',

	components: { NcSelect },

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
		value: objectProp({ limit: DEFAULT_LIMIT, days: DEFAULT_DAYS }),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			limitOptions: LIMIT_OPTIONS,
			dayOptions: DAY_OPTIONS,
			limit: LIMIT_OPTIONS.includes(Number(initial.limit)) ? Number(initial.limit) : DEFAULT_LIMIT,
			days: DAY_OPTIONS.includes(Number(initial.days)) ? Number(initial.days) : DEFAULT_DAYS,
		}
	},

	methods: {
		t,

		/**
		 * Keep the new limit and tell the modal.
		 *
		 * @param {number} limit Lines to show.
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
		 */
		updateLimit(limit) {
			this.limit = LIMIT_OPTIONS.includes(Number(limit)) ? Number(limit) : DEFAULT_LIMIT
			this.emitContent()
		},

		/**
		 * Keep the new period and tell the modal.
		 *
		 * @param {number} days Days back.
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
		 */
		updateDays(days) {
			this.days = DAY_OPTIONS.includes(Number(days)) ? Number(days) : DEFAULT_DAYS
			this.emitContent()
		},

		/** @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1 */
		emitContent() {
			this.$emit('update:content', { limit: this.limit, days: this.days })
		},

		/**
		 * Validate the form. Both settings always have a valid value.
		 *
		 * @return {string[]} The validation errors (always empty).
		 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.1
		 */
		validate() {
			return []
		},
	},
}
</script>

<style scoped>
.colleague-activity-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.colleague-activity-form__hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}
</style>
