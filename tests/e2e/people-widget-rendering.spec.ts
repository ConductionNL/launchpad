/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for how the People widget renders
 * (openspec/specs/people-widget REQ-PPL-007..010), written for
 * e2e-coverage-programme: the three layouts, showFields, avatar sizes, the
 * empty and error states, and click-through to a person's profile.
 *
 * Every test seeds the admin's active dashboard with a `people` placement
 * filtered to a group this file created, so the rendered list is exactly the
 * six people below, and removes the dashboard afterwards:
 *
 *   alice smith  role "Product Manager", organisation "Team Noord", an email
 *   Bob Bakker   an email
 *   Carla Jansen, Dirk de Vries, Eva Mulder, Frank Visser   names only
 *
 * The locators match both renderers that exist for the `people` type (see
 * support/peopleWidget.ts), so these tests state the spec's behaviour rather
 * than one component's markup.
 *
 * Gate-19 @e2e traceability:
 *   @e2e people-widget::avatar-displayed-at-layout-appropriate-size
 *   @e2e people-widget::card-layout-displays-full-profile
 *   @e2e people-widget::grid-layout-shows-avatar-name-only
 *   @e2e people-widget::list-layout-shows-compact-rows
 *   @e2e people-widget::showfields-filtered-per-layout
 *   @e2e people-widget::no-users-match-filter
 *   @e2e people-widget::failed-api-request
 *   @e2e people-widget::empty-result-after-search
 *   @e2e people-widget::card-click-opens-profile
 *   @e2e people-widget::list-item-click-opens-profile
 *   @e2e people-widget::avatar-click-opens-profile
 *   @e2e people-widget::profile-page-respects-access-control
 *
 * @spec openspec/specs/people-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { PeoplePlacement, Person } from './support/peopleWidget.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { removeSeededDashboard } from './support/dashboardFixture.ts'
import {
	groupFilter,
	mustEditAccount,
	peopleWidget,
	personItem,
	personItems,
	profileLink,
	provisionPerson,
	removeGroups,
	removePeople,
	searchBox,
	seedPeopleDashboard,
	stampedGroup,
} from './support/peopleWidget.ts'
import { adminContext } from './support/principals.ts'

const STAMP = `${Date.now()}`
const NAMES = ['alice smith', 'Bob Bakker', 'Carla Jansen', 'Dirk de Vries', 'Eva Mulder', 'Frank Visser']

let admin: APIRequestContext
let group = ''
let people: Person[] = []
let alice: Person
let bob: Person
let dashboard: SeededDashboard | null = null

test.setTimeout(120_000)

test.beforeAll(async () => {
	test.setTimeout(300_000)
	admin = await adminContext()
	group = await stampedGroup(admin, 'e2e-ppl-render')
	alice = await provisionPerson(admin, 'e2e-ppl-alice', {
		displayName: NAMES[0],
		email: `alice-${STAMP}@example.com`,
		groups: [group],
		self: { role: 'Product Manager', organisation: 'Team Noord' },
	})
	bob = await provisionPerson(admin, 'e2e-ppl-bob', {
		displayName: NAMES[1],
		email: `bob-${STAMP}@example.com`,
		groups: [group],
	})
	people = [alice, bob]
	for (const name of NAMES.slice(2)) {
		people.push(await provisionPerson(admin, 'e2e-ppl-rest', { displayName: name, groups: [group] }))
	}
})

test.afterAll(async () => {
	test.setTimeout(120_000)
	await removePeople(admin, people)
	await removeGroups(admin, [group].filter(Boolean))
	await admin.dispose()
})

test.afterEach(async () => {
	await removeSeededDashboard(admin, dashboard)
	dashboard = null
})

/**
 * Seed the admin's active dashboard with people placements and open it.
 *
 * @param page the page.
 * @param placements the placements.
 * @return nothing.
 */
async function openWith(page: Page, placements: PeoplePlacement[]): Promise<void> {
	dashboard = await seedPeopleDashboard(admin, `E2E People render ${STAMP}-${Math.random()}`, placements)
	await gotoLaunchPad(page)
}

/**
 * The rendered width of the first avatar in a widget.
 *
 * @param widget the widget root.
 * @return the width in CSS pixels.
 */
async function avatarWidth(widget: Locator): Promise<number> {
	const img = widget.locator('[role="listitem"] img').first()
	await expect(img).toBeVisible({ timeout: 20_000 })
	await img.scrollIntoViewIfNeeded()
	const box = await img.boundingBox()
	return box?.width ?? 0
}

/**
 * How many column tracks the item container lays out.
 *
 * @param widget the widget root.
 * @return the track count.
 */
async function columnTracks(widget: Locator): Promise<number> {
	return widget.locator('[role="list"]').first().evaluate((el) => {
		const tracks = getComputedStyle(el).gridTemplateColumns.trim()
		return tracks === 'none' || tracks === '' ? 1 : tracks.split(/\s+/).length
	})
}

/**
 * The top edges of the first `n` items, rounded.
 *
 * @param widget the widget root.
 * @param n how many.
 * @return the y coordinates.
 */
async function rowTops(widget: Locator, n: number): Promise<number[]> {
	const tops: number[] = []
	for (let i = 0; i < n; i++) {
		const box = await personItems(widget).nth(i).boundingBox()
		tops.push(Math.round(box?.y ?? -1))
	}
	return tops
}

test.describe('REQ-PPL-007 / REQ-PPL-008: layouts', () => {
	// @e2e people-widget::avatar-displayed-at-layout-appropriate-size
	test('avatars show at 80 px in card, 64 px in grid and 44 px in list', async ({ page }) => {
		await openWith(page, [
			{ content: { layout: 'card', filters: groupFilter(group) }, gridY: 0, gridHeight: 8 },
			{ content: { layout: 'grid', filters: groupFilter(group) }, gridY: 8, gridHeight: 6 },
			{ content: { layout: 'list', filters: groupFilter(group) }, gridY: 14, gridHeight: 6 },
		])
		expect(Math.round(await avatarWidth(peopleWidget(page, 'card')))).toBe(80)
		expect(Math.round(await avatarWidth(peopleWidget(page, 'grid')))).toBe(64)
		expect(Math.round(await avatarWidth(peopleWidget(page, 'list')))).toBe(44)
	})

	// @e2e people-widget::card-layout-displays-full-profile
	test('card layout shows the avatar and every configured field, three cards to a row', async ({ page }) => {
		await openWith(page, [{
			content: {
				layout: 'card',
				filters: groupFilter(group),
				showFields: { displayName: true, role: true, organisation: true, email: true },
			},
		}])
		const widget = peopleWidget(page, 'card')
		await expect(personItems(widget)).toHaveCount(NAMES.length, { timeout: 20_000 })

		const card = personItem(widget, NAMES[0])
		await expect(card).toContainText(NAMES[0])
		await expect(card).toContainText('Product Manager')
		await expect(card).toContainText('Team Noord')
		await expect(card).toContainText(alice.email!)
		expect(Math.round(await avatarWidth(widget))).toBe(80)

		// Default columns for card is 3: three cards share a row, the fourth wraps.
		expect(await columnTracks(widget)).toBe(3)
		const [a, b, c, d] = await rowTops(widget, 4)
		expect([b, c]).toEqual([a, a])
		expect(d).toBeGreaterThan(a)

		// "Approximately 200 x 280": a full card, taller than wide, not a chip.
		const box = (await card.boundingBox())!
		expect(box.height).toBeGreaterThanOrEqual(180)
		expect(box.height).toBeLessThanOrEqual(400)
	})

	// @e2e people-widget::grid-layout-shows-avatar-name-only
	test('grid layout shows only the avatar and a truncated name, four to a row', async ({ page }) => {
		await openWith(page, [{
			content: {
				layout: 'grid',
				filters: groupFilter(group),
				showFields: { displayName: true, role: true, organisation: true, email: true },
			},
			gridWidth: 6,
		}])
		const widget = peopleWidget(page, 'grid')
		await expect(personItems(widget)).toHaveCount(NAMES.length, { timeout: 20_000 })

		const cell = personItem(widget, NAMES[0])
		await expect(cell).toContainText(NAMES[0])
		await expect(cell).not.toContainText('Product Manager')
		await expect(cell).not.toContainText('Team Noord')
		await expect(cell).not.toContainText(alice.email!)
		expect(Math.round(await avatarWidth(widget))).toBe(64)

		// The name takes at most two lines.
		const lines = await cell.getByText(NAMES[0]).first().evaluate((el) => {
			const style = getComputedStyle(el)
			const lineHeight = parseFloat(style.lineHeight) || parseFloat(style.fontSize) * 1.2
			return el.getBoundingClientRect().height / lineHeight
		})
		expect(lines).toBeLessThanOrEqual(2.05)

		// Default columns for grid is 4.
		expect(await columnTracks(widget)).toBe(4)
		const tops = await rowTops(widget, 5)
		expect(tops.slice(1, 4)).toEqual([tops[0], tops[0], tops[0]])
		expect(tops[4]).toBeGreaterThan(tops[0])

		const box = (await cell.boundingBox())!
		expect(box.height).toBeGreaterThanOrEqual(70)
		expect(box.height).toBeLessThanOrEqual(200)
	})

	// @e2e people-widget::list-layout-shows-compact-rows
	test('list layout shows one compact row per person with name and email, scrolling when long', async ({ page }) => {
		await openWith(page, [{
			content: {
				layout: 'list',
				filters: groupFilter(group),
				showFields: { displayName: true, email: true },
			},
			// 3 grid rows of 60 px: six rows of 44 px avatars cannot fit.
			gridHeight: 3,
		}])
		const widget = peopleWidget(page, 'list')
		await expect(personItems(widget)).toHaveCount(NAMES.length, { timeout: 20_000 })

		const row = personItem(widget, NAMES[0])
		await expect(row).toContainText(NAMES[0])
		await expect(row).toContainText(alice.email!)
		await expect(row).not.toContainText('Product Manager')
		await expect(row).not.toContainText('Team Noord')
		expect(Math.round(await avatarWidth(widget))).toBe(44)

		for (let i = 0; i < NAMES.length; i++) {
			const box = (await personItems(widget).nth(i).boundingBox())!
			// The board's list row (LpVandaag "Nieuwe collega's"): a 44px avatar,
			// 10px above and below and the 1px rule over it, 65px.
			expect(box.height, `row ${i} is a single compact line`).toBeLessThanOrEqual(65)
		}

		const scroll = await widget.locator('[role="list"]').first().evaluate((el) => ({
			overflowY: getComputedStyle(el).overflowY,
			scrollHeight: el.scrollHeight,
			clientHeight: el.clientHeight,
		}))
		expect(scroll.scrollHeight, 'the rows exceed the widget height').toBeGreaterThan(scroll.clientHeight)
		expect(['auto', 'scroll']).toContain(scroll.overflowY)
	})

	// @e2e people-widget::showfields-filtered-per-layout
	test('showFields {displayName, organisation} leaves email, phone and role off a card', async ({ page }) => {
		await openWith(page, [{
			content: {
				layout: 'card',
				filters: groupFilter(group),
				showFields: { displayName: true, organisation: true },
			},
		}])
		const card = personItem(peopleWidget(page, 'card'), NAMES[0])
		await expect(card).toBeVisible({ timeout: 20_000 })
		await expect(card).toContainText(NAMES[0])
		await expect(card).toContainText('Team Noord')
		await expect(card).not.toContainText(alice.email!)
		await expect(card).not.toContainText('Product Manager')
	})
})

test.describe('REQ-PPL-009: empty and error states', () => {
	// @e2e people-widget::no-users-match-filter
	test('a filter nobody matches says "No matching users." and draws no grid', async ({ page }) => {
		await openWith(page, [{
			content: { layout: 'grid', filters: groupFilter(`e2e-ppl-nonexistent-${STAMP}`) },
		}])
		const widget = peopleWidget(page)
		await expect(widget).toBeVisible({ timeout: 20_000 })
		await expect(widget.getByText('No matching users.')).toBeVisible({ timeout: 20_000 })
		await expect(widget.locator('[role="list"]')).toHaveCount(0)
		await expect(personItems(widget)).toHaveCount(0)
	})

	// @e2e people-widget::failed-api-request
	test('a 500 from the endpoint shows "Failed to load users" and a Retry that recovers', async ({ page }) => {
		await page.route('**/apps/launchpad/api/people**', (route) =>
			route.fulfill({
				status: 500,
				contentType: 'application/json',
				body: JSON.stringify({
					message: 'PDOException: SQLSTATE[HY000] forced by people-widget-rendering.spec.ts',
					trace: '#0 /var/www/html/lib/private/DB/Connection.php(123)',
				}),
			}),
		)
		await openWith(page, [{ content: { layout: 'list', filters: groupFilter(group) } }])

		const widget = peopleWidget(page)
		await expect(widget.getByText('Failed to load users')).toBeVisible({ timeout: 20_000 })
		const retry = widget.getByRole('button', { name: /^retry$/i })
		await expect(retry).toBeVisible()
		await expect(widget).not.toContainText('PDOException')
		await expect(widget).not.toContainText('Connection.php')
		await expect(widget).not.toContainText('500')

		await page.unroute('**/apps/launchpad/api/people**')
		await retry.click()
		await expect(personItem(widget, NAMES[0])).toBeVisible({ timeout: 20_000 })
	})

	// @e2e people-widget::empty-result-after-search
	test('a search nobody matches says "No users match your search"', async ({ page }) => {
		await openWith(page, [{ content: { layout: 'list', filters: groupFilter(group) } }])
		const widget = peopleWidget(page)
		await expect(personItems(widget)).toHaveCount(NAMES.length, { timeout: 20_000 })
		await searchBox(widget).fill('zzz')
		await expect(widget.getByText('No users match your search')).toBeVisible({ timeout: 10_000 })
		await expect(personItems(widget)).toHaveCount(0)
	})
})

test.describe('REQ-PPL-010: click-through to the profile', () => {
	/**
	 * Click, then require a same-tab navigation to the person's profile.
	 *
	 * @param page the page.
	 * @param target what to click.
	 * @param person whose profile it must be.
	 * @return nothing.
	 */
	async function expectProfileInSameTab(page: Page, target: Locator, person: Person): Promise<void> {
		const opened: Page[] = []
		page.context().on('page', (p) => opened.push(p))
		await target.click()
		await page.waitForURL(new RegExp(`/u/${person.username}$`), { timeout: 30_000 })
		expect(opened, 'no new tab or window').toHaveLength(0)
		await expect(page.locator('body')).toContainText(person.displayName, { timeout: 30_000 })
	}

	// @e2e people-widget::card-click-opens-profile
	test('clicking a card opens /u/{uid} in the same tab', async ({ page }) => {
		await openWith(page, [{ content: { layout: 'card', filters: groupFilter(group) } }])
		const widget = peopleWidget(page, 'card')
		await expect(personItem(widget, NAMES[0])).toBeVisible({ timeout: 20_000 })
		await expectProfileInSameTab(page, profileLink(widget, alice.username), alice)
	})

	// @e2e people-widget::list-item-click-opens-profile
	test('clicking a list row opens /u/{uid} in the same tab', async ({ page }) => {
		await openWith(page, [{ content: { layout: 'list', filters: groupFilter(group) } }])
		const widget = peopleWidget(page, 'list')
		await expect(personItem(widget, NAMES[1])).toBeVisible({ timeout: 20_000 })
		await expectProfileInSameTab(page, profileLink(widget, bob.username), bob)
	})

	// @e2e people-widget::avatar-click-opens-profile
	test('clicking an avatar opens /u/{uid} in the same tab', async ({ page }) => {
		await openWith(page, [{ content: { layout: 'grid', filters: groupFilter(group) } }])
		const widget = peopleWidget(page, 'grid')
		await expect(personItem(widget, NAMES[0])).toBeVisible({ timeout: 20_000 })
		await expectProfileInSameTab(page, profileLink(widget, alice.username).locator('img'), alice)
	})

	// @e2e people-widget::profile-page-respects-access-control
	test('a profile its owner closed is refused when another user opens it by address', async ({ browser }) => {
		let charlie: Person | null = null
		let dave: Person | null = null
		let open: Person | null = null
		try {
			charlie = await provisionPerson(admin, 'e2e-ppl-charlie', { displayName: 'Charlie Kijker' })
			open = await provisionPerson(admin, 'e2e-ppl-open', { displayName: `Olga Open ${STAMP}` })
			dave = await provisionPerson(admin, 'e2e-ppl-dave', { displayName: `Dave Dicht ${STAMP}` })
			await mustEditAccount(dave.api, dave.username, 'profile_enabled', '0')

			const { context, page } = await loginAs(browser, charlie.username, charlie.password)
			try {
				// Control: an open profile loads for charlie, so a refusal below
				// is the access rule and not a broken profile route.
				const ok = await page.goto(`/index.php/u/${open.username}`)
				expect(ok?.status()).toBe(200)
				await expect(page.locator('body')).toContainText(open.displayName, { timeout: 30_000 })

				const refused = await page.goto(`/index.php/u/${dave.username}`)
				const status = refused?.status() ?? 0
				const body = (await page.locator('body').innerText()).toLowerCase()
				expect(
					status >= 400 || /not found|not available|access denied|no access/.test(body),
					`status ${status}, body "${body.slice(0, 200)}"`,
				).toBe(true)
				await expect(page.locator('body')).not.toContainText(dave.displayName)
			} finally {
				await context.close()
			}
		} finally {
			await removePeople(admin, [charlie, dave, open])
		}
	})
})
