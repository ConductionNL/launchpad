/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright globalSetup — logs into Nextcloud once and persists the
 * resulting cookie jar / localStorage to `tests/e2e/.auth/admin.json`.
 * Every spec then reuses that storage state via the `use.storageState`
 * setting in playwright.config.ts, so individual tests start from an
 * authenticated session without each one paying the login cost.
 *
 * Why a real browser login (instead of POSTing to /login directly):
 * Nextcloud's login form ships a CSRF token (`requesttoken`) plus a
 * `oc_session_passphrase` cookie that must be set in the same browser
 * context. Driving the form via Playwright sidesteps having to
 * reverse-engineer the token-rotation contract, which has shifted across
 * NC 28 / 29 / 30.
 */

import { chromium, request, type FullConfig } from '@playwright/test'
import { execSync } from 'child_process'
import * as path from 'path'
import * as fs from 'fs'
import { BASE_URL } from './support/baseUrl'

const AUTH_DIR = path.resolve(__dirname, '.auth')
const STORAGE_STATE = path.join(AUTH_DIR, 'admin.json')
const APP_ROOT = path.resolve(__dirname, '..', '..')
const BUNDLE_PATH = path.join(APP_ROOT, 'js', 'launchpad-main.js')

/**
 * Ensure the webpack bundle exists before specs hit `/apps/launchpad/`.
 *
 * The shared `ConductionNL/.github/quality.yml` Playwright job runs
 * `npm ci` + `npx playwright install` before the spec run, but never
 * `npm run build`. On a fresh CI VM the `js/launchpad-main.js` artefact
 * doesn't exist, so the rendered page loads a 404 script tag and the
 * Vue app never mounts — every selector wait then times out.
 *
 * This helper detects the missing bundle and invokes the build once
 * before specs run. Local dev usually has the bundle present (latest
 * webpack output is cached on disk) so the build is a no-op there.
 *
 * Skipping the build entirely on CI would require a cross-repo PR to
 * `ConductionNL/.github` adding a `npm run build` step to the shared
 * workflow; doing it here keeps the fix self-contained.
 */
function ensureBundleBuilt(): void {
	if (fs.existsSync(BUNDLE_PATH)) {
		return
	}
	// eslint-disable-next-line no-console
	console.log(
		`[playwright globalSetup] bundle missing at ${BUNDLE_PATH}; running 'npm run build' once…`,
	)
	execSync('npm run build', { cwd: APP_ROOT, stdio: 'inherit' })
}

async function ensureNextcloudReachable(baseURL: string): Promise<void> {
	const ctx = await request.newContext()
	try {
		const res = await ctx.get(`${baseURL}/status.php`, {
			failOnStatusCode: false,
		})
		if (!res.ok()) {
			throw new Error(
				`Nextcloud status.php returned ${res.status()} at ${baseURL}. `
					+ `Make sure the docker container is running and reachable.`,
			)
		}
		const body = await res.json().catch(() => ({}))
		if (!body || body.installed !== true) {
			throw new Error(
				`Nextcloud at ${baseURL} is not installed (status.php = ${JSON.stringify(body)}).`,
			)
		}
	} finally {
		await ctx.dispose()
	}
}

export default async function globalSetup(config: FullConfig): Promise<void> {
	// `config.projects[0].use.baseURL` is itself resolved from support/baseUrl,
	// so the `??` is only a guard against a project that overrides `use` — it
	// can no longer fall through to a hardcoded shared-container default.
	const baseURL =
		(config.projects[0]?.use?.baseURL as string | undefined) ?? BASE_URL
	const username = process.env.NC_ADMIN_USER ?? 'admin'
	const password = process.env.NC_ADMIN_PASS ?? 'admin'

	ensureBundleBuilt()
	await ensureNextcloudReachable(baseURL)
	fs.mkdirSync(AUTH_DIR, { recursive: true })

	const browser = await chromium.launch()
	const context = await browser.newContext({ baseURL })
	const page = await context.newPage()

	// Hit the login form so the CSRF token + session passphrase land in the
	// browser jar.
	await page.goto('/index.php/login')
	await page.locator('input[name="user"]').fill(username)
	await page.locator('input[name="password"]').fill(password)
	await page.locator('button[type="submit"]').first().click()
	// Nextcloud bounces to /apps/dashboard/ (or another default app) on
	// success. Wait for the global header that only renders on
	// authenticated pages — the URL-based wait races with the in-flight
	// click navigation and is unreliable on slower test rigs.
	await page.waitForSelector('#header, header.header', { timeout: 45_000 })
	// Catch wrong-credentials early so the failure message is clear.
	const currentUrl = page.url()
	if (/\/login(\?|$|\/)/.test(currentUrl)) {
		throw new Error(
			`Login appears to have failed — still on ${currentUrl}. `
				+ `Check NC_ADMIN_USER / NC_ADMIN_PASS (defaults admin/admin).`,
		)
	}

	await dismissFirstRunWizard(page)

	// Persist the storage state so individual specs reuse the session.
	/*
	 * Suppress the product walkthrough (ADR-043) for automated runs, the way
	 * dossiq's global-setup already does.
	 *
	 * This became load-bearing with @conduction/nextcloud-vue 2.22.x. A
	 * `placement: "center"` welcome step used to be parked in `_pendingAutoTour`
	 * and never opened; the library now correctly starts it on any route, so the
	 * tour actually appears — and its `cn-walkthrough__dim--full` layer is a
	 * `role="dialog" aria-modal="true"` overlay that intercepts every click
	 * behind it. Specs that had never had to account for a tour started timing
	 * out, and `getByRole('dialog').first()` began resolving to the dim layer
	 * instead of the modal under test.
	 *
	 * The marker is per USER, not per test, so without it the suite is also
	 * order-dependent: whichever spec runs first wears the tour and the rest
	 * inherit a dismissed one.
	 *
	 * The sentinel is higher than any real app version, so every step's
	 * `sinceVersion` sorts below it and the tour composes to an empty step set
	 * rather than merely starting dismissed. The page is already on the instance
	 * origin after login, which is the origin storageState persists.
	 */
	try {
		await page.evaluate(() => {
			try {
				window.localStorage.setItem(
					'cn-walkthrough-seen:launchpad',
					'999.0.0',
				)
			} catch (e) {
				// localStorage unavailable — specs fall back to dismissing by hand.
			}
		})
	} catch {
		// Never fail setup over an optional convenience.
	}

	await context.storageState({ path: STORAGE_STATE })
	await browser.close()
}

/**
 * Dismiss Nextcloud's first-run wizard for the test user.
 *
 * WHY THIS IS NOT OPTIONAL
 * ------------------------
 * `firstrunwizard` ships enabled by default. On a FRESH instance — which is
 * exactly what CI provisions and what a disposable local rig is — it renders a
 * full-screen modal ("A collaboration platform that puts you in control") over
 * the app on first load.
 *
 * Measured 2026-08-19 against a clean rig: 7 of 9 `tile-quick-search` specs
 * failed with it up. It does not merely obscure the page, it EATS KEYSTROKES —
 * Escape closes the wizard instead of clearing the search bar, so
 * "Escape clears the query" failed with the query still present. Any spec that
 * types, clicks the grid, or asserts focus is affected, and the failure never
 * names the modal, so it reads as a broken feature.
 *
 * It survives a fresh browser context because the dismissal is a SERVER-SIDE
 * user preference, not localStorage — which is also why one call here fixes
 * every spec rather than needing per-test handling.
 *
 * `DELETE /apps/firstrunwizard/wizard` is the app's own dismissal route. It is
 * best-effort: an instance with the app disabled returns 404, which is fine.
 *
 * @param {import('@playwright/test').Page} page an authenticated page.
 * @return {Promise<void>}
 */
async function dismissFirstRunWizard(
	page: import('@playwright/test').Page,
): Promise<void> {
	try {
		const status = await page.evaluate(async () => {
			const token =
				document
					.querySelector('head[data-requesttoken]')
					?.getAttribute('data-requesttoken')
				?? document.getElementById('requesttoken')?.getAttribute('value')
				?? ''
			const res = await fetch('/index.php/apps/firstrunwizard/wizard', {
				method: 'DELETE',
				headers: { requesttoken: token, 'OCS-APIRequest': 'true' },
			})
			return res.status
		})
		if (status >= 400 && status !== 404) {
			// eslint-disable-next-line no-console
			console.warn(
				`[playwright globalSetup] first-run wizard dismissal returned ${status}; `
					+ 'specs may hit a modal over the app.',
			)
		}
	} catch (error) {
		// eslint-disable-next-line no-console
		console.warn(
			'[playwright globalSetup] could not dismiss the first-run wizard:',
			error,
		)
	}
}
