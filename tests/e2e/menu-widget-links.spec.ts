/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for what the `menu` widget does with an item's
 * URL and icon: active-item highlighting, external and internal links, and
 * icon resolution (openspec/specs/menu-widget REQ-MENU-006/008/010). Written
 * for e2e-coverage-programme.
 *
 * ACTIVE ITEMS ARE MATCHED AGAINST A REAL ADDRESS. The widget compares an
 * item's URL with `window.location.pathname` below the router base
 * (`/index.php/apps/launchpad`). These tests give the seeded dashboard a slug
 * — and, for the prefix scenario, a chain of parent slugs — and open it at
 * that address, so `/reports` and `/users/alice/profile` in the scenarios
 * become `<base>/<slug>` and `<base>/<users>/<alice>/<profile>` here. Each
 * highlight test also carries one item that must NOT be highlighted, so a
 * widget that highlights everything fails as surely as one that highlights
 * nothing.
 *
 * NO REAL EXTERNAL SITE IS CONTACTED. The external-link test answers
 * `https://github.com/` from a route handler, so the new tab loads without
 * network access and can be inspected for `window.opener` and the referrer.
 *
 * Gate-19 @e2e traceability:
 *   @e2e menu-widget::exact-url-match-highlights-active-item
 *   @e2e menu-widget::route-prefix-match-for-internal-routes
 *   @e2e menu-widget::external-urls-don-t-auto-highlight
 *   @e2e menu-widget::activeitemhighlight-left-bar-renders-left-border
 *   @e2e menu-widget::external-url-opens-in-new-tab
 *   @e2e menu-widget::internal-url-uses-router-push
 *   @e2e menu-widget::fallback-a-tag-has-rel-attribute
 *   @e2e menu-widget::mdi-icon-in-dropdown
 *   @e2e menu-widget::custom-url-icon-in-tree
 *
 * @spec openspec/specs/menu-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { MenuDashboard, MenuSeed } from './support/menuWidget.ts'

import { expect, test } from '@playwright/test'
import {
	APP,
	createDashboard,
	openMenuDashboard,
	removeDashboards,
	seedMenuDashboard,
	treeRow,
} from './support/menuWidget.ts'
import { adminContext } from './support/principals.ts'

const RUN = Date.now()
const ACTIVE = /\bcn-menu-widget__item--active\b/
const IN_PATH = /\bcn-menu-widget__item--in-path\b/

let api: APIRequestContext
/** Dashboards a test created, in creation order; removed after it. */
const dashboards: number[] = []

test.beforeAll(async () => {
	api = await adminContext()
})

test.afterEach(async () => {
	await removeDashboards(api, dashboards)
})

test.afterAll(async () => {
	await api?.dispose()
})

/**
 * Seed and remember a dashboard with one menu placement.
 *
 * @param name a readable part of the dashboard name.
 * @param seed the content, and optionally a slug and parent.
 * @return the seeded dashboard.
 */
async function seed(name: string, seed: MenuSeed): Promise<MenuDashboard> {
	const dash = await seedMenuDashboard(api, `E2E Menu ${name} ${RUN}`, seed)
	dashboards.push(dash.id)
	return dash
}

/**
 * Wait until the address bar shows a path, so the widget is judged against
 * the address the scenario names and not a transient one.
 *
 * @param page the page.
 * @param path the expected pathname.
 * @return nothing.
 */
async function expectPath(page: Page, path: string): Promise<void> {
	await expect.poll(() => new URL(page.url()).pathname.replace(/\/+$/, '')).toBe(path)
}

/**
 * Whether a box lies to the left of another, and is 16-24 px square.
 *
 * @param icon the icon.
 * @param label the label.
 * @return nothing; asserts.
 */
async function expectIconLeftOf(icon: Locator, label: Locator): Promise<void> {
	const i = (await icon.boundingBox())!
	const l = (await label.boundingBox())!
	expect(i.x + i.width, 'the icon sits to the left of the label').toBeLessThanOrEqual(l.x + 1)
	for (const side of [i.width, i.height]) {
		expect(side).toBeGreaterThanOrEqual(16)
		expect(side).toBeLessThanOrEqual(24)
	}
}

test.describe('menu widget — active item (REQ-MENU-006)', () => {
	// @e2e menu-widget::exact-url-match-highlights-active-item
	test('the item whose URL is the current page is highlighted, and so is its ancestor', async ({ page }) => {
		const slug = `e2e-menu-reports-${RUN}`
		const dash = await seed('Exact', {
			slug,
			content: {
				style: 'tree',
				expandedByDefault: true,
				activeItemHighlight: 'background',
				items: [
					{ label: 'Analytics', children: [{ label: 'Reports', url: `/${slug}` }] },
					{ label: 'Elsewhere', url: `/e2e-menu-elsewhere-${RUN}` },
				],
			},
		})
		const widget = await openMenuDashboard(page, dash)
		await expectPath(page, `${APP}/${slug}`)
		await page.mouse.move(0, 0)

		const reports = treeRow(widget, 'Reports')
		const analytics = treeRow(widget, 'Analytics')
		const elsewhere = treeRow(widget, 'Elsewhere')
		await expect(reports).toHaveClass(ACTIVE)
		await expect(analytics).toHaveClass(IN_PATH)
		await expect(elsewhere).not.toHaveClass(ACTIVE)
		await expect(elsewhere).not.toHaveClass(IN_PATH)

		// The configured style is what is drawn: a background the others lack.
		const bg = (row: Locator) => row.evaluate((el) => getComputedStyle(el).backgroundColor)
		const plain = await bg(elsewhere)
		expect(await bg(reports)).not.toBe(plain)
		expect(await bg(analytics)).not.toBe(plain)
	})

	// @e2e menu-widget::route-prefix-match-for-internal-routes
	test('below /users/alice, Alice is active and Users is in the current path', async ({ page }) => {
		const users = `e2e-menu-users-${RUN}`
		const alice = `e2e-menu-alice-${RUN}`
		const profile = `e2e-menu-profile-${RUN}`
		const top = await createDashboard(api, `E2E Menu Users ${RUN}`, users)
		dashboards.push(top.id)
		const mid = await createDashboard(api, `E2E Menu Alice ${RUN}`, alice, top.uuid)
		dashboards.push(mid.id)
		const dash = await seed('Profile', {
			slug: profile,
			parentUuid: mid.uuid,
			content: {
				style: 'tree',
				expandedByDefault: true,
				items: [
					{ label: 'Users', url: `/${users}`, children: [{ label: 'Alice', url: `/${users}/${alice}` }] },
					{ label: 'Elsewhere', url: `/e2e-menu-elsewhere-${RUN}` },
				],
			},
		})
		const path = `${users}/${alice}/${profile}`
		const widget = await openMenuDashboard(page, dash, path)
		await expectPath(page, `${APP}/${path}`)

		await expect(treeRow(widget, 'Alice')).toHaveClass(ACTIVE)
		await expect(treeRow(widget, 'Users')).toHaveClass(IN_PATH)
		await expect(treeRow(widget, 'Users')).not.toHaveClass(ACTIVE)
		await expect(treeRow(widget, 'Elsewhere')).not.toHaveClass(ACTIVE)
	})

	// @e2e menu-widget::external-urls-don-t-auto-highlight
	test('an external URL is never highlighted, while an internal match on the same page is', async ({ page }) => {
		const slug = `e2e-menu-external-${RUN}`
		const dash = await seed('ExternalHi', {
			slug,
			content: {
				items: [
					{ label: 'External', url: 'https://example.com' },
					{ label: 'Here', url: `/${slug}` },
				],
			},
		})
		const widget = await openMenuDashboard(page, dash)
		await expectPath(page, `${APP}/${slug}`)

		const external = widget.getByRole('menuitem', { name: 'External', exact: true })
		await expect(widget.getByRole('menuitem', { name: 'Here', exact: true })).toHaveClass(ACTIVE)
		await expect(external).toBeVisible()
		await expect(external).not.toHaveClass(ACTIVE)
		await expect(external).not.toHaveClass(IN_PATH)
	})

	// @e2e menu-widget::activeitemhighlight-left-bar-renders-left-border
	test('left-bar draws a 4-6 px left border on the active item only', async ({ page }) => {
		const slug = `e2e-menu-leftbar-${RUN}`
		const dash = await seed('LeftBar', {
			slug,
			content: {
				activeItemHighlight: 'left-bar',
				items: [
					{ label: 'Here', url: `/${slug}` },
					{ label: 'Other', url: `/e2e-menu-other-${RUN}` },
				],
			},
		})
		const widget = await openMenuDashboard(page, dash)
		await expectPath(page, `${APP}/${slug}`)

		const here = widget.getByRole('menuitem', { name: 'Here', exact: true })
		const other = widget.getByRole('menuitem', { name: 'Other', exact: true })
		await expect(here).toHaveClass(ACTIVE)
		const border = (el: Locator) => el.evaluate((node) => {
			const style = getComputedStyle(node)
			return { width: parseFloat(style.borderLeftWidth), style: style.borderLeftStyle }
		})
		const active = await border(here)
		expect(active.style).toBe('solid')
		expect(active.width).toBeGreaterThanOrEqual(4)
		expect(active.width).toBeLessThanOrEqual(6)
		expect((await border(other)).width).toBe(0)
	})
})

test.describe('menu widget — links (REQ-MENU-008)', () => {
	// @e2e menu-widget::external-url-opens-in-new-tab
	test('an external item opens in a new tab with no opener and no referrer', async ({ page, context }) => {
		/*
		 * The scenario names `window.open(url, '_blank', 'noopener,noreferrer')`.
		 * What a person can observe of that call is asserted here: a NEW tab at
		 * the URL, the dashboard tab unchanged, and in the new tab no
		 * `window.opener` (noopener) and an empty `document.referrer`
		 * (noreferrer). The page answering for github.com is served locally.
		 */
		await context.route(/^https:\/\/github\.com\//, (route) => route.fulfill({
			status: 200,
			contentType: 'text/html',
			body: '<!doctype html><title>stub</title><p>stub</p>',
		}))
		const dash = await seed('ExternalTab', { content: { items: [{ label: 'GitHub', url: 'https://github.com' }] } })
		const widget = await openMenuDashboard(page, dash)
		const before = page.url()

		const opened = context.waitForEvent('page')
		await widget.getByRole('menuitem', { name: 'GitHub', exact: true }).click()
		const tab = await opened
		await tab.waitForLoadState()
		try {
			expect(tab.url()).toBe('https://github.com/')
			expect(await tab.evaluate(() => window.opener)).toBeNull()
			expect(await tab.evaluate(() => document.referrer)).toBe('')
			expect(page.url(), 'the dashboard tab stays where it was').toBe(before)
		} finally {
			await tab.close()
		}
	})

	// @e2e menu-widget::internal-url-uses-router-push
	test('an internal item navigates in the same tab without a page load', async ({ page, context }) => {
		// `/store` stands in for the scenario's `/dashboard`: a route the app's
		// router knows, so arriving there is observable.
		const dash = await seed('Internal', { content: { items: [{ label: 'Dashboard', url: '/store' }] } })
		const widget = await openMenuDashboard(page, dash)
		const pages = context.pages().length
		// A router push keeps the document; a full navigation would wipe this.
		await page.evaluate((run) => {
			(window as unknown as { __menuRun: number }).__menuRun = run
		}, RUN)

		await widget.getByRole('menuitem', { name: 'Dashboard', exact: true }).click()
		await expect(page).toHaveURL(new RegExp(`${APP}/store$`))
		expect(await page.evaluate(() => (window as unknown as { __menuRun?: number }).__menuRun)).toBe(RUN)
		expect(context.pages()).toHaveLength(pages)
	})

	// @e2e menu-widget::fallback-a-tag-has-rel-attribute
	test('an external item is an <a> carrying rel="noopener noreferrer"', async ({ page }) => {
		const dash = await seed('Rel', { content: { items: [{ label: 'External', url: 'https://external.com' }] } })
		const widget = await openMenuDashboard(page, dash)

		const link = widget.locator('a').filter({ hasText: 'External' })
		await expect(link).toHaveCount(1)
		await expect(link).toHaveAttribute('href', /^https:\/\/external\.com\/?$/)
		await expect(link).toHaveAttribute('rel', 'noopener noreferrer')
		await expect(link).toHaveAttribute('target', '_blank')
	})
})

test.describe('menu widget — icons (REQ-MENU-010)', () => {
	// @e2e menu-widget::mdi-icon-in-dropdown
	test('a registry icon name draws that MDI icon left of the label', async ({ page }) => {
		const dash = await seed('MdiIcon', {
			content: { style: 'dropdown', items: [{ label: 'Settings', icon: 'Cog' }] },
		})
		const widget = await openMenuDashboard(page, dash)

		const entry = widget.getByRole('menuitem', { name: 'Settings', exact: true })
		const icon = entry.locator('.cn-menu-widget__icon')
		await expect(icon.locator('.cog-icon')).toBeVisible()
		await expectIconLeftOf(icon, entry.locator('.cn-menu-widget__label'))
	})

	// @e2e menu-widget::custom-url-icon-in-tree
	test('a URL icon draws that image left of the label in a tree', async ({ page }) => {
		const src = '/apps/launchpad/icons/custom.svg'
		await page.route(`**${src}`, (route) => route.fulfill({
			status: 200,
			contentType: 'image/svg+xml',
			body: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"><rect width="20" height="20" fill="red"/></svg>',
		}))
		const dash = await seed('UrlIcon', { content: { style: 'tree', items: [{ label: 'Custom', icon: src }] } })
		const widget = await openMenuDashboard(page, dash)

		const row = treeRow(widget, 'Custom')
		const img = row.locator(`img[src="${src}"]`)
		await expect(img).toBeVisible()
		await expect.poll(() => img.evaluate((el) => (el as HTMLImageElement).naturalWidth)).toBeGreaterThan(0)
		await expectIconLeftOf(img, row.locator('.cn-menu-tree-node__label'))
	})
})
