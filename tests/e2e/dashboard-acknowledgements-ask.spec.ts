/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for asking readers to confirm a widget from the
 * widget menu (openspec/specs/dashboard-acknowledgements REQ-ACK-007),
 * written for e2e-coverage-programme task 2.11.
 *
 * Sanne owns a dashboard with the text widget "New expense rules" and shares
 * it with Pieter. She opens the widget menu (edit mode, right-click), picks
 * "Read confirmation…", ticks "Ask readers to confirm they have read this"
 * and saves. Pieter then opens the dashboard and must see the prompt.
 *
 * Pieter holds no copy: he is asked because the recipients of a share of a
 * personal dashboard are in the announcement's audience (REQ-ACK-003).
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-acknowledgements::ask-for-confirmation
 *   @e2e dashboard-acknowledgements::a-share-recipient-of-a-personal-dashboard-must-confirm
 *
 * @spec openspec/specs/dashboard-acknowledgements/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRef } from './fixtures/acknowledgements.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	activate,
	addTextWidget,
	API,
	createPersonalDashboard,
	placementWithText,
	promptSelector,
	readUserDashboardsFlag,
	setUserDashboardsFlag,
	share,
} from './fixtures/acknowledgements.ts'
import { loginAs } from './fixtures/secondary-user.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const WIDGET = `New expense rules ${RUN}`
const SENTENCE = 'I have read the new expense rules'

let admin: APIRequestContext
let userDashboardsBefore: boolean | null = null
let sanne: Principal | null = null
let pieter: Principal | null = null
let dashboard: DashboardRef | null = null
let placementId = 0

test.setTimeout(180_000)

test.beforeAll(async () => {
	test.setTimeout(300_000)
	admin = await adminContext()
	userDashboardsBefore = await readUserDashboardsFlag(admin)
	await setUserDashboardsFlag(admin, true)

	sanne = await createPrincipal(admin, 'e2e-ack-sanne')
	pieter = await createPrincipal(admin, 'e2e-ack-pieter')
	dashboard = await createPersonalDashboard(sanne, `Team ${RUN}`)
	await activate(sanne.api, dashboard.uuid)
	placementId = await addTextWidget(sanne.api, dashboard.id, WIDGET, 0)
	await publishForRecipients(sanne.api, dashboard.id)
	await share(sanne.api, dashboard.id, pieter.username, 'view_only')
	await activate(pieter.api, dashboard.uuid)
})

test.afterAll(async () => {
	if (sanne !== null && dashboard !== null) {
		await sanne.api.delete(`${API}/dashboard/${dashboard.id}`)
	}
	await removePrincipal(admin, sanne)
	await removePrincipal(admin, pieter)
	if (userDashboardsBefore !== null) {
		await setUserDashboardsFlag(admin, userDashboardsBefore)
	}
	await admin.dispose()
})

// @e2e dashboard-acknowledgements::ask-for-confirmation
test('Sanne asks for a read confirmation from the widget menu and Pieter sees the prompt', async ({
	browser,
}) => {
	// WHEN: Sanne ticks "Ask readers to confirm they have read this" and saves.
	const editor = await loginAs(browser, sanne!.username, sanne!.password)
	try {
		const { page } = editor
		await page.goto('/index.php/apps/launchpad/')
		const cell = page.locator(`[data-placement-id="${placementId}"]`)
		await expect(cell).toContainText(WIDGET, { timeout: 30_000 })
		await enterEditMode(page)
		await cell.dispatchEvent('contextmenu')
		await page.locator('[data-testid="ctx-read-confirmation"]').click()

		const dialog = page.getByRole('dialog', { name: 'Read confirmation' })
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		await dialog.getByText('Ask readers to confirm they have read this').click()
		await dialog.getByLabel('What readers confirm').fill(SENTENCE)

		await dialog.getByRole('button', { name: 'Save' }).click()
		await expect(dialog).toBeHidden({ timeout: 10_000 })
		// REQ-EDIT-002: the read confirmation is in the draft; Save stores it.
		const saved = page.waitForResponse(
			(r) =>
				/\/api\/dashboard\/\d+$/.test(new URL(r.url()).pathname)
				&& r.request().method() === 'PUT',
		)
		await page.getByTestId('launchpad-edit-bar-save').click()
		const response = await saved
		expect(response.status(), await response.text()).toBe(200)
		const sent = (JSON.parse(response.request().postData() ?? '{}').placements ?? [])
			.find((p: { id?: number }) => Number(p.id) === placementId)
		expect(sent).toMatchObject({
			requiresAcknowledgement: 1,
			acknowledgementPrompt: SENTENCE,
		})
	} finally {
		await editor.context.close()
	}
	const stored = await placementWithText(sanne!.api, dashboard!.id, WIDGET)
	expect(stored.requiresAcknowledgement).toBe(1)
	expect(String(stored.announcementKey ?? '')).not.toBe('')

	// THEN: Pieter sees the confirmation prompt on that widget the next time
	// he opens the dashboard.
	const reader = await loginAs(browser, pieter!.username, pieter!.password)
	try {
		await reader.page.goto('/index.php/apps/launchpad/')
		await expect(
			reader.page.locator(`[data-placement-id="${placementId}"]`),
		).toContainText(WIDGET, { timeout: 30_000 })
		const prompt = reader.page.locator(promptSelector(placementId))
		await expect(prompt).toBeVisible({ timeout: 15_000 })
		await expect(
			prompt.locator('[data-testid="acknowledgement-prompt-text"]'),
		).toHaveText(SENTENCE)
	} finally {
		await reader.context.close()
	}
})
