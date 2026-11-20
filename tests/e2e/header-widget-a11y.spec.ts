/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the header widget's accessibility and
 * print rendering (openspec/specs/header-widget, REQ-HDR-011/012), written
 * for e2e-coverage-programme task 4.4.
 *
 * Every test seeds its own dashboard (made active for the admin, whose
 * session the browser carries) and places header widgets on it through
 * `POST /api/dashboard/{id}/widgets` with exactly the `content` the scenario
 * names; `support/headerWidget.ts` registers those hooks and deletes the
 * dashboard after each test. The assertions read the rendered DOM: computed
 * styles, bounding boxes, network requests and the accessibility tree.
 *
 * Images are same-origin on purpose; see `support/headerWidget.ts` for why an
 * external host would make every load and fallback test pass or fail for the
 * wrong reason (the workspace CSP refuses it before a request is made).
 *
 * Gate-19 @e2e traceability:
 *   @e2e header-widget::title-is-keyboard-navigable
 *   @e2e header-widget::cta-button-is-keyboard-accessible
 *   @e2e header-widget::cta-button-has-accessible-label
 *   @e2e header-widget::subtitle-accessible-to-screen-readers
 *   @e2e header-widget::image-alt-text-or-absence
 *   @e2e header-widget::header-widget-prints-with-background-image
 *   @e2e header-widget::header-widget-prints-with-overlay-and-text
 *
 * @spec openspec/specs/header-widget/spec.md
 */

import type { Locator, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	bg,
	contrastRatio,
	cssOf,
	expectImageLoaded,
	headerSuite,
	LOGO_URL,
	render,
	rgb,
} from './support/headerWidget.ts'

const RUN = Date.now()

test.setTimeout(120_000)

const suite = headerSuite({ withFile: true })
const { place } = suite

test.describe('REQ-HDR-011 accessibility', () => {
	/**
	 * Press Tab until focus satisfies a predicate, up to a bound.
	 *
	 * @param page the page.
	 * @param reached evaluated against `document.activeElement`.
	 * @param arg passed to the predicate.
	 * @return whether focus got there.
	 */
	async function tabUntil(
		page: Page,
		reached: (arg: string) => boolean,
		arg: string,
	): Promise<boolean> {
		await page.locator('body').focus()
		for (let i = 0; i < 120; i++) {
			await page.keyboard.press('Tab')
			if (await page.evaluate(reached, arg)) {
				return true
			}
		}
		return false
	}

	// @e2e header-widget::title-is-keyboard-navigable
	test('Tab reaches the widget, and its h2 is inside the focused region', async ({ page }) => {
		const id = await place({ title: `Keyboard ${RUN}` })
		await render(page, id)

		const reached = await tabUntil(page, (placement: string) => {
			const active = document.activeElement
			const h2 = document.querySelector(`[data-testid="widget-placement-${placement}"] .cn-header-widget h2`)
			return active !== null && active !== document.body && h2 !== null && (active === h2 || active.contains(h2))
		}, String(id))
		expect(reached, 'the widget is in the tab order').toBe(true)

		const focused = page.locator(':focus')
		await expect(focused.locator('h2')).toHaveText(`Keyboard ${RUN}`)
		const role = await focused.getAttribute('role')
		const tag = await focused.evaluate((el) => el.tagName)
		expect(
			role === 'region' || role === 'group' || tag === 'H2',
			`focus lands on the heading or on the widget's accessible region (role=${role}, tag=${tag})`,
		).toBe(true)
	})

	// @e2e header-widget::cta-button-is-keyboard-accessible
	test('Tab focuses the CTA and Enter follows it', async ({ page }) => {
		const target = new URL('/internal/keyboard', BASE_URL).href
		await page.route(target, (route) =>
			route.fulfill({ status: 200, contentType: 'text/html', body: '<title>internal</title>' }))
		const id = await place({ title: 'Keys', cta: { label: `Continue ${RUN}`, url: '/internal/keyboard', style: 'primary' } })
		const [b] = await render(page, id)

		const reached = await tabUntil(page, (label: string) => {
			const active = document.activeElement
			return active !== null && active.tagName === 'A' && (active.textContent ?? '').trim() === label
		}, `Continue ${RUN}`)
		expect(reached, 'the CTA is focusable by Tab').toBe(true)
		await expect(b.getByRole('link', { name: `Continue ${RUN}` })).toBeFocused()

		await page.keyboard.press('Enter')
		await page.waitForURL(target)
	})

	// @e2e header-widget::cta-button-has-accessible-label
	test('an external CTA is named by its text and says it opens a new tab', async ({ page }) => {
		const id = await place({ title: 'Label', cta: { label: 'Sign up', url: 'https://example.com/signup' } })
		const [b] = await render(page, id)
		const cta = b.getByRole('link', { name: /Sign up/ })

		await expect(cta).toHaveCount(1)
		const name = await cta.evaluate((el) => el.getAttribute('aria-label') ?? el.textContent ?? '')
		expect(name).toContain('Sign up')
		expect(name, 'the destination is announced').toMatch(/new tab/i)
		await expect(b.getByRole('link', { name: /Sign up.*new tab/i })).toHaveCount(1)
	})

	// @e2e header-widget::subtitle-accessible-to-screen-readers
	test('the subtitle is an exposed paragraph, never aria-hidden', async ({ page }) => {
		const id = await place({ title: 'Read me', subtitle: 'Explore our dashboard' })
		const [b] = await render(page, id)

		const para = b.getByRole('paragraph').filter({ hasText: 'Explore our dashboard' })
		await expect(para).toHaveCount(1)
		const hidden = await b.getByText('Explore our dashboard').evaluate((el) => el.closest('[aria-hidden="true"]') !== null)
		expect(hidden, 'no aria-hidden on it or any ancestor').toBe(false)
	})

	// @e2e header-widget::image-alt-text-or-absence
	test('a background image is decoration and the content stands on its own', async ({ page }) => {
		const id = await place({
			title: 'Decorated',
			subtitle: 'Words first',
			backgroundImageUrl: LOGO_URL,
			cta: { label: 'Read', url: '/index.php/apps/launchpad/', style: 'primary' },
		})
		const [b] = await render(page, id)
		expect(await bg(b), 'the image is a CSS background').toContain('logo.svg')

		const imgs = await b.locator('img').evaluateAll((list) =>
			list.map((img) => ({ alt: img.getAttribute('alt'), hidden: img.getAttribute('aria-hidden') })))
		for (const img of imgs) {
			expect(img.alt === null || img.alt === '' || img.hidden === 'true', JSON.stringify(img)).toBe(true)
			expect(img.alt ?? '', 'no descriptive alt for decoration').toBe('')
		}
		await expect(b.getByRole('img'), 'nothing image-like in the accessibility tree').toHaveCount(0)
		await expect(b.getByRole('heading', { level: 2, name: 'Decorated' })).toBeVisible()
		await expect(b.getByRole('paragraph').filter({ hasText: 'Words first' })).toHaveCount(1)
		await expect(b.getByRole('link', { name: 'Read' })).toBeVisible()
	})
})

test.describe('REQ-HDR-012 print-friendly rendering', () => {
	/**
	 * The banner's print-color-adjust, under whichever spelling the engine knows.
	 *
	 * @param b the banner.
	 * @return the value.
	 */
	async function colorAdjust(b: Locator): Promise<string> {
		return b.evaluate((el) => {
			const s = window.getComputedStyle(el)
			return s.getPropertyValue('print-color-adjust') || s.getPropertyValue('-webkit-print-color-adjust')
		})
	}

	// @e2e header-widget::header-widget-prints-with-background-image
	test('printed, the banner keeps its background image and asks for exact colours', async ({ page }) => {
		const id = await place({ title: 'Print me', backgroundImageFileId: suite.fileId() })
		const [b] = await render(page, id)
		await expectImageLoaded(b)
		expect(await colorAdjust(b), 'on screen nothing is forced').toBe('economy')

		await page.emulateMedia({ media: 'print' })
		await expect(b).toBeVisible()
		expect((await b.boundingBox())!.height).toBeGreaterThan(0)
		expect(await bg(b), 'the image is still the background').toContain(`/core/preview?fileId=${suite.fileId()}`)
		// Backgrounds print without the user ticking "Print backgrounds".
		expect(await colorAdjust(b)).toBe('exact')
	})

	// @e2e header-widget::header-widget-prints-with-overlay-and-text
	test('printed, the tint overlay, the colour and the text all remain, readably', async ({ page }) => {
		const id = await place({
			title: 'Printed title',
			subtitle: 'Printed subtitle',
			backgroundColor: '#1a1a1a',
			overlayMode: 'tint',
		})
		const [b] = await render(page, id)

		await page.emulateMedia({ media: 'print' })
		const overlay = b.locator('.cn-header-widget__overlay')
		await expect(overlay).toBeAttached()
		expect(await cssOf(overlay, 'display')).not.toBe('none')
		expect(await cssOf(overlay, 'visibility')).toBe('visible')
		expect(Number(await cssOf(overlay, 'opacity'))).toBeGreaterThan(0)
		expect(await cssOf(b, 'background-color')).toBe(rgb('#1a1a1a'))
		expect(await colorAdjust(b), 'the colour is printed, not dropped').toBe('exact')

		for (const text of [b.locator('h2'), b.locator('p')]) {
			await expect(text).toBeVisible()
			const color = await cssOf(text, 'color')
			expect(contrastRatio(color, rgb('#1a1a1a')), `contrast of ${color} on #1a1a1a`).toBeGreaterThanOrEqual(4.5)
		}
	})
})
