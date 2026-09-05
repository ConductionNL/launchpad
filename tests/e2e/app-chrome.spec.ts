/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The bottom-left app chrome, in a browser (ADR-114).
 *
 * gate-107 reads the manifest and can prove the entries are DECLARED. It
 * cannot prove they RENDER, and this programme has already produced three
 * defects of exactly that shape: an icon name that is not registered renders
 * NO glyph (not a fallback, not a console error — this app shipped one), an
 * entry whose `route` names a page the app does not host renders a row that
 * goes nowhere, and `nav.includePersonalSettings: false` silently removed the
 * entry that reaches the user's notification preferences.
 *
 * The report is a declarative `type: "dashboard"` page over LaunchPad's own
 * register, which adds a fourth failure mode no manifest gate can see: a widget
 * whose `source` names a schema or field that does not match renders its card,
 * its title and no value, silently. So the assertion below looks for a VALUE,
 * not just for the card.
 *
 * 🔴 LAUNCHPAD DOES NOT RENDER `CnAppNav`, AND THAT IS DELIBERATE. It does not
 * root on `CnAppRoot`/`NcContent`; `App.vue` says so and writes its own
 * `.workspace-shell` (org navigation rail, slide-in sidebar, branded
 * DashboardFooter) with its own skip link. So there is no
 * `[data-testid="cn-nav"]` and no `.cn-app-nav__footer-list` on any page.
 *
 * These five tests asserted both, from the commit that gave this app a Store
 * (2026-09-04) onward, and the E2E leg has been red on every push since: a
 * `beforeEach` waiting 30 s for a nav that cannot appear, reported as five
 * broken features.
 *
 * What the chrome IS here: four destinations the manifest declares in its
 * `footer` section — Documentation (an external href), Store, Reports and
 * Features & roadmap — each of which must resolve to a page this app hosts.
 * That is what these tests check now: the shell it renders, and the
 * destinations it declares, by route rather than by a nav entry that does not
 * exist. A gate can prove the entries are DECLARED; only a browser can prove
 * the destinations RENDER, which is what the failure modes above are about.
 *
 * ⚠️ IF LAUNCHPAD EVER ADOPTS `CnAppRoot`, the first test below fails on
 * purpose: it asserts the absence, so the adoption cannot land silently while
 * these tests keep passing against a shell that is no longer there.
 */

import type { Page } from '@playwright/test'

import { expect, test } from '@playwright/test'

const APP_BASE = '/apps/launchpad'

/**
 * Dismiss the first-run setup wizard if it is open.
 *
 * ⚠️ On a FRESH instance CnSetupWizard opens over the app and its modal
 * intercepts pointer events, so every nav click resolves its locator and then
 * times out after 30s — a failure that reads like the navigation is broken.
 * Tests that navigate by URL pass, which is what makes this so easy to miss:
 * only the click-through tests fail, and only on a clean install.
 *
 * @param page The page.
 */
async function dismissSetupWizard(page: Page): Promise<void> {
	const modal = page.locator('[data-testid="cn-modal"]')
	if ((await modal.count()) === 0) {
		return
	}
	await modal.first().getByRole('button', { name: 'Close' }).click()
	await expect(modal).toHaveCount(0, { timeout: 15_000 })
}

test.describe('app chrome (ADR-114)', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto(`${APP_BASE}/`, { waitUntil: 'domcontentloaded' })
		await expect(page.locator('.workspace-shell')).toBeVisible({
			timeout: 30_000,
		})
		await dismissSetupWizard(page)
	})

	test("the shell is LaunchPad's own, not the shared CnAppNav one", async ({
		page,
	}) => {
		// The premise every other test here rests on, asserted rather than
		// assumed. It is also the tripwire: an app that quietly starts rooting
		// on CnAppRoot fails HERE, where the reason is written down, instead of
		// leaving four tests passing against chrome that moved.
		await expect(page.locator('.workspace-shell')).toBeVisible()
		await expect(page.locator('#launchpad-main-content')).toBeAttached()
		await expect(
			page.locator('[data-testid="cn-nav"]'),
			'LaunchPad now renders CnAppNav — the chrome tests below should assert it instead of its own shell',
		).toHaveCount(0)
	})

	test('the chrome declares Documentation, Store, Reports and Features & roadmap', async () => {
		// The manifest is read here rather than restated, so a renamed or
		// dropped entry is a failure instead of a silently stale literal.

		const manifest = JSON.parse(
			// eslint-disable-next-line @typescript-eslint/no-require-imports
			require('fs').readFileSync(
				// eslint-disable-next-line @typescript-eslint/no-require-imports
				require('path').resolve(__dirname, '../../src/manifest.json'),
				'utf-8',
			),
		)
		const footer = (manifest.menu ?? [])
			.filter((e: any) => e.section === 'footer')
			.sort((a: any, b: any) => (a.order ?? 0) - (b.order ?? 0))

		expect(
			footer.map((e: any) => e.label),
			'ADR-114 declares four footer destinations, in this order',
		).toEqual(['Documentation', 'Store', 'Reports', 'Features & roadmap'])

		// A GLYPH ON EVERY ONE. An icon name that is not registered renders no
		// glyph — not a fallback, not a console error; this app shipped one.
		for (const entry of footer) {
			expect(entry.icon, `${entry.label} declares no icon`).toBeTruthy()
		}

		// Documentation leaves the app, so it is an href and there is nothing
		// here to render. The other three name a page this app must host.
		expect(footer[0].href, 'Documentation must be an external href').toMatch(
			/^https:\/\//,
		)

		const pages = new Map(
			(manifest.pages ?? []).map((p: any) => [p.id, p.route]),
		)
		for (const entry of footer.slice(1)) {
			expect(
				pages.get(entry.route),
				`${entry.label} names page "${entry.route}", which this app does not host`,
			).toBeTruthy()
		}
	})

	test('each declared chrome destination opens', async ({ page }) => {
		// 🔴 EXPECTED TO FAIL, AND FILED. LaunchPad declares nine pages and
		// serves ONE: `/store`, `/reports` and `/flows` each redirect to
		// `/dashboard`. It has no vue-router at all — `createRouter` appears
		// nowhere in src/ — because navigation is Pinia state that never
		// touches the URL. `main.js` calls this Tier 1 and names the change
		// that fixes it; that change is now filed at
		// `openspec/changes/launchpad-manifest-tier-3/`.
		//
		// `test.fail()` rather than a skip, deliberately: a skipped test proves
		// nothing and quietly stops being read, while this one FAILS THE RUN
		// the moment routing lands. That is the notification we want — the
		// marker comes off in the same change that makes it pass.
		test.fail()

		const manifest = JSON.parse(
			// eslint-disable-next-line @typescript-eslint/no-require-imports
			require('fs').readFileSync(
				// eslint-disable-next-line @typescript-eslint/no-require-imports
				require('path').resolve(__dirname, '../../src/manifest.json'),
				'utf-8',
			),
		)
		const footer = (manifest.menu ?? [])
			.filter((e: any) => e.section === 'footer' && e.route)
			.sort((a: any, b: any) => (a.order ?? 0) - (b.order ?? 0))
		const pages = new Map(
			(manifest.pages ?? []).map((p: any) => [p.id, p.route]),
		)

		for (const entry of footer) {
			const route = pages.get(entry.route)
			await page.goto(`${APP_BASE}${route}`, {
				waitUntil: 'domcontentloaded',
			})
			await expect(page).toHaveURL(new RegExp(`${route}(\\?|$)`), {
				timeout: 15_000,
			})
		}
	})

	test('Reports lists the one report this app can honestly offer', async ({
		page,
	}) => {
		// 🔴 EXPECTED TO FAIL UNTIL ROUTING LANDS — see
		// `openspec/changes/launchpad-manifest-tier-3/`. This app serves one
		// page; the route below redirects to `/dashboard`. `test.fail()` rather
		// than a skip, so the run goes red the moment it starts passing.
		test.fail()

		// One card, deliberately. LaunchPad's register holds a single schema —
		// dashboard — so a second report would either repeat this one or invent
		// a reading the data cannot support. If a schema is added later and no
		// report follows, this count is what notices.
		await page.goto(`${APP_BASE}/reports`, { waitUntil: 'domcontentloaded' })
		await expect(page).toHaveURL(/\/apps\/launchpad\/reports(\?|$)/, {
			timeout: 15_000,
		})
		await expect(
			page
				.locator('main, .app-content')
				.first()
				.getByText('Dashboards', { exact: false })
				.first(),
		).toBeVisible({ timeout: 15_000 })
	})

	test('the dashboards report renders real numbers, not empty cards', async ({
		page,
	}) => {
		// 🔴 EXPECTED TO FAIL UNTIL ROUTING LANDS — see
		// `openspec/changes/launchpad-manifest-tier-3/`. This app serves one
		// page; the route below redirects to `/dashboard`. `test.fail()` rather
		// than a skip, so the run goes red the moment it starts passing.
		test.fail()

		await page.goto(`${APP_BASE}/reports/dashboards`)
		await expect(page.locator('.workspace-shell')).toBeVisible({
			timeout: 30_000,
		})
		await expect(
			page.getByText('Admin templates', { exact: false }).first(),
		).toBeVisible({ timeout: 30_000 })
		await expect(page.locator('main, .app-content').first()).toContainText(
			/\d/,
			{ timeout: 30_000 },
		)
	})

	test('Store opens the hosted store surface, which this app writes no backend for', async ({
		page,
	}) => {
		// 🔴 EXPECTED TO FAIL UNTIL ROUTING LANDS — see
		// `openspec/changes/launchpad-manifest-tier-3/`. This app serves one
		// page; the route below redirects to `/dashboard`. `test.fail()` rather
		// than a skip, so the run goes red the moment it starts passing.
		test.fail()

		await page.goto(`${APP_BASE}/store`, { waitUntil: 'domcontentloaded' })

		await expect(page).toHaveURL(/\/apps\/launchpad\/store(\?|$)/, {
			timeout: 15_000,
		})

		// The page is declarative: openregister hosts the store plane, so this
		// app ships NO store controller (ADR-080, ADR-114 Decision 4). With no
		// registry configured it renders the app's own items and makes NO
		// network call, so this must pass on a plain instance.
		await expect(page.locator('.workspace-shell')).toBeVisible({
			timeout: 30_000,
		})
	})

	test('the admin settings section renders', async ({ page }) => {
		// ⚠️ NOT A FOLDOUT TEST ANY MORE, and it cannot be. The settings
		// foldout is `CnAppNav`'s, and this app renders no CnAppNav — the
		// personal-settings entry in particular is a nav widget with no
		// equivalent in `.workspace-shell`, so there is nothing here to assert
		// about it. What survives is the half that is about LaunchPad rather
		// than about the nav component, and it is a Nextcloud settings route
		// rather than one of this app's own, which is why it still passes.
		await page.goto('/settings/admin/launchpad', {
			waitUntil: 'domcontentloaded',
		})
		await expect(
			page.locator('#app-content, main').first(),
			'the admin settings section did not render',
		).toBeVisible({ timeout: 30_000 })
	})

	test('the Flows page opens', async ({ page }) => {
		// 🔴 EXPECTED TO FAIL UNTIL ROUTING LANDS — see
		// `openspec/changes/launchpad-manifest-tier-3/`. This app serves one
		// page; the route below redirects to `/dashboard`. `test.fail()` rather
		// than a skip, so the run goes red the moment it starts passing.
		test.fail()

		await page.goto(`${APP_BASE}/flows`, { waitUntil: 'domcontentloaded' })
		await expect(page).toHaveURL(/\/apps\/launchpad\/flows(\?|$)/, {
			timeout: 15_000,
		})
	})
})
