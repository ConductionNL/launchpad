<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="kiosk-player">
		<div
			v-if="unavailable"
			class="kiosk-player__message"
			role="status"
			data-testid="kiosk-unavailable">
			<p>{{ t('launchpad', 'This playlist is no longer available.') }}</p>
		</div>
		<div
			v-else-if="current"
			class="kiosk-player__dashboard"
			data-testid="kiosk-dashboard">
			<h1 class="kiosk-player__title">
				{{ current.dashboard?.name || current.dashboard?.title || '' }}
			</h1>
			<PublicDashboardGrid :placements="current.placements || []" />
		</div>
		<p v-else-if="loaded" class="kiosk-player__message" role="status">
			{{ t('launchpad', 'This playlist has no dashboards to show.') }}
		</p>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { translate as t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import PublicDashboardGrid from '../components/PublicDashboardGrid.vue'

/** A network failure is retried this soon, whatever the refresh interval. */
const RETRY_MS = 30 * 1000

/**
 * KioskPlayerView: the full-screen player behind a kiosk link. It shows
 * each dashboard of the playlist for its dwell time, starts over at the
 * end, re-reads the playlist every `refreshSeconds`, shows a message once
 * the link is revoked (404), and keeps the last good playlist when the
 * network fails, retrying every 30 seconds. No login and no controls.
 *
 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
 */
export default {
	name: 'KioskPlayerView',

	components: {
		PublicDashboardGrid,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
	},

	/** @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md */
	data() {
		return {
			entries: [],
			index: 0,
			refreshSeconds: 300,
			unavailable: false,
			loaded: false,
			dwellTimer: null,
			refreshTimer: null,
		}
	},

	computed: {
		/**
		 * @return {object|null} The entry on screen now.
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		current() {
			return this.entries[this.index] ?? null
		},
	},

	/** @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md */
	async mounted() {
		await this.load()
	},

	/** @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md */
	beforeUnmount() {
		clearTimeout(this.dwellTimer)
		clearTimeout(this.refreshTimer)
	},

	methods: {
		t,

		/**
		 * Read the playlist and schedule the next read.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		async load() {
			let next
			try {
				const { data } = await axios.get(
					generateUrl(
						`/apps/launchpad/kiosk/${encodeURIComponent(this.token)}`,
					),
					{ headers: { Accept: 'application/json' } },
				)
				this.apply(data)
				next = this.refreshSeconds * 1000
			} catch (error) {
				const status = error?.response?.status
				if (status === 404 || status === 410) {
					this.stop()
					this.unavailable = true
					this.loaded = true
					return
				}
				// Anything else is a blip: keep what is on screen.
				next = RETRY_MS
				this.loaded = true
			}
			clearTimeout(this.refreshTimer)
			this.refreshTimer = setTimeout(() => this.load(), next)
		},

		/**
		 * Take a fresh playlist. The rotation continues where it was when
		 * the same number of entries came back, and restarts otherwise.
		 *
		 * @param {object} data `{playlist, entries}` from the kiosk route.
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		apply(data) {
			const entries = Array.isArray(data?.entries) ? data.entries : []
			const refresh = Number(data?.playlist?.refreshSeconds)
			this.refreshSeconds =
				Number.isFinite(refresh) && refresh >= 30 ? refresh : 300
			const firstLoad = !this.loaded
			if (entries.length !== this.entries.length) {
				this.index = 0
			}
			this.entries = entries
			this.unavailable = false
			this.loaded = true
			if (firstLoad || this.dwellTimer === null) {
				this.scheduleNext()
			}
		},

		/**
		 * Show the current entry for its dwell time, then move on.
		 *
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		scheduleNext() {
			clearTimeout(this.dwellTimer)
			this.dwellTimer = null
			if (this.entries.length === 0) {
				return
			}
			const dwell = Math.max(10, Number(this.current?.dwellSeconds) || 10)
			this.dwellTimer = setTimeout(() => {
				this.index = (this.index + 1) % this.entries.length
				this.scheduleNext()
			}, dwell * 1000)
		},

		/**
		 * Stop rotating.
		 *
		 * @spec openspec/changes/sharing-kiosk-screens/specs/dashboard-kiosk-mode/spec.md
		 */
		stop() {
			clearTimeout(this.dwellTimer)
			this.dwellTimer = null
			this.entries = []
		},
	},
}
</script>

<style scoped>
.kiosk-player {
	min-height: 100vh;
	padding: 24px;
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.kiosk-player__title {
	margin: 0 0 16px;
	font-size: 2em;
}

.kiosk-player__message {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 80vh;
	font-size: 1.5em;
}
</style>
