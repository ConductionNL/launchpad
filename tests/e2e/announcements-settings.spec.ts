/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Announcements are managed in settings (announcements-managed-in-settings):
 * a delegated manager writes and schedules one on the Announcements tab of the
 * admin settings page; a reader sees it in the widget (no New, like or comment
 * button there) and presses the check; the manager sees 1 seen and 1
 * confirmed via the check; on a notice the check keeps the banner, marked
 * read, and the x closes it; the announcement's own view holds likes and
 * comments; a widget that asks for confirmation is listed on the tab, and the
 * dashboard offers no Read receipts.
 *
 * @e2e openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { addTextWidget, createPersonalDashboard, readUserDashboardsFlag, setUserDashboardsFlag, updatePlacement } from './fixtures/acknowledgements.ts'
import { loginAs } from './fixtures/secondary-user.ts'
import { addToGroup, adminContext, API, createGroup, createPrincipal, removeGroup, removePrincipal } from './support/principals.ts'

const STAMP = Date.now().toString(36)
const DAY_MS = 24 * 3600 * 1000
const SETTINGS_PAGE = '/index.php/settings/admin/launchpad'
const ANNOUNCEMENTS_CLASS = 'OCA\\LaunchPad\\Settings\\LaunchPadAnnouncementsAdmin'
const DELEGATION_URL = '/index.php/apps/settings/settings/authorizedgroups/saveSettings'

let admin: APIRequestContext
let group = ''
let userDashboardsWere = false
let femke: Principal | null = null
const readers: Principal[] = []
const written: string[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

/**
 * A time some days from now, as ISO and as a `datetime-local` value.
 *
 * @param days days from now.
 * @param hour the hour of the day.
 * @return the values.
 */
function ahead(days: number, hour = 8): { iso: string, local: string } {
	const at = new Date(Date.now() + days * DAY_MS)
	at.setHours(hour, 0, 0, 0)
	const pad = (n: number) => String(n).padStart(2, '0')
	return { iso: at.toISOString(), local: `${at.getFullYear()}-${pad(at.getMonth() + 1)}-${pad(at.getDate())}T${pad(hour)}:00` }
}

/**
 * A reader with a dashboard of their own that holds the Announcements widget.
 *
 * @param prefix the account prefix.
 * @return the reader.
 */
async function readerWithWidget(prefix: string): Promise<Principal> {
	const reader = await createPrincipal(admin, prefix)
	readers.push(reader)
	const dashboard = await createPersonalDashboard(reader, `E2E ${prefix} ${STAMP}`)
	const placed = await reader.api.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: { widgetId: 'announcements', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 6, content: {} },
	})
	expect(placed.status(), await placed.text()).toBeLessThan(300)
	const start = await reader.api.post(`${API}/dashboards/default`, { data: { uuid: dashboard.uuid } })
	expect(start.status(), await start.text()).toBeLessThan(300)
	return reader
}

/**
 * Publish an announcement as the manager, shown from now for two weeks.
 *
 * @param data the announcement's fields.
 * @return its uuid.
 */
async function publishNow(data: Record<string, unknown>): Promise<string> {
	const created = await femke!.api.post(`${API}/announcements`, {
		data: { expiresAt: ahead(14, 17).iso, ...data },
	})
	expect(created.status(), await created.text()).toBe(201)
	const uuid = String((await created.json()).uuid)
	written.push(uuid)
	const published = await femke!.api.post(`${API}/announcements/${uuid}/publish`)
	expect(published.status(), await published.text()).toBeLessThan(300)
	return uuid
}

/**
 * Open the reader's start page in a browser of their own.
 *
 * @param browser the browser.
 * @param reader the reader.
 * @return the page and a close function.
 */
async function openAs(browser: Browser, reader: Principal): Promise<{ page: Page, close: () => Promise<void> }> {
	const { context, page } = await loginAs(browser, reader.username, reader.password)
	await page.goto('/index.php/apps/launchpad/')
	return { page, close: () => context.close() }
}

test.beforeAll(async () => {
	admin = await adminContext()
	userDashboardsWere = await readUserDashboardsFlag(admin)
	await setUserDashboardsFlag(admin, true)
	group = await createGroup(admin, `e2e-ann-managers-${STAMP}`)
	femke = await createPrincipal(admin, 'e2e-ann-femke')
	await addToGroup(admin, femke.username, group)
	const delegated = await admin.post(DELEGATION_URL, {
		data: { newGroups: [{ gid: group, displayName: group }], class: ANNOUNCEMENTS_CLASS },
	})
	expect(delegated.ok(), 'the announcements setting is delegated').toBeTruthy()
})

test.afterAll(async () => {
	if (!admin) {
		return
	}
	for (const uuid of written) {
		await admin.delete(`${API}/announcements/${uuid}`).catch(() => undefined)
	}
	await admin.post(DELEGATION_URL, { data: { newGroups: [], class: ANNOUNCEMENTS_CLASS } }).catch(() => undefined)
	for (const reader of readers) {
		await removePrincipal(admin, reader).catch(() => undefined)
	}
	await removePrincipal(admin, femke).catch(() => undefined)
	await removeGroup(admin, group).catch(() => undefined)
	await setUserDashboardsFlag(admin, userDashboardsWere).catch(() => undefined)
	await admin.dispose()
})

// @e2e announcements::scheduling-for-later
// @e2e announcements::write-a-news-item
test('a delegated manager writes and schedules an announcement on the Announcements tab', async ({ browser }) => {
	const { context, page } = await loginAs(browser, femke!.username, femke!.password)
	try {
		await page.goto(`${SETTINGS_PAGE}#announcements`)
		await page.getByTestId('announcement-new').click({ timeout: 60_000 })
		const editor = page.locator('[data-test="announcement-editor"]')
		await expect(editor).toBeVisible()
		const title = `Inloopspreekuur privacy ${STAMP}`
		await editor.locator('[data-test="editor-title"] input, input[data-test="editor-title"]').first().fill(title)
		await expect(editor.locator('[data-test="editor-preview-pane"] [data-test="announcement-card"] h4')).toHaveText(title)
		await editor.locator('[data-test="editor-publish-at"]').fill(ahead(3).local)
		await editor.locator('[data-test="editor-expires-at"]').fill(ahead(10, 17).local)
		await expect(editor.locator('[data-test="editor-publish"]')).toHaveText('Schedule')

		const created = page.waitForResponse((res) => res.url().endsWith('/api/announcements') && res.request().method() === 'POST')
		await editor.locator('[data-test="editor-publish"]').click()
		const response = await created
		expect(response.status(), await response.text()).toBe(201)
		written.push(String((await response.json()).uuid))
		await expect(page.getByTestId('announcement-row').filter({ hasText: title })).toContainText('Scheduled')
	} finally {
		await context.close()
	}

	// A reader may not write one.
	const reader = await readerWithWidget('e2e-ann-pieter')
	const refused = await reader.api.post(`${API}/announcements`, { data: { kind: 'news', title: 'x', expiresAt: ahead(2).iso } })
	expect(refused.status()).toBe(403)
})

// @e2e announcements::pieters-widget-has-no-management-or-social-buttons
// @e2e announcements::the-check-confirms
// @e2e announcements::151-of-164-seen
test('a reader presses the check in the widget, and the manager sees 1 seen and 1 confirmed via the check', async ({ browser }) => {
	const title = `Nieuwe werkwijze Woo-verzoeken ${STAMP}`
	const uuid = await publishNow({ kind: 'news', title, requiresConfirmation: true, confirmBefore: ahead(5).iso, allowComments: true, category: 'Privacy' })
	const pieter = readers[0] ?? await readerWithWidget('e2e-ann-pieter')
	const { page, close } = await openAs(browser, pieter)
	try {
		const widget = page.locator('[data-test="announcements-widget"]')
		await expect(widget).toBeVisible({ timeout: 60_000 })
		const card = widget.locator('[data-test="announcement-card"]').filter({ hasText: title })
		await expect(card).toBeVisible()
		await expect(widget.locator('[data-test="announcements-new"]')).toHaveCount(0)
		await expect(card.locator('[data-test="announcement-like"], [data-test="announcement-comments"]')).toHaveCount(0)
		await expect(widget.locator('[data-test="announcements-follow"]')).toHaveCount(0)
		await expect(card.locator('[data-test="announcement-confirm-before"]')).toContainText('Confirm before')

		const confirmed = page.waitForResponse((res) => res.url().endsWith(`/api/announcements/${uuid}/confirm`))
		await card.locator('[data-test="announcement-confirm-read"]').click()
		expect((await confirmed).status()).toBe(200)
		await expect(card.locator('[data-test="announcement-confirm-read"]')).toHaveAttribute('aria-label', 'Read')
		await expect(card).toBeVisible()

		// The view holds the likes and the comments.
		await card.locator('[data-test="announcement-open"]').click()
		const view = page.locator('[data-test="announcement-modal"]')
		await expect(view).toBeVisible()
		await expect(view.locator('[data-test="announcement-like"]')).toBeVisible()
		await expect(view.locator('[data-test="announcement-comment-input"]')).toBeVisible()
		await expect(view.locator('[data-test="announcements-follow"]')).toContainText('Privacy')
	} finally {
		await close()
	}

	const report = await femke!.api.get(`${API}/announcements/${uuid}/receipts`)
	expect(report.status(), await report.text()).toBe(200)
	const figures = await report.json()
	const row = (figures.rows as Array<{ userId: string, seenAt: string | null, confirmedVia: string | null }>).find((r) => r.userId === pieter.username)
	expect(row?.seenAt).toBeTruthy()
	expect(row?.confirmedVia).toBe('check')
	expect(figures.confirmedViaCheck).toBeGreaterThanOrEqual(1)
	expect((await pieter.api.get(`${API}/announcements/${uuid}/receipts`)).status()).toBe(403)

	const counts = await femke!.api.get(`${API}/announcements/receipt-counts`, { params: { 'uuids[]': uuid } })
	expect(counts.status(), await counts.text()).toBe(200)
	expect((await counts.json()).counts[uuid]).toEqual({ seen: expect.any(Number), confirmed: expect.any(Number) })
})

// @e2e announcements::the-check-keeps-the-banner-the-x-hides-it
// @e2e announcements::the-x-on-a-notice-confirms-it
test('on a notice the check keeps the banner, marked read, and the x closes it', async ({ browser }) => {
	const title = `Onderhoud zaaksysteem ${STAMP}`
	const uuid = await publishNow({ kind: 'notice', title, level: 'warning' })
	const sanne = await readerWithWidget('e2e-ann-sanne')
	const kees = await readerWithWidget('e2e-ann-kees')

	const first = await openAs(browser, sanne)
	try {
		const banner = first.page.locator('[data-test="notice-banner"]').filter({ hasText: title })
		await expect(banner).toBeVisible({ timeout: 60_000 })
		await banner.locator('[data-test="notice-confirm"]').click()
		await expect(banner.locator('[data-test="notice-confirm"]')).toHaveAttribute('aria-label', 'Read')
		await first.page.reload()
		await expect(first.page.locator('[data-test="notice-banner"]').filter({ hasText: title })).toBeVisible({ timeout: 60_000 })
	} finally {
		await first.close()
	}

	const second = await openAs(browser, kees)
	try {
		const banner = second.page.locator('[data-test="notice-banner"]').filter({ hasText: title })
		await expect(banner).toBeVisible({ timeout: 60_000 })
		const closed = second.page.waitForResponse((res) => res.url().endsWith(`/api/announcements/${uuid}/confirm`))
		await banner.locator('[data-test="notice-dismiss"]').click()
		expect((await closed).status()).toBe(200)
		await expect(banner).toHaveCount(0)
		await second.page.reload()
		await expect(second.page.locator('[data-test="announcements-widget"]')).toBeVisible({ timeout: 60_000 })
		await expect(second.page.locator('[data-test="notice-banner"]').filter({ hasText: title })).toHaveCount(0)
	} finally {
		await second.close()
	}

	const report = await (await femke!.api.get(`${API}/announcements/${uuid}/receipts`)).json()
	const via = (who: Principal) => (report.rows as Array<{ userId: string, confirmedVia: string | null }>).find((r) => r.userId === who.username)?.confirmedVia
	expect(via(sanne)).toBe('check')
	expect(via(kees)).toBe('x')
})

// @e2e dashboard-acknowledgements::read-the-report
// @e2e dashboard-acknowledgements::the-key-reaches-the-dashboard
test('a widget that asks for confirmation is listed on the tab, and the dashboard has no Read receipts', async ({ browser }) => {
	const owner = readers[0] ?? await readerWithWidget('e2e-ann-owner')
	const dashboard = (await (await owner.api.get(`${API}/dashboards/default`)).json())
	const dashboardId = Number(dashboard.id ?? dashboard.dashboard?.id ?? dashboard.data?.id)
	const text = `New expense rules ${STAMP}`
	const placement = await addTextWidget(owner.api, dashboardId, text, 6)
	const asked = await updatePlacement(owner.api, placement, { requiresAcknowledgement: 1, acknowledgementPrompt: 'I have read the expense rules', customTitle: text })
	expect(asked.status(), await asked.text()).toBeLessThan(300)

	const list = await femke!.api.get('/index.php/apps/launchpad/api/acknowledgements/widgets')
	expect(list.status(), await list.text()).toBe(200)
	const row = ((await list.json()).widgets as Array<{ title: string, announcementKey: string }>).find((w) => w.title === text)
	expect(row, 'the widget is listed').toBeTruthy()
	expect((await owner.api.get(`${API}/acknowledgements/report/${row!.announcementKey}`)).status()).toBe(403)

	const { context, page: manager } = await loginAs(browser, femke!.username, femke!.password)
	try {
		await manager.goto(`${SETTINGS_PAGE}#announcements`)
		const widgets = manager.getByTestId('admin-acknowledged-widgets')
		const listed = widgets.getByTestId('acknowledged-widget-row').filter({ hasText: text })
		await expect(listed).toBeVisible({ timeout: 60_000 })
		await listed.getByTestId('acknowledged-widget-open').click()
		await expect(manager.getByTestId('ack-report-eyebrow')).toContainText('Widget')
	} finally {
		await context.close()
	}

	const ownerView = await openAs(browser, owner)
	try {
		await ownerView.page.getByTestId('dashboard-more-menu').click({ timeout: 60_000 })
		await expect(ownerView.page.getByText('Read receipts', { exact: true })).toHaveCount(0)
	} finally {
		await ownerView.close()
	}
})
