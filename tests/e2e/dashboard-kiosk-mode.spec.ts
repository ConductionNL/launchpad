/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for kiosk mode (openspec/specs/dashboard-kiosk-mode),
 * written for the e2e-coverage-programme.
 *
 * WHO DOES WHAT
 * =============
 * - alice and bob are throwaway ordinary accounts (tests/e2e/support/principals.ts),
 *   provisioned over basic auth. alice owns the dashboards her playlists play;
 *   bob is the least-privileged principal for every refusal.
 * - The admin plays "Ruben", the administrator of REQ-KIOSKUI-001, through the
 *   real Beheer ▸ Kiosk tab (src/components/admin/tabs/KioskTab.vue).
 * - The screen is a browser context with an EMPTY storage state, so it is
 *   logged out. Every player test checks that first, because the shared
 *   config otherwise hands each context the admin session.
 *
 * TIME
 * ====
 * Dwell, refresh and retry timers are driven by Playwright's page.clock: the
 * clock is installed before the player loads and paused once the first
 * dashboard is on screen (`freeze`). From then on time moves only through
 * `runFor`, so "45 seconds later" costs no real waiting. The few hundred
 * milliseconds between the player scheduling its first dwell timer and the
 * pause are absorbed by checkpoints placed at least five seconds away from
 * every boundary.
 *
 * Network trouble is simulated with page.route on the player's JSON fetch of
 * its own URL (src/views/KioskPlayerView.vue `load()`: GET
 * /apps/launchpad/kiosk/{token} with `Accept: application/json`). The page
 * navigation to the same URL is never intercepted.
 *
 * BRUTE-FORCE BUDGET
 * ==================
 * Every 404 from /kiosk/{token} registers an attempt on the IP-global
 * `launchpad_share_access` bucket (KioskController::render), and so does a 404
 * from /s/{token}/data. This file spends five: the unknown and the revoked
 * playlist token, the revoked player re-reading its playlist, and the expired
 * share read with and without the flag. Nothing else here asks for a dead
 * token, which is also why the throttle scenario itself is excluded.
 *
 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
 */

import type {
	APIRequestContext,
	APIResponse,
	Browser,
	BrowserContext,
	Locator,
	Page,
	Request,
	Route,
} from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, request, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { installBundleOverride } from './support/bundleOverride.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const PLAYLISTS = `${API}/kiosk/playlists`
const SETTINGS_URL = '/index.php/settings/admin/launchpad'
const RUN = Date.now()

/** A logged-out browser: no cookies, no local storage. */
const LOGGED_OUT = { cookies: [], origins: [] }

/** Workspace chrome, as rendered by the normal (non-kiosk) dashboard route. */
const NC_HEADER = '#header'
const APP_NAVIGATION = '.app-navigation, #app-navigation'
const SWITCHER = '.dashboard-switcher-sidebar'
/** The workspace's More menu, which holds the dashboard switcher. */
const SIDEBAR_TOGGLE = MORE_MENU
const FLOATING_CONTROLS = '.launchpad-floating-controls'
const CONTEXT_MENU = '.widget-context-menu'
const RESIZE_HANDLE = '.ui-resizable-handle'
const DRAGGABLE = '.grid-stack-item.ui-draggable:not(.ui-draggable-disabled)'
/** The workspace grid (CnDashboardGrid in src/views/Views.vue). */
const WORKSPACE_GRID = '.grid-stack'
/** The read-only grid (src/components/PublicDashboardGrid.vue). */
const PUBLIC_GRID = '.public-share-view__grid'
const PUBLIC_WIDGET_TEXT = '.public-share-view__widget-text'

/** Words that would mean the screen is showing a technical error. */
const TECHNICAL =
	/exception|stack ?trace|traceback|fatal error|internal server error|\.php|\b500\b|undefined/i

type Dashboard = {
	id: number
	uuid: string
	name: string
	slug: string
	placementId: number
	text: string
}

type Playlist = {
	id: number
	name: string
	token: string
	url: string | null
	entries: Array<{ dashboardUuid: string; dwellSeconds: number }>
	refreshSeconds: number
	createdBy?: string
	revokedAt: string | null
}

let admin: APIRequestContext
let alice: Principal | null = null
let bob: Principal | null = null
let priorSettings: { allowUserDash: boolean; allowMultiDash: boolean } | null = null

/** Dashboards to delete in afterAll, with the context that owns each. */
const createdDashboards: Array<{ api: APIRequestContext; id: number }> = []
/** Playlists to revoke in afterAll (the admin may revoke any). */
const createdPlaylists: number[] = []

let counter = 0

/**
 * A short unique suffix for names, slugs and widget texts in this run.
 *
 * @return the suffix.
 */
function stamp(): string {
	counter += 1
	return `${RUN}-${counter}`
}

/**
 * Create a dashboard with one text widget, owned by whoever `api` acts as.
 *
 * @param api the owner's context.
 * @param label a readable prefix for the name.
 * @return the dashboard, its slug, the widget's placement id and its text.
 */
async function createDashboard(
	api: APIRequestContext,
	label: string,
): Promise<Dashboard> {
	const s = stamp()
	const name = `E2E Kiosk ${label} ${s}`
	const slug = `e2e-kiosk-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}-${s}`
	const created = await api.post(`${API}/dashboard`, { data: { name, slug } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const id = Number(dash.id)
	expect(id, `no dashboard id in ${JSON.stringify(body)}`).toBeTruthy()
	createdDashboards.push({ api, id })

	const text = `kiosk text ${label} ${s}`
	const widget = await api.post(`${API}/dashboard/${id}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			content: { text, contentMode: 'markdown' },
		},
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)
	const placement = await widget.json()

	return {
		id,
		uuid: String(dash.uuid),
		name,
		slug: String(dash.slug ?? slug),
		placementId: Number(
			placement.id ?? placement.data?.id ?? placement.placement?.id,
		),
		text,
	}
}

/**
 * Change the text of a dashboard's text widget.
 *
 * @param api the owner's context.
 * @param dashboard the dashboard whose widget changes.
 * @param text the new text.
 * @return nothing.
 */
async function setWidgetText(
	api: APIRequestContext,
	dashboard: Dashboard,
	text: string,
): Promise<void> {
	const res = await api.put(`${API}/widgets/${dashboard.placementId}`, {
		data: { content: { text, contentMode: 'markdown' } },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	dashboard.text = text
}

/**
 * POST a playlist and remember it for cleanup when it was created.
 *
 * @param api the creator's context.
 * @param body the request body.
 * @return the raw response.
 */
async function postPlaylist(
	api: APIRequestContext,
	body: Record<string, unknown>,
): Promise<APIResponse> {
	const res = await api.post(PLAYLISTS, { data: body })
	if (res.status() === 201) {
		createdPlaylists.push(Number((await res.json()).id))
	}
	return res
}

/**
 * Create a playlist that must succeed.
 *
 * @param api the creator's context.
 * @param name the playlist name.
 * @param entries dashboards with their dwell time.
 * @param refreshSeconds the refresh interval.
 * @return the created playlist.
 */
async function createPlaylist(
	api: APIRequestContext,
	name: string,
	entries: Array<[Dashboard, number]>,
	refreshSeconds: number,
): Promise<Playlist> {
	const res = await postPlaylist(api, {
		name,
		entries: entries.map(([d, dwellSeconds]) => ({
			dashboardUuid: d.uuid,
			dwellSeconds,
		})),
		refreshSeconds,
	})
	expect(res.status(), await res.text()).toBe(201)
	return res.json()
}

/**
 * The caller's playlists.
 *
 * @param api the caller's context.
 * @return the list.
 */
async function listPlaylists(api: APIRequestContext): Promise<Playlist[]> {
	const res = await api.get(PLAYLISTS)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * An anonymous request context: no cookies, no credentials.
 *
 * @return the context; the caller disposes it.
 */
async function anonymousApi(): Promise<APIRequestContext> {
	return request.newContext({ baseURL: BASE_URL, storageState: LOGGED_OUT })
}

/**
 * The kiosk route as the player reads it: JSON.
 *
 * @param api the context to ask with.
 * @param token the playlist token.
 * @return the response.
 */
async function kioskJson(
	api: APIRequestContext,
	token: string,
): Promise<APIResponse> {
	return api.get(`${APP}/kiosk/${token}`, {
		headers: { Accept: 'application/json' },
	})
}

/**
 * A logged-out browser context, proven logged out.
 *
 * @param browser the browser.
 * @return the context.
 */
async function screenContext(browser: Browser): Promise<BrowserContext> {
	const context = await browser.newContext({
		baseURL: BASE_URL,
		storageState: LOGGED_OUT,
	})
	await installBundleOverride(context)
	const me = await context.request.get('/ocs/v2.php/cloud/user?format=json', {
		headers: { 'OCS-APIRequest': 'true' },
	})
	expect(
		me.status(),
		'CONTROL: the screen must be logged out, or "no login" proves nothing',
	).toBe(401)
	return context
}

/** True for the player's own JSON read of its playlist, not the page load. */
function isPlaylistRead(req: Request, token: string): boolean {
	return (
		req.url().includes(`/kiosk/${token}`)
		&& ['xhr', 'fetch'].includes(req.resourceType())
	)
}

/**
 * Open a playlist link on a logged-out screen with a fake clock, wait for the
 * first dashboard, and pause time there.
 *
 * @param browser the browser.
 * @param token the playlist token.
 * @param options `before` runs after the clock is installed and before the page loads.
 * @return the context, page, the page response and a live list of playlist reads.
 */
async function openPlayer(
	browser: Browser,
	token: string,
	options: {
		before?: (page: Page) => Promise<void>
		expectDashboard?: boolean
	} = {},
) {
	const context = await screenContext(browser)
	const page = await context.newPage()
	const reads: Request[] = []
	page.on('request', (req) => {
		if (isPlaylistRead(req, token)) {
			reads.push(req)
		}
	})
	await page.clock.install()
	if (options.before) {
		await options.before(page)
	}
	const response = await page.goto(`${APP}/kiosk/${token}`)
	if (options.expectDashboard !== false) {
		await expect(page.getByTestId('kiosk-dashboard')).toBeVisible({
			timeout: 30_000,
		})
		await freeze(page)
	}
	return { context, page, response, reads }
}

/**
 * Pause the page's fake clock where it is now.
 *
 * @param page the page.
 * @return nothing.
 */
async function freeze(page: Page): Promise<void> {
	const now = await page.evaluate(() => Date.now())
	await page.clock.pauseAt(now + 500)
}

/**
 * The title of the dashboard on screen.
 *
 * @param page the player page.
 * @return the heading.
 */
function onScreen(page: Page): Locator {
	return page.getByTestId('kiosk-dashboard').locator('h1')
}

/**
 * Advance the fake clock and check which dashboard is on screen.
 *
 * @param page the player page.
 * @param ms how far to advance.
 * @param dashboard the dashboard expected afterwards.
 * @return nothing.
 */
async function after(page: Page, ms: number, dashboard: Dashboard): Promise<void> {
	await page.clock.runFor(ms)
	await expect(onScreen(page)).toHaveText(dashboard.name)
}

/**
 * Whether the page's layout overflows the viewport (scrollbars would show).
 *
 * @param page the page.
 * @return true when the document is larger than the viewport.
 */
async function overflowsViewport(page: Page): Promise<boolean> {
	return page.evaluate(() => {
		const el = document.scrollingElement ?? document.documentElement
		return (
			el.scrollWidth > window.innerWidth
			|| el.scrollHeight > window.innerHeight
		)
	})
}

/**
 * Assert a grid spans (almost) the full viewport width.
 *
 * @param page the page.
 * @param grid the grid element.
 * @return nothing.
 */
async function expectFullViewport(page: Page, grid: Locator): Promise<void> {
	const box = await grid.boundingBox()
	const viewport = page.viewportSize()
	expect(box, 'the dashboard grid must be rendered').not.toBeNull()
	expect(viewport).not.toBeNull()
	expect(
		box!.x,
		'the grid must start at the left edge of the viewport',
	).toBeLessThanOrEqual(viewport!.width * 0.03)
	expect(box!.width, 'the grid must span the viewport').toBeGreaterThanOrEqual(
		viewport!.width * 0.94,
	)
	expect(await overflowsViewport(page), 'no scrollbars in kiosk mode').toBe(false)
}

/**
 * The cursor the page shows at a point.
 *
 * @param page the page.
 * @param x viewport x.
 * @param y viewport y.
 * @return the computed CSS cursor of the element under the point.
 */
async function cursorAt(page: Page, x: number, y: number): Promise<string> {
	return page.evaluate(
		([px, py]) => {
			const el = document.elementFromPoint(px, py) ?? document.body
			return getComputedStyle(el).cursor
		},
		[x, y] as const,
	)
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')

	const before = await admin.get(`${API}/admin/settings`)
	expect(before.status(), await before.text()).toBe(200)
	const settings = await before.json()
	priorSettings = {
		allowUserDash: settings.allowUserDashboards === true,
		allowMultiDash: settings.allowMultipleDashboards !== false,
	}
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true, allowMultiDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)

	alice = await createPrincipal(admin, 'e2e-kiosk-alice')
	bob = await createPrincipal(admin, 'e2e-kiosk-bob')
	await assertActingAs(alice.api, alice.username)
	await assertActingAs(bob.api, bob.username)
})

test.afterAll(async () => {
	for (const id of createdPlaylists) {
		await admin.delete(`${PLAYLISTS}/${id}`)
	}
	for (const { api, id } of createdDashboards.reverse()) {
		try {
			await api.delete(`${API}/dashboard/${id}`)
		} catch {
			// The owner's context is gone with its account; nothing to delete.
		}
	}
	await removePrincipal(admin, alice)
	await removePrincipal(admin, bob)
	if (priorSettings) {
		await admin.put(`${API}/admin/settings`, { data: priorSettings })
	}
	await admin.dispose()
})

test.describe('REQ-KIOSK-001 chrome-less kiosk render flag', () => {
	// @e2e dashboard-kiosk-mode::authenticated-kiosk-render-hides-all-chrome
	test('?kiosk=1 renders the dashboard full-viewport with no chrome and no edit affordances', async ({
		browser,
	}) => {
		const lobby = await createDashboard(alice!.api, 'Display')
		const { context, page } = await loginAs(
			browser,
			alice!.username,
			alice!.password,
		)
		try {
			// CONTROL: without the flag the same route shows the chrome, so
			// every "absent" below is measured against selectors that match.
			await page.goto(`${APP}/${lobby.slug}`)
			await expect(page.getByText(lobby.text).first()).toBeVisible({
				timeout: 30_000,
			})
			await expect(page.locator(NC_HEADER).first()).toBeVisible()
			await expect(page.locator(SIDEBAR_TOGGLE).first()).toBeVisible()

			await page.goto(`${APP}/${lobby.slug}?kiosk=1`)
			await expect(page.getByText(lobby.text).first()).toBeVisible({
				timeout: 30_000,
			})
			await expectFullViewport(page, page.locator(WORKSPACE_GRID).first())

			await expect(page.locator(NC_HEADER), 'no Nextcloud header').toHaveCount(
				0,
			)
			await expect(
				page.locator(APP_NAVIGATION),
				'no app navigation',
			).toHaveCount(0)
			await expect(page.locator(SWITCHER), 'no switcher sidebar').toHaveCount(
				0,
			)
			await expect(page.locator(SIDEBAR_TOGGLE)).toHaveCount(0)
			await expect(
				page.locator(FLOATING_CONTROLS),
				'no edit entry point',
			).toHaveCount(0)
			await expect(
				page.locator(RESIZE_HANDLE),
				'no resize handles',
			).toHaveCount(0)
			await expect(page.locator(DRAGGABLE), 'nothing draggable').toHaveCount(0)

			await page.getByText(lobby.text).first().click({ button: 'right' })
			await expect(page.locator(CONTEXT_MENU), 'no context menu').toHaveCount(
				0,
			)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-kiosk-mode::public-share-kiosk-render
	test('/s/{token}?kiosk=1 renders chrome-less for a visitor and keeps the password gate, expiry and read-only rules', async ({
		browser,
	}) => {
		const shared = await createDashboard(admin, 'Shared')
		const share = await admin.post(
			`${API}/dashboards/${shared.uuid}/public-share`,
			{
				data: {},
			},
		)
		expect(share.status(), await share.text()).toBe(201)
		const token = (await share.json()).token

		const password = `Kiosk-${RUN}-pw`
		const locked = await admin.post(
			`${API}/dashboards/${shared.uuid}/public-share`,
			{
				data: { password },
			},
		)
		expect(locked.status(), await locked.text()).toBe(201)
		const lockedToken = (await locked.json()).token

		const expired = await admin.post(
			`${API}/dashboards/${shared.uuid}/public-share`,
			{
				data: { expiresAt: '2020-01-01 00:00:00' },
			},
		)
		expect(expired.status(), await expired.text()).toBe(201)
		const expiredToken = (await expired.json()).token

		const context = await screenContext(browser)
		const anon = await anonymousApi()
		try {
			const page = await context.newPage()

			// CONTROL: the share page without the flag carries the public header.
			await page.goto(`${APP}/s/${token}`)
			await expect(page.locator(PUBLIC_WIDGET_TEXT)).toHaveText(shared.text, {
				timeout: 30_000,
			})
			await expect(page.locator(NC_HEADER).first()).toBeVisible()

			const response = await page.goto(`${APP}/s/${token}?kiosk=1`)
			expect(response?.status()).toBe(200)
			await expect(page.locator(PUBLIC_WIDGET_TEXT)).toHaveText(shared.text, {
				timeout: 30_000,
			})
			await expect(page.locator(NC_HEADER), 'no Nextcloud header').toHaveCount(
				0,
			)
			await expect(page.locator(APP_NAVIGATION)).toHaveCount(0)
			await expectFullViewport(page, page.locator(PUBLIC_GRID))
			// Read-only exactly as without the flag: no workspace, no editable cells.
			await expect(page.locator(SIDEBAR_TOGGLE)).toHaveCount(0)
			await expect(page.locator('.launchpad-grid-item')).toHaveCount(0)

			// The password gate still stands with the flag.
			await page.goto(`${APP}/s/${lockedToken}?kiosk=1`)
			await expect(
				page.getByRole('dialog', { name: 'Password required' }),
			).toBeVisible({
				timeout: 30_000,
			})
			await expect(page.getByText(shared.text)).toHaveCount(0)
			const gated = await anon.get(`${APP}/s/${lockedToken}/data?kiosk=1`)
			expect(gated.status()).toBe(401)
			expect((await gated.json()).passwordRequired).toBe(true)
			const unlocked = await anon.get(`${APP}/s/${lockedToken}/data?kiosk=1`, {
				headers: { 'X-Share-Password': password },
			})
			expect(unlocked.status(), 'CONTROL: the right password opens it').toBe(
				200,
			)

			// Expiry still applies with the flag, with the same answer as without.
			const plain = await anon.get(`${APP}/s/${expiredToken}/data`)
			const flagged = await anon.get(`${APP}/s/${expiredToken}/data?kiosk=1`)
			expect(plain.status()).toBe(404)
			expect(flagged.status()).toBe(plain.status())
			expect(await flagged.text()).toBe(await plain.text())
		} finally {
			await anon.dispose()
			await context.close()
		}
	})

	// @e2e dashboard-kiosk-mode::esc-exits-authenticated-kiosk-mode
	test('Escape leaves kiosk mode for the normal dashboard route', async ({
		browser,
	}) => {
		const lobby = await createDashboard(alice!.api, 'Escape')
		const { context, page } = await loginAs(
			browser,
			alice!.username,
			alice!.password,
		)
		try {
			await page.goto(`${APP}/${lobby.slug}?kiosk=1`)
			await expect(page.getByText(lobby.text).first()).toBeVisible({
				timeout: 30_000,
			})
			await expect(
				page.locator(NC_HEADER),
				'CONTROL: kiosk mode is on',
			).toHaveCount(0)

			await page.keyboard.press('Escape')

			await expect
				.poll(() => new URL(page.url()).searchParams.get('kiosk'))
				.toBeNull()
			expect(new URL(page.url()).pathname.replace(/\/+$/, '')).toBe(
				`${APP}/${lobby.slug}`,
			)
			await expect(page.locator(NC_HEADER).first()).toBeVisible()
			await expect(page.locator(SIDEBAR_TOGGLE).first()).toBeVisible()
			await expect(page.getByText(lobby.text).first()).toBeVisible()
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-kiosk-mode::kiosk-flag-does-not-weaken-server-side-authorization
	test('?kiosk=1 changes no permission check', async ({ browser }) => {
		const secret = await createDashboard(admin, 'Private')

		// CONTROL: the owner reads it, flag or not.
		const owner = await admin.get(`${API}/dashboard/${secret.id}?kiosk=1`)
		expect(owner.status(), await owner.text()).toBe(200)
		expect((await owner.json()).dashboard?.uuid).toBe(secret.uuid)

		// bob, an ordinary user without access: identical refusals.
		const plain = await bob!.api.get(`${API}/dashboard/${secret.id}`)
		const flagged = await bob!.api.get(`${API}/dashboard/${secret.id}?kiosk=1`)
		expect(plain.status()).toBe(404)
		expect(flagged.status()).toBe(plain.status())
		expect(await flagged.text()).toBe(await plain.text())

		const byPath = await bob!.api.get(`${API}/dashboards/by-path/${secret.slug}`)
		const byPathFlagged = await bob!.api.get(
			`${API}/dashboards/by-path/${secret.slug}?kiosk=1`,
		)
		expect(byPathFlagged.status()).toBe(byPath.status())
		expect(await byPathFlagged.text()).toBe(await byPath.text())

		// And in his browser the dashboard never renders, with the flag or without.
		const { context, page } = await loginAs(
			browser,
			bob!.username,
			bob!.password,
		)
		try {
			const route = await page.goto(`${APP}/${secret.slug}`)
			// Wait for the shell's own dashboard read instead of `networkidle`,
			// which never settles on Nextcloud (ADR-074 rule 4). The absence
			// assertions below are only meaningful once that read has answered.
			const dashboardsRead = page.waitForResponse(
				(res) => res.url().includes('/apps/launchpad/api/dashboards'),
				{ timeout: 30_000 },
			)
			const routeFlagged = await page.goto(`${APP}/${secret.slug}?kiosk=1`)
			expect(routeFlagged?.status()).toBe(route?.status())
			await expect(page.locator('.workspace-shell')).toBeVisible({
				timeout: 30_000,
			})
			await dashboardsRead
			await expect(page.getByText(secret.text)).toHaveCount(0)
			await expect(page.getByText(secret.name)).toHaveCount(0)
		} finally {
			await context.close()
		}

		// Nothing was written by the probes.
		const reread = await admin.get(`${API}/dashboard/${secret.id}`)
		expect((await reread.json()).dashboard?.name).toBe(secret.name)
	})

	// @e2e dashboard-kiosk-mode::cursor-hides-on-idle
	test('the cursor hides after 10 seconds without pointer movement and returns on movement', async ({
		browser,
	}) => {
		const a = await createDashboard(alice!.api, 'Cursor')
		const playlist = await createPlaylist(
			alice!.api,
			`Cursor ${stamp()}`,
			[[a, 600]],
			3600,
		)
		const { context, page } = await openPlayer(browser, playlist.token)
		try {
			const box = await onScreen(page).boundingBox()
			const x = box!.x + 10
			const y = box!.y + box!.height / 2
			await page.mouse.move(x, y)
			await page.clock.runFor(2_000)
			expect(
				await cursorAt(page, x, y),
				'CONTROL: the cursor shows while moving',
			).not.toBe('none')

			await page.clock.runFor(10_500)
			await expect.poll(() => cursorAt(page, x, y)).toBe('none')

			await page.mouse.move(x + 20, y)
			await expect.poll(() => cursorAt(page, x + 20, y)).not.toBe('none')
		} finally {
			await context.close()
		}
	})
})

test.describe('REQ-KIOSK-002 playlist management API', () => {
	// @e2e dashboard-kiosk-mode::create-a-playlist
	test('POST creates a playlist with a 64-character URL-safe token, the creator and its public url', async () => {
		const a = await createDashboard(alice!.api, 'A')
		const b = await createDashboard(alice!.api, 'B')
		const name = `Reception screens ${stamp()}`
		const res = await postPlaylist(alice!.api, {
			name,
			entries: [
				{ dashboardUuid: a.uuid, dwellSeconds: 45 },
				{ dashboardUuid: b.uuid, dwellSeconds: 90 },
			],
			refreshSeconds: 300,
		})
		expect(res.status(), await res.text()).toBe(201)
		const body: Playlist = await res.json()

		expect(body.name).toBe(name)
		expect(body.token).toMatch(/^[A-Za-z0-9_-]{64}$/)
		expect(body.createdBy).toBe(alice!.username)
		expect(body.entries).toEqual([
			{ dashboardUuid: a.uuid, dwellSeconds: 45 },
			{ dashboardUuid: b.uuid, dwellSeconds: 90 },
		])
		expect(body.refreshSeconds).toBe(300)
		expect(body.revokedAt).toBeNull()

		// A second playlist gets a different token: it is generated, not derived.
		const other = await createPlaylist(alice!.api, `${name} 2`, [[a, 45]], 300)
		expect(other.token).not.toBe(body.token)

		const listed = await listPlaylists(alice!.api)
		expect(listed.map((p) => p.id)).toContain(body.id)

		// The create response itself carries the computed public url.
		expect(
			body.url,
			'the 201 response carries the computed public url',
		).toBeTruthy()
		expect(String(body.url)).toMatch(
			new RegExp(`/apps/launchpad/kiosk/${body.token}$`),
		)
	})

	// @e2e dashboard-kiosk-mode::playlist-creation-rejects-dashboards-the-creator-may-not-share
	test('a dashboard the creator does not own rejects the whole request with 403', async () => {
		const alicesDash = await createDashboard(alice!.api, 'Owned')
		const bobsDash = await createDashboard(bob!.api, 'Bobs')
		const name = `Bob tries ${stamp()}`

		const refused = await postPlaylist(bob!.api, {
			name,
			entries: [
				{ dashboardUuid: bobsDash.uuid, dwellSeconds: 30 },
				{ dashboardUuid: alicesDash.uuid, dwellSeconds: 30 },
			],
			refreshSeconds: 300,
		})
		expect(refused.status(), await refused.text()).toBe(403)

		// Nothing was created: not for bob, and not anywhere (the admin sees all).
		expect(
			(await listPlaylists(bob!.api)).filter((p) => p.name === name),
		).toHaveLength(0)
		expect(
			(await listPlaylists(admin)).filter((p) => p.name === name),
		).toHaveLength(0)

		// The allowed principal succeeds: bob with only his own dashboard.
		const allowed = await createPlaylist(bob!.api, name, [[bobsDash, 30]], 300)
		expect(allowed.createdBy).toBe(bob!.username)
		expect((await listPlaylists(bob!.api)).map((p) => p.id)).toContain(
			allowed.id,
		)
	})

	// @e2e dashboard-kiosk-mode::dwell-and-refresh-values-are-clamped
	test('dwell is clamped to [10, 86400] and refresh to [30, 86400], and the stored values come back', async () => {
		const a = await createDashboard(alice!.api, 'Clamp')
		const low = await createPlaylist(alice!.api, `Low ${stamp()}`, [[a, 2]], 5)
		expect(low.entries[0].dwellSeconds).toBe(10)
		expect(low.refreshSeconds).toBe(30)

		const high = await createPlaylist(
			alice!.api,
			`High ${stamp()}`,
			[[a, 100_000]],
			999_999,
		)
		expect(high.entries[0].dwellSeconds).toBe(86400)
		expect(high.refreshSeconds).toBe(86400)

		// CONTROL: an in-range value is kept as sent.
		const mid = await createPlaylist(
			alice!.api,
			`Mid ${stamp()}`,
			[[a, 45]],
			120,
		)
		expect(mid.entries[0].dwellSeconds).toBe(45)
		expect(mid.refreshSeconds).toBe(120)

		// What is stored is the clamped value.
		const stored = await listPlaylists(alice!.api)
		const lowStored = stored.find((p) => p.id === low.id)
		expect(lowStored?.entries[0].dwellSeconds).toBe(10)
		expect(lowStored?.refreshSeconds).toBe(30)
	})

	// @e2e dashboard-kiosk-mode::update-re-validates-all-entries
	test('PUT adding a dashboard the owner may not share is 403 and changes nothing', async () => {
		const a = await createDashboard(alice!.api, 'Keep')
		const foreign = await createDashboard(bob!.api, 'Foreign')
		const name = `Update ${stamp()}`
		const playlist = await createPlaylist(alice!.api, name, [[a, 30]], 300)

		const refused = await alice!.api.put(`${PLAYLISTS}/${playlist.id}`, {
			data: {
				name: `${name} changed`,
				entries: [
					{ dashboardUuid: a.uuid, dwellSeconds: 60 },
					{ dashboardUuid: foreign.uuid, dwellSeconds: 30 },
				],
				refreshSeconds: 600,
			},
		})
		expect(refused.status(), await refused.text()).toBe(403)

		const unchanged = (await listPlaylists(alice!.api)).find(
			(p) => p.id === playlist.id,
		)
		expect(unchanged?.name).toBe(name)
		expect(unchanged?.entries).toEqual([
			{ dashboardUuid: a.uuid, dwellSeconds: 30 },
		])
		expect(unchanged?.refreshSeconds).toBe(300)

		// CONTROL: the same owner may update with dashboards she owns.
		const allowed = await alice!.api.put(`${PLAYLISTS}/${playlist.id}`, {
			data: {
				name: `${name} changed`,
				entries: [{ dashboardUuid: a.uuid, dwellSeconds: 60 }],
				refreshSeconds: 600,
			},
		})
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect((await allowed.json()).name).toBe(`${name} changed`)
	})

	// @e2e dashboard-kiosk-mode::list-own-playlists
	test("a user lists exactly her active playlists; an admin lists every user's active ones", async () => {
		// A fresh owner, so "exactly" is about this test's playlists only.
		const carol = await createPrincipal(admin, 'e2e-kiosk-carol')
		try {
			const a = await createDashboard(carol.api, 'List')
			const one = await createPlaylist(
				carol.api,
				`One ${stamp()}`,
				[[a, 30]],
				300,
			)
			const two = await createPlaylist(
				carol.api,
				`Two ${stamp()}`,
				[[a, 30]],
				300,
			)
			const gone = await createPlaylist(
				carol.api,
				`Gone ${stamp()}`,
				[[a, 30]],
				300,
			)
			const revoke = await carol.api.delete(`${PLAYLISTS}/${gone.id}`)
			expect(revoke.status()).toBe(204)

			const bobsDash = await createDashboard(bob!.api, 'Other')
			const bobs = await createPlaylist(
				bob!.api,
				`Bobs ${stamp()}`,
				[[bobsDash, 30]],
				300,
			)

			const own = await carol.api.get(PLAYLISTS)
			expect(own.status()).toBe(200)
			const ownIds = ((await own.json()) as Playlist[]).map((p) => p.id).sort()
			expect(ownIds).toEqual([one.id, two.id].sort())

			const all = await listPlaylists(admin)
			const allIds = all.map((p) => p.id)
			expect(allIds).toEqual(expect.arrayContaining([one.id, two.id, bobs.id]))
			expect(allIds).not.toContain(gone.id)
			expect(all.every((p) => p.revokedAt === null)).toBe(true)
		} finally {
			for (const { api, id } of createdDashboards.filter(
				(d) => d.api === carol.api,
			)) {
				await api.delete(`${API}/dashboard/${id}`)
			}
			await removePrincipal(admin, carol)
		}
	})

	// @e2e dashboard-kiosk-mode::revoke-a-playlist
	test('DELETE soft-revokes for the owner with 204 and is 403 for a non-owner', async () => {
		const a = await createDashboard(alice!.api, 'Revoke')
		const playlist = await createPlaylist(
			alice!.api,
			`Revoke ${stamp()}`,
			[[a, 30]],
			300,
		)

		const refused = await bob!.api.delete(`${PLAYLISTS}/${playlist.id}`)
		expect(refused.status(), await refused.text()).toBe(403)
		expect(
			(await listPlaylists(alice!.api)).map((p) => p.id),
			'the refused DELETE revoked nothing',
		).toContain(playlist.id)

		const revoked = await alice!.api.delete(`${PLAYLISTS}/${playlist.id}`)
		expect(revoked.status(), await revoked.text()).toBe(204)

		// revokedAt is set: the playlist is out of every active listing and no
		// longer addressable, while the stored row itself is pinned by
		// KioskServiceTest::testRevokePlaylistOwnerSoftRevokes.
		expect((await listPlaylists(alice!.api)).map((p) => p.id)).not.toContain(
			playlist.id,
		)
		expect((await listPlaylists(admin)).map((p) => p.id)).not.toContain(
			playlist.id,
		)
		const again = await alice!.api.put(`${PLAYLISTS}/${playlist.id}`, {
			data: {
				name: 'x',
				entries: [{ dashboardUuid: a.uuid, dwellSeconds: 30 }],
			},
		})
		expect(again.status()).toBe(404)
	})
})

test.describe('REQ-KIOSK-003 public playlist render', () => {
	// @e2e dashboard-kiosk-mode::render-a-valid-playlist-token
	test('an anonymous client gets the descriptor and read-only payload, and the screen rotates 45 s / 90 s and loops', async ({
		browser,
	}) => {
		const a = await createDashboard(alice!.api, 'Rot A')
		const b = await createDashboard(alice!.api, 'Rot B')
		const playlist = await createPlaylist(
			alice!.api,
			`Rotation ${stamp()}`,
			[
				[a, 45],
				[b, 90],
			],
			3600,
		)

		const anon = await anonymousApi()
		try {
			const res = await kioskJson(anon, playlist.token)
			expect(res.status(), await res.text()).toBe(200)
			const body = await res.json()
			expect(body.playlist.id).toBe(playlist.id)
			expect(body.playlist.name).toBe(playlist.name)
			expect(body.playlist.refreshSeconds).toBe(3600)
			expect(body.playlist).not.toHaveProperty('createdBy')
			expect(body.entries[0].dashboard.uuid).toBe(a.uuid)
			expect(body.entries[0].dwellSeconds).toBe(45)
			expect(body.entries[0].dashboard).not.toHaveProperty('userId')
			expect(JSON.stringify(body.entries[0].placements)).toContain(a.text)
		} finally {
			await anon.dispose()
		}

		const { context, page } = await openPlayer(browser, playlist.token)
		try {
			await expect(onScreen(page)).toHaveText(a.name)
			await expect(page.locator(PUBLIC_WIDGET_TEXT)).toHaveText(a.text)
			await after(page, 40_000, a)
			await after(page, 10_000, b) // 50 s
			await after(page, 80_000, b) // 130 s
			await after(page, 10_000, a) // 140 s: looped
			await expect(page.locator(SIDEBAR_TOGGLE)).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-kiosk-mode::unknown-or-revoked-token-returns-404
	test('an unknown and a revoked token both answer 404 with the same shape', async () => {
		const a = await createDashboard(alice!.api, 'Dead')
		const playlist = await createPlaylist(
			alice!.api,
			`Dead ${stamp()}`,
			[[a, 30]],
			300,
		)
		const anon = await anonymousApi()
		try {
			// CONTROL: the token works while the playlist is live.
			expect((await kioskJson(anon, playlist.token)).status()).toBe(200)

			const revoke = await alice!.api.delete(`${PLAYLISTS}/${playlist.id}`)
			expect(revoke.status()).toBe(204)

			const unknown = await kioskJson(anon, `Zz${RUN}`.padEnd(64, 'Q'))
			const revoked = await kioskJson(anon, playlist.token)
			expect(unknown.status()).toBe(404)
			expect(revoked.status()).toBe(404)
			expect(revoked.headers()['content-type']).toBe(
				unknown.headers()['content-type'],
			)
			expect(await revoked.json()).toEqual(await unknown.json())
		} finally {
			await anon.dispose()
		}
	})

	// @e2e dashboard-kiosk-mode::entry-whose-dashboard-was-deleted-is-skipped-at-render-time
	test('an entry whose dashboard was deleted is left out and the rest rotate', async ({
		browser,
	}) => {
		const a = await createDashboard(alice!.api, 'Del A')
		const b = await createDashboard(alice!.api, 'Del B')
		const c = await createDashboard(alice!.api, 'Del C')
		const playlist = await createPlaylist(
			alice!.api,
			`Deleted ${stamp()}`,
			[
				[a, 30],
				[b, 30],
				[c, 30],
			],
			3600,
		)
		const anon = await anonymousApi()
		try {
			const before = await (await kioskJson(anon, playlist.token)).json()
			expect(
				before.entries,
				'CONTROL: three entries while B exists',
			).toHaveLength(3)

			const del = await alice!.api.delete(`${API}/dashboard/${b.id}`)
			expect(del.status(), await del.text()).toBeLessThan(300)

			const res = await kioskJson(anon, playlist.token)
			expect(res.status()).toBe(200)
			const body = await res.json()
			expect(body).not.toHaveProperty('error')
			expect(
				body.entries.map(
					(e: { dashboard: { uuid: string } }) => e.dashboard.uuid,
				),
			).toEqual([a.uuid, c.uuid])
		} finally {
			await anon.dispose()
		}

		const { context, page } = await openPlayer(browser, playlist.token)
		try {
			await expect(onScreen(page)).toHaveText(a.name)
			await after(page, 35_000, c) // straight from A to C: no slot for B
			await after(page, 30_000, a)
			await expect(page.getByText(b.name)).toHaveCount(0)
			await expect(page.locator('.kiosk-player__message')).toHaveCount(0)
		} finally {
			await context.close()
		}
	})
})

test.describe('REQ-KIOSK-004 in-place refresh', () => {
	// @e2e dashboard-kiosk-mode::widget-data-refreshes-without-page-reload
	test('after refreshSeconds the widgets show the new data with no navigation or reload', async ({
		browser,
	}) => {
		const a = await createDashboard(alice!.api, 'Refresh')
		const playlist = await createPlaylist(
			alice!.api,
			`Refresh ${stamp()}`,
			[[a, 600]],
			300,
		)
		const { context, page, reads } = await openPlayer(browser, playlist.token)
		try {
			let navigations = 0
			page.on('framenavigated', (frame) => {
				if (frame === page.mainFrame()) {
					navigations += 1
				}
			})
			await page.evaluate((marker) => {
				;(window as unknown as { __kioskMarker: number }).__kioskMarker =
					marker
			}, RUN)
			const before = a.text
			await expect(page.locator(PUBLIC_WIDGET_TEXT)).toHaveText(before)
			const readsBefore = reads.length

			await setWidgetText(alice!.api, a, `refreshed ${stamp()}`)

			// CONTROL: not yet refreshed before the interval.
			await page.clock.runFor(280_000)
			await expect(page.locator(PUBLIC_WIDGET_TEXT)).toHaveText(before)

			await page.clock.runFor(25_000)
			await expect(page.locator(PUBLIC_WIDGET_TEXT)).toHaveText(a.text)
			expect(reads.length).toBeGreaterThan(readsBefore)

			expect(navigations, 'no navigation or reload').toBe(0)
			expect(
				await page.evaluate(
					() =>
						(window as unknown as { __kioskMarker?: number })
							.__kioskMarker,
				),
				'the same document is still loaded',
			).toBe(RUN)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-kiosk-mode::rotation-entries-are-fetched-just-in-time
	test('the next entry is fetched about 5 seconds before the switch and shows that fresh data', async ({
		browser,
	}) => {
		const a = await createDashboard(alice!.api, 'JIT A')
		const b = await createDashboard(alice!.api, 'JIT B')
		const playlist = await createPlaylist(
			alice!.api,
			`JIT ${stamp()}`,
			[
				[a, 30],
				[b, 30],
			],
			3600,
		)
		const { context, page, reads } = await openPlayer(browser, playlist.token)
		try {
			await expect(onScreen(page)).toHaveText(a.name)
			await setWidgetText(alice!.api, b, `fresh for B ${stamp()}`)

			await page.clock.runFor(20_000)
			const earlyReads = reads.length

			// The window around "5 seconds left on A".
			await page.clock.runFor(8_000)
			await expect
				.poll(() => reads.length, {
					message: 'B is fetched in the background before the switch',
				})
				.toBeGreaterThan(earlyReads)

			await page.clock.runFor(5_000)
			await expect(onScreen(page)).toHaveText(b.name)
			await expect(page.locator(PUBLIC_WIDGET_TEXT)).toHaveText(b.text)
			await expect(page.locator('.kiosk-player__message')).toHaveCount(0)
			await expect(page.locator('.loading-icon')).toHaveCount(0)
		} finally {
			await context.close()
		}
	})
})

test.describe('REQ-KIOSK-005 unattended resilience', () => {
	// @e2e dashboard-kiosk-mode::failed-entry-is-skipped-and-retried-next-loop
	test('an entry whose fetch fails is skipped within 10 seconds, silently, and retried next loop', async ({
		browser,
	}) => {
		const a = await createDashboard(alice!.api, 'Fail A')
		const b = await createDashboard(alice!.api, 'Fail B')
		const c = await createDashboard(alice!.api, 'Fail C')
		const playlist = await createPlaylist(
			alice!.api,
			`Fail ${stamp()}`,
			[
				[a, 30],
				[b, 60],
				[c, 30],
			],
			3600,
		)
		let bAttempts = 0
		const { context, page } = await openPlayer(browser, playlist.token, {
			before: async (p) => {
				// Any fetch for B's render payload answers 500.
				await p.route(
					(url) => url.href.includes(b.uuid),
					async (route) => {
						bAttempts += 1
						await route.fulfill({
							status: 500,
							body: 'Internal Server Error',
						})
					},
				)
			},
		})
		try {
			await expect(onScreen(page)).toHaveText(a.name)
			// A ends at 30 s; B gets 10 s to load; by 42 s the screen is on C.
			await after(page, 42_000, c)
			expect(bAttempts, 'B was attempted').toBeGreaterThanOrEqual(1)
			await expect(page.locator('body')).not.toContainText(TECHNICAL)

			// C (30 s) and A (30 s) later, the next loop reaches B again.
			const firstLoop = bAttempts
			await page.clock.runFor(75_000)
			await expect
				.poll(() => bAttempts, { message: 'B is retried next loop' })
				.toBeGreaterThan(firstLoop)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-kiosk-mode::network-loss-retains-last-known-content
	test('offline, the screen keeps its content with a small reconnect indicator, and recovers by itself', async ({
		browser,
	}) => {
		const a = await createDashboard(alice!.api, 'Net A')
		const b = await createDashboard(alice!.api, 'Net B')
		const playlist = await createPlaylist(
			alice!.api,
			`Net ${stamp()}`,
			[
				[a, 120],
				[b, 120],
			],
			30,
		)
		const { context, page, reads } = await openPlayer(browser, playlist.token)
		try {
			await expect(onScreen(page)).toHaveText(a.name)
			await expect(
				page.getByRole('status'),
				'CONTROL: no indicator while online',
			).toHaveCount(0)

			await page.route(`**/kiosk/${playlist.token}`, (route) =>
				route.abort('internetdisconnected'),
			)
			await page.clock.runFor(35_000)
			await expect.poll(() => reads.length).toBeGreaterThan(1)

			await expect(onScreen(page), 'the last content stays').toHaveText(a.name)
			await expect(page.locator(PUBLIC_WIDGET_TEXT)).toHaveText(a.text)
			const indicator = page.getByRole('status')
			await expect(indicator, 'a reconnect indicator shows').toBeVisible()
			const box = await indicator.boundingBox()
			const viewport = page.viewportSize()!
			expect(
				box!.width * box!.height,
				'the indicator is small: under a tenth of the screen',
			).toBeLessThan((viewport.width * viewport.height) / 10)

			await page.unroute(`**/kiosk/${playlist.token}`)
			await page.clock.runFor(35_000)
			await expect(
				page.getByRole('status'),
				'the indicator goes once a poll succeeds',
			).toHaveCount(0)
			// Rotation resumes: A's 120 s run out and B follows.
			await after(page, 60_000, b)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-kiosk-mode::total-failure-shows-a-neutral-placeholder
	test('a cold start during an outage shows a neutral placeholder and recovers once a fetch succeeds', async ({
		browser,
	}) => {
		const a = await createDashboard(alice!.api, 'Cold')
		const playlist = await createPlaylist(
			alice!.api,
			`Cold ${stamp()}`,
			[[a, 60]],
			300,
		)
		let failures = 0
		const failing = async (route: Route) => {
			if (!['xhr', 'fetch'].includes(route.request().resourceType())) {
				await route.continue()
				return
			}
			failures += 1
			await route.fulfill({
				status: 500,
				contentType: 'text/html',
				body: '<h1>Internal Server Error</h1><pre>Fatal error: Uncaught Exception in /var/www/html/lib/private/AppFramework/App.php:183 Stack trace: #0</pre>',
			})
		}
		const { context, page } = await openPlayer(browser, playlist.token, {
			expectDashboard: false,
			before: async (p) => {
				await p.route(`**/kiosk/${playlist.token}`, failing)
			},
		})
		try {
			const placeholder = page.getByRole('status')
			await expect(placeholder).toBeVisible({ timeout: 30_000 })
			await freeze(page)
			await expect(page.getByTestId('kiosk-dashboard')).toHaveCount(0)
			await expect(page.locator('body')).not.toContainText(TECHNICAL)
			await expect(page.locator('body')).not.toContainText(/error/i)
			await expect(
				page.locator('#body-login, .guest-box, .error-page'),
			).toHaveCount(0)

			// It keeps polling.
			const firstFailures = failures
			await page.clock.runFor(35_000)
			await expect.poll(() => failures).toBeGreaterThan(firstFailures)

			// And recovers by itself once the server answers again.
			await page.unroute(`**/kiosk/${playlist.token}`)
			await page.clock.runFor(35_000)
			await expect(onScreen(page)).toHaveText(a.name, { timeout: 15_000 })
			await expect(page.locator(PUBLIC_WIDGET_TEXT)).toHaveText(a.text)
		} finally {
			await context.close()
		}
	})
})

test.describe('REQ-KIOSKUI-001 the administrator manages kiosk playlists', () => {
	let nieuws: Dashboard
	let kantine: Dashboard

	test.beforeAll(async () => {
		nieuws = await createDashboard(admin, 'Nieuws')
		kantine = await createDashboard(admin, 'Kantine')
	})

	/**
	 * Open Beheer ▸ Kiosk and the new-playlist form.
	 *
	 * @param page the admin's page.
	 * @return the form dialog.
	 */
	async function openNewPlaylistForm(page: Page): Promise<Locator> {
		await page.goto(SETTINGS_URL)
		const tab = adminTab(page, 'kiosk')
		await expect(tab).toBeVisible({ timeout: 30_000 })
		await tab.click()
		await expect(page.getByTestId('kiosk-tab')).toBeVisible()
		await page.getByTestId('kiosk-new').click()
		const dialog = page.getByRole('dialog', { name: 'New playlist' })
		await expect(dialog).toBeVisible()
		return dialog
	}

	/**
	 * Choose a dashboard in one row of the form.
	 *
	 * @param page the admin's page.
	 * @param row the entry row.
	 * @param dashboard the dashboard to pick.
	 * @return nothing.
	 */
	async function chooseDashboard(
		page: Page,
		row: Locator,
		dashboard: Dashboard,
	): Promise<void> {
		await row.locator('input.vs__search').click()
		await row.locator('input.vs__search').fill(dashboard.name)
		// NcSelect splits option labels across spans, so the filtered list is
		// matched by position; the stamped name leaves exactly one option.
		const options = page.locator('.vs__dropdown-option')
		await expect(options).toHaveCount(1)
		await options.first().click()
		await expect(row.locator('.vs__selected')).toContainText(dashboard.name)
	}

	/**
	 * The playlist name field (NcTextField puts the attribute on the input).
	 *
	 * @param dialog the form dialog.
	 * @return the input.
	 */
	function nameField(dialog: Locator): Locator {
		return dialog
			.locator(
				'input[data-testid="kiosk-name"], [data-testid="kiosk-name"] input',
			)
			.first()
	}

	// @e2e dashboard-kiosk-mode::create-a-lobby-playlist
	test('Lobby with Nieuws 30 s and Kantine 20 s is listed with a copyable link', async ({
		page,
	}) => {
		await page.addInitScript(() => {
			const copied: string[] = []
			;(window as unknown as { __copied: string[] }).__copied = copied
			Object.defineProperty(navigator, 'clipboard', {
				configurable: true,
				value: {
					writeText: async (text: string) => {
						copied.push(text)
					},
				},
			})
		})
		const name = `Lobby ${stamp()}`
		const dialog = await openNewPlaylistForm(page)
		await nameField(dialog).fill(name)

		const rows = dialog.getByTestId('kiosk-entry')
		await chooseDashboard(page, rows.nth(0), nieuws)
		await rows.nth(0).getByTestId('kiosk-dwell').fill('30')
		await dialog.getByTestId('kiosk-add-entry').click()
		await expect(rows).toHaveCount(2)
		await chooseDashboard(page, rows.nth(1), kantine)
		await rows.nth(1).getByTestId('kiosk-dwell').fill('20')
		await dialog.getByTestId('kiosk-refresh').fill('300')
		await expect(dialog.getByTestId('kiosk-problem')).toHaveCount(0)

		const posted = page.waitForResponse(
			(r) =>
				r.url().endsWith('/api/kiosk/playlists')
				&& r.request().method() === 'POST',
		)
		await dialog.getByTestId('kiosk-save').click()
		const response = await posted
		expect(response.status()).toBe(201)
		const created: Playlist = await response.json()
		createdPlaylists.push(created.id)
		expect(created.entries).toEqual([
			{ dashboardUuid: nieuws.uuid, dwellSeconds: 30 },
			{ dashboardUuid: kantine.uuid, dwellSeconds: 20 },
		])
		expect(created.refreshSeconds).toBe(300)
		await expect(dialog).toBeHidden()

		const row = page.getByTestId('kiosk-row').filter({ hasText: name })
		await expect(row).toHaveCount(1)
		await expect(row.locator('td').nth(1), 'two dashboards').toHaveText('2')
		const origin = new URL(page.url()).origin
		const link = `${origin}${APP}/kiosk/${created.token}`
		await expect(row.locator('.kiosk-tab__link')).toHaveText(link)

		await row.getByTestId('kiosk-copy').click()
		await expect
			.poll(() =>
				page.evaluate(
					() => (window as unknown as { __copied: string[] }).__copied,
				),
			)
			.toEqual([link])
		await expect(page.locator('.kiosk-tab__error')).toHaveCount(0)
	})

	// @e2e dashboard-kiosk-mode::dwell-below-the-minimum
	test('a dwell of 5 seconds shows the 10-second minimum and the form does not save', async ({
		page,
	}) => {
		const posts: string[] = []
		page.on('request', (req) => {
			if (
				req.url().includes('/api/kiosk/playlists')
				&& req.method() === 'POST'
			) {
				posts.push(req.url())
			}
		})
		const name = `Too short ${stamp()}`
		const dialog = await openNewPlaylistForm(page)
		await nameField(dialog).fill(name)
		const row = dialog.getByTestId('kiosk-entry').first()
		await chooseDashboard(page, row, nieuws)
		const dwell = row.getByTestId('kiosk-dwell')
		await expect(dwell).toHaveAttribute('min', '10')

		// CONTROL: with a valid dwell the form is saveable, so the refusal below
		// is about the dwell and nothing else.
		await dwell.fill('10')
		await expect(dialog.getByTestId('kiosk-problem')).toHaveCount(0)
		await expect(dialog.getByTestId('kiosk-save')).toBeEnabled()

		await dwell.fill('5')
		await expect(dialog.getByTestId('kiosk-problem')).toContainText(
			'at least 10 seconds',
		)
		await expect(dialog.getByTestId('kiosk-save')).toBeDisabled()
		await dialog.getByTestId('kiosk-save').click({ force: true })
		await expect(dialog).toBeVisible()
		expect(posts, 'nothing was sent').toHaveLength(0)
		expect(
			(await listPlaylists(admin)).filter((p) => p.name === name),
		).toHaveLength(0)
	})
})

test.describe('REQ-KIOSKUI-002 the kiosk link plays without a login', () => {
	let nieuws: Dashboard
	let kantine: Dashboard

	test.beforeAll(async () => {
		nieuws = await createDashboard(admin, 'Nieuws')
		kantine = await createDashboard(admin, 'Kantine')
	})

	// @e2e dashboard-kiosk-mode::rotation
	test('the screen shows Nieuws for 30 s, then Kantine for 20 s, then Nieuws again', async ({
		browser,
	}) => {
		const lobby = await createPlaylist(
			admin,
			`Lobby ${stamp()}`,
			[
				[nieuws, 30],
				[kantine, 20],
			],
			300,
		)
		const { context, page, response } = await openPlayer(browser, lobby.token)
		try {
			expect(response?.status()).toBe(200)
			expect(page.url()).not.toContain('/login')
			await expect(onScreen(page)).toHaveText(nieuws.name)
			await after(page, 25_000, nieuws)
			await after(page, 10_000, kantine) // 35 s
			await after(page, 10_000, kantine) // 45 s
			await after(page, 10_000, nieuws) // 55 s
			// No controls on the screen.
			await expect(page.getByRole('button')).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-kiosk-mode::revoked-link
	test('after Ruben revokes the playlist, the screen says it is no longer available at its next read', async ({
		browser,
		page,
	}) => {
		const name = `Lobby revoked ${stamp()}`
		const lobby = await createPlaylist(
			admin,
			name,
			[
				[nieuws, 30],
				[kantine, 20],
			],
			30,
		)
		const screen = await openPlayer(browser, lobby.token)
		try {
			await expect(onScreen(screen.page)).toHaveText(nieuws.name)
			await expect(screen.page.getByTestId('kiosk-unavailable')).toHaveCount(0)

			// Ruben revokes it in Beheer ▸ Kiosk.
			await page.goto(SETTINGS_URL)
			await adminTab(page, 'kiosk').click()
			const row = page.getByTestId('kiosk-row').filter({ hasText: name })
			await expect(row).toHaveCount(1, { timeout: 30_000 })
			await row.getByTestId('kiosk-revoke').click()
			const deleted = page.waitForResponse(
				(r) =>
					r.url().includes(`/api/kiosk/playlists/${lobby.id}`)
					&& r.request().method() === 'DELETE',
			)
			await page
				.getByRole('dialog', { name: 'Revoke this playlist?' })
				.getByRole('button', { name: 'Revoke' })
				.click()
			expect((await deleted).status()).toBe(204)
			await expect(row).toHaveCount(0)

			// The screen re-reads the playlist on its refresh interval.
			await screen.page.clock.runFor(31_000)
			await expect(screen.page.getByTestId('kiosk-unavailable')).toHaveText(
				'This playlist is no longer available.',
			)
			await expect(screen.page.getByTestId('kiosk-dashboard')).toHaveCount(0)
		} finally {
			await screen.context.close()
		}
	})

	// @e2e dashboard-kiosk-mode::network-blip
	test('a minute without connection keeps the last playlist on screen and the screen keeps trying', async ({
		browser,
	}) => {
		const lobby = await createPlaylist(
			admin,
			`Lobby blip ${stamp()}`,
			[
				[nieuws, 30],
				[kantine, 20],
			],
			30,
		)
		const { context, page, reads } = await openPlayer(browser, lobby.token)
		try {
			await expect(onScreen(page)).toHaveText(nieuws.name)
			let aborted = 0
			await page.route(`**/kiosk/${lobby.token}`, async (route) => {
				aborted += 1
				await route.abort('internetdisconnected')
			})

			// The refresh interval passes while offline.
			await page.clock.runFor(31_000)
			await expect.poll(() => aborted).toBeGreaterThanOrEqual(1)
			await expect(page.getByTestId('kiosk-dashboard')).toBeVisible()
			await expect(onScreen(page)).toHaveText(
				new RegExp(`${nieuws.name}|${kantine.name}`),
			)
			await expect(page.getByTestId('kiosk-unavailable')).toHaveCount(0)

			// And it tries again.
			await page.clock.runFor(31_000)
			await expect.poll(() => aborted).toBeGreaterThanOrEqual(2)
			await expect(page.getByTestId('kiosk-dashboard')).toBeVisible()

			// The connection comes back after the minute: the next try succeeds.
			await page.unroute(`**/kiosk/${lobby.token}`)
			const readsBefore = reads.length
			const answered = page.waitForResponse(
				(r) =>
					isPlaylistRead(r.request(), lobby.token) && r.status() === 200,
			)
			await page.clock.runFor(31_000)
			await answered
			expect(reads.length).toBeGreaterThan(readsBefore)
			await expect(page.getByTestId('kiosk-dashboard')).toBeVisible()
		} finally {
			await context.close()
		}
	})
})
