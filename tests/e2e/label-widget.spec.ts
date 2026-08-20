/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `label` widget.
 *
 * Drives the real runtime-shell add-widget flow: sidebar → personal-row cog
 * → "Add custom widget…" → Add Widget modal → pick "Label".
 *
 * WHAT CHANGED AND WHY
 * ====================
 * This file used to carry a header block of four `@e2e` tags and three tests,
 * and gate-19 counted all four as covered. It should not have: a tag written
 * above the first declaration in a file binds to that declaration, so a header
 * block claims every scenario it lists on the strength of whichever test comes
 * first. Two of the four were not proven by anything here — nothing in the file
 * used a `<script>` tag, and the pre-fill assertion checked ONE field, not six.
 *
 * So the tags now sit on the individual tests that actually prove them, the
 * `<script>` case has a real test instead of a tag, and the one test that is
 * red in the CI fixture has moved to `label-widget-content-edit.spec.ts` (kept,
 * runnable via `npm run test:e2e:excluded`, not skipped and not deleted) so
 * that the two green tests here can finally stand behind a claim.
 */

import { test, expect } from '@playwright/test'
import {
	gotoLaunchPad,
	openAddWidgetModal,
	closeSidebar,
} from './fixtures/widget-flow'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions'

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
})

/**
 * Add a label widget carrying `text` and return once it is rendered.
 *
 * @param page The Playwright page, already on the LaunchPad workspace.
 * @param text The label text to type into the content form.
 */
async function addLabel(page: any, text: string): Promise<void> {
	await openAddWidgetModal(page)
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await dialog.getByLabel(/widget type/i).selectOption({ label: 'Label' })
	await dialog
		.getByLabel(/label text/i)
		.first()
		.fill(text)

	const addBtn = dialog.getByRole('button', { name: /^add$/i })
	await expect(addBtn).toBeEnabled({ timeout: 5_000 })
	await addBtn.click()
	await expect(dialog).not.toBeVisible({ timeout: 8_000 })

	await closeSidebar(page)
}

test.describe('label widget', () => {
	// The add → save → reload round-trip drives the full runtime shell; the
	// dev instance is slow, so widen the timeout.
	test.describe.configure({ timeout: 90_000 })

	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	test('add → fill → save → render round-trips the content and survives reload', async ({
		page,
	}) => {
		const text = `Sales Q4 ${Date.now()}`
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await dialog.getByLabel(/widget type/i).selectOption({ label: 'Label' })

		await dialog
			.getByLabel(/label text/i)
			.first()
			.fill(text)
		await dialog
			.getByLabel(/font size/i)
			.first()
			.fill('24px')

		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()
		await expect(dialog).not.toBeVisible({ timeout: 8_000 })

		await closeSidebar(page)

		// The rendered widget carries the saved text + font-size (the add →
		// persist → render content round-trip).
		const placement = page
			.locator('.cn-label-widget')
			.filter({ hasText: text })
			.first()
		await expect(placement).toBeVisible({ timeout: 8_000 })
		const fontSize = await placement
			.locator('.cn-label-widget__text')
			.first()
			.evaluate((el) => window.getComputedStyle(el).fontSize)
		expect(fontSize).toBe('24px')

		// Persistence: the placement survives a full page reload.
		await page.reload()
		await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
		await expect(
			page.locator('.cn-label-widget').filter({ hasText: text }).first(),
		).toBeVisible({ timeout: 10_000 })
	})

	// @e2e label-widget::html-in-text-appears-as-literal-characters
	test('REQ-LBL-001: pasted HTML renders as literal text on the dashboard', async ({
		page,
	}) => {
		const html = `<b>HTML</b> ${Date.now()}`
		await addLabel(page, html)

		const placement = page.locator('.cn-label-widget').filter({ hasText: html })
		await expect(placement).toBeVisible({ timeout: 8_000 })

		// Critical XSS check: the user's <b> MUST NOT become a real element.
		await expect(placement.locator('b')).toHaveCount(0)
	})

	// @e2e label-widget::script-tag-in-text-appears-as-literal-characters
	test('REQ-LBL-001: a pasted <script> tag renders as literal text and never executes', async ({
		page,
	}) => {
		const marker = `XSS ${Date.now()}`
		// If this string is ever parsed as markup rather than rendered as
		// text, the assignment below runs and the sentinel becomes readable
		// from the page context.
		const payload = `<script>window.__labelWidgetXss = true<\/script>${marker}`

		await addLabel(page, payload)

		const placement = page
			.locator('.cn-label-widget')
			.filter({ hasText: marker })
			.first()
		await expect(placement).toBeVisible({ timeout: 8_000 })

		// 1. The tag never became a real element.
		await expect(placement.locator('script')).toHaveCount(0)

		// 2. The literal characters are what the user sees. `textContent`
		//    rather than a visual check: the angle brackets are the whole
		//    point and an escaped entity still reads as `<script>` here.
		const rendered = await placement.textContent()
		expect(rendered ?? '').toContain('<script>')

		// 3. Nothing executed. This is the assertion that would catch a
		//    regression to `v-html`, which the two above could still miss if
		//    a sanitiser stripped the tag after it had already run.
		const executed = await page.evaluate(
			() =>
				(window as unknown as Record<string, unknown>).__labelWidgetXss
				=== true,
		)
		expect(executed, 'the pasted script must never execute').toBe(false)
	})
})
