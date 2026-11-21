/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `dialogReturnFocus` (REQ-WDG-013).
 */

import { afterEach, describe, expect, it } from 'vitest'
import { dialogReturnFocus } from '../returnFocus.js'

afterEach(() => {
	document.body.innerHTML = ''
})

/**
 * Put the More menu button on the page.
 *
 * @return {HTMLElement} the button.
 */
function moreMenuButton() {
	document.body.innerHTML = '<div data-testid="launchpad-more-menu"><button data-testid="cn-grouped-menu-trigger">More</button></div>'
	return document.querySelector('button')
}

describe('dialogReturnFocus', () => {
	it('returns to the element that opened the dialog while it is on the page', () => {
		moreMenuButton()
		const cog = document.createElement('button')
		document.body.appendChild(cog)

		expect(dialogReturnFocus(cog)).toBe(cog)
	})

	it('falls back to the More menu button when the opener is gone or was the body', () => {
		const button = moreMenuButton()
		const removedMenuItem = document.createElement('button')

		expect(dialogReturnFocus(removedMenuItem)).toBe(button)
		expect(dialogReturnFocus(document.body)).toBe(button)
		expect(dialogReturnFocus(null)).toBe(button)
	})

	it('leaves focus alone when there is no More menu', () => {
		expect(dialogReturnFocus(null)).toBe(false)
	})
})
