/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The connections a Live data widget can read from (REQ-LIVETILE-020).
 *
 * The rows are integriq's `app_connection` objects for this app, the same rows
 * the Integrations page lists, read through OpenRegister with the editor's own
 * session so the editor only sees what OpenRegister lets them read.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/** Statuses integriq uses for a connection whose last check succeeded. */
const SUCCESS_STATUSES = Object.freeze(['ok', 'connected', 'healthy', 'success'])

/**
 * When a connection last succeeded: its own last-success time, else the time
 * of its last check when that check succeeded, else null.
 *
 * @param {object} row An `app_connection` object.
 * @return {string|null} the ISO time, or null when unknown.
 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 */
export function lastSuccessOf(row) {
	const own = row?.lastSuccessAt ?? row?.lastSuccess ?? null
	if (typeof own === 'string' && own !== '') {
		return own
	}
	const status = String(row?.status ?? '').toLowerCase()
	if (SUCCESS_STATUSES.includes(status) && typeof row?.checkedAt === 'string' && row.checkedAt !== '') {
		return row.checkedAt
	}
	return null
}

/**
 * One `app_connection` row as a choice in the Live data form. The value is the
 * integriq source id the live tile resolves; a row without one is left out.
 *
 * @param {object} row An `app_connection` object.
 * @return {{value: string, label: string, lastSuccessAt: (string|null)}|null} the choice.
 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 */
export function toConnectionOption(row) {
	const value = row?.sourceId ?? row?.source ?? row?.id ?? null
	if (value === null || value === undefined || String(value) === '') {
		return null
	}
	const title = typeof row?.title === 'string' && row.title !== '' ? row.title : String(value)
	return { value: String(value), label: title, lastSuccessAt: lastSuccessOf(row) }
}

/**
 * The connections of this app. Fails soft: no integriq, no register or a
 * refused read gives an empty list, and the form then asks for a source id.
 *
 * @return {Promise<Array<{value: string, label: string, lastSuccessAt: (string|null)}>>} the choices.
 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 */
export async function fetchLiveTileConnections() {
	const { rows } = await fetchAppConnectionRows()
	return rows.map(toConnectionOption).filter((option) => option !== null)
}

/**
 * Integriq's `app_connection` rows of this app, read through OpenRegister
 * with the viewer's own session. Fails soft: no integriq, no register or a
 * refused read gives state `error` and no rows.
 *
 * @return {Promise<{state: string, rows: Array<object>}>} `ok` with the rows, or `error`.
 * @spec openspec/changes/widget-content-forms-board-sync/specs/live-data-tile-widget/spec.md#requirement-the-live-data-form-as-lpwidgetinstellen-draws-it-req-livetile-020
 * @spec openspec/changes/archive/2026-11-25-dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
 */
export async function fetchAppConnectionRows() {
	const url = generateUrl('/apps/openregister/api/objects/{register}/{schema}', {
		register: 'integriq',
		schema: 'app_connection',
	})
	try {
		const response = await axios.get(url, { params: { app: 'launchpad', _limit: 100 } })
		return { state: 'ok', rows: Array.isArray(response?.data?.results) ? response.data.results : [] }
	} catch {
		return { state: 'error', rows: [] }
	}
}
