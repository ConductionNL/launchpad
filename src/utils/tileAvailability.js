/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { clockTime } from './embedOutage.js'

/**
 * The line under the tile form's "Check availability" switch: the result of
 * the tile's last health ping with its time (REQ-TILE-042), read from the
 * same cached reading the tile's badge shows (REQ-HPING-003).
 *
 * `online` and `degraded` answered, so they read as reachable; `offline` as
 * not reachable. No reading, or a reading without a usable time, says that
 * the tile has not been checked yet.
 *
 * @param {object|null|undefined} reading `{state, checkedAt}` from `GET /api/health-ping/{id}`.
 * @param {Date} now The current time.
 * @param {function(string, string, object=): string} t The translate function.
 * @return {string} E.g. "Reachable at the last check, today 10:40".
 * @spec openspec/changes/archive/2026-11-25-tile-form-board-sync/specs/tiles/spec.md#requirement-the-last-availability-check-in-the-form-req-tile-042
 */
export function lastCheckLine(reading, now, t) {
	const state = reading?.state
	const at = new Date(reading?.checkedAt ?? '')
	if (!['online', 'degraded', 'offline'].includes(state) || Number.isNaN(at.getTime())) {
		return t('launchpad', 'Not checked yet')
	}
	const when = at.toDateString() === now.toDateString()
		? t('launchpad', 'today {time}', { time: clockTime(at) })
		: t('launchpad', '{date} {time}', { date: at.toLocaleDateString(), time: clockTime(at) })
	return state === 'offline'
		? t('launchpad', 'Not reachable at the last check, {when}', { when })
		: t('launchpad', 'Reachable at the last check, {when}', { when })
}
