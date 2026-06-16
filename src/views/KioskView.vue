<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V.
  - SPDX-License-Identifier: EUPL-1.2
  -
  - Chrome-less full-viewport kiosk renderer. Shared by the public playlist
  - route (/kiosk/{token}, rotating) and the authenticated kiosk=1 flag on a
  - single dashboard (no rotation). Drives the framework-agnostic rotation
  - engine, hides the cursor on idle, supports Esc-to-exit on authenticated
  - views, and degrades to a reconnect indicator / neutral placeholder rather
  - than ever showing an error page. REQ-KIOSK-001/003/004/005.
  -
  - @spec openspec/changes/dashboard-kiosk-mode/specs/dashboard-kiosk-mode/spec.md
-->

<template>
	<div class="kiosk-view"
		:class="{ 'kiosk-view--cursor-hidden': cursorHidden }"
		@mousemove="onPointerMove">
		<!-- Reconnect indicator: shown while retaining last-known content. -->
		<div v-if="connectionState === 'reconnecting'" class="kiosk-view__reconnect">
			<NcLoadingIcon :size="16" />
			<span>{{ t('launchpad', 'Reconnecting…') }}</span>
		</div>

		<!-- Neutral placeholder: cold-start total failure, no stack traces. -->
		<div v-if="connectionState === 'placeholder'" class="kiosk-view__placeholder">
			<NcLoadingIcon :size="48" />
			<p>{{ t('launchpad', 'Waiting for content…') }}</p>
		</div>

		<!-- The live dashboard surface. The actual grid renderer is supplied by
		     the parent via the default slot, scoped with the current dashboard. -->
		<div v-show="connectionState === 'live'" class="kiosk-view__stage">
			<slot :dashboard="currentDashboard" />
		</div>
	</div>
</template>

<script>
import { NcLoadingIcon } from '@conduction/nextcloud-vue'
import { KioskRotationEngine } from '../utils/kioskRotationEngine.js'
import { useKioskPlaylistStore } from '../stores/kioskPlaylists.js'

const CURSOR_IDLE_MS = 10000

export default {
	name: 'KioskView',

	components: {
		NcLoadingIcon,
	},

	props: {
		/** Public playlist token; when set, the view rotates. */
		token: {
			type: String,
			default: null,
		},
		/** Single dashboard payload for the authenticated kiosk=1 flag (no rotation). */
		dashboard: {
			type: Object,
			default: null,
		},
		/** True for authenticated kiosk=1 views: enables Esc-to-exit. */
		authenticated: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		return { store: useKioskPlaylistStore() }
	},

	data() {
		return {
			engine: null,
			currentDashboard: this.dashboard,
			connectionState: this.dashboard ? 'live' : 'placeholder',
			cursorHidden: false,
			cursorTimer: null,
		}
	},

	mounted() {
		// Suppress chrome and scrollbars while the kiosk view is mounted.
		document.body.classList.add('kiosk-mode-active')
		this.armCursorTimer()

		if (this.authenticated) {
			window.addEventListener('keydown', this.onKeydown)
		}

		if (this.token) {
			this.startRotation()
		}
	},

	beforeDestroy() {
		document.body.classList.remove('kiosk-mode-active')
		window.removeEventListener('keydown', this.onKeydown)
		if (this.cursorTimer) {
			clearTimeout(this.cursorTimer)
		}
		if (this.engine) {
			this.engine.stop()
		}
	},

	methods: {
		async startRotation() {
			let initial = []
			try {
				const payload = await this.store.fetchRender(this.token)
				initial = payload.entries ?? []
			} catch (e) {
				// Unknown/revoked token or cold-start outage: start empty; the
				// engine shows the placeholder and keeps polling for recovery.
				initial = []
			}

			this.engine = new KioskRotationEngine({
				entries: initial,
				fetchEntry: this.fetchEntryPayload,
				onRender: (entry) => { this.currentDashboard = entry.dashboard },
				onStateChange: (state) => { this.connectionState = state },
			})
			this.engine.start()
		},

		/**
		 * Re-fetch the visible entry's render payload in place (REQ-KIOSK-004).
		 * Refetches the whole playlist render and returns the entry at `index`.
		 */
		async fetchEntryPayload(index) {
			const payload = await this.store.fetchRender(this.token)
			const entries = payload.entries ?? []
			if (this.engine) {
				this.engine.setEntries(entries)
			}
			const entry = entries[index]
			return entry ? entry.dashboard : null
		},

		onKeydown(event) {
			if (event.key === 'Escape' && this.authenticated) {
				this.$emit('exit')
			}
		},

		onPointerMove() {
			this.cursorHidden = false
			this.armCursorTimer()
		},

		armCursorTimer() {
			if (this.cursorTimer) {
				clearTimeout(this.cursorTimer)
			}
			this.cursorTimer = setTimeout(() => {
				this.cursorHidden = true
			}, CURSOR_IDLE_MS)
		},
	},
}
</script>

<style scoped>
.kiosk-view {
	position: fixed;
	inset: 0;
	width: 100vw;
	height: 100vh;
	overflow: hidden;
	background: var(--color-main-background);
}

.kiosk-view--cursor-hidden {
	cursor: none;
}

.kiosk-view__stage {
	width: 100%;
	height: 100%;
}

.kiosk-view__reconnect {
	position: fixed;
	bottom: 16px;
	right: 16px;
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 12px;
	border-radius: var(--border-radius-pill);
	background: var(--color-background-dark);
	color: var(--color-text-maxcontrast);
	font-size: 0.85em;
	z-index: 10;
}

.kiosk-view__placeholder {
	position: fixed;
	inset: 0;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 16px;
	color: var(--color-text-maxcontrast);
}
</style>
