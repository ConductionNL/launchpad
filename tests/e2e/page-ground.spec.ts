/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The board grid sits on the page ground, not on the white content panel
 * (runtime-shell REQ-SHELL-012). The content area paints the page ground
 * token; a widget keeps its own surface.
 *
 * @e2e openspec/specs/runtime-shell/spec.md#req-shell-012
 */
import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null

// A fresh dashboard is seeded with the default widget bundle
// (default-widget-bundle), so the grid has a widget whose surface can be
// read. Without one the "each widget keeps its own surface" clause had
// nothing to check and passed by default.
test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E Page ground ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

// @e2e runtime-shell::the-grid-on-the-page-ground
test('the content area carries the page ground and a widget keeps its surface', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/', { waitUntil: 'domcontentloaded' })
	const root = page.locator('[data-testid="cn-app-root"]')
	await expect(root).toHaveClass(/cn-app-root--page-ground/, { timeout: 60_000 })
	await expect(page.locator('.grid-stack-item-content').first()).toBeVisible({ timeout: 30_000 })

	const colours = await page.evaluate(() => {
		const content = document.querySelector('#app-content-vue')
		const probe = document.createElement('div')
		probe.style.backgroundColor = 'var(--nldesign-component-content-surface-background-color, var(--color-background-hover))'
		content?.appendChild(probe)
		const ground = getComputedStyle(probe).backgroundColor
		probe.style.backgroundColor = 'var(--color-main-background)'
		const main = getComputedStyle(probe).backgroundColor
		probe.remove()
		const widget = document.querySelector('.grid-stack-item-content')
		return {
			content: content ? getComputedStyle(content).backgroundColor : null,
			ground,
			main,
			widget: widget ? getComputedStyle(widget).backgroundColor : null,
		}
	})
	expect(colours.content).toBe(colours.ground)
	// "Not the main background": on a theme where the ground token resolved
	// to the main background the first check would hold on the white panel.
	expect(colours.ground, 'the page ground differs from the main background on this theme').not.toBe(colours.main)
	expect(colours.content).not.toBe(colours.main)
	expect(colours.widget, 'a widget is on the grid').not.toBeNull()
	expect(colours.widget).not.toBe(colours.content)
})
