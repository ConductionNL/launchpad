/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The App or link tile form (app-link-tile-widget REQ-APPTILE-004, -005):
 * the choice An app / A link, adding several apps at once, the text colour
 * with its contrast line and warning, and the brand defaults.
 *
 * @spec openspec/changes/app-link-tile-widget/specs/app-tile-widget/spec.md
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('../../services/healthPingClient.js', () => ({ validateHealthPingConfig: vi.fn(), fetchHealthPingBadge: vi.fn() }))
vi.mock('../../services/iframeClient.js', () => ({ validateIframeUrl: vi.fn().mockResolvedValue({ valid: true }) }))
vi.mock('../../utils/loadInitialState.js', () => ({ loadInitialState: () => ({}), isOnOfficeNetwork: () => false }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	imagePath: (app, file) => `/apps/${app}/img/${file}`,
}))

const { default: TileEditor } = await import('../TileEditor.vue')

const stubs = {
	NcModal: { name: 'NcModal', props: ['show', 'name'], template: '<div><slot /></div>' },
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<label class="text-field" :data-label="label"><input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" /></label>',
	},
	NcSelect: { name: 'NcSelect', template: '<div />' },
	NcColorPicker: { name: 'NcColorPicker', template: '<div><slot /></div>' },
	NcCheckboxRadioSwitch: {
		name: 'NcCheckboxRadioSwitch',
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<label class="switch" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></label>',
	},
	NcButton: { name: 'NcButton', props: ['disabled'], emits: ['click'], template: '<button class="button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
	CnIconBrowser: { name: 'CnIconBrowser', template: '<div />' },
}

const APPS = [
	{ appId: 'zaaksysteem', name: 'Zaaksysteem', href: '/apps/zaaksysteem/', count: 14 },
	{ appId: 'calendar', name: 'Agenda', href: '/apps/calendar/' },
]

function mountEditor(tile = null, props = {}) {
	return mount(TileEditor, { props: { open: true, tile, discoveredApps: APPS, ...props }, global: { stubs } })
}

const saveButton = (wrapper) => wrapper.find('[data-testid="tile-editor-save"]')

describe('TileEditor as the App or link tile form', () => {
	beforeEach(() => {
		globalThis.t = (_app, key, vars) => key.replace(/\{(\w+)\}/g, (match, name) => (vars && name in vars ? String(vars[name]) : match))
	})

	it('starts with the choice An app or A link, a link chosen', () => {
		const wrapper = mountEditor()
		const cards = wrapper.find('[data-testid="tile-mode"]').findAll('.choice-cards__card')
		expect(cards.map((card) => card.find('.choice-cards__title').text())).toEqual(['An app', 'A link'])
		expect(wrapper.find('[data-testid="tile-mode"] input[value="link"]').element.checked).toBe(true)
		expect(wrapper.find('[data-testid="tile-app-chooser"]').exists()).toBe(false)
	})

	it('adds two apps at once with the appearance chosen', async () => {
		const wrapper = mountEditor()
		await wrapper.find('[data-testid="tile-mode"] input[value="app"]').setValue(true)
		expect(wrapper.find('[data-testid="tile-app-chooser"]').exists()).toBe(true)
		expect(wrapper.find('.text-field[data-label="Web address"]').exists()).toBe(false)
		expect(saveButton(wrapper).element.disabled).toBe(true)

		await wrapper.find('[data-testid="tile-app-tick-app:zaaksysteem"]').setValue(true)
		await wrapper.find('[data-testid="tile-app-tick-app:calendar"]').setValue(true)
		await wrapper.find('[data-test="tile-editor-swatch--sc-accent"]').trigger('change')
		expect(saveButton(wrapper).text()).toBe('Add 2 tiles')
		await saveButton(wrapper).trigger('click')

		const [{ choices, appearance }] = wrapper.emitted('addApps')[0]
		expect(choices.map((choice) => choice.tile.appId)).toEqual(['zaaksysteem', 'calendar'])
		expect(appearance).toEqual({ colourToken: '--sc-accent', textColour: 'white', showCount: true, pinned: false, iconOnly: false })
		expect(wrapper.emitted('save')).toBeUndefined()
	})

	it('saves a link tile with its title, address and text colour', async () => {
		const wrapper = mountEditor()
		await wrapper.find('.text-field[data-label="Name"] input').setValue('Intranet')
		await wrapper.find('.text-field[data-label="Web address"] input').setValue('https://intranet.zuiddrecht.nl')
		await wrapper.find('[data-testid="tile-editor-text-black"]').trigger('change')
		await saveButton(wrapper).trigger('click')

		expect(wrapper.emitted('save')[0][0]).toMatchObject({ mode: 'link', title: 'Intranet', linkValue: 'https://intranet.zuiddrecht.nl', textColour: 'black' })
	})

	it('keeps a placed tile in its mode', async () => {
		const wrapper = mountEditor({ id: 3, title: 'Intranet', linkType: 'url', linkValue: 'https://a.test' })
		expect(wrapper.find('[data-testid="tile-mode"] input[value="app"]').element.disabled).toBe(true)
		wrapper.vm.chooseMode('app')
		expect(wrapper.vm.form.mode).toBe('link')
	})

	it('offers ten text colours, white chosen, and says the contrast meets WCAG AA', () => {
		const wrapper = mountEditor()
		expect(wrapper.findAll('[data-testid="tile-editor-text-colours"] input')).toHaveLength(10)
		expect(wrapper.find('[data-testid="tile-editor-text-white"]').element.checked).toBe(true)
		wrapper.vm.form.colourToken = '--sc-primary'
		return wrapper.vm.$nextTick().then(() => {
			const line = wrapper.find('[data-testid="tile-editor-contrast"]')
			expect(line.text()).toBe('White on primary: contrast 5.6:1, meets WCAG AA (4.5:1)')
			expect(line.classes()).not.toContain('tile-editor__warning')
		})
	})

	it('warns for black on a dark tile and keeps Save available', async () => {
		const wrapper = mountEditor()
		await wrapper.find('[data-test="tile-editor-swatch--sc-work-text"]').trigger('change')
		await wrapper.find('[data-testid="tile-editor-text-black"]').trigger('change')

		const line = wrapper.find('[data-testid="tile-editor-contrast"]')
		expect(line.text()).toContain('below WCAG AA (4.5:1). Choose white or a lighter tile colour.')
		expect(line.attributes('role')).toBe('alert')
		expect(line.find('svg, .material-design-icon').exists()).toBe(true)
		expect(saveButton(wrapper).element.disabled).toBe(false)
	})

	it('reads a stored text colour and defaults to white', async () => {
		const wrapper = mountEditor({ id: 4, title: 'Z', textColour: '--sc-primary-deep' })
		expect(wrapper.vm.form.textColour).toBe('--sc-primary-deep')
		await wrapper.setProps({ tile: { id: 5, title: 'Y', textColour: 'purple' } })
		expect(wrapper.vm.form.textColour).toBe('white')
	})

	it('gives the Nextcloud site the white logo on #0082C9 with white text', async () => {
		const wrapper = mountEditor()
		await wrapper.find('.text-field[data-label="Web address"] input').setValue('https://nextcloud.com')

		expect(wrapper.vm.form).toMatchObject({ icon: '/apps/launchpad/img/nextcloud-white.svg', iconType: 'url', backgroundColor: '#0082C9', colourToken: '', textColour: 'white' })
		expect(wrapper.find('[data-testid="tile-editor-brand"]').text()).toBe('The Nextcloud tile shows its white logo, without payoff, on the brand colour.')

		await wrapper.find('.text-field[data-label="Web address"] input').setValue('https://intranet.test')
		expect(wrapper.find('[data-testid="tile-editor-brand"]').exists()).toBe(false)
	})

	it('hands a bookmarks file on to the bookmark import', async () => {
		const wrapper = mountEditor()
		await wrapper.find('[data-testid="tile-mode"] input[value="app"]').setValue(true)
		const file = new File(['<dl></dl>'], 'bookmarks.html')
		wrapper.findComponent({ name: 'TileAppChooser' }).vm.$emit('importBookmarks', file)
		expect(wrapper.emitted('importBookmarks')).toEqual([[file]])
	})

	// add-widget-dialog-with-miniatures REQ-WDG-040: inside Add widget.
	it('draws the form without a dialog of its own when embedded', async () => {
		const wrapper = mountEditor(null, { embedded: true })
		expect(wrapper.find('[data-testid="tile-editor-embedded"]').exists()).toBe(true)
		expect(wrapper.findComponent({ name: 'NcModal' }).exists()).toBe(false)
		expect(wrapper.find('[data-testid="tile-mode"]').exists()).toBe(true)
		await wrapper.findAll('button.button').find((b) => b.text() === 'Cancel').trigger('click')
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('is a dialog of its own otherwise', () => {
		const modal = mountEditor().findComponent({ name: 'NcModal' })
		expect(modal.exists()).toBe(true)
		modal.vm.$emit('update:show', false)
		expect(mountEditor().find('[data-testid="tile-editor-embedded"]').exists()).toBe(false)
	})

	// Boards LpTegelToevoegen / LpTegelBewerken: the Appearance panel.
	it('shows the Appearance panel with a preview, and checks an app tile without a colour on the primary colour', async () => {
		const wrapper = mountEditor()
		await wrapper.find('[data-testid="tile-mode"] input[value="app"]').setValue(true)
		await wrapper.find('[data-testid="tile-app-tick-app:zaaksysteem"]').setValue(true)
		const panel = wrapper.find('[data-testid="tile-editor-appearance"]')
		expect(panel.find('[data-testid="tile-editor-preview"]').text()).toContain('Zaaksysteem')
		expect(panel.find('[data-testid="tile-editor-contrast"]').text()).toBe('White on primary: contrast 5.6:1, meets WCAG AA (4.5:1)')
		expect(saveButton(wrapper).text()).toBe('Add tile')
	})

	it('leaves out its own heading when embedded', () => {
		expect(mountEditor(null, { embedded: true }).find('.tile-editor__heading').exists()).toBe(false)
		expect(mountEditor().find('.tile-editor__heading').exists()).toBe(true)
	})
})
