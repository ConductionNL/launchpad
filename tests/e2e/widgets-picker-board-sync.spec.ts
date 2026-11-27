/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The four scenarios of the open change widget-picker-board-sync
 * (REQ-WDG-040 to REQ-WDG-043, a delta on `widgets`): the widget picker as
 * board LpWidgetKiezen draws it, its kinds with counts, its App tiles
 * section, and a widget type the reader's role may not add. Gate-19 reads a
 * delta scenario as `widgets::<slug>`, so until this file the four were
 * uncited.
 *
 * THE FIXTURE
 * ===========
 * Pieter is a throwaway account (English, the scenarios' language) in a
 * group that stands for the role Employee. His one personal dashboard is
 * "My workspace", empty; he opens the picker from the edit bar's
 * Add widget, as "Pieter edits My workspace ... he chooses Add widget"
 * says.
 *   - "Case system" is an app-catalogue entry the admin approved for that
 *     group (POST /api/admin/catalogue), so it is in Pieter's tile catalogue.
 *   - The role's feature permission denies `iframe` (Embedded page) to the
 *     group (POST /api/role-feature-permissions), so Embedded page is a type
 *     his role may not add. Both are removed afterwards.
 *
 * "Only the data and chart widgets are shown": the change names the kind but
 * not its members. The test holds Pie chart and Graph (registered for data,
 * src/constants/widgetRegistry.js) as in it, Clock, Section heading and
 * Embedded page as outside it, and requires the kind's count to equal the
 * number of cards it leaves.
 *
 * Not serial on purpose: each scenario stands on its own.
 *
 * The role rule copies the shipped Employee rule's allow-list without
 * Embedded page (an empty allow-list allows nothing) and puts the group
 * first in group_order, since a rule is only read for a group listed there;
 * both are put back afterwards.
 *
 * Verified live 25 Nov 2026 on Nextcloud 34.0.6 + MariaDB: 4/4.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::open-the-picker
 *   @e2e widgets::filter-by-kind
 *   @e2e widgets::place-an-app-tile
 *   @e2e widgets::embedded-page-not-allowed
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Locator, Page } from '@playwright/test'
import type { RoleFeaturePermissionRow } from './fixtures/role-feature-permissions.ts'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import {
	deleteRoleFeaturePermission,
	listRoleFeaturePermissions,
	readGroupOrder,
	saveRoleFeaturePermission,
	writeGroupOrder,
} from './fixtures/role-feature-permissions.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import { setLanguage } from './support/orWidget.ts'
import { openOwnDashboard, readPlacements, setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import { addToGroup, adminContext, API, createGroup, removeGroup } from './support/principals.ts'

const STAMP = `${Date.now()}`
const DASHBOARD = `My workspace ${STAMP}`
const EMPLOYEE = `e2e-employee-${STAMP}`
const CASE_SYSTEM = 'Case system'
const KINDS = ['All', 'Content', 'Tiles and links', 'People', 'Data and charts', 'From other apps', 'Office']
const SECTIONS = ['Widgets from Launchpad', 'App tiles', 'From other Nextcloud apps']

let own: OwnDashboard
let admin: APIRequestContext
let catalogueUuid: string | null = null
let rule: RoleFeaturePermissionRow | null = null
let groupOrderBefore: string[] | null = null

test.setTimeout(120_000)

/**
 * Open My workspace, enter edit mode and choose Add widget in the edit bar.
 *
 * @param browser The browser.
 * @return The context, page and picker.
 */
async function openPicker(browser: Browser): Promise<{ context: BrowserContext; page: Page; picker: Locator }> {
	const count = (await readPlacements(own.owner.api, own.id)).length
	const { context, page } = await openOwnDashboard(browser, own, count)
	await enterEditMode(page)
	await page.locator('.launchpad-edit-mode').getByRole('button', { name: 'Add widget', exact: true }).first().click()
	const picker = page.getByRole('dialog').filter({ hasText: 'Add widget' }).last()
	await expect(picker).toBeVisible({ timeout: 10_000 })
	return { context, page, picker }
}

/**
 * The kind column.
 *
 * @param picker The picker.
 * @return The list of kinds (a tab list or a navigation list).
 */
function kinds(picker: Locator): Locator {
	return picker.getByRole('tablist').or(picker.getByRole('navigation')).or(picker.getByRole('list', { name: /kind/i })).first()
}

/**
 * One kind in the column.
 *
 * @param picker The picker.
 * @param name The kind.
 * @return The kind's control.
 */
function kind(picker: Locator, name: string): Locator {
	const label = new RegExp(`^${name}\\s*\\(?\\d+\\)?$`)
	return kinds(picker).getByRole('tab', { name: label }).or(kinds(picker).getByRole('button', { name: label })).first()
}

/**
 * The number a kind shows.
 *
 * @param control The kind's control.
 * @return The count.
 */
async function countOf(control: Locator): Promise<number> {
	const text = (await control.innerText()).trim()
	const match = text.match(/(\d+)\)?$/)
	expect(match, `the kind "${text}" shows no count`).not.toBeNull()
	return Number(match![1])
}

/**
 * The widget cards the picker shows now.
 *
 * @param picker The picker.
 * @return The cards.
 */
function cards(picker: Locator): Locator {
	return picker.locator('.widget-picker__widget:visible, [data-testid^="widget-picker-card"]:visible')
}

test.beforeAll(async () => {
	admin = await adminContext()
	own = await setUpOwnDashboard('e2e-picker-pieter', DASHBOARD)
	await setLanguage(own.owner, 'en')
	await createGroup(admin, EMPLOYEE)
	await addToGroup(admin, own.owner.username, EMPLOYEE)

	const entry = await admin.post(`${API}/admin/catalogue`, {
		data: {
			name: CASE_SYSTEM,
			description: 'Cases for the front office',
			linkType: 'url',
			linkValue: 'https://zaken.e2e.invalid/',
			targetGroups: [EMPLOYEE],
		},
	})
	expect(entry.status(), await entry.text()).toBe(201)
	catalogueUuid = (await entry.json()).app.uuid

	// The role allows what the shipped Employee rule allows (else the shipped
	// default rule) except Embedded page. An empty allow-list would allow
	// nothing at all, and a rule is only read for a group in group_order.
	const shipped = await listRoleFeaturePermissions(admin)
	const base = shipped.find((row) => row.groupId === 'medewerkers')
		?? own.defaultRuleBefore
		?? shipped.find((row) => row.groupId === 'default')
	const allowedWidgets = (base?.allowedWidgets ?? ['clock', 'graph', 'pie-chart', 'section-heading', 'tile', 'app-tile', 'text'])
		.filter((id) => id !== 'iframe')
	rule = await saveRoleFeaturePermission(admin, {
		groupId: EMPLOYEE,
		name: `Employee ${STAMP}`,
		allowedWidgets,
		deniedWidgets: ['iframe'],
	})
	groupOrderBefore = await readGroupOrder(admin)
	await writeGroupOrder(admin, [EMPLOYEE, ...groupOrderBefore.filter((g) => g !== EMPLOYEE)])
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
	if (admin) {
		if (catalogueUuid) {
			await admin.delete(`${API}/admin/catalogue/${catalogueUuid}`)
		}
		if (groupOrderBefore !== null) {
			await writeGroupOrder(admin, groupOrderBefore)
		}
		if (rule) {
			await deleteRoleFeaturePermission(admin, rule.id)
		}
		await removeGroup(admin, EMPLOYEE)
		await admin.dispose()
	}
})

// @e2e widgets::open-the-picker
test('Add widget on My workspace opens one dialog with the search, the kinds and the card sections in that order', async ({
	browser,
}) => {
	const { context, picker } = await openPicker(browser)
	try {
		// Titled with the dashboard name over "Add widget", about 720 px wide.
		const dashboardName = picker.getByText(DASHBOARD, { exact: true }).first()
		const title = picker.getByRole('heading', { name: 'Add widget', exact: true })
		await expect(dashboardName).toBeVisible()
		await expect(title).toBeVisible()
		expect((await dashboardName.boundingBox())!.y).toBeLessThan((await title.boundingBox())!.y)
		// The dialog role sits on the full-screen mask; the dialog box is its .modal-container.
		const width = (await picker.locator('.modal-container').first().boundingBox())!.width
		expect(Math.abs(width - 720), `the dialog is ${width} px wide`).toBeLessThanOrEqual(24)

		// The search, then the kinds, with the cards to their right.
		const search = picker.getByRole('textbox', { name: /search/i }).or(picker.getByRole('searchbox')).first()
		await expect(search).toBeVisible()
		const column = kinds(picker)
		await expect(column).toBeVisible()
		const searchBox = (await search.boundingBox())!
		const columnBox = (await column.boundingBox())!
		expect(columnBox.y).toBeGreaterThan(searchBox.y)

		const tops: number[] = []
		for (const section of SECTIONS) {
			const heading = picker.getByRole('heading', { name: section, exact: true })
			await expect(heading, `no section ${section}`).toBeVisible()
			const box = (await heading.boundingBox())!
			expect(box.x, `${section} is not to the right of the kinds`).toBeGreaterThan(columnBox.x + columnBox.width - 1)
			tops.push(box.y)
		}
		expect([...tops].sort((a, b) => a - b)).toEqual(tops)

		// Cards in two columns, each with an icon, a name and one line.
		const visible = cards(picker)
		await expect(visible.first()).toBeVisible()
		const lefts = new Set<number>()
		for (const card of (await visible.all()).slice(0, 6)) {
			lefts.add(Math.round((await card.boundingBox())!.x))
		}
		expect(lefts.size, 'the cards do not stand in two columns').toBe(2)

		// It ends with Cancel and Add widget.
		const cancel = picker.getByRole('button', { name: 'Cancel', exact: true })
		const add = picker.getByRole('button', { name: 'Add widget', exact: true })
		await expect(cancel).toBeVisible()
		await expect(add).toBeVisible()
		// The card list scrolls; the footer stays under it, at the dialog's bottom.
		const body = (await picker.locator('.widget-picker__body').first().boundingBox())!
		expect((await add.boundingBox())!.y).toBeGreaterThanOrEqual(body.y + body.height - 1)
	} finally {
		await context.close()
	}
})

// @e2e widgets::filter-by-kind
test('choosing Data and charts shows only the data and chart widgets, and the kind shows their count', async ({
	browser,
}) => {
	const { context, picker } = await openPicker(browser)
	try {
		for (const name of KINDS) {
			await expect(kind(picker, name), `no kind ${name} with a count`).toBeVisible()
		}
		const all = await countOf(kind(picker, 'All'))

		const data = kind(picker, 'Data and charts')
		await data.click()
		await expect.poll(() => data.evaluate((el) => ['aria-selected', 'aria-current', 'aria-pressed']
			.some((name) => ['true', 'page'].includes(el.getAttribute(name) ?? '')))).toBe(true)

		for (const name of ['Pie chart', 'Graph']) {
			await expect(picker.getByText(name, { exact: true }).first(), `${name} is a chart`).toBeVisible()
		}
		for (const name of ['Clock', 'Section heading', 'Embedded page']) {
			await expect(picker.getByText(name, { exact: true }), `${name} is not a chart`).toBeHidden()
		}
		const shown = await cards(picker).count()
		expect(await countOf(data)).toBe(shown)
		expect(shown).toBeLessThan(all)
	} finally {
		await context.close()
	}
})

// @e2e widgets::place-an-app-tile
test('choosing Case system under App tiles and Add widget places a one-cell tile for Case system', async ({
	browser,
}) => {
	const before = new Set((await readPlacements(own.owner.api, own.id)).map((p) => Number(p.id)))
	const { context, page, picker } = await openPicker(browser)
	try {
		const section = picker.locator('section').filter({ has: page.getByRole('heading', { name: 'App tiles', exact: true }) }).first()
		const tile = section.getByRole('button', { name: new RegExp(`^${CASE_SYSTEM}`) }).first()
		await expect(tile).toBeVisible()
		// The section ends with the entry Own tile.
		await expect(section.getByRole('button', { name: /^Own tile/ })).toBeVisible()

		await tile.click()
		// Chosen, not yet placed.
		expect((await readPlacements(own.owner.api, own.id)).length).toBe(before.size)
		await picker.getByRole('button', { name: 'Add widget', exact: true }).click()
		await expect(picker).toBeHidden({ timeout: 15_000 })

		await expect.poll(async () => (await readPlacements(own.owner.api, own.id)).length, { timeout: 15_000 })
			.toBe(before.size + 1)
		const placed = (await readPlacements(own.owner.api, own.id)).find((p) => !before.has(Number(p.id)))!
		const name = String(placed.tileTitle ?? placed.customTitle ?? placed.content?.title ?? '')
		expect(name).toBe(CASE_SYSTEM)
		expect([Number(placed.gridWidth), Number(placed.gridHeight)], 'the tile is not one cell').toEqual([1, 1])
	} finally {
		await context.close()
	}
})

// @e2e widgets::embedded-page-not-allowed
test('for a role that may not add Embedded page its card is grey, says "Not allowed for your role" and cannot be chosen', async ({
	browser,
}) => {
	const before = (await readPlacements(own.owner.api, own.id)).length
	const { context, page, picker } = await openPicker(browser)
	try {
		const card = picker.getByRole('button', { name: /^Embedded page/ })
			.or(picker.locator('.widget-picker__widget, [data-testid^="widget-picker-card"]').filter({ hasText: 'Embedded page' }))
			.first()
		await expect(card, 'Embedded page is not shown at all').toBeVisible()
		await expect(card).toContainText('Not allowed for your role')
		expect(await card.evaluate((el) => el.getAttribute('aria-disabled') === 'true' || el.hasAttribute('disabled')), 'the card is not disabled').toBe(true)

		// Grey: the card is visibly dimmed or desaturated against an allowed one.
		const allowed = picker.getByRole('button', { name: /^Clock/ }).first()
		const look = (el: Element): string => {
			const style = getComputedStyle(el)
			return `${style.opacity}|${style.filter}|${style.color}`
		}
		expect(await card.evaluate(look)).not.toBe(await allowed.evaluate(look))

		// It cannot be chosen: a click selects nothing and Add widget places nothing.
		await card.click({ force: true })
		await expect(card).not.toHaveAttribute('aria-pressed', 'true')
		await expect(card).not.toHaveAttribute('aria-selected', 'true')
		const add = picker.getByRole('button', { name: 'Add widget', exact: true })
		if (await add.isEnabled()) {
			await add.click()
		}
		await page.waitForTimeout(1_000)
		expect((await readPlacements(own.owner.api, own.id)).length).toBe(before)
	} finally {
		await context.close()
	}
})
