/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * PhoneHiddenNotice: on a phone, the notice names the hidden widgets and
 * "Which?" explains why (launchpad-mobile-remote-access REQ-MRA-007).
 *
 * @spec openspec/changes/phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => String(vars[k])),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcNoteCard: { name: 'NcNoteCard', template: '<div><slot /></div>' },
	NcButton: { name: 'NcButton', emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
}))

import PhoneHiddenNotice from '../PhoneHiddenNotice.vue'

describe('PhoneHiddenNotice', () => {
	it('shows nothing when no widget is hidden', () => {
		const wrapper = mount(PhoneHiddenNotice, { props: { titles: [] } })
		expect(wrapper.find('[data-testid="phone-hidden-notice"]').exists()).toBe(false)
	})

	it('names the hidden widgets with a Which? link and no desktop layout link', () => {
		const wrapper = mount(PhoneHiddenNotice, { props: { titles: ['Snel beginnen (Word)', 'Assistent'] } })
		expect(wrapper.find('[data-testid="phone-hidden-sentence"]').text())
			.toBe('2 widgets do not work on a phone and are hidden: Snel beginnen (Word) and Assistent.')
		expect(wrapper.find('[data-testid="phone-hidden-which"]').text()).toBe('Which?')
		expect(wrapper.text()).not.toMatch(/desktop/i)
	})

	it('explains on Which? and hides the explanation again', async () => {
		const wrapper = mount(PhoneHiddenNotice, { props: { titles: ['Assistent'] } })
		expect(wrapper.find('[data-testid="phone-hidden-why"]').exists()).toBe(false)
		await wrapper.find('[data-testid="phone-hidden-which"]').trigger('click')
		expect(wrapper.find('[data-testid="phone-hidden-why"]').text()).toContain('larger screen')
		await wrapper.find('[data-testid="phone-hidden-which"]').trigger('click')
		expect(wrapper.find('[data-testid="phone-hidden-why"]').exists()).toBe(false)
	})

	it('names three of five and counts the rest', () => {
		const wrapper = mount(PhoneHiddenNotice, { props: { titles: ['A', 'B', 'C', 'D', 'E'] } })
		expect(wrapper.text()).toContain('A, B, C and 2 more')
	})
})
