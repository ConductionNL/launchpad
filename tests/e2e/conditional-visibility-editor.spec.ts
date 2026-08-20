/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The conditional-visibility rule builder — the "Visibility rules…" modal
 * reached from a placement's right-click context menu
 * (conditional-visibility-editor REQ-CVUI-001..005).
 *
 * WHY A BROWSER
 * =============
 * Almost every claim in this spec is either about the DOM (which section a
 * row sits in, whether the include/exclude distinction survives without
 * colour, what the empty state says) or about the exact request body a row
 * emits when the author presses Save. The request body half is the
 * interesting one: REQ-CVUI-001 says the editor MUST NOT introduce a new
 * persistence path or alter the stored rule shape, and the only way to check
 * that is to watch what the browser actually sends. So these tests assert on
 * intercepted request bodies rather than on the row re-rendering, which a
 * purely local component could fake.
 *
 * WHAT IS DELIBERATELY NOT HERE, AND WHY IT IS NOT AN @e2e exclude
 * ================================================================
 * Five scenarios in this spec need the author to PICK A GROUP, and the group
 * picker has no options to pick from — `Views.vue` renders
 * `<VisibilityRulesModal :open :placement-id>` and never passes
 * `available-groups`, so the prop keeps its `default: () => []`. Measured in
 * a browser: the combobox reports "No results", and typing a group name and
 * pressing Enter selects nothing (it is not taggable). The same empty list
 * feeds the preview-as-audience picker, so "preview as groups [marketing]"
 * cannot be performed either.
 *
 *   REQ-CVUI-001 add-a-group-inclusion-rule-through-the-ui
 *   REQ-CVUI-002 group-row-operands
 *   REQ-CVUI-004 preview-shows-visible-for-a-matching-audience
 *   REQ-CVUI-004 preview-shows-hidden-for-a-non-matching-audience
 *   REQ-CVUI-004 preview-reflects-an-exclude-override
 *
 * Those five carry NO `@e2e exclude`. An exclusion states "a browser cannot
 * observe this scenario", which is false — a browser observes it perfectly
 * well, and what it observes is that the feature is not wired. Per
 * `.github#345` the gate scores an exclusion as POSITIVE coverage, so
 * excluding them would buy five findings with a false statement. Filed
 * instead as launchpad#97.
 *
 * The group RULE ITSELF works end to end when seeded through the API — it is
 * only the picker's option list that is missing — which is why the tests
 * below can still load, render, preview and evaluate group rules.
 *
 * A SECOND BLOCKER, FOUND WHILE WRITING THESE TESTS: NO EXCLUDE RULE CAN BE
 * CREATED AT ALL (launchpad#96)
 * ==========================================================================
 * `POST /api/widgets/{id}/rules` with `isInclude: false` answers **HTTP 400
 * `{"error":"Operation failed"}`**. The identical body with `isInclude: true`
 * answers 201. `PUT /api/rules/{id}` with `{"isInclude": false}` is refused
 * the same way, so there is no path to an exclude rule — not through the API,
 * not through this editor.
 *
 * Root cause is a type mismatch: `oc_launchpad_conditional_rules.is_include`
 * is `smallint NOT NULL DEFAULT 1`, while `ConditionalRule` declares
 * `protected bool $isInclude` plus `addType('isInclude', 'boolean')`, so
 * `QBMapper::getParameterTypeForProperty()` binds `PARAM_BOOL`. On PostgreSQL
 * — the shared workflow's DEFAULT database, so this is the CI fixture too —
 * a boolean will not go into a smallint column. The controller reports it
 * through `ResponseHelper::error()` WITHOUT a logger, so the real exception is
 * written nowhere and the client sees only the generic message.
 *
 * That costs `include-rules-grouped-under-an-or-heading`, which needs a saved
 * exclude rule to exist. It is left uncovered and unexcluded for the same
 * reason as the five above.
 *
 * `includeexclude-toggle` IS still covered, deliberately and narrowly: that
 * requirement is about what the ROW emits and where the row moves to, and
 * both are observable before the server ever sees the request. The test
 * asserts exactly that and does not assert persistence, which would be
 * asserting #97 is fixed.
 *
 * @spec openspec/specs/conditional-visibility-editor/spec.md
 */

import {
	expect,
	request,
	test,
	type APIRequestContext,
	type Locator,
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

const EDITOR = '[data-test="conditional-visibility-editor"]'
const ROW_INCLUDE = '[data-test="visibility-rule-row-include"]'
const ROW_EXCLUDE = '[data-test="visibility-rule-row-exclude"]'
const ADD_RULE = '[data-test="add-rule"]'
const EMPTY_STATE = '[data-test="visibility-empty-state"]'
const INCLUDE_SECTION = '[data-test="include-section"]'
const EXCLUDE_SECTION = '[data-test="exclude-section"]'
const RUN_PREVIEW = '[data-test="run-preview"]'
const PREVIEW_VERDICT = '[data-test="preview-verdict-text"]'

/*
 * `VisibilityRuleRow.vue`'s own root carries `data-test="visibility-rule-row"`,
 * but the parent passes `data-test="visibility-rule-row-include"` /
 * `-exclude` on the `<VisibilityRuleRow>` tag — and a fallthrough attribute
 * OVERWRITES the child's own. So `visibility-rule-row` never appears in the
 * DOM at all. Written down because a selector that matches nothing fails as a
 * timeout, which reads like the modal not opening.
 */

/** The rule-type options, in the order `typeOptions` declares them. */
const TYPE = { group: 0, time: 1, date: 2, attribute: 3 } as const

const STAMP = `${Date.now()}`

/*
 * A `startDate` that is open-ended and already past, so a rule carrying it
 * MATCHES right now.
 *
 * This is load-bearing, not decoration. Under include=OR a placement with
 * include rules and no match is HIDDEN — correctly — and a hidden placement
 * cannot be right-clicked, so the editor cannot be reopened to inspect the
 * very rules that hid it. Any fixture that seeds a non-matching include rule
 * must therefore seed a matching one alongside it, or it locks itself out of
 * the surface under test. (Measured: seeding a lone `groups:["marketing"]`
 * rule as admin made the tile vanish and the next right-click time out.)
 */
const MATCHING_START_DATE = `${new Date().getUTCFullYear() - 1}-01-01`
const KEEP_VISIBLE = {
	ruleType: 'date',
	ruleConfig: { startDate: MATCHING_START_DATE },
	isInclude: true,
} as const

function basic(user: string, pass: string): string {
	return `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`
}

/*
 * An API context that is unambiguously the admin. The jar is spelled
 * `{ cookies: [], origins: [] }` and NOT `storageState: undefined` — option
 * merging reads an explicit `undefined` as "not supplied" and falls back to
 * the project default, which is the very session this is meant to drop.
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

let dashboardId = 0
let dashboardUuid = ''
let placementId = 0
let priorAllowUserDash = true

/** Every rule currently stored against the shared placement. */
async function storedRules(
	api: APIRequestContext,
): Promise<Array<Record<string, unknown>>> {
	const res = await api.get(
		`/index.php/apps/launchpad/api/widgets/${placementId}/rules`,
	)
	expect(res.status(), `reading stored rules: ${await res.text()}`).toBe(200)
	return (await res.json()).rules ?? []
}

/** Remove every rule on the shared placement, so each test starts clean. */
async function clearRules(api: APIRequestContext): Promise<void> {
	for (const rule of await storedRules(api)) {
		const res = await api.delete(
			`/index.php/apps/launchpad/api/rules/${rule.id}`,
		)
		expect(
			res.status(),
			`clearing rule ${rule.id}: ${await res.text()}`,
		).toBeLessThan(300)
	}
	expect(
		await storedRules(api),
		'the placement must start each test with no rules',
	).toHaveLength(0)
}

/** Seed one rule directly, bypassing the UI. Returns its server id. */
async function seedRule(
	api: APIRequestContext,
	rule: {
		ruleType: string
		ruleConfig: Record<string, unknown>
		isInclude: boolean
	},
): Promise<number> {
	const res = await api.post(
		`/index.php/apps/launchpad/api/widgets/${placementId}/rules`,
		{ data: rule },
	)
	expect(
		res.status(),
		`seeding ${rule.ruleType} rule: ${await res.text()}`,
	).toBeLessThan(300)
	const body = await res.json()
	return Number(body.id ?? body.rule?.id)
}

test.beforeAll(async () => {
	const api = await adminApi()

	const before = await api.get(SETTINGS)
	expect(before.status(), await before.text()).toBe(200)
	priorAllowUserDash = (await before.json()).allowUserDashboards === true
	const enable = await api.put(SETTINGS, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)

	const created = await api.post('/index.php/apps/launchpad/api/dashboard', {
		data: { name: `E2E Visibility ${STAMP}` },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	dashboardId = Number(dash.id)
	dashboardUuid = String(dash.uuid)
	expect(dashboardId, `no dashboard id: ${JSON.stringify(body)}`).toBeTruthy()

	const tile = await api.post(
		`/index.php/apps/launchpad/api/dashboard/${dashboardId}/tile`,
		{
			data: {
				title: `Visibility subject ${STAMP}`,
				linkType: 'url',
				linkValue: `https://example.invalid/${STAMP}`,
				gridX: 0,
				gridY: 0,
				gridWidth: 3,
				gridHeight: 2,
			},
		},
	)
	expect(
		tile.status(),
		`seeding the subject tile: ${await tile.text()}`,
	).toBeLessThan(300)
	placementId = Number((await tile.json()).id)
	expect(placementId, 'no placement id for the subject tile').toBeTruthy()

	/*
	 * Activate through BOTH mechanisms. `/activate` sets the id-based
	 * `is_active` column; `POST /api/dashboards/active` sets the per-user UUID
	 * PREFERENCE, and the shell resolves through the preference. With only the
	 * first called, a preference left behind by an earlier spec in this serial
	 * job wins and every assertion below runs against someone else's dashboard.
	 */
	const activate = await api.post(
		`/index.php/apps/launchpad/api/dashboard/${dashboardId}/activate`,
	)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const preference = await api.post(
		'/index.php/apps/launchpad/api/dashboards/active',
		{
			data: { uuid: dashboardUuid },
		},
	)
	expect(preference.status(), await preference.text()).toBeLessThan(300)

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

/** Load the workspace on the seeded dashboard, in edit mode. */
async function openWorkspaceInEditMode(page: Page): Promise<void> {
	await page.goto(APP_URL)

	/*
	 * Gate on THIS suite's own tile, not on a bare grid-item count. A
	 * dashboard seeded with the default bundle clears a count gate easily, so
	 * when the shell resolves a different dashboard a count passes and every
	 * later assertion fails downstream with a symptom three steps from its
	 * cause.
	 */
	await expect(
		page.locator('.launchpad-grid-item').filter({ hasText: STAMP }).first(),
		`the active dashboard must be the one this suite seeded (tile stamped ${STAMP})`,
	).toBeVisible({ timeout: 30_000 })

	/*
	 * IDEMPOTENT ON PURPOSE. Edit mode is server-side state that SURVIVES a
	 * reload, so on every test after the first the dashboard is already in it
	 * and the cog menu offers "Stop editing" rather than "Edit dashboard".
	 * The first draft of this helper clicked unconditionally, and every test
	 * but the first then failed at the *next* step — the context menu never
	 * opened — which reads like a context-menu bug and is not one.
	 */
	if ((await page.locator('.launchpad-edit-mode').count()) === 0) {
		await page.locator('.launchpad-sidebar-toggle').first().click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', {
			timeout: 10_000,
		})
		/*
		 * `.active`, NOT `.first()`. The sidebar lists every personal
		 * dashboard, and the first one is whichever sorts first — not
		 * necessarily this suite's. Opening a different row's cog menu and
		 * choosing "Edit dashboard" SWITCHES the active dashboard, so the
		 * grid then holds someone else's tiles and the right-click below
		 * cannot find the stamped one.
		 *
		 * Measured: with `.first()` the suite entered edit mode on a stale
		 * dashboard left behind by an earlier run, and failed at the
		 * right-click with "locator not found" — three steps from the cause,
		 * and reading as a context-menu defect.
		 */
		const activeRow = page
			.locator('[data-source="user"].dashboard-switcher-sidebar__item.active')
			.first()
		await expect(
			activeRow,
			'the active dashboard must be a personal one this suite owns before edit mode is entered',
		).toBeVisible({ timeout: 10_000 })
		await activeRow.locator('.dashboard-row-actions button').first().click()
		await page.getByRole('menuitem', { name: /edit dashboard/i }).click()
		await page.waitForSelector('.launchpad-edit-mode', { timeout: 10_000 })

		// Close the sidebar so it cannot occlude the grid for the right-click.
		const closeBtn = page.locator('.dashboard-switcher-sidebar__close').first()
		if (await closeBtn.isVisible().catch(() => false)) {
			await closeBtn.click()
			await page
				.waitForFunction(
					() =>
						!document.querySelector('.dashboard-switcher-sidebar.open'),
					{ timeout: 5_000 },
				)
				.catch(() => null)
		}
	}

	await expect(
		page.locator('.launchpad-edit-mode'),
		'the grid must be in edit mode, or the placement context menu never opens',
	).toHaveCount(1)

	// Re-gate AFTER edit mode. Entering it goes through the dashboard switcher,
	// which is exactly the step that can change which dashboard is active, so
	// the gate taken before it is not still true here.
	await expect(
		page.locator('.launchpad-grid-item').filter({ hasText: STAMP }).first(),
		`edit mode must have been entered on THIS suite's dashboard (tile stamped ${STAMP})`,
	).toBeVisible({ timeout: 30_000 })
}

/** Open the Visibility rules modal on the seeded placement. */
async function openVisibilityEditor(page: Page): Promise<void> {
	await openWorkspaceInEditMode(page)
	await page
		.locator('.launchpad-grid-item')
		.filter({ hasText: STAMP })
		.first()
		.click({ button: 'right' })
	await page.locator('[data-testid="ctx-visibility-rules"]').click()
	await expect(
		page.locator(EDITOR),
		'the visibility editor must mount',
	).toBeVisible({ timeout: 15_000 })
	// The editor renders its loading spinner in place of the body, so waiting
	// for the Add-rule button is what says the rules fetch has settled.
	await expect(page.locator(ADD_RULE)).toBeVisible({ timeout: 15_000 })
}

/**
 * Record every non-GET launchpad API request the page makes, with its body.
 * The whole point of REQ-CVUI-001 is which endpoint and which shape, so the
 * wire is the evidence — not the re-rendered row.
 */
function recordWrites(
	page: Page,
): Array<{ method: string; url: string; body: string }> {
	const writes: Array<{ method: string; url: string; body: string }> = []
	page.on('request', (req) => {
		if (req.method() !== 'GET' && req.url().includes('/apps/launchpad/api/')) {
			writes.push({
				method: req.method(),
				url: req.url(),
				body: req.postData() ?? '',
			})
		}
	})
	return writes
}

/**
 * Activate an `NcCheckboxRadioSwitch` (radio, switch or checkbox).
 *
 * The component's `data-test` lands on its `<input>`, and that input is
 * VISUALLY HIDDEN. Playwright resolves it happily and then waits forever for
 * it to become clickable, which times out as
 * `locator.click: Timeout ... exceeded` — indistinguishable from the control
 * being missing or disabled.
 *
 * AND THERE IS NO `<label>` TO CLICK INSTEAD. The measured markup is:
 *
 *   <span class="checkbox-radio-switch …">
 *     <input id="checkbox-radio-switch-nc-vue-38"
 *            aria-labelledby="nc-vue-39"
 *            class="checkbox-radio-switch__input" data-test="…">
 *     <span id="checkbox-radio-switch-nc-vue-38-label"
 *           class="checkbox-content … checkbox-radio-switch__content">…</span>
 *   </span>
 *
 * The visible control is a `<span>` whose id is the input's id plus `-label`,
 * and the association is `aria-labelledby` only. So `label[for=…]` matches
 * nothing — which is the second timeout this cost — and the element a user
 * actually clicks is `#${inputId}-label`.
 *
 * `force: true` would also make the click "work" and is deliberately not used:
 * it dispatches an event at an element a real user cannot reach, so the test
 * would stay green even if the control became genuinely unreachable.
 */
async function activateSwitch(input: Locator): Promise<void> {
	const id = await input.getAttribute('id')
	expect(
		id,
		'an NcCheckboxRadioSwitch input must carry an id its content span can hang off',
	).toBeTruthy()
	await input.page().locator(`[id="${id}-label"]`).click()
}

/** Pick a rule type in a row's `NcSelect` by its position in `typeOptions`. */
async function chooseType(page: Page, row: Locator, index: number): Promise<void> {
	await row.locator('[data-test="rule-type-select"] input').first().click()
	/*
	 * Matched by POSITION, not by label text. `NcSelect` renders each option
	 * through nc-vue's `name-parts`, which splits the label across two spans
	 * ("Time o" + "f day"), so `hasText: /Time of day/` does not match the
	 * rendered text nodes. The order is fixed by `typeOptions`.
	 */
	const options = page.locator('.vs__dropdown-option')
	await expect(
		options,
		'the rule-type select must offer all four types',
	).toHaveCount(4)
	await options.nth(index).click()
}

/** The single visible rule row, whichever section it currently sits in. */
function anyRow(page: Page): Locator {
	return page.locator(`${ROW_INCLUDE}, ${ROW_EXCLUDE}`).first()
}

/** The last write matching a method + URL fragment, as parsed JSON. */
function lastWrite(
	writes: Array<{ method: string; url: string; body: string }>,
	method: string,
	fragment: string,
): Record<string, unknown> {
	const hit = [...writes]
		.reverse()
		.find((w) => w.method === method && w.url.includes(fragment))
	expect(
		hit,
		`no ${method} to a URL containing "${fragment}" was sent. Seen: ${writes.map((w) => `${w.method} ${w.url}`).join(' | ') || '(none)'}`,
	).toBeTruthy()
	return JSON.parse(hit!.body || '{}')
}

test.describe('conditional-visibility editor — rule builder (REQ-CVUI-001)', () => {
	test.beforeEach(async () => {
		const api = await adminApi()
		await clearRules(api)
		await api.dispose()
	})

	// @e2e conditional-visibility-editor::visibility-section-appears-in-the-settings-panel
	test("the Visibility section loads the placement's existing rules and renders each as a populated row", async ({
		page,
	}) => {
		// Both seeded as INCLUDE rules: an exclude rule cannot be created at
		// all (launchpad#96), so a mixed fixture would fail in the seed rather
		// than in the assertion, and say nothing about this scenario.
		const api = await adminApi()
		await seedRule(api, {
			ruleType: 'group',
			ruleConfig: { groups: ['marketing'] },
			isInclude: true,
		})
		await seedRule(api, { ...KEEP_VISIBLE })
		await api.dispose()

		// The spec requires the section to LOAD the rules over
		// `GET /api/widgets/{id}/rules`, so the request itself is asserted, not
		// only its visible effect.
		const rulesFetches: string[] = []
		page.on('request', (req) => {
			if (
				req.method() === 'GET'
				&& req.url().includes(`/api/widgets/${placementId}/rules`)
			) {
				rulesFetches.push(req.url())
			}
		})

		await openVisibilityEditor(page)

		expect(
			rulesFetches.length,
			`the editor must load rules via GET /api/widgets/${placementId}/rules`,
		).toBeGreaterThan(0)

		// Each loaded rule renders as its own row.
		await expect(
			page.locator(ROW_INCLUDE),
			'both stored rules must render as rows',
		).toHaveCount(2)

		// …with their operands POPULATED and TYPE-CORRECT, not merely present.
		// A pair of rows rendered with empty configs would satisfy a bare count,
		// and so would two rows of the same type.
		await expect(
			page
				.locator(ROW_INCLUDE)
				.nth(0)
				.locator('[data-test="rule-type-select"]'),
			'the first row must show the stored group rule type',
		).toContainText(/Group/)
		await expect(
			page
				.locator(ROW_INCLUDE)
				.nth(1)
				.locator('[data-test="rule-start-date"]'),
			'the second row must show the stored startDate operand',
		).toHaveValue(MATCHING_START_DATE)
	})

	// @e2e conditional-visibility-editor::edit-an-existing-rule-through-the-ui
	test('editing a row sends PUT /api/rules/{id} with the updated ruleConfig', async ({
		page,
	}) => {
		/*
		 * The spec's GIVEN names a GROUP rule and adds a group to it. The group
		 * operand cannot be edited through the UI at all (launchpad#97 — the
		 * picker has no options), so the same requirement is exercised on an
		 * ATTRIBUTE rule, whose operands are free-text. What is under test here
		 * is the requirement's actual claim — that an edit goes out as
		 * `PUT /api/rules/{id}` carrying the updated `ruleConfig`, and that the
		 * row reflects it — and that claim is type-independent. Recorded rather
		 * than quietly substituted.
		 */
		const api = await adminApi()
		const ruleId = await seedRule(api, {
			ruleType: 'attribute',
			ruleConfig: { attribute: 'language', operator: 'equals', value: 'nl' },
			isInclude: true,
		})
		// The attribute rule above does not match this account, and a
		// placement with only non-matching include rules is hidden — see
		// KEEP_VISIBLE.
		await seedRule(api, { ...KEEP_VISIBLE })
		await api.dispose()

		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		const row = page.locator(ROW_INCLUDE).first()
		await expect(row.locator('[data-test="rule-value"]')).toHaveValue('nl')

		await row.locator('[data-test="rule-value"]').fill('de')
		await row.locator('[data-test="rule-save"]').click()

		await expect
			.poll(
				() =>
					writes.filter(
						(w) =>
							w.method === 'PUT'
							&& w.url.includes(`/api/rules/${ruleId}`),
					).length,
				{
					message: `an edit must be persisted with PUT /api/rules/${ruleId}`,
					timeout: 15_000,
				},
			)
			.toBeGreaterThan(0)

		const sent = lastWrite(writes, 'PUT', `/api/rules/${ruleId}`)
		expect(sent.ruleConfig, 'the PUT must carry the updated ruleConfig').toEqual(
			{
				attribute: 'language',
				operator: 'equals',
				value: 'de',
			},
		)

		// And it must really be stored — a request that the server rejected
		// would still show up in `writes`.
		const api2 = await adminApi()
		const stored = await storedRules(api2)
		await api2.dispose()
		const edited = stored.find((r) => r.ruleType === 'attribute')
		expect(
			edited,
			`the edited attribute rule must still exist: ${JSON.stringify(stored)}`,
		).toBeTruthy()
		expect((edited!.ruleConfig as Record<string, unknown>).value).toBe('de')
	})

	// @e2e conditional-visibility-editor::delete-a-rule-through-the-ui
	test('removing a row sends DELETE /api/rules/{id} and the row disappears', async ({
		page,
	}) => {
		// Seeded MATCHING, so the placement stays visible and right-clickable
		// while the rule exists — see KEEP_VISIBLE.
		const api = await adminApi()
		const ruleId = await seedRule(api, { ...KEEP_VISIBLE })
		await api.dispose()

		const writes = recordWrites(page)
		await openVisibilityEditor(page)
		await expect(
			page.locator(ROW_INCLUDE),
			'CONTROL: the row must exist before it can be removed',
		).toHaveCount(1)

		await page
			.locator(ROW_INCLUDE)
			.first()
			.locator('[data-test="rule-remove"]')
			.click()

		await expect
			.poll(
				() =>
					writes.filter(
						(w) =>
							w.method === 'DELETE'
							&& w.url.includes(`/api/rules/${ruleId}`),
					).length,
				{
					message: `removing a row must send DELETE /api/rules/${ruleId}`,
					timeout: 15_000,
				},
			)
			.toBeGreaterThan(0)

		await expect(
			page.locator(ROW_INCLUDE),
			'the row must disappear from the editor',
		).toHaveCount(0)

		const api2 = await adminApi()
		expect(
			await storedRules(api2),
			'the rule must be gone from the server too',
		).toHaveLength(0)
		await api2.dispose()
	})

	/*
	 * REQ-CVUI-001 `editor-does-not-change-evaluation-semantics` HAS NO TEST,
	 * because there is no render-time evaluation for the editor to preserve.
	 * See launchpad#98 — conditional visibility is never enforced.
	 *
	 * A test was written for it and is deliberately not kept, because it could
	 * only ever have been red: it saved an include rule whose window closed in
	 * 2020 and expected the placement to stop rendering. It kept rendering.
	 *
	 * Three independent observations, in the order they were taken:
	 *   1. the browser still renders the placement after the rule is saved;
	 *   2. `GET /api/dashboard/{id}` (HTTP 200) still returns that placement,
	 *      with `isVisible: 1`;
	 *   3. `ConditionalService::checkRulesForPlacement()` has exactly one
	 *      production caller — `RuleApiController::getRules()`, i.e. the
	 *      EDITOR's own read — and `isWidgetVisible()`, the render-time entry
	 *      point REQ-CVUI-005 names, does not exist anywhere in `lib/`.
	 *
	 * (3) is an absence claim, so it was taken with a positive control: the
	 * same grep for `previewRules` finds its caller immediately.
	 *
	 * No `@e2e exclude`. A browser observes this scenario perfectly well the
	 * moment the feature is wired; what it observes today is that it is not.
	 */
})

test.describe('conditional-visibility editor — per-row operands (REQ-CVUI-002)', () => {
	test.beforeEach(async () => {
		const api = await adminApi()
		await clearRules(api)
		await api.dispose()
	})

	// @e2e conditional-visibility-editor::time-row-operands-with-day-of-week
	test('a time row emits startTime, endTime and a camelCase days array', async ({
		page,
	}) => {
		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		await page.locator(ADD_RULE).click()
		const row = anyRow(page)
		await chooseType(page, row, TYPE.time)
		await row.locator('[data-test="rule-start-time"]').fill('09:00')
		await row.locator('[data-test="rule-end-time"]').fill('17:00')

		// Mon..Fri are the first five switches, in `dayOptions` order.
		const days = row.locator('[data-test="rule-day-toggle"]')
		await expect(days, 'all seven weekday toggles must render').toHaveCount(7)
		for (let i = 0; i < 5; i++) {
			await activateSwitch(days.nth(i))
		}

		await row.locator('[data-test="rule-save"]').click()

		await expect
			.poll(
				() =>
					writes.filter(
						(w) => w.method === 'POST' && w.url.includes('/rules'),
					).length,
				{
					message: 'saving a time row must POST it',
					timeout: 15_000,
				},
			)
			.toBeGreaterThan(0)

		const sent = lastWrite(writes, 'POST', `/api/widgets/${placementId}/rules`)
		expect(sent.ruleType).toBe('time')
		expect(
			sent.ruleConfig,
			'the time row must emit the canonical camelCase shape',
		).toEqual({
			startTime: '09:00',
			endTime: '17:00',
			days: ['mon', 'tue', 'wed', 'thu', 'fri'],
		})
	})

	// @e2e conditional-visibility-editor::date-row-operands-with-open-ended-range
	test('a date row with only a start date omits endDate entirely rather than sending it empty', async ({
		page,
	}) => {
		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		await page.locator(ADD_RULE).click()
		const row = anyRow(page)
		await chooseType(page, row, TYPE.date)
		await row.locator('[data-test="rule-start-date"]').fill('2026-12-01')
		await row.locator('[data-test="rule-save"]').click()

		await expect
			.poll(
				() =>
					writes.filter(
						(w) => w.method === 'POST' && w.url.includes('/rules'),
					).length,
				{ timeout: 15_000 },
			)
			.toBeGreaterThan(0)

		const sent = lastWrite(writes, 'POST', `/api/widgets/${placementId}/rules`)
		expect(sent.ruleConfig).toEqual({ startDate: '2026-12-01' })
		// Spelled out separately: `toEqual` above would also pass for
		// `{startDate, endDate: undefined}` in some serialisers, and the
		// requirement is specifically that the KEY is absent.
		expect(
			Object.keys(sent.ruleConfig as Record<string, unknown>),
			'an unset endDate must not be sent as an empty key',
		).toEqual(['startDate'])
	})

	// @e2e conditional-visibility-editor::attribute-row-operands
	test('an attribute row emits attribute, operator and value', async ({
		page,
	}) => {
		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		await page.locator(ADD_RULE).click()
		const row = anyRow(page)
		await chooseType(page, row, TYPE.attribute)
		await row.locator('[data-test="rule-attribute"]').fill('language')
		await row.locator('[data-test="rule-value"]').fill('nl')
		await row.locator('[data-test="rule-save"]').click()

		await expect
			.poll(
				() =>
					writes.filter(
						(w) => w.method === 'POST' && w.url.includes('/rules'),
					).length,
				{ timeout: 15_000 },
			)
			.toBeGreaterThan(0)

		const sent = lastWrite(writes, 'POST', `/api/widgets/${placementId}/rules`)
		expect(sent.ruleType).toBe('attribute')
		expect(sent.ruleConfig).toEqual({
			attribute: 'language',
			operator: 'equals',
			value: 'nl',
		})
	})

	// @e2e conditional-visibility-editor::includeexclude-toggle
	test('toggling a row to exclude emits isInclude false and moves the row to the Hide-when section', async ({
		page,
	}) => {
		/*
		 * SCOPE, deliberately: REQ-CVUI-002's exclude scenario says the ROW
		 * must emit `isInclude: false` and MOVE to the "Hide when…" section.
		 * Both are observable before the request leaves the browser, and both
		 * are asserted below.
		 *
		 * What is NOT asserted is that the rule is then stored, because it is
		 * not: the server refuses every `isInclude: false` write with HTTP 400
		 * (launchpad#96). Asserting persistence here would be asserting that a
		 * different, unfixed defect is fixed, and would make this test red for
		 * a reason that has nothing to do with the row editor.
		 */
		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		await page.locator(ADD_RULE).click()
		const row = anyRow(page)
		await chooseType(page, row, TYPE.date)
		await row.locator('[data-test="rule-start-date"]').fill('2026-12-01')

		// CONTROL: a fresh row starts life in the include section, so "it is in
		// the exclude section" below is a change and not the resting state.
		await expect(
			page.locator(ROW_INCLUDE),
			'CONTROL: a new row starts as an include rule',
		).toHaveCount(1)
		await expect(page.locator(ROW_EXCLUDE)).toHaveCount(0)

		await activateSwitch(
			page
				.locator(ROW_INCLUDE)
				.first()
				.locator('[data-test="rule-mode-exclude"]'),
		)

		await expect(
			page.locator(ROW_EXCLUDE),
			'the row must move to the exclude section',
		).toHaveCount(1)
		await expect(
			page.locator(ROW_INCLUDE),
			'and leave the include section',
		).toHaveCount(0)

		await page
			.locator(ROW_EXCLUDE)
			.first()
			.locator('[data-test="rule-save"]')
			.click()
		await expect
			.poll(
				() =>
					writes.filter(
						(w) => w.method === 'POST' && w.url.includes('/rules'),
					).length,
				{
					message:
						'saving the toggled row must emit a request carrying its state',
					timeout: 15_000,
				},
			)
			.toBeGreaterThan(0)

		const sent = lastWrite(writes, 'POST', `/api/widgets/${placementId}/rules`)
		expect(sent.isInclude, 'an exclude row must emit isInclude false').toBe(
			false,
		)
		expect(sent.ruleType, 'and must still carry its own type and operands').toBe(
			'date',
		)
	})
})

test.describe('conditional-visibility editor — legible semantics (REQ-CVUI-003)', () => {
	test.beforeEach(async () => {
		const api = await adminApi()
		await clearRules(api)
		await api.dispose()
	})

	/*
	 * REQ-CVUI-003 `include-rules-grouped-under-an-or-heading` HAS NO TEST.
	 *
	 * Its GIVEN is "placement id 10 has two include rules and one exclude
	 * rule", and a stored exclude rule cannot be created — every
	 * `isInclude: false` write is refused with HTTP 400 (launchpad#96). No
	 * `@e2e exclude` is added: a browser could observe this scenario perfectly
	 * well the moment the rule can be saved, so recording "a browser cannot
	 * see this" would be false, and per `.github#345` it would be counted as
	 * coverage.
	 *
	 * The sibling scenario below IS covered, because it can build its exclude
	 * section from an UNSAVED draft row — the grouping is computed client-side
	 * from `isInclude`, so a toggled draft populates the "Hide when…" section
	 * without ever touching the server.
	 */

	// @e2e conditional-visibility-editor::semantics-conveyed-without-relying-on-colour
	test('the include/exclude distinction survives with colour removed', async ({
		page,
	}) => {
		const api = await adminApi()
		await seedRule(api, { ...KEEP_VISIBLE })
		await api.dispose()

		await openVisibilityEditor(page)

		// Populate the exclude section with a draft row, which needs no server.
		await page.locator(ADD_RULE).click()
		const draft = page.locator(ROW_INCLUDE).last()
		await chooseType(page, draft, TYPE.date)
		await activateSwitch(draft.locator('[data-test="rule-mode-exclude"]'))
		await expect(
			page.locator(EXCLUDE_SECTION),
			'both sections must be on screen for this comparison',
		).toBeVisible()
		await expect(page.locator(INCLUDE_SECTION)).toBeVisible()

		// The headings themselves, before colour is touched.
		await expect(page.locator(`${INCLUDE_SECTION} h3`)).toHaveText(
			/Show when ANY of these match/i,
		)
		await expect(page.locator(`${EXCLUDE_SECTION} h3`)).toHaveText(
			/Hide when ANY of these match/i,
		)

		/*
		 * Force every colour in the document to the same value. If the
		 * distinction were carried by colour it would be gone now; anything
		 * still readable is text and layout. This is stronger than reading the
		 * headings on a normal page, which cannot tell whether colour was ALSO
		 * doing the work.
		 */
		await page.addStyleTag({
			content:
				'*, *::before, *::after { color: #000 !important; background: #fff !important; '
				+ 'border-color: #000 !important; fill: #000 !important; }',
		})

		const includeText = (
			await page.locator(INCLUDE_SECTION).innerText()
		).toLowerCase()
		const excludeText = (
			await page.locator(EXCLUDE_SECTION).innerText()
		).toLowerCase()

		expect(
			includeText,
			'the include section must say what it means in words',
		).toContain('show when')
		expect(
			excludeText,
			'the exclude section must say what it means in words',
		).toContain('hide when')
		expect(
			includeText === excludeText,
			'the two sections must not read identically once colour is removed',
		).toBe(false)

		// The per-row toggle must be legible too — two radios, each labelled.
		const modes = page.locator(`${ROW_INCLUDE} [data-test="rule-mode"]`).first()
		await expect(
			modes,
			'the row toggle must name both states in text',
		).toContainText(/Include/i)
		await expect(modes).toContainText(/Exclude/i)
	})

	// @e2e conditional-visibility-editor::empty-state-explains-default-visibility
	test('with no rules the editor states the widget is always shown', async ({
		page,
	}) => {
		await openVisibilityEditor(page)

		await expect(
			page.locator(EMPTY_STATE),
			'an empty rule set must explain the default',
		).toBeVisible()
		await expect(page.locator(EMPTY_STATE)).toHaveText(/always shown/i)

		// CONTROL: the empty state is a statement about there being no rules,
		// not a permanent fixture. Add one and it must go.
		const api = await adminApi()
		await seedRule(api, {
			ruleType: 'date',
			ruleConfig: { startDate: '2026-01-01' },
			isInclude: true,
		})
		await api.dispose()

		await openVisibilityEditor(page)
		await expect(
			page.locator(EMPTY_STATE),
			'CONTROL: with a rule present the empty state must not show',
		).toHaveCount(0)
	})
})

test.describe('conditional-visibility editor — preview (REQ-CVUI-004, REQ-CVUI-005)', () => {
	test.beforeEach(async () => {
		const api = await adminApi()
		await clearRules(api)
		await api.dispose()
	})

	// @e2e conditional-visibility-editor::preview-evaluates-unsaved-edits
	// @e2e conditional-visibility-editor::preview-persists-nothing
	test('preview evaluates a row that was never saved, and stores nothing', async ({
		page,
	}) => {
		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		await page.locator(ADD_RULE).click()
		const row = anyRow(page)
		await chooseType(page, row, TYPE.date)
		// A window that is certainly in the past, so the verdict is decidable.
		await row.locator('[data-test="rule-start-date"]').fill('2020-01-01')
		await row.locator('[data-test="rule-end-date"]').fill('2020-12-31')

		// NOT saved. No rule-save click anywhere in this test.
		await page.locator(RUN_PREVIEW).click()

		await expect(
			page.locator(PREVIEW_VERDICT),
			'the preview must return a verdict',
		).toBeVisible({ timeout: 15_000 })
		await expect(
			page.locator(PREVIEW_VERDICT),
			'an include rule whose window has passed must preview as Hidden',
		).toHaveText(/Hidden/i)

		// The unsaved row really did reach the request — otherwise the verdict
		// above would be "no rules at all", which also reads as a verdict.
		const previewBody = lastWrite(writes, 'POST', '/api/visibility/preview')
		const rules = previewBody.rules as Array<Record<string, unknown>>
		expect(rules, 'the in-editor rule set must be sent').toHaveLength(1)
		expect((rules[0].ruleConfig as Record<string, unknown>).startDate).toBe(
			'2020-01-01',
		)

		// REQ-CVUI-005: nothing persisted.
		expect(
			writes.filter(
				(w) =>
					w.url.includes('/rules')
					|| (w.method === 'PUT' && w.url.includes('/api/rules/')),
			),
			'preview must not write any rule',
		).toHaveLength(0)

		const api = await adminApi()
		expect(
			await storedRules(api),
			'no rule may exist on the server after a preview',
		).toHaveLength(0)

		/*
		 * POSITIVE CONTROL for the two assertions above. "No rules were
		 * stored" is exactly what a broken editor, a failed request or a wrong
		 * placement id would also produce. Writing one through the same
		 * endpoint proves the check can see a rule when there is one.
		 */
		await seedRule(api, {
			ruleType: 'date',
			ruleConfig: { startDate: '2026-01-01' },
			isInclude: true,
		})
		expect(
			await storedRules(api),
			'CONTROL: the stored-rules probe must be able to see a rule, or its zero above means nothing',
		).toHaveLength(1)
		await api.dispose()
	})

	// @e2e conditional-visibility-editor::preview-uses-the-same-rule-shape-as-the-editor-emits
	test('the preview request carries byte-identical ruleConfig to the one the row persists', async ({
		page,
	}) => {
		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		await page.locator(ADD_RULE).click()
		const row = anyRow(page)
		await chooseType(page, row, TYPE.attribute)
		await row.locator('[data-test="rule-attribute"]').fill('language')
		await row.locator('[data-test="rule-value"]').fill('nl')

		await page.locator(RUN_PREVIEW).click()
		await expect(page.locator(PREVIEW_VERDICT)).toBeVisible({ timeout: 15_000 })
		const previewed = (
			lastWrite(writes, 'POST', '/api/visibility/preview').rules as Array<
				Record<string, unknown>
			>
		)[0]

		await row.locator('[data-test="rule-save"]').click()
		await expect
			.poll(
				() =>
					writes.filter(
						(w) =>
							w.method === 'POST'
							&& w.url.includes(`/api/widgets/${placementId}/rules`),
					).length,
				{ timeout: 15_000 },
			)
			.toBeGreaterThan(0)
		const persisted = lastWrite(
			writes,
			'POST',
			`/api/widgets/${placementId}/rules`,
		)

		// The requirement is "no translation layer that could drift", so the
		// two shapes are compared directly rather than each being checked
		// against a hand-written expectation that could drift with them.
		expect(
			previewed.ruleType,
			'preview and save must agree on the rule type',
		).toBe(persisted.ruleType)
		expect(
			previewed.ruleConfig,
			'preview and save must send the same ruleConfig',
		).toEqual(persisted.ruleConfig)
		expect(previewed.isInclude).toBe(persisted.isInclude)
	})

	/*
	 * REQ-CVUI-005 `preview-verdict-matches-render-time-verdict-for-identical-inputs`
	 * HAS NO TEST, and this is the scenario that found launchpad#98.
	 *
	 * The test existed and ran. Its first half passed — the editor previews a
	 * closed-window include rule as **Hidden**. Its second half failed:
	 * after saving that same rule, the dashboard still rendered the placement.
	 * The two verdicts do not merely risk drifting, as the requirement
	 * anticipates; they disagree today, because only one of them is computed
	 * at all. See the note in the REQ-CVUI-001 describe above for the three
	 * observations, and launchpad#98.
	 *
	 * The requirement's own words are the clearest statement of what is
	 * broken: the equality "MUST hold because both paths execute the same
	 * evaluation code". There is no second path.
	 *
	 * Not `@e2e exclude`d — the scenario is browser-observable, and a browser
	 * is exactly what observed it failing.
	 */

	// @e2e conditional-visibility-editor::preview-rejects-an-invalid-rule-set
	test('the preview endpoint refuses an unknown ruleType with HTTP 400', async ({
		page,
	}) => {
		/*
		 * Issued from the logged-in page rather than from a bare API context,
		 * so it travels the same session, CSRF token and middleware stack the
		 * editor's own preview does. The UI cannot produce this rule set — the
		 * type select offers exactly four options — but the endpoint is
		 * required to validate server-side regardless of what the UI can send,
		 * which is the whole point of the requirement.
		 */
		await openVisibilityEditor(page)

		const bad = await page.evaluate(async () => {
			const res = await fetch(
				'/index.php/apps/launchpad/api/visibility/preview',
				{
					method: 'POST',
					credentials: 'include',
					headers: {
						'Content-Type': 'application/json',
						'OCS-APIRequest': 'true',
						requesttoken:
							(
								document.head.querySelector(
									'meta[name="csrf-token"]',
								) as HTMLMetaElement | null
							)?.content
							?? (
								window as unknown as {
									OC?: { requestToken?: string }
								}
							).OC?.requestToken
							?? '',
					},
					body: JSON.stringify({
						rules: [
							{
								ruleType: 'weather',
								ruleConfig: { outlook: 'sunny' },
								isInclude: true,
							},
						],
						context: { groups: [], datetime: null },
					}),
				},
			)
			return { status: res.status, body: (await res.text()).slice(0, 400) }
		})

		expect(
			bad.status,
			`an unknown ruleType must be rejected — got ${bad.status}: ${bad.body}`,
		).toBe(400)

		/*
		 * CONTROL. A 400 for every request would satisfy the line above, and
		 * "the endpoint is simply broken" is indistinguishable from "the
		 * endpoint validates" without it. The same call with a VALID ruleType
		 * must succeed.
		 */
		const good = await page.evaluate(async () => {
			const res = await fetch(
				'/index.php/apps/launchpad/api/visibility/preview',
				{
					method: 'POST',
					credentials: 'include',
					headers: {
						'Content-Type': 'application/json',
						'OCS-APIRequest': 'true',
						requesttoken:
							(
								document.head.querySelector(
									'meta[name="csrf-token"]',
								) as HTMLMetaElement | null
							)?.content
							?? (
								window as unknown as {
									OC?: { requestToken?: string }
								}
							).OC?.requestToken
							?? '',
					},
					body: JSON.stringify({
						rules: [
							{
								ruleType: 'date',
								ruleConfig: { startDate: '2026-01-01' },
								isInclude: true,
							},
						],
						context: { groups: [], datetime: null },
					}),
				},
			)
			return { status: res.status, body: (await res.text()).slice(0, 400) }
		})

		expect(
			good.status,
			`CONTROL: a valid rule set must be accepted, or the 400 above is not evidence of validation — got ${good.status}: ${good.body}`,
		).toBeLessThan(300)
	})

	// @e2e conditional-visibility-editor::preview-requires-an-authenticated-user
	test('the preview endpoint is not reachable without a session', async () => {
		const anonymous = await request.newContext({
			baseURL: ENV_BASE_URL,
			// Spelled explicitly. `storageState: undefined` is read as "not
			// supplied" and falls back to the project's admin session, which
			// would make this an assertion about an administrator.
			storageState: { cookies: [], origins: [] },
			extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
		})

		const res = await anonymous.post(
			'/index.php/apps/launchpad/api/visibility/preview',
			{
				data: {
					rules: [
						{
							ruleType: 'date',
							ruleConfig: { startDate: '2026-01-01' },
							isInclude: true,
						},
					],
					context: { groups: [], datetime: null },
				},
			},
		)
		expect(
			res.status(),
			`an anonymous preview must be refused, not served — got ${res.status()}: ${(await res.text()).slice(0, 300)}`,
		).toBeGreaterThanOrEqual(400)
		await anonymous.dispose()

		/*
		 * CONTROL, and it is the whole test. A 401 proves nothing on its own —
		 * a route that does not exist, a typo in the path, or a wholly broken
		 * endpoint all refuse anonymous callers too. The SAME request with
		 * credentials must succeed, which is what makes the refusal above
		 * attributable to the missing session.
		 */
		const authed = await adminApi()
		const ok = await authed.post(
			'/index.php/apps/launchpad/api/visibility/preview',
			{
				data: {
					rules: [
						{
							ruleType: 'date',
							ruleConfig: { startDate: '2026-01-01' },
							isInclude: true,
						},
					],
					context: { groups: [], datetime: null },
				},
			},
		)
		expect(
			ok.status(),
			`CONTROL: the same request WITH credentials must be served — got ${ok.status()}: ${(await ok.text()).slice(0, 300)}`,
		).toBeLessThan(300)
		await authed.dispose()
	})
})
