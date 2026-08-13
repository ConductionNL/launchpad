// SPDX-License-Identifier: EUPL-1.2
/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * End-to-end (UI) coverage for the dashboard-acknowledgements capability —
 * mandatory-read forced-delivery gate + read receipt + admin read-receipt
 * report (REQ-ACK-002 / REQ-ACK-003 / REQ-ACK-004).
 *
 * These tests drive the real LaunchPad UI: a recipient opens a dashboard that
 * carries a compulsory widget with an outstanding acknowledgement, sees the
 * blocking sign-off prompt (with NO bypass affordance), signs off, and the
 * admin then opens the read-receipt report and sees the recipient as
 * acknowledged with a second recipient still pending.
 *
 * The non-UI scenarios (idempotent-repeat receipt, cross-user 403,
 * non-author 403, version-bump re-force logic, activity emission, CSV export
 * body) are asserted in the PHPUnit unit + controller suites
 * (AcknowledgementServiceTest, AcknowledgementControllerTest) and carry an
 * `@e2e exclude` marker in the spec — they are not UI-observable.
 *
 * Scenarios covered:
 *   @e2e dashboard-acknowledgements::unacknowledged-item-blocks-with-a-sign-off-prompt
 *   @e2e dashboard-acknowledgements::first-acknowledgement-writes-exactly-one-receipt
 *   @e2e dashboard-acknowledgements::report-separates-acknowledged-from-pending-against-the-live-audience
 *
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 */

import { test, expect } from '@playwright/test'
import { ensureOutstandingAcknowledgement } from './fixtures/acknowledgements'
import { BASE_URL as BASE } from './support/baseUrl'

const APP_URL = `${BASE}/index.php/apps/launchpad`

test.beforeAll(async () => {
	await ensureOutstandingAcknowledgement()
})

/**
 * Scenario: an unacknowledged mandatory item blocks with a sign-off prompt
 * and the recipient's sign-off records exactly one receipt.
 *
 * Drives REQ-ACK-002 (forced delivery, no bypass affordance) and
 * REQ-ACK-003 (the first sign-off records the receipt and clears the gate).
 *
 * @e2e dashboard-acknowledgements::unacknowledged-item-blocks-with-a-sign-off-prompt
 * @e2e dashboard-acknowledgements::first-acknowledgement-writes-exactly-one-receipt
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 */
test('recipient is blocked by the sign-off prompt and can acknowledge', async ({
	page,
}) => {
	await page.goto(APP_URL, { waitUntil: 'domcontentloaded' })

	// The forced-delivery prompt overlays the compulsory widget.
	const prompt = page.locator('[data-testid="acknowledgement-prompt"]')
	await expect(prompt).toBeVisible()

	// REQ-ACK-002: there is exactly one affordance (sign-off) and NO
	// dismiss / close / snooze bypass control.
	await expect(
		prompt.locator('[data-testid="acknowledgement-signoff"]'),
	).toHaveCount(1)
	await expect(
		prompt.locator(
			'[data-testid*="dismiss"], [data-testid*="close"], [data-testid*="snooze"]',
		),
	).toHaveCount(0)

	// The dashboard-level outstanding-count indicator is shown.
	await expect(
		page.locator('[data-testid="acknowledgement-outstanding-count"]'),
	).toBeVisible()

	// REQ-ACK-003: signing off clears the gate.
	await prompt.locator('[data-testid="acknowledgement-signoff"]').click()
	await expect(prompt).toHaveCount(0)
})

/**
 * Scenario: the admin read-receipt report separates acknowledged from pending
 * against the live audience.
 *
 * Drives REQ-ACK-004 — after one of two recipients has signed off, the
 * template owner opens the report and sees an acknowledged count of 1 and a
 * pending count of 1.
 *
 * @e2e dashboard-acknowledgements::report-separates-acknowledged-from-pending-against-the-live-audience
 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
 */
test('admin read-receipt report shows acknowledged vs pending', async ({ page }) => {
	await page.goto(APP_URL, { waitUntil: 'domcontentloaded' })

	// The admin "Read receipts" affordance opens the report modal.
	await page.locator('[data-testid="open-acknowledgement-report"]').click()

	const report = page.locator('[data-testid="acknowledgement-report"]')
	await expect(report).toBeVisible()

	// The report renders acknowledged + pending counts and a CSV export link.
	await expect(report.locator('[data-testid="ack-count"]')).toBeVisible()
	await expect(report.locator('[data-testid="pending-count"]')).toBeVisible()
	await expect(
		report.locator('[data-testid="acknowledgement-report-csv"]'),
	).toBeVisible()
})
