/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the two dashboard-view-analytics background
 * jobs (openspec/specs/dashboard-view-analytics), written for
 * e2e-coverage-programme task 5.1.
 *
 * These scenarios were excluded as "a background job with no HTTP trigger or
 * read". A job can be run on demand: `occ background-job:execute
 * --force-execute` runs the real, registered job on the instance (the same
 * route scheduled-exports-delivery.spec.ts uses). What a job leaves behind is
 * read back the way an operator would: the app values through
 * `occ config:app:get`/`config:list --private`, the job's own log line in
 * nextcloud.log, and the analytics API for the rows it must keep.
 *
 * The retention scenarios here are judged on the cutoff the purge job logs and
 * on today's view row surviving it; that boundary is the arithmetic no unit
 * test pins (PurgeViewsJobTest mocks getPurgeCutoffDate()). Rows older than
 * the cutoff, and the job deleting them, are seeded and read back in
 * dashboard-view-analytics-history.spec.ts.
 *
 * Settings this file changes are read first and put back: `analytics_enabled`,
 * `analytics_retention_days`, the personal dashboard flags and `loglevel`
 * (info only while a job's log line is being looked for). The salt rotation
 * it forces is what the daily job does anyway.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-view-analytics::salt-rotation-discards-previous-salt-with-no-history-retained
 *   @e2e dashboard-view-analytics::default-retention-is-365-days
 *   @e2e dashboard-view-analytics::admin-can-configure-retention-period
 *   @e2e dashboard-view-analytics::retention-minimum-and-maximum-bounds
 *   @e2e dashboard-view-analytics::purge-logs-execution
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRow } from './support/dashboardHierarchy.ts'

import { expect, test } from '@playwright/test'
import {
	allowManyPersonalDashboards,
	createDashboard,
} from './support/dashboardHierarchy.ts'
import {
	appValue,
	describeRun,
	executeJobNow,
	logDuring,
	runOcc,
	setIntAppValue,
} from './support/occ.ts'
import { adminContext, API } from './support/principals.ts'

const RUN = Date.now()
const STAMP = `E2E ANLT jobs ${RUN}`
const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'

const SALT_JOB = 'OCA\\LaunchPad\\BackgroundJob\\SaltRotationJob'
const PURGE_JOB = 'OCA\\LaunchPad\\BackgroundJob\\PurgeViewsJob'
const SALT_KEY = 'analytics_dailysalt'
const SALT_DATE_KEY = 'analytics_dailysalt_date'
const RETENTION_KEY = 'analytics_retention_days'
const ENABLED_KEY =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/analytics_enabled'
const ANALYTICS = `${API}/admin/analytics`

/** The purge job's summary line (lib/BackgroundJob/PurgeViewsJob.php). */
const PURGE_LINE =
	/^launchpad analytics purge: deleted (\d+) view rows and (\d+) tile-click rows older than (\d{4}-\d{2}-\d{2})$/

let admin: APIRequestContext
let restoreSettings: (() => Promise<void>) | null = null
let enabledBefore: string | null = null
let retentionBefore: string | null = null
let viewed: DashboardRow | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	restoreSettings = await allowManyPersonalDashboards(admin)
	retentionBefore = await appValue(RETENTION_KEY)

	const enabled = await admin.get(`${ENABLED_KEY}?format=json`)
	const data = enabled.ok() ? (await enabled.json())?.ocs?.data?.data : null
	enabledBefore = typeof data === 'string' && data !== '' ? data : null
	const on = await admin.post(`${ENABLED_KEY}?format=json`, {
		form: { value: '1' },
	})
	expect(on.ok(), `analytics_enabled=1 -> ${on.status()}`).toBe(true)

	viewed = await createDashboard(admin, { name: `${STAMP} viewed` })
	const view = await admin.post(`${API}/dashboards/${viewed.uuid}/view-event`, {
		data: {},
		headers: { 'Content-Type': 'application/json' },
	})
	expect(view.status(), await view.text()).toBe(204)
	expect(
		(await todayViews(viewed.uuid)) ?? 0,
		'precondition: today has a view row for the dashboard',
	).toBeGreaterThanOrEqual(1)
})

test.afterAll(async () => {
	await setIntAppValue(
		RETENTION_KEY,
		retentionBefore === null ? null : Number.parseInt(retentionBefore, 10),
	).catch(() => undefined)
	if (enabledBefore === null) {
		await admin.delete(`${ENABLED_KEY}?format=json`).catch(() => undefined)
	} else {
		await admin
			.post(`${ENABLED_KEY}?format=json`, { form: { value: enabledBefore } })
			.catch(() => undefined)
	}
	if (viewed !== null) {
		await admin.delete(`${API}/dashboard/${viewed.id}`).catch(() => undefined)
	}
	if (restoreSettings !== null) {
		await restoreSettings()
	}
	await admin.dispose()
})

/** Today's date in UTC, as the view buckets and the cutoff are written. */
function utcDate(offsetDays = 0): string {
	const now = new Date()
	return new Date(
		Date.UTC(
			now.getUTCFullYear(),
			now.getUTCMonth(),
			now.getUTCDate() + offsetDays,
		),
	)
		.toISOString()
		.slice(0, 10)
}

/**
 * Today's view count of a dashboard from the admin breakdown, or null when it
 * has no row for today.
 *
 * @param uuid the dashboard.
 * @return the count.
 */
async function todayViews(uuid: string): Promise<number | null> {
	const res = await admin.get(`${ANALYTICS}/dashboards/${uuid}?period=7d`)
	expect(res.status(), await res.text()).toBe(200)
	const rows = (await res.json()) as Array<{
		viewBucket: string
		viewCount: number
	}>
	const today = rows.find((r) => r.viewBucket === utcDate())
	return today === undefined ? null : Number(today.viewCount)
}

/**
 * Run the purge job once with the given retention setting and return the
 * cutoff it logged, plus the full summary line.
 *
 * @param retention the raw `analytics_retention_days`, or null for unset.
 * @param effectiveDays the retention the job must use.
 * @return the expected cutoffs (before and after the run, for a midnight
 *         crossing), the logged cutoff and the log line.
 */
async function purgeWith(
	retention: number | null,
	effectiveDays: number,
): Promise<{
	expected: string[]
	cutoff: string
	line: string
	messages: string[]
}> {
	await setIntAppValue(RETENTION_KEY, retention)
	const expected = [utcDate(-effectiveDays)]
	const messages = await logDuring(async () => {
		const result = await executeJobNow(PURGE_JOB)
		expect(result.code, describeRun(result)).toBe(0)
	})
	expected.push(utcDate(-effectiveDays))
	const lines = messages.filter((m) => m.startsWith('launchpad analytics purge:'))
	expect(
		lines,
		`the purge job's log line among:\n${messages.join('\n')}`,
	).toHaveLength(1)
	const match = PURGE_LINE.exec(lines[0])
	expect(match, lines[0]).not.toBeNull()
	return { expected, cutoff: match![3], line: lines[0], messages }
}

test.describe('REQ-ANLT-003 the daily salt', () => {
	// @e2e dashboard-view-analytics::salt-rotation-discards-previous-salt-with-no-history-retained
	test('a rotation overwrites the salt with a fresh 32-byte value and keeps no copy of the old one', async () => {
		const first = await executeJobNow(SALT_JOB)
		expect(first.code, describeRun(first)).toBe(0)
		const saltA = await appValue(SALT_KEY)
		expect(saltA, 'a salt is stored after a rotation').toMatch(/^[0-9a-f]{64}$/)

		const messages = await logDuring(async () => {
			const second = await executeJobNow(SALT_JOB)
			expect(second.code, describeRun(second)).toBe(0)
		})
		const saltB = await appValue(SALT_KEY)
		expect(saltB, '32 random bytes, hex encoded').toMatch(/^[0-9a-f]{64}$/)
		expect(saltB, 'the salt was overwritten').not.toBe(saltA)
		expect(await appValue(SALT_DATE_KEY)).toBe(utcDate())

		const all = await runOcc('config:list', 'launchpad', '--private')
		expect(all.code, describeRun(all)).toBe(0)
		expect(
			all.stdout,
			'the previous salt is kept under no app key',
		).not.toContain(saltA!)

		const log = messages.join('\n')
		expect(log, 'the job logged its rotation').toContain(
			`launchpad analytics salt rotated for ${utcDate()}`,
		)
		expect(log, 'no salt appears in the log').not.toContain(saltA!)
		expect(log, 'no salt appears in the log').not.toContain(saltB!)
	})
})

test.describe('REQ-ANLT-009 retention and purge', () => {
	// @e2e dashboard-view-analytics::default-retention-is-365-days
	test("with no retention set, the purge cuts at 365 days and keeps today's row", async () => {
		const run = await purgeWith(null, 365)
		expect(run.expected, run.line).toContain(run.cutoff)
		expect(
			await todayViews(viewed!.uuid),
			"today's row is preserved",
		).toBeGreaterThanOrEqual(1)
	})

	// @e2e dashboard-view-analytics::admin-can-configure-retention-period
	test('a retention of 90 days takes effect on the next run', async () => {
		const run = await purgeWith(90, 90)
		expect(run.expected, run.line).toContain(run.cutoff)
		expect(
			await todayViews(viewed!.uuid),
			"today's row is preserved",
		).toBeGreaterThanOrEqual(1)
	})

	// @e2e dashboard-view-analytics::retention-minimum-and-maximum-bounds
	test('a retention of 10 is used as 30 and one of 5000 as 3650', async () => {
		const low = await purgeWith(10, 30)
		expect(low.expected, `10 days is clamped to 30: ${low.line}`).toContain(
			low.cutoff,
		)

		const high = await purgeWith(5000, 3650)
		expect(
			high.expected,
			`5000 days is clamped to 3650: ${high.line}`,
		).toContain(high.cutoff)

		expect(
			await todayViews(viewed!.uuid),
			"today's row is preserved",
		).toBeGreaterThanOrEqual(1)
	})

	// @e2e dashboard-view-analytics::purge-logs-execution
	test('the purge logs how many rows it deleted and the cutoff, and nothing about a person', async () => {
		const run = await purgeWith(90, 90)
		const match = PURGE_LINE.exec(run.line)!
		expect(
			Number.isInteger(Number(match[1])),
			'view rows deleted, as a number',
		).toBe(true)
		expect(
			Number.isInteger(Number(match[2])),
			'tile rows deleted, as a number',
		).toBe(true)
		expect(run.expected).toContain(match[3])

		const jobLines = run.messages.filter((m) =>
			m.startsWith('launchpad analytics'),
		)
		for (const line of jobLines) {
			expect(line, 'no user id in the purge log').not.toContain(ADMIN_USER)
			expect(line, 'no dashboard id in the purge log').not.toContain(
				viewed!.uuid,
			)
		}
	})
})
