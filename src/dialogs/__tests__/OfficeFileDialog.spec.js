/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * integrations-office-tiles REQ-OFT-002: the "New ..." dialog asks for a name
 * and folder, starts from the tile label and /Documents, and only confirms a
 * non-blank name and folder.
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: { name: 'NcDialog', props: ['name', 'open'], template: '<div class="dialog"><slot /><slot name="actions" /></div>' },
	NcButton: { name: 'NcButton', props: ['disabled', 'variant'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label', 'disabled'],
		emits: ['update:modelValue', 'keyup'],
		template: '<input :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" @keyup="$emit(\'keyup\', $event)" />',
	},
}))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))

const { default: OfficeFileDialog } = await import('../OfficeFileDialog.vue')

function mountDialog(props = {}) {
	return mount(OfficeFileDialog, { props: { open: true, title: 'New document', defaultName: 'New document', ...props } })
}

describe('OfficeFileDialog', () => {
	it('starts from the tile label and the Documents folder', () => {
		const inputs = mountDialog().findAll('input')

		expect(inputs[0].element.value).toBe('New document')
		expect(inputs[1].element.value).toBe('/Documents')
	})

	it('confirms the trimmed name and folder', async () => {
		const wrapper = mountDialog()
		const [name, folder] = wrapper.findAll('input')
		await name.setValue('  Notulen ')
		await folder.setValue(' /Projects ')
		await wrapper.findAll('button')[1].trigger('click')

		expect(wrapper.emitted('confirm')).toEqual([[{ name: 'Notulen', folder: '/Projects' }]])
	})

	it('refuses a blank name, also on Enter', async () => {
		const wrapper = mountDialog()
		const name = wrapper.findAll('input')[0]
		await name.setValue('   ')
		await name.trigger('keyup', { key: 'Enter' })

		expect(wrapper.findAll('button')[1].attributes('disabled')).toBeDefined()
		expect(wrapper.emitted('confirm')).toBeUndefined()
	})

	it('confirms nothing while a file is being created', async () => {
		const wrapper = mountDialog({ busy: true })
		wrapper.vm.submit()

		expect(wrapper.emitted('confirm')).toBeUndefined()
	})

	it('cancels by closing', async () => {
		const wrapper = mountDialog()
		await wrapper.findAll('button')[0].trigger('click')

		expect(wrapper.emitted('update:open')).toEqual([[false]])
	})

	it('resets the name and folder when it opens again', async () => {
		const wrapper = mountDialog({ open: false })
		await wrapper.findAll('input')[0].setValue('Other')
		await wrapper.setProps({ open: true })

		expect(wrapper.findAll('input')[0].element.value).toBe('New document')
	})
})
