/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The connections one dashboard uses, with the state integriq holds for each
 * (REQ-LP-CONN-005, board LpManagement "Koppelingen van dit dashboard").
 *
 * Which widget type uses which declared connection is configuration
 * (lib/Settings/connection-uses.json, next to connections.json), not code per
 * widget type. The states are integriq's `app_connection` rows for this app,
 * read through OpenRegister with the viewer's own session; nothing here ever
 * calls a connection itself.
 */

import { formatConnectionStatus } from '@conduction/nextcloud-vue/dist/esm/utils/builtInFormatters.js'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import connectionUses from '../../lib/Settings/connection-uses.json'
import declarations from '../../lib/Settings/connections.json'
import { lastSuccessOf } from './liveTileConnections.js'

/** Statuses integriq stores for a connection whose last check failed. */
const ERROR_STATUSES = Object.freeze(['error', 'failed'])

/**
 * Whether a placement is a launcher tile with its health ping switched on.
 *
 * @param {object} placement A placement row.
 * @return {boolean} true for a tile that pings its service.
 * @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
 */
function isPingingTile(placement) {
	const id = String(placement?.widgetId ?? '')
	const tile = id === 'tile' || id === 'app-tile' || id.startsWith('tile-') || Boolean(placement?.tileType)
	return tile && placement?.content?.healthPingEnabled === true
}

/**
 * The declared connections the placements use, once each, in declared order.
 * A connection no placement uses is left out.
 *
 * @param {Array<object>} placements The dashboard's placements.
 * @param {{connections: Array<object>}} [declared] The connections.json declaration.
 * @param {{connections: Record<string, {uses?: string[], healthPingTiles?: boolean}>}} [uses] The uses map.
 * @return {Array<{key: string, title: string, order: number}>} The connections in use.
 * @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
 */
export function connectionsUsedBy(placements, declared = declarations, uses = connectionUses) {
	const list = Array.isArray(placements) ? placements : []
	const types = new Set(list.map((placement) => String(placement?.widgetId ?? '')))
	const pinging = list.some(isPingingTile)
	const map = uses?.connections ?? {}
	return (declared?.connections ?? [])
		.filter((connection) => {
			const entry = map[connection.key] ?? {}
			const byType = (entry.uses ?? []).some((type) => types.has(type))
			return byType || (entry.healthPingTiles === true && pinging)
		})
		.sort((a, b) => (Number(a.order) || 0) - (Number(b.order) || 0))
		.map((connection) => ({ key: connection.key, title: connection.title, order: Number(connection.order) || 0 }))
}

/**
 * When a connection last failed: its own last-error time, else the time of
 * its last check when that check failed, else null.
 *
 * @param {object|null} row An `app_connection` object.
 * @return {string|null} The ISO time, or null when unknown.
 * @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
 */
export function lastErrorOf(row) {
	const own = row?.lastErrorAt ?? row?.lastError ?? null
	if (typeof own === 'string' && own !== '') {
		return own
	}
	const status = String(row?.status ?? '').toLowerCase()
	if (ERROR_STATUSES.includes(status) && typeof row?.checkedAt === 'string' && row.checkedAt !== '') {
		return row.checkedAt
	}
	return null
}

/**
 * The status as the Integrations page names it: the same `connectionStatus`
 * formatter its Status column uses (REQ-LP-CONN-004). A connection integriq
 * holds no row for yet reads as not configured.
 *
 * @param {object|null} row An `app_connection` object.
 * @return {string} The status label.
 * @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
 */
export function statusLabelOf(row) {
	const status = typeof row?.status === 'string' && row.status !== '' ? row.status : 'unconfigured'
	return formatConnectionStatus(status)
}

/**
 * The connections in use joined with integriq's rows, by connection key.
 *
 * @param {Array<{key: string, title: string}>} used The connections in use.
 * @param {Array<object>} rows The `app_connection` rows of this app.
 * @return {Array<object>} One line per connection: key, title, status, label, last success, last error, message.
 * @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
 */
export function joinConnectionStates(used, rows) {
	const byKey = new Map((Array.isArray(rows) ? rows : []).filter((row) => row?.key).map((row) => [row.key, row]))
	return (Array.isArray(used) ? used : []).map((connection) => {
		const row = byKey.get(connection.key) ?? null
		const status = typeof row?.status === 'string' && row.status !== '' ? row.status : 'unconfigured'
		return {
			key: connection.key,
			title: (typeof row?.title === 'string' && row.title !== '') ? row.title : connection.title,
			status,
			statusLabel: statusLabelOf(row),
			isError: ERROR_STATUSES.includes(status),
			lastSuccessAt: lastSuccessOf(row),
			lastErrorAt: lastErrorOf(row),
			message: typeof row?.statusMessage === 'string' ? row.statusMessage : '',
		}
	})
}

/**
 * Integriq's connection rows of this app, read with the viewer's session.
 *
 * @return {Promise<{state: string, rows: Array<object>}>} `ok` with the rows, or `error` when the read fails.
 * @spec openspec/changes/dashboard-connections-widget/specs/app-connections/spec.md#requirement-req-lp-conn-005-a-dashboard-shows-the-state-of-its-own-connections
 */
export async function fetchConnectionRows() {
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
