/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The runtime-manifest boot contract (ADR-036 Decision 8), observed from a
 * browser rather than asserted about `src/main.js`.
 *
 * `src/main.js` registers the BUNDLED manifest synchronously at module scope
 * (`useAppManifest('launchpad', bundledStub)`, line 95) and only then kicks off
 * an async `GET /apps/launchpad/api/manifest` whose success replaces the stub
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
 * Scenarios covered:
 *   @e2e launchpad-adopt-or-abstractions::manifest-loads-on-app-boot
 */

import { test, expect } from '@playwright/test'

const APP_URL = '/index.php/apps/launchpad'
const MANIFEST_GLOB = '**/apps/launchpad/api/manifest*'

// The first stable landmark the Vue bootstrap injects. Neighbouring CI-run
// specs (`spec-coverage/spec-coverage.spec.ts`) wait on the same element, so
// "the app rendered" means the same thing here as it does there.
const APP_LANDMARK = '.launchpad-sidebar-toggle'

test.describe('runtime manifest boot', () => {
	test('boot issues the runtime-manifest fetch and renders the app', async ({
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

		expect(
			manifestRequests,
			'src/main.js must attempt GET /apps/launchpad/api/manifest on boot — no request means the runtime-manifest loader was dropped',
		).not.toHaveLength(0)
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
		// this isolates the fallback branch.
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
