/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * THE Playwright configuration for launchpad. One file, used by CI and by
 * developers, and it is the same file gate-19 reads.
 *
 * THAT SAMENESS IS THE POINT, AND IT IS NEW
 * =========================================
 * There used to be two configs. `tests/e2e/ci/playwright.config.ts` selected a
 * four-test subset, because the shared quality workflow resolves
 * `<playwright-test-path>/playwright.config.ts` BEFORE falling back to this
 * one; this file described the full suite that only ever ran by hand. The
 * split was written as a deliberate "green floor that grows", and as a floor
 * it worked. What it could not do is tell anyone the truth about coverage,
 * because gate-19 reads THIS file and CI read the other one, and nothing
 * compared them.
 *
 * Measured on 2026-08-10 (ConductionNL/launchpad#82): CI executed **4** tests
 * while **113** existed, and of **117 `@e2e` annotations only 9** sat in files
 * CI ran — so 108 coverage claims rested on tests that had never executed in
 * CI, and gate-19 counted 71 scenarios covered when 4 had an executing test
 * behind them.
 *
 * So the subset config is deleted and `playwright-test-path` names
 * `tests/e2e`, which contains no config of its own — the workflow therefore
 * falls back HERE. What CI runs and what gate-19 counts are now the same
 * `testDir` filtered by the same `testIgnore`, and they cannot drift apart
 * without someone editing this file.
 *
 * WHICH MEANS `testIgnore` BELOW IS A COVERAGE STATEMENT
 * =====================================================
 * Since `.github#308`, gate-19 parses this config and refuses to count an
 * annotation in a file no project would run. Every entry below therefore
 * gives up coverage credit, deliberately and visibly, and every entry carries
 * the measurement that put it there — the run is
 * `ConductionNL/launchpad` run 31367057618, which executed all 80 root-suite
 * tests against the CI fixture for the first time: **65 passed, 15 failed**.
 *
 * Removing an entry is how coverage grows. It requires the file to be green in
 * that job, and the proof is the test names in its run list — not the job's
 * colour, which can be green because it ran four tests.
 *
 * Environment: see tests/e2e/support/baseUrl.ts (no default, on purpose) and
 * tests/e2e/global-setup.ts (one browser login, reused via `use.storageState`).
 */

import { defineConfig, devices } from '@playwright/test'
import * as path from 'path'
import { BASE_URL as baseURL } from './tests/e2e/support/baseUrl'

export default defineConfig({
	testDir: './tests/e2e',
	testIgnore: [
		// ── Not tests ────────────────────────────────────────────────────
		'**/global-setup.ts',
		'**/fixtures/**',
		'**/support/**',

		// ── Deliberate, structural ───────────────────────────────────────
		// API-direct HTTP-contract specs assert raw /api responses rather
		// than rendered UI; their contract coverage belongs to Newman
		// (tests/integration/*.postman_collection.json). Additionally
		// `group-shared-dashboards.spec.ts` needs fixture accounts CI does
		// not have (`member`, `nonmember`, group `e2e-test-group`), so it
		// could not pass here even if it were in scope. These files carry
		// no `@e2e` annotations and claim no scenario coverage.
		'**/api-direct/**',
		// A documentation CAPTURE job, not a regression suite: it reshoots
		// docs/screenshots/tutorials/**. Run it on purpose with
		// `npm run test:e2e:docs` (playwright.docs.config.ts). It carries no
		// `@e2e` annotations.
		'**/docs-screenshots.spec.ts',

		// ── Blocked on a fixture account, not on the code ────────────────
		// 4 of 4 fail. The suite needs a `recipient` account to be the share
		// target (LAUNCHPAD_E2E_SHAREE, default `recipient`); the CI seed
		// creates `e2e-grantee` and nothing else, so the recipient-side
		// scenario has no second user to be. Un-excluding this one needs a
		// change to the shared seed, not to launchpad.
		'**/dashboard-sharing.spec.ts',

		// ── The 2026-08-19 exclusion block is GONE, and here is why ──────
		// Six files sat here with notes like "4 of 4 fail at 47s". Two causes,
		// both since fixed, and neither of them the code under test:
		//
		// 1. Nextcloud's first-run wizard. It ships enabled, renders a
		//    full-screen modal on any FRESH instance — which is what CI
		//    provisions — and swallows clicks and keystrokes.
		//    `tests/e2e/global-setup.ts` now dismisses it once per user. That
		//    alone made `image-widget-clickthrough` and `runtime-shell-canEdit`
		//    green (CI run 32308042394).
		//
		// 2. A dashboard that nothing seeds. `tests/e2e/seed.sh` creates the
		//    `e2e-grantee` USER and nothing else, so on a cold instance
		//    LaunchPad renders "No dashboards available" and every control in
		//    the workspace shell is absent — `.launchpad-sidebar-toggle` most
		//    of all. Four specs were quietly relying on a dashboard some
		//    EARLIER spec happened to leave behind, which is exactly why they
		//    passed on a warm rig and failed on a cold one. They now seed their
		//    own via `tests/e2e/support/dashboardFixture.ts`, the way
		//    `tile-quick-search` always did.
		//
		// Measured after both fixes, against a clean isolated instance:
		// active-dashboard-resolution 3/3, add-widget-modal 4/4,
		// allow-personal-dashboards-flag 2/2, label-widget-content-edit 1/1 —
		// and 10/10 with all four run in one job, so they do not fight over
		// each other's fixtures.
		//
		// ── PROMOTED 2026-08-19: the seven files that used to sit here ────
		// `active-dashboard-resolution`, `add-widget-modal`,
		// `allow-personal-dashboards-flag`, `image-widget-clickthrough`,
		// `label-widget-content-edit` and `runtime-shell-canEdit` were all
		// recorded RED against run 31367057618 with symptoms that never named
		// their cause: clicks that did not land, "element is outside of the
		// viewport" through 23 retries, and 14-47s timeouts.
		//
		// The common cause was Nextcloud's own first-run wizard. It ships
		// enabled and renders a full-screen modal on any FRESH instance —
		// which is exactly what the CI fixture provisions — and it does not
		// merely obscure the page, it EATS KEYSTROKES and swallows clicks.
		// `tests/e2e/global-setup.ts` now dismisses it once, per user, before
		// any spec runs.
		//
		// Measured after that fix, against a clean isolated instance:
		// `allow-personal-dashboards-flag` went 0/2 -> 4/4 green.
		// The rest are promoted here so THIS job measures them rather than
		// assuming; anything still red gets re-excluded with its own fresh
		// evidence, not with the stale note it carried before.
	],
	// 60s, not 30s: the value the 80-test measurement run used, under which
	// every promoted file was green. The old 30s was never exercised against
	// this fixture by anything but the four-test subset.
	timeout: 60_000,
	expect: {
		timeout: 10_000,
	},
	fullyParallel: false,
	workers: 1,
	// Deliberately 0. A retry turns an intermittent failure into a green run
	// with a footnote, and the whole point of this file is that the run list
	// says what is true.
	retries: 0,
	// Stop the run on our own clock, before CI kills it. The shared
	// ConductionNL quality workflow caps this job at `timeout-minutes: 45`, and
	// a job cancelled by that cap produces NO verdict: Playwright never prints
	// its tally, the `if: failure()` trace upload never fires, and the
	// `if: always()` report upload does not run on a cancelled job either. The
	// run you most need to read is the one that leaves nothing behind — and it
	// still renders as "fail" in `gh pr checks` while carrying no information.
	// Measured overhead before `Run Playwright tests` starts in that job is
	// 2.0-2.4 min and the uploads after it take seconds, so 38m keeps ~7 min of
	// margin while guaranteeing a tally and its artifacts.
	globalTimeout: 38 * 60_000,
	reporter: process.env.CI
		? [['list'], ['github'], ['html', { open: 'never' }]]
		: 'list',
	globalSetup: path.resolve(__dirname, 'tests/e2e/global-setup.ts'),
	use: {
		baseURL,
		storageState: path.resolve(__dirname, 'tests/e2e/.auth/admin.json'),
		// `on-first-retry` writes a trace only when a retry actually happens,
		// which makes it a function of `retries` — and `retries` is 0 above,
		// unconditionally. There is no first retry to trigger on, so this suite
		// wrote ZERO traces for its entire history while the config read as
		// though tracing were on. `retain-on-failure` captures every test and
		// keeps only the failures; it cannot be silently disabled from
		// elsewhere in the file.
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		actionTimeout: 10_000,
		navigationTimeout: 60_000,
	},
	// ONE project. A second project is not free here: gate-19 counts a file as
	// live if ANY project would run it, so a "local-only" project would hand
	// back exactly the coverage credit the `testIgnore` above gives up, and
	// this file would go back to describing something other than what runs.
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
})
