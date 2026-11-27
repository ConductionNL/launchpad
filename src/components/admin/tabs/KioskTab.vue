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

		<!-- REQ-KIOSK-007: screen links from a dashboard's Sharing tab, saved at once. -->
		<NcCheckboxRadioSwitch
			type="switch"
			:modelValue="screenLinksAllowed"
			:disabled="savingSwitch"
			data-testid="kiosk-screen-links-allowed"
			@update:modelValue="setScreenLinksAllowed">
			{{ t('launchpad', 'Allow showing dashboards on a screen') }}
		</NcCheckboxRadioSwitch>
		<p class="kiosk-tab__hint">
			{{
				t(
					'launchpad',
					'People who may share a dashboard can make a screen link for it on its Sharing tab. Separate from public links. Existing screen links keep playing until they are revoked.',
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
					v-for="playlist in orderedPlaylists"
					:key="playlist.id"
					:data-testid="isScreenLink(playlist) ? 'kiosk-screen-link-row' : 'kiosk-row'">
					<td>
						<span v-if="isScreenLink(playlist)" class="kiosk-tab__badge" data-testid="kiosk-screen-link-label">
							{{ t('launchpad', 'Screen link') }}
						</span>
						{{ playlist.name }}
					</td>
					<td>
						<template v-if="isScreenLink(playlist)">
							{{ t('launchpad', '{dashboard}, made by {creator}', { dashboard: dashboardTitle(playlist), creator: playlist.createdBy ?? '' }) }}
						</template>
						<template v-else>
							{{ (playlist.entries || []).length }}
						</template>
					</td>
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
							v-if="!isScreenLink(playlist)"
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

		<CnConfirmDialog
			v-if="revokeTarget !== null"
			:dialogTitle="t('launchpad', 'Revoke this playlist?')"
			:message="
				t(
					'launchpad',
					'Screens showing {name} stop at their next check, and the link stops working for good.',
					{ name: revokeTarget.name ?? '' },
				)
			"
			:confirmLabel="t('launchpad', 'Revoke')"
			variant="error"
			@confirm="revoke"
			@close="revokeTarget = null" />
	</div>
</template>

<script>
import { CnConfirmDialog, NcButton, NcCheckboxRadioSwitch } from '@conduction/nextcloud-vue'
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import KioskPlaylistDialog from '../../../dialogs/KioskPlaylistDialog.vue'
import { api } from '../../../services/api.js'
import { useKioskPlaylistStore } from '../../../stores/kioskPlaylists.js'

/**
 * KioskTab: Beheer ▸ Kiosk. Lists kiosk playlists with their public link,
 * and creates, edits and revokes them through the existing playlist
 * endpoints (KioskController, owner-or-admin per dashboard).
 *
 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
 */
export default {
	name: 'KioskTab',

	components: {
		KioskPlaylistDialog,
		NcButton,
		NcCheckboxRadioSwitch,
		CnConfirmDialog,
	},

	/** @spec openspec/specs/dashboard-kiosk-mode/spec.md */
	setup() {
		return { store: useKioskPlaylistStore() }
	},

	/** @spec openspec/specs/dashboard-kiosk-mode/spec.md */
	data() {
		return {
			dashboards: [],
			formOpen: false,
			editing: null,
			revokeTarget: null,
			error: '',
			screenLinksAllowed: true,
			savingSwitch: false,
		}
	},

	computed: {
		/**
		 * Playlists first, then the screen links (REQ-KIOSK-008).
		 *
		 * @return {object[]}
		 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-6
		 */
		orderedPlaylists() {
			const playlists = this.store.playlists.filter((p) => !this.isScreenLink(p))
			const screenLinks = this.store.playlists.filter((p) => this.isScreenLink(p))
			return [...playlists, ...screenLinks]
		},
	},

	/** @spec openspec/specs/dashboard-kiosk-mode/spec.md */
	async mounted() {
		this.loadSwitch()
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
		 * Whether a row is the screen link of one dashboard (REQ-KIOSK-008).
		 *
		 * @param {object} playlist Playlist row.
		 * @return {boolean}
		 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-6
		 */
		isScreenLink(playlist) {
			return typeof playlist?.screenDashboardUuid === 'string' && playlist.screenDashboardUuid !== ''
		},

		/**
		 * The title of a screen link's dashboard, or the link's own name.
		 *
		 * @param {object} playlist Screen link row.
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-6
		 */
		dashboardTitle(playlist) {
			const dashboard = this.dashboards.find((d) => d.uuid === playlist.screenDashboardUuid)
			return dashboard?.name ?? playlist.name ?? ''
		},

		/**
		 * Read the screen link switch (REQ-KIOSK-007); on when not set.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-6
		 */
		async loadSwitch() {
			try {
				const { data } = await api.getAdminSettings()
				const settings = data?.data ?? data ?? {}
				this.screenLinksAllowed = settings.kioskScreenLinksAllowed !== false
			} catch {
				this.screenLinksAllowed = true
			}
		},

		/**
		 * Save the screen link switch at once (REQ-ASET-008).
		 *
		 * @param {boolean} value The new state.
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-6
		 */
		async setScreenLinksAllowed(value) {
			const previous = this.screenLinksAllowed
			this.screenLinksAllowed = value === true
			this.savingSwitch = true
			this.error = ''
			try {
				await api.updateAdminSettings({ kioskScreenLinksAllowed: this.screenLinksAllowed })
			} catch {
				this.screenLinksAllowed = previous
				this.error = t('launchpad', 'The setting could not be saved.')
			} finally {
				this.savingSwitch = false
			}
		},

		/**
		 * The public link of a playlist.
		 *
		 * @param {object} playlist Playlist row.
		 * @return {string} Absolute kiosk URL.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
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
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
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
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
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
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
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
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
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

.kiosk-tab__hint {
	margin: 0 0 16px;
	color: var(--color-text-maxcontrast);
}

.kiosk-tab__badge {
	display: inline-block;
	margin-inline-end: 4px;
	padding: 0 6px;
	border-radius: var(--border-radius-pill);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-size: 0.85em;
}

.kiosk-tab__error {
	color: var(--color-error-text);
}
</style>
