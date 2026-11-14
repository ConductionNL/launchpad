/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `HealthPingBadge.vue` state edges: unknown and
 * error answers, the tooltip without latency or check time, and that the
 * poll stops on unmount. The browser client is mocked.
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import HealthPingBadge from '../HealthPingBadge.vue'
import { fetchHealthPingBadge } from '../../services/healthPingClient.js'

vi.mock('../../services/healthPingClient.js', () => ({
	fetchHealthPingBadge: vi.fn(),
}))

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
	globalThis.t = (_app, key, vars) =>
		vars ? key.replace(/\{(\w+)\}/g, (_, name) => (Object.hasOwn(vars, name) ? vars[name] : `{${name}}`)) : key
	fetchHealthPingBadge.mockReset()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('HealthPingBadge — answers that hide the badge', () => {
	it('clears to hidden on an error answer, an empty answer or no state', async () => {
		for (const answer of [{ error: 'forbidden', state: 'online' }, null, { checkedAt: 'x' }]) {
			fetchHealthPingBadge.mockResolvedValueOnce(answer)
			const wrapper = mount(HealthPingBadge, { propsData: { placementId: 1, interval: 60 } })
			await flushPromises()
			expect(wrapper.vm.state).toBe(null)
			expect(wrapper.vm.badgeClass).toBe('')
			expect(wrapper.vm.tooltipText).toBe('')
			wrapper.destroy?.()
			wrapper.unmount?.()
		}
	})

	it('labels an unknown state as nothing but keeps a class for it', async () => {
		fetchHealthPingBadge.mockResolvedValueOnce({ state: 'maintenance', latencyMs: '12' })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 1, interval: 60 } })
		await flushPromises()
		expect(wrapper.vm.stateLabel).toBe('')
		expect(wrapper.vm.badgeClass).toBe('health-ping-badge--maintenance')
		expect(wrapper.vm.latencyMs).toBe(12)
		expect(wrapper.vm.stateIcon).toBeTruthy()
	})
})

describe('HealthPingBadge — tooltip', () => {
	it('says never checked and leaves out latency when the server has none', async () => {
		fetchHealthPingBadge.mockResolvedValueOnce({ state: 'degraded', latencyMs: null, stale: 1 })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 1, interval: 60 } })
		await flushPromises()
		expect(wrapper.vm.checkedAt).toBe(null)
		expect(wrapper.vm.latencyMs).toBe(null)
		expect(wrapper.vm.stale).toBe(true)
		expect(wrapper.vm.tooltipText).toBe('Degraded — checked never (last known)')
	})

	it('drops the last-known suffix when the answer is fresh', async () => {
		fetchHealthPingBadge.mockResolvedValueOnce({ state: 'offline' })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 1, interval: 60 } })
		await flushPromises()
		expect(wrapper.vm.tooltipText).toBe('Offline — checked never')
	})
})

describe('HealthPingBadge — polling lifecycle', () => {
	it('stops polling when unmounted and stopping twice is harmless', async () => {
		vi.useFakeTimers()
		fetchHealthPingBadge.mockResolvedValue({ state: 'online' })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 5, interval: 15 } })
		expect(fetchHealthPingBadge).toHaveBeenCalledTimes(1)
		expect(wrapper.vm.pollHandle).not.toBe(null)
		if (wrapper.unmount) {
			wrapper.unmount()
		} else {
			wrapper.destroy()
		}
		expect(wrapper.vm.pollHandle).toBe(null)
		wrapper.vm.stopPolling()
		vi.advanceTimersByTime(60000)
		expect(fetchHealthPingBadge).toHaveBeenCalledTimes(1)
	})
})
