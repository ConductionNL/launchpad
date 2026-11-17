/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Import a browser bookmarks file as tiles (launcher-bookmark-import).
 *
 * The file is a small Netscape bookmarks export with two folders. Only one is
 * ticked, and the container it becomes must land below what the dashboard
 * already holds.
 *
 * @e2e openspec/changes/launcher-bookmark-import/specs/tiles/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	type SeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const STAMP = `${Date.now()}`
const WORK = `Werk ${STAMP}`
const PRIVATE = `Privé ${STAMP}`

const BOOKMARKS_FILE = `<!DOCTYPE NETSCAPE-Bookmark-file-1>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>
<DL><p>
    <DT><H3>${WORK}</H3>
    <DL><p>
        <DT><A HREF="https://wiki.example.invalid/">Wiki</A>
        <DT><A HREF="https://mail.example.invalid/">Mail</A>
        <DT><A HREF="javascript:alert(1)">Bookmarklet</A>
        <DT><A HREF="https://plan.example.invalid/">Planning</A>
    </DL><p>
    <DT><H3>${PRIVATE}</H3>
    <DL><p>
        <DT><A HREF="https://news.example.invalid/">Nieuws</A>
        <DT><A HREF="https://shop.example.invalid/">Winkel</A>
    </DL><p>
</DL>
`

/*
 * The admin over basic auth, with an explicitly empty jar so the project's
 * stored browser session is not sent along.
 */
async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
	})
}

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E bookmark import ${STAMP}`)
	const tile = await api.post(`/index.php/apps/launchpad/api/dashboard/${dashboard.id}/tile`, {
		data: {
			title: `Bestaand ${STAMP}`,
			linkType: 'url',
			linkValue: 'https://example.invalid/bestaand',
			gridX: 0,
			gridY: 0,
			gridWidth: 3,
			gridHeight: 3,
		},
	})
	expect(tile.status(), await tile.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

// @e2e tiles::import-one-folder
// @e2e tiles::a-script-bookmark-is-skipped
test('one ticked folder becomes a container at the bottom, the other is left out', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/')
	await page.locator('[data-testid="launchpad-more-menu"] button').first().click()
	await page.getByText('All dashboards', { exact: true }).click()

	const row = page
		.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.filter({ hasText: dashboard!.name })
	await row.locator('.dashboard-row-actions button').first().click()
	await page.getByRole('menuitem', { name: /import bookmarks/i }).click()

	const dialog = page.locator('[data-testid="bookmark-import"]')
	await dialog.locator('[data-testid="bookmark-import-file"]').setInputFiles({
		name: 'bookmarks.html',
		mimeType: 'text/html',
		buffer: Buffer.from(BOOKMARKS_FILE, 'utf-8'),
	})
	await dialog.getByLabel(new RegExp(PRIVATE)).uncheck()
	await expect(dialog.getByLabel(new RegExp(WORK))).toBeChecked()
	await dialog.locator('[data-testid="bookmark-import-confirm"]').click()

	const container = page.locator('.container-widget').filter({ hasText: WORK })
	await expect(container).toBeVisible({ timeout: 15_000 })
	await expect(container.locator('.container-widget__child')).toHaveCount(3)
	await expect(page.locator('.container-widget').filter({ hasText: PRIVATE })).toHaveCount(0)

	const listed = await api.get(`/index.php/apps/launchpad/api/dashboard/${dashboard!.id}`)
	expect(listed.status(), await listed.text()).toBe(200)
	const body = await listed.json()
	const placements: Array<Record<string, any>> = body.placements ?? body.dashboard?.placements ?? []
	const imported = placements.find((p) => p.widgetId === 'container')
	const existing = placements.find((p) => p.widgetId !== 'container')
	expect(imported, JSON.stringify(placements)).toBeTruthy()
	expect(existing, JSON.stringify(placements)).toBeTruthy()
	expect(Number(imported!.gridY)).toBeGreaterThanOrEqual(Number(existing!.gridY) + Number(existing!.gridHeight))
	expect(placements.filter((p) => p.widgetId === 'container')).toHaveLength(1)
})
