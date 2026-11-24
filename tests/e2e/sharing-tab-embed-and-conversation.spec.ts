/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the Sharing tab's two new parts (openspec
 * change `sharing-tab-embed-and-conversation`): "Embed on your website"
 * (REQ-EMB-011) and "Conversation beside this dashboard" (REQ-LEAF-006).
 *
 * The admin opens a dashboard of their own, seeded in beforeAll and removed
 * in afterAll, through the switcher's cog menu, as dashboard-sharing does.
 * The token is issued through the real form and the real embed token API;
 * its JWT must show once with the iframe code, and Withdraw must revoke it.
 * Tokens the run created are revoked again in afterAll if a test stopped
 * part-way.
 *
 * Gate-19 @e2e traceability:
 *   @e2e embedded-analytics::create-a-token
 *   @e2e dashboard-talk-leaf::a-personal-dashboard
 *
 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md
 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { openDashboardSwitcher } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const TOKENS = '/index.php/apps/launchpad/api/embed-tokens'
const SITE = 'https://www.zuiddrecht.example'

let api: APIRequestContext
let seeded: SeededDashboard | null = null
const issued: string[] = []

/**
 * Open the seeded dashboard's settings on the Sharing tab.
 *
 * @param {Page} page The page.
 * @return {Promise<Locator>} The sharing panel.
 */
async function openSharingTab(page: Page): Promise<Locator> {
	if (seeded === null) {
		throw new Error('sharing-tab-embed-and-conversation: beforeAll did not seed a dashboard')
	}
	await page.goto('/index.php/apps/launchpad')
	await openDashboardSwitcher(page)
	const row = page
		.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.and(page.locator(`[aria-label="${seeded.name}"]`))
		.first()
	await row.locator('.dashboard-row-actions button').first().click()
	await page.locator('[data-testid="cog-dashboard-config"]').click()
	await page.locator('[data-testid="dashboard-name-input"]').waitFor({ state: 'visible', timeout: 8_000 })
	await page.locator('[data-test="config-tab-sharing"]').click()
	const panel = page.locator('[data-test="config-panel-sharing"]')
	await expect(panel).toBeVisible({ timeout: 5_000 })
	return panel
}

test.describe('Sharing tab: embed tokens and the conversation (REQ-EMB-011, REQ-LEAF-006)', () => {
	test.beforeAll(async () => {
		api = await request.newContext({
			baseURL: BASE_URL,
			httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
			extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
		})
		seeded = await seedActiveDashboard(api, `E2E Sharing embed ${Date.now()}`)
	})

	test.afterAll(async () => {
		for (const id of issued) {
			await api.post(`${TOKENS}/${encodeURIComponent(id)}/revoke`, { data: { reason: 'no_longer_needed' } })
		}
		await removeSeededDashboard(api, seeded)
		seeded = null
		await api?.dispose()
	})

	test('a personal dashboard says the conversation appears once it is shared', async ({ page }) => {
		const panel = await openSharingTab(page)
		const part = panel.locator('[data-test="sharing-conversation"]')
		await expect(part).toContainText('Conversation beside this dashboard')
		await expect(part.locator('[data-test="sharing-conversation-unshared"]')).toHaveText(
			'This dashboard is yours alone. Share it with people or a group and the conversation appears here too.',
		)
		await expect(part.locator('[data-test="sharing-conversation-switch"]')).toHaveCount(0)
	})

	test('create a token: shown once, listed as Active, with the iframe code; Withdraw revokes it', async ({ page }) => {
		test.setTimeout(90_000)
		const panel = await openSharingTab(page)
		const part = panel.locator('[data-test="sharing-embed"]')
		await expect(part).toContainText('Embed on your website', { timeout: 8_000 })

		await part.locator('[data-test="sharing-embed-new"]').click()
		const form = part.locator('[data-test="embed-token-form"]')
		await expect(form).toBeVisible({ timeout: 8_000 })
		await form.locator('[data-test="embed-token-name"] input').fill('Website Zuiddrecht')
		await form.locator('[data-test="embed-token-new-origin"] input').fill(SITE)
		await form.locator('[data-test="embed-token-add-origin"]').click()
		const created = page.waitForResponse((response) => response.url().endsWith('/api/embed-tokens') && response.request().method() === 'POST')
		await form.locator('[data-test="embed-token-submit"]').click()
		const body = await (await created).json()
		issued.push(body.id)

		const note = part.locator('[data-test="sharing-embed-issued"]')
		await expect(note).toContainText('Token Website Zuiddrecht has been created. Copy it now: it is not shown again.')
		await expect(part.locator('[data-test="sharing-embed-jwt"]')).toHaveText(body.jwt)
		await expect(part.locator(`[data-test="sharing-embed-state-${body.id}"]`)).toHaveText('Active')
		await expect(part.locator(`[data-test="sharing-embed-row-${body.id}"]`)).toContainText(SITE)
		const code = await part.locator('[data-test="sharing-embed-code"] textarea').inputValue()
		expect(code).toContain('<iframe src="')
		expect(code).toContain(`/embed/dashboard/${seeded?.uuid}?token=`)

		await part.locator('[data-test="sharing-embed-issued-done"]').click()
		await expect(part.locator('[data-test="sharing-embed-jwt"]')).toHaveCount(0)

		await part.locator(`[data-test="sharing-embed-withdraw-${body.id}"]`).click()
		const dialog = page.getByRole('dialog').filter({ hasText: 'Website Zuiddrecht' }).last()
		await dialog.getByRole('button', { name: /revoke/i }).last().click()
		await expect(part.locator(`[data-test="sharing-embed-state-${body.id}"]`)).toContainText('Revoked on', { timeout: 8_000 })
	})
})
