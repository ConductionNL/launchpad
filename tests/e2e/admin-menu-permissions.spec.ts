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
 * Gate-19 traceability: this file cites no scenario. It used to carry three
 * runtime-shell slugs (admin-menu-entries-are-hidden-from-non-admins,
 * admin-routes-redirect-non-admins, admin-menu-entries-render-for-admins)
 * that match no heading in openspec/specs/runtime-shell/spec.md, or in any
 * other spec, at any point in its history: the rule came in with #587
 * without a scenario. The tests stay, because they are the only run of the
 * real permission filter; the rule needs a scenario of its own (a spec
 * change) before this file can cite it.
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
import { openSettingsGroup } from './support/dashboardSwitcher.ts'

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
/*
 * `menuId` and `href` are what the More menu renders for the surface. More
 * offers the administration page once, as the Settings group's
 * `admin-settings-link` to `/apps/launchpad/admin/general`, and not the
 * manifest entry beside it.
 */
const ADMIN_PAGES = [
	{
		id: 'admin-templates',
		menuId: 'admin-templates',
		path: '/admin/templates',
		href: '/admin/templates',
		route: 'admin-templates-index',
	},
	{
		id: 'admin-settings',
		menuId: 'admin-settings-link',
		path: '/admin/general',
		href: '/apps/launchpad/admin/general',
		route: 'admin-settings',
	},
] as const

/** A menu entry every account holds, as the positive control. */
const UNGATED_ENTRY_ID = 'StoreMenu'

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
 * Open the app on a page other than the dashboard view and open the More
 * menu there, which holds every entry the navigation panel used to hold
 * (runtime-shell REQ-SHELL-009: LaunchPad renders no panel).
 *
 * Waiting for the menu items specifically is load-bearing: every assertion
 * below is about which entries the menu holds, and a menu that never opened
 * holds none of them.
 *
 * @param page The page.
 */
async function openApp(page: Page): Promise<void> {
	// 60s, not 30s. A cold boot of this app on a loaded instance went past 30s
	// and turned a run that had already passed on the same bundle red. A guard
	// test whose setup is marginal reports the environment, not the guard.
	await page.goto(`${APP_BASE}/store`, { waitUntil: 'domcontentloaded' })
	const holder = page.locator('[data-testid="launchpad-page-menu"]')
	await expect(holder).toBeVisible({ timeout: 60_000 })
	await holder.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await openSettingsGroup(page)
	await expect(
		page.locator(`[data-testid="launchpad-more-menu-item-${UNGATED_ENTRY_ID}"]`),
	).toBeVisible({ timeout: 15_000 })
}

/**
 * The manifest menu-entry IDS the More menu currently holds.
 *
 * The More menu renders `data-testid="launchpad-more-menu-item-${item.id}"`
 * on every entry, keyed on the manifest id, which does not move when a route
 * is re-pointed. See the note on ADMIN_PAGES for what keying the question on
 * the route instead cost.
 *
 * @param page The page, with the More menu open.
 * @return The manifest menu entry ids present in the menu.
 */
async function navEntryIds(page: Page): Promise<string[]> {
	return page
		.locator('[data-testid^="launchpad-more-menu-item-"]')
		.evaluateAll((nodes) =>
			nodes.map((n) =>
				(n.getAttribute('data-testid') ?? '').replace(
					/^launchpad-more-menu-item-/,
					'',
				),
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

			for (const { menuId } of ADMIN_PAGES) {
				expect(
					entryIds,
					`admin-gated menu entry "${menuId}" rendered for a non-admin`,
				).not.toContain(menuId)
			}
		} finally {
			await context.close()
		}
	})

	for (const { path: route } of ADMIN_PAGES) {
		test(`refuses a non-admin ${route} on the server`, async ({ browser }) => {
			const { context, page } = await loginAs(
				browser,
				account.username,
				account.password,
			)
			try {
				// IDENTITY FIRST, AND ON A PAGE THAT CAN ANSWER IT: Nextcloud's
				// 403 page loads no `OC` bundle, so `whoami` there reads null.
				await openApp(page)

				const who = await whoami(page)
				expect(
					who.uid,
					'the session under test is the throwaway account',
				).toBe(account.username)
				expect(who.isAdmin, 'the account under test is not an admin').toBe(
					false,
				)

				// The administration is a page of this app
				// (`/apps/launchpad/admin/<tab>`, admin-settings REQ-ADM-030),
				// and the server checks the reader before it renders: a
				// non-admin gets 403 and nothing of the page.
				const response = await page.goto(`${APP_BASE}${route}`, {
					waitUntil: 'domcontentloaded',
				})
				expect(
					response?.status(),
					'the server served the administration to a non-admin',
				).toBe(403)
				await expect(
					page.locator('[data-testid="AdminPage"]'),
					'the administration page rendered for a non-admin',
				).toHaveCount(0)
				await expect(
					page.locator('[data-testid="cn-admin-settings-tabs"]'),
					'the administration tabs rendered for a non-admin',
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
		for (const { menuId } of ADMIN_PAGES) {
			expect(
				entryIds,
				`admin-gated menu entry "${menuId}" is missing for an admin`,
			).toContain(menuId)
		}

		// The entry must also WORK, not merely render. Both entries used to
		// declare a URL path where a route NAME was expected, so vue-router
		// could not resolve either one and both rendered with no anchor:
		// visible, inert, and for everybody.
		for (const { menuId: id, href: routePath } of ADMIN_PAGES) {
			await expect(
				page
					.locator(`[data-testid="launchpad-more-menu-item-${id}"]`)
					.locator(`xpath=descendant-or-self::a[contains(@href, "${routePath}")]`)
					.first(),
				`admin-gated menu entry "${id}" renders no working link`,
			).toBeAttached()
		}
	})
})
