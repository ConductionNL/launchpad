// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The eight theme colours a tile can take (REQ-ICT-004). A tile stores the
 * token name (`content.colourToken`) and renders through it, so the colour
 * follows the theme; a tile with a hex colour keeps it.
 *
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-the-tile-colour-is-one-of-eight-theme-colours-req-ict-004
 */

/** The swatches in palette order, each with its fallback for an unthemed page. */
export const TILE_COLOUR_TOKENS = Object.freeze([
	{ token: '--sc-primary', fallback: '#3669a5' },
	{ token: '--sc-primary-deep', fallback: '#234a78' },
	{ token: '--sc-accent', fallback: '#cc0000' },
	{ token: '--sc-accent-deep', fallback: '#a30000' },
	{ token: '--sc-work-ink', fallback: '#1b1c1d' },
	{ token: '--sc-work-text', fallback: '#3d4047' },
	{ token: '--sc-work-muted', fallback: '#5e6168' },
	{ token: '--sc-work-field', fallback: '#828a95' },
])

const PRIMARY = `var(--sc-primary, ${TILE_COLOUR_TOKENS[0].fallback})`

/**
 * Whether a value names a theme colour token.
 *
 * @param {unknown} token The stored value.
 * @return {boolean}
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-the-tile-colour-is-one-of-eight-theme-colours-req-ict-004
 */
export function isColourToken(token) {
	return typeof token === 'string' && /^--sc-[a-z0-9-]+$/.test(token)
}

/**
 * The CSS background of a tile: through its token when it stores one (a
 * token the theme does not define falls back to the primary colour), else
 * its hex colour, else the primary colour.
 *
 * @param {{backgroundColor?: string}|null} tile The tile.
 * @param {{colourToken?: string}|null} content The placement content.
 * @return {string} the CSS value.
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-the-tile-colour-is-one-of-eight-theme-colours-req-ict-004
 */
export function tileBackground(tile, content) {
	const token = content?.colourToken
	if (isColourToken(token)) {
		const known = TILE_COLOUR_TOKENS.find((entry) => entry.token === token)
		return known ? `var(${token}, ${known.fallback})` : `var(${token}, ${PRIMARY})`
	}
	if (typeof tile?.backgroundColor === 'string' && tile.backgroundColor !== '') {
		return tile.backgroundColor
	}
	return PRIMARY
}
