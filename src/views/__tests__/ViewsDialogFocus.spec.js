/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Closing the widget picker or the widget form never leaves focus on the page
 * body (REQ-WDG-013 "focus MUST return to the element that triggered the open").
 */

import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views

/**
 * A host with the real Views methods.
 *
 * @return {object} host
 */
function makeHost() {
	const host = {}
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	return host
}

/**
 * Put the More menu button on the page.
 *
 * @return {HTMLElement} the button.
 */
function moreMenuButton() {
	const menu = document.createElement('div')
	menu.setAttribute('data-testid', 'launchpad-more-menu')
	menu.innerHTML = '<button data-testid="cn-grouped-menu-trigger">More</button>'
	document.body.appendChild(menu)
	return menu.querySelector('button')
}

/**
 * Let the restore's task run.
 *
 * @return {Promise<void>}
 */
function settle() {
	return new Promise((resolve) => setTimeout(resolve, 5))
}

beforeEach(async () => {
	setActivePinia(createPinia())
	Views = (await import('../Views.vue')).default
})

afterEach(() => {
	document.body.innerHTML = ''
})

describe('Views widget dialog focus', () => {
	it('a widget form closed before its focus trap ran gives focus to the More menu button', async () => {
		const button = moreMenuButton()
		const pickerItem = document.createElement('button')
		document.body.appendChild(pickerItem)
		pickerItem.focus()
		const host = makeHost()
		host.widgetDialogOpener = document.activeElement
		host.isCustomWidgetModalOpen = true
		pickerItem.remove()

		host.closeCustomWidgetModal()
		await settle()

		expect(host.isCustomWidgetModalOpen).toBe(false)
		expect(document.activeElement).toBe(button)
	})

	it('a dismissed widget picker gives focus back to the More menu button', async () => {
		const button = moreMenuButton()
		const host = makeHost()
		host.widgetDialogOpener = null
		host.isWidgetModalOpen = true

		host.onWidgetPickerClose()
		await settle()

		expect(host.isWidgetModalOpen).toBe(false)
		expect(document.activeElement).toBe(button)
	})
})
