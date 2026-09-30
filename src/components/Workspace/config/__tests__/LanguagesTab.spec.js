/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * LanguagesTab (REQ-LANGUI-001): list, add blank or copied, make primary,
 * and the refusals. Shapes are DashboardTranslationApiController's
 * (`{translations: [DashboardTranslation::jsonSerialize]}`, 409
 * `language_exists`, 400 `last_variant` / `primary_variant`).
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

beforeEach(() => vi.clearAllMocks())

describe('LanguagesTab', () => {
	it('REQ-LANGUI-001: adds Dutch copied from English', async () => {
		api.listTranslations.mockResolvedValueOnce({ data: { translations: [en] } })
		api.listTranslations.mockResolvedValue({ data: { translations: [en, nl] } })
		api.createTranslation.mockResolvedValue({ data: nl })
		const wrapper = mount(LanguagesTab, { props: { dashboardUuid: 'intra' } })
		await flushPromises()
		const [language, from] = wrapper.findAll('select')
		language.element.selectedIndex = language
			.findAll('option')
			.findIndex((o) => o.text() === 'Dutch')
		await language.trigger('change')
		from.element.selectedIndex = 1
		await from.trigger('change')
		await wrapper.find('[data-testid="language-add"]').trigger('click')
		await flushPromises()
		expect(api.createTranslation).toHaveBeenCalledWith('intra', {
			languageCode: 'nl',
			copyFrom: 'en',
		})
		expect(wrapper.findAll('[data-testid="language-row"]')).toHaveLength(2)
	})

	it('REQ-LANGUI-001: makes Dutch primary', async () => {
		api.listTranslations.mockResolvedValue({ data: { translations: [en, nl] } })
		api.setPrimaryTranslation.mockResolvedValue({ data: {} })
		const wrapper = mount(LanguagesTab, { props: { dashboardUuid: 'intra' } })
		await flushPromises()
		expect(wrapper.findAll('[data-testid="language-row"]')[0].text()).toContain(
			'Primary',
		)
		await wrapper.find('[data-testid="language-make-primary"]').trigger('click')
		await flushPromises()
		expect(api.setPrimaryTranslation).toHaveBeenCalledWith('intra', 'nl')
	})

	it('shows the refusal to delete the primary', async () => {
		api.listTranslations.mockResolvedValue({ data: { translations: [en, nl] } })
		api.deleteTranslation.mockRejectedValue(
			Object.assign(new Error('x'), {
				response: {
					status: 400,
					data: {
						status: 'error',
						error: 'primary_variant',
						message: 'x',
					},
				},
			}),
		)
		const wrapper = mount(LanguagesTab, { props: { dashboardUuid: 'intra' } })
		await flushPromises()
		await wrapper.find('[data-testid="language-delete"]').trigger('click')
		await flushPromises()
		expect(wrapper.find('[data-testid="languages-error"]').text()).toContain(
			'Make another language primary',
		)
	})
})
