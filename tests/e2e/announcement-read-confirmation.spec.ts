/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * An announcement asks for a read confirmation (announcement-form-board-sync,
 * REQ-ANN-007): the author writes and schedules one with "Ask for read
 * confirmation" and "Confirm before", the preview shows "I have read this"
 * and the date; a reader in the audience confirms once, and the author reads
 * who confirmed.
 *
 * Written in the cloud lane without a live instance; it runs in the nightly.
 *
 * @e2e openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { adminContext, API, createPrincipal, removePrincipal } from './support/principals.ts'

const STAMP = Date.now().toString(36)
const DAY_MS = 24 * 3600 * 1000

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let reader: Principal | null = null
const written: string[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * A day some days from now, as a `date` and a `datetime-local` value.
 *
 * @param days days from today.
 * @param hour the hour of the day.
 * @return the values.
 */
function dayAhead(days: number, hour = 8): { date: string, local: string } {
	const at = new Date(Date.now() + days * DAY_MS)
	const pad = (n: number) => String(n).padStart(2, '0')
	const date = `${at.getFullYear()}-${pad(at.getMonth() + 1)}-${pad(at.getDate())}`
	return { date, local: `${date}T${pad(hour)}:00` }
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

test.beforeAll(async () => {
	admin = await adminContext()
	dashboard = await seedActiveDashboard(admin, `E2E Leesbevestiging ${STAMP}`)
	await clearSeededWidgets(admin, dashboard.id)
	const widget = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: { widgetId: 'announcements', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 6, content: {} },
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)
	reader = await createPrincipal(admin, 'e2e-ann-reader')
})

test.afterAll(async () => {
	if (admin) {
		for (const uuid of written) {
			await admin.delete(`${API}/announcements/${uuid}`).catch(() => undefined)
		}
		await removePrincipal(admin, reader).catch(() => undefined)
		await removeSeededDashboard(admin, dashboard).catch(() => undefined)
		await admin.dispose()
	}
})

// @e2e announcements::write-a-news-item
// @e2e announcements::confirm-before-friday
test('the author writes and schedules an announcement with a read confirmation', async ({ page }) => {
	const editor = await openEditor(page)
	const title = `Nieuwe werkwijze ${STAMP}`
	const sent: string[] = []
	page.on('request', (req) => {
		if (req.url().includes('/api/announcements') && ['POST', 'PUT'].includes(req.method())) {
			sent.push(req.url())
		}
	})
	await editor.locator('input[data-test="editor-title"], [data-test="editor-title"] input').first().fill(title)
	// Write a news item: the preview shows the title at once, without saving.
	await expect(editor.locator('[data-test="editor-preview-pane"] [data-test="announcement-card"] h4')).toHaveText(title)
	expect(sent).toEqual([])
	await editor.locator('[data-test="editor-publish-at"]').fill(dayAhead(2).local)
	await editor.locator('[data-test="editor-expires-at"]').fill(dayAhead(14, 17).local)
	// The switch's label overlays its input, so a plain click is intercepted.
	await editor.locator('[data-test="editor-requires-confirmation"]').check({ force: true })
	await editor.locator('[data-test="editor-confirm-before"]').fill(dayAhead(5).date)

	const preview = editor.locator('[data-test="editor-preview-pane"]')
	await expect(preview.locator('[data-test="announcement-confirm-read"]')).toHaveAttribute('aria-label', 'Mark as read')
	await expect(preview.locator('[data-test="announcement-confirm-before"]')).toContainText('Confirm before')
	await expect(editor.locator('[data-test="editor-publish"]')).toHaveText('Schedule')

	const created = page.waitForResponse((res) => res.url().endsWith('/api/announcements') && res.request().method() === 'POST')
	await editor.locator('[data-test="editor-publish"]').click()
	const response = await created
	expect(response.status(), await response.text()).toBe(201)
	const stored = await response.json()
	written.push(String(stored.uuid))
	expect(stored.requiresConfirmation).toBe(true)
	expect(String(stored.confirmBefore)).toContain(dayAhead(5).date.slice(0, 7))
	await expect(editor).toHaveCount(0, { timeout: 10_000 })
})

// @e2e announcements::confirm-before-friday
test('a reader confirms "I have read this" once, and the author reads who confirmed', async () => {
	const created = await admin.post(`${API}/announcements`, {
		data: {
			kind: 'news',
			title: `Lees en bevestig ${STAMP}`,
			expiresAt: new Date(Date.now() + 14 * DAY_MS).toISOString(),
			requiresConfirmation: true,
			confirmBefore: new Date(Date.now() + 5 * DAY_MS).toISOString(),
		},
	})
	expect(created.status(), await created.text()).toBe(201)
	const uuid = String((await created.json()).uuid)
	written.push(uuid)
	const published = await admin.post(`${API}/announcements/${uuid}/publish`)
	expect(published.status(), await published.text()).toBeLessThan(300)

	const first = await reader!.api.post(`${API}/announcements/${uuid}/read`)
	expect(first.status(), await first.text()).toBe(200)
	const again = await reader!.api.post(`${API}/announcements/${uuid}/read`)
	expect((await again.json()).confirmedAt).toBe((await first.json()).confirmedAt)

	const state = await reader!.api.get(`${API}/announcements/read-state`)
	expect((await state.json()).confirmed).toContain(uuid)

	// Only the author or an administrator reads the confirmations.
	const refused = await reader!.api.get(`${API}/announcements/${uuid}/receipts`)
	expect(refused.status()).toBe(403)
	const report = await admin.get(`${API}/announcements/${uuid}/receipts`)
	expect(report.status(), await report.text()).toBe(200)
	const rows = (await report.json()).rows as Array<{ userId: string, status: string }>
	expect(rows.find((row) => row.userId === reader!.username)?.status).toBe('acknowledged')
})
