/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the launch types of `TileEditor.vue`
 * (launcher-tile-launch-types task 4): the "Opens" picker, the fields each
 * type shows, the template picker with no free address field for single
 * sign-on, and the hint naming the schemes an administrator allowed.
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const initialState = { tileAllowedSchemes: [], ssoLaunchTemplates: [] }

vi.mock('../../services/healthPingClient.js', () => ({ validateHealthPingConfig: vi.fn() }))
vi.mock('../../utils/loadInitialState.js', () => ({ loadInitialState: () => ({ ...initialState }) }))

const { default: TileEditor } = await import('../TileEditor.vue')

const stubs = {
	NcModal: { name: 'NcModal', props: ['show', 'name'], template: '<div><slot /></div>' },
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label', 'helperText'],
		emits: ['update:modelValue'],
		template: '<label class="text-field" :data-label="label"><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /><small>{{ helperText }}</small></label>',
	},
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel'],
		emits: ['update:modelValue'],
		template: '<div class="select" :data-label="inputLabel"><button v-for="o in options" :key="o.value" class="option" :data-value="o.value" @click="$emit(\'update:modelValue\', o.value)">{{ o.label }}</button></div>',
	},
	NcColorPicker: { name: 'NcColorPicker', template: '<div><slot /></div>' },
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue', 'type'],
		emits: ['update:modelValue'],
		template: '<label class="switch" :data-type="type" @click="$emit(\'update:modelValue\', true)"><slot /></label>',
	},
	NcButton: { name: 'NcButton', emits: ['click'], template: '<button class="button" @click="$emit(\'click\')"><slot /></button>' },
	CnIconBrowser: { name: 'CnIconBrowser', template: '<div />' },
}

function mountEditor(tile = null) {
	return mount(TileEditor, { props: { open: true, tile }, global: { stubs } })
}

function labels(wrapper) {
	return wrapper.findAll('.text-field').map((f) => f.attributes('data-label'))
}

async function pick(wrapper, selectLabel, value) {
	await wrapper.find(`.select[data-label="${selectLabel}"] .option[data-value="${value}"]`).trigger('click')
}

describe('TileEditor launch types', () => {
	const plainT = globalThis.t

	beforeEach(() => {
		initialState.tileAllowedSchemes = []
		initialState.ssoLaunchTemplates = []
		// Script-level t() with the placeholders filled in, so the hint's schemes can be read.
		globalThis.t = (_app, key, vars) => key.replace(/\{(\w+)\}/g, (match, name) => (vars && name in vars ? String(vars[name]) : match))
	})

	afterEach(() => {
		globalThis.t = plainT
	})

	it('offers the four things a tile opens, labelled for the picker', () => {
		const options = mountEditor().find('.select[data-label="Opens"]').findAll('.option').map((o) => o.text())
		expect(options).toEqual(['Web address', 'Program on this computer', 'Remote desktop', 'Single sign-on app'])
	})

	it('keeps an older app tile selectable under its own label', () => {
		const options = mountEditor({ id: 1, title: 'Files', linkType: 'app', linkValue: 'files' })
			.find('.select[data-label="Opens"]').findAll('.option')
		expect(options[0].attributes('data-value')).toBe('app')
		expect(options[0].text()).toBe('Nextcloud app')
		expect(labels(mountEditor({ id: 1, title: 'Files', linkType: 'app', linkValue: 'files' }))).toContain('URL')
	})

	it('asks a program tile for its address and names the allowed schemes', async () => {
		initialState.tileAllowedSchemes = ['ms-word', 'vscode']
		const wrapper = mountEditor()
		await pick(wrapper, 'Opens', 'program')

		expect(labels(wrapper)).toContain('Program address')
		expect(labels(wrapper)).not.toContain('URL')
		expect(wrapper.find('.text-field[data-label="Program address"] small').text()).toBe('Allowed schemes: ms-word, vscode')
	})

	it('says when no scheme is allowed yet', async () => {
		const wrapper = mountEditor()
		await pick(wrapper, 'Opens', 'program')
		expect(wrapper.find('.text-field[data-label="Program address"] small').text())
			.toBe('No program schemes are allowed yet. An administrator allows them on the LaunchPad admin page.')
	})

	it('shows the gateway address, or the RDP fields, for a remote desktop', async () => {
		const wrapper = mountEditor()
		await pick(wrapper, 'Opens', 'remote-desktop')
		expect(labels(wrapper)).toContain('Gateway address')
		expect(labels(wrapper)).not.toContain('Computer or server')

		await wrapper.find('[data-testid="tile-remote-rdp"]').trigger('click')

		expect(labels(wrapper)).toEqual(expect.arrayContaining(['Computer or server', 'Port', 'Published program (optional)', 'Remote desktop gateway (optional)']))
		expect(labels(wrapper)).not.toContain('Gateway address')
		expect(wrapper.vm.form.remoteMode).toBe('rdp')
	})

	it('lets an sso author pick a template and enter an app id, with no address field', async () => {
		initialState.ssoLaunchTemplates = [{ key: 'entra', name: 'Microsoft Entra ID' }]
		const wrapper = mountEditor()
		await pick(wrapper, 'Opens', 'sso')

		expect(labels(wrapper)).toEqual(expect.arrayContaining(['Title', 'App id']))
		expect(labels(wrapper)).not.toContain('URL')
		expect(wrapper.find('.select[data-label="Sign-on template"]').findAll('.option').map((o) => o.text())).toEqual(['Microsoft Entra ID'])

		await pick(wrapper, 'Sign-on template', 'entra')
		await wrapper.find('.text-field[data-label="App id"] input').setValue('a1b2c3')
		await wrapper.findAll('button.button').find((b) => b.text() === 'Save').trigger('click')

		expect(wrapper.emitted('save')[0][0]).toMatchObject({ linkType: 'sso', ssoTemplate: 'entra', ssoAppId: 'a1b2c3' })
	})

	it('says when there are no sign-on templates', async () => {
		const wrapper = mountEditor()
		await pick(wrapper, 'Opens', 'sso')
		expect(wrapper.text()).toContain('No sign-on templates yet. An administrator adds them on the LaunchPad admin page.')
	})

	it('clears an address the server resolved when the type changes', async () => {
		const wrapper = mountEditor({ id: 4, title: 'Salaris', linkType: 'sso', linkValue: 'https://launcher.example/a1', ssoTemplate: 'entra', ssoAppId: 'a1' })
		await pick(wrapper, 'Opens', 'url')
		expect(wrapper.vm.form.linkValue).toBe('')
		expect(wrapper.vm.form.linkType).toBe('url')
	})
})
