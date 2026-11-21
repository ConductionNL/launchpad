<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section
		class="export-runs"
		data-testid="export-run-history"
		aria-labelledby="export-runs-title">
		<div class="export-runs__head">
			<h3 id="export-runs-title">
				{{
					t('launchpad', 'Runs of {name}', { name: exportData.name ?? '' })
				}}
			</h3>
			<NcButton
				variant="tertiary"
				data-testid="export-runs-back"
				@click="$emit('back')">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
				{{ t('launchpad', 'Back to the list') }}
			</NcButton>
		</div>

		<p
			v-if="error"
			class="export-runs__error"
			role="alert"
			data-testid="export-runs-error">
			{{ error }}
		</p>
		<CnDataTable
			v-else
			:columns="columns"
			:rows="runs"
			:loading="loading"
			:emptyText="t('launchpad', 'This export has not run yet.')"
			rowKey="id"
			@rowClick="(row) => $emit('openRun', row)">
			<template #column-startedAt="{ row }">
				<button
					type="button"
					class="export-runs__open"
					data-testid="export-run-open"
					@click.stop="$emit('openRun', row)">
					{{ formatDateTime(row.startedAt) }}
				</button>
			</template>
			<template #column-status="{ row }">
				<ExportStatus :status="row.status" />
			</template>
			<template #column-triggeredBy="{ row }">
				{{
					row.triggeredBy === 'manual'
						? t('launchpad', 'By hand')
						: t('launchpad', 'On schedule')
				}}
			</template>
			<template #column-recipients="{ row }">
				{{ (row.deliveries ?? []).length }}
			</template>
			<template #column-outcome="{ row }">
				<span data-testid="export-run-outcome">{{ outcome(row) }}</span>
			</template>
		</CnDataTable>
	</section>
</template>

<script>
import { CnDataTable, NcButton } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import ExportStatus from './ExportStatus.vue'
import { REQUIRED_OBJECT_PROP } from '../../constants/propDefinitions.js'
import { useExportsStore } from '../../stores/scheduledExports.js'
import { formatDateTime, serverMessage } from '../../utils/scheduledExports.js'

/**
 * ExportRunHistory: the past runs of one scheduled export, newest first,
 * with when it fired, how it ended, who triggered it, how many recipients
 * it had and how their deliveries went. A run opens its detail.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-14
 */
export default {
	name: 'ExportRunHistory',

	components: {
		ArrowLeft,
		CnDataTable,
		ExportStatus,
		NcButton,
	},

	props: {
		/** The export whose runs are shown. */
		exportData: REQUIRED_OBJECT_PROP,
	},

	emits: ['back', 'openRun'],

	/** @spec openspec/changes/scheduled-exports/tasks.md#task-14 */
	data() {
		return {
			loading: false,
			error: '',
		}
	},

	computed: {
		/**
		 * @return {object[]} The run-rows of this export, as the store holds them.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-14
		 */
		runs() {
			return useExportsStore().runsByExport[this.exportData.id] ?? []
		},

		/**
		 * @return {object[]} The table columns.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-14
		 */
		columns() {
			return [
				{ key: 'startedAt', label: t('launchpad', 'Started') },
				{ key: 'status', label: t('launchpad', 'Status') },
				{ key: 'triggeredBy', label: t('launchpad', 'Triggered') },
				{ key: 'recipients', label: t('launchpad', 'Recipients') },
				{ key: 'outcome', label: t('launchpad', 'Deliveries') },
			]
		},
	},

	watch: {
		'exportData.id': {
			immediate: true,
			/**
			 * Load the runs of the export shown.
			 *
			 * @spec openspec/changes/scheduled-exports/tasks.md#task-14
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		t,
		formatDateTime,

		/**
		 * Read the run history.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-14
		 */
		async load() {
			if (!this.exportData.id) {
				return
			}
			this.loading = true
			this.error = ''
			try {
				await useExportsStore().fetchRuns(this.exportData.id)
			} catch (e) {
				this.error = await serverMessage(
					e,
					t('launchpad', 'The runs could not be loaded.'),
				)
			} finally {
				this.loading = false
			}
		},

		/**
		 * How the deliveries of a run went, counted by outcome.
		 *
		 * @param {object} run The run-row.
		 * @return {string} For example "Delivered: 2, failed: 1".
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-14
		 */
		outcome(run) {
			const deliveries = run.deliveries ?? []
			if (run.skipReason && deliveries.length === 0) {
				return t('launchpad', 'Skipped: {reason}', {
					reason: run.skipReason,
				})
			}
			const count = (statuses) =>
				deliveries.filter((d) => statuses.includes(d.status)).length
			const parts = []
			const delivered = count(['success'])
			const failed = count(['failed-permanent'])
			const waiting = count(['pending', 'retrying'])
			if (delivered > 0) {
				parts.push(
					t('launchpad', 'delivered: {count}', { count: delivered }),
				)
			}
			if (failed > 0) {
				parts.push(t('launchpad', 'failed: {count}', { count: failed }))
			}
			if (waiting > 0) {
				parts.push(t('launchpad', 'waiting: {count}', { count: waiting }))
			}
			return parts.length > 0
				? parts.join(', ')
				: t('launchpad', 'No deliveries')
		},
	},
}
</script>

<style scoped>
.export-runs {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.export-runs__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.export-runs__head h3 {
	margin: 0;
	font-size: 1.1em;
	font-weight: bold;
}

.export-runs__open {
	background: none;
	border: 0;
	padding: 0;
	margin: 0;
	min-height: auto;
	color: var(--color-main-text);
	text-decoration: underline;
	cursor: pointer;
}

.export-runs__error {
	color: var(--color-error-text);
}
</style>
