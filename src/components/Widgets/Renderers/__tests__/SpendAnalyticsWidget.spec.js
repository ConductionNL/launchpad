/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `SpendAnalyticsWidget.vue` (REQ-SAW-004..-007).
 * Verifies the renderer fetches via the service (never axios directly),
 * renders summary cards on success, renders the financeq-absent
 * empty-state, renders the partial-availability vendor empty-state,
 * and deep-links a row click to the owning sibling app.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'

const fetchFinanceSummary = vi.fn()
const fetchVendorCommitments = vi.fn()
const fetchSpendNarrative = vi.fn()
const resolveDeepLink = vi.fn((app, kind, id) => `/apps/${app}/${kind}/${id}`)

vi.mock('../../../../services/spendAnalytics.js', () => ({
	fetchFinanceSummary: (...a) => fetchFinanceSummary(...a),
	fetchVendorCommitments: (...a) => fetchVendorCommitments(...a),
	fetchSpendNarrative: (...a) => fetchSpendNarrative(...a),
	resolveDeepLink: (...a) => resolveDeepLink(...a),
}))

import SpendAnalyticsWidget from '../SpendAnalyticsWidget.vue'

async function flush() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
	vi.clearAllMocks()
	globalThis.t = (_app, key, params = {}) => Object.entries(params)
		.reduce((acc, [k, v]) => acc.replace(`{${k}}`, v), key)
})

describe('SpendAnalyticsWidget (REQ-SAW-004..-007)', () => {
	it('fetches finance summary on mount and renders the total card (REQ-SAW-004)', async () => {
		fetchFinanceSummary.mockResolvedValue({ available: true, empty: false, total: 1234.5, currency: 'EUR', byCategory: [{ category: 'IT', amount: 1234.5 }], trend: [] })
		const wrapper = mount(SpendAnalyticsWidget, { propsData: { content: { viewMode: 'summary', period: 'quarter' } } })
		await flush()
		expect(fetchFinanceSummary).toHaveBeenCalledWith(expect.objectContaining({ period: 'quarter' }))
		expect(wrapper.text()).toContain('Total spend')
	})

	it('renders the financeq-absent empty-state (REQ-SAW-005)', async () => {
		fetchFinanceSummary.mockResolvedValue({ available: false, reason: 'financeq is not installed', code: 'not_installed' })
		const wrapper = mount(SpendAnalyticsWidget, { propsData: { content: { viewMode: 'summary' } } })
		await flush()
		expect(wrapper.text()).toContain('No spend data — financeq is not installed')
	})

	it('renders finance cards but an inline vendor empty-state under partial availability (REQ-SAW-005)', async () => {
		fetchFinanceSummary.mockResolvedValue({ available: true, empty: false, total: 100, currency: 'EUR', byCategory: [], trend: [] })
		fetchVendorCommitments.mockResolvedValue({ available: false, reason: 'procest is not installed', code: 'not_installed' })
		const wrapper = mount(SpendAnalyticsWidget, { propsData: { content: { viewMode: 'top-vendors', period: 'quarter' } } })
		await flush()
		expect(wrapper.text()).toContain('Total spend')
		expect(wrapper.text()).toContain('No vendor data — procest is not installed')
	})

	it('deep-links a vendor row click to procest (REQ-SAW-007)', async () => {
		fetchFinanceSummary.mockResolvedValue({ available: true, empty: false, total: 50, currency: 'EUR', byCategory: [], trend: [] })
		fetchVendorCommitments.mockResolvedValue({ available: true, empty: false, topVendors: [{ vendor: 'acme', committed: 50 }] })
		const wrapper = mount(SpendAnalyticsWidget, { propsData: { content: { viewMode: 'top-vendors', period: 'quarter' } } })
		await flush()
		// drillTo assigns window.location.href; assert the resolver is called with the owning app.
		wrapper.vm.drillTo('procest', 'vendor', 'acme')
		expect(resolveDeepLink).toHaveBeenCalledWith('procest', 'vendor', 'acme')
	})

	it('formats currency by locale', async () => {
		fetchFinanceSummary.mockResolvedValue({ available: true, empty: false, total: 1234.5, currency: 'EUR', byCategory: [], trend: [] })
		const wrapper = mount(SpendAnalyticsWidget, { propsData: { content: { viewMode: 'summary' } } })
		await flush()
		const formatted = wrapper.vm.formatAmount(1234.5)
		expect(formatted).toMatch(/1[.,\s]?234/)
	})
})
