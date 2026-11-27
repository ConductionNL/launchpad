/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The API reads the workspace page carries in `workspaceApiReads`
 * (`[{path, params, data}]`, answered by the server while it rendered the
 * page). Each one answers the FIRST matching GET once, without a request;
 * every later GET of the same endpoint asks the server as before.
 *
 * Done at the HTTP client so every caller (the notice banner, the reactions
 * bar, the personal layer, each Files widget) keeps its own code path and
 * error handling: a read the page did not carry simply goes out.
 *
 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-3
 */

import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'

const APP_SEGMENT = '/apps/launchpad'

/** @type {Map<string, object|Array|string|null>|null} Carried reads by request key, emptied as they are used. */
let carried = null
let installed = false

/**
 * The key of a GET: its path below `/apps/launchpad` plus its query, with the
 * query's values as strings and its names sorted, so `{limit: 50}` and
 * `?limit=50` meet.
 *
 * @param {string} url The request URL (relative or absolute, may hold a query).
 * @param {object} [params] The request's params.
 * @return {string|null} The key, or null for a URL outside the app.
 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-3
 */
export function requestKey(url, params = {}) {
	const raw = String(url || '')
	const at = raw.indexOf(APP_SEGMENT + '/')
	if (at === -1) {
		return null
	}
	const [path, query = ''] = raw.slice(at + APP_SEGMENT.length).split('?')
	return path + '?' + new URLSearchParams(queryEntries(query, params)).toString()
}

/**
 * A request's query as sorted `[name, value]` pairs: the URL's own query plus
 * its params, values as strings, empty params left out.
 *
 * @param {string} query The query string from the URL.
 * @param {object} params The request's params.
 * @return {Array<Array<string>>} The pairs.
 */
function queryEntries(query, params) {
	const given = Object.entries(params || {})
		.filter(([, value]) => value !== undefined && value !== null)
		.map(([name, value]) => [name, String(value)])
	return [...new URLSearchParams(query).entries(), ...given]
		.sort(([a], [b]) => a.localeCompare(b))
}

/**
 * The carried reads, read from the page once.
 *
 * @return {Map<string, object|Array|string|null>} The reads by key.
 */
function reads() {
	if (carried === null) {
		carried = keyed(pageReads())
	}
	return carried
}

/**
 * The list the page carries, or an empty list.
 *
 * @return {Array<object>} The `{path, params, data}` entries.
 */
function pageReads() {
	try {
		const list = loadState('launchpad', 'workspaceApiReads', [])
		return Array.isArray(list) ? list : []
	} catch {
		return []
	}
}

/**
 * The well-formed entries by request key.
 *
 * @param {Array<object>} list The `{path, params, data}` entries.
 * @return {Map<string, object|Array|string|null>} The reads by key.
 */
function keyed(list) {
	const usable = list.filter((entry) => typeof entry?.path === 'string' && Object.hasOwn(entry, 'data'))
	return new Map(usable.map((entry) => [requestKey(APP_SEGMENT + entry.path, entry.params || {}), entry.data]))
}

/**
 * Take the carried answer of one GET, once.
 *
 * @param {string} url The request URL.
 * @param {object} [params] The request's params.
 * @return {{data: (object|Array|string|null)}|null} The answer, or null when the page did not carry it or it was taken.
 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-3
 */
export function takeCarriedRead(url, params) {
	const key = requestKey(url, params)
	const all = reads()
	if (key === null || !all.has(key)) {
		return null
	}
	const data = all.get(key)
	all.delete(key)
	return { data }
}

/**
 * Answer a GET the page carries without sending it.
 *
 * @param {object} config The axios request config.
 * @return {object} The config, with an adapter that answers from the page when carried.
 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-3
 */
export function answerCarried(config) {
	if (String(config?.method || 'get').toLowerCase() !== 'get') {
		return config
	}
	const hit = takeCarriedRead(config.url, config.params)
	if (hit === null) {
		return config
	}
	config.adapter = () => Promise.resolve({
		data: hit.data,
		status: 200,
		statusText: 'OK',
		headers: {},
		config,
		request: null,
	})
	return config
}

/**
 * Install the carried reads on the app's HTTP client, once.
 *
 * @param {object} [client] The axios instance (default: `@nextcloud/axios`).
 * @return {void}
 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-3
 */
export function installCarriedReads(client = axios) {
	if (installed) {
		return
	}
	installed = true
	client.interceptors.request.use(answerCarried)
}

/**
 * Forget what was read and installed (tests only).
 *
 * @return {void}
 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-4
 */
export function resetCarriedReads() {
	carried = null
	installed = false
}
