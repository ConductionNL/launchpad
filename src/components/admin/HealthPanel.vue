<!--
  - SPDX-FileCopyrightText: 2026 MyDash Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="health-panel" data-test="health-panel">
		<h3>{{ t('launchpad', 'Health') }}</h3>
		<span
			v-if="loading"
			class="health-panel__badge health-panel__badge--loading"
			data-test="health-badge">
			{{ t('launchpad', 'Checking…') }}
		</span>
		<span
			v-else-if="healthy"
			class="health-panel__badge health-panel__badge--ok"
			data-test="health-badge">
			{{ t('launchpad', 'Healthy') }}
		</span>
		<span
			v-else
			class="health-panel__badge health-panel__badge--bad"
			data-test="health-badge">
			{{ t('launchpad', 'Degraded') }}
		</span>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { api } from '../../services/api.js'

/**
 * HealthPanel — reads `/api/health` on mount and renders a green "Healthy"
 * badge when `status === 'ok'`, otherwise a red "Degraded" badge
 * (prometheus-metrics spec, health badge requirement).
 */
export default {
	name: 'HealthPanel',

	data() {
		return {
			healthy: false,
			loading: true,
		}
	},

	/** @spec openspec/specs/prometheus-metrics/spec.md */
	created() {
		this.load()
	},

	methods: {
		t,

		/** @spec openspec/specs/prometheus-metrics/spec.md */
		async load() {
			this.loading = true
			try {
				const { data } = await api.getHealth()
				const payload = data?.data ?? data ?? {}
				this.healthy = payload.status === 'ok'
			} catch (e) {
				// Non-200 / network error → degraded.
				this.healthy = false
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.health-panel__badge {
	display: inline-block;
	padding: 4px 12px;
	border-radius: var(--border-radius-pill);
	font-size: 13px;
	font-weight: 600;
}

.health-panel__badge--ok {
	background: var(--color-success, #2d7d46);
	color: #fff;
}

.health-panel__badge--bad {
	background: var(--color-error, #d32f2f);
	color: #fff;
}

.health-panel__badge--loading {
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
}
</style>
