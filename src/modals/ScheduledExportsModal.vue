<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		class="launchpad-modal launchpad-modal--wide"
		size="large"
		:name="t('launchpad', 'Scheduled exports')"
		@close="$emit('close')">
		<div class="scheduled-exports" data-testid="scheduled-exports">
			<ExportRunHistory
				v-if="historyFor"
				:exportData="historyFor"
				@back="historyFor = null"
				@openRun="(run) => (detailRun = run)" />

			<template v-else>
				<div class="scheduled-exports__head">
					<h2 class="launchpad-modal__title">
						{{ t('launchpad', 'Scheduled exports') }}
					</h2>
					<NcButton
						variant="primary"
						data-testid="scheduled-exports-new"
						@click="openEditor(null)">
						<template #icon>
							<Plus :size="20" />
						</template>
						{{ t('launchpad', 'New scheduled export') }}
					</NcButton>
				</div>

				<p
					v-if="error"
					class="scheduled-exports__error"
					role="alert"
					data-testid="scheduled-exports-error">
					{{ error }}
				</p>
				<CnDataTable
					:columns="columns"
					:rows="store.exports"
					:loading="store.loading"
					:emptyText="
						t(
							'launchpad',
							'No scheduled exports yet. Make one to send this dashboard on a schedule.',
						)
					"
					rowKey="id">
					<template #column-name="{ row }">
						<span data-testid="scheduled-export-name">{{
							row.name
						}}</span>
					</template>
					<template #column-subject="{ row }">
						{{ subjectName(row) }}
					</template>
					<template #column-nextRunAt="{ row }">
						<span data-testid="scheduled-export-next">{{
							nextRun(row)
						}}</span>
					</template>
					<template #column-lastRunStatus="{ row }">
						<ExportStatus
							:status="
								store.running[row.id] ? 'running' : row.lastRunStatus
							" />
					</template>
					<template #row-actions="{ row }">
						<NcActions
							:aria-label="
								t('launchpad', 'Actions for {name}', {
									name: row.name,
								})
							"
							:forceMenu="true">
							<NcActionButton
								:closeAfterClick="true"
								data-testid="scheduled-export-edit"
								@click="openEditor(row)">
								<template #icon>
									<Pencil :size="20" />
								</template>
								{{ t('launchpad', 'Edit') }}
							</NcActionButton>
							<NcActionButton
								:closeAfterClick="true"
								:disabled="Boolean(store.running[row.id])"
								data-testid="scheduled-export-run"
								@click="runNow(row)">
								<template #icon>
									<Play :size="20" />
								</template>
								{{ t('launchpad', 'Run now') }}
							</NcActionButton>
							<NcActionButton
								:closeAfterClick="true"
								data-testid="scheduled-export-preview"
								@click="previewFor = row">
								<template #icon>
									<Eye :size="20" />
								</template>
								{{ t('launchpad', 'Preview') }}
							</NcActionButton>
							<NcActionButton
								:closeAfterClick="true"
								data-testid="scheduled-export-runs"
								@click="historyFor = row">
								<template #icon>
									<History :size="20" />
								</template>
								{{ t('launchpad', 'View runs') }}
							</NcActionButton>
							<NcActionButton
								:closeAfterClick="true"
								data-testid="scheduled-export-delete"
								@click="deleteTarget = row">
								<template #icon>
									<Delete :size="20" />
								</template>
								{{ t('launchpad', 'Delete') }}
							</NcActionButton>
						</NcActions>
					</template>
				</CnDataTable>
			</template>
		</div>

		<ScheduledExportModal
			v-if="editorOpen"
			:open="true"
			:exportData="editing"
			:subjects="subjects"
			:defaultSubject="defaultSubject"
			@saved="onSaved"
			@close="closeEditor" />
		<RunDetailModal
			v-if="detailRun"
			:open="true"
			:exportData="historyFor"
			:run="detailRun"
			@close="detailRun = null" />
		<PreviewModal
			v-if="previewFor"
			:open="true"
			:exportData="previewFor"
			@close="previewFor = null" />
		<CnConfirmDialog
			v-if="deleteTarget"
			:dialogTitle="t('launchpad', 'Delete this scheduled export?')"
			:message="
				t(
					'launchpad',
					'{name} no longer runs. Its past runs stay in the audit log.',
					{ name: deleteTarget.name },
				)
			"
			:confirmLabel="t('launchpad', 'Delete')"
			@confirm="confirmDelete"
			@close="deleteTarget = null" />
	</NcModal>
</template>

<script>
import {
	CnConfirmDialog,
	CnDataTable,
	NcActionButton,
	NcActions,
	NcButton,
	NcModal,
} from '@conduction/nextcloud-vue'
import { showError, showInfo, showSuccess, showWarning } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import Delete from 'vue-material-design-icons/Delete.vue'
import Eye from 'vue-material-design-icons/Eye.vue'
import History from 'vue-material-design-icons/History.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Play from 'vue-material-design-icons/Play.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import ExportRunHistory from '../components/ScheduledExports/ExportRunHistory.vue'
import ExportStatus from '../components/ScheduledExports/ExportStatus.vue'
import PreviewModal from './PreviewModal.vue'
import RunDetailModal from './RunDetailModal.vue'
import ScheduledExportModal from './ScheduledExportModal.vue'
import {
	LIST_PROP,
	NULLABLE_OBJECT_PROP,
	REQUIRED_FLAG_PROP,
} from '../constants/propDefinitions.js'
import { useExportsStore } from '../stores/scheduledExports.js'
import { formatDateTime, serverMessage } from '../utils/scheduledExports.js'

/**
 * ScheduledExportsModal: the scheduled exports the user may see, opened
 * from the dashboard menu. Each row shows the export's name, what it
 * exports, its next run and how its last run went, with Edit, Run now,
 * Preview, View runs and Delete. View runs swaps the list for the run
 * history; a run opens its detail.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
 */
export default {
	name: 'ScheduledExportsModal',

	components: {
		CnConfirmDialog,
		CnDataTable,
		Delete,
		ExportRunHistory,
		ExportStatus,
		Eye,
		History,
		NcActionButton,
		NcActions,
		NcButton,
		NcModal,
		Pencil,
		Play,
		Plus,
		PreviewModal,
		RunDetailModal,
		ScheduledExportModal,
	},

	props: {
		open: REQUIRED_FLAG_PROP,

		/** What can be exported: `{type: 'dashboard'|'widget', id, label}`. */
		subjects: LIST_PROP,

		/** The subject a new export starts with: `{type, id}`. */
		defaultSubject: NULLABLE_OBJECT_PROP,
	},

	emits: ['close'],

	/** @spec openspec/changes/scheduled-exports/tasks.md#task-13 */
	data() {
		return {
			editorOpen: false,
			editing: null,
			historyFor: null,
			detailRun: null,
			previewFor: null,
			deleteTarget: null,
			error: '',
		}
	},

	computed: {
		/**
		 * @return {object} The scheduled-exports store.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		store() {
			return useExportsStore()
		},

		/**
		 * @return {object[]} The table columns.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		columns() {
			return [
				{ key: 'name', label: t('launchpad', 'Name') },
				{ key: 'subject', label: t('launchpad', 'Exports') },
				{ key: 'nextRunAt', label: t('launchpad', 'Next run') },
				{ key: 'lastRunStatus', label: t('launchpad', 'Last run') },
			]
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Read the list each time the dialog opens.
			 *
			 * @param {boolean} isOpen New open state.
			 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
			 */
			handler(isOpen) {
				if (isOpen) {
					this.historyFor = null
					this.load()
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * Read the exports.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		async load() {
			this.error = ''
			try {
				await this.store.fetchExports()
			} catch (e) {
				this.error = await serverMessage(
					e,
					t('launchpad', 'The scheduled exports could not be loaded.'),
				)
			}
		},

		/**
		 * What an export exports, by name when the dashboard or widget is known.
		 *
		 * @param {object} row The export.
		 * @return {string} The subject.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		subjectName(row) {
			const known = this.subjects.find(
				(s) =>
					s.type === row.subjectType
					&& String(s.id) === String(row.subjectId),
			)
			const name = known?.label ?? row.subjectId ?? ''
			return row.subjectType === 'widget'
				? t('launchpad', 'Widget: {name}', { name })
				: t('launchpad', 'Dashboard: {name}', { name })
		},

		/**
		 * The next run, or why there is none.
		 *
		 * @param {object} row The export.
		 * @return {string} The date and time, or "Off".
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		nextRun(row) {
			if (row.enabled === false) {
				return t('launchpad', 'Off')
			}
			return formatDateTime(row.nextRunAt) || t('launchpad', 'Not planned')
		},

		/**
		 * Open the editor for an export, or for a new one.
		 *
		 * @param {object|null} row The export, or null.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		openEditor(row) {
			this.editing = row
			this.editorOpen = true
		},

		/**
		 * Close the editor.
		 *
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		closeEditor() {
			this.editorOpen = false
			this.editing = null
		},

		/**
		 * The editor saved: the store already holds the export.
		 *
		 * @param {object} saved The saved export.
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-12
		 */
		onSaved(saved) {
			showSuccess(
				t('launchpad', '{name} is saved.', { name: saved?.name ?? '' }),
			)
		},

		/**
		 * "Run now": fire the export and report how the run ended.
		 *
		 * @param {object} row The export.
		 * @return {Promise<void>}
		 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-009-manual-run-now-and-preview-actions
		 */
		async runNow(row) {
			showInfo(t('launchpad', '{name} is running…', { name: row.name }))
			try {
				const run = await this.store.runExportNow(row.id)
				if (run.status === 'success') {
					showSuccess(
						t('launchpad', '{name} ran and was delivered.', {
							name: row.name,
						}),
					)
				} else if (run.status === 'failed') {
					showError(
						t('launchpad', '{name} failed. Open its runs to see why.', {
							name: row.name,
						}),
					)
				} else if (run.status === 'running') {
					showInfo(
						t(
							'launchpad',
							'{name} is still running. Its runs show when it is done.',
							{ name: row.name },
						),
					)
				} else {
					showWarning(
						t(
							'launchpad',
							'{name} ran, but not every delivery succeeded.',
							{ name: row.name },
						),
					)
				}
				await this.store.fetchExports()
			} catch (e) {
				showError(
					await serverMessage(
						e,
						t('launchpad', '{name} could not be run.', {
							name: row.name,
						}),
					),
				)
			}
		},

		/**
		 * Delete the export the confirmation was for.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/scheduled-exports/tasks.md#task-13
		 */
		async confirmDelete() {
			const target = this.deleteTarget
			this.deleteTarget = null
			if (!target) {
				return
			}
			try {
				await this.store.deleteExport(target.id)
			} catch (e) {
				this.error = await serverMessage(
					e,
					t('launchpad', 'The scheduled export could not be deleted.'),
				)
			}
		},
	},
}
</script>

<style scoped>
.scheduled-exports {
	padding: 24px;
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.scheduled-exports__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 8px;
}

.scheduled-exports__error {
	color: var(--color-error-text);
}
</style>
