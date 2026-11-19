/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for dashboard view analytics and tile-click
 * analytics (openspec/specs/dashboard-view-analytics), written for
 * e2e-coverage-programme task 5.1.
 *
 * What a running instance can show, and what this file therefore asserts:
 *   - the record endpoints (`POST /api/dashboards/{uuid}/view-event`,
 *     `POST /api/tile-click/{placementId}`): their status, and their effect
 *     read back through the admin report endpoints;
 *   - the admin report endpoints (top dashboards, per-dashboard breakdown,
 *     summary, CSV export, top tiles, per-dashboard tiles, tile CSV export),
 *     including the 403 for an ordinary logged-in account;
 *   - the two gates: the global `analytics_enabled` app value (written over
 *     the provisioning API, the only writer the product has) and the
 *     person's own `analytics_optout` (written through `/api/analytics/me`);
 *   - the frontend: the view-event a dashboard visit sends, the personal
 *     "Count my visits" switch, and the Tiles report on the admin page.
 *
 * Only today's UTC bucket can be written from outside, so scenarios that need
 * rows on other days, the purge job, the salt rotation job or the schema are
 * excluded in the spec with the unit test that pins them.
 *
 * Every dashboard and tile is created fresh per test, so its counters start at
 * zero and a count read back belongs to this test alone. Instance-wide reads
 * (top lists, summary) are compared with the CSV export of the same period,
 * so they stay exact on a shared instance that has other traffic.
 *
 * Unique counts rely on the Nextcloud cache (`ICacheFactory::createDistributed`).
 * An instance without any memcache gets a NullCache and counts every view as a
 * new viewer; the dedup assertions then fail, and they should.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-view-analytics::one-row-per-dashboard-per-day
 *   @e2e dashboard-view-analytics::authenticated-user-records-a-view-event
 *   @e2e dashboard-view-analytics::unauthenticated-user-is-rejected
 *   @e2e dashboard-view-analytics::invalid-dashboard-uuid-returns-404
 *   @e2e dashboard-view-analytics::empty-request-body-is-valid
 *   @e2e dashboard-view-analytics::same-user-on-same-day-increments-viewcount-but-not-uniqueviewercount-twice
 *   @e2e dashboard-view-analytics::different-users-on-same-day-increment-uniqueviewercount-separately
 *   @e2e dashboard-view-analytics::user-opts-out-their-events-are-ignored
 *   @e2e dashboard-view-analytics::user-opts-back-in-events-resume
 *   @e2e dashboard-view-analytics::opt-out-is-per-user-not-global
 *   @e2e dashboard-view-analytics::global-disable-turns-off-all-tracking
 *   @e2e dashboard-view-analytics::global-re-enable-resumes-tracking
 *   @e2e dashboard-view-analytics::default-is-enabled
 *   @e2e dashboard-view-analytics::list-top-10-dashboards-by-view-count-7-day-period
 *   @e2e dashboard-view-analytics::limit-parameter-is-respected
 *   @e2e dashboard-view-analytics::non-admin-receives-403
 *   @e2e dashboard-view-analytics::valid-periods-are-7d-30d-90d
 *   @e2e dashboard-view-analytics::daily-breakdown-for-a-dashboard-30-day-period
 *   @e2e dashboard-view-analytics::missing-days-are-omitted
 *   @e2e dashboard-view-analytics::non-existent-dashboard-returns-404
 *   @e2e dashboard-view-analytics::summary-returns-instance-wide-totals-and-top-5
 *   @e2e dashboard-view-analytics::period-parameter-changes-calculation
 *   @e2e dashboard-view-analytics::csv-export-contains-dashboard-statistics
 *   @e2e dashboard-view-analytics::csv-respects-period-parameter
 *   @e2e dashboard-view-analytics::csv-filename-includes-export-date
 *   @e2e dashboard-view-analytics::dashboard-component-calls-view-event-on-mount
 *   @e2e dashboard-view-analytics::debouncing-prevents-multi-tab-double-counting
 *   @e2e dashboard-view-analytics::different-dashboards-are-not-debounced-against-each-other
 *   @e2e dashboard-view-analytics::reload-on-same-dashboard-triggers-new-event
 *   @e2e dashboard-view-analytics::view-event-is-not-sent-if-analytics-is-disabled
 *   @e2e dashboard-view-analytics::one-row-per-tile-per-day
 *   @e2e dashboard-view-analytics::authenticated-user-records-a-tile-click
 *   @e2e dashboard-view-analytics::same-actor-same-day-increments-clickcount-but-uniqueactorcount-once
 *   @e2e dashboard-view-analytics::different-actors-same-day-increment-uniqueactorcount-separately
 *   @e2e dashboard-view-analytics::unauthenticated-request-is-rejected
 *   @e2e dashboard-view-analytics::global-disable-makes-recording-a-no-op
 *   @e2e dashboard-view-analytics::per-user-opt-out-makes-that-users-clicks-a-no-op
 *   @e2e dashboard-view-analytics::opt-out-is-per-user-not-global-and-no-new-setting-exists
 *   @e2e dashboard-view-analytics::default-enabled
 *   @e2e dashboard-view-analytics::top-tiles-by-click-count
 *   @e2e dashboard-view-analytics::per-dashboard-tile-breakdown
 *   @e2e dashboard-view-analytics::csv-export-contains-tile-statistics
 *   @e2e dashboard-view-analytics::non-admin-cannot-export
 *   @e2e dashboard-view-analytics::top-tiles-for-thirty-days
 *   @e2e dashboard-view-analytics::export
 *   @e2e dashboard-view-analytics::tracking-off
 *   @e2e dashboard-view-analytics::a-person-stops-their-visits-being-counted
 *   @e2e dashboard-view-analytics::a-person-counts-again
 *   @e2e dashboard-view-analytics::analytics-is-off-for-everyone
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */

import type { APIRequestContext, APIResponse, Browser, BrowserContext, Page, Request } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, request as pwRequest, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	adminContext,
	allowPersonalDashboards,
	assertActingAs,
	createPrincipal,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const API = `${APP}/api`
const ANALYTICS = `${API}/admin/analytics`
const ENABLED_KEY =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/analytics_enabled'
const ADMIN_PAGE = '/index.php/settings/admin/launchpad?tab=versioning-audit'
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const RUN = Date.now()

type Dashboard = { id: number; uuid: string; name: string; slug: string }
type Counts = { viewCount: number; uniqueViewerCount: number }
type ClickCounts = { clickCount: number; uniqueActorCount: number }
type DayRow = { viewBucket: string; viewCount: number; uniqueViewerCount: number }
type TopRow = { dashboardUuid: string; name: string | null; viewCount: number; uniqueViewerCount: number }
type TileRow = {
	placementUuid: string
	dashboardUuid: string
	clickCount: number
	uniqueActorCount: number
}

let admin: APIRequestContext
let alice: Principal | null = null
let bob: Principal | null = null
let eve: Principal | null = null
let originalEnabled: string | null = null
let adminWasOptedOut = false
const createdDashboards: number[] = []
let seq = 0

test.setTimeout(180_000)

// ── helpers ────────────────────────────────────────────────────────────

/**
 * Today's date in UTC as YYYY-MM-DD, the bucket every write lands in.
 *
 * @param daysBack how many days before today.
 * @return the date.
 */
function utcDate(daysBack = 0): string {
	const d = new Date()
	d.setUTCDate(d.getUTCDate() - daysBack)
	return d.toISOString().slice(0, 10)
}

/**
 * A request context with no credentials and no inherited session cookie.
 *
 * @return the context; the caller disposes it.
 */
async function anonymousContext(): Promise<APIRequestContext> {
	return pwRequest.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

/**
 * The stored `analytics_enabled` app value, or null when it is not set.
 *
 * @return the raw value.
 */
async function readEnabledValue(): Promise<string | null> {
	const res = await admin.get(`${ENABLED_KEY}?format=json`)
	if (!res.ok()) {
		return null
	}
	const data = (await res.json())?.ocs?.data
	return typeof data?.data === 'string' && data.data !== '' ? data.data : null
}

/**
 * Write the global `analytics_enabled` app value, or remove it (null), and
 * check the product reads it back that way.
 *
 * @param value '1', '0', or null to fall back to the default.
 * @return nothing.
 */
async function setEnabled(value: string | null): Promise<void> {
	if (value === null) {
		const res = await admin.delete(`${ENABLED_KEY}?format=json`)
		expect(res.status(), `delete analytics_enabled -> ${await res.text()}`).toBeLessThan(500)
	} else {
		const res = await admin.post(`${ENABLED_KEY}?format=json`, { form: { value } })
		expect(res.ok(), `set analytics_enabled=${value} -> ${res.status()}`).toBeTruthy()
	}
	const me = await admin.get(`${API}/analytics/me`)
	expect(me.status()).toBe(200)
	const expected = value === null || ['1', 'true', 'yes', 'on'].includes(value.toLowerCase())
	expect((await me.json()).enabled, 'the product reads the analytics setting back').toBe(expected)
}

/**
 * Store a person's own opt-out through the endpoint the settings switch uses.
 *
 * @param api the person's context.
 * @param optedOut the value.
 * @return nothing.
 */
async function setOptOut(api: APIRequestContext, optedOut: boolean): Promise<void> {
	const res = await api.put(`${API}/analytics/me`, { data: { optedOut } })
	expect(res.status(), await res.text()).toBe(200)
	expect((await res.json()).optedOut).toBe(optedOut)
}

/**
 * Create an admin-owned dashboard, optionally shared view-only with people.
 *
 * @param label a readable part of the name.
 * @param viewers accounts that may view it.
 * @return the dashboard.
 */
async function newDashboard(label: string, viewers: Principal[] = []): Promise<Dashboard> {
	seq += 1
	const name = `${label} ${RUN}-${seq}`
	const slug = `e2e-anlt-${RUN}-${seq}`
	const created = await admin.post(`${API}/dashboard`, { data: { name, slug } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const dashboard = { id: Number(dash.id), uuid: String(dash.uuid), name, slug }
	createdDashboards.push(dashboard.id)
	for (const viewer of viewers) {
		await shareWith(admin, dashboard.id, viewer.username, 'view_only')
	}
	return dashboard
}

/**
 * Add a titled tile (a text widget with a custom title) to a dashboard.
 *
 * @param dashboard the dashboard.
 * @param title the title the report shows.
 * @return the placement id.
 */
async function newTile(dashboard: Dashboard, title: string): Promise<number> {
	const widget = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			content: { text: title, contentMode: 'markdown' },
		},
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)
	const placement = await widget.json()
	const id = Number(placement.id ?? placement.data?.id ?? placement.placement?.id)
	expect(id, `no placement id in ${JSON.stringify(placement)}`).toBeGreaterThan(0)
	const titled = await admin.put(`${API}/widgets/${id}`, { data: { customTitle: title } })
	expect(titled.status(), await titled.text()).toBeLessThan(300)
	return id
}

/**
 * Post a view event.
 *
 * @param api who views.
 * @param uuid the dashboard.
 * @param body the JSON body to send.
 * @return the response.
 */
async function view(api: APIRequestContext, uuid: string, body: unknown = {}): Promise<APIResponse> {
	return api.post(`${API}/dashboards/${encodeURIComponent(uuid)}/view-event`, {
		data: body,
		headers: { 'Content-Type': 'application/json' },
	})
}

/**
 * Post a view event that must be accepted with 204 and no body.
 *
 * @param api who views.
 * @param uuid the dashboard.
 * @return nothing.
 */
async function viewOk(api: APIRequestContext, uuid: string): Promise<void> {
	const res = await view(api, uuid)
	expect(res.status(), await res.text()).toBe(204)
}

/**
 * Post a tile click.
 *
 * @param api who clicks.
 * @param placementId the tile.
 * @return the response.
 */
async function click(api: APIRequestContext, placementId: number): Promise<APIResponse> {
	return api.post(`${API}/tile-click/${placementId}`, {
		data: {},
		headers: { 'Content-Type': 'application/json' },
	})
}

/**
 * Post a tile click that must be accepted with 204.
 *
 * @param api who clicks.
 * @param placementId the tile.
 * @return nothing.
 */
async function clickOk(api: APIRequestContext, placementId: number): Promise<void> {
	const res = await click(api, placementId)
	expect(res.status(), await res.text()).toBe(204)
}

/**
 * GET an admin analytics endpoint as the admin and return its JSON.
 *
 * @param path the path under /api/admin/analytics.
 * @return the parsed body.
 */
async function adminRead<T>(path: string): Promise<T> {
	const res = await admin.get(`${ANALYTICS}${path}`)
	expect(res.status(), `${path}: ${await res.text()}`).toBe(200)
	return (await res.json()) as T
}

/**
 * The daily breakdown of one dashboard.
 *
 * @param uuid the dashboard.
 * @param period the period.
 * @return the rows.
 */
async function dailyRows(uuid: string, period = '7d'): Promise<DayRow[]> {
	return adminRead<DayRow[]>(`/dashboards/${uuid}?period=${period}`)
}

/**
 * Today's counters of one dashboard; zeros when it has no row today.
 *
 * @param uuid the dashboard.
 * @return the counters.
 */
async function todayCounts(uuid: string): Promise<Counts> {
	const rows = await dailyRows(uuid)
	const today = rows.filter((r) => r.viewBucket === utcDate())
	expect(today.length, 'at most one row per dashboard per day').toBeLessThanOrEqual(1)
	return today[0]
		? { viewCount: Number(today[0].viewCount), uniqueViewerCount: Number(today[0].uniqueViewerCount) }
		: { viewCount: 0, uniqueViewerCount: 0 }
}

/**
 * The tile counters of one placement over the period, from the per-dashboard
 * tile breakdown; zeros when it has no clicks.
 *
 * @param dashboard the dashboard the tile is on.
 * @param placementId the tile.
 * @return the counters.
 */
async function tileCounts(dashboard: Dashboard, placementId: number): Promise<ClickCounts> {
	const rows = await adminRead<TileRow[]>(`/tiles/by-dashboard/${dashboard.uuid}?period=7d`)
	const row = rows.find((r) => String(r.placementUuid) === String(placementId))
	return row
		? { clickCount: Number(row.clickCount), uniqueActorCount: Number(row.uniqueActorCount) }
		: { clickCount: 0, uniqueActorCount: 0 }
}

/**
 * Parse an RFC 4180 CSV document (quoted cells, doubled quotes, CRLF).
 *
 * @param text the document.
 * @return the rows, header first.
 */
function parseCsv(text: string): string[][] {
	const rows: string[][] = []
	let row: string[] = []
	let cell = ''
	let quoted = false
	for (let i = 0; i < text.length; i++) {
		const ch = text[i]
		if (quoted) {
			if (ch === '"' && text[i + 1] === '"') {
				cell += '"'
				i++
			} else if (ch === '"') {
				quoted = false
			} else {
				cell += ch
			}
		} else if (ch === '"') {
			quoted = true
		} else if (ch === ',') {
			row.push(cell)
			cell = ''
		} else if (ch === '\n' || ch === '\r') {
			if (ch === '\r' && text[i + 1] === '\n') {
				i++
			}
			row.push(cell)
			rows.push(row)
			row = []
			cell = ''
		} else {
			cell += ch
		}
	}
	if (cell !== '' || row.length > 0) {
		row.push(cell)
		rows.push(row)
	}
	return rows
}

/**
 * Download a CSV export as the admin and parse it into header and records.
 *
 * @param path the export path under /api/admin/analytics.
 * @return the response, the header row and the records keyed by column.
 */
async function csvExport(path: string): Promise<{
	res: APIResponse
	header: string[]
	records: Record<string, string>[]
}> {
	const res = await admin.get(`${ANALYTICS}${path}`)
	expect(res.status(), `${path}: ${res.status()}`).toBe(200)
	const [header, ...rest] = parseCsv(await res.text())
	const records = rest.map((cells) =>
		Object.fromEntries(header.map((col, i) => [col, cells[i] ?? ''])),
	)
	return { res, header, records }
}

/**
 * Per-dashboard view totals of a period, from the dashboard CSV export.
 *
 * @param period the period.
 * @return totals per dashboard, sorted by views descending.
 */
async function viewTotals(period: string): Promise<{ uuid: string; views: number; unique: number }[]> {
	const { records } = await csvExport(`/export?period=${period}`)
	const by = new Map<string, { uuid: string; views: number; unique: number }>()
	for (const r of records) {
		const entry = by.get(r.dashboardUuid) ?? { uuid: r.dashboardUuid, views: 0, unique: 0 }
		entry.views += Number(r.viewCount)
		entry.unique += Number(r.uniqueViewerCount)
		by.set(r.dashboardUuid, entry)
	}
	return [...by.values()].sort((a, b) => b.views - a.views)
}

/**
 * Whether a request is the view-event POST for a dashboard.
 *
 * @param req the request.
 * @param uuid the dashboard.
 * @return true when it is.
 */
function isViewEvent(req: Request, uuid: string): boolean {
	return req.method() === 'POST'
		&& new RegExp(`/api/(v1/)?dashboards/${uuid}/view-event$`).test(new URL(req.url()).pathname)
}

/**
 * Collect the view-event POSTs a browser context sends for a dashboard.
 *
 * @param context the context.
 * @param uuid the dashboard.
 * @return the live list.
 */
function collectViewEvents(context: BrowserContext, uuid: string): Request[] {
	const seen: Request[] = []
	context.on('request', (req) => {
		if (isViewEvent(req, uuid)) {
			seen.push(req)
		}
	})
	return seen
}

/**
 * Wait until a dashboard is the one rendered on the page.
 *
 * @param page the page.
 * @param uuid the dashboard.
 * @return nothing.
 */
async function waitForDashboard(page: Page, uuid: string): Promise<void> {
	await expect(
		page.locator(`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${uuid}"]`),
	).toBeVisible({ timeout: 45_000 })
}

/**
 * Open the user settings dialog the way a person does: from the "More" menu
 * when the start page has no panel, otherwise from the panel's settings
 * foldout.
 *
 * @param page the page, on LaunchPad.
 * @return nothing.
 */
async function openPersonalSettings(page: Page): Promise<void> {
	const more = page.locator('[data-testid="launchpad-more-menu"] [data-testid="cn-grouped-menu-trigger"]')
	if (await more.isVisible().catch(() => false)) {
		await more.click()
		const item = page.locator('[data-testid="cn-grouped-menu-item-personal-settings"]')
		if (await item.isVisible({ timeout: 3_000 }).catch(() => false)) {
			await item.click()
			return
		}
		await page.keyboard.press('Escape')
	}
	await page.locator('[data-testid="cn-nav-settings"] button').first().click()
	await page.locator('[data-testid="cn-nav-personal-settings"]').click()
}

/**
 * Log in as a principal in a fresh browser context.
 *
 * @param browser the browser.
 * @param who the principal.
 * @return the context and page.
 */
async function browserAs(browser: Browser, who: Principal) {
	return loginAs(browser, who.username, who.password)
}

// ── fixture ────────────────────────────────────────────────────────────

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN.user)
	await allowPersonalDashboards(admin, true)
	alice = await createPrincipal(admin, 'e2e-anlt-alice')
	bob = await createPrincipal(admin, 'e2e-anlt-bob')
	eve = await createPrincipal(admin, 'e2e-anlt-eve')
	for (const p of [alice, bob, eve]) {
		await assertActingAs(p.api, p.username)
	}

	// Start from tracking on, for the admin too (the Tiles report reads the
	// calling admin's own gate); both are put back afterwards.
	originalEnabled = await readEnabledValue()
	if (originalEnabled !== null && !['1', 'true', 'yes', 'on'].includes(originalEnabled.toLowerCase())) {
		await setEnabled('1')
	}
	const me = await admin.get(`${API}/analytics/me`)
	adminWasOptedOut = (await me.json()).optedOut === true
	if (adminWasOptedOut) {
		await setOptOut(admin, false)
	}
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	await setEnabled(originalEnabled)
	if (adminWasOptedOut) {
		await setOptOut(admin, true)
	}
	for (const id of createdDashboards) {
		await admin.delete(`${API}/dashboard/${id}`)
	}
	await removePrincipal(admin, alice)
	await removePrincipal(admin, bob)
	await removePrincipal(admin, eve)
	await admin.dispose()
})

// ── REQ-ANLT-001..005: recording a view ────────────────────────────────

test.describe.serial('recording a dashboard view (REQ-ANLT-001..005)', () => {
	// @e2e dashboard-view-analytics::authenticated-user-records-a-view-event
	test('a logged-in viewer records a view: 204, no body, today counted', async () => {
		const dash = await newDashboard('View', [alice!])
		expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 0, uniqueViewerCount: 0 })

		const res = await view(alice!.api, dash.uuid, {})
		expect(res.status()).toBe(204)
		expect((await res.body()).length, 'a 204 carries no body').toBe(0)

		expect((await todayCounts(dash.uuid)).viewCount).toBe(1)
	})

	// @e2e dashboard-view-analytics::empty-request-body-is-valid
	test('a JSON body of {} is accepted and counted', async () => {
		const dash = await newDashboard('Empty body', [alice!])
		const res = await alice!.api.post(`${API}/dashboards/${dash.uuid}/view-event`, {
			headers: { 'Content-Type': 'application/json' },
			data: '{}',
		})
		expect(res.status(), await res.text()).toBe(204)
		expect((await todayCounts(dash.uuid)).viewCount).toBe(1)
	})

	// @e2e dashboard-view-analytics::one-row-per-dashboard-per-day
	test('two views on one day stay one row, counted twice', async () => {
		const dash = await newDashboard('One row', [alice!])
		await viewOk(alice!.api, dash.uuid)
		await viewOk(alice!.api, dash.uuid)

		const rows = await dailyRows(dash.uuid)
		expect(rows.filter((r) => r.viewBucket === utcDate())).toHaveLength(1)
		expect(rows).toHaveLength(1)
		expect(Number(rows[0].viewCount)).toBe(2)

		// The export lists the stored rows: one for this dashboard today.
		const { records } = await csvExport('/export?period=7d')
		const mine = records.filter((r) => r.dashboardUuid === dash.uuid)
		expect(mine).toHaveLength(1)
		expect(mine[0].viewBucket).toBe(utcDate())
		expect(mine[0].viewCount).toBe('2')
	})

	// @e2e dashboard-view-analytics::unauthenticated-user-is-rejected
	test('an unauthenticated view is refused with 401 and counts nothing', async () => {
		const dash = await newDashboard('Anonymous', [alice!])
		const anon = await anonymousContext()
		try {
			const res = await view(anon, dash.uuid)
			expect(res.status()).toBe(401)
		} finally {
			await anon.dispose()
		}
		expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 0, uniqueViewerCount: 0 })

		// The same request logged in is accepted, so the 401 is about the caller.
		await viewOk(alice!.api, dash.uuid)
		expect((await todayCounts(dash.uuid)).viewCount).toBe(1)
	})

	// @e2e dashboard-view-analytics::invalid-dashboard-uuid-returns-404
	test('a view of a dashboard that does not exist is 404 and stores no row', async () => {
		const missing = `00000000-0000-4000-8000-${String(RUN).padStart(12, '0').slice(-12)}`
		const res = await view(alice!.api, missing)
		expect(res.status(), await res.text()).toBe(404)

		const { records } = await csvExport('/export?period=7d')
		expect(records.filter((r) => r.dashboardUuid === missing)).toHaveLength(0)
		// Unknown to the breakdown too.
		const detail = await admin.get(`${ANALYTICS}/dashboards/${missing}?period=7d`)
		expect(detail.status()).toBe(404)
	})

	// @e2e dashboard-view-analytics::same-user-on-same-day-increments-viewcount-but-not-uniqueviewercount-twice
	test('one person viewing twice in a day: viewCount 2, uniqueViewerCount 1', async () => {
		const dash = await newDashboard('Same viewer', [alice!])
		await viewOk(alice!.api, dash.uuid)
		await viewOk(alice!.api, dash.uuid)
		expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 2, uniqueViewerCount: 1 })
	})

	// @e2e dashboard-view-analytics::different-users-on-same-day-increment-uniqueviewercount-separately
	test('two people viewing on one day: uniqueViewerCount 2', async () => {
		const dash = await newDashboard('Two viewers', [alice!, bob!])
		await viewOk(alice!.api, dash.uuid)
		await viewOk(bob!.api, dash.uuid)
		expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 2, uniqueViewerCount: 2 })
	})

	// @e2e dashboard-view-analytics::user-opts-out-their-events-are-ignored
	// @e2e dashboard-view-analytics::user-opts-back-in-events-resume
	test('an opted-out view is a 204 no-op; opting back in counts again', async () => {
		const dash = await newDashboard('Opt-out', [eve!])
		await setOptOut(eve!.api, true)
		try {
			const res = await view(eve!.api, dash.uuid)
			expect(res.status()).toBe(204)
			expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 0, uniqueViewerCount: 0 })
		} finally {
			await setOptOut(eve!.api, false)
		}

		// Opted in again, the next view counts. uniqueViewerCount 1 also shows
		// the opted-out view left no hash in the dedup cache: had it, this view
		// would have been a repeat and left the unique count at 0.
		await viewOk(eve!.api, dash.uuid)
		expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 1, uniqueViewerCount: 1 })
	})

	// @e2e dashboard-view-analytics::opt-out-is-per-user-not-global
	test('one person opting out does not stop another being counted', async () => {
		const dash = await newDashboard('Per user', [eve!, bob!])
		await setOptOut(eve!.api, true)
		try {
			await viewOk(eve!.api, dash.uuid)
			await viewOk(bob!.api, dash.uuid)
			expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 1, uniqueViewerCount: 1 })
		} finally {
			await setOptOut(eve!.api, false)
		}
	})

	// @e2e dashboard-view-analytics::global-disable-turns-off-all-tracking
	// @e2e dashboard-view-analytics::global-re-enable-resumes-tracking
	test('with analytics off every view is a 204 no-op; switched on, tracking resumes', async () => {
		const dash = await newDashboard('Global off', [alice!, bob!])
		await setEnabled('0')
		try {
			for (const who of [alice!, bob!]) {
				const res = await view(who.api, dash.uuid)
				expect(res.status()).toBe(204)
			}
			expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 0, uniqueViewerCount: 0 })
		} finally {
			await setEnabled('1')
		}

		// Back on: counted as normal, and as first views of the day, so the
		// disabled calls added no hash to the cache.
		await viewOk(alice!.api, dash.uuid)
		await viewOk(bob!.api, dash.uuid)
		expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 2, uniqueViewerCount: 2 })
	})

	// @e2e dashboard-view-analytics::default-is-enabled
	test('with no analytics setting at all, views are tracked', async () => {
		const dash = await newDashboard('Default on', [alice!])
		await setEnabled(null)
		try {
			await viewOk(alice!.api, dash.uuid)
			expect(await todayCounts(dash.uuid)).toEqual({ viewCount: 1, uniqueViewerCount: 1 })
		} finally {
			await setEnabled(originalEnabled === null ? null : '1')
		}
	})
})

// ── REQ-ANLT-006..010: the admin report endpoints ─────────────────────

test.describe.serial('admin view reports (REQ-ANLT-006..010)', () => {
	/** Five dashboards with known view counts today, most viewed first. */
	const ranked: { dash: Dashboard; views: number }[] = []

	test.beforeAll(async () => {
		for (const views of [10, 8, 6, 4, 2]) {
			const dash = await newDashboard(`Ranked ${views}`)
			for (let i = 0; i < views; i++) {
				await viewOk(admin, dash.uuid)
			}
			ranked.push({ dash, views })
		}
	})

	// @e2e dashboard-view-analytics::list-top-10-dashboards-by-view-count-7-day-period
	test('top dashboards for 7 days: sorted by views, named, summed, at most the limit', async () => {
		const top = await adminRead<TopRow[]>('/dashboards/top?period=7d&limit=10')
		expect(Array.isArray(top)).toBe(true)

		for (const row of top) {
			expect(Object.keys(row)).toEqual(
				expect.arrayContaining(['dashboardUuid', 'name', 'viewCount', 'uniqueViewerCount']),
			)
		}
		const counts = top.map((r) => Number(r.viewCount))
		expect(counts).toEqual([...counts].sort((a, b) => b - a))

		// Exactly what the stored rows of the period say: every dashboard
		// with views when there are fewer than the limit, else the top 10.
		const totals = await viewTotals('7d')
		expect(top).toHaveLength(Math.min(totals.length, 10))
		expect(counts).toEqual(totals.slice(0, 10).map((t) => t.views))

		// Our five, with their names and 7-day sums, in rank order.
		const wide = await adminRead<TopRow[]>('/dashboards/top?period=7d&limit=1000')
		const ours = wide.filter((r) => ranked.some((x) => x.dash.uuid === r.dashboardUuid))
		expect(ours.map((r) => r.dashboardUuid)).toEqual(ranked.map((x) => x.dash.uuid))
		expect(ours.map((r) => r.name)).toEqual(ranked.map((x) => x.dash.name))
		expect(ours.map((r) => Number(r.viewCount))).toEqual(ranked.map((x) => x.views))
		expect(ours.map((r) => Number(r.uniqueViewerCount))).toEqual([1, 1, 1, 1, 1])
	})

	// @e2e dashboard-view-analytics::limit-parameter-is-respected
	test('limit=5 returns exactly the top five', async () => {
		const top = await adminRead<TopRow[]>('/dashboards/top?period=30d&limit=5')
		expect(top).toHaveLength(5)
		const totals = await viewTotals('30d')
		expect(top.map((r) => Number(r.viewCount))).toEqual(totals.slice(0, 5).map((t) => t.views))
	})

	// @e2e dashboard-view-analytics::valid-periods-are-7d-30d-90d
	test('7d, 30d and 90d are accepted and each includes today', async () => {
		const probe = ranked[0]
		for (const period of ['7d', '30d', '90d']) {
			const top = await adminRead<TopRow[]>(`/dashboards/top?period=${period}&limit=1000`)
			const row = top.find((r) => r.dashboardUuid === probe.dash.uuid)
			expect(Number(row?.viewCount), `${period} includes today's views`).toBe(probe.views)
			const daily = await dailyRows(probe.dash.uuid, period)
			expect(daily.map((r) => r.viewBucket)).toEqual([utcDate()])
		}
		// Not a pass-through: a period outside the three is refused.
		const bad = await admin.get(`${ANALYTICS}/dashboards/top?period=42d`)
		expect(bad.status()).toBe(400)
	})

	// @e2e dashboard-view-analytics::daily-breakdown-for-a-dashboard-30-day-period
	test('the 30-day breakdown is daily records within the period, oldest first', async () => {
		const probe = ranked[1]
		const res = await admin.get(`${ANALYTICS}/dashboards/${probe.dash.uuid}?period=30d`)
		expect(res.status()).toBe(200)
		const rows = (await res.json()) as DayRow[]
		expect(rows.length).toBeGreaterThan(0)
		// The spec's bound: viewBucket >= CURRENT_DATE - 30 days.
		const start = utcDate(30)
		for (const row of rows) {
			expect(Object.keys(row).sort()).toEqual(['uniqueViewerCount', 'viewBucket', 'viewCount'])
			expect(row.viewBucket >= start, `${row.viewBucket} is inside 30 days`).toBe(true)
		}
		const buckets = rows.map((r) => r.viewBucket)
		expect(buckets).toEqual([...buckets].sort())
		expect(rows[rows.length - 1]).toEqual({
			viewBucket: utcDate(),
			viewCount: probe.views,
			uniqueViewerCount: 1,
		})
	})

	// @e2e dashboard-view-analytics::missing-days-are-omitted
	test('days without views are left out, not filled with zeros', async () => {
		const rows = await dailyRows(ranked[2].dash.uuid, '7d')
		expect(rows).toHaveLength(1)
		expect(rows.every((r) => Number(r.viewCount) > 0)).toBe(true)
	})

	// @e2e dashboard-view-analytics::non-existent-dashboard-returns-404
	test('the breakdown of a dashboard that does not exist is 404', async () => {
		const res = await admin.get(`${ANALYTICS}/dashboards/00000000-0000-4000-8000-000000000000?period=7d`)
		expect(res.status()).toBe(404)
	})

	// @e2e dashboard-view-analytics::summary-returns-instance-wide-totals-and-top-5
	test('the summary has the instance totals, the period and the top five', async () => {
		const summary = await adminRead<{
			totalViewCount: number
			totalUniqueViewers: number
			dashboardCount: number
			period: string
			top5: TopRow[]
		}>('/summary?period=30d')

		const totals = await viewTotals('30d')
		expect(summary.period).toBe('30d')
		expect(Number(summary.totalViewCount)).toBe(totals.reduce((s, t) => s + t.views, 0))
		expect(Number(summary.totalUniqueViewers)).toBe(totals.reduce((s, t) => s + t.unique, 0))
		expect(Number(summary.dashboardCount)).toBe(totals.length)

		expect(summary.top5).toHaveLength(Math.min(5, totals.length))
		const counts = summary.top5.map((r) => Number(r.viewCount))
		expect(counts).toEqual([...counts].sort((a, b) => b - a))
		expect(counts).toEqual(totals.slice(0, 5).map((t) => t.views))
		for (const row of summary.top5) {
			expect(Object.keys(row)).toEqual(
				expect.arrayContaining(['dashboardUuid', 'name', 'viewCount', 'uniqueViewerCount']),
			)
		}
	})

	// @e2e dashboard-view-analytics::period-parameter-changes-calculation
	test('the summary totals follow the requested period', async () => {
		for (const period of ['7d', '30d']) {
			const summary = await adminRead<{ totalViewCount: number; totalUniqueViewers: number; period: string }>(
				`/summary?period=${period}`,
			)
			const totals = await viewTotals(period)
			expect(summary.period).toBe(period)
			expect(Number(summary.totalViewCount)).toBe(totals.reduce((s, t) => s + t.views, 0))
			expect(Number(summary.totalUniqueViewers)).toBe(totals.reduce((s, t) => s + t.unique, 0))
		}
	})

	// @e2e dashboard-view-analytics::csv-export-contains-dashboard-statistics
	// @e2e dashboard-view-analytics::csv-filename-includes-export-date
	test('the CSV export: text/csv attachment named for today, header, sorted rows', async () => {
		const { res, header, records } = await csvExport('/export?period=30d')
		expect(res.headers()['content-type']).toMatch(/^text\/csv/)
		expect(res.headers()['content-disposition']).toMatch(
			new RegExp(`^attachment; filename="?dashboard-analytics-${utcDate()}\\.csv"?$`),
		)
		expect(header).toEqual(['dashboardUuid', 'dashboardName', 'viewBucket', 'viewCount', 'uniqueViewerCount'])

		const keys = records.map((r) => [r.dashboardUuid, r.viewBucket] as const)
		const sorted = [...keys].sort((a, b) =>
			a[0] === b[0] ? (a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : 0) : a[0] < b[0] ? -1 : 1,
		)
		expect(keys).toEqual(sorted)

		const mine = records.find((r) => r.dashboardUuid === ranked[0].dash.uuid)
		expect(mine).toEqual({
			dashboardUuid: ranked[0].dash.uuid,
			dashboardName: ranked[0].dash.name,
			viewBucket: utcDate(),
			viewCount: String(ranked[0].views),
			uniqueViewerCount: '1',
		})
	})

	// @e2e dashboard-view-analytics::csv-respects-period-parameter
	test('the 7-day CSV holds only rows of the last 7 days', async () => {
		const week = await csvExport('/export?period=7d')
		const quarter = await csvExport('/export?period=90d')
		// The spec's bound: viewBucket >= CURRENT_DATE - 7 days.
		const start = utcDate(7)
		expect(week.records.length).toBeGreaterThan(0)
		for (const r of week.records) {
			expect(r.viewBucket >= start, `${r.viewBucket} is inside 7 days`).toBe(true)
		}
		// Older rows of the 90-day export are left out of the week...
		const key = (r: Record<string, string>) => `${r.dashboardUuid}|${r.viewBucket}`
		const weekKeys = new Set(week.records.map(key))
		for (const r of quarter.records.filter((q) => q.viewBucket < start)) {
			expect(weekKeys.has(key(r)), `${key(r)} is older than 7 days`).toBe(false)
		}
		// ...and every row of the last six days and today is in it.
		const recent = quarter.records.filter((q) => q.viewBucket >= utcDate(6))
		expect(week.records.filter((r) => r.viewBucket >= utcDate(6))).toEqual(recent)
	})

	// @e2e dashboard-view-analytics::non-admin-receives-403
	test('an ordinary account is refused every admin view report with 403', async () => {
		const uuid = ranked[0].dash.uuid
		const paths = [
			'/dashboards/top?period=7d',
			`/dashboards/${uuid}?period=7d`,
			'/summary?period=7d',
			'/export?period=30d',
		]
		for (const path of paths) {
			const refused = await alice!.api.get(`${ANALYTICS}${path}`)
			expect(refused.status(), `alice GET ${path}`).toBe(403)
			expect(refused.headers()['content-type'] ?? '').not.toMatch(/text\/csv/)
			expect(await refused.text()).not.toContain('dashboardUuid,')

			const allowed = await admin.get(`${ANALYTICS}${path}`)
			expect(allowed.status(), `admin GET ${path}`).toBe(200)
		}
	})
})

// ── REQ-TANLT-001..005: tile clicks ────────────────────────────────────

test.describe.serial('tile clicks (REQ-TANLT-001..005)', () => {
	// @e2e dashboard-view-analytics::authenticated-user-records-a-tile-click
	test('a logged-in click: 204, no body, clickCount for today goes up', async () => {
		const dash = await newDashboard('Tile click')
		const tile = await newTile(dash, `Click ${RUN}`)
		const res = await click(alice!.api, tile)
		expect(res.status()).toBe(204)
		expect((await res.body()).length).toBe(0)
		expect((await tileCounts(dash, tile)).clickCount).toBe(1)
	})

	// @e2e dashboard-view-analytics::one-row-per-tile-per-day
	// @e2e dashboard-view-analytics::same-actor-same-day-increments-clickcount-but-uniqueactorcount-once
	test('two clicks by one person in a day: one row, clickCount 2, uniqueActorCount 1', async () => {
		const dash = await newDashboard('Tile same actor')
		const tile = await newTile(dash, `Twice ${RUN}`)
		await clickOk(alice!.api, tile)
		await clickOk(alice!.api, tile)
		expect(await tileCounts(dash, tile)).toEqual({ clickCount: 2, uniqueActorCount: 1 })

		// The stored rows: one aggregate for this tile today, not one per click.
		const { records } = await csvExport('/tiles/export?period=7d')
		const mine = records.filter((r) => r.placementUuid === String(tile))
		expect(mine).toHaveLength(1)
		expect(mine[0]).toEqual({
			placementUuid: String(tile),
			dashboardUuid: dash.uuid,
			clickBucket: utcDate(),
			clickCount: '2',
			uniqueActorCount: '1',
		})
	})

	// @e2e dashboard-view-analytics::different-actors-same-day-increment-uniqueactorcount-separately
	test('two people clicking one tile: uniqueActorCount 2', async () => {
		const dash = await newDashboard('Tile two actors')
		const tile = await newTile(dash, `Two ${RUN}`)
		await clickOk(alice!.api, tile)
		await clickOk(bob!.api, tile)
		expect(await tileCounts(dash, tile)).toEqual({ clickCount: 2, uniqueActorCount: 2 })
	})

	// @e2e dashboard-view-analytics::unauthenticated-request-is-rejected
	test('an unauthenticated click is refused with 401 and records nothing', async () => {
		const dash = await newDashboard('Tile anonymous')
		const tile = await newTile(dash, `Anon ${RUN}`)
		const anon = await anonymousContext()
		try {
			expect((await click(anon, tile)).status()).toBe(401)
		} finally {
			await anon.dispose()
		}
		expect(await tileCounts(dash, tile)).toEqual({ clickCount: 0, uniqueActorCount: 0 })
		await clickOk(alice!.api, tile)
		expect((await tileCounts(dash, tile)).clickCount).toBe(1)
	})

	// @e2e dashboard-view-analytics::global-disable-makes-recording-a-no-op
	test('with analytics off a click is a 204 no-op that leaves no hash behind', async () => {
		const dash = await newDashboard('Tile global off')
		const tile = await newTile(dash, `Off ${RUN}`)
		await setEnabled('0')
		try {
			expect((await click(alice!.api, tile)).status()).toBe(204)
			expect(await tileCounts(dash, tile)).toEqual({ clickCount: 0, uniqueActorCount: 0 })
			const { records } = await csvExport('/tiles/export?period=7d')
			expect(records.filter((r) => r.placementUuid === String(tile))).toHaveLength(0)
		} finally {
			await setEnabled('1')
		}
		// First counted click of the day, so the disabled one cached no hash.
		await clickOk(alice!.api, tile)
		expect(await tileCounts(dash, tile)).toEqual({ clickCount: 1, uniqueActorCount: 1 })
	})

	// @e2e dashboard-view-analytics::per-user-opt-out-makes-that-users-clicks-a-no-op
	test('an opted-out person\'s click is a 204 no-op that leaves no hash behind', async () => {
		const dash = await newDashboard('Tile opt-out')
		const tile = await newTile(dash, `Eve ${RUN}`)
		await setOptOut(eve!.api, true)
		try {
			expect((await click(eve!.api, tile)).status()).toBe(204)
			expect(await tileCounts(dash, tile)).toEqual({ clickCount: 0, uniqueActorCount: 0 })
		} finally {
			await setOptOut(eve!.api, false)
		}
		await clickOk(eve!.api, tile)
		expect(await tileCounts(dash, tile)).toEqual({ clickCount: 1, uniqueActorCount: 1 })
	})

	// @e2e dashboard-view-analytics::opt-out-is-per-user-not-global-and-no-new-setting-exists
	test('the one analytics_optout governs tile clicks, per person', async () => {
		const dash = await newDashboard('Tile per user')
		const tile = await newTile(dash, `Frank ${RUN}`)
		await setOptOut(eve!.api, true)
		try {
			// The same switch the view counting reads is what the tile hook asks.
			const eveConfig = await eve!.api.get(`${API}/tile-analytics/config`)
			expect((await eveConfig.json()).enabled).toBe(false)
			const bobConfig = await bob!.api.get(`${API}/tile-analytics/config`)
			expect((await bobConfig.json()).enabled).toBe(true)

			await clickOk(eve!.api, tile)
			await clickOk(bob!.api, tile)
			expect(await tileCounts(dash, tile)).toEqual({ clickCount: 1, uniqueActorCount: 1 })
		} finally {
			await setOptOut(eve!.api, false)
		}
		const eveConfig = await eve!.api.get(`${API}/tile-analytics/config`)
		expect((await eveConfig.json()).enabled).toBe(true)
	})

	// @e2e dashboard-view-analytics::default-enabled
	test('with no analytics setting at all, clicks are recorded', async () => {
		const dash = await newDashboard('Tile default')
		const tile = await newTile(dash, `Default ${RUN}`)
		await setEnabled(null)
		try {
			await clickOk(alice!.api, tile)
			expect(await tileCounts(dash, tile)).toEqual({ clickCount: 1, uniqueActorCount: 1 })
		} finally {
			await setEnabled(originalEnabled === null ? null : '1')
		}
	})

	// @e2e dashboard-view-analytics::top-tiles-by-click-count
	test('top tiles: sorted by clicks, with placement, dashboard and period sums', async () => {
		const dash = await newDashboard('Top tiles')
		const tiles: { id: number; clicks: number }[] = []
		for (const clicks of [8, 6, 4, 2]) {
			const id = await newTile(dash, `Top ${clicks} ${RUN}`)
			for (let i = 0; i < clicks; i++) {
				await clickOk(admin, id)
			}
			tiles.push({ id, clicks })
		}

		const top = await adminRead<TileRow[]>('/tiles/top?period=7d&limit=10')
		for (const row of top) {
			expect(Object.keys(row)).toEqual(
				expect.arrayContaining(['placementUuid', 'dashboardUuid', 'clickCount', 'uniqueActorCount']),
			)
		}
		const counts = top.map((r) => Number(r.clickCount))
		expect(counts).toEqual([...counts].sort((a, b) => b - a))

		// The period sums agree with the stored rows of the same period.
		const { records } = await csvExport('/tiles/export?period=7d')
		const sums = new Map<string, number>()
		for (const r of records) {
			sums.set(r.placementUuid, (sums.get(r.placementUuid) ?? 0) + Number(r.clickCount))
		}
		expect(counts).toEqual([...sums.values()].sort((a, b) => b - a).slice(0, 10))

		const wide = await adminRead<TileRow[]>('/tiles/top?period=7d&limit=1000')
		const ours = wide.filter((r) => tiles.some((t) => String(t.id) === String(r.placementUuid)))
		expect(ours.map((r) => String(r.placementUuid))).toEqual(tiles.map((t) => String(t.id)))
		expect(ours.map((r) => Number(r.clickCount))).toEqual([8, 6, 4, 2])
		expect(ours.map((r) => Number(r.uniqueActorCount))).toEqual([1, 1, 1, 1])
		expect(ours.every((r) => r.dashboardUuid === dash.uuid)).toBe(true)
	})

	// @e2e dashboard-view-analytics::per-dashboard-tile-breakdown
	test('the per-dashboard breakdown lists that dashboard\'s three tiles for 30 days', async () => {
		const dash = await newDashboard('Breakdown')
		const other = await newDashboard('Breakdown other')
		const tiles = [await newTile(dash, `B1 ${RUN}`), await newTile(dash, `B2 ${RUN}`), await newTile(dash, `B3 ${RUN}`)]
		const elsewhere = await newTile(other, `Elsewhere ${RUN}`)
		for (const [i, id] of tiles.entries()) {
			for (let n = 0; n <= i; n++) {
				await clickOk(alice!.api, id)
			}
		}
		await clickOk(alice!.api, elsewhere)

		const res = await admin.get(`${ANALYTICS}/tiles/by-dashboard/${dash.uuid}?period=30d`)
		expect(res.status()).toBe(200)
		const rows = (await res.json()) as TileRow[]
		expect(rows.map((r) => String(r.placementUuid)).sort()).toEqual(tiles.map(String).sort())

		// The sums are exactly the 30-day rows of the export for those tiles.
		const { records } = await csvExport('/tiles/export?period=30d')
		const start = utcDate(30)
		for (const id of tiles) {
			const stored = records.filter((r) => r.placementUuid === String(id))
			expect(stored.every((r) => r.clickBucket >= start)).toBe(true)
			const row = rows.find((r) => String(r.placementUuid) === String(id))
			expect(Number(row?.clickCount)).toBe(stored.reduce((s, r) => s + Number(r.clickCount), 0))
		}
		expect(rows.map((r) => Number(r.clickCount)).sort()).toEqual([1, 2, 3])
	})

	// @e2e dashboard-view-analytics::csv-export-contains-tile-statistics
	test('the tile CSV: text/csv attachment named for today, header, sorted rows', async () => {
		const dash = await newDashboard('Tile CSV')
		const tile = await newTile(dash, `CSV ${RUN}`)
		await clickOk(alice!.api, tile)

		const { res, header, records } = await csvExport('/tiles/export?period=30d')
		expect(res.headers()['content-type']).toMatch(/^text\/csv/)
		expect(res.headers()['content-disposition']).toMatch(
			new RegExp(`^attachment; filename="?tile-analytics-${utcDate()}\\.csv"?$`),
		)
		expect(header).toEqual(['placementUuid', 'dashboardUuid', 'clickBucket', 'clickCount', 'uniqueActorCount'])
		const keys = records.map((r) => [r.placementUuid, r.clickBucket] as const)
		const sorted = [...keys].sort((a, b) =>
			a[0] === b[0] ? (a[1] < b[1] ? -1 : a[1] > b[1] ? 1 : 0) : a[0] < b[0] ? -1 : 1,
		)
		expect(keys).toEqual(sorted)
		expect(records.find((r) => r.placementUuid === String(tile))).toEqual({
			placementUuid: String(tile),
			dashboardUuid: dash.uuid,
			clickBucket: utcDate(),
			clickCount: '1',
			uniqueActorCount: '1',
		})
	})

	// @e2e dashboard-view-analytics::non-admin-receives-403
	// @e2e dashboard-view-analytics::non-admin-cannot-export
	test('an ordinary account is refused the tile reports and export with 403', async () => {
		const dash = await newDashboard('Tile refusal')
		for (const path of ['/tiles/top?period=7d', `/tiles/by-dashboard/${dash.uuid}?period=30d`, '/tiles/export?period=30d']) {
			const refused = await alice!.api.get(`${ANALYTICS}${path}`)
			expect(refused.status(), `alice GET ${path}`).toBe(403)
			expect(refused.headers()['content-type'] ?? '').not.toMatch(/text\/csv/)
			expect(await refused.text()).not.toContain('placementUuid')

			const allowed = await admin.get(`${ANALYTICS}${path}`)
			expect(allowed.status(), `admin GET ${path}`).toBe(200)
		}
	})
})

// ── REQ-TILEUI-001/002: the Tiles report on the admin page ─────────────

test.describe.serial('the Tiles report in administration (REQ-TILEUI-001/002)', () => {
	let team: Dashboard
	let tile: number
	const tileTitle = `Zaaksysteem ${RUN}`

	test.beforeAll(async () => {
		team = await newDashboard('Team')
		tile = await newTile(team, tileTitle)
		for (let i = 0; i < 6; i++) {
			await clickOk(alice!.api, tile)
			await clickOk(bob!.api, tile)
		}
	})

	test.beforeEach(async ({ page }) => {
		await page.context().setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	})

	/**
	 * Open the analytics on the admin page for a period.
	 *
	 * @param page the admin's page.
	 * @param period the period to choose.
	 * @return nothing.
	 */
	async function openTiles(page: Page, period: string): Promise<void> {
		await page.goto(ADMIN_PAGE)
		const select = page.locator('#launchpad-analytics-period')
		await expect(select).toBeVisible({ timeout: 30_000 })
		await select.selectOption(period)
		await expect(page.locator('[data-testid="tile-click-report"]')).toBeVisible()
	}

	// @e2e dashboard-view-analytics::top-tiles-for-thirty-days
	test('the 30-day tiles table shows Team, Zaaksysteem, 12 clicks, 2 people', async ({ page }) => {
		await openTiles(page, '30d')
		const row = page.locator('[data-testid="tile-click-row"]').filter({ hasText: tileTitle })
		await expect(row).toHaveCount(1, { timeout: 20_000 })
		await expect(row.locator('td')).toHaveText([team.name, tileTitle, '12', '2'])
	})

	// @e2e dashboard-view-analytics::export
	test('"Export CSV" downloads the file the export endpoint serves', async ({ page }) => {
		await openTiles(page, '30d')
		await expect(page.locator('[data-testid="tile-click-table"]')).toBeVisible({ timeout: 20_000 })

		const served = page.waitForResponse((res) =>
			/\/api\/(v1\/)?admin\/analytics\/tiles\/export/.test(new URL(res.url()).pathname),
		)
		const downloaded = page.waitForEvent('download')
		await page.locator('[data-testid="tile-export"]').click()
		const response = await served
		expect(response.status()).toBe(200)
		expect(new URL(response.url()).searchParams.get('period')).toBe('30d')

		const download = await downloaded
		const stream = await download.createReadStream()
		const chunks: Buffer[] = []
		for await (const chunk of stream) {
			chunks.push(Buffer.from(chunk))
		}
		const file = Buffer.concat(chunks).toString('utf8')
		expect(file).toBe(await response.text())
		expect(parseCsv(file)[0]).toEqual(['placementUuid', 'dashboardUuid', 'clickBucket', 'clickCount', 'uniqueActorCount'])
		expect(file).toContain(`"${tile}","${team.uuid}"`)
	})

	// @e2e dashboard-view-analytics::tracking-off
	test('with tracking off the section says so and shows no table or export', async ({ page }) => {
		await setEnabled('0')
		try {
			await openTiles(page, '30d')
			const off = page.locator('[data-testid="tile-tracking-off"]')
			await expect(off).toBeVisible({ timeout: 20_000 })
			await expect(off).toContainText('not being recorded')
			await expect(page.locator('[data-testid="tile-click-table"]')).toHaveCount(0)
			await expect(page.locator('[data-testid="tile-export"]')).toHaveCount(0)
		} finally {
			await setEnabled('1')
		}
		// Control: on again, the same page has the table back.
		await openTiles(page, '30d')
		await expect(page.locator('[data-testid="tile-click-table"]')).toBeVisible({ timeout: 20_000 })
	})
})

// ── REQ-ANLT-011: the view-event the dashboard sends ───────────────────

test.describe.serial('the frontend view-event (REQ-ANLT-011)', () => {
	let dashA: Dashboard
	let dashB: Dashboard

	test.beforeAll(async () => {
		dashA = await newDashboard('Frontend A', [alice!])
		dashB = await newDashboard('Frontend B', [alice!])
	})

	// @e2e dashboard-view-analytics::dashboard-component-calls-view-event-on-mount
	test('opening a dashboard sends one view-event, without waiting for it and without a UI change', async ({ browser }) => {
		const { context, page } = await browserAs(browser, alice!)
		try {
			const sent = collectViewEvents(context, dashA.uuid)
			let released = false
			let markSeen: () => void = () => {}
			const seen = new Promise<void>((resolve) => {
				markSeen = resolve
			})
			await page.route(/\/view-event$/, async (route) => {
				markSeen()
				await new Promise((resolve) => setTimeout(resolve, 4_000))
				released = true
				await route.continue()
			})
			const answered = page.waitForResponse((res) => isViewEvent(res.request(), dashA.uuid), { timeout: 60_000 })

			await page.goto(`${APP}/${dashA.slug}`)
			await seen
			// The dashboard is on screen while the view-event is still held.
			await waitForDashboard(page, dashA.uuid)
			expect(released, 'the dashboard rendered before the view-event answered').toBe(false)

			const response = await answered
			expect(response.status()).toBe(204)
			await page.waitForTimeout(2_000)
			expect(sent).toHaveLength(1)
			// Handled silently: no error toast, the dashboard still there.
			await expect(page.locator('.toast-error')).toHaveCount(0)
			await waitForDashboard(page, dashA.uuid)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-view-analytics::debouncing-prevents-multi-tab-double-counting
	test('two tabs opening the same dashboard within a second send one view-event', async ({ browser }) => {
		const { context, page } = await browserAs(browser, alice!)
		try {
			const second = await context.newPage()
			const sent = collectViewEvents(context, dashA.uuid)
			await Promise.all([page.goto(`${APP}/${dashA.slug}`), second.goto(`${APP}/${dashA.slug}`)])
			await Promise.all([waitForDashboard(page, dashA.uuid), waitForDashboard(second, dashA.uuid)])
			await page.waitForTimeout(2_000)
			expect(sent, 'view-event POSTs across both tabs').toHaveLength(1)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-view-analytics::different-dashboards-are-not-debounced-against-each-other
	test('two tabs on two dashboards each send their own view-event', async ({ browser }) => {
		const { context, page } = await browserAs(browser, alice!)
		try {
			const second = await context.newPage()
			const sentA = collectViewEvents(context, dashA.uuid)
			const sentB = collectViewEvents(context, dashB.uuid)
			await Promise.all([page.goto(`${APP}/${dashA.slug}`), second.goto(`${APP}/${dashB.slug}`)])
			await Promise.all([waitForDashboard(page, dashA.uuid), waitForDashboard(second, dashB.uuid)])
			await expect.poll(() => sentA.length, { timeout: 10_000 }).toBeGreaterThanOrEqual(1)
			await expect.poll(() => sentB.length, { timeout: 10_000 }).toBeGreaterThanOrEqual(1)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-view-analytics::reload-on-same-dashboard-triggers-new-event
	test('reloading a dashboard sends a new view-event', async ({ browser }) => {
		const { context, page } = await browserAs(browser, alice!)
		try {
			const sent = collectViewEvents(context, dashB.uuid)
			await page.goto(`${APP}/${dashB.slug}`)
			await waitForDashboard(page, dashB.uuid)
			await expect.poll(() => sent.length, { timeout: 10_000 }).toBe(1)

			await page.reload()
			await waitForDashboard(page, dashB.uuid)
			await expect.poll(() => sent.length, { timeout: 10_000 }).toBe(2)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-view-analytics::view-event-is-not-sent-if-analytics-is-disabled
	test('with analytics off, opening a dashboard sends no view-event', async ({ browser }) => {
		await setEnabled('0')
		const { context, page } = await browserAs(browser, alice!)
		try {
			const sent = collectViewEvents(context, dashA.uuid)
			await page.goto(`${APP}/${dashA.slug}`)
			await waitForDashboard(page, dashA.uuid)
			await page.waitForTimeout(3_000)
			expect(sent, 'view-event POSTs while analytics is off').toHaveLength(0)
		} finally {
			await context.close()
			await setEnabled('1')
		}
	})
})

// ── REQ-ANLT-UI-001: "Count my visits" ────────────────────────────────

test.describe.serial('"Count my visits" in personal settings (REQ-ANLT-UI-001)', () => {
	let dash: Dashboard

	test.beforeAll(async () => {
		dash = await newDashboard('Count my visits', [eve!])
		await setOptOut(eve!.api, false)
	})

	test.afterAll(async () => {
		await setOptOut(eve!.api, false)
	})

	/**
	 * Visit the dashboard in the browser and wait for its view-event answer.
	 *
	 * @param page eve's page.
	 * @return nothing.
	 */
	async function visit(page: Page): Promise<void> {
		const answered = page.waitForResponse((res) => isViewEvent(res.request(), dash.uuid), { timeout: 45_000 })
		await page.goto(`${APP}/${dash.slug}`)
		await waitForDashboard(page, dash.uuid)
		expect((await answered).status()).toBe(204)
	}

	/**
	 * Flip the switch and wait for the server to store it.
	 *
	 * @param page eve's page, settings open.
	 * @param optedOut the value the switch must store.
	 * @return nothing.
	 */
	async function flip(page: Page, optedOut: boolean): Promise<void> {
		const stored = page.waitForResponse(
			(res) => res.request().method() === 'PUT' && /\/api\/(v1\/)?analytics\/me$/.test(new URL(res.url()).pathname),
		)
		await page.locator('[data-testid="launchpad-count-my-visits"]').click()
		const res = await stored
		expect(res.status()).toBe(200)
		expect(res.request().postDataJSON()).toEqual({ optedOut })
	}

	// @e2e dashboard-view-analytics::a-person-stops-their-visits-being-counted
	test('turning "Count my visits" off stores the opt-out and the next visit adds nothing', async ({ browser }) => {
		const { context, page } = await browserAs(browser, eve!)
		try {
			await page.goto(`${APP}/${dash.slug}`)
			await waitForDashboard(page, dash.uuid)
			await openPersonalSettings(page)
			const toggle = page.locator('[data-testid="launchpad-count-my-visits"]')
			await expect(toggle).toBeVisible({ timeout: 20_000 })
			await expect(toggle.locator('input')).toBeChecked()
			await flip(page, true)
			await expect(toggle.locator('input')).not.toBeChecked()

			const me = await eve!.api.get(`${API}/analytics/me`)
			expect((await me.json()).optedOut).toBe(true)

			const before = await todayCounts(dash.uuid)
			await visit(page)
			expect(await todayCounts(dash.uuid)).toEqual(before)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-view-analytics::a-person-counts-again
	test('turning it on again stores false and the next visit is counted', async ({ browser }) => {
		await setOptOut(eve!.api, true)
		const { context, page } = await browserAs(browser, eve!)
		try {
			await page.goto(`${APP}/${dash.slug}`)
			await waitForDashboard(page, dash.uuid)
			await openPersonalSettings(page)
			const toggle = page.locator('[data-testid="launchpad-count-my-visits"]')
			await expect(toggle).toBeVisible({ timeout: 20_000 })
			await expect(toggle.locator('input')).not.toBeChecked()
			await flip(page, false)
			await expect(toggle.locator('input')).toBeChecked()

			const me = await eve!.api.get(`${API}/analytics/me`)
			expect((await me.json()).optedOut).toBe(false)

			const before = await todayCounts(dash.uuid)
			await visit(page)
			expect((await todayCounts(dash.uuid)).viewCount).toBe(before.viewCount + 1)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-view-analytics::analytics-is-off-for-everyone
	test('with analytics off for the organisation the switch is not shown', async ({ browser }) => {
		const { context, page } = await browserAs(browser, eve!)
		try {
			// Control: with analytics on, the same dialog has the switch.
			await page.goto(`${APP}/${dash.slug}`)
			await waitForDashboard(page, dash.uuid)
			await openPersonalSettings(page)
			await expect(page.locator('[data-testid="launchpad-count-my-visits"]')).toBeVisible({ timeout: 20_000 })

			await setEnabled('0')
			await page.goto(`${APP}/${dash.slug}`)
			await waitForDashboard(page, dash.uuid)
			await openPersonalSettings(page)
			await expect(page.getByRole('dialog').first()).toBeVisible({ timeout: 20_000 })
			// The answer that decides it has arrived before the absence is read.
			await page.waitForTimeout(2_000)
			await expect(page.locator('[data-testid="launchpad-count-my-visits"]')).toHaveCount(0)
		} finally {
			await context.close()
			await setEnabled('1')
		}
	})
})
