/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `dialogReturnFocus` (REQ-WDG-013).
 */

import { afterEach, describe, expect, it } from 'vitest'
import { dialogReturnFocus, restoreLostFocus } from '../returnFocus.js'

afterEach(() => {
	document.body.innerHTML = ''
})

/**
 * Put the More menu button on the page.
 *
 * @return {HTMLElement} the button.
 */
function moreMenuButton() {
	document.body.innerHTML = '<div data-testid="launchpad-more-menu"><button data-testid="launchpad-more-menu-trigger">More</button></div>'
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

describe('restoreLostFocus', () => {
	it('moves focus that fell to the page to the More menu button when the opener is gone', async () => {
		const button = moreMenuButton()
		const removedPickerItem = document.createElement('button')
		document.activeElement.blur()

		await restoreLostFocus(removedPickerItem)

		expect(document.activeElement).toBe(button)
	})

	it('moves focus that fell to the page back to an opener still on the page', async () => {
		moreMenuButton()
		const cog = document.createElement('button')
		document.body.appendChild(cog)

		await restoreLostFocus(cog)

		expect(document.activeElement).toBe(cog)
	})

	it('leaves focus where a focus trap or the user already put it', async () => {
		const button = moreMenuButton()
		const field = document.createElement('input')
		document.body.appendChild(field)
		field.focus()

		await restoreLostFocus(null)

		expect(document.activeElement).toBe(field)
		expect(document.activeElement).not.toBe(button)
	})
})
