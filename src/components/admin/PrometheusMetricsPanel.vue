<!--
  - SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="metrics-panel" data-test="prometheus-metrics-panel">
		<div class="metrics-panel__header">
			<h3>{{ t('launchpad', 'Prometheus metrics') }}</h3>
			<NcButton
				variant="secondary"
				:disabled="!body"
				data-test="metrics-copy"
				@click="copy">
				<template #icon>
					<ContentCopy :size="18" />
				</template>
				{{ copied ? t('launchpad', 'Copied') : t('launchpad', 'Copy') }}
			</NcButton>
		</div>

		<div v-if="loading" class="metrics-panel__loading">
			<NcLoadingIcon :size="24" />
		</div>
		<p v-else-if="error" class="metrics-panel__error" data-test="metrics-error">
			{{ t('launchpad', 'Failed to load metrics.') }}
		</p>
		<pre v-else class="metrics-panel__pre" data-test="metrics-body">{{
			body
		}}</pre>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import { api } from '../../services/api.js'

/**
 * PrometheusMetricsPanel — reads `/api/metrics` on mount and renders the raw
 * exposition output in a read-only `<pre>` block with a copy-to-clipboard
 * button (prometheus-metrics spec, Operations Tab Surface requirement).
 */
export default {
	name: 'PrometheusMetricsPanel',

	components: {
		NcButton,
		NcLoadingIcon,
		ContentCopy,
	},

	data() {
		return {
			body: '',
			loading: true,
			error: false,
			copied: false,
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
			this.error = false
			try {
				const { data } = await api.getMetrics()
				this.body =
					typeof data === 'string' ? data : JSON.stringify(data, null, 2)
			} catch {
				this.error = true
			} finally {
				this.loading = false
			}
		},

		/** @spec openspec/specs/prometheus-metrics/spec.md */
		async copy() {
			try {
				if (navigator?.clipboard?.writeText) {
					await navigator.clipboard.writeText(this.body)
					this.copied = true
					setTimeout(() => {
						this.copied = false
					}, 2000)
				}
			} catch {
				// Clipboard may be blocked; leave the body visible for manual copy.
			}
		},
	},
}
</script>

<style scoped>
.metrics-panel__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 12px;
}

.metrics-panel__header h3 {
	margin: 0;
}

.metrics-panel__loading {
	padding: 16px 0;
}

.metrics-panel__error {
	color: var(--color-error);
}

.metrics-panel__pre {
	max-height: 320px;
	overflow: auto;
	padding: 12px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
	font-family: monospace;
	font-size: 12px;
	white-space: pre-wrap;
	word-break: break-all;
}
</style>
