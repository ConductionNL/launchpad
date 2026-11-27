/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * "Show until" is required for a new announcement (announcement-show-until,
 * REQ-ANN-001): the form "New announcement" will not go on without an end
 * time and the server refuses one with 400; a publish time ahead turns the
 * confirm button into "Schedule", and an empty one leaves it "Publish".
 *
 * The admin is the author, on a dashboard of their own that holds the
 * announcements widget. Every draft the form or the API creates is deleted
 * afterwards, with the dashboard; nothing is ever published.
 *
 * Written from the change's scenarios; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/specs/announcements/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = Date.now().toString(36)
const DAY_MS = 24 * 3600 * 1000

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
const drafts: string[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * A moment some days from now as a `datetime-local` value, at a set hour.
 *
 * @param days days from today.
 * @param hour the hour of the day.
 * @return the value, `YYYY-MM-DDTHH:mm`.
 */
function localAt(days: number, hour: number): string {
	const at = new Date(Date.now() + days * DAY_MS)
	const pad = (n: number) => String(n).padStart(2, '0')
	return `${at.getFullYear()}-${pad(at.getMonth() + 1)}-${pad(at.getDate())}T${pad(hour)}:00`
}

/**
 * Open the Announcements tab of the admin settings page and its form
 * "New announcement" (announcements-managed-in-settings REQ-ANN-006: the
 * widget no longer writes).
 *
 * @param page the page.
 * @return the editor.
 */
async function openEditor(page: Page): Promise<Locator> {
	await page.addInitScript(() => {
		window.sessionStorage.setItem('launchpad:setup-wizard-offered', '1')
	})
	await page.goto('/index.php/settings/admin/launchpad#announcements')
	await page.getByTestId('announcement-new').click({ timeout: 60_000 })
	const editor = page.locator('[data-test="announcement-editor"]')
	await expect(editor).toBeVisible({ timeout: 10_000 })
	return editor
}

/**
 * The title input of the editor.
 *
 * @param editor the editor.
 * @return the input.
 */
function titleInput(editor: Locator): Locator {
	return editor
		.locator('input[data-test="editor-title"], [data-test="editor-title"] input')
		.first()
}

test.beforeAll(async () => {
	admin = await adminContext()
	dashboard = await seedActiveDashboard(admin, `E2E Aankondigingen ${STAMP}`)
	await clearSeededWidgets(admin, dashboard.id)
	const widget = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: { widgetId: 'announcements', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 6, content: {} },
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	if (admin) {
		for (const uuid of drafts) {
			await admin.delete(`${API}/announcements/${uuid}`).catch(() => undefined)
		}
		await removeSeededDashboard(admin, dashboard).catch(() => undefined)
		await admin.dispose()
	}
})

// @e2e announcements::an-end-time-is-required
test('the form does not go on without "Show until", and the server refuses it with 400', async ({
	page,
}) => {
	const editor = await openEditor(page)
	const sent: string[] = []
	page.on('request', (req) => {
		if (req.url().includes('/api/announcements') && ['POST', 'PUT'].includes(req.method())) {
			sent.push(`${req.method()} ${req.url()}`)
		}
	})

	await titleInput(editor).fill(`Inloopspreekuur privacy ${STAMP}`)
	const showUntil = editor.locator('[data-test="editor-expires-at"]')
	await expect(showUntil).toHaveAttribute('required', '')
	await expect(editor.locator('[data-test="editor-show-until-hint"]')).toHaveText(
		'After this time the announcement disappears from every dashboard.',
	)

	// The form is one dialog with a live preview (REQ-ANN-006): Publish
	// without an end time names what is missing and sends nothing.
	expect(await showUntil.evaluate((el) => (el as HTMLInputElement).validity.valueMissing)).toBe(true)
	await editor.locator('[data-test="editor-publish"]').click()
	await expect(editor.locator('[data-test="editor-error"]')).toContainText(
		'Choose until when the announcement shows',
	)
	await expect(editor).toBeVisible()
	expect(sent).toEqual([])

	// The server refuses a new announcement without an end time as well.
	const refused = await admin.post(`${API}/announcements`, {
		data: { kind: 'news', title: `Zonder einde ${STAMP}`, body: 'Tekst' },
	})
	const text = await refused.text()
	expect(refused.status(), text).toBe(400)
	expect(text).toMatch(/Show until|expiresAt/)
})

// @e2e announcements::scheduling-for-later
test('a publish time ahead makes the confirm button "Schedule"; without one it reads "Publish"', async ({
	page,
}) => {
	const editor = await openEditor(page)
	await titleInput(editor).fill(`Inloopspreekuur ${STAMP}`)
	await editor.locator('[data-test="editor-publish-at"]').fill(localAt(7, 8))
	await editor.locator('[data-test="editor-expires-at"]').fill(localAt(9, 17))

	// The live preview shows the end time, and the button names what it does.
	await expect(editor.locator('[data-test="editor-visible-until"]')).toContainText('Visible until')
	await expect(editor.locator('[data-test="editor-publish"]')).toHaveText('Schedule')

	// Without a publish time it publishes at once.
	await editor.locator('[data-test="editor-publish-at"]').fill('')
	await expect(editor.locator('[data-test="editor-publish"]')).toHaveText('Publish')
})
