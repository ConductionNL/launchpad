/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Administrator CSS on the page (platform-dashboard-theme-and-css
 * REQ-THM-003, design D4). The server stores only cleaned and scoped CSS;
 * the page inserts it as the text of a `<style>` element, never as markup.
 */

/** The element holding the CSS for all of LaunchPad. */
export const GLOBAL_STYLE_ID = 'launchpad-custom-css'

/** The element holding the CSS of the dashboard on screen. */
export const DASHBOARD_STYLE_ID = 'launchpad-dashboard-custom-css'

/**
 * Put CSS on the page as the text of one `<style>` element, replacing what it
 * held before. Empty CSS removes the element.
 *
 * @param {string} id The element's id.
 * @param {string|null|undefined} css The cleaned CSS.
 * @param {Document} doc The document, for tests.
 * @return {HTMLStyleElement|null} The element, or null when there is no CSS.
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */
export function applyCustomCss(id, css, doc = document) {
	const text = typeof css === 'string' ? css : ''
	if (text.trim() === '') {
		doc.getElementById(id)?.remove()
		return null
	}

	const style = styleElement(id, doc)
	style.textContent = text
	return style
}

/**
 * The `<style>` element with this id, created in the head when there is none
 * or when the id belongs to another element, which is removed.
 *
 * @param {string} id The element's id.
 * @param {Document} doc The document.
 * @return {HTMLStyleElement}
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */
function styleElement(id, doc) {
	const existing = doc.getElementById(id)
	if (existing?.tagName === 'STYLE') {
		return existing
	}
	existing?.remove()
	const style = doc.createElement('style')
	style.id = id
	doc.head.appendChild(style)
	return style
}
