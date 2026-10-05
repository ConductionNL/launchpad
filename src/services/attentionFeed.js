/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The attention feed's browser half (openspec/specs/attention-feed). Pure
 * functions plus one loader: no component state lives here, so every rule can
 * be tested without mounting anything.
 *
 * A source is what an app declared in its `appinfo/attention.json`, as
 * `GET /api/attention/sources` returns it. For each one the feed asks
 * OpenRegister for a count as the signed-in user, decides whether it needs
 * attention, and builds the link into the app FROM THE SAME FILTER, so the
 * number and the list it opens cannot drift (REQ-ATT-003).
 *
 * A count that cannot run is a FAILED entry. It is never a zero: a zero says
 * "nothing to do", and that is the one thing a failure must not say
 * (REQ-ATT-005).
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/** Most urgent first. */
export const SEVERITIES = ['error', 'warning', 'info']

const DAY_TOKEN = /^@today([+-])(\d+)d$/

/**
 * Format a date as local `YYYY-MM-DD`.
 *
 * @param {Date} date The date.
 * @return {string} The date-only string.
 */
function ymd(date) {
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${date.getFullYear()}-${month}-${day}`
}

/**
 * Resolve one filter value. A value that starts with `@` MUST be a token this
 * function knows; anything else throws, so an unknown token fails its source
 * instead of being sent to OpenRegister as a literal (REQ-ATT-003).
 *
 * @param {unknown} value The filter value.
 * @param {{userId: string, now: Date}} ctx Who asks, and when.
 * @return {unknown} The resolved value.
 * @spec openspec/specs/attention-feed/spec.md#req-att-003
 */
export function resolveToken(value, ctx) {
	if (typeof value !== 'string' || value.charAt(0) !== '@') {
		return value
	}
	const now = ctx.now
	if (value === '@me') {
		if (!ctx.userId) {
			throw new Error('@me needs a signed-in user')
		}
		return ctx.userId
	}
	if (value === '@now') {
		return now.toISOString()
	}
	if (value === '@today') {
		return ymd(now)
	}
	const delta = DAY_TOKEN.exec(value)
	if (delta) {
		const days = Number(delta[2]) * (delta[1] === '-' ? -1 : 1)
		return ymd(new Date(now.getFullYear(), now.getMonth(), now.getDate() + days))
	}
	if (value === '@monthStart') {
		return ymd(new Date(now.getFullYear(), now.getMonth(), 1))
	}
	if (value === '@quarterStart') {
		return ymd(new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1))
	}
	if (value === '@yearStart') {
		return ymd(new Date(now.getFullYear(), 0, 1))
	}
	throw new Error(`unknown token ${value}`)
}

/**
 * Write a flat filter as query pairs, in the filter's own key order. A list
 * becomes repeated `key[]` pairs, the form OpenRegister reads as an IN-list.
 * `resolve` is applied to every value: the count passes the token resolver,
 * the link passes nothing.
 *
 * @param {object} filter The declared filter.
 * @param {Function} [resolve] Maps each value before it is written.
 * @return {Array<[string, string]>} The pairs.
 * @spec openspec/specs/attention-feed/spec.md#req-att-003
 */
export function filterPairs(filter, resolve = (value) => value) {
	const pairs = []
	for (const [key, value] of Object.entries(filter || {})) {
		if (Array.isArray(value)) {
			for (const entry of value) {
				pairs.push([`${key}[]`, String(resolve(entry))])
			}
		} else {
			pairs.push([key, String(resolve(value))])
		}
	}
	return pairs
}

/**
 * Join pairs into a query string. Brackets in keys and `@` in values stay
 * readable (`deadline[lt]=@today%2B1d`); everything else is percent-encoded,
 * `+` included, because a bare `+` in a query reads as a space.
 *
 * @param {Array<[string, string]>} pairs The pairs.
 * @return {string} `a=b&c=d`, or an empty string.
 * @spec openspec/specs/attention-feed/spec.md#req-att-003
 */
export function toQuery(pairs) {
	return pairs
		.map(([key, value]) => `${encodeURIComponent(key).replace(/%5B/g, '[').replace(/%5D/g, ']')}=${encodeURIComponent(value).replace(/%40/g, '@')}`)
		.join('&')
}

/**
 * The OpenRegister request that counts a source, tokens resolved.
 *
 * @param {object} source A source from the sources endpoint.
 * @param {{userId: string, now: Date}} ctx Who asks, and when.
 * @return {string} App-relative URL, before `generateUrl`.
 * @spec openspec/specs/attention-feed/spec.md#req-att-003
 */
export function countPath(source, ctx) {
	const { register, schema, filter } = source.source
	const pairs = filterPairs(filter, (value) => resolveToken(value, ctx))
	pairs.push(['_limit', '1'])
	return `/apps/openregister/api/objects/${encodeURIComponent(register)}/${encodeURIComponent(schema)}?${toQuery(pairs)}`
}

/**
 * The link into the owning app: the declared path with the SAME filter as its
 * query, tokens left as written, which is what the apps' own links carry.
 *
 * @param {object} source A source from the sources endpoint.
 * @return {string} App-relative URL, before `generateUrl`.
 * @spec openspec/specs/attention-feed/spec.md#req-att-003
 */
export function actionPath(source) {
	const query = toQuery(filterPairs(source.source.filter))
	return query === '' ? source.action.path : `${source.action.path}?${query}`
}

/**
 * Whether a count needs attention under the source's rule.
 *
 * @param {number} count The count.
 * @param {string} op `gt`, `gte`, `lt`, `lte`, `eq` or `neq`.
 * @param {number} value The right-hand side.
 * @return {boolean} True when the item should be shown.
 * @spec openspec/specs/attention-feed/spec.md#req-att-004
 */
export function needsAttention(count, op, value) {
	switch (op) {
	case 'gt': return count > value
	case 'gte': return count >= value
	case 'lt': return count < value
	case 'lte': return count <= value
	case 'eq': return count === value
	case 'neq': return count !== value
	default: throw new Error(`unknown operator ${op}`)
	}
}

/**
 * Order the entries that need attention: severity, then the higher count,
 * then app name, then item id, so the order is stable.
 *
 * @param {Array<object>} entries Entries with `severity`, `count`, `appName`, `id`.
 * @return {Array<object>} A sorted copy.
 * @spec openspec/specs/attention-feed/spec.md#req-att-004
 */
export function rank(entries) {
	return [...entries].sort((a, b) =>
		SEVERITIES.indexOf(a.severity) - SEVERITIES.indexOf(b.severity)
		|| b.count - a.count
		|| a.appName.localeCompare(b.appName)
		|| a.id.localeCompare(b.id),
	)
}

/**
 * Count one source. Resolves to an entry that is either checked (`count`,
 * `attention`) or failed (`failed: true`). It never rejects, so one source
 * cannot take the others down, and it never turns a failure into a zero.
 *
 * @param {object} source A source from the sources endpoint.
 * @param {{userId: string, now: Date}} ctx Who asks, and when.
 * @return {Promise<object>} The entry.
 * @spec openspec/specs/attention-feed/spec.md#req-att-005
 */
export async function checkSource(source, ctx) {
	const entry = {
		key: `${source.appId}/${source.id}`,
		id: source.id,
		appId: source.appId,
		appName: source.appName,
		title: source.title,
		severity: source.severity,
		actionLabel: source.action.label,
		href: generateUrl(actionPath(source)),
	}
	try {
		const { data } = await axios.get(generateUrl(countPath(source, ctx)))
		const total = data?.total
		if (typeof total !== 'number' || !Number.isFinite(total)) {
			throw new Error('the answer has no numeric total')
		}
		return {
			...entry,
			failed: false,
			count: total,
			attention: needsAttention(total, source.op, source.value),
			reason: source.reason.replace(/\{value\}/g, String(total)),
		}
	} catch (error) {
		return { ...entry, failed: true, error: error?.message || String(error) }
	}
}

/**
 * Load the whole feed: the declared sources, each one counted.
 *
 * Rejects only when the sources endpoint itself fails; the widget then says
 * the list could not be loaded.
 *
 * @param {{now?: Date}} [ctx] When (default now). Who asks comes from the endpoint.
 * @return {Promise<{items: Array<object>, failedApps: Array<string>, sourceCount: number}>} The ranked items that need attention, the names of apps whose count failed or whose declaration is invalid, and how many sources were declared.
 * @spec openspec/specs/attention-feed/spec.md#req-att-005
 */
export async function loadAttentionFeed(ctx = {}) {
	const { data } = await axios.get(generateUrl('/apps/launchpad/api/attention/sources'))
	const context = { userId: data?.userId || '', now: ctx.now || new Date() }
	const sources = Array.isArray(data?.sources) ? data.sources : []
	const invalid = Array.isArray(data?.invalid) ? data.invalid : []

	const entries = await Promise.all(sources.map((source) => checkSource(source, context)))

	const failedApps = [
		...entries.filter((entry) => entry.failed).map((entry) => entry.appName),
		...invalid.map((entry) => entry.appName || entry.appId),
	]

	return {
		items: rank(entries.filter((entry) => !entry.failed && entry.attention)),
		failedApps: [...new Set(failedApps)].sort((a, b) => a.localeCompare(b)),
		sourceCount: sources.length + invalid.length,
	}
}
