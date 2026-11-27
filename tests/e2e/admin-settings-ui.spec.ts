/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin settings in the browser: the LaunchPad page under Settings >
 * Administration, its form, the program-scheme and launch-template lists, and
 * what the personal-dashboard switch does to the workspace a user is served
 * (admin-settings REQ-ASET-003, REQ-ASET-008, REQ-ASET-015, REQ-ASET-020).
 *
 * 🔴 THE ORDINARY USER IS A REAL, LOGGED-IN BROWSER SESSION. `loginAs` starts
 * from `storageState: undefined` and logs in through the form, so the user's
 * pages are never secretly the admin session from playwright.config. Every
 * absence ("the Add dashboard button is not there") is paired with a presence
 * on the same page (the sidebar is open and lists the user's own dashboard),
 * so a page that never rendered cannot pass as a hidden control.
 *
 * 🔴 THE EMPTY STATE CANNOT BE REACHED. Since #361 `SeedDefaultDashboard`
 * ships an instance-wide dashboard, so an account that owns nothing still
 * lands on a dashboard (measured in active-dashboard-resolution.spec.ts). The
 * create affordance a user can actually meet is the sidebar's "Add dashboard"
 * button (`data-testid="add-dashboard-button"`), which is also the only fork
 * affordance; the spec's `DashboardConfigMenu` does not exist in `src/`. These
 * tests assert on that button, and on the empty-state buttons being absent.
 *
 * Settings are put back in `afterEach`; the instance is shared.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext, BrowserContext, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	initialStateFromPage,
	listItems,
	readSettings,
	restoreSettings,
	writeSettings,
} from './support/adminSettings.ts'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import { openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const SETTINGS_PAGE = `${BASE}/index.php/settings/admin/launchpad`
const WORKSPACE = '/index.php/apps/launchpad/'
const DISABLED_TEXT = 'Personal dashboards are not enabled by your administrator'

let admin: APIRequestContext
let baseline: Record<string, unknown> | null = null
let user: Principal | null = null
let userSession: { context: BrowserContext; page: Page } | null = null
let userDashboard: { id: number; uuid: string; name: string } | null = null

/**
 * Open the LaunchPad admin page on its General tab.
 *
 * @param page an admin page.
 * @return nothing.
 */
async function openAdminPage(page: Page): Promise<void> {
	await page.goto(SETTINGS_PAGE)
	await adminTab(page, 'general').click()
	await expect(page.getByTestId('admin-default-settings')).toBeVisible({
		timeout: 30_000,
	})
}

/**
 * Wait for the next settings PUT the page sends.
 *
 * @param page the admin page.
 * @return the response, once it arrives.
 */
function nextSettingsPut(page: Page) {
	return page.waitForResponse(
		(res) =>
			res.url().includes('/api/admin/settings')
			&& res.request().method() === 'PUT',
	)
}

/**
 * Load the workspace as the user and open the dashboard switcher.
 *
 * @param page the user's page.
 * @return nothing.
 */
async function openWorkspaceSidebar(page: Page): Promise<void> {
	await page.goto(WORKSPACE, { waitUntil: 'domcontentloaded' })
	await openDashboardSwitcher(page)
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async ({ browser }) => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	baseline = await readSettings(admin)

	// The user gets a personal dashboard of their own, active, so the
	// workspace opens on it and the sidebar has a personal section.
	await writeSettings(admin, {
		allowUserDash: true,
		allowMultiDash: true,
		maxDashboardsPerUser: 0,
	})
	user = await createPrincipal(admin, 'e2e-aset-ui')
	await assertActingAs(user.api, user.username)
	const name = `E2E Mine ${Date.now()}`
	const created = await user.api.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBe(201)
	const dash = (await created.json()).dashboard
	userDashboard = { id: Number(dash.id), uuid: String(dash.uuid), name }
	expect(
		(await user.api.post(`${API}/dashboard/${dash.id}/activate`)).status(),
	).toBe(200)
	expect(
		(
			await user.api.post(`${API}/dashboards/active`, {
				data: { uuid: dash.uuid },
			})
		).status(),
	).toBe(200)

	userSession = await loginAs(browser, user.username, user.password)
})

test.afterEach(async () => {
	await restoreSettings(admin, baseline)
})

test.afterAll(async () => {
	await userSession?.context.close()
	await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	if (user !== null && userDashboard !== null) {
		await user.api
			.delete(`${API}/dashboard/${userDashboard.id}`)
			.catch(() => undefined)
	}
	await restoreSettings(admin, baseline)
	await removePrincipal(admin, user)
	await admin.dispose()
})

test.describe('REQ-ASET-008 the admin settings page', () => {
	// @e2e admin-settings::admin-settings-page-is-registered
	test('Settings > Administration lists LaunchPad and the entry opens its page', async ({
		page,
	}) => {
		await page.goto(`${BASE}/index.php/settings/admin`)
		// The navigation entry; a LaunchPad notification links the same page.
		const entry = page.locator('a[href*="/settings/admin/launchpad"]:visible').first()
		await expect(entry).toBeVisible({ timeout: 30_000 })
		await expect(entry).toContainText('LaunchPad')

		// The section links on to the administration page (REQ-ADM-031).
		await entry.click()
		await expect(page).toHaveURL(/\/settings\/admin\/launchpad/)
		await page.getByTestId('launchpad-admin-settings-link').getByRole('link').click()
		// /admin opens General; the router leaves the default tab out of the link.
		await expect(page).toHaveURL(/\/apps\/launchpad\/admin(\/general)?(\?|#|$)/)
		await adminTab(page, 'general').click()
		await expect(page.getByTestId('admin-default-settings')).toBeVisible({
			timeout: 30_000,
		})
	})

	// @e2e admin-settings::regular-user-cannot-access-admin-settings-page
	test('an ordinary user has no LaunchPad entry and is answered 403 on the page', async ({
		page,
	}) => {
		const userPage = userSession!.page
		await userPage.goto(`${BASE}/index.php/settings/user`)
		// Control: the settings navigation rendered for this user.
		await expect(
			userPage.locator('a[href*="/settings/user/security"]').first(),
		).toBeVisible({ timeout: 30_000 })
		await expect(
			userPage.locator('a[href*="/settings/admin/launchpad"]'),
		).toHaveCount(0)

		const refused = await userPage.goto(SETTINGS_PAGE)
		expect(refused?.status()).toBe(403)

		// The admin is served the same address.
		const allowed = await page.goto(SETTINGS_PAGE)
		expect(allowed?.status()).toBe(200)
	})

	// @e2e admin-settings::settings-form-layout
	test('the form shows the four settings with their current values and no Save button', async ({
		page,
	}) => {
		await writeSettings(admin, {
			allowUserDash: true,
			allowMultiDash: false,
			defaultPermLevel: 'full',
			defaultGridCols: 8,
		})
		await openAdminPage(page)
		const form = page.getByTestId('admin-default-settings')

		await expect(
			form.getByLabel('Allow users to create custom dashboards', {
				exact: true,
			}),
		).toBeChecked()
		await expect(
			form.getByLabel('Allow users to have multiple dashboards', {
				exact: true,
			}),
		).not.toBeChecked()

		const permission = form
			.locator('.launchpad-admin__field')
			.filter({ hasText: 'Default permission level' })
		await expect(permission).toContainText('Full customization')

		const grid = form
			.locator('.launchpad-admin__field')
			.filter({ hasText: 'Default grid columns' })
		await expect(grid).toContainText('8')

		// The grid columns are a select of 6, 8 and 12 (REQ-ASET-006).
		await grid.locator('.vs__dropdown-toggle').click()
		const options = page.locator('.vs__dropdown-menu [role="option"]')
		await expect(options).toHaveText(['6', '8', '12'])
		await page.keyboard.press('Escape')

		// AND: no Save button, since every change is saved at once.
		await expect(form.getByRole('button', { name: /^Save$/ })).toHaveCount(0)
	})

	// @e2e admin-settings::each-change-is-saved-at-once
	test('switching "Allow user dashboards" off is saved at once and is stored', async ({
		page,
	}) => {
		await writeSettings(admin, { allowUserDash: true })
		await openAdminPage(page)
		const form = page.getByTestId('admin-default-settings')
		await expect(
			form.getByLabel('Allow users to create custom dashboards', {
				exact: true,
			}),
		).toBeChecked()

		const put = nextSettingsPut(page)
		await form
			.getByText('Allow users to create custom dashboards', { exact: true })
			.click()
		// THEN: the switch alone sends the change, with no Save step.
		const res = await put
		expect(res.status()).toBe(200)
		expect(res.request().postDataJSON()).toMatchObject({ allowUserDash: false })

		expect((await readSettings(admin)).allowUserDashboards).toBe(false)
	})
})

test.describe('REQ-ASET-020 program schemes and launch templates', () => {
	// @e2e admin-settings::a-script-scheme-cannot-be-allowed
	test('saving "javascript" as a scheme is refused with 400 naming it, and the list is unchanged', async ({
		page,
	}) => {
		const before = (await readSettings(admin)).tileAllowedSchemes
		await openAdminPage(page)
		const section = page.getByTestId('admin-tile-launch')
		await expect(section).toBeVisible({ timeout: 30_000 })

		await section.getByLabel('Scheme', { exact: true }).fill('javascript')
		const refused = nextSettingsPut(page)
		await section.getByRole('button', { name: 'Allow scheme' }).click()
		const res = await refused
		expect(res.status()).toBe(400)
		expect(await res.text()).toContain('javascript')
		await expect(section.getByTestId('admin-tile-launch-error')).toContainText(
			'javascript',
		)
		expect((await readSettings(admin)).tileAllowedSchemes).toEqual(before)

		// A scheme that is allowed goes through the same form.
		await section.getByLabel('Scheme', { exact: true }).fill('vscode')
		const accepted = nextSettingsPut(page)
		await section.getByRole('button', { name: 'Allow scheme' }).click()
		expect((await accepted).status()).toBe(200)
		expect((await readSettings(admin)).tileAllowedSchemes).toContain('vscode')
	})

	// @e2e admin-settings::a-launch-template-must-be-https-with-the-app-id
	test('a launch template on http is refused with 400 and nothing is stored', async ({
		page,
	}) => {
		const before = (await readSettings(admin)).ssoLaunchTemplates
		const key = `e2e-idp-${Date.now()}`
		await openAdminPage(page)
		const section = page.getByTestId('admin-tile-launch')
		await expect(section).toBeVisible({ timeout: 30_000 })

		// The template form's three fields, in order: name, key, address.
		const fields = section
			.locator('form.tile-launch-settings__add')
			.nth(1)
			.locator('input')
		await expect(fields).toHaveCount(3)
		await fields.nth(0).fill('E2E IdP')
		await fields.nth(1).fill(key)
		await fields.nth(2).fill('http://idp.example.nl/{appId}')
		const refused = nextSettingsPut(page)
		await section.getByRole('button', { name: 'Add template' }).click()
		expect((await refused).status()).toBe(400)
		await expect(section.getByTestId('admin-tile-launch-error')).toBeVisible()

		const stored = (await readSettings(admin)).ssoLaunchTemplates as Array<{
			key: string
		}>
		expect(stored).toEqual(before)
		expect(stored.map((t) => t.key)).not.toContain(key)

		// The same template on https is stored, so the form itself works.
		await fields.nth(2).fill('https://idp.example.nl/{appId}')
		const accepted = nextSettingsPut(page)
		await section.getByRole('button', { name: 'Add template' }).click()
		expect((await accepted).status()).toBe(200)
		expect(
			(
				(await readSettings(admin)).ssoLaunchTemplates as Array<{
					key: string
				}>
			).map((t) => t.key),
		).toContain(key)
	})
})

test.describe('REQ-ASET-015 and REQ-ASET-003 the switch as the pages carry it', () => {
	// @e2e admin-settings::initial-state-matches-setting-on-the-workspace-page
	test('with the switch on the workspace page carries allowUserDashboards true and offers Add dashboard', async () => {
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		const page = userSession!.page
		await openWorkspaceSidebar(page)

		expect(await initialStateFromPage(page, 'allowUserDashboards')).toBe(true)
		// The value reaches the sidebar through `provide`, which is what draws the button.
		await expect(page.getByTestId('add-dashboard-button')).toBeVisible()
	})

	// @e2e admin-settings::frontend-hides-create-affordance-when-disabled
	// @e2e admin-settings::frontend-honours-the-flag
	test('with the switch off the workspace offers no create or fork control, and the API still refuses', async () => {
		await writeSettings(admin, { allowUserDash: false, allowMultiDash: true })
		const page = userSession!.page
		await openWorkspaceSidebar(page)

		expect(await initialStateFromPage(page, 'allowUserDashboards')).toBe(false)

		// Control: the sidebar is open and lists the user's own dashboard.
		await expect(
			page
				.locator('.dashboard-switcher-sidebar.open')
				.getByText(userDashboard!.name)
				.first(),
		).toBeVisible()
		// "Add dashboard" is both the create entry and the only fork affordance.
		await expect(page.getByTestId('add-dashboard-button')).toHaveCount(0)
		await expect(
			page.getByRole('button', { name: /^Create (your first )?dashboard$/ }),
		).toHaveCount(0)

		// The empty state, where the "not enabled by your administrator" text
		// would be read, cannot be reached since SeedDefaultDashboard (#361);
		// the toast test below asserts that text where a user does meet it.

		// Defence in depth: the calls the hidden controls would make are refused.
		const create = await user!.api.post(`${API}/dashboard`, {
			data: { name: 'Stale create' },
		})
		expect(create.status()).toBe(403)
		expect(await create.json()).toEqual({
			status: 'error',
			error: 'personal_dashboards_disabled',
			message: DISABLED_TEXT,
		})
		const fork = await user!.api.post(
			`${API}/dashboards/${userDashboard!.uuid}/fork`,
			{ data: {} },
		)
		expect(fork.status()).toBe(403)
		expect((await fork.json()).error).toBe('personal_dashboards_disabled')
	})

	// @e2e admin-settings::initial-state-matches-setting-on-the-admin-page
	test('the admin page carries allowUserDashboards as the setting has it', async ({
		page,
	}) => {
		await writeSettings(admin, { allowUserDash: false })
		await page.goto(SETTINGS_PAGE)
		expect(await initialStateFromPage(page, 'allowUserDashboards')).toBe(false)

		// And it follows the setting, so the false above is not a constant.
		await writeSettings(admin, { allowUserDash: true })
		await page.goto(SETTINGS_PAGE)
		expect(await initialStateFromPage(page, 'allowUserDashboards')).toBe(true)
	})

	// @e2e admin-settings::toast-surfaces-the-stable-error-code-on-direct-api-invocation
	test('a create the server refuses shows the localised notice and the error reaches the caller', async () => {
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		const page = userSession!.page
		await openWorkspaceSidebar(page)
		const idsBefore = listItems(
			await (await user!.api.get(`${API}/dashboards`)).json(),
		).map((d) => d.id)

		// The page was served with the switch on; it goes off underneath it,
		// so the store's create call meets the 403 a stale page would.
		await writeSettings(admin, { allowUserDash: false })

		// Called on the live store: no control in the shell calls
		// `createDashboard` once a dashboard exists (see the header).
		const outcome = await page.evaluate(async () => {
			const root = document.querySelector('#workspace-vue') as unknown as {
				__vue_app__?: {
					config: {
						globalProperties: { $pinia?: { _s: Map<string, unknown> } }
					}
				}
			} | null
			const store = root?.__vue_app__?.config.globalProperties.$pinia?._s.get(
				'dashboard',
			) as { createDashboard: (payload: object) => Promise<void> } | undefined
			if (store === undefined) {
				return { reached: false, threw: false, status: 0, code: '' }
			}
			try {
				await store.createDashboard({ name: 'E2E stale create' })
				return { reached: true, threw: false, status: 0, code: '' }
			} catch (error) {
				const response = (
					error as {
						response?: { status?: number; data?: { error?: string } }
					}
				).response
				return {
					reached: true,
					threw: true,
					status: response?.status ?? 0,
					code: response?.data?.error ?? '',
				}
			}
		})

		expect(
			outcome.reached,
			'the dashboard store was not found on the page',
		).toBe(true)
		expect(outcome.threw, 'createDashboard swallowed the refusal').toBe(true)
		expect(outcome.status).toBe(403)
		expect(outcome.code).toBe('personal_dashboards_disabled')
		// @nextcloud/dialogs 8 renders an error toast as role="alert", with
		// hashed CSS-module classes and no `.toastify` any more.
		await expect(
			page.getByRole('alert').filter({ hasText: DISABLED_TEXT }).first(),
		).toBeVisible({ timeout: 10_000 })

		const idsAfter = listItems(
			await (await user!.api.get(`${API}/dashboards`)).json(),
		).map((d) => d.id)
		expect(idsAfter, 'a refused create still added a dashboard').toEqual(
			idsBefore,
		)
	})
})
