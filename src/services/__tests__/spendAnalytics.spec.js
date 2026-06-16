/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for the spend-analytics data layer (REQ-SAW-004,
 * REQ-SAW-005, REQ-SAW-006, REQ-SAW-007). Verifies finance/vendor
 * aggregation, the per-source unavailable envelope on a missing sibling
 * app, the openconnector LLM hand-off (and its graceful-absent path),
 * and deep-link resolution.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import axios from '@nextcloud/axios'
import { queryGraphql, GraphQLSourceError } from '../graphqlClient.js'
import {
	fetchFinanceSummary,
	fetchVendorCommitments,
	fetchSpendNarrative,
	resolveDeepLink,
	SPEND_SOURCES,
} from '../spendAnalytics.js'

vi.mock('../graphqlClient.js', async (importOriginal) => {
	const actual = await importOriginal()
	return {
		...actual,
		queryGraphql: vi.fn(),
	}
})
vi.mock('@nextcloud/axios', () => ({
	default: { post: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) => Object.entries(params).reduce(
		(acc, [key, value]) => acc.replace(`{${key}}`, value),
		path,
	),
}))

describe('spendAnalytics data layer', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('aggregates finance transactions into total + byCategory + trend (REQ-SAW-004)', async () => {
		queryGraphql.mockResolvedValue({
			transactions: [
				{ totalAmount: 100, currency: 'EUR', category: 'IT', bookedAt: '2026-01-15' },
				{ totalAmount: 50, currency: 'EUR', category: 'IT', bookedAt: '2026-02-10' },
				{ totalAmount: 25, currency: 'EUR', category: 'Travel', bookedAt: '2026-01-20' },
			],
		})
		const result = await fetchFinanceSummary({ period: 'quarter' })

		expect(queryGraphql).toHaveBeenCalledWith(expect.objectContaining({ app: SPEND_SOURCES.FINANCE }))
		expect(result.available).toBe(true)
		expect(result.total).toBe(175)
		expect(result.currency).toBe('EUR')
		// byCategory sorted desc by amount.
		expect(result.byCategory[0]).toEqual({ category: 'IT', amount: 150 })
		// trend sorted asc by month.
		expect(result.trend.map((t) => t.month)).toEqual(['2026-01', '2026-02'])
	})

	it('returns available:false with code when financeq is absent (REQ-SAW-005)', async () => {
		queryGraphql.mockRejectedValue(new GraphQLSourceError('not_installed', 'financeq', 'financeq is not installed'))
		const result = await fetchFinanceSummary({ period: 'quarter' })
		expect(result).toMatchObject({ available: false, code: 'not_installed' })
	})

	it('returns available:true empty:true when financeq has no rows (REQ-SAW-005)', async () => {
		queryGraphql.mockResolvedValue({ transactions: [] })
		const result = await fetchFinanceSummary({ period: 'month' })
		expect(result).toMatchObject({ available: true, empty: true, total: 0 })
	})

	it('aggregates procest vendor commitments desc (REQ-SAW-004)', async () => {
		queryGraphql.mockResolvedValue({
			cases: [
				{ vendor: 'acme', totalCommitted: 30 },
				{ vendor: 'acme', totalCommitted: 20 },
				{ vendor: 'globex', totalCommitted: 40 },
			],
		})
		const result = await fetchVendorCommitments({ period: 'quarter' })
		expect(result.available).toBe(true)
		expect(result.topVendors[0]).toEqual({ vendor: 'acme', committed: 50 })
		expect(result.topVendors[1]).toEqual({ vendor: 'globex', committed: 40 })
	})

	it('vendor source returns unavailable envelope when procest absent (REQ-SAW-005)', async () => {
		queryGraphql.mockRejectedValue(new GraphQLSourceError('not_installed', 'procest', 'procest is not installed'))
		const result = await fetchVendorCommitments({ period: 'quarter' })
		expect(result).toMatchObject({ available: false, code: 'not_installed' })
	})

	it('routes AI narrative through openconnector, not Ollama directly (REQ-SAW-006)', async () => {
		axios.post.mockResolvedValue({ data: { narrative: 'Spend is up 12%.' } })
		const result = await fetchSpendNarrative({ summary: { total: 100 } })
		expect(result).toEqual({ available: true, narrative: 'Spend is up 12%.' })
		const [url, body] = axios.post.mock.calls[0]
		expect(url).toContain('/apps/openconnector/')
		expect(url).not.toContain('11434')
		// Conduction local-LLM defaults.
		expect(body.think).toBe(false)
		expect(body.keep_alive).toBe(-1)
	})

	it('disables AI panel gracefully when openconnector is absent (REQ-SAW-006)', async () => {
		axios.post.mockRejectedValue({ response: { status: 404 } })
		const result = await fetchSpendNarrative({ summary: { total: 100 } })
		expect(result).toMatchObject({ available: false, code: 'not_installed' })
	})

	it('resolves a deep-link to the owning sibling app (REQ-SAW-007)', () => {
		expect(resolveDeepLink('financeq', 'transaction', 'tx-1')).toBe('/apps/financeq/transaction/tx-1')
		expect(resolveDeepLink('procest', 'vendor', 'acme')).toBe('/apps/procest/vendor/acme')
	})
})
