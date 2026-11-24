/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Which org-nav node the rail marks active for the page the user is on
 * (openspec/specs/navigation-editor-org, REQ-ONAV-005 point 3 and
 * REQ-ONAV-009).
 *
 * WHY THE URLS ARE NOT THE SCENARIOS' LITERAL PATHS. The scenarios write
 * example pages (`/apps/launchpad/policies`, `/apps/launchpad/hubris`,
 * `/apps/launchpad/dashboards/sales/overview`). LaunchPad serves none of them:
 * the server answers every path with the workspace (`page#deepLink`), but the
 * client router sends any path no manifest page declares back to `/`
 * (`src/utils/manifestRoutes.js`, the `/:pathMatch(.*)*` redirect), so the
 * user is never actually on those pages and the GIVEN cannot hold. The rule
 * under test is the match between a node's url and the page the user is on,
 * so each test first lets the workspace settle on the page it really shows
 * (`settledPath`), and derives the node urls from that page: the page itself
 * (exact), a path-segment prefix of it, and the page minus its last character
 * (a prefix without a boundary). Then it opens that page and asserts.
 *
 * WHAT A RED HERE MEANS. `OrgNavigationPanel.vue` reads
 * `window.location.pathname` once, in `mounted()`, and never again. The page
 * can still rewrite its address after that (Views.vue `replaceUrlFromInitialState`
 * and `pushUrlForActiveDashboard` replace it with the dashboard's canonical
 * path). Each test asserts that the address bar shows the page it derived the
 * nodes from before it reads the classes, so a red on the active class while
 * that holds is the panel matching against an address the user is no longer
 * on: a product defect, not a test fault.
 *
 * Each test arranges its own tree; the file is not serial, so one red cannot
 * skip the others. The trees, the position and the action matrix are recorded
 * in beforeAll and put back in afterAll (support/orgNavigation.ts).
 *
 * Gate-19 @e2e traceability:
 *   @e2e navigation-editor-org::active-item-is-highlighted
 *   @e2e navigation-editor-org::exact-url-match
 *   @e2e navigation-editor-org::prefix-match-with-path-segment-boundary
 *   @e2e navigation-editor-org::prefix-without-boundary-does-not-match
 *   @e2e navigation-editor-org::parent-auto-expands-if-child-is-active
 *
 * @spec openspec/specs/navigation-editor-org/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { NavNode, Snapshot } from './support/orgNavigation.ts'

import { expect, test } from '@playwright/test'
import {
	APP_PATH,
	grantReadsToEveryone,
	navItem,
	navNode,
	RAIL,
	restore,
	seedTree,
	setPosition,
	snapshot,
} from './support/orgNavigation.ts'
import { adminContext } from './support/principals.ts'

const RUN = Date.now()
const ACTIVE = /\borg-nav-item--active\b/

let admin: APIRequestContext
let snap: Snapshot | null = null

test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	snap = await snapshot(admin)
	await grantReadsToEveryone(admin)
})

test.afterAll(async () => {
	await restore(admin, snap)
	await admin.dispose()
})

/**
 * Open a path and wait until the address bar has stopped changing.
 *
 * @param page the page.
 * @param path an absolute pathname.
 * @return the pathname the page settled on.
 */
async function settledPath(page: Page, path: string): Promise<string> {
	await page.goto(path)
	await expect(page.locator('.workspace-shell')).toBeVisible({ timeout: 30_000 })
	let last = ''
	let since = Date.now()
	await expect
		.poll(
			async () => {
				const now = await page.evaluate(() => window.location.pathname)
				if (now !== last) {
					last = now
					since = Date.now()
				}
				return Date.now() - since
			},
			{ timeout: 30_000, intervals: [250] },
		)
		.toBeGreaterThanOrEqual(2_000)
	return last
}

/**
 * The page the workspace shows when opened at the app root, exactly as
 * `window.location.pathname` spells it.
 *
 * @param page the page.
 * @return the pathname.
 */
async function workspacePage(page: Page): Promise<string> {
	const path = await settledPath(page, `${APP_PATH}/`)
	expect(path.startsWith(APP_PATH), path).toBe(true)
	return path
}

/**
 * A pathname without its trailing slashes.
 *
 * @param path a pathname.
 * @return the same path, unslashed.
 */
function trim(path: string): string {
	return path.replace(/\/+$/, '')
}

/**
 * The pathname one segment up.
 *
 * @param path a pathname.
 * @return its parent, without a trailing slash.
 */
function parentOf(path: string): string {
	const bare = trim(path)
	return bare.slice(0, bare.lastIndexOf('/'))
}

/**
 * Seed the tree at position left, open the page and check the user is on it.
 *
 * @param page the page.
 * @param tree the tree.
 * @param on the pathname the user must be on.
 * @return nothing.
 */
async function openWith(page: Page, tree: NavNode[], on: string): Promise<void> {
	await seedTree(admin, tree)
	await setPosition(admin, 'left')
	// The GIVEN: the user is on this page.
	expect(await settledPath(page, on)).toBe(on)
	await expect(page.locator(RAIL)).toBeVisible({ timeout: 30_000 })
}

// @e2e navigation-editor-org::active-item-is-highlighted
test('the node whose url leads to the current page carries org-nav-item--active', async ({
	page,
}) => {
	const here = await workspacePage(page)
	const sales = `E2E Sales ${RUN}`
	const other = `E2E Other ${RUN}`
	await openWith(
		page,
		[
			navNode(sales, { url: parentOf(here) }),
			navNode(other, { url: `${APP_PATH}/onav-other-${RUN}` }),
		],
		here,
	)

	await expect(navItem(page, sales)).toHaveClass(ACTIVE)
	await expect(navItem(page, other)).toBeVisible()
	await expect(navItem(page, other)).not.toHaveClass(ACTIVE)
})

// @e2e navigation-editor-org::exact-url-match
test('a node whose url is exactly the current page is active', async ({
	page,
}) => {
	const here = await workspacePage(page)
	const label = `E2E Exact ${RUN}`
	await openWith(page, [navNode(label, { url: here })], here)

	await expect(navItem(page, label)).toHaveClass(ACTIVE)
})

// @e2e navigation-editor-org::prefix-match-with-path-segment-boundary
test('a node whose url is a path-segment prefix of the current page is active', async ({
	page,
}) => {
	const here = await workspacePage(page)
	const prefix = parentOf(parentOf(here))
	// A real prefix, ending on a segment boundary of the page.
	expect(here.startsWith(`${prefix}/`)).toBe(true)
	const label = `E2E Prefix ${RUN}`
	await openWith(page, [navNode(label, { url: prefix })], here)

	await expect(navItem(page, label)).toHaveClass(ACTIVE)
})

// @e2e navigation-editor-org::prefix-without-boundary-does-not-match
test('a node whose url is a prefix of the page without a boundary is not active', async ({
	page,
}) => {
	const here = await workspacePage(page)
	const cut = trim(here).slice(0, -1)
	// Like /hub on /hubris: a string prefix that stops inside a segment.
	expect(here.startsWith(cut)).toBe(true)
	expect(here.charAt(cut.length)).toMatch(/[^/?#]/)
	const hub = `E2E Hub ${RUN}`
	const control = `E2E Here ${RUN}`
	await openWith(
		page,
		[navNode(hub, { url: cut }), navNode(control, { url: here })],
		here,
	)

	// Rendered, so the missing class is the match rule and not an absent node.
	await expect(navItem(page, hub)).toBeVisible()
	await expect(navItem(page, hub)).not.toHaveClass(ACTIVE)
	// Control: the panel does match against this page.
	await expect(navItem(page, control)).toHaveClass(ACTIVE)
})

// @e2e navigation-editor-org::parent-auto-expands-if-child-is-active
test('the section holding the active child opens by itself', async ({
	page,
}) => {
	const here = await workspacePage(page)
	const parent = `E2E Dashboards section ${RUN}`
	const child = `E2E Sales Dashboard ${RUN}`
	await openWith(
		page,
		[navNode(parent, { children: [navNode(child, { url: here })] })],
		here,
	)

	await expect(navItem(page, parent)).toHaveAttribute('aria-expanded', 'true')
	await expect(navItem(page, child)).toBeVisible()
	await expect(navItem(page, child)).toHaveClass(ACTIVE)
})
