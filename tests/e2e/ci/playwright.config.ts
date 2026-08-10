/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * MEASUREMENT CONFIG — NOT FOR MERGE.
 *
 * This branch exists to answer one question with evidence rather than reading:
 * which of the ~107 specs under tests/e2e actually pass in the CI environment
 * the shared quality workflow provides (NC stable32 + openregister, admin/admin,
 * a built frontend bundle, php -S on :8080, one seeded `e2e-grantee` account)?
 *
 * So it points testDir at the whole root suite, wires the root globalSetup +
 * storageState (which the shipped CI config does not need, because its four
 * specs are API-only), and runs with retries 0 so a flake reads as a flake.
 */

import { defineConfig, devices } from '@playwright/test'
import * as path from 'path'

const baseURL = process.env.BASE_URL ?? process.env.NC_BASE_URL

if (baseURL === undefined || baseURL === '') {
	throw new Error('BASE_URL (or NC_BASE_URL) must be set.')
}

const E2E_ROOT = path.resolve(__dirname, '..')

export default defineConfig({
	testDir: E2E_ROOT,
	testIgnore: [
		'**/global-setup.ts',
		'**/fixtures/**',
		'**/support/**',
		// Known-illegitimate targets, excluded from the measurement itself:
		//  - api-direct/group-shared-dashboards.spec.ts needs fixture users CI
		//    does not have (member / nonmember / group e2e-test-group)
		//  - docs-screenshots.spec.ts is a capture job, not a regression suite
		'**/api-direct/**',
		'**/docs-screenshots.spec.ts',
		// The four specs already executed by CI are not the question; they pass.
		// Excluding them also keeps the OCS-APIRequest header they need out of
		// the browser contexts the UI specs use.
		'**/ci/**',
	],
	fullyParallel: false,
	forbidOnly: !!process.env.CI,
	retries: 0,
	workers: 1,
	reporter: process.env.CI
		? [['list'], ['json', { outputFile: 'playwright-report/measure.json' }], ['html', { open: 'never' }]]
		: [['list']],
	globalTimeout: 38 * 60_000,
	timeout: 60_000,
	expect: { timeout: 10_000 },
	globalSetup: path.resolve(E2E_ROOT, 'global-setup.ts'),
	use: {
		baseURL: baseURL.replace(/\/$/, ''),
		storageState: path.resolve(E2E_ROOT, '.auth', 'admin.json'),
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'off',
		actionTimeout: 10_000,
		navigationTimeout: 60_000,
	},
	projects: [
		{ name: 'chromium', use: { ...devices['Desktop Chrome'] } },
	],
})
