/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for the live-data tile (`livetile`), written for
 * e2e-coverage-programme task 4.19: one verdict per scenario of
 * openspec/specs/live-data-tile-widget/spec.md. The two list scenarios
 * (personal data, script link) are cited in live-tile-lists.spec.ts.
 *
 * SEEDING (same approach as live-tile-lists.spec.ts)
 * ==================================================
 * The spec starts a small HTTP server that Nextcloud fetches from. Every path
 * counts its hits, so a test can tell whether the server fetched upstream at
 * all (caching, the 403 guard, the allow-list at fetch time). The admin's
 * `livetile_allowed_hosts` is set to the fixture host for the run and put
 * back afterwards.
 *
 * Two dashboards: `api` is never opened in the browser, so the tiles on it
 * are only ever resolved by the API calls a test makes, and a page load can
 * never warm or refresh their cache. `shown` is the active one the browser
 * renders.
 *
 * PRECONDITIONS
 * =============
 * - Nextcloud must reach this runner: LIVE_TILE_FIXTURE_HOST (default
 *   127.0.0.1) and `allow_local_remote_servers`. A probe tile checks it in
 *   beforeAll; tests fed by the fixture skip and say so when it cannot.
 * - The caching scenarios need a distributed memcache (APCu/Redis). Without
 *   one Nextcloud hands out a null cache and they go red, which is right: the
 *   product then does not cache.
 * - The connector-absent scenario needs Integriq NOT to be installed; it skips
 *   when the capability probe says it is. The connector-mode form scenario
 *   stubs only the browser's capability probe (the fixture has no Integriq).
 *
 * @spec openspec/specs/live-data-tile-widget/spec.md
 */
import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { Server } from 'node:http'
import type { AddressInfo } from 'node:net'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { createServer } from 'node:http'
import {
	deprovisionUser,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { closeSidebar, gotoLaunchPad, openSidebar } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { contextFor } from './support/principals.ts'
import { chooseAndAdd } from './support/widgetPicker.ts'

const API = '/index.php/apps/launchpad/api'
const CONFIG =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/livetile_allowed_hosts'
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? process.env.NEXTCLOUD_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? process.env.NEXTCLOUD_PASS ?? 'admin',
}
const FIXTURE_HOST = process.env.LIVE_TILE_FIXTURE_HOST || '127.0.0.1'
const STAMP = Date.now().toString(36)
const SECRET = `tok${STAMP}secret`
/** A host only the allow-list knows; nothing is ever fetched from it. */
const ALLOWED_HOST = `livetile-${STAMP}.example.org`
const BLOCKED_HOST = `blocked-${STAMP}.example.org`

/** Graph-shaped answer of a document library, newest first, twelve items. */
const DOCUMENTS = {
	value: Array.from({ length: 12 }, (_, i) => ({
		name: `Document ${String(i + 1).padStart(2, '0')} ${STAMP}.docx`,
		webUrl: `https://intranet.example.org/sites/raad/doc-${i + 1}.docx`,
		lastModifiedDateTime: `2026-11-${String(18 - i).padStart(2, '0')}T09:00:00Z`,
		lastModifiedBy: { user: { displayName: `Editor ${i + 1}` } },
	})),
}

const hits = new Map<string, number>()
let flakyFails = false

let server: Server
let admin: APIRequestContext
let apiDash: SeededDashboard | null = null
let shownDash: SeededDashboard | null = null
let previousHosts: string | null = null
let fixtureBase = ''
let reachable = false

const tiles: Record<string, number> = {}

/**
 * Hits so far on one fixture path.
 *
 * @param path The path, without query.
 * @return the number of requests the fixture answered on it.
 */
function hitsOn(path: string): number {
	return hits.get(path) ?? 0
}

/**
 * The app value of the live-tile host allow-list, or null when unset.
 *
 * @return the stored JSON list, or null.
 */
async function readHosts(): Promise<string | null> {
	const res = await admin.get(`${CONFIG}?format=json`)
	if (!res.ok()) {
		return null
	}
	const data = (await res.json())?.ocs?.data
	return typeof data?.data === 'string' ? data.data : null
}

/**
 * Write the app value of the live-tile host allow-list.
 *
 * @param list The hosts to allow, or the raw stored value to put back.
 */
async function writeHosts(list: string[] | string): Promise<void> {
	const value = typeof list === 'string' ? list : JSON.stringify(list)
	const res = await admin.post(`${CONFIG}?format=json`, { form: { value } })
	expect(res.ok(), `set livetile_allowed_hosts -> ${res.status()}`).toBeTruthy()
}

/**
 * Place a live tile over the API and return its placement id.
 *
 * @param dashboardId The dashboard to place it on.
 * @param gridY The row to place it in.
 * @param content The tile content.
 * @return the placement id.
 */
async function placeTile(
	dashboardId: number,
	gridY: number,
	content: Record<string, unknown>,
): Promise<number> {
	const res = await admin.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'livetile',
			gridX: 0,
			gridY,
			gridWidth: 4,
			gridHeight: 4,
			content,
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(
		body.widget?.id ?? body.placement?.id ?? body.data?.id ?? body.id,
	)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * The placements on a dashboard, read back as the admin.
 *
 * @param dashboardId The dashboard.
 * @return its placements.
 */
async function placementsOf(
	dashboardId: number,
): Promise<Array<Record<string, any>>> {
	const res = await admin.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (body.placements ?? body.dashboard?.placements ?? []) as Array<
		Record<string, any>
	>
}

/**
 * GET the tile's reading as the admin.
 *
 * @param placementId The placement.
 * @return the status and parsed body.
 */
async function reading(
	placementId: number,
): Promise<{ status: number; body: any; text: string }> {
	const res = await admin.get(`${API}/livetile/${placementId}`)
	const text = await res.text()
	let body: any
	try {
		body = JSON.parse(text)
	} catch {
		body = null
	}
	return { status: res.status(), body, text }
}

/**
 * The rendered tile of one placement.
 *
 * @param page The page.
 * @param placementId The placement.
 * @return the `.live-tile-widget` inside that placement.
 */
function tileOf(page: Page, placementId: number): Locator {
	return page.locator(
		`[data-testid="widget-placement-${placementId}"] .live-tile-widget`,
	)
}

/**
 * Open the Add Widget modal on the `shown` dashboard's own row and pick the
 * live-data tile type.
 *
 * @param page The page, on the workspace.
 * @return the dialog and the tile form inside it.
 */
async function openTileForm(
	page: Page,
): Promise<{ dialog: Locator; form: Locator }> {
	await openSidebar(page)
	const row = page
		.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.filter({ hasText: shownDash!.name })
		.first()
	await expect(row).toBeVisible({ timeout: 10_000 })
	await row.locator('.dashboard-row-actions button').first().click()
	const item = page.getByRole('menuitem', { name: /^add widget$/i })
	await expect(item).toBeVisible({ timeout: 5_000 })
	await item.click()
	await chooseAndAdd(page.locator('[data-testid="widget-picker-type-livetile"]'))
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	const form = dialog.locator('.live-tile-widget-form')
	await expect(form).toBeVisible({ timeout: 10_000 })
	return { dialog, form }
}

/**
 * Pick an option of one of the form's NcSelects, by position.
 * Selects in order: 0 Source, 1 Display, 2 Preset (list/table/keyvalue only).
 *
 * @param page The page.
 * @param form The tile form.
 * @param select The select's position in the form.
 * @param option The option's position in the select.
 */
async function choose(
	page: Page,
	form: Locator,
	select: number,
	option: number,
): Promise<void> {
	await form
		.locator('.v-select')
		.nth(select)
		.locator('.vs__dropdown-toggle')
		.click()
	const options = page.locator('.vs__dropdown-menu .vs__dropdown-option')
	await expect(options.first()).toBeVisible({ timeout: 5_000 })
	await options.nth(option).click()
}

/**
 * Click Add, wait for the modal to close, and return the one new placement.
 *
 * @param page The page.
 * @param dialog The Add Widget dialog.
 * @param before The placement ids on `shown` before the add.
 * @return the new placement as the API returns it.
 */
async function addAndRead(
	page: Page,
	dialog: Locator,
	before: number[],
): Promise<Record<string, any>> {
	const add = dialog.getByRole('button', { name: /^add$/i })
	await expect(add).toBeEnabled({ timeout: 10_000 })
	await add.click()
	await expect(dialog).not.toBeVisible({ timeout: 10_000 })
	await closeSidebar(page)
	const added = (await placementsOf(shownDash!.id)).filter(
		(p) => !before.includes(Number(p.id)),
	)
	expect(added, 'exactly one placement landed on the dashboard').toHaveLength(1)
	expect(added[0].widgetId).toBe('livetile')
	return added[0]
}

/**
 * The placement ids on the `shown` dashboard right now.
 *
 * @return the ids.
 */
async function shownIds(): Promise<number[]> {
	return (await placementsOf(shownDash!.id)).map((p) => Number(p.id))
}

/**
 * Why a fixture-fed test skips.
 *
 * @return the skip reason.
 */
function UNREACHABLE(): string {
	return `Nextcloud could not fetch ${fixtureBase}; set LIVE_TILE_FIXTURE_HOST to an address it reaches, or allow local remote servers`
}

test.describe.configure({ mode: 'serial', timeout: 120_000 })

test.beforeAll(async () => {
	server = createServer((req, res) => {
		const path = (req.url ?? '/').split('?')[0]
		hits.set(path, hitsOn(path) + 1)
		const json = (status: number, body: unknown) => {
			res.writeHead(status, { 'Content-Type': 'application/json' })
			res.end(JSON.stringify(body))
		}
		if (path === '/documents.json') {
			json(200, DOCUMENTS)
		} else if (path === '/fail.json') {
			json(500, { error: 'upstream down' })
		} else if (path === '/flaky.json') {
			if (flakyFails) {
				json(503, { error: 'upstream down' })
			} else {
				json(200, { data: { count: 41 } })
			}
		} else {
			json(200, { data: { count: 1234 }, open: 7 })
		}
	})
	await new Promise<void>((resolve) =>
		server.listen(0, '0.0.0.0', () => resolve()),
	)
	const { port } = server.address() as AddressInfo
	fixtureBase = `http://${FIXTURE_HOST}:${port}`

	admin = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	previousHosts = await readHosts()
	await writeHosts([FIXTURE_HOST, ALLOWED_HOST])

	// `api` first, so `shown` ends up the active dashboard.
	apiDash = await seedActiveDashboard(admin, `Live tile API ${STAMP}`)
	shownDash = await seedActiveDashboard(admin, `Live tile ${STAMP}`)

	const value = (path: string, extra: Record<string, unknown> = {}) => ({
		sourceMode: 'url',
		url: `${fixtureBase}${path}`,
		valueExpr: '$.data.count',
		refresh: 300,
		...extra,
	})

	// Tiles only the API resolves.
	tiles.probe = await placeTile(apiDash.id, 0, value('/probe.json'))
	tiles.cache = await placeTile(apiDash.id, 4, value('/cache.json'))
	tiles.auth = await placeTile(apiDash.id, 8, value('/auth.json'))
	tiles.flaky = await placeTile(
		apiDash.id,
		12,
		value('/flaky.json', { refresh: 30 }),
	)
	tiles.clamp = await placeTile(
		apiDash.id,
		16,
		value('/clamp.json', { refresh: 5 }),
	)

	// Tiles the browser renders.
	tiles.formatted = await placeTile(
		shownDash.id,
		0,
		value(`/value.json?token=${SECRET}`, {
			label: `Open invoices ${STAMP}`,
			format: { prefix: '€', suffix: ' open', thousands: true },
			badge: {
				thresholds: [
					{ max: 10, state: 'ok', label: 'Fine' },
					{ max: 100, state: 'warn', label: 'Busy' },
					{ max: 100000, state: 'alert', label: 'Critical' },
				],
			},
		}),
	)
	tiles.legacy = await placeTile(shownDash.id, 4, {
		label: `Legacy tile ${STAMP}`,
		sourceMode: 'url',
		url: `${fixtureBase}/legacy.json`,
		valueExpr: '$.open',
	})
	tiles.sameTab = await placeTile(
		shownDash.id,
		8,
		value('/same-tab.json', {
			label: `Same tab ${STAMP}`,
			linkUrl: `${BASE_URL}/status.php`,
			linkTarget: 'same-tab',
		}),
	)
	tiles.newTab = await placeTile(
		shownDash.id,
		12,
		value('/new-tab.json', {
			label: `New tab ${STAMP}`,
			linkUrl: `${BASE_URL}/status.php`,
			linkTarget: 'new-tab',
		}),
	)
	tiles.down = await placeTile(
		shownDash.id,
		16,
		value('/fail.json', { label: `Down ${STAMP}` }),
	)
	tiles.connector = await placeTile(shownDash.id, 20, {
		label: `Tickets ${STAMP}`,
		sourceMode: 'connector',
		sourceId: 'topdesk',
		valueExpr: '$.open',
		refresh: 300,
	})

	const probe = await reading(tiles.probe)
	reachable =
		probe.status === 200
		&& probe.body?.value === 1234
		&& hitsOn('/probe.json') > 0
})

test.afterAll(async () => {
	if (admin) {
		await removeSeededDashboard(admin, shownDash)
		await removeSeededDashboard(admin, apiDash)
		await writeHosts(previousHosts ?? '[]').catch(() => undefined)
		await admin.dispose()
	}
	await new Promise<void>((resolve) => server?.close(() => resolve()))
})

// ─── REQ-LIVETILE-002: configure the tile source ────────────────────────────

// @e2e live-data-tile-widget::direct-url-source-mode
test('REQ-LIVETILE-002: an off-list host is refused at save, an allowed one persists the url config', async ({
	page,
}) => {
	await gotoLaunchPad(page)
	const before = await shownIds()
	const { dialog, form } = await openTileForm(page)

	await form.getByLabel('Value expression', { exact: true }).fill('$.data.count')
	const url = form.getByLabel('URL', { exact: true })

	// A host that is NOT on the allow-list: the server refuses it and the
	// modal will not save.
	await url.fill(`https://${BLOCKED_HOST}/stats.json`)
	const refused = page.waitForResponse((r) =>
		r.url().includes('/api/livetile/validate-source'),
	)
	await url.press('Tab')
	const refusal = await (await refused).json()
	expect(refusal.valid).toBe(false)
	expect(refusal.errors).toContain('host_not_allowed')
	await expect(form.getByText('This host is not on the allow-list.')).toBeVisible()
	const add = dialog.getByRole('button', { name: /^add$/i })
	await expect(add, 'an off-list URL cannot be saved').toBeDisabled()
	await expect(add).toHaveAttribute('title', 'This host is not on the allow-list.')

	// A host on the allow-list saves, with the default refresh.
	const allowed = `https://${ALLOWED_HOST}/stats.json`
	await url.fill(allowed)
	const accepted = page.waitForResponse((r) =>
		r.url().includes('/api/livetile/validate-source'),
	)
	await url.press('Tab')
	expect((await (await accepted).json()).valid).toBe(true)
	await expect(form.getByText('This host is not on the allow-list.')).toHaveCount(
		0,
	)

	const placement = await addAndRead(page, dialog, before)
	try {
		expect(placement.content).toMatchObject({
			sourceMode: 'url',
			url: allowed,
			valueExpr: '$.data.count',
			refresh: 300,
		})
	} finally {
		await admin.delete(`${API}/widgets/${placement.id}`)
	}
})

// @e2e live-data-tile-widget::refresh-interval-bounds
test('REQ-LIVETILE-002: a refresh below 30 s is raised to 30, an unset one becomes 300, when saved', async ({
	page,
}) => {
	await gotoLaunchPad(page)
	const placed: number[] = []
	try {
		for (const [typed, expected] of [
			['10', 30],
			['', 300],
		] as const) {
			const before = await shownIds()
			const { dialog, form } = await openTileForm(page)
			await expect(form.getByText('Minimum 30 seconds.')).toBeVisible()
			await expect(form.getByLabel('Refresh interval (seconds)')).toHaveValue(
				'300',
			)
			await form
				.getByLabel('URL', { exact: true })
				.fill(`https://${ALLOWED_HOST}/stats.json`)
			await form
				.getByLabel('Value expression', { exact: true })
				.fill('$.data.count')
			await form.getByLabel('Refresh interval (seconds)').fill(typed)
			const placement = await addAndRead(page, dialog, before)
			placed.push(Number(placement.id))
			expect(placement.content.refresh, `typed "${typed}"`).toBe(expected)
		}
	} finally {
		for (const id of placed) {
			await admin.delete(`${API}/widgets/${id}`)
		}
	}
})

// Not a citation: the server half of the same bound. A tile stored with
// refresh 5 (bypassing the form) is still cached for 30 s on the server.
test('REQ-LIVETILE-002: the server clamps a stored refresh of 5 s to 30 s', async ({
	page,
}) => {
	test.skip(!reachable, UNREACHABLE())
	const first = await reading(tiles.clamp)
	expect(first.status, first.text).toBe(200)
	const fetched = hitsOn('/clamp.json')
	expect(fetched).toBe(1)
	await page.waitForTimeout(6_000)
	const second = await reading(tiles.clamp)
	expect(second.body.stale).toBe(false)
	expect(second.body.fetchedAt).toBe(first.body.fetchedAt)
	expect(hitsOn('/clamp.json'), 'refresh 5 is clamped to 30 s on the server').toBe(
		fetched,
	)
})

// @e2e live-data-tile-widget::openconnector-source-mode
test('REQ-LIVETILE-002: with the connector capability, the author picks a source and the connector config persists', async ({
	page,
}) => {
	// The fixture has no Integriq; stub only the browser's capability probe.
	await page.route('**/apps/launchpad/api/livetile/connector/status', (route) =>
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: '{"available":true}',
		}),
	)
	await gotoLaunchPad(page)
	const before = await shownIds()
	const { dialog, form } = await openTileForm(page)

	await form.locator('.v-select').nth(0).locator('.vs__dropdown-toggle').click()
	const options = page.locator('.vs__dropdown-menu .vs__dropdown-option')
	await expect(options).toHaveCount(2)
	await expect(options.nth(1)).toContainText('Integriq source')
	await options.nth(1).click()

	await expect(form.getByText('Integriq is not installed')).toHaveCount(0)
	const source = form.getByLabel('Integriq source id')
	await expect(source).toBeEnabled()
	await expect(form.getByLabel('URL', { exact: true })).toHaveCount(0)
	await source.fill(`topdesk-${STAMP}`)
	await form.getByLabel('Value expression', { exact: true }).fill('$.open')

	const placement = await addAndRead(page, dialog, before)
	try {
		expect(placement.content).toMatchObject({
			sourceMode: 'connector',
			sourceId: `topdesk-${STAMP}`,
			valueExpr: '$.open',
			refresh: 300,
		})
	} finally {
		await admin.delete(`${API}/widgets/${placement.id}`)
	}
})

// ─── REQ-LIVETILE-003: resolve and cache on the server ──────────────────────

// @e2e live-data-tile-widget::browser-fetches-via-the-placement-endpoint
test('REQ-LIVETILE-003: the browser reads the placement endpoint, which answers the reading without the source URL', async ({
	page,
}) => {
	test.skip(!reachable, UNREACHABLE())
	const answered = page.waitForResponse(
		(r) =>
			new URL(r.url()).pathname.endsWith(`/api/livetile/${tiles.formatted}`),
		{ timeout: 30_000 },
	)
	// The page never talks to the source itself.
	const direct: string[] = []
	page.on('request', (r) => {
		if (r.url().startsWith(fixtureBase)) {
			direct.push(r.url())
		}
	})
	await gotoLaunchPad(page)
	const response = await answered
	expect(response.status()).toBe(200)
	const text = await response.text()
	const body = JSON.parse(text)

	expect(Object.keys(body).sort()).toEqual([
		'badge',
		'fetchedAt',
		'formatted',
		'stale',
		'value',
	])
	expect(body.value).toBe(1234)
	expect(body.stale).toBe(false)
	expect(typeof body.fetchedAt).toBe('string')
	expect(text, 'no source URL').not.toContain(fixtureBase)
	expect(text, 'no source host').not.toContain(FIXTURE_HOST)
	expect(text, 'no credentials from the URL').not.toContain(SECRET)
	expect(text).not.toContain('value.json')

	await expect(tileOf(page, tiles.formatted)).toBeVisible({ timeout: 30_000 })
	expect(direct).toEqual([])
})

// @e2e live-data-tile-widget::caller-authorization
test('REQ-LIVETILE-003: a user who may not view the dashboard gets 403 and nothing is fetched', async () => {
	test.skip(!reachable, UNREACHABLE())
	const outsider = await provisionThrowawayUser('e2e-livetile')
	// Not `request.newContext` with bare credentials: that context rides the
	// admin's stored session and never sends them.
	const ctx = await contextFor(outsider.username, outsider.password)
	try {
		expect(hitsOn('/auth.json')).toBe(0)
		const refused = await ctx.get(`${API}/livetile/${tiles.auth}`)
		expect(refused.status(), await refused.text()).toBe(403)
		expect(JSON.stringify(await refused.json())).not.toContain(fixtureBase)
		expect(hitsOn('/auth.json'), 'the refusal performs no fetch').toBe(0)

		// The owner may: the same endpoint answers and fetches.
		const owner = await reading(tiles.auth)
		expect(owner.status, owner.text).toBe(200)
		expect(owner.body.value).toBe(1234)
		expect(hitsOn('/auth.json')).toBe(1)
	} finally {
		await ctx.dispose()
		await deprovisionUser(outsider.username)
	}
})

// @e2e live-data-tile-widget::cached-within-ttl
test('REQ-LIVETILE-003: a second read inside the refresh interval is served from cache, fresh', async () => {
	test.skip(!reachable, UNREACHABLE())
	const first = await reading(tiles.cache)
	expect(first.status, first.text).toBe(200)
	expect(first.body.stale).toBe(false)
	expect(hitsOn('/cache.json')).toBe(1)

	const second = await reading(tiles.cache)
	expect(second.status, second.text).toBe(200)
	expect(second.body.value).toBe(1234)
	expect(second.body.stale).toBe(false)
	expect(second.body.fetchedAt, 'the cached reading').toBe(first.body.fetchedAt)
	expect(hitsOn('/cache.json'), 'no new upstream fetch').toBe(1)
})

// @e2e live-data-tile-widget::upstream-failure-degrades-gracefully
test('REQ-LIVETILE-003: an upstream failure serves the last value stale, or null/stale and an error state', async ({
	page,
}) => {
	test.skip(!reachable, UNREACHABLE())

	// With a cached value: refresh 30, a good read, then the source fails and
	// the interval runs out.
	const good = await reading(tiles.flaky)
	expect(good.status, good.text).toBe(200)
	expect(good.body.value).toBe(41)
	flakyFails = true
	const fetched = hitsOn('/flaky.json')
	await page.waitForTimeout(31_000)
	const degraded = await reading(tiles.flaky)
	expect(degraded.status, degraded.text).toBe(200)
	expect(
		hitsOn('/flaky.json'),
		'the expired reading was refetched',
	).toBeGreaterThan(fetched)
	expect.soft(degraded.body.value, 'the last-known value is served').toBe(41)
	expect.soft(degraded.body.stale).toBe(true)

	// Without a cached value: the source always fails.
	const empty = await reading(tiles.down)
	expect(empty.status, empty.text).toBe(200)
	expect(empty.body).toMatchObject({ value: null, stale: true })
	expect(hitsOn('/fail.json')).toBeGreaterThan(0)

	const errors: string[] = []
	page.on('pageerror', (e) => errors.push(e.message))
	await gotoLaunchPad(page)
	const tile = tileOf(page, tiles.down)
	await expect(tile).toBeVisible({ timeout: 30_000 })
	await expect(tile.locator('.live-tile-widget__state--error')).toBeVisible({
		timeout: 30_000,
	})
	await expect(tile).not.toContainText('Showing last known value')
	// The rest of the dashboard still renders: the tile did not crash it.
	await expect(tileOf(page, tiles.formatted)).toContainText('€1,234 open')
	expect(errors.filter((m) => /livetile|live-tile/i.test(m))).toEqual([])
})

// @e2e live-data-tile-widget::allow-list-enforced-at-fetch-time
test('REQ-LIVETILE-003: a host removed from the allow-list after configuration is never fetched, and the tile shows an error', async ({
	page,
}) => {
	test.skip(!reachable, UNREACHABLE())
	// Configured while the host was allowed: the same fixture URL other tiles
	// read without trouble.
	const id = await placeTile(shownDash!.id, 24, {
		label: `Removed host ${STAMP}`,
		sourceMode: 'url',
		url: `${fixtureBase}/removed.json`,
		valueExpr: '$.data.count',
		refresh: 300,
	})
	try {
		await writeHosts([ALLOWED_HOST])
		const refused = await reading(id)
		expect(refused.status, refused.text).toBe(200)
		expect(refused.body).toMatchObject({ value: null, stale: true })
		expect(hitsOn('/removed.json'), 'fail-closed: nothing was fetched').toBe(0)

		await gotoLaunchPad(page)
		const tile = tileOf(page, id)
		await expect(tile).toBeVisible({ timeout: 30_000 })
		await expect(tile.locator('.live-tile-widget__state--error')).toBeVisible({
			timeout: 30_000,
		})
		expect(hitsOn('/removed.json')).toBe(0)
	} finally {
		await writeHosts([FIXTURE_HOST, ALLOWED_HOST])
		await admin.delete(`${API}/widgets/${id}`)
	}
})

// ─── REQ-LIVETILE-004: render with formatting and badge ─────────────────────

// @e2e live-data-tile-widget::number-formatting
test('REQ-LIVETILE-004: prefix, thousands separator and suffix render as €1,234 open', async ({
	page,
}) => {
	test.skip(!reachable, UNREACHABLE())
	const api = await reading(tiles.formatted)
	expect(api.body.value).toBe(1234)
	expect(api.body.formatted).toBe('€1,234 open')

	await gotoLaunchPad(page)
	const value = tileOf(page, tiles.formatted).locator('.live-tile-widget__value')
	await expect(value).toHaveText('€1,234 open', { timeout: 30_000 })
})

// @e2e live-data-tile-widget::threshold-badge-is-not-colour-only
test('REQ-LIVETILE-004: a value in the alert range shows the badge with an icon and a text label', async ({
	page,
}) => {
	test.skip(!reachable, UNREACHABLE())
	const api = await reading(tiles.formatted)
	expect(api.body.badge).toEqual({ state: 'alert', label: 'Critical' })

	await gotoLaunchPad(page)
	const badge = tileOf(page, tiles.formatted).locator('.live-tile-widget__badge')
	await expect(badge).toBeVisible({ timeout: 30_000 })
	await expect(badge).toHaveClass(/live-tile-widget__badge--alert/)
	await expect(badge, 'a text label').toHaveText('Critical')
	await expect(badge.locator('svg'), 'an icon').toHaveCount(1)
	await expect(badge.locator('svg')).toBeVisible()
})

// @e2e live-data-tile-widget::click-through
test('REQ-LIVETILE-004: activating a linked tile opens the link in the same tab or a new one, as configured', async ({
	page,
	context,
}) => {
	test.skip(!reachable, UNREACHABLE())
	await gotoLaunchPad(page)
	const target = `${BASE_URL}/status.php`

	const newTab = tileOf(page, tiles.newTab)
	await expect(newTab).toContainText(`New tab ${STAMP}`, { timeout: 30_000 })
	await expect(newTab).toHaveAttribute('href', target)
	await expect(newTab).toHaveAttribute('target', '_blank')
	const popupOpened = context.waitForEvent('page')
	await newTab.click()
	const popup = await popupOpened
	await popup.waitForLoadState()
	expect(popup.url()).toBe(target)
	await popup.close()
	expect(page.url(), 'the dashboard stays open').toContain('/apps/launchpad')

	const sameTab = tileOf(page, tiles.sameTab)
	await expect(sameTab).toContainText(`Same tab ${STAMP}`)
	await expect(sameTab).toHaveAttribute('href', target)
	expect(await sameTab.getAttribute('target')).toBeNull()
	const pagesBefore = context.pages().length
	await sameTab.click()
	await page.waitForURL(target, { timeout: 15_000 })
	expect(context.pages().length, 'no new tab').toBe(pagesBefore)
})

// ─── REQ-LIVETILE-005: OpenConnector is an optional leaf ────────────────────

// @e2e live-data-tile-widget::openconnector-absent
test('REQ-LIVETILE-005: without the connector only URL mode is offered and a connector tile says its source is unavailable', async ({
	page,
}) => {
	const status = await admin.get(`${API}/livetile/connector/status`)
	expect(status.status(), await status.text()).toBe(200)
	test.skip(
		(await status.json()).available === true,
		'Integriq is installed on this instance; the scenario needs it absent',
	)

	await gotoLaunchPad(page)
	const tile = tileOf(page, tiles.connector)
	await expect(tile).toBeVisible({ timeout: 30_000 })
	const unavailable = tile.locator('.live-tile-widget__state--unavailable')
	await expect(unavailable).toBeVisible({ timeout: 30_000 })
	await expect(unavailable).toContainText('Data source unavailable')
	await expect(tile.locator('.live-tile-widget__state--error')).toHaveCount(0)

	const { form } = await openTileForm(page)
	await form.locator('.v-select').nth(0).locator('.vs__dropdown-toggle').click()
	const options = page.locator('.vs__dropdown-menu .vs__dropdown-option')
	await expect(options).toHaveCount(1)
	await expect(options.first()).toContainText('Direct URL')
	await options.first().click()
	await expect(form.getByLabel('Integriq source id')).toHaveCount(0)
	await expect(form.getByLabel('URL', { exact: true })).toBeVisible()
})

// ─── REQ-LTC-001 / REQ-LTC-004: lists and the documents preset ──────────────

// @e2e live-data-tile-widget::existing-single-value-tile
test('REQ-LTC-001: a tile saved with only valueExpr and no display still shows its single value', async ({
	page,
}) => {
	test.skip(!reachable, UNREACHABLE())
	const api = await reading(tiles.legacy)
	expect(api.status, api.text).toBe(200)
	expect(api.body.value).toBe(7)
	expect(api.body).not.toHaveProperty('display')
	expect(api.body).not.toHaveProperty('items')

	await gotoLaunchPad(page)
	const tile = tileOf(page, tiles.legacy)
	await expect(tile.locator('.live-tile-widget__value')).toHaveText('7', {
		timeout: 30_000,
	})
	await expect(
		tile.locator(
			'.live-tile-widget__list, .live-tile-widget__table, .live-tile-widget__pairs',
		),
	).toHaveCount(0)
})

// @e2e live-data-tile-widget::sharepoint-library
test('REQ-LTC-004: the SharePoint "Latest documents" preset lists ten documents with date and editor, titles opening the document', async ({
	page,
}) => {
	test.skip(!reachable, UNREACHABLE())
	// The fixture has no Integriq, so the Graph-shaped library answers over a
	// direct URL; the preset and the rendering are the same for both modes.
	await gotoLaunchPad(page)
	const before = await shownIds()
	const { dialog, form } = await openTileForm(page)
	const url = form.getByLabel('URL', { exact: true })
	await url.fill(`${fixtureBase}/documents.json`)
	const checked = page.waitForResponse((r) =>
		r.url().includes('/api/livetile/validate-source'),
	)
	await url.press('Tab')
	expect((await (await checked).json()).valid).toBe(true)

	await choose(page, form, 1, 1) // Display: List
	await choose(page, form, 2, 0) // Preset: Latest documents (SharePoint)
	await expect(form.getByLabel('Value expression', { exact: true })).toHaveValue(
		'$.value',
	)
	await expect(form.getByLabel('Number of rows (1 to 50)')).toHaveValue('10')
	const paths = form.getByLabel('Field path')
	await expect(paths).toHaveCount(4)

	const placement = await addAndRead(page, dialog, before)
	try {
		expect(placement.content).toMatchObject({
			display: 'list',
			limit: 10,
			valueExpr: '$.value',
		})

		const tile = tileOf(page, Number(placement.id))
		const rows = tile.locator('.live-tile-widget__list-item')
		await expect(rows).toHaveCount(10, { timeout: 30_000 })
		for (let i = 0; i < 10; i++) {
			const doc = DOCUMENTS.value[i]
			const link = rows.nth(i).locator('a.live-tile-widget__list-title')
			await expect(link).toHaveText(doc.name)
			await expect(link).toHaveAttribute('href', doc.webUrl)
			await expect(link).toHaveAttribute('target', '_blank')
			await expect(rows.nth(i)).toContainText('Last modified: ')
			await expect(rows.nth(i)).toContainText(
				`Modified by: ${doc.lastModifiedBy.user.displayName}`,
			)
		}
		await expect(tile).not.toContainText(DOCUMENTS.value[10].name)
		const shown = await rows
			.nth(0)
			.locator('.live-tile-widget__list-detail')
			.first()
			.textContent()
		expect(shown ?? '', 'a date, not the raw timestamp').not.toContain(
			'T09:00:00Z',
		)
	} finally {
		await admin.delete(`${API}/widgets/${placement.id}`)
	}
})
