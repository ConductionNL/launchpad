<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="activity-report-form">
		<p class="activity-report-form__hint">
			{{
				t(
					'launchpad',
					'Shows activity per type and per day. Anybody can see their own; another person\'s activity needs activity reporting turned on by an administrator, shows its purpose and is logged.',
				)
			}}
		</p>

		<NcSelect
			:modelValue="days"
			:options="dayOptions"
			:inputLabel="t('launchpad', 'Days shown at first')"
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

const DEFAULT_DAYS = 30
const DAY_OPTIONS = [7, 30, 90, 365]

/**
 * ActivityReportWidgetForm: the period the activity report opens on
 * (dashboards-and-who-may-see-them REQ-DWMS-006). Who is reported on is
 * chosen in the widget itself, never stored on the placement.
 */
export default {
	name: 'ActivityReportWidgetForm',

	components: { NcSelect },

	props: {
		/** The placement being edited, when there is one. */
		editingWidget: NULLABLE_OBJECT_PROP,

		/** The content to start from: `{ days }`. */
		value: objectProp({ days: DEFAULT_DAYS }),
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			dayOptions: DAY_OPTIONS,
			days: DAY_OPTIONS.includes(Number(initial.days)) ? Number(initial.days) : DEFAULT_DAYS,
		}
	},

	methods: {
		t,

		/**
		 * @param {number} days the chosen period length
		 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-4.2
		 */
		updateDays(days) {
			this.days = DAY_OPTIONS.includes(Number(days)) ? Number(days) : DEFAULT_DAYS
			this.$emit('update:content', { days: this.days })
		},

		/**
		 * @return {Array<string>} nothing to refuse: every option is valid
		 * @spec openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them/tasks.md#task-4.2
		 */
		validate() {
			return []
		},
	},
}
</script>

<style scoped>
.activity-report-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.activity-report-form__hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}
</style>
