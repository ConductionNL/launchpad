/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The connections one dashboard uses and their states (REQ-LP-CONN-005).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import declarations from '../../../lib/Settings/connections.json'
import connectionUses from '../../../lib/Settings/connection-uses.json'
import { connectionsUsedBy, fetchConnectionRows, joinConnectionStates, lastErrorOf, statusLabelOf } from '../dashboardConnections.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params) => path.replace(/\{(\w+)\}/g, (_, key) => params[key]),
}))

beforeEach(() => {
	axios.get.mockReset()
})

describe('connection-uses.json', () => {
	it('maps exactly the connections connections.json declares', () => {
		expect(Object.keys(connectionUses.connections).sort()).toEqual(declarations.connections.map((c) => c.key).sort())
	})
})

describe('connectionsUsedBy', () => {
	it('lists the connections of the management dashboard once each, in declared order, and leaves the unused out', () => {
		const placements = [
			{ widgetId: 'livetile', content: {} },
			{ widgetId: 'news', content: {} },
			{ widgetId: 'livetile', content: {} },
			{ widgetId: 'text', content: {} },
		]
		expect(connectionsUsedBy(placements).map((c) => c.key)).toEqual(['news-feeds', 'live-tiles'])
	})

	it('counts a tile only when its health ping is on', () => {
		expect(connectionsUsedBy([{ widgetId: 'tile', content: { healthPingEnabled: false } }])).toEqual([])
		expect(connectionsUsedBy([{ widgetId: 'app-tile', content: { healthPingEnabled: true } }]).map((c) => c.key))
			.toEqual(['health-ping'])
	})

	it('gives nothing for no placements or a missing list', () => {
		expect(connectionsUsedBy([])).toEqual([])
		expect(connectionsUsedBy(null)).toEqual([])
	})
})

describe('status labels and times', () => {
	it('names the status as the Integrations page does, and a missing row as not configured', () => {
		expect(statusLabelOf({ status: 'configured' })).toBe('Configured')
		expect(statusLabelOf({ status: 'error' })).toBe('Error')
		expect(statusLabelOf(null)).toBe('Not configured')
	})

	it('takes the last error from the row, else from a failed last check', () => {
		expect(lastErrorOf({ lastErrorAt: '2026-11-25T09:30:00Z' })).toBe('2026-11-25T09:30:00Z')
		expect(lastErrorOf({ status: 'error', checkedAt: '2026-11-25T09:30:00Z' })).toBe('2026-11-25T09:30:00Z')
		expect(lastErrorOf({ status: 'configured', checkedAt: '2026-11-25T09:30:00Z' })).toBeNull()
	})
})

describe('joinConnectionStates', () => {
	it('joins integriq\'s rows by key with the status, last success, last error and message', () => {
		const used = [{ key: 'live-tiles', title: 'Live tiles' }, { key: 'news-feeds', title: 'News feeds' }, { key: 'weather', title: 'Weather provider' }]
		const rows = [
			{ key: 'live-tiles', title: 'Live tiles', status: 'configured', lastSuccessAt: '2026-11-25T10:44:00Z' },
			{ key: 'news-feeds', title: 'News feeds', status: 'error', checkedAt: '2026-11-25T09:30:00Z', statusMessage: 'feeds.example.nl answered 503' },
			{ key: 'dashboard-registry', status: 'configured' },
		]
		const lines = joinConnectionStates(used, rows)
		expect(lines.map((l) => l.key)).toEqual(['live-tiles', 'news-feeds', 'weather'])
		expect(lines[0]).toMatchObject({ statusLabel: 'Configured', isError: false, lastSuccessAt: '2026-11-25T10:44:00Z', lastErrorAt: null })
		expect(lines[1]).toMatchObject({ statusLabel: 'Error', isError: true, lastErrorAt: '2026-11-25T09:30:00Z', message: 'feeds.example.nl answered 503' })
		expect(lines[2]).toMatchObject({ title: 'Weather provider', status: 'unconfigured', statusLabel: 'Not configured', message: '' })
	})
})

describe('fetchConnectionRows', () => {
	it('reads this app\'s rows from integriq through OpenRegister', async () => {
		axios.get.mockResolvedValue({ data: { results: [{ key: 'news-feeds' }] } })
		expect(await fetchConnectionRows()).toEqual({ state: 'ok', rows: [{ key: 'news-feeds' }] })
		expect(axios.get).toHaveBeenCalledWith(
			'/apps/openregister/api/objects/integriq/app_connection',
			{ params: { app: 'launchpad', _limit: 100 } },
		)
	})

	it('says error when the read fails', async () => {
		axios.get.mockRejectedValue(new Error('403'))
		expect(await fetchConnectionRows()).toEqual({ state: 'error', rows: [] })
	})
})
