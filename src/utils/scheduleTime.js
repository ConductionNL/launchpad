/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Conversions between the server's stored schedule times and the browser.
 * The server stores `publishAt` / `unpublishAt` as `Y-m-d H:i:s` in UTC;
 * a `datetime-local` input speaks the reader's own time zone.
 *
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */

const pad = (n) => String(n).padStart(2, '0')

/**
 * Parse a stored schedule time (UTC, `Y-m-d H:i:s` or ISO-8601).
 *
 * @param {string|null|undefined} value The stored value.
 * @return {Date|null} The instant, or null when empty or unparseable.
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */
export function parseStoredTime(value) {
	if (!value) {
		return null
	}
	const text = String(value).trim()
	const iso = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(text)
		? text.replace(' ', 'T') + 'Z'
		: text
	const date = new Date(iso)
	return Number.isNaN(date.getTime()) ? null : date
}

/**
 * Render a stored time as a `datetime-local` input value in local time.
 *
 * @param {string|null|undefined} value The stored value.
 * @return {string} `YYYY-MM-DDTHH:mm`, or an empty string.
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */
export function toLocalInput(value) {
	const date = parseStoredTime(value)
	if (date === null) {
		return ''
	}
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/**
 * Turn a `datetime-local` input value into an ISO-8601 UTC timestamp.
 *
 * @param {string} value The input value.
 * @return {string|null} The ISO string, or null when empty or invalid.
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */
export function fromLocalInput(value) {
	if (!value) {
		return null
	}
	const date = new Date(value)
	return Number.isNaN(date.getTime()) ? null : date.toISOString()
}

/**
 * Format a stored time for reading, in the reader's locale.
 *
 * @param {string|null|undefined} value The stored value.
 * @return {string} The formatted date and time, or an empty string.
 * @spec openspec/changes/sharing-dashboard-schedule-screen/specs/dashboards/spec.md
 */
export function formatStoredTime(value) {
	const date = parseStoredTime(value)
	if (date === null) {
		return ''
	}
	return date.toLocaleString(undefined, {
		dateStyle: 'medium',
		timeStyle: 'short',
	})
}
