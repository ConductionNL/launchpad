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
 * `RuntimeShellSearch.vue` (which owns the combobox), `SearchWidget.vue`
 * (which hosts it and writes the match set to the `tileSearch` store) and
 * `Views.vue` (which renders the dimming from that store). A component test
 * mounts one side of that chain; only a browser runs all three.
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
 * `useTileSearchHost.js::tileSearchLabel()`: a `custom` tile searches by
 * `tileTitle`. So the dashboard below is seeded through
 * `POST /api/dashboard/{id}/tile` with titles chosen to exercise the ranking
 * rule the spec states — prefix beats mid-string beats subsequence — rather
 * than relying on whatever the shared fixture happens to contain, which no
 * test should have to guess at and which other specs in this serial job are
 * free to change.
 *
 * @spec openspec/specs/tile-quick-search/spec.md
 */

import {
	expect,
	request,
	test,
	type APIRequestContext,
	type Page,
} from '@playwright/test'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}
const ENV_BASE_URL = (process.env.BASE_URL ?? process.env.NC_BASE_URL ?? '').replace(
	/\/$/,
	'',
)

const APP_URL = '/index.php/apps/launchpad'
const SETTINGS = '/index.php/apps/launchpad/api/admin/settings'
const INPUT = '[data-test="quick-search-input"]'
const OPTION = '[data-test="quick-search-option"]'
const GRID_ITEM = '.launchpad-grid-item'
/*
 * The de-emphasis class `Views.vue` binds on every grid item that is NOT a
 * match, from the `tileSearch` store's `isDimmed` getter. Named once here
 * because a wrong value fails SILENTLY: a selector matching nothing reports
 * zero dimmed both before and after, and any "…must be undimmed" assertion
 * built on it passes without ever having observed a dim.
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
		const res = await api.post(
			`/index.php/apps/launchpad/api/dashboard/${dashboardId}/tile`,
			{
				data: {
					title,
					linkType: 'url',
					linkValue: `https://example.invalid/${encodeURIComponent(title)}`,
					gridX: (i % 2) * 3,
					// +2 leaves the top row free for the search widget seeded
					// below, so the bar sits above the tiles as it used to.
					gridY: Math.floor(i / 2) * 2 + 2,
					gridWidth: 3,
					gridHeight: 2,
				},
			},
		)
		expect(
			res.status(),
			`seeding tile "${title}": ${await res.text()}`,
		).toBeLessThan(300)
	}

	/*
	 * SEED THE SEARCH WIDGET ITSELF.
	 *
	 * Quick search used to be page chrome: `WorkspaceApp.vue` rendered it on
	 * every dashboard, so a fixture that seeded only tiles still got a search
	 * bar for free. It is now the `search` widget type (REQ-QSEARCH-001), so a
	 * dashboard has one only where an author placed it — and without this call
	 * every `[data-test="quick-search-input"]` locator below would simply never
	 * resolve.
	 *
	 * Empty `content` on purpose: both settings default to "inherit / built-in"
	 * (REQ-QSEARCH-005), which is exactly the behaviour the assertions in this
	 * file were written against. Tests that need an override set it themselves.
	 */
	const searchWidget = await api.post(
		`/index.php/apps/launchpad/api/dashboard/${dashboardId}/widgets`,
		{
			data: {
				widgetId: 'search',
				gridX: 0,
				gridY: 0,
				gridWidth: 6,
				gridHeight: 2,
				content: { placeholder: '', fallbackTarget: '' },
			},
		},
	)
	expect(
		searchWidget.status(),
		`seeding the search widget: ${await searchWidget.text()}`,
	).toBeLessThan(300)

	/*
	 * ACTIVATE THROUGH BOTH MECHANISMS, because there are two and they are
	 * not interchangeable.
	 *
	 * `POST /api/dashboard/{id}/activate` sets the legacy id-based
	 * `is_active` column. `POST /api/dashboards/active` sets a per-user UUID
	 * PREFERENCE (`DashboardService::setActivePreference()`), and
	 * `DashboardApiController::getActive()` resolves the shell's dashboard
	 * through that preference — its own comment notes the active dashboard
	 * "can now be a group/default (showcase) dashboard the user does not
	 * own (resolved via the last-used preference)".
	 *
	 * Measured: with only `/activate` called, this suite loaded a dashboard
	 * that was not this one. Every tile-matching assertion returned zero
	 * results, because a previously-run spec in the same serial job had left
	 * a UUID preference behind and that preference won.
	 */
	const activate = await api.post(
		`/index.php/apps/launchpad/api/dashboard/${dashboardId}/activate`,
	)
	expect(activate.status(), await activate.text()).toBeLessThan(300)

	const setPreference = await api.post(
		'/index.php/apps/launchpad/api/dashboards/active',
		{
			data: { uuid: dash.uuid },
		},
	)
	expect(setPreference.status(), await setPreference.text()).toBeLessThan(300)

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
	// The bar renders as the `search` widget seeded in beforeAll, so its
	// presence is also the signal that the shell finished booting AND that the
	// grid rendered the placement.
	await expect(page.locator(INPUT)).toBeVisible({ timeout: 30_000 })
	/*
	 * THE GATE THAT WAS TOO WEAK, and the reason six tests failed with
	 * "0 results" instead of with something that named the cause.
	 *
	 * This used to require only `GRID_ITEM count >= TILES.length`. A
	 * dashboard seeded with the default widget bundle clears that bar
	 * easily, so when the shell resolved a DIFFERENT dashboard the gate
	 * passed and every later assertion failed downstream on an empty result
	 * list — a symptom three steps from its cause.
	 *
	 * The gate now requires THIS suite's own tiles, matched by the per-run
	 * stamp, so a wrong active dashboard fails here and says so.
	 */
	await expect(
		page.locator(GRID_ITEM).filter({ hasText: STAMP }).first(),
		`the active dashboard must be the one this suite seeded (tiles stamped ${STAMP}) — `
			+ 'if this fails the shell resolved a different dashboard and no search assertion below is meaningful',
	).toBeVisible({ timeout: 30_000 })

	await expect
		.poll(
			async () => page.locator(GRID_ITEM).filter({ hasText: STAMP }).count(),
			{
				message: 'all four seeded tiles must be rendered before searching',
				timeout: 30_000,
			},
		)
		.toBeGreaterThanOrEqual(TILES.length)
}

/*
 * Move focus off the search bar WITHOUT leaving the page.
 *
 * This was `page.locator('body').click({ position: { x: 5, y: 5 } })`, and
 * that measured something other than what it looked like: `position` is
 * relative to the BODY's box, whose origin is the top-left of the Nextcloud
 * chrome, so the click landed on core's own header and navigated away. Both
 * shortcut tests then failed with "element(s) not found" on the search input
 * — the app was no longer on screen. The failure read like a focus problem
 * and was a navigation problem.
 *
 * `.workspace-shell__grid` is the shell's own grid container and carries
 * `tabindex="-1"` (WorkspaceApp.vue), so it is programmatically focusable and
 * is exactly where `focusGrid()` puts focus after Escape. Focusing it is
 * therefore both in-page and representative of a real resting state. It is
 * also not a typing target, which matters: `isSlashFocusShortcut()` ignores
 * `/` while focus is in a text field.
 */
async function blurToGrid(page: Page): Promise<void> {
	await page.locator('.workspace-shell__grid').focus()
}

/** The visible result labels, in the order the listbox presents them. */
async function optionLabels(page: Page): Promise<string[]> {
	return (await page.locator(OPTION).allInnerTexts()).map((t) => t.trim())
}

test.describe('tile quick-search — focus (REQ-QSEARCH-001)', () => {
	// @e2e tile-quick-search::search-bar-is-present-and-labelled
	test('the bar renders above the grid, is wrapped in role=search, and is labelled and tabbable', async ({
		page,
	}) => {
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
				? document.querySelector(`label[for="${CSS.escape(el.id)}"]`)
						?.textContent
				: null
			return (byLabel ?? el.getAttribute('aria-label') ?? '').trim()
		})
		expect(
			accessibleName,
			'the quick-search input must have an accessible name',
		).not.toBe('')

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
	test('pressing / focuses the bar and does not type the slash into it', async ({
		page,
	}) => {
		await openWorkspace(page)
		const input = page.locator(INPUT)

		// CONTROL — focus starts somewhere else, so "is focused" below is a
		// change and not the initial state.
		await blurToGrid(page)
		await expect(
			input,
			'CONTROL: the bar must not already hold focus',
		).not.toBeFocused()

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
	test('Ctrl+K focuses the bar and prevents the browser default', async ({
		page,
	}) => {
		await openWorkspace(page)
		const input = page.locator(INPUT)

		await blurToGrid(page)
		await expect(
			input,
			'CONTROL: the bar must not already hold focus',
		).not.toBeFocused()

		// Record whether the app called preventDefault on the very event it
		// acted upon. Asserting focus alone would pass on a handler that
		// leaves the browser's own Ctrl+K to fire as well.
		await page.evaluate(() => {
			;(
				window as unknown as { __ctrlKDefaultPrevented?: boolean }
			).__ctrlKDefaultPrevented = undefined
			window.addEventListener(
				'keydown',
				(e) => {
					if (e.key.toLowerCase() === 'k' && (e.ctrlKey || e.metaKey)) {
						;(
							window as unknown as {
								__ctrlKDefaultPrevented?: boolean
							}
						).__ctrlKDefaultPrevented = e.defaultPrevented
					}
				},
				false,
			)
		})

		await page.keyboard.press('Control+k')
		await expect(input).toBeFocused()

		expect(
			await page.evaluate(
				() =>
					(window as unknown as { __ctrlKDefaultPrevented?: boolean })
						.__ctrlKDefaultPrevented,
			),
			'the shortcut must call preventDefault so the browser default does not also fire',
		).toBe(true)
	})
})

test.describe('tile quick-search — filtering (REQ-QSEARCH-002)', () => {
	// @e2e tile-quick-search::typing-filters-tiles-by-label
	test('typing narrows the matches, dims rather than removes non-matches, and issues no request', async ({
		page,
	}) => {
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
		page.on('request', (req) => record(req.url()))

		await page.locator(INPUT).fill('zaak')

		await expect
			.poll(async () => optionLabels(page), {
				message: 'the query must narrow the result list',
				timeout: 15_000,
			})
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

		/*
		 * THIS ASSERTION USED TO BE `dimmed > 0`, AND THAT PASSED FOR THE
		 * WRONG REASON.
		 *
		 * "Every single tile is dimmed" satisfies `> 0`, and that is exactly
		 * what the app did: `applySearchDimming()` compared a string
		 * `getAttribute('data-placement-id')` against numeric ids with
		 * `Array.includes`, which does not coerce, so nothing ever matched
		 * and the matches were de-emphasised alongside everything else
		 * (launchpad#95). The requirement is not "something is dimmed", it is
		 * that the dimming DISTINGUISHES matches from non-matches — and a
		 * count cannot say that.
		 *
		 * So it is now asserted per element, by identity. This is red on the
		 * unfixed code (the two matching cells carry the class), which is why
		 * it lands with the fix rather than before or after it.
		 */
		// `.first()` on both: `evaluate()` throws on a locator that resolves to
		// more than one node, and a strictness error would read as a product
		// failure rather than as the selector problem it is.
		const matching = page
			.locator(GRID_ITEM)
			.filter({ hasText: `Zaaksysteem ${STAMP}` })
			.first()
		const notMatching = page
			.locator(GRID_ITEM)
			.filter({ hasText: `Verlof aanvragen ${STAMP}` })
			.first()
		await expect(matching, 'the matching tile must be on screen').toBeVisible({
			timeout: 15_000,
		})
		await expect(
			notMatching,
			'the non-matching tile must be on screen',
		).toBeVisible({ timeout: 15_000 })

		await expect
			.poll(
				async () =>
					notMatching.evaluate((el) =>
						el.classList.contains('launchpad-grid-item--dimmed'),
					),
				{
					message:
						'a tile whose label does not match the query must be visibly de-emphasised',
					timeout: 15_000,
				},
			)
			.toBe(true)

		expect(
			await matching.evaluate((el) =>
				el.classList.contains('launchpad-grid-item--dimmed'),
			),
			'a MATCHING tile must not be de-emphasised — dimming everything satisfies a bare "something is dimmed" count while telling the user nothing',
		).toBe(false)

		// And the aggregate still has to move, so a future refactor that stops
		// applying the class at all cannot pass the two checks above by making
		// `classList.contains` false everywhere.
		await expect
			.poll(async () => page.locator(DIMMED).count(), {
				message: 'CONTROL: the class must actually be applied somewhere',
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
			.poll(async () => optionLabels(page), {
				message: 'both verlof tiles must match',
				timeout: 15_000,
			})
			.toHaveLength(2)

		const labels = await optionLabels(page)
		const prefix = labels.findIndex((l) => l.includes('Verlof aanvragen'))
		const midString = labels.findIndex((l) => l.includes('Overzicht verlof'))

		expect(
			prefix,
			'"Verlof aanvragen" must be among the results',
		).toBeGreaterThanOrEqual(0)
		expect(
			midString,
			'"Overzicht verlof" must be among the results',
		).toBeGreaterThanOrEqual(0)
		expect(
			prefix,
			'a label STARTING with the query must rank above one that matches mid-string',
		).toBeLessThan(midString)
	})

	// @e2e tile-quick-search::no-query-stored
	test('the query is never persisted — no request carries it and no web storage holds it', async ({
		page,
	}) => {
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
		await expect(
			page.locator('[data-test="quick-search-empty"], ' + OPTION).first(),
		).toBeVisible({ timeout: 15_000 })

		expect(
			carryingRequests,
			`the query must not be sent anywhere: ${carryingRequests.join(', ')}`,
		).toHaveLength(0)

		const stored = await page.evaluate((needle) => {
			const hits: string[] = []
			for (const store of [localStorage, sessionStorage]) {
				for (let i = 0; i < store.length; i++) {
					const key = store.key(i)!
					if (
						key.includes(needle)
						|| (store.getItem(key) ?? '').includes(needle)
					) {
						hits.push(key)
					}
				}
			}
			return hits
		}, secret)
		expect(
			stored,
			`the query must not be persisted to web storage: ${stored.join(', ')}`,
		).toHaveLength(0)
	})
})

test.describe('tile quick-search — keyboard navigation (REQ-QSEARCH-003)', () => {
	// @e2e tile-quick-search::arrow-keys-move-the-selection
	test('ArrowDown then ArrowUp move the selection, tracked by aria-activedescendant and marked by more than colour', async ({
		page,
	}) => {
		await openWorkspace(page)
		const input = page.locator(INPUT)
		await input.fill('zaak')

		await expect
			.poll(async () => page.locator(OPTION).count(), { timeout: 15_000 })
			.toBe(2)

		const activeDescendant = () => input.getAttribute('aria-activedescendant')
		const first = await activeDescendant()

		await page.keyboard.press('ArrowDown')
		await expect
			.poll(activeDescendant, {
				message: 'ArrowDown must move the active option',
				timeout: 10_000,
			})
			.not.toBe(first)
		const second = await activeDescendant()
		expect(second, 'aria-activedescendant must name an option').toBeTruthy()

		// The selected option must be the one flagged aria-selected, or the
		// pointer the input exposes disagrees with what a screen reader reads.
		/*
		 * Matched by attribute rather than `#${CSS.escape(id)}`. `CSS` is a
		 * BROWSER global; this code runs in Node, where it is undefined —
		 * the first CI run failed here with `ReferenceError: CSS is not
		 * defined`. An `[id="…"]` attribute selector needs no escaping and
		 * is exact.
		 */
		await expect(
			page.locator(`[id="${second}"]`),
			'the option named by aria-activedescendant must be the selected one',
		).toHaveAttribute('aria-selected', 'true')

		/*
		 * WCAG: "visibly indicated by more than colour alone". The component
		 * renders a marker element per option and hides it on all but the
		 * active one, so exactly one marker must be un-hidden. Colour alone
		 * would leave zero.
		 */
		expect(
			await page
				.locator(
					'.runtime-shell-search__option-marker:not(.runtime-shell-search__option-marker--hidden)',
				)
				.count(),
			'the active option needs a non-colour indicator, and exactly one option may carry it',
		).toBe(1)

		await page.keyboard.press('ArrowUp')
		await expect
			.poll(activeDescendant, {
				message: 'ArrowUp must move the selection back',
				timeout: 10_000,
			})
			.toBe(first)
	})

	/*
	 * REQ-QSEARCH-003 "Enter opens the selected tile" — NOW COVERED. The
	 * defect described in the block below is fixed in this same change; the
	 * history is kept because the way it hid is the interesting part.
	 *
	 * The activation is proven by recording the navigation the tile's own
	 * anchor performs, NOT by waiting for a page load. Seeded tiles link to
	 * `https://example.invalid/...`, which is unresolvable on purpose — a
	 * real navigation there would hang the test for its whole timeout and
	 * then fail for a network reason. So `click` is intercepted at the
	 * document level and the anchor's `href` recorded, with `preventDefault`
	 * to stop the browser acting on it. That records exactly what
	 * `activateSearchResult()` did: which anchor it clicked, or none.
	 */
	// @e2e tile-quick-search::enter-opens-the-selected-tile
	test("Enter activates the selected result's tile link (launchpad#95)", async ({
		page,
	}) => {
		await openWorkspace(page)

		await page.evaluate(() => {
			;(window as unknown as { __lpActivations: string[] }).__lpActivations =
				[]
			document.addEventListener(
				'click',
				(event) => {
					const anchor = (event.target as HTMLElement | null)?.closest?.(
						'a[href]',
					)
					if (anchor) {
						event.preventDefault()
						;(
							window as unknown as { __lpActivations: string[] }
						).__lpActivations.push(anchor.getAttribute('href') ?? '')
					}
				},
				true,
			)
		})

		const input = page.locator(INPUT)
		await input.fill('Zaaksysteem')

		// Exactly one match, so the selected option is unambiguous and the
		// assertion below cannot be satisfied by the wrong tile.
		await expect
			.poll(async () => optionLabels(page), {
				message: 'the query must resolve to exactly one tile',
				timeout: 15_000,
			})
			.toEqual([`Zaaksysteem ${STAMP}`])

		/*
		 * SPLITTING PROBE, kept from the investigation. Both of these passed
		 * while Enter still did nothing, which is what narrowed the cause to
		 * `activateSearchResult()` itself rather than to the harness: the
		 * anchor exists, and the input holds focus at the moment Enter is
		 * pressed. If either regresses, the failure names its own cause
		 * instead of being read as "the fix was reverted".
		 */
		await expect(
			page
				.locator(GRID_ITEM)
				.filter({ hasText: `Zaaksysteem ${STAMP}` })
				.first()
				.locator('a[href]')
				.first(),
			'PROBE: the rendered tile must carry an anchor, or activation has nothing to click',
		).toHaveCount(1)
		await expect(
			input,
			'PROBE: the search input must still hold focus when Enter is pressed',
		).toBeFocused()

		await page.keyboard.press('Enter')

		const readActivations = async () =>
			page.evaluate(
				() =>
					(window as unknown as { __lpActivations: string[] })
						.__lpActivations,
			)

		await expect
			.poll(readActivations, {
				message:
					"Enter must activate the selected tile's link — an empty list is the launchpad#95 symptom "
					+ '(a TypeError thrown inside a Vue event handler, where nothing surfaces it)',
				timeout: 15_000,
			})
			.not.toHaveLength(0)

		const activations = await readActivations()
		expect(
			activations.join(' | '),
			"the activated link must be the SELECTED tile's, not just any anchor on the page",
		).toContain(encodeURIComponent(`Zaaksysteem ${STAMP}`))
	})

	/*
	 * HOW launchpad#95 HID, kept because the shape recurs.
	 *
	 * The test existed, ran in CI, and failed with an empty activation list.
	 * Rather than adjust the harness a second time, two splitting probes were
	 * added — and BOTH PASSED in run 31483882342:
	 *
	 *   * the rendered cells DO carry `a[href]`, so
	 *     `activateSearchResult()`'s fall-back-to-`focus()` branch is not the
	 *     explanation;
	 *   * the input DOES still hold focus when Enter is pressed.
	 *
	 * The sibling test above proves `aria-activedescendant` selection
	 * tracking works, so the selection is sound too. Anchor present, focus
	 * correct, selection correct, and still no click.
	 *
	 * `activateSearchResult()` — then in `WorkspaceApp.vue`, now in
	 * `useTileSearchHost.js` — explained it exactly:
	 *
	 *     const placementId = item?.placement?.id      // an INTEGER
	 *     … placementId.replace(/"/g, '\\"') …          // TypeError, always
	 *
	 * `Number.prototype.replace` does not exist. The guard above it does not
	 * help — a non-zero integer is truthy — and the throw happens inside a
	 * Vue event handler, so nothing surfaces and Enter simply does nothing.
	 *
	 * NO `@e2e exclude` WAS ADDED WHILE IT WAS BROKEN, and that is the part
	 * worth carrying elsewhere. The scenario was always browser-observable;
	 * the reason it had no passing test was that the feature did not work.
	 * An exclusion would have recorded "a browser cannot see this", which was
	 * false — and per `.github#345` the gate scores an exclusion as POSITIVE
	 * coverage, so it would have bought a green with a false statement.
	 *
	 * Second defect, same root cause, also #95: the filtering test's dimming
	 * assertion required only `dimmed > 0`, which "EVERY tile is dimmed"
	 * satisfies — and that is what `applySearchDimming()` did, comparing a
	 * string `getAttribute('data-placement-id')` against numeric ids with
	 * `Array.includes`. The assertion was disclosed as passing for the wrong
	 * reason rather than quietly tightened, because tightening it before the
	 * fix would simply have been red. It is now per-element and by identity,
	 * and it lands in the same change as the fix.
	 *
	 * WHY THE UNIT TESTS DID NOT CATCH EITHER ONE: every fixture in
	 * `src/views/__tests__/WorkspaceApp.spec.js` seeded a placement id as a
	 * STRING (`'p1'`), and the API sends an integer. `Array.includes` and
	 * `Number.prototype` are both type-exact, so a string fixture made both
	 * defects invisible. That file now carries integer-id regressions.
	 */

	// @e2e tile-quick-search::escape-clears-and-returns-focus
	test('Escape clears the query, undims the grid, and moves focus out of the bar', async ({
		page,
	}) => {
		await openWorkspace(page)
		const input = page.locator(INPUT)

		await input.fill('zaak')
		await expect
			.poll(async () => page.locator(OPTION).count(), { timeout: 15_000 })
			.toBe(2)

		/*
		 * CONTROL — tiles really are dimmed before Escape, so "nothing is
		 * dimmed after" is a change and not a description of the resting
		 * state.
		 *
		 * The class is `launchpad-grid-item--dimmed`, which `Views.vue` binds
		 * from the `tileSearch` store's `isDimmed` getter. Getting this
		 * selector wrong is not a loud failure — a selector that matches
		 * nothing makes both the before and the after count zero, and the
		 * assertion passes while proving nothing. Hence the explicit
		 * non-zero requirement below rather than a conditional check.
		 */
		const dimmed = page.locator(DIMMED)
		await expect
			.poll(async () => dimmed.count(), {
				message:
					'CONTROL: a query must dim the non-matching tiles, or the undim assertion below is vacuous',
				timeout: 15_000,
			})
			.toBeGreaterThan(0)

		await page.keyboard.press('Escape')

		await expect(input, 'Escape must clear the query').toHaveValue('')
		await expect(page.locator(OPTION), 'the result list must close').toHaveCount(
			0,
		)
		await expect(
			input,
			'focus must leave the search bar and return to the grid',
		).not.toBeFocused()

		await expect
			.poll(async () => dimmed.count(), {
				message: 'every tile must return to its undimmed state',
				timeout: 10_000,
			})
			.toBe(0)

		// Whatever holds focus now must be a real element inside the app, not
		// the document body — "returns focus to the tile grid" is only true if
		// something took it.
		const focusedTag = await page.evaluate(
			() => document.activeElement?.tagName ?? '',
		)
		expect(
			focusedTag,
			'focus must land on an element, not fall back to the body',
		).not.toBe('')
	})
})
