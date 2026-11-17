/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A container sorts its tiles when viewed (launcher-tile-sorting).
 *
 * The container comes from the bookmark import, the one path in LaunchPad
 * that writes a container with children, so its link children are exactly
 * what a real user gets. Usage is counted in the viewer's browser only, so
 * the counts live in this test's page storage.
 *
 * @e2e openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'

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
const STORED_ORDER = ['Zaaksysteem', 'Agenda', 'Mail']

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

async function shownOrder(page: Page): Promise<string[]> {
	const container = page.locator('.container-widget').filter({ hasText: `Applicaties ${STAMP}` })
	await expect(container).toBeVisible()
	const labels = await container.locator('.container-widget__child').allInnerTexts()
	return labels.map((label) => label.trim()).filter((label) => label !== '')
}

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
let containerId = 0
let containerContent: Record<string, unknown> = {}

async function sortBy(mode: string): Promise<void> {
	const updated = await api.put(`/index.php/apps/launchpad/api/widgets/${containerId}`, {
		data: { content: { ...containerContent, sortBy: mode } },
	})
	expect(updated.status(), await updated.text()).toBeLessThan(300)
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E tile sorting ${STAMP}`)
	const imported = await api.post(
		`/index.php/apps/launchpad/api/dashboard/${dashboard.id}/tiles/import`,
		{
			data: {
				folders: [{
					name: `Applicaties ${STAMP}`,
					bookmarks: STORED_ORDER.map((title) => ({
						title,
						url: `https://example.invalid/${title.toLowerCase()}`,
					})),
				}],
				bookmarks: [],
			},
		},
	)
	expect(imported.status(), await imported.text()).toBe(201)
	const container = (await imported.json()).placements[0]
	containerId = Number(container.id)
	containerContent = container.content
	expect(containerContent.placements).toHaveLength(STORED_ORDER.length)
	await sortBy('most-used')
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

// @e2e container-widget::a-daily-app-moves-to-the-front
test('most used: a tile opened three times is first after a reload', async ({ page, context }) => {
	context.on('page', (popup) => {
		popup.close().catch(() => undefined)
	})
	await page.goto('/index.php/apps/launchpad/')
	expect(await shownOrder(page)).toEqual(['Agenda', 'Mail', 'Zaaksysteem'])

	const container = page.locator('.container-widget').filter({ hasText: `Applicaties ${STAMP}` })
	for (let i = 0; i < 3; i++) {
		await container.getByText('Zaaksysteem', { exact: true }).click()
	}

	await page.reload()
	expect((await shownOrder(page))[0]).toBe('Zaaksysteem')
})

// @e2e container-widget::back-to-the-authors-layout
test('by hand: the stored order comes back', async ({ page }) => {
	await sortBy('manual')

	await page.goto('/index.php/apps/launchpad/')
	expect(await shownOrder(page)).toEqual(STORED_ORDER)
})
