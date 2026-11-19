/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The quick-search bar as a placed `search` WIDGET (tile-quick-search
 * REQ-QSEARCH-001 no-widget case, REQ-QSEARCH-004..008) and the admin's search
 * shortcuts (REQ-SPX-001..003).
 *
 * `tile-quick-search.spec.ts` covers the keyboard and filtering contract of a
 * single bar. This file covers everything that depends on HOW MANY bars a
 * dashboard carries, on how each one is CONFIGURED, and on the two admin
 * settings that reach the page through initial state: the
 * `quicksearch_fallback_target` setting and the search shortcut list. Both are
 * read once, at page load (`PageController` → `InitialStateBuilder` →
 * `main.js` `app.provide`), so every test that flips one does so BEFORE
 * `page.goto`.
 *
 * WHAT THE FALLBACKS ACTUALLY DO, read from `useTileSearchHost.js`
 * ===============================================================
 * `performFallback()` is the only place a no-match Enter has an effect:
 *
 *   - web-search    → `window.open(url, '_blank', 'noopener,noreferrer')`
 *   - unified-search → `window.dispatchEvent(new CustomEvent(
 *                        'nextcloud:unified-search.search',
 *                        { detail: { query } }))` and nothing else
 *   - none          → nothing; `RuntimeShellSearch.vue` renders the
 *                     `quick-search-empty` text and the aria-live status
 *
 * A shortcut hit (REQ-SPX-002) goes through the same `window.open` path.
 * So `instrument()` below replaces `window.open` with a recorder (no real host
 * is ever contacted — the templates point at hosts nobody should be hitting
 * from CI) and listens for the unified-search event. Both are installed with
 * `addInitScript`, so they exist before the app boots.
 *
 * FIXTURE
 * =======
 * `beforeAll` seeds two dashboards, stamped per run:
 *   - MAIN: three custom tiles and ONE `search` widget (`searchId`) with empty
 *     content. Tests that need a second bar, an override, a clock or an
 *     nc-widget add it through the API and remove it in `finally`.
 *   - BARE: one tile and NO search widget (REQ-QSEARCH-001/006 no-widget
 *     cases). Activated only inside its own describe block.
 * The admin fallback setting, the shortcut list and `allowUserDashboards` are
 * read first and restored in `afterAll`.
 *
 * @spec openspec/specs/tile-quick-search/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { closeSidebar } from './fixtures/widget-flow.ts'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { enterEditMode, openDashboardSwitcher } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const APP_URL = '/index.php/apps/launchpad'
const API = '/index.php/apps/launchpad/api'
const SETTINGS = `${API}/admin/settings`
const SHORTCUTS = `${API}/admin/search-shortcuts`
const ADMIN_PAGE = '/index.php/settings/admin/launchpad'

// Stable hooks shipped by RuntimeShellSearch.vue.
const INPUT = '[data-test="quick-search-input"]'
const OPTION = '[data-test="quick-search-option"]'
const EMPTY = '[data-test="quick-search-empty"]'
const STATUS = '[data-test="quick-search-status"]'
// Views.vue: every placement's cell, and the class it binds from the
// tileSearch store's `isDimmed` (exempting `widgetId === 'search'`).
const GRID_ITEM = '.launchpad-grid-item'
const DIMMED_CLASS = 'launchpad-grid-item--dimmed'
// RuntimeShellSearch.vue `placeholderText()` — the built-in text.
const DEFAULT_PLACEHOLDER = 'Search tiles… (/ or Ctrl+K)'

const STAMP = `${Date.now()}`
const MAIN_NAME = `E2E QuickSearch widget ${STAMP}`
const BARE_NAME = `E2E QuickSearch bare ${STAMP}`
const TILES = [
	`Zaaksysteem ${STAMP}`,
	`Verlof aanvragen ${STAMP}`,
	`Overzicht verlof ${STAMP}`,
]
const BARE_TILE = `Bare tile ${STAMP}`

/*
 * A query no label on MAIN can match, not even as a subsequence: no tile
 * title, nor the search widget's own label "Search", contains a `q`.
 */
const NO_MATCH = `qqxj${STAMP}`

/* Fallback / shortcut templates. Never opened: `window.open` is recorded. */
const ADMIN_TEMPLATE = 'https://admin-fallback.example.org/search?q={query}'
const WIDGET_TEMPLATE = 'https://widget-override.example.org/find?term={query}'
const TOPDESK = {
	prefix: '!t',
	name: 'TOPdesk',
	urlTemplate: 'https://topdesk.gemeente.nl/tas/secure/search?q={query}',
}
const ZAAKSYSTEEM = {
	prefix: '!z',
	name: 'Zaaksysteem',
	urlTemplate: 'https://zaken.gemeente.nl/zoeken?q={query}',
}

interface Seeded {
	id: number
	uuid: string
}

interface Placement {
	id: number
	widgetId: string
	tileTitle?: string
	content?: unknown
}

let main: Seeded
let bare: Seeded
let searchId: number
let priorAllowUserDash = true
let priorFallback = 'none'
let priorShortcuts: unknown[] = []

function basic(user: string, pass: string): string {
	return `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`
}

/*
 * Unambiguously the admin: an explicit empty jar (an explicit `undefined`
 * would fall back to the project's admin session cookie) plus basic auth.
 * Same construction as tile-quick-search.spec.ts.
 */
async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: basic(ADMIN.user, ADMIN.pass),
		},
	})
}

/** Run `fn` with a fresh admin API context, always disposing it. */
async function withApi<T>(fn: (api: APIRequestContext) => Promise<T>): Promise<T> {
	const api = await adminApi()
	try {
		return await fn(api)
	} finally {
		await api.dispose()
	}
}

async function createDashboard(
	api: APIRequestContext,
	name: string,
): Promise<Seeded> {
	const created = await api.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const id = Number(dash.id)
	expect(id, `no dashboard id: ${JSON.stringify(body)}`).toBeTruthy()
	return { id, uuid: String(dash.uuid) }
}

async function addTile(
	api: APIRequestContext,
	dashboardId: number,
	title: string,
	gridX: number,
	gridY: number,
): Promise<void> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/tile`, {
		data: {
			title,
			linkType: 'url',
			linkValue: `https://example.invalid/${encodeURIComponent(title)}`,
			gridX,
			gridY,
			gridWidth: 3,
			gridHeight: 2,
		},
	})
	expect(
		res.status(),
		`seeding tile "${title}": ${await res.text()}`,
	).toBeLessThan(300)
}

/** Add a placement of any widget type to MAIN; returns its id. */
async function addPlacement(
	api: APIRequestContext,
	widgetId: string,
	content: Record<string, unknown>,
	grid: { x: number; y: number; w?: number; h?: number },
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${main.id}/widgets`, {
		data: {
			widgetId,
			gridX: grid.x,
			gridY: grid.y,
			gridWidth: grid.w ?? 6,
			gridHeight: grid.h ?? 2,
			content,
		},
	})
	expect(res.status(), `adding a ${widgetId} placement: ${await res.text()}`).toBe(
		201,
	)
	const body = await res.json()
	const id = Number(body.id ?? body.placement?.id ?? body.data?.id)
	expect(id, `no placement id: ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

async function removePlacement(api: APIRequestContext, id: number): Promise<void> {
	await api.delete(`${API}/widgets/${id}`)
}

async function setContent(
	api: APIRequestContext,
	id: number,
	content: Record<string, unknown>,
): Promise<void> {
	const res = await api.put(`${API}/widgets/${id}`, { data: { content } })
	expect(
		res.status(),
		`updating placement ${id}: ${await res.text()}`,
	).toBeLessThan(300)
}

/** MAIN's placements, re-read from the server (`GET /api/dashboard/{id}`). */
async function placements(api: APIRequestContext): Promise<Placement[]> {
	const res = await api.get(`${API}/dashboard/${main.id}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.placements ?? body.data?.placements ?? []
}

function contentOf(p: Placement | undefined): Record<string, unknown> {
	if (!p || p.content === null || p.content === undefined) {
		return {}
	}
	if (typeof p.content === 'string') {
		return p.content === '' ? {} : JSON.parse(p.content)
	}
	return p.content as Record<string, unknown>
}

async function setAdminFallback(
	api: APIRequestContext,
	value: string,
): Promise<void> {
	const res = await api.put(SETTINGS, {
		data: { quicksearchFallbackTarget: value },
	})
	expect(
		res.status(),
		`setting the admin fallback to ${value}: ${await res.text()}`,
	).toBe(200)
}

async function readAdminFallback(api: APIRequestContext): Promise<string> {
	const res = await api.get(SETTINGS)
	expect(res.status(), await res.text()).toBe(200)
	return String((await res.json()).quicksearchFallbackTarget)
}

async function setShortcuts(api: APIRequestContext, list: unknown[]): Promise<void> {
	const res = await api.put(SHORTCUTS, { data: { shortcuts: list } })
	expect(res.status(), `saving shortcuts: ${await res.text()}`).toBe(200)
}

async function readShortcuts(
	api: APIRequestContext,
): Promise<Array<{ prefix: string; name: string; urlTemplate: string }>> {
	const res = await api.get(SHORTCUTS)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).shortcuts ?? []
}

/*
 * Both activation mechanisms — the legacy `is_active` column and the per-user
 * UUID preference the shell actually resolves through. See
 * tile-quick-search.spec.ts for the measured reason both are needed.
 */
async function activate(api: APIRequestContext, dash: Seeded): Promise<void> {
	const a = await api.post(`${API}/dashboard/${dash.id}/activate`)
	expect(a.status(), await a.text()).toBeLessThan(300)
	const b = await api.post(`${API}/dashboards/active`, {
		data: { uuid: dash.uuid },
	})
	expect(b.status(), await b.text()).toBeLessThan(300)
}

test.beforeAll(async () => {
	await withApi(async (api) => {
		const before = await api.get(SETTINGS)
		expect(before.status(), await before.text()).toBe(200)
		const settings = await before.json()
		priorAllowUserDash = settings.allowUserDashboards === true
		priorFallback = String(settings.quicksearchFallbackTarget ?? 'none')
		priorShortcuts = await readShortcuts(api)

		const enable = await api.put(SETTINGS, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
		// Start every test from "no shortcuts, no fallback"; each test that
		// needs either sets it.
		await setShortcuts(api, [])
		await setAdminFallback(api, 'none')

		main = await createDashboard(api, MAIN_NAME)
		for (const [i, title] of TILES.entries()) {
			await addTile(
				api,
				main.id,
				title,
				(i % 2) * 3,
				Math.floor(i / 2) * 2 + 2,
			)
		}
		searchId = await addPlacement(
			api,
			'search',
			{ placeholder: '', fallbackTarget: '' },
			{ x: 0, y: 0 },
		)

		bare = await createDashboard(api, BARE_NAME)
		await addTile(api, bare.id, BARE_TILE, 0, 0)

		await activate(api, main)
	})
})

test.afterAll(async () => {
	await withApi(async (api) => {
		if (main?.id) {
			await api.delete(`${API}/dashboard/${main.id}`)
		}
		if (bare?.id) {
			await api.delete(`${API}/dashboard/${bare.id}`)
		}
		await api.put(SETTINGS, {
			data: { quicksearchFallbackTarget: priorFallback },
		})
		await api.put(SHORTCUTS, { data: { shortcuts: priorShortcuts } })
		if (priorAllowUserDash === false) {
			await api.put(SETTINGS, { data: { allowUserDash: false } })
		}
	})
})

/*
 * Record every `window.open` and every unified-search hand-off instead of
 * letting either leave the page. Installed before the app boots.
 */
async function instrument(page: Page): Promise<void> {
	await page.addInitScript(() => {
		const w = window as unknown as {
			__qsOpened: Array<{ url: string; target: string }>
			__qsUnified: unknown[]
		}
		w.__qsOpened = []
		w.__qsUnified = []
		window.open = ((url?: string | URL, target?: string) => {
			w.__qsOpened.push({
				url: String(url ?? ''),
				target: String(target ?? ''),
			})
			return null
		}) as typeof window.open
		window.addEventListener('nextcloud:unified-search.search', (event) => {
			w.__qsUnified.push((event as CustomEvent).detail?.query ?? null)
		})
	})
}

async function opened(page: Page): Promise<Array<{ url: string; target: string }>> {
	return page.evaluate(
		() =>
			(
				window as unknown as {
					__qsOpened: Array<{ url: string; target: string }>
				}
			).__qsOpened,
	)
}

async function unified(page: Page): Promise<unknown[]> {
	return page.evaluate(
		() => (window as unknown as { __qsUnified: unknown[] }).__qsUnified,
	)
}

/**
 * Open the workspace and wait until it shows the dashboard this file seeded
 * (matched by the per-run stamp, so a wrong active dashboard fails here) and
 * exactly `searchBars` search inputs.
 */
async function openWorkspace(
	page: Page,
	stampText: string,
	searchBars: number,
): Promise<void> {
	await page.goto(APP_URL)
	await expect(
		page.locator(GRID_ITEM).filter({ hasText: stampText }).first(),
		`the active dashboard must be the one this file seeded (${stampText})`,
	).toBeVisible({ timeout: 30_000 })
	await expect(
		page.locator(INPUT),
		`the dashboard must carry exactly ${searchBars} search bar(s)`,
	).toHaveCount(searchBars, { timeout: 15_000 })
}

/** The grid cell of one placement. */
function cell(page: Page, placementId: number): Locator {
	return page.locator(`${GRID_ITEM}[data-placement-id="${placementId}"]`)
}

/** The search input inside one search placement. */
function inputOf(page: Page, placementId: number): Locator {
	return cell(page, placementId).locator(INPUT)
}

/** Grid cells that are tiles (i.e. carry no search input). */
function tileCells(page: Page): Locator {
	return page.locator(GRID_ITEM).filter({ hasNot: page.locator(INPUT) })
}

async function isDimmed(loc: Locator): Promise<boolean> {
	return loc.evaluate((el, cls) => el.classList.contains(cls), DIMMED_CLASS)
}

/* Focus the grid container (WorkspaceApp.vue, tabindex="-1"): in-page and not a typing target. */
async function blurToGrid(page: Page): Promise<void> {
	await page.locator('#launchpad-main-content').focus()
}

async function optionLabels(page: Page): Promise<string[]> {
	return (await page.locator(OPTION).allInnerTexts()).map((t) => t.trim())
}

/**
 * Record, for every `/` and Ctrl+K keydown that reaches `window`, whether it
 * was default-prevented, and count every `focusin` from now on. Registered
 * after the app's own window listener, so it observes the app's decision.
 */
async function recordKeys(page: Page): Promise<void> {
	await page.evaluate(() => {
		const w = window as unknown as {
			__qsKeys: Array<{ key: string; prevented: boolean }>
			__qsFocusins: number
		}
		w.__qsKeys = []
		w.__qsFocusins = 0
		window.addEventListener('keydown', (e) => {
			if (
				e.key === '/'
				|| (e.key.toLowerCase() === 'k' && (e.ctrlKey || e.metaKey))
			) {
				w.__qsKeys.push({ key: e.key, prevented: e.defaultPrevented })
			}
		})
		document.addEventListener('focusin', () => {
			w.__qsFocusins += 1
		})
	})
}

async function recordedKeys(page: Page): Promise<{
	keys: Array<{ key: string; prevented: boolean }>
	focusins: number
}> {
	return page.evaluate(() => {
		const w = window as unknown as {
			__qsKeys: Array<{ key: string; prevented: boolean }>
			__qsFocusins: number
		}
		return { keys: w.__qsKeys, focusins: w.__qsFocusins }
	})
}

/** Press `/` then Ctrl+K from the grid and prove neither did anything. */
async function assertShortcutsInert(page: Page): Promise<void> {
	await blurToGrid(page)
	const activeBefore = await page.evaluate(() => document.activeElement?.id ?? '')
	expect(activeBefore, 'CONTROL: focus must start on the grid container').toBe(
		'launchpad-main-content',
	)
	await recordKeys(page)

	await page.keyboard.press('/')
	await page.keyboard.press('Control+k')

	const { keys, focusins } = await recordedKeys(page)
	expect(
		keys.map((k) => k.key.toLowerCase()),
		'CONTROL: both keypresses must have reached the page, or "not prevented" below is vacuous',
	).toEqual(['/', 'k'])
	expect(
		keys.filter((k) => k.prevented),
		'with no search widget the shortcuts must not be intercepted (no preventDefault)',
	).toHaveLength(0)
	expect(focusins, 'no element may receive focus as a result of / or Ctrl+K').toBe(
		0,
	)
	expect(
		await page.evaluate(() => document.activeElement?.id ?? ''),
		'focus must still be where it was',
	).toBe(activeBefore)
}

/** Open the edit dialog of one `search` placement through its cog. */
async function openSearchWidgetEditor(
	page: Page,
	placementId: number,
): Promise<Locator> {
	await enterEditMode(page)
	await openCog(page, placementId)
	await page.locator('[data-testid="cn-widget-edit-cog-edit"]').first().click()
	// CnAddWidgetModal in edit mode; the SearchWidgetForm root identifies it.
	const dialog = page
		.getByRole('dialog')
		.filter({ has: page.locator('.search-widget-form') })
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/* WidgetWrapper.vue → CnWidgetEditCog (NcActions, data-testid on its root). */
async function openCog(page: Page, placementId: number): Promise<void> {
	const cog = cell(page, placementId).locator('[data-testid="cn-widget-edit-cog"]')
	await expect(cog.first(), 'the widget cog must show in edit mode').toBeVisible({
		timeout: 10_000,
	})
	const trigger = cog.locator('button').first()
	if ((await trigger.count()) > 0) {
		await trigger.click()
	} else {
		await cog.first().click()
	}
}

/*
 * Pick a mode in SearchWidgetForm's NcSelect, by POSITION in
 * `fallbackModeOptions`: 0 inherit, 1 none, 2 unified-search, 3 web-search.
 * NcSelect splits option text across spans, so text matching is unreliable.
 */
async function chooseFallbackMode(
	page: Page,
	dialog: Locator,
	index: number,
): Promise<void> {
	await dialog.locator('.search-widget-form .vs__dropdown-toggle').first().click()
	const options = page.locator('.vs__dropdown-option')
	await expect(options, 'the fallback select offers four modes').toHaveCount(4)
	await options.nth(index).click()
}

// ─────────────────────────────────────────────────────────────────────────────
// A dashboard with no search widget
// ─────────────────────────────────────────────────────────────────────────────

test.describe('tile quick-search — no search widget placed', () => {
	test.beforeAll(async () => {
		await withApi((api) => activate(api, bare))
	})
	test.afterAll(async () => {
		await withApi((api) => activate(api, main))
	})

	// @e2e tile-quick-search::no-search-widget-placed-renders-no-bar
	test('REQ-QSEARCH-001: a dashboard with no search placement renders no bar anywhere', async ({
		page,
	}) => {
		// openWorkspace waits for BARE's own tile first: the "0 bars" below is
		// measured on a dashboard that has rendered, not on an empty page.
		await openWorkspace(page, BARE_TILE, 0)

		await expect(
			page.locator(INPUT),
			'no quick-search input anywhere',
		).toHaveCount(0)
		await expect(
			page.locator('.runtime-shell-search'),
			'no quick-search bar component anywhere, including as chrome above the grid',
		).toHaveCount(0)
		await expect(
			page.locator('[role="search"]').filter({ has: page.locator(INPUT) }),
		).toHaveCount(0)
		await expect(
			page.locator('[data-widget-id="search"]'),
			'and no search placement in the grid',
		).toHaveCount(0)
	})

	// @e2e tile-quick-search::no-search-widget-placed
	test('REQ-QSEARCH-006: / and Ctrl+K focus nothing and are not intercepted', async ({
		page,
	}) => {
		await openWorkspace(page, BARE_TILE, 0)
		await assertShortcutsInert(page)
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-QSEARCH-004 — no-match fallback
// ─────────────────────────────────────────────────────────────────────────────

test.describe('tile quick-search — no-match fallback (REQ-QSEARCH-004)', () => {
	test.afterEach(async () => {
		await withApi(async (api) => {
			await setAdminFallback(api, 'none')
			await setContent(api, searchId, { placeholder: '', fallbackTarget: '' })
		})
	})

	// @e2e tile-quick-search::unified-search-fallback
	test('unified-search hands the query to the unified-search event and stays on the dashboard', async ({
		page,
	}) => {
		await withApi((api) => setAdminFallback(api, 'unified-search'))
		await instrument(page)
		await openWorkspace(page, TILES[0], 1)
		const urlBefore = page.url()

		const input = page.locator(INPUT)
		await input.fill(NO_MATCH)
		await expect(
			page.locator(OPTION),
			'CONTROL: the query must match nothing',
		).toHaveCount(0)
		await input.press('Enter')

		await expect
			.poll(() => unified(page), {
				message:
					'the query must be handed to unified search (nextcloud:unified-search.search)',
			})
			.toEqual([NO_MATCH])
		expect(
			await opened(page),
			'unified-search must not open a tab',
		).toHaveLength(0)
		expect(page.url(), 'the dashboard must not navigate away on its own').toBe(
			urlBefore,
		)
		await expect(input, 'the bar is still on screen').toBeVisible()
	})

	// @e2e tile-quick-search::web-search-fallback
	test('a web-search template opens with the URL-encoded query substituted', async ({
		page,
	}) => {
		// Resolved from the widget's own override, with the admin left at
		// `none`, so only the widget's template can be the source.
		await withApi((api) =>
			setContent(api, searchId, {
				placeholder: '',
				fallbackTarget: WIDGET_TEMPLATE,
			}),
		)
		await instrument(page)
		await openWorkspace(page, TILES[0], 1)

		// Characters that need encoding: space, ampersand, slash, a non-ASCII.
		const query = `${NO_MATCH} a&b/ü`
		const input = page.locator(INPUT)
		await input.fill(query)
		await expect(
			page.locator(OPTION),
			'CONTROL: the query must match nothing',
		).toHaveCount(0)
		await input.press('Enter')

		await expect
			.poll(() => opened(page), {
				message: 'Enter must open the web-search template',
			})
			.toHaveLength(1)
		const [hit] = await opened(page)
		expect(hit.url).toBe(
			WIDGET_TEMPLATE.replace('{query}', encodeURIComponent(query)),
		)
		expect(
			hit.url,
			'the placeholder must be substituted, not left in',
		).not.toContain('{query}')
		expect(hit.target).toBe('_blank')
		expect(await unified(page)).toHaveLength(0)
	})

	// @e2e tile-quick-search::no-fallback-configured
	test('none takes no navigation action and shows an accessible no-results message', async ({
		page,
	}) => {
		await instrument(page)
		await openWorkspace(page, TILES[0], 1)
		const urlBefore = page.url()
		expect(
			await page.evaluate(() =>
				Array.isArray(
					(window as unknown as { __qsOpened: unknown }).__qsOpened,
				),
			),
			'CONTROL: the window.open recorder must be installed',
		).toBe(true)

		const input = page.locator(INPUT)
		await input.fill(NO_MATCH)
		await input.press('Enter')

		const message = `No matching tiles for "${NO_MATCH}"`
		await expect(page.locator(EMPTY), 'a visible no-results message').toHaveText(
			message,
		)
		// The visible one is aria-hidden; the announcement is the live region.
		const status = page.locator(STATUS)
		await expect(status).toHaveAttribute('aria-live', 'polite')
		await expect(status, 'the no-results message must be announced').toHaveText(
			message,
		)

		expect(await opened(page), 'no tab may open').toHaveLength(0)
		expect(
			await unified(page),
			'nothing may be handed to unified search',
		).toHaveLength(0)
		expect(page.url(), 'no navigation').toBe(urlBefore)
	})

	// @e2e tile-quick-search::fallback-template-validation
	test('the admin setting refuses a non-https template or one without {query}, and stores nothing', async () => {
		await withApi(async (api) => {
			await setAdminFallback(api, 'none')

			for (const bad of [
				'http://intranet.example.org/zoek?q={query}',
				'https://intranet.example.org/zoek',
			]) {
				const res = await api.put(SETTINGS, {
					data: { quicksearchFallbackTarget: bad },
				})
				expect(res.status(), `"${bad}" must be refused at save time`).toBe(
					400,
				)
				expect(
					await res.text(),
					'the refusal must say what is wrong',
				).toContain('quicksearchFallbackTarget')
				expect(
					await readAdminFallback(api),
					`"${bad}" must not have been stored`,
				).toBe('none')
			}

			// CONTROL: the same endpoint accepts a valid template.
			await setAdminFallback(api, ADMIN_TEMPLATE)
			expect(await readAdminFallback(api)).toBe(ADMIN_TEMPLATE)
		})
	})

	// @e2e tile-quick-search::per-widget-override-takes-precedence-over-the-admin-default
	test("a widget's own `none` beats the admin's unified-search", async ({
		page,
	}) => {
		let inheritingId = 0
		try {
			await withApi(async (api) => {
				await setAdminFallback(api, 'unified-search')
				await setContent(api, searchId, {
					placeholder: '',
					fallbackTarget: 'none',
				})
				// CONTROL bar: no override, so it inherits unified-search.
				inheritingId = await addPlacement(
					api,
					'search',
					{ placeholder: '', fallbackTarget: '' },
					{ x: 6, y: 0 },
				)
			})
			await instrument(page)
			await openWorkspace(page, TILES[0], 2)
			const urlBefore = page.url()

			const overridden = inputOf(page, searchId)
			await overridden.fill(NO_MATCH)
			await overridden.press('Enter')
			await expect(
				cell(page, searchId).locator(EMPTY),
				'the overridden bar shows its no-results message',
			).toBeVisible()
			expect(await unified(page), 'the override (none) must win').toHaveLength(
				0,
			)
			expect(await opened(page)).toHaveLength(0)
			expect(page.url()).toBe(urlBefore)

			// CONTROL: the admin's unified-search really is in force — a bar
			// without an override hands off.
			const inheriting = inputOf(page, inheritingId)
			await inheriting.fill(NO_MATCH)
			await inheriting.press('Enter')
			await expect
				.poll(() => unified(page), {
					message:
						'CONTROL: the inheriting bar must hand off, proving the admin setting is unified-search',
				})
				.toEqual([NO_MATCH])
		} finally {
			if (inheritingId) {
				await withApi((api) => removePlacement(api, inheritingId))
			}
		}
	})

	// @e2e tile-quick-search::empty-per-widget-override-inherits-the-admin-default
	test("an empty override resolves exactly as the admin's web-search template", async ({
		page,
	}) => {
		await withApi(async (api) => {
			await setAdminFallback(api, ADMIN_TEMPLATE)
			await setContent(api, searchId, { placeholder: '', fallbackTarget: '' })
			const stored = (await placements(api)).find((p) => p.id === searchId)
			expect(
				contentOf(stored).fallbackTarget ?? '',
				'CONTROL: the override is empty',
			).toBe('')
		})
		await instrument(page)
		await openWorkspace(page, TILES[0], 1)

		const input = page.locator(INPUT)
		await input.fill(NO_MATCH)
		await input.press('Enter')

		await expect.poll(() => opened(page)).toHaveLength(1)
		const [hit] = await opened(page)
		expect(hit).toEqual({
			url: ADMIN_TEMPLATE.replace('{query}', encodeURIComponent(NO_MATCH)),
			target: '_blank',
		})
		expect(await unified(page)).toHaveLength(0)
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-QSEARCH-005 — the widget's config form
// ─────────────────────────────────────────────────────────────────────────────

test.describe('tile quick-search — widget configuration (REQ-QSEARCH-005)', () => {
	test.describe.configure({ timeout: 120_000 })

	test.afterEach(async () => {
		await withApi(async (api) => {
			await setAdminFallback(api, 'none')
			await setContent(api, searchId, { placeholder: '', fallbackTarget: '' })
		})
	})

	// @e2e tile-quick-search::default-content-on-add
	test('a search widget added through Add Widget defaults to empty placeholder and inherited fallback', async ({
		page,
	}) => {
		let addedId = 0
		try {
			await withApi((api) => setAdminFallback(api, ADMIN_TEMPLATE))
			await instrument(page)
			await openWorkspace(page, TILES[0], 1)

			// Add Widget flow on THIS dashboard's row (widget-flow.ts opens
			// the first personal row, which need not be this one).
			await openDashboardSwitcher(page)
			const row = page
				.locator('[data-source="user"].dashboard-switcher-sidebar__item')
				.filter({ hasText: MAIN_NAME })
				.first()
			await expect(row).toBeVisible({ timeout: 10_000 })
			await row.locator('.dashboard-row-actions button').first().click()
			await page.getByRole('menuitem', { name: /add custom widget/i }).click()
			const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
			await expect(dialog).toBeVisible({ timeout: 10_000 })

			await dialog
				.locator('[data-testid="widget-type-select"]')
				.selectOption('search')
			await expect(dialog.locator('.search-widget-form')).toBeVisible()

			const created = page.waitForResponse(
				(r) =>
					r.request().method() === 'POST'
					&& r.url().includes(`/api/dashboard/${main.id}/widgets`),
			)
			await dialog.locator('[data-testid="add-widget-save"]').click()
			const response = await created
			expect(response.status(), await response.text()).toBe(201)
			const body = await response.json()
			addedId = Number(body.id ?? body.placement?.id ?? body.data?.id)
			expect(addedId, 'the new placement id').toBeTruthy()
			await expect(dialog).not.toBeVisible({ timeout: 10_000 })

			// THEN: the persisted content is the default.
			const stored = await withApi(async (api) =>
				(await placements(api)).find((p) => p.id === addedId),
			)
			expect(stored?.widgetId).toBe('search')
			expect(
				contentOf(stored),
				'persisted content must default to empty placeholder and fallback',
			).toMatchObject({
				placeholder: '',
				fallbackTarget: '',
			})

			// AND: the empty placeholder renders the built-in text.
			await closeSidebar(page)
			const input = inputOf(page, addedId)
			await expect(input).toBeVisible({ timeout: 15_000 })
			await expect(input).toHaveAttribute('placeholder', DEFAULT_PLACEHOLDER)

			// AND: the empty fallback inherits the admin template.
			await input.fill(NO_MATCH)
			await input.press('Enter')
			await expect.poll(() => opened(page)).toHaveLength(1)
			expect((await opened(page))[0].url).toBe(
				ADMIN_TEMPLATE.replace('{query}', encodeURIComponent(NO_MATCH)),
			)
		} finally {
			if (addedId) {
				await withApi((api) => removePlacement(api, addedId))
			}
		}
	})

	// @e2e tile-quick-search::configuring-a-custom-placeholder
	test('a placeholder saved in the config form replaces the built-in text', async ({
		page,
	}) => {
		const custom = `Zoek in het intranet ${STAMP}`
		await openWorkspace(page, TILES[0], 1)
		await expect(
			inputOf(page, searchId),
			'CONTROL: the built-in placeholder shows before the change',
		).toHaveAttribute('placeholder', DEFAULT_PLACEHOLDER)

		const dialog = await openSearchWidgetEditor(page, searchId)
		await dialog.getByLabel('Placeholder text').fill(custom)

		const saved = page.waitForResponse(
			(r) =>
				r.request().method() === 'PUT'
				&& r.url().includes(`/api/widgets/${searchId}`),
		)
		await dialog.locator('[data-testid="add-widget-save"]').click()
		expect((await saved).status()).toBeLessThan(300)
		await expect(dialog).not.toBeVisible({ timeout: 10_000 })

		await expect(inputOf(page, searchId)).toHaveAttribute('placeholder', custom)

		// Saved, not just held in the page.
		await page.reload()
		await expect(inputOf(page, searchId)).toHaveAttribute(
			'placeholder',
			custom,
			{
				timeout: 30_000,
			},
		)
	})

	// @e2e tile-quick-search::configuring-a-per-widget-fallback-override
	test('a web-search override saved in the form persists and wins over the admin setting', async ({
		page,
	}) => {
		await withApi((api) => setAdminFallback(api, 'unified-search'))
		await openWorkspace(page, TILES[0], 1)

		const dialog = await openSearchWidgetEditor(page, searchId)
		await chooseFallbackMode(page, dialog, 3)
		await dialog.getByLabel('Search URL').fill(WIDGET_TEMPLATE)

		const saved = page.waitForResponse(
			(r) =>
				r.request().method() === 'PUT'
				&& r.url().includes(`/api/widgets/${searchId}`),
		)
		await dialog.locator('[data-testid="add-widget-save"]').click()
		expect((await saved).status()).toBeLessThan(300)
		await expect(dialog).not.toBeVisible({ timeout: 10_000 })

		// THEN: content.fallbackTarget persists that value.
		const stored = await withApi(async (api) =>
			(await placements(api)).find((p) => p.id === searchId),
		)
		expect(contentOf(stored).fallbackTarget).toBe(WIDGET_TEMPLATE)

		// AND: a no-match Enter resolves per the per-widget override.
		await instrument(page)
		await openWorkspace(page, TILES[0], 1)
		const input = page.locator(INPUT)
		await input.fill(NO_MATCH)
		await input.press('Enter')
		await expect.poll(() => opened(page)).toHaveLength(1)
		expect((await opened(page))[0].url).toBe(
			WIDGET_TEMPLATE.replace('{query}', encodeURIComponent(NO_MATCH)),
		)
		expect(
			await unified(page),
			"the admin's unified-search must not fire for an overridden widget",
		).toHaveLength(0)
	})

	// @e2e tile-quick-search::fallback-override-template-validation
	test('the config form refuses a non-https template or one without {query}', async ({
		page,
	}) => {
		await openWorkspace(page, TILES[0], 1)
		const dialog = await openSearchWidgetEditor(page, searchId)

		const writes: string[] = []
		page.on('request', (r) => {
			if (
				r.method() === 'PUT'
				&& r.url().includes(`/api/widgets/${searchId}`)
			) {
				writes.push(r.url())
			}
		})

		await chooseFallbackMode(page, dialog, 3)
		const field = dialog.getByLabel('Search URL')
		const save = dialog.locator('[data-testid="add-widget-save"]')
		const error = 'Enter an https URL containing {query}.'

		for (const bad of [
			'http://intranet.example.org/zoek?q={query}',
			'https://intranet.example.org/zoek',
		]) {
			await field.fill(bad)
			await expect(
				dialog.getByText(error).first(),
				`"${bad}" must show the validation error`,
			).toBeVisible()
			await expect(save, `"${bad}" must not be saveable`).toBeDisabled()
			await save.click({ force: true }).catch(() => undefined)
			await expect(dialog, 'the form stays open').toBeVisible()
		}

		// CONTROL: a valid template clears the error and enables Save.
		await field.fill(WIDGET_TEMPLATE)
		await expect(dialog.getByText(error)).toHaveCount(0)
		await expect(save).toBeEnabled()

		expect(writes, 'nothing may have been saved').toHaveLength(0)
		const stored = await withApi(async (api) =>
			(await placements(api)).find((p) => p.id === searchId),
		)
		expect(contentOf(stored).fallbackTarget ?? '').toBe('')
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-QSEARCH-006 / 007 — one bar, several bars
// ─────────────────────────────────────────────────────────────────────────────

test.describe('tile quick-search — one or several bars (REQ-QSEARCH-006, REQ-QSEARCH-007)', () => {
	// @e2e tile-quick-search::exactly-one-search-widget-placed
	test('with exactly one bar, / and Ctrl+K both focus it', async ({ page }) => {
		const stored = await withApi(async (api) =>
			(await placements(api)).filter((p) => p.widgetId === 'search'),
		)
		expect(
			stored.map((p) => p.id),
			'CONTROL: exactly one search placement',
		).toEqual([searchId])
		await openWorkspace(page, TILES[0], 1)
		const input = inputOf(page, searchId)

		await blurToGrid(page)
		await expect(input).not.toBeFocused()
		await page.keyboard.press('/')
		await expect(input, '/ must focus the bar').toBeFocused()
		await expect(input).toHaveValue('')

		await blurToGrid(page)
		await expect(input).not.toBeFocused()
		await page.keyboard.press('Control+k')
		await expect(input, 'Ctrl+K must focus the bar').toBeFocused()
	})

	// @e2e tile-quick-search::multiple-search-widgets-placed
	test('with two bars, only the first-mounted one takes focus', async ({
		page,
	}) => {
		let secondId = 0
		try {
			// Created after, and to the right of, the fixture bar: first by id,
			// by position and by array order, so "first-mounted" is the fixture bar.
			secondId = await withApi((api) =>
				addPlacement(
					api,
					'search',
					{ placeholder: `Second ${STAMP}`, fallbackTarget: '' },
					{ x: 6, y: 0 },
				),
			)
			await openWorkspace(page, TILES[0], 2)
			const first = inputOf(page, searchId)
			const second = inputOf(page, secondId)

			await second.evaluate((el) => {
				;(window as unknown as { __qsSecondFocus: number }).__qsSecondFocus =
					0
				el.addEventListener('focus', () => {
					;(
						window as unknown as { __qsSecondFocus: number }
					).__qsSecondFocus += 1
				})
			})

			for (const key of ['/', 'Control+k']) {
				await blurToGrid(page)
				await expect(first).not.toBeFocused()
				await page.keyboard.press(key)
				await expect(
					first,
					`${key} must focus the first-mounted bar`,
				).toBeFocused()
				await expect(second).not.toBeFocused()
			}
			expect(
				await page.evaluate(
					() =>
						(window as unknown as { __qsSecondFocus: number })
							.__qsSecondFocus,
				),
				'the second bar must never have claimed focus, even momentarily',
			).toBe(0)
		} finally {
			if (secondId) {
				await withApi((api) => removePlacement(api, secondId))
			}
		}
	})

	// @e2e tile-quick-search::the-search-widget-stays-at-full-emphasis-while-filtering
	test('a query that does not match "Search" dims tiles but never the bar itself', async ({
		page,
	}) => {
		await openWorkspace(page, TILES[0], 1)
		await page.locator(INPUT).fill('zaak')
		await expect.poll(() => optionLabels(page)).toEqual([`Zaaksysteem ${STAMP}`])
		expect(
			(await optionLabels(page)).some((l) => /search/i.test(l)),
			"CONTROL: the query does not match the search widget's own label",
		).toBe(false)

		const nonMatching = tileCells(page)
			.filter({ hasText: `Verlof aanvragen ${STAMP}` })
			.first()
		const alsoNonMatching = tileCells(page)
			.filter({ hasText: `Overzicht verlof ${STAMP}` })
			.first()
		await expect
			.poll(() => isDimmed(nonMatching), {
				message: 'a non-matching tile must be dimmed',
			})
			.toBe(true)
		expect(
			await isDimmed(alsoNonMatching),
			'every non-matching tile must be dimmed',
		).toBe(true)
		expect(
			await isDimmed(cell(page, searchId)),
			'the search placement must not be de-emphasised',
		).toBe(false)
	})

	// @e2e tile-quick-search::several-search-widgets-are-all-exempt
	test('with two bars, a query in either one dims neither', async ({ page }) => {
		let secondId = 0
		try {
			secondId = await withApi((api) =>
				addPlacement(
					api,
					'search',
					{ placeholder: '', fallbackTarget: '' },
					{ x: 6, y: 0 },
				),
			)
			await openWorkspace(page, TILES[0], 2)
			const nonMatching = tileCells(page)
				.filter({ hasText: `Verlof aanvragen ${STAMP}` })
				.first()

			for (const typingIn of [searchId, secondId]) {
				const input = inputOf(page, typingIn)
				await input.fill('zaak')
				await expect
					.poll(() => isDimmed(nonMatching), {
						message: 'CONTROL: the query must be dimming tiles',
					})
					.toBe(true)
				expect(
					await isDimmed(cell(page, searchId)),
					`typing in ${typingIn}: first bar dimmed`,
				).toBe(false)
				expect(
					await isDimmed(cell(page, secondId)),
					`typing in ${typingIn}: second bar dimmed`,
				).toBe(false)
				await input.press('Escape')
				await expect.poll(() => isDimmed(nonMatching)).toBe(false)
			}
		} finally {
			if (secondId) {
				await withApi((api) => removePlacement(api, secondId))
			}
		}
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-QSEARCH-008 — labels match the grid titles
// ─────────────────────────────────────────────────────────────────────────────

/** The title a placement shows on the grid: its own nc-widget header, or the card header. */
async function renderedTitle(page: Page, placementId: number): Promise<string> {
	const title = cell(page, placementId)
		.locator('.cn-nc-widget-widget__header-title, .cn-widget-wrapper__title')
		.first()
	await expect(title, 'the placement must render a title').toBeVisible({
		timeout: 15_000,
	})
	return (await title.innerText()).trim()
}

async function catalog(
	api: APIRequestContext,
): Promise<Array<{ id: string; title?: string }>> {
	const res = await api.get(`${API}/widgets`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return Array.isArray(body) ? body : (body.widgets ?? body.data ?? [])
}

test.describe('tile quick-search — labels (REQ-QSEARCH-008)', () => {
	// @e2e tile-quick-search::a-proxied-nextcloud-dashboard-widget-is-findable-by-its-real-name
	test("an nc-widget placement is found by the proxied widget's title, shown as on the tile", async ({
		page,
	}) => {
		let ncId = 0
		try {
			const real = await withApi(async (api) => {
				const list = await catalog(api)
				// A real Nextcloud widget whose title differs from its id, so a
				// match by title proves the title — not the id — is searched.
				const pick = list.find(
					(w) =>
						w.title
						&& w.title.trim() !== ''
						&& w.title !== w.id
						&& w.id !== 'nc-widget',
				)
				expect(
					pick,
					`the catalog must offer a Nextcloud dashboard widget: ${JSON.stringify(list.map((w) => w.id))}`,
				).toBeTruthy()
				ncId = await addPlacement(
					api,
					'nc-widget',
					{ widgetId: pick!.id },
					{ x: 6, y: 2, w: 4, h: 4 },
				)
				return pick!
			})
			await openWorkspace(page, TILES[0], 1)

			await page.locator(INPUT).fill(real.title!)
			await expect
				.poll(() => optionLabels(page), {
					message: 'the proxied widget must be found by its title',
				})
				.toContain(real.title)
			expect(
				await optionLabels(page),
				'the placement must not be listed under its widget id',
			).not.toContain(real.id)

			expect(
				await renderedTitle(page, ncId),
				'the result label must equal the title rendered on the tile',
			).toBe(real.title)
		} finally {
			if (ncId) {
				await withApi((api) => removePlacement(api, ncId))
			}
		}
	})

	// @e2e tile-quick-search::a-typed-widget-with-no-catalog-entry-falls-back-to-its-type-name
	test('a clock placement is labelled with its type name, not the generic "Widget"', async ({
		page,
	}) => {
		let clockId = 0
		try {
			await withApi(async (api) => {
				expect(
					(await catalog(api)).some((w) => w.id === 'clock'),
					'CONTROL: `clock` must be a registered type with no catalog entry',
				).toBe(false)
				clockId = await addPlacement(
					api,
					'clock',
					{},
					{ x: 6, y: 2, w: 3, h: 3 },
				)
			})
			await openWorkspace(page, TILES[0], 1)

			await page.locator(INPUT).fill('clock')
			// `displayName: 'Clock'` in widgetRegistry.js.
			await expect.poll(() => optionLabels(page)).toEqual(['Clock'])
			expect(await renderedTitle(page, clockId)).toBe('Clock')

			await page.locator(INPUT).fill('widget')
			expect(
				await optionLabels(page),
				'the generic fallback word must not be used when the type name resolves',
			).not.toContain('Widget')
		} finally {
			if (clockId) {
				await withApi((api) => removePlacement(api, clockId))
			}
		}
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SPX-001..003 — search shortcuts
// ─────────────────────────────────────────────────────────────────────────────

test.describe('search shortcuts (REQ-SPX-001..003)', () => {
	test.afterEach(async () => {
		await withApi(async (api) => {
			await setShortcuts(api, [])
			await setAdminFallback(api, 'none')
		})
	})

	async function openShortcutsTab(page: Page): Promise<Locator> {
		await page.goto(ADMIN_PAGE)
		await adminTab(page, 'search-shortcuts').click()
		const tab = page.locator('[data-testid="search-shortcuts-tab"]')
		await expect(tab).toBeVisible({ timeout: 15_000 })
		await expect(tab.getByText('Loading…')).toHaveCount(0, { timeout: 15_000 })
		return tab
	}

	async function addRow(
		tab: Locator,
		entry: { prefix: string; name: string; urlTemplate: string },
	): Promise<void> {
		await tab.getByRole('button', { name: 'Add shortcut' }).click()
		const row = tab.locator('tbody tr').last()
		await row.getByRole('textbox', { name: 'Prefix' }).fill(entry.prefix)
		await row.getByRole('textbox', { name: 'Name' }).fill(entry.name)
		await row
			.getByRole('textbox', { name: 'Address with {query}' })
			.fill(entry.urlTemplate)
	}

	// @e2e tile-quick-search::add-the-topdesk-shortcut
	test('an admin adds !t TOPdesk on the Search shortcuts tab and it is listed', async ({
		page,
	}) => {
		const tab = await openShortcutsTab(page)
		await expect(
			tab.getByText('No search shortcuts yet.'),
			'CONTROL: starts empty',
		).toBeVisible()

		await addRow(tab, TOPDESK)
		await tab.getByRole('button', { name: 'Save search shortcuts' }).click()
		await expect(tab.getByRole('status')).toHaveText('Search shortcuts saved.')
		await expect(tab.getByRole('alert')).toHaveCount(0)

		expect(await withApi(readShortcuts)).toEqual([TOPDESK])

		// Listed after a fresh load, from the server.
		const again = await openShortcutsTab(page)
		const row = again.locator('tbody tr')
		await expect(row).toHaveCount(1)
		await expect(row.getByRole('textbox', { name: 'Prefix' })).toHaveValue('!t')
		await expect(row.getByRole('textbox', { name: 'Name' })).toHaveValue(
			'TOPdesk',
		)
		await expect(
			row.getByRole('textbox', { name: 'Address with {query}' }),
		).toHaveValue(TOPDESK.urlTemplate)
	})

	// @e2e tile-quick-search::plain-http-is-refused
	test("a plain-http address is refused with the server's message and not stored", async ({
		page,
	}) => {
		const tab = await openShortcutsTab(page)
		await addRow(tab, {
			prefix: '!t',
			name: 'TOPdesk',
			urlTemplate: 'http://intranet.gemeente.nl/zoek?q={query}',
		})
		const put = page.waitForResponse(
			(r) =>
				r.request().method() === 'PUT'
				&& r.url().includes('/api/admin/search-shortcuts'),
		)
		await tab.getByRole('button', { name: 'Save search shortcuts' }).click()
		expect((await put).status(), 'the server must refuse the entry').toBe(400)

		await expect(tab.getByRole('alert')).toContainText(
			'Use an https address that contains {query}',
		)
		await expect(tab.getByText('Search shortcuts saved.')).toHaveCount(0)
		expect(await withApi(readShortcuts), 'nothing may be stored').toEqual([])
	})

	// @e2e tile-quick-search::search-topdesk
	test('!t printer 3e verdieping offers one TOPdesk result and Enter opens it in a new tab', async ({
		page,
	}) => {
		await withApi((api) => setShortcuts(api, [TOPDESK]))
		await instrument(page)
		await openWorkspace(page, TILES[0], 1)

		const input = page.locator(INPUT)
		await input.fill('!t printer 3e verdieping')
		await expect
			.poll(() => optionLabels(page))
			.toEqual(['Search TOPdesk for printer 3e verdieping'])

		await input.press('Enter')
		await expect.poll(() => opened(page)).toHaveLength(1)
		expect((await opened(page))[0]).toEqual({
			url: 'https://topdesk.gemeente.nl/tas/secure/search?q=printer%203e%20verdieping',
			target: '_blank',
		})
	})

	// @e2e tile-quick-search::unknown-prefix-falls-through
	test('!x rapport, with no !x shortcut, filters tiles and then applies the normal fallback', async ({
		page,
	}) => {
		const tileTitle = `!x rapport archief ${STAMP}`
		await withApi(async (api) => {
			await setShortcuts(api, [TOPDESK])
			await setAdminFallback(api, ADMIN_TEMPLATE)
			await addTile(api, main.id, tileTitle, 6, 2)
		})
		try {
			await instrument(page)
			await openWorkspace(page, TILES[0], 1)
			const input = page.locator(INPUT)

			// CONTROL: the shortcut machinery is live — a known prefix is caught.
			await input.fill('!t rapport')
			await expect
				.poll(() => optionLabels(page))
				.toEqual(['Search TOPdesk for rapport'])

			// The unknown prefix is a plain tile query.
			await input.fill('!x rapport')
			await expect
				.poll(() => optionLabels(page), {
					message: '!x rapport must filter tiles',
				})
				.toEqual([tileTitle])
			const other = tileCells(page)
				.filter({ hasText: `Zaaksysteem ${STAMP}` })
				.first()
			await expect
				.poll(() => isDimmed(other), {
					message: 'non-matching tiles are dimmed',
				})
				.toBe(true)
			expect(
				await isDimmed(
					tileCells(page).filter({ hasText: tileTitle }).first(),
				),
			).toBe(false)
		} finally {
			await withApi(async (api) => {
				const tile = (await placements(api)).find(
					(p) => p.tileTitle === tileTitle,
				)
				if (tile) {
					await removePlacement(api, tile.id)
				}
			})
		}

		// …and with nothing matching, Enter applies the normal fallback.
		await openWorkspace(page, TILES[0], 1)
		const input = page.locator(INPUT)
		await input.fill('!x rapport')
		await expect(page.locator(EMPTY)).toBeVisible()
		await input.press('Enter')
		await expect.poll(() => opened(page)).toHaveLength(1)
		expect((await opened(page))[0].url).toBe(
			ADMIN_TEMPLATE.replace('{query}', encodeURIComponent('!x rapport')),
		)
	})

	// @e2e tile-quick-search::list-the-shortcuts
	test('typing ? lists every shortcut with prefix and name, announced to screen readers', async ({
		page,
	}) => {
		await withApi((api) => setShortcuts(api, [TOPDESK, ZAAKSYSTEEM]))
		await openWorkspace(page, TILES[0], 1)

		await page.locator(INPUT).fill('?')
		await expect
			.poll(() => optionLabels(page))
			.toEqual(['!t TOPdesk', '!z Zaaksysteem'])
		await expect(
			page.getByRole('listbox', { name: 'Search shortcuts' }),
		).toBeVisible()
		const status = page.locator(STATUS)
		await expect(status).toHaveAttribute('aria-live', 'polite')
		await expect(status).toHaveText('!t TOPdesk, !z Zaaksysteem')
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-QSEARCH-005 — removal. Last: it deletes the fixture bar (and re-seeds it).
// ─────────────────────────────────────────────────────────────────────────────

test.describe('tile quick-search — removing the widget (REQ-QSEARCH-005)', () => {
	// @e2e tile-quick-search::removing-the-widget-removes-search-from-the-dashboard
	test('deleting the only search widget removes the bar and leaves / and Ctrl+K inert', async ({
		page,
	}) => {
		try {
			await openWorkspace(page, TILES[0], 1)
			await enterEditMode(page)
			await openCog(page, searchId)
			const deleted = page.waitForResponse(
				(r) =>
					r.request().method() === 'DELETE'
					&& r.url().includes(`/api/widgets/${searchId}`),
			)
			await page
				.locator('[data-testid="cn-widget-edit-cog-delete"]')
				.first()
				.click()
			expect((await deleted).status()).toBeLessThan(300)

			// Re-rendered in place: no bar.
			await expect(cell(page, searchId)).toHaveCount(0, { timeout: 10_000 })
			await expect(page.locator(INPUT)).toHaveCount(0)
			await expect(
				tileCells(page).filter({ hasText: TILES[0] }).first(),
				'CONTROL: the rest of the dashboard is still rendered',
			).toBeVisible()

			const stored = await withApi(placements)
			expect(
				stored.some((p) => p.widgetId === 'search'),
				'the deletion must be persisted',
			).toBe(false)

			// And in a fresh render, the shortcuts behave as with no widget.
			await openWorkspace(page, TILES[0], 0)
			await assertShortcutsInert(page)
		} finally {
			await withApi(async (api) => {
				const stillThere = (await placements(api)).some(
					(p) => p.id === searchId,
				)
				if (!stillThere) {
					searchId = await addPlacement(
						api,
						'search',
						{ placeholder: '', fallbackTarget: '' },
						{ x: 0, y: 0 },
					)
				}
			})
		}
	})
})
