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
			<p
				v-if="offline"
				class="kiosk-player__reconnect"
				role="status"
				data-testid="kiosk-reconnecting">
				{{ t('launchpad', 'Reconnecting…') }}
			</p>
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
import { REQUIRED_TEXT_PROP } from '../constants/propDefinitions.js'
import { hideCursorWhenIdle } from '../utils/kioskMode.js'

/** A network failure is retried this soon, whatever the refresh interval. */
const RETRY_MS = 30 * 1000

/** The next entry is fetched this long before its turn (REQ-KIOSK-004). */
export const PREFETCH_LEAD_MS = 5 * 1000

/** An entry that has not loaded this long after its turn is skipped (REQ-KIOSK-005). */
export const ENTRY_TIMEOUT_MS = 10 * 1000

/**
 * KioskPlayerView: the full-screen player behind a kiosk link. It shows
 * each dashboard of the playlist for its dwell time, starts over at the
 * end, re-reads the playlist every `refreshSeconds`, shows a message once
 * the link is revoked (404), and keeps the last good playlist when the
 * network fails, retrying every 30 seconds with a small reconnect indicator
 * on screen until a read succeeds. No login and no controls.
 *
 * Each next entry is fetched on its own about 5 seconds before its turn, so
 * the screen shows fresh data without a loading state (REQ-KIOSK-004). An
 * entry that fails, or has not loaded 10 seconds after its turn, is skipped
 * without a message and tried again on the next loop (REQ-KIOSK-005). The
 * cursor hides after 10 seconds without pointer movement (REQ-KIOSK-001).
 *
 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
 */
export default {
	name: 'KioskPlayerView',

	components: {
		PublicDashboardGrid,
	},

	props: {
		token: REQUIRED_TEXT_PROP,
	},

	/** @spec openspec/specs/dashboard-kiosk-mode/spec.md */
	data() {
		return {
			entries: [],
			index: 0,
			refreshSeconds: 300,
			unavailable: false,
			loaded: false,
			offline: false,
			dwellTimer: null,
			prefetchTimer: null,
			refreshTimer: null,
			prefetched: null,
			advancing: false,
			stopCursor: null,
		}
	},

	computed: {
		/**
		 * @return {object|null} The entry on screen now.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		current() {
			return this.entries[this.index] ?? null
		},
	},

	/** @spec openspec/specs/dashboard-kiosk-mode/spec.md */
	async mounted() {
		this.stopCursor = hideCursorWhenIdle(document)
		await this.load()
	},

	/** @spec openspec/specs/dashboard-kiosk-mode/spec.md */
	beforeUnmount() {
		this.stopCursor?.()
		clearTimeout(this.dwellTimer)
		clearTimeout(this.prefetchTimer)
		clearTimeout(this.refreshTimer)
	},

	methods: {
		t,

		/**
		 * Read the playlist and schedule the next read.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
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
				this.offline = false
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
				this.offline = true
			}
			clearTimeout(this.refreshTimer)
			this.refreshTimer = setTimeout(() => this.load(), next)
		},

		/**
		 * Take a fresh playlist. The rotation continues where it was when
		 * the same number of entries came back, and restarts otherwise.
		 *
		 * @param {object} data `{playlist, entries}` from the kiosk route.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
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
			if (firstLoad || (this.dwellTimer === null && !this.advancing)) {
				this.scheduleNext()
			}
		},

		/**
		 * Show the current entry for its dwell time, fetch the next one
		 * shortly before the end, then move on.
		 *
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		scheduleNext() {
			clearTimeout(this.dwellTimer)
			clearTimeout(this.prefetchTimer)
			this.dwellTimer = null
			if (this.entries.length === 0) {
				return
			}
			const dwellMs = Math.max(10, Number(this.current?.dwellSeconds) || 10) * 1000
			const next = (this.index + 1) % this.entries.length
			this.prefetchTimer = setTimeout(
				() => this.prefetch(next),
				Math.max(0, dwellMs - PREFETCH_LEAD_MS),
			)
			this.dwellTimer = setTimeout(() => this.advance(), dwellMs)
		},

		/**
		 * Start fetching an entry before its turn.
		 *
		 * @param {number} index The entry's position in the playlist.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		prefetch(index) {
			const entry = this.entries[index]
			if (entry) {
				this.prefetched = { index, uuid: entry.dashboard?.uuid, result: this.fetchEntry(entry) }
			}
		},

		/**
		 * Fetch one entry's render payload, or null when it fails or has not
		 * answered within the entry timeout.
		 *
		 * @param {object} entry The entry as the playlist read gave it.
		 * @return {Promise<object|null>} The fresh entry, or null.
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		fetchEntry(entry) {
			const uuid = entry?.dashboard?.uuid
			if (!uuid) {
				return Promise.resolve(null)
			}
			const read = axios
				.get(
					generateUrl(
						`/apps/launchpad/kiosk/${encodeURIComponent(this.token)}/dashboards/${encodeURIComponent(uuid)}`,
					),
					{ headers: { Accept: 'application/json' } },
				)
				.then(({ data }) => (data?.dashboard ? { ...entry, ...data } : null))
				.catch(() => null)
			const timeout = new Promise((resolve) => {
				setTimeout(() => resolve(null), ENTRY_TIMEOUT_MS)
			})
			return Promise.race([read, timeout])
		},

		/**
		 * Move to the next entry that loads: the prefetched one when it
		 * arrived, otherwise each following entry in turn. A failed entry
		 * is skipped this loop only. When none loads, the screen keeps what
		 * it shows.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		async advance() {
			this.dwellTimer = null
			this.advancing = true
			const count = this.entries.length
			for (let step = 1; step <= count; step++) {
				const index = (this.index + step) % count
				const entry = this.entries[index]
				const ready = this.prefetched?.index === index && this.prefetched.uuid === entry?.dashboard?.uuid
				const fresh = await (ready ? this.prefetched.result : this.fetchEntry(entry))
				if (fresh !== null && this.entries[index]?.dashboard?.uuid === fresh.dashboard?.uuid) {
					this.entries.splice(index, 1, fresh)
					this.index = index
					break
				}
			}
			this.prefetched = null
			this.advancing = false
			this.scheduleNext()
		},

		/**
		 * Stop rotating.
		 *
		 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
		 */
		stop() {
			clearTimeout(this.dwellTimer)
			clearTimeout(this.prefetchTimer)
			this.dwellTimer = null
			this.prefetched = null
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

.kiosk-player__reconnect {
	position: fixed;
	inset-inline-end: 16px;
	inset-block-end: 16px;
	margin: 0;
	padding: 4px 12px;
	border-radius: var(--border-radius-pill);
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 0.875em;
}

.kiosk-player__message {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 80vh;
	font-size: 1.5em;
}
</style>
