/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ChoiceCards: one choice as cards, a radiogroup of native radios.
 *
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ChoiceCards from '../ChoiceCards.vue'

const options = [
	{ value: 'a', label: 'First', hint: 'The first one.' },
	{ value: 'b', label: 'Second' },
	{ value: 'c', label: 'Third', disabled: true },
]

/**
 * Mount the cards.
 *
 * @param {string} modelValue The chosen value.
 * @return {object} The wrapper.
 */
function mountCards(modelValue = '') {
	return mount(ChoiceCards, { props: { label: 'Kind', name: 'kind', modelValue, options, columns: 3 } })
}

describe('ChoiceCards', () => {
	it('is a radiogroup named by its label, one radio per card', () => {
		const wrapper = mountCards()
		const group = wrapper.find('[role="radiogroup"]')
		const labelId = group.attributes('aria-labelledby')
		expect(wrapper.find(`#${labelId}`).text()).toBe('Kind')
		expect(wrapper.findAll('input[type="radio"]').map((i) => i.attributes('name'))).toEqual(['kind', 'kind', 'kind'])
		expect(wrapper.find('.choice-cards__grid--3').exists()).toBe(true)
	})

	it('shows each title and its line, marks the chosen card and checks its radio', () => {
		const wrapper = mountCards('b')
		const cards = wrapper.findAll('.choice-cards__card')
		expect(cards[0].text()).toContain('First')
		expect(cards[0].find('.choice-cards__hint').text()).toBe('The first one.')
		expect(cards[1].find('.choice-cards__hint').exists()).toBe(false)
		expect(cards[1].classes()).toContain('choice-cards__card--chosen')
		expect(cards[1].find('input').element.checked).toBe(true)
		expect(cards[0].find('input').element.checked).toBe(false)
	})

	it('emits the value of the card chosen, and a disabled card cannot be chosen', async () => {
		const wrapper = mountCards('a')
		await wrapper.find('input[value="b"]').setValue(true)
		expect(wrapper.emitted('update:modelValue')).toEqual([['b']])

		const third = wrapper.find('input[value="c"]')
		expect(third.element.disabled).toBe(true)
		expect(wrapper.findAll('.choice-cards__card')[2].classes()).toContain('choice-cards__card--disabled')
	})

	it('checks no card when none is chosen', () => {
		expect(mountCards('').findAll('input').some((i) => i.element.checked)).toBe(false)
	})
})
