<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="kiosk-tab" data-testid="kiosk-tab">
		<h3>{{ t('launchpad', 'Kiosk screens') }}</h3>
		<p class="kiosk-tab__intro">
			{{
				t(
					'launchpad',
					'A playlist shows dashboards one after another on a screen in a lobby or canteen. Anyone with the link can watch it, without logging in.',
				)
			}}
		</p>

		<NcButton variant="primary" data-testid="kiosk-new" @click="openForm(null)">
			{{ t('launchpad', 'New playlist') }}
		</NcButton>

		<p v-if="error" class="kiosk-tab__error" role="alert">
			{{ error }}
		</p>

		<p
			v-if="!store.loading && store.playlists.length === 0"
			class="kiosk-tab__empty">
			{{ t('launchpad', 'No playlists yet.') }}
		</p>
		<table v-else class="kiosk-tab__table">
			<thead>
				<tr>
					<th scope="col">
						{{ t('launchpad', 'Name') }}
					</th>
					<th scope="col">
						{{ t('launchpad', 'Dashboards') }}
					</th>
					<th scope="col">
						{{ t('launchpad', 'Link') }}
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
					v-for="playlist in store.playlists"
					:key="playlist.id"
					data-testid="kiosk-row">
					<td>{{ playlist.name }}</td>
					<td>{{ (playlist.entries || []).length }}</td>
					<td>
						<code class="kiosk-tab__link">{{ linkFor(playlist) }}</code>
						<NcButton
							variant="tertiary"
							:aria-label="
								t('launchpad', 'Copy the link to {name}', {
									name: playlist.name,
								})
							"
							data-testid="kiosk-copy"
							@click="copyLink(playlist)">
							{{ t('launchpad', 'Copy link') }}
						</NcButton>
					</td>
					<td>
						<NcButton
							variant="tertiary"
							data-testid="kiosk-edit"
							@click="openForm(playlist)">
							{{ t('launchpad', 'Edit') }}
						</NcButton>
						<NcButton
							variant="tertiary"
							data-testid="kiosk-revoke"
							@click="revokeTarget = playlist">
							{{ t('launchpad', 'Revoke') }}
						</NcButton>
					</td>
				</tr>
			</tbody>
		</table>

		<KioskPlaylistDialog
			:open="formOpen"
			:playlist="editing"
			:dashboards="dashboards"
			@update:open="formOpen = $event"
			@save="savePlaylist" />

		<RevokeKioskPlaylistDialog
			:open="revokeTarget !== null"
			:name="revokeTarget?.name ?? ''"
			@update:open="
				(v) => {
					if (!v) revokeTarget = null
				}
			"
			@confirm="revoke" />
	</div>
</template>

<script>
import { NcButton } from '@conduction/nextcloud-vue'
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import KioskPlaylistDialog from '../../../dialogs/KioskPlaylistDialog.vue'
import RevokeKioskPlaylistDialog from '../../../dialogs/RevokeKioskPlaylistDialog.vue'
import { api } from '../../../services/api.js'
import { useKioskPlaylistStore } from '../../../stores/kioskPlaylists.js'

/**
 * KioskTab: Beheer ▸ Kiosk. Lists kiosk playlists with their public link,
 * and creates, edits and revokes them through the existing playlist
 * endpoints (KioskController, owner-or-admin per dashboard).
 *
 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
 */
export default {
	name: 'KioskTab',

	components: {
		KioskPlaylistDialog,
		NcButton,
		RevokeKioskPlaylistDialog,
	},

	/** @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md */
	setup() {
		return { store: useKioskPlaylistStore() }
	},

	/** @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md */
	data() {
		return {
			dashboards: [],
			formOpen: false,
			editing: null,
			revokeTarget: null,
			error: '',
		}
	},

	/** @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md */
	async mounted() {
		try {
			await this.store.fetchPlaylists()
			const { data } = await api.getVisibleDashboards()
			this.dashboards = Array.isArray(data) ? data : (data?.items ?? [])
		} catch {
			this.error = t('launchpad', 'The playlists could not be loaded.')
		}
	},

	methods: {
		t,

		/**
		 * The public link of a playlist.
		 *
		 * @param {object} playlist Playlist row.
		 * @return {string} Absolute kiosk URL.
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		linkFor(playlist) {
			return (
				window.location.origin
				+ generateUrl(
					`/apps/launchpad/kiosk/${encodeURIComponent(playlist.token)}`,
				)
			)
		},

		/**
		 * Copy the link to the clipboard.
		 *
		 * @param {object} playlist Playlist row.
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		async copyLink(playlist) {
			try {
				await navigator.clipboard.writeText(this.linkFor(playlist))
				showSuccess(t('launchpad', 'Link copied'))
			} catch {
				this.error = t(
					'launchpad',
					'The link could not be copied. Select it and copy it by hand.',
				)
			}
		},

		/**
		 * Open the form for a new or an existing playlist.
		 *
		 * @param {object|null} playlist Playlist to edit, or null.
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		openForm(playlist) {
			this.editing = playlist
			this.formOpen = true
		},

		/**
		 * Create or update through the store.
		 *
		 * @param {object} body `{name, entries, refreshSeconds}`.
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		async savePlaylist(body) {
			this.error = ''
			try {
				if (this.editing) {
					await this.store.updatePlaylist(this.editing.id, body)
				} else {
					await this.store.createPlaylist(body)
				}
				this.formOpen = false
			} catch (e) {
				this.error =
					e?.response?.status === 403
						? t(
								'launchpad',
								'You may only put dashboards you own or manage in a playlist.',
							)
						: t('launchpad', 'The playlist could not be saved.')
			}
		},

		/**
		 * Revoke the confirmed playlist.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		async revoke() {
			const target = this.revokeTarget
			this.revokeTarget = null
			if (!target) {
				return
			}
			try {
				await this.store.revokePlaylist(target.id)
			} catch {
				this.error = t('launchpad', 'The playlist could not be revoked.')
			}
		},
	},
}
</script>

<style scoped>
.kiosk-tab__intro {
	margin: 8px 0 16px;
}

.kiosk-tab__table {
	width: 100%;
	margin-top: 16px;
	border-collapse: collapse;
}

.kiosk-tab__table th,
.kiosk-tab__table td {
	padding: 4px 8px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}

.kiosk-tab__link {
	word-break: break-all;
}

.kiosk-tab__error {
	color: var(--color-error-text);
}
</style>
