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

import type { Page } from '@playwright/test'
import type { FullConfig } from '@playwright/test'

import { chromium, request } from '@playwright/test'
import { execSync } from 'child_process'
import * as fs from 'fs'
import * as path from 'path'
import { healLeftoverWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { BASE_URL } from './support/baseUrl.ts'

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
	await disableFirstRunWizardForRun(baseURL, username, password)
	await liftCalendarCreationLimitForRun(baseURL, username, password)
	await allowUserDashboardsForRun(baseURL, username, password)
	await markSupportNoteSeen(page)
	await settleSetupWizard(page)
	await completeAdminSetupWizard(page)
	if (await healLeftoverWidgetRestriction()) {
		console.warn('[global-setup] put back the shipped default widget rule over a leftover e2e restriction')
	}

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
				// The admin settings page has its own tour under its own key.
				window.localStorage.setItem(
					'cn-walkthrough-seen:launchpad:admin-tour',
					'999.0.0',
				)
			} catch {
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
/**
 * Record the first-open support note as seen, for the whole run.
 *
 * 🔴 IT MASKS EVERY CLICK UNTIL SOMETHING DISMISSES IT. `CnAppRoot` mounts
 * `CnSupportDialog` on first open by default, and like every nc-vue dialog it
 * renders a `modal-mask` whose subtree intercepts pointer events:
 *
 *     <div data-testid-modal="cn-support-dialog" class="dialog__modal modal-mask">
 *     subtree intercepts pointer events
 *
 * This app rooted on `CnAppRoot` in `launchpad-manifest-tier-3`, and the first
 * run after that adoption came back 62 failed / 85 passed — every one of the 62
 * a click that never became actionable, across a dozen unrelated spec files.
 * The chrome spec was among the survivors only because it happens to dismiss
 * `[data-testid="cn-modal"]` in its own `beforeEach`.
 *
 * Dismissal persists per USER through the app's preferences endpoint, so
 * setting it once here settles it for every spec in the run — the same shape as
 * `dismissFirstRunWizard` above. Best-effort: a non-2xx is warned about, not
 * fatal, because a run against an instance without the route should still tell
 * you what it found rather than refusing to start.
 *
 * ⚠️ NOT `:support-dialog="false"` in the app. That would take a real feature
 * away from real users to make the tests quiet.
 *
 * @param {import('@playwright/test').Page} page an authenticated page.
 * @return {Promise<void>}
 */
/**
 * Record the setup wizard's outstanding choice, so it does not open over the app.
 *
 * ⚠️ COMPLETED IS NOT THE SAME AS SETTLED, and that distinction is the whole
 * bug. `GET /api/setup/status` answered `"completed": true` while still
 * reporting `steps: { "demo-data": { done: false }, "load-demo-data":
 * { done: false } }`, and `CnAppRoot` opens the wizard while ANY step is
 * outstanding, optional or not (nextcloud-vue#806). So the wizard opened on a
 * setup that called itself complete.
 *
 * It is not a cosmetic overlay. `cn-wizard-dialog` is an `aria-modal` that
 * INTERCEPTS POINTER EVENTS, so every click in the app lands on the dialog and
 * the failure names a button that the same log calls visible, enabled and
 * stable. Measured locally against a clean instance: with the wizard up,
 * dashboard-sharing failed 4 of 4; with it settled, runtime-shell-canEdit and
 * add-widget-modal went to 10 passed, 0 failed.
 *
 * This app had no wizard at all until `launchpad-manifest-tier-3` rooted it on
 * `CnAppRoot`, which is why nothing here had to account for one before.
 *
 * Recording the CHOICE is what settles it, not writing the completion key: the
 * choice is what marks both `demo-data` and its dependent `load-demo-data`
 * done. `none` is the honest answer for a test run, and it imports nothing.
 *
 * @param page A page already authenticated against the instance.
 */
async function settleSetupWizard(page: Page): Promise<void> {
	try {
		const status = await page.evaluate(async () => {
			const token =
				document
					.querySelector('head[data-requesttoken]')
					?.getAttribute('data-requesttoken')
				?? document.getElementById('requesttoken')?.getAttribute('value')
				?? ''
			const res = await fetch('/index.php/apps/launchpad/api/setup/config', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					requesttoken: token,
					'OCS-APIRequest': 'true',
				},
				body: JSON.stringify({ demo_dataset: 'none' }),
			})
			return res.status
		})
		if (status >= 400) {
			console.warn(
				`[playwright globalSetup] setup-wizard settle returned ${status}; `
					+ 'specs will hit a wizard mask over every click.',
			)
		}
	} catch (error) {
		console.warn(
			'[playwright globalSetup] could not settle the setup wizard:',
			error,
		)
	}
}

/**
 * Mark launchpad's own admin setup wizard complete, so it does not open over
 * the admin page.
 *
 * This is a different wizard from the one `settleSetupWizard` settles: the
 * LaunchPad setup wizard of the admin page (ADR-042, `setup_wizard_complete`
 * in `oc_launchpad_admin_settings`). Since the admin page moved into
 * Nextcloud's settings (REQ-ADM-032) it opens by itself on the first visit in
 * a browser session while that flag is false, which is every fresh instance,
 * CI's included. Its `aria-modal` intercepts every click on the tabs, so each
 * spec that opens a tab failed there with "subtree intercepts pointer
 * events" (prometheus-metrics, public-start-page-admin, role-based-content,
 * runtime-shell's admin tour, sharing-policy ... on 27 Nov 2026, NC 34).
 * setup-wizard.spec.ts removes the flag itself before each test that needs
 * a fresh instance, so a completed flag here takes nothing from it.
 *
 * @param page A page already authenticated against the instance.
 */
async function completeAdminSetupWizard(page: Page): Promise<void> {
	try {
		const status = await page.evaluate(async () => {
			const token =
				document
					.querySelector('head[data-requesttoken]')
					?.getAttribute('data-requesttoken')
				?? document.getElementById('requesttoken')?.getAttribute('value')
				?? ''
			const res = await fetch('/index.php/apps/launchpad/api/admin/setup-wizard/complete', {
				method: 'POST',
				headers: { requesttoken: token, 'OCS-APIRequest': 'true' },
			})
			return res.status
		})
		if (status >= 400) {
			console.warn(
				`[playwright globalSetup] admin setup wizard complete returned ${status}; `
					+ 'it will open over the admin page tabs.',
			)
		}
	} catch (error) {
		console.warn('[playwright globalSetup] could not complete the admin setup wizard:', error)
	}
}

async function markSupportNoteSeen(page: Page): Promise<void> {
	try {
		const status = await page.evaluate(async () => {
			const token =
				document
					.querySelector('head[data-requesttoken]')
					?.getAttribute('data-requesttoken')
				?? document.getElementById('requesttoken')?.getAttribute('value')
				?? ''
			const res = await fetch(
				'/index.php/apps/launchpad/api/preferences/support-dialog-seen',
				{
					method: 'PUT',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: token,
						'OCS-APIRequest': 'true',
					},
					body: JSON.stringify({ value: '1' }),
				},
			)
			return res.status
		})
		if (status >= 400) {
			console.warn(
				`[playwright globalSetup] support-note dismissal returned ${status}; `
					+ 'specs will hit a modal mask over every click.',
			)
		}

		// AND the localStorage fallback the composable drops to when the
		// server read fails, so a flaky preferences call cannot put the mask
		// back. `cn-support-dialog-shown:<appId>` is nextcloud-vue's key. The
		// page is already on the instance origin after login, which is the
		// origin storageState persists — the same reason the walkthrough
		// sentinel above is set here rather than per spec.
		await page.evaluate(() => {
			try {
				window.localStorage.setItem('cn-support-dialog-shown:launchpad', '1')
			} catch {
				// A browser with site data blocked. The server preference above
				// is the real mechanism; this is belt and braces.
			}
		})
	} catch (error) {
		console.warn(
			'[playwright globalSetup] could not record the support note as seen:',
			error,
		)
	}
}

/**
 * Switch the first-run wizard off for every account the run creates.
 *
 * {@link dismissFirstRunWizard} only clears it for the admin. Most specs log
 * in as throwaway accounts made mid-run (createPrincipal, loginAs,
 * seeded users), and each of those meets the wizard on its first page load:
 * its modal sits over the More menu, so `launchpad-more-menu-trigger` never
 * becomes clickable and the spec times out without naming the modal. Measured
 * 25 Nov 2026 on a fresh Nextcloud 34: app-catalogue, divider-widget-discovery
 * and add-widget-modal failed this way, and passed with the wizard off.
 *
 * The suite only runs against an instance it may write to (support/baseUrl),
 * so it turns the app off through the provisioning API rather than
 * dismissing it once per account in every helper that makes one.
 * Best-effort: an instance without the app answers non-2xx, which is fine.
 *
 * @param {string} baseURL the instance under test.
 * @param {string} username an admin account.
 * @param {string} password its password.
 * @return {Promise<void>}
 */
async function disableFirstRunWizardForRun(
	baseURL: string,
	username: string,
	password: string,
): Promise<void> {
	try {
		const res = await fetch(new URL('/ocs/v1.php/cloud/apps/firstrunwizard', baseURL), {
			method: 'DELETE',
			headers: {
				Authorization: `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}`,
				'OCS-APIRequest': 'true',
			},
		})
		if (!res.ok && res.status !== 404) {
			console.warn(
				`[playwright globalSetup] turning the first-run wizard off returned ${res.status}; `
					+ 'specs that log in as a new account may hit its modal.',
			)
		}
	} catch (error) {
		console.warn('[playwright globalSetup] could not turn the first-run wizard off:', error)
	}
}

/**
 * Lift Nextcloud's calendar-creation rate limit for the run.
 *
 * The calendar-widget specs each create their own calendar over CalDAV
 * (support/calendarFixture.ts) and delete it afterwards, but the `dav` app
 * allows one user 10 new calendars per hour (`rateLimitCalendarCreation`).
 * The suite creates more than that as admin, so from the eleventh on every
 * MKCALENDAR answers 429 "Too many calendars created" and the calendar specs
 * fail with nothing wrong in LaunchPad. Measured 27 Nov 2026 on a fresh
 * Nextcloud 34: shard 1/8 failed 7 calendar tests this way, and all 21 tests
 * of calendar-widget-config and calendar-widget-rendering passed with the
 * limit raised.
 *
 * The suite only runs against an instance it may write to (support/baseUrl),
 * so it raises the limit through the provisioning API, as it turns the
 * first-run wizard off. Best-effort: a refusal is reported, never fatal.
 *
 * @param {string} baseURL the instance under test.
 * @param {string} username an admin account.
 * @param {string} password its password.
 * @return {Promise<void>}
 */
async function liftCalendarCreationLimitForRun(
	baseURL: string,
	username: string,
	password: string,
): Promise<void> {
	try {
		const res = await fetch(
			new URL('/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/dav/rateLimitCalendarCreation', baseURL),
			{
				method: 'POST',
				headers: {
					Authorization: `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}`,
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: 'value=1000',
			},
		)
		if (!res.ok) {
			console.warn(
				`[playwright globalSetup] raising the calendar-creation limit returned ${res.status}; `
					+ 'calendar specs past the tenth calendar this hour may get 429.',
			)
		}
	} catch (error) {
		console.warn('[playwright globalSetup] could not raise the calendar-creation limit:', error)
	}
}

/**
 * Turn the admin flag `allowUserDashboards` on for the run.
 *
 * A fresh install has it off, and then every personal dashboard a plain user
 * seeds is refused with 403 `personal_dashboards_disabled`. Specs that seed
 * one without setting the flag passed only after an earlier spec had left it
 * on. Measured 28 Nov 2026 on Nextcloud 34 with the flag off:
 * launchpad-mobile-remote-access failed this way (as did
 * first-paint-shell-contexts and conditional-visibility-render, fixed in
 * their own files by #2446 and #2448). Specs that test the flag off still
 * set it themselves and put back what they found.
 *
 * Best-effort, like the calendar limit: a refusal is reported, never fatal.
 *
 * @param {string} baseURL the instance under test.
 * @param {string} username an admin account.
 * @param {string} password its password.
 * @return {Promise<void>}
 */
async function allowUserDashboardsForRun(
	baseURL: string,
	username: string,
	password: string,
): Promise<void> {
	try {
		const res = await fetch(new URL('/index.php/apps/launchpad/api/admin/settings', baseURL), {
			method: 'PUT',
			headers: {
				Authorization: `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}`,
				'OCS-APIRequest': 'true',
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({ allowUserDashboards: true }),
		})
		if (!res.ok) {
			console.warn(
				`[playwright globalSetup] turning allowUserDashboards on returned ${res.status}; `
					+ 'specs that seed a personal dashboard may get 403 personal_dashboards_disabled.',
			)
		}
	} catch (error) {
		console.warn('[playwright globalSetup] could not turn allowUserDashboards on:', error)
	}
}

async function dismissFirstRunWizard(page: Page): Promise<void> {
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
			console.warn(
				`[playwright globalSetup] first-run wizard dismissal returned ${status}; `
					+ 'specs may hit a modal over the app.',
			)
		}
	} catch (error) {
		console.warn(
			'[playwright globalSetup] could not dismiss the first-run wizard:',
			error,
		)
	}
}
