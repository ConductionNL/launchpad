/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * CI-safe Playwright config — a deliberately small, green-on-arrival subset.
 *
 * WHY A SECOND CONFIG RATHER THAN ENABLING THE ROOT ONE. The root config points
 * `testDir` at all of `tests/e2e` — 19 spec files, several of which capture
 * documentation screenshots or drive multi-user UI flows that need seeded
 * fixtures. Turning that on wholesale is red on the first run, and a gate that is
 * red on arrival is a gate nobody turns on. The shared workflow resolves
 * `<playwright-test-path>/playwright.config.ts` FIRST and only falls back to the
 * root one, so pointing `playwright-test-path` at this directory selects a
 * config whose `testDir` is just this directory. The floor is green and it grows.
 *
 * This mirrors the same move already made in openregister.
 */

import { defineConfig, devices } from '@playwright/test'

/*
 * CI exports BASE_URL / ADMIN_USER / ADMIN_PASSWORD. The repo's older specs read
 * NC_BASE_URL / NC_ADMIN_USER / NC_ADMIN_PASS, so both spellings are accepted,
 * CI's first.
 *
 * There is deliberately NO localhost fallback. A default would make a run with
 * no environment silently target whatever happens to be on localhost:8080 —
 * which, on a developer machine, is the SHARED dev container. Failing loudly is
 * the only safe behaviour.
 */
const baseURL = process.env.BASE_URL ?? process.env.NC_BASE_URL

if (baseURL === undefined || baseURL === '') {
	throw new Error(
		'BASE_URL (or NC_BASE_URL) must be set. Refusing to guess — a localhost '
		+ 'default would silently target the shared dev instance.',
	)
}

export default defineConfig({
	testDir: '.',
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: process.env.CI ? [['list'], ['github']] : [['list']],
	timeout: 60_000,
	expect: { timeout: 10_000 },
	use: {
		baseURL: baseURL.replace(/\/$/, ''),
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	},
	projects: [
		{ name: 'chromium', use: { ...devices['Desktop Chrome'] } },
	],
})
