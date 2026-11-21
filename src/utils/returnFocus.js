/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Where focus goes when a dialog closes (REQ-WDG-013).
 */

/** The dashboard's More menu button. */
export const MORE_MENU_TRIGGER = '[data-testid="launchpad-more-menu"] [data-testid="cn-grouped-menu-trigger"]'

/**
 * The element a closing dialog returns focus to: the element that had focus
 * when it opened while that is still on the page, else the More menu button.
 * A dialog opened from the More menu was opened by an item of a menu that has
 * closed since, so focus goes back to the button that opened the menu.
 *
 * @param {Element|null|undefined} previous The element focused when the dialog opened.
 * @param {Document} doc The document to look in.
 * @return {Element|false} The element, or false to leave focus where it is.
 * @spec openspec/specs/widgets/spec.md
 */
export function dialogReturnFocus(previous, doc = document) {
	if (previous && previous.isConnected && previous !== doc.body) {
		return previous
	}
	return doc.querySelector(MORE_MENU_TRIGGER) ?? false
}
