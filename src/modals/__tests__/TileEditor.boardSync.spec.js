/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The tile form as board LpTegelBewerken draws it (tile-form-board-sync):
 * the heading and where line, the fields in order, the group and its sort
 * order, the last availability check and the icon upload.
 *
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import TileEditor from '../TileEditor.vue'
import { fetchHealthPingBadge } from '../../services/healthPingClient.js'
import { uploadFile } from '../../services/resourceService.js'

vi.mock('../../services/healthPingClient.js', () => ({ validateHealthPingConfig: vi.fn(), fetchHealthPingBadge: vi.fn() }))
vi.mock('../../services/resourceService.js', () => ({ uploadFile: vi.fn() }))
vi.mock('../../services/iframeClient.js', () => ({ validateIframeUrl: vi.fn().mockResolvedValue({ valid: true, errors: [] }) }))

const stubs = {
	NcModal: { name: 'NcModal', props: ['show', 'name'], template: '<div class="modal" :data-name="name"><slot /></div>' },
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label', 'disabled', 'helperText'],
		emits: ['update:modelValue'],
		template: '<input class="text-field field" :data-label="label" :disabled="disabled" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel', 'disabled'],
		emits: ['update:modelValue'],
		template: '<div class="select field" :data-label="inputLabel" :data-disabled="disabled ? \'yes\' : \'no\'" :data-value="modelValue"><button v-for="o in options" :key="o.value" class="option" :data-value="o.value" @click="$emit(\'update:modelValue\', o.value)">{{ o.label }}</button></div>',
	},
	NcColorPicker: { name: 'NcColorPicker', template: '<div class="color-picker"><slot /></div>' },
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue', 'type'],
		emits: ['update:modelValue'],
		template: '<label class="switch field" :data-label="type === \'switch\' ? \'switch\' : \'\'" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></label>',
	},
	NcButton: { name: 'NcButton', props: ['disabled'], emits: ['click'], template: '<button class="button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
	CnIconBrowser: { name: 'CnIconBrowser', template: '<div class="icon-browser" />' },
}

const caseSystem = { id: 7, title: 'Case system', linkType: 'sso', linkValue: '', linkTarget: 'new-tab', ssoTemplate: 'entra', ssoAppId: 'z1' }

/**
 * Mount the editor.
 *
 * @param {object|null} tile The tile.
 * @param {object} [props] More props.
 * @return {object} The wrapper.
 */
function mountEditor(tile, props = {}) {
	return mount(TileEditor, { props: { open: true, tile, dashboardName: 'My workspace', ...props }, global: { stubs } })
}

/**
 * A button by its text.
 *
 * @param {object} wrapper The wrapper.
 * @param {string} text The text.
 * @return {object|undefined} The button.
 */
function button(wrapper, text) {
	return wrapper.findAll('button.button').find((b) => b.text() === text)
}

describe('TileEditor as LpTegelBewerken draws it', () => {
	const plainT = globalThis.t

	beforeEach(() => {
		globalThis.t = (_app, key, vars) => key.replace(/\{(\w+)\}/g, (match, name) => (vars && name in vars ? String(vars[name]) : match))
		fetchHealthPingBadge.mockReset()
		uploadFile.mockReset()
	})

	afterEach(() => {
		globalThis.t = plainT
	})

	it('titles the form Edit tile: <name> under <group> · <dashboard>', () => {
		const wrapper = mountEditor(caseSystem, { groupName: 'Work apps' })
		expect(wrapper.find('h2').text()).toBe('Edit tile: Case system')
		expect(wrapper.find('.modal').attributes('data-name')).toBe('Edit tile: Case system')
		expect(wrapper.find('[data-testid="tile-editor-where"]').text()).toBe('Work apps · My workspace')
		expect(mountEditor(caseSystem).find('[data-testid="tile-editor-where"]').text()).toBe('No group · My workspace')
	})

	it('shows the fields in the board\'s order, with App with single sign-on and New tab chosen', () => {
		const wrapper = mountEditor({ ...caseSystem, linkType: 'url', linkValue: 'https://zaak.example' })
		const html = wrapper.html()
		const order = [
			'data-label="Name"',
			'data-label="Group"',
			'data-testid="tile-link-type"',
			'data-label="Web address"',
			'data-label="Internal address (optional)"',
			'data-test="tile-editor-opens-in"',
			'tile-editor__icon',
			'Colour',
			'data-label="Sort order in this group"',
			'tile-editor__health-ping',
			'Delete tile',
		].map((needle) => html.indexOf(needle))
		expect(order.every((at) => at >= 0)).toBe(true)
		expect([...order].sort((a, b) => a - b)).toEqual(order)

		const sso = mountEditor(caseSystem)
		expect(sso.find('[data-testid="tile-link-type"] input[value="sso"]').element.checked).toBe(true)
		expect(sso.find('[data-test="tile-editor-opens-in"] input[value="new-tab"]').element.checked).toBe(true)
	})

	it('puts Delete tile at the left and Cancel and Save at the right, and only for a saved tile', () => {
		const actions = mountEditor(caseSystem).find('.tile-editor__actions')
		expect(actions.element.firstElementChild.textContent.trim()).toBe('Delete tile')
		expect(actions.find('.tile-editor__actions-right').findAll('button').map((b) => b.text())).toEqual(['Cancel', 'Save'])
		expect(button(mountEditor(null), 'Delete tile')).toBeUndefined()
	})

	it('shows the group and the group\'s sort order, and saves the order with the tile', async () => {
		const wrapper = mountEditor(caseSystem, { groupName: 'Work apps', groupSort: 'manual' })
		expect(wrapper.find('.text-field[data-label="Group"]').element.value).toBe('Work apps')
		const sort = wrapper.find('.select[data-label="Sort order in this group"]')
		expect(sort.attributes('data-disabled')).toBe('no')
		expect(sort.attributes('data-value')).toBe('manual')
		expect(sort.findAll('.option').map((o) => o.text())).toEqual(['Last used first', 'Alphabetical', 'Random', 'As placed'])

		await sort.find('.option[data-value="last-used"]').trigger('click')
		await button(wrapper, 'Save').trigger('click')
		expect(wrapper.emitted('save')[0][0].groupSortBy).toBe('last-used')
	})

	it('keeps a most-used group as it is, and offers no sort order outside a group', async () => {
		const most = mountEditor(caseSystem, { groupName: 'Work apps', groupSort: 'most-used' })
		expect(most.find('.select[data-label="Sort order in this group"] .option[data-value="most-used"]').text()).toBe('Most used first')

		const loose = mountEditor(caseSystem)
		expect(loose.find('.text-field[data-label="Group"]').element.value).toBe('No group')
		expect(loose.find('.select[data-label="Sort order in this group"]').attributes('data-disabled')).toBe('yes')
		await button(loose, 'Save').trigger('click')
		expect(loose.emitted('save')[0][0]).not.toHaveProperty('groupSortBy')
	})

	it('reads the last check under Check availability', async () => {
		const at = new Date()
		at.setHours(10, 40, 0, 0)
		fetchHealthPingBadge.mockResolvedValue({ state: 'online', checkedAt: at.toISOString() })
		const wrapper = mountEditor({ ...caseSystem, healthPingEnabled: true, healthUrl: 'https://zaak.example/health' })
		await flushPromises()

		expect(fetchHealthPingBadge).toHaveBeenCalledWith(7)
		expect(wrapper.find('.tile-editor__health-ping').text()).toContain('Check availability')
		expect(wrapper.find('[data-testid="tile-last-check"]').text()).toBe('Reachable at the last check, today 10:40')
	})

	it('reads the last check once the switch is turned on, and says not checked yet when the read fails', async () => {
		fetchHealthPingBadge.mockRejectedValue(new Error('404'))
		const wrapper = mountEditor(caseSystem)
		await flushPromises()
		expect(fetchHealthPingBadge).not.toHaveBeenCalled()

		await wrapper.find('.tile-editor__health-ping .switch').trigger('click')
		await flushPromises()
		expect(fetchHealthPingBadge).toHaveBeenCalledWith(7)
		expect(wrapper.find('[data-testid="tile-last-check"]').text()).toBe('Not checked yet')
	})

	it('shows no last check for a tile that is not saved yet', async () => {
		const wrapper = mountEditor(null)
		await wrapper.find('.tile-editor__health-ping .switch').trigger('click')
		await flushPromises()
		expect(fetchHealthPingBadge).not.toHaveBeenCalled()
		expect(wrapper.find('[data-testid="tile-last-check"]').exists()).toBe(false)
	})

	it('opens the icon list on demand and stores an uploaded icon as its URL', async () => {
		const wrapper = mountEditor(caseSystem)
		expect(wrapper.find('.icon-browser').exists()).toBe(false)
		await wrapper.find('[data-testid="tile-icon-choose"]').trigger('click')
		expect(wrapper.find('.icon-browser').exists()).toBe(true)

		uploadFile.mockResolvedValue({ url: '/apps/launchpad/resource/zaak.png', name: 'zaak.png', size: 10 })
		const input = wrapper.find('input[type="file"]')
		const file = new File(['x'], 'zaak.png', { type: 'image/png' })
		Object.defineProperty(input.element, 'files', { value: [file] })
		await input.trigger('change')
		await flushPromises()

		expect(uploadFile).toHaveBeenCalledWith(file)
		expect(wrapper.vm.form.icon).toBe('/apps/launchpad/resource/zaak.png')
		expect(wrapper.vm.form.iconType).toBe('url')
	})

	it('says so when the icon upload fails, and keeps the icon', async () => {
		uploadFile.mockRejectedValue(new Error('file_too_large'))
		const wrapper = mountEditor({ ...caseSystem, icon: 'M0 0h24v24H0z', iconType: 'svg' })
		const input = wrapper.find('input[type="file"]')
		Object.defineProperty(input.element, 'files', { value: [new File(['x'], 'big.png', { type: 'image/png' })] })
		await input.trigger('change')
		await flushPromises()

		expect(wrapper.find('[role="alert"]').text()).toBe('The icon could not be uploaded.')
		expect(wrapper.vm.form.icon).toBe('M0 0h24v24H0z')
	})

	it('opens the file chooser from Upload another icon', async () => {
		const wrapper = mountEditor(caseSystem)
		const click = vi.spyOn(wrapper.find('input[type="file"]').element, 'click').mockImplementation(() => {})
		await button(wrapper, 'Upload another icon').trigger('click')
		expect(click).toHaveBeenCalled()
	})
})
