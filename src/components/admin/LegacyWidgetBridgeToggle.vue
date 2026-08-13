<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="bridge-toggle" data-test="legacy-widget-bridge-toggle">
		<h3>{{ t('launchpad', 'Legacy widget bridge') }}</h3>
		<NcCheckboxRadioSwitch
			:model-value="enabled"
			:disabled="loading"
			data-test="bridge-toggle-switch"
			@update:modelValue="onToggle">
			{{ t('launchpad', 'Enable the legacy widget bridge') }}
		</NcCheckboxRadioSwitch>
		<p class="bridge-toggle__hint">
			{{
				t(
					'launchpad',
					'When disabled, existing dashboards that embed bridged Nextcloud widgets render an "Unavailable" state until the bridge is re-enabled.',
				)
			}}
		</p>
	</div>
</template>

<script>
import { NcCheckboxRadioSwitch } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'

/**
 * LegacyWidgetBridgeToggle — Beheer ▸ Operations enable/disable switch for
 * the legacy widget bridge (legacy-widget-bridge spec, Bridge Toggle
 * requirement). Persists via the admin settings API and warns about the
 * impact on existing bridged placements.
 */
export default {
	name: 'LegacyWidgetBridgeToggle',

	components: {
		NcCheckboxRadioSwitch,
	},

	data() {
		return {
			enabled: true,
			loading: true,
		}
	},

	/** @spec openspec/specs/legacy-widget-bridge/spec.md */
	created() {
		this.load()
	},

	methods: {
		t,

		/** @spec openspec/specs/legacy-widget-bridge/spec.md */
		async load() {
			this.loading = true
			try {
				const { data } = await api.getAdminSettings()
				const settings = data?.data ?? data ?? {}
				this.enabled = settings.legacyWidgetBridgeEnabled !== false
			} catch (error) {
				console.error('Failed to load bridge setting:', error)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Flip the bridge on or off, optimistically updating the switch and
		 * rolling back to `previous` if the save fails.
		 *
		 * @param {boolean} value Requested new state.
		 * @spec openspec/specs/legacy-widget-bridge/spec.md
		 */
		async onToggle(value) {
			const previous = this.enabled
			this.enabled = value
			try {
				await api.updateAdminSettings({ legacyWidgetBridgeEnabled: value })
			} catch (error) {
				// Roll back so the switch never lies about the persisted state.
				this.enabled = previous
				console.error('Failed to save bridge setting:', error)
			}
		},
	},
}
</script>

<style scoped>
.bridge-toggle__hint {
	color: var(--color-text-maxcontrast);
	margin-top: 8px;
	font-size: 13px;
}
</style>
