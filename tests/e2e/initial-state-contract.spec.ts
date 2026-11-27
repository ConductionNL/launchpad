/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the initial-state contract
 * (openspec/specs/initial-state-contract), written for
 * e2e-coverage-programme task 5.11.
 *
 * Nextcloud writes every `provideInitialState` value into the page as
 * `<input type="hidden" id="initial-state-launchpad-<key>" value="<base64 json>">`,
 * and `loadState('launchpad', <key>)` is a decode of exactly that input. The
 * tests decode the same inputs in the browser and compare them with what the
 * API says about the same account, as a throwaway non-admin account and as
 * the administrator.
 *
 * The schema-version warning is provoked the way deploy skew provokes it: the
 * page HTML is rewritten in flight so the server's `_schemaVersion` is one
 * ahead of the bundle, and the console is read for the warning while the
 * dashboard still opens.
 *
 * The remaining scenarios are about source code (a missing setter, a direct
 * call, a provide wrapped in ref) or about values inside the bundle that the
 * page never exposes; each carries its exclusion, with the PHPUnit / Vitest
 * test that pins it, in the spec.
 *
 * Gate-19 @e2e traceability:
 *   @e2e initial-state-contract::builder-writes-all-keys
 *   @e2e initial-state-contract::schema-version-mismatch-warning
 *
 * @spec openspec/specs/initial-state-contract/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const ADMIN_SETTINGS = '/index.php/settings/admin/launchpad'
const PREFIX = 'initial-state-launchpad-'
const RUN = Date.now()
const NAME = `E2E Initial state ${RUN}`
const ROW = '.dashboard-switcher-sidebar__item'

/** The ten workspace keys of the spec's Data Model (REQ-INIT-002). */
const WORKSPACE_KEYS = [
	'widgets',
	'layout',
	'primaryGroup',
	'primaryGroupName',
	'isAdmin',
	'activeDashboardId',
	'dashboardSource',
	'groupDashboards',
	'userDashboards',
	'allowUserDashboards',
]

/** The four admin-page keys of the spec's Data Model (REQ-INIT-002). */
const ADMIN_KEYS = ['allGroups', 'configuredGroups', 'widgets', 'allowUserDashboards']

type State = Record<string, unknown>
type Dashboard = { id: number; uuid: string }

let admin: APIRequestContext
let user: Principal | null = null
let dash: Dashboard

/**
 * Decode every LaunchPad initial-state input on the page, the way
 * `loadState('launchpad', key)` does.
 *
 * @param page the page, after the document loaded.
 * @return key to decoded value.
 */
async function initialState(page: Page): Promise<State> {
	return page.evaluate((prefix) => {
		const out: Record<string, unknown> = {}
		const inputs = document.querySelectorAll<HTMLInputElement>(`input[type="hidden"][id^="${prefix}"]`)
		for (const input of Array.from(inputs)) {
			const bytes = Uint8Array.from(atob(input.value), (c) => c.charCodeAt(0))
			out[input.id.slice(prefix.length)] = JSON.parse(new TextDecoder().decode(bytes))
		}
		return out
	}, PREFIX)
}

/**
 * Log in as the throwaway account.
 *
 * @param browser the browser.
 * @return the context and page.
 */
async function asUser(browser: Browser) {
	return loginAs(browser, user!.username, user!.password)
}

/**
 * Expect every workspace key of the Data Model to be present, with its type.
 *
 * @param state the decoded initial state.
 * @return nothing.
 */
function expectWorkspaceShape(state: State): void {
	for (const key of WORKSPACE_KEYS) {
		expect(state, `workspace initial state lacks "${key}"`).toHaveProperty(key)
		expect(state[key], `"${key}" is undefined`).not.toBeUndefined()
	}
	expect(Array.isArray(state.widgets)).toBe(true)
	for (const widget of state.widgets as State[]) {
		expect(widget).toHaveProperty('id')
		expect(widget).toHaveProperty('title')
	}
	expect(Array.isArray(state.layout)).toBe(true)
	expect(typeof state.primaryGroup).toBe('string')
	expect(typeof state.primaryGroupName).toBe('string')
	expect(typeof state.isAdmin).toBe('boolean')
	expect(typeof state.activeDashboardId).toBe('string')
	expect(['user', 'group', 'default']).toContain(state.dashboardSource)
	expect(Array.isArray(state.groupDashboards)).toBe(true)
	for (const row of state.groupDashboards as State[]) {
		expect(Object.keys(row)).toEqual(expect.arrayContaining(['id', 'name', 'icon']))
	}
	expect(Array.isArray(state.userDashboards)).toBe(true)
	for (const row of state.userDashboards as State[]) {
		expect(Object.keys(row)).toEqual(expect.arrayContaining(['id', 'name', 'icon']))
	}
	expect(typeof state.allowUserDashboards).toBe('boolean')
	expect(typeof state._schemaVersion).toBe('number')
}

test.beforeAll(async () => {
	admin = await adminContext()
	const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)

	user = await createPrincipal(admin, 'e2e-initstate')
	await assertActingAs(user.api, user.username)
	const created = await user.api.post(`${API}/dashboard`, { data: { name: NAME } })
	expect(created.status(), await created.text()).toBe(201)
	const body = (await created.json()).dashboard
	dash = { id: Number(body.id), uuid: String(body.uuid) }
	const active = await user.api.post(`${API}/dashboards/default`, { data: { uuid: dash.uuid } })
	expect(active.status(), await active.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	if (user && dash) {
		await user.api.delete(`${API}/dashboard/${dash.id}`)
	}
	await removePrincipal(admin, user)
	await admin.dispose()
})

test.describe.serial('initial-state contract (REQ-INIT-001, REQ-INIT-002)', () => {
	// @e2e initial-state-contract::builder-writes-all-keys
	test('a non-admin workspace page carries all ten keys, with the values the API reports', async ({ browser }) => {
		const placements = await user!.api.get(`${API}/dashboard/${dash.id}`)
		expect(placements.status(), await placements.text()).toBe(200)
		const placementIds = ((await placements.json()).placements as State[])
			.map((p) => Number(p.id))
			.sort((a, b) => a - b)

		const { context, page } = await asUser(browser)
		try {
			const response = await page.goto(APP)
			expect(response?.status()).toBe(200)
			const state = await initialState(page)

			expectWorkspaceShape(state)
			expect(state.isAdmin).toBe(false)
			expect(state.allowUserDashboards).toBe(true)
			expect(state.activeDashboardId).toBe(dash.uuid)
			expect(state.dashboardSource).toBe('user')
			expect(state.userDashboards).toContainEqual({ id: dash.uuid, name: NAME, icon: '' })
			expect((state.layout as State[]).map((p) => Number(p.id)).sort((a, b) => a - b)).toEqual(placementIds)

			// The bundle read them: the switcher opens on the dashboard the
			// initial state named as active.
			await openDashboardSwitcher(page)
			await expect(page.locator(ROW).filter({ hasText: NAME })).toHaveClass(/\bactive\b/)
		} finally {
			await context.close()
		}
	})

	// @e2e initial-state-contract::builder-writes-all-keys
	test('the administrator\'s workspace page carries all ten keys, with isAdmin true', async ({ page }) => {
		const response = await page.goto(APP)
		expect(response?.status()).toBe(200)
		const state = await initialState(page)

		expectWorkspaceShape(state)
		expect(state.isAdmin).toBe(true)
		expect(state.allowUserDashboards).toBe(true)
	})

	test('the admin settings page carries the admin keys, and a non-admin is given none of them', async ({ page, browser }) => {
		const response = await page.goto(ADMIN_SETTINGS)
		expect(response?.status()).toBe(200)
		const state = await initialState(page)
		for (const key of ADMIN_KEYS) {
			expect(state, `admin initial state lacks "${key}"`).toHaveProperty(key)
		}
		expect(state.allGroups).toEqual(expect.arrayContaining([expect.objectContaining({ id: 'admin' })]))
		expect(Array.isArray(state.configuredGroups)).toBe(true)
		expect(Array.isArray(state.widgets)).toBe(true)
		expect(state.allowUserDashboards).toBe(true)
		expect(typeof state._schemaVersion).toBe('number')

		const { context, page: userPage } = await asUser(browser)
		try {
			await userPage.goto(ADMIN_SETTINGS)
			const leaked = await initialState(userPage)
			for (const key of ['allGroups', 'configuredGroups']) {
				expect(leaked, `a non-admin was sent "${key}"`).not.toHaveProperty(key)
			}
		} finally {
			await context.close()
		}
	})

	// @e2e initial-state-contract::schema-version-mismatch-warning
	test('a server schema version ahead of the bundle logs the mismatch warning and the page still loads', async ({ browser }) => {
		const { context, page } = await asUser(browser)
		try {
			let serverVersion: number | null = null
			await page.route(
				(url) => url.pathname.replace(/\/+$/, '') === APP,
				async (route) => {
					if (route.request().resourceType() !== 'document') {
						await route.continue()
						return
					}
					const original = await route.fetch()
					const html = await original.text()
					const pattern = new RegExp(`(<input[^>]*id="${PREFIX}_schemaVersion"[^>]*value=")([^"]*)(")`)
					const match = html.match(pattern)
					expect(match, 'the page carries no _schemaVersion input').not.toBeNull()
					// The bundle is built in lockstep with the server constant,
					// so the served version is the bundle's own.
					const bundleVersion = Number(JSON.parse(Buffer.from(match![2], 'base64').toString('utf8')))
					serverVersion = bundleVersion + 1
					const pushed = Buffer.from(JSON.stringify(serverVersion)).toString('base64')
					await route.fulfill({ response: original, body: html.replace(pattern, `$1${pushed}$3`) })
				},
			)

			const warnings: string[] = []
			page.on('console', (message) => {
				if (message.type() === 'warning') {
					warnings.push(message.text())
				}
			})

			const response = await page.goto(APP)
			expect(response?.status()).toBe(200)
			expect(serverVersion).not.toBeNull()
			expect((await initialState(page))._schemaVersion).toBe(serverVersion)

			const expected = `LaunchPad initial-state schema mismatch: server v${serverVersion} vs client v${serverVersion! - 1} — refresh recommended`
			await expect.poll(() => warnings.some((w) => w.includes(expected)), { timeout: 30_000 }).toBe(true)

			// Graceful degradation: the known keys were still read, so the
			// workspace opens on the account's own active dashboard.
			await expect(page.locator('.workspace-shell')).toBeVisible({ timeout: 30_000 })
			await openDashboardSwitcher(page)
			await expect(page.locator(ROW).filter({ hasText: NAME })).toHaveClass(/\bactive\b/)
		} finally {
			await context.close()
		}
	})
})
