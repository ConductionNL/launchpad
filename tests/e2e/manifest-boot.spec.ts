/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The runtime-manifest boot contract (ADR-036 Decision 8), observed from a
 * browser rather than asserted about `src/main.js`.
 *
 * `src/main.js` registers the BUNDLED manifest synchronously at module scope
 * (`useAppManifest('launchpad', bundledStub)`) and only then takes the runtime
 * manifest: from the page's `runtimeManifest` initial state when the server
 * kept one for the viewer (REQ-INIT-009, a warm load), else from an async
 * `GET /apps/launchpad/api/manifest`. Either way the result replaces the stub
 * outright. The failure branch is the interesting one and the one that has
 * never had a test: on any error the loader keeps the stub, logs
 * `[launchpad] Runtime manifest fetch failed; using stub`, and the app must
 * still render — because the existing UI routes from its Pinia stores and does
 * not depend on the manifest at all.
 *
 * That claim is exactly the kind that rots silently: a future refactor that
 * makes the shell await the manifest, or that lets the rejection escape, turns
 * a degraded-but-working app into a blank page on any instance where
 * `/api/manifest` is slow, 404s or is refused. Test 2 fails the moment that
 * happens, and nothing else in the suite would.
 *
 * Because a warm load carries the manifest and makes no request, test 2 takes
 * the kept manifest out of the page first; only then is the fetch, and so its
 * failure branch, reached.
 *
 * Scenarios covered:
 *   @e2e launchpad-adopt-or-abstractions::manifest-loads-on-app-boot
 */

import type {Page} from '@playwright/test';

import { expect, test } from '@playwright/test'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

const APP_URL = '/index.php/apps/launchpad'
const MANIFEST_GLOB = '**/apps/launchpad/api/manifest*'

// The start page's More menu: the first stable landmark of a rendered shell.
const APP_LANDMARK = MORE_MENU

// The kept manifest the server puts in the page (REQ-INIT-009).
const CARRIED_MANIFEST = '#initial-state-launchpad-runtimeManifest'

/**
 * Serve the workspace page without the kept manifest, so the page has to
 * request `/api/manifest` itself (the cold-load path).
 *
 * @param page The Playwright page.
 */
async function dropCarriedManifest(page: Page): Promise<void> {
	await page.route(
		(url) => /\/apps\/launchpad\/?$/.test(url.pathname),
		async (route) => {
			const response = await route.fetch()
			const body = (await response.text()).replace(
				/<input[^>]*id="initial-state-launchpad-runtimeManifest"[^>]*>/,
				'',
			)
			await route.fulfill({ response, body })
		},
	)
}

test.describe('runtime manifest boot', () => {
	test('boot takes the runtime manifest, carried or fetched, and renders the app', async ({
		page,
	}) => {
		// Record the request rather than waiting for it after the fact: the
		// loader is fired at module scope, so it can complete before `goto()`
		// resolves and a later `waitForRequest` would hang on an event that
		// already happened.
		const manifestRequests: string[] = []
		page.on('request', (req) => {
			if (req.url().includes('/apps/launchpad/api/manifest')) {
				manifestRequests.push(req.url())
			}
		})

		await page.goto(APP_URL)
		await expect(page.locator(APP_LANDMARK).first()).toBeVisible({
			timeout: 30_000,
		})

		// A warm load carries the manifest and asks for nothing; a cold one
		// asks once. Neither carried nor requested means the loader was dropped.
		const carried = await page.locator(CARRIED_MANIFEST).count()
		expect(
			carried + manifestRequests.length,
			'the page must carry runtimeManifest or request GET /apps/launchpad/api/manifest on boot',
		).toBeGreaterThan(0)
		if (carried > 0) {
			expect(
				manifestRequests,
				'a page that carries the kept manifest must not request it again (REQ-INIT-009)',
			).toHaveLength(0)
		}
	})

	test('a page without the kept manifest requests it on boot', async ({
		page,
	}) => {
		const manifestRequests: string[] = []
		page.on('request', (req) => {
			if (req.url().includes('/apps/launchpad/api/manifest')) {
				manifestRequests.push(req.url())
			}
		})
		await dropCarriedManifest(page)

		await page.goto(APP_URL)
		await expect(page.locator(APP_LANDMARK).first()).toBeVisible({
			timeout: 30_000,
		})
		await expect(page.locator(CARRIED_MANIFEST)).toHaveCount(0)
		await expect
			.poll(() => manifestRequests.length, {
				message: 'with no runtimeManifest key the page must request GET /apps/launchpad/api/manifest',
				timeout: 15_000,
			})
			.toBeGreaterThan(0)
	})

	test('a failing manifest fetch degrades silently and still renders the app', async ({
		page,
	}) => {
		const warnings: string[] = []
		page.on('console', (msg) => {
			if (msg.type() === 'warning') {
				warnings.push(msg.text())
			}
		})

		// Fail the runtime manifest only. Everything else — including the
		// bundled stub, which ships inside the JS bundle — is untouched, so
		// this isolates the fallback branch. The kept manifest is taken out
		// of the page first, or the loader never reaches the fetch.
		await dropCarriedManifest(page)
		await page.route(MANIFEST_GLOB, (route) =>
			route.fulfill({
				status: 500,
				contentType: 'application/json',
				body: '{"error":"forced by manifest-boot.spec.ts"}',
			}),
		)

		await page.goto(APP_URL)

		// THE assertion: the app is still usable. A regression that awaits the
		// manifest, or that lets the rejection escape the IIFE, renders nothing
		// here while every other test in the suite stays green.
		await expect(
			page.locator(APP_LANDMARK).first(),
			'the app must render from the bundled stub when /api/manifest fails',
		).toBeVisible({ timeout: 30_000 })

		await expect
			.poll(
				() =>
					warnings.some((w) =>
						w.includes('Runtime manifest fetch failed'),
					),
				{
					message: 'the fallback must be logged, not swallowed',
					timeout: 15_000,
				},
			)
			.toBe(true)
	})
})
