/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * LaunchPad renders no navigation panel (runtime-shell REQ-SHELL-009). The
 * dashboard view offers the panel's destinations in the workspace's More
 * menu; every other page carries the compact More menu at its top, whose
 * Dashboards entry leads back.
 *
 * @e2e openspec/specs/runtime-shell/spec.md#req-shell-009
 */
import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'

let member: { username: string; password: string } | null = null

test.setTimeout(180_000)

test.beforeAll(async () => {
	member = await provisionThrowawayUser('e2e-norail')
})

test.afterAll(async () => {
	if (member !== null) {
		await deprovisionUser(member.username)
	}
})

/** The panel's destinations a member must find in More. */
const DESTINATIONS = [
	'Documentation',
	'StoreMenu',
	'ReportsMenu',
	'FlowsMenu',
	'FeaturesRoadmapMenu',
]

async function expectNoPanel(page: Page): Promise<void> {
	await expect(page.locator('[data-testid="cn-nav"]')).toHaveCount(0)
	await expect(page.locator('#app-navigation-vue')).toHaveCount(0)
	const contentBox = await page.locator('#app-content-vue').boundingBox()
	expect(contentBox?.x ?? 999).toBeLessThan(40)
}

// @e2e runtime-shell::no-route-renders-the-panel
// @e2e runtime-shell::the-dashboard-view-carries-its-destinations-in-more
// @e2e runtime-shell::no-destination-is-lost
test('the dashboard view has no panel and every destination in More', async ({ browser }) => {
	const { context, page } = await loginAs(browser, member!.username, member!.password)
	try {
		await page.goto('/index.php/apps/launchpad/')
		const more = page.locator('[data-testid="launchpad-more-menu"]')
		await expect(more).toBeVisible({ timeout: 30_000 })
		await expectNoPanel(page)
		await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
		await expect(page.locator('[data-testid="cn-grouped-menu-panel"]')).toBeVisible()
		const ids = await page
			.locator('[data-testid^="cn-grouped-menu-item-"]')
			.evaluateAll((els) => els.map((el) => el.getAttribute('data-testid') ?? ''))
		for (const id of DESTINATIONS) {
			expect(ids, `${id} is not in More`).toContain(`cn-grouped-menu-item-${id}`)
		}
		expect(ids).toContain('cn-grouped-menu-item-all-dashboards')
		expect(ids).toContain('cn-grouped-menu-item-personal-settings')
	} finally {
		await context.close()
	}
})

// @e2e runtime-shell::no-route-renders-the-panel
// @e2e runtime-shell::another-page-carries-more-at-its-top
test('the Marketplace has no panel, More at its top, and Dashboards leads back', async ({ browser }) => {
	const { context, page } = await loginAs(browser, member!.username, member!.password)
	try {
		await page.goto('/index.php/apps/launchpad/store')
		const holder = page.locator('[data-testid="launchpad-page-menu"]')
		await expect(holder).toBeVisible({ timeout: 30_000 })
		await expectNoPanel(page)
		await holder.locator('button').first().click()
		for (const id of DESTINATIONS) {
			await expect(
				page.locator(`[data-testid="launchpad-start-page-menu-${id}"]`),
				`${id} is not in More`,
			).toBeVisible()
		}
		await page.locator('[data-testid="launchpad-start-page-menu-dashboards"]').click()
		// The dashboard view writes the open dashboard's own address
		// (`/apps/launchpad/<slug>`, dashboard-deeplinking), so the URL only
		// has to have left the Marketplace; the view itself is the proof.
		await expect(page).not.toHaveURL(/\/store(\?|#|$)/)
		await expect(page.locator('[data-testid="launchpad-more-menu"]')).toBeVisible({ timeout: 30_000 })
		await expect(page.locator('[data-testid="launchpad-page-menu"]')).toHaveCount(0)
	} finally {
		await context.close()
	}
})
