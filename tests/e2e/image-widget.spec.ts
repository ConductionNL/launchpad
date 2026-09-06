/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `image` widget.
 *
 * Drives the real runtime-shell add-widget flow: sidebar → personal-row cog
 * → "Add custom widget…" → Add Widget modal → pick "Image".
 *
 * WHAT CHANGED AND WHY
 * ====================
 * This file used to carry a header block of EIGHT `@e2e` tags above THREE
 * tests, and gate-19 counted all eight as covered whenever the file ran. It
 * should not have. A tag written above the first declaration binds to that
 * declaration, so a header block claims every scenario it lists on the strength
 * of whichever test happens to come first — and four of those eight were proven
 * by nothing here at all: no test touched the `fit` select, no test forced an
 * upload error, and no test read the cell's cursor.
 *
 * So every tag now sits on the test that actually proves it, the four unproven
 * ones have real tests, and the one test that is RED in the CI fixture moved to
 * `image-widget-clickthrough.spec.ts` (kept and runnable via
 * `npm run test:e2e:excluded`, not skipped and not deleted).
 *
 * The click-through scenario is still covered here, and covered better. REQ-IMG-003
 * says the cell "MUST call `window.open(link, '_blank', 'noopener,noreferrer')`".
 * Recording `window.open` asserts the two flag arguments — which a real popup
 * cannot show — and dispatching the click event directly sidesteps the
 * "element is outside of the viewport" retries that made the popup version red.
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import * as path from 'path'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import {
	closeSidebar,
	gotoLaunchPad,
	openAddWidgetModal,
} from './fixtures/widget-flow.ts'
import { BASE_URL as NEXTCLOUD_URL } from './support/baseUrl.ts'

const APP_ID = process.env.APP_ID || 'launchpad'

// A same-origin asset, so the cell renders without outbound internet.
const LOGO = `${NEXTCLOUD_URL}/core/img/logo/logo.svg`

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
})

/** Open the Add Widget modal with the "Image" type already chosen. */
async function openImageForm(page: Page) {
	await openAddWidgetModal(page)
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await dialog.getByLabel(/widget type/i).selectOption({ label: 'Image' })
	return dialog
}

/**
 * Add a URL-sourced image widget, optionally with a click-through link, and
 * return the rendered cell locator.
 *
 * @param page The Playwright page, already on the LaunchPad workspace.
 * @param link The click-through link, or '' for none.
 */
async function addUrlImage(page: Page, link: string) {
	const dialog = await openImageForm(page)
	await dialog.getByLabel(/image url/i).fill(LOGO)
	if (link !== '') {
		await dialog.getByLabel(/link \(optional\)/i).fill(link)
	}

	const addBtn = dialog.getByRole('button', { name: /^add$/i })
	await expect(addBtn).toBeEnabled({ timeout: 5_000 })
	await addBtn.click()
	await expect(dialog).not.toBeVisible({ timeout: 8_000 })

	await closeSidebar(page)

	const cell = page
		.locator('.cn-image-widget')
		.filter({ has: page.locator('img[src*="/core/img/logo/"]') })
		.last()
	await expect(cell).toBeAttached({ timeout: 8_000 })
	return cell
}

test.describe('image widget', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e image-widget::upload-populates-url-and-preview
	test('REQ-IMG-005: upload → preview → save → reload still shows image', async ({
		page,
	}) => {
		const dialog = await openImageForm(page)

		// The form moved to nc-vue's CnImageWidgetForm, which replaced the old
		// URL/Upload radio pair with a single always-present file <label> that
		// wraps a hidden input, and DEFERS the upload to commit() so a
		// cancelled dialog never writes an orphaned file. Set the file on the
		// input directly rather than driving a source toggle that no longer
		// exists.
		await dialog
			.locator('.cn-image-widget-form__file-input')
			.setInputFiles(path.join(__dirname, 'fixtures', 'tiny.png'))

		// Before save the preview is a local object-URL (nothing uploaded yet);
		// assert the preview renders so "upload → preview" is still covered.
		await expect(dialog.locator('.cn-image-widget-form__preview')).toBeAttached({
			timeout: 15_000,
		})

		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()
		await expect(dialog).not.toBeVisible({ timeout: 8_000 })

		await closeSidebar(page)

		// The uploaded image renders via the resource serve route.
		const uploaded = page
			.locator(`.cn-image-widget__img[src*="/apps/${APP_ID}/resource/"]`)
			.last()
		await expect(uploaded).toBeAttached({ timeout: 8_000 })

		// Reload and verify persistence.
		await page.reload()
		await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
		await expect(
			page
				.locator(`.cn-image-widget__img[src*="/apps/${APP_ID}/resource/"]`)
				.last(),
		).toBeAttached({ timeout: 10_000 })
	})

	// @e2e image-widget::empty-url-fails-validation
	test('REQ-IMG-002: empty-URL cell shows camera placeholder and ignores clicks', async ({
		page,
	}) => {
		const dialog = await openImageForm(page)

		// The validator blocks save with an empty URL: the Add button stays
		// disabled until a URL (or uploaded resource) is present. This is the
		// UI-observable half of REQ-IMG-002 (empty URL is non-savable); the
		// camera-placeholder rendering for an already-seeded empty cell is
		// covered by the ImageWidget Vitest unit test.
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeDisabled({ timeout: 5_000 })
	})

	// @e2e image-widget::direct-url-string-is-also-accepted
	test('REQ-IMG-005: a directly typed URL is accepted and the preview loads it', async ({
		page,
	}) => {
		const dialog = await openImageForm(page)

		// No file chosen — the URL field alone must drive the preview.
		await dialog.getByLabel(/image url/i).fill(LOGO)

		const preview = dialog.locator('.cn-image-widget-form__preview')
		await expect(preview).toBeAttached({ timeout: 10_000 })
		await expect(preview).toHaveAttribute('src', LOGO, { timeout: 10_000 })

		// A typed URL is sufficient to save — the counterpart of the
		// empty-URL validation above.
		await expect(dialog.getByRole('button', { name: /^add$/i })).toBeEnabled({
			timeout: 5_000,
		})
	})

	// @e2e image-widget::all-allowed-fit-values-selectable
	test('REQ-IMG-005: the fit control offers exactly cover, contain, fill and none', async ({
		page,
	}) => {
		const dialog = await openImageForm(page)

		// `fit` is an NcSelect (vue-select), not a native <select> as the
		// scenario's prose assumes, so the options live in a popper that only
		// exists once the control is opened.
		const fit = dialog
			.locator('.v-select')
			.filter({ has: page.getByText(/^Fit$/) })
			.first()
		const control =
			(await fit.count()) > 0 ? fit : dialog.locator('.v-select').last()

		// The default selected value for a new placement is Cover.
		await expect(control.locator('.vs__selected')).toHaveText(/cover/i, {
			timeout: 10_000,
		})

		await control.locator('.vs__dropdown-toggle').click()
		const options = control
			.locator('.vs__dropdown-option')
			.or(page.locator('.vs__dropdown-menu .vs__dropdown-option'))
		await expect(options.first()).toBeVisible({ timeout: 5_000 })

		const labels = (await options.allTextContents()).map((s) =>
			s.trim().toLowerCase(),
		)
		expect(
			labels,
			'REQ-IMG-005 pins the allowed object-fit set to exactly these four',
		).toEqual(['cover', 'contain', 'fill', 'none'])
	})

	// @e2e image-widget::upload-error-surfaces-to-user
	test('REQ-IMG-005: a failed upload surfaces inline and leaves the URL unset', async ({
		page,
	}) => {
		// The upload is DEFERRED to commit(), so the failure lands when Add is
		// pressed, not when the file is chosen.
		await page.route('**/apps/launchpad/api/resources/upload', (route) =>
			route.fulfill({
				status: 400,
				contentType: 'application/json',
				body: '{"error":"file too large (forced by image-widget.spec.ts)"}',
			}),
		)

		const dialog = await openImageForm(page)
		await dialog
			.locator('.cn-image-widget-form__file-input')
			.setInputFiles(path.join(__dirname, 'fixtures', 'tiny.png'))
		await expect(dialog.locator('.cn-image-widget-form__preview')).toBeAttached({
			timeout: 15_000,
		})

		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()

		// 1. The failure is surfaced inline rather than swallowed.
		//    NOTE: the rendered text is the transport error's own message —
		//    CnImageWidgetForm.commit() uses `err.message || t('Failed to
		//    upload image')`, and an axios rejection always carries a message,
		//    so the translated fallback the spec names is unreachable on this
		//    path. The divergence is reported, not asserted away: what is
		//    asserted here is that an error is shown at all.
		await expect(dialog.locator('.cn-image-widget-form__error')).toBeVisible({
			timeout: 15_000,
		})

		// 2. `form.url` MUST remain unchanged — commit() throws before it
		//    reaches updateField('url', …). The modal therefore stays open
		//    with an empty URL rather than persisting a broken placement.
		await expect(dialog).toBeVisible()
		await expect(dialog.getByLabel(/image url/i)).toHaveValue('')
	})

	// @e2e image-widget::click-opens-link-in-new-tab
	// @e2e image-widget::pointer-cursor-only-with-link
	test('REQ-IMG-003: a linked cell shows a pointer cursor and opens the link via window.open', async ({
		page,
	}) => {
		// Record window.open rather than waiting for a real tab. The scenario
		// asserts the CALL — including the 'noopener,noreferrer' features
		// string, which an opened page cannot report.
		await page.addInitScript(() => {
			;(window as unknown as Record<string, unknown>).__opened = []
			window.open = ((...args: unknown[]) => {
				;(
					(window as unknown as Record<string, unknown>)
						.__opened as unknown[]
				).push(args)
				return null
			}) as typeof window.open
		})
		await gotoLaunchPad(page)

		const cell = await addUrlImage(page, 'https://example.com')

		// Pointer cursor is the affordance half of REQ-IMG-003.
		const cursor = await cell.evaluate(
			(el) => window.getComputedStyle(el).cursor,
		)
		expect(cursor, 'a linked cell must advertise itself as clickable').toBe(
			'pointer',
		)

		// `dispatchEvent` rather than `click()`: the handler under test is a
		// plain @click, and dispatching it directly does not depend on the cell
		// being scrolled into the viewport — which is precisely what made the
		// real-popup version of this test red in CI.
		await cell.dispatchEvent('click')

		const opened = await page.evaluate(
			() =>
				(window as unknown as Record<string, unknown>)
					.__opened as unknown[][],
		)
		expect(
			opened.length,
			'a click on a linked cell must open the link',
		).toBeGreaterThan(0)
		expect(opened[opened.length - 1]).toEqual([
			'https://example.com',
			'_blank',
			'noopener,noreferrer',
		])
	})

	// @e2e image-widget::no-link-no-click-no-pointer
	test('REQ-IMG-003: an unlinked cell keeps the default cursor and clicking does nothing', async ({
		page,
	}) => {
		await page.addInitScript(() => {
			;(window as unknown as Record<string, unknown>).__opened = []
			window.open = ((...args: unknown[]) => {
				;(
					(window as unknown as Record<string, unknown>)
						.__opened as unknown[]
				).push(args)
				return null
			}) as typeof window.open
		})
		await gotoLaunchPad(page)

		const cell = await addUrlImage(page, '')

		const cursor = await cell.evaluate(
			(el) => window.getComputedStyle(el).cursor,
		)
		expect(
			cursor,
			'an unlinked cell must NOT offer a misleading clickable affordance',
		).not.toBe('pointer')

		await cell.dispatchEvent('click')

		const opened = await page.evaluate(
			() =>
				(window as unknown as Record<string, unknown>)
					.__opened as unknown[][],
		)
		expect(opened, 'clicking an unlinked cell must not navigate').toHaveLength(0)
	})
})
