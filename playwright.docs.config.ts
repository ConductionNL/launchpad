/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Documentation capture, on purpose and on demand:
 *
 *   npm run test:e2e:docs
 *
 * `tests/e2e/docs-screenshots.spec.ts` reshoots the tutorial screenshots into
 * `docs/screenshots/tutorials/{user,admin}/`. It is a capture job, not a
 * regression suite — a red run here means a screenshot did not get taken, not
 * that the product broke — so it must not sit in the suite that gates a PR.
 *
 * WHY IT IS A SEPARATE FILE RATHER THAN A SECOND PROJECT IN
 * `playwright.config.ts`. It used to be a `docs-capture` project there, which
 * looked tidy and had one consequence nobody had measured: since
 * `.github#308`, gate-19 counts a spec as live if ANY project in
 * `playwright.config.ts` would run it. A project whose whole purpose is "do
 * not run this in CI" therefore keeps handing gate-19 the file's coverage
 * credit. Moving it to a config gate-19 does not read (it looks only for
 * `playwright.config.{ts,js,mts,cjs}`) makes the exclusion mean what it says.
 *
 * This file carries no `@e2e` annotations today, so the move costs no
 * coverage — it removes a mechanism that would have hidden some later.
 */

import { defineConfig, devices } from '@playwright/test'
import * as path from 'path'
import { BASE_URL as baseURL } from './tests/e2e/support/baseUrl'

export default defineConfig({
	testDir: './tests/e2e',
	testMatch: /docs-screenshots\.spec\.ts$/,
	fullyParallel: false,
	workers: 1,
	retries: 0,
	timeout: 90_000,
	expect: { timeout: 10_000 },
	reporter: 'list',
	globalSetup: path.resolve(__dirname, 'tests/e2e/global-setup.ts'),
	use: {
		baseURL,
		storageState: path.resolve(__dirname, 'tests/e2e/.auth/admin.json'),
		viewport: { width: 1280, height: 800 },
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		actionTimeout: 10_000,
		navigationTimeout: 60_000,
		...devices['Desktop Chrome'],
	},
})
