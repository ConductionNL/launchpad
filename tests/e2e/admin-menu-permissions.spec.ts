/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The manifest's `permission` field, proved against a real non-admin session
 * and the real `@conduction/nextcloud-vue` bundle.
 *
 * 🔴 WHY THIS FILE EXISTS AT ALL. `manifest.menu` declares
 * `permission: "admin"` on `admin-templates` and `admin-settings`, and
 * `App.vue` passed no `permissions` prop to `CnAppRoot`.
 * `CnAppNav.passesPermission` renders every entry when the list is missing or
 * empty, so both admin entries rendered for every authenticated account and
 * the declaration was inert.
 *
 * The unit suite cannot prove this fixed. `vitest.config.js` redirects
 * `@conduction/nextcloud-vue` to a stub for EVERY spec, so nothing in it runs
 * the real filter. This is the only test that does.
 *
 * ⚠️ THREE WAYS A TEST LIKE THIS PASSES WITHOUT PROVING ANYTHING, all of which
 * have shipped in this fleet, and each of which is closed below:
 *
 *  1. The "non-admin" context is the admin. `browser.newContext()` merges
 *     playwright.config's `use.storageState`, so a context created without an
 *     explicit override carries the admin cookie. `loginAs` passes
 *     `storageState: undefined` for exactly this reason. Dossiq's version of
 *     this test asked whether an admin could see an admin page and therefore
 *     could only ever fail (ConductionNL/dossiq#2307).
 *  2. The session is anonymous. Clearing the storage state without logging in
 *     lands on `/login`, where the nav has no entries and the route has no
 *     content — so both assertions pass for reasons unrelated to permissions.
 *  3. The page never rendered. "No admin links" is also what a blank page
 *     looks like.
 *
 * So the identity is asserted FIRST, and every absence assertion is paired
 * with a presence assertion that fails if the shell did not render.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::admin-menu-entries-are-hidden-from-non-admins
 *   @e2e runtime-shell::admin-routes-redirect-non-admins
 *   @e2e runtime-shell::admin-menu-entries-render-for-admins
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { installBundleOverride } from './support/bundleOverride.ts'

const APP_BASE = '/apps/launchpad'

/**
 * The two admin surfaces, by all three of the names they go by: the manifest
 * MENU ENTRY id, the URL path, and the route NAME the entry resolves through.
 *
 * 🔴 `id` IS HERE BECAUSE THE ABSENCE ASSERTIONS MUST KEY ON IT, and getting
 * that wrong made this test pass against a bundle with the bug in it.
 *
 * The first version of this file identified the entries by `data-cn-route`,
 * which renders `item.route` verbatim. But this fix CHANGES `item.route`:
 * both entries used to declare the URL path (`/admin/templates`) where
 * `CnAppNav` wants a route name (`admin-templates-index`). So against the
 * unfixed bundle `expect(routes).not.toContain('admin-templates-index')`
 * passed — not because the entry was hidden, but because the unfixed manifest
 * never emits that string. The entries were on screen the whole time, under
 * the names the same run recorded for the admin:
 *
 *     ["Workspace","Store","Reports","FeaturesRoadmap","Flows",
 *      "/admin/templates","/admin/settings"]
 *
 * An absence assertion is only worth what its locator is worth, and a locator
 * keyed to a value the fix introduces can never see the unfixed state. `id`
 * is untouched by this change and by any future re-routing, so it is what the
 * absence is asserted on. `route` stays, used only where the question really
 * is about the route: whether an admin's entry resolves to a working link.
 */
const ADMIN_PAGES = [
	{ id: 'admin-templates', path: '/admin/templates', route: 'admin-templates-index' },
	{ id: 'admin-settings', path: '/admin/settings', route: 'admin-settings' },
] as const

/** A menu entry every account holds, as the positive control. */
const UNGATED_ENTRY_ID = 'dashboards'

/**
 * Who the browser is actually signed in as, read from Nextcloud itself rather
 * than assumed from how the context was built.
 *
 * @param page The page.
 * @return The uid and admin flag.
 */
async function whoami(page: Page): Promise<{ uid: unknown; isAdmin: unknown }> {
	return page.evaluate(() => ({
		 
		uid: (window as any).OC?.getCurrentUser?.()?.uid ?? null,
		 
		isAdmin: (window as any).OC?.isUserAdmin?.() ?? null,
	}))
}

/**
 * Open the app and wait for the shell AND the shared navigation to be present.
 *
 * Waiting for the nav specifically is load-bearing: every assertion below is
 * about which entries the nav holds, and a nav that never rendered holds none
 * of them.
 *
 * @param page The page.
 */
async function openApp(page: Page): Promise<void> {
	await page.goto(`${APP_BASE}/`, { waitUntil: 'domcontentloaded' })
	await expect(page.locator('.workspace-shell')).toBeVisible({ timeout: 30_000 })
	await expect(page.locator('[data-testid="cn-nav"]')).toBeVisible({
		timeout: 30_000,
	})
}

/**
 * The manifest routes the navigation currently holds, read from
 * `CnAppNav`'s own `data-cn-route` attribute.
 *
 * ⚠️ BY ATTRIBUTE, NOT BY LABEL OR BY LINK, and both alternatives were tried
 * and rejected against the running instance:
 *
 *  - By anchor. `CnAppNav.itemTo` builds `{ name: item.route }`, so an entry
 *    whose route name vue-router cannot resolve renders a nav item with NO
 *    `<a>` at all. Counting anchors therefore reports zero for an entry that
 *    is on screen, which is the exact false pass this test exists to prevent.
 *  - By label. The settings foldout also contains the LIBRARY's own
 *    auto-injected "Admin settings" link to `/settings/admin/launchpad`
 *    (`CnAppNav.showAdminSettingsLink`), which is gated separately on
 *    `isAdmin` and is not a manifest entry. Matching the text "Admin
 *    settings" cannot tell the two apart, so a passing run would prove the
 *    library's gate and say nothing about this app's.
 *
 * `data-cn-route` is rendered on the nav item itself and carries the manifest
 * `route` verbatim, so it identifies exactly the entries under test.
 *
 * @param page The page.
 * @return The manifest route names present in the nav.
 */
async function navRoutes(page: Page): Promise<string[]> {
	return page
		.locator('[data-testid="cn-nav"] [data-cn-route]')
		.evaluateAll((nodes) =>
			nodes.map((n) => n.getAttribute('data-cn-route') ?? ''),
		)
}

/**
 * The manifest menu-entry IDS the navigation currently holds.
 *
 * `CnAppNav` renders `data-testid="cn-nav-entry-${item.id}"` on every
 * non-caption entry. Unlike `data-cn-route` this does not move when a route is
 * re-pointed, which is what makes it the right key for asking whether an entry
 * is on screen at all. See the note on ADMIN_PAGES for what keying the
 * question on the route instead cost.
 *
 * @param page The page.
 * @return The manifest menu entry ids present in the nav.
 */
async function navEntryIds(page: Page): Promise<string[]> {
	return page
		.locator('[data-testid="cn-nav"] [data-testid^="cn-nav-entry-"]')
		.evaluateAll((nodes) =>
			nodes.map((n) =>
				(n.getAttribute('data-testid') ?? '').replace(/^cn-nav-entry-/, ''),
			),
		)
}

test.describe('manifest permission: the admin surfaces', () => {
	// Three of these four tests provision a brand-new account and log it in
	// cold. That is a first login for that user: Nextcloud builds the home
	// folder, copies the skeleton, and — on any instance where `seed.sh` has
	// not disabled it — runs the first-run wizard. Measured on the shared dev
	// box, that path alone took 59s, so the config's 60s budget expired
	// during LOGIN and all three reported a timeout instead of an assertion.
	//
	// A timeout is the worst possible outcome for a guard test: it is red
	// whether the guard works or not, so it can neither confirm nor deny.
	// The budget is raised rather than the waits shortened — nothing here is
	// asserted more weakly, it just gets far enough to assert at all.
	test.describe.configure({ timeout: 180_000 })

	let account: { username: string; password: string }

	test.beforeAll(async () => {
		// A freshly provisioned account holds no group membership at all, so
		// it cannot have inherited admin from a reused instance — which is how
		// a "non-admin" fixture quietly becomes an admin over time.
		account = await provisionThrowawayUser('e2e-perm')
	})

	test.afterAll(async () => {
		if (account) {
			await deprovisionUser(account.username)
		}
	})

	test('hides both admin menu entries from an account that is not an admin', async ({
		browser,
	}) => {
		const { context, page } = await loginAs(
			browser,
			account.username,
			account.password,
		)
		try {
			await openApp(page)

			// FIRST, and before anything that could pass for the wrong reason.
			const who = await whoami(page)
			expect(who.uid, 'the session under test is the throwaway account').toBe(
				account.username,
			)
			expect(who.isAdmin, 'the account under test is not an admin').toBe(false)

			const entryIds = await navEntryIds(page)

			// The positive control, asserted BEFORE the absence. Without it a
			// nav that never built satisfies the next assertion perfectly.
			expect(
				entryIds,
				'the navigation rendered its ungated entries',
			).toContain(UNGATED_ENTRY_ID)

			for (const { id } of ADMIN_PAGES) {
				expect(
					entryIds,
					`admin-gated menu entry "${id}" rendered for a non-admin`,
				).not.toContain(id)
			}
		} finally {
			await context.close()
		}
	})

	for (const { path: route } of ADMIN_PAGES) {
		test(`redirects a non-admin off ${route}`, async ({ browser }) => {
			const { context, page } = await loginAs(
				browser,
				account.username,
				account.password,
			)
			try {
				await page.goto(`${APP_BASE}${route}`, {
					waitUntil: 'domcontentloaded',
				})
				await expect(page.locator('.workspace-shell')).toBeVisible({
					timeout: 30_000,
				})

				const who = await whoami(page)
				expect(who.uid).toBe(account.username)
				expect(who.isAdmin, 'the account under test is not an admin').toBe(
					false,
				)

				// The destination, not only the absence of the page. "Not on the
				// admin route" is also true of a page that failed to load.
				await expect(page).toHaveURL(new RegExp(`${APP_BASE}/?$`))
				await expect(
					page.locator('[data-testid="AdminSettingsRedirect"]'),
					'the admin redirect page rendered for a non-admin',
				).toHaveCount(0)
			} finally {
				await context.close()
			}
		})
	}

	test('still shows both admin entries, and serves both routes, to an admin', async ({
		page,
	}) => {
		// This context is the config's, not one `loginAs` built, so the bundle
		// override has to be installed here too — and before the first
		// navigation. Without `LAUNCHPAD_BUNDLE_DIR` it does nothing.
		await installBundleOverride(page.context())

		// The other half of the gate. Without this, hiding the entries from
		// EVERYONE — including admins — also passes the tests above, and that
		// is a regression rather than a fix.
		await openApp(page)

		const who = await whoami(page)
		expect(who.isAdmin, 'the default e2e session is the admin').toBe(true)

		const entryIds = await navEntryIds(page)
		for (const { id } of ADMIN_PAGES) {
			expect(
				entryIds,
				`admin-gated menu entry "${id}" is missing for an admin`,
			).toContain(id)
		}

		// And the entries point where they are supposed to. This is the one
		// question that really is about the route, so it reads `data-cn-route`
		// rather than the id: an entry can be present and still declare a
		// route name vue-router cannot resolve, which is what both of these
		// used to do.
		const routes = await navRoutes(page)
		for (const { route } of ADMIN_PAGES) {
			expect(
				routes,
				`admin-gated menu entry "${route}" declares no resolvable route`,
			).toContain(route)
		}

		// The entry must also WORK, not merely render. Both entries used to
		// declare a URL path where CnAppNav expects a route NAME, so
		// vue-router could not resolve either one and both rendered as nav
		// items with no anchor — visible, inert, and for everybody.
		for (const { path: routePath, route } of ADMIN_PAGES) {
			await expect(
				page.locator(`[data-cn-route="${route}"] a, a[href$="${routePath}"]`).first(),
				`admin-gated menu entry "${route}" renders no working link`,
			).toBeAttached()
		}
	})
})
