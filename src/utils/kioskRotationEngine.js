/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Framework-agnostic kiosk rotation engine. Drives the chrome-less wall-display
 * rotation: per-entry dwell timers, just-in-time prefetch (~5 s before a switch),
 * skip-on-failure with next-loop retry, last-known-content retention on network
 * loss, neutral placeholder on cold-start total failure, and a liveness watchdog
 * that restarts the rotation if no advance occurs within 2 x max dwell.
 *
 * Time, scheduling, and the fetch function are all injected so the engine can be
 * unit-tested with a mocked clock and no wall-clock waits (Task 9 / REQ-KIOSK-005).
 *
 * @spec openspec/changes/dashboard-kiosk-mode/specs/dashboard-kiosk-mode/spec.md
 */

const PREFETCH_LEAD_MS = 5000
const FAILURE_GRACE_MS = 10000
const TOTAL_FAILURE_BACKOFF_MS = 10000

/**
 * @typedef {Object} RotationEntry
 * @property {number} dwellSeconds
 * @property {Object} dashboard
 */

export class KioskRotationEngine {

	/**
	 * @param {Object}   opts
	 * @param {RotationEntry[]} opts.entries        Initial entries (may be re-set later).
	 * @param {Function} opts.fetchEntry            async (index) => payload | throws. Used for refresh + prefetch.
	 * @param {Function} opts.onRender              (entry, index) => void. Called when an entry becomes visible.
	 * @param {Function} [opts.onStateChange]       (state) => void. 'live' | 'reconnecting' | 'placeholder'.
	 * @param {Function} [opts.now]                 () => epoch ms. Defaults to Date.now.
	 * @param {Function} [opts.setTimer]            (cb, ms) => handle. Defaults to setTimeout.
	 * @param {Function} [opts.clearTimer]          (handle) => void. Defaults to clearTimeout.
	 */
	constructor({
		entries = [],
		fetchEntry,
		onRender,
		onStateChange = () => {},
		now = () => Date.now(),
		setTimer = (cb, ms) => setTimeout(cb, ms),
		clearTimer = (h) => clearTimeout(h),
	}) {
		this.entries = entries
		this.fetchEntry = fetchEntry
		this.onRender = onRender
		this.onStateChange = onStateChange
		this.now = now
		this.setTimer = setTimer
		this.clearTimer = clearTimer

		this.currentIndex = -1
		/** @type {Set<number>} indices that failed this loop and must be retried next loop */
		this.failedThisLoop = new Set()
		this.lastAdvanceAt = 0
		this.state = 'placeholder'
		this.hasEverRendered = false
		/** @type {number} successful renders since the current loop started */
		this.renderedThisLoop = 0
		this._dwellTimer = null
		this._watchdogTimer = null
		this._backoffTimer = null
		this._running = false
	}

	/** Replace the entry set (e.g. after a render-payload refresh). */
	setEntries(entries) {
		this.entries = Array.isArray(entries) ? entries : []
	}

	/** Longest dwell across entries, in ms. Used to size the watchdog. */
	maxDwellMs() {
		const max = this.entries.reduce((m, e) => Math.max(m, e.dwellSeconds || 0), 0)
		return Math.max(max, 1) * 1000
	}

	/** Start rotation from the first available entry. */
	start() {
		this._running = true
		this.currentIndex = -1
		this.failedThisLoop.clear()
		this.renderedThisLoop = 0
		this._armWatchdog()
		this._advance()
	}

	/** Stop all timers. */
	stop() {
		this._running = false
		for (const key of ['_dwellTimer', '_watchdogTimer', '_backoffTimer']) {
			if (this[key] !== null) {
				this.clearTimer(this[key])
				this[key] = null
			}
		}
	}

	/** @returns {number} index of the next entry to try after `from`, wrapping. */
	_nextIndex(from) {
		if (this.entries.length === 0) {
			return -1
		}
		return (from + 1) % this.entries.length
	}

	/** Advance to the next entry, skipping nothing here — failures are handled at render time. */
	_advance() {
		if (this._running === false) {
			return
		}

		if (this.entries.length === 0) {
			this._setState('placeholder')
			// Nothing to show yet — poll again after the backoff rather than
			// spinning, so a cold start during an outage stays calm.
			this._scheduleBackoffRetry()
			return
		}

		const next = this._nextIndex(this.currentIndex)

		// A full loop has completed when we wrap back to or past the start.
		if (next <= this.currentIndex) {
			// If the whole loop produced no successful render, every entry is
			// currently failing. Do NOT immediately re-advance (that busy-loops
			// through microtasks and never yields); back off and retry the loop
			// after a delay, keeping the placeholder / reconnect indicator up.
			if (this.renderedThisLoop === 0) {
				this._setState(this.hasEverRendered ? 'reconnecting' : 'placeholder')
				this.currentIndex = -1
				this.failedThisLoop.clear()
				this._scheduleBackoffRetry()
				return
			}
			// Loop succeeded at least once — reset counters and continue.
			this.failedThisLoop.clear()
			this.renderedThisLoop = 0
		}

		this.currentIndex = next
		this._showCurrent()
	}

	/** Retry a stalled/empty rotation after a backoff (no busy-loop). */
	_scheduleBackoffRetry() {
		if (this._backoffTimer !== null) {
			this.clearTimer(this._backoffTimer)
		}
		this._backoffTimer = this.setTimer(() => {
			this._backoffTimer = null
			if (this._running === true) {
				this.currentIndex = -1
				this._advance()
			}
		}, TOTAL_FAILURE_BACKOFF_MS)
	}

	/** Render the current entry; on failure, skip after the grace period. */
	_showCurrent() {
		const index = this.currentIndex
		const entry = this.entries[index]
		this.lastAdvanceAt = this.now()
		this._armWatchdog()

		let settled = false

		const grace = this.setTimer(() => {
			if (settled === true) {
				return
			}
			settled = true
			// Entry did not render within the grace window — skip it.
			this.failedThisLoop.add(index)
			this._advance()
		}, FAILURE_GRACE_MS)

		Promise.resolve()
			.then(() => this.fetchEntry(index))
			.then((payload) => {
				if (settled === true) {
					return
				}
				settled = true
				this.clearTimer(grace)

				if (payload !== undefined && payload !== null) {
					this.entries[index] = { ...this.entries[index], dashboard: payload }
				}

				this.hasEverRendered = true
				this.renderedThisLoop += 1
				this._setState('live')
				this.onRender(this.entries[index], index)
				this._scheduleDwell(entry ? entry.dwellSeconds : 10)
			})
			.catch(() => {
				if (settled === true) {
					return
				}
				settled = true
				this.clearTimer(grace)
				this.failedThisLoop.add(index)
				// On a network/render failure, retain last-known content if we
				// have ever rendered; otherwise show the neutral placeholder.
				this._setState(this.hasEverRendered ? 'reconnecting' : 'placeholder')
				this._advance()
			})
	}

	/** Schedule the switch to the next entry after `dwellSeconds`, with prefetch. */
	_scheduleDwell(dwellSeconds) {
		const dwellMs = Math.max(1, dwellSeconds) * 1000

		// Just-in-time prefetch of the next entry ~5 s before the switch.
		const prefetchDelay = Math.max(0, dwellMs - PREFETCH_LEAD_MS)
		this.setTimer(() => {
			const next = this._nextIndex(this.currentIndex)
			if (next !== -1 && this.fetchEntry) {
				Promise.resolve()
					.then(() => this.fetchEntry(next))
					.then((payload) => {
						if (payload !== undefined && payload !== null && this.entries[next]) {
							this.entries[next] = { ...this.entries[next], dashboard: payload }
						}
					})
					.catch(() => {})
			}
		}, prefetchDelay)

		if (this._dwellTimer !== null) {
			this.clearTimer(this._dwellTimer)
		}
		this._dwellTimer = this.setTimer(() => this._advance(), dwellMs)
	}

	/** (Re)arm the liveness watchdog: restart if no advance within 2 x max dwell. */
	_armWatchdog() {
		if (this._watchdogTimer !== null) {
			this.clearTimer(this._watchdogTimer)
		}
		const window = 2 * this.maxDwellMs()
		this._watchdogTimer = this.setTimer(() => {
			if (this._running === false) {
				return
			}
			const sinceAdvance = this.now() - this.lastAdvanceAt
			if (sinceAdvance >= window) {
				// Frozen rotation — restart from the first available entry.
				this.currentIndex = -1
				this.failedThisLoop.clear()
				this._advance()
			} else {
				this._armWatchdog()
			}
		}, window)
	}

	/** Emit a state transition (live | reconnecting | placeholder). */
	_setState(state) {
		if (this.state !== state) {
			this.state = state
			this.onStateChange(state)
		}
	}

}

export { PREFETCH_LEAD_MS, FAILURE_GRACE_MS }
