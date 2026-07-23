/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `HealthPingBadge.vue` (REQ-HPING-003/004). The
 * browser client (`healthPingClient.js`) is mocked so these tests never
 * perform a real network call.
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import HealthPingBadge from '../HealthPingBadge.vue'
import { fetchHealthPingBadge } from '../../services/healthPingClient.js'

vi.mock('../../services/healthPingClient.js', () => ({
	fetchHealthPingBadge: vi.fn(),
}))

// Vue 2's @vue/test-utils does not export `flushPromises`.
async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
	globalThis.t = (_app, key, vars) => {
		if (vars && typeof key === 'string') {
			return key.replace(/\{(\w+)\}/g, (_, name) =>
				Object.prototype.hasOwnProperty.call(vars, name) ? vars[name] : `{${name}}`)
		}
		return key
	}
	fetchHealthPingBadge.mockReset()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('HealthPingBadge — REQ-HPING-003 fetches via the placement endpoint only', () => {
	it('calls fetchHealthPingBadge(placementId) on mount', async () => {
		fetchHealthPingBadge.mockResolvedValue({ state: 'online', checkedAt: '2026-07-23T10:00:00Z', latencyMs: 42, stale: false })
		mount(HealthPingBadge, { propsData: { placementId: 7, interval: 60 } })
		await flushPromises()
		expect(fetchHealthPingBadge).toHaveBeenCalledWith(7)
	})

	it('never fetches when placementId is not set (not-yet-saved tile)', async () => {
		mount(HealthPingBadge, { propsData: { placementId: null, interval: 60 } })
		await flushPromises()
		expect(fetchHealthPingBadge).not.toHaveBeenCalled()
	})
})

describe('HealthPingBadge — REQ-HPING-004 renders icon + text, never colour alone', () => {
	it('renders the online state with an icon AND a text label', async () => {
		fetchHealthPingBadge.mockResolvedValue({ state: 'online', checkedAt: '2026-07-23T10:00:00Z', latencyMs: 42, stale: false })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 1, interval: 60 } })
		await flushPromises()
		const badge = wrapper.find('.health-ping-badge')
		expect(badge.exists()).toBe(true)
		expect(badge.classes()).toContain('health-ping-badge--online')
		expect(badge.find('svg').exists()).toBe(true)
		expect(badge.text()).toContain('Online')
	})

	it('renders the degraded state with an icon AND a text label', async () => {
		fetchHealthPingBadge.mockResolvedValue({ state: 'degraded', checkedAt: '2026-07-23T10:00:00Z', latencyMs: 3000, stale: false })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 2, interval: 60 } })
		await flushPromises()
		const badge = wrapper.find('.health-ping-badge')
		expect(badge.classes()).toContain('health-ping-badge--degraded')
		expect(badge.find('svg').exists()).toBe(true)
		expect(badge.text()).toContain('Degraded')
	})

	it('renders the offline state with an icon AND a text label', async () => {
		fetchHealthPingBadge.mockResolvedValue({ state: 'offline', checkedAt: '2026-07-23T10:00:00Z', latencyMs: null, stale: false })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 3, interval: 60 } })
		await flushPromises()
		const badge = wrapper.find('.health-ping-badge')
		expect(badge.classes()).toContain('health-ping-badge--offline')
		expect(badge.find('svg').exists()).toBe(true)
		expect(badge.text()).toContain('Offline')
	})

	it('exposes checked-at time and latency via a keyboard-reachable, announced tooltip', async () => {
		fetchHealthPingBadge.mockResolvedValue({ state: 'online', checkedAt: '2026-07-23T10:00:00Z', latencyMs: 55, stale: false })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 4, interval: 60 } })
		await flushPromises()
		const badge = wrapper.find('.health-ping-badge')
		// A real <button> is natively keyboard-reachable (focusable, Enter/Space
		// activated) — this is the mechanism REQ-HPING-004 relies on.
		expect(badge.element.tagName).toBe('BUTTON')
		expect(badge.attributes('title')).toContain('55')
		expect(badge.attributes('aria-label')).toBe(badge.attributes('title'))
	})
})

describe('HealthPingBadge — REQ-HPING-004 ping disabled / no data shows no badge', () => {
	it('renders nothing when the endpoint reports not_configured', async () => {
		fetchHealthPingBadge.mockResolvedValue({ error: 'not_configured' })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 5, interval: 60 } })
		await flushPromises()
		expect(wrapper.find('.health-ping-badge').exists()).toBe(false)
	})

	it('renders nothing (never crashes) on a network failure', async () => {
		fetchHealthPingBadge.mockRejectedValue(new Error('network error'))
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 6, interval: 60 } })
		await flushPromises()
		expect(wrapper.find('.health-ping-badge').exists()).toBe(false)
	})
})

describe('HealthPingBadge — interval clamp', () => {
	it('clamps a below-minimum interval to 15s for the poll cadence', () => {
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 1, interval: 5 } })
		expect(wrapper.vm.clampedInterval).toBe(15)
	})

	it('defaults an unset/zero interval to 60s', () => {
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 1, interval: 0 } })
		expect(wrapper.vm.clampedInterval).toBe(60)
	})

	it('polls again after the clamped interval elapses', async () => {
		vi.useFakeTimers()
		fetchHealthPingBadge.mockResolvedValue({ state: 'online', checkedAt: '2026-07-23T10:00:00Z', latencyMs: 1, stale: false })
		mount(HealthPingBadge, { propsData: { placementId: 1, interval: 15 } })
		// Mount's own load() call.
		await vi.advanceTimersByTimeAsync(0)
		expect(fetchHealthPingBadge).toHaveBeenCalledTimes(1)

		await vi.advanceTimersByTimeAsync(15000)
		expect(fetchHealthPingBadge).toHaveBeenCalledTimes(2)
	})
})

describe('HealthPingBadge — stale reading', () => {
	it('still renders the last-known state when the badge is marked stale', async () => {
		fetchHealthPingBadge.mockResolvedValue({ state: 'online', checkedAt: '2026-07-23T09:00:00Z', latencyMs: 40, stale: true })
		const wrapper = mount(HealthPingBadge, { propsData: { placementId: 8, interval: 60 } })
		await flushPromises()
		const badge = wrapper.find('.health-ping-badge')
		expect(badge.exists()).toBe(true)
		expect(badge.classes()).toContain('health-ping-badge--online')
		expect(badge.attributes('title')).toContain('last known')
	})
})
