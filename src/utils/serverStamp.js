/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

/** A server timestamp without an offset: `Y-m-d H:i:s`, written in UTC. */
const BARE_STAMP = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2}(\.\d+)?)?$/

/**
 * Parse a timestamp the server sent. Nextcloud runs PHP in UTC, so a stamp
 * without an offset is UTC; one with an offset or `Z` is read as written.
 *
 * @param {string|null|undefined} value The server's timestamp.
 * @return {Date|null} The moment, or null when empty or unreadable.
 * @spec openspec/specs/dashboards/spec.md#requirement-a-manager-can-publish-unpublish-and-schedule-a-dashboard-req-schedui-001
 */
export function parseServerStamp(value) {
	if (value === null || value === undefined || value === '') {
		return null
	}
	const text = String(value).trim()
	const parsed = new Date(BARE_STAMP.test(text) ? `${text.replace(' ', 'T')}Z` : text)
	return Number.isNaN(parsed.getTime()) ? null : parsed
}
