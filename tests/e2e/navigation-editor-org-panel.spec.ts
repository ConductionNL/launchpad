/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the org navigation rail a user sees in the
 * workspace: position, rendering, icons, empty and filtered states, active
 * item detection and the mobile drawer (openspec/specs/navigation-editor-org,
 * REQ-ONAV-004..006 and REQ-ONAV-008..010), written for the
 * e2e-coverage-programme.
 *
 * The tree and position are seeded through the API; what is asserted is what
 * renders (`OrgNavigationPanel.vue`, `OrgNavigationItem.vue`).
 *
 * SUSPECTED BUG, AND WHY EVERY TEST HERE IS `test.fixme`. The rail can never
 * appear. `src/views/WorkspaceApp.vue:20` mounts the panel only
 * `v-if="orgNavStore.shouldRender"`, and `shouldRender`
 * (`src/stores/orgNavigation.js:89-94`) is false until a position other than
 * 'hidden' AND a non-empty tree are in the store. The only workspace code that
 * fetches either is the panel's own `mounted()`
 * (`src/components/OrgNavigationPanel.vue:115-119`), which never runs because
 * the panel is never mounted. Nothing else on the workspace page calls
 * `fetchTree` or `fetchPosition` (only the admin editor does). The unit tests
 * miss it because `src/views/__tests__/WorkspaceApp.behaviour.spec.js:24`
 * stubs the store with `shouldRender` set by hand.
 *
 * The tests are written to the scenarios, so they go red until that is fixed.
 * The absence scenarios (hidden position, empty tree, filtered to zero) each
 * carry a positive control in the same test: without one they would pass
 * today for the wrong reason.
 *
 * A second finding for the active-item scenarios: the panel only exists on
 * WorkspaceApp's routes (`/` and `/dashboards/:id` in src/manifest.json), so
 * pages such as `/apps/launchpad/policies` or `/apps/launchpad/hubris` render
 * no workspace at all, and these tests will stay red there even after the
 * mount is fixed unless the panel moves up a level or the scenario URLs
 * change.
 *
 * The trees, the position and the action matrix are recorded in beforeAll and
 * put back in afterAll (support/orgNavigation.ts).
 *
 * Gate-19 @e2e traceability:
 *   @e2e navigation-editor-org::admin-changes-position-to-left
 *   @e2e navigation-editor-org::position-hidden-suppresses-rail
 *   @e2e navigation-editor-org::tree-renders-with-icons-and-labels
 *   @e2e navigation-editor-org::active-item-is-highlighted
 *   @e2e navigation-editor-org::child-link-without-url-is-not-clickable
 *   @e2e navigation-editor-org::openinnewtab-respected
 *   @e2e navigation-editor-org::icon-from-url-renders-as-img
 *   @e2e navigation-editor-org::icon-name-renders-inline
 *   @e2e navigation-editor-org::missing-icon-renders-no-icon
 *   @e2e navigation-editor-org::empty-tree-renders-nothing
 *   @e2e navigation-editor-org::filtered-to-zero-nodes-renders-nothing
 *   @e2e navigation-editor-org::partial-filtering-still-renders-visible-nodes
 *   @e2e navigation-editor-org::exact-url-match
 *   @e2e navigation-editor-org::prefix-match-with-path-segment-boundary
 *   @e2e navigation-editor-org::prefix-without-boundary-does-not-match
 *   @e2e navigation-editor-org::parent-auto-expands-if-child-is-active
 *   @e2e navigation-editor-org::mobile-hamburger-visible
 *   @e2e navigation-editor-org::click-hamburger-opens-drawer
 *   @e2e navigation-editor-org::close-drawer-on-selection
 *   @e2e navigation-editor-org::desktop-rail-visible-at-800px
 *
 * @spec openspec/specs/navigation-editor-org/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { NavNode, Snapshot } from './support/orgNavigation.ts'
import type { Principal } from './support/principals.ts'

import { mdiBriefcase, mdiFileDocument, mdiFolder } from '@mdi/js'
import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	APP_PATH,
	grantReadsToEveryone,
	navNode,
	ONAV_POSITION,
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
const RAIL = '.org-nav'

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
 * Open a LaunchPad page and wait for the workspace shell.
 *
 * @param page the page.
 * @param path the path below the app.
 * @return nothing.
 */
async function openWorkspace(page: Page, path = '/'): Promise<void> {
	await page.goto(`${APP_PATH}${path}`)
	await expect(page.locator('.workspace-shell')).toBeVisible({ timeout: 30_000 })
}

/**
 * The rail item whose own row carries the label.
 *
 * @param page the page.
 * @param label the node label.
 * @return the item's `<li>`.
 */
function item(page: Page, label: string): Locator {
	return page.locator('li.org-nav-item').filter({
		has: page.locator(':scope > .org-nav-item__rowwrap', { hasText: label }),
	})
}

/**
 * The item's own row (link or section button), not its children's.
 *
 * @param page the page.
 * @param label the node label.
 * @return the row.
 */
function rowOf(page: Page, label: string): Locator {
	return item(page, label).locator(
		':scope > .org-nav-item__rowwrap > .org-nav-item__row',
	)
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
	test.fixme('after the admin sets left, every user’s rail is on the left', async ({
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
	test.fixme('position hidden draws no rail over a non-empty tree', async ({
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
	test.fixme('a section with a folder icon expands to an indented child with a document icon', async ({
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

		const sectionItem = item(page, section)
		await expect(sectionItem).toBeVisible({ timeout: 30_000 })
		await expect(sectionItem).toHaveClass(/org-nav-item--section/)
		await expect(sectionItem).toHaveAttribute('aria-expanded', 'false')
		const sectionIcon = rowOf(page, section).locator('svg.org-nav-item__icon')
		await expect(sectionIcon).toHaveAttribute('width', '24')
		await expect(sectionIcon).toHaveAttribute('height', '24')
		await expect(sectionIcon.locator('path')).toHaveAttribute('d', mdiFolder)
		await expect(
			rowOf(page, section).locator('.org-nav-item__label'),
		).toHaveText(section)
		await expect(item(page, child)).toHaveCount(0)

		await sectionItem
			.locator(':scope > .org-nav-item__rowwrap > .org-nav-item__toggle')
			.click()
		await expect(sectionItem).toHaveAttribute('aria-expanded', 'true')
		const childItem = sectionItem.locator(
			':scope > ul.org-nav-item__children > li.org-nav-item',
		)
		await expect(childItem).toHaveCount(1)
		await expect(childItem.locator('.org-nav-item__label')).toHaveText(child)
		const childIcon = rowOf(page, child).locator('svg.org-nav-item__icon')
		await expect(childIcon.locator('path')).toHaveAttribute('d', mdiFileDocument)
		await expect(childIcon).toHaveAttribute('width', '24')

		// Indented: the child's indent is wider than the section's.
		const indent = (label: string) =>
			item(page, label)
				.locator(':scope > .org-nav-item__rowwrap > .org-nav-item__indent')
				.evaluate((el) => parseFloat(getComputedStyle(el).paddingLeft))
		expect(await indent(child)).toBeGreaterThan(await indent(section))
	})

	// @e2e navigation-editor-org::active-item-is-highlighted
	test.fixme('the node for the current page carries org-nav-item--active', async ({
		page,
	}) => {
		const sales = `E2E Sales ${RUN}`
		const other = `E2E Other ${RUN}`
		await arrange([
			navNode(sales, { url: `${APP_PATH}/dashboards/sales` }),
			navNode(other, { url: `${APP_PATH}/dashboards/other` }),
		])
		await openWorkspace(page, '/dashboards/sales/overview')

		await expect(item(page, sales)).toHaveClass(/\borg-nav-item--active\b/, {
			timeout: 30_000,
		})
		await expect(item(page, other)).not.toHaveClass(/\borg-nav-item--active\b/)
	})

	// @e2e navigation-editor-org::child-link-without-url-is-not-clickable
	test.fixme('a section without a url is a button that expands, never a link', async ({
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

		const sectionRow = rowOf(page, section)
		await expect(sectionRow).toBeVisible({ timeout: 30_000 })
		expect(await sectionRow.evaluate((el) => el.tagName)).toBe('BUTTON')
		await expect(
			item(page, section).locator(':scope > .org-nav-item__rowwrap a'),
		).toHaveCount(0)
		await expect(sectionRow).not.toHaveAttribute('href', /.*/)

		const before = page.url()
		await sectionRow.hover()
		await sectionRow.click()
		expect(page.url()).toBe(before)
		await expect(item(page, section)).toHaveAttribute('aria-expanded', 'true')
		await expect(item(page, `E2E Under ${RUN}`)).toBeVisible()
	})

	// @e2e navigation-editor-org::openinnewtab-respected
	test.fixme('openInNewTab opens the URL in a new tab with noopener noreferrer', async ({
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

		const link = rowOf(page, label)
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
	test.fixme('a URL icon is an <img> left of the label', async ({ page }) => {
		const label = `E2E Portal ${RUN}`
		await arrange([
			navNode(label, {
				icon: '/apps/launchpad/icons/custom.png',
				url: `${APP_PATH}/dashboards/onav-portal`,
			}),
		])
		await openWorkspace(page)

		const img = rowOf(page, label).locator('img')
		await expect(img).toHaveCount(1, { timeout: 30_000 })
		await expect(img).toHaveAttribute('src', '/apps/launchpad/icons/custom.png')
		await expect(img).toHaveAttribute('width', '24')
		await expect(img).toHaveAttribute('height', '24')
		const imgBox = await img.boundingBox()
		const labelBox = await rowOf(page, label)
			.locator('.org-nav-item__label')
			.boundingBox()
		expect(imgBox!.x + imgBox!.width).toBeLessThanOrEqual(labelBox!.x)
	})

	// @e2e navigation-editor-org::icon-name-renders-inline
	//
	// Also disagrees with the product apart from the mount: a bare name is
	// mapped to its MDI path (src/services/iconCatalogue.js:44-55) and drawn
	// as an <svg> by CnDashboardIcon (OrgNavigationItem.vue:53-57), so the
	// name never appears as text. The scenario's inline-label rule is what
	// this test holds the product to.
	test.fixme('a bare icon name appears as inline text left of the label', async ({
		page,
	}) => {
		const label = `E2E Business ${RUN}`
		await arrange([
			navNode(label, {
				icon: 'briefcase',
				url: `${APP_PATH}/dashboards/onav-business`,
			}),
		])
		await openWorkspace(page)

		const row = rowOf(page, label)
		await expect(row).toBeVisible({ timeout: 30_000 })
		const icon = row.locator('.org-nav-item__icon')
		await expect(icon).toHaveText('briefcase')
		// Not merely the glyph the name resolves to.
		await expect(row.locator(`path[d="${mdiBriefcase}"]`)).toHaveCount(0)
		const iconBox = await icon.boundingBox()
		const labelBox = await row.locator('.org-nav-item__label').boundingBox()
		expect(iconBox!.x + iconBox!.width).toBeLessThanOrEqual(labelBox!.x)
	})

	// @e2e navigation-editor-org::missing-icon-renders-no-icon
	test.fixme('a null icon draws nothing but the label', async ({ page }) => {
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

		const row = rowOf(page, label)
		await expect(row).toBeVisible({ timeout: 30_000 })
		await expect(row.locator('.org-nav-item__icon, img, svg')).toHaveCount(0)
		await expect(row).toHaveText(label)
		// Control: the neighbour with an icon does draw one.
		await expect(
			rowOf(page, withIcon).locator('.org-nav-item__icon'),
		).toHaveCount(1)
	})
})

test.describe
	.serial('navigation-editor-org — empty and filtered (REQ-ONAV-008)', () => {
	// @e2e navigation-editor-org::empty-tree-renders-nothing
	test.fixme('an empty tree at position left draws no rail', async ({ page }) => {
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
	test.fixme('a non-admin whose every node is filtered out sees no rail', async ({
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
		await expect(item(page, label)).toBeVisible()
	})

	// @e2e navigation-editor-org::partial-filtering-still-renders-visible-nodes
	test.fixme('a non-admin sees the open section and not the admin one', async ({
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
			await expect(item(session.page, open)).toBeVisible()
			await expect(item(session.page, restricted)).toHaveCount(0)
		} finally {
			await session.context.close()
		}
	})
})

test.describe.serial('navigation-editor-org — active item (REQ-ONAV-009)', () => {
	// @e2e navigation-editor-org::exact-url-match
	test.fixme('a node whose url is exactly the page is active', async ({
		page,
	}) => {
		const label = `E2E Policies ${RUN}`
		await arrange([navNode(label, { url: `${APP_PATH}/policies` })])
		await page.goto(`${APP_PATH}/policies`)
		expect(new URL(page.url()).pathname).toBe(`${APP_PATH}/policies`)
		await expect(item(page, label)).toHaveClass(/\borg-nav-item--active\b/, {
			timeout: 30_000,
		})
	})

	// @e2e navigation-editor-org::prefix-match-with-path-segment-boundary
	test.fixme('a node whose url is a path prefix of the page is active', async ({
		page,
	}) => {
		const label = `E2E Dashboards ${RUN}`
		await arrange([navNode(label, { url: `${APP_PATH}/dashboards` })])
		await page.goto(`${APP_PATH}/dashboards/sales/details`)
		await expect(item(page, label)).toHaveClass(/\borg-nav-item--active\b/, {
			timeout: 30_000,
		})
	})

	// @e2e navigation-editor-org::prefix-without-boundary-does-not-match
	test.fixme('/hub is not active on /hubris', async ({ page }) => {
		const label = `E2E Hub ${RUN}`
		await arrange([navNode(label, { url: `${APP_PATH}/hub` })])
		await page.goto(`${APP_PATH}/hubris`)
		// Rendered, so the missing class is the match rule and not an absent node.
		await expect(item(page, label)).toBeVisible({ timeout: 30_000 })
		await expect(item(page, label)).not.toHaveClass(/\borg-nav-item--active\b/)
	})

	// @e2e navigation-editor-org::parent-auto-expands-if-child-is-active
	test.fixme('the section holding the active child opens by itself', async ({
		page,
	}) => {
		const parent = `E2E Dashboards section ${RUN}`
		const child = `E2E Sales Dashboard ${RUN}`
		await arrange([
			navNode(parent, {
				children: [navNode(child, { url: `${APP_PATH}/dashboards/sales` })],
			}),
		])
		await page.goto(`${APP_PATH}/dashboards/sales`)

		await expect(item(page, parent)).toHaveAttribute('aria-expanded', 'true', {
			timeout: 30_000,
		})
		await expect(item(page, child)).toBeVisible()
		await expect(item(page, child)).toHaveClass(/\borg-nav-item--active\b/)
	})
})

test.describe.serial('navigation-editor-org — mobile layout (REQ-ONAV-010)', () => {
	// @e2e navigation-editor-org::mobile-hamburger-visible
	test.fixme('at 600px a hamburger shows and the rail does not', async ({
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
	test.fixme('the hamburger opens a side drawer over the content with the whole tree', async ({
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
		await expect(item(page, a)).toBeVisible()
		await expect(item(page, b)).toBeVisible()
	})

	// @e2e navigation-editor-org::close-drawer-on-selection
	test.fixme('choosing a link in the drawer navigates and closes the drawer', async ({
		page,
	}) => {
		const label = `E2E Target ${RUN}`
		const target = `${APP_PATH}/dashboards/onav-target-${RUN}`
		await arrange([navNode(label, { url: target })])
		await page.setViewportSize({ width: 600, height: 900 })
		await openWorkspace(page)

		await page.locator('.org-nav__hamburger').click()
		await expect(page.locator('.org-nav__panel--drawer-open')).toBeVisible()
		await rowOf(page, label).click()
		await page.waitForURL(`**${target}`)
		expect(new URL(page.url()).pathname).toBe(target)
		await expect(page.locator('.org-nav__panel--drawer-open')).toHaveCount(0)
		await expect(page.locator('.org-nav__hamburger')).toBeVisible({
			timeout: 30_000,
		})
		await expect(page.locator('.org-nav__panel')).toBeHidden()
	})

	// @e2e navigation-editor-org::desktop-rail-visible-at-800px
	test.fixme('at 800px the full rail is on the left and no hamburger shows', async ({
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
