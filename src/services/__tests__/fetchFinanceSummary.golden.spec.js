/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Golden test for fetchFinanceSummary(): a set of GraphQL answers and errors,
 * each with the variables sent, compared with
 * fixtures/fetchFinanceSummary.golden.json. Set RECORD_GOLDEN=1 to rewrite it.
 */

import fs from 'node:fs'
import path from 'node:path'
import { describe, expect, it, vi } from 'vitest'
import { GraphQLSourceError, queryGraphql } from '../graphqlClient.js'
import { fetchFinanceSummary } from '../spendAnalytics.js'

vi.mock('../graphqlClient.js', async (importOriginal) => {
	const actual = await importOriginal()
	return { ...actual, queryGraphql: vi.fn() }
})

const FIXTURE = path.resolve(__dirname, 'fixtures/fetchFinanceSummary.golden.json')

const ROWS = [
	{ totalAmount: 100, currency: 'EUR', category: 'IT', bookedAt: '2026-01-15' },
	{ totalAmount: '50.5', currency: 'USD', category: 'IT', bookedAt: '2026-02-10' },
	{ totalAmount: 0.1, category: 'Travel', bookedAt: '2026-01-31T10:00:00Z' },
	{ totalAmount: 0.2, currency: 5, category: '', bookedAt: 20260101 },
	{ totalAmount: 'abc', category: null, bookedAt: '2025-12' },
	{ totalAmount: -30, currency: 'GBP', category: 'Travel' },
	{ totalAmount: null, category: 'Office', bookedAt: '' },
	{ totalAmount: 7, currency: '', category: 'Office', bookedAt: '2026-02-01' },
]

const ANSWERS = {
	'all rows': { transactions: ROWS },
	'first row only': { transactions: ROWS.slice(0, 1) },
	'from the second row': { transactions: ROWS.slice(1) },
	'rows without currency': { transactions: ROWS.slice(2, 5) },
	'rows reversed': { transactions: [...ROWS].reverse() },
	'equal amounts': {
		transactions: [
			{ totalAmount: 5, category: 'B', bookedAt: '2026-03-01' },
			{ totalAmount: 5, category: 'A', bookedAt: '2026-01-01' },
		],
	},
	'empty list': { transactions: [] },
	'not a list': { transactions: 'nope' },
	'no transactions key': {},
	null: null,
	undefined,
	'null row': { transactions: [null] },
	'row without fields': { transactions: [{}] },
}

const ERRORS = {
	'source error': new GraphQLSourceError(
		'not_installed',
		'financeq',
		'financeq is not installed',
	),
	'other error': new TypeError('boom'),
}

const PARAMS = {
	'period only': { period: 'month' },
	'all filters': { period: 'ytd', categoryIds: ['IT'], departmentIds: ['d1'] },
	'null filters': { period: 'fy', categoryIds: null, departmentIds: undefined },
}

/**
 * Run one case and record what it resolved to, or what it rejected with,
 * and the request it sent.
 *
 * @param {object} params The aggregation parameters.
 * @return {Promise<object>} The outcome.
 */
async function outcome(params) {
	try {
		const result = await fetchFinanceSummary(params)
		return { result, sent: queryGraphql.mock.calls }
	} catch (error) {
		return {
			rejected: `${error.constructor.name}: ${error.message}`,
			sent: queryGraphql.mock.calls,
		}
	}
}

describe('fetchFinanceSummary (golden)', () => {
	it('matches the recorded results', async () => {
		const actual = {}
		for (const [p, params] of Object.entries(PARAMS)) {
			for (const [a, answer] of Object.entries(ANSWERS)) {
				queryGraphql.mockReset()
				queryGraphql.mockResolvedValue(answer)
				actual[`${p} | ${a}`] = await outcome(params)
			}
			for (const [e, error] of Object.entries(ERRORS)) {
				queryGraphql.mockReset()
				queryGraphql.mockRejectedValue(error)
				actual[`${p} | ${e}`] = await outcome(params)
			}
		}
		if (process.env.RECORD_GOLDEN) {
			fs.writeFileSync(FIXTURE, JSON.stringify(actual, null, '\t') + '\n')
		}
		const expected = JSON.parse(fs.readFileSync(FIXTURE, 'utf8'))
		for (const key of Object.keys(expected)) {
			expect(actual[key], key).toEqual(expected[key])
		}
		expect(Object.keys(actual)).toEqual(Object.keys(expected))
	})
})
