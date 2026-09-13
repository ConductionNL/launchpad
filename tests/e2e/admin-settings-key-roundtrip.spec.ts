/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin-settings GET → PUT round-trip (REQ-ASET-001, REQ-ASET-002).
 *
 * 🔴 WHY THIS EXISTS: A `{"status":"ok"}` THAT WROTE NOTHING
 * =========================================================
 * `GET /api/admin/settings` answers with long keys — `allowUserDashboards`,
 * `allowMultipleDashboards`, `defaultGridColumns`, `linkCreateFileExtensions`,
 * `defaultPermissionLevel`. `PUT /api/admin/settings` bound only the SHORT
 * spellings — `allowUserDash`, `allowMultiDash`, `defaultGridCols`,
 * `linkCreateFileExts`, `defaultPermLevel`.
 *
 * Nextcloud does not reject a parameter it cannot bind; it leaves the argument
 * at its default, and every one of those arguments defaults to `null` — which
 * `AdminSettingsService::updateSettings()` reads as "not supplied". So a caller
 * that reads the settings, edits one field and writes the object back wrote
 * NOTHING, and was answered `{"status":"ok"}` for it.
 *
 * Measured on a live instance before the fix:
 *
 *     PUT {"allowUserDashboards":true,"defaultGridColumns":8}  ->  {"status":"ok"}
 *     GET                                    ->  allowUserDashboards=false, defaultGridColumns=12
 *
 * A 200 that changed nothing is indistinguishable from a 200 that worked, which
 * is why nothing caught this. THE ASSERTION THAT MATTERS IS THEREFORE NOT THE
 * STATUS CODE — it is the re-read. Every test below writes, then reads back, and
 * fails on the value rather than on the response.
 *
 * THE SPEC USED TO BLESS THE DEFECT. REQ-ASET-002's scenario "Update a single
 * boolean setting" carried a NOTE reading "The API update endpoint accepts
 * abbreviated camelCase parameter names ... NOT the full response key names."
 * That note was rewritten alongside these tests, and REQ-ASET-002 gained the two
 * scenarios this file cites: the GET-to-PUT round trip, and the rule that the
 * short name wins when both arrive.
 *
 * WHAT IS DELIBERATELY NOT ASSERTED. That an unknown key is *rejected*. It is
 * not — REQ-ASET-002 "Update with unknown setting key" says unrecognised keys
 * are ignored, and that stays true. The defect was never that unknown keys are
 * ignored; it was that the endpoint's OWN read vocabulary was among them.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext, Page, request } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const SETTINGS = `${BASE}/index.php/apps/launchpad/api/admin/settings`
const APP_URL = '/index.php/apps/launchpad'

/**
 * Every key the GET side publishes that the PUT side aliases, paired with the
 * short spelling that has always worked and a value guaranteed to differ from
 * the factory default.
 *
 * The short spelling is here for two reasons: it restores the instance in
 * `afterAll` through a path that is known good, and it lets the tie-break test
 * send both spellings in one body.
 */
const ALIASED_KEYS: Array<{
	long: string
	short: string
	/** A value that differs from this instance's default. */
	probe: unknown
	/** A second distinct value, so neither leg can pass on a stuck state. */
	counterProbe: unknown
}> = [
	{
		long: 'allowUserDashboards',
		short: 'allowUserDash',
		probe: false,
		counterProbe: true,
	},
	{
		long: 'allowMultipleDashboards',
		short: 'allowMultiDash',
		probe: false,
		counterProbe: true,
	},
	{
		long: 'defaultGridColumns',
		short: 'defaultGridCols',
		probe: 8,
		counterProbe: 12,
	},
	{
		long: 'linkCreateFileExtensions',
		short: 'linkCreateFileExts',
		probe: ['txt', 'md'],
		counterProbe: ['txt', 'md', 'docx'],
	},
	{
		long: 'defaultPermissionLevel',
		short: 'defaultPermLevel',
		probe: 'view_only',
		counterProbe: 'add_only',
	},
]

/** An admin-authenticated request context using HTTP Basic auth. */
async function adminApi(playwright: {
	request: { newContext: typeof request.newContext }
}): Promise<APIRequestContext> {
	return playwright.request.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/** Read the whole settings object, failing loudly on a non-2xx. */
async function readSettings(
	api: APIRequestContext,
): Promise<Record<string, unknown>> {
	const res = await api.get(SETTINGS)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()) as Record<string, unknown>
}

/**
 * Write a body and assert only that the call was accepted.
 *
 * The status is NOT evidence of a write — that is the whole point of this file
 * — so callers must follow every `writeSettings` with a `readSettings`.
 *
 * @param api an admin-authenticated request context.
 * @param body the PUT payload.
 * @return the parsed response body.
 */
async function writeSettings(
	api: APIRequestContext,
	body: Record<string, unknown>,
): Promise<unknown> {
	const res = await api.put(SETTINGS, { data: body })
	expect(res.status(), await res.text()).toBe(200)
	return await res.json()
}

/*
 * NOT `serial`. Under `serial` the first red test skips the remaining six, and
 * the run list would show one failure where five distinct keys are broken — the
 * reviewer could not tell how wide the defect is. The config already pins
 * `workers: 1` and `fullyParallel: false`, so they still run one at a time.
 *
 * 🔴 WHAT MAKES THAT SAFE IS `afterEach`, AND IT WAS MISSING AT FIRST. These
 * tests used to rely on each one arranging its own precondition, and that held
 * only while the writes were broken. The first CI run against the fixed
 * controller proved it: the `allowMultipleDashboards` test really did leave the
 * flag `false`, which caps every user at one dashboard, and the browser leg two
 * tests later failed its PRECONDITION — the Add-Dashboard control was on screen
 * but disabled, and a disabled control swaps its accessible name for the quota
 * tooltip ("You have reached the limit of 1 dashboards"). Reproduced on the dev
 * instance by applying exactly that leaked state. A leak that only exists once
 * the fix works is invisible on the code the test was written against.
 */
test.describe('admin settings — the keys GET publishes are the keys PUT accepts', () => {
	let api: APIRequestContext
	let baseline: Record<string, unknown>
	let seeded: SeededDashboard | null = null

	/**
	 * Put every aliased key back to the value it had before this file ran.
	 *
	 * Written through the SHORT spellings on purpose: they work on fixed and
	 * unfixed code alike, so the restore succeeds whether or not the fix is
	 * present — which is the one situation a cleanup must not depend on.
	 *
	 * @return nothing.
	 */
	const restoreBaseline = async (): Promise<void> => {
		const restore: Record<string, unknown> = {}
		for (const { long, short } of ALIASED_KEYS) {
			if (baseline[long] !== undefined) {
				restore[short] = baseline[long]
			}
		}
		await api.put(SETTINGS, { data: restore })
	}

	test.beforeAll(async ({ playwright }) => {
		api = await adminApi({ request: playwright.request })
		baseline = await readSettings(api)

		// Seeding a second personal dashboard is refused outright ("Multiple
		// dashboards not allowed") while `allowMultipleDashboards` is off and
		// the admin already owns one — measured on the dev instance. The
		// baseline is restored in `afterAll`.
		await api.put(SETTINGS, { data: { allowMultiDash: true } })

		/*
		 * SEED A DASHBOARD FOR THE BROWSER LEG. `.launchpad-sidebar-toggle`
		 * lives in `.launchpad-floating-controls`, which renders only once a
		 * dashboard is ACTIVE. `tests/e2e/seed.sh` creates the `e2e-grantee`
		 * USER and nothing else, so on the cold instance CI provisions
		 * LaunchPad renders "No dashboards available" and the toggle is simply
		 * absent — the failure mode that had four other specs excluded from
		 * this suite. Seeding here rather than leaning on whatever an earlier
		 * spec left behind is why this passes on a cold rig as well as a warm
		 * one.
		 */
		seeded = await seedActiveDashboard(api, `E2E SettingsKeys ${Date.now()}`)
	})

	/*
	 * 🔴 RESTORE IN `afterAll`, NOT AT THE END OF A TEST BODY. These tests flip
	 * `allowUserDashboards` off, and this suite runs against an instance other
	 * specs share — `dashboardFixture.seedActiveDashboard` refuses to create a
	 * dashboard while that flag is off. A test that failed halfway through and
	 * left the flag down would redden every spec scheduled after it, and the
	 * cause would be invisible in their output.
	 *
	 * The restore goes through the SHORT spellings on purpose: they are the
	 * ones that work on unfixed code, so cleanup succeeds whether or not the
	 * fix is present.
	 */
	test.afterEach(async () => {
		if (api !== undefined && baseline !== undefined) {
			await restoreBaseline()
		}
	})

	test.afterAll(async () => {
		if (api === undefined || baseline === undefined) {
			return
		}
		// Personal-dashboard creation must be ON for the delete to be allowed,
		// and the baseline may have it OFF.
		await api.put(SETTINGS, { data: { allowUserDash: true } })
		await removeSeededDashboard(api, seeded)
		await restoreBaseline()
		await api.dispose()
	})

	for (const { long, short, probe, counterProbe } of ALIASED_KEYS) {
		// @e2e admin-settings::a-settings-object-read-from-get-can-be-written-back-through-put
		test(`PUT accepts the response key "${long}" and the value actually lands`, async () => {
			// Arrange through the SHORT spelling, which works on unfixed code.
			// Without this the probe could coincide with the value already
			// stored, and a no-op write would read back as a success.
			await writeSettings(api, { [short]: counterProbe })
			const before = await readSettings(api)
			expect(
				before[long],
				`could not establish a starting value for ${long}`,
			).toEqual(counterProbe)

			// Act through the LONG spelling — the one a GET → edit → PUT caller
			// naturally sends, and the one that was silently dropped.
			const ack = await writeSettings(api, { [long]: probe })

			// The endpoint said ok. That is not the assertion.
			expect(ack).toMatchObject({ status: 'ok' })

			// THIS is the assertion. Re-read and require the write to have landed.
			const after = await readSettings(api)
			expect(
				after[long],
				`PUT {"${long}": ${JSON.stringify(probe)}} answered ${JSON.stringify(ack)} `
					+ `and left the stored value at ${JSON.stringify(after[long])}. `
					+ `A 200 that wrote nothing is the defect this test exists for: the `
					+ `PUT side must bind "${long}" as well as "${short}".`,
			).toEqual(probe)
		})
	}

	// @e2e admin-settings::both-spellings-in-one-body-resolve-to-the-short-one
	test('a body carrying BOTH spellings resolves to the short one', async () => {
		/*
		 * Accepting two names for one setting creates a case that did not exist
		 * before: what happens when both arrive and disagree. The rule is that
		 * the short spelling wins — it is the documented parameter, every
		 * existing caller in this repo sends it (`support/dashboardFixture.ts`
		 * among them), and a new alias must not be able to override it.
		 *
		 * Unpinned, this is exactly the kind of tie-break that flips during a
		 * later refactor without anything going red.
		 *
		 * ⚠️ BE HONEST ABOUT WHAT THIS PROVES TODAY. On unfixed code the long
		 * spelling is dropped entirely, so this test passes without the tie-break
		 * ever being exercised — "short wins" and "long is ignored" are the same
		 * observation here. It only starts measuring the rule once the PUT side
		 * binds both names, which is precisely when the rule starts to exist. It
		 * is a green line in the run list that is not yet evidence.
		 */
		await writeSettings(api, { allowUserDash: false })
		expect((await readSettings(api)).allowUserDashboards).toBe(false)

		await writeSettings(api, {
			allowUserDash: true,
			allowUserDashboards: false,
		})

		expect(
			(await readSettings(api)).allowUserDashboards,
			'when both spellings are supplied the short one must win',
		).toBe(true)
	})

	// @e2e admin-settings::a-settings-object-read-from-get-can-be-written-back-through-put
	test('a value written through the response key reaches the rendered app', async ({
		page,
	}) => {
		/*
		 * TWO FULL SHELL LOADS IN ONE TEST, AND THEY ARE NOT CHEAP. Measured on
		 * this fleet's dev instance, one `/apps/launchpad` load takes 55-60s to
		 * settle the workspace shell — a 13 MB main bundle plus, with the legacy
		 * widget bridge on, every enabled app's widget scripts. Two of them
		 * overrun the config's 60s per-test timeout, and the symptom is not a
		 * timeout message but `locator.click: Target page, context or browser has
		 * been closed`, which reads like a bug in the test. `test.slow()` triples
		 * the budget, the same way `spec-coverage/demo-data-setup-step.spec.ts`
		 * buys room for its genuinely slow import.
		 */
		test.slow()

		/*
		 * 🔴 THE BROWSER LEG. The tests above prove the value reaches storage.
		 * This one proves it reaches a user, which is the reason the setting
		 * exists at all — `allowUserDashboards` is pushed into the initial state
		 * by `InitialStateBuilder` and gates the sidebar's Add-Dashboard control.
		 *
		 * Both directions are asserted in one test on purpose. A single
		 * direction can pass on a stuck instance: if the flag happened to
		 * already sit where the test wanted it, a write that did nothing would
		 * still show the expected UI.
		 */
		const addButton = async (): Promise<void> => {
			// `domcontentloaded`, not the default `load`. This instance serves a
			// 13 MB main bundle and, with the legacy widget bridge on, the widget
			// scripts of every enabled app on top — measured at 55-60s to fire
			// `load`, which is the config's whole 60s navigationTimeout. The
			// `waitForSelector` below is the real gate anyway: it waits for the
			// shell to have rendered, which `load` does not promise either.
			await page.goto(APP_URL, { waitUntil: 'domcontentloaded' })
			await page.waitForSelector(
				'.launchpad-floating-controls, .workspace-shell',
				{ timeout: 20_000 },
			)
			await openSidebar(page)
		}

		// Precondition through the short spelling: flag ON, control present.
		// `allowMultiDash` is set too because the control's accessible name
		// depends on it: with multiple dashboards off, the admin is at a quota
		// of one and the button renders DISABLED, named after the quota
		// tooltip rather than "Add dashboard". A baseline with that flag off
		// would fail this precondition for a reason unrelated to the setting
		// under test.
		await writeSettings(api, { allowUserDash: true, allowMultiDash: true })
		await addButton()
		const sidebar = page.locator('.dashboard-switcher-sidebar')
		const add = sidebar.getByRole('button', {
			name: /add dashboard|dashboard toevoegen/i,
		})
		await expect(
			add,
			'precondition failed: the control is absent with the flag ON',
		).toBeVisible({ timeout: 8_000 })

		// Turn it OFF through the LONG spelling only. On unfixed code this
		// write is a no-op and the control below stays on screen.
		await writeSettings(api, { allowUserDashboards: false })
		await addButton()
		await expect(
			page
				.locator('.dashboard-switcher-sidebar')
				.getByRole('button', { name: /add dashboard|dashboard toevoegen/i }),
			'PUT {"allowUserDashboards": false} did not reach the rendered app: '
				+ 'the Add-Dashboard control is still there.',
		).toHaveCount(0)
	})
})

/**
 * Open the dashboard-switcher sidebar via the floating toggle.
 *
 * Targets `.launchpad-sidebar-toggle` exactly. `.launchpad-floating-controls`
 * also hosts the dashboard-cog and Share buttons and the cog precedes the
 * toggle in DOM order, so a comma-selector `.first()` opens the wrong popover
 * and leaves the sidebar shut.
 *
 * @param page the page showing the workspace shell.
 * @return nothing.
 */
async function openSidebar(page: Page): Promise<void> {
	const toggle = page.locator('.launchpad-sidebar-toggle').first()
	await expect(toggle).toBeVisible({ timeout: 10_000 })
	const isOpen = await page
		.locator('.dashboard-switcher-sidebar.open')
		.isVisible()
		.catch(() => false)
	if (!isOpen) {
		await toggle.click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', {
			timeout: 8_000,
		})
	}
}
