/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * platform-dashboard-theme-and-css, task 8: one dashboard's look changes
 * nothing else, and `@import` is dropped from custom CSS.
 *
 * Two dashboards of its own ("E2E Team Groen" with an accent, "E2E Mijn
 * werkplek" without), made and removed by this file through the dashboard
 * fixture. The look is set over the API exactly as the Look tab saves it, and
 * the browser reads what is drawn: the accent variable on the dashboard region
 * of the green dashboard only, and the Nextcloud header unchanged on both.
 *
 * The CSS test saves the instance's global custom CSS, so it reads the value
 * first and puts it back afterwards.
 *
 * Not run in the change that wrote it (decision 139): the live pass runs it.
 *
 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md
 */
// @e2e openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#scenario-a-green-team-dashboard
// @e2e openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#scenario-import-dropped

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const DASHBOARD = '/index.php/apps/launchpad/api/dashboard'
const CUSTOM_CSS = '/index.php/apps/launchpad/api/admin/custom-css'
const GREEN = '#1f5c3a'

let api: APIRequestContext
let green: SeededDashboard | null = null
let plain: SeededDashboard | null = null

/**
 * Make a dashboard the caller's active one, as the dashboard switcher does.
 *
 * @param dashboard The dashboard.
 */
async function activate(dashboard: SeededDashboard): Promise<void> {
	const activated = await api.post(`${DASHBOARD}/${dashboard.id}/activate`)
	expect(activated.status(), await activated.text()).toBeLessThan(300)
	const preference = await api.post('/index.php/apps/launchpad/api/dashboards/default', {
		data: { uuid: dashboard.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)
}

/**
 * Open LaunchPad on the active dashboard and answer what its region and the
 * Nextcloud header draw.
 *
 * @param page The page.
 * @param uuid The dashboard expected on screen.
 * @return The region's accent variable and the header's background.
 */
async function drawn(page: Page, uuid: string): Promise<{ accent: string; header: string }> {
	await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
	const region = page.getByTestId('launchpad-dashboard-region')
	await expect(region).toHaveAttribute('data-dashboard-uuid', uuid, { timeout: 30_000 })

	const accent = await region.evaluate((el) => el.style.getPropertyValue('--color-primary-element').trim())
	const header = await page
		.locator('#header, header.header')
		.first()
		.evaluate((el) => getComputedStyle(el).backgroundColor + ' ' + getComputedStyle(el).backgroundImage)

	return { accent, header }
}

test.describe('a dashboard look and custom CSS stay where they belong', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	test.beforeAll(async () => {
		api = await request.newContext({
			baseURL: BASE_URL,
			httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
			extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
		})
		plain = await seedActiveDashboard(api, `E2E Mijn werkplek ${Date.now()}`)
		green = await seedActiveDashboard(api, `E2E Team Groen ${Date.now()}`)
	})

	test.afterAll(async () => {
		await removeSeededDashboard(api, green)
		await removeSeededDashboard(api, plain)
		await api?.dispose()
	})

	test('one dashboard\'s accent is drawn on that dashboard only', async ({ page }) => {
		if (green === null || plain === null) {
			throw new Error('the dashboards were not seeded')
		}

		// Before: neither dashboard has a look of its own.
		await activate(plain)
		const before = await drawn(page, plain.uuid)
		expect(before.accent).toBe('')

		// The Look tab's save: the accent on "Team Groen".
		const saved = await api.put(`${DASHBOARD}/${green.id}`, { data: { themeAccent: GREEN } })
		expect(saved.status(), await saved.text()).toBeLessThan(300)
		const body = await saved.json()
		expect((body.dashboard ?? body.data?.dashboard ?? body).themeAccent).toBe(GREEN)

		await activate(green)
		const themed = await drawn(page, green.uuid)
		expect(themed.accent.toLowerCase()).toBe(GREEN)
		expect(themed.header).toBe(before.header)

		// The other dashboard and the header look as before.
		await activate(plain)
		const after = await drawn(page, plain.uuid)
		expect(after.accent).toBe('')
		expect(after.header).toBe(before.header)
	})

	test('@import is dropped from custom CSS and the rest is kept scoped', async ({ page }) => {
		const previous = await api.get(CUSTOM_CSS)
		expect(previous.ok(), `GET custom css -> ${previous.status()}`).toBeTruthy()
		const original = String((await previous.json()).customCss ?? '')

		try {
			const saved = await api.put(CUSTOM_CSS, {
				data: {
					customCss: '@import url(https://evil.example/x.css); .tile-widget__title { font-weight: 700; }',
				},
			})
			expect(saved.status(), await saved.text()).toBe(200)
			const result = await saved.json()
			expect(result.removed).toContain('@import')
			expect(String(result.customCss)).not.toContain('@import')
			expect(String(result.customCss)).not.toContain('evil.example')
			expect(String(result.customCss)).toContain('.tile-widget__title')
			expect(String(result.customCss)).toMatch(/font-weight:\s*700/)

			// What LaunchPad inserts on the page is the cleaned CSS, never the import.
			await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
			await expect(page.getByTestId('launchpad-dashboard-region')).toBeVisible({ timeout: 30_000 })
			const inserted = await page.evaluate(
				() => document.getElementById('launchpad-custom-css')?.textContent ?? '',
			)
			expect(inserted).not.toContain('@import')
			expect(inserted).toContain('.tile-widget__title')
		} finally {
			await api.put(CUSTOM_CSS, { data: { customCss: original } })
		}
	})
})
