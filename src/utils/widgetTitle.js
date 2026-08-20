/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The one rule for "what is this placement called".
 *
 * WHY THIS IS SHARED RATHER THAN DUPLICATED
 * -----------------------------------------
 * Two places need a placement's display name and they MUST agree: the grid
 * header (`WidgetWrapper.vue`) and the quick-search result list
 * (`useTileSearchHost.js`). The spec is explicit that search results read like
 * the rendered tile titles — if the two drift, quick search starts listing
 * names the user cannot see on screen, or fails to find tiles they can.
 *
 * They had already drifted into two copies of the same expression, and both
 * copies were incomplete in the same way (measured on a real dashboard,
 * 2026-08-19): six `nc-widget` proxy placements — "Deals overview", "Recent
 * activity", "Document Anonymization", "Catalogi Overview", "My Leads",
 * "Recommended files" — all resolved to the generic word "Widget". They were
 * therefore unfindable by quick search, while rendering their real titles on
 * screen from inside their own renderer. The `search` widget itself had the
 * same problem and shipped a header reading "Widget".
 *
 * WHY `nc-widget` NEEDS ITS OWN STEP
 * ----------------------------------
 * An `nc-widget` placement is a PROXY. Its own `widgetId` is the literal
 * string `nc-widget`, which is not in the Nextcloud Dashboard catalog and
 * never will be; the id of the widget it actually proxies lives in
 * `content.widgetId` (e.g. `pipelinq_deals_overview_widget`). Looking up
 * `placement.widgetId` alone can only ever miss.
 *
 * WHY THE COMMUNAL REGISTRY, NOT LAUNCHPAD'S
 * ------------------------------------------
 * The type display name is read from nc-vue's `dashboardWidgetRegistry`
 * rather than from `src/constants/widgetRegistry.js`. LaunchPad's registry
 * imports the renderer components — including `SearchWidget.vue`, which
 * reaches this module through `useTileSearchHost.js`. Importing it back here
 * would close the loop `widgetRegistry → SearchWidget → useTileSearchHost →
 * widgetTitle → widgetRegistry`, and a module-level `registerDashboardWidget`
 * call in a cycle can run before the renderer binding is initialised, leaving
 * the type registered with an `undefined` renderer. Reading the communal
 * object, which LaunchPad registers INTO, gets the same value with no cycle.
 */

import { dashboardWidgetRegistry } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'

/**
 * Resolve the human-readable title of a widget placement.
 *
 * Order, most specific first:
 *  1. a launcher tile's own title
 *  2. the author's explicit per-placement override
 *  3. the Nextcloud Dashboard widget the placement IS
 *  4. the Nextcloud Dashboard widget an `nc-widget` placement PROXIES
 *  5. the widget type's display name (`Search`, `Chart`, `Clock`, …)
 *  6. the generic fallback
 *
 * @param {object} placement a `widgetPlacements` row.
 * @param {Array<object>} availableWidgets the Nextcloud Dashboard widget
 *   catalog (`{id, title}` entries) to resolve ids against.
 * @return {string} the title to display and to search by.
 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
 */
export function resolveWidgetTitle(placement, availableWidgets) {
	if (!placement) {
		return t('launchpad', 'Widget')
	}

	if (placement.tileType === 'custom') {
		return placement.tileTitle || t('launchpad', 'Tile')
	}

	// An empty string is a real stored value here (the add-widget modal writes
	// `''` when the author leaves the field alone), so truthiness is the right
	// test — `!= null` would return the empty string and blank the header.
	if (placement.customTitle) {
		return placement.customTitle
	}

	const catalog = availableWidgets || []

	const direct = catalog.find((w) => w.id === placement.widgetId)
	if (direct?.title) {
		return direct.title
	}

	const proxiedId = placement.content?.widgetId
	if (proxiedId) {
		const proxied = catalog.find((w) => w.id === proxiedId)
		if (proxied?.title) {
			return proxied.title
		}
	}

	// The communal registration stores a plain English label; re-localise it
	// the same way `widgetRegistry.js::decorate()` does, so the picker, the
	// grid header and the search results all read identically.
	const displayName = dashboardWidgetRegistry?.[placement.widgetId]?.displayName
	if (displayName) {
		return t('launchpad', displayName)
	}

	return t('launchpad', 'Widget')
}
