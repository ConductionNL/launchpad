/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Where the workspace's top-right controls (the More menu, board LpMeer)
 * render.
 *
 * Without a greeting row they float at the top right. When the dashboard
 * opens with a greeting on the page ground (a header widget with `ground`),
 * LpStart draws the page's actions in that row; floating, they covered the
 * greeting. So the controls dock into the greeting row instead: this module
 * finds the row and gives it one actions element to teleport into.
 */

/** Class of the element the controls are teleported into. */
export const HEADER_ACTIONS_CLASS = 'launchpad-header-actions'

/** Class of the actions slot in a dashboard's page title row (DashboardPageHeader). */
export const PAGE_HEADER_DOCK_CLASS = 'launchpad-page-header__dock'

/** The greeting row the controls dock into. */
const GROUND_HEADER_SELECTOR = '.cn-header-widget--ground'

/**
 * Find (or create) the element inside the greeting row that holds the
 * controls. Without a greeting row, or while editing (the row is then a
 * draggable grid item), the controls go into the page title row when the
 * page has one, and float otherwise (null).
 * Removes an actions element that is no longer used, so the row lays out
 * as before when the controls float.
 *
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
 * @param {Element|null} container The dashboard grid container.
 * @param {boolean} isEditMode Whether the dashboard is being edited.
 * @return {HTMLElement|null} The element to teleport the controls into.
 */
export function resolveHeaderActionsTarget(container, isEditMode) {
	if (!container) {
		return null
	}
	const header = container.querySelector(GROUND_HEADER_SELECTOR)
	for (const stale of container.querySelectorAll('.' + HEADER_ACTIONS_CLASS)) {
		if (isEditMode || stale.parentElement !== header) {
			stale.remove()
		}
	}
	if (!header || isEditMode) {
		return container.querySelector('.' + PAGE_HEADER_DOCK_CLASS)
	}
	let target = header.querySelector(':scope > .' + HEADER_ACTIONS_CLASS)
	if (!target) {
		target = document.createElement('div')
		target.className = HEADER_ACTIONS_CLASS
		header.appendChild(target)
	}
	return target
}
