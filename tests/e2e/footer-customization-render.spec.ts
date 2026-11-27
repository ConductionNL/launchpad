/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for how the footer RENDERS under a dashboard
 * (openspec/specs/footer-customization): global on/off, HTML and structured
 * content, the per-dashboard override, language variants, print and colours.
 * The settings API contract lives in footer-customization-api.spec.ts.
 *
 * LaunchPad has no footer settings screen (nothing in src/ calls
 * /api/admin/footer-settings), so the administrator's footer is set through
 * that API, which is how the scenarios phrase it; what is asserted is what a
 * person sees in `<footer class="launchpad-footer">` (src/components/DashboardFooter.vue).
 *
 * 🔴 SUSPECTED BUG — THE FOOTER NEVER REACHES THE PAGE. DashboardFooter is
 * mounted by src/views/WorkspaceApp.vue:114 with `effectiveFooter`, which
 * reads either the injected initial-state key `effectiveFooter`
 * (WorkspaceApp.vue:219-221) or `effectiveFooter` on the active dashboard's
 * payload (WorkspaceApp.vue:310). Neither is ever produced:
 *   - src/utils/loadInitialState.js PAGE_KEYS.workspace declares no
 *     `effectiveFooter` (nor `viewerLocale`, which the locale prop reads), and
 *     lib/Service/InitialStateBuilder.php pushes neither;
 *   - Dashboard::jsonSerialize() (lib/Db/Dashboard.php:793-797) emits
 *     dashboardFooterMode/dashboardFooterHtml but no effectiveFooter;
 *   - FooterService::resolveFooterForDashboard() is reached only through
 *     DashboardService::resolveFooterForDashboard()
 *     (lib/Service/DashboardService.php:4053), which nothing calls.
 * The two "must NOT render" scenarios run here. Every scenario that needs a
 * footer ON the page runs in footer-customization-render-defects.spec.ts,
 * where it is expected red; it sits in its own non-serial file so a red
 * there cannot skip these. The per-dashboard override is also unreachable
 * over HTTP; see footer-customization-override-defects.spec.ts.
 *
 * The footer is instance-wide. `beforeAll` snapshots all five footer keys and
 * the personal-dashboard flags; `afterAll` writes them back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e footer-customization::footer-disabled-by-default
 *   @e2e footer-customization::footer-is-not-rendered-when-disabled
 *
 * @spec openspec/specs/footer-customization/spec.md
 */

import type { APIRequestContext, Browser, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { FooterSettings } from './support/footerSettings.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import {
	readFooter,
	resetFooter,
	restoreFooter,
} from './support/footerSettings.ts'
import {
	adminContext,
	API,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const SETTINGS = `${API}/admin/settings`
const FOOTER = 'footer.launchpad-footer'
const GRID = '.workspace-shell__grid'

let admin: APIRequestContext
let footerSnapshot: FooterSettings | undefined
let flagSnapshot: { allowUserDash: boolean; allowMultiDash: boolean } | undefined
let adminDash: SeededDashboard | null = null
const principals: Principal[] = []

/**
 * Open LaunchPad on the active dashboard and wait for its grid.
 *
 * @param page a page logged in as the viewer.
 * @return the footer locator.
 */
async function openDashboard(page: Page): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
	await expect(page.locator(GRID)).toBeVisible({ timeout: 45_000 })
	return page.locator(FOOTER)
}

/**
 * A throwaway non-admin viewer with Nextcloud language `lang` and a personal
 * dashboard of their own, made active.
 *
 * @param lang the viewer's NC language, or null to leave the default.
 * @return the principal and its dashboard's id and uuid.
 */
async function viewer(
	lang: string | null,
): Promise<{ who: Principal; id: number; uuid: string }> {
	const who = await createPrincipal(admin, 'e2e-footer-view')
	principals.push(who)
	if (lang !== null) {
		const set = await admin.put(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(who.username)}`,
			{
				form: { key: 'language', value: lang },
			},
		)
		expect(set.status(), await set.text()).toBe(200)
	}
	const created = await who.api.post(`${API}/dashboard`, {
		data: { name: `E2E Footer Viewer ${RUN}` },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	const active = await who.api.post(`${API}/dashboards/default`, {
		data: { uuid: dash.uuid },
	})
	expect(active.status(), await active.text()).toBeLessThan(300)
	return { who, id: Number(dash.id), uuid: String(dash.uuid) }
}

/**
 * Log in as a principal and open its active dashboard.
 *
 * @param browser the browser.
 * @param who the principal.
 * @return the session and the footer locator.
 */
async function openAs(browser: Browser, who: Principal) {
	const session = await loginAs(browser, who.username, who.password)
	const footer = await openDashboard(session.page)
	return { ...session, footer }
}

test.beforeAll(async () => {
	admin = await adminContext()
	footerSnapshot = await readFooter(admin)

	const settings = await admin.get(SETTINGS)
	expect(settings.status(), await settings.text()).toBe(200)
	const body = await settings.json()
	flagSnapshot = {
		allowUserDash: Boolean(body.allowUserDashboards),
		allowMultiDash: body.allowMultipleDashboards !== false,
	}
	const enable = await admin.put(SETTINGS, {
		data: { allowUserDash: true, allowMultiDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)

	adminDash = await seedActiveDashboard(admin, `E2E Footer ${RUN}`)
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	await removeSeededDashboard(admin, adminDash)
	await restoreFooter(admin, footerSnapshot)
	if (flagSnapshot !== undefined) {
		await admin.put(SETTINGS, { data: flagSnapshot })
	}
	for (const who of principals) {
		await removePrincipal(admin, who)
	}
	await admin.dispose()
})

test.describe.serial('global footer on and off (REQ-FTR-001, REQ-FTR-004)', () => {
	// @e2e footer-customization::footer-disabled-by-default
	test('an untouched instance answers footerEnabled=false and renders no footer', async ({
		page,
	}) => {
		// The snapshot is the instance as this spec found it. Every footer spec
		// restores it, so on the CI fixture it is the installation default.
		expect(footerSnapshot?.footerEnabled).toBe(false)
		expect((await readFooter(admin)).footerEnabled).toBe(false)

		const footer = await openDashboard(page)
		await expect(footer).toHaveCount(0)
	})

	// @e2e footer-customization::footer-is-not-rendered-when-disabled
	test('with footer_enabled=false no footer element is in the DOM, whatever content is stored', async ({
		page,
		browser,
	}) => {
		const marker = `E2E disabled footer ${RUN}`
		await resetFooter(admin, {
			footerEnabled: false,
			footerHtml: `<p>${marker}</p>`,
			footerBackgroundColor: '#1a1a1a',
		})

		const footer = await openDashboard(page)
		await expect(footer).toHaveCount(0)
		await expect(
			page.locator('[role="contentinfo"].launchpad-footer'),
		).toHaveCount(0)
		await expect(page.getByText(marker)).toHaveCount(0)

		// Any user: a non-admin viewer on a dashboard of their own.
		const { who } = await viewer(null)
		const session = await openAs(browser, who)
		try {
			await expect(session.footer).toHaveCount(0)
			await expect(session.page.getByText(marker)).toHaveCount(0)
		} finally {
			await session.context.close()
		}
	})
})
