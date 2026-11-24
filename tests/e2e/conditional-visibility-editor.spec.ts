/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The conditional-visibility rule builder — the "Who sees this widget?"
 * dialog reached from a placement's right-click context menu
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
 * THREE PRODUCT DEFECTS THESE TESTS ARE EXPECTED TO GO RED ON
 * ===========================================================
 * Nine scenarios here used to carry no test at all, with a note per scenario
 * explaining which defect blocked it. They now carry tests that assert the
 * SPEC's behaviour, and those tests are expected to be red until the defect
 * underneath them is fixed. A test that is red for a named reason is a
 * finding; a missing test is not.
 *
 * 1. The group pickers have no options (launchpad#97). `Views.vue` renders
 *    `<VisibilityRulesModal :open :placement-id>` and never passes
 *    `available-groups`, so the prop keeps its `default: () => []`. With an
 *    empty `:options`, the vendored `NcSelectTags` falls back to listing
 *    SYSTEM TAGS, not groups. Measured earlier: the combobox reports "No
 *    results". The same empty list feeds the preview-as-audience picker.
 *    Red on: add-a-group-inclusion-rule-through-the-ui, group-row-operands,
 *    preview-shows-visible-for-a-matching-audience,
 *    preview-shows-hidden-for-a-non-matching-audience,
 *    preview-reflects-an-exclude-override. Each fails at `pickGroup()`, whose
 *    message names #97.
 *
 * 2. No exclude rule can be stored (launchpad#96).
 *    `POST /api/widgets/{id}/rules` with `isInclude: false` answers HTTP 400
 *    `{"error":"Operation failed"}`; the same body with `isInclude: true`
 *    answers 201. `oc_launchpad_conditional_rules.is_include` is
 *    `smallint NOT NULL DEFAULT 1` (lib/Migration/RulesTableBuilder.php) while
 *    `ConditionalRule` declares `addType('isInclude', 'boolean')`
 *    (lib/Db/ConditionalRule.php), so the mapper binds PARAM_BOOL, which
 *    PostgreSQL will not put into a smallint.
 *    Red on: include-rules-grouped-under-an-or-heading, and the last shape of
 *    endpoint-delegates-to-the-shared-evaluation-service. Both fail inside
 *    `seedRule()`, with the server's body in the message.
 *
 * 3. Visibility is enforced at render time (launchpad#98, REQ-VIS-013).
 *    A viewer does not get a placement whose rules hide it. The owner and
 *    editors still get it, dimmed and marked "Hidden for viewers", so the
 *    rendered verdict below counts a marked placement as hidden.
 *
 * `includeexclude-toggle` asserts only what the ROW emits and where it moves,
 * both observable before the server sees the request, so #96 does not turn
 * it red.
 *
 * WHY THE SUBJECT PLACEMENT STAYS VISIBLE
 * =======================================
 * Under include=OR a placement with include rules and no match is HIDDEN,
 * and a hidden placement cannot be right-clicked, so the editor could not be
 * reopened once enforcement lands. Two things guard against that: every
 * fixture with a non-matching include rule seeds a matching one beside it
 * (KEEP_VISIBLE), and the admin is a member of the run's "marketing" group,
 * so a group rule naming it matches the author too.
 *
 * GROUP NAMES ARE STAMPED
 * =======================
 * The scenarios say "marketing", "sales" and "engineering". This suite
 * creates `cve-marketing-<stamp>` and so on, and deletes them in afterAll,
 * because creating or deleting a group literally called "marketing" on a
 * shared instance could take one somebody else relies on. The asserted shape
 * is the same: `{"groups":[<id>, <id>]}`.
 *
 * @spec openspec/specs/conditional-visibility-editor/spec.md
 */

import type {
	APIRequestContext,
	Browser,
	BrowserContext,
	Locator,
	Page,
} from '@playwright/test'
import type { Principal } from './support/principals.ts'
import type { ActionMatrix } from './support/visibilityRules.ts'

import { expect, request, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	addToGroup,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'
import { grantRuleActionsToAll, writeActionMatrix } from './support/visibilityRules.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}
// Through the shared resolver, not a private read of `BASE_URL`. That read
// ignored `PLAYWRIGHT_BASE_URL`, the variable tests/e2e/support/baseUrl.ts
// documents, so a local run that set only it failed here with `Invalid URL`.
// CI exports `BASE_URL`, which the resolver still honours.
const ENV_BASE_URL = BASE_URL

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

/*
 * Groups the group-rule scenarios pick from, stamped per run (see the header).
 * The admin joins `marketing`, so a rule naming it matches the author and the
 * subject placement stays right-clickable once render-time enforcement lands.
 */
const GROUP = {
	marketing: `cve-marketing-${STAMP}`,
	sales: `cve-sales-${STAMP}`,
	engineering: `cve-engineering-${STAMP}`,
} as const

/*
 * A second tile on the shared dashboard that carries NO rules. It is the
 * CONTROL for every "the subject is not rendered" assertion: until it is on
 * screen, a zero count for the subject says nothing. Its title is letters
 * only, so the `hasText: STAMP` locators above can never pick it instead of
 * the subject.
 */
const ANCHOR_TITLE = `Visibility anchor ${Math.random()
	.toString(36)
	.replace(/[^a-z]/g, '')
	.slice(0, 8)}x`

let groupsCreated: string[] = []

test.beforeAll(async () => {
	const api = await adminApi()
	for (const id of Object.values(GROUP)) {
		await createGroup(api, id)
		groupsCreated.push(id)
	}
	await addToGroup(api, ADMIN.user, GROUP.marketing)

	const anchor = await api.post(
		`/index.php/apps/launchpad/api/dashboard/${dashboardId}/tile`,
		{
			data: {
				title: ANCHOR_TITLE,
				linkType: 'url',
				linkValue: 'https://example.invalid/anchor',
				gridX: 4,
				gridY: 0,
				gridWidth: 3,
				gridHeight: 2,
			},
		},
	)
	expect(
		anchor.status(),
		`seeding the anchor tile: ${await anchor.text()}`,
	).toBeLessThan(300)
	await api.dispose()
})

test.afterAll(async () => {
	const api = await adminApi()
	for (const id of groupsCreated) {
		await removeGroup(api, id)
	}
	groupsCreated = []
	await api.dispose()
})

/**
 * Pick one group in an `NcSelectTags` group picker by its id.
 *
 * Typed first, so the option list is narrowed to the stamped id and the
 * option count is exactly one. Clicked by the user's own route: open the
 * combobox, choose the option. Escape closes the dropdown so the next pick
 * starts from a closed control.
 *
 * @param page the page.
 * @param field the picker's `data-test` selector (it may land on the root or
 *   on the input, so both are tried).
 * @param scope where to look for the picker: a row, or the whole page.
 * @param groupId the group to choose.
 */
async function pickGroup(
	page: Page,
	field: string,
	scope: Locator | Page,
	groupId: string,
): Promise<void> {
	const input = scope.locator(`${field} input, input${field}`).first()
	await input.click()
	await input.fill(groupId)
	const option = page.locator('.vs__dropdown-option').filter({ hasText: groupId })
	await expect(
		option,
		`the picker must offer the group "${groupId}". It is fed by `
			+ '`availableGroups`, which Views.vue never passes to '
			+ 'VisibilityRulesModal (launchpad#97)',
	).toHaveCount(1)
	await option.click()
	await page.keyboard.press('Escape')
}

/**
 * Pick the person to view as (View as, REQ-CVUI-004/006). Typed first, so
 * the people search narrows to the stamped user, then chosen from the list.
 *
 * @param page the page.
 * @param username the person to view as.
 */
async function pickPerson(page: Page, username: string): Promise<void> {
	const input = page.locator('[data-test="preview-person"] input, input[data-test="preview-person"]').first()
	await input.click()
	await input.fill(username)
	const option = page.locator('.vs__dropdown-option').filter({ hasText: username })
	await expect(option, `the people search must offer "${username}"`).toHaveCount(1, { timeout: 15_000 })
	await option.click()
	await page.keyboard.press('Escape')
}

/**
 * Set the moment to view at.
 *
 * @param page the page.
 * @param moment a `datetime-local` value, e.g. `2026-07-23T14:30`.
 */
async function pickMoment(page: Page, moment: string): Promise<void> {
	await page.locator('[data-test="preview-datetime"] input, input[data-test="preview-datetime"]').first().fill(moment)
}

/** The body of the last `POST /api/visibility/preview` the page sent. */
function lastPreviewBody(
	writes: Array<{ method: string; url: string; body: string }>,
): {
	rules: Array<Record<string, unknown>>
	context: { groups: string[]; datetime: string | null; userId?: string }
	match?: string
	placementId?: number
} {
	return lastWrite(writes, 'POST', '/api/visibility/preview') as never
}

/**
 * Press Preview, require the request to succeed, and wait for the verdict.
 *
 * @param page the page.
 */
async function runPreviewAndWait(page: Page): Promise<void> {
	const settled = page.waitForResponse(
		(res) =>
			res.request().method() === 'POST'
			&& res.url().includes('/api/visibility/preview'),
	)
	await page.locator(RUN_PREVIEW).click()
	const res = await settled
	expect(
		res.status(),
		`the preview request must succeed: ${await res.text()}`,
	).toBe(200)
	await expect(page.locator(PREVIEW_VERDICT)).toBeVisible({ timeout: 15_000 })
}

/**
 * Reload the workspace and report whether the subject placement rendered.
 *
 * The anchor tile is waited for first: it carries no rules, so once it is on
 * screen the grid has rendered this dashboard's placements, and the subject's
 * absence is a verdict rather than a page that has not loaded yet.
 *
 * The owner of the dashboard still sees a hidden placement, dimmed and
 * marked "Hidden for viewers" (REQ-VIS-013), so a marked placement counts as
 * hidden.
 *
 * @param page the page, as whoever is viewing.
 * @param subject text unique to the subject tile.
 * @param anchor text unique to the anchor tile.
 * @return whether the subject is rendered for viewers.
 */
async function renderedVerdict(
	page: Page,
	subject: string,
	anchor: string,
): Promise<boolean> {
	await page.goto(APP_URL)
	await expect(
		page.locator('.launchpad-grid-item').filter({ hasText: anchor }).first(),
		`CONTROL: the rule-free anchor tile "${anchor}" must render, or the subject's absence below means nothing`,
	).toBeVisible({ timeout: 30_000 })
	const items = page.locator('.launchpad-grid-item').filter({ hasText: subject })
	if ((await items.count()) === 0) {
		return false
	}
	return (
		(await items
			.first()
			.evaluate((el) => el.classList.contains('launchpad-grid-item--hidden-for-viewers'))) === false
	)
}

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
		await enterEditMode(page)
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

/** Open the "Who sees this widget?" dialog on the seeded placement. */
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
	// A saved rule shows as a card (visibility-dialog-board-sync); open each
	// card's editor so the row's fields are on screen as these tests expect.
	const closed = page.locator('[data-test="rule-card-edit"][aria-expanded="false"]')
	while ((await closed.count()) > 0) {
		await closed.first().click()
	}
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

	// @e2e conditional-visibility-editor::add-a-group-inclusion-rule-through-the-ui
	test('adding a group include rule POSTs the canonical body and, on 201, shows it in the include section', async ({
		page,
	}) => {
		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		// GIVEN: no rules. Asserted, not assumed: beforeEach cleared them.
		await expect(
			page.locator(EMPTY_STATE),
			'CONTROL: the placement must start with no rules',
		).toBeVisible()
		await expect(page.locator(ROW_INCLUDE)).toHaveCount(0)

		await page.locator(ADD_RULE).click()
		const row = anyRow(page)
		await chooseType(page, row, TYPE.group)
		await pickGroup(page, '[data-test="rule-groups"]', row, GROUP.marketing)
		await pickGroup(page, '[data-test="rule-groups"]', row, GROUP.sales)

		// "sets it to include": the row's own Include radio, clicked rather than
		// trusted to be the default.
		await activateSwitch(row.locator('[data-test="rule-mode-include"]'))

		const created = page.waitForResponse(
			(res) =>
				res.request().method() === 'POST'
				&& res.url().includes(`/api/widgets/${placementId}/rules`),
		)
		await page
			.locator(ROW_INCLUDE)
			.first()
			.locator('[data-test="rule-save"]')
			.click()
		const response = await created

		const sent = lastWrite(writes, 'POST', `/api/widgets/${placementId}/rules`)
		expect(
			sent,
			'the editor must send exactly the canonical body, nothing more',
		).toEqual({
			ruleType: 'group',
			ruleConfig: { groups: [GROUP.marketing, GROUP.sales] },
			isInclude: true,
		})
		expect(
			response.status(),
			`the create must answer HTTP 201: ${await response.text()}`,
		).toBe(201)

		// The persisted shape, read back from the server rather than echoed.
		const api = await adminApi()
		const stored = await storedRules(api)
		await api.dispose()
		expect(stored, 'exactly one rule must be stored').toHaveLength(1)
		expect(stored[0].ruleType).toBe('group')
		expect(
			stored[0].ruleConfig,
			'the stored ruleConfig must be the canonical camelCase `groups` array',
		).toEqual({ groups: [GROUP.marketing, GROUP.sales] })

		// And the row sits in the include section, populated.
		await expect(
			page.locator(`${INCLUDE_SECTION} ${ROW_INCLUDE}`),
			'the new rule must appear as a row in the include section',
		).toHaveCount(1)
		await expect(page.locator(ROW_EXCLUDE)).toHaveCount(0)
		await expect(page.locator(ROW_INCLUDE).first()).toContainText(
			GROUP.marketing,
		)
		await expect(page.locator(ROW_INCLUDE).first()).toContainText(GROUP.sales)
		await expect(page.locator(EMPTY_STATE)).toHaveCount(0)
	})

	// @e2e conditional-visibility-editor::editor-does-not-change-evaluation-semantics
	test('rules created and edited through the editor are what the render-time pipeline evaluates', async ({
		page,
	}) => {
		/*
		 * The render-time verdict is read two ways and both must agree with
		 * the rules: the pipeline's own verdict (`isVisible` from
		 * `GET /api/widgets/{id}/rules`, which is
		 * `ConditionalService::checkRulesForPlacement()`), and what the
		 * browser actually renders after a reload.
		 */
		const closedEnd = `${new Date().getUTCFullYear() - 1}-12-31`
		const writes = recordWrites(page)

		try {
			// CREATE through the editor: an include rule that matches now.
			await openVisibilityEditor(page)
			await page.locator(ADD_RULE).click()
			const draft = anyRow(page)
			await chooseType(page, draft, TYPE.date)
			await draft
				.locator('[data-test="rule-start-date"]')
				.fill(MATCHING_START_DATE)
			const created = page.waitForResponse(
				(res) =>
					res.request().method() === 'POST'
					&& res.url().includes(`/api/widgets/${placementId}/rules`),
			)
			await draft.locator('[data-test="rule-save"]').click()
			expect((await created).status(), 'the create must succeed').toBe(201)

			const api = await adminApi()
			const afterCreate = await api.get(
				`/index.php/apps/launchpad/api/widgets/${placementId}/rules`,
			)
			const createdBody = await afterCreate.json()
			const createdRules = createdBody.rules ?? createdBody.data?.rules ?? []
			expect(createdRules).toHaveLength(1)
			expect(
				createdRules[0].ruleConfig,
				'the stored shape must be the one the row emitted, untranslated',
			).toEqual({ startDate: MATCHING_START_DATE })
			expect(
				createdBody.isVisible ?? createdBody.data?.isVisible,
				'CONTROL: the pipeline must judge a started, open-ended include rule visible',
			).toBe(true)
			expect(
				await renderedVerdict(page, STAMP, ANCHOR_TITLE),
				'CONTROL: a matching rule must leave the placement rendered',
			).toBe(true)

			// EDIT through the editor: close the window, so the rule no
			// longer matches.
			await openVisibilityEditor(page)
			const row = page.locator(ROW_INCLUDE).first()
			await expect(row.locator('[data-test="rule-start-date"]')).toHaveValue(
				MATCHING_START_DATE,
			)
			await row.locator('[data-test="rule-end-date"]').fill(closedEnd)
			const ruleId = Number(createdRules[0].id)
			const updated = page.waitForResponse(
				(res) =>
					res.request().method() === 'PUT'
					&& res.url().includes(`/api/rules/${ruleId}`),
			)
			await row.locator('[data-test="rule-save"]').click()
			expect((await updated).status(), 'the edit must succeed').toBe(200)

			const afterEdit = await api.get(
				`/index.php/apps/launchpad/api/widgets/${placementId}/rules`,
			)
			const editedBody = await afterEdit.json()
			const editedRules = editedBody.rules ?? editedBody.data?.rules ?? []
			await api.dispose()
			expect(
				editedRules[0].ruleConfig,
				'the edited rule must be stored in the canonical date shape',
			).toEqual({ startDate: MATCHING_START_DATE, endDate: closedEnd })
			const pipeline = editedBody.isVisible ?? editedBody.data?.isVisible
			expect(
				pipeline,
				'the pipeline must judge an include rule whose window has closed hidden',
			).toBe(false)

			// The render must follow the pipeline (launchpad#98).
			expect(
				await renderedVerdict(page, STAMP, ANCHOR_TITLE),
				'the dashboard must render what the pipeline decided: a placement whose only include rule has closed must not render (launchpad#98)',
			).toBe(pipeline)

			// No alternative persistence path: every rule-related write went to
			// the two canonical CRUD endpoints.
			const ruleWrites = writes.filter((w) => /rule|visibilit/i.test(w.url))
			expect(
				ruleWrites.length,
				'CONTROL: the create and the edit must both have been seen',
			).toBeGreaterThanOrEqual(2)
			for (const w of ruleWrites) {
				const canonical =
					(w.method === 'POST'
						&& w.url.includes(`/api/widgets/${placementId}/rules`))
					|| (w.method === 'PUT' && w.url.includes(`/api/rules/${ruleId}`))
				expect(
					canonical,
					`a rule write went somewhere other than the CRUD endpoints: ${w.method} ${w.url}`,
				).toBe(true)
			}
		} finally {
			const api = await adminApi()
			await clearRules(api)
			await api.dispose()
		}
	})
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

	// @e2e conditional-visibility-editor::group-row-operands
	test('a group row emits ruleConfig {groups: [...]} with the selected groups', async ({
		page,
	}) => {
		/*
		 * "The row MUST emit" is observed at both places the emission goes:
		 * the live `update:rule` sync, which is what the preview request
		 * carries before anything is saved, and the save itself.
		 */
		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		await page.locator(ADD_RULE).click()
		const row = anyRow(page)
		await chooseType(page, row, TYPE.group)
		await pickGroup(page, '[data-test="rule-groups"]', row, GROUP.marketing)
		await pickGroup(page, '[data-test="rule-groups"]', row, GROUP.sales)

		await runPreviewAndWait(page)
		const previewed = lastPreviewBody(writes).rules
		expect(
			previewed,
			'the in-editor rule set must hold the one row',
		).toHaveLength(1)
		expect(previewed[0].ruleType).toBe('group')
		expect(
			previewed[0].ruleConfig,
			'the row must emit exactly {groups: [marketing, sales]}',
		).toEqual({ groups: [GROUP.marketing, GROUP.sales] })

		await page
			.locator(`${ROW_INCLUDE}, ${ROW_EXCLUDE}`)
			.first()
			.locator('[data-test="rule-save"]')
			.click()
		await expect
			.poll(
				() =>
					writes.filter(
						(w) =>
							w.method === 'POST'
							&& w.url.includes(`/api/widgets/${placementId}/rules`),
					).length,
				{ message: 'saving the group row must POST it', timeout: 15_000 },
			)
			.toBeGreaterThan(0)
		const saved = lastWrite(writes, 'POST', `/api/widgets/${placementId}/rules`)
		expect(
			saved.ruleConfig,
			'the saved row must emit the same groups shape',
		).toEqual({ groups: [GROUP.marketing, GROUP.sales] })
	})
})

test.describe('conditional-visibility editor — legible semantics (REQ-CVUI-003)', () => {
	test.beforeEach(async () => {
		const api = await adminApi()
		await clearRules(api)
		await api.dispose()
	})

	// @e2e conditional-visibility-editor::include-rules-grouped-under-an-or-heading
	test('two include rules sit under "Show when ANY", one exclude rule under "Hide when ANY"', async ({
		page,
	}) => {
		/*
		 * Expected RED until launchpad#96 is fixed: the exclude rule in the
		 * GIVEN cannot be stored, and `seedRule()` fails with the server's
		 * 400 body. The exclude rule's window closed last year, so once it CAN
		 * be stored it does not hide the subject (and lock the editor out).
		 */
		const year = new Date().getUTCFullYear() - 1
		const api = await adminApi()
		await seedRule(api, { ...KEEP_VISIBLE })
		await seedRule(api, {
			ruleType: 'attribute',
			ruleConfig: { attribute: 'language', operator: 'equals', value: 'nl' },
			isInclude: true,
		})
		await seedRule(api, {
			ruleType: 'date',
			ruleConfig: { startDate: `${year}-01-01`, endDate: `${year}-01-31` },
			isInclude: false,
		})
		await api.dispose()

		await openVisibilityEditor(page)

		await expect(
			page.locator(`${INCLUDE_SECTION} h3`),
			'the include section must say it is an OR',
		).toHaveText(/Show when ANY of these match/i)
		await expect(
			page.locator(`${INCLUDE_SECTION} ${ROW_INCLUDE}`),
			'both include rules must sit under the include heading',
		).toHaveCount(2)
		await expect(page.locator(`${INCLUDE_SECTION} ${ROW_EXCLUDE}`)).toHaveCount(
			0,
		)

		await expect(
			page.locator(`${EXCLUDE_SECTION} h3`),
			'the exclude section must say any match hides the widget',
		).toHaveText(/Hide when ANY of these match/i)
		await expect(
			page.locator(`${EXCLUDE_SECTION} ${ROW_EXCLUDE}`),
			'the exclude rule must sit under the exclude heading',
		).toHaveCount(1)
		await expect(page.locator(`${EXCLUDE_SECTION} ${ROW_INCLUDE}`)).toHaveCount(
			0,
		)
	})

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
	/*
	 * View as one person at one moment: a person in `marketing` and one in
	 * `engineering`, so a group rule naming `marketing` matches one and not
	 * the other.
	 */
	let marketer: Principal | null = null
	let engineer: Principal | null = null

	test.beforeAll(async () => {
		const api = await adminApi()
		marketer = await createPrincipal(api, 'cve-marketer')
		await addToGroup(api, marketer.username, GROUP.marketing)
		engineer = await createPrincipal(api, 'cve-engineer')
		await addToGroup(api, engineer.username, GROUP.engineering)
		await api.dispose()
	})

	test.afterAll(async () => {
		const api = await adminApi()
		await removePrincipal(api, marketer)
		await removePrincipal(api, engineer)
		await api.dispose()
	})

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

	// @e2e conditional-visibility-editor::preview-shows-visible-for-a-matching-audience
	test('viewing as a person in the matching group shows Visible, with icon and text, and names the matched include rule', async ({
		page,
	}) => {
		const api = await adminApi()
		const ruleId = await seedRule(api, {
			ruleType: 'group',
			ruleConfig: { groups: [GROUP.marketing] },
			isInclude: true,
		})
		await api.dispose()

		const writes = recordWrites(page)
		await openVisibilityEditor(page)
		await expect(
			page.locator(ROW_INCLUDE),
			'CONTROL: the editor must hold the one seeded include rule',
		).toHaveCount(1)

		await pickPerson(page, marketer!.username)
		await pickMoment(page, '2026-07-23T14:30')
		await runPreviewAndWait(page)

		// The request: the current rule set, the person and the moment. The
		// editor sends the datetime-local value as an ISO instant, computed in
		// the browser's own timezone, so the expectation is computed there too.
		const expectedInstant = await page.evaluate(() =>
			new Date('2026-07-23T14:30').toISOString(),
		)
		const body = lastPreviewBody(writes)
		expect(body.rules, 'the preview must carry the editor rule set').toEqual([
			{
				id: ruleId,
				ruleType: 'group',
				ruleConfig: { groups: [GROUP.marketing] },
				isInclude: true,
			},
		])
		expect(body.context.userId, 'the preview must view as the chosen person').toBe(marketer!.username)
		expect(body.context.groups, 'the person\'s own groups count, none are sent').toEqual([])
		expect(body.context.datetime).toBe(expectedInstant)
		expect(body.placementId).toBe(placementId)

		// The verdict: text AND an icon, so it is not carried by colour alone.
		const verdict = page.locator(
			'[data-test="preview-result"] .conditional-visibility-editor__preview-verdict',
		)
		await expect(page.locator(PREVIEW_VERDICT)).toHaveText(/^\s*Visible\s*$/)
		await expect(
			verdict.locator('.eye-icon svg'),
			'a Visible verdict must carry the eye icon',
		).toHaveCount(1)
		await expect(verdict.locator('.eye-off-icon')).toHaveCount(0)

		// The matched include rule is indicated.
		const matched = page.locator('[data-test="preview-matched-include"]')
		await expect(
			matched,
			'the matched include rule must be indicated',
		).toHaveCount(1)
		await expect(matched).toContainText(GROUP.marketing)
		await expect(
			page.locator('[data-test="preview-matched-exclude"]'),
		).toHaveCount(0)
		await expect(page.locator('[data-test="visibility-sentence"]')).toHaveText(
			`${marketer!.username} sees this widget, because ${marketer!.username} is in ${GROUP.marketing}.`,
		)
	})

	// @e2e conditional-visibility-editor::preview-shows-hidden-for-a-non-matching-audience
	test('viewing as a person outside the group the rule names shows Hidden and indicates no matched include rule', async ({
		page,
	}) => {
		const api = await adminApi()
		await seedRule(api, {
			ruleType: 'group',
			ruleConfig: { groups: [GROUP.marketing] },
			isInclude: true,
		})
		await api.dispose()

		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		// "at any moment": the moment field keeps its default, now.
		await pickPerson(page, engineer!.username)
		await runPreviewAndWait(page)

		expect(lastPreviewBody(writes).context.userId).toBe(engineer!.username)
		await expect(page.locator(PREVIEW_VERDICT)).toHaveText(/^\s*Hidden\s*$/)
		await expect(
			page.locator('[data-test="preview-result"] .eye-off-icon svg'),
			'a Hidden verdict must carry the eye-off icon',
		).toHaveCount(1)
		await expect(
			page.locator('[data-test="preview-matched-include"]'),
			'no include rule may be indicated as matched',
		).toHaveCount(0)

		await expect(page.locator('[data-test="visibility-sentence"]')).toHaveText(
			`${engineer!.username} does not see this widget, because ${engineer!.username} is not in ${GROUP.marketing}.`,
		)

		/*
		 * CONTROL. "No include rule indicated" is also what a broken indicator
		 * would show. Viewing as a person in the matching group must flip the
		 * verdict and make the indicator appear, which proves it can.
		 */
		await pickPerson(page, marketer!.username)
		await runPreviewAndWait(page)
		await expect(page.locator(PREVIEW_VERDICT)).toHaveText(/^\s*Visible\s*$/)
		await expect(
			page.locator('[data-test="preview-matched-include"]'),
			'CONTROL: viewing as a person in the matching group, the include rule must be indicated',
		).toHaveCount(1)
	})

	// @e2e conditional-visibility-editor::preview-reflects-an-exclude-override
	test('a matching exclude date rule overrides a matching include rule, and is named as the reason', async ({
		page,
	}) => {
		/*
		 * The include rule is stored; the exclude rule is held in the editor
		 * as an unsaved row, which is what "the editor holds" allows, and the
		 * only way to hold one while launchpad#96 refuses to store it. The
		 * unsaved row is previewed under its negative local key, which the
		 * endpoint echoes back, so the editor can name it as the reason.
		 */
		const api = await adminApi()
		await seedRule(api, {
			ruleType: 'group',
			ruleConfig: { groups: [GROUP.marketing] },
			isInclude: true,
		})
		await api.dispose()

		const writes = recordWrites(page)
		await openVisibilityEditor(page)

		await page.locator(ADD_RULE).click()
		const draft = page.locator(ROW_INCLUDE).last()
		await chooseType(page, draft, TYPE.date)
		await draft.locator('[data-test="rule-start-date"]').fill('2026-07-01')
		await draft.locator('[data-test="rule-end-date"]').fill('2026-07-31')
		await activateSwitch(draft.locator('[data-test="rule-mode-exclude"]'))
		await expect(page.locator(ROW_EXCLUDE)).toHaveCount(1)

		await pickPerson(page, marketer!.username)
		await pickMoment(page, '2026-07-15T10:00')
		await runPreviewAndWait(page)

		const body = lastPreviewBody(writes)
		expect(body.rules, 'both rules must be previewed').toHaveLength(2)
		expect(
			body.rules.find((r) => r.isInclude === false)?.ruleConfig,
			'the exclude row must be sent with its window',
		).toEqual({ startDate: '2026-07-01', endDate: '2026-07-31' })

		await expect(page.locator(PREVIEW_VERDICT)).toHaveText(/^\s*Hidden\s*$/)

		// CONTROL: the include rule DID match, so Hidden is the exclude
		// override and not an include miss.
		await expect(
			page.locator('[data-test="preview-matched-include"]'),
			'CONTROL: the include rule must be shown as matched, or Hidden could be an include miss',
		).toHaveCount(1)

		const reason = page.locator('[data-test="preview-matched-exclude"]')
		await expect(
			reason,
			'the matched exclude rule must be indicated as the reason',
		).toHaveCount(1)
		await expect(reason).toContainText('2026-07-01')
		await expect(page.locator('[data-test="visibility-sentence"]')).toHaveText(
			/^.+ does not see this widget, because .+ is within the period\.$/,
		)
	})

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

/*
 * REQ-CVUI-005, measured against REAL AUDIENCES.
 *
 * "The same evaluation path" is a wiring claim, and an e2e cannot read
 * wiring. What it can do is the thing the wiring is for: put the same rule
 * set in front of the preview endpoint and in front of a real user of that
 * audience, at the same moment, and compare. Each audience here is an
 * ordinary throwaway account that OWNS its dashboard (rules are owner-only:
 * `verifyPlacementOwnership` in RuleApiController), with a subject tile that
 * carries the rules and an anchor tile that carries none.
 *
 * The render-time verdict is read twice: as the pipeline reports it
 * (`isVisible` on `GET /api/widgets/{id}/rules`, which is
 * `ConditionalService::checkRulesForPlacement()` for that user), and as the
 * browser renders the dashboard for that user. The preview is called by the
 * same user, with the context set to that user's ACTUAL groups (read back
 * from Nextcloud, not assumed) and the current instant.
 *
 */
test.describe('conditional-visibility editor — preview equals render for a real audience (REQ-CVUI-005)', () => {
	const API = '/index.php/apps/launchpad/api'
	const AUDIENCE_GROUP = `cve-audience-${STAMP}`
	const OTHER_GROUP = `cve-other-${STAMP}`

	interface Audience {
		who: Principal
		dashboardId: number
		subjectId: number
		subject: string
		anchor: string
		groups: string[]
	}

	type Rule = {
		ruleType: string
		ruleConfig: Record<string, unknown>
		isInclude: boolean
	}

	let member: Audience | null = null
	let outsider: Audience | null = null
	/*
	 * Rule writes ship admin-only, and each audience must write rules on its
	 * own placement, so the rule actions are opened to every user for this
	 * block and the stored matrix is put back afterwards.
	 */
	let priorMatrix: ActionMatrix | null = null

	/** Seed a dashboard the principal owns, with a subject and an anchor tile. */
	async function seedAudience(
		admin: APIRequestContext,
		who: Principal,
		label: string,
	): Promise<Audience> {
		await assertActingAs(who.api, who.username)
		const subject = `Audience subject ${label} ${STAMP}`
		const anchor = `Audience anchor ${label} ${STAMP}`

		const created = await who.api.post(`${API}/dashboard`, {
			data: { name: `E2E Audience ${label} ${STAMP}` },
		})
		expect(
			created.status(),
			`${who.username} creating a dashboard: ${await created.text()}`,
		).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		const dashboardId = Number(dash.id)

		const tile = async (title: string, gridX: number): Promise<number> => {
			const res = await who.api.post(`${API}/dashboard/${dashboardId}/tile`, {
				data: {
					title,
					linkType: 'url',
					linkValue: `https://example.invalid/${encodeURIComponent(title)}`,
					gridX,
					gridY: 0,
					gridWidth: 3,
					gridHeight: 2,
				},
			})
			expect(
				res.status(),
				`seeding tile "${title}": ${await res.text()}`,
			).toBeLessThan(300)
			return Number((await res.json()).id)
		}
		const subjectId = await tile(subject, 0)
		await tile(anchor, 4)

		// Both activation mechanisms, as in the suite's own beforeAll.
		const activate = await who.api.post(
			`${API}/dashboard/${dashboardId}/activate`,
		)
		expect(activate.status(), await activate.text()).toBeLessThan(300)
		const preference = await who.api.post(`${API}/dashboards/active`, {
			data: { uuid: String(dash.uuid) },
		})
		expect(preference.status(), await preference.text()).toBeLessThan(300)

		// The audience's real group set, as Nextcloud reports it.
		const groupsRes = await admin.get(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(who.username)}/groups?format=json`,
		)
		expect(groupsRes.status(), await groupsRes.text()).toBe(200)
		const groups: string[] = (await groupsRes.json()).ocs?.data?.groups ?? []

		return { who, dashboardId, subjectId, subject, anchor, groups }
	}

	/** The audience's stored rules, and the pipeline's verdict for that user. */
	async function pipeline(
		a: Audience,
	): Promise<{ rules: Array<Record<string, unknown>>; isVisible: unknown }> {
		const res = await a.who.api.get(`${API}/widgets/${a.subjectId}/rules`)
		expect(res.status(), `reading rules: ${await res.text()}`).toBe(200)
		const body = await res.json()
		return {
			rules: body.rules ?? body.data?.rules ?? [],
			isVisible: body.isVisible ?? body.data?.isVisible,
		}
	}

	/** Replace the subject's rules with `rules`, through the CRUD endpoints. */
	async function storeRules(a: Audience, rules: Rule[]): Promise<void> {
		for (const old of (await pipeline(a)).rules) {
			const res = await a.who.api.delete(`${API}/rules/${old.id}`)
			expect(res.status(), await res.text()).toBeLessThan(300)
		}
		for (const rule of rules) {
			const res = await a.who.api.post(`${API}/widgets/${a.subjectId}/rules`, {
				data: rule,
			})
			expect(
				res.status(),
				`storing ${rule.isInclude ? 'an include' : 'an exclude'} ${rule.ruleType} rule: ${await res.text()}`,
			).toBe(201)
		}
		expect(
			(await pipeline(a)).rules,
			'CONTROL: the stored rule set must be exactly the one under test',
		).toHaveLength(rules.length)
	}

	/** Preview the STORED rule set, as the audience, for its own groups, now. */
	async function previewStored(a: Audience): Promise<boolean> {
		const stored = (await pipeline(a)).rules
		const res = await a.who.api.post(`${API}/visibility/preview`, {
			data: {
				rules: stored.map((r) => ({
					id: r.id,
					ruleType: r.ruleType,
					ruleConfig: r.ruleConfig,
					isInclude: r.isInclude,
				})),
				context: { groups: a.groups, datetime: new Date().toISOString() },
			},
		})
		expect(res.status(), `preview: ${await res.text()}`).toBe(200)
		const body = await res.json()
		expect(
			Object.keys(body).sort(),
			'the preview must answer with its documented fields',
		).toEqual(['matchedExcludeRuleIds', 'matchedIncludeRuleIds', 'visible'])
		return body.visible
	}

	/** Run `fn` in a browser session that is the audience's own login. */
	async function asAudience<T>(
		browser: Browser,
		a: Audience,
		fn: (page: Page) => Promise<T>,
	): Promise<T> {
		const session: { context: BrowserContext; page: Page } = await loginAs(
			browser,
			a.who.username,
			a.who.password,
		)
		try {
			return await fn(session.page)
		} finally {
			await session.context.close()
		}
	}

	test.beforeAll(async () => {
		const admin = await adminApi()
		priorMatrix = await grantRuleActionsToAll(admin)
		for (const id of [AUDIENCE_GROUP, OTHER_GROUP]) {
			await createGroup(admin, id)
			groupsCreated.push(id)
		}
		const m = await createPrincipal(admin, 'cve-member')
		await addToGroup(admin, m.username, AUDIENCE_GROUP)
		member = await seedAudience(admin, m, 'member')
		const o = await createPrincipal(admin, 'cve-outsider')
		outsider = await seedAudience(admin, o, 'outsider')
		await admin.dispose()

		expect(
			member.groups,
			'CONTROL: the member must really be in the audience group',
		).toContain(AUDIENCE_GROUP)
		expect(
			outsider.groups,
			'CONTROL: the outsider must really be outside it',
		).not.toContain(AUDIENCE_GROUP)
	})

	test.afterAll(async () => {
		const admin = await adminApi()
		for (const a of [member, outsider]) {
			if (a !== null) {
				await a.who.api.delete(`${API}/dashboard/${a.dashboardId}`)
				await removePrincipal(admin, a.who)
			}
		}
		member = null
		outsider = null
		if (priorMatrix !== null) {
			await writeActionMatrix(admin, priorMatrix)
			priorMatrix = null
		}
		await admin.dispose()
	})

	// @e2e conditional-visibility-editor::endpoint-delegates-to-the-shared-evaluation-service
	test('for every rule shape, the preview verdict is the verdict the render-time pipeline reaches', async ({
		browser,
	}) => {
		const a = member!
		const year = new Date().getUTCFullYear()
		const shapes: Array<{ name: string; expected: boolean; rules: Rule[] }> = [
			{ name: 'no rules', expected: true, rules: [] },
			{
				name: 'include: a group the user is in',
				expected: true,
				rules: [
					{
						ruleType: 'group',
						ruleConfig: { groups: [AUDIENCE_GROUP] },
						isInclude: true,
					},
				],
			},
			{
				name: 'include: a group the user is not in',
				expected: false,
				rules: [
					{
						ruleType: 'group',
						ruleConfig: { groups: [OTHER_GROUP] },
						isInclude: true,
					},
				],
			},
			{
				name: 'include OR: one miss, one match',
				expected: true,
				rules: [
					{
						ruleType: 'group',
						ruleConfig: { groups: [OTHER_GROUP] },
						isInclude: true,
					},
					{
						ruleType: 'group',
						ruleConfig: { groups: [AUDIENCE_GROUP] },
						isInclude: true,
					},
				],
			},
			{
				name: 'include: a date window that has closed',
				expected: false,
				rules: [
					{
						ruleType: 'date',
						ruleConfig: {
							startDate: `${year - 2}-01-01`,
							endDate: `${year - 2}-12-31`,
						},
						isInclude: true,
					},
				],
			},
			{
				name: 'include: an open-ended date that has started',
				expected: true,
				rules: [
					{
						ruleType: 'date',
						ruleConfig: { startDate: `${year - 1}-01-01` },
						isInclude: true,
					},
				],
			},
			{
				name: 'include: a time window covering the whole day',
				expected: true,
				rules: [
					{
						ruleType: 'time',
						ruleConfig: { startTime: '00:00', endTime: '23:59' },
						isInclude: true,
					},
				],
			},
			// Last on purpose: it cannot be stored until launchpad#96 is fixed,
			// and its failure must not hide the verdicts of the shapes above.
			{
				name: 'include matches, but a matching exclude overrides it',
				expected: false,
				rules: [
					{
						ruleType: 'group',
						ruleConfig: { groups: [AUDIENCE_GROUP] },
						isInclude: true,
					},
					{
						ruleType: 'date',
						ruleConfig: { startDate: `${year - 1}-01-01` },
						isInclude: false,
					},
				],
			},
		]

		const seen = new Set<boolean>()
		try {
			await asAudience(browser, a, async (page) => {
				for (const shape of shapes) {
					await storeRules(a, shape.rules)
					const previewed = await previewStored(a)
					seen.add(previewed)
					expect
						.soft(previewed, `[${shape.name}] preview verdict`)
						.toBe(shape.expected)
					expect
						.soft(
							(await pipeline(a)).isVisible,
							`[${shape.name}] the pipeline's verdict must equal the preview's`,
						)
						.toBe(previewed)
					expect
						.soft(
							await renderedVerdict(page, a.subject, a.anchor),
							`[${shape.name}] what the user is shown must equal the preview's verdict (launchpad#98)`,
						)
						.toBe(previewed)
				}
			})
		} finally {
			await storeRules(a, []).catch(() => null)
		}

		// CONTROL: equality between two booleans that were always `true`
		// would prove nothing.
		expect(
			[...seen].sort(),
			'CONTROL: the shapes must produce both verdicts',
		).toEqual([false, true])
	})

	// @e2e conditional-visibility-editor::preview-verdict-matches-render-time-verdict-for-identical-inputs
	test('one rule set, two real audiences: each sees exactly what the preview predicted for them', async ({
		browser,
	}) => {
		const R: Rule[] = [
			{
				ruleType: 'group',
				ruleConfig: { groups: [AUDIENCE_GROUP] },
				isInclude: true,
			},
		]

		const verdicts: Record<
			string,
			{ preview: boolean; pipeline: unknown; rendered: boolean }
		> = {}
		try {
			for (const [label, a] of [
				['member', member!],
				['outsider', outsider!],
			] as const) {
				await storeRules(a, R)
				const preview = await previewStored(a)
				const pipelineVerdict = (await pipeline(a)).isVisible
				const rendered = await asAudience(browser, a, (page) =>
					renderedVerdict(page, a.subject, a.anchor),
				)
				verdicts[label] = { preview, pipeline: pipelineVerdict, rendered }
			}
		} finally {
			for (const a of [member, outsider]) {
				if (a !== null) {
					await storeRules(a, []).catch(() => null)
				}
			}
		}

		// CONTROL: the two audiences must get DIFFERENT predictions, or an
		// endpoint that always answers the same thing would pass.
		expect(
			verdicts.member.preview,
			'CONTROL: the preview must predict Visible for a member of the group',
		).toBe(true)
		expect(
			verdicts.outsider.preview,
			'CONTROL: the preview must predict Hidden for a non-member',
		).toBe(false)

		for (const label of ['member', 'outsider']) {
			expect(
				verdicts[label].pipeline,
				`${label}: the render-time pipeline must reach the preview's verdict`,
			).toBe(verdicts[label].preview)
			expect(
				verdicts[label].rendered,
				`${label}: what the user is actually shown must equal the preview's verdict (launchpad#98)`,
			).toBe(verdicts[label].preview)
		}
	})
})
