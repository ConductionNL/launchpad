<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: EUPL-1.2
  -
  - Kiosk playlist management surface: list playlists with their public URL,
  - copy the URL to point a TV browser at, create/edit via the modal, and
  - revoke. REQ-KIOSK-002.
  -
  - @spec openspec/changes/dashboard-kiosk-mode/specs/dashboard-kiosk-mode/spec.md
-->

<template>
	<div class="kiosk-management">
		<div class="kiosk-management__header">
			<h2>{{ t('launchpad', 'Kiosk playlists') }}</h2>
			<NcButton type="primary" @click="openCreate">
				{{ t('launchpad', 'New playlist') }}
			</NcButton>
		</div>

		<p class="kiosk-management__intro">
			{{ t('launchpad', 'Point a wall display or reception screen at a playlist URL to rotate dashboards chrome-less and unattended.') }}
		</p>

		<NcLoadingIcon v-if="store.loading && !store.playlists.length" :size="32" />

		<ul v-else-if="store.playlists.length" class="kiosk-management__list">
			<li v-for="playlist in store.playlists"
				:key="playlist.id"
				class="kiosk-management__row">
				<div class="kiosk-management__row-main">
					<span class="kiosk-management__name">{{ playlist.name }}</span>
					<span class="kiosk-management__meta">
						{{ n('launchpad', '%n dashboard', '%n dashboards', playlist.entries.length) }}
						· {{ t('launchpad', 'refresh every {seconds}s', { seconds: playlist.refreshSeconds }) }}
					</span>
					<code class="kiosk-management__url">{{ playlist.url }}</code>
				</div>
				<div class="kiosk-management__row-actions">
					<NcButton type="tertiary" @click="copyUrl(playlist)">
						{{ t('launchpad', 'Copy URL') }}
					</NcButton>
					<NcButton type="tertiary" @click="openEdit(playlist)">
						{{ t('launchpad', 'Edit') }}
					</NcButton>
					<NcButton type="error" @click="revoke(playlist)">
						{{ t('launchpad', 'Revoke') }}
					</NcButton>
				</div>
			</li>
		</ul>

		<NcEmptyContent v-else :name="t('launchpad', 'No kiosk playlists yet')">
			<template #description>
				{{ t('launchpad', 'Create a playlist to put dashboards on a wall display.') }}
			</template>
		</NcEmptyContent>

		<KioskPlaylistModal v-if="modalOpen"
			:playlist="editing"
			:dashboards="dashboards"
			:saving="store.loading"
			@close="closeModal"
			@save="onSave" />
	</div>
</template>

<script>
import {
	NcButton,
	NcEmptyContent,
	NcLoadingIcon,
} from '@conduction/nextcloud-vue'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { useKioskPlaylistStore } from '../stores/kioskPlaylists.js'
import KioskPlaylistModal from '../modals/KioskPlaylistModal.vue'

export default {
	name: 'KioskPlaylistManagement',

	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		KioskPlaylistModal,
	},

	props: {
		/** Dashboards the caller may add to a playlist. [{ uuid, name }] */
		dashboards: {
			type: Array,
			default: () => [],
		},
	},

	setup() {
		return { store: useKioskPlaylistStore() }
	},

	data() {
		return {
			modalOpen: false,
			editing: null,
		}
	},

	async mounted() {
		try {
			await this.store.fetchPlaylists()
		} catch (e) {
			showError(t('launchpad', 'Could not load kiosk playlists'))
		}
	},

	methods: {
		openCreate() {
			this.editing = null
			this.modalOpen = true
		},

		openEdit(playlist) {
			this.editing = playlist
			this.modalOpen = true
		},

		closeModal() {
			this.modalOpen = false
			this.editing = null
		},

		async onSave(payload) {
			try {
				if (payload.id) {
					await this.store.updatePlaylist(payload.id, payload)
				} else {
					await this.store.createPlaylist(payload)
				}
				showSuccess(t('launchpad', 'Kiosk playlist saved'))
				this.closeModal()
			} catch (e) {
				if (e?.response?.status === 403) {
					showError(t('launchpad', 'You may only add dashboards you own'))
				} else {
					showError(t('launchpad', 'Could not save kiosk playlist'))
				}
			}
		},

		async copyUrl(playlist) {
			try {
				await navigator.clipboard.writeText(playlist.url)
				showSuccess(t('launchpad', 'Kiosk URL copied'))
			} catch (e) {
				showError(t('launchpad', 'Could not copy the URL'))
			}
		},

		async revoke(playlist) {
			try {
				await this.store.revokePlaylist(playlist.id)
				showSuccess(t('launchpad', 'Kiosk playlist revoked'))
			} catch (e) {
				showError(t('launchpad', 'Could not revoke the playlist'))
			}
		},
	},
}
</script>

<style scoped>
.kiosk-management {
	padding: 16px;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.kiosk-management__header {
	display: flex;
	align-items: center;
	justify-content: space-between;
}

.kiosk-management__list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.kiosk-management__row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
}

.kiosk-management__row-main {
	display: flex;
	flex-direction: column;
	gap: 4px;
	min-width: 0;
}

.kiosk-management__name {
	font-weight: bold;
}

.kiosk-management__meta {
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
}

.kiosk-management__url {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-size: 0.8em;
}

.kiosk-management__row-actions {
	display: flex;
	gap: 4px;
	flex-shrink: 0;
}
</style>
