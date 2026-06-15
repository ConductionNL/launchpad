<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: EUPL-1.2
  -
  - Create/edit modal for a kiosk playlist. Lives in its own file per the
  - modal-isolation rule. The dashboard picker is limited to dashboards the
  - caller may share (own-or-admin); per-entry dwell and the refresh interval
  - are editable here. REQ-KIOSK-002.
  -
  - @spec openspec/changes/dashboard-kiosk-mode/specs/dashboard-kiosk-mode/spec.md
-->

<template>
	<NcModal @close="$emit('close')">
		<div class="kiosk-playlist-modal">
			<h3>{{ playlist && playlist.id ? t('launchpad', 'Edit kiosk playlist') : t('launchpad', 'New kiosk playlist') }}</h3>

			<NcTextField :value="form.name"
				:label="t('launchpad', 'Playlist name')"
				required
				@update:value="form.name = $event" />

			<NcSelect v-model="selectedDashboard"
				:options="dashboardOptions"
				:input-label="t('launchpad', 'Add a dashboard')"
				label="label"
				@input="addEntry" />

			<ul v-if="form.entries.length" class="kiosk-playlist-modal__entries">
				<li v-for="(entry, index) in form.entries"
					:key="index"
					class="kiosk-playlist-modal__entry">
					<span class="kiosk-playlist-modal__entry-name">
						{{ dashboardLabel(entry.dashboardUuid) }}
					</span>
					<NcTextField type="number"
						:value="String(entry.dwellSeconds)"
						:label="t('launchpad', 'Dwell (seconds)')"
						@update:value="entry.dwellSeconds = Number($event)" />
					<NcButton type="tertiary"
						:aria-label="t('launchpad', 'Remove entry')"
						@click="removeEntry(index)">
						{{ t('launchpad', 'Remove') }}
					</NcButton>
				</li>
			</ul>
			<p v-else class="kiosk-playlist-modal__empty">
				{{ t('launchpad', 'Add at least one dashboard to the rotation.') }}
			</p>

			<NcTextField type="number"
				:value="String(form.refreshSeconds)"
				:label="t('launchpad', 'Refresh interval (seconds)')"
				@update:value="form.refreshSeconds = Number($event)" />

			<div class="kiosk-playlist-modal__actions">
				<NcButton type="tertiary" @click="$emit('close')">
					{{ t('launchpad', 'Cancel') }}
				</NcButton>
				<NcButton type="primary"
					:disabled="saving || !form.name || form.entries.length === 0"
					@click="save">
					{{ t('launchpad', 'Save') }}
				</NcButton>
			</div>
		</div>
	</NcModal>
</template>

<script>
import {
	NcButton,
	NcModal,
	NcSelect,
	NcTextField,
} from '@conduction/nextcloud-vue'

export default {
	name: 'KioskPlaylistModal',

	components: {
		NcButton,
		NcModal,
		NcSelect,
		NcTextField,
	},

	props: {
		/** Existing playlist to edit, or null to create a new one. */
		playlist: {
			type: Object,
			default: null,
		},
		/** Dashboards the caller may add (own-or-admin). [{ uuid, name }] */
		dashboards: {
			type: Array,
			default: () => [],
		},
		saving: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			selectedDashboard: null,
			form: {
				name: this.playlist?.name ?? '',
				refreshSeconds: this.playlist?.refreshSeconds ?? 300,
				entries: (this.playlist?.entries ?? []).map((e) => ({
					dashboardUuid: e.dashboardUuid,
					dwellSeconds: e.dwellSeconds ?? 45,
				})),
			},
		}
	},

	computed: {
		dashboardOptions() {
			return this.dashboards.map((d) => ({ id: d.uuid, label: d.name }))
		},
	},

	methods: {
		dashboardLabel(uuid) {
			const match = this.dashboards.find((d) => d.uuid === uuid)
			return match ? match.name : uuid
		},

		addEntry(option) {
			if (!option) {
				return
			}
			this.form.entries.push({ dashboardUuid: option.id, dwellSeconds: 45 })
			this.$nextTick(() => { this.selectedDashboard = null })
		},

		removeEntry(index) {
			this.form.entries.splice(index, 1)
		},

		save() {
			this.$emit('save', {
				id: this.playlist?.id ?? null,
				name: this.form.name,
				refreshSeconds: this.form.refreshSeconds,
				entries: this.form.entries,
			})
		},
	},
}
</script>

<style scoped>
.kiosk-playlist-modal {
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.kiosk-playlist-modal__entries {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.kiosk-playlist-modal__entry {
	display: flex;
	align-items: flex-end;
	gap: 8px;
}

.kiosk-playlist-modal__entry-name {
	flex: 1;
	font-weight: bold;
}

.kiosk-playlist-modal__actions {
	display: flex;
	justify-content: flex-end;
	gap: 8px;
	margin-top: 12px;
}
</style>
