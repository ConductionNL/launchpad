/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The start page without the navigation panel (runtime-shell REQ-SHELL-009).
 * Off, a member's dashboard view has the panel and no "Menu" button; on, it
 * has no panel and the button offers the panel's destinations beside the
 * dashboard switcher. The option is put back to what it was.
 *
 * @e2e openspec/specs/runtime-shell/spec.md#req-shell-009
 */
import type { APIRequestContext } from '@playwright/test'

import { expect, request as pwRequest, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const API = '/index.php/apps/launchpad/api'

let api: APIRequestContext
let member: { username: string; password: string } | null = null
let before: boolean | null = null

test.setTimeout(180_000)

test.beforeAll(async () => {
	api = await pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	const current = await api.get(`${API}/admin/settings`)
	expect(current.ok(), `settings answered ${current.status()}`).toBe(true)
	const body = await current.json()
	before = Boolean((body.data ?? body).startPageWithoutNavigation)
	member = await provisionThrowawayUser('e2e-norail')
})

test.afterAll(async () => {
	if (before !== null) {
		await api.put(`${API}/admin/settings`, {
			data: { startPageWithoutNavigation: before },
		})
	}
	if (member !== null) {
		await deprovisionUser(member.username)
	}
	await api.dispose()
})

async function setOption(on: boolean): Promise<void> {
	const put = await api.put(`${API}/admin/settings`, {
		data: { startPageWithoutNavigation: on },
	})
	expect(put.ok(), `PUT answered ${put.status()}`).toBe(true)
	const read = await api.get(`${API}/admin/settings`)
	const body = await read.json()
	expect((body.data ?? body).startPageWithoutNavigation).toBe(on)
}

const railIds: string[] = []

// @e2e runtime-shell::the-option-is-off
test('off: the panel is there and there is no menu button', async ({ browser }) => {
	await setOption(false)
	const { context, page } = await loginAs(
		browser,
		member!.username,
		member!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')
		await expect(page.locator('[data-testid="cn-nav"]')).toBeVisible({
			timeout: 30_000,
		})
		await expect(
			page.locator('[data-testid="launchpad-start-page-menu"]'),
		).toHaveCount(0)
		railIds.push(
			...(await page
				.locator('[data-testid^="cn-nav-entry-"]')
				.evaluateAll((els) =>
					els.map((el) =>
						(el.getAttribute('data-testid') ?? '').replace(
							'cn-nav-entry-',
							'',
						),
					),
				)),
		)
		expect(railIds.length).toBeGreaterThan(0)
	} finally {
		await context.close()
	}
})

// @e2e runtime-shell::the-option-is-on
// @e2e runtime-shell::no-destination-is-lost
test('on: no panel, the content at the left edge, every panel entry in the menu', async ({
	browser,
}) => {
	await setOption(true)
	const { context, page } = await loginAs(
		browser,
		member!.username,
		member!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const menu = page.locator('[data-testid="launchpad-start-page-menu"]')
		await expect(menu).toBeVisible({ timeout: 30_000 })
		await expect(page.locator('[data-testid="cn-nav"]')).toHaveCount(0)
		await expect(page.locator('#app-navigation-vue')).toHaveCount(0)
		// The content starts where the panel used to.
		const contentBox = await page.locator('#app-content-vue').boundingBox()
		expect(contentBox?.x ?? 999).toBeLessThan(40)
		// The switcher is beside the menu.
		await expect(page.getByRole('button', { name: 'Dashboards' })).toBeVisible()
		await menu.getByRole('button').first().click()
		const menuIds = await page
			.locator('[data-testid^="launchpad-start-page-menu-"]')
			.evaluateAll((els) =>
				els.map((el) =>
					(el.getAttribute('data-testid') ?? '').replace(
						'launchpad-start-page-menu-',
						'',
					),
				),
			)
		// Every entry the panel showed with the option off is in the menu.
		for (const id of railIds) {
			expect(menuIds, `panel entry ${id} is not in the menu`).toContain(id)
		}
		expect(menuIds).toContain('personal-settings')
	} finally {
		await context.close()
	}
})

// @e2e runtime-shell::other-pages-keep-the-panel
test('on: the Store page keeps the panel', async ({ browser }) => {
	await setOption(true)
	const { context, page } = await loginAs(
		browser,
		member!.username,
		member!.password,
	)
	try {
		await page.goto('/index.php/apps/launchpad/store')
		await expect(page.locator('[data-testid="cn-nav"]')).toBeVisible({
			timeout: 30_000,
		})
		await expect(
			page.locator('[data-testid="launchpad-start-page-menu"]'),
		).toHaveCount(0)
	} finally {
		await context.close()
	}
})
