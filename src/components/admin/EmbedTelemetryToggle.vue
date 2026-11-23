<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="embed-telemetry-toggle" data-test="embed-telemetry-toggle">
		<h3>{{ t('launchpad', 'Embed usage') }}</h3>
		<NcCheckboxRadioSwitch
			:modelValue="enabled"
			:disabled="loading"
			data-test="embed-telemetry-switch"
			@update:modelValue="onToggle">
			{{ t('launchpad', 'Collect embed usage telemetry') }}
		</NcCheckboxRadioSwitch>
		<p class="embed-telemetry-toggle__hint">
			{{ t('launchpad', 'Counts views and interactions of embedded widgets and dashboards per token, without IP addresses or visitor identifiers. When off, no usage is recorded.') }}
		</p>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { embedApi } from '../../services/embedApi.js'
import { logger } from '../../utils/logger.js'

/**
 * EmbedTelemetryToggle — Beheer ▸ Operations switch for embed usage
 * telemetry (embedded-analytics task 30). The server writes the change to
 * the audit log; the switch rolls back when saving fails.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-30
 */
export default {
	name: 'EmbedTelemetryToggle',

	components: {
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			enabled: true,
			loading: true,
		}
	},

	/** @spec openspec/changes/embedded-analytics/tasks.md#task-30 */
	created() {
		this.load()
	},

	methods: {
		t,

		/** @spec openspec/changes/embedded-analytics/tasks.md#task-30 */
		async load() {
			this.loading = true
			try {
				this.enabled = await embedApi.telemetryEnabled()
			} catch (error) {
				logger.error('Failed to load embed telemetry setting:', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Switch telemetry, showing the new state at once and rolling back if saving fails.
		 *
		 * @param {boolean} value The requested state.
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-30
		 */
		async onToggle(value) {
			const previous = this.enabled
			this.enabled = value
			try {
				this.enabled = await embedApi.setTelemetryEnabled(value)
			} catch (error) {
				this.enabled = previous
				logger.error('Failed to save embed telemetry setting:', error)
			}
		},
	},
}
</script>

<style scoped>
.embed-telemetry-toggle__hint {
	color: var(--color-text-maxcontrast);
	margin-top: 8px;
	font-size: 13px;
}
</style>
