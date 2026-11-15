<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal launchpad-modal--wide"
		size="large"
		:name="t('launchpad', 'Preview')"
		@close="$emit('close')">
		<div class="export-preview" data-testid="export-preview">
			<header>
				<p class="launchpad-modal__kicker">
					{{ exportData?.name ?? '' }}
				</p>
				<h2 class="launchpad-modal__title">
					{{ t('launchpad', 'Preview') }}
				</h2>
				<p class="export-preview__hint">
					{{
						t(
							'launchpad',
							'The preview is made the way a recipient would get it, but nothing is sent.',
						)
					}}
				</p>
			</header>

			<div class="export-preview__choices">
				<NcSelect
					v-model="format"
					:inputLabel="t('launchpad', 'Format')"
					:options="formatOptions"
					label="label"
					:clearable="false"
					data-testid="preview-format" />
				<NcSelect
					v-model="recipient"
					:inputLabel="t('launchpad', 'As received by')"
					:options="recipientOptions"
					label="label"
					:clearable="false"
					data-testid="preview-recipient" />
				<NcButton
					variant="primary"
					:disabled="loading || !format"
					data-testid="preview-render"
					@click="render">
					{{ t('launchpad', 'Make preview') }}
				</NcButton>
			</div>

			<div v-if="loading" class="export-preview__loading">
				<NcLoadingIcon :size="32" />
			</div>
			<p
				v-else-if="error"
				class="export-preview__error"
				role="alert"
				data-testid="preview-error">
				{{ error }}
			</p>
			<template v-else-if="result">
				<object
					v-if="result.format === 'pdf'"
					class="export-preview__frame"
					:data="result.url"
					type="application/pdf"
					:aria-label="t('launchpad', 'Preview of the PDF')"
					data-testid="preview-pdf">
					<a :href="result.url" :download="result.filename">{{
						t('launchpad', 'Download the preview')
					}}</a>
				</object>
				<img
					v-else-if="result.format === 'png'"
					class="export-preview__image"
					:src="result.url"
					:alt="
						t('launchpad', 'Preview of {name}', {
							name: exportData?.name ?? '',
						})
					"
					data-testid="preview-png" />
				<p v-else data-testid="preview-download">
					{{ t('launchpad', 'This format cannot be shown here.') }}
					<a :href="result.url" :download="result.filename">{{
						t('launchpad', 'Download {filename}', {
							filename: result.filename,
						})
					}}</a>
				</p>
			</template>
		</div>
	</NcModal>
</template>

<script>
import {
	NcButton,
	NcLoadingIcon,
	NcModal,
	NcSelect,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import {
	NULLABLE_OBJECT_PROP,
	REQUIRED_FLAG_PROP,
} from '../constants/propDefinitions.js'
import { useExportsStore } from '../stores/scheduledExports.js'
import {
	EXPORT_FORMATS,
	formatLabel,
	recipientId,
	recipientLabel,
	serverMessage,
} from '../utils/scheduledExports.js'

/**
 * PreviewModal: render a scheduled export in one format, filtered for one
 * recipient, without delivering it and without a run-row (REQ-SCH-009).
 * A PDF or PNG shows in the dialog; a CSV or XLSX is offered as a download.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-16
 */
export default {
	name: 'PreviewModal',

	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcSelect,
	},

	props: {
		open: REQUIRED_FLAG_PROP,

		/** The export to preview. */
		exportData: NULLABLE_OBJECT_PROP,
	},

	emits: ['close'],

	/** @spec openspec/changes/scheduled-exports/tasks.md#task-16 */
	data() {
		return {
			format: null,
			recipient: null,
			loading: false,
			error: '',
			result: null,
		}
	},

	computed: {
		/**
		 * @return {object[]} The four formats as options.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-16
		 */
		formatOptions() {
			return EXPORT_FORMATS.map((id) => ({ id, label: formatLabel(id) }))
		},

		/**
		 * @return {object[]} "No recipient" and each recipient of the export.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-16
		 */
		recipientOptions() {
			return [
				{ id: '', label: t('launchpad', 'No recipient (all data)') },
				...(this.exportData?.recipients ?? []).map((r, i) => ({
					id: recipientId(r, i),
					label: recipientLabel(r, i),
				})),
			]
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Start each opening with the export's first format and no recipient.
			 *
			 * @param {boolean} isOpen New open state.
			 * @spec openspec/changes/scheduled-exports/tasks.md#task-16
			 */
			handler(isOpen) {
				if (!isOpen) {
					this.clear()
					return
				}
				const first = this.exportData?.renderTargets?.[0]?.format ?? 'pdf'
				this.format =
					this.formatOptions.find((option) => option.id === first)
					?? this.formatOptions[0]
				this.recipient = this.recipientOptions[0]
				this.error = ''
			},
		},
	},

	/** Let go of the rendered file. */
	beforeUnmount() {
		this.clear()
	},

	methods: {
		t,

		/**
		 * Drop the shown preview and free its object URL.
		 *
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-16
		 */
		clear() {
			if (this.result?.url) {
				URL.revokeObjectURL(this.result.url)
			}
			this.result = null
		},

		/**
		 * Ask the server for the preview and show it.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-009-manual-run-now-and-preview-actions
		 */
		async render() {
			const format = this.format?.id
			if (!format || !this.exportData?.id) {
				return
			}
			this.clear()
			this.loading = true
			this.error = ''
			try {
				const blob = await useExportsStore().previewExport(
					this.exportData.id,
					this.recipient?.id ?? '',
					format,
				)
				const base = String(this.exportData.name || 'export').replace(
					/[\\/:*?"<>|]+/g,
					'-',
				)
				this.result = {
					format,
					url: URL.createObjectURL(blob),
					filename: `${base}.${format}`,
				}
			} catch (e) {
				this.error = await serverMessage(
					e,
					t('launchpad', 'The preview could not be made.'),
				)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>

<style scoped>
.export-preview {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.export-preview__hint {
	color: var(--color-text-maxcontrast);
	margin: 0;
}

.export-preview__choices {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	align-items: flex-end;
}

.export-preview__choices > :not(:last-child) {
	flex: 1 1 200px;
}

.export-preview__frame {
	width: 100%;
	height: 60vh;
	border: 1px solid var(--color-border);
}

.export-preview__image {
	max-width: 100%;
	border: 1px solid var(--color-border);
}

.export-preview__loading {
	display: flex;
	justify-content: center;
}

.export-preview__error {
	color: var(--color-error-text);
}
</style>
