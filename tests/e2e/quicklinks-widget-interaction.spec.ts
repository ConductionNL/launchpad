/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for what a person DOES with a quicklinks widget
 * (openspec/specs/quicklinks-widget): hover it, click its links, and reach
 * them with the keyboard. Written for e2e-coverage-programme.
 *
 * Each test seeds one placement through `POST /api/dashboard/{id}/widgets`
 * and drives the rendered links with a real pointer and real key presses.
 * `window.open` is recorded by an init script so the exact call
 * (`'_blank', 'noopener,noreferrer'`) is observed, not inferred; where the
 * scenario also says a tab MUST open, the recorder passes the call through
 * and the test waits for the new page.
 *
 * Gate-19 @e2e traceability:
 *   @e2e quicklinks-widget::lift-effect-on-hover
 *   @e2e quicklinks-widget::fade-effect-on-hover
 *   @e2e quicklinks-widget::border-effect-on-hover
 *   @e2e quicklinks-widget::no-hover-effect
 *   @e2e quicklinks-widget::external-url-opens-in-new-tab
 *   @e2e quicklinks-widget::internal-url-opens-in-same-tab
 *   @e2e quicklinks-widget::click-suppressed-in-edit-mode
 *   @e2e quicklinks-widget::explicit-openinnewtab-overrides-auto-detect
 *   @e2e quicklinks-widget::link-is-keyboard-accessible
 *   @e2e quicklinks-widget::aria-label-uses-visible-label-when-available
 *   @e2e quicklinks-widget::aria-label-uses-hostname-for-overlay-labels
 *   @e2e quicklinks-widget::focus-visible-on-keyboard-navigation
 *
 * @spec openspec/specs/quicklinks-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	clearPlacements,
	makeLinks,
	openedCalls,
	openWidget,
	recordWindowOpen,
	resolveColor,
	seedQuicklinks,
} from './support/quicklinks.ts'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.setTimeout(90_000)

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E Quicklinks interaction ${Date.now()}`)
})

test.afterEach(async () => {
	await clearPlacements(api, dashboard?.id ?? 0)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api?.dispose()
})

/**
 * Seed a placement on this file's dashboard and open it.
 *
 * @param page the page.
 * @param content the content blob.
 * @return the rendered widget root.
 */
async function render(page: Page, content: Record<string, unknown>): Promise<Locator> {
	const id = await seedQuicklinks(api, dashboard!.id, content)
	return openWidget(page, id)
}

/**
 * The paint a hover effect may change, for one link.
 *
 * @param link the `<a>`.
 * @return its computed transform/opacity/shadow/outline and transitions.
 */
async function linkPaint(link: Locator) {
	return link.evaluate((el) => {
		const cs = getComputedStyle(el)
		const icon = el.querySelector('.cn-quicklinks-widget__icon') as HTMLElement
		const ics = getComputedStyle(icon)
		const props = cs.transitionProperty.split(',').map((s) => s.trim())
		const durations = cs.transitionDuration.split(',').map((s) => parseFloat(s))
		const transitions: Record<string, number> = {}
		props.forEach((p, i) => {
			transitions[p] = durations[i % durations.length]
		})
		const matrix = cs.transform === 'none' ? [1, 0, 0, 1, 0, 0] : cs.transform.replace(/^matrix\(|\)$/g, '').split(',').map(Number)
		return {
			transform: cs.transform,
			translateY: matrix[5] ?? 0,
			opacity: Number(cs.opacity),
			boxShadow: cs.boxShadow,
			cursor: cs.cursor,
			transitions,
			// A "border" may be drawn as an outline or a border, on the icon
			// container or on the link tile that wraps it.
			rings: [
				{ on: 'icon-outline', style: ics.outlineStyle, width: parseFloat(ics.outlineWidth), color: ics.outlineColor },
				{ on: 'icon-border', style: ics.borderTopStyle, width: parseFloat(ics.borderTopWidth), color: ics.borderTopColor },
				{ on: 'link-outline', style: cs.outlineStyle, width: parseFloat(cs.outlineWidth), color: cs.outlineColor },
				{ on: 'link-border', style: cs.borderTopStyle, width: parseFloat(cs.borderTopWidth), color: cs.borderTopColor },
			].filter((r) => r.style !== 'none' && r.width > 0),
		}
	})
}

/** Park the pointer outside the widget. */
async function unhover(page: Page): Promise<void> {
	await page.mouse.move(1, 1)
}

test.describe('quicklinks widget: hover effects (REQ-QLNK-007)', () => {
	// @e2e quicklinks-widget::lift-effect-on-hover
	test('lift raises only the hovered link 2-4px with a soft shadow over ~200ms', async ({ page }) => {
		const widget = await render(page, { links: makeLinks(5), hoverEffect: 'lift' })
		const links = widget.locator('.cn-quicklinks-widget__link')
		await expect(links).toHaveCount(5)

		await unhover(page)
		await links.nth(2).hover()
		await expect.poll(async () => (await linkPaint(links.nth(2))).translateY, {
			message: 'link 3 translates upward 2-4px',
		}).toBeLessThanOrEqual(-2)
		const hovered = await linkPaint(links.nth(2))
		expect(hovered.translateY).toBeGreaterThanOrEqual(-4)
		expect(hovered.boxShadow, 'link 3 shows a shadow').not.toBe('none')
		expect(hovered.transitions.transform, 'the lift transitions in about 200ms').toBeGreaterThanOrEqual(0.15)
		expect(hovered.transitions.transform).toBeLessThanOrEqual(0.25)

		for (const i of [0, 1, 3, 4]) {
			const other = await linkPaint(links.nth(i))
			expect(other.translateY, `link ${i + 1} stays put`).toBe(0)
			expect(other.boxShadow, `link ${i + 1} has no shadow`).toBe('none')
		}
	})

	// @e2e quicklinks-widget::fade-effect-on-hover
	test('fade dims every other link to 0.6 while the hovered one stays at 1.0', async ({ page }) => {
		const widget = await render(page, { links: makeLinks(5), hoverEffect: 'fade' })
		const links = widget.locator('.cn-quicklinks-widget__link')
		await expect(links).toHaveCount(5)

		await unhover(page)
		await links.nth(1).hover()
		for (const i of [0, 2, 3, 4]) {
			await expect.poll(async () => (await linkPaint(links.nth(i))).opacity, {
				message: `link ${i + 1} fades to 0.6`,
			}).toBeCloseTo(0.6, 2)
		}
		const hovered = await linkPaint(links.nth(1))
		expect(hovered.opacity, 'link 2 stays fully opaque').toBe(1)
		expect(hovered.transitions.opacity, 'the fade transitions in about 150ms').toBeGreaterThanOrEqual(0.1)
		expect(hovered.transitions.opacity).toBeLessThanOrEqual(0.2)
	})

	// @e2e quicklinks-widget::border-effect-on-hover
	test('border rings the hovered icon 2-3px in the link colour, else the primary colour', async ({ page }) => {
		const widget = await render(page, {
			links: [
				{ label: 'Red', url: 'https://example.com/red', icon: 'Star', color: '#ff0000' },
				{ label: 'Plain', url: 'https://example.com/plain', icon: 'Star' },
			],
			hoverEffect: 'border',
			iconShape: 'rounded',
		})
		const links = widget.locator('.cn-quicklinks-widget__link')
		await expect(links).toHaveCount(2)
		const primary = await resolveColor(widget, 'var(--color-primary)')
		const red = await resolveColor(widget, '#ff0000')

		await unhover(page)
		expect((await linkPaint(links.nth(0))).rings, 'no ring at rest').toEqual([])

		for (const [i, expected, what] of [[0, red, 'the link colour'], [1, primary, 'the primary colour']] as const) {
			await links.nth(i).hover()
			await expect.poll(async () => (await linkPaint(links.nth(i))).rings.length, {
				message: `hovering link ${i + 1} draws a ring`,
			}).toBeGreaterThan(0)
			const ring = (await linkPaint(links.nth(i))).rings[0]
			expect(ring.width, 'the ring is 2-3px').toBeGreaterThanOrEqual(2)
			expect(ring.width).toBeLessThanOrEqual(3)
			expect(ring.color, `link ${i + 1}'s ring uses ${what}`).toBe(expected)

			await unhover(page)
			await expect.poll(async () => (await linkPaint(links.nth(i))).rings, {
				message: 'the ring disappears when hover ends',
			}).toEqual([])
		}
	})

	// @e2e quicklinks-widget::no-hover-effect
	test('hoverEffect none leaves the hovered link unchanged apart from the pointer cursor', async ({ page }) => {
		const widget = await render(page, { links: makeLinks(3), hoverEffect: 'none' })
		const links = widget.locator('.cn-quicklinks-widget__link')
		await expect(links).toHaveCount(3)

		await unhover(page)
		const before = await Promise.all([0, 1, 2].map((i) => linkPaint(links.nth(i))))
		await links.nth(1).hover()
		// Longer than the slowest transition the widget declares (200ms).
		await page.waitForTimeout(400)
		const after = await Promise.all([0, 1, 2].map((i) => linkPaint(links.nth(i))))

		for (let i = 0; i < 3; i++) {
			expect(after[i].transform, `link ${i + 1} transform`).toBe(before[i].transform)
			expect(after[i].opacity, `link ${i + 1} opacity`).toBe(before[i].opacity)
			expect(after[i].boxShadow, `link ${i + 1} shadow`).toBe(before[i].boxShadow)
			expect(after[i].rings, `link ${i + 1} ring`).toEqual(before[i].rings)
		}
		expect(after[1].transform).toBe('none')
		expect(after[1].opacity).toBe(1)
		expect(after[1].cursor, 'only the cursor changes, to pointer').toBe('pointer')
	})
})

test.describe('quicklinks widget: click and navigation (REQ-QLNK-009)', () => {
	// @e2e quicklinks-widget::external-url-opens-in-new-tab
	test('an external link opens a new tab via window.open with noopener,noreferrer', async ({ page }) => {
		await recordWindowOpen(page, true)
		const widget = await render(page, { links: [{ label: 'Example', url: 'https://example.com', icon: 'Web' }] })
		const link = widget.locator('.cn-quicklinks-widget__link').first()
		const startUrl = page.url()

		const popup = page.context().waitForEvent('page', { timeout: 15_000 })
		await link.click()
		const tab = await popup
		try {
			expect(await openedCalls(page)).toEqual([['https://example.com', '_blank', 'noopener,noreferrer']])
			expect(page.url(), 'the dashboard stays where it was').toBe(startUrl)
		} finally {
			await tab.close()
		}
	})

	// @e2e quicklinks-widget::internal-url-opens-in-same-tab
	test('a relative link navigates the same tab and opens no new one', async ({ page }) => {
		await recordWindowOpen(page, false)
		const widget = await render(page, { links: [{ label: 'Files', url: '/apps/files/', icon: 'FolderOutline' }] })
		const link = widget.locator('.cn-quicklinks-widget__link').first()
		await expect(link).not.toHaveAttribute('target', '_blank')

		let popups = 0
		page.context().on('page', () => {
			popups++
		})
		// Catch a window.open the click might make before the page unloads.
		await page.evaluate(() => {
			window.addEventListener('beforeunload', () => {
				sessionStorage.setItem('e2e-quicklinks-opened', JSON.stringify((window as unknown as Record<string, unknown>).__opened))
			})
		})

		await link.click()
		await page.waitForURL(/\/apps\/files\/?(\?.*)?$/, { timeout: 30_000 })
		const opened = await page.evaluate(() => sessionStorage.getItem('e2e-quicklinks-opened'))
		expect(JSON.parse(opened ?? '[]'), 'no window.open on the way out').toEqual([])
		expect(popups, 'no new tab').toBe(0)
		expect(page.context().pages()).toHaveLength(1)
	})

	// @e2e quicklinks-widget::click-suppressed-in-edit-mode
	test('in edit mode a click navigates nowhere and the link shows a not-allowed cursor', async ({ page }) => {
		await recordWindowOpen(page, false)
		const widget = await render(page, {
			links: [
				{ label: 'Example', url: 'https://example.com', icon: 'Web' },
				{ label: 'Files', url: '/apps/files/', icon: 'FolderOutline' },
			],
		})
		await enterEditMode(page)
		const startUrl = page.url()
		const links = widget.locator('.cn-quicklinks-widget__link')

		// The click handler, not GridStack's drag surface, is under test, so
		// dispatch the click on the link itself (the same remedy the image
		// widget spec uses for grid cells in edit mode).
		// The external link first, read back before anything could navigate
		// the page away (a navigation would reset the recorder).
		await links.nth(0).dispatchEvent('click')
		await page.waitForTimeout(500)
		expect(await openedCalls(page), 'no window.open in edit mode').toEqual([])

		await links.nth(1).dispatchEvent('click')
		await page.waitForTimeout(1_000)
		expect(page.url(), 'no same-tab navigation in edit mode').toBe(startUrl)
		await expect(page.locator('.launchpad-edit-mode'), 'still on the dashboard, still editing').toBeVisible()
		for (const i of [0, 1]) {
			const cursor = await links.nth(i).evaluate((el) => getComputedStyle(el).cursor)
			expect(cursor, `link ${i + 1} reads as inactive`).toBe('not-allowed')
		}
	})

	// @e2e quicklinks-widget::explicit-openinnewtab-overrides-auto-detect
	test('openInNewTab true opens even a relative link in a new tab', async ({ page }) => {
		await recordWindowOpen(page, true)
		const widget = await render(page, {
			links: [{ label: 'Files', url: '/apps/files/', icon: 'FolderOutline', openInNewTab: true }],
		})
		const link = widget.locator('.cn-quicklinks-widget__link').first()
		await expect(link).toHaveAttribute('target', '_blank')
		const startUrl = page.url()

		const popup = page.context().waitForEvent('page', { timeout: 15_000 })
		await link.click()
		const tab = await popup
		try {
			expect(await openedCalls(page)).toEqual([['/apps/files/', '_blank', 'noopener,noreferrer']])
			expect(page.url(), 'the dashboard tab does not navigate').toBe(startUrl)
		} finally {
			await tab.close()
		}
	})
})

test.describe('quicklinks widget: accessibility (REQ-QLNK-010)', () => {
	/**
	 * Put a focusable sentinel immediately before the widget's links and
	 * focus it, so "focus outside the widget, then Tab" starts from a known
	 * point instead of the page's whole header. Test-only DOM; the links
	 * themselves are untouched.
	 *
	 * @param widget the widget root.
	 * @return the sentinel.
	 */
	async function focusJustBefore(widget: Locator): Promise<Locator> {
		await widget.evaluate((root) => {
			const sentinel = document.createElement('button')
			sentinel.type = 'button'
			sentinel.textContent = 'before the widget'
			sentinel.setAttribute('data-e2e-sentinel', '')
			root.prepend(sentinel)
			sentinel.focus()
		})
		const sentinel = widget.locator('[data-e2e-sentinel]')
		await expect(sentinel).toBeFocused()
		return sentinel
	}

	// @e2e quicklinks-widget::link-is-keyboard-accessible
	test('Tab cycles through the links in source order and Shift+Tab goes back', async ({ page }) => {
		const widget = await render(page, { links: makeLinks(3) })
		const links = widget.locator('.cn-quicklinks-widget__link')
		await expect(links).toHaveCount(3)
		for (let i = 0; i < 3; i++) {
			expect(await links.nth(i).evaluate((el) => el.tagName), 'each link is an <a>').toBe('A')
			await expect(links.nth(i)).toHaveAttribute('href', `https://example.com/link-${i + 1}`)
		}

		await focusJustBefore(widget)
		await page.keyboard.press('Tab')
		await expect(links.nth(0), 'Tab enters the widget at the first link').toBeFocused()
		await page.keyboard.press('Tab')
		await expect(links.nth(1)).toBeFocused()
		await page.keyboard.press('Tab')
		await expect(links.nth(2)).toBeFocused()

		await page.keyboard.press('Shift+Tab')
		await expect(links.nth(1), 'Shift+Tab moves backwards').toBeFocused()
		await page.keyboard.press('Shift+Tab')
		await expect(links.nth(0)).toBeFocused()
	})

	// @e2e quicklinks-widget::aria-label-uses-visible-label-when-available
	test('with visible labels below, the <a> is labelled by the visible label', async ({ page }) => {
		const widget = await render(page, {
			links: [{ label: 'Docs', url: 'https://docs.example.com', icon: 'FileDocument' }],
			showLabels: true,
			labelPosition: 'below',
		})
		const link = widget.locator('a.cn-quicklinks-widget__link').first()
		await expect(link).toHaveAttribute('aria-label', 'Docs')
		await expect(link).toHaveAttribute('href', 'https://docs.example.com')
		await expect(widget.getByRole('link', { name: 'Docs', exact: true })).toHaveCount(1)
	})

	// @e2e quicklinks-widget::aria-label-uses-hostname-for-overlay-labels
	test('with overlay labels, the <a> is labelled with the URL hostname', async ({ page }) => {
		const widget = await render(page, {
			links: [{ label: 'Docs', url: 'https://docs.example.com', icon: 'FileDocument' }],
			showLabels: true,
			labelPosition: 'overlay',
		})
		const link = widget.locator('a.cn-quicklinks-widget__link').first()
		const aria = await link.getAttribute('aria-label')
		expect(aria, 'the hostname gives the screen reader context').toMatch(/docs\.example\.com/)
		expect(aria, 'not just the hover-only visible label').not.toBe('Docs')
		expect(aria).toBe('Link to docs.example.com')
	})

	// @e2e quicklinks-widget::focus-visible-on-keyboard-navigation
	test('a link reached with Tab shows a focus indicator with at least 3:1 contrast', async ({ page }) => {
		const widget = await render(page, { links: makeLinks(2) })
		const link = widget.locator('.cn-quicklinks-widget__link').first()

		await focusJustBefore(widget)
		await page.keyboard.press('Tab')
		await expect(link).toBeFocused()

		const focus = await link.evaluate((el) => {
			const cs = getComputedStyle(el)
			const parse = (c: string): number[] => {
				const m = c.match(/rgba?\(([^)]+)\)/)
				if (!m) {
					return [0, 0, 0, 0]
				}
				const parts = m[1].split(/[\s,/]+/).filter(Boolean).map(Number)
				return [parts[0], parts[1], parts[2], parts.length > 3 ? parts[3] : 1]
			}
			// Composite every ancestor background, from the root down, onto a
			// white canvas: what is actually painted behind the indicator.
			const chain: Element[] = []
			for (let n: Element | null = el.parentElement; n; n = n.parentElement) {
				chain.unshift(n)
			}
			let bg = [255, 255, 255]
			for (const n of chain) {
				const [r, g, b, a] = parse(getComputedStyle(n).backgroundColor)
				bg = [r * a + bg[0] * (1 - a), g * a + bg[1] * (1 - a), b * a + bg[2] * (1 - a)]
			}
			const lum = (rgb: number[]): number => {
				const [r, g, b] = rgb.map((v) => {
					const s = v / 255
					return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
				})
				return 0.2126 * r + 0.7152 * g + 0.0722 * b
			}
			const ring = parse(cs.outlineColor)
			const l1 = lum(ring.slice(0, 3))
			const l2 = lum(bg)
			return {
				focusVisible: el.matches(':focus-visible'),
				style: cs.outlineStyle,
				width: parseFloat(cs.outlineWidth),
				contrast: (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05),
			}
		})
		expect(focus.focusVisible, 'keyboard focus is :focus-visible').toBe(true)
		expect(focus.style, 'a visible indicator is drawn').not.toBe('none')
		expect(focus.width, 'the indicator has width').toBeGreaterThanOrEqual(2)
		expect(focus.contrast, 'WCAG AA non-text contrast').toBeGreaterThanOrEqual(3)
	})
})
