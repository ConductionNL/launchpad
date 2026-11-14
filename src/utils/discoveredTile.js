// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * A discovered app (an entry of the user's Nextcloud app menu, from
 * `GET /api/tiles/discover`) as a tile, and which discovered apps a dashboard
 * already holds.
 *
 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md#requirement-one-click-turns-a-discovered-app-into-a-tile-req-disc-002
 */

/** An app's own page: `/apps/<id>/`, with or without `/index.php`. */
const APP_PAGE = /^(?:.*\/index\.php)?\/apps\/([^/?#]+)\/?$/

/**
 * The app id an address opens when it is an app's own page.
 *
 * @param {string} href The address.
 * @return {string} The app id, '' for any other address.
 */
function appIdOfHref(href) {
	const path = String(href || '').replace(/^https?:\/\/[^/]+/, '')
	const match = path.match(APP_PAGE)
	return match ? match[1] : ''
}

/**
 * The tile payload for a discovered app: its name, its icon as an image, and
 * an `app` link when the address is the app's own page, else a `url` link to
 * the address.
 *
 * @param {{appId: string, name: string, href: string, icon: string}} app The discovered app.
 * @return {{title: string, icon: string, iconType: string, linkType: string, linkValue: string}} The tile.
 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md#requirement-one-click-turns-a-discovered-app-into-a-tile-req-disc-002
 */
export function tileFromDiscoveredApp(app) {
	const ownPage = appIdOfHref(app.href) === app.appId
	return {
		title: app.name,
		icon: app.icon,
		iconType: 'url',
		linkType: ownPage ? 'app' : 'url',
		linkValue: ownPage ? app.appId : app.href,
	}
}

/**
 * The discovered apps a dashboard already holds, read from its tiles: an
 * `app` tile by its app id, a `url` tile by the address it opens.
 *
 * @param {Array<object>} placements The dashboard's widget placements.
 * @param {Array<{appId: string, href: string}>} apps The discovered apps.
 * @return {Array<string>} The app ids on the dashboard.
 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md#requirement-one-click-turns-a-discovered-app-into-a-tile-req-disc-002
 */
export function discoveredAppIdsOnDashboard(placements, apps) {
	const appTiles = new Set()
	const urlTiles = new Set()
	for (const placement of placements || []) {
		if (placement?.tileLinkType === 'app') {
			appTiles.add(String(placement.tileLinkValue || ''))
		} else if (placement?.tileLinkType === 'url') {
			urlTiles.add(String(placement.tileLinkValue || ''))
		}
	}
	return (apps || [])
		.filter((app) => appTiles.has(app.appId) || urlTiles.has(app.href))
		.map((app) => app.appId)
}
