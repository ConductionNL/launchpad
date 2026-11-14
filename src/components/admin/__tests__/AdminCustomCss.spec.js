/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `AdminCustomCss.vue` (platform-dashboard-theme-and-css
 * REQ-THM-003): loading the stored CSS, saving it, and showing what the
 * server's cleaning removed.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AdminCustomCss from '../AdminCustomCss.vue'
import { api } from '../../../services/api.js'

vi.mock('@nextcloud/l10n', () => {
	const t = (_app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (_m, name) => vars[name] ?? `{${name}}`)
	return { t, translate: t }
})
vi.mock('../../../services/api.js', () => ({
	api: {
		getCustomCss: vi.fn(),
		updateCustomCss: vi.fn(),
	},
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const NcButtonStub = { name: 'NcButton', props: ['disabled'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' }

function mountSection() {
	return mount(AdminCustomCss, { global: { stubs: { NcButton: NcButtonStub } } })
}

beforeEach(() => {
	vi.clearAllMocks()
	api.getCustomCss.mockResolvedValue({ data: { customCss: '.launchpad-workspace p {\n  margin: 0;\n}' } })
})

describe('AdminCustomCss', () => {
	it('shows the stored CSS in a labelled field', async () => {
		const wrapper = mountSection()
		await flushPromises()

		const input = wrapper.find('[data-testid="admin-custom-css-input"]')
		expect(input.element.value).toBe('.launchpad-workspace p {\n  margin: 0;\n}')
		expect(wrapper.find(`label[for="${input.attributes('id')}"]`).exists()).toBe(true)
	})

	it('saves what the administrator typed and says what was removed (the spec\'s "Import dropped")', async () => {
		api.updateCustomCss.mockResolvedValue({
			data: { customCss: '.launchpad-workspace .tile-widget__title {\n  font-weight: 700;\n}', removed: ['@import'] },
		})
		const wrapper = mountSection()
		await flushPromises()

		const typed = '@import url(https://evil.example/x.css); .tile-widget__title { font-weight: 700; }'
		await wrapper.find('[data-testid="admin-custom-css-input"]').setValue(typed)
		await wrapper.find('[data-testid="admin-custom-css-save"]').trigger('click')
		await flushPromises()

		expect(api.updateCustomCss).toHaveBeenCalledWith(typed)
		expect(wrapper.find('[data-testid="admin-custom-css-removed"]').text()).toBe('Removed: @import')
		expect(wrapper.find('[data-testid="admin-custom-css-input"]').element.value)
			.toBe('.launchpad-workspace .tile-widget__title {\n  font-weight: 700;\n}')
		expect(wrapper.find('[data-testid="admin-custom-css-saved"]').exists()).toBe(false)
	})

	it('confirms a save that removed nothing', async () => {
		api.updateCustomCss.mockResolvedValue({ data: { customCss: '', removed: [] } })
		const wrapper = mountSection()
		await flushPromises()

		await wrapper.find('[data-testid="admin-custom-css-save"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="admin-custom-css-saved"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="admin-custom-css-removed"]').exists()).toBe(false)
	})

	it('shows the server\'s refusal, such as the size cap', async () => {
		api.updateCustomCss.mockRejectedValue({ response: { data: { error: 'Custom CSS may be at most 20 KB.' } } })
		const wrapper = mountSection()
		await flushPromises()

		await wrapper.find('[data-testid="admin-custom-css-save"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="admin-custom-css-error"]').text()).toBe('Custom CSS may be at most 20 KB.')
		expect(wrapper.find('[data-testid="admin-custom-css-saved"]').exists()).toBe(false)
	})

	it('says so when the CSS cannot be loaded', async () => {
		api.getCustomCss.mockRejectedValue(new Error('boom'))
		const wrapper = mountSection()
		await flushPromises()

		expect(wrapper.find('[data-testid="admin-custom-css-error"]').text()).toBe('The custom CSS could not be loaded.')
	})
})
