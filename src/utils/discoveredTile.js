// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * A discovered app (an entry of the user's Nextcloud app menu, from
 * `GET /api/tiles/discover`) as an app tile, and which discovered apps a
 * dashboard already holds.
 *
 * @spec openspec/changes/archive/2026-11-19-launcher-discover-server-services/specs/tiles/spec.md#requirement-one-click-turns-a-discovered-app-into-a-tile-req-disc-002
 */

/**
 * The `app-tile` widget a discovered app becomes: one square tile for that
 * app, in its default colour, with its count of new items. The tile reads its
 * name, icon and address from the viewer's own app menu when it draws.
 *
 * @param {{appId: string}} app The discovered app.
 * @return {{type: string, content: {appId: string, colour: string, showCount: boolean}}} The widget payload.
 * @spec openspec/changes/app-tile-widget/specs/app-tile-widget/spec.md#requirement-the-add-flow-offers-every-app-as-its-own-tile-req-apptile-001
 */
export function appTileFromDiscoveredApp(app) {
	return {
		type: 'app-tile',
		content: { appId: String(app.appId), colour: '', showCount: true },
	}
}

/**
 * The discovered apps a dashboard already holds: an `app-tile` widget by its
 * app id, an `app` tile by its app id, a `url` tile by the address it opens.
 *
 * @param {Array<object>} placements The dashboard's widget placements.
 * @param {Array<{appId: string, href: string}>} apps The discovered apps.
 * @return {Array<string>} The app ids on the dashboard.
 * @spec openspec/changes/archive/2026-11-19-launcher-discover-server-services/specs/tiles/spec.md#requirement-one-click-turns-a-discovered-app-into-a-tile-req-disc-002
 * @spec openspec/changes/app-tile-widget/specs/app-tile-widget/spec.md#requirement-the-add-flow-offers-every-app-as-its-own-tile-req-apptile-001
 */
export function discoveredAppIdsOnDashboard(placements, apps) {
	const appTiles = new Set()
	const urlTiles = new Set()
	for (const placement of placements || []) {
		if (placement?.widgetId === 'app-tile') {
			appTiles.add(String(placement.content?.appId || ''))
		} else if (placement?.tileLinkType === 'app') {
			appTiles.add(String(placement.tileLinkValue || ''))
		} else if (placement?.tileLinkType === 'url') {
			urlTiles.add(String(placement.tileLinkValue || ''))
		}
	}
	return (apps || [])
		.filter((app) => appTiles.has(app.appId) || urlTiles.has(app.href))
		.map((app) => app.appId)
}

/**
 * The tile a catalogue entry places: an ordinary tile with the entry's name,
 * icon and address. Later edits of the entry do not change it
 * (launcher-app-catalogue D1).
 *
 * @param {{name: string, icon?: string, iconType?: string, linkType: string, linkValue: string}} app The catalogue entry.
 * @return {{title: string, icon: string, iconType: string, linkType: string, linkValue: string}} The tile payload.
 * @spec openspec/changes/archive/2026-11-19-launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-add-catalogue-apps-as-tiles-from-the-add-flow-req-acat-002
 */
export function tileFromCatalogueApp(app) {
	return {
		title: app.name,
		icon: app.icon || '',
		iconType: app.iconType === 'url' ? 'url' : 'class',
		linkType: app.linkType === 'app' ? 'app' : 'url',
		linkValue: app.linkValue,
	}
}
