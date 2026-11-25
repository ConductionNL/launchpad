/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Unit tests for `headerSearchField.js` (header-search-dropdown, REQ-SRCH-013):
 * which element the top bar's results list serves, across core's top bar
 * shapes, and that LaunchPad's own field comes out again cleanly.
 *
 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */

import { afterEach, describe, expect, it } from 'vitest'
import {
	fieldWords,
	INLINE_FIELD_CLASS,
	INLINE_HOST_CLASS,
	removeInlineField,
	resolveSearchField,
} from '../headerSearchField.js'

/**
 * Nextcloud 31+'s desktop top bar search: a landmark with only a button.
 *
 * @param {object} [options] Options.
 * @param {string} [options.label] The button's visible words.
 * @param {boolean} [options.mobile] The small-screen variant.
 * @return {HTMLElement} The landmark, attached to the body.
 */
function coreLandmark({ label = 'Search apps, files, tags, messages', mobile = false } = {}) {
	const host = document.createElement('search')
	host.className = mobile ? 'unified-search-input unified-search-input--mobile' : 'unified-search-input'
	const button = document.createElement('button')
	button.className = 'unified-search-input__button'
	button.setAttribute('aria-label', 'Unified search')
	const span = document.createElement('span')
	span.className = 'unified-search-input__label'
	span.textContent = label
	button.appendChild(span)
	host.appendChild(button)
	document.body.appendChild(host)
	return host
}

describe('resolveSearchField', () => {
	afterEach(() => {
		document.body.innerHTML = ''
	})

	it('gives null when nothing matched', () => {
		expect(resolveSearchField(null, 'Search')).toBeNull()
	})

	it('uses a core text field as it is', () => {
		const input = document.createElement('input')
		document.body.appendChild(input)
		expect(resolveSearchField(input, 'Search')).toEqual({ field: input, created: false, label: null })
	})

	it('uses the text field inside a wrapper', () => {
		const wrapper = document.createElement('div')
		const input = document.createElement('input')
		wrapper.appendChild(input)
		expect(resolveSearchField(wrapper, 'Search').field).toBe(input)
	})

	it('places LaunchPad\'s own field in the button\'s place on Nextcloud 31+, with the button\'s words', () => {
		const host = coreLandmark({ label: 'Zoek in Zuiddrecht' })
		const resolved = resolveSearchField(host, 'Search')
		expect(resolved.created).toBe(true)
		expect(resolved.field.tagName).toBe('INPUT')
		expect(resolved.field.type).toBe('search')
		expect(resolved.field.classList.contains(INLINE_FIELD_CLASS)).toBe(true)
		expect(resolved.field.placeholder).toBe('Zoek in Zuiddrecht')
		expect(resolved.field.getAttribute('aria-label')).toBe('Zoek in Zuiddrecht')
		expect(resolved.field.nextElementSibling.className).toBe('unified-search-input__button')
		expect(host.classList.contains(INLINE_HOST_CLASS)).toBe(true)
		expect(resolved.label.textContent).toBe('Zoek in Zuiddrecht')
	})

	it('leaves the small-screen button alone', () => {
		expect(resolveSearchField(coreLandmark({ mobile: true }), 'Search')).toBeNull()
		expect(document.querySelector(`.${INLINE_FIELD_CLASS}`)).toBeNull()
	})

	it('gives null for a shape it does not know', () => {
		const div = document.createElement('div')
		expect(resolveSearchField(div, 'Search')).toBeNull()
	})
})

describe('fieldWords', () => {
	it('prefers the visible label, then the accessible name, then the fallback', () => {
		const button = document.createElement('button')
		expect(fieldWords(button, 'Search')).toBe('Search')
		button.setAttribute('aria-label', 'Unified search')
		expect(fieldWords(button, 'Search')).toBe('Unified search')
		const span = document.createElement('span')
		span.className = 'unified-search-input__label'
		span.textContent = '  Zoeken  '
		button.appendChild(span)
		expect(fieldWords(button, 'Search')).toBe('Zoeken')
		expect(fieldWords(null, 'Search')).toBe('Search')
	})
})

describe('removeInlineField', () => {
	afterEach(() => {
		document.body.innerHTML = ''
	})

	it('takes LaunchPad\'s field out and gives the button back', () => {
		const host = coreLandmark()
		const { field } = resolveSearchField(host, 'Search')
		removeInlineField(field)
		expect(host.querySelector('input')).toBeNull()
		expect(host.classList.contains(INLINE_HOST_CLASS)).toBe(false)
	})

	it('never removes a core field', () => {
		const input = document.createElement('input')
		document.body.appendChild(input)
		removeInlineField(input)
		removeInlineField(null)
		expect(document.body.contains(input)).toBe(true)
	})
})
