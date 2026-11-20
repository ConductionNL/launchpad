<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<span
		class="export-status"
		:class="`export-status--${tone}`"
		:data-status="status || 'none'"
		data-testid="export-status">
		<component :is="icon" :size="18" aria-hidden="true" />
		<span>{{ label }}</span>
	</span>
</template>

<script>
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import CloseCircle from 'vue-material-design-icons/CloseCircle.vue'
import MinusCircleOutline from 'vue-material-design-icons/MinusCircleOutline.vue'
import ProgressClock from 'vue-material-design-icons/ProgressClock.vue'
import { TEXT_PROP } from '../../constants/propDefinitions.js'
import { statusLabel } from '../../utils/scheduledExports.js'

/**
 * ExportStatus: the status of a run or a delivery as an icon with its
 * name beside it, so the meaning never rests on colour alone.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
 */
export default {
	name: 'ExportStatus',

	props: {
		/** running, success, partial, failed, pending, retrying, failed-permanent; empty for never run. */
		status: TEXT_PROP,
	},

	computed: {
		/**
		 * @return {string} success, warning, error or neutral.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		tone() {
			if (this.status === 'success') {
				return 'success'
			}
			if (['partial', 'retrying'].includes(this.status)) {
				return 'warning'
			}
			if (['failed', 'failed-permanent'].includes(this.status)) {
				return 'error'
			}
			return 'neutral'
		},

		/**
		 * @return {object} The icon component of the tone.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		icon() {
			if (['running', 'pending', 'retrying'].includes(this.status)) {
				return ProgressClock
			}
			return (
				{
					success: CheckCircle,
					warning: AlertCircle,
					error: CloseCircle,
				}[this.tone] ?? MinusCircleOutline
			)
		},

		/**
		 * @return {string} The status name.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		label() {
			return statusLabel(this.status)
		},
	},
}
</script>

<style scoped>
.export-status {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.export-status--success {
	color: var(--color-success-text);
}

.export-status--warning {
	color: var(--color-warning-text);
}

.export-status--error {
	color: var(--color-error-text);
}

.export-status--neutral {
	color: var(--color-text-maxcontrast);
}
</style>
