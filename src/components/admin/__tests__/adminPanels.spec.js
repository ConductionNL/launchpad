/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for four small admin panels: HealthPanel,
 * PrometheusMetricsPanel, LegacyWidgetBridgeToggle and
 * DashboardSharingPolicy. Each loads from the admin API on creation; the
 * toggle and the sharing policy save back and handle failures.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardSharingPolicy from '../DashboardSharingPolicy.vue'
import HealthPanel from '../HealthPanel.vue'
import LegacyWidgetBridgeToggle from '../LegacyWidgetBridgeToggle.vue'
import PrometheusMetricsPanel from '../PrometheusMetricsPanel.vue'
import { api } from '../../../services/api.js'
import { logger } from '../../../utils/logger.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => key }))
vi.mock('../../../services/api.js', () => ({
	api: {
		getHealth: vi.fn(),
		getMetrics: vi.fn(),
		getAdminSettings: vi.fn(),
		updateAdminSettings: vi.fn(),
	},
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const ButtonStub = {
	props: ['disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}
const SwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'disabled'],
	emits: ['update:modelValue'],
	template: '<label class="switch-stub"><slot /></label>',
}
const SelectStub = {
	name: 'NcSelect',
	props: ['modelValue', 'options'],
	emits: ['update:modelValue'],
	template: '<div class="select-stub" />',
}
const TagsStub = {
	name: 'NcSelectTags',
	props: ['modelValue', 'options'],
	emits: ['update:modelValue'],
	template: '<div class="tags-stub" />',
}

const stubs = {
	NcButton: ButtonStub,
	NcLoadingIcon: { template: '<span class="loading-stub" />' },
	ContentCopy: true,
	NcCheckboxRadioSwitch: SwitchStub,
	NcSelect: SelectStub,
	NcSelectTags: TagsStub,
}

const mountWith = (component, props = {}) => mount(component, { props, global: { stubs } })

beforeEach(() => {
	vi.clearAllMocks()
	api.updateAdminSettings.mockResolvedValue({})
})

afterEach(() => {
	vi.useRealTimers()
})

describe('HealthPanel', () => {
	it.each([
		['an ok status', { data: { status: 'ok' } }, 'Healthy'],
		['a wrapped ok status', { data: { data: { status: 'ok' } } }, 'Healthy'],
		['another status', { data: { status: 'degraded' } }, 'Degraded'],
		['no data', { data: null }, 'Degraded'],
	])('shows %s as %s', async (_case, response, label) => {
		api.getHealth.mockResolvedValue(response)
		const wrapper = mountWith(HealthPanel)
		expect(wrapper.find('[data-test="health-badge"]').text()).toBe('Checking…')
		await flushPromises()
		expect(wrapper.find('[data-test="health-badge"]').text()).toBe(label)
	})

	it('shows degraded when the check fails', async () => {
		api.getHealth.mockRejectedValue(new Error('503'))
		const wrapper = mountWith(HealthPanel)
		await flushPromises()
		expect(wrapper.find('.health-panel__badge--bad').exists()).toBe(true)
	})
})

describe('PrometheusMetricsPanel', () => {
	it('shows the metrics text as returned', async () => {
		api.getMetrics.mockResolvedValue({ data: 'launchpad_up 1\n' })
		const wrapper = mountWith(PrometheusMetricsPanel)
		expect(wrapper.find('.loading-stub').exists()).toBe(true)
		await flushPromises()
		expect(wrapper.find('[data-test="metrics-body"]').text()).toBe('launchpad_up 1')
		expect(wrapper.find('button').attributes('disabled')).toBeUndefined()
	})

	it('pretty-prints a non-text payload', async () => {
		api.getMetrics.mockResolvedValue({ data: { up: 1 } })
		const wrapper = mountWith(PrometheusMetricsPanel)
		await flushPromises()
		expect(wrapper.vm.body).toBe('{\n  "up": 1\n}')
	})

	it('shows an error and disables copy when loading fails', async () => {
		api.getMetrics.mockRejectedValue(new Error('403'))
		const wrapper = mountWith(PrometheusMetricsPanel)
		await flushPromises()
		expect(wrapper.find('[data-test="metrics-error"]').exists()).toBe(true)
		expect(wrapper.find('button').attributes('disabled')).toBeDefined()
	})

	it('copies the body and shows "Copied" for two seconds', async () => {
		vi.useFakeTimers()
		const writeText = vi.fn().mockResolvedValue()
		Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
		api.getMetrics.mockResolvedValue({ data: 'm 1' })
		const wrapper = mountWith(PrometheusMetricsPanel)
		await flushPromises()

		await wrapper.find('button').trigger('click')
		await flushPromises()
		expect(writeText).toHaveBeenCalledWith('m 1')
		expect(wrapper.find('button').text()).toBe('Copied')

		vi.advanceTimersByTime(1999)
		await wrapper.vm.$nextTick()
		expect(wrapper.find('button').text()).toBe('Copied')

		vi.advanceTimersByTime(1)
		await wrapper.vm.$nextTick()
		expect(wrapper.find('button').text()).toBe('Copy')
	})

	it('stays on "Copy" when the clipboard refuses or is missing', async () => {
		Object.defineProperty(navigator, 'clipboard', {
			value: { writeText: vi.fn().mockRejectedValue(new Error('denied')) },
			configurable: true,
		})
		api.getMetrics.mockResolvedValue({ data: 'm 1' })
		const wrapper = mountWith(PrometheusMetricsPanel)
		await flushPromises()

		await wrapper.vm.copy()
		expect(wrapper.vm.copied).toBe(false)

		Object.defineProperty(navigator, 'clipboard', { value: undefined, configurable: true })
		await wrapper.vm.copy()
		expect(wrapper.vm.copied).toBe(false)
	})
})

describe('LegacyWidgetBridgeToggle', () => {
	it.each([
		[{ data: { legacyWidgetBridgeEnabled: false } }, false],
		[{ data: { data: { legacyWidgetBridgeEnabled: true } } }, true],
		[{ data: {} }, true],
	])('reads the stored setting %#', async (response, enabled) => {
		api.getAdminSettings.mockResolvedValue(response)
		const wrapper = mountWith(LegacyWidgetBridgeToggle)
		expect(wrapper.findComponent(SwitchStub).props('disabled')).toBe(true)
		await flushPromises()

		expect(wrapper.findComponent(SwitchStub).props()).toEqual({ modelValue: enabled, disabled: false })
	})

	it('stays enabled and logs when loading fails', async () => {
		api.getAdminSettings.mockRejectedValue(new Error('x'))
		const wrapper = mountWith(LegacyWidgetBridgeToggle)
		await flushPromises()
		expect(wrapper.vm.enabled).toBe(true)
		expect(logger.error).toHaveBeenCalledWith('Failed to load bridge setting:', expect.any(Error))
	})

	it('saves a toggle and reverts it when saving fails', async () => {
		api.getAdminSettings.mockResolvedValue({ data: {} })
		const wrapper = mountWith(LegacyWidgetBridgeToggle)
		await flushPromises()

		wrapper.findComponent(SwitchStub).vm.$emit('update:modelValue', false)
		await flushPromises()
		expect(api.updateAdminSettings).toHaveBeenCalledWith({ legacyWidgetBridgeEnabled: false })
		expect(wrapper.vm.enabled).toBe(false)

		api.updateAdminSettings.mockRejectedValue(new Error('x'))
		wrapper.findComponent(SwitchStub).vm.$emit('update:modelValue', true)
		await flushPromises()
		expect(wrapper.vm.enabled).toBe(false)
		expect(logger.error).toHaveBeenCalledWith('Failed to save bridge setting:', expect.any(Error))
	})
})

describe('DashboardSharingPolicy', () => {
	it('loads the stored level and forced groups', async () => {
		api.getAdminSettings.mockResolvedValue({
			data: { defaultSharePermissionLevel: 'full', forcedShareGroups: ['staff'] },
		})
		const wrapper = mountWith(DashboardSharingPolicy, { groups: ['staff', 'ops'] })
		await flushPromises()

		expect(wrapper.findComponent(SelectStub).props('modelValue')).toEqual({ id: 'full', label: 'Full customization' })
		expect(wrapper.findComponent(TagsStub).props()).toEqual({ modelValue: ['staff'], options: ['staff', 'ops'] })
	})

	it('defaults to add only and no groups for missing or unknown values', async () => {
		api.getAdminSettings.mockResolvedValue({ data: { data: { defaultSharePermissionLevel: 'odd', forcedShareGroups: 'x' } } })
		const wrapper = mountWith(DashboardSharingPolicy)
		await flushPromises()

		expect(wrapper.vm.defaultPermission).toEqual({ id: 'add_only', label: 'Add only' })
		expect(wrapper.vm.forcedGroups).toEqual([])
	})

	it('keeps the defaults and logs when loading fails', async () => {
		api.getAdminSettings.mockRejectedValue(new Error('x'))
		const wrapper = mountWith(DashboardSharingPolicy)
		await flushPromises()
		expect(wrapper.vm.defaultPermission.id).toBe('add_only')
		expect(logger.error).toHaveBeenCalledWith('Failed to load sharing policy:', expect.any(Error))
	})

	it('saves the level id and groups on every change', async () => {
		api.getAdminSettings.mockResolvedValue({ data: {} })
		const wrapper = mountWith(DashboardSharingPolicy, { groups: ['a', 'b'] })
		await flushPromises()

		wrapper.findComponent(SelectStub).vm.$emit('update:modelValue', { id: 'view_only', label: 'View only' })
		await flushPromises()
		wrapper.findComponent(TagsStub).vm.$emit('update:modelValue', ['b'])
		await flushPromises()

		expect(api.updateAdminSettings.mock.calls).toEqual([
			[{ defaultSharePermissionLevel: 'view_only', forcedShareGroups: [] }],
			[{ defaultSharePermissionLevel: 'view_only', forcedShareGroups: ['b'] }],
		])
	})

	it('logs a failed save', async () => {
		api.getAdminSettings.mockResolvedValue({ data: {} })
		api.updateAdminSettings.mockRejectedValue(new Error('x'))
		const wrapper = mountWith(DashboardSharingPolicy)
		await flushPromises()

		await wrapper.vm.save()
		expect(logger.error).toHaveBeenCalledWith('Failed to save sharing policy:', expect.any(Error))
	})
})

describe('DashboardSharingPolicy sharing limits (REQ-SHARE-014)', () => {
	const scope = (wrapper) => wrapper.find('[data-test="sharing-policy-recipient-scope"]').findComponent(SelectStub)
	const allowed = (wrapper) => wrapper.find('[data-test="sharing-policy-allowed-groups"]').findComponent(TagsStub)

	it('loads the allowed groups and the recipient scope', async () => {
		api.getAdminSettings.mockResolvedValue({
			data: { sharingAllowedGroups: ['communicatie'], sharingRecipientScope: 'shared-groups' },
		})
		const wrapper = mountWith(DashboardSharingPolicy, { groups: ['communicatie', 'staff'] })
		await flushPromises()

		expect(wrapper.vm.allowedGroups).toEqual(['communicatie'])
		expect(wrapper.vm.recipientScope).toEqual({ id: 'shared-groups', label: 'Only people who share a group with the sharer' })
	})

	it('reads missing or unknown values as everyone may share with anyone', async () => {
		api.getAdminSettings.mockResolvedValue({ data: { sharingAllowedGroups: 'x', sharingRecipientScope: 'odd' } })
		const wrapper = mountWith(DashboardSharingPolicy)
		await flushPromises()

		expect(wrapper.vm.allowedGroups).toEqual([])
		expect(wrapper.vm.recipientScope.id).toBe('everyone')
	})

	it('saves only the two limits when either changes', async () => {
		api.getAdminSettings.mockResolvedValue({ data: {} })
		const wrapper = mountWith(DashboardSharingPolicy, { groups: ['a', 'b'] })
		await flushPromises()
		api.updateAdminSettings.mockClear()

		wrapper.vm.allowedGroups = ['a']
		allowed(wrapper).vm.$emit('update:modelValue', ['a'])
		await flushPromises()
		wrapper.vm.recipientScope = wrapper.vm.scopeOptions[1]
		scope(wrapper).vm.$emit('update:modelValue', wrapper.vm.scopeOptions[1])
		await flushPromises()

		expect(api.updateAdminSettings.mock.calls).toEqual([
			[{ sharingAllowedGroups: ['a'], sharingRecipientScope: 'everyone' }],
			[{ sharingAllowedGroups: ['a'], sharingRecipientScope: 'shared-groups' }],
		])
	})

	it('logs a failed save of the limits', async () => {
		api.getAdminSettings.mockResolvedValue({ data: {} })
		api.updateAdminSettings.mockRejectedValue(new Error('x'))
		const wrapper = mountWith(DashboardSharingPolicy)
		await flushPromises()

		await wrapper.vm.saveLimits()
		expect(logger.error).toHaveBeenCalledWith('Failed to save sharing limits:', expect.any(Error))
	})
})

