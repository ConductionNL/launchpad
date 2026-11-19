/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the header widget's text, height presets
 * and call-to-action (openspec/specs/header-widget, REQ-HDR-008/009/010),
 * written for e2e-coverage-programme task 4.4.
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
 *   @e2e header-widget::title-rendered-as-h2
 *   @e2e header-widget::subtitle-rendered-as-paragraph
 *   @e2e header-widget::text-alignment-applied-correctly
 *   @e2e header-widget::vertical-alignment-applied-correctly
 *   @e2e header-widget::text-color-applied-with-contrast-fallback
 *   @e2e header-widget::height-small-120px
 *   @e2e header-widget::height-medium-200px
 *   @e2e header-widget::height-large-320px
 *   @e2e header-widget::height-xlarge-480px
 *   @e2e header-widget::cta-button-with-primary-style
 *   @e2e header-widget::cta-button-with-secondary-style
 *   @e2e header-widget::cta-button-with-ghost-style
 *   @e2e header-widget::external-url-opens-in-new-tab
 *   @e2e header-widget::internal-url-opens-in-same-tab
 *   @e2e header-widget::no-cta-button-when-cta-is-null
 *
 * @spec openspec/specs/header-widget/spec.md
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	contrastRatio,
	cssOf,
	headerSuite,
	render,
	rgb,
} from './support/headerWidget.ts'

test.setTimeout(120_000)

const { place } = headerSuite()

test.describe('REQ-HDR-008 text rendering and styling', () => {
	// @e2e header-widget::title-rendered-as-h2
	test('the title is an h2 and the only heading the banner adds', async ({ page }) => {
		const id = await place({ title: 'Welcome' })
		const [b] = await render(page, id)

		await expect(b.locator('h2')).toHaveCount(1)
		await expect(b.locator('h2')).toHaveText('Welcome')
		await expect(b.getByRole('heading', { level: 2, name: 'Welcome' })).toBeVisible()
		// Proper hierarchy: the banner never claims the page's h1 and adds no
		// sub-headings above or below its own h2.
		await expect(b.locator('h1, h3, h4, h5, h6')).toHaveCount(0)
		await expect(b.getByRole('heading')).toHaveCount(1)
	})

	// @e2e header-widget::subtitle-rendered-as-paragraph
	test('a subtitle is a paragraph, and an empty or null one renders no tag', async ({ page }) => {
		const withSub = await place({ title: 'With', subtitle: 'Explore our dashboard' }, 0)
		const nullSub = await place({ title: 'Null', subtitle: null }, 4)
		const emptySub = await place({ title: 'Empty', subtitle: '' }, 8)
		const [a, b, c] = await render(page, withSub, nullSub, emptySub)

		await expect(a.locator('p')).toHaveCount(1)
		await expect(a.locator('p')).toHaveText('Explore our dashboard')
		await expect(b.locator('p'), 'null subtitle: no tag').toHaveCount(0)
		await expect(c.locator('p'), 'empty subtitle: no tag').toHaveCount(0)
	})

	// @e2e header-widget::text-alignment-applied-correctly
	test('textAlign right right-aligns the title and subtitle', async ({ page }) => {
		const id = await place({ title: 'Right', subtitle: 'Also right', textAlign: 'right' })
		const [b] = await render(page, id)
		const content = b.locator('.cn-header-widget__content')

		expect(await cssOf(content, 'text-align'), 'on the content container').toBe('right')
		for (const el of [b.locator('h2'), b.locator('p')]) {
			expect(await cssOf(el, 'text-align')).toBe('right')
			const [box, cbox] = [await el.boundingBox(), await content.boundingBox()]
			// The right edge sits at the container's right padding (24px).
			expect(cbox!.x + cbox!.width - (box!.x + box!.width)).toBeLessThanOrEqual(26)
			expect(box!.x - cbox!.x, 'and well away from the left edge').toBeGreaterThan(100)
		}
	})

	// @e2e header-widget::vertical-alignment-applied-correctly
	test('verticalAlign top puts the title and subtitle at the top of the banner', async ({ page }) => {
		const id = await place({ title: 'Top', subtitle: 'Under it', verticalAlign: 'top' }, 0, 6)
		const [b] = await render(page, id)
		const content = b.locator('.cn-header-widget__content')

		const display = await cssOf(content, 'display')
		const justify = await cssOf(content, 'justify-content')
		const position = await cssOf(b.locator('h2'), 'position')
		expect(
			(display === 'flex' && justify === 'flex-start') || position === 'absolute',
			`flexbox justify-content or absolute positioning (got ${display}/${justify}/${position})`,
		).toBe(true)

		const [bb, hb, pb] = [await b.boundingBox(), await b.locator('h2').boundingBox(), await b.locator('p').boundingBox()]
		expect(bb!.height, 'tall enough for "top" to mean something').toBeGreaterThan(200)
		expect(hb!.y - bb!.y, 'title at the top (only the 16px padding above it)').toBeLessThanOrEqual(20)
		expect(pb!.y + pb!.height - bb!.y, 'subtitle in the top half').toBeLessThan(bb!.height / 2)
	})

	// @e2e header-widget::text-color-applied-with-contrast-fallback
	test('white background picks dark text; an explicit textColor wins', async ({ page }) => {
		const auto = await place({ title: 'Auto', subtitle: 'auto', backgroundColor: '#ffffff', textColor: null }, 0)
		const fixed = await place({ title: 'Fixed', subtitle: 'fixed', backgroundColor: '#ffffff', textColor: '#ff0000' }, 4)
		const [a, f] = await render(page, auto, fixed)

		const dark = await cssOf(a.locator('h2'), 'color')
		expect(contrastRatio(dark, rgb('#ffffff')), `readable dark text, got ${dark}`).toBeGreaterThanOrEqual(7)
		expect(await cssOf(a.locator('p'), 'color')).toBe(dark)
		expect(await cssOf(f.locator('h2'), 'color')).toBe(rgb('#ff0000'))
		expect(await cssOf(f.locator('p'), 'color')).toBe(rgb('#ff0000'))
	})
})

test.describe('REQ-HDR-009 height presets', () => {
	/*
	 * Each banner is seeded into a 10-row cell (well over 600px), so a banner
	 * that merely fills its cell cannot pass for a preset.
	 */
	/**
	 * Seed one banner with a preset and measure it against the grid.
	 *
	 * @param page the page.
	 * @param height the preset, or undefined for the default.
	 * @return the banner's height and width, and the grid's width.
	 */
	async function measure(page: Page, height: string | undefined): Promise<{ h: number; w: number; gridW: number }> {
		const content: Record<string, unknown> = { title: `Height ${height ?? 'default'}` }
		if (height !== undefined) {
			content.height = height
		}
		const id = await place(content, 0, 10)
		const [b] = await render(page, id)
		const box = await b.boundingBox()
		const grid = await page.locator('[data-testid="launchpad-dashboard-region"] .grid-stack').first().boundingBox()
		return { h: box!.height, w: box!.width, gridW: grid!.width }
	}

	// @e2e header-widget::height-small-120px
	test('height small is 120px and spans the dashboard width', async ({ page }) => {
		const m = await measure(page, 'small')
		expect(Math.abs(m.h - 120), `measured ${m.h}px`).toBeLessThanOrEqual(1)
		expect(m.w, 'full dashboard width').toBeGreaterThanOrEqual(m.gridW * 0.95)
	})

	// @e2e header-widget::height-medium-200px
	test('no height set is the medium preset, 200px', async ({ page }) => {
		const m = await measure(page, undefined)
		expect(Math.abs(m.h - 200), `measured ${m.h}px`).toBeLessThanOrEqual(1)
	})

	// @e2e header-widget::height-large-320px
	test('height large is 320px', async ({ page }) => {
		const m = await measure(page, 'large')
		expect(Math.abs(m.h - 320), `measured ${m.h}px`).toBeLessThanOrEqual(1)
	})

	// @e2e header-widget::height-xlarge-480px
	test('height xlarge is 480px', async ({ page }) => {
		const m = await measure(page, 'xlarge')
		expect(Math.abs(m.h - 480), `measured ${m.h}px`).toBeLessThanOrEqual(1)
	})
})

test.describe('REQ-HDR-010 call-to-action button', () => {
	/**
	 * Answer every request to a host the CI fixture cannot reach, so a click
	 * that navigates there is observable without outbound internet.
	 *
	 * @param page the page.
	 * @return nothing.
	 */
	async function stubExampleCom(page: Page): Promise<void> {
		await page.context().route('https://example.com/**', (route) =>
			route.fulfill({ status: 200, contentType: 'text/html', body: '<title>stub</title>' }))
	}

	/**
	 * Stub a same-origin path so a navigation to it lands on a page.
	 *
	 * @param page the page.
	 * @param path the root-relative path.
	 * @return the absolute URL.
	 */
	async function stubInternal(page: Page, path: string): Promise<string> {
		const url = new URL(path, BASE_URL).href
		await page.route(url, (route) =>
			route.fulfill({ status: 200, contentType: 'text/html', body: '<title>internal</title>' }))
		return url
	}

	// @e2e header-widget::cta-button-with-primary-style
	test('a primary CTA shows its label with primary styling and navigates', async ({ page }) => {
		await stubExampleCom(page)
		const id = await place({ title: 'Join', cta: { label: 'Sign up', url: 'https://example.com/signup', style: 'primary' } })
		const [b] = await render(page, id)
		const cta = b.getByRole('link', { name: /Sign up/ })

		await expect(cta).toHaveText('Sign up')
		await expect(cta).toHaveClass(/cn-header-widget__cta--primary/)
		// The theme primary as the CTA itself inherits it, resolved to rgb().
		const swatch = await cta.evaluate((el) => {
			const primary = window.getComputedStyle(el).getPropertyValue('--color-primary').trim()
			const probe = document.createElement('div')
			probe.style.backgroundColor = primary || '#0070c0'
			document.body.appendChild(probe)
			const value = window.getComputedStyle(probe).backgroundColor
			probe.remove()
			return value
		})
		expect(await cssOf(cta, 'background-color'), 'filled with the theme primary').toBe(swatch)

		const popup = page.context().waitForEvent('page')
		await cta.click()
		const opened = await popup
		await opened.waitForURL('https://example.com/signup')
		expect(opened.url()).toBe('https://example.com/signup')
	})

	// @e2e header-widget::cta-button-with-secondary-style
	test('a secondary CTA with an internal URL navigates in the same tab', async ({ page }) => {
		const target = await stubInternal(page, '/internal/docs')
		const id = await place({ title: 'Docs', cta: { label: 'Learn more', url: '/internal/docs', style: 'secondary' } })
		const [b] = await render(page, id)
		const cta = b.getByRole('link', { name: 'Learn more' })

		await expect(cta).toHaveClass(/cn-header-widget__cta--secondary/)
		expect(await cssOf(cta, 'border-top-style')).toBe('solid')
		const primaryLike = await cssOf(cta, 'background-color')
		expect(primaryLike, 'not the transparent ghost look').not.toBe('rgba(0, 0, 0, 0)')

		let popups = 0
		page.context().on('page', () => popups++)
		await cta.click()
		await page.waitForURL(target)
		expect(popups, 'no new tab').toBe(0)
	})

	// @e2e header-widget::cta-button-with-ghost-style
	test('a ghost CTA is outlined with a transparent fill', async ({ page }) => {
		const id = await place({ title: 'Ghost', cta: { label: 'Dismiss', url: '#', style: 'ghost' } })
		const [b] = await render(page, id)
		const cta = b.getByRole('link', { name: 'Dismiss' })

		await expect(cta).toBeVisible()
		await expect(cta).toHaveClass(/cn-header-widget__cta--ghost/)
		expect(await cssOf(cta, 'background-color'), 'minimal: no fill').toBe('rgba(0, 0, 0, 0)')
		expect(await cssOf(cta, 'border-top-style'), 'outline').toBe('solid')
		expect(await cssOf(cta, 'border-top-width')).toBe('1px')
	})

	// @e2e header-widget::external-url-opens-in-new-tab
	test('an external CTA URL opens a new tab with noopener noreferrer', async ({ page }) => {
		await stubExampleCom(page)
		const id = await place({ title: 'Visit', cta: { label: 'Visit', url: 'https://example.com', style: 'primary' } })
		const [b] = await render(page, id)
		const cta = b.getByRole('link', { name: /Visit/ })

		await expect(cta).toHaveAttribute('target', '_blank')
		await expect(cta).toHaveAttribute('rel', 'noopener noreferrer')
		const before = page.url()
		const popup = page.context().waitForEvent('page')
		await cta.click()
		const opened = await popup
		await opened.waitForURL(/^https:\/\/example\.com\/?$/)
		expect(page.url(), 'the dashboard tab stays where it was').toBe(before)
	})

	// @e2e header-widget::internal-url-opens-in-same-tab
	test('an internal CTA URL navigates the current tab without target or rel', async ({ page }) => {
		const target = await stubInternal(page, '/settings')
		const id = await place({ title: 'Settings', cta: { label: 'Go to settings', url: '/settings', style: 'primary' } })
		const [b] = await render(page, id)
		const cta = b.getByRole('link', { name: 'Go to settings' })

		expect(await cta.getAttribute('target')).toBeNull()
		expect(await cta.getAttribute('rel')).toBeNull()
		let popups = 0
		page.context().on('page', () => popups++)
		await cta.click()
		await page.waitForURL(target)
		expect(popups).toBe(0)
	})

	// @e2e header-widget::no-cta-button-when-cta-is-null
	test('cta null renders no button, only title, subtitle and background', async ({ page }) => {
		const id = await place({ title: 'Plain', subtitle: 'No button', backgroundColor: '#336699', cta: null })
		const [b] = await render(page, id)

		await expect(b.locator('a, button')).toHaveCount(0)
		await expect(b.getByRole('link')).toHaveCount(0)
		await expect(b.getByRole('button')).toHaveCount(0)
		await expect(b.getByRole('heading', { level: 2, name: 'Plain' })).toBeVisible()
		await expect(b.getByText('No button')).toBeVisible()
		expect(await cssOf(b, 'background-color')).toBe(rgb('#336699'))
	})
})
