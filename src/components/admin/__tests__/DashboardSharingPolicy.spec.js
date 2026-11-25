/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for `DashboardSharingPolicy.vue`: "Sharing allowed for"
 * Everyone, Only these groups or Nobody, and the public-link switch
 * (dashboard-sharing REQ-SHARE-015, board LpBeheerDelen).
 *
 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const getAdminSettings = vi.fn()
const updateAdminSettings = vi.fn()
vi.mock('../../../services/api.js', () => ({
	api: {
		getAdminSettings: (...args) => getAdminSettings(...args),
		updateAdminSettings: (...args) => updateAdminSettings(...args),
	},
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))

const stubs = {
	NcSelect: { template: '<div class="nc-select" />' },
	NcSelectTags: { template: '<div class="nc-select-tags" :data-test="$attrs[\'data-test\']" />' },
	NcCheckboxRadioSwitch: {
		props: ['modelValue', 'value', 'type'],
		emits: ['update:modelValue'],
		template:
			'<label :data-test="$attrs[\'data-test\']" :data-checked="String(type === \'radio\' ? modelValue === value : modelValue)" @click="$emit(\'update:modelValue\', type === \'radio\' ? value : !modelValue)"><slot /></label>',
	},
}

let DashboardSharingPolicy

beforeEach(async () => {
	getAdminSettings.mockReset()
	updateAdminSettings.mockReset()
	updateAdminSettings.mockResolvedValue({})
	DashboardSharingPolicy = (await import('../DashboardSharingPolicy.vue')).default
})

async function mountPolicy(settings) {
	getAdminSettings.mockResolvedValue({ data: settings })
	const wrapper = mount(DashboardSharingPolicy, {
		props: { groups: ['sales', 'communicatie'] },
		global: { stubs },
	})
	await flushPromises()
	return wrapper
}

describe('DashboardSharingPolicy (REQ-SHARE-015)', () => {
	it('offers Everyone, Only these groups and Nobody, with the stored choice selected', async () => {
		const wrapper = await mountPolicy({ sharingAllowed: 'none', publicLinksAllowed: true })

		expect(wrapper.find('[data-test="sharing-policy-allowed"]').text()).toContain('Sharing allowed for')
		expect(wrapper.find('[data-test="sharing-policy-allowed-choice-everyone"]').attributes('data-checked')).toBe('false')
		expect(wrapper.find('[data-test="sharing-policy-allowed-choice-groups"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="sharing-policy-allowed-choice-none"]').attributes('data-checked')).toBe('true')
		expect(wrapper.find('[data-test="sharing-policy-nobody-hint"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="sharing-policy-allowed-choice-groups"]').text()).toBe('Only these groups')
	})

	it('reads an older server without the choice from the group list', async () => {
		const withGroups = await mountPolicy({ sharingAllowedGroups: ['sales'] })
		expect(withGroups.vm.sharingAllowed).toBe('groups')
		expect(withGroups.find('.nc-select-tags[data-test="sharing-policy-allowed-groups"]').exists()).toBe(true)

		const without = await mountPolicy({})
		expect(without.vm.sharingAllowed).toBe('everyone')
		expect(without.vm.publicLinksAllowed).toBe(true)
	})

	it('saves Nobody with the limits and hides the group picker', async () => {
		const wrapper = await mountPolicy({ sharingAllowed: 'groups', sharingAllowedGroups: ['sales'] })

		await wrapper.find('[data-test="sharing-policy-allowed-choice-none"]').trigger('click')
		await flushPromises()

		expect(updateAdminSettings).toHaveBeenCalledWith(expect.objectContaining({
			sharingAllowed: 'none',
			sharingAllowedGroups: ['sales'],
		}))
		expect(wrapper.find('.nc-select-tags[data-test="sharing-policy-allowed-groups"]').exists()).toBe(false)
	})

	it('switches public links off and says a link always expires', async () => {
		const wrapper = await mountPolicy({ publicLinksAllowed: true })
		const field = wrapper.find('[data-test="sharing-policy-public-links"]')
		expect(field.text()).toBe('Allow public links')
		expect(wrapper.text()).toContain('A link always has an expiry date.')

		await field.trigger('click')
		await flushPromises()

		expect(updateAdminSettings).toHaveBeenCalledWith({ publicLinksAllowed: false })
		expect(wrapper.vm.publicLinksAllowed).toBe(false)
	})
})
