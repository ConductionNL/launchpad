/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace's period choice (dashboard-period-and-download REQ-DPD-002):
 * offered when a shown widget names a period field, and kept in the address
 * without touching the stored dashboard. Run against the real Views methods
 * and computed properties on a stand-in host.
 *
 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */

import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { logger } from '../../utils/logger.js'

vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))

let Views

beforeAll(async () => {
	Views = (await import('../Views.vue')).default
})

afterEach(() => {
	vi.restoreAllMocks()
	window.history.replaceState(null, '', '/')
})

describe('Views period', () => {
	it('offers the period only when a shown placement names a period field', () => {
		const dated = { shownPlacements: [{ content: { dataSource: { periodField: 'startDate' } } }] }
		const plain = { shownPlacements: [{ content: { url: 'https://example.org' } }] }
		expect(Views.computed.showsPeriod.call(dated)).toBe(true)
		expect(Views.computed.showsPeriod.call(plain)).toBe(false)
	})

	it('takes the chosen period and keeps it in the address, the rest of the address kept', () => {
		window.history.replaceState(null, '', '/apps/launchpad/d/abc?edit=1')
		const host = { dashboardPeriod: 'month' }
		Views.methods.onPeriodChange.call(host, 'quarter')
		expect(host.dashboardPeriod).toBe('quarter')
		expect(window.location.pathname + window.location.search).toBe('/apps/launchpad/d/abc?edit=1&period=quarter')
	})

	it('falls back to This month for an unknown choice', () => {
		const host = { dashboardPeriod: 'year' }
		Views.methods.onPeriodChange.call(host, 'decade')
		expect(host.dashboardPeriod).toBe('month')
		expect(window.location.search).toBe('?period=month')
	})

	it('logs, and keeps the choice, when the address cannot be rewritten', () => {
		vi.spyOn(window.history, 'replaceState').mockImplementation(() => { throw new Error('SecurityError') })
		const host = { dashboardPeriod: 'month' }
		Views.methods.onPeriodChange.call(host, 'year')
		expect(host.dashboardPeriod).toBe('year')
		expect(logger.warn).toHaveBeenCalled()
	})
})
