/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The "New colleagues" selection of the people widget (REQ-PPL-014): the
 * people whose start date lies in a window around today, read from
 * `GET /api/people/new` and shaped as a `CnPeopleWidget` page.
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */

import { t } from '@nextcloud/l10n'

/** The selection mode a placement stores for New colleagues. */
export const NEW_SELECTION_MODE = 'new'

/** Days back and ahead, as the server clamps them (NewColleagues.php). */
export const NEW_WINDOWS = Object.freeze({
	since: Object.freeze({ default: 30, min: 1, max: 90 }),
	until: Object.freeze({ default: 14, min: 0, max: 30 }),
})

/** The custom-field key of the "starts on" line. */
export const STARTS_FIELD_KEY = 'lp-starts'

/** The filter field that carries the window into the widget's fetch arguments. */
export const WINDOW_FILTER_FIELD = 'startDateWindow'

const DAY_MS = 86400000

/**
 * One window bound of a placement: its stored number when it is a whole
 * number inside the range, else the default.
 *
 * @param {object} content The placement content.
 * @param {'since'|'until'} bound Which window.
 * @return {number} The days.
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export function windowDays(content, bound) {
	const range = NEW_WINDOWS[bound]
	const stored = (content || {})[bound === 'since' ? 'newSinceDays' : 'newUntilDays']
	if (Number.isInteger(stored) && stored >= range.min && stored <= range.max) {
		return stored
	}
	return range.default
}

/**
 * Whether a placement lists new colleagues.
 *
 * @param {object} content The placement content.
 * @return {boolean}
 */
export function isNewSelection(content) {
	return (content || {}).selectionMode === NEW_SELECTION_MODE
}

/**
 * The content `CnPeopleWidget` gets for a New colleagues placement: the
 * window rides along as a filter, so a changed window refetches and the
 * widget's page cache never mixes this list with a directory list of the
 * same placement settings. The data source below strips it again.
 *
 * @param {object} content The placement content.
 * @return {object} The content for the widget.
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export function widgetContentFor(content) {
	const filters = (Array.isArray(content?.filters) ? content.filters : [])
		.filter((filter) => filter?.fieldName !== WINDOW_FILTER_FIELD)
	return {
		...content,
		filters: [...filters, {
			fieldName: WINDOW_FILTER_FIELD,
			operator: 'between',
			values: [windowDays(content, 'since'), windowDays(content, 'until')],
		}],
	}
}

/**
 * The calendar date of an ISO `YYYY-MM-DD` string at UTC midnight, in ms.
 *
 * @param {string} iso The date.
 * @return {number} The timestamp, NaN when unreadable.
 */
function utcDay(iso) {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(iso || ''))
	return match ? Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : NaN
}

/**
 * When someone who has not started yet starts, for the viewer: the weekday
 * within a week ("Monday"), else the day and month ("12 Oct").
 *
 * @param {string} startDate The start date, `YYYY-MM-DD`.
 * @param {string} today Today, `YYYY-MM-DD`.
 * @param {string} locale The viewer's locale (BCP 47).
 * @return {string} The day, or '' when the date is unreadable.
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export function startsOn(startDate, today, locale) {
	const start = utcDay(startDate)
	if (Number.isNaN(start)) {
		return ''
	}
	const days = Math.round((start - utcDay(today)) / DAY_MS)
	const options = days >= 1 && days <= 7
		? { weekday: 'long', timeZone: 'UTC' }
		: { day: 'numeric', month: 'short', timeZone: 'UTC' }
	return new Intl.DateTimeFormat(locale || 'en', options).format(new Date(start))
}

/**
 * The group ids of a placement's group filter, or null without one.
 *
 * @param {object[]} filters The widget's filters.
 * @return {string[]|null}
 */
function groupFilterOf(filters) {
	const group = (Array.isArray(filters) ? filters : []).find((filter) => filter?.fieldName === 'group')
	return group && Array.isArray(group.values) && group.values.length > 0 ? group.values.map(String) : null
}

/**
 * The `/api/people/new` answer as a `CnPeopleWidget` page: each person with
 * the widget's `uid`, inside the placement's group filter, and a "Starts"
 * line for whoever has not started yet. The server's order (newest start
 * first) is kept.
 *
 * @param {object} data The endpoint answer `{configured, users}`.
 * @param {{filters?: object[], today: string, locale: string}} context The placement's filters, today and the locale.
 * @return {{users: object[], total: number, hasMore: boolean}} The page.
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export function newColleaguesPage(data, context) {
	const groups = groupFilterOf(context.filters)
	const users = (Array.isArray(data?.users) ? data.users : [])
		.filter((user) => groups === null || (Array.isArray(user.groups) && user.groups.some((id) => groups.includes(String(id)))))
		.map((user) => {
			const fields = Array.isArray(user.customFields) ? user.customFields : []
			const starts = user.starts === true ? startsOn(user.startDate, context.today, context.locale) : ''
			return {
				...user,
				uid: user.uid || user.id,
				customFields: starts === ''
					? fields
					: [{ key: STARTS_FIELD_KEY, label: t('launchpad', 'Starts'), type: 'text', values: [starts] }, ...fields],
			}
		})
	return { users, total: users.length, hasMore: false }
}

/**
 * Today in UTC, as the server reads it (`gmdate('Y-m-d')`).
 *
 * @param {Date} [now] The moment.
 * @return {string} `YYYY-MM-DD`.
 */
export function utcToday(now = new Date()) {
	return now.toISOString().slice(0, 10)
}

/**
 * Ask the server for the new colleagues of a window.
 *
 * @param {{since: number, until: number}} window Days back and ahead.
 * @return {Promise<{configured: boolean, users: object[]}>}
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
export async function fetchNewColleagues(window) {
	const [{ default: axios }, { generateUrl }] = await Promise.all([
		import('@nextcloud/axios'),
		import('@nextcloud/router'),
	])
	const response = await axios.get(generateUrl('/apps/launchpad/api/people/new'), {
		params: { since: window.since, until: window.until },
	})
	const data = response?.data || {}
	return {
		configured: data.configured === true,
		users: Array.isArray(data.users) ? data.users : [],
	}
}
