/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * useDashboardFilter — the URL form of a dashboard's filter state
 * (drill-down-cross-widget-filter, REQ-CWF-003).
 *
 * The whole filter state travels in ONE query parameter, `?f=<base64url>`
 * (design D4). Its payload is a JSON array of clauses with short keys, so a
 * dashboard with ten clauses stays well under the 2 KB a URL can safely carry:
 *
 *     d  dimension        e.g. "gemeenteCode", "period.month"
 *     o  operator         eq | in | between | gte | lte | contains | notNull
 *     v  value            scalar, array, or [low, high]; absent for notNull
 *     s  source           userExplicit | widgetClick | urlBootstrap | savedView | parentScope
 *     w  sourceWidgetId   only when set
 *     p  pinned           only when true
 *
 * base64url (RFC 4648 §5, no padding) needs no percent-encoding, so the
 * parameter survives copy and paste. A malformed parameter never breaks the
 * page: it reads as an empty filter state with one console warning.
 */

import { logger } from '../utils/logger.js'

/**
 * The query parameter that carries the filter state.
 *
 * @type {string}
 */
export const FILTER_PARAM = 'f'

/**
 * The comparison operators a clause may use.
 *
 * @type {string[]}
 */
export const OPERATORS = ['eq', 'in', 'between', 'gte', 'lte', 'contains', 'notNull']

/**
 * Where a clause can come from.
 *
 * @type {string[]}
 */
export const SOURCES = ['userExplicit', 'widgetClick', 'urlBootstrap', 'savedView', 'parentScope']

/**
 * Base64url-encode a UTF-8 string, without padding.
 *
 * @param {string} text The text.
 * @return {string}
 */
function toBase64Url(text) {
	const bytes = new TextEncoder().encode(text)
	let binary = ''
	bytes.forEach((byte) => {
		binary += String.fromCharCode(byte)
	})
	return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

/**
 * Decode a base64url string to UTF-8 text.
 *
 * @param {string} encoded The base64url text.
 * @return {string}
 * @throws {Error} When the text is not base64url or not UTF-8.
 */
function fromBase64Url(encoded) {
	if (!/^[A-Za-z0-9_-]*$/.test(encoded)) {
		throw new Error('not base64url')
	}
	const base64 = encoded.replace(/-/g, '+').replace(/_/g, '/')
	const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4)
	const binary = atob(padded)
	const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0))
	return new TextDecoder('utf-8', { fatal: true }).decode(bytes)
}

/**
 * A clause in its short URL form.
 *
 * @param {object} clause The clause.
 * @return {object}
 */
function compact(clause) {
	const short = { d: clause.dimension, o: clause.operator }
	if (clause.operator !== 'notNull') {
		short.v = clause.value
	}
	if (clause.source) {
		short.s = clause.source
	}
	if (clause.sourceWidgetId) {
		short.w = clause.sourceWidgetId
	}
	if (clause.pinned === true) {
		short.p = true
	}
	return short
}

/**
 * Whether a value is a plain object (not null, not a list).
 *
 * @param {object} short The compact clause.
 * @return {boolean}
 */
function isPlainObject(short) {
	return Boolean(short) && typeof short === 'object' && !Array.isArray(short)
}

/**
 * Whether a compact clause names a dimension and a known operator.
 *
 * @param {object} short The compact clause.
 * @return {boolean}
 */
function hasDimensionAndOperator(short) {
	return typeof short.d === 'string' && short.d !== '' && OPERATORS.includes(short.o)
}

/**
 * Whether a compact clause carries the value its operator needs and a known source.
 *
 * @param {object} short The compact clause.
 * @return {boolean}
 */
function hasValueAndSource(short) {
	return (short.o === 'notNull' || 'v' in short) && (short.s === undefined || SOURCES.includes(short.s))
}

/**
 * Whether a compact clause from the URL is well formed.
 *
 * @param {object} short The compact clause.
 * @return {boolean}
 */
function isWellFormed(short) {
	return isPlainObject(short) && hasDimensionAndOperator(short) && hasValueAndSource(short)
}

/**
 * The widget a compact clause came from, or null.
 *
 * @param {object} short The compact clause.
 * @return {string|null}
 */
function sourceWidgetOf(short) {
	return typeof short.w === 'string' && short.w !== '' ? short.w : null
}

/**
 * A clause read back from its short URL form, or null when it is not one.
 *
 * @param {unknown} short The short form.
 * @return {object|null}
 */
function expand(short) {
	if (!isWellFormed(short)) {
		return null
	}
	const clause = {
		dimension: short.d,
		operator: short.o,
		source: short.s || 'urlBootstrap',
		sourceWidgetId: sourceWidgetOf(short),
		pinned: short.p === true,
	}
	if (short.o !== 'notNull') {
		clause.value = short.v
	}
	return clause
}

/**
 * The URL parameter value for a set of clauses; '' when there are none.
 *
 * @param {Array<object>} clauses The active clauses.
 * @return {string}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-003-url-encoding-makes-filtered-views-bookmarkable
 */
export function serializeFilterState(clauses) {
	const list = (Array.isArray(clauses) ? clauses : []).filter((clause) => clause && typeof clause.dimension === 'string')
	if (list.length === 0) {
		return ''
	}
	return toBase64Url(JSON.stringify(list.map(compact)))
}

/**
 * The clauses an encoded filter parameter holds.
 *
 * @param {string} param The URL parameter.
 * @return {Array<object>} The clauses.
 * @throws {Error} When it is not a list of well-formed clauses.
 */
function parseClauses(param) {
	const parsed = JSON.parse(fromBase64Url(String(param)))
	if (!Array.isArray(parsed)) {
		throw new Error('not a list of clauses')
	}
	const clauses = parsed.map(expand)
	if (clauses.includes(null)) {
		throw new Error('a clause is malformed')
	}
	return clauses
}

/**
 * The clauses a URL parameter value carries. Anything malformed (not base64url,
 * not JSON, not a list, or a clause without a dimension or with an unknown
 * operator) gives no clauses, an `error`, and one console warning.
 *
 * @param {string|null|undefined} param The `f` parameter value.
 * @return {{clauses: Array<object>, error: string|null}}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-003-url-encoding-makes-filtered-views-bookmarkable
 */
export function deserializeFilterState(param) {
	if (param === null || param === undefined || param === '') {
		return { clauses: [], error: null }
	}
	try {
		return { clauses: parseClauses(param), error: null }
	} catch (error) {
		logger.warn('[launchpad] the filter in the URL could not be read; the dashboard opens unfiltered:', error.message)
		return { clauses: [], error: 'malformed' }
	}
}

/**
 * The composable.
 *
 * @return {{serializeFilterState: typeof serializeFilterState, deserializeFilterState: typeof deserializeFilterState, FILTER_PARAM: string}}
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-003-url-encoding-makes-filtered-views-bookmarkable
 */
export function useDashboardFilter() {
	return { serializeFilterState, deserializeFilterState, FILTER_PARAM }
}
