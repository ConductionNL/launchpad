/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the org navigation rail a user sees in the
 * workspace: position, rendering, icons, empty and filtered states and the
 * mobile drawer (openspec/specs/navigation-editor-org, REQ-ONAV-004..006,
 * REQ-ONAV-008 and REQ-ONAV-010), written for the e2e-coverage-programme.
 *
 * The tree and position are seeded through the API; what is asserted is what
 * renders (`OrgNavigationPanel.vue`, `OrgNavigationItem.vue`).
 *
 * These tests were `test.fixme` until 2026-11-24, which gate-19 does not
 * count. They were written while the rail could never appear: WorkspaceApp
 * mounted the panel only `v-if="orgNavStore.shouldRender"` and nothing on the
 * workspace page fetched the tree or the position. 37c354f8 made the shell
 * load both on mount (`src/views/WorkspaceApp.vue` `mounted()`), so they run
 * now, unchanged, and assert the scenarios as written.
 *
 * The absence scenarios (hidden position, empty tree, filtered to zero) each
 * carry a positive control in the same test: without one they would pass
 * for the wrong reason.
 *
 * Two groups live elsewhere, in files without serial mode, so a red there
 * cannot skip the tests here:
 *   - the active-item scenarios (REQ-ONAV-009 and active-item-is-highlighted)
 *     in navigation-editor-org-active-item.spec.ts;
 *   - icon-name-renders-inline, expected red, in
 *     navigation-editor-org-icon-name.spec.ts.
 *
 * The trees, the position and the action matrix are recorded in beforeAll and
 * put back in afterAll (support/orgNavigation.ts).
 *
 * Gate-19 @e2e traceability:
 *   @e2e navigation-editor-org::admin-changes-position-to-left
 *   @e2e navigation-editor-org::position-hidden-suppresses-rail
 *   @e2e navigation-editor-org::tree-renders-with-icons-and-labels
 *   @e2e navigation-editor-org::child-link-without-url-is-not-clickable
 *   @e2e navigation-editor-org::openinnewtab-respected
 *   @e2e navigation-editor-org::icon-from-url-renders-as-img
 *   @e2e navigation-editor-org::missing-icon-renders-no-icon
 *   @e2e navigation-editor-org::empty-tree-renders-nothing
 *   @e2e navigation-editor-org::filtered-to-zero-nodes-renders-nothing
 *   @e2e navigation-editor-org::partial-filtering-still-renders-visible-nodes
 *   @e2e navigation-editor-org::mobile-hamburger-visible
 *   @e2e navigation-editor-org::click-hamburger-opens-drawer
 *   @e2e navigation-editor-org::close-drawer-on-selection
 *   @e2e navigation-editor-org::desktop-rail-visible-at-800px
 *
 * @spec openspec/specs/navigation-editor-org/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { NavNode, Snapshot } from './support/orgNavigation.ts'
import type { Principal } from './support/principals.ts'

import { mdiFileDocument, mdiFolder } from '@mdi/js'
import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	APP_PATH,
	grantReadsToEveryone,
	navItem,
	navNode,
	navRow,
	ONAV_POSITION,
	openWorkspace,
	RAIL,
	readPosition,
	readTree,
	restore,
	seedTree,
	setPosition,
	snapshot,
} from './support/orgNavigation.ts'
import {
	adminContext,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()

let admin: APIRequestContext
let snap: Snapshot | null = null
let user: Principal | null = null

test.beforeAll(async () => {
	admin = await adminContext()
	snap = await snapshot(admin)
	user = await createPrincipal(admin, 'e2e-onav-rail')
	await assertActingAs(user.api, user.username)
	// The rail's two reads are admin-only as shipped (support/orgNavigation.ts).
	await grantReadsToEveryone(admin)
})

test.afterAll(async () => {
	await removePrincipal(admin, user)
	await restore(admin, snap)
	await admin.dispose()
})

/**
 * Seed the tree and the position.
 *
 * @param tree the tree.
 * @param position the rail position.
 * @return nothing.
 */
async function arrange(tree: NavNode[], position = 'left'): Promise<void> {
	await seedTree(admin, tree)
	await setPosition(admin, position)
}

/**
 * Assert the rail is drawn, on the left half of the viewport.
 *
 * @param page the page.
 * @return nothing.
 */
async function expectLeftRail(page: Page): Promise<void> {
	const rail = page.locator(RAIL)
	await expect(rail).toBeVisible({ timeout: 30_000 })
	await expect(rail).toHaveClass(/\borg-nav--left\b/)
	await expect(page.locator('.workspace-shell')).toHaveClass(
		/workspace-shell--org-nav-left/,
	)
	const box = await page.locator(`${RAIL} .org-nav__panel`).boundingBox()
	const width = page.viewportSize()!.width
	expect(box).not.toBeNull()
	expect(box!.x).toBeLessThan(width / 2)
}

test.describe.serial('navigation-editor-org — position (REQ-ONAV-004)', () => {
	// @e2e navigation-editor-org::admin-changes-position-to-left
	test('after the admin sets left, every user’s rail is on the left', async ({
		page,
		browser,
	}) => {
		await arrange(
			[
				navNode(`E2E Left ${RUN}`, {
					url: `${APP_PATH}/dashboards/onav-left`,
				}),
			],
			'hidden',
		)

		const res = await admin.put(ONAV_POSITION, { data: { position: 'left' } })
		expect(res.status(), await res.text()).toBe(200)
		expect(await res.json()).toEqual({ position: 'left' })
		expect((await readPosition(user!.api)).body).toEqual({ position: 'left' })

		await openWorkspace(page)
		await expectLeftRail(page)

		const session = await loginAs(browser, user!.username, user!.password)
		try {
			await openWorkspace(session.page)
			await expectLeftRail(session.page)
		} finally {
			await session.context.close()
		}
	})

	// @e2e navigation-editor-org::position-hidden-suppresses-rail
	test('position hidden draws no rail over a non-empty tree', async ({
		page,
	}) => {
		const tree = [
			navNode(`E2E Hidden ${RUN}`, {
				url: `${APP_PATH}/dashboards/onav-hidden`,
			}),
		]
		await arrange(tree, 'hidden')
		expect((await readTree(admin)).body.tree).toEqual(tree)

		await openWorkspace(page)
		await expect(page.locator(RAIL)).toHaveCount(0)
		await expect(page.locator('.workspace-shell')).not.toHaveClass(
			/workspace-shell--org-nav-/,
		)

		// Control: the same tree at 'left' does draw, so the absence was the position.
		await setPosition(admin, 'left')
		await openWorkspace(page)
		await expectLeftRail(page)
	})
})

test.describe.serial('navigation-editor-org — rendering (REQ-ONAV-005)', () => {
	// @e2e navigation-editor-org::tree-renders-with-icons-and-labels
	test('a section with a folder icon expands to an indented child with a document icon', async ({
		page,
	}) => {
		const section = `E2E Company Resources ${RUN}`
		const child = `E2E Handbook ${RUN}`
		await arrange([
			navNode(section, {
				icon: 'folder',
				children: [
					navNode(child, {
						icon: 'file-document',
						url: `${APP_PATH}/dashboards/onav-handbook`,
					}),
				],
			}),
		])
		await openWorkspace(page)

		const sectionItem = navItem(page, section)
		await expect(sectionItem).toBeVisible({ timeout: 30_000 })
		await expect(sectionItem).toHaveClass(/org-nav-item--section/)
		await expect(sectionItem).toHaveAttribute('aria-expanded', 'false')
		const sectionIcon = navRow(page, section).locator('svg.org-nav-item__icon')
		await expect(sectionIcon).toHaveAttribute('width', '24')
		await expect(sectionIcon).toHaveAttribute('height', '24')
		await expect(sectionIcon.locator('path')).toHaveAttribute('d', mdiFolder)
		await expect(
			navRow(page, section).locator('.org-nav-item__label'),
		).toHaveText(section)
		await expect(navItem(page, child)).toHaveCount(0)

		await sectionItem
			.locator(':scope > .org-nav-item__rowwrap > .org-nav-item__toggle')
			.click()
		await expect(sectionItem).toHaveAttribute('aria-expanded', 'true')
		const childItem = sectionItem.locator(
			':scope > ul.org-nav-item__children > li.org-nav-item',
		)
		await expect(childItem).toHaveCount(1)
		await expect(childItem.locator('.org-nav-item__label')).toHaveText(child)
		const childIcon = navRow(page, child).locator('svg.org-nav-item__icon')
		await expect(childIcon.locator('path')).toHaveAttribute('d', mdiFileDocument)
		await expect(childIcon).toHaveAttribute('width', '24')

		// Indented: the child's indent is wider than the section's.
		const indent = (label: string) =>
			navItem(page, label)
				.locator(':scope > .org-nav-item__rowwrap > .org-nav-item__indent')
				.evaluate((el) => parseFloat(getComputedStyle(el).paddingLeft))
		expect(await indent(child)).toBeGreaterThan(await indent(section))
	})

	// @e2e navigation-editor-org::child-link-without-url-is-not-clickable
	test('a section without a url is a button that expands, never a link', async ({
		page,
	}) => {
		const section = `E2E No url ${RUN}`
		await arrange([
			navNode(section, {
				children: [
					navNode(`E2E Under ${RUN}`, {
						url: `${APP_PATH}/dashboards/onav-under`,
					}),
				],
			}),
		])
		await openWorkspace(page)

		const sectionRow = navRow(page, section)
		await expect(sectionRow).toBeVisible({ timeout: 30_000 })
		expect(await sectionRow.evaluate((el) => el.tagName)).toBe('BUTTON')
		await expect(
			navItem(page, section).locator(':scope > .org-nav-item__rowwrap a'),
		).toHaveCount(0)
		await expect(sectionRow).not.toHaveAttribute('href', /.*/)

		const before = page.url()
		await sectionRow.hover()
		await sectionRow.click()
		expect(page.url()).toBe(before)
		await expect(navItem(page, section)).toHaveAttribute('aria-expanded', 'true')
		await expect(navItem(page, `E2E Under ${RUN}`)).toBeVisible()
	})

	// @e2e navigation-editor-org::openinnewtab-respected
	test('openInNewTab opens the URL in a new tab with noopener noreferrer', async ({
		page,
		context,
	}) => {
		const label = `E2E External ${RUN}`
		await arrange([
			navNode(label, { url: 'https://external.com', openInNewTab: true }),
		])
		await context.route('https://external.com/**', (route) =>
			route.fulfill({
				status: 200,
				contentType: 'text/html',
				body: '<p>external</p>',
			}),
		)
		await openWorkspace(page)

		const link = navRow(page, label)
		await expect(link).toBeVisible({ timeout: 30_000 })
		await expect(link).toHaveAttribute('href', 'https://external.com')
		await expect(link).toHaveAttribute('target', '_blank')
		await expect(link).toHaveAttribute('rel', 'noopener noreferrer')

		const before = page.url()
		const [popup] = await Promise.all([
			context.waitForEvent('page'),
			link.click(),
		])
		await popup.waitForURL('https://external.com/')
		expect(page.url()).toBe(before)
		await popup.close()
	})
})

test.describe.serial('navigation-editor-org — icons (REQ-ONAV-006)', () => {
	// @e2e navigation-editor-org::icon-from-url-renders-as-img
	test('a URL icon is an <img> left of the label', async ({ page }) => {
		const label = `E2E Portal ${RUN}`
		await arrange([
			navNode(label, {
				icon: '/apps/launchpad/icons/custom.png',
				url: `${APP_PATH}/dashboards/onav-portal`,
			}),
		])
		await openWorkspace(page)

		const img = navRow(page, label).locator('img')
		await expect(img).toHaveCount(1, { timeout: 30_000 })
		await expect(img).toHaveAttribute('src', '/apps/launchpad/icons/custom.png')
		await expect(img).toHaveAttribute('width', '24')
		await expect(img).toHaveAttribute('height', '24')
		const imgBox = await img.boundingBox()
		const labelBox = await navRow(page, label)
			.locator('.org-nav-item__label')
			.boundingBox()
		expect(imgBox!.x + imgBox!.width).toBeLessThanOrEqual(labelBox!.x)
	})

	// @e2e navigation-editor-org::missing-icon-renders-no-icon
	test('a null icon draws nothing but the label', async ({ page }) => {
		const label = `E2E Help ${RUN}`
		const withIcon = `E2E With icon ${RUN}`
		await arrange([
			navNode(label, { icon: null, url: `${APP_PATH}/dashboards/onav-help` }),
			navNode(withIcon, {
				icon: 'folder',
				url: `${APP_PATH}/dashboards/onav-icon`,
			}),
		])
		await openWorkspace(page)

		const row = navRow(page, label)
		await expect(row).toBeVisible({ timeout: 30_000 })
		await expect(row.locator('.org-nav-item__icon, img, svg')).toHaveCount(0)
		await expect(row).toHaveText(label)
		// Control: the neighbour with an icon does draw one.
		await expect(
			navRow(page, withIcon).locator('.org-nav-item__icon'),
		).toHaveCount(1)
	})
})

test.describe
	.serial('navigation-editor-org — empty and filtered (REQ-ONAV-008)', () => {
	// @e2e navigation-editor-org::empty-tree-renders-nothing
	test('an empty tree at position left draws no rail', async ({ page }) => {
		await arrange([], 'left')
		expect((await readTree(admin)).body.tree).toEqual([])
		await openWorkspace(page)
		await expect(page.locator(RAIL)).toHaveCount(0)
		await expect(page.locator('.workspace-shell')).not.toHaveClass(
			/workspace-shell--org-nav-/,
		)

		// Control: one node at the same position does draw.
		await seedTree(admin, [
			navNode(`E2E Not empty ${RUN}`, {
				url: `${APP_PATH}/dashboards/onav-ne`,
			}),
		])
		await openWorkspace(page)
		await expectLeftRail(page)
	})

	// @e2e navigation-editor-org::filtered-to-zero-nodes-renders-nothing
	test('a non-admin whose every node is filtered out sees no rail', async ({
		page,
		browser,
	}) => {
		const label = `E2E Admins ${RUN}`
		await arrange([
			navNode(label, {
				groupVisibility: ['admin'],
				url: `${APP_PATH}/dashboards/onav-adm`,
			}),
		])
		const read = await readTree(user!.api)
		expect(read.status).toBe(200)
		expect(read.body.tree).toEqual([])

		const session = await loginAs(browser, user!.username, user!.password)
		try {
			await openWorkspace(session.page)
			await expect(session.page.locator(RAIL)).toHaveCount(0)
		} finally {
			await session.context.close()
		}

		// Control: the admin, who may see the node, gets the rail.
		await openWorkspace(page)
		await expectLeftRail(page)
		await expect(navItem(page, label)).toBeVisible()
	})

	// @e2e navigation-editor-org::partial-filtering-still-renders-visible-nodes
	test('a non-admin sees the open section and not the admin one', async ({
		browser,
	}) => {
		const open = `E2E For all ${RUN}`
		const restricted = `E2E For admins ${RUN}`
		await arrange([
			navNode(open, { url: `${APP_PATH}/dashboards/onav-all` }),
			navNode(restricted, {
				groupVisibility: ['admin'],
				url: `${APP_PATH}/dashboards/onav-admins`,
			}),
		])
		const session = await loginAs(browser, user!.username, user!.password)
		try {
			await openWorkspace(session.page)
			await expectLeftRail(session.page)
			await expect(navItem(session.page, open)).toBeVisible()
			await expect(navItem(session.page, restricted)).toHaveCount(0)
		} finally {
			await session.context.close()
		}
	})
})

test.describe.serial('navigation-editor-org — mobile layout (REQ-ONAV-010)', () => {
	// @e2e navigation-editor-org::mobile-hamburger-visible
	test('at 600px a hamburger shows and the rail does not', async ({
		page,
	}) => {
		await arrange([
			navNode(`E2E Mobile ${RUN}`, { url: `${APP_PATH}/dashboards/onav-m` }),
		])
		await page.setViewportSize({ width: 600, height: 900 })
		await openWorkspace(page)

		const burger = page.locator('.org-nav__hamburger')
		await expect(burger).toBeVisible({ timeout: 30_000 })
		await expect(burger).toHaveAttribute(
			'aria-label',
			'Open organization navigation',
		)
		await expect(page.locator('.org-nav__panel')).toBeHidden()
	})

	// @e2e navigation-editor-org::click-hamburger-opens-drawer
	test('the hamburger opens a side drawer over the content with the whole tree', async ({
		page,
	}) => {
		const a = `E2E Drawer A ${RUN}`
		const b = `E2E Drawer B ${RUN}`
		await arrange([
			navNode(a, { url: `${APP_PATH}/dashboards/onav-da` }),
			navNode(b, { url: `${APP_PATH}/dashboards/onav-db` }),
		])
		await page.setViewportSize({ width: 600, height: 900 })
		await openWorkspace(page)

		await page.locator('.org-nav__hamburger').click()
		const drawer = page.locator('.org-nav__panel')
		await expect(drawer).toBeVisible()
		await expect(drawer).toHaveClass(/org-nav__panel--drawer-open/)
		expect(await drawer.evaluate((el) => getComputedStyle(el).position)).toBe(
			'fixed',
		)
		const box = await drawer.boundingBox()
		expect(box!.x).toBe(0)
		await expect(
			drawer.getByRole('button', { name: 'Close navigation' }),
		).toBeVisible()
		await expect(navItem(page, a)).toBeVisible()
		await expect(navItem(page, b)).toBeVisible()
	})

	// @e2e navigation-editor-org::close-drawer-on-selection
	test('choosing a link in the drawer navigates and closes the drawer', async ({
		page,
	}) => {
		const label = `E2E Target ${RUN}`
		const target = `${APP_PATH}/dashboards/onav-target-${RUN}`
		await arrange([navNode(label, { url: target })])
		await page.setViewportSize({ width: 600, height: 900 })
		await openWorkspace(page)

		await page.locator('.org-nav__hamburger').click()
		await expect(page.locator('.org-nav__panel--drawer-open')).toBeVisible()
		await navRow(page, label).click()
		// Reaching the address is the navigation. The slug names no dashboard,
		// so the page then falls back to the resolver's dashboard and puts
		// that one's path in the address (REQ-DDL stale slug); the pathname is
		// not asserted after that.
		await page.waitForURL(`**${target}`)
		await expect(page.locator('.org-nav__panel--drawer-open')).toHaveCount(0)
		await expect(page.locator('.org-nav__hamburger')).toBeVisible({
			timeout: 30_000,
		})
		await expect(page.locator('.org-nav__panel')).toBeHidden()
	})

	// @e2e navigation-editor-org::desktop-rail-visible-at-800px
	test('at 800px the full rail is on the left and no hamburger shows', async ({
		page,
	}) => {
		await arrange(
			[
				navNode(`E2E Desktop ${RUN}`, {
					url: `${APP_PATH}/dashboards/onav-d`,
				}),
			],
			'left',
		)
		await page.setViewportSize({ width: 800, height: 900 })
		await openWorkspace(page)

		await expectLeftRail(page)
		await expect(page.locator('.org-nav__panel')).toBeVisible()
		await expect(page.locator('.org-nav__hamburger')).toBeHidden()
	})
})
