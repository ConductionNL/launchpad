<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcDialog
		:name="
			playlist
				? t('launchpad', 'Edit playlist')
				: t('launchpad', 'New playlist')
		"
		:open="open"
		size="normal"
		@update:open="$emit('update:open', $event)">
		<div class="kiosk-playlist-form">
			<NcTextField
				v-model="name"
				:label="t('launchpad', 'Name')"
				data-testid="kiosk-name" />

			<fieldset class="kiosk-playlist-form__entries">
				<legend>
					{{ t('launchpad', 'Dashboards, shown in this order') }}
				</legend>
				<div
					v-for="(entry, index) in entries"
					:key="entry.key"
					class="kiosk-playlist-form__entry"
					data-testid="kiosk-entry">
					<NcSelect
						v-model="entry.dashboard"
						:inputLabel="t('launchpad', 'Dashboard')"
						:options="dashboardOptions"
						label="name"
						trackBy="uuid"
						:clearable="false" />
					<label class="kiosk-playlist-form__number">
						{{ t('launchpad', 'Seconds on screen') }}
						<input
							v-model.number="entry.dwellSeconds"
							type="number"
							:min="DWELL_MIN"
							:max="DWELL_MAX"
							data-testid="kiosk-dwell" />
					</label>
					<NcButton
						variant="tertiary"
						:aria-label="t('launchpad', 'Remove this dashboard')"
						@click="removeEntry(index)">
						{{ t('launchpad', 'Remove') }}
					</NcButton>
				</div>
				<NcButton
					variant="secondary"
					data-testid="kiosk-add-entry"
					@click="addEntry">
					{{ t('launchpad', 'Add a dashboard') }}
				</NcButton>
			</fieldset>

			<label class="kiosk-playlist-form__number">
				{{ t('launchpad', 'Check for changes every (seconds)') }}
				<input
					v-model.number="refreshSeconds"
					type="number"
					:min="REFRESH_MIN"
					:max="REFRESH_MAX"
					data-testid="kiosk-refresh" />
			</label>

			<p
				v-if="problem"
				class="kiosk-playlist-form__problem"
				role="alert"
				data-testid="kiosk-problem">
				{{ problem }}
			</p>
		</div>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('update:open', false)">
				{{ t('launchpad', 'Cancel') }}
			</NcButton>
			<NcButton
				variant="primary"
				:disabled="problem !== ''"
				data-testid="kiosk-save"
				@click="save">
				{{ t('launchpad', 'Save') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import { NcButton, NcDialog, NcSelect, NcTextField } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'

/** Limits enforced by KioskService (DWELL_MIN/MAX, REFRESH_MIN/MAX). */
export const DWELL_MIN = 10
export const DWELL_MAX = 86400
export const REFRESH_MIN = 30
export const REFRESH_MAX = 86400

let nextKey = 0

/**
 * KioskPlaylistDialog: create or edit a kiosk playlist. Checks the service
 * limits before saving so the form never sends what the server would clamp.
 * Emits `save` with `{name, entries: [{dashboardUuid, dwellSeconds}],
 * refreshSeconds}`, the body the playlist endpoints take.
 *
 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
 */
export default {
	name: 'KioskPlaylistDialog',

	components: {
		NcButton,
		NcDialog,
		NcSelect,
		NcTextField,
	},

	props: {
		open: {
			type: Boolean,
			required: true,
		},

		/** Playlist to edit, or null for a new one. */
		playlist: {
			type: Object,
			default: null,
		},

		/** Dashboards to choose from (`uuid`, `name`). */
		dashboards: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['update:open', 'save'],

	/** @spec openspec/specs/dashboard-kiosk-mode/spec.md */
	data() {
		return {
			DWELL_MIN,
			DWELL_MAX,
			REFRESH_MIN,
			REFRESH_MAX,
			name: '',
			entries: [],
			refreshSeconds: 300,
		}
	},

	computed: {
		/**
		 * @return {Array<object>} Dashboards offered in the picker.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		dashboardOptions() {
			return this.dashboards.filter((d) => d?.uuid)
		},

		/**
		 * @return {string} Why the form cannot be saved, or empty.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		problem() {
			if (this.name.trim() === '') {
				return t('launchpad', 'Give the playlist a name.')
			}
			if (
				this.entries.length === 0
				|| this.entries.some((e) => !e.dashboard?.uuid)
			) {
				return t('launchpad', 'Choose a dashboard for every row.')
			}
			if (
				this.entries.some(
					(e) =>
						!(
							Number(e.dwellSeconds) >= DWELL_MIN
							&& Number(e.dwellSeconds) <= DWELL_MAX
						),
				)
			) {
				return t(
					'launchpad',
					'Each dashboard stays on screen for at least {min} seconds.',
					{ min: DWELL_MIN },
				)
			}
			if (!(
				Number(this.refreshSeconds) >= REFRESH_MIN
				&& Number(this.refreshSeconds) <= REFRESH_MAX
			)) {
				return t(
					'launchpad',
					'Check for changes no more often than every {min} seconds.',
					{ min: REFRESH_MIN },
				)
			}
			return ''
		},
	},

	watch: {
		open: {
			immediate: true,
			/**
			 * Reset the form each time it opens.
			 *
			 * @param {boolean} isOpen New open state.
			 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
			 */
			handler(isOpen) {
				if (isOpen) {
					this.reset()
				}
			},
		},
	},

	methods: {
		t,

		/**
		 * Fill the form from the playlist being edited, or empty it.
		 *
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		reset() {
			this.name = this.playlist?.name ?? ''
			this.refreshSeconds = this.playlist?.refreshSeconds ?? 300
			const source = Array.isArray(this.playlist?.entries)
				? this.playlist.entries
				: []
			this.entries = source.map((e) => ({
				key: nextKey++,
				dashboard: this.dashboardOptions.find(
					(d) => d.uuid === e.dashboardUuid,
				) ?? { uuid: e.dashboardUuid, name: e.dashboardUuid },
				dwellSeconds: e.dwellSeconds,
			}))
			if (this.entries.length === 0) {
				this.addEntry()
			}
		},

		/**
		 * Add an empty row.
		 *
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		addEntry() {
			this.entries.push({ key: nextKey++, dashboard: null, dwellSeconds: 30 })
		},

		/**
		 * Remove one row.
		 *
		 * @param {number} index Row index.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		removeEntry(index) {
			this.entries.splice(index, 1)
		},

		/**
		 * Emit the playlist body when the form is valid.
		 *
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		save() {
			if (this.problem !== '') {
				return
			}
			this.$emit('save', {
				name: this.name.trim(),
				entries: this.entries.map((e) => ({
					dashboardUuid: e.dashboard.uuid,
					dwellSeconds: Number(e.dwellSeconds),
				})),
				refreshSeconds: Number(this.refreshSeconds),
			})
		},
	},
}
</script>

<style scoped>
.kiosk-playlist-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.kiosk-playlist-form__entries {
	display: flex;
	flex-direction: column;
	gap: 8px;
	border: none;
	padding: 0;
}

.kiosk-playlist-form__entry {
	display: flex;
	gap: 8px;
	align-items: flex-end;
}

.kiosk-playlist-form__number {
	display: flex;
	flex-direction: column;
}

.kiosk-playlist-form__problem {
	color: var(--color-error-text);
}
</style>
