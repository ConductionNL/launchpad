/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Data-source adapters bridging the shared @conduction/nextcloud-vue data
 * widgets (which are app-agnostic — they pull data via injected `cn*Source`
 * objects or `*Endpoint`/`apiBase` props) to launchpad's own endpoints and
 * services.
 *
 * Used by both WidgetRenderer (top-level placements) and the container's
 * ContainerChild (nested placements), so nested data widgets get the same
 * working data sources.
 */

import { generateUrl } from '@nextcloud/router'
import {
	fetchFinanceSummary,
	fetchVendorCommitments,
	fetchSpendNarrative,
	resolveDeepLink,
} from './spendAnalytics.js'

/**
 * Adapter for `CnPeopleWidget` (`cnPeopleSource.fetchPeople`). Maps to
 * launchpad's `/apps/launchpad/api/people` and returns `{ users, total, hasMore }`.
 * Axios is lazy-imported (keeps it out of the vitest css-no-op transform path).
 *
 * @param {object} args `{ offset, limit, filters, excludeDisabled, sortBy }`.
 * @return {Promise<{users: object[], total: number, hasMore: boolean}>} page.
 */
export async function fetchPeoplePage(args = {}) {
	const [{ default: axios }, { generateUrl: genUrl }] = await Promise.all([
		import('@nextcloud/axios'),
		import('@nextcloud/router'),
	])
	const params = new URLSearchParams()
	if (args.filters !== undefined) {
		params.append('filters', JSON.stringify(args.filters || []))
	}
	if (args.excludeDisabled !== undefined) {
		params.append('excludeDisabled', args.excludeDisabled ? '1' : '0')
	}
	if (args.sortBy) {
		params.append('sortBy', String(args.sortBy))
	}
	params.append('limit', String(args.limit ?? 50))
	params.append('offset', String(args.offset ?? 0))
	const url = `${genUrl('/apps/launchpad/api/people')}?${params.toString()}`
	const response = await axios.get(url)
	const data = response?.data || {}
	return {
		users: Array.isArray(data.users) ? data.users : [],
		total: typeof data.total === 'number' ? data.total : 0,
		hasMore: data.hasMore === true,
	}
}

/**
 * Adapter for `CnCalendarWidget` (`cnCalendarSource.fetchEvents`). Maps the
 * `{ from, to }` window to launchpad's per-placement calendar endpoint.
 *
 * @param {string|number} placementId the widget placement id.
 * @param {object} args `{ from, to }` ISO date strings.
 * @return {Promise<{events: object[], failures: object[]}>} the events payload.
 */
export async function fetchCalendarEvents(placementId, args = {}) {
	const [{ default: axios }, { generateUrl: genUrl }] = await Promise.all([
		import('@nextcloud/axios'),
		import('@nextcloud/router'),
	])
	const url = genUrl('/apps/launchpad/api/widgets/calendar/{placementId}/events', { placementId })
	const response = await axios.get(url, { params: { from: args.from, to: args.to } })
	const data = response?.data || {}
	return {
		events: Array.isArray(data.events) ? data.events : [],
		failures: Array.isArray(data.failures) ? data.failures : [],
	}
}

/**
 * Build the `provide()` map of data-source adapters for the nc-vue data
 * widgets. Calendar is placement-scoped, so the caller passes a getter for the
 * current placement id.
 *
 * @param {() => (string|number|undefined)} getPlacementId returns the placement id.
 * @return {object} the injected `cn*Source` adapters.
 */
export function buildWidgetDataProvide(getPlacementId) {
	return {
		cnPeopleSource: { fetchPeople: fetchPeoplePage },
		cnCalendarSource: { fetchEvents: (args) => fetchCalendarEvents(getPlacementId(), args) },
		cnSpendAnalyticsSource: {
			fetchSummary: fetchFinanceSummary,
			fetchVendorCommitments,
			fetchNarrative: fetchSpendNarrative,
			resolveDeepLink,
		},
	}
}

/**
 * Per-widget-type extra props for the nc-vue renderers that take a prop rather
 * than an injection: News' `itemsEndpoint` builder and Files' `apiBase`.
 *
 * @param {string} widgetId the placement widget type.
 * @return {object} extra props to v-bind onto the renderer (empty for most types).
 */
export function buildRendererExtraProps(widgetId) {
	if (widgetId === 'news') {
		return {
			itemsEndpoint: (placementId) => generateUrl(
				'/apps/launchpad/api/widgets/news/{placementId}/items',
				{ placementId },
			),
		}
	}
	if (widgetId === 'files') {
		return { apiBase: '/apps/launchpad' }
	}
	return {}
}
