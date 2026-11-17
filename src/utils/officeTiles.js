// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The office tile pack as one container placement: the pack's tiles as
 * `tile` children in a grid of two 2 by 2 tiles per row.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-the-add-flow-offers-an-office-tile-pack-built-from-what-the-server-has-req-oft-001
 */

/** Width and height of one tile inside the container, in inner-grid cells. */
const TILE_CELLS = 2

/** Tiles per row: the container's inner grid is four cells wide. */
const TILES_PER_ROW = 2

/**
 * The link of a pack tile as a `tile` child stores it. An `app` tile links
 * to the app's own page, which the shared tile renders through generateUrl.
 *
 * @param {{linkType: string, linkValue: string}} tile The pack tile.
 * @return {{linkType: string, linkValue: string}} The child's link.
 */
function childLink(tile) {
	if (tile.linkType === 'app') {
		return { linkType: 'app', linkValue: '/apps/' + tile.linkValue + '/' }
	}
	return { linkType: tile.linkType, linkValue: tile.linkValue }
}

/**
 * The container content and size for a pack.
 *
 * @param {Array<{title: string, linkType: string, linkValue: string, icon: string, iconType: string}>} tiles The pack's tiles.
 * @param {string} title The container title.
 * @return {{content: object, size: {w: number, h: number}}} The container content and its outer size.
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md#requirement-the-add-flow-offers-an-office-tile-pack-built-from-what-the-server-has-req-oft-001
 */
export function officePackContainer(tiles, title) {
	const placements = (tiles || []).map((tile, index) => ({
		type: 'tile',
		content: {
			title: tile.title,
			icon: tile.icon,
			iconType: tile.iconType,
			backgroundColor: '',
			textColor: '',
			...childLink(tile),
		},
		gridX: (index % TILES_PER_ROW) * TILE_CELLS,
		gridY: Math.floor(index / TILES_PER_ROW) * TILE_CELLS,
		gridWidth: TILE_CELLS,
		gridHeight: TILE_CELLS,
	}))
	const rows = Math.max(1, Math.ceil(placements.length / TILES_PER_ROW))

	return {
		content: { title, backgroundColor: 'transparent', padding: 'medium', placements },
		size: { w: TILES_PER_ROW * TILE_CELLS, h: rows * TILE_CELLS + 1 },
	}
}
