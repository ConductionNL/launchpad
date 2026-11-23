/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The embed telemetry switch shows the stored state, saves a change through
 * the admin API and rolls back when saving fails (embedded-analytics task 30).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-30
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import EmbedTelemetryToggle from '../EmbedTelemetryToggle.vue'
import { logger } from '../../../utils/logger.js'

const http = vi.hoisted(() => ({ get: vi.fn(), put: vi.fn() }))
vi.mock('@nextcloud/axios', () => ({ default: http }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => key }))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const SwitchStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'disabled'],
	emits: ['update:modelValue'],
	template: '<label class="switch-stub"><slot /></label>',
}

const mountToggle = () => mount(EmbedTelemetryToggle, { global: { stubs: { NcCheckboxRadioSwitch: SwitchStub } } })

describe('EmbedTelemetryToggle', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it.each([
		[{ enabled: false }, false],
		[{ enabled: true }, true],
		[{}, true],
	])('shows the stored setting %#', async (answer, enabled) => {
		http.get.mockResolvedValue({ data: answer })
		const wrapper = mountToggle()
		expect(wrapper.findComponent(SwitchStub).props('disabled')).toBe(true)
		await flushPromises()

		expect(http.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/embed-settings/telemetry')
		expect(wrapper.findComponent(SwitchStub).props()).toEqual({ modelValue: enabled, disabled: false })
		expect(wrapper.text()).toContain('Collect embed usage telemetry')
	})

	it('saves a change and takes the state the server stored', async () => {
		http.get.mockResolvedValue({ data: { enabled: true } })
		http.put.mockResolvedValue({ data: { enabled: false } })
		const wrapper = mountToggle()
		await flushPromises()

		wrapper.findComponent(SwitchStub).vm.$emit('update:modelValue', false)
		await flushPromises()

		expect(http.put).toHaveBeenCalledWith('/index.php/apps/launchpad/api/embed-settings/telemetry', { enabled: false })
		expect(wrapper.findComponent(SwitchStub).props('modelValue')).toBe(false)
	})

	it('rolls back and logs when saving or loading fails', async () => {
		http.get.mockRejectedValue(new Error('down'))
		const wrapper = mountToggle()
		await flushPromises()
		expect(wrapper.vm.enabled).toBe(true)
		expect(logger.error).toHaveBeenCalledWith('Failed to load embed telemetry setting:', expect.any(Error))

		http.put.mockRejectedValue(new Error('forbidden'))
		wrapper.findComponent(SwitchStub).vm.$emit('update:modelValue', false)
		await flushPromises()

		expect(wrapper.vm.enabled).toBe(true)
		expect(logger.error).toHaveBeenCalledWith('Failed to save embed telemetry setting:', expect.any(Error))
	})
})
