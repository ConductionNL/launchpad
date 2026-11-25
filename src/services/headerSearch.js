/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The data side of the top bar's results list (header-search-dropdown,
 * REQ-SRCH-013): it asks Nextcloud's unified search providers for a query,
 * groups their answers per provider with LaunchPad's dashboards first, and
 * builds the shortcut tip. The list itself is `HeaderSearchDropdown.vue`.
 *
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/** The OCS headers the unified search API needs to answer JSON. */
const OCS_CONFIG = { headers: { 'OCS-APIRequest': 'true', Accept: 'application/json' } }

/** LaunchPad's own provider id (REQ-SRCH-001). */
export const LAUNCHPAD_PROVIDER_ID = 'launchpad'

/** The list opens from this many characters on. */
export const MIN_QUERY_LENGTH = 2

/** Results shown per provider group. */
export const PER_GROUP_LIMIT = 5

/**
 * Whether a query is long enough to open the list.
 *
 * @param {string} query The field's value.
 * @return {boolean} True from two characters on (spaces at the ends ignored).
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function isSearchable(query) {
	return typeof query === 'string' && query.trim().length >= MIN_QUERY_LENGTH
}

/**
 * The providers in the order the list shows them: LaunchPad's dashboards
 * first, then by the provider's own order, then by name.
 *
 * @param {Array<{id: string, name?: string, order?: number}>} providers The providers.
 * @return {Array<object>} A sorted copy.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function orderProviders(providers) {
	const list = Array.isArray(providers) ? providers.filter((p) => p && typeof p.id === 'string') : []
	return list.slice().sort((a, b) => {
		const aFirst = a.id === LAUNCHPAD_PROVIDER_ID ? 0 : 1
		const bFirst = b.id === LAUNCHPAD_PROVIDER_ID ? 0 : 1
		if (aFirst !== bFirst) {
			return aFirst - bFirst
		}
		const aOrder = Number.isFinite(a.order) ? a.order : Number.MAX_SAFE_INTEGER
		const bOrder = Number.isFinite(b.order) ? b.order : Number.MAX_SAFE_INTEGER
		if (aOrder !== bOrder) {
			return aOrder - bOrder
		}
		return String(a.name ?? a.id).localeCompare(String(b.name ?? b.id))
	})
}

/**
 * One result as the list shows it: icon, name, one detail line and a link.
 *
 * @param {object} entry A unified search entry.
 * @param {string} providerId The provider that answered it.
 * @param {number} index Its place in the provider's answer.
 * @return {{key: string, title: string, detail: string, icon: string, url: string}} The row.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function toResult(entry, providerId, index) {
	return {
		key: `${providerId}-${index}`,
		title: String(entry?.title ?? ''),
		detail: String(entry?.subline ?? ''),
		icon: String(entry?.thumbnailUrl || entry?.icon || ''),
		url: String(entry?.resourceUrl ?? ''),
	}
}

/**
 * Group the providers' answers: one group per provider that found
 * something, in provider order, each capped at {@link PER_GROUP_LIMIT}.
 *
 * @param {Array<{id: string, name?: string}>} providers The providers, already ordered.
 * @param {Object<string, Array<object>>} entriesByProvider The entries each provider returned, by id.
 * @return {Array<{id: string, name: string, results: Array<object>}>} The groups.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function groupResults(providers, entriesByProvider) {
	const groups = []
	for (const provider of providers) {
		const entries = entriesByProvider?.[provider.id]
		if (!Array.isArray(entries) || entries.length === 0) {
			continue
		}
		groups.push({
			id: provider.id,
			name: String(provider.name ?? provider.id),
			results: entries
				.slice(0, PER_GROUP_LIMIT)
				.map((entry, index) => toResult(entry, provider.id, index))
				.filter((result) => result.title !== ''),
		})
	}
	return groups.filter((group) => group.results.length > 0)
}

/**
 * The first configured search shortcut, for the tip at the foot of the list.
 *
 * @param {Array<{prefix?: string, name?: string}>} shortcuts The admin's shortcuts (REQ-SPX-002).
 * @return {{prefix: string, name: string}|null} The shortcut, or null when none is usable.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function firstShortcut(shortcuts) {
	if (!Array.isArray(shortcuts)) {
		return null
	}
	for (const shortcut of shortcuts) {
		const prefix = String(shortcut?.prefix ?? '').trim()
		const name = String(shortcut?.name ?? '').trim()
		if (prefix !== '' && name !== '') {
			return { prefix, name }
		}
	}
	return null
}

/**
 * The next selected index when an arrow key moves the selection; wraps at
 * both ends, and stays at -1 for an empty list.
 *
 * @param {number} current The selected index.
 * @param {number} delta +1 for Arrow down, -1 for Arrow up.
 * @param {number} count The number of options.
 * @return {number} The new index.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export function moveSelection(current, delta, count) {
	if (count <= 0) {
		return -1
	}
	if (current < 0) {
		return delta > 0 ? 0 : count - 1
	}
	return (((current + delta) % count) + count) % count
}

/**
 * The providers Nextcloud offers on this page.
 *
 * @param {string} route The current route, so providers can rank themselves.
 * @param {AbortSignal} [signal] Cancels the request.
 * @return {Promise<Array<object>>} The providers, or an empty list on failure.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export async function fetchProviders(route, signal) {
	try {
		const response = await axios.get(generateOcsUrl('search/providers'), {
			...OCS_CONFIG,
			params: { from: route },
			signal,
		})
		const data = response?.data?.ocs?.data
		return Array.isArray(data) ? data : []
	} catch {
		return []
	}
}

/**
 * One provider's entries for a query.
 *
 * @param {string} providerId The provider.
 * @param {string} query The query.
 * @param {string} route The current route.
 * @param {AbortSignal} [signal] Cancels the request.
 * @return {Promise<Array<object>>} The entries, or an empty list on failure.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export async function fetchProviderEntries(providerId, query, route, signal) {
	try {
		const response = await axios.get(
			generateOcsUrl('search/providers/{providerId}/search', { providerId }),
			{
				...OCS_CONFIG,
				params: { term: query, from: route, limit: PER_GROUP_LIMIT },
				signal,
			},
		)
		const entries = response?.data?.ocs?.data?.entries
		return Array.isArray(entries) ? entries : []
	} catch {
		return []
	}
}

/**
 * Search every provider for a query and group the answers.
 *
 * @param {string} query The query.
 * @param {object} [options] Options.
 * @param {string} [options.route] The current route (defaults to the location's path).
 * @param {AbortSignal} [options.signal] Cancels the requests.
 * @return {Promise<Array<{id: string, name: string, results: Array<object>}>>} The groups.
 * @spec openspec/changes/archive/2026-11-25-header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
export async function searchAll(query, { route = window.location.pathname, signal } = {}) {
	const providers = orderProviders(await fetchProviders(route, signal))
	const answers = await Promise.all(
		providers.map((provider) => fetchProviderEntries(provider.id, query, route, signal)),
	)
	const entriesByProvider = {}
	providers.forEach((provider, index) => {
		entriesByProvider[provider.id] = answers[index]
	})
	return groupResults(providers, entriesByProvider)
}
