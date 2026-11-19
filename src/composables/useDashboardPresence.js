/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { reactive } from 'vue'
import { api } from '../services/api.js'

/**
 * How often an editor renews its presence. The server drops an editor 90
 * seconds after its last renewal, so 30 seconds survives two missed beats.
 */
export const PRESENCE_BEAT_MS = 30 * 1000

/**
 * Who else edits the dashboard this person is editing
 * (building-edit-a-dashboard-together, task 1.1).
 *
 * `state.editors` holds the colleagues as `{displayName, since}`, longest
 * first; `state.status` is `none`, `editing`, or `forbidden` when the server
 * refused (403) or did not know the dashboard (404). A failed renewal keeps
 * the last list; the next beat tries again. `onRefused` is called once when a
 * renewal is refused, so the page can leave edit mode.
 *
 * @param {object} [options] Options.
 * @param {() => void} [options.onRefused] Called when a renewal is refused.
 * @return {{state: object, start: (uuid: string) => Promise<boolean>, stop: () => Promise<void>}}
 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
 */
export function useDashboardPresence({ onRefused } = {}) {
	const state = reactive({
		status: 'none',
		uuid: null,
		editors: [],
	})
	let timer = null

	/**
	 * Withdraw the presence while the page closes.
	 *
	 * @return {void}
	 */
	const onPageHide = () => {
		if (state.status === 'editing' && state.uuid) {
			api.leavePresence(state.uuid, true).catch(() => {})
		}
	}

	/**
	 * Stop the heartbeat and the page-hide listener.
	 *
	 * @return {void}
	 */
	function clear() {
		if (timer !== null) {
			clearInterval(timer)
			timer = null
		}
		window.removeEventListener('pagehide', onPageHide)
	}

	/**
	 * Announce or renew, and take the colleagues the server answers.
	 *
	 * @return {Promise<boolean>} False when the server refused.
	 */
	async function beat() {
		const uuid = state.uuid
		const renewal = state.status === 'editing' && timer !== null
		try {
			const { data } = await api.joinPresence(uuid)
			if (state.uuid !== uuid) {
				return true
			}
			state.status = 'editing'
			state.editors = Array.isArray(data?.editors) ? data.editors : []
			return true
		} catch (error) {
			const status = error?.response?.status
			if (status === 403 || status === 404) {
				clear()
				state.status = 'forbidden'
				state.editors = []
				if (renewal && typeof onRefused === 'function') {
					onRefused()
				}
				return false
			}
			// A network hiccup: keep the last list, the next beat tries again.
			return state.status === 'editing'
		}
	}

	/**
	 * Start announcing this person on a dashboard.
	 *
	 * @param {string} uuid The dashboard UUID.
	 * @return {Promise<boolean>} False when the server refused.
	 */
	async function start(uuid) {
		await stop()
		state.uuid = uuid
		state.status = 'editing'
		const joined = await beat()
		if (state.status === 'editing') {
			timer = setInterval(beat, PRESENCE_BEAT_MS)
			window.addEventListener('pagehide', onPageHide)
		}
		return joined
	}

	/**
	 * Stop announcing; tells the server when this person was editing.
	 *
	 * @return {Promise<void>}
	 */
	async function stop() {
		const uuid = state.uuid
		const wasEditing = state.status === 'editing'
		clear()
		state.status = 'none'
		state.uuid = null
		state.editors = []
		if (wasEditing && uuid) {
			try {
				await api.leavePresence(uuid)
			} catch {
				// A missed leave costs at most the 90-second timeout.
			}
		}
	}

	return { state, start, stop }
}
