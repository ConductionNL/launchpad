/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the tile health ping
 * (openspec/specs/service-health-ping), written for e2e-coverage-programme
 * task 5.10.
 *
 * An author account owns a dashboard of launcher tiles. The ping is
 * configured through the tile editor where the scenario is about the author's
 * choices, and seeded through `PUT /api/widgets/{id}` where it is about how
 * the server classifies, caches and guards the reading behind
 * `GET /api/health-ping/{placementId}`.
 *
 * The upstream service is a small HTTP server this spec starts. It counts the
 * requests it receives per URL, which is how "the server pinged" and "the
 * server did NOT ping" are observed: Nextcloud makes the request itself, so
 * the count is the only witness the browser has. Nextcloud must reach the
 * runner: set HEALTH_PING_FIXTURE_HOST (falls back to LIVE_TILE_FIXTURE_HOST,
 * then 127.0.0.1, right when both run on one machine as in CI). A Nextcloud
 * that refuses local addresses (`allow_local_remote_servers` off) never
 * reaches it; the tests that need the upstream then skip and say so.
 *
 * The background refresh job is run on demand through
 * `occ background-job:execute --force-execute` (support/occ.ts). It needs no
 * badge read to be observed: the fixture's request count shows which tiles
 * the job itself pinged.
 *
 * Gate-19 @e2e traceability:
 *   @e2e service-health-ping::enable-ping-with-a-health-url
 *   @e2e service-health-ping::host-not-on-the-allow-list-is-rejected-at-save
 *   @e2e service-health-ping::interval-bounds
 *   @e2e service-health-ping::online-when-status-matches-within-latency-threshold
 *   @e2e service-health-ping::degraded-when-slow
 *   @e2e service-health-ping::offline-after-consecutive-failed-pings
 *   @e2e service-health-ping::a-failed-ping-marks-the-badge-stale
 *   @e2e service-health-ping::allow-list-enforced-at-ping-time-fail-closed
 *   @e2e service-health-ping::endpoint-serves-the-cached-badge
 *   @e2e service-health-ping::caller-authorization
 *   @e2e service-health-ping::cached-within-ttl
 *   @e2e service-health-ping::background-refresh-of-due-entries
 *   @e2e service-health-ping::stale-fallback-on-refresh-failure
 *   @e2e service-health-ping::badge-is-not-colour-only
 *   @e2e service-health-ping::accessible-detail-on-demand
 *   @e2e service-health-ping::ping-disabled-shows-no-badge
 *
 * @spec openspec/specs/service-health-ping/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Locator, Page } from '@playwright/test'
import type { IncomingMessage, Server, ServerResponse } from 'node:http'
import type { AddressInfo } from 'node:net'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { createServer } from 'node:http'
import { loginAs } from './fixtures/secondary-user.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import { describeRun, executeJobNow } from './support/occ.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const CONFIG = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad'
const ALLOWED_HOSTS = 'healthping_allowed_hosts'
const LATENCY_THRESHOLD = 'healthping_latency_threshold_ms'
const OFFLINE_AFTER = 'healthping_offline_after_failures'
const JOB = 'OCA\\LaunchPad\\BackgroundJob\\HealthPingRefreshJob'
const FIXTURE_HOST = process.env.HEALTH_PING_FIXTURE_HOST || process.env.LIVE_TILE_FIXTURE_HOST || '127.0.0.1'
const STAMP = `${Date.now()}`
const UPSTREAM_BODY = `UPSTREAM-BODY-${STAMP}`
const UPSTREAM_HEADER = 'X-Upstream-Marker'
const UPSTREAM_HEADER_VALUE = `upstream-header-${STAMP}`
const SLOW_MS = 1500
const UNREACHABLE = 'Nextcloud never reached the fixture server; set HEALTH_PING_FIXTURE_HOST to an address it reaches, or allow local remote servers'

let admin: APIRequestContext
let author: Principal | null = null
let stranger: Principal | null = null
let dashboardId = 0
let gridY = 0
let reachable = false
let previousHosts: string | null = null
let previousThreshold: string | null = null
let previousOfflineAfter: string | null = null

let upstream: Server
let upstreamBase = ''
let refusedBase = ''
const hits = new Map<string, number>()
const openSockets = new Set<ServerResponse>()

/**
 * Answer the fixture paths: `/ok` 200 with a marker body and header, `/slow`
 * the same after SLOW_MS, `/status500` a 500, `/hang` never.
 *
 * @param req the incoming request.
 * @param res the response.
 * @return nothing.
 */
function upstreamHandler(req: IncomingMessage, res: ServerResponse): void {
	const url = req.url ?? '/'
	hits.set(url, (hits.get(url) ?? 0) + 1)
	const reply = (status: number): void => {
		res.writeHead(status, { 'Content-Type': 'text/plain', [UPSTREAM_HEADER]: UPSTREAM_HEADER_VALUE })
		res.end(UPSTREAM_BODY)
	}
	if (url.startsWith('/ok')) {
		reply(200)
	} else if (url.startsWith('/slow')) {
		setTimeout(() => reply(200), SLOW_MS)
	} else if (url.startsWith('/status500')) {
		reply(500)
	} else if (url.startsWith('/hang')) {
		openSockets.add(res)
	} else {
		reply(404)
	}
}

/**
 * Start an HTTP server on an ephemeral port.
 *
 * @param handler the request handler.
 * @return the server and the base URL Nextcloud reaches it on.
 */
async function startServer(
	handler: (req: IncomingMessage, res: ServerResponse) => void,
): Promise<{ server: Server; base: string }> {
	const server = createServer(handler)
	await new Promise<void>((resolve) => server.listen(0, '0.0.0.0', () => resolve()))
	const { port } = server.address() as AddressInfo
	return { server, base: `http://${FIXTURE_HOST}:${port}` }
}

/**
 * Close a server, dropping any connection it still holds.
 *
 * @param server the server.
 * @return resolves once it no longer listens.
 */
async function stopServer(server: Server | undefined): Promise<void> {
	if (!server?.listening) {
		return
	}
	server.closeAllConnections()
	await new Promise<void>((resolve) => server.close(() => resolve()))
}

/**
 * How many requests the fixture received for one path and query.
 *
 * @param url the full fixture URL.
 * @return the request count.
 */
function hitsFor(url: string): number {
	const parsed = new URL(url)
	return hits.get(`${parsed.pathname}${parsed.search}`) ?? 0
}

/**
 * An app value of LaunchPad, or null when unset.
 *
 * @param key the app config key.
 * @return the stored string, or null.
 */
async function readAppValue(key: string): Promise<string | null> {
	const res = await admin.get(`${CONFIG}/${key}?format=json`)
	if (!res.ok()) {
		return null
	}
	const data = (await res.json())?.ocs?.data
	return typeof data?.data === 'string' && data.data !== '' ? data.data : null
}

/**
 * Write an app value of LaunchPad, or delete it when `value` is null.
 *
 * @param key the app config key.
 * @param value the value, or null to delete.
 * @return nothing; throws when the write is refused.
 */
async function writeAppValue(key: string, value: string | null): Promise<void> {
	const res = value === null
		? await admin.delete(`${CONFIG}/${key}?format=json`)
		: await admin.post(`${CONFIG}/${key}?format=json`, { form: { value } })
	expect(res.ok(), `set ${key} -> ${res.status()}`).toBeTruthy()
}

/**
 * Put the fixture host (and only it) on the health-ping allow-list.
 *
 * @return nothing.
 */
async function allowFixtureHost(): Promise<void> {
	await writeAppValue(ALLOWED_HOSTS, JSON.stringify([FIXTURE_HOST]))
}

/**
 * Create a launcher tile on the author's dashboard.
 *
 * @param title the tile title.
 * @return the placement id.
 */
async function createTile(title: string): Promise<number> {
	const res = await author!.api.post(`${API}/dashboard/${dashboardId}/tile`, {
		data: { title, linkType: 'url', linkValue: 'https://example.org/', gridX: 0, gridY, gridWidth: 2, gridHeight: 2 },
	})
	gridY += 2
	expect(res.status(), await res.text()).toBe(201)
	return Number((await res.json()).id)
}

/**
 * Store a tile's content blob through the placement update endpoint.
 *
 * @param placementId the placement.
 * @param content the full content blob.
 * @return the response status.
 */
async function putContent(placementId: number, content: Record<string, unknown>): Promise<number> {
	const res = await author!.api.put(`${API}/widgets/${placementId}`, { data: { content } })
	return res.status()
}

/**
 * Create a tile and store a health-ping config on it.
 *
 * @param title the tile title.
 * @param healthUrl the health URL.
 * @param overrides config keys to change from the defaults.
 * @return the placement id.
 */
async function pingTile(title: string, healthUrl: string, overrides: Record<string, unknown> = {}): Promise<number> {
	const id = await createTile(title)
	const status = await putContent(id, {
		healthPingEnabled: true,
		healthUrl,
		expectedStatus: 200,
		pingInterval: 60,
		...overrides,
	})
	expect(status, `could not configure the ping on ${title}`).toBeLessThan(300)
	return id
}

/**
 * Wait until Nextcloud reaches a fixture server that just started. On a
 * runner where the fixture host is forwarded (WSL), a new port answers
 * Nextcloud only after about a second, and a ping before that reads offline
 * and is cached for the interval. Each probe is a fresh tile, so no cached
 * reading answers it.
 *
 * @param base the server's base URL.
 * @return resolves once a ping of it reads online.
 */
async function awaitReachable(base: string): Promise<void> {
	await expect
		.poll(async () => {
			const probe = await pingTile(`Reach ${STAMP}`, `${base}/ok?p=reach-${Date.now()}`)
			const reading = await badge(author!.api, probe)
			await author!.api.delete(`${API}/widgets/${probe}`)
			return reading.body?.state
		}, { message: 'Nextcloud must reach the new fixture server', timeout: 20_000, intervals: [500, 1_000] })
		.toBe('online')
}

/**
 * Read one placement of the author's dashboard, as the author.
 *
 * @param placementId the placement.
 * @return the serialised placement, with `content` decoded.
 */
async function readPlacement(placementId: number): Promise<Record<string, any>> {
	const res = await author!.api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const placements = ((body.data ?? body).placements ?? []) as Array<Record<string, any>>
	const placement = placements.find((candidate) => Number(candidate.id) === placementId)
	expect(placement, `placement ${placementId} is not on the dashboard`).toBeTruthy()
	const content = typeof placement!.content === 'string' ? JSON.parse(placement!.content || '{}') : (placement!.content ?? {})
	return { ...placement!, content }
}

/**
 * Read a placement's badge.
 *
 * @param api the caller.
 * @param placementId the placement.
 * @return the status, the decoded body and the raw text.
 */
async function badge(api: APIRequestContext, placementId: number): Promise<{ status: number; body: any; text: string }> {
	const res = await api.get(`${API}/health-ping/${placementId}`)
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
 * Log the author in and open their dashboard.
 *
 * @param browser the browser.
 * @return the context and page; the caller closes the context.
 */
async function openDashboard(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
	const session = await loginAs(browser, author!.username, author!.password)
	await session.page.goto('/index.php/apps/launchpad/')
	return session
}

/**
 * The rendered tile with a given title.
 *
 * @param page the dashboard page.
 * @param title the tile title.
 * @return the tile locator.
 */
function tileNamed(page: Page, title: string): Locator {
	return page.locator('.tile-widget').filter({ hasText: title })
}

/**
 * Open a tile's editor from edit mode.
 *
 * @param page the dashboard page, already in edit mode.
 * @param title the tile title.
 * @return the editor dialog.
 */
async function openTileEditor(page: Page, title: string): Promise<Locator> {
	const tile = tileNamed(page, title)
	await expect(tile).toBeVisible({ timeout: 30_000 })
	await tile.getByRole('button', { name: 'Tile menu' }).click()
	await page.getByRole('menuitem', { name: 'Edit tile' }).click()
	const editor = page.getByRole('dialog').filter({ hasText: 'Show a live status badge (health ping)' })
	await expect(editor.getByText('Show a live status badge (health ping)')).toBeVisible({ timeout: 10_000 })
	return editor
}

/**
 * Switch the health ping on in an open tile editor.
 *
 * @param editor the editor dialog.
 * @return nothing.
 */
async function enablePing(editor: Locator): Promise<void> {
	await editor.getByText('Show a live status badge (health ping)').click()
	await expect(editor.getByLabel('Health check URL')).toBeVisible()
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	test.setTimeout(120_000)
	;({ server: upstream, base: upstreamBase } = await startServer(upstreamHandler))
	// A port that was listening a moment ago and no longer is: connection refused.
	const refused = await startServer(() => undefined)
	refusedBase = refused.base
	await stopServer(refused.server)

	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	previousHosts = await readAppValue(ALLOWED_HOSTS)
	previousThreshold = await readAppValue(LATENCY_THRESHOLD)
	previousOfflineAfter = await readAppValue(OFFLINE_AFTER)
	await allowFixtureHost()
	await writeAppValue(LATENCY_THRESHOLD, '5000')
	// One failed ping reads offline, so a tile on a failing URL shows its
	// offline badge on the first read; the stale test sets its own count.
	await writeAppValue(OFFLINE_AFTER, '1')

	await allowPersonalDashboards(admin, true)
	author = await createPrincipal(admin, 'e2e-hping-author')
	await assertActingAs(author.api, author.username)
	stranger = await createPrincipal(admin, 'e2e-hping-stranger')
	await assertActingAs(stranger.api, stranger.username)

	const created = await author.api.post(`${API}/dashboard`, { data: { name: `E2E health ping ${STAMP}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dashboard = body.dashboard ?? body.data?.dashboard ?? body
	dashboardId = Number(dashboard.id)
	expect((await author.api.post(`${API}/dashboard/${dashboardId}/activate`)).status()).toBeLessThan(300)
	expect((await author.api.post(`${API}/dashboards/active`, { data: { uuid: dashboard.uuid } })).status()).toBeLessThan(300)

	// Can Nextcloud reach the fixture at all? One ping says.
	const probeUrl = `${upstreamBase}/ok?p=probe`
	const probe = await pingTile(`Probe ${STAMP}`, probeUrl)
	await badge(author.api, probe)
	reachable = hitsFor(probeUrl) > 0
	await author.api.delete(`${API}/widgets/${probe}`)
})

test.afterAll(async () => {
	if (admin) {
		await writeAppValue(ALLOWED_HOSTS, previousHosts).catch(() => undefined)
		await writeAppValue(LATENCY_THRESHOLD, previousThreshold).catch(() => undefined)
		await writeAppValue(OFFLINE_AFTER, previousOfflineAfter).catch(() => undefined)
		if (author && dashboardId) {
			await author.api.delete(`${API}/dashboard/${dashboardId}`).catch(() => undefined)
		}
		await removePrincipal(admin, author)
		await removePrincipal(admin, stranger)
		await admin.dispose()
	}
	for (const res of openSockets) {
		res.destroy()
	}
	await stopServer(upstream)
})

// @e2e service-health-ping::enable-ping-with-a-health-url
test('the author enables a ping in the tile editor and it lands in the placement content', async ({ browser }) => {
	const title = `Enable ping ${STAMP}`
	const placementId = await createTile(title)
	const healthUrl = `${upstreamBase}/ok?p=enable`
	const columnsBefore = Object.keys(await readPlacement(placementId)).sort()

	const { context, page } = await openDashboard(browser)
	try {
		await enterEditMode(page)
		const editor = await openTileEditor(page, title)
		await enablePing(editor)
		await editor.getByLabel('Health check URL').fill(healthUrl)
		await editor.getByLabel('Expected HTTP status').fill('200')
		await editor.getByLabel('Check interval (seconds)').fill('60')
		await editor.getByRole('button', { name: 'Save' }).click()
		await expect(editor).toBeHidden({ timeout: 10_000 })
	} finally {
		await context.close()
	}

	const saved = await readPlacement(placementId)
	expect(saved.content).toMatchObject({
		healthPingEnabled: true,
		healthUrl,
		expectedStatus: 200,
		pingInterval: 60,
	})
	// No migration, no new column: the placement serialises the same fields
	// as before, and none of them is a health-ping column.
	expect(Object.keys(saved).sort()).toEqual(columnsBefore)
	expect(Object.keys(saved).filter((key) => /health|ping/i.test(key))).toEqual([])
})

// @e2e service-health-ping::host-not-on-the-allow-list-is-rejected-at-save
test('a health URL on a host outside the allow-list is refused and not persisted', async ({ browser }) => {
	const title = `Refused host ${STAMP}`
	const placementId = await createTile(title)
	const badUrl = `https://not-allowed-${STAMP}.example.invalid/health`

	// The validation endpoint names the refusal, and accepts an allowed host.
	const refusal = await author!.api.post(`${API}/health-ping/validate`, {
		data: { config: { healthPingEnabled: true, healthUrl: badUrl } },
	})
	expect(refusal.status(), await refusal.text()).toBe(200)
	expect(await refusal.json()).toEqual({ valid: false, errors: ['host_not_allowed'] })
	const accepted = await author!.api.post(`${API}/health-ping/validate`, {
		data: { config: { healthPingEnabled: true, healthUrl: `${upstreamBase}/ok` } },
	})
	expect(await accepted.json()).toEqual({ valid: true, errors: [] })

	// Through the editor: the author sees the validation error, saves anyway,
	// and the ping config must not be stored.
	const { context, page } = await openDashboard(browser)
	try {
		await enterEditMode(page)
		const editor = await openTileEditor(page, title)
		await enablePing(editor)
		await editor.getByLabel('Health check URL').fill(badUrl)
		await editor.getByLabel('Health check URL').press('Tab')
		await expect(editor.getByText('This host is not on the allow-list.')).toBeVisible({ timeout: 10_000 })
		await editor.getByRole('button', { name: 'Save' }).click()
		await page.waitForTimeout(1_500)
	} finally {
		await context.close()
	}
	let saved = await readPlacement(placementId)
	expect(saved.content.healthPingEnabled, 'the refused ping config was persisted').not.toBe(true)
	expect(saved.content.healthUrl ?? '').not.toBe(badUrl)

	// Straight to the save endpoint: refused, and the re-read is unchanged.
	const status = await putContent(placementId, {
		healthPingEnabled: true,
		healthUrl: badUrl,
		expectedStatus: 200,
		pingInterval: 60,
	})
	expect(status, 'the save endpoint accepted a host outside the allow-list').toBe(400)
	saved = await readPlacement(placementId)
	expect(saved.content.healthPingEnabled).not.toBe(true)
	expect(saved.content.healthUrl ?? '').not.toBe(badUrl)
})

// @e2e service-health-ping::interval-bounds
test('an interval under 15 seconds is raised to 15, and an unset one becomes 60', async ({ browser }) => {
	const title = `Interval bounds ${STAMP}`
	const placementId = await createTile(title)
	const healthUrl = `${upstreamBase}/ok?p=interval`

	const { context, page } = await openDashboard(browser)
	try {
		await enterEditMode(page)
		let editor = await openTileEditor(page, title)
		await enablePing(editor)
		await editor.getByLabel('Health check URL').fill(healthUrl)
		await editor.getByLabel('Check interval (seconds)').fill('5')
		await editor.getByRole('button', { name: 'Save' }).click()
		await expect(editor).toBeHidden({ timeout: 10_000 })
		await expect.poll(async () => (await readPlacement(placementId)).content.pingInterval).toBe(15)

		editor = await openTileEditor(page, title)
		await editor.getByLabel('Check interval (seconds)').fill('')
		await editor.getByRole('button', { name: 'Save' }).click()
		await expect(editor).toBeHidden({ timeout: 10_000 })
		await expect.poll(async () => (await readPlacement(placementId)).content.pingInterval).toBe(60)
	} finally {
		await context.close()
	}
})

// @e2e service-health-ping::online-when-status-matches-within-latency-threshold
test('the expected status inside the latency threshold reads online', async () => {
	test.skip(!reachable, UNREACHABLE)
	await allowFixtureHost()
	await writeAppValue(LATENCY_THRESHOLD, '5000')
	const url = `${upstreamBase}/ok?p=online`
	const id = await pingTile(`Online ${STAMP}`, url)

	const reading = await badge(author!.api, id)
	expect(reading.status, reading.text).toBe(200)
	expect(hitsFor(url), 'the server did not ping the service').toBe(1)
	expect(reading.body.state).toBe('online')
	expect(reading.body.stale).toBe(false)
	expect(reading.body.latencyMs).toBeLessThanOrEqual(5000)
})

// @e2e service-health-ping::degraded-when-slow
test('the expected status slower than the threshold reads degraded', async () => {
	test.skip(!reachable, UNREACHABLE)
	await allowFixtureHost()
	await writeAppValue(LATENCY_THRESHOLD, '300')
	try {
		const url = `${upstreamBase}/slow?p=degraded`
		const id = await pingTile(`Degraded ${STAMP}`, url)

		const reading = await badge(author!.api, id)
		expect(reading.status, reading.text).toBe(200)
		expect(hitsFor(url)).toBe(1)
		expect(reading.body.state).toBe('degraded')
		expect(reading.body.latencyMs).toBeGreaterThan(300)
	} finally {
		await writeAppValue(LATENCY_THRESHOLD, '5000')
	}
})

// @e2e service-health-ping::offline-after-consecutive-failed-pings
test('with offline after one failed ping, a wrong status, a refused connection and a timeout each read offline', async () => {
	test.skip(!reachable, UNREACHABLE)
	test.setTimeout(120_000)
	await allowFixtureHost()
	await writeAppValue(LATENCY_THRESHOLD, '5000')

	const cases = [
		{ name: 'status 500, 200 expected', url: `${upstreamBase}/status500?p=offline` },
		{ name: 'status 200, 204 expected', url: `${upstreamBase}/ok?p=offline-204`, expectedStatus: 204 },
		{ name: 'connection refused', url: `${refusedBase}/ok?p=offline-refused` },
		{ name: 'timeout', url: `${upstreamBase}/hang?p=offline-timeout` },
	]
	for (const item of cases) {
		const id = await pingTile(`Offline ${item.name} ${STAMP}`, item.url, item.expectedStatus ? { expectedStatus: item.expectedStatus } : {})
		const reading = await badge(author!.api, id)
		expect(reading.status, `${item.name}: ${reading.text}`).toBe(200)
		expect(reading.body.state, item.name).toBe('offline')
		expect(reading.body.stale, item.name).toBe(false)
		// Keep the dashboard quiet for the UI tests that follow.
		await author!.api.delete(`${API}/widgets/${id}`)
	}
})

// @e2e service-health-ping::allow-list-enforced-at-ping-time-fail-closed
test('a host removed from the allow-list after configuration is never pinged nor reported up', async () => {
	test.skip(!reachable, UNREACHABLE)
	await allowFixtureHost()
	const url = `${upstreamBase}/ok?p=fail-closed`
	const id = await pingTile(`Fail closed ${STAMP}`, url)

	await writeAppValue(ALLOWED_HOSTS, JSON.stringify([`elsewhere-${STAMP}.example.invalid`]))
	try {
		const reading = await badge(author!.api, id)
		expect(reading.status, reading.text).toBe(200)
		expect(hitsFor(url), 'the refused host was contacted').toBe(0)
		expect(reading.body.state).not.toBe('online')
		// No ping performed: no state, no check time, marked stale.
		expect(reading.body).toEqual({ state: null, checkedAt: null, latencyMs: null, stale: true })
	} finally {
		await allowFixtureHost()
	}

	// The same placement pings once its host is allowed again.
	const allowed = await badge(author!.api, id)
	expect(allowed.body.state).toBe('online')
	expect(hitsFor(url)).toBe(1)
	await author!.api.delete(`${API}/widgets/${id}`)
})

// @e2e service-health-ping::endpoint-serves-the-cached-badge
test('the badge answer holds state, check time, latency and staleness, and nothing of the upstream', async () => {
	test.skip(!reachable, UNREACHABLE)
	await allowFixtureHost()
	const url = `${upstreamBase}/ok?p=shape`
	const id = await pingTile(`Shape ${STAMP}`, url)

	const reading = await badge(author!.api, id)
	expect(reading.status, reading.text).toBe(200)
	expect(hitsFor(url)).toBe(1)
	expect(Object.keys(reading.body).sort()).toEqual(['checkedAt', 'latencyMs', 'stale', 'state'])
	expect(reading.body.state).toBe('online')
	expect(Number.isNaN(Date.parse(reading.body.checkedAt))).toBe(false)
	expect(typeof reading.body.latencyMs).toBe('number')
	expect(typeof reading.body.stale).toBe('boolean')

	expect(reading.text, 'the health URL leaked').not.toContain(url)
	expect(reading.text).not.toContain(new URL(url).host)
	expect(reading.text, 'the upstream body leaked').not.toContain(UPSTREAM_BODY)
	expect(reading.text, 'an upstream header leaked').not.toContain(UPSTREAM_HEADER)
	expect(reading.text).not.toContain(UPSTREAM_HEADER_VALUE)
	await author!.api.delete(`${API}/widgets/${id}`)
})

// @e2e service-health-ping::caller-authorization
test('a logged-in user who may not view the dashboard gets 403 and causes no ping', async () => {
	test.skip(!reachable, UNREACHABLE)
	await allowFixtureHost()
	const url = `${upstreamBase}/ok?p=authz`
	const id = await pingTile(`Authz ${STAMP}`, url)

	const refused = await badge(stranger!.api, id)
	expect(refused.status, refused.text).toBe(403)
	expect(refused.body?.state).toBeUndefined()
	expect(refused.body?.latencyMs).toBeUndefined()
	expect(refused.body?.checkedAt).toBeUndefined()
	expect(refused.text).not.toContain(url)
	expect(hitsFor(url), 'the refused caller caused a ping').toBe(0)

	// The owner may, which pings once: the refusal was the rule, not a broken endpoint.
	const allowed = await badge(author!.api, id)
	expect(allowed.status, allowed.text).toBe(200)
	expect(allowed.body.state).toBe('online')
	expect(hitsFor(url)).toBe(1)
	await author!.api.delete(`${API}/widgets/${id}`)
})

// @e2e service-health-ping::cached-within-ttl
test('a second read inside the interval is served from cache without a new upstream request', async () => {
	test.skip(!reachable, UNREACHABLE)
	await allowFixtureHost()
	const url = `${upstreamBase}/ok?p=ttl`
	const id = await pingTile(`TTL ${STAMP}`, url, { pingInterval: 60 })

	const first = await badge(author!.api, id)
	expect(first.status, first.text).toBe(200)
	expect(hitsFor(url)).toBe(1)

	const second = await badge(author!.api, id)
	expect(second.status, second.text).toBe(200)
	expect(second.body.stale).toBe(false)
	expect(second.body).toEqual(first.body)
	expect(hitsFor(url), 'the cached badge re-pinged the upstream').toBe(1)
	await author!.api.delete(`${API}/widgets/${id}`)
})

// @e2e service-health-ping::background-refresh-of-due-entries
test('the refresh job pings the due ping-enabled tiles only, and skips a host off the allow-list', async () => {
	test.skip(!reachable, UNREACHABLE)
	test.setTimeout(150_000)
	await allowFixtureHost()
	const dueUrl = `${upstreamBase}/ok?p=job-due`
	const freshUrl = `${upstreamBase}/ok?p=job-fresh`
	const offUrl = `${upstreamBase}/ok?p=job-off`
	const due = await pingTile(`Job due ${STAMP}`, dueUrl, { pingInterval: 15 })
	const fresh = await pingTile(`Job fresh ${STAMP}`, freshUrl, { pingInterval: 3600 })
	const off = await pingTile(`Job off ${STAMP}`, offUrl, { healthPingEnabled: false })
	try {
		// One read each caches a reading; the ping-off tile is never read.
		expect((await badge(author!.api, due)).body.state).toBe('online')
		expect((await badge(author!.api, fresh)).body.state).toBe('online')
		expect(hitsFor(dueUrl)).toBe(1)
		expect(hitsFor(freshUrl)).toBe(1)

		// The 15-second tile's reading ages past its interval; the other's does not.
		await new Promise((resolve) => setTimeout(resolve, 16_500))
		const run = await executeJobNow(JOB)
		expect(run.code, describeRun(run)).toBe(0)
		expect(hitsFor(dueUrl), 'the job refreshed the due tile, with no badge read').toBe(2)
		expect(hitsFor(freshUrl), 'the job pinged a tile inside its interval').toBe(1)
		expect(hitsFor(offUrl), 'the job pinged a tile with the ping off').toBe(0)

		// The endpoint now serves the job's reading without pinging again.
		const served = await badge(author!.api, due)
		expect(served.body.state).toBe('online')
		expect(served.body.stale).toBe(false)
		expect(hitsFor(dueUrl), 'the read after the job re-pinged').toBe(2)

		// Due again, but its host is no longer allow-listed: the job contacts nothing.
		await new Promise((resolve) => setTimeout(resolve, 16_500))
		await writeAppValue(ALLOWED_HOSTS, JSON.stringify([`elsewhere-${STAMP}.example.invalid`]))
		try {
			const refused = await executeJobNow(JOB)
			expect(refused.code, describeRun(refused)).toBe(0)
			expect(hitsFor(dueUrl), 'the job pinged a host off the allow-list').toBe(2)
		} finally {
			await allowFixtureHost()
		}
	} finally {
		for (const id of [due, fresh, off]) {
			await author!.api.delete(`${API}/widgets/${id}`)
		}
	}
})

// @e2e service-health-ping::stale-fallback-on-refresh-failure
// @e2e service-health-ping::a-failed-ping-marks-the-badge-stale
test('when a refresh cannot reach the upstream the last-known badge comes back marked stale', async ({ browser }) => {
	test.skip(!reachable, UNREACHABLE)
	test.setTimeout(150_000)
	await allowFixtureHost()
	await writeAppValue(OFFLINE_AFTER, '3')
	const own = await startServer(upstreamHandler)
	await awaitReachable(own.base)
	const title = `Stale ${STAMP}`
	const url = `${own.base}/ok?p=stale`
	let id = 0
	try {
		id = await pingTile(title, url, { pingInterval: 15 })

		const first = await badge(author!.api, id)
		expect(first.status, first.text).toBe(200)
		expect(first.body.state).toBe('online')

		// The upstream goes away, and the cached reading ages past the interval.
		await stopServer(own.server)
		await new Promise((resolve) => setTimeout(resolve, 16_500))

		const second = await badge(author!.api, id)
		expect(second.status, second.text).toBe(200)
		expect(second.body.state, 'the last-known state was not served').toBe(first.body.state)
		expect(second.body.checkedAt).toBe(first.body.checkedAt)
		expect(second.body.stale).toBe(true)

		// The tile still renders.
		const { context, page } = await openDashboard(browser)
		try {
			const tile = tileNamed(page, title)
			await expect(tile).toBeVisible({ timeout: 30_000 })
			await expect(tile.locator('.tile-widget__link')).toBeVisible()
		} finally {
			await context.close()
		}

		// THEN: once the configured number of consecutive failed pings is
		// reached (two here), the badge reads offline.
		await writeAppValue(OFFLINE_AFTER, '2')
		await new Promise((resolve) => setTimeout(resolve, 16_500))
		const third = await badge(author!.api, id)
		expect(third.status, third.text).toBe(200)
		expect(third.body.state).toBe('offline')
		expect(third.body.stale).toBe(false)
	} finally {
		await writeAppValue(OFFLINE_AFTER, '1')
		if (id > 0) {
			await author!.api.delete(`${API}/widgets/${id}`)
		}
	}
})

// @e2e service-health-ping::badge-is-not-colour-only
test('each badge state shows its own icon and a text label', async ({ browser }) => {
	test.skip(!reachable, UNREACHABLE)
	test.setTimeout(120_000)
	await allowFixtureHost()
	await writeAppValue(LATENCY_THRESHOLD, '800')
	const tiles = [
		{ title: `Badge online ${STAMP}`, url: `${upstreamBase}/ok?p=ui-online`, label: 'Online', icon: 'check-circle-outline-icon' },
		{ title: `Badge degraded ${STAMP}`, url: `${upstreamBase}/slow?p=ui-degraded`, label: 'Degraded', icon: 'alert-outline-icon' },
		{ title: `Badge offline ${STAMP}`, url: `${upstreamBase}/status500?p=ui-offline`, label: 'Offline', icon: 'close-circle-outline-icon' },
	]
	const ids: number[] = []
	try {
		for (const tile of tiles) {
			ids.push(await pingTile(tile.title, tile.url))
		}

		const { context, page } = await openDashboard(browser)
		try {
			for (const tile of tiles) {
				const shown = tileNamed(page, tile.title).locator('.health-ping-badge')
				await expect(shown).toBeVisible({ timeout: 30_000 })
				await expect(shown.locator('.health-ping-badge__label')).toHaveText(tile.label)
				await expect(shown.locator(`.${tile.icon} svg`)).toBeVisible()
				for (const other of tiles.filter((candidate) => candidate !== tile)) {
					await expect(shown.locator(`.${other.icon}`)).toHaveCount(0)
				}
			}
		} finally {
			await context.close()
		}
	} finally {
		await writeAppValue(LATENCY_THRESHOLD, '5000')
		for (const id of ids) {
			await author!.api.delete(`${API}/widgets/${id}`)
		}
	}
})

// @e2e service-health-ping::accessible-detail-on-demand
test('the badge is a keyboard-reachable button whose name and tooltip give check time and latency', async ({ browser }) => {
	test.skip(!reachable, UNREACHABLE)
	await allowFixtureHost()
	const title = `Badge detail ${STAMP}`
	const id = await pingTile(title, `${upstreamBase}/ok?p=ui-detail`)
	try {
		const { context, page } = await openDashboard(browser)
		try {
			const shown = tileNamed(page, title).locator('.health-ping-badge')
			await expect(shown).toBeVisible({ timeout: 30_000 })

			// Announced: the accessible name carries state, check time and latency.
			await expect(shown).toHaveAccessibleName(/^Online — checked .+ \(\d+ ms\)$/)
			// On hover: the native tooltip carries the same text.
			const name = await shown.getAttribute('aria-label')
			await expect(shown).toHaveAttribute('title', name ?? '')
			await shown.hover()

			// Keyboard: a real, enabled button in the tab order takes focus.
			expect(await shown.evaluate((element) => element.tagName)).toBe('BUTTON')
			await expect(shown).toBeEnabled()
			expect(await shown.evaluate((element) => (element as HTMLElement).tabIndex)).toBeGreaterThanOrEqual(0)
			await shown.focus()
			await expect(shown).toBeFocused()
		} finally {
			await context.close()
		}
	} finally {
		await author!.api.delete(`${API}/widgets/${id}`)
	}
})

// @e2e service-health-ping::ping-disabled-shows-no-badge
test('a tile with the ping switched off shows no badge and never asks for one', async ({ browser }) => {
	test.skip(!reachable, UNREACHABLE)
	await allowFixtureHost()
	const offTitle = `Ping off ${STAMP}`
	const onTitle = `Ping on ${STAMP}`
	const offId = await createTile(offTitle)
	expect(await putContent(offId, {
		healthPingEnabled: false,
		healthUrl: `${upstreamBase}/ok?p=ui-off`,
		expectedStatus: 200,
		pingInterval: 60,
	})).toBeLessThan(300)
	const onId = await pingTile(onTitle, `${upstreamBase}/ok?p=ui-on`)

	try {
		const { context, page } = await openDashboard(browser)
		const asked: string[] = []
		page.on('request', (req) => {
			const match = /\/api\/health-ping\/(\d+)/.exec(req.url())
			if (match) {
				asked.push(match[1])
			}
		})
		try {
			await page.reload()
			await expect(tileNamed(page, offTitle)).toBeVisible({ timeout: 30_000 })
			// The enabled tile on the same page proves the listener sees badge requests.
			await expect(tileNamed(page, onTitle).locator('.health-ping-badge')).toBeVisible({ timeout: 30_000 })
			expect(asked).toContain(String(onId))

			await expect(tileNamed(page, offTitle).locator('.health-ping-badge')).toHaveCount(0)
			expect(asked, 'the disabled tile asked for a badge').not.toContain(String(offId))
		} finally {
			await context.close()
		}
	} finally {
		await author!.api.delete(`${API}/widgets/${offId}`)
		await author!.api.delete(`${API}/widgets/${onId}`)
	}
})
