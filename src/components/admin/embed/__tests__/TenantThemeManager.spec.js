/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The tenant theme manager lists themes, creates and edits them with a live
 * preview, refuses non-:root CSS while typing, shows the server's refusal,
 * deletes (forcing only after warning about tokens) and copies a theme's JSON
 * (embedded-analytics task 16).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TenantThemeManager from '../TenantThemeManager.vue'

const embed = vi.hoisted(() => ({ listThemes: vi.fn(), createTheme: vi.fn(), updateTheme: vi.fn(), deleteTheme: vi.fn() }))
const toasts = vi.hoisted(() => ({ showSuccess: vi.fn() }))
vi.mock('../../../../services/embedApi.js', () => ({ embedApi: embed }))
vi.mock('@nextcloud/dialogs', () => toasts)
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text, n: (_app, one, many, count) => (count === 1 ? one : many).replace('%n', count) }))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

function Field (name) {
  return {
	name,
	props: ['modelValue', 'label', 'error', 'helperText'],
	emits: ['update:modelValue'],
	template: `<label class="${name}">{{ helperText }}<input :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"></label>`,
}
}
const Button = { name: 'NcButton', props: ['type', 'disabled'], emits: ['click'], template: '<button :type="type || \'button\'" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' }
const DialogStub = { name: 'DeleteTenantThemeDialog', props: ['open', 'name', 'tokenCount'], emits: ['update:open', 'confirm'], template: '<div class="delete-stub" />' }

const ZEIST = { id: 'th-1', name: 'Zeist', cssVariables: { '--c-primary': '#0b5394', '--c-surface': '#ffffff' }, logoUrl: 'https://www.zeist.nl/logo.svg', customCss: '', createdAt: '2026-05-22T14:30:00Z', tokenCount: 2 }
const UNUSED = { id: 'th-2', name: 'Leeg', cssVariables: {}, logoUrl: '', customCss: '', tokenCount: 0 }

function mountManager () {
  return mount(TenantThemeManager, {
	global: {
		stubs: {
			NcTextField: Field('text-field'),
			NcTextArea: Field('text-area'),
			NcButton: Button,
			NcNoteCard: { props: ['type'], template: '<div class="note"><slot /></div>' },
			NcLoadingIcon: true,
			DeleteTenantThemeDialog: DialogStub,
		},
	},
})
}

describe('TenantThemeManager', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		embed.listThemes.mockResolvedValue([ZEIST, UNUSED])
	})

	it('lists the themes with their primary colour and token count', async () => {
		const wrapper = mountManager()
		await flushPromises()

		const row = wrapper.find('[data-test="tenant-theme-row-th-1"]')
		expect(row.text()).toContain('Zeist')
		expect(row.text()).toContain('#0b5394')
		expect(row.findAll('td')[4].text()).toBe('2')
	})

	it('creates a theme from the form, dropping empty variables, with a live preview', async () => {
		embed.createTheme.mockResolvedValue({ id: 'th-3' })
		const wrapper = mountManager()
		await flushPromises()

		await wrapper.find('[data-test="tenant-theme-new"]').trigger('click')
		await wrapper.find('[data-test="tenant-theme-name"] input').setValue('Amsterdam')
		await wrapper.find('[data-test="tenant-theme-var--c-primary"] input').setValue('#ec0000')
		await wrapper.find('[data-test="tenant-theme-var--c-border"] input').setValue('')
		expect(wrapper.find('[data-test="tenant-theme-preview"]').attributes('style')).toContain('--preview-primary: #ec0000')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(embed.createTheme).toHaveBeenCalledWith({
			name: 'Amsterdam',
			cssVariables: { '--c-primary': '#ec0000', '--c-surface': '#ffffff', '--c-text': '#222222' },
			logoUrl: '',
			customCss: '',
		})
		expect(embed.listThemes).toHaveBeenCalledTimes(2)
		expect(wrapper.find('form').exists()).toBe(false)
		expect(toasts.showSuccess).toHaveBeenCalledWith('Theme saved.')
	})

	it('edits a theme through the update call and shows a server refusal', async () => {
		embed.updateTheme.mockRejectedValue({ response: { data: { message: 'Primary colour contrast is 2.1:1; at least 4.5:1 is required.' } } })
		const wrapper = mountManager()
		await flushPromises()

		await wrapper.find('[data-test="tenant-theme-edit-th-1"]').trigger('click')
		expect(wrapper.find('[data-test="tenant-theme-name"] input').element.value).toBe('Zeist')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(embed.updateTheme).toHaveBeenCalledWith('th-1', expect.objectContaining({ name: 'Zeist', logoUrl: 'https://www.zeist.nl/logo.svg' }))
		expect(wrapper.find('[data-test="tenant-theme-error"]').text()).toBe('Primary colour contrast is 2.1:1; at least 4.5:1 is required.')
		expect(wrapper.find('form').exists()).toBe(true)
	})

	it('refuses non-:root custom CSS before saving', async () => {
		const wrapper = mountManager()
		await flushPromises()
		await wrapper.find('[data-test="tenant-theme-new"]').trigger('click')
		await wrapper.find('[data-test="tenant-theme-css"] input').setValue('body { color: red; }')

		expect(wrapper.find('[data-test="tenant-theme-css"]').text()).toBe('Custom CSS may only set :root rules.')
		expect(wrapper.find('[data-test="tenant-theme-save"]').attributes('disabled')).toBeDefined()
	})

	it('forces the delete of a theme in use only after the dialog warned, and not of an unused one', async () => {
		embed.deleteTheme.mockResolvedValue({ deleted: true })
		const wrapper = mountManager()
		await flushPromises()

		await wrapper.find('[data-test="tenant-theme-delete-th-1"]').trigger('click')
		expect(wrapper.findComponent(DialogStub).props()).toMatchObject({ name: 'Zeist', tokenCount: 2 })
		wrapper.findComponent(DialogStub).vm.$emit('confirm')
		await flushPromises()
		await wrapper.find('[data-test="tenant-theme-delete-th-2"]').trigger('click')
		wrapper.findComponent(DialogStub).vm.$emit('confirm')
		await flushPromises()

		expect(embed.deleteTheme.mock.calls).toEqual([['th-1', true], ['th-2', false]])
		expect(wrapper.findAll('tbody tr')).toHaveLength(0)
	})

	it('copies a theme as JSON', async () => {
		const writeText = vi.fn().mockResolvedValue()
		Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
		const wrapper = mountManager()
		await flushPromises()

		await wrapper.find('[data-test="tenant-theme-copy-th-1"]').trigger('click')
		await flushPromises()

		expect(JSON.parse(writeText.mock.calls[0][0])).toEqual({ name: 'Zeist', cssVariables: ZEIST.cssVariables, logoUrl: ZEIST.logoUrl, customCss: '' })
	})
})
