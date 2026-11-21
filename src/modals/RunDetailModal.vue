<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal launchpad-modal--wide"
		size="large"
		:name="t('launchpad', 'Run details')"
		@close="$emit('close')">
		<div class="run-detail" data-testid="run-detail">
			<header>
				<p class="launchpad-modal__kicker" data-testid="run-detail-export">
					{{ exportData?.name ?? '' }}
				</p>
				<h2 class="launchpad-modal__title">
					{{ t('launchpad', 'Run details') }}
				</h2>
			</header>

			<p
				v-if="error"
				class="run-detail__error"
				role="alert"
				data-testid="run-detail-error">
				{{ error }}
			</p>

			<dl class="run-detail__facts">
				<dt>{{ t('launchpad', 'Started') }}</dt>
				<dd data-testid="run-detail-started">
					{{ formatDateTime(current.startedAt) }}
				</dd>
				<dt>{{ t('launchpad', 'Finished') }}</dt>
				<dd>{{ formatDateTime(current.finishedAt) || '–' }}</dd>
				<dt>{{ t('launchpad', 'Status') }}</dt>
				<dd><ExportStatus :status="current.status" /></dd>
				<dt>{{ t('launchpad', 'Triggered') }}</dt>
				<dd>
					{{
						current.triggeredBy === 'manual'
							? t('launchpad', 'By hand by {user}', {
									user: current.triggeredByUser ?? '',
								})
							: t('launchpad', 'On schedule')
					}}
				</dd>
				<template v-if="current.skipReason">
					<dt>{{ t('launchpad', 'Skipped because') }}</dt>
					<dd>{{ current.skipReason }}</dd>
				</template>
			</dl>

			<section aria-labelledby="run-detail-deliveries">
				<div class="run-detail__section-head">
					<h3 id="run-detail-deliveries">
						{{ t('launchpad', 'Deliveries') }}
					</h3>
					<NcButton
						v-if="hasFailedDelivery"
						variant="primary"
						:disabled="retrying"
						data-testid="run-detail-retry"
						@click="retry">
						<template #icon>
							<Refresh :size="20" />
						</template>
						{{ t('launchpad', 'Retry failed deliveries') }}
					</NcButton>
				</div>
				<p v-if="deliveries.length === 0">
					{{ t('launchpad', 'Nothing was delivered in this run.') }}
				</p>
				<table v-else class="run-detail__table">
					<thead>
						<tr>
							<th scope="col">
								{{ t('launchpad', 'Recipient') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'Channel') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'Status') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'Attempts') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'Error') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="(delivery, index) in deliveries"
							:key="`${delivery.recipientId}-${index}`"
							data-testid="run-detail-delivery">
							<td>{{ recipientName(delivery) }}</td>
							<td>{{ channelLabel(delivery.channel) }}</td>
							<td><ExportStatus :status="delivery.status" /></td>
							<td>{{ delivery.attemptCount ?? 0 }}</td>
							<td class="run-detail__error-cell">
								{{ delivery.error ?? '' }}
							</td>
						</tr>
					</tbody>
				</table>
			</section>

			<section aria-labelledby="run-detail-artefacts">
				<h3 id="run-detail-artefacts">
					{{ t('launchpad', 'Files') }}
				</h3>
				<p v-if="artefacts.length === 0">
					{{ t('launchpad', 'No files were made in this run.') }}
				</p>
				<table v-else class="run-detail__table">
					<thead>
						<tr>
							<th scope="col">
								{{ t('launchpad', 'Format') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'Size') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'SHA-256') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'File') }}
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="(artefact, index) in artefacts"
							:key="`${artefact.sha256}-${index}`"
							data-testid="run-detail-artefact">
							<td>{{ formatLabel(artefact.format) }}</td>
							<td>{{ formatBytes(artefact.sizeBytes) }}</td>
							<td>
								<code :title="artefact.sha256">{{
									String(artefact.sha256 ?? '').slice(0, 12)
								}}</code>
							</td>
							<td>
								<a
									v-if="artefactDownloadUrl(artefact.storagePath)"
									:href="artefactDownloadUrl(artefact.storagePath)"
									download
									data-testid="run-detail-download">
									{{ t('launchpad', 'Download') }}
								</a>
								<span v-else class="run-detail__muted">
									{{
										t(
											'launchpad',
											'Removed after the retention period',
										)
									}}
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</section>
		</div>
	</NcModal>
</template>

<script>
import { NcButton, NcModal } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import Refresh from 'vue-material-design-icons/Refresh.vue'
import ExportStatus from '../components/ScheduledExports/ExportStatus.vue'
import {
	NULLABLE_OBJECT_PROP,
	REQUIRED_FLAG_PROP,
} from '../constants/propDefinitions.js'
import { useExportsStore } from '../stores/scheduledExports.js'
import {
	artefactDownloadUrl,
	channelLabel,
	formatBytes,
	formatDateTime,
	formatLabel,
	recipientLabel,
	serverMessage,
} from '../utils/scheduledExports.js'

/**
 * RunDetailModal: one run of a scheduled export: when it fired, how it
 * ended, each delivery (channel, status, attempts, error) and each file
 * it made (format, size, hash, download). When a delivery failed for good
 * a Retry button asks the server to attempt those deliveries again.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
 */
export default {
	name: 'RunDetailModal',

	components: {
		ExportStatus,
		NcButton,
		NcModal,
		Refresh,
	},

	props: {
		open: REQUIRED_FLAG_PROP,

		/** The export the run belongs to. */
		exportData: NULLABLE_OBJECT_PROP,

		/** The run-row as the history listed it. */
		run: NULLABLE_OBJECT_PROP,
	},

	emits: ['close', 'retried'],

	/** @spec openspec/changes/scheduled-exports/tasks.md#task-15 */
	data() {
		return {
			loaded: null,
			retrying: false,
			error: '',
		}
	},

	computed: {
		/**
		 * @return {object} The run: as read from the server, else as listed.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
		 */
		current() {
			return this.loaded ?? this.run ?? {}
		},

		/**
		 * @return {object[]} The deliveries of the run.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
		 */
		deliveries() {
			return this.current.deliveries ?? []
		},

		/**
		 * @return {object[]} The files the run made.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
		 */
		artefacts() {
			return this.current.renderArtefacts ?? []
		},

		/**
		 * @return {boolean} Whether a delivery failed for good, so it can be retried.
		 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
		 */
		hasFailedDelivery() {
			return this.deliveries.some(
				(delivery) => delivery.status === 'failed-permanent',
			)
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Read the run with every delivery each time the dialog opens.
			 *
			 * @param {boolean} isOpen New open state.
			 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
			 */
			handler(isOpen) {
				if (isOpen) {
					this.load()
				}
			},
		},
	},

	methods: {
		t,
		artefactDownloadUrl,
		channelLabel,
		formatBytes,
		formatDateTime,
		formatLabel,

		/**
		 * Read the run from the server.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
		 */
		async load() {
			this.loaded = null
			this.error = ''
			if (!this.exportData?.id || !this.run?.id) {
				return
			}
			try {
				this.loaded = await useExportsStore().fetchRun(
					this.exportData.id,
					this.run.id,
				)
			} catch (e) {
				this.error = await serverMessage(
					e,
					t('launchpad', 'The run could not be loaded.'),
				)
			}
		},

		/**
		 * The recipient of a delivery, by its channel and address when the export still has it.
		 *
		 * @param {object} delivery The delivery.
		 * @return {string} The name.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-15
		 */
		recipientName(delivery) {
			const recipients = this.exportData?.recipients ?? []
			const index = recipients.findIndex(
				(r, i) => String(r.id ?? i) === String(delivery.recipientId),
			)
			if (index === -1) {
				return String(delivery.recipientId ?? '')
			}
			return recipientLabel(recipients[index], index)
		},

		/**
		 * Retry the deliveries that failed for good; the answer replaces the run.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
		 */
		async retry() {
			this.retrying = true
			this.error = ''
			try {
				this.loaded = await useExportsStore().retryRun(
					this.exportData.id,
					this.current.id,
				)
				this.$emit('retried', this.loaded)
			} catch (e) {
				this.error = await serverMessage(
					e,
					t('launchpad', 'The deliveries could not be retried.'),
				)
			} finally {
				this.retrying = false
			}
		},
	},
}
</script>

<style scoped>
.run-detail {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 20px;
}

.run-detail h3 {
	margin: 0;
	font-size: 1.1em;
	font-weight: bold;
}

.run-detail__facts {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: 4px 16px;
	margin: 0;
}

.run-detail__facts dt {
	font-weight: bold;
}

.run-detail__facts dd {
	margin: 0;
}

.run-detail__section-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
	margin-bottom: 8px;
}

.run-detail__table {
	width: 100%;
	border-collapse: collapse;
}

.run-detail__table th,
.run-detail__table td {
	padding: 4px 8px;
	text-align: start;
	vertical-align: top;
	border-bottom: 1px solid var(--color-border);
}

.run-detail__error-cell {
	color: var(--color-error-text);
	overflow-wrap: anywhere;
}

.run-detail__error {
	color: var(--color-error-text);
}

.run-detail__muted {
	color: var(--color-text-maxcontrast);
}
</style>
