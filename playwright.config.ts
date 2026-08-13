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

		// ── Red in the CI fixture, measured, run 31367057618 ─────────────
		// Each of these RUNS LOCALLY and is worth keeping; none of them is
		// green in this job, so none of them may claim coverage here. The
		// named test is the one that fails; the file is excluded whole
		// because `testIgnore` is file-granular and a partially-executed
		// file would still hand gate-19 the whole file's annotations.
		//
		// 2 of 3 fail — "clicking a sidebar row activates the chosen
		// dashboard server-side" and "a stale saved UUID is silently
		// discarded" both time out at 24s.
		'**/active-dashboard-resolution.spec.ts',
		// 4 of 4 fail, all at 47s: the add-widget modal never reaches the
		// state the close-discipline assertions need.
		'**/add-widget-modal.spec.ts',
		// 2 of 2 fail at 14.6s — the sidebar Add-Dashboard button assertions.
		'**/allow-personal-dashboards-flag.spec.ts',
		// 4 of 4 fail. The suite needs a `recipient` account to be the share
		// target (LAUNCHPAD_E2E_SHAREE, default `recipient`); the CI seed
		// creates `e2e-grantee` and nothing else, so the recipient-side
		// scenario has no second user to be.
		'**/dashboard-sharing.spec.ts',
		// The image suite WAS excluded whole because 1 of its 3 tests fails:
		// "REQ-IMG-003: a URL image with a click-through link opens the link in
		// a new tab" — the widget cell resolves but Playwright reports "element
		// is outside of the viewport" through 23 click retries, then the popup
		// wait times out. That one test now lives in its own file so the rest
		// of the suite can run. It is unchanged and still runnable via
		// `npm run test:e2e:excluded`; the SCENARIO it covers is now proven in
		// `image-widget.spec.ts` by recording `window.open`, which asserts the
		// 'noopener,noreferrer' argument a real popup cannot show.
		'**/image-widget-clickthrough.spec.ts',
		// The label suite WAS excluded whole because 1 of its 3 tests fails.
		// That one test now lives in its own file, so the two green ones run
		// here and the red one stays out — which is what file-granular
		// `testIgnore` makes necessary. The failing test is unchanged and
		// still runnable via `npm run test:e2e:excluded`.
		'**/label-widget-content-edit.spec.ts',
		// 7 of 8 pass. "ADR-023: … the empty-state Create CTA it is offered
		// actually works" fails at 10.9s. The seven green ones are a real
		// loss here and this is the first file to promote once that one is
		// fixed; it carries no `@e2e` annotations, so excluding it costs no
		// coverage credit today.
		'**/runtime-shell-canEdit.spec.ts',
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
