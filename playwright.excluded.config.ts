/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The specs `playwright.config.ts` excludes — runnable, not counted:
 *
 *   npm run test:e2e:excluded
 *
 * Every file here is a real test that someone wrote for a real reason, and
 * several of them mostly pass. What none of them can do is stand behind a
 * coverage claim, because they are not green in the CI fixture (or, for
 * `api-direct/`, are structurally out of scope for the UI gate — Newman owns
 * that contract). Measured in run 31367057618: 65 of 80 root-suite tests
 * passed; the 15 failures are concentrated in the files listed below and the
 * per-file verdicts are recorded next to each entry in `playwright.config.ts`.
 *
 * WHY THIS FILE EXISTS AT ALL. Withholding coverage credit and deleting the
 * ability to run a test are different acts, and only the first one is
 * warranted. gate-19 reads `playwright.config.{ts,js,mts,cjs}` and nothing
 * else, so a spec reachable only from here claims nothing — while a developer
 * fixing one of these still has a one-command way to run it, and the promotion
 * path stays short: get the file green, move its glob out of the `testIgnore`
 * in `playwright.config.ts` and out of the `testMatch` here, and confirm its
 * test names in the CI job's run list.
 *
 * Keep the two lists complements of each other. If a file appears in both, or
 * in neither, someone has quietly changed what "covered" means.
 */

import { defineConfig, devices } from '@playwright/test'
import * as path from 'path'
import { BASE_URL as baseURL } from './tests/e2e/support/baseUrl'

export default defineConfig({
	testDir: './tests/e2e',
	testMatch: [
		// API-direct HTTP-contract specs: Newman owns their coverage, so the
		// main job never runs them. They stay runnable here on purpose.
		'**/api-direct/**/*.spec.ts',
		// Re-excluded from the main job 2026-08-19 with fresh evidence (run
		// 32308042394): still red with the first-run wizard already dismissed.
		// The first three need a dashboard the suite never seeds; making them
		// self-seeding is the fix. Runnable here in the meantime.
		// `image-widget-clickthrough` and `runtime-shell-canEdit` were PROMOTED
		// into the main job on 2026-08-19 and are green there: dismissing
		// Nextcloud's first-run wizard in global-setup really was their cause.
		// They are deliberately absent from this list so the two configs cannot
		// disagree about what is excluded.
	],
	fullyParallel: false,
	workers: 1,
	retries: 0,
	timeout: 60_000,
	expect: { timeout: 10_000 },
	reporter: 'list',
	globalSetup: path.resolve(__dirname, 'tests/e2e/global-setup.ts'),
	use: {
		baseURL,
		storageState: path.resolve(__dirname, 'tests/e2e/.auth/admin.json'),
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		actionTimeout: 10_000,
		navigationTimeout: 60_000,
		...devices['Desktop Chrome'],
	},
})
