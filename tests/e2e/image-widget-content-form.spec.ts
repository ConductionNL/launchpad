/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for the Image content form: the `image-widget`
 * delta of the open change `widget-content-forms-board-sync` (REQ-IMG-020),
 * which gate 19 counts as `image-widget::<slug>`. Written for
 * e2e-coverage-programme (task 4.10).
 *
 * EXPECTED RED until the change's Image form task lands. nc-vue 2.218.0's
 * `CnImageWidgetForm.validate()` returns an error only for an empty URL, so
 * an image with a URL and no alternative text saves today. The test drives
 * the Add Widget modal the way `image-widget.spec.ts` does and reads the
 * refusal where the modal puts it: `CnAddWidgetModal` disables its Save
 * button (`data-testid="add-widget-save"`) while the sub-form has errors and
 * sets the first error as the button's `title`. An inline message naming the
 * field counts too.
 *
 * The control half fills the alternative text and expects Save enabled, so a
 * Save that is disabled for some other reason (no URL, a broken modal) does
 * not pass as the refusal.
 *
 * Gate-19 @e2e traceability:
 *   image-widget::alternative-text-is-required
 *
 * @spec openspec/changes/widget-content-forms-board-sync/specs/image-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { BASE_URL as NEXTCLOUD_URL } from './support/baseUrl.ts'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { apiAs, openAddWidgetDialog } from './support/filesWidget.ts'

// A same-origin asset, so the preview loads without outbound internet.
const LOGO = `${NEXTCLOUD_URL}/core/img/logo/logo.svg`
const ADD_REQUEST = /\/apps\/launchpad\/api\/dashboard\/(\d+)\/widgets(\?|$)/

/** What names the field: its label as built, the spec's wording, or Dutch. */
const NAMES_ALT_FIELD = /alt(ernative)?[\s-]*text|who (does not|cannot|can't) see|alternatieve tekst|wie de afbeelding niet ziet/i

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
	api = await apiAs()
	dashboard = await seedActiveDashboard(api, `E2E Image content form ${Date.now()}`)
	await clearSeededWidgets(api, dashboard.id)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
	await restoreWidgetRestriction?.()
})

/**
 * Open the Add Widget modal on the active dashboard with "Image" chosen.
 *
 * @param page the page.
 * @return the dialog.
 */
async function openImageForm(page: Page): Promise<Locator> {
	const dialog = await openAddWidgetDialog(page)
	await dialog.getByLabel(/widget type/i).selectOption({ label: 'Image' })
	await expect(dialog.getByLabel(/image url/i)).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Everything the dialog says about why it will not save: the Save button's
 * title and any alert or error text in the dialog.
 *
 * @param dialog the dialog.
 * @param save the Save button.
 * @return the text.
 */
async function refusalText(dialog: Locator, save: Locator): Promise<string> {
	const title = (await save.getAttribute('title')) ?? ''
	const inline = await dialog.locator('[role="alert"], .error, [class*="error"], .input-field__helper-text-message')
		.allInnerTexts()
	return [title, ...inline].join(' | ')
}

test.describe('REQ-IMG-020: the Image content form', () => {
	// @e2e image-widget::alternative-text-is-required
	test('an image without alternative text is refused, and the refusal names the field', async ({ page }) => {
		const posts: string[] = []
		page.on('request', (req) => {
			if (req.method() === 'POST' && ADD_REQUEST.test(req.url())) {
				posts.push(req.url())
			}
		})

		const dialog = await openImageForm(page)
		await dialog.getByLabel(/image url/i).fill(LOGO)
		// The source is complete; only the alternative text is missing.
		await expect(dialog.locator('img[src*="/core/img/logo/"]').first()).toBeVisible({ timeout: 10_000 })

		const save = dialog.getByTestId('add-widget-save')
		await expect(save, 'the form must refuse an image without alternative text').toBeDisabled({ timeout: 5_000 })
		await expect
			.poll(() => refusalText(dialog, save), { timeout: 5_000, message: 'the refusal names the alternative text field' })
			.toMatch(NAMES_ALT_FIELD)

		// Trying anyway sends nothing and leaves the dialog open.
		await save.click({ force: true }).catch(() => null)
		await expect(dialog).toBeVisible()
		expect(posts).toHaveLength(0)
		const placements = await api.get(`/index.php/apps/launchpad/api/dashboard/${dashboard!.id}`)
		expect(placements.status(), await placements.text()).toBe(200)
		const body = await placements.json()
		const images = ((body.placements ?? body.data?.placements ?? []) as Array<Record<string, unknown>>)
			.filter((placement) => placement.widgetId === 'image')
		expect(images).toHaveLength(0)

		// Control: with the alternative text filled in, the same form may save.
		await dialog.getByLabel(/alt(ernative)?[\s-]*text|who (does not|cannot|can't) see/i).first().fill('Logo van de gemeente')
		await expect(save).toBeEnabled({ timeout: 5_000 })
	})
})
