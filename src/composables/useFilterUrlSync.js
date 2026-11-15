/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useFilterUrlSync — keeps the dashboard's filter bus and the address bar in
 * step (drill-down-cross-widget-filter, REQ-CWF-003).
 *
 * On start the `f` parameter is read once and its clauses enter the bus as
 * `urlBootstrap`, so a bookmarked or shared link opens with the same filter.
 * After that every change of the bus is written back to the URL, debounced so
 * a burst of clicks rewrites the address once. An empty bus removes `f`. The
 * URL is replaced, never pushed: filtering is not navigation, so the back
 * button still leaves the dashboard instead of stepping through clicks.
 */

import { deserializeFilterState, FILTER_PARAM, serializeFilterState } from './useDashboardFilter.js'

/**
 * Milliseconds a change waits before the URL is rewritten.
 *
 * @type {number}
 */
export const URL_SYNC_DEBOUNCE_MS = 500

/**
 * The URL with `f` set to the encoded clauses, or without `f` when there are none.
 *
 * @param {string} href The current URL.
 * @param {Array<object>} clauses The bus's clauses.
 * @return {string}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-003-url-encoding-makes-filtered-views-bookmarkable
 */
export function urlWithFilter(href, clauses) {
	const url = new URL(href)
	const list = Array.isArray(clauses) ? clauses : []
	if (list.length === 0) {
		url.searchParams.delete(FILTER_PARAM)
	} else {
		url.searchParams.set(FILTER_PARAM, serializeFilterState(list))
	}
	return url.toString()
}

/**
 * Connect a bus to the address bar.
 *
 * @param {object} options The wiring.
 * @param {object} options.bus A bus from useFilterBus().
 * @param {{href: string}} [options.location] The location to read (window.location).
 * @param {{replaceState: (state: unknown, title: string, url: string) => void, state: unknown}} [options.history] The history to write (window.history).
 * @param {number} [options.debounceMs] The debounce delay.
 * @param {boolean} [options.hydrate] Whether the URL's `f` seeds the bus; false only writes.
 * @return {{stop: () => void, flush: () => void, restored: Array<object>}} `stop` ends the sync, `flush` writes now; `restored` the clauses read from the URL.
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-003-url-encoding-makes-filtered-views-bookmarkable
 */
export function useFilterUrlSync({ bus, location = window.location, history = window.history, debounceMs = URL_SYNC_DEBOUNCE_MS, hydrate = true }) {
	const param = hydrate ? new URL(location.href).searchParams.get(FILTER_PARAM) : null
	const restored = param ? deserializeFilterState(param).clauses : []
	if (restored.length > 0) {
		bus.hydrate(restored, 'urlBootstrap')
	}

	let timer = null
	let pending = null

	/**
	 * Write the pending clause list to the URL, when it changes the URL.
	 *
	 * @return {void}
	 */
	function flush() {
		if (timer !== null) {
			clearTimeout(timer)
			timer = null
		}
		if (pending === null) {
			return
		}
		const next = urlWithFilter(location.href, pending)
		pending = null
		if (next !== location.href) {
			history.replaceState(history.state, '', next)
		}
	}

	const unsubscribe = bus.subscribe((clauses) => {
		pending = clauses
		if (timer !== null) {
			clearTimeout(timer)
		}
		timer = setTimeout(flush, debounceMs)
	})

	/**
	 * End the sync; a change still waiting is written first.
	 *
	 * @return {void}
	 */
	function stop() {
		unsubscribe()
		flush()
	}

	return { stop, flush, restored }
}
