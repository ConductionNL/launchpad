// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The text colour of an App or link tile (REQ-APPTILE-005): one of the
 * theme's tile colours, white or black, white by default. The tile stores
 * the name (`content.textColour`) and draws its title, count and icon in it;
 * the form checks its contrast on the tile colour against WCAG AA (4.5:1).
 *
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
 */

import { isColourToken, TILE_COLOUR_TOKENS } from './tileColour.js'

/** The text colour a tile without one draws in. */
export const DEFAULT_TEXT_COLOUR = 'white'

/** WCAG AA for normal text. */
export const WCAG_AA = 4.5

const PLAIN = Object.freeze({ white: '#ffffff', black: '#000000' })

/** The text colours the form offers, in order: the eight tile tokens, then white and black. */
export const TEXT_COLOURS = Object.freeze([
	...TILE_COLOUR_TOKENS.map((entry) => entry.token),
	'white',
	'black',
])

/**
 * Whether a stored value is a text colour the tile knows.
 *
 * @param {unknown} value The stored value.
 * @return {boolean}
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
 */
export function isTextColour(value) {
	return value === 'white' || value === 'black' || isColourToken(value)
}

/**
 * The text colour a tile draws in: its stored one, else white.
 *
 * @param {{textColour?: string}|null} content The tile's content.
 * @return {string} The text colour name.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
 */
export function tileTextColour(content) {
	return isTextColour(content?.textColour) ? content.textColour : DEFAULT_TEXT_COLOUR
}

/**
 * The CSS value of a text colour: a token through the theme (with the
 * palette's fallback), white or black as is.
 *
 * @param {string} textColour The text colour name.
 * @return {string} The CSS value.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
 */
export function textColourValue(textColour) {
	if (PLAIN[textColour]) {
		return PLAIN[textColour]
	}
	if (isColourToken(textColour)) {
		const known = TILE_COLOUR_TOKENS.find((entry) => entry.token === textColour)
		return `var(${textColour}, ${known ? known.fallback : PLAIN.white})`
	}
	return PLAIN.white
}

/**
 * Parse a CSS colour as `#rgb`, `#rrggbb` or `rgb()`/`rgba()`.
 *
 * @param {string} value The colour.
 * @return {Array<number>|null} The red, green and blue channels, 0..255, or null.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
 */
export function parseColour(value) {
	const text = String(value || '').trim().toLowerCase()
	const hex = text.match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/)
	if (hex) {
		const digits = hex[1].length === 3 ? hex[1].split('').map((d) => d + d).join('') : hex[1]
		return [0, 2, 4].map((offset) => parseInt(digits.slice(offset, offset + 2), 16))
	}
	const rgb = text.match(/^rgba?\(\s*(\d+(?:\.\d+)?)[\s,]+(\d+(?:\.\d+)?)[\s,]+(\d+(?:\.\d+)?)/)
	if (rgb) {
		return [rgb[1], rgb[2], rgb[3]].map((channel) => Math.min(255, Math.round(Number(channel))))
	}
	return null
}

/**
 * The relative luminance of a colour (WCAG 2.x).
 *
 * @param {Array<number>} channels Red, green and blue, 0..255.
 * @return {number} 0..1.
 */
function luminance(channels) {
	const [r, g, b] = channels.map((c) => {
		const s = c / 255
		return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
	})
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * The contrast ratio of two CSS colours (WCAG 2.x), or null when one cannot
 * be read.
 *
 * @param {string} first One colour.
 * @param {string} second The other.
 * @return {number|null} 1..21.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
 */
export function contrastRatio(first, second) {
	const a = parseColour(first)
	const b = parseColour(second)
	if (!a || !b) {
		return null
	}
	const [light, dark] = [luminance(a), luminance(b)].sort((x, y) => y - x)
	return (light + 0.05) / (dark + 0.05)
}

/**
 * Resolve a colour name (a theme token, white, black or a CSS colour) to the
 * value the current theme gives it.
 *
 * @param {string} name The colour.
 * @param {Element|null} [element] Where the theme's custom properties are read; the document root by default.
 * @return {string} The CSS colour.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
 */
export function resolveColour(name, element = null) {
	if (PLAIN[name]) {
		return PLAIN[name]
	}
	if (!isColourToken(name)) {
		return String(name || '')
	}
	const value = themeValue(name, element)
	if (value !== '') {
		return value
	}
	const known = TILE_COLOUR_TOKENS.find((entry) => entry.token === name)
	return known ? known.fallback : TILE_COLOUR_TOKENS[0].fallback
}

/**
 * The value the theme gives a custom property, or '' where none can be read.
 *
 * @param {string} name The custom property.
 * @param {Element|null} element Where to read it; the document root by default.
 * @return {string}
 */
function themeValue(name, element) {
	const root = element || (typeof document !== 'undefined' ? document.documentElement : null)
	if (!root || typeof getComputedStyle !== 'function') {
		return ''
	}
	return getComputedStyle(root).getPropertyValue(name).trim()
}

/**
 * The contrast of a tile's text colour on its tile colour in the current
 * theme, and whether it meets WCAG AA.
 *
 * @param {{textColour?: string, colourToken?: string, backgroundColor?: string}} tile The text colour, the tile colour token, or its own hex colour.
 * @param {function(string): string} [resolve] Resolves a colour name to a CSS colour; the current theme by default.
 * @return {{ratio: number|null, passes: boolean}} The ratio, rounded to one decimal, and whether it is at least 4.5:1.
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-text-colour-is-the-tiles-own-and-is-checked-for-contrast-req-apptile-005
 */
export function textContrast(tile, resolve = resolveColour) {
	const text = resolve(tileTextColour(tile))
	const background = isColourToken(tile?.colourToken)
		? resolve(tile.colourToken)
		: resolve(tile?.backgroundColor || TILE_COLOUR_TOKENS[0].token)
	const ratio = contrastRatio(text, background)
	if (ratio === null) {
		return { ratio: null, passes: true }
	}
	return { ratio: Math.round(ratio * 10) / 10, passes: ratio >= WCAG_AA }
}
