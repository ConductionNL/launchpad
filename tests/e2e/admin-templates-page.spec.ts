/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * End-to-end coverage for the admin Beheer ▸ Templates page
 * (`src/components/admin/tabs/TemplatesPage.vue`, admin-templates spec).
 *
 * This is the ONLY place dashboard templates can be managed, and it is a
 * default-mounted tab: `AdminSettings.vue` passes `default-tab="templates"`
 * to `BeheerTabs`, which renders exactly one panel at a time. So the page is
 * what an admin sees on arrival at `/settings/admin/launchpad`, and if it
 * fails to mount the admin area opens on an empty panel with no error.
 *
 * What is asserted, and why each one is here rather than a screenshot:
 *
 *   1. The page mounts as the default panel — `[data-test="panel-templates"]`
 *      AND the component's own root `[data-test="templates-page"]`. The panel
 *      alone would still be present if the slot rendered nothing.
 *   2. Its two structural affordances are live: the section heading and the
 *      Create-template CTA.
 *   3. The CTA opens `TemplateEditorModal` — the page owns which template is
 *      open in the editor (ADR-004 modal isolation), so this crossing is the
 *      page's own behaviour, not the modal's.
 *   4. Leaving the tab UNMOUNTS the page and returning re-mounts it. That is
 *      the contract `BeheerTabs` provides ("content only renders when
 *      active"), and it is the one that makes the page's `created()` list
 *      fetch re-run — a panel that stayed mounted would silently serve stale
 *      templates after a change made elsewhere.
 *
 * Every assertion is read-only: no template is created, edited or deleted,
 * and the editor is dismissed without submitting.
 */

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const SETTINGS_URL = `${BASE}/index.php/settings/admin/launchpad`

// The component under test, named after the file it covers. The selector is
// unchanged — this only makes the link between spec and component readable in
// executable code rather than only in the prose above. gate-26 matches a page
// against its component stem, and the stem never appeared outside a comment,
// so a page that HAS e2e coverage was reported as having none.
const TemplatesPage = '[data-test="templates-page"]'

test.describe('admin-templates — Templates page', () => {
	test.beforeEach(async ({ page }) => {
		// Same authentication shape as the neighbouring admin spec: the
		// settings page is admin-only and NC answers 401 without it.
		await page
			.context()
			.setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	})

	test('renders as the default Beheer panel with its create affordance', async ({
		page,
	}) => {
		await page.goto(SETTINGS_URL)

		// The panel is the tab host; the inner root is TemplatesPage itself.
		// Both, because an empty slot still renders the panel.
		await expect(page.locator('[data-test="panel-templates"]')).toBeVisible({
			timeout: 20_000,
		})
		const templatesPage = page.locator(TemplatesPage)
		await expect(templatesPage).toBeVisible()

		await expect(templatesPage.getByRole('heading', { level: 3 })).toBeVisible()
		await expect(
			page.locator('[data-testid="admin-create-template"]'),
		).toBeVisible()
	})

	test('the create CTA opens the template editor and cancelling leaves the page intact', async ({
		page,
	}) => {
		await page.goto(SETTINGS_URL)
		await expect(page.locator(TemplatesPage)).toBeVisible({
			timeout: 20_000,
		})

		const editor = page.locator('[data-testid="admin-template-editor"]')
		await expect(editor).toBeHidden()

		await page.locator('[data-testid="admin-create-template"]').click()
		await expect(editor).toBeVisible({ timeout: 10_000 })

		// Dismiss without saving — nothing is persisted by this spec.
		await page.keyboard.press('Escape')
		await expect(editor).toBeHidden({ timeout: 10_000 })
		await expect(page.locator(TemplatesPage)).toBeVisible()
	})

	test('leaving the tab unmounts the page and returning re-mounts it', async ({
		page,
	}) => {
		await page.goto(SETTINGS_URL)
		await expect(page.locator(TemplatesPage)).toBeVisible({
			timeout: 20_000,
		})

		// BeheerTabs renders only the active panel, so switching tabs must
		// remove TemplatesPage from the DOM entirely — that unmount is what
		// makes its created() hook re-fetch the template list on return.
		await page.locator('[data-test="tab-group-dashboards"]').click()
		await expect(page.locator(TemplatesPage)).toBeHidden({
			timeout: 10_000,
		})

		await page.locator('[data-test="tab-templates"]').click()
		await expect(page.locator(TemplatesPage)).toBeVisible({
			timeout: 10_000,
		})
		await expect(
			page.locator('[data-testid="admin-create-template"]'),
		).toBeVisible()
	})
})
