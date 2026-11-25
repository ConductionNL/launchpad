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

import {
	fetchFinanceSummary,
	fetchSpendNarrative,
	fetchVendorCommitments,
	resolveDeepLink,
} from './spendAnalytics.js'

/**
 * Adapter for `CnPeopleWidget` (`cnPeopleSource.fetchPeople`). Maps to
 * launchpad's `/apps/launchpad/api/people` and returns `{ users, total, hasMore }`.
 * Axios is lazy-imported (keeps it out of the vitest css-no-op transform path).
 *
 * @param {object} args `{ offset, limit, filters, excludeDisabled, sortBy, showBirthdays, q }`;
 *   `q` searches the whole directory by name, email and profile fields (REQ-PEX-003).
 * @return {Promise<{users: object[], total: number, hasMore: boolean}>} page.
 * @spec openspec/specs/people-widget/spec.md
 */
export async function fetchPeoplePage(args = {}) {
	const [{ default: axios }, { generateUrl: genUrl }] = await Promise.all([
		import('@nextcloud/axios'),
		import('@nextcloud/router'),
	])
	const url = `${genUrl('/apps/launchpad/api/people')}?${peopleQuery(args)}`
	const response = await axios.get(url)
	return peoplePageOf(response)
}

/**
 * The people endpoint's query string: the filters, the disabled-account
 * switch, the sort key and the search text only when given, then the page
 * size and offset.
 *
 * @param {object} args `{ offset, limit, filters, excludeDisabled, sortBy, showBirthdays, q }`.
 * @return {string} The query string.
 */
function peopleQuery(args) {
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
	if (args.showBirthdays !== undefined) {
		params.append('showBirthdays', args.showBirthdays ? '1' : '0')
	}
	if (args.q) {
		params.append('q', String(args.q))
	}
	params.append('limit', String(args.limit ?? 50))
	params.append('offset', String(args.offset ?? 0))
	return params.toString()
}

/**
 * The page of people in a people endpoint response, with empty defaults.
 *
 * @param {object} response The axios response.
 * @return {{users: object[], total: number, hasMore: boolean}} The page.
 */
function peoplePageOf(response) {
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
 * @spec openspec/specs/calendar-widget/spec.md#requirement-req-cal-003-events-endpoint
 */
export async function fetchCalendarEvents(placementId, args = {}) {
	const [{ default: axios }, { generateUrl: genUrl }] = await Promise.all([
		import('@nextcloud/axios'),
		import('@nextcloud/router'),
	])
	const url = genUrl('/apps/launchpad/api/widgets/calendar/{placementId}/events', {
		placementId,
	})
	const response = await axios.get(url, {
		params: { from: args.from, to: args.to },
	})
	const data = response?.data || {}
	return {
		events: Array.isArray(data.events) ? data.events : [],
		failures: Array.isArray(data.failures) ? data.failures : [],
	}
}

/**
 * The new colleagues for CnPeopleWidget's `selectionMode: 'new'`:
 * `GET /apps/launchpad/api/people/new?since=&until=`, answered as
 * `{ configured, users }` (each with id, displayName, avatarUrl, role,
 * organisation, startDate, starts). A failed read throws, so the widget shows
 * its error state.
 *
 * @param {object} [args] `{ since, until }` in days.
 * @return {Promise<{configured: boolean, users: Array<object>}>}
 * @spec openspec/changes/archive/2026-11-25-people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export async function fetchNewPeople(args = {}) {
	const [{ default: axios }, { generateUrl: genUrl }] = await Promise.all([
		import('@nextcloud/axios'),
		import('@nextcloud/router'),
	])
	const response = await axios.get(genUrl('/apps/launchpad/api/people/new'), {
		params: { since: args.since, until: args.until },
	})
	const data = response?.data || {}
	return {
		configured: data.configured === true,
		users: Array.isArray(data.users) ? data.users : [],
	}
}

/** The one start-date answer of this page load (a promise), or null. */
let startDateFieldAnswer = null

/**
 * Whether an administrator marked a profile field as the start date, which
 * is when the people widget form offers New colleagues. Asked once per page
 * load; a failed read offers nothing.
 *
 * @return {Promise<boolean>}
 * @spec openspec/changes/archive/2026-11-25-people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export function hasStartDateField() {
	if (startDateFieldAnswer === null) {
		startDateFieldAnswer = fetchNewPeople({ since: 1, until: 0 })
			.then((answer) => answer.configured === true)
			.catch(() => {
				startDateFieldAnswer = null
				return false
			})
	}
	return startDateFieldAnswer
}

/**
 * The people source for places outside a placement (the widget forms):
 * the directory, the new colleagues and the start-date question.
 *
 * @return {object} the `cnPeopleSource` adapter.
 * @spec openspec/changes/archive/2026-11-25-people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export function buildPeopleSource() {
	return {
		fetchPeople: fetchPeoplePage,
		searchPeople: fetchPeoplePage,
		fetchNewPeople,
		hasStartDateField,
	}
}

/**
 * Build the `provide()` map of data-source adapters for the nc-vue data
 * widgets. Calendar is placement-scoped, so the caller passes a getter for the
 * current placement id.
 *
 * @param {() => (string|number|undefined)} getPlacementId returns the placement id.
 * @param {() => (object|undefined)} [getContent] returns the placement content; the people adapter sends its `showBirthdays` switch.
 * @return {object} the injected `cn*Source` adapters.
 * @spec openspec/specs/people-widget/spec.md
 */
export function buildWidgetDataProvide(getPlacementId, getContent = () => ({})) {
	const people = (args) => fetchPeoplePage({
		...args,
		showBirthdays: (getContent() || {}).showBirthdays !== false,
	})
	return {
		cnPeopleSource: {
			fetchPeople: people,
			searchPeople: people,
			fetchNewPeople,
			hasStartDateField,
		},
		cnCalendarSource: {
			fetchEvents: (args) => fetchCalendarEvents(getPlacementId(), args),
		},
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
 * than an injection: Files' `apiBase`.
 *
 * `news` was here too; it now lives in LaunchPad and calls its own endpoint.
 *
 * @param {string} widgetId the placement widget type.
 * @return {object} extra props to v-bind onto the renderer (empty for most types).
 * @spec exclude wiring-only prop mapping — the only case is Files' `apiBase`, which
 * carries no widget-specific requirement of its own; news now fetches via its own
 * prop rather than an extra prop, so REQ-NEWS-003 coverage lives on
 * NewsWidget.loadItems() instead.
 */
export function buildRendererExtraProps(widgetId) {
	if (widgetId === 'files') {
		return { apiBase: '/apps/launchpad' }
	}
	return {}
}
