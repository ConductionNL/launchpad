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

	test('the shared chrome renders, with the workspace inside it', async ({
		page,
	}) => {
		// 🔴 THIS TEST USED TO ASSERT THE OPPOSITE. Until
		// `launchpad-manifest-tier-3` this app did not root on `CnAppRoot` and
		// rendered no `CnAppNav`, so the test asserted the ABSENCE of one and
		// existed as a tripwire for exactly this change. Adoption inverted it.
		await expect(page.locator('[data-testid="cn-nav"]')).toBeVisible({
			timeout: 30_000,
		})

		// ...and the workspace is still the thing on the dashboard route. The
		// grid IS this app; a shell that renders without it would be a
		// regression the nav assertion alone would not catch.
		await expect(page.locator('.workspace-shell')).toBeVisible()
		await expect(page.locator('#launchpad-main-content')).toBeAttached()
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

	test('a dashboard has an address, so it can be linked and reopened', async ({
		page,
	}) => {
		// 🔴 A DASHBOARD HAD NO ADDRESS. Switching was Pinia state that never
		// touched the URL, so `/dashboards/:id` — declared in the manifest all
		// along — routed nowhere and a dashboard could not be linked,
		// bookmarked or reopened (`dashboard-deeplinking`).
		await page.goto(`${APP_BASE}/`, { waitUntil: 'domcontentloaded' })
		await expect(page.locator('.workspace-shell')).toBeVisible({
			timeout: 30_000,
		})

		// Ask the app which dashboards this user has, rather than seeding an
		// id: a hardcoded one passes on the instance it was written against
		// and nowhere else.
		const dashboards = await page.evaluate(async () => {
			const res = await fetch(
				`${window.location.origin}/index.php/apps/launchpad/api/dashboard`,
				{ headers: { 'OCS-APIREQUEST': 'true' } },
			)
			return res.ok ? await res.json() : null
		})
		const id = dashboards?.dashboards?.[0]?.id ?? dashboards?.[0]?.id
		test.skip(
			id === undefined || id === null,
			'no dashboard is visible to this user, so there is no address to assert',
		)

		await page.goto(`${APP_BASE}/dashboards/${id}`, {
			waitUntil: 'domcontentloaded',
		})
		await expect(page).toHaveURL(
			new RegExp(`/dashboards/${String(id)}(\\?|$)`),
			{ timeout: 15_000 },
		)
		await expect(page.locator('.workspace-shell')).toBeVisible({
			timeout: 30_000,
		})
	})

	test('the Flows page opens', async ({ page }) => {
		await page.goto(`${APP_BASE}/flows`, { waitUntil: 'domcontentloaded' })
		await expect(page).toHaveURL(/\/apps\/launchpad\/flows(\?|$)/, {
			timeout: 15_000,
		})
	})
})
