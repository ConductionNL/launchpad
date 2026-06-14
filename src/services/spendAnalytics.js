/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Spend-analytics runtime data layer (REQ-SAW-004, REQ-SAW-005).
 *
 * Fetches procurement + financial spend data live at render time from
 * sibling apps via the shared GraphQL client (`graphqlClient.js`) and
 * aggregates it into the shapes the renderer consumes. Two sources:
 *  - financeq → `transactions { totalAmount currency }` (summary, trend,
 *    top categories),
 *  - procest  → `cases { vendor totalCommitted }` (top vendors).
 *
 * Each source is fetched independently so a partially-available fleet
 * (financeq present, procest absent) still renders the parts it can —
 * the caller branches on the per-source `available` flag (REQ-SAW-005
 * "partial sibling availability"). This module never throws on a
 * missing sibling app; it returns `{available: false, reason}` so the
 * widget renders an empty-state, never a 500.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { queryGraphql, GraphQLSourceError } from './graphqlClient.js'

/** Sibling app ids this widget reads from (REQ-SAW-004). */
export const SPEND_SOURCES = Object.freeze({
	FINANCE: 'financeq',
	PROCEST: 'procest',
})

/**
 * financeq spend query — transactions in the selected period window.
 * The `period` filter is passed as a variable (REQ-SAW-004 scenario:
 * "the query MUST include the period filter as a variable").
 */
export const FINANCE_SPEND_QUERY = `query SpendSummary($period: String!, $categoryIds: [String!], $departmentIds: [String!]) {
  transactions(period: $period, categoryIds: $categoryIds, departmentIds: $departmentIds)
  {
    totalAmount
    currency
    category
    bookedAt
  }
}`

/**
 * procest vendor commitment query — committed spend per vendor on cases.
 */
export const PROCEST_VENDOR_QUERY = `query VendorCommitments($period: String!, $vendorIds: [String!]) {
  cases(period: $period, vendorIds: $vendorIds)
  {
    vendor
    totalCommitted
    caseId
  }
}`

/**
 * Map a thrown {@see GraphQLSourceError} to a stable per-source
 * unavailable envelope. Re-throws anything that is not a source error
 * so genuine programming bugs surface in dev.
 *
 * @param {unknown} err  the caught error
 * @param {string}  app  the sibling app id
 * @return {{available: false, reason: string, code: string}}
 */
function toUnavailable(err, app) {
	if (err instanceof GraphQLSourceError) {
		return { available: false, reason: err.message, code: err.code }
	}
	throw err
}

/**
 * Fetch + aggregate the financeq summary for a placement.
 *
 * @param {object} params               aggregation parameters
 * @param {string} params.period        one of month|quarter|ytd|fy
 * @param {string[]} [params.categoryIds]   CPV / category filter
 * @param {string[]} [params.departmentIds] cost-centre filter
 * @return {Promise<object>} `{available, total, currency, byCategory, trend}`
 *   or `{available:false, reason, code}` when financeq is absent/empty
 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
 */
export async function fetchFinanceSummary({ period, categoryIds = [], departmentIds = [] }) {
	let data
	try {
		data = await queryGraphql({
			app: SPEND_SOURCES.FINANCE,
			query: FINANCE_SPEND_QUERY,
			variables: { period, categoryIds, departmentIds },
		})
	} catch (err) {
		return toUnavailable(err, SPEND_SOURCES.FINANCE)
	}

	const rows = Array.isArray(data?.transactions) ? data.transactions : []
	if (rows.length === 0) {
		return { available: true, empty: true, total: 0, currency: 'EUR', byCategory: [], trend: [] }
	}

	const currency = rows.find((row) => typeof row.currency === 'string')?.currency || 'EUR'
	const total = rows.reduce((sum, row) => sum + (Number(row.totalAmount) || 0), 0)

	// Aggregate by category (top categories view).
	const byCategoryMap = new Map()
	for (const row of rows) {
		const key = typeof row.category === 'string' && row.category ? row.category : 'uncategorised'
		byCategoryMap.set(key, (byCategoryMap.get(key) || 0) + (Number(row.totalAmount) || 0))
	}
	const byCategory = [...byCategoryMap.entries()]
		.map(([category, amount]) => ({ category, amount }))
		.sort((a, b) => b.amount - a.amount)

	// Aggregate by booking month (trend view).
	const trendMap = new Map()
	for (const row of rows) {
		const month = typeof row.bookedAt === 'string' ? row.bookedAt.slice(0, 7) : 'unknown'
		trendMap.set(month, (trendMap.get(month) || 0) + (Number(row.totalAmount) || 0))
	}
	const trend = [...trendMap.entries()]
		.map(([month, amount]) => ({ month, amount }))
		.sort((a, b) => a.month.localeCompare(b.month))

	return { available: true, empty: false, total, currency, byCategory, trend }
}

/**
 * Fetch + aggregate the procest vendor commitments for a placement.
 *
 * @param {object} params             aggregation parameters
 * @param {string} params.period      one of month|quarter|ytd|fy
 * @param {string[]} [params.vendorIds]  supplier filter
 * @return {Promise<object>} `{available, topVendors}` or
 *   `{available:false, reason, code}` when procest is absent
 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
 */
export async function fetchVendorCommitments({ period, vendorIds = [] }) {
	let data
	try {
		data = await queryGraphql({
			app: SPEND_SOURCES.PROCEST,
			query: PROCEST_VENDOR_QUERY,
			variables: { period, vendorIds },
		})
	} catch (err) {
		return toUnavailable(err, SPEND_SOURCES.PROCEST)
	}

	const rows = Array.isArray(data?.cases) ? data.cases : []
	if (rows.length === 0) {
		return { available: true, empty: true, topVendors: [] }
	}

	const vendorMap = new Map()
	for (const row of rows) {
		const vendor = typeof row.vendor === 'string' && row.vendor ? row.vendor : 'unknown'
		vendorMap.set(vendor, (vendorMap.get(vendor) || 0) + (Number(row.totalCommitted) || 0))
	}
	const topVendors = [...vendorMap.entries()]
		.map(([vendor, committed]) => ({ vendor, committed }))
		.sort((a, b) => b.committed - a.committed)

	return { available: true, empty: false, topVendors }
}

/**
 * The openconnector LLM source alias the AI-insight path routes through
 * (REQ-SAW-006). Per `reference_llphant-ollama-think-false`, this is the
 * local Ollama + Qwen source registered in openconnector as `local-llm`
 * — launchpad never talks to Ollama directly and imports no LLM SDK.
 */
export const LLM_SOURCE_ALIAS = 'local-llm'

/**
 * Request an AI-generated spend narrative through openconnector's LLM
 * source endpoint (REQ-SAW-006). launchpad MUST NOT call `localhost:11434`
 * or any Ollama / hosted-LLM URL directly — the request always targets
 * openconnector, which owns the model hand-off. When openconnector (or
 * the `local-llm` source) is absent the call 404s and we surface a typed
 * unavailable envelope so the widget disables the AI panel gracefully
 * (REQ-SAW-006 scenario "openconnector absent disables AI panel").
 *
 * @param {object} params              inference parameters
 * @param {object} params.summary      the spend summary used as context
 * @param {number} [params.timeoutMs]  per-call timeout
 * @return {Promise<object>} `{available:true, narrative}` or
 *   `{available:false, reason, code}`
 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
 */
export async function fetchSpendNarrative({ summary, timeoutMs = 5000 }) {
	const url = generateUrl('/apps/openconnector/api/sources/{source}/call', { source: LLM_SOURCE_ALIAS })
	try {
		const response = await axios.post(
			url,
			{
				// Conduction local-LLM defaults per reference_llphant-ollama-think-false.
				think: false,
				keep_alive: -1,
				context: { spendSummary: summary },
			},
			{ timeout: timeoutMs },
		)
		const narrative = response?.data?.narrative ?? response?.data?.message ?? ''
		return { available: true, narrative: String(narrative) }
	} catch (err) {
		const status = err?.response?.status
		if (status === 404) {
			return { available: false, reason: 'openconnector local-llm source is not configured', code: 'not_installed' }
		}
		if (err?.code === 'ECONNABORTED') {
			return { available: false, reason: 'The LLM source did not respond in time', code: 'timeout' }
		}
		return { available: false, reason: err?.message || 'Inference failed', code: 'network_error' }
	}
}

/**
 * Resolve a deep-link to a sibling app's detail surface (REQ-SAW-007).
 * launchpad MUST NOT mirror sibling-app pages — clicking a row navigates
 * to the owning app's detail URL, resolved here via the Nextcloud route
 * helper (the OR deep-link registry contract). Returns an absolute path
 * the caller assigns to `window.location` / an anchor `href`.
 *
 * @param {string} app    the owning sibling app id (e.g. `financeq`)
 * @param {string} kind   the entity kind segment (e.g. `transaction`)
 * @param {string} id     the entity identifier
 * @return {string} the sibling app's detail URL
 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
 */
export function resolveDeepLink(app, kind, id) {
	return generateUrl('/apps/{app}/{kind}/{id}', { app, kind, id: String(id) })
}
