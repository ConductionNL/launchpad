/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The badge tells its tile the reading, so an icon-only tile can show the
 * outage as a dot (icon-only-app-tiles REQ-ICT-003).
 *
 * @spec openspec/changes/icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-an-outage-req-ict-003
 */
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import HealthPingBadge from '../HealthPingBadge.vue'
import { fetchHealthPingBadge } from '../../services/healthPingClient.js'

vi.mock('../../services/healthPingClient.js', () => ({
	fetchHealthPingBadge: vi.fn(),
}))

async function flush() {
	await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
	fetchHealthPingBadge.mockReset()
})

describe('HealthPingBadge reading', () => {
	it('reports an offline reading with since when, and draws nothing when hidden', async () => {
		fetchHealthPingBadge.mockResolvedValue({ state: 'offline', checkedAt: '2026-11-25T09:20:00Z', latencyMs: null, stale: false, downSince: '2026-11-25T09:12:00Z' })
		const wrapper = mount(HealthPingBadge, { props: { placementId: 4, interval: 60, hidden: true } })
		await flush()
		expect(wrapper.emitted('reading').at(-1)).toEqual([{ state: 'offline', downSince: '2026-11-25T09:12:00Z' }])
		expect(wrapper.find('.health-ping-badge').exists()).toBe(false)
		wrapper.unmount()
	})

	it('reports no outage time for an online service, and draws the badge when not hidden', async () => {
		fetchHealthPingBadge.mockResolvedValue({ state: 'online', checkedAt: '2026-11-25T09:20:00Z', latencyMs: 40, stale: false, downSince: null })
		const wrapper = mount(HealthPingBadge, { props: { placementId: 4, interval: 60 } })
		await flush()
		expect(wrapper.emitted('reading').at(-1)).toEqual([{ state: 'online', downSince: null }])
		expect(wrapper.find('.health-ping-badge').exists()).toBe(true)
		wrapper.unmount()
	})

	it('reports no state when the badge cannot be read', async () => {
		fetchHealthPingBadge.mockRejectedValue(new Error('403'))
		const wrapper = mount(HealthPingBadge, { props: { placementId: 4, interval: 60 } })
		await flush()
		expect(wrapper.emitted('reading').at(-1)).toEqual([{ state: null, downSince: null }])
		wrapper.unmount()
	})
})
