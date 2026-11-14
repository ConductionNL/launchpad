/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The look of one dashboard (platform-dashboard-theme-and-css REQ-THM-001/002):
 * the same contrast rules the server enforces in DashboardLook.php, so the
 * editor can show the refusal before saving, and the CSS variables that draw
 * the look on the dashboard region only.
 */
import { generateUrl } from '@nextcloud/router'

/** The least contrast an accent needs against the background (WCAG 2.2 SC 1.4.11). */
export const MIN_ACCENT_CONTRAST = 3

/** The background a dashboard without a background colour of its own is drawn on. */
export const INSTANCE_BACKGROUND = '#ffffff'

/**
 * A colour as lower-case `#rrggbb`, or null when it is not a hex colour.
 *
 * @param {string} colour The colour as entered.
 * @return {string|null}
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
 */
export function normaliseHex(colour) {
	let value = String(colour ?? '').trim().toLowerCase()
	const short = value.match(/^#([0-9a-f])([0-9a-f])([0-9a-f])$/)
	if (short) {
		value = `#${short[1]}${short[1]}${short[2]}${short[2]}${short[3]}${short[3]}`
	}
	return /^#[0-9a-f]{6}$/.test(value) ? value : null
}

/**
 * The WCAG relative luminance of a `#rrggbb` colour.
 *
 * @param {string} colour The colour.
 * @return {number}
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */
export function relativeLuminance(colour) {
	const [r, g, b] = [1, 3, 5].map((offset) => {
		const value = parseInt(colour.slice(offset, offset + 2), 16) / 255
		return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4
	})
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * The WCAG contrast ratio between two colours.
 *
 * @param {string} first One colour.
 * @param {string} second The other.
 * @return {number}
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */
export function contrast(first, second) {
	const a = relativeLuminance(first)
	const b = relativeLuminance(second)
	return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)
}

/**
 * Black or white, whichever reads better on the background.
 *
 * @param {string} background The background colour.
 * @return {string}
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */
export function textColorFor(background) {
	return contrast('#000000', background) >= contrast('#ffffff', background) ? '#000000' : '#ffffff'
}

/**
 * The accent's contrast against the background, and whether the server will accept it.
 *
 * @param {string|null} accent The accent, or null for none.
 * @param {string|null} background The background, or null for the instance's.
 * @return {{ratio: number|null, readable: boolean}}
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */
export function accentCheck(accent, background) {
	if (!accent) {
		return { ratio: null, readable: true }
	}
	const ratio = Math.round(contrast(accent, background || INSTANCE_BACKGROUND) * 10) / 10
	return { ratio, readable: contrast(accent, background || INSTANCE_BACKGROUND) >= MIN_ACCENT_CONTRAST }
}

/**
 * The inline style that draws a dashboard's look on its region: the accent as
 * Nextcloud's primary colour variables, the background colour with black or
 * white text, and the background image. Empty for a dashboard that follows
 * the theme, so nothing outside the region and nothing on such a dashboard
 * changes.
 *
 * @param {object|null} dashboard The dashboard.
 * @return {object}
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
 */
export function lookStyle(dashboard) {
	const style = {}
	const accent = normaliseHex(dashboard?.themeAccent)
	const background = normaliseHex(dashboard?.themeBackgroundColor)
	const resource = String(dashboard?.themeBackgroundResource ?? '')
	if (accent) {
		style['--color-primary-element'] = accent
		style['--color-primary-element-text'] = textColorFor(accent)
	}
	if (background) {
		const text = textColorFor(background)
		style['background-color'] = background
		style.color = text
		style['--color-main-text'] = text
	}
	if (/^[A-Za-z0-9][A-Za-z0-9._-]*\.(png|jpe?g|gif|webp|svg)$/i.test(resource) && !resource.includes('..')) {
		style['background-image'] = `url("${generateUrl('/apps/launchpad/resource/' + encodeURIComponent(resource))}")`
		style['background-size'] = 'cover'
		style['background-position'] = 'center'
	}
	return style
}
