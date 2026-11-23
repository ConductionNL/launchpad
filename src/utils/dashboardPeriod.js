/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The period a dashboard's figures follow (REQ-DPD-002): This month, Quarter
 * or Year, kept in the address as `?period=`, and added to the query of every
 * widget whose source names the date field of its records. Read-only: the
 * period never changes the stored dashboard.
 */

/** The periods, This month first. */
export const PERIODS = ['month', 'quarter', 'year']

/** The period a dashboard opens with when the address names none. */
export const DEFAULT_PERIOD = 'month'

/**
 * The period the address asks for, or This month.
 *
 * @param {string} search A `location.search` string.
 * @return {string} `month`, `quarter` or `year`.
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */
export function readPeriod(search) {
	const asked = new URLSearchParams(search || '').get('period')
	return PERIODS.includes(asked) ? asked : DEFAULT_PERIOD
}

/**
 * The address with the period in its query, the rest kept.
 *
 * @param {string} href The current address.
 * @param {string} period The chosen period.
 * @return {string} The new address.
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */
export function addressWithPeriod(href, period) {
	const url = new URL(href)
	url.searchParams.set('period', PERIODS.includes(period) ? period : DEFAULT_PERIOD)
	return url.pathname + url.search + url.hash
}

/**
 * The first day of the period that holds `now`, at midnight.
 *
 * @param {string} period `month`, `quarter` or `year`.
 * @param {Date} now The current moment.
 * @return {Date} The period's start.
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */
export function periodStart(period, now) {
	if (period === 'year') {
		return new Date(now.getFullYear(), 0, 1)
	}
	if (period === 'quarter') {
		return new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1)
	}
	return new Date(now.getFullYear(), now.getMonth(), 1)
}

/**
 * A date as `YYYY-MM-DD` in local time.
 *
 * @param {Date} date The date.
 * @return {string}
 */
function isoDay(date) {
	const month = String(date.getMonth() + 1).padStart(2, '0')
	const day = String(date.getDate()).padStart(2, '0')
	return `${date.getFullYear()}-${month}-${day}`
}

/**
 * The date field a widget's register source names for the period, or ''.
 * The source is `content.source` when it is an object, else the chart's
 * `content.dataSource`.
 *
 * @param {object|null} content The placement content.
 * @return {string} The field name, or ''.
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */
export function periodFieldOf(content) {
	const sources = [content?.source, content?.dataSource, content?.props?.dataSource]
	for (const source of sources) {
		if (source && typeof source === 'object' && typeof source.periodField === 'string' && source.periodField.trim() !== '') {
			return source.periodField.trim()
		}
	}
	return ''
}

/**
 * Whether any placement on the dashboard names a period field, so the
 * header offers the period choice.
 *
 * @param {Array<object>} placements The dashboard's shown placements.
 * @return {boolean}
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */
export function namesPeriod(placements) {
	return (Array.isArray(placements) ? placements : []).some((placement) => periodFieldOf(placement?.content) !== '')
}

/**
 * A data source with the period added to its filter: the period field
 * between the period's start and now. A source without a period field, or no source, is
 * returned as it is.
 *
 * @param {object|null} dataSource The widget's data source.
 * @param {string} field The period field, '' for none.
 * @param {string} period The chosen period.
 * @param {Date} now The current moment.
 * @return {object|null} The data source.
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */
export function withPeriod(dataSource, field, period, now) {
	if (!dataSource || typeof dataSource !== 'object' || field === '') {
		return dataSource
	}
	const declared = dataSource.filter && typeof dataSource.filter === 'object' ? dataSource.filter : {}
	return {
		...dataSource,
		filter: {
			...declared,
			[`${field}[gte]`]: isoDay(periodStart(period, now)),
			[`${field}[lte]`]: now.toISOString(),
		},
	}
}
