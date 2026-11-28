/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * LanguagesTab: editing and saving a variant's name and description,
 * deleting a non-primary variant, the load failure, every refusal message,
 * adding a blank variant, and doing nothing without a chosen language.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LanguagesTab from '../LanguagesTab.vue'
import { api } from '../../../../services/api.js'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
	getLanguage: () => 'en',
}))
vi.mock('../../../../services/api.js', () => ({
	api: {
		listTranslations: vi.fn(),
		createTranslation: vi.fn(),
		updateTranslation: vi.fn(),
		deleteTranslation: vi.fn(),
		setPrimaryTranslation: vi.fn(),
	},
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcButton: {
		name: 'NcButton',
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template:
			'<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
	NcSelect: {
		name: 'NcSelect',
		props: ['modelValue', 'options', 'inputLabel'],
		emits: ['update:modelValue'],
		template:
			'<select :aria-label="inputLabel" @change="$emit(\'update:modelValue\', options[$event.target.selectedIndex])"><option v-for="o in options" :key="o.code">{{ o.name }}</option></select>',
	},
}))

const en = {
	id: 1,
	dashboardUuid: 'intra',
	languageCode: 'en',
	name: 'Intranet',
	description: 'News',
	widgetTreeJson: null,
	isPrimary: 1,
}
const nl = {
	id: 2,
	dashboardUuid: 'intra',
	languageCode: 'nl',
	name: 'Intranet',
	description: 'News',
	widgetTreeJson: null,
	isPrimary: 0,
}

/**
 * A refused request carrying an error code.
 *
 * @param {string} error The error code.
 * @return {Error} The rejection.
 */
function refused(error) {
	return Object.assign(new Error('x'), { response: { data: { error } } })
}

/**
 * Mount the tab and let it load.
 *
 * @return {Promise<object>} The wrapper.
 */
async function loaded() {
	const wrapper = mount(LanguagesTab, { props: { dashboardUuid: 'intra' } })
	await flushPromises()
	return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('LanguagesTab editing', () => {
	it('saves the edited name and description of a variant', async () => {
		api.listTranslations.mockResolvedValue({ data: { translations: [en, nl] } })
		api.updateTranslation.mockResolvedValue({ data: {} })
		const wrapper = await loaded()
		const [, , nlName, nlDescription] = wrapper.findAll('input')

		await nlName.setValue('Intranet NL')
		await nlDescription.setValue('Nieuws')
		await wrapper.findAll('[data-testid="language-save"]')[1].trigger('click')
		await flushPromises()

		expect(api.updateTranslation).toHaveBeenCalledWith('intra', 'nl', { name: 'Intranet NL', description: 'Nieuws' })
		expect(api.listTranslations).toHaveBeenCalledTimes(2)
	})

	it('deletes a non-primary variant and offers no delete for the primary', async () => {
		api.listTranslations.mockResolvedValue({ data: { translations: [en, { ...nl, isPrimary: false }] } })
		api.deleteTranslation.mockResolvedValue({ data: {} })
		const wrapper = await loaded()

		expect(wrapper.findAll('[data-testid="language-delete"]')).toHaveLength(1)
		await wrapper.find('[data-testid="language-delete"]').trigger('click')
		await flushPromises()

		expect(api.deleteTranslation).toHaveBeenCalledWith('intra', 'nl')
	})

	it('says when the language versions could not be loaded', async () => {
		api.listTranslations.mockRejectedValue(new Error('down'))
		const wrapper = await loaded()

		expect(wrapper.find('[data-testid="languages-error"]').text()).toBe('The language versions could not be loaded.')
		expect(wrapper.findAll('[data-testid="language-row"]')).toHaveLength(0)
	})

	it('treats a response without a list as no variants', async () => {
		api.listTranslations.mockResolvedValue({ data: {} })
		const wrapper = await loaded()

		expect(wrapper.findAll('[data-testid="language-row"]')).toHaveLength(0)
	})

	it('explains each refusal of a change', async () => {
		api.listTranslations.mockResolvedValue({ data: { translations: [en, nl] } })
		const wrapper = await loaded()
		const cases = [
			['language_exists', 'This dashboard already has that language.'],
			['last_variant', 'A dashboard keeps at least one language.'],
			['something_else', 'The change could not be saved.'],
		]

		for (const [code, message] of cases) {
			api.updateTranslation.mockRejectedValueOnce(refused(code))
			await wrapper.find('[data-testid="language-save"]').trigger('click')
			await flushPromises()
			expect(wrapper.find('[data-testid="languages-error"]').text()).toBe(message)
		}

		api.setPrimaryTranslation.mockRejectedValueOnce(new Error('network'))
		await wrapper.find('[data-testid="language-make-primary"]').trigger('click')
		await flushPromises()
		expect(wrapper.find('[data-testid="languages-error"]').text()).toBe('The change could not be saved.')
	})

	it('adds nothing without a chosen language, and a blank variant without a source', async () => {
		api.listTranslations.mockResolvedValue({ data: { translations: [en] } })
		api.createTranslation.mockRejectedValueOnce(refused('language_exists'))
		const wrapper = await loaded()

		await wrapper.find('[data-testid="language-add"]').trigger('click')
		await flushPromises()
		expect(api.createTranslation).not.toHaveBeenCalled()

		wrapper.vm.newLanguage = { code: 'de' }
		wrapper.vm.copyFrom = null
		await wrapper.vm.add()
		expect(api.createTranslation).toHaveBeenCalledWith('intra', { languageCode: 'de' })
		expect(wrapper.vm.error).toBe('This dashboard already has that language.')
	})
})
