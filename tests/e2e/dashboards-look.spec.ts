/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A dashboard's own look and administrator CSS (openspec/specs/dashboards/
 * spec.md, REQ-THM-001..003): the Look tab sets an accent and a background
 * image on one dashboard only, an unreadable accent is refused with its
 * measured contrast, a dark background gets light text, and custom CSS is
 * cleaned, scoped to LaunchPad and kept from non-administrators.
 *
 * The administrator plays Ella (the owner, full permission) and Noor (the
 * administrator) in the browser through the dashboard settings dialog and the
 * admin page; Pieter and a non-admin Ella are throwaway accounts. Every admin
 * CSS value this file writes is read first and put back afterwards.
 *
 * tests/e2e/dashboard-look-and-css.spec.ts covers the same flows for the
 * archived change; this file is the verdict for the living spec and drives
 * the dialog rather than the API for the look.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::a-green-team-dashboard
 *   @e2e dashboards::pale-accent-on-white-refused
 *   @e2e dashboards::dark-background-gets-light-text
 *   @e2e dashboards::import-dropped
 *   @e2e dashboards::non-administrator-refused
 *   @e2e dashboards::css-cannot-reach-outside-launchpad
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import * as path from 'path'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import {
	allowManyPersonalDashboards,
	createDashboard,
	publication,
	readDashboard,
	removeTree,
} from './support/dashboardHierarchy.ts'
import { MORE_MENU, openDashboardSettings } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	createPrincipal,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const T = Date.now()
const GREEN = '#1f5c3a'
const PALE = '#f5f5dc'
const PALE_MESSAGE =
	'Buttons in this colour are hard to see (contrast 1.1 to 1, needs 3 to 1)'
const TINY_PNG = path.resolve(__dirname, 'fixtures/tiny.png')

let admin: APIRequestContext
let restoreSettings: (() => Promise<void>) | null = null
let green: SeededDashboard | null = null
let werkplek: SeededDashboard | null = null
const principals: Principal[] = []

/**
 * Make one of the administrator's dashboards the active one.
 *
 * @param dash the dashboard.
 * @return nothing.
 */
async function activate(dash: SeededDashboard): Promise<void> {
	expect(
		(await admin.post(`${API}/dashboard/${dash.id}/activate`)).status(),
	).toBeLessThan(300)
	expect(
		(
			await admin.post(`${API}/dashboards/default`, {
				data: { uuid: dash.uuid },
			})
		).status(),
	).toBeLessThan(300)
}

/**
 * Open LaunchPad on a dashboard and read what its region and the Nextcloud
 * header draw.
 *
 * @param page the page.
 * @param uuid the dashboard expected on screen.
 * @return the region's accent and background image, and the header's look.
 */
async function drawn(
	page: Page,
	uuid: string,
): Promise<{ accent: string; image: string; header: string }> {
	await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
	const region = page.getByTestId('launchpad-dashboard-region')
	await expect(region).toHaveAttribute('data-dashboard-uuid', uuid, {
		timeout: 30_000,
	})
	const look = await region.evaluate((el) => ({
		accent: (el as HTMLElement).style
			.getPropertyValue('--color-primary-element')
			.trim(),
		image: getComputedStyle(el).backgroundImage,
	}))
	const header = await page
		.locator('#header, header.header')
		.first()
		.evaluate(
			(el) =>
				`${getComputedStyle(el).backgroundColor} ${getComputedStyle(el).backgroundImage}`,
		)
	return { ...look, header }
}

/**
 * Open the active dashboard's settings on the Look tab.
 *
 * @param page the start page.
 * @return nothing.
 */
async function openLookTab(page: Page): Promise<void> {
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await openDashboardSettings(page)
	// The look is the Own look block of the Details tab (REQ-THM-001).
	await page.locator('[data-test="config-tab-general"]').click()
	await expect(page.locator('[data-test="config-look"]')).toBeVisible()
}

/**
 * The accent text input of the Look tab.
 *
 * @param page the page.
 * @return the input.
 */
function accentInput(page: Page) {
	return page
		.locator(
			'input[data-testid="look-accent"], [data-testid="look-accent"] input',
		)
		.first()
}

test.describe('dashboards — a look of its own and custom CSS (REQ-THM-001..003)', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	test.beforeAll(async () => {
		admin = await adminContext()
		restoreSettings = await allowManyPersonalDashboards(admin)
		werkplek = await seedActiveDashboard(admin, `E2E Mijn werkplek ${T}`)
		green = await seedActiveDashboard(admin, `E2E Team Groen ${T}`)
	})

	test.afterAll(async () => {
		await removeSeededDashboard(admin, green)
		await removeSeededDashboard(admin, werkplek)
		for (const principal of principals) {
			await removePrincipal(admin, principal)
		}
		await restoreSettings?.()
		await admin?.dispose()
	})

	// @e2e dashboards::a-green-team-dashboard
	test('the Look tab gives "Team Groen" its accent and image; the other dashboard and the header stay as they were', async ({
		page,
	}) => {
		await activate(werkplek!)
		const before = await drawn(page, werkplek!.uuid)
		expect(before.accent).toBe('')
		expect(before.image).toBe('none')

		await activate(green!)
		await drawn(page, green!.uuid)
		await openLookTab(page)
		await accentInput(page).fill(GREEN)
		const uploaded = page.waitForResponse((res) =>
			res.url().includes('/api/resources/upload'),
		)
		await page.getByTestId('look-image-input').setInputFiles(TINY_PNG)
		expect((await uploaded).status()).toBeLessThan(300)
		await expect(page.getByTestId('look-image-name')).toBeVisible()

		const saved = page.waitForResponse(
			(res) =>
				res.request().method() === 'PUT'
				&& res.url().endsWith(`/api/dashboard/${green!.id}`),
		)
		await page.getByTestId('dashboard-save-button').click()
		expect((await saved).status()).toBe(200)

		const stored = (await readDashboard(admin, green!.id)).dashboard!
		expect(String(stored.themeAccent).toLowerCase()).toBe(GREEN)
		expect(String(stored.themeBackgroundResource)).toMatch(/\.png$/)

		const themed = await drawn(page, green!.uuid)
		expect(themed.accent.toLowerCase()).toBe(GREEN)
		expect(themed.image).toContain(
			`/apps/launchpad/resource/${encodeURIComponent(String(stored.themeBackgroundResource))}`,
		)
		expect(themed.header).toBe(before.header)

		await activate(werkplek!)
		const after = await drawn(page, werkplek!.uuid)
		expect(after.accent).toBe('')
		expect(after.image).toBe('none')
		expect(after.header).toBe(before.header)
	})

	// @e2e dashboards::pale-accent-on-white-refused
	test('a pale accent on the white instance background is refused with its measured contrast', async ({
		page,
	}) => {
		const plain = await seedActiveDashboard(admin, `E2E Team Bleek ${T}`)
		try {
			expect(
				(await readDashboard(admin, plain.id)).dashboard
					?.themeBackgroundColor,
			).toBeFalsy()

			// In the editor: the same check while choosing, and no save.
			await drawn(page, plain.uuid)
			await openLookTab(page)
			await accentInput(page).fill(PALE)
			await expect(page.getByTestId('look-contrast-error')).toHaveText(
				PALE_MESSAGE,
			)
			await expect(page.getByTestId('dashboard-save-button')).toBeDisabled()

			// On the server: the save fails with the measured ratio.
			const res = await admin.put(`${API}/dashboard/${plain.id}`, {
				data: { themeAccent: PALE },
			})
			expect(res.status()).toBe(400)
			const body = await res.json()
			expect(body.error).toBe('contrast_too_low')
			expect(body.ratio).toBe(1.1)
			expect(body.message).toBe(PALE_MESSAGE)
			expect(
				(await readDashboard(admin, plain.id)).dashboard?.themeAccent,
			).toBeFalsy()
		} finally {
			await removeSeededDashboard(admin, plain)
		}
	})

	// @e2e dashboards::dark-background-gets-light-text
	test("on a dark background colour, the dashboard's text is white for a viewer", async ({
		browser,
	}) => {
		const pieter = await createPrincipal(admin, 'e2e-look-pieter')
		principals.push(pieter)
		const dark = await createDashboard(admin, { name: `E2E Team Donker ${T}` })
		try {
			const set = await admin.put(`${API}/dashboard/${dark.id}`, {
				data: { themeBackgroundColor: '#12301f' },
			})
			expect(set.status(), await set.text()).toBe(200)
			expect((await set.json()).dashboard.themeTextColor).toBe('#ffffff')
			await publication(admin, dark.uuid, 'publish')
			await shareWith(admin, dark.id, pieter.username, 'view_only')

			expect(
				(
					await pieter.api.post(`${API}/dashboards/default`, {
						data: { uuid: dark.uuid },
					})
				).status(),
			).toBeLessThan(300)
			const { context, page } = await loginAs(
				browser,
				pieter.username,
				pieter.password,
			)
			try {
				await page.goto('/index.php/apps/launchpad')
				const region = page.getByTestId('launchpad-dashboard-region')
				await expect(region).toHaveAttribute(
					'data-dashboard-uuid',
					dark.uuid,
					{ timeout: 30_000 },
				)
				await expect(region).toHaveCSS('background-color', 'rgb(18, 48, 31)')
				await expect(region).toHaveCSS('color', 'rgb(255, 255, 255)')
				expect(
					await region.evaluate((el) =>
						(el as HTMLElement).style
							.getPropertyValue('--color-main-text')
							.trim(),
					),
				).toBe('#ffffff')
			} finally {
				await context.close()
			}
		} finally {
			await removeTree(admin, dark.id)
		}
	})

	// @e2e dashboards::import-dropped
	test('global CSS with an @import keeps only the scoped rule, and the admin section says "Removed: @import"', async ({
		page,
	}) => {
		const previous = await admin.get(`${API}/admin/custom-css`)
		expect(previous.status()).toBe(200)
		const original = String((await previous.json()).customCss ?? '')

		try {
			await page.goto('/index.php/settings/admin/launchpad')
			const input = page.getByTestId('admin-custom-css-input')
			await expect(input).toBeEnabled({ timeout: 30_000 })
			await input.fill(
				'@import url(https://evil.example/x.css); .tile-widget__title { font-weight: 700; }',
			)
			const saved = page.waitForResponse(
				(res) =>
					res.request().method() === 'PUT'
					&& res.url().endsWith('/api/admin/custom-css'),
			)
			await page.getByTestId('admin-custom-css-save').click()
			expect((await saved).status()).toBe(200)

			await expect(page.getByTestId('admin-custom-css-removed')).toHaveText(
				'Removed: @import',
			)

			const stored = String(
				(await (await admin.get(`${API}/admin/custom-css`)).json()).customCss
					?? '',
			)
			expect(stored).not.toContain('@import')
			expect(stored).not.toContain('evil.example')
			expect(stored).toContain('.launchpad-workspace .tile-widget__title')
			expect(stored).toMatch(/font-weight:\s*700/)
			expect(stored.split('{')).toHaveLength(2)
			await expect(input).toHaveValue(stored)
		} finally {
			await admin.put(`${API}/admin/custom-css`, {
				data: { customCss: original },
			})
		}
	})

	// @e2e dashboards::non-administrator-refused
	test('a non-administrator with full permission sending customCss is refused 403 and nothing is stored', async () => {
		const ella = await createPrincipal(admin, 'e2e-look-ella')
		principals.push(ella)
		const team = await createDashboard(ella.api, { name: `E2E Ella Groen ${T}` })
		try {
			const refused = await ella.api.put(`${API}/dashboard/${team.id}`, {
				data: { customCss: '.tile-widget__title { color: red; }' },
			})
			expect(refused.status()).toBe(403)
			expect(
				(await readDashboard(ella.api, team.id)).dashboard?.customCss ?? '',
			).toBe('')

			// The same owner may change anything else on it.
			const renamed = await ella.api.put(`${API}/dashboard/${team.id}`, {
				data: { name: `E2E Ella Groen 2 ${T}` },
			})
			expect(renamed.status()).toBe(200)

			// Control: an administrator may set CSS on her dashboard.
			const allowed = await admin.put(
				`${API}/admin/custom-css/dashboards/${team.uuid}`,
				{
					data: { customCss: '.tile-widget__title { color: red; }' },
				},
			)
			expect(allowed.status(), await allowed.text()).toBe(200)
			expect(
				String(
					(await readDashboard(ella.api, team.id)).dashboard?.customCss,
				),
			).toContain('.tile-widget__title')
		} finally {
			await removeTree(ella.api, team.id)
		}
	})

	// @e2e dashboards::css-cannot-reach-outside-launchpad
	test('dashboard CSS "body { display: none; }" is scoped to that dashboard, and Files looks as always', async ({
		browser,
	}) => {
		const pieter = await createPrincipal(admin, 'e2e-look-files')
		principals.push(pieter)

		const saved = await admin.put(
			`${API}/admin/custom-css/dashboards/${green!.uuid}`,
			{
				data: { customCss: 'body { display: none; }' },
			},
		)
		expect(saved.status(), await saved.text()).toBe(200)
		try {
			const scope = `.launchpad-dashboard[data-dashboard-uuid="${green!.uuid}"]`
			const stored = String((await saved.json()).customCss)
			expect(stored.startsWith(`${scope} body`)).toBe(true)
			expect(stored).not.toMatch(/(^|\}\s*)body\s*\{/)
			expect(
				String((await readDashboard(admin, green!.id)).dashboard?.customCss),
			).toBe(stored)

			const { context, page } = await loginAs(
				browser,
				pieter.username,
				pieter.password,
			)
			try {
				await page.goto('/index.php/apps/files')
				await expect(
					page.locator('#content, #app-content-vue, #app-content').first(),
				).toBeVisible({ timeout: 30_000 })
				await expect(page.locator('body')).not.toHaveCSS('display', 'none')
				const bodyCss = await page.evaluate(() =>
					[...document.styleSheets]
						.flatMap((sheet) => {
							try {
								return [...sheet.cssRules].map(
									(rule) => rule.cssText,
								)
							} catch {
								return []
							}
						})
						.filter(
							(text) =>
								/display:\s*none/.test(text)
								&& text.includes('body')
								&& !text.includes('launchpad-dashboard'),
						),
				)
				expect(bodyCss.filter((text) => /^body\s*\{/.test(text))).toEqual([])
			} finally {
				await context.close()
			}
		} finally {
			await admin.put(`${API}/admin/custom-css/dashboards/${green!.uuid}`, {
				data: { customCss: '' },
			})
		}
	})
})
