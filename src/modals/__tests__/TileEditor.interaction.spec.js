/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `TileEditor.vue` driven through its rendered form:
 * every field writes back into the form, the health-ping fields appear with
 * the switch, and the modal and action buttons emit what the parent acts on.
 *
 * The Nextcloud form controls are replaced by minimal stand-ins that render
 * their slot and emit the same events, so the template's own bindings run.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import TileEditor from '../TileEditor.vue'
import { validateHealthPingConfig } from '../../services/healthPingClient.js'

vi.mock('../../services/healthPingClient.js', () => ({
	validateHealthPingConfig: vi.fn(),
}))

const stubs = {
	NcModal: {
		name: 'NcModal',
		props: ['show', 'name'],
		emits: ['close', 'update:show'],
		template: '<div class="nc-modal-stub"><slot /></div>',
	},
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue', 'blur'],
		template: '<input class="text-field" :data-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" @blur="$emit(\'blur\')" />',
	},
	NcColorPicker: {
		name: 'NcColorPicker',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<div class="color-picker"><slot /></div>',
	},
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<label class="switch"><slot /></label>',
	},
	NcButton: {
		name: 'NcButton',
		emits: ['click'],
		template: '<button class="button" @click="$emit(\'click\')"><slot /></button>',
	},
	CnIconBrowser: { name: 'CnIconBrowser', template: '<div class="icon-browser" />' },
}

function mountEditor(tile = null) {
	return mount(TileEditor, { propsData: { open: true, tile }, stubs })
}

function field(wrapper, label) {
	return wrapper.find(`input.text-field[data-label="${label}"]`)
}

function button(wrapper, text) {
	return wrapper.findAll('button.button').find((b) => b.text() === text)
}

describe('TileEditor form bindings', () => {
	it('titles the editor by whether a tile is being edited', () => {
		expect(mountEditor().find('h2').text()).toBe('Create Tile')
		expect(mountEditor({ id: 1, title: 'Files' }).find('h2').text()).toBe('Edit Tile')
	})

	it('writes the title and URL fields back and shows the title in the preview', async () => {
		const wrapper = mountEditor()

		await field(wrapper, 'Title').setValue('Bestanden')
		await field(wrapper, 'URL').setValue('/apps/files')

		expect(wrapper.vm.form.title).toBe('Bestanden')
		expect(wrapper.vm.form.linkValue).toBe('/apps/files')
		expect(wrapper.find('.tile-preview__title').text()).toBe('Bestanden')
	})

	it('writes both colours back into the form and the preview', async () => {
		const wrapper = mountEditor()
		const [background, text] = wrapper.findAllComponents({ name: 'NcColorPicker' })

		background.vm.$emit('update:modelValue', '#123456')
		text.vm.$emit('update:modelValue', '#abcdef')
		await flushPromises()

		expect(wrapper.vm.form.backgroundColor).toBe('#123456')
		expect(wrapper.vm.form.textColor).toBe('#abcdef')
		const style = wrapper.find('.tile-preview').attributes('style')
		expect(style).toContain('background-color: rgb(18, 52, 86)')
		expect(style).toContain('color: rgb(171, 205, 239)')
	})
})

describe('TileEditor health-ping fields', () => {
	it('shows the health fields only once the switch is on, and writes them back', async () => {
		const wrapper = mountEditor()
		expect(field(wrapper, 'Health check URL').exists()).toBe(false)

		wrapper.findComponent({ name: 'NcCheckboxRadioSwitch' }).vm.$emit('update:modelValue', true)
		await flushPromises()

		await field(wrapper, 'Health check URL').setValue('https://status.example.org')
		await field(wrapper, 'Expected HTTP status').setValue('204')
		await field(wrapper, 'Check interval (seconds)').setValue('30')

		expect(wrapper.vm.form.healthPingEnabled).toBe(true)
		expect(wrapper.vm.form.healthUrl).toBe('https://status.example.org')
		expect(wrapper.vm.form.expectedStatus).toBe('204')
		expect(wrapper.vm.form.pingInterval).toBe('30')
	})

	it('checks the URL on blur, shows a refusal, and clears it while typing', async () => {
		validateHealthPingConfig.mockResolvedValue({ valid: false, errors: ['host_not_allowed'] })
		const wrapper = mountEditor({ id: 1, title: 'T', healthPingEnabled: true, healthUrl: 'https://blocked.example' })

		await field(wrapper, 'Health check URL').trigger('blur')
		await flushPromises()
		expect(wrapper.find('.tile-editor__warning').text()).toBe('This host is not on the allow-list.')

		await field(wrapper, 'Health check URL').setValue('https://other.example')
		expect(wrapper.find('.tile-editor__warning').exists()).toBe(false)
	})
})

describe('TileEditor actions', () => {
	it('offers Delete only for an existing tile and emits delete', async () => {
		expect(button(mountEditor(), 'Delete')).toBeUndefined()

		const wrapper = mountEditor({ id: 1, title: 'T' })
		await button(wrapper, 'Delete').trigger('click')

		expect(wrapper.emitted('delete')).toHaveLength(1)
	})

	it('emits close from Cancel and save with the normalised form from Save', async () => {
		const wrapper = mountEditor()
		await field(wrapper, 'Title').setValue('Mail')

		await button(wrapper, 'Cancel').trigger('click')
		await button(wrapper, 'Save').trigger('click')

		expect(wrapper.emitted('close')).toHaveLength(1)
		expect(wrapper.emitted('save')[0][0]).toMatchObject({ title: 'Mail', expectedStatus: 200, pingInterval: 60 })
	})

	it('forwards the modal closing as close, and ignores it opening', async () => {
		const wrapper = mountEditor()
		const modal = wrapper.findComponent({ name: 'NcModal' })

		modal.vm.$emit('update:show', true)
		expect(wrapper.emitted('close')).toBeUndefined()

		modal.vm.$emit('update:show', false)
		modal.vm.$emit('close')
		expect(wrapper.emitted('close')).toHaveLength(2)
		expect(modal.props('name')).toBe('Create Tile')
	})
})
