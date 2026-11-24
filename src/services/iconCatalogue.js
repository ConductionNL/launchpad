/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared icon catalogue for CnIconBrowser across LaunchPad's icon-input
 * surfaces (tiles, org-nav, dashboards). LaunchPad already depends on `@mdi/js`
 * and stores icons as SVG path strings, so we use the path-based `mdiCatalogue`
 * adapter — the emitted/stored value is a self-contained path that
 * CnDashboardIcon / CnTileWidget render anywhere.
 *
 * `@mdi/js` holds every MDI icon (about 2.8 MB), so it is loaded on demand by
 * {@link loadIconCatalogue} instead of with the page. Until it arrives the
 * catalogue is empty and {@link normaliseIconValue} returns its input; both
 * are reactive, so a picker or icon that reads them updates once it loads.
 */

import { DASHBOARD_ICONS, mdiCatalogue } from '@conduction/nextcloud-vue'
import { markRaw, shallowReactive, shallowRef } from 'vue'

/**
 * The full MDI catalogue, filled by {@link loadIconCatalogue}. Shallow, so the
 * entries are not made reactive.
 *
 * @type {Array<object>}
 */
export const ICON_CATALOGUE = shallowReactive([])

const mdiIcons = shallowRef(null)
let catalogueLoad = null

/**
 * Load `@mdi/js` once and fill {@link ICON_CATALOGUE}.
 *
 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-009
 * @return {Promise<Array<object>>} The filled catalogue.
 */
export function loadIconCatalogue() {
	if (!catalogueLoad) {
		catalogueLoad = import(/* webpackChunkName: "mdi-icons" */ '@mdi/js')
			.then((mdi) => {
				const icons = markRaw({ ...mdi })
				ICON_CATALOGUE.splice(0, ICON_CATALOGUE.length, ...mdiCatalogue(icons))
				mdiIcons.value = icons
				return ICON_CATALOGUE
			})
			.catch((error) => {
				catalogueLoad = null
				throw error
			})
	}
	return catalogueLoad
}

/**
 * {@link ICON_CATALOGUE} for an app-wide provide: the first read, which is a
 * picker rendering, starts {@link loadIconCatalogue}, so a page that never
 * opens a picker never fetches `@mdi/js`.
 *
 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-009
 */
export const LAZY_ICON_CATALOGUE = new Proxy(ICON_CATALOGUE, {
	/**
	 * Read from the catalogue, starting its load on the first read.
	 *
	 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-009
	 * @param {Array<object>} target The catalogue.
	 * @param {string|symbol} key The property read.
	 * @return {unknown} The property's value.
	 */
	get(target, key) {
		if (!catalogueLoad) {
			loadIconCatalogue().catch(() => {})
		}
		return Reflect.get(target, key)
	},
})

/**
 * The dashboard icon picker's catalogue: the names of the shared icon
 * registry (`DASHBOARD_ICONS` in nextcloud-vue), so a picked icon is stored
 * as its registry name (REQ-ICON-003). Tiles and the organisation navigation
 * keep the full MDI catalogue above.
 *
 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-003
 */
export const DASHBOARD_ICON_CATALOGUE = Object.freeze(
	Object.keys(DASHBOARD_ICONS).map((key) => ({
		key,
		label: key.replace(/([a-z\d])([A-Z])/g, '$1 $2'),
		value: key,
		search: key.toLowerCase(),
		component: DASHBOARD_ICONS[key],
	})),
)

// A bare icon-name token: a letter followed by letters/digits and word
// separators only. SVG path strings (commas, dots — see CnDashboardIcon's
// `isSvgPath`) and custom URLs (`/…`, `http…`) deliberately fail this and are
// left untouched.
const BARE_ICON_NAME = /^[A-Za-z][A-Za-z0-9 _-]*$/

/**
 * Rescue a legacy free-text icon name into a renderable value.
 *
 * The org-nav editor was once a free-text `<input>`, so existing nav trees
 * hold arbitrary MDI-style names (`star`, `folder`, `chart-bar`) rather than
 * the SVG paths the icon picker now emits. CnDashboardIcon can't resolve those
 * lowercase names against its PascalCase registry, so it silently falls back to
 * the default icon. Map such names to their `@mdi/js` path so the admin's
 * original choice still renders; anything already renderable (an SVG path, a
 * URL, or a name with no `@mdi/js` match) is returned unchanged.
 *
 * This is what lets the single `icon` column hold legacy names, SVG paths and
 * custom URLs side by side and still render, with no data migration
 * (REQ-ICON-009 "Mixed values across rows render without migration").
 * Before `@mdi/js` has loaded the value comes back unchanged and the load
 * starts; a caller inside a computed or render recomputes once it arrives.
 *
 * @spec openspec/specs/dashboard-icons/spec.md#req-icon-009
 * @param {string|null|undefined} value stored `icon` field value.
 * @return {string|null|undefined} an SVG path when a legacy name resolves,
 *   otherwise the input untouched.
 */
export function normaliseIconValue(value) {
	if (typeof value !== 'string' || !BARE_ICON_NAME.test(value)) {
		return value
	}
	const pascal = value
		.split(/[\s_-]+/)
		.filter(Boolean)
		.map((word) => word.charAt(0).toUpperCase() + word.slice(1))
		.join('')
	const icons = mdiIcons.value
	if (!icons) {
		loadIconCatalogue().catch(() => {})
		return value
	}
	const path = icons['mdi' + pascal]
	return typeof path === 'string' ? path : value
}
