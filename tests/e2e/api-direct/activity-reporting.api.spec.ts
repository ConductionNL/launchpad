/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * API-direct HTTP-contract coverage for activity reporting with a declared
 * purpose, colleague activity, and the personal layer sweep
 * (dashboards-and-who-may-see-them REQ-DWMS-001, REQ-DWMS-005..007).
 *
 * Raw /api responses, so this lives under api-direct/ like the personal
 * layer spec. Written in the build lane and not yet run: the live pass runs
 * it (decision 139).
 *
 * Scenarios covered:
 *   @e2e dashboards-and-who-may-see-them::the-capability-is-off-by-default
 *   @e2e dashboards-and-who-may-see-them::reading-a-colleagues-activity-is-logged
 *   @e2e dashboards-and-who-may-see-them::a-person-reads-their-own-activity
 *   @e2e dashboards-and-who-may-see-them::a-month-as-a-heatmap
 *   @e2e dashboards-and-who-may-see-them::the-export-carries-the-same-numbers
 *   @e2e dashboards-and-who-may-see-them::a-colleagues-work-on-a-hidden-dashboard-is-not-listed
 *   @e2e dashboards-and-who-may-see-them::entries-for-a-removed-widget-are-swept
 *
 * The audit entry itself lands in admin_audit's log file, which no HTTP
 * route exposes; ActivityReportServiceTest asserts the event, its six
 * parameters and its six placeholders.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL as BASE } from '../support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const GRANTEE = {
	user: process.env.E2E_GRANTEE_USER ?? 'e2e-grantee',
	pass: process.env.E2E_GRANTEE_PASS ?? 'E2eGranteePw123',
}

const API = `${BASE}/index.php/apps/launchpad/api`

function month(): { from: string; until: string } {
	const now = new Date()
	const pad = (value: number) => String(value).padStart(2, '0')
	const first = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`
	const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
	return { from: first, until: today }
}

async function as(who: { user: string; pass: string }): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE,
		httpCredentials: { username: who.user, password: who.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

test.describe('activity reporting names its purpose', () => {
	let admin: APIRequestContext
	let grantee: APIRequestContext

	test.beforeAll(async () => {
		admin = await as(ADMIN)
		grantee = await as(GRANTEE)
	})

	test.afterAll(async () => {
		await admin.put(`${API}/admin/activity-reporting`, {
			data: { enabled: false, purpose: '' },
		})
		await admin.dispose()
		await grantee.dispose()
	})

	test('off by default: reading somebody else is refused and says so', async () => {
		// @e2e dashboards-and-who-may-see-them::the-capability-is-off-by-default
		await admin.put(`${API}/admin/activity-reporting`, {
			data: { enabled: false, purpose: '' },
		})

		const res = await admin.get(`${API}/activity-report`, {
			params: { userId: GRANTEE.user, ...month() },
		})

		expect(res.status()).toBe(403)
		expect((await res.json()).error).toBe('reporting_disabled')
	})

	test('it cannot be turned on without a purpose', async () => {
		const res = await admin.put(`${API}/admin/activity-reporting`, {
			data: { enabled: true, purpose: '  ' },
		})

		expect(res.status()).toBe(400)
		expect((await res.json()).error).toBe('purpose_required')
	})

	test('a report on a colleague carries the purpose', async () => {
		// @e2e dashboards-and-who-may-see-them::reading-a-colleagues-activity-is-logged
		await admin.put(`${API}/admin/activity-reporting`, {
			data: { enabled: true, purpose: 'workload balancing' },
		})

		const res = await admin.get(`${API}/activity-report`, {
			params: { userId: GRANTEE.user, ...month() },
		})

		expect(res.ok()).toBeTruthy()
		expect((await res.json()).purpose).toBe('workload balancing')
	})

	test('a person reads their own with the switch off, and no purpose is attached', async () => {
		// @e2e dashboards-and-who-may-see-them::a-person-reads-their-own-activity
		await admin.put(`${API}/admin/activity-reporting`, {
			data: { enabled: false, purpose: '' },
		})

		const res = await grantee.get(`${API}/activity-report`, { params: month() })

		expect(res.ok()).toBeTruthy()
		expect((await res.json()).purpose).toBeNull()
	})

	test('a month is a calendar with one cell per day, and the export totals match', async () => {
		// @e2e dashboards-and-who-may-see-them::a-month-as-a-heatmap
		// @e2e dashboards-and-who-may-see-them::the-export-carries-the-same-numbers
		const period = month()
		const report = await (
			await admin.get(`${API}/activity-report`, { params: period })
		).json()
		const days = Number(period.until.slice(-2))

		expect(report.days).toHaveLength(days)
		for (const day of report.days) {
			expect(day.level === 0).toBe(day.count === 0)
		}
		const sum = report.days.reduce(
			(total: number, day: { count: number }) => total + day.count,
			0,
		)
		expect(sum).toBe(report.total)

		const csv = await (
			await admin.get(`${API}/activity-report/export`, { params: period })
		).text()
		const last = csv.trim().split('\n').pop() ?? ''
		expect(last).toBe(`"total","","${report.total}"`)
	})

	test('a non-administrator cannot read somebody else', async () => {
		await admin.put(`${API}/admin/activity-reporting`, {
			data: { enabled: true, purpose: 'workload balancing' },
		})

		const res = await grantee.get(`${API}/activity-report`, {
			params: { userId: ADMIN.user, ...month() },
		})

		expect(res.status()).toBe(403)
		expect((await res.json()).error).toBe('forbidden')
	})
})

test.describe('recent colleague activity', () => {
	test('the count is the count of what is listed', async () => {
		// @e2e dashboards-and-who-may-see-them::a-colleagues-work-on-a-hidden-dashboard-is-not-listed
		const grantee = await as(GRANTEE)
		const feed = await (
			await grantee.get(`${API}/colleague-activity`, { params: { limit: 20 } })
		).json()

		expect(feed.total).toBe(feed.items.length)
		for (const item of feed.items) {
			expect(item.actor).not.toBe(GRANTEE.user)
		}
		await grantee.dispose()
	})
})

test.describe('the personal layer sweep', () => {
	test('the daily cleanup knows the layer sweep and runs it by default', async () => {
		// @e2e dashboards-and-who-may-see-them::entries-for-a-removed-widget-are-swept
		const admin = await as(ADMIN)
		const scan = await (await admin.get(`${API}/admin/cleanup/scan`)).json()
		const body = JSON.stringify(scan)

		expect(body).toContain('orphaned_personal_layer_entries')
		await admin.dispose()
	})
})
