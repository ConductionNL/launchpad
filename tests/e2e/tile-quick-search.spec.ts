/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The quick-search / launcher bar above the tile grid (tile-quick-search
 * REQ-QSEARCH-001..003), driven from the keyboard exactly as a user would.
 *
 * WHY A BROWSER IS THE ONLY PLACE THIS CAN BE PROVEN
 * ==================================================
 * Every claim in these three requirements is about the DOM or about the
 * keyboard: which element has focus after `/`, whether the browser's own
 * Ctrl+K was prevented, whether a non-matching tile is DIMMED rather than
 * removed from the grid, whether `aria-activedescendant` tracks the arrow
 * keys. None of it is visible to a unit test of the component in isolation,
 * because the interesting half is the interaction between
 * `RuntimeShellSearch.vue` (which owns the combobox) and `Views.vue` (which
 * owns the grid) — `WorkspaceApp.vue` bridges them with a plain DOM query,
 * by its own comment, "because the grid DOM lives in a sibling component's
 * tree". A component test mounts one side of that bridge.
 *
 * WHAT THE SELECTORS ARE
 * ======================
 * `RuntimeShellSearch.vue` ships stable `data-test` hooks — `quick-search-
 * input`, `quick-search-option`, `quick-search-status`, `quick-search-empty`
 * — so these tests do not depend on CSS class names that are free to change.
 *
 * THE FIXTURE BUILDS ITS OWN DASHBOARD
 * ====================================
 * The searchable label of a placement is decided by
 * `WorkspaceApp.vue::tileSearchLabel()`: a `custom` tile searches by
 * `tileTitle`. So the dashboard below is seeded through
 * `POST /api/dashboard/{id}/tile` with titles chosen to exercise the ranking
 * rule the spec states — prefix beats mid-string beats subsequence — rather
 * than relying on whatever the shared fixture happens to contain, which no
 * test should have to guess at and which other specs in this serial job are
 * free to change.
 *
 * @spec openspec/specs/tile-quick-search/spec.md
 */

import { expect, request, test, type APIRequestContext, type Page } from '@playwright/test'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}
const ENV_BASE_URL = (process.env.BASE_URL ?? process.env.NC_BASE_URL ?? '').replace(/\/$/, '')

const APP_URL = '/index.php/apps/launchpad'
const SETTINGS = '/index.php/apps/launchpad/api/admin/settings'
const INPUT = '[data-test="quick-search-input"]'
const OPTION = '[data-test="quick-search-option"]'
const STATUS = '[data-test="quick-search-status"]'
const GRID_ITEM = '.launchpad-grid-item'
/*
 * The de-emphasis class `WorkspaceApp.vue::applySearchDimming()` toggles on
 * every `.launchpad-grid-item[data-placement-id]` that is NOT a match. Named
 * once here because a wrong value fails SILENTLY: a selector matching nothing
 * reports zero dimmed both before and after, and any "…must be undimmed"
 * assertion built on it passes without ever having observed a dim.
 */
const DIMMED = '.launchpad-grid-item--dimmed'

/*
 * Tile titles, chosen to make the ranking rule decidable.
 *
 * For the query `verlof`:
 *   "Verlof aanvragen"  — PREFIX match
 *   "Overzicht verlof"  — mid-string SUBSTRING match
 * For the query `zaak`:
 *   "Zaaksysteem", "Zaakbrowser" match; "Verlof aanvragen" must not.
 *
 * A stamp keeps them unique per run so a re-run against a warm instance
 * cannot match a leftover tile from a previous one.
 */
const STAMP = `${Date.now()}`
const TILES = [
	`Zaaksysteem ${STAMP}`,
	`Zaakbrowser ${STAMP}`,
	`Verlof aanvragen ${STAMP}`,
	`Overzicht verlof ${STAMP}`,
]

function basic(user: string, pass: string): string {
	return `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`
}

/*
 * An API context that is unambiguously the admin.
 *
 * The jar is spelled `{ cookies: [], origins: [] }` and NOT
 * `storageState: undefined`: option merging treats an explicit `undefined`
 * as "not supplied" and falls back to the project default, which is the very
 * `use.storageState` session this is meant to drop.
 */
async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: ENV_BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: basic(ADMIN.user, ADMIN.pass),
		},
	})
}

let dashboardId: number
let priorAllowUserDash = true

test.beforeAll(async () => {
	const api = await adminApi()

	const before = await api.get(SETTINGS)
	expect(before.status(), await before.text()).toBe(200)
	priorAllowUserDash = (await before.json()).allowUserDashboards === true
	const enable = await api.put(SETTINGS, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)

	const created = await api.post('/index.php/apps/launchpad/api/dashboard', {
		data: { name: `E2E QuickSearch ${STAMP}` },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	dashboardId = Number(dash.id)
	expect(dashboardId, `no dashboard id: ${JSON.stringify(body)}`).toBeTruthy()

	for (const [i, title] of TILES.entries()) {
		const res = await api.post(`/index.php/apps/launchpad/api/dashboard/${dashboardId}/tile`, {
			data: {
				title,
				linkType: 'url',
				linkValue: `https://example.invalid/${encodeURIComponent(title)}`,
				gridX: (i % 2) * 3,
				gridY: Math.floor(i / 2) * 2,
				gridWidth: 3,
				gridHeight: 2,
			},
		})
		expect(res.status(), `seeding tile "${title}": ${await res.text()}`).toBeLessThan(300)
	}

	const activate = await api.post(`/index.php/apps/launchpad/api/dashboard/${dashboardId}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)

	await api.dispose()
})

test.afterAll(async () => {
	const api = await adminApi()
	if (dashboardId) {
		await api.delete(`/index.php/apps/launchpad/api/dashboard/${dashboardId}`)
	}
	if (priorAllowUserDash === false) {
		await api.put(SETTINGS, { data: { allowUserDash: false } })
	}
	await api.dispose()
})

/** Open the workspace on the seeded dashboard with the search bar mounted. */
async function openWorkspace(page: Page): Promise<void> {
	await page.goto(APP_URL)
	// The bar only renders when an active dashboard is resolved
	// (`v-if="hasActiveDashboard"` in WorkspaceApp.vue), so its presence is
	// also the signal that the shell finished booting.
	await expect(page.locator(INPUT)).toBeVisible({ timeout: 30_000 })
	// The tiles have to be in the store before any filtering assertion means
	// anything — `searchableTiles` reads the live Pinia placements.
	await expect
		.poll(
			async () => page.locator(GRID_ITEM).count(),
			{ message: 'the seeded tiles must be rendered before searching', timeout: 30_000 },
		)
		.toBeGreaterThanOrEqual(TILES.length)
}

/** The visible result labels, in the order the listbox presents them. */
async function optionLabels(page: Page): Promise<string[]> {
	return (await page.locator(OPTION).allInnerTexts()).map(t => t.trim())
}

test.describe('tile quick-search — focus (REQ-QSEARCH-001)', () => {
	// @e2e tile-quick-search::search-bar-is-present-and-labelled
	test('the bar renders above the grid, is wrapped in role=search, and is labelled and tabbable', async ({ page }) => {
		await openWorkspace(page)

		const input = page.locator(INPUT)
		await expect(input).toBeVisible()

		// `role="search"` is on the wrapper, per RuntimeShellSearch.vue.
		await expect(
			page.locator('[role="search"]').filter({ has: page.locator(INPUT) }),
			'the input must be wrapped in a role="search" landmark',
		).toHaveCount(1)

		// An accessible name, however it is supplied (the component uses a
		// visually-hidden <label for>). Asking the accessibility tree rather
		// than the markup keeps this true if the mechanism changes.
		const accessibleName = await input.evaluate((el) => {
			const byLabel = el.id
				? document.querySelector(`label[for="${CSS.escape(el.id)}"]`)?.textContent
				: null
			return (byLabel ?? el.getAttribute('aria-label') ?? '').trim()
		})
		expect(accessibleName, 'the quick-search input must have an accessible name').not.toBe('')

		// Reachable in the normal tab order — i.e. not tabindex="-1".
		expect(
			await input.getAttribute('tabindex'),
			'the bar must stay in the natural tab order',
		).not.toBe('-1')

		// …and it can actually take focus, with a focus indicator that is not
		// `outline: none` with nothing put back.
		await input.focus()
		await expect(input).toBeFocused()
	})

	// @e2e tile-quick-search::slash-focuses-the-bar
	test('pressing / focuses the bar and does not type the slash into it', async ({ page }) => {
		await openWorkspace(page)
		const input = page.locator(INPUT)

		// CONTROL — focus starts somewhere else, so "is focused" below is a
		// change and not the initial state.
		await page.locator('body').click({ position: { x: 5, y: 5 } })
		await expect(input, 'CONTROL: the bar must not already hold focus').not.toBeFocused()

		await page.keyboard.press('/')
		await expect(input).toBeFocused()

		// THE second clause of the scenario: the `/` must be swallowed, not
		// delivered. A handler that focuses without preventing the default
		// leaves a stray "/" in the field.
		await expect(
			input,
			'the / that triggered the shortcut must not be inserted into the field',
		).toHaveValue('')
	})

	// @e2e tile-quick-search::ctrlk-focuses-the-bar
	test('Ctrl+K focuses the bar and prevents the browser default', async ({ page }) => {
		await openWorkspace(page)
		const input = page.locator(INPUT)

		await page.locator('body').click({ position: { x: 5, y: 5 } })
		await expect(input, 'CONTROL: the bar must not already hold focus').not.toBeFocused()

		// Record whether the app called preventDefault on the very event it
		// acted upon. Asserting focus alone would pass on a handler that
		// leaves the browser's own Ctrl+K to fire as well.
		await page.evaluate(() => {
			;(window as unknown as { __ctrlKDefaultPrevented?: boolean }).__ctrlKDefaultPrevented = undefined
			window.addEventListener('keydown', (e) => {
				if (e.key.toLowerCase() === 'k' && (e.ctrlKey || e.metaKey)) {
					;(window as unknown as { __ctrlKDefaultPrevented?: boolean })
						.__ctrlKDefaultPrevented = e.defaultPrevented
				}
			}, false)
		})

		await page.keyboard.press('Control+k')
		await expect(input).toBeFocused()

		expect(
			await page.evaluate(() => (window as unknown as { __ctrlKDefaultPrevented?: boolean }).__ctrlKDefaultPrevented),
			'the shortcut must call preventDefault so the browser default does not also fire',
		).toBe(true)
	})
})

test.describe('tile quick-search — filtering (REQ-QSEARCH-002)', () => {
	// @e2e tile-quick-search::typing-filters-tiles-by-label
	test('typing narrows the matches, dims rather than removes non-matches, and issues no request', async ({ page }) => {
		await openWorkspace(page)

		const gridItems = page.locator(GRID_ITEM)
		const gridCountBefore = await gridItems.count()

		// Watch for ANY request while typing. The spec says the filter is
		// entirely client-side; a debounced backend search would show up here.
		const requestsWhileTyping: string[] = []
		const record = (url: string) => {
			if (url.includes('/apps/launchpad/')) {
				requestsWhileTyping.push(url)
			}
		}
		page.on('request', req => record(req.url()))

		await page.locator(INPUT).fill('zaak')

		await expect
			.poll(async () => optionLabels(page), { message: 'the query must narrow the result list', timeout: 15_000 })
			.toHaveLength(2)

		const labels = await optionLabels(page)
		expect(labels.join(' | ')).toContain('Zaaksysteem')
		expect(labels.join(' | ')).toContain('Zaakbrowser')
		expect(
			labels.join(' | '),
			'a tile whose label does not match must not be offered as a result',
		).not.toContain('Verlof aanvragen')

		/*
		 * THE distinguishing assertion of this scenario, in two halves. The
		 * grid must keep every tile — a filter implemented by unmounting
		 * cells would satisfy every assertion above — AND the non-matching
		 * ones must actually carry the de-emphasis class, or "de-emphasised
		 * rather than removed" is only half proven.
		 */
		expect(
			await gridItems.count(),
			'non-matching tiles must be de-emphasised, not removed from the grid layout',
		).toBe(gridCountBefore)

		await expect
			.poll(async () => page.locator(DIMMED).count(), {
				message: 'the tiles that do not match must be visibly de-emphasised',
				timeout: 15_000,
			})
			.toBeGreaterThan(0)

		expect(
			requestsWhileTyping,
			`filtering must be client-side; these launchpad requests fired while typing: ${requestsWhileTyping.join(', ')}`,
		).toHaveLength(0)
	})

	// @e2e tile-quick-search::ranking-order
	test('a prefix match ranks above a mid-string match', async ({ page }) => {
		await openWorkspace(page)
		await page.locator(INPUT).fill('verlof')

		await expect
			.poll(async () => optionLabels(page), { message: 'both verlof tiles must match', timeout: 15_000 })
			.toHaveLength(2)

		const labels = await optionLabels(page)
		const prefix = labels.findIndex(l => l.includes('Verlof aanvragen'))
		const midString = labels.findIndex(l => l.includes('Overzicht verlof'))

		expect(prefix, '"Verlof aanvragen" must be among the results').toBeGreaterThanOrEqual(0)
		expect(midString, '"Overzicht verlof" must be among the results').toBeGreaterThanOrEqual(0)
		expect(
			prefix,
			'a label STARTING with the query must rank above one that matches mid-string',
		).toBeLessThan(midString)
	})

	// @e2e tile-quick-search::no-query-stored
	test('the query is never persisted — no request carries it and no web storage holds it', async ({ page }) => {
		await openWorkspace(page)

		const secret = `qsprobe${STAMP}`
		const carryingRequests: string[] = []
		page.on('request', (req) => {
			if (req.url().includes(secret)) {
				carryingRequests.push(`${req.method()} ${req.url()}`)
				return
			}
			const body = req.postData()
			if (body && body.includes(secret)) {
				carryingRequests.push(`${req.method()} ${req.url()} (body)`)
			}
		})

		await page.locator(INPUT).fill(secret)
		// Give any debounced persistence a chance to fire before concluding
		// it did not — an assertion made too early would pass on a store that
		// simply had not flushed yet.
		await expect(page.locator('[data-test="quick-search-empty"], ' + OPTION).first())
			.toBeVisible({ timeout: 15_000 })

		expect(
			carryingRequests,
			`the query must not be sent anywhere: ${carryingRequests.join(', ')}`,
		).toHaveLength(0)

		const stored = await page.evaluate((needle) => {
			const hits: string[] = []
			for (const store of [localStorage, sessionStorage]) {
				for (let i = 0; i < store.length; i++) {
					const key = store.key(i)!
					if (key.includes(needle) || (store.getItem(key) ?? '').includes(needle)) {
						hits.push(key)
					}
				}
			}
			return hits
		}, secret)
		expect(stored, `the query must not be persisted to web storage: ${stored.join(', ')}`).toHaveLength(0)
	})
})

test.describe('tile quick-search — keyboard navigation (REQ-QSEARCH-003)', () => {
	// @e2e tile-quick-search::arrow-keys-move-the-selection
	test('ArrowDown then ArrowUp move the selection, tracked by aria-activedescendant and marked by more than colour', async ({ page }) => {
		await openWorkspace(page)
		const input = page.locator(INPUT)
		await input.fill('zaak')

		await expect.poll(async () => page.locator(OPTION).count(), { timeout: 15_000 }).toBe(2)

		const activeDescendant = () => input.getAttribute('aria-activedescendant')
		const first = await activeDescendant()

		await page.keyboard.press('ArrowDown')
		await expect
			.poll(activeDescendant, { message: 'ArrowDown must move the active option', timeout: 10_000 })
			.not.toBe(first)
		const second = await activeDescendant()
		expect(second, 'aria-activedescendant must name an option').toBeTruthy()

		// The selected option must be the one flagged aria-selected, or the
		// pointer the input exposes disagrees with what a screen reader reads.
		await expect(
			page.locator(`#${CSS.escape(second!)}`),
			'the option named by aria-activedescendant must be the selected one',
		).toHaveAttribute('aria-selected', 'true')

		/*
		 * WCAG: "visibly indicated by more than colour alone". The component
		 * renders a marker element per option and hides it on all but the
		 * active one, so exactly one marker must be un-hidden. Colour alone
		 * would leave zero.
		 */
		expect(
			await page.locator('.runtime-shell-search__option-marker:not(.runtime-shell-search__option-marker--hidden)').count(),
			'the active option needs a non-colour indicator, and exactly one option may carry it',
		).toBe(1)

		await page.keyboard.press('ArrowUp')
		await expect
			.poll(activeDescendant, { message: 'ArrowUp must move the selection back', timeout: 10_000 })
			.toBe(first)
	})

	// @e2e tile-quick-search::enter-opens-the-selected-tile
	test('Enter activates the selected tile, honouring its configured link target', async ({ page }) => {
		await openWorkspace(page)

		/*
		 * The seeded tiles link to `https://example.invalid/...`, which is
		 * unroutable on purpose — the assertion is about which target the app
		 * ASKS for, not about a page loading. Recording `window.open` and
		 * blocking navigation captures that without depending on the network,
		 * and it is the same technique image-widget.spec.ts uses for
		 * REQ-IMG-003.
		 */
		await page.evaluate(() => {
			const w = window as unknown as { __opened?: string[] }
			w.__opened = []
			const realOpen = window.open.bind(window)
			window.open = ((url?: string | URL, ...rest: unknown[]) => {
				w.__opened!.push(String(url ?? ''))
				return null as unknown as ReturnType<typeof realOpen>
			}) as typeof window.open
		})
		// Nothing should actually leave the page; if the app navigates in the
		// same tab instead, this route abort makes that visible rather than
		// letting the test wander onto a dead host.
		await page.route('https://example.invalid/**', route => route.abort())

		const input = page.locator(INPUT)
		await input.fill('Zaaksysteem')
		await expect.poll(async () => page.locator(OPTION).count(), { timeout: 15_000 }).toBe(1)

		await page.keyboard.press('Enter')

		await expect
			.poll(
				async () => page.evaluate(() => (window as unknown as { __opened?: string[] }).__opened ?? []),
				{ message: 'Enter must activate the selected tile', timeout: 15_000 },
			)
			.not.toHaveLength(0)

		const opened = await page.evaluate(() => (window as unknown as { __opened?: string[] }).__opened ?? [])
		expect(
			opened.join(' | '),
			'the activation must target the tile\'s own configured link, not a generic route',
		).toContain('example.invalid')
	})

	// @e2e tile-quick-search::escape-clears-and-returns-focus
	test('Escape clears the query, undims the grid, and moves focus out of the bar', async ({ page }) => {
		await openWorkspace(page)
		const input = page.locator(INPUT)

		await input.fill('zaak')
		await expect.poll(async () => page.locator(OPTION).count(), { timeout: 15_000 }).toBe(2)

		/*
		 * CONTROL — tiles really are dimmed before Escape, so "nothing is
		 * dimmed after" is a change and not a description of the resting
		 * state.
		 *
		 * The class is `launchpad-grid-item--dimmed`, which is what
		 * `WorkspaceApp.vue::applySearchDimming()` toggles. Getting this
		 * selector wrong is not a loud failure — a selector that matches
		 * nothing makes both the before and the after count zero, and the
		 * assertion passes while proving nothing. Hence the explicit
		 * non-zero requirement below rather than a conditional check.
		 */
		const dimmed = page.locator(DIMMED)
		await expect
			.poll(async () => dimmed.count(), {
				message: 'CONTROL: a query must dim the non-matching tiles, or the undim assertion below is vacuous',
				timeout: 15_000,
			})
			.toBeGreaterThan(0)

		await page.keyboard.press('Escape')

		await expect(input, 'Escape must clear the query').toHaveValue('')
		await expect(page.locator(OPTION), 'the result list must close').toHaveCount(0)
		await expect(input, 'focus must leave the search bar and return to the grid').not.toBeFocused()

		await expect
			.poll(async () => dimmed.count(), { message: 'every tile must return to its undimmed state', timeout: 10_000 })
			.toBe(0)

		// Whatever holds focus now must be a real element inside the app, not
		// the document body — "returns focus to the tile grid" is only true if
		// something took it.
		const focusedTag = await page.evaluate(() => document.activeElement?.tagName ?? '')
		expect(focusedTag, 'focus must land on an element, not fall back to the body').not.toBe('')
	})
})
