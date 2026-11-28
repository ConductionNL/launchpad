/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the People widget's in-widget search and
 * its client-side cache (openspec/specs/people-widget REQ-PPL-011 and
 * REQ-PPL-012), written for e2e-coverage-programme.
 *
 * The widget shows a group this file created:
 *
 *   alice smith     alice.smith-…@example.com
 *   Alice Johnson   alice.johnson-…@example.com
 *   John Doe        jdoe-…@other.test
 *   Bram de Vries   bram-…@example.com
 *   Carla Jansen    carla-…@other.test
 *
 * One scenario is about the second PAGE, which needs more than the widget's
 * page size of 50; provisioning 51 accounts for it would make this the
 * slowest file in the suite, so that test answers `/api/people` itself with a
 * 60-person directory and asserts only what the browser does with it. Every
 * "john" is on the first page, so the test holds whether a query of two or
 * more characters is filtered in the browser (REQ-PPL-011) or asked of the
 * server (REQ-PEX-003).
 *
 * Caching is observed by counting requests to `/api/people` while the person
 * leaves the dashboard and comes back through the sidebar, an in-app
 * navigation that unmounts and remounts the widget without reloading the page.
 *
 * Gate-19 @e2e traceability:
 *   @e2e people-widget::search-filters-current-page
 *   @e2e people-widget::search-is-case-insensitive
 *   @e2e people-widget::search-on-email
 *   @e2e people-widget::clear-search-restores-all-users
 *   @e2e people-widget::search-input-ui-affordance
 *   @e2e people-widget::results-cached-for-60-seconds
 *   @e2e people-widget::cache-expires-after-60-seconds
 *   @e2e people-widget::force-refresh-clears-cache
 *
 * @spec openspec/specs/people-widget/spec.md
 */

import type { APIRequestContext, Locator, Page, Route } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { PeoplePlacement, Person } from './support/peopleWidget.ts'

import { expect, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { removeSeededDashboard } from './support/dashboardFixture.ts'
import { switchToDashboard } from './support/dashboardSwitcher.ts'
import {
	groupFilter,
	isPeopleRequest,
	mustEditAccount,
	peopleWidget,
	personItem,
	personItems,
	provisionPerson,
	removeGroups,
	removePeople,
	searchBox,
	seedPeopleDashboard,
	stampedGroup,
} from './support/peopleWidget.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = `${Date.now()}`

let admin: APIRequestContext
let group = ''
const people: Person[] = []
const dashboards: Array<SeededDashboard | null> = []

test.setTimeout(120_000)

test.beforeAll(async () => {
	test.setTimeout(240_000)
	admin = await adminContext()
	group = await stampedGroup(admin, 'e2e-ppl-search')
	const specs: Array<[string, string]> = [
		['alice smith', `alice.smith-${STAMP}@example.com`],
		['Alice Johnson', `alice.johnson-${STAMP}@example.com`],
		['John Doe', `jdoe-${STAMP}@other.test`],
		['Bram de Vries', `bram-${STAMP}@example.com`],
		['Carla Jansen', `carla-${STAMP}@other.test`],
	]
	for (const [displayName, email] of specs) {
		people.push(await provisionPerson(admin, 'e2e-ppl-find', { displayName, email, groups: [group] }))
	}
})

test.afterAll(async () => {
	test.setTimeout(120_000)
	await removePeople(admin, people)
	await removeGroups(admin, [group].filter(Boolean))
	await admin.dispose()
})

test.afterEach(async () => {
	while (dashboards.length > 0) {
		await removeSeededDashboard(admin, dashboards.pop() ?? null)
	}
})

/**
 * Seed the admin's active dashboard with one people placement and open it.
 *
 * @param page the page.
 * @param placement the placement; a list of the file's group by default.
 * @return the widget root, with its people rendered.
 */
async function openWidget(page: Page, placement?: PeoplePlacement): Promise<Locator> {
	const seeded = await seedPeopleDashboard(admin, `E2E People search ${STAMP}-${dashboards.length}`, [
		placement ?? { content: { layout: 'list', filters: groupFilter(group) } },
	])
	dashboards.push(seeded)
	await gotoLaunchPad(page)
	const widget = peopleWidget(page)
	await expect(widget).toBeVisible({ timeout: 20_000 })
	return widget
}

/**
 * The display names the widget shows, sorted.
 *
 * @param widget the widget root.
 * @return the names.
 */
async function shownNames(widget: Locator): Promise<string[]> {
	const names = await personItems(widget).evaluateAll((items) =>
		items.map((item) => (item.querySelector('strong')?.textContent ?? '').trim()),
	)
	return names.sort()
}

/**
 * Leave for another dashboard and come back, without reloading the page.
 *
 * @param page the page.
 * @param away the name of the dashboard to visit.
 * @param home the name of the dashboard to return to.
 * @param between run while away (e.g. move the clock).
 * @return nothing.
 */
async function leaveAndReturn(
	page: Page,
	away: string,
	home: string,
	between?: () => Promise<void>,
): Promise<void> {
	await switchToDashboard(page, away)
	await expect(peopleWidget(page)).toHaveCount(0, { timeout: 15_000 })
	if (between) {
		await between()
	}
	// Choosing a dashboard closes More; open it again to come back.
	await switchToDashboard(page, home)
}

/**
 * Create a second, people-free dashboard for the admin to step away to.
 *
 * @param name its name.
 * @return the dashboard.
 */
async function createAwayDashboard(name: string): Promise<SeededDashboard> {
	const res = await admin.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const seeded = { id: Number(dash.id), uuid: String(dash.uuid), name }
	dashboards.push(seeded)
	return seeded
}

test.describe('REQ-PPL-011: in-widget search', () => {
	// @e2e people-widget::search-filters-current-page
	test('a search narrows the current page, and paging on is not affected by it', async ({ page }) => {
		// A 60-person directory: 50 on page one (three of them "john"), 10 on page two.
		const directory = Array.from({ length: 60 }, (_, i) => {
			const n = String(i).padStart(2, '0')
			const john = i === 3 ? 'John Smit' : i === 17 ? 'Johnny Bos' : null
			return {
				uid: `e2e-synthetic-${n}`,
				displayName: john ?? `Persoon ${n}`,
				email: i === 40 ? `t.john-${n}@example.com` : `persoon-${n}@example.com`,
				groups: [],
			}
		})
		const requested: URL[] = []
		const answer = (route: Route) => {
			const url = new URL(route.request().url())
			requested.push(url)
			const q = (url.searchParams.get('q') ?? '').toLowerCase()
			const offset = Number(url.searchParams.get('offset') ?? 0)
			const limit = Number(url.searchParams.get('limit') ?? 50)
			const pool = q
				? directory.filter((u) => `${u.displayName} ${u.email}`.toLowerCase().includes(q))
				: directory
			const users = pool.slice(offset, offset + limit)
			return route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({ users, total: pool.length, hasMore: offset + users.length < pool.length }),
			})
		}
		await page.route('**/apps/launchpad/api/people**', answer)

		const widget = await openWidget(page)
		await expect(personItems(widget)).toHaveCount(50, { timeout: 20_000 })

		await searchBox(widget).fill('john')
		await expect.poll(() => shownNames(widget), { timeout: 10_000 }).toEqual(
			['John Smit', 'Johnny Bos', 'Persoon 40'].sort(),
		)

		// Clearing the search gives page one back, and page two still comes
		// from offset 50: the search did not move the pagination.
		await searchBox(widget).fill('')
		await expect(personItems(widget)).toHaveCount(50, { timeout: 10_000 })
		const loadMore = widget.getByRole('button', { name: /load more/i })
		await expect(loadMore).toBeVisible()
		const pageTwo = page.waitForRequest((req) => isPeopleRequest(req.url()) && new URL(req.url()).searchParams.get('offset') === '50')
		await loadMore.click()
		const req = new URL((await pageTwo).url())
		expect(req.searchParams.get('q') ?? '').toBe('')
		await expect(personItems(widget)).toHaveCount(60, { timeout: 10_000 })
	})

	// @e2e people-widget::search-is-case-insensitive
	test('"ALICE" matches "alice smith" and "Alice Johnson"', async ({ page }) => {
		const widget = await openWidget(page)
		await expect(personItems(widget)).toHaveCount(5, { timeout: 20_000 })
		await searchBox(widget).fill('ALICE')
		await expect.poll(() => shownNames(widget), { timeout: 10_000 }).toEqual(
			['Alice Johnson', 'alice smith'].sort(),
		)
	})

	// @e2e people-widget::search-on-email
	test('"example.com" shows everyone with that in their email, and only them', async ({ page }) => {
		const widget = await openWidget(page)
		await expect(personItems(widget)).toHaveCount(5, { timeout: 20_000 })
		await searchBox(widget).fill('example.com')
		await expect.poll(() => shownNames(widget), { timeout: 10_000 }).toEqual(
			['Alice Johnson', 'Bram de Vries', 'alice smith'].sort(),
		)
	})

	// @e2e people-widget::clear-search-restores-all-users
	test('clearing the search box shows everyone again', async ({ page }) => {
		const widget = await openWidget(page)
		await expect(personItems(widget)).toHaveCount(5, { timeout: 20_000 })
		await searchBox(widget).fill('john')
		await expect.poll(() => shownNames(widget), { timeout: 10_000 }).toEqual(['Alice Johnson', 'John Doe'])
		await searchBox(widget).fill('')
		await expect.poll(() => shownNames(widget), { timeout: 10_000 }).toEqual(
			['Alice Johnson', 'Bram de Vries', 'Carla Jansen', 'John Doe', 'alice smith'].sort(),
		)
	})

	// @e2e people-widget::search-input-ui-affordance
	test('a labelled search input with a name-or-email placeholder is visible', async ({ page }) => {
		const widget = await openWidget(page)
		const box = searchBox(widget)
		await expect(box).toBeVisible()
		const placeholder = (await box.getAttribute('placeholder')) ?? ''
		expect(placeholder).toMatch(/search/i)
		expect(placeholder).toMatch(/name/i)
		expect(placeholder).toMatch(/email/i)
		expect(((await box.getAttribute('aria-label')) ?? '').trim()).not.toBe('')
	})
})

test.describe('REQ-PPL-012: client-side cache', () => {
	// @e2e people-widget::results-cached-for-60-seconds
	test('reopening the widget within 60 seconds makes no new request and shows the same people', async ({ page }) => {
		const away = await createAwayDashboard(`E2E People away ${STAMP}-a`)
		const requests: string[] = []
		page.on('request', (req) => {
			if (isPeopleRequest(req.url())) {
				requests.push(req.url())
			}
		})
		const widget = await openWidget(page)
		await expect(personItems(widget)).toHaveCount(5, { timeout: 20_000 })
		const before = await shownNames(widget)
		const fetched = requests.length
		expect(fetched).toBeGreaterThan(0)
		const home = dashboards[dashboards.length - 1]!.name

		await leaveAndReturn(page, away.name, home)
		await expect(personItems(peopleWidget(page))).toHaveCount(5, { timeout: 20_000 })
		expect(await shownNames(peopleWidget(page))).toEqual(before)
		// Let any late request land before counting.
		await page.waitForTimeout(2_000)
		expect(requests.length, requests.join('\n')).toBe(fetched)
	})

	// @e2e people-widget::cache-expires-after-60-seconds
	test('reopening the widget after 61 seconds fetches afresh', async ({ page }) => {
		await page.clock.install()
		const away = await createAwayDashboard(`E2E People away ${STAMP}-b`)
		const requests: string[] = []
		page.on('request', (req) => {
			if (isPeopleRequest(req.url())) {
				requests.push(req.url())
			}
		})
		const widget = await openWidget(page)
		await expect(personItems(widget)).toHaveCount(5, { timeout: 20_000 })
		const fetched = requests.length
		const home = dashboards[dashboards.length - 1]!.name

		await leaveAndReturn(page, away.name, home, () => page.clock.fastForward(61_000))
		await expect(personItems(peopleWidget(page))).toHaveCount(5, { timeout: 20_000 })
		await expect.poll(() => requests.length, { timeout: 10_000 }).toBeGreaterThan(fetched)
	})

	// @e2e people-widget::force-refresh-clears-cache
	test('Refresh fetches at once and shows the latest state', async ({ page }) => {
		let renamed: Person | null = null
		let own = ''
		try {
			own = await stampedGroup(admin, 'e2e-ppl-refresh')
			renamed = await provisionPerson(admin, 'e2e-ppl-refresh', {
				displayName: `Voor ${STAMP}`,
				groups: [own],
			})
			const widget = await openWidget(page, { content: { layout: 'list', filters: groupFilter(own) } })
			await expect(personItem(widget, `Voor ${STAMP}`)).toBeVisible({ timeout: 20_000 })

			await mustEditAccount(admin, renamed.username, 'displayname', `Na ${STAMP}`)
			const refetch = page.waitForRequest((req) => isPeopleRequest(req.url()), { timeout: 5_000 })
			await widget.getByRole('button', { name: /^refresh$/i }).click()
			await refetch
			await expect(personItem(widget, `Na ${STAMP}`)).toBeVisible({ timeout: 10_000 })
			await expect(personItem(widget, `Voor ${STAMP}`)).toHaveCount(0)
		} finally {
			await removePeople(admin, [renamed])
			await removeGroups(admin, [own].filter(Boolean))
		}
	})
})
