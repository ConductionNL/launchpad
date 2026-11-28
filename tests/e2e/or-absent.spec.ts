/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for LaunchPad on an instance WITHOUT
 * OpenRegister (and without OpenConnector), for the scenarios of
 * openspec/specs/runtime-or-consumption (task 5.12) and
 * openspec/specs/launchpad-adopt-or-abstractions (task 5.8) whose GIVEN is
 * "OpenRegister is not installed".
 *
 * Those scenarios were excluded as "one CI job cannot be two instances": the
 * Playwright fixture installs OpenRegister on purpose. It does not take a
 * second instance. `occ app:disable openregister` makes this one OR-less for
 * the length of this file, which is what LaunchPad sees on an install without
 * it: the app's routes, services and classes are gone. Both apps are put back
 * with `occ app:enable` in afterAll, whatever happened, and the suite runs
 * one worker (`playwright.config.ts`), so no other spec runs while OR is off.
 * Because it switches apps off, the file refuses a shared instance.
 *
 * What stays excluded, in the specs: `useOrFeatureDetect()` and its "no
 * request to OpenRegister" (never built), tenant scope and delegation (never
 * built), and the checked-in-file scenarios.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-or-consumption::fresh-install-without-or-boots-normally
 *   @e2e runtime-or-consumption::or-absent-empty-state-shown
 *   @e2e launchpad-adopt-or-abstractions::sharing-on-or-less-install
 *   @e2e launchpad-adopt-or-abstractions::install-on-or-less-nextcloud
 *
 * @spec openspec/specs/runtime-or-consumption/spec.md
 * @spec openspec/specs/launchpad-adopt-or-abstractions/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { refuseOnSharedInstance } from './shared-instance.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { describeRun, logDuring, runOcc } from './support/occ.ts'
import {
	degradedLabel,
	objectListWidget,
	OR_REGISTER,
	OR_SCHEMA,
	RAW_ERROR,
} from './support/orWidget.ts'
import { setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import {
	addToGroup,
	adminContext,
	API,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const ADMIN_SETTINGS = '/index.php/settings/admin/launchpad'
const RUN = Date.now()
const STAMP = `E2E OR-less ${RUN}`

/** The apps a GIVEN of "neither OR nor OC" switches off. */
const OR_APPS = ['openregister', 'openconnector']

/**
 * What the log must not carry on an OR-less instance: a missing-dependency
 * complaint, a fatal error, or a LaunchPad class reaching for an OpenRegister
 * class that is not there.
 */
const BROKEN_DEPENDENCY = /missing dependency|fatal|Class "?\\?OCA\\OpenRegister\\[^ ]* not found/i

let admin: APIRequestContext
let own: OwnDashboard | undefined
let member: Principal | null = null
let group: string | null = null
const switchedOff: string[] = []
const dashboards: number[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(240_000)

/**
 * The apps `occ app:list` reports enabled.
 *
 * @return their ids.
 */
async function enabledApps(): Promise<string[]> {
	const listed = await runOcc('app:list', '--output=json')
	expect(listed.code, describeRun(listed)).toBe(0)
	return Object.keys((JSON.parse(listed.stdout) as { enabled: Record<string, string> }).enabled)
}

/**
 * Collect the page's uncaught errors and console errors.
 *
 * The browser's own "Failed to load resource" line for a 4xx/5xx response is
 * left out: it is network noise, not an error the app raised, and whether the
 * app should call OpenRegister at all without it is the separate (excluded,
 * never built) feature-detect scenario.
 *
 * @param page the page, before it navigates.
 * @return the live list.
 */
function collectErrors(page: Page): string[] {
	const errors: string[] = []
	page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`))
	page.on('console', (msg) => {
		if (msg.type() === 'error' && !msg.text().startsWith('Failed to load resource')) {
			errors.push(`console: ${msg.text()}`)
		}
	})
	return errors
}

/**
 * Open the OR-data dashboard as its owner and return the object-list widget's
 * degraded label once it has settled.
 *
 * @param browser the browser.
 * @return the label text, and whether a spinner is still showing.
 */
async function orWidgetOnOrLessInstance(
	browser: Browser,
): Promise<{ label: string; spinnerVisible: boolean }> {
	const { context, page } = await loginAs(browser, own!.owner.username, own!.owner.password)
	try {
		await page.goto(APP)
		const widget = objectListWidget(page)
		await expect(widget, 'the OR-data widget still renders').toBeVisible({ timeout: 30_000 })
		const label = degradedLabel(widget)
		await expect(label, 'the widget settles on a labelled state').toBeVisible({ timeout: 30_000 })
		return {
			label: (await label.innerText()).trim(),
			spinnerVisible: await widget.locator('.loading-icon, .icon-loading').first().isVisible().catch(() => false),
		}
	} finally {
		await context.close()
	}
}

test.beforeAll(async () => {
	refuseOnSharedInstance(
		BASE_URL,
		'or-absent.spec.ts',
		'It disables the openregister and openconnector apps for the length of the file.',
	)
	admin = await adminContext()

	// The OR-data dashboard is set up while OpenRegister is still there, the
	// way an install that loses OR later already has one.
	own = await setUpOwnDashboard('e2e-orless', `${STAMP} OR data`)
	const placed = await own.owner.api.post(`${API}/dashboard/${own.id}/widgets`, {
		data: {
			widgetId: 'object-list',
			gridX: 0,
			gridY: 0,
			gridWidth: 8,
			gridHeight: 5,
			content: {
				register: OR_REGISTER,
				schema: OR_SCHEMA,
				columns: [{ key: 'title', label: 'Title' }],
			},
		},
	})
	expect(placed.status(), await placed.text()).toBeLessThan(300)

	const enabled = await enabledApps()
	for (const app of OR_APPS.filter((a) => enabled.includes(a))) {
		const off = await runOcc('app:disable', app)
		expect(off.code, describeRun(off)).toBe(0)
		switchedOff.push(app)
	}
	const now = await enabledApps()
	for (const app of OR_APPS) {
		expect(now, `${app} is not enabled for this file`).not.toContain(app)
	}
	expect(now, 'LaunchPad itself is enabled').toContain('launchpad')
})

test.afterAll(async () => {
	// LaunchPad first: the install test switches it off and on.
	const lp = await runOcc('app:enable', 'launchpad')
	for (const app of switchedOff) {
		const on = await runOcc('app:enable', app)
		if (on.code !== 0) {
			console.error(`could not re-enable ${app}\n${describeRun(on)}`)
		}
	}
	if (lp.code !== 0) {
		console.error(`could not re-enable launchpad\n${describeRun(lp)}`)
	}
	if (admin !== undefined) {
		for (const id of dashboards) {
			await admin.delete(`${API}/dashboard/${id}`).catch(() => undefined)
		}
		if (member !== null) {
			await removePrincipal(admin, member)
		}
		if (group !== null) {
			await removeGroup(admin, group)
		}
	}
	await tearDownOwnDashboard(own)
	if (admin !== undefined) {
		await admin.dispose()
	}
})

// ── runtime-or-consumption REQ-OR-001 ──────────────────────────────────

// @e2e runtime-or-consumption::fresh-install-without-or-boots-normally
test('without OpenRegister the app, its dashboards, widgets and admin settings work, and nothing complains in the log', async ({
	page,
}) => {
	const errors = collectErrors(page)
	const messages = await logDuring(async () => {
		await page.goto(APP)
		await expect(page.locator('#launchpad-main-content'), 'the app shell mounts').toBeAttached({
			timeout: 30_000,
		})

		// Dashboards: create, read back, list.
		const created = await admin.post(`${API}/dashboard`, { data: { name: `${STAMP} boot` } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		dashboards.push(Number(dash.id))
		const read = await admin.get(`${API}/dashboard/${dash.id}`)
		expect(read.status(), await read.text()).toBe(200)

		// Widgets: add, restyle, list, remove.
		const added = await admin.post(`${API}/dashboard/${dash.id}/widgets`, {
			data: { widgetId: 'text', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2, content: { text: STAMP } },
		})
		expect(added.status(), await added.text()).toBeLessThan(300)
		const placement = await added.json()
		const placementId = Number(placement.id ?? placement.data?.id ?? placement.placement?.id)
		expect(placementId).toBeGreaterThan(0)
		const styled = await admin.put(`${API}/widgets/${placementId}`, { data: { customTitle: `${STAMP} tile` } })
		expect(styled.status(), await styled.text()).toBe(200)
		const removed = await admin.delete(`${API}/widgets/${placementId}`)
		expect(removed.status(), await removed.text()).toBe(200)

		// Admin settings: the API and the page.
		const settings = await admin.get(`${API}/admin/settings`)
		expect(settings.status(), await settings.text()).toBe(200)
		await page.goto(ADMIN_SETTINGS)
		await expect(page.getByTestId('admin-default-settings'), 'the admin settings page renders').toBeVisible({
			timeout: 30_000,
		})
	})

	expect(errors, 'no uncaught or console error in the app or its admin page').toEqual([])
	expect(
		messages.filter((m) => BROKEN_DEPENDENCY.test(m)),
		'no missing-dependency warning or fatal error in the Nextcloud log',
	).toEqual([])
})

// ── runtime-or-consumption REQ-OR-005 ──────────────────────────────────

// @e2e runtime-or-consumption::or-absent-empty-state-shown
test('without OpenRegister an OR-data widget shows a readable empty state, not a spinner or a raw error', async ({
	browser,
}) => {
	const { label, spinnerVisible } = await orWidgetOnOrLessInstance(browser)
	expect(label, 'a human-readable explanation').not.toBe('')
	expect(label, 'no raw HTTP or stack text').not.toMatch(RAW_ERROR)
	expect(spinnerVisible, 'no spinner left behind').toBe(false)
})

// ── launchpad-adopt-or-abstractions: sharing ───────────────────────────

// @e2e launchpad-adopt-or-abstractions::sharing-on-or-less-install
test('without OpenRegister an add_only group share lets a member see and add, not delete or reshare', async () => {
	group = await createGroup(admin, `e2e-kcc-team-${RUN}`)
	member = await createPrincipal(admin, 'e2e-orless-member')
	await addToGroup(admin, member.username, group)

	const created = await admin.post(`${API}/dashboard`, { data: { name: `${STAMP} shared` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const id = Number(dash.id)
	dashboards.push(id)
	const published = await admin.post(`${API}/dashboards/${dash.uuid}/publish`)
	expect(published.status(), await published.text()).toBe(200)
	const shared = await admin.post(`${API}/dashboard/${id}/shares`, {
		data: { shareType: 'group', shareWith: group, permissionLevel: 'add_only' },
	})
	expect(shared.status(), await shared.text()).toBeLessThan(300)
	const sharesBefore = await (await admin.get(`${API}/dashboard/${id}/shares`)).text()

	// They see it.
	const seen = await member.api.get(`${API}/dashboard/${id}`)
	expect(seen.status(), await seen.text()).toBe(200)

	// They can add a widget.
	const added = await member.api.post(`${API}/dashboard/${id}/widgets`, {
		data: { widgetId: 'text', gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2 },
	})
	expect(added.status(), await added.text()).toBe(201)

	// They cannot delete the dashboard.
	const deleted = await member.api.delete(`${API}/dashboard/${id}`)
	expect(deleted.status(), await deleted.text()).toBe(403)
	expect((await admin.get(`${API}/dashboard/${id}`)).status(), 'the dashboard is still there').toBe(200)

	// They cannot change who it is shared with, or how.
	const reshared = await member.api.post(`${API}/dashboard/${id}/shares`, {
		data: { shareType: 'user', shareWith: member.username, permissionLevel: 'full' },
	})
	expect(reshared.status(), await reshared.text()).toBe(403)
	expect(
		await (await admin.get(`${API}/dashboard/${id}/shares`)).text(),
		'the shares are unchanged',
	).toBe(sharesBefore)
})

// ── launchpad-adopt-or-abstractions: install ───────────────────────────

// @e2e launchpad-adopt-or-abstractions::install-on-or-less-nextcloud
test('without OpenRegister or OpenConnector LaunchPad enables, appears in the menu, renders, and an OR dashboard shows its empty state', async ({
	page,
	browser,
}) => {
	// The apps page's enable posts behind password confirmation, which a
	// suite started more than 30 minutes after login cannot pass, so the
	// enable is occ's: the same AppManager::enableApp() call.
	const off = await runOcc('app:disable', 'launchpad')
	expect(off.code, describeRun(off)).toBe(0)
	expect(await enabledApps()).not.toContain('launchpad')

	const on = await runOcc('app:enable', 'launchpad')
	expect(on.code, `the enable action succeeds\n${describeRun(on)}`).toBe(0)
	expect(await enabledApps()).toContain('launchpad')

	const nav = await admin.get('/ocs/v2.php/core/navigation/apps?format=json&absolute=false')
	expect(nav.status(), await nav.text()).toBe(200)
	const entries = ((await nav.json())?.ocs?.data ?? []) as Array<{ id: string; href: string }>
	expect(
		entries.some((e) => e.id === 'launchpad'),
		`the LaunchPad menu entry is there: ${JSON.stringify(entries.map((e) => e.id))}`,
	).toBe(true)

	const errors = collectErrors(page)
	await page.goto('/index.php')
	// Nextcloud 34's header lists the apps in the apps menu, a popover the
	// header's "Open apps menu" button opens; the entry is a link in it.
	await page.locator('#header').getByRole('button', { name: 'Open apps menu', exact: true }).click()
	await expect(
		page.locator('a[href*="/apps/launchpad"]').filter({ hasText: 'Launchpad' }).first(),
		'the menu entry is in the header\'s apps menu',
	).toBeVisible({ timeout: 30_000 })
	await page.keyboard.press('Escape')
	await page.goto(APP)
	await expect(page.locator('#launchpad-main-content'), 'the dashboards index renders').toBeAttached({
		timeout: 30_000,
	})
	expect(errors, 'without error').toEqual([])

	const { label, spinnerVisible } = await orWidgetOnOrLessInstance(browser)
	expect(label, 'the pre-existing OR dashboard shows a documented empty state').not.toBe('')
	expect(label).not.toMatch(RAW_ERROR)
	expect(spinnerVisible).toBe(false)
})
