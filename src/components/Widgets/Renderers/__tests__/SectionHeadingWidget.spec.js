/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The section heading widget ("Tussenkop", REQ-WDG-031): its renderer, its
 * form, its registration and its full-width size.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	t: (_app, text) => text,
	translate: (_app, text) => text,
}))
vi.mock('@nextcloud/vue', () => ({
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label', 'helperText', 'required'],
		emits: ['update:modelValue'],
		template: '<input :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
}))

import SectionHeadingWidget from '../SectionHeadingWidget.vue'
import SectionHeadingWidgetForm from '../SectionHeadingWidgetForm.vue'
import { defaultSizeFor, ONE_ROW_TYPES } from '../../../../composables/useGridManager.js'

describe('SectionHeadingWidget', () => {
	it('draws the title as a heading and the explanation below it', () => {
		const wrapper = mount(SectionHeadingWidget, {
			props: { content: { title: 'Samenwerken', description: 'De tegels van het team' } },
		})
		expect(wrapper.find('h2').text()).toBe('Samenwerken')
		expect(wrapper.find('p').text()).toBe('De tegels van het team')
	})

	it('leaves the explanation out when there is none, and shows a placeholder without a title', () => {
		const wrapper = mount(SectionHeadingWidget, { props: { content: { title: '  ' } } })
		expect(wrapper.find('h2').text()).toBe('Section heading')
		expect(wrapper.find('p').exists()).toBe(false)
	})
})

describe('SectionHeadingWidgetForm', () => {
	it('emits the trimmed title and explanation', async () => {
		const wrapper = mount(SectionHeadingWidgetForm, { props: { editingWidget: null } })
		await wrapper.find('[data-testid="section-heading-form-title"]').setValue(' Wegwijs ')
		await wrapper.find('[data-testid="section-heading-form-description"]').setValue('Snel naar de vaste plekken')
		expect(wrapper.emitted('update:content').at(-1)[0]).toEqual({ title: 'Wegwijs', description: 'Snel naar de vaste plekken' })
	})

	it('starts from the placement being edited, and needs a title', () => {
		const editing = mount(SectionHeadingWidgetForm, {
			props: { editingWidget: { content: { title: 'Collega\'s', description: 'Wie nieuw is' } } },
		})
		expect(editing.vm.validate()).toEqual([])
		expect(mount(SectionHeadingWidgetForm, { props: { editingWidget: null } }).vm.validate()).toEqual(['Give the heading a title'])
	})
})

describe('section-heading placement', () => {
	it('spans the grid\'s full width, one row tall', () => {
		expect(defaultSizeFor('section-heading', 12)).toEqual({ w: 12, h: 1 })
		expect(defaultSizeFor('section-heading', 8)).toEqual({ w: 8, h: 1 })
		expect(ONE_ROW_TYPES).toContain('section-heading')
	})
})
