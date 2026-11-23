<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="launchpad-versions" data-testid="version-history">
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
			<p class="launchpad-versions__intro">
				{{ t('launchpad', 'Every saved version of this dashboard. Pick an older version to restore it; the current version stays in the list.') }}
			</p>

			<p v-if="sortedVersions.length === 0">
				{{ t('launchpad', 'No versions saved yet.') }}
			</p>
			<table v-else class="launchpad-versions__table">
				<thead>
					<tr>
						<th v-for="heading in headings" :key="heading" scope="col">
							{{ heading }}
						</th>
						<th scope="col">
							<span class="hidden-visually">{{ t('launchpad', 'Status') }}</span>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="version in sortedVersions"
						:key="version.versionNumber"
						class="launchpad-versions__row"
						:class="{ 'launchpad-versions__row--selected': selected && selected.versionNumber === version.versionNumber }"
						:aria-selected="selected && selected.versionNumber === version.versionNumber ? 'true' : 'false'"
						tabindex="0"
						data-testid="version-row"
						@click="select(version)"
						@keydown.enter="select(version)">
						<td>{{ whenText(version.createdAt) }}</td>
						<td>{{ version.createdByName || version.createdBy }}</td>
						<td>{{ noteLabel(version.note) }}</td>
						<td class="launchpad-versions__status">
							<span
								v-if="version.versionNumber === currentNumber"
								class="launchpad-versions__current"
								data-testid="version-current">
								{{ t('launchpad', 'Current version') }}
							</span>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- The chosen older version and what restoring it does (LpVersies). -->
			<div
				v-if="selected && selected.versionNumber !== currentNumber"
				class="launchpad-versions__preview"
				data-testid="version-preview">
				<span class="launchpad-versions__thumb" aria-hidden="true">
					<span /><span /><span /><span />
				</span>
				<span class="launchpad-versions__preview-text">
					<strong>{{ t('launchpad', 'Version of {date}', { date: whenText(selected.createdAt) }) }}</strong>
					<span>
						{{ selected.note ? noteLabel(selected.note) + '. ' : '' }}{{ t('launchpad', 'Restoring it drops the changes made after it. The current version stays in this list.') }}
					</span>
				</span>
			</div>

			<div class="launchpad-versions__save">
				<NcTextField
					v-model="note"
					:label="
						t('launchpad', 'Note for this version (optional)')
					" />
				<NcButton
					variant="tertiary"
					:disabled="saving"
					data-testid="version-save"
					@click="saveVersion">
					{{ t('launchpad', 'Save this version now') }}
				</NcButton>
			</div>

			<div class="launchpad-versions__actions">
				<NcButton
					variant="secondary"
					data-testid="version-close"
					@click="$emit('close')">
					{{ t('launchpad', 'Close') }}
				</NcButton>
				<NcButton
					variant="primary"
					:disabled="!selected || selected.versionNumber === currentNumber"
					data-testid="version-restore"
					@click="askRestore(selected)">
					<template #icon>
						<Restore :size="20" />
					</template>
					{{ t('launchpad', 'Restore this version') }}
				</NcButton>
			</div>
		</template>

		<CnConfirmDialog
			v-if="restoreTarget !== null"
			:dialogTitle="t('launchpad', 'Restore this version?')"
			:message="
				t(
					'launchpad',
					'The dashboard goes back to how it was on {date}. How it looks now is saved as a version first, so you can undo this.',
					{ date: formatDate(restoreTarget.createdAt) },
				)
			"
			:confirmLabel="t('launchpad', 'Restore')"
			@confirm="confirmRestore"
			@close="restoreTarget = null" />
	</div>
</template>

<script>
import {
	CnConfirmDialog,
	NcButton,
	NcLoadingIcon,
	NcTextField,
} from '@conduction/nextcloud-vue'
import { getCanonicalLocale, t } from '@nextcloud/l10n'
import Restore from 'vue-material-design-icons/Restore.vue'
import { TEXT_PROP } from '../../../constants/propDefinitions.js'
import { api } from '../../../services/api.js'
import { parseServerStamp } from '../../../utils/serverStamp.js'

/**
 * VersionsTab: the Versions tab of the dashboard settings (board LpVersies):
 * the saved versions of one dashboard, newest first, with "Save this version
 * now" and a confirmed restore. After a restore the list is read again, so
 * the `pre-restore` version that makes the restore reversible shows at the
 * top, and `restored` tells the page to reload.
 *
 * @spec openspec/specs/dashboard-versioning/spec.md
 */
export default {
	name: 'VersionsTab',

	components: {
		CnConfirmDialog,
		NcButton,
		NcLoadingIcon,
		NcTextField,
		Restore,
	},

	props: {
		dashboardUuid: TEXT_PROP,
	},

	emits: ['restored', 'close'],

	/** @spec openspec/specs/dashboard-versioning/spec.md */
	data() {
		return {
			versions: [],
			modeSupported: true,
			loading: false,
			saving: false,
			error: '',
			note: '',
			restoreTarget: null,
			selectedNumber: null,
		}
	},

	computed: {
		/**
		 * The table's column headings, before the actions column.
		 *
		 * @return {string[]}
		 * @spec openspec/specs/dashboard-versioning/spec.md
		 * @spec openspec/changes/version-change-notes/specs/dashboard-versioning/spec.md#requirement-the-owner-sees-the-version-history-req-versui-001
		 */
		headings() {
			return [
				t('launchpad', 'When'),
				t('launchpad', 'By'),
				t('launchpad', 'What changed'),
			]
		},

		/**
		 * @return {number|null} The newest version's number: the current one.
		 * @spec openspec/specs/dashboard-versioning/spec.md
		 * @spec openspec/changes/version-change-notes/specs/dashboard-versioning/spec.md#requirement-the-owner-sees-the-version-history-req-versui-001
		 */
		currentNumber() {
			return this.sortedVersions.length > 0 ? this.sortedVersions[0].versionNumber : null
		},

		/**
		 * @return {object|null} The version picked in the list.
		 * @spec openspec/specs/dashboard-versioning/spec.md
		 */
		selected() {
			return this.sortedVersions.find((version) => version.versionNumber === this.selectedNumber) ?? null
		},

		/**
		 * @return {Array<object>} Versions, newest first.
		 * @spec openspec/specs/dashboard-versioning/spec.md
		 */
		sortedVersions() {
			return [...this.versions].sort(
				(a, b) => b.versionNumber - a.versionNumber,
			)
		},
	},

	watch: {
		dashboardUuid: {
			immediate: true,
			/**
			 * Read the list when the tab opens and for another dashboard.
			 *
			 * @param {string} uuid The dashboard's uuid.
			 * @spec openspec/specs/dashboard-versioning/spec.md
			 */
			handler(uuid) {
				if (uuid) {
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
		 * @spec openspec/specs/dashboard-versioning/spec.md
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
		 * @spec openspec/specs/dashboard-versioning/spec.md
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
		 * @spec openspec/specs/dashboard-versioning/spec.md
		 */
		/**
		 * Pick a version in the list.
		 *
		 * @param {object} version The version.
		 * @spec openspec/specs/dashboard-versioning/spec.md
		 */
		select(version) {
			this.selectedNumber = version.versionNumber
		},

		/**
		 * When a version was saved, as the board words it: "today 10:12",
		 * else "Friday 2 October, 16:40".
		 *
		 * @param {string} value The stamp.
		 * @return {string}
		 * @spec openspec/specs/dashboard-versioning/spec.md
		 */
		whenText(value) {
			const parsed = value ? parseServerStamp(value) : null
			if (parsed === null) {
				return value ? String(value) : ''
			}
			const locale = getCanonicalLocale()
			const time = parsed.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' })
			const now = new Date()
			if (parsed.toDateString() === now.toDateString()) {
				return t('launchpad', 'today {time}', { time })
			}
			const day = parsed.toLocaleDateString(locale, { weekday: 'long', day: 'numeric', month: 'long' })
			return `${day}, ${time}`
		},

		askRestore(version) {
			this.restoreTarget = version
		},

		/**
		 * Restore the chosen version, read the list again and tell the page.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-versioning/spec.md
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
		 * @spec openspec/specs/dashboard-versioning/spec.md
		 */
		formatDate(value) {
			if (!value) {
				return ''
			}
			const parsed = parseServerStamp(value)
			if (parsed === null) {
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
		 * @spec openspec/specs/dashboard-versioning/spec.md
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
	display: flex;
	flex-direction: column;
	gap: 16px;
}

.launchpad-versions__save {
	display: flex;
	gap: 8px;
	align-items: flex-end;
}

.launchpad-versions__table {
	width: 100%;
	border-collapse: collapse;
}

/* The table, the chosen row and the preview as LpVersies draws them. */
.launchpad-versions__intro {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 14px;
}

.launchpad-versions__table th {
	padding: 10px 12px;
	border-bottom: 1px solid var(--color-border);
	color: var(--color-text-maxcontrast);
	font-size: 13px;
	font-weight: 600;
	text-align: start;
	white-space: nowrap;
}

.launchpad-versions__table td {
	padding: 12px;
	border-bottom: 1px solid var(--color-border);
	font-size: 14px;
	text-align: start;
	vertical-align: middle;
}

.launchpad-versions__row {
	cursor: pointer;
}

.launchpad-versions__row:hover,
.launchpad-versions__row:focus-visible {
	background: var(--color-background-hover);
}

.launchpad-versions__row--selected,
.launchpad-versions__row--selected:hover {
	background: var(--color-primary-element-light);
}

.launchpad-versions__status {
	text-align: end;
}

.launchpad-versions__current {
	display: inline-flex;
	align-items: center;
	padding: 3px 10px;
	border-radius: 12px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-size: 13px;
	font-weight: 600;
	white-space: nowrap;
}

.launchpad-versions__preview {
	display: flex;
	gap: 16px;
	align-items: center;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-background-hover);
}

.launchpad-versions__thumb {
	display: grid;
	flex: none;
	grid-template-columns: 1fr 1fr;
	gap: 6px;
	width: 120px;
	padding: 8px;
	border-radius: 8px;
	background: var(--color-main-background);
}

.launchpad-versions__thumb > span {
	height: 22px;
	border-radius: 4px;
	background: var(--color-border);
}

.launchpad-versions__preview-text {
	display: flex;
	flex-direction: column;
	gap: 4px;
	font-size: 14px;
}

.launchpad-versions__actions {
	display: flex;
	gap: 8px;
	justify-content: flex-end;
}

.launchpad-versions__error {
	color: var(--color-error-text);
}
</style>
