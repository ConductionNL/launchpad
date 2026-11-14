/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * How a tile opens: same tab, new tab or the panel beside the tiles
 * (launcher-tile-open-behaviour D1, D2). Mirrors lib/Service/TileLinkTarget.php.
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */

export const SAME_TAB = 'same-tab'
export const NEW_TAB = 'new-tab'
export const SIDE_PANEL = 'side-panel'

/** Every accepted link target, in the order the editors offer them. */
export const LINK_TARGETS = [SAME_TAB, NEW_TAB, SIDE_PANEL]

/**
 * Whether a value is one of the three link targets.
 *
 * @param {string|null|undefined} value The value.
 * @return {boolean}
 */
export function isLinkTarget(value) {
	return LINK_TARGETS.includes(value)
}

/**
 * The tile's own target, else the dashboard default, else the rule from
 * before the setting existed: a web address in a new tab, anything else
 * (an app, an internal path) in the same tab.
 *
 * @param {string|null|undefined} tileTarget The tile's own target.
 * @param {string|null|undefined} dashboardDefault The dashboard's default.
 * @param {string|null|undefined} linkType The tile's link type.
 * @return {string} The target.
 */
export function resolveLinkTarget(tileTarget, dashboardDefault, linkType) {
	if (isLinkTarget(tileTarget)) {
		return tileTarget
	}
	if (isLinkTarget(dashboardDefault)) {
		return dashboardDefault
	}
	return linkType === 'url' ? NEW_TAB : SAME_TAB
}

/**
 * Whether an address resolves to this Nextcloud: such an address may always
 * open in the panel. An outside address needs the embed allow-list.
 *
 * @param {string} href The resolved address.
 * @param {string} [origin] The page origin (defaults to window.location.origin).
 * @return {boolean}
 */
export function isSameOrigin(href, origin = window.location.origin) {
	try {
		return new URL(href, origin).origin === origin
	} catch {
		return false
	}
}
