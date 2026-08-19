/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Host-side wiring for the tile quick-search (tile-quick-search
 * REQ-QSEARCH-002..004).
 *
 * `RuntimeShellSearch.vue` is a pure combobox: it ranks, filters and emits.
 * Something has to turn those emissions into effects on the page — resolve a
 * placement's display label, scroll the chosen tile into view and activate
 * it, move focus back to the grid on Escape, and perform the no-match
 * fallback. That used to live in `WorkspaceApp.vue`, which owned the only
 * search bar. Now that search is a placeable widget, this module owns it and
 * `SearchWidget.vue` calls in.
 *
 * WHAT IS AND IS NOT A DOM CONCERN HERE
 * ------------------------------------
 * Dimming is NOT here — it is reactive state in `src/stores/tileSearch.js`,
 * because deciding whether a tile is dimmed is application state and ADR-004
 * forbids reading that back out of the DOM.
 *
 * What remains are genuine imperative *actions* on elements: scrolling a tile
 * into view, clicking its link so the configured target is honoured, and
 * moving focus. Those cannot be expressed as reactive state. They resolve
 * their element from the page-level grid container and, for activation, a
 * selector built from the STORE's id — nothing is ever read back out of the
 * markup.
 */

import { translate as t } from '@nextcloud/l10n'

/**
 * Id of the grid container declared once in `WorkspaceApp.vue`. It carries
 * `tabindex="-1"` precisely so it can be a programmatic focus target for the
 * Escape contract, and it is the same node the old `.workspace-shell__grid`
 * class selector resolved to.
 *
 * @type {string}
 */
export const GRID_CONTAINER_ID = 'launchpad-main-content'

/**
 * Resolve the grid container element, or `null` when the page has not
 * rendered one (unit tests, SSR, empty-state dashboards).
 *
 * @return {HTMLElement|null} the grid container.
 */
function gridContainer() {
	if (typeof document === 'undefined') {
		return null
	}
	return document.getElementById(GRID_CONTAINER_ID)
}

/**
 * Resolve a placement's quick-search display label (REQ-QSEARCH-002).
 *
 * Mirrors the exact rule the grid itself uses so search results read like the
 * rendered tile titles: tile placements use `tileTitle`; every other
 * placement uses `customTitle || widget.title || 'Widget'` — the same
 * expression as `WidgetWrapper.vue`'s `widgetTitle` computed. If those two
 * ever diverge, the search bar starts listing names the user cannot see on
 * screen.
 *
 * @param {object} placement a `widgetPlacements` row.
 * @param {Array<object>} availableWidgets the widget catalog to resolve
 *   `placement.widgetId` against.
 * @return {string} the label to search and display for this placement.
 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
 */
export function tileSearchLabel(placement, availableWidgets) {
	if (placement.tileType === 'custom') {
		return placement.tileTitle || t('launchpad', 'Tile')
	}
	const widget = (availableWidgets || []).find(
		(w) => w.id === placement.widgetId,
	)
	return placement.customTitle || widget?.title || t('launchpad', 'Widget')
}

/**
 * Build the searchable item list the combobox consumes (REQ-QSEARCH-002).
 *
 * Sourced from the live store rather than the initial-state snapshot so the
 * results re-filter correctly after a placement add/remove or a dashboard
 * switch, with no page reload.
 *
 * @param {Array<object>} placements the current `widgetPlacements`.
 * @param {Array<object>} availableWidgets the widget catalog.
 * @return {Array<{id: (string|number), label: string, placement: object}>} the
 *   searchable items.
 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
 */
export function buildSearchableTiles(placements, availableWidgets) {
	return (placements || []).map((placement) => ({
		id: placement.id,
		label: tileSearchLabel(placement, availableWidgets),
		placement,
	}))
}

/**
 * Activate a search result's rendered tile: scroll it into view, then click
 * its link so whatever `target="_blank"`/`_self` `TileWidget.vue` already
 * rendered is honoured (REQ-QSEARCH-003 "honouring its configured link
 * target"). Placements with no link are focused instead, best-effort.
 *
 * `String(... ?? '')`, not the raw value. `placement.id` is an INTEGER off
 * the API row, and this line used to call `placementId.replace(...)` on it.
 * `Number.prototype.replace` does not exist, so it threw a `TypeError` on
 * every activation — inside a Vue event handler, where nothing surfaces it,
 * so pressing Enter on a result silently did nothing (launchpad#95). The
 * truthiness guard did not catch it: a non-zero integer is truthy. `?? ''`
 * rather than a bare cast so `null`/`undefined` become the empty string and
 * are rejected below, instead of being stringified to the literal `"null"`
 * and sent to `querySelector` as a real id to look for.
 *
 * @param {{id: (string|number), placement: object}} item the opened result.
 * @return {boolean} whether a tile was found and acted on.
 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-003
 */
export function activateSearchResult(item) {
	const placementId = String(item?.placement?.id ?? '')
	const grid = gridContainer()
	if (!placementId || !grid) {
		return false
	}
	const el = grid.querySelector(
		`.launchpad-grid-item[data-placement-id="${placementId.replace(/"/g, '\\"')}"]`,
	)
	if (!el) {
		return false
	}
	if (typeof el.scrollIntoView === 'function') {
		el.scrollIntoView({ behavior: 'smooth', block: 'center' })
	}
	const link = el.querySelector('a[href]')
	if (link && typeof link.click === 'function') {
		link.click()
		return true
	}
	if (typeof el.focus === 'function') {
		if (!el.hasAttribute('tabindex')) {
			el.setAttribute('tabindex', '-1')
		}
		el.focus({ preventScroll: true })
	}
	return true
}

/**
 * Move focus to the grid container (REQ-QSEARCH-003 "Escape clears and
 * returns focus" — "focus MUST return to the tile grid"). The search widget
 * is a cell inside the grid, so it cannot move focus to its own container
 * declaratively; it asks for it here.
 *
 * @return {boolean} whether focus was moved.
 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-003
 */
export function focusGrid() {
	const grid = gridContainer()
	if (grid && typeof grid.focus === 'function') {
		grid.focus({ preventScroll: true })
		return true
	}
	return false
}

/**
 * Perform the resolved no-match fallback (REQ-QSEARCH-004).
 *
 * `RuntimeShellSearch` only decides *which* action to take (a pure decision,
 * unit-tested in `useTileSearch.spec.js`); the side-effect happens here
 * because window/navigation access is a host concern.
 *
 * @param {{type: string, url?: string, query?: string}|null} action the
 *   resolved fallback action.
 * @return {void}
 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-004
 */
export function performFallback(action) {
	if (!action) {
		return
	}
	if (action.type === 'web-search' && action.url) {
		window.open(action.url, '_blank', 'noopener,noreferrer')
		return
	}
	if (action.type === 'unified-search') {
		// Best-effort hand-off (REQ-QSEARCH-004 "Unified-search fallback"):
		// dispatch a CustomEvent Nextcloud's own unified-search UI can listen
		// for. Nothing else is touched — if no listener is present the
		// dashboard simply stays put, satisfying "MUST NOT navigate away from
		// the dashboard on its own beyond what the unified-search integration
		// does".
		window.dispatchEvent(
			new CustomEvent('nextcloud:unified-search.search', {
				detail: { query: action.query },
			}),
		)
	}
	// 'none' — RuntimeShellSearch already renders the accessible no-results
	// message; no further action here.
}
