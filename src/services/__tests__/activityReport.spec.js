/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The activity report client: the query, a refusal as an answer, and the
 * CSV link over the same query (dashboards-and-who-may-see-them REQ-DWMS-005/006).
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const get = vi.fn()
vi.mock('@nextcloud/axios', () => ({ default: { get: (...args) => get(...args) } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

import { activityReportCsvUrl, loadActivityReport } from '../activityReport.js'

beforeEach(() => {
	get.mockReset()
})

describe('loadActivityReport', () => {
	it('leaves the user out when it is yourself, and passes the period', async () => {
		get.mockResolvedValue({ data: { subject: 'me', self: true, from: '2026-09-01', to: '2026-09-30', purpose: null, available: true, counts: { a: 2 }, days: { '2026-09-03': 2 }, total: 2 } })

		const report = await loadActivityReport({ user: '  ', from: '2026-09-01', to: '2026-09-30' })

		expect(get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/activity-report', { params: { from: '2026-09-01', to: '2026-09-30' } })
		expect(report).toEqual({ refused: false, subject: 'me', self: true, from: '2026-09-01', to: '2026-09-30', purpose: null, available: true, counts: { a: 2 }, days: { '2026-09-03': 2 }, total: 2 })
	})

	it('sends the trimmed user and keeps the purpose', async () => {
		get.mockResolvedValue({ data: { subject: 'alice', self: false, from: '2026-09-01', to: '2026-09-30', purpose: 'workload balancing', available: true, counts: [], days: null, total: '0' } })

		const report = await loadActivityReport({ user: ' alice ', from: '2026-09-01', to: '2026-09-30' })

		expect(get.mock.calls[0][1].params.user).toBe('alice')
		expect(report.purpose).toBe('workload balancing')
		expect(report.counts).toEqual({})
		expect(report.days).toEqual({})
		expect(report.total).toBe(0)
	})

	it('answers a refusal with its status and whether reporting is on', async () => {
		get.mockRejectedValue({ response: { status: 403, data: { error: 'off', activityReportingEnabled: false } } })

		await expect(loadActivityReport({ user: 'alice', from: '2026-09-01', to: '2026-09-30' }))
			.resolves.toEqual({ refused: true, status: 403, message: 'off', enabled: false })
	})

	it('throws anything else', async () => {
		get.mockRejectedValue({ response: { status: 500, data: {} } })

		await expect(loadActivityReport({ user: '', from: '2026-09-01', to: '2026-09-30' })).rejects.toEqual({ response: { status: 500, data: {} } })
	})
})

describe('activityReportCsvUrl', () => {
	it('asks the export for the same person and period', () => {
		expect(activityReportCsvUrl({ user: 'alice', from: '2026-09-01', to: '2026-09-30' }))
			.toBe('/index.php/apps/launchpad/api/activity-report/export?user=alice&from=2026-09-01&to=2026-09-30')
	})

	it('leaves the user out for yourself', () => {
		expect(activityReportCsvUrl({ user: '', from: '2026-09-01', to: '2026-09-02' }))
			.toBe('/index.php/apps/launchpad/api/activity-report/export?from=2026-09-01&to=2026-09-02')
	})
})
