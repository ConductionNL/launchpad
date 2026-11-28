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
import { openSettingsGroup } from './support/dashboardSwitcher.ts'

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
	// "The content MUST take the full width": starting at the left edge is
	// not enough, a panel on the right or a fixed max-width would pass it.
	const viewport = page.viewportSize()
	expect(viewport, 'the page has a viewport').not.toBeNull()
	expect(contentBox?.width ?? 0).toBeGreaterThan(viewport!.width - 80)
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
		await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
		await expect(page.locator('[data-testid="launchpad-more-menu-panel"]')).toBeVisible()
		const ids = await page
			.locator('[data-testid^="launchpad-more-menu-item-"]')
			.evaluateAll((els) => els.map((el) => el.getAttribute('data-testid') ?? ''))
		for (const id of DESTINATIONS) {
			expect(ids, `${id} is not in More`).toContain(`launchpad-more-menu-item-${id}`)
		}
		expect(ids).toContain('launchpad-more-menu-item-all-dashboards')
		expect(ids).toContain('launchpad-more-menu-item-personal-settings')
		// The admin settings link is an administrator's destination only.
		expect(ids).not.toContain('launchpad-more-menu-item-admin-settings-link')
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
		await holder.locator('[data-testid="launchpad-more-menu-trigger"]').click()
		await openSettingsGroup(page)
		for (const id of DESTINATIONS) {
			await expect(
				page.locator(`[data-testid="launchpad-more-menu-item-${id}"]`),
				`${id} is not in More`,
			).toBeVisible()
		}
		// Boards LpMeer/LpMeerAlle (more-menu-favourites): folded, the list
		// holds only the starred dashboards and 'All dashboards' unfolds the
		// rest; a dashboard row leads back.
		await page.locator('[data-testid="launchpad-more-menu-item-all-dashboards"]').click()
		await page
			.locator('[data-testid="launchpad-more-menu-dashboard-list"] [role="menuitemradio"]')
			.first()
			.click()
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

// @e2e runtime-shell::no-route-renders-the-panel
test('Reports and Flows render no panel either', async ({ browser }) => {
	const { context, page } = await loginAs(browser, member!.username, member!.password)
	try {
		for (const route of ['reports', 'flows']) {
			await page.goto(`/index.php/apps/launchpad/${route}`)
			// The page has rendered its own chrome before the panel's absence
			// is read: an empty, still-mounting page would have no panel either.
			await expect(
				page.locator('[data-testid="launchpad-page-menu"]'),
				`/${route} renders the page More menu`,
			).toBeVisible({ timeout: 30_000 })
			await expectNoPanel(page)
		}
	} finally {
		await context.close()
	}
})

// @e2e runtime-shell::no-route-renders-the-panel
test('an admin page renders no panel of LaunchPad\'s own', async ({ page }) => {
	// The default storage state is the administrator, the one principal who
	// may open the admin pages. Since admin-page-in-nextcloud-settings the
	// admin page is Nextcloud's settings page (REQ-ADM-032): Nextcloud's own
	// admin settings menu is on the left by design and LaunchPad's app shell,
	// its More menu included, is not loaded there (REQ-ADM-035). What stays
	// true is that LaunchPad adds no navigation panel of its own.
	await page.goto('/index.php/settings/admin/launchpad#templates')
	await expect(page.locator('[data-testid="AdminRoot"]')).toBeVisible({ timeout: 30_000 })
	await expect(page.locator('[data-testid="cn-nav"]')).toHaveCount(0)
	await expect(page.locator('[data-testid="launchpad-page-menu"]')).toHaveCount(0)
})

// @e2e runtime-shell::no-destination-is-lost
test('an administrator keeps every destination, and the admin settings link, in More', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/')
	const more = page.locator('[data-testid="launchpad-more-menu"]')
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await openSettingsGroup(page)
	for (const id of [...DESTINATIONS, 'personal-settings', 'admin-settings-link']) {
		await expect(
			page.locator(`[data-testid="launchpad-more-menu-item-${id}"]`),
			`${id} is not in the administrator's More`,
		).toBeVisible()
	}
	// The in-app admin-settings route only redirects, so it is offered once,
	// as the link, never as a second entry.
	await expect(page.locator('[data-testid="launchpad-more-menu-item-admin-settings"]')).toHaveCount(0)
})
