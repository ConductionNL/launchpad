// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * The live count of an icon-only app tile (REQ-ICT-002), read from the
 * tile's `content.countSource`:
 *
 * - `{type: 'register', register, schema, filter}`: the OpenRegister objects
 *   the viewer may read that match the filter, asked with the viewer's own
 *   session; a filter value `@me` stands for the viewer. A source with a
 *   register and a schema and no type is a register source.
 * - `{type: 'nc-widget', widgetId}`: the items a Nextcloud dashboard widget
 *   (API v2) returns for the viewer.
 *
 * No app is named here. A source that does not answer (app missing, refused,
 * offline) counts as no count: the tile shows no pill and no error. Each
 * source is asked at most once a minute, however many tiles name it.
 *
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

/** How long a count is reused, in milliseconds. */
export const COUNT_TTL_MS = 60 * 1000

/** @type {Map<string, {at: number, value: Promise<number|null>}>} */
const cache = new Map()

/**
 * The kind of a count source, or null when it is not one.
 *
 * @param {object|null} source The count source.
 * @return {'register'|'nc-widget'|null}
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
 */
export function sourceType(source) {
	if (!source || typeof source !== 'object') {
		return null
	}
	if (source.type === 'nc-widget') {
		return typeof source.widgetId === 'string' && source.widgetId !== '' ? 'nc-widget' : null
	}
	if (source.type === undefined || source.type === 'register') {
		return source.register && source.schema ? 'register' : null
	}
	return null
}

/**
 * The query of a register source, with `@me` replaced by the viewer.
 *
 * @param {object} filter The source's filter.
 * @param {string} uid The viewer.
 * @return {object} the query parameters.
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
 */
export function registerQuery(filter, uid) {
	const params = { _limit: 1 }
	for (const [key, value] of Object.entries(filter || {})) {
		params[key] = value === '@me' ? uid : value
	}
	return params
}

/**
 * The count in a register answer: its total, else the number of results.
 *
 * @param {object} data The answer.
 * @return {number|null}
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
 */
export function registerTotal(data) {
	const total = Number(data?.total)
	if (Number.isFinite(total) && total >= 0) {
		return total
	}
	return Array.isArray(data?.results) ? data.results.length : null
}

/**
 * The count in a dashboard API v2 answer for one widget.
 *
 * @param {object} data The OCS answer.
 * @param {string} widgetId The widget.
 * @return {number|null}
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
 */
export function widgetItemsCount(data, widgetId) {
	const entry = data?.ocs?.data?.[widgetId]
	const items = Array.isArray(entry) ? entry : entry?.items
	return Array.isArray(items) ? items.length : null
}

/**
 * Ask the source for its count.
 *
 * @param {object} source The count source.
 * @param {string} uid The viewer.
 * @return {Promise<number|null>} the count, null when the source does not answer.
 */
async function fetchCount(source, uid) {
	try {
		if (sourceType(source) === 'register') {
			const url = generateUrl('/apps/openregister/api/objects/{register}/{schema}', {
				register: source.register,
				schema: source.schema,
			})
			const response = await axios.get(url, { params: registerQuery(source.filter, uid) })
			return registerTotal(response?.data)
		}
		const url = generateOcsUrl('/apps/dashboard/api/v2/widget-items')
		const response = await axios.get(url, { params: { 'widgets[]': source.widgetId } })
		return widgetItemsCount(response?.data, source.widgetId)
	} catch {
		return null
	}
}

/**
 * The count of a tile's source, reusing an answer younger than a minute.
 *
 * @param {object|null} source The count source.
 * @param {string} uid The viewer.
 * @param {number} [now] The current time in milliseconds.
 * @return {Promise<number|null>} the count, null for none.
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
 */
export function resolveTileCount(source, uid, now = Date.now()) {
	if (sourceType(source) === null) {
		return Promise.resolve(null)
	}
	const key = JSON.stringify([uid, source])
	const hit = cache.get(key)
	if (hit && now - hit.at < COUNT_TTL_MS) {
		return hit.value
	}
	const value = fetchCount(source, uid)
	cache.set(key, { at: now, value })
	return value
}

/**
 * The pill text for a count: '' for none or 0, "99+" above 99.
 *
 * @param {number|null} count The count.
 * @return {string}
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
 */
export function countLabel(count) {
	const value = Number(count)
	if (count === null || !Number.isFinite(value) || value <= 0) {
		return ''
	}
	return value > 99 ? '99+' : String(Math.floor(value))
}

/**
 * Forget every cached count (tests, and a viewer who switches account).
 *
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
 */
export function clearTileCounts() {
	cache.clear()
}
