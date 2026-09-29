/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { reactive } from 'vue'
import { api } from '../services/api.js'

/**
 * How often a held lock is refreshed. The server keeps a lock for 15 minutes
 * after its last refresh, so five minutes survives two missed beats.
 */
export const HEARTBEAT_MS = 5 * 60 * 1000

/**
 * The editing lock of one dashboard, as the workspace page holds it.
 *
 * `state.status` is one of:
 * - `none`: no lock asked for, or released;
 * - `held`: this person holds the lock and may edit;
 * - `blocked`: a colleague holds it (`holderName`, `since`, `expiresIn`);
 * - `forbidden`: the server refused (403), so the page stays read-only;
 * - `unavailable`: the request failed, so the page stays read-only;
 * - `lost`: a refresh answered 404, someone else has the lock now.
 *
 * Every state but `held` means read-only. There is no path to editing
 * without a granted lock.
 *
 * @param {object} [options] Options.
 * @param {() => void} [options.onLost] Called once when a held lock is lost.
 * @return {object} `{state, acquire, release, takeOver, stop}`.
 * @spec openspec/changes/dashboard-edit-lock-ui/specs/dashboard-locking/spec.md
 */
export function useDashboardLock({ onLost } = {}) {
	const state = reactive({
		status: 'none',
		uuid: null,
		holderName: '',
		since: null,
		expiresIn: null,
	})
	let timer = null

	const onPageHide = () => {
		if (state.status === 'held' && state.uuid) {
			api.releaseLockOnPageHide(state.uuid).catch(() => {})
		}
	}

	/**
	 * Stop refreshing and stop listening for the page closing.
	 *
	 * @spec openspec/changes/dashboard-edit-lock-ui/specs/dashboard-locking/spec.md
	 */
	function stop() {
		if (timer !== null) {
			clearInterval(timer)
			timer = null
		}
		window.removeEventListener('pagehide', onPageHide)
	}

	/**
	 * Refresh the held lock; a 404 or 403 means it is gone.
	 *
	 * @spec openspec/changes/dashboard-edit-lock-ui/specs/dashboard-locking/spec.md
	 */
	async function beat() {
		if (state.status !== 'held') {
			return
		}
		try {
			await api.heartbeatLock(state.uuid)
		} catch (error) {
			const status = error?.response?.status
			if (status === 404 || status === 403) {
				stop()
				state.status = 'lost'
				if (typeof onLost === 'function') {
					onLost()
				}
			}
			// Anything else is a network hiccup: the next beat tries again,
			// and the server keeps the lock for three beats.
		}
	}

	/**
	 * Ask for the lock. Resolves true only when it is granted.
	 *
	 * @param {string} uuid UUID of the dashboard.
	 * @return {Promise<boolean>} Whether the lock is held now.
	 * @spec openspec/changes/dashboard-edit-lock-ui/specs/dashboard-locking/spec.md
	 */
	async function acquire(uuid) {
		stop()
		state.uuid = uuid
		state.holderName = ''
		state.since = null
		state.expiresIn = null
		try {
			const { data } = await api.acquireLock(uuid)
			state.status = 'held'
			state.since = data?.acquiredAt ?? null
			timer = setInterval(beat, HEARTBEAT_MS)
			window.addEventListener('pagehide', onPageHide)
			return true
		} catch (error) {
			const response = error?.response
			if (response?.status === 409) {
				state.status = 'blocked'
				state.holderName = response.data?.lock?.displayName ?? ''
				state.since = response.data?.lock?.acquiredAt ?? null
				state.expiresIn = response.data?.lock?.expiresIn ?? null
			} else if (response?.status === 403) {
				state.status = 'forbidden'
			} else {
				state.status = 'unavailable'
			}
			return false
		}
	}

	/**
	 * Give the lock back. Safe to call when nothing is held.
	 *
	 * @return {Promise<void>}
	 * @spec openspec/changes/dashboard-edit-lock-ui/specs/dashboard-locking/spec.md
	 */
	async function release() {
		const wasHeld = state.status === 'held'
		const uuid = state.uuid
		stop()
		state.status = 'none'
		state.uuid = null
		state.holderName = ''
		state.since = null
		if (wasHeld && uuid) {
			try {
				await api.releaseLock(uuid)
			} catch {
				// A missed release costs at most the lock timeout.
			}
		}
	}

	/**
	 * Administrator take-over: force-release the colleague's lock, then
	 * acquire it.
	 *
	 * @return {Promise<boolean>} Whether the lock is held now.
	 * @spec openspec/changes/dashboard-edit-lock-ui/specs/dashboard-locking/spec.md
	 */
	async function takeOver() {
		const uuid = state.uuid
		if (!uuid) {
			return false
		}
		try {
			await api.forceReleaseLock(uuid)
		} catch (error) {
			state.status =
				error?.response?.status === 403 ? 'forbidden' : 'unavailable'
			return false
		}
		return acquire(uuid)
	}

	return { state, acquire, release, takeOver, stop }
}
