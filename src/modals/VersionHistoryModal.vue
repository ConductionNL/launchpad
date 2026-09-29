<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="open"
		size="normal"
		:name="t('launchpad', 'Version history')"
		@close="$emit('close')">
		<div class="launchpad-versions" data-testid="version-history">
			<h2>{{ t('launchpad', 'Version history') }}</h2>

			<div v-if="loading" class="launchpad-versions__loading">
				<NcLoadingIcon :size="32" />
			</div>

			<p
				v-else-if="error"
				class="launchpad-versions__error"
				role="alert"
				data-testid="version-error">
				{{ error }}
			</p>

			<p v-else-if="!modeSupported" data-testid="version-unsupported">
				{{
					t(
						'launchpad',
						'This dashboard is stored in a way that does not keep versions.',
					)
				}}
			</p>

			<template v-else>
				<div class="launchpad-versions__save">
					<NcTextField
						v-model="note"
						:label="
							t('launchpad', 'Note for this version (optional)')
						" />
					<NcButton
						variant="secondary"
						:disabled="saving"
						data-testid="version-save"
						@click="saveVersion">
						{{ t('launchpad', 'Save this version now') }}
					</NcButton>
				</div>

				<p v-if="sortedVersions.length === 0">
					{{ t('launchpad', 'No versions saved yet.') }}
				</p>
				<table v-else class="launchpad-versions__table">
					<thead>
						<tr>
							<th scope="col">
								{{ t('launchpad', 'Version') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'Saved on') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'By') }}
							</th>
							<th scope="col">
								{{ t('launchpad', 'Note') }}
							</th>
							<th scope="col">
								<span class="hidden-visually">{{
									t('launchpad', 'Actions')
								}}</span>
							</th>
						</tr>
					</thead>
					<tbody>
						<tr
							v-for="version in sortedVersions"
							:key="version.versionNumber"
							data-testid="version-row">
							<td>{{ version.versionNumber }}</td>
							<td>{{ formatDate(version.createdAt) }}</td>
							<td>{{ version.createdBy }}</td>
							<td>{{ noteLabel(version.note) }}</td>
							<td>
								<NcButton
									variant="tertiary"
									data-testid="version-restore"
									@click="askRestore(version)">
									{{ t('launchpad', 'Restore') }}
								</NcButton>
							</td>
						</tr>
					</tbody>
				</table>
			</template>
		</div>

		<RestoreVersionDialog
			:open="restoreTarget !== null"
			:date="restoreTarget ? formatDate(restoreTarget.createdAt) : ''"
			@update:open="onRestoreDialog"
			@confirm="confirmRestore" />
	</NcModal>
</template>

<script>
import {
	NcButton,
	NcLoadingIcon,
	NcModal,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import RestoreVersionDialog from '../dialogs/RestoreVersionDialog.vue'
import { api } from '../services/api.js'

/**
 * VersionHistoryModal: the saved versions of one dashboard, newest first,
 * with "Save this version now" and a confirmed restore. After a restore the
 * list is read again, so the `pre-restore` version that makes the restore
 * reversible shows at the top, and `restored` tells the page to reload.
 *
 * @spec openspec/changes/dashboard-version-history-ui/specs/dashboard-versioning/spec.md
 */
export default {
	name: 'VersionHistoryModal',

	components: {
		NcButton,
		NcLoadingIcon,
		NcModal,
		NcTextField,
		RestoreVersionDialog,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		dashboardUuid: {
			type: String,
			default: '',
		},
	},

	emits: ['close', 'restored'],

	data() {
		return {
			versions: [],
			modeSupported: true,
			loading: false,
			saving: false,
			error: '',
			note: '',
			restoreTarget: null,
		}
	},

	computed: {
		/** @return {Array<object>} Versions, newest first. */
		sortedVersions() {
			return [...this.versions].sort(
				(a, b) => b.versionNumber - a.versionNumber,
			)
		},
	},

	watch: {
		open: {
			immediate: true,
			handler(isOpen) {
				if (isOpen && this.dashboardUuid) {
					this.load()
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * Read the version list.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/dashboard-version-history-ui/specs/dashboard-versioning/spec.md
		 */
		async load() {
			this.loading = true
			this.error = ''
			try {
				const { data } = await api.listVersions(this.dashboardUuid)
				this.versions = Array.isArray(data?.versions) ? data.versions : []
				this.modeSupported = data?.modeSupported !== false
			} catch {
				this.error = t(
					'launchpad',
					'The version history could not be loaded.',
				)
			} finally {
				this.loading = false
			}
		},

		/**
		 * Save the dashboard as it is now, with the typed note.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/dashboard-version-history-ui/specs/dashboard-versioning/spec.md
		 */
		async saveVersion() {
			this.saving = true
			this.error = ''
			try {
				const note = this.note.trim() === '' ? null : this.note.trim()
				const { data } = await api.createVersion(this.dashboardUuid, note)
				if (data?.version) {
					this.versions = [data.version, ...this.versions]
				}
				this.note = ''
			} catch {
				this.error = t('launchpad', 'The version could not be saved.')
			} finally {
				this.saving = false
			}
		},

		/**
		 * Open the confirmation for one version.
		 *
		 * @param {object} version The version row.
		 * @spec openspec/changes/dashboard-version-history-ui/specs/dashboard-versioning/spec.md
		 */
		askRestore(version) {
			this.restoreTarget = version
		},

		/**
		 * The confirmation closed without confirming.
		 *
		 * @param {boolean} isOpen New open state.
		 * @spec openspec/changes/dashboard-version-history-ui/specs/dashboard-versioning/spec.md
		 */
		onRestoreDialog(isOpen) {
			if (!isOpen) {
				this.restoreTarget = null
			}
		},

		/**
		 * Restore the chosen version, read the list again and tell the page.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/dashboard-version-history-ui/specs/dashboard-versioning/spec.md
		 */
		async confirmRestore() {
			const target = this.restoreTarget
			this.restoreTarget = null
			if (!target) {
				return
			}
			try {
				await api.restoreVersion(this.dashboardUuid, target.versionNumber)
			} catch {
				this.error = t('launchpad', 'The version could not be restored.')
				return
			}
			this.$emit('restored', target)
			await this.load()
		},

		/**
		 * Show a stored `Y-m-d H:i:s` timestamp in the viewer's locale.
		 *
		 * @param {string} value Timestamp from the server.
		 * @return {string} Formatted date and time, or the raw value.
		 * @spec openspec/changes/dashboard-version-history-ui/specs/dashboard-versioning/spec.md
		 */
		formatDate(value) {
			if (!value) {
				return ''
			}
			const parsed = new Date(String(value).replace(' ', 'T'))
			if (Number.isNaN(parsed.getTime())) {
				return String(value)
			}
			return parsed.toLocaleString(undefined, {
				dateStyle: 'medium',
				timeStyle: 'short',
			})
		},

		/**
		 * The note column; the automatic safety copy gets a readable label.
		 *
		 * @param {string|null} note Stored note.
		 * @return {string} Text for the note column.
		 * @spec openspec/changes/dashboard-version-history-ui/specs/dashboard-versioning/spec.md
		 */
		noteLabel(note) {
			if (note === 'pre-restore') {
				return t('launchpad', 'Saved automatically before a restore')
			}
			return note ?? ''
		},
	},
}
</script>

<style scoped>
.launchpad-versions {
	padding: 16px;
}

.launchpad-versions__save {
	display: flex;
	gap: 8px;
	align-items: flex-end;
	margin-bottom: 16px;
}

.launchpad-versions__table {
	width: 100%;
	border-collapse: collapse;
}

.launchpad-versions__table th,
.launchpad-versions__table td {
	padding: 4px 8px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}

.launchpad-versions__error {
	color: var(--color-error-text);
}
</style>
