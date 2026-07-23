/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * useTileSearch — the on-dashboard quick-search / launcher-bar composable
 * (tile-quick-search: REQ-QSEARCH-001..004).
 *
 * Owns:
 *  - The client-side filter + ranking of the caller-supplied item list by
 *    label (REQ-QSEARCH-002). Ranking order: exact case/diacritic-
 *    insensitive prefix match, then substring match, then a non-contiguous
 *    subsequence ("fuzzy") match; ties preserve the caller's input order.
 *  - The active-selection cursor with wrap-around arrow-key movement
 *    (REQ-QSEARCH-003).
 *  - The pure keyboard-shortcut decisions (`/` and `Ctrl+K` focus-the-bar,
 *    REQ-QSEARCH-001) so the owning component's global `keydown` listener
 *    stays a thin dispatcher.
 *  - The no-match fallback-routing decision (REQ-QSEARCH-004): given the
 *    `quicksearch_fallback_target` setting value and the current query,
 *    decide whether to hand off to Nextcloud unified search, open a
 *    validated web-search URL template, or do nothing.
 *
 * Filtering is entirely in-memory over the `items` the caller passes to
 * `setItems()` — this module never performs a network request and never
 * persists the query anywhere (REQ-QSEARCH-002 "No query stored" scenario).
 *
 * Follows the `Vue.observable()` composable convention already used by
 * `useGridManager.js` / `useNestedGridManager.js` in this codebase (plain
 * Options-API components, no `setup()` required to consume it).
 *
 * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
 */

import Vue from 'vue'

/**
 * No-match fallback: take no action beyond showing the "no results"
 * message (REQ-QSEARCH-004 "No fallback configured" scenario).
 *
 * @type {string}
 */
export const FALLBACK_TARGET_NONE = 'none'

/**
 * No-match fallback: hand the query to Nextcloud's unified search
 * (REQ-QSEARCH-004 "Unified-search fallback" scenario).
 *
 * @type {string}
 */
export const FALLBACK_TARGET_UNIFIED_SEARCH = 'unified-search'

/**
 * Strip diacritics and lower-case a string for comparison. `String#normalize`
 * decomposes accented characters into a base letter + combining marks
 * (NFD); stripping the combining-mark Unicode range leaves the bare ASCII
 * letters, so "café" and "cafe" compare equal. Falls back to a plain
 * lower-case when `normalize` throws (defensive; every supported runtime
 * has it).
 *
 * @param {*} value the raw string (or nullish value) to normalise.
 * @return {string} the normalised, comparison-ready string.
 */
export function normalizeForSearch(value) {
	const str = String(value ?? '')
	try {
		return str.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
	} catch {
		return str.toLowerCase()
	}
}

/**
 * Whether `query`'s characters all appear in `label`, in order, but not
 * necessarily contiguously (the "fuzzy" / subsequence tier).
 *
 * @param {string} query already-normalised query.
 * @param {string} label already-normalised label.
 * @return {boolean} true when `query` is a subsequence of `label`.
 */
function isSubsequence(query, label) {
	let qi = 0
	for (let li = 0; li < label.length && qi < query.length; li++) {
		if (label[li] === query[qi]) {
			qi += 1
		}
	}
	return qi === query.length
}

/**
 * Rank tiers, lowest wins (REQ-QSEARCH-002 "Ranking order" scenario):
 * a prefix match outranks a substring match, which outranks a
 * non-contiguous subsequence match.
 *
 * @type {{PREFIX: number, SUBSTRING: number, SUBSEQUENCE: number}}
 */
export const MATCH_RANK = {
	PREFIX: 0,
	SUBSTRING: 1,
	SUBSEQUENCE: 2,
}

/**
 * Score a single label against a query. Returns `null` when the label does
 * not match at all (excluded from results).
 *
 * @param {string} query the raw (not yet normalised) query.
 * @param {string} label the raw (not yet normalised) item label.
 * @return {number|null} one of {@link MATCH_RANK}'s values, or `null`.
 *
 * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
 */
export function matchRank(query, label) {
	const q = normalizeForSearch(query)
	if (q === '') {
		return null
	}
	const l = normalizeForSearch(label)
	if (l.startsWith(q)) {
		return MATCH_RANK.PREFIX
	}
	if (l.includes(q)) {
		return MATCH_RANK.SUBSTRING
	}
	if (isSubsequence(q, l)) {
		return MATCH_RANK.SUBSEQUENCE
	}
	return null
}

/**
 * Filter + rank `items` (each `{id, label, ...}`) against `query`.
 * Non-matching items are dropped; matches are sorted by
 * {@link matchRank} tier, then by original input order (stable within a
 * tier) so the "Ranking order" scenario's prefix-before-substring-before-
 * subsequence guarantee holds deterministically.
 *
 * @param {string} query the raw query text.
 * @param {Array<{id: string, label: string}>} items the searchable items.
 * @return {Array<{id: string, label: string, item: object, rank: number}>}
 *   the ranked matches, richest match first.
 *
 * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
 */
export function rankItems(query, items) {
	const list = Array.isArray(items) ? items : []
	const scored = []
	list.forEach((item, index) => {
		const label = (item && item.label) ? String(item.label) : ''
		const rank = matchRank(query, label)
		if (rank !== null) {
			scored.push({
				id: item?.id,
				label,
				item,
				rank,
				index,
			})
		}
	})
	scored.sort((a, b) => (a.rank - b.rank) || (a.index - b.index))
	return scored
}

/**
 * Whether a `https` URL template contains the required `{query}`
 * placeholder (REQ-QSEARCH-004 "Fallback template validation" scenario).
 * Substitutes a harmless placeholder for `{query}` before parsing so a
 * template like `https://example.org/search?q={query}` — which is not a
 * valid URL as-is — still validates correctly.
 *
 * @param {*} template the candidate fallback-target value.
 * @return {boolean} true when the template is a valid `https` URL
 *   containing `{query}`.
 *
 * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
 */
export function isValidFallbackTemplate(template) {
	if (typeof template !== 'string' || template.trim() === '') {
		return false
	}
	if (template.includes('{query}') === false) {
		return false
	}
	try {
		const probe = new URL(template.replace('{query}', 'quicksearch-validation-probe'))
		return probe.protocol === 'https:'
	} catch {
		return false
	}
}

/**
 * Decide the no-match fallback action for the given
 * `quicksearch_fallback_target` setting value (REQ-QSEARCH-004).
 *
 * @param {string} fallbackTarget one of `'none'`, `'unified-search'`, or a
 *   `https` URL template containing `{query}`.
 * @param {string} query the query the user typed (not URL-encoded).
 * @return {{type: 'none'}|{type: 'unified-search', query: string}|{type: 'web-search', url: string}}
 *   the resolved action; the caller executes the actual side effect.
 *
 * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
 */
export function resolveFallbackAction(fallbackTarget, query) {
	if (fallbackTarget === FALLBACK_TARGET_UNIFIED_SEARCH) {
		return { type: FALLBACK_TARGET_UNIFIED_SEARCH, query }
	}
	if (isValidFallbackTemplate(fallbackTarget)) {
		return {
			type: 'web-search',
			url: fallbackTarget.replace('{query}', encodeURIComponent(query)),
		}
	}
	return { type: FALLBACK_TARGET_NONE }
}

/**
 * Whether a keydown `target` is a place where typing text is the user's
 * intent — the global `/` shortcut MUST NOT hijack it (REQ-QSEARCH-001
 * "Slash focuses the bar" scenario).
 *
 * @param {EventTarget|null} target the event's `target`.
 * @return {boolean} true for `<input>`, `<textarea>`, `<select>`, or any
 *   `contenteditable` element.
 *
 * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
 */
export function isTypingTarget(target) {
	if (!target || typeof target !== 'object') {
		return false
	}
	const tagName = typeof target.tagName === 'string' ? target.tagName.toLowerCase() : ''
	if (tagName === 'input' || tagName === 'textarea' || tagName === 'select') {
		return true
	}
	return Boolean(target.isContentEditable)
}

/**
 * Whether a `keydown` event is the "focus the search bar with `/`"
 * shortcut (REQ-QSEARCH-001). Excludes modified presses (`Ctrl+/` etc,
 * which browsers/OSes may bind elsewhere) and any press while the user is
 * already typing in a text field.
 *
 * @param {KeyboardEvent} event the keydown event.
 * @return {boolean} true when the event should focus the search input.
 *
 * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
 */
export function isSlashFocusShortcut(event) {
	if (!event || event.key !== '/') {
		return false
	}
	if (event.ctrlKey || event.metaKey || event.altKey) {
		return false
	}
	return isTypingTarget(event.target) === false
}

/**
 * Whether a `keydown` event is the "focus the search bar with Ctrl+K /
 * Cmd+K" shortcut (REQ-QSEARCH-001). Unlike `/`, this fires even while
 * focus is already in a text field — Ctrl+K is an explicit chord, not a
 * printable character, so it never collides with normal typing.
 *
 * @param {KeyboardEvent} event the keydown event.
 * @return {boolean} true when the event should focus the search input.
 *
 * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
 */
export function isCtrlKFocusShortcut(event) {
	if (!event) {
		return false
	}
	const key = typeof event.key === 'string' ? event.key.toLowerCase() : ''
	if (key !== 'k') {
		return false
	}
	if (event.shiftKey || event.altKey) {
		return false
	}
	return Boolean(event.ctrlKey || event.metaKey)
}

/**
 * Factory — one instance per mounted search bar. Mirrors the
 * `useGridManager()` shape: a `Vue.observable()` reactive `state` plus
 * plain methods, consumable from an Options-API component without
 * `setup()`.
 *
 * @param {object} [options] factory options.
 * @param {Function} [options.onOpen] called with the matched item's raw
 *   payload (`result.item`) when Enter opens a highlighted match.
 * @param {Function} [options.onFallback] called with the
 *   {@link resolveFallbackAction} result when Enter is pressed with zero
 *   matches; the caller performs the actual navigation/dispatch side
 *   effect (this composable stays side-effect free by design).
 * @param {Function} [options.getFallbackTarget] returns the current
 *   `quicksearch_fallback_target` value; consulted lazily so the caller
 *   can change it without recreating the composable.
 * @return {object} the `{state, ...methods}` API — see inline method docs.
 *
 * @spec openspec/changes/tile-quick-search/specs/tile-quick-search/spec.md
 */
export function useTileSearch(options = {}) {
	const { onOpen, onFallback, getFallbackTarget } = options

	const state = Vue.observable({
		query: '',
		results: [],
		activeIndex: -1,
	})

	/** @type {Array<{id: string, label: string}>} */
	let items = []

	/**
	 * Recompute `state.results` from the current `query` + `items`, and
	 * reset the active-selection cursor to the top match (or `-1` when
	 * there are none / the query is empty).
	 *
	 * @return {void}
	 */
	function recompute() {
		const trimmed = state.query.trim()
		if (trimmed === '') {
			state.results = []
			state.activeIndex = -1
			return
		}
		state.results = rankItems(trimmed, items)
		state.activeIndex = state.results.length > 0 ? 0 : -1
	}

	/**
	 * Replace the searchable item list (REQ-QSEARCH-002). Call whenever the
	 * caller's underlying tile/widget list changes (e.g. after a dashboard
	 * switch) so a live query re-filters against the fresh list.
	 *
	 * @param {Array<{id: string, label: string}>} newItems the current
	 *   dashboard's searchable items.
	 * @return {void}
	 */
	function setItems(newItems) {
		items = Array.isArray(newItems) ? newItems : []
		recompute()
	}

	/**
	 * Update the query text and re-filter live (REQ-QSEARCH-002 "Typing
	 * filters tiles by label" scenario). Never triggers a network request
	 * and never persists `query` anywhere (REQ-QSEARCH-002 "No query
	 * stored" scenario) — it lives only in this in-memory observable.
	 *
	 * @param {string} query the new query text.
	 * @return {void}
	 */
	function setQuery(query) {
		state.query = typeof query === 'string' ? query : ''
		recompute()
	}

	/**
	 * Move the active-selection cursor by `delta` positions, wrapping
	 * around at either end (REQ-QSEARCH-003 "Arrow keys move the
	 * selection" scenario).
	 *
	 * @param {number} delta `1` for ArrowDown, `-1` for ArrowUp.
	 * @return {void}
	 */
	function moveSelection(delta) {
		const len = state.results.length
		if (len === 0) {
			state.activeIndex = -1
			return
		}
		const current = state.activeIndex < 0 ? 0 : state.activeIndex
		state.activeIndex = ((current + delta) % len + len) % len
	}

	/**
	 * The currently active result, or `null` when there is none.
	 *
	 * @return {{id: string, label: string, item: object, rank: number}|null}
	 *   the active result.
	 */
	function activeResult() {
		if (state.activeIndex < 0 || state.activeIndex >= state.results.length) {
			return null
		}
		return state.results[state.activeIndex]
	}

	/**
	 * Handle Enter (REQ-QSEARCH-003 "Enter opens the selected tile" +
	 * REQ-QSEARCH-004). With an active match, invokes `onOpen(item)`. With
	 * zero matches, resolves the fallback action per
	 * {@link resolveFallbackAction} and invokes `onFallback(action)` —
	 * this composable never navigates or dispatches anything itself.
	 *
	 * @return {{type: string, [key: string]: *}|{type: 'open', result: object}}
	 *   the action taken, primarily useful for tests.
	 */
	function pressEnter() {
		const result = activeResult()
		if (result) {
			if (typeof onOpen === 'function') {
				onOpen(result.item)
			}
			return { type: 'open', result }
		}

		const target = typeof getFallbackTarget === 'function' ? getFallbackTarget() : FALLBACK_TARGET_NONE
		const action = resolveFallbackAction(target, state.query.trim())
		if (typeof onFallback === 'function') {
			onFallback(action)
		}
		return action
	}

	/**
	 * Clear the query and reset selection (REQ-QSEARCH-003 "Escape clears
	 * and returns focus" scenario). Returning focus to the tile grid is the
	 * owning component's responsibility (DOM concern).
	 *
	 * @return {void}
	 */
	function clear() {
		state.query = ''
		state.results = []
		state.activeIndex = -1
	}

	return {
		state,
		setItems,
		setQuery,
		moveSelection,
		activeResult,
		pressEnter,
		clear,
	}
}
