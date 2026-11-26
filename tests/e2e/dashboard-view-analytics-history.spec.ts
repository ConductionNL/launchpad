/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the dashboard-view-analytics scenarios that
 * need a row on another day, or the table itself
 * (openspec/specs/dashboard-view-analytics), written for
 * e2e-coverage-programme task 5.1.
 *
 * These were excluded as "only today's bucket can be written from outside" and
 * "the migration's schema has no HTTP or UI read". Both reasons assumed a test
 * can only reach the instance over HTTP. It can also run PHP there with
 * Nextcloud booted (`support/instanceDb.ts`, on the route `support/occ.ts`
 * already uses for occ), which gives it the app's own database connection on
 * whatever database the instance runs. That is used for two things only:
 *   - to read the live schema the migrations left, and the rows a request
 *     wrote, exactly as stored;
 *   - to arrange a GIVEN no request can: move a row a real request wrote to
 *     yesterday (a day passing), or seed rows on past dates.
 * What is judged is still the product's own behaviour, driven through its
 * endpoints and its registered background job: the view-event endpoint
 * opening a new bucket, the report endpoints' date range, the purge job's
 * deletions.
 *
 * A day passing is simulated the way the product sees one: the row written
 * today is moved to yesterday, and the stored salt date
 * (`analytics_dailysalt_date`) is set to yesterday, which is what the salt the
 * dedup holds looks like the morning after. The dedup cache is NOT cleared, so
 * a viewer counted again after the "day change" can only be explained by a
 * different hash.
 *
 * Every row this file writes or seeds belongs to a dashboard it created, and
 * is deleted afterwards. Settings it changes (`analytics_enabled`, the
 * admin's own opt-out, `analytics_retention_days`, the personal dashboard
 * flags) are read first and put back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-view-analytics::create-views-table-with-correct-schema
 *   @e2e dashboard-view-analytics::create-tile-clicks-table-with-correct-schema
 *   @e2e dashboard-view-analytics::two-different-days-create-separate-rows
 *   @e2e dashboard-view-analytics::daily-salt-rotation-prevents-user-re-identification-across-days
 *   @e2e dashboard-view-analytics::period-parameter-filters-date-range
 *   @e2e dashboard-view-analytics::period-parameter-filters-the-date-range
 *   @e2e dashboard-view-analytics::purge-job-is-automatic-and-idempotent
 *   @e2e dashboard-view-analytics::retention-purge-also-deletes-tile-rows
 *   @e2e dashboard-view-analytics::daily-salt-is-shared-not-duplicated
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRow } from './support/dashboardHierarchy.ts'
import type { TableShape } from './support/instanceDb.ts'

import { expect, test } from '@playwright/test'
import {
	allowManyPersonalDashboards,
	createDashboard,
} from './support/dashboardHierarchy.ts'
import {
	dateOf,
	deleteRows,
	insertRow,
	selectRows,
	tableShape,
	updateRows,
} from './support/instanceDb.ts'
import {
	appValue,
	describeRun,
	executeJobNow,
	runOcc,
	setIntAppValue,
} from './support/occ.ts'
import { adminContext, API } from './support/principals.ts'

const RUN = Date.now()
const STAMP = `E2E ANLT history ${RUN}`

const VIEWS = 'launchpad_dashboard_views'
const CLICKS = 'launchpad_tile_clicks'
const ANALYTICS = `${API}/admin/analytics`

/** How long a web process may serve app config from its local cache (Nextcloud 34: 3 s), plus a margin. */
const APP_CONFIG_LOCAL_CACHE_MS = 4_000
const ENABLED_KEY =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/analytics_enabled'
const PURGE_JOB = 'OCA\\LaunchPad\\BackgroundJob\\PurgeViewsJob'
const SALT_KEY = 'analytics_dailysalt'
const SALT_DATE_KEY = 'analytics_dailysalt_date'
const RETENTION_KEY = 'analytics_retention_days'

type Row = Record<string, string | number | null>
type TopRow = { dashboardUuid: string; viewCount: number; uniqueViewerCount: number }
type TileRow = {
	placementUuid: string
	dashboardUuid: string
	clickCount: number
	uniqueActorCount: number
}

let admin: APIRequestContext
let restoreSettings: (() => Promise<void>) | null = null
let enabledBefore: string | null = null
let optedOutBefore = false
let retentionBefore: string | null = null
const dashboards: DashboardRow[] = []
let seq = 0

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

	const me = await admin.get(`${API}/analytics/me`)
	expect(me.status(), await me.text()).toBe(200)
	optedOutBefore = (await me.json()).optedOut === true
	if (optedOutBefore) {
		await setOptOut(false)
	}
})

test.afterAll(async () => {
	for (const d of dashboards) {
		await deleteRows(VIEWS, { dashboard_uuid: d.uuid }).catch(() => undefined)
		await deleteRows(CLICKS, { dashboard_uuid: d.uuid }).catch(() => undefined)
		await admin.delete(`${API}/dashboard/${d.id}`).catch(() => undefined)
	}
	await setIntAppValue(
		RETENTION_KEY,
		retentionBefore === null ? null : Number.parseInt(retentionBefore, 10),
	).catch(() => undefined)
	if (optedOutBefore) {
		await setOptOut(true).catch(() => undefined)
	}
	if (enabledBefore === null) {
		await admin.delete(`${ENABLED_KEY}?format=json`).catch(() => undefined)
	} else {
		await admin
			.post(`${ENABLED_KEY}?format=json`, { form: { value: enabledBefore } })
			.catch(() => undefined)
	}
	if (restoreSettings !== null) {
		await restoreSettings()
	}
	await admin.dispose()
})

// ── helpers ────────────────────────────────────────────────────────────

/**
 * A UTC date relative to today, as buckets and cutoffs are written.
 *
 * @param offsetDays days from today (negative is the past).
 * @return YYYY-MM-DD.
 */
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
 * Store the admin's own opt-out through the endpoint the settings switch uses.
 *
 * @param optedOut the value.
 * @return nothing.
 */
async function setOptOut(optedOut: boolean): Promise<void> {
	const res = await admin.put(`${API}/analytics/me`, { data: { optedOut } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * A fresh admin-owned dashboard, so every row read back belongs to this test.
 *
 * @param label a readable part of the name.
 * @return the dashboard.
 */
async function newDashboard(label: string): Promise<DashboardRow> {
	seq += 1
	const d = await createDashboard(admin, { name: `${STAMP} ${label} ${seq}` })
	dashboards.push(d)
	return d
}

/**
 * Add a tile (a text widget) to a dashboard.
 *
 * @param dashboard the dashboard.
 * @return the placement id, which the click table stores as `placement_uuid`.
 */
async function newTile(dashboard: DashboardRow): Promise<number> {
	const widget = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			content: { text: `${STAMP} tile`, contentMode: 'markdown' },
		},
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)
	const placement = await widget.json()
	const id = Number(placement.id ?? placement.data?.id ?? placement.placement?.id)
	expect(id, `no placement id in ${JSON.stringify(placement)}`).toBeGreaterThan(0)
	return id
}

/**
 * Post a view event as the admin, which must be accepted with 204.
 *
 * @param uuid the dashboard.
 * @return nothing.
 */
async function viewOk(uuid: string): Promise<void> {
	const res = await admin.post(`${API}/dashboards/${uuid}/view-event`, {
		data: {},
		headers: { 'Content-Type': 'application/json' },
	})
	expect(res.status(), await res.text()).toBe(204)
}

/**
 * Post a tile click as the admin, which must be accepted with 204.
 *
 * @param placementId the tile.
 * @return nothing.
 */
async function clickOk(placementId: number): Promise<void> {
	const res = await admin.post(`${API}/tile-click/${placementId}`, {
		data: {},
		headers: { 'Content-Type': 'application/json' },
	})
	expect(res.status(), await res.text()).toBe(204)
}

/**
 * GET an admin analytics endpoint and return its JSON.
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
 * The stored view rows of one dashboard, keyed by bucket date.
 *
 * @param uuid the dashboard.
 * @return bucket => [viewCount, uniqueViewerCount].
 */
async function viewRowsByDay(uuid: string): Promise<Map<string, [number, number]>> {
	const rows = await selectRows(VIEWS, { dashboard_uuid: uuid })
	const byDay = new Map<string, [number, number]>()
	for (const r of rows as Row[]) {
		const day = dateOf(r.view_bucket)
		expect(byDay.has(day), `one row per dashboard per day, ${day} twice`).toBe(false)
		byDay.set(day, [Number(r.view_count), Number(r.unique_viewer_count)])
	}
	return byDay
}

/**
 * The stored click rows of one tile, keyed by bucket date.
 *
 * @param placementId the tile.
 * @return bucket => [clickCount, uniqueActorCount].
 */
async function clickRowsByDay(placementId: number): Promise<Map<string, [number, number]>> {
	const rows = await selectRows(CLICKS, { placement_uuid: String(placementId) })
	const byDay = new Map<string, [number, number]>()
	for (const r of rows as Row[]) {
		const day = dateOf(r.click_bucket)
		expect(byDay.has(day), `one row per tile per day, ${day} twice`).toBe(false)
		byDay.set(day, [Number(r.click_count), Number(r.unique_actor_count)])
	}
	return byDay
}

/**
 * Seed a view row for a dashboard on a past date, and insist it went in.
 *
 * @param uuid the dashboard.
 * @param day the bucket date.
 * @param views the view count.
 * @param unique the unique-viewer count.
 * @return nothing.
 */
async function seedView(uuid: string, day: string, views: number, unique: number): Promise<void> {
	const refused = await insertRow(VIEWS, {
		dashboard_uuid: uuid,
		view_bucket: day,
		view_count: views,
		unique_viewer_count: unique,
	})
	expect(refused, `seed ${day}: ${JSON.stringify(refused)}`).toBeNull()
}

/**
 * Seed a click row for a tile on a past date, and insist it went in.
 *
 * @param placementId the tile.
 * @param dashboardUuid the dashboard it is on.
 * @param day the bucket date.
 * @param clicks the click count.
 * @param unique the unique-actor count.
 * @return nothing.
 */
async function seedClick(
	placementId: number,
	dashboardUuid: string,
	day: string,
	clicks: number,
	unique: number,
): Promise<void> {
	const refused = await insertRow(CLICKS, {
		placement_uuid: String(placementId),
		dashboard_uuid: dashboardUuid,
		click_bucket: day,
		click_count: clicks,
		unique_actor_count: unique,
	})
	expect(refused, `seed ${day}: ${JSON.stringify(refused)}`).toBeNull()
}

/**
 * Let a day pass for one dashboard as the product would see it: the row a
 * request wrote today becomes yesterday's, and the stored salt is dated
 * yesterday. The dedup cache is left alone.
 *
 * @param uuid the dashboard.
 * @return nothing.
 */
async function letADayPass(uuid: string): Promise<void> {
	const moved = await updateRows(
		VIEWS,
		{ view_bucket: utcDate(-1) },
		{ dashboard_uuid: uuid, view_bucket: utcDate() },
	)
	expect(moved, "today's row becomes yesterday's").toBe(1)
	const dated = await runOcc(
		'config:app:set',
		'launchpad',
		SALT_DATE_KEY,
		`--value=${utcDate(-1)}`,
	)
	expect(dated.code, describeRun(dated)).toBe(0)
	// Nextcloud 34 keeps app config in each web process's local cache for
	// 3 s (AppConfig::LOCAL_CACHE_TTL), which occ cannot clear. Wait it out,
	// so the next view reads the moved salt date as midnight would leave it.
	await new Promise((resolve) => setTimeout(resolve, APP_CONFIG_LOCAL_CACHE_MS))
}

/**
 * Assert a column's shape.
 *
 * @param shape the table.
 * @param name the column (snake_case, as Nextcloud entities store a camelCase
 *        property: `dashboardUuid` is `dashboard_uuid`).
 * @param want the expected type family, nullability, default and length.
 * @param want.types the acceptable DBAL type names.
 * @param want.default the default, or undefined to not check one.
 * @param want.length the length, or undefined to not check one.
 * @return nothing.
 */
function expectColumn(
	shape: TableShape,
	name: string,
	want: { types: string[]; default?: string; length?: number },
): void {
	const col = shape.columns[name]
	expect(col, `${shape.name} has a ${name} column`).toBeDefined()
	expect(want.types, `${name} type`).toContain(col.type)
	expect(col.notnull, `${name} is NOT NULL`).toBe(true)
	if (want.default !== undefined) {
		expect(String(col.default ?? '').replace(/^'(.*)'$/, '$1'), `${name} defaults to ${want.default}`).toBe(
			want.default,
		)
	}
	if (want.length !== undefined) {
		expect(col.length, `${name} length`).toBe(want.length)
	}
}

/**
 * Find the index over exactly these columns.
 *
 * @param shape the table.
 * @param columns the columns, in order.
 * @return the index, or undefined.
 */
function indexOn(shape: TableShape, columns: string[]) {
	return Object.values(shape.indexes).find(
		(i) => i.columns.join(',') === columns.join(','),
	)
}

/**
 * Run the purge job once, which must succeed.
 *
 * @return nothing.
 */
async function purge(): Promise<void> {
	const result = await executeJobNow(PURGE_JOB)
	expect(result.code, describeRun(result)).toBe(0)
}

// ── REQ-ANLT-001 / REQ-TANLT-001: the tables ───────────────────────────

test.describe('the analytics tables, as the migrations left them', () => {
	// @e2e dashboard-view-analytics::create-views-table-with-correct-schema
	test('the views table has the five columns, the (dashboard, day) unique index and the day index', async () => {
		const shape = await tableShape(VIEWS)
		expect(shape, `${VIEWS} exists`).not.toBeNull()

		const id = shape!.columns.id
		expect(['integer', 'bigint'], 'id is an integer').toContain(id?.type)
		expect(id.autoincrement, 'id auto-increments').toBe(true)
		const primary = Object.values(shape!.indexes).find((i) => i.primary)
		expect(primary?.columns, 'id is the primary key').toEqual(['id'])

		expectColumn(shape!, 'dashboard_uuid', { types: ['string'], length: 36 })
		expectColumn(shape!, 'view_bucket', { types: ['date', 'date_immutable'] })
		expectColumn(shape!, 'view_count', { types: ['integer', 'bigint'], default: '0' })
		expectColumn(shape!, 'unique_viewer_count', { types: ['integer', 'bigint'], default: '0' })
		expect(
			Object.keys(shape!.columns).sort(),
			'no other column: nothing about a viewer is stored',
		).toEqual(['dashboard_uuid', 'id', 'unique_viewer_count', 'view_bucket', 'view_count'])

		expect(indexOn(shape!, ['dashboard_uuid', 'view_bucket'])?.unique, 'unique (dashboard, day)').toBe(true)
		expect(indexOn(shape!, ['view_bucket']), 'an index on the day').toBeDefined()

		// The unique index is enforced by this database, not just declared.
		const d = await newDashboard('schema')
		await seedView(d.uuid, utcDate(-3), 1, 1)
		const dup = await insertRow(VIEWS, {
			dashboard_uuid: d.uuid,
			view_bucket: utcDate(-3),
			view_count: 1,
			unique_viewer_count: 1,
		})
		expect(dup?.unique, `a second row for the same day is refused: ${JSON.stringify(dup)}`).toBe(true)
	})

	// @e2e dashboard-view-analytics::create-tile-clicks-table-with-correct-schema
	test('the tile clicks table has the six columns, the (tile, day) unique index and the day index', async () => {
		const shape = await tableShape(CLICKS)
		expect(shape, `${CLICKS} exists`).not.toBeNull()

		const id = shape!.columns.id
		expect(['integer', 'bigint'], 'id is an integer').toContain(id?.type)
		expect(id.autoincrement, 'id auto-increments').toBe(true)
		const primary = Object.values(shape!.indexes).find((i) => i.primary)
		expect(primary?.columns, 'id is the primary key').toEqual(['id'])

		expectColumn(shape!, 'placement_uuid', { types: ['string'], length: 36 })
		expectColumn(shape!, 'dashboard_uuid', { types: ['string'], length: 36 })
		expectColumn(shape!, 'click_bucket', { types: ['date', 'date_immutable'] })
		expectColumn(shape!, 'click_count', { types: ['integer', 'bigint'], default: '0' })
		expectColumn(shape!, 'unique_actor_count', { types: ['integer', 'bigint'], default: '0' })
		expect(
			Object.keys(shape!.columns).sort(),
			'no other column: no per-event or per-person data',
		).toEqual(['click_bucket', 'click_count', 'dashboard_uuid', 'id', 'placement_uuid', 'unique_actor_count'])

		expect(indexOn(shape!, ['placement_uuid', 'click_bucket'])?.unique, 'unique (tile, day)').toBe(true)
		expect(indexOn(shape!, ['click_bucket']), 'an index on the day').toBeDefined()

		const d = await newDashboard('tile schema')
		const tile = await newTile(d)
		await seedClick(tile, d.uuid, utcDate(-3), 1, 1)
		const dup = await insertRow(CLICKS, {
			placement_uuid: String(tile),
			dashboard_uuid: d.uuid,
			click_bucket: utcDate(-3),
			click_count: 1,
			unique_actor_count: 1,
		})
		expect(dup?.unique, `a second row for the same day is refused: ${JSON.stringify(dup)}`).toBe(true)
	})
})

// ── REQ-ANLT-001 / REQ-ANLT-003: a day passing ─────────────────────────

test.describe('a day passing', () => {
	// @e2e dashboard-view-analytics::two-different-days-create-separate-rows
	test('a view on the next day opens a new row and leaves the previous day as it was', async () => {
		const d = await newDashboard('two days')
		await viewOk(d.uuid)
		expect([...(await viewRowsByDay(d.uuid))]).toEqual([[utcDate(), [1, 1]]])

		await letADayPass(d.uuid)
		await viewOk(d.uuid)

		const rows = await viewRowsByDay(d.uuid)
		expect(rows.size, 'two separate rows').toBe(2)
		expect(rows.get(utcDate(-1)), 'day one: one view, one viewer').toEqual([1, 1])
		expect(rows.get(utcDate()), 'day two: one view, one viewer').toEqual([1, 1])

		const breakdown = await adminRead<Array<{ viewBucket: string; viewCount: number }>>(
			`/dashboards/${d.uuid}?period=7d`,
		)
		expect(
			breakdown.map((r) => [r.viewBucket, Number(r.viewCount)]),
			'the report shows both days, oldest first',
		).toEqual([
			[utcDate(-1), 1],
			[utcDate(), 1],
		])
	})

	// @e2e dashboard-view-analytics::daily-salt-rotation-prevents-user-re-identification-across-days
	test('the next day the same person hashes differently, so each day counts them once', async () => {
		const d = await newDashboard('salt')
		await viewOk(d.uuid)
		const saltDayOne = await appValue(SALT_KEY)
		expect(saltDayOne, 'a 32-byte salt, hex encoded').toMatch(/^[0-9a-f]{64}$/)
		expect(await appValue(SALT_DATE_KEY), 'the salt is dated by the UTC day').toBe(utcDate())

		// Same day, same salt: the dedup recognises the person.
		await viewOk(d.uuid)
		expect((await viewRowsByDay(d.uuid)).get(utcDate()), 'two views, one viewer').toEqual([2, 1])

		await letADayPass(d.uuid)
		await viewOk(d.uuid)

		const saltDayTwo = await appValue(SALT_KEY)
		expect(saltDayTwo, 'a new 32-byte salt for the new day').toMatch(/^[0-9a-f]{64}$/)
		expect(saltDayTwo, 'independent of the previous day').not.toBe(saltDayOne)
		expect(await appValue(SALT_DATE_KEY)).toBe(utcDate())

		// The day-one cache entry was not cleared, so a count of 1 on day two
		// means the person's day-two hash is a different cache key.
		const rows = await viewRowsByDay(d.uuid)
		expect(rows.get(utcDate(-1)), 'day one counts the person once').toEqual([2, 1])
		expect(rows.get(utcDate()), 'day two counts the person once, independently').toEqual([1, 1])

		const stored = await selectRows(VIEWS, { dashboard_uuid: d.uuid })
		for (const row of stored) {
			expect(
				Object.keys(row).sort(),
				'the table holds no hash or user id to correlate',
			).toEqual(['dashboard_uuid', 'id', 'unique_viewer_count', 'view_bucket', 'view_count'])
			expect(JSON.stringify(row)).not.toContain(process.env.NC_ADMIN_USER ?? 'admin')
		}
	})
})

// ── REQ-ANLT-006 / REQ-TANLT-004: the period ───────────────────────────

test.describe('the report period', () => {
	// @e2e dashboard-view-analytics::period-parameter-filters-date-range
	test('top dashboards for 7d sums only the last seven days', async () => {
		const d = await newDashboard('period')
		// The scenario's days, relative to today: 30 and 16 days ago are out of
		// a 7-day window; 6 days ago and today are in it.
		await seedView(d.uuid, utcDate(-30), 70, 7)
		await seedView(d.uuid, utcDate(-16), 50, 5)
		await seedView(d.uuid, utcDate(-6), 3, 2)
		await viewOk(d.uuid)

		const ours = async (period: string): Promise<TopRow | undefined> => {
			const top = await adminRead<TopRow[]>(`/dashboards/top?period=${period}&limit=500`)
			return top.find((r) => r.dashboardUuid === d.uuid)
		}

		const week = await ours('7d')
		expect(week, 'the dashboard is in the 7-day top list').toBeDefined()
		expect(Number(week!.viewCount), 'only 6 days ago and today: 3 + 1').toBe(4)
		expect(Number(week!.uniqueViewerCount), 'only 6 days ago and today: 2 + 1').toBe(3)

		// The seeded rows are readable at all: a wider window sums every one.
		const quarter = await ours('90d')
		expect(Number(quarter!.viewCount), 'all four days in 90d').toBe(124)
	})

	// @e2e dashboard-view-analytics::period-parameter-filters-the-date-range
	test('top tiles for 7d sums only the last seven days', async () => {
		const d = await newDashboard('tile period')
		const tile = await newTile(d)
		await seedClick(tile, d.uuid, utcDate(-30), 90, 9)
		await seedClick(tile, d.uuid, utcDate(-16), 60, 6)
		await seedClick(tile, d.uuid, utcDate(-6), 2, 2)
		await clickOk(tile)

		const ours = async (period: string): Promise<TileRow | undefined> => {
			const top = await adminRead<TileRow[]>(`/tiles/top?period=${period}&limit=500`)
			return top.find((r) => String(r.placementUuid) === String(tile))
		}

		const week = await ours('7d')
		expect(week, 'the tile is in the 7-day top list').toBeDefined()
		expect(Number(week!.clickCount), 'only 6 days ago and today: 2 + 1').toBe(3)
		expect(Number(week!.uniqueActorCount), 'only 6 days ago and today: 2 + 1').toBe(3)

		const quarter = await ours('90d')
		expect(Number(quarter!.clickCount), 'all four days in 90d').toBe(153)
	})
})

// ── REQ-ANLT-009 / REQ-TANLT-005: the purge ────────────────────────────

test.describe('the retention purge', () => {
	// @e2e dashboard-view-analytics::purge-job-is-automatic-and-idempotent
	test('the registered purge job deletes old view rows, and a second run raises nothing and brings nothing back', async () => {
		const listed = await runOcc('background-job:list', `--class=${PURGE_JOB}`, '--output=json')
		expect(listed.code, describeRun(listed)).toBe(0)
		expect(
			(JSON.parse(listed.stdout) as unknown[]).length,
			'the purge job is registered with the scheduler',
		).toBeGreaterThanOrEqual(1)

		await setIntAppValue(RETENTION_KEY, 30)
		const d = await newDashboard('purge')
		await seedView(d.uuid, utcDate(-45), 4, 2)
		await seedView(d.uuid, utcDate(-10), 3, 1)
		await viewOk(d.uuid)

		await purge()
		const afterFirst = await viewRowsByDay(d.uuid)
		expect(afterFirst.has(utcDate(-45)), 'the row older than 30 days is deleted').toBe(false)
		expect(afterFirst.get(utcDate(-10)), 'a row within 30 days is kept').toEqual([3, 1])
		expect(afterFirst.get(utcDate()), "today's row is kept").toEqual([1, 1])

		await purge()
		const afterSecond = await viewRowsByDay(d.uuid)
		expect(afterSecond.has(utcDate(-45)), 'the deleted row stays deleted').toBe(false)
		expect([...afterSecond].sort(), 'nothing else changed').toEqual([...afterFirst].sort())
	})

	// @e2e dashboard-view-analytics::retention-purge-also-deletes-tile-rows
	test('with 90 days retention the purge deletes tile rows before the cutoff and keeps the rest, twice over', async () => {
		await setIntAppValue(RETENTION_KEY, 90)
		const d = await newDashboard('tile purge')
		const tile = await newTile(d)
		await seedClick(tile, d.uuid, utcDate(-91), 5, 2)
		await seedClick(tile, d.uuid, utcDate(-90), 4, 2)
		await seedClick(tile, d.uuid, utcDate(-60), 3, 1)
		await clickOk(tile)

		await purge()
		const afterFirst = await clickRowsByDay(tile)
		expect(afterFirst.has(utcDate(-91)), '91 days ago is older than the cutoff: deleted').toBe(false)
		expect(afterFirst.get(utcDate(-90)), '90 days ago is the cutoff itself: kept').toEqual([4, 2])
		expect(afterFirst.get(utcDate(-60)), 'within 90 days: kept').toEqual([3, 1])
		expect(afterFirst.get(utcDate()), "today's row: kept").toEqual([1, 1])

		await purge()
		const afterSecond = await clickRowsByDay(tile)
		expect([...afterSecond].sort(), 're-running deletes nothing more and restores nothing').toEqual(
			[...afterFirst].sort(),
		)
	})
})

// ── REQ-TANLT-005: one salt ────────────────────────────────────────────

test.describe('the tile-click salt', () => {
	// @e2e dashboard-view-analytics::daily-salt-is-shared-not-duplicated
	test('a tile click rotates and uses analytics_dailysalt itself; there is no second salt and no second rotation job', async () => {
		const d = await newDashboard('shared salt')
		const tile = await newTile(d)
		const before = await appValue(SALT_KEY)

		// The stored salt is dated yesterday, so the first hash of today has to
		// rotate it. The first hash of today is a tile click's.
		const dated = await runOcc('config:app:set', 'launchpad', SALT_DATE_KEY, `--value=${utcDate(-1)}`)
		expect(dated.code, describeRun(dated)).toBe(0)
		await new Promise((resolve) => setTimeout(resolve, APP_CONFIG_LOCAL_CACHE_MS))
		await clickOk(tile)

		const afterClick = await appValue(SALT_KEY)
		expect(afterClick, 'the tile click rotated launchpad.analytics_dailysalt').toMatch(/^[0-9a-f]{64}$/)
		expect(afterClick).not.toBe(before)
		expect(await appValue(SALT_DATE_KEY), 'and dated it today').toBe(utcDate())
		expect((await clickRowsByDay(tile)).get(utcDate()), 'the click was counted').toEqual([1, 1])

		// A view hashes with the very salt the click wrote: it does not rotate.
		await viewOk(d.uuid)
		expect(await appValue(SALT_KEY), 'views and clicks share the one salt').toBe(afterClick)

		const keys = await runOcc('config:list', 'launchpad', '--private')
		expect(keys.code, describeRun(keys)).toBe(0)
		const appKeys = Object.keys(
			((JSON.parse(keys.stdout) as { apps?: Record<string, Record<string, unknown>> }).apps ?? {}).launchpad ?? {},
		)
		expect(
			appKeys.filter((k) => /salt/i.test(k)).sort(),
			'no separate tile-analytics salt',
		).toEqual([SALT_KEY, SALT_DATE_KEY].sort())

		const jobs = await runOcc('background-job:list', '--output=json', '--limit=10000')
		expect(jobs.code, describeRun(jobs)).toBe(0)
		const saltJobs = [
			...new Set(
				(JSON.parse(jobs.stdout) as Array<{ class: string }>)
					.map((j) => j.class)
					.filter((c) => c.startsWith('OCA\\LaunchPad\\') && /salt/i.test(c)),
			),
		]
		expect(saltJobs, 'one rotation job').toEqual(['OCA\\LaunchPad\\BackgroundJob\\SaltRotationJob'])
	})
})
