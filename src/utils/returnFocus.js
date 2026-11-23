/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Where focus goes when a dialog closes (REQ-WDG-013).
 */

/** The dashboard's More menu button. */
export const MORE_MENU_TRIGGER = '[data-testid="launchpad-more-menu"] [data-testid="launchpad-more-menu-trigger"]'

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

/**
 * Put focus back after a dialog closed when nothing else did. A dialog closed
 * before its open transition ended never set up its focus trap, so nothing
 * returns focus and it falls to the page body. Call it once the dialog has
 * left the DOM; it waits one task so a trap that did run returns focus first.
 *
 * @param {Element|null|undefined} previous The element focused when the dialog opened.
 * @param {Document} doc The document to look in.
 * @return {Promise<void>} Resolves once focus was checked.
 * @spec openspec/specs/widgets/spec.md
 */
export function restoreLostFocus(previous, doc = document) {
	return new Promise((resolve) => {
		setTimeout(() => {
			const active = doc.activeElement
			if (!active || active === doc.body) {
				const target = dialogReturnFocus(previous, doc)
				if (target && typeof target.focus === 'function') {
					target.focus()
				}
			}
			resolve()
		}, 0)
	})
}
