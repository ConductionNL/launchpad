/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Dashboard publication state: draft, published and scheduled, who sees
 * which, the publish / unpublish / schedule actions, the store patching its
 * copy in place, and the schedule screen with go-live and take-down times
 * (openspec/specs/dashboards/spec.md, REQ-DASH-031..037, REQ-SCHEDUI-001..002).
 *
 * Alice owns the dashboards, Bob is the colleague she shares them with (so
 * the only thing keeping a dashboard from Bob is its publication state), and
 * the administrator is the instance admin. The API contract scenarios drive
 * the API; the store and schedule-screen scenarios drive the start page's
 * More menu and the schedule dialog. "Becomes visible" and "comes down" use a
 * go-live or take-down time a few seconds ahead on the SERVER's clock
 * (measured, see serverClockOffset) and poll Bob's listing until it passes.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::timestamp-formats
 *   @e2e dashboards::scheduled-state-requires-publishat
 *   @e2e dashboards::publish-a-draft-dashboard
 *   @e2e dashboards::publish-is-idempotent
 *   @e2e dashboards::only-owner-or-admin-can-publish
 *   @e2e dashboards::unpublish-a-published-dashboard
 *   @e2e dashboards::unpublish-hides-dashboard-from-non-owners
 *   @e2e dashboards::unpublish-is-idempotent
 *   @e2e dashboards::schedule-a-draft-dashboard
 *   @e2e dashboards::cannot-schedule-with-past-date
 *   @e2e dashboards::cannot-schedule-with-empty-unparseable-publishat
 *   @e2e dashboards::scheduled-dashboard-becomes-visible-when-publishat-passes-lazy-materialisation
 *   @e2e dashboards::future-scheduled-dashboard-hidden-from-non-owners
 *   @e2e dashboards::new-dashboards-default-to-draft-despite-the-column-default
 *   @e2e dashboards::draft-dashboard-hidden-from-other-users
 *   @e2e dashboards::draft-dashboard-visible-to-owner
 *   @e2e dashboards::admin-can-see-draft-dashboards-of-other-users
 *   @e2e dashboards::publish-unpublish-schedule-actions-patch-local-state
 *   @e2e dashboards::schedule-go-live
 *   @e2e dashboards::past-time-refused
 *   @e2e dashboards::take-down-passes
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { DashboardRow } from './support/dashboardHierarchy.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	allowManyPersonalDashboards,
	createDashboard,
	parseStamp,
	publication,
	readDashboard,
	removeTree,
	serverClockOffset,
	STAMP,
	visibleRows,
} from './support/dashboardHierarchy.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const T = Date.now()
const PAST_MESSAGE = 'publishAt must be a future timestamp'
const FAR_FUTURE = '2099-04-01T10:00:00Z'
const FAR_FUTURE_STORED = '2099-04-01 10:00:00'

let admin: APIRequestContext
let restoreSettings: (() => Promise<void>) | null = null
let alice: Principal | null = null
let bob: Principal | null = null
let offset = 0
const created: number[] = []

/**
 * A fresh dashboard of Alice's, shared with Bob at view level, still a draft.
 *
 * @param label the readable part of the name.
 * @return the dashboard.
 */
async function aliceDashboard(label: string): Promise<DashboardRow> {
	const dash = await createDashboard(alice!.api, { name: `${label} ${T}` })
	created.push(dash.id)
	await shareWith(alice!.api, dash.id, bob!.username, 'view_only')
	return dash
}

/**
 * The row of a dashboard in a principal's visible listing, if any.
 *
 * @param api the principal's context.
 * @param uuid the dashboard.
 * @return the row, or undefined.
 */
async function visibleRow(
	api: APIRequestContext,
	uuid: string,
): Promise<DashboardRow | undefined> {
	return (await visibleRows(api)).find((row) => row.uuid === uuid)
}

/**
 * The dashboard as its owner reads it now.
 *
 * @param dash the dashboard.
 * @return the stored row.
 */
async function reread(dash: DashboardRow): Promise<DashboardRow> {
	const read = await readDashboard(alice!.api, dash.id)
	expect(read.status).toBe(200)
	return read.dashboard!
}

/**
 * An ISO time `seconds` ahead of the server's clock.
 *
 * @param seconds how far ahead.
 * @return the ISO-8601 string.
 */
function serverSoon(seconds: number): string {
	return new Date(Date.now() + offset + seconds * 1000).toISOString()
}

/**
 * Log Alice in on the start page with the given dashboard active.
 *
 * @param browser the browser.
 * @param dash the dashboard to open.
 * @return the context and page.
 */
async function openAs(browser: Browser, dash: DashboardRow) {
	const activate = await alice!.api.post(`${API}/dashboard/${dash.id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const preference = await alice!.api.post(`${API}/dashboards/active`, {
		data: { uuid: dash.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)

	const session = await loginAs(browser, alice!.username, alice!.password)
	await session.page.goto('/index.php/apps/launchpad')
	await expect(
		session.page.getByTestId('launchpad-dashboard-region'),
	).toHaveAttribute('data-dashboard-uuid', dash.uuid, { timeout: 30_000 })
	return session
}

/**
 * Open the More menu and answer the locator of one of its items.
 *
 * @param page the start page.
 * @param id the item id.
 * @return the item.
 */
async function moreItem(page: Page, id: string) {
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
	return page.locator(`[data-testid="cn-grouped-menu-item-${id}"]`)
}

test.describe('dashboards — publication state (REQ-DASH-031..037, REQ-SCHEDUI-001..002)', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	test.beforeAll(async () => {
		admin = await adminContext()
		restoreSettings = await allowManyPersonalDashboards(admin)
		alice = await createPrincipal(admin, 'e2e-pub-alice')
		bob = await createPrincipal(admin, 'e2e-pub-bob')
		await assertActingAs(alice.api, alice.username)
		await assertActingAs(bob.api, bob.username)
		offset = await serverClockOffset(alice.api, `Clock ${T}`)
	})

	test.afterAll(async () => {
		for (const id of created) {
			await removeTree(alice!.api, id)
		}
		await removePrincipal(admin, alice)
		await removePrincipal(admin, bob)
		await restoreSettings?.()
		await admin?.dispose()
	})

	// ── REQ-DASH-031 ────────────────────────────────────────────────────

	// @e2e dashboards::timestamp-formats
	test('publication timestamps serialise as Y-m-d H:i:s, and absent ones as explicit nulls', async () => {
		const published = await aliceDashboard('Stamp published')
		const p = (await publication(alice!.api, published.uuid, 'publish'))
			.dashboard!
		expect(p.publishedAt).toMatch(STAMP)
		expect(Object.hasOwn(p, 'publishAt')).toBe(true)
		expect(p.publishAt).toBeNull()

		const scheduled = await aliceDashboard('Stamp scheduled')
		const s = (
			await publication(alice!.api, scheduled.uuid, 'schedule', {
				publishAt: FAR_FUTURE,
			})
		).dashboard!
		expect(s.publishAt).toMatch(STAMP)
		expect(Object.hasOwn(s, 'publishedAt')).toBe(true)
		expect(s.publishedAt).toBeNull()

		// The same shapes on a plain read.
		expect((await reread(published)).publishedAt).toMatch(STAMP)
		expect((await reread(scheduled)).publishedAt).toBeNull()
	})

	// @e2e dashboards::scheduled-state-requires-publishat
	test('scheduled means a publishAt strictly in the future; past or missing is a 400', async () => {
		const dash = await aliceDashboard('Needs publishAt')

		for (const body of [
			{},
			{ publishAt: null },
			{ publishAt: '2020-01-01T00:00:00Z' },
		]) {
			const refused = await publication(
				alice!.api,
				dash.uuid,
				'schedule',
				body,
			)
			expect(refused.status, JSON.stringify(body)).toBe(400)
			expect(refused.body.message).toBe(PAST_MESSAGE)
		}
		expect((await reread(dash)).publicationStatus).toBe('draft')

		const scheduled = await publication(alice!.api, dash.uuid, 'schedule', {
			publishAt: serverSoon(3600),
		})
		expect(scheduled.status).toBe(200)
		expect(scheduled.dashboard?.publicationStatus).toBe('scheduled')
		expect(parseStamp(String(scheduled.dashboard?.publishAt))).toBeGreaterThan(
			Date.now() + offset,
		)
	})

	// ── REQ-DASH-032 ────────────────────────────────────────────────────

	// @e2e dashboards::publish-a-draft-dashboard
	test('publishing a draft makes it published, stamps publishedAt and clears publishAt', async () => {
		const dash = await aliceDashboard('Publish draft')
		expect(dash.publicationStatus).toBe('draft')
		expect(dash.publishedAt).toBeNull()

		const res = await publication(alice!.api, dash.uuid, 'publish')
		expect(res.status).toBe(200)
		expect(res.dashboard?.uuid).toBe(dash.uuid)
		expect(res.dashboard?.publicationStatus).toBe('published')
		expect(res.dashboard?.publishAt).toBeNull()
		expect(res.dashboard?.publishedAt).toMatch(STAMP)
		expect(
			Math.abs(
				parseStamp(String(res.dashboard?.publishedAt))
					- (Date.now() + offset),
			),
		).toBeLessThan(60_000)

		const stored = await reread(dash)
		expect(stored.publicationStatus).toBe('published')
		expect(stored.publishedAt).toBe(res.dashboard?.publishedAt)

		// A pending go-live time is cleared by publishing now.
		const pending = await aliceDashboard('Publish scheduled')
		await publication(alice!.api, pending.uuid, 'schedule', {
			publishAt: FAR_FUTURE,
		})
		const now = await publication(alice!.api, pending.uuid, 'publish')
		expect(now.dashboard?.publicationStatus).toBe('published')
		expect(now.dashboard?.publishAt).toBeNull()
	})

	// @e2e dashboards::publish-is-idempotent
	test('publishing again answers 200 and leaves publishedAt as it was', async () => {
		const dash = await aliceDashboard('Publish twice')
		const first = (await publication(alice!.api, dash.uuid, 'publish'))
			.dashboard!
		// Let the server clock move past the first stamp's second.
		await new Promise((resolve) => setTimeout(resolve, 1500))

		const again = await publication(alice!.api, dash.uuid, 'publish')
		expect(again.status).toBe(200)
		expect(again.dashboard?.publicationStatus).toBe('published')
		expect(again.dashboard?.publishedAt).toBe(first.publishedAt)
		expect((await reread(dash)).publishedAt).toBe(first.publishedAt)
	})

	// @e2e dashboards::only-owner-or-admin-can-publish
	test('a non-owner is refused 403 "Forbidden: owner or admin only"; the administrator may publish', async () => {
		const dash = await aliceDashboard('Publish guard')

		const refused = await publication(bob!.api, dash.uuid, 'publish')
		expect(refused.status).toBe(403)
		expect(refused.body.error).toBe('Forbidden: owner or admin only')
		expect((await reread(dash)).publicationStatus).toBe('draft')

		const byAdmin = await publication(admin, dash.uuid, 'publish')
		expect(byAdmin.status).toBe(200)
		expect(byAdmin.dashboard?.publicationStatus).toBe('published')
		expect((await reread(dash)).publicationStatus).toBe('published')
	})

	// ── REQ-DASH-033 ────────────────────────────────────────────────────

	// @e2e dashboards::unpublish-a-published-dashboard
	test('unpublishing returns to draft, keeps publishedAt and clears publishAt', async () => {
		const dash = await aliceDashboard('Unpublish')
		const published = (await publication(alice!.api, dash.uuid, 'publish'))
			.dashboard!

		const res = await publication(alice!.api, dash.uuid, 'unpublish')
		expect(res.status).toBe(200)
		expect(res.dashboard?.publicationStatus).toBe('draft')
		expect(res.dashboard?.publishedAt).toBe(published.publishedAt)
		expect(res.dashboard?.publishAt).toBeNull()

		const stored = await reread(dash)
		expect(stored.publicationStatus).toBe('draft')
		expect(stored.publishedAt).toBe(published.publishedAt)
		expect(stored.publishAt).toBeNull()
	})

	// @e2e dashboards::unpublish-hides-dashboard-from-non-owners
	test('after unpublishing, the colleague no longer sees it and the owner still does', async () => {
		const dash = await aliceDashboard('Unpublish hides')
		await publication(alice!.api, dash.uuid, 'publish')
		expect(await visibleRow(bob!.api, dash.uuid)).toBeDefined()

		await publication(alice!.api, dash.uuid, 'unpublish')

		expect(await visibleRow(bob!.api, dash.uuid)).toBeUndefined()
		expect((await visibleRow(alice!.api, dash.uuid))?.publicationStatus).toBe(
			'draft',
		)
	})

	// @e2e dashboards::unpublish-is-idempotent
	test('unpublishing a draft answers 200 and changes nothing', async () => {
		const dash = await aliceDashboard('Unpublish draft')
		const before = await reread(dash)

		const res = await publication(alice!.api, dash.uuid, 'unpublish')
		expect(res.status).toBe(200)
		expect(res.dashboard?.publicationStatus).toBe('draft')

		const after = await reread(dash)
		expect(after.publicationStatus).toBe(before.publicationStatus)
		expect(after.publishedAt).toBe(before.publishedAt)
		expect(after.publishAt).toBe(before.publishAt)
		expect(after.updatedAt).toBe(before.updatedAt)
	})

	// ── REQ-DASH-034 ────────────────────────────────────────────────────

	// @e2e dashboards::schedule-a-draft-dashboard
	test('scheduling a draft stores the go-live time in the storage format', async () => {
		const dash = await aliceDashboard('Schedule draft')

		const res = await publication(alice!.api, dash.uuid, 'schedule', {
			publishAt: FAR_FUTURE,
		})
		expect(res.status).toBe(200)
		expect(res.dashboard?.publicationStatus).toBe('scheduled')
		expect(res.dashboard?.publishAt).toBe(FAR_FUTURE_STORED)

		const stored = await reread(dash)
		expect(stored.publicationStatus).toBe('scheduled')
		expect(stored.publishAt).toBe(FAR_FUTURE_STORED)
	})

	// @e2e dashboards::cannot-schedule-with-past-date
	test('a past go-live time is refused 400, changes nothing, and the message exists in English and Dutch', async ({
		browser,
	}) => {
		const dash = await aliceDashboard('Schedule past')

		const refused = await publication(alice!.api, dash.uuid, 'schedule', {
			publishAt: '2020-03-19T10:00:00Z',
		})
		expect(refused.status).toBe(400)
		expect(refused.body.message).toBe(PAST_MESSAGE)
		const stored = await reread(dash)
		expect(stored.publicationStatus).toBe('draft')
		expect(stored.publishAt).toBeNull()

		// The l10n entry: the frontend catalogue LaunchPad registers resolves
		// the message in English for an English user and in Dutch for a
		// Dutch one.
		const translate = async (language: string): Promise<string> => {
			const set = await admin.put(
				`/ocs/v1.php/cloud/users/${encodeURIComponent(alice!.username)}`,
				{
					form: { key: 'language', value: language },
				},
			)
			expect(set.ok(), await set.text()).toBe(true)
			const { context, page } = await loginAs(
				browser,
				alice!.username,
				alice!.password,
			)
			try {
				await page.goto('/index.php/apps/launchpad')
				await expect(
					page.getByTestId('launchpad-dashboard-region'),
				).toBeVisible({ timeout: 30_000 })
				return await page.evaluate(
					(key) =>
						(
							window as unknown as {
								t: (app: string, text: string) => string
							}
						).t('launchpad', key),
					PAST_MESSAGE,
				)
			} finally {
				await context.close()
			}
		}
		try {
			expect(await translate('nl')).toBe(
				'publishAt moet een toekomstige datum zijn',
			)
			expect(await translate('en')).toBe(PAST_MESSAGE)
		} finally {
			await admin.put(
				`/ocs/v1.php/cloud/users/${encodeURIComponent(alice!.username)}`,
				{
					form: { key: 'language', value: 'en' },
				},
			)
		}
	})

	// @e2e dashboards::cannot-schedule-with-empty-unparseable-publishat
	test('an empty or unparseable go-live time is refused 400 with the same message', async () => {
		const dash = await aliceDashboard('Schedule garbage')

		for (const body of [{}, { publishAt: 'not-a-date' }]) {
			const refused = await publication(
				alice!.api,
				dash.uuid,
				'schedule',
				body,
			)
			expect(refused.status, JSON.stringify(body)).toBe(400)
			expect(refused.body.message).toBe(PAST_MESSAGE)
		}
		const stored = await reread(dash)
		expect(stored.publicationStatus).toBe('draft')
		expect(stored.publishAt).toBeNull()
	})

	// @e2e dashboards::scheduled-dashboard-becomes-visible-when-publishat-passes-lazy-materialisation
	test('once its go-live time passes, a scheduled dashboard reads as published to everyone', async () => {
		const dash = await aliceDashboard('Lazy go-live')
		const scheduled = await publication(alice!.api, dash.uuid, 'schedule', {
			publishAt: serverSoon(6),
		})
		expect(scheduled.dashboard?.publicationStatus).toBe('scheduled')
		expect(await visibleRow(bob!.api, dash.uuid)).toBeUndefined()

		await expect
			.poll(
				async () =>
					(await visibleRow(bob!.api, dash.uuid))?.publicationStatus
					?? 'absent',
				{
					timeout: 40_000,
					intervals: [1000],
				},
			)
			.toBe('published')
	})

	// @e2e dashboards::future-scheduled-dashboard-hidden-from-non-owners
	test('a dashboard scheduled for the future is hidden from the colleague, and shown to the owner as scheduled', async () => {
		const dash = await aliceDashboard('Future go-live')
		await publication(alice!.api, dash.uuid, 'schedule', {
			publishAt: FAR_FUTURE,
		})

		expect(await visibleRow(bob!.api, dash.uuid)).toBeUndefined()
		const own = await visibleRow(alice!.api, dash.uuid)
		expect(own?.publicationStatus).toBe('scheduled')
		expect(own?.publishAt).toBe(FAR_FUTURE_STORED)
	})

	// ── REQ-DASH-035 ────────────────────────────────────────────────────

	// @e2e dashboards::new-dashboards-default-to-draft-despite-the-column-default
	test('a new dashboard is a draft, hidden from a colleague until it is published', async () => {
		const dash = await aliceDashboard('Born draft')
		expect(dash.publicationStatus).toBe('draft')
		expect((await reread(dash)).publicationStatus).toBe('draft')
		expect(await visibleRow(bob!.api, dash.uuid)).toBeUndefined()

		// The share itself works: publishing is what lets Bob see it.
		await publication(alice!.api, dash.uuid, 'publish')
		expect(await visibleRow(bob!.api, dash.uuid)).toBeDefined()
	})

	// ── REQ-DASH-036 ────────────────────────────────────────────────────

	// @e2e dashboards::draft-dashboard-hidden-from-other-users
	test("a draft is not in another user's visible listing", async () => {
		const dash = await aliceDashboard('Draft hidden')
		expect(await visibleRow(bob!.api, dash.uuid)).toBeUndefined()
	})

	// @e2e dashboards::draft-dashboard-visible-to-owner
	test("a draft is in its owner's visible listing, marked draft", async () => {
		const dash = await aliceDashboard('Draft own')
		expect((await visibleRow(alice!.api, dash.uuid))?.publicationStatus).toBe(
			'draft',
		)
	})

	// @e2e dashboards::admin-can-see-draft-dashboards-of-other-users
	test("the administrator sees another user's draft", async () => {
		const dash = await createDashboard(alice!.api, {
			name: `Draft for admin ${T}`,
		})
		created.push(dash.id)
		expect(dash.publicationStatus).toBe('draft')

		const row = await visibleRow(admin, dash.uuid)
		expect(row).toBeDefined()
		expect(row?.publicationStatus).toBe('draft')
		// Control: an ordinary user does not.
		expect(await visibleRow(bob!.api, dash.uuid)).toBeUndefined()
	})

	// ── REQ-DASH-037 ────────────────────────────────────────────────────

	// @e2e dashboards::publish-unpublish-schedule-actions-patch-local-state
	test('publishing from the menu updates the page in place, without reloading the dashboard list', async ({
		browser,
	}) => {
		const dash = await aliceDashboard('Store patch')
		const { context, page } = await openAs(browser, dash)
		try {
			const listReads: string[] = []
			page.on('request', (req) => {
				const url = req.url()
				if (
					req.method() === 'GET'
					&& /\/api\/dashboards(\/visible)?(\?|$)|\/api\/dashboard(\?|$)/.test(
						url,
					)
				) {
					listReads.push(url)
				}
			})

			// Publish: the menu now offers Unpublish.
			const publishItem = await moreItem(page, 'publish')
			const published = page.waitForResponse((res) =>
				res.url().endsWith(`/dashboards/${dash.uuid}/publish`),
			)
			await publishItem.click()
			expect((await published).status()).toBe(200)
			await expect(await moreItem(page, 'unpublish')).toBeVisible()
			await page.keyboard.press('Escape')

			// Unpublish: back to Publish.
			const unpublishItem = await moreItem(page, 'unpublish')
			const unpublished = page.waitForResponse((res) =>
				res.url().endsWith(`/dashboards/${dash.uuid}/unpublish`),
			)
			await unpublishItem.click()
			expect((await unpublished).status()).toBe(200)
			await expect(await moreItem(page, 'publish')).toBeVisible()
			await page.keyboard.press('Escape')

			// Schedule: the note appears from the patched copy.
			const scheduleItem = await moreItem(page, 'schedule')
			await scheduleItem.click()
			const dialog = page
				.getByRole('dialog')
				.filter({ has: page.getByTestId('schedule-go-live') })
			await dialog.getByTestId('schedule-go-live').fill('2099-04-01T10:00')
			const scheduled = page.waitForResponse((res) =>
				res.url().endsWith(`/dashboards/${dash.uuid}/schedule`),
			)
			await dialog
				.getByRole('button', { name: 'Schedule', exact: true })
				.click()
			expect((await scheduled).status()).toBe(200)
			await expect(page.getByTestId('publication-note')).toContainText(
				'Goes live on',
			)

			expect(listReads, 'the dashboard list was fetched again').toEqual([])
			expect((await reread(dash)).publicationStatus).toBe('scheduled')
		} finally {
			await context.close()
		}
	})

	// ── REQ-SCHEDUI-001 ─────────────────────────────────────────────────

	// @e2e dashboards::schedule-go-live
	test('scheduling go-live for tomorrow 09:00 shows "Goes live on" and hides it from colleagues', async ({
		browser,
	}) => {
		const dash = await aliceDashboard('Open day')
		await publication(alice!.api, dash.uuid, 'publish')
		expect(await visibleRow(bob!.api, dash.uuid)).toBeDefined()

		const { context, page } = await openAs(browser, dash)
		try {
			const { local, shown } = await page.evaluate(() => {
				const when = new Date()
				when.setDate(when.getDate() + 1)
				when.setHours(9, 0, 0, 0)
				const pad = (value: number) => String(value).padStart(2, '0')
				return {
					local: `${when.getFullYear()}-${pad(when.getMonth() + 1)}-${pad(when.getDate())}T09:00`,
					shown: when.toLocaleString(undefined, {
						dateStyle: 'medium',
						timeStyle: 'short',
					}),
				}
			})

			await (await moreItem(page, 'schedule')).click()
			const dialog = page
				.getByRole('dialog')
				.filter({ has: page.getByTestId('schedule-go-live') })
			await dialog.getByTestId('schedule-go-live').fill(local)
			await dialog
				.getByRole('button', { name: 'Schedule', exact: true })
				.click()
			await expect(dialog).toBeHidden()

			await expect(page.getByTestId('publication-note')).toHaveText(
				`Goes live on ${shown}.`,
			)
			expect((await reread(dash)).publicationStatus).toBe('scheduled')
			expect(await visibleRow(bob!.api, dash.uuid)).toBeUndefined()
		} finally {
			await context.close()
		}
	})

	// @e2e dashboards::past-time-refused
	test('a go-live time in the past is refused in the dialog and nothing changes', async ({
		browser,
	}) => {
		const dash = await aliceDashboard('Past refused')
		const before = await reread(dash)

		const { context, page } = await openAs(browser, dash)
		try {
			await (await moreItem(page, 'schedule')).click()
			const dialog = page
				.getByRole('dialog')
				.filter({ has: page.getByTestId('schedule-go-live') })
			await dialog.getByTestId('schedule-go-live').fill('2020-03-19T09:00')
			const refused = page.waitForResponse((res) =>
				res.url().endsWith(`/dashboards/${dash.uuid}/schedule`),
			)
			await dialog
				.getByRole('button', { name: 'Schedule', exact: true })
				.click()
			expect((await refused).status()).toBe(400)

			await expect(dialog.getByTestId('schedule-error')).toHaveText(
				'Choose a time in the future.',
			)
			await expect(dialog).toBeVisible()
			await expect(page.getByTestId('publication-note')).toHaveCount(0)
		} finally {
			await context.close()
		}

		const after = await reread(dash)
		expect(after.publicationStatus).toBe(before.publicationStatus)
		expect(after.publishAt).toBe(before.publishAt)
		expect(after.unpublishAt).toBe(before.unpublishAt)
	})

	// ── REQ-SCHEDUI-002 ─────────────────────────────────────────────────

	// @e2e dashboards::take-down-passes
	test("once its take-down time passes, a published dashboard is gone from a colleague's list", async () => {
		const dash = await aliceDashboard('Take down')
		await publication(alice!.api, dash.uuid, 'publish')
		expect((await visibleRow(bob!.api, dash.uuid))?.publicationStatus).toBe(
			'published',
		)

		const scheduled = await publication(alice!.api, dash.uuid, 'schedule', {
			unpublishAt: serverSoon(6),
		})
		expect(scheduled.status, JSON.stringify(scheduled.body)).toBe(200)
		expect(scheduled.dashboard?.unpublishAt).toMatch(STAMP)

		await expect
			.poll(
				async () => (await visibleRow(bob!.api, dash.uuid)) === undefined,
				{
					timeout: 40_000,
					intervals: [1000],
				},
			)
			.toBe(true)
		// The owner still has it, now reading as a draft.
		expect((await visibleRow(alice!.api, dash.uuid))?.publicationStatus).toBe(
			'draft',
		)
	})
})
