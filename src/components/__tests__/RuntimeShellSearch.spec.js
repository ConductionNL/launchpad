/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `RuntimeShellSearch.vue` (tile-quick-search:
 * REQ-QSEARCH-001..004). Covers the DOM/keyboard-event binding layer —
 * filter/rank/selection/fallback logic itself is covered exhaustively in
 * `useTileSearch.spec.js`; these tests assert the component wires that
 * composable to the right DOM structure, ARIA attributes, and emitted
 * events.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import RuntimeShellSearch from '../RuntimeShellSearch.vue'

const ITEMS = [
	{ id: 'z1', label: 'Zaaksysteem' },
	{ id: 'z2', label: 'Zaakbrowser' },
	{ id: 'v1', label: 'Verlof aanvragen' },
]

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

/**
 * @param {object} [props] prop overrides.
 * @return {import('@vue/test-utils').Wrapper}
 */
function mountSearch(props = {}) {
	return mount(RuntimeShellSearch, {
		attachTo: document.body,
		propsData: {
			items: ITEMS,
			fallbackTarget: 'none',
			...props,
		},
	})
}

describe('RuntimeShellSearch', () => {
	let wrappers = []

	afterEach(() => {
		wrappers.forEach((w) => w.unmount())
		wrappers = []
	})

	function track(wrapper) {
		wrappers.push(wrapper)
		return wrapper
	}

	describe('REQ-QSEARCH-001: labelled search bar', () => {
		it('wraps the input in role="search" with an accessible label', () => {
			const wrapper = track(mountSearch())
			expect(wrapper.find('[role="search"]').exists()).toBe(true)
			const input = wrapper.find('input')
			const label = wrapper.find(`label[for="${input.attributes('id')}"]`)
			expect(label.exists()).toBe(true)
			expect(label.text().length).toBeGreaterThan(0)
		})

		it('exposes the combobox role + aria-autocomplete on the input', () => {
			const wrapper = track(mountSearch())
			const input = wrapper.find('input')
			expect(input.attributes('role')).toBe('combobox')
			expect(input.attributes('aria-autocomplete')).toBe('list')
		})
	})

	describe('REQ-QSEARCH-002: live client-side filtering', () => {
		it('filters as the user types and emits the matching ids', async () => {
			const wrapper = track(mountSearch())
			const input = wrapper.find('input')
			await input.setValue('zaak')

			const options = wrapper.findAll('[data-test="quick-search-option"]')
			expect(options).toHaveLength(2)
			expect(options.at(0).text()).toContain('Zaaksysteem')
			expect(options.at(1).text()).toContain('Zaakbrowser')

			const filterEvents = wrapper.emitted('filter')
			expect(filterEvents[filterEvents.length - 1][0]).toEqual(['z1', 'z2'])
		})

		it('is case-insensitive', async () => {
			const wrapper = track(mountSearch())
			await wrapper.find('input').setValue('ZAAK')
			expect(
				wrapper.findAll('[data-test="quick-search-option"]'),
			).toHaveLength(2)
		})

		it('emits filter(null) when the query is cleared back to empty by typing', async () => {
			const wrapper = track(mountSearch())
			const input = wrapper.find('input')
			await input.setValue('zaak')
			await input.setValue('')
			const filterEvents = wrapper.emitted('filter')
			expect(filterEvents[filterEvents.length - 1][0]).toBeNull()
		})

		it('emits an empty match array (not null) when the query matches nothing', async () => {
			const wrapper = track(mountSearch())
			await wrapper.find('input').setValue('nonexistent')
			const filterEvents = wrapper.emitted('filter')
			expect(filterEvents[filterEvents.length - 1][0]).toEqual([])
		})

		it('re-filters when the items prop changes (e.g. dashboard switch)', async () => {
			const wrapper = track(mountSearch())
			await wrapper.find('input').setValue('zaak')
			expect(
				wrapper.findAll('[data-test="quick-search-option"]'),
			).toHaveLength(2)

			await wrapper.setProps({ items: [{ id: 'other', label: 'Weer' }] })
			expect(
				wrapper.findAll('[data-test="quick-search-option"]'),
			).toHaveLength(0)
		})
	})

	describe('REQ-QSEARCH-003: keyboard selection + activation', () => {
		it('ArrowDown/ArrowUp move aria-activedescendant with wrap-around', async () => {
			const wrapper = track(mountSearch())
			const input = wrapper.find('input')
			await input.setValue('za') // 2 matches

			const options = wrapper.findAll('[data-test="quick-search-option"]')
			expect(input.attributes('aria-activedescendant')).toBe(
				options.at(0).attributes('id'),
			)

			await input.trigger('keydown', { key: 'ArrowDown' })
			expect(input.attributes('aria-activedescendant')).toBe(
				options.at(1).attributes('id'),
			)

			await input.trigger('keydown', { key: 'ArrowDown' })
			expect(input.attributes('aria-activedescendant')).toBe(
				options.at(0).attributes('id'),
			)
		})

		it('the active option is marked aria-selected and carries a non-colour marker', async () => {
			const wrapper = track(mountSearch())
			await wrapper.find('input').setValue('za')
			const options = wrapper.findAll('[data-test="quick-search-option"]')
			expect(options.at(0).attributes('aria-selected')).toBe('true')
			expect(options.at(1).attributes('aria-selected')).toBe('false')
			// The active option's check-mark icon is not visibility:hidden.
			expect(
				options
					.at(0)
					.find('.runtime-shell-search__option-marker--hidden')
					.exists(),
			).toBe(false)
			expect(
				options
					.at(1)
					.find('.runtime-shell-search__option-marker--hidden')
					.exists(),
			).toBe(true)
		})

		it('Enter opens the active match and emits "open" with its raw item', async () => {
			const wrapper = track(mountSearch())
			const input = wrapper.find('input')
			await input.setValue('verlof')
			await input.trigger('keydown', { key: 'Enter' })
			expect(wrapper.emitted('open')).toBeTruthy()
			expect(wrapper.emitted('open')[0][0]).toEqual(ITEMS[2])
		})

		it('Enter opens whichever match arrow keys most recently selected', async () => {
			const wrapper = track(mountSearch())
			const input = wrapper.find('input')
			await input.setValue('za')
			await input.trigger('keydown', { key: 'ArrowDown' })
			await input.trigger('keydown', { key: 'Enter' })
			expect(wrapper.emitted('open')[0][0]).toEqual(ITEMS[1])
		})

		it('clicking an option selects and opens it', async () => {
			const wrapper = track(mountSearch())
			await wrapper.find('input').setValue('za')
			const options = wrapper.findAll('[data-test="quick-search-option"]')
			await options.at(1).trigger('mousedown')
			expect(wrapper.emitted('open')[0][0]).toEqual(ITEMS[1])
		})

		it('Escape clears the query, undims the grid, and emits clear', async () => {
			const wrapper = track(mountSearch())
			const input = wrapper.find('input')
			await input.setValue('zaak')
			await input.trigger('keydown', { key: 'Escape' })

			expect(input.element.value).toBe('')
			expect(
				wrapper.findAll('[data-test="quick-search-option"]'),
			).toHaveLength(0)
			expect(wrapper.emitted('clear')).toBeTruthy()
			const filterEvents = wrapper.emitted('filter')
			expect(filterEvents[filterEvents.length - 1][0]).toBeNull()
		})
	})

	describe('REQ-QSEARCH-004: no-match fallback', () => {
		it('emits a "fallback" unified-search action when configured and Enter is pressed with zero matches', async () => {
			const wrapper = track(mountSearch({ fallbackTarget: 'unified-search' }))
			const input = wrapper.find('input')
			await input.setValue('nonexistent')
			await input.trigger('keydown', { key: 'Enter' })
			expect(wrapper.emitted('fallback')[0][0]).toEqual({
				type: 'unified-search',
				query: 'nonexistent',
			})
			expect(wrapper.emitted('open')).toBeFalsy()
		})

		it('emits a "fallback" web-search action with the query URL-encoded', async () => {
			const wrapper = track(
				mountSearch({ fallbackTarget: 'https://duckduckgo.com/?q={query}' }),
			)
			const input = wrapper.find('input')
			await input.setValue('hello world')
			await input.trigger('keydown', { key: 'Enter' })
			expect(wrapper.emitted('fallback')[0][0]).toEqual({
				type: 'web-search',
				url: 'https://duckduckgo.com/?q=hello%20world',
			})
		})

		it('shows an accessible no-results message when no fallback is configured', async () => {
			const wrapper = track(mountSearch({ fallbackTarget: 'none' }))
			await wrapper.find('input').setValue('nonexistent')
			const message = wrapper.find('[data-test="quick-search-empty"]')
			expect(message.exists()).toBe(true)
			expect(message.text().length).toBeGreaterThan(0)

			await wrapper.find('input').trigger('keydown', { key: 'Enter' })
			expect(wrapper.emitted('fallback')[0][0]).toEqual({ type: 'none' })
		})

		it('announces the result count via the aria-live status region', async () => {
			const wrapper = track(mountSearch())
			await wrapper.find('input').setValue('za')
			const status = wrapper.find('[data-test="quick-search-status"]')
			expect(status.attributes('aria-live')).toBe('polite')
			expect(status.text().length).toBeGreaterThan(0)
		})
	})

	describe('global "/" and Ctrl+K shortcuts', () => {
		it('"/" focuses the input when focus is outside any text field', async () => {
			const wrapper = track(mountSearch())
			const outside = document.createElement('div')
			document.body.appendChild(outside)
			outside.tabIndex = -1
			outside.focus()

			// Dispatched on the actually-focused element (not window
			// directly) so `event.target` reflects reality — a real keydown
			// fires on the focused element and bubbles up to `window`.
			const event = new KeyboardEvent('keydown', {
				key: '/',
				bubbles: true,
				cancelable: true,
			})
			const preventDefault = vi.spyOn(event, 'preventDefault')
			outside.dispatchEvent(event)
			await wrapper.vm.$nextTick()

			expect(document.activeElement).toBe(wrapper.find('input').element)
			expect(preventDefault).toHaveBeenCalled()
			outside.remove()
		})

		it('"/" does NOT hijack typing in an unrelated text field', async () => {
			const wrapper = track(mountSearch())
			const otherInput = document.createElement('input')
			document.body.appendChild(otherInput)
			otherInput.focus()

			const event = new KeyboardEvent('keydown', {
				key: '/',
				bubbles: true,
				cancelable: true,
			})
			otherInput.dispatchEvent(event)
			await wrapper.vm.$nextTick()

			expect(document.activeElement).toBe(otherInput)
			otherInput.remove()
		})

		it('Ctrl+K focuses the input and prevents the browser default', async () => {
			const wrapper = track(mountSearch())
			const event = new KeyboardEvent('keydown', {
				key: 'k',
				ctrlKey: true,
				bubbles: true,
				cancelable: true,
			})
			const preventDefault = vi.spyOn(event, 'preventDefault')
			document.body.dispatchEvent(event)
			await wrapper.vm.$nextTick()

			expect(document.activeElement).toBe(wrapper.find('input').element)
			expect(preventDefault).toHaveBeenCalled()
		})

		it('Ctrl+K focuses the input even while another text field has focus', async () => {
			const wrapper = track(mountSearch())
			const otherInput = document.createElement('input')
			document.body.appendChild(otherInput)
			otherInput.focus()

			const event = new KeyboardEvent('keydown', {
				key: 'k',
				ctrlKey: true,
				bubbles: true,
				cancelable: true,
			})
			otherInput.dispatchEvent(event)
			await wrapper.vm.$nextTick()

			expect(document.activeElement).toBe(wrapper.find('input').element)
			otherInput.remove()
		})

		it('removes the window keydown listener on destroy', () => {
			const wrapper = mountSearch()
			const removeSpy = vi.spyOn(window, 'removeEventListener')
			wrapper.unmount()
			expect(removeSpy).toHaveBeenCalledWith('keydown', expect.any(Function))
			removeSpy.mockRestore()
		})
	})
})
