/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Two widgets of the boards (widgets REQ-WDG-032, REQ-WDG-033):
 *
 *  - "Verder waar u was", the `recent-work` widget (boards LpVandaag and
 *    LpStart): Pieter, a throwaway account, saves a file over WebDAV the way
 *    a desktop client does, and the widget on his own dashboard lists it once,
 *    named by the file, with "files" and the part of the day. A file he
 *    deleted is not listed, `apps` keeps only those apps' work, and with the
 *    Activity app switched off the widget says it needs it.
 *  - "Zoeken op internet", the `search` widget with `content.layout: 'web'`
 *    (board LpWerkplek): typing words and choosing Search opens the engine's
 *    url with the encoded words in a new tab; an engine url without `{q}` is
 *    ignored and DuckDuckGo is named instead.
 *
 * Everything is seeded over the API as the person it belongs to (his
 * dashboard, its placements, his files) and removed afterwards. The Activity
 * app is the instance's own: the listing tests skip, saying so, when it is not
 * installed, and the test that switches it off puts it back on. No test needs
 * the internet: the new tab's DuckDuckGo page is answered in the browser.
 *
 * Written from the change's scenarios (recent-work-and-web-search-widgets);
 * it runs in the nightly against a live instance.
 *
 * @e2e openspec/specs/widgets/spec.md
 */

import type { APIRequestContext, Browser, Locator, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'
import type { OwnDashboard } from './support/widgets.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { quietFirstVisit } from './support/firstVisit.ts'
import { adminContext, API, createPrincipal, removePrincipal } from './support/principals.ts'
import {
	activate,
	createOwnDashboard,
	readAllowUserDashboards,
	restoreAllowUserDashboards,
	seedPlacement,
} from './support/widgets.ts'

const RUN = Date.now()
const SAVED = `Besluit Woo-verzoek jeugdzorg ${RUN}.docx`
const DELETED = `concept ${RUN}.docx`
const QUERY = 'omgevingswet termijnen'
const ACTIVITY_SELF = '/ocs/v2.php/apps/activity/api/v2/activity/self?format=json&limit=200'
const NO_ACTIVITY = 'the Activity app is not installed on this instance, so there is no own activity to list'

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

let admin: APIRequestContext
let allowUserDashboardsBefore: boolean | null = null
let pieter: Principal | null = null
let dashboard: OwnDashboard | null = null
let activityAvailable = false
const placements: Record<string, number> = {}

/**
 * Write a file into Pieter's home over WebDAV.
 *
 * @param name the file name.
 * @param body the file's content.
 * @return nothing; throws when the upload fails.
 */
async function saveFile(name: string, body: string): Promise<void> {
	const res = await pieter!.api.put(
		`/remote.php/dav/files/${encodeURIComponent(pieter!.username)}/${encodeURIComponent(name)}`,
		{ data: body, headers: { 'Content-Type': 'application/octet-stream' } },
	)
	expect([201, 204], `PUT ${name} -> ${res.status()}`).toContain(res.status())
}

/**
 * The object names in Pieter's own activity, newest first.
 *
 * @return the names, or [] when there is no activity yet.
 */
async function ownActivityNames(): Promise<string[]> {
	const res = await pieter!.api.get(ACTIVITY_SELF)
	if (res.status() === 304) {
		return []
	}
	expect(res.status(), await res.text()).toBe(200)
	const data = (await res.json())?.ocs?.data ?? []
	return (data as Array<{ object_name?: string }>).map((a) => String(a.object_name ?? ''))
}

/**
 * Seed a placement on Pieter's dashboard and remember it by a name.
 *
 * @param key the name the tests use for it.
 * @param widgetId the widget type.
 * @param content the widget content.
 * @param gridY the row it starts on.
 * @return nothing.
 */
async function place(
	key: string,
	widgetId: string,
	content: Record<string, unknown>,
	gridY: number,
): Promise<void> {
	const placement = await seedPlacement(pieter!.api, dashboard!.id, {
		widgetId,
		gridX: 0,
		gridY,
		gridWidth: 6,
		gridHeight: 4,
		content,
	})
	placements[key] = placement.id
}

/**
 * One of the seeded widgets on the page.
 *
 * @param page Pieter's page.
 * @param key the name it was seeded under.
 * @return its grid cell.
 */
function widget(page: Page, key: string): Locator {
	return page.locator(`.launchpad-grid-item[data-placement-id="${placements[key]}"]`)
}

/**
 * Open Pieter's dashboard in his own browser session.
 *
 * @param browser the browser.
 * @return the context and page.
 */
async function openAsPieter(browser: Browser) {
	const session = await loginAs(browser, pieter!.username, pieter!.password)
	await session.page.goto('/index.php/apps/launchpad/')
	await expect(session.page.locator('[data-testid="launchpad-more-menu"]')).toBeVisible({
		timeout: 30_000,
	})
	return session
}

test.beforeAll(async () => {
	test.setTimeout(300_000)
	admin = await adminContext()
	allowUserDashboardsBefore = await readAllowUserDashboards(admin)
	const allow = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(allow.status(), await allow.text()).toBeLessThan(300)

	pieter = await createPrincipal(admin, 'e2e-recent-pieter')
	await quietFirstVisit(pieter.api)
	dashboard = await createOwnDashboard(pieter.api, `Mijn werkdag ${RUN}`)
	await clearSeededWidgets(pieter.api, dashboard.id)
	await place('recent', 'recent-work', { limit: 6 }, 0)
	await place('filesOnly', 'recent-work', { limit: 6, apps: ['files'] }, 4)
	await place('deckOnly', 'recent-work', { limit: 6, apps: ['deck'] }, 8)
	await place('web', 'search', { layout: 'web' }, 12)
	await place('intranet', 'search', {
		layout: 'web',
		engine: { name: 'Intranet', url: 'https://intranet.example.nl/search' },
	}, 16)
	await activate(pieter.api, dashboard)

	const probe = await pieter.api.get(ACTIVITY_SELF)
	activityAvailable = probe.status() === 200 || probe.status() === 304
	if (activityAvailable) {
		// This morning Pieter saved the decision twice, and wrote and then
		// deleted a draft.
		await saveFile(SAVED, 'eerste versie')
		await saveFile(SAVED, 'tweede versie')
		await saveFile(DELETED, 'concept')
		const removed = await pieter.api.delete(
			`/remote.php/dav/files/${encodeURIComponent(pieter.username)}/${encodeURIComponent(DELETED)}`,
		)
		expect(removed.status(), `DELETE ${DELETED}`).toBe(204)
		await expect
			.poll(ownActivityNames, { timeout: 60_000 })
			.toEqual(expect.arrayContaining([expect.stringContaining(SAVED)]))
	}
})

test.afterAll(async () => {
	if (pieter !== null && dashboard !== null) {
		await pieter.api.delete(`${API}/dashboard/${dashboard.id}`)
	}
	await removePrincipal(admin, pieter)
	await restoreAllowUserDashboards(admin, allowUserDashboardsBefore)
	await admin.dispose()
})

// @e2e widgets::pieter-picks-up-where-he-left-off
test('the file Pieter just saved is listed once, named by the file, with files and the part of the day', async ({
	browser,
}) => {
	test.skip(!activityAvailable, NO_ACTIVITY)
	const { context, page } = await openAsPieter(browser)
	try {
		const recent = widget(page, 'recent')
		await expect(recent.locator('[data-testid="recent-work-list"]')).toBeVisible({
			timeout: 30_000,
		})
		const rows = recent.locator('[data-testid="recent-work-row"]')
		const saved = rows.filter({ hasText: SAVED })
		// Saved twice, listed once.
		await expect(saved).toHaveCount(1)
		await expect(saved.locator('.recent-work__title')).toHaveText(SAVED)
		await expect(saved.locator('.recent-work__meta')).toHaveText(
			/^files · this (morning|afternoon|evening)$/,
		)
		// A row links to the thing.
		await expect(saved).toHaveAttribute('href', /.+/)
		// Newest first: the decision was the last thing kept.
		await expect(rows.first()).toContainText(SAVED)
		expect(await rows.count()).toBeLessThanOrEqual(6)
	} finally {
		await context.close()
	}
})

// A deletion is its own activity; recentWorkRows (RecentWorkWidget.vue)
// leaves it out and also the file's earlier "created" and "changed"
// activity, which come after it in the newest-first stream.
// @e2e widgets::a-deleted-file-is-not-work-to-pick-up
test('a draft Pieter deleted is not listed', async ({ browser }) => {
	test.skip(!activityAvailable, NO_ACTIVITY)
	expect(await ownActivityNames()).toEqual(
		expect.arrayContaining([expect.stringContaining(DELETED)]),
	)
	const { context, page } = await openAsPieter(browser)
	try {
		const recent = widget(page, 'recent')
		const rows = recent.locator('[data-testid="recent-work-row"]')
		// Control: the widget did list his work.
		await expect(rows.filter({ hasText: SAVED })).toHaveCount(1, { timeout: 30_000 })
		await expect(rows.filter({ hasText: DELETED })).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e widgets::only-the-apps-the-author-chose
test('with apps files only Files work is listed, and with apps deck none of it', async ({
	browser,
}) => {
	test.skip(!activityAvailable, NO_ACTIVITY)
	const { context, page } = await openAsPieter(browser)
	try {
		const rows = widget(page, 'filesOnly').locator('[data-testid="recent-work-row"]')
		await expect(rows.filter({ hasText: SAVED })).toHaveCount(1, { timeout: 30_000 })
		for (const meta of await rows.locator('.recent-work__meta').allInnerTexts()) {
			expect(meta).toMatch(/^files · /)
		}
		// Pieter did nothing in Deck: the same activity, filtered to deck, is empty.
		await expect(
			widget(page, 'deckOnly').locator('[data-testid="recent-work-empty"]'),
		).toHaveText('Nothing you worked on recently.')
	} finally {
		await context.close()
	}
})

// @e2e widgets::without-the-activity-app
test('with the Activity app switched off the widget says it needs it', async ({
	browser,
}) => {
	const apps = await admin.get('/ocs/v1.php/cloud/apps?filter=enabled&format=json')
	expect(apps.ok()).toBeTruthy()
	const wasEnabled = ((await apps.json()).ocs.data.apps as string[]).includes('activity')
	if (wasEnabled) {
		const off = await admin.delete('/ocs/v1.php/cloud/apps/activity')
		expect(off.ok(), `disable activity -> ${off.status()}`).toBeTruthy()
	}
	try {
		const { context, page } = await openAsPieter(browser)
		try {
			await expect(
				widget(page, 'recent').locator('[data-testid="recent-work-unavailable"]'),
			).toHaveText('Recent work needs the Activity app.', { timeout: 30_000 })
			await expect(
				widget(page, 'recent').locator('[data-testid="recent-work-row"]'),
			).toHaveCount(0)
		} finally {
			await context.close()
		}
	} finally {
		if (wasEnabled) {
			const on = await admin.post('/ocs/v1.php/cloud/apps/activity')
			expect(on.ok(), `enable activity -> ${on.status()}`).toBeTruthy()
		}
	}
})

// @e2e widgets::pieter-searches-the-internet
test('typing words and choosing Search opens DuckDuckGo with the encoded words in a new tab', async ({
	browser,
}) => {
	const { context, page } = await openAsPieter(browser)
	try {
		// The new tab's page is answered here, so no test reaches the internet.
		await context.route('https://duckduckgo.com/**', (route) =>
			route.fulfill({ status: 200, contentType: 'text/html', body: '<title>results</title>' }),
		)
		const form = widget(page, 'web').locator('[data-testid="search-widget-web"]')
		await expect(form).toBeVisible({ timeout: 30_000 })
		await expect(form.locator('[data-testid="search-widget-engine"]')).toHaveText('DuckDuckGo')
		await expect(form).toContainText('Opens the results in a new tab')
		const field = form.getByLabel('Internet search')
		const search = form.getByRole('button', { name: 'Search', exact: true })

		// An empty field does nothing.
		let opened = 0
		context.on('page', () => {
			opened += 1
		})
		await search.click()
		await page.waitForTimeout(1_000)
		expect(opened).toBe(0)

		await field.fill(QUERY)
		const tab = context.waitForEvent('page')
		await search.click()
		const results = await tab
		await results.waitForLoadState('domcontentloaded')
		expect(results.url()).toBe('https://duckduckgo.com/?q=omgevingswet%20termijnen')
		// The page Pieter searched from stays where it was.
		expect(page.url()).toContain('/apps/launchpad')
		// noopener: the new tab has no handle on LaunchPad.
		expect(await results.evaluate(() => window.opener)).toBeNull()
	} finally {
		await context.close()
	}
})

// @e2e widgets::an-engine-without-a-placeholder-is-ignored
test('an engine url without {q} is ignored and the widget names DuckDuckGo', async ({
	browser,
}) => {
	const { context, page } = await openAsPieter(browser)
	try {
		const form = widget(page, 'intranet').locator('[data-testid="search-widget-web"]')
		await expect(form).toBeVisible({ timeout: 30_000 })
		await expect(form.locator('[data-testid="search-widget-engine"]')).toHaveText('DuckDuckGo')
		await expect(form).not.toContainText('Intranet')
	} finally {
		await context.close()
	}
})
