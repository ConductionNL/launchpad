/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The tile editor's "Open in" choice (launcher-tile-open-behaviour D1, D4),
 * as three choice cards (tile-form-board-sync REQ-TILE-040).
 *
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-tile-form-as-lptegelbewerken-draws-it-req-tile-040
 * @spec openspec/changes/archive/2026-11-19-launcher-tile-open-behaviour/specs/tiles/spec.md
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TileEditor from '../TileEditor.vue'
import { validateIframeUrl } from '../../services/iframeClient.js'

vi.mock('../../services/healthPingClient.js', () => ({ validateHealthPingConfig: vi.fn() }))
vi.mock('../../services/iframeClient.js', () => ({ validateIframeUrl: vi.fn() }))

const stubs = {
	NcModal: { name: 'NcModal', template: '<div><slot /></div>' },
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input class="text-field" :data-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
	},
	NcColorPicker: { name: 'NcColorPicker', template: '<div><slot /></div>' },
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue', 'value', 'disabled', 'type'],
		emits: ['update:modelValue'],
		template: '<label class="radio" :data-value="value" :data-disabled="disabled ? \'yes\' : \'no\'" @click="$emit(\'update:modelValue\', value)"><slot /></label>',
	},
	NcButton: { name: 'NcButton', emits: ['click'], template: '<button class="button" @click="$emit(\'click\')"><slot /></button>' },
	CnIconBrowser: { name: 'CnIconBrowser', template: '<div />' },
}

/**
 * Mount the editor.
 *
 * @param {object|null} tile The tile, or null for a new one.
 * @return {object} The wrapper.
 */
function mountEditor(tile = null) {
	return mount(TileEditor, { propsData: { open: true, tile }, stubs })
}

/**
 * The radio for a value.
 *
 * @param {object} wrapper The wrapper.
 * @param {string} value The link target, '' for the dashboard.
 * @return {object} The radio.
 */
function radio(wrapper, value) {
	return wrapper.find(`[data-test="tile-editor-opens-in"] .choice-cards__card[data-value="${value}"]`)
}

/**
 * Whether a card's radio is disabled.
 *
 * @param {object} wrapper The wrapper.
 * @param {string} value The link target.
 * @return {boolean}
 */
function disabled(wrapper, value) {
	return radio(wrapper, value).find('input').element.disabled
}

describe('TileEditor opens in', () => {
	beforeEach(() => {
		validateIframeUrl.mockReset()
	})

	it('offers the three targets as cards, none chosen follows the dashboard, and saves the chosen one', async () => {
		const wrapper = mountEditor({ id: 1, title: 'Files', linkType: 'app', linkValue: 'files' })

		expect(wrapper.find('[data-test="tile-editor-opens-in"] [role="radiogroup"]').exists()).toBe(true)
		expect(wrapper.findAll('[data-test="tile-editor-opens-in"] .choice-cards__card').map((r) => r.attributes('data-value')))
			.toEqual(['same-tab', 'new-tab', 'side-panel'])
		expect(wrapper.findAll('[data-test="tile-editor-opens-in"] .choice-cards__title').map((r) => r.text()))
			.toEqual(['Same tab', 'New tab', 'In Launchpad, next to the tiles'])
		expect(wrapper.vm.form.linkTarget).toBe('')
		expect(wrapper.find('[data-test="tile-editor-opens-in-default"]').text()).toContain('as the dashboard decides')

		await radio(wrapper, 'side-panel').find('input').setValue(true)
		wrapper.vm.saveTile()

		expect(wrapper.emitted('save')[0][0].linkTarget).toBe('side-panel')
		expect(validateIframeUrl).not.toHaveBeenCalled()
	})

	it('goes back to the dashboard\'s choice from the button under the cards', async () => {
		const wrapper = mountEditor({ id: 5, title: 'T', linkType: 'url', linkValue: '', linkTarget: 'new-tab' })
		expect(radio(wrapper, 'new-tab').find('input').element.checked).toBe(true)

		await wrapper.find('[data-test="tile-editor-follow-dashboard"]').trigger('click')

		expect(wrapper.vm.form.linkTarget).toBe('')
		expect(wrapper.findAll('[data-test="tile-editor-opens-in"] input').some((i) => i.element.checked)).toBe(false)
	})

	it('seeds the choice from the tile', () => {
		expect(mountEditor({ id: 2, title: 'T', linkType: 'url', linkValue: '', linkTarget: 'new-tab' }).vm.form.linkTarget).toBe('new-tab')
		expect(mountEditor(null).vm.form.linkTarget).toBe('')
	})

	it('greys out the panel with a reason for an outside host not on the allow-list, and drops that choice', async () => {
		validateIframeUrl.mockResolvedValue({ valid: false, errors: ['host_not_allowed'] })
		const wrapper = mountEditor({ id: 3, title: 'Ext', linkType: 'url', linkValue: '', linkTarget: 'side-panel' })

		await wrapper.find('input.text-field[data-label="Web address"]').setValue('https://blocked.example/page')
		await flushPromises()

		expect(validateIframeUrl).toHaveBeenCalledWith({ url: 'https://blocked.example/page' })
		expect(disabled(wrapper, 'side-panel')).toBe(true)
		expect(wrapper.find('[data-test="tile-editor-panel-refusal"]').text()).toContain('cannot open in the panel')
		expect(wrapper.vm.form.linkTarget).toBe('')
	})

	it('keeps the panel for an allowed outside host', async () => {
		validateIframeUrl.mockResolvedValue({ valid: true, errors: [] })
		const wrapper = mountEditor({ id: 4, title: 'Ok', linkType: 'url', linkValue: '', linkTarget: 'side-panel' })

		await wrapper.find('input.text-field[data-label="Web address"]').setValue('https://ok.example')
		await flushPromises()

		expect(disabled(wrapper, 'side-panel')).toBe(false)
		expect(wrapper.find('[data-test="tile-editor-panel-refusal"]').exists()).toBe(false)
		expect(wrapper.vm.form.linkTarget).toBe('side-panel')
	})
})
