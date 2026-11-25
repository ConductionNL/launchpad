/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The section heading widget, "Tussenkop" (widgets REQ-WDG-031): a
 * full-width heading with one line of explanation, on the page ground.
 *
 * The scenario's WHEN is an editor adding the heading in edit mode, so the
 * test does that: Edit, Add widget in the edit bar, Section heading in the
 * picker, the form's Heading and Explanation, Add. The API only seeds the
 * empty dashboard and reads the stored placement back. "Without a card" is
 * read from the widget wrapper's computed style: no border and a transparent
 * background, which is what a card placement does not have.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::a-section-heading-heads-a-group
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E Section heading ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

test('a section heading heads a group', async ({ page }) => {
	// @e2e widgets::a-section-heading-heads-a-group
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })

	// GIVEN an editor in edit mode.
	await enterEditMode(page)

	// WHEN they add a Section heading "Samenwerken" with the line
	// "De tegels van het team".
	await page.locator('[data-testid="launchpad-edit-bar-add-widget"]').click()
	await page.locator('[data-testid="widget-picker-type-section-heading"]').click()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await dialog.getByLabel('Heading', { exact: true }).fill('Samenwerken')
	await dialog
		.getByLabel('Explanation', { exact: true })
		.fill('De tegels van het team')
	const created = page.waitForResponse(
		(r) =>
			r.request().method() === 'POST'
			&& r.url().includes(`/api/dashboard/${seeded!.id}/widgets`),
	)
	await dialog.locator('[data-testid="add-widget-save"]').click()
	expect((await created).status()).toBe(201)
	await expect(dialog).not.toBeVisible({ timeout: 10_000 })

	// The placement was stored with that content.
	const read = await api.get(
		`/index.php/apps/launchpad/api/dashboard/${seeded!.id}`,
	)
	expect(read.status()).toBe(200)
	const placements = ((await read.json()).placements ?? []) as Array<{
		widgetId: string
		content: Record<string, unknown> | null
	}>
	const stored = placements.filter((p) => p.widgetId === 'section-heading')
	expect(stored).toHaveLength(1)
	expect(stored[0].content).toMatchObject({
		title: 'Samenwerken',
		description: 'De tegels van het team',
	})

	// THEN a full-width heading "Samenwerken" with that line below it and a
	// rule under both, without a card.
	const heading = page.locator('[data-testid="section-heading-widget"]')
	await expect(heading).toHaveCount(1)
	await expect(heading).toBeVisible({ timeout: 15_000 })
	const title = heading.getByRole('heading', { level: 2 })
	const line = heading.locator('.lp-section-heading__description')
	await expect(title).toHaveText('Samenwerken')
	await expect(line).toHaveText('De tegels van het team')
	const titleBox = await title.boundingBox()
	const lineBox = await line.boundingBox()
	expect(lineBox!.y, 'the line sits below the heading').toBeGreaterThanOrEqual(
		titleBox!.y + titleBox!.height - 1,
	)

	await expect(heading).toHaveCSS('border-bottom-width', '1px')
	await expect(heading).not.toHaveCSS('border-bottom-style', 'none')
	const headingBox = await heading.boundingBox()
	expect(
		headingBox!.y + headingBox!.height,
		'the rule is under both',
	).toBeGreaterThanOrEqual(lineBox!.y + lineBox!.height)

	const grid = await page
		.locator('[data-testid="launchpad-dashboard-region"]')
		.boundingBox()
	expect(headingBox!.width, 'full width').toBeGreaterThan(grid!.width * 0.9)

	const wrapper = page
		.locator(
			'.launchpad-widget[data-widget-id="section-heading"] .cn-widget-wrapper',
		)
		.first()
	await expect(wrapper).toHaveCSS('border-top-width', '0px')
	await expect(wrapper).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)')
	await expect(wrapper.locator('.cn-widget-wrapper__title')).toHaveCount(0)
})
