/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the dashboard switcher sidebar
 * (openspec/specs/dashboard-switcher), written for e2e-coverage-programme.
 *
 * Throwaway accounts carry known dashboards, seeded through the API, so every
 * row, id and section is known before the page opens. A person reaches the
 * switcher the way the start page offers it: More → All dashboards
 * (support/dashboardSwitcher.ts). The sidebar is
 * src/components/Workspace/DashboardSwitcherSidebar.vue, teleported to body
 * by Views.vue; its rows carry `data-source` (the `source` the row emits on
 * click) and `.active`.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-switcher::all-three-sections-present
 *   @e2e dashboard-switcher::personal-section-visible-when-allowed-even-with-empty-list
 *   @e2e dashboard-switcher::click-a-personal-dashboard
 *   @e2e dashboard-switcher::click-a-default-group-dashboard
 *   @e2e dashboard-switcher::active-highlight-follows-prop
 *   @e2e dashboard-switcher::active-highlight-updates-on-prop-change
 *   @e2e dashboard-switcher::delete-lives-in-the-row-options-menu
 *   @e2e dashboard-switcher::delete-click-does-not-trigger-switch
 *   @e2e dashboard-switcher::create-button-hidden-when-feature-disabled
 *   @e2e dashboard-switcher::create-button-click
 *   @e2e dashboard-switcher::closed-state-is-off-screen
 *   @e2e dashboard-switcher::open-state-slides-in
 *   @e2e dashboard-switcher::mixed-built-in-and-custom-icons
 *   @e2e dashboard-switcher::card-visible-with-personal-dashboards-enabled
 *   @e2e dashboard-switcher::card-hidden-when-personal-dashboards-disabled
 *   @e2e dashboard-switcher::click-invokes-create-flow
 *   @e2e dashboard-switcher::footer-renders-both-brand-logos-with-safe-target-attributes
 *   @e2e dashboard-switcher::footer-documentation-link-uses-the-same-url-as-the-gear-menu-link-did
 *   @e2e dashboard-switcher::footer-stays-visible-while-list-scrolls
 *
 * @spec openspec/specs/dashboard-switcher/spec.md
 */

import type { APIRequestContext, Browser, Locator, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { loginAs } from './fixtures/secondary-user.ts'
import { MORE_MENU, openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import { quietFirstVisit } from './support/firstVisit.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const APP_URL = '/index.php/apps/launchpad'
const RUN = Date.now()

const SIDEBAR = '.dashboard-switcher-sidebar'
const ROW = '.dashboard-switcher-sidebar__item'
const ADD_CARD = '[data-testid="add-dashboard-button"]'

/** The URL SidebarFooter.vue's DOCS_URL holds, the gear menu's old target. */
const DOCS_URL = 'https://launchpad.conduction.nl/docs/intro'

type Dashboard = { id: number; uuid: string; name: string }

let admin: APIRequestContext
let allowUserDashboardsBefore = true
const people: Principal[] = []
let group: string | null = null
const groupDashboards: Dashboard[] = []

/** The member with two group, the default and two personal dashboards. */
let maria: Principal
let personalA: Dashboard
let personalB: Dashboard

/**
 * Set the admin flag that allows personal dashboards.
 *
 * @param allowed the value.
 * @return nothing.
 */
async function setAllowUserDashboards(allowed: boolean): Promise<void> {
	const res = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: allowed },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
}

/**
 * Create a personal dashboard as the account.
 *
 * @param who the account.
 * @param name the name.
 * @param icon the icon value, or undefined for none.
 * @return the dashboard.
 */
async function createPersonal(
	who: Principal,
	name: string,
	icon?: string,
): Promise<Dashboard> {
	const res = await who.api.post(`${API}/dashboard`, {
		data: icon === undefined ? { name } : { name, icon },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	return { id: Number(dash.id), uuid: String(dash.uuid), name }
}

/**
 * A throwaway member who will not meet a tour or note.
 *
 * @param prefix account name prefix.
 * @return the principal.
 */
async function freshMember(prefix: string): Promise<Principal> {
	const who = await createPrincipal(admin, prefix)
	people.push(who)
	await assertActingAs(who.api, who.username)
	await quietFirstVisit(who.api)
	return who
}

/**
 * Everything the account sees, as `/api/dashboards/visible` lists it.
 *
 * @param who the account.
 * @return the rows, each with its `source`.
 */
async function visibleTo(
	who: Principal,
): Promise<Array<{ id: number; uuid: string; name: string; source: string }>> {
	const res = await who.api.get(`${API}/dashboards/visible`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return Array.isArray(body) ? body : (body.items ?? body.data?.items ?? [])
}

/**
 * The id of the account's active dashboard, as the server resolves it.
 *
 * @param who the account.
 * @return the id.
 */
async function activeIdOf(who: Principal): Promise<number> {
	const res = await who.api.get(`${API}/dashboard`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return Number(body.dashboard?.id ?? body.data?.dashboard?.id)
}

/**
 * Wait for the switch to record a dashboard as the account's last-used one
 * (`POST /api/dashboards/active`, REQ-DASH-019). Since start-page-stays-vandaag
 * `GET /api/dashboard` answers the start dashboard, not the one switched to,
 * so the stored pick is what a switch leaves on the server.
 *
 * @param page the page.
 * @param uuid the dashboard the switch must record.
 * @return resolves once the matching request has been answered.
 * @spec openspec/changes/start-page-stays-vandaag/specs/dashboards/spec.md
 */
function lastUsedStored(page: Page, uuid: string): Promise<unknown> {
	return page.waitForResponse(
		(res) =>
			res.request().method() === 'POST'
			&& res.url().includes('/api/dashboards/active')
			&& (res.request().postData() ?? '').includes(uuid),
	)
}

/**
 * Make a personal dashboard the account's active one (both mechanisms; see
 * support/dashboardFixture.ts).
 *
 * @param who the account.
 * @param dash the dashboard.
 * @return nothing.
 */
async function activate(who: Principal, dash: Dashboard): Promise<void> {
	const legacy = await who.api.post(`${API}/dashboard/${dash.id}/activate`)
	expect(legacy.status(), await legacy.text()).toBeLessThan(300)
	const pref = await who.api.post(`${API}/dashboards/default`, {
		data: { uuid: dash.uuid },
	})
	expect(pref.status(), await pref.text()).toBeLessThan(300)
}

/**
 * Log the account in and open LaunchPad.
 *
 * @param browser the browser.
 * @param who the account.
 * @return the context and page.
 */
async function open(browser: Browser, who: Principal) {
	const session = await loginAs(browser, who.username, who.password)
	await session.page.goto(APP_URL)
	await expect(session.page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	return session
}

/**
 * The switcher row of a dashboard, by its exact name.
 *
 * @param page the page.
 * @param name the dashboard's name.
 * @return the row.
 */
function row(page: Page, name: string): Locator {
	return page.locator(ROW).filter({
		has: page.locator('.dashboard-switcher-sidebar__label', { hasText: name }),
	})
}

/**
 * Whether the sidebar is open, by its `open` class.
 *
 * @param page the page.
 * @return the sidebar locator.
 */
function sidebar(page: Page): Locator {
	return page.locator(SIDEBAR)
}

test.beforeAll(async () => {
	test.setTimeout(180_000)
	admin = await adminContext()
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status(), await settings.text()).toBe(200)
	const body = await settings.json()
	allowUserDashboardsBefore = (body.data ?? body).allowUserDashboards === true
	await setAllowUserDashboards(true)

	group = await createGroup(admin, `e2e-switcher-${RUN}`)
	maria = await freshMember('e2e-switcher-maria')
	await addToGroup(admin, maria.username, group)
	for (const name of [`E2E Group one ${RUN}`, `E2E Group two ${RUN}`]) {
		const res = await admin.post(`${API}/dashboards/group/${group}`, {
			data: { name },
		})
		expect(res.status(), await res.text()).toBe(201)
		const dash = (await res.json()).dashboard
		// A new dashboard is a draft, which only its owner sees (REQ-DASH-036).
		const published = await admin.post(`${API}/dashboards/${dash.uuid}/publish`)
		expect(published.status(), await published.text()).toBe(200)
		groupDashboards.push({ id: Number(dash.id), uuid: String(dash.uuid), name })
	}
	personalA = await createPersonal(maria, `E2E Personal A ${RUN}`)
	personalB = await createPersonal(maria, `E2E Personal B ${RUN}`)
})

test.afterAll(async () => {
	for (const dash of groupDashboards) {
		await admin.delete(`${API}/dashboards/group/${group}/${dash.uuid}`)
	}
	for (const who of people) {
		for (const dash of await visibleTo(who).catch(() => [])) {
			if (dash.source === 'user') {
				await who.api.delete(`${API}/dashboard/${dash.id}`)
			}
		}
		await removePrincipal(admin, who)
	}
	await removeGroup(admin, group)
	await setAllowUserDashboards(allowUserDashboardsBefore)
	await admin.dispose()
})

test.beforeEach(async () => {
	await setAllowUserDashboards(true)
	await activate(maria, personalA)
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SWITCH-001 Three-section navigation
// ─────────────────────────────────────────────────────────────────────────────

// @e2e dashboard-switcher::all-three-sections-present
test('the group, Default and My Dashboards sections render in order, with two dividers', async ({
	browser,
}) => {
	const visible = await visibleTo(maria)
	// The instance-wide default dashboard (SeedDefaultDashboard) gives every
	// account at least one `default` row; the spec's "1 default item".
	expect(visible.filter((d) => d.source === 'default').length).toBeGreaterThan(0)
	expect(visible.filter((d) => d.source === 'shared')).toHaveLength(0)

	const { context, page } = await open(browser, maria)
	try {
		// The heading of the group section is the injected primaryGroupName.
		const groupName = await page.evaluate(() => {
			const input = document.getElementById(
				'initial-state-launchpad-primaryGroupName',
			) as HTMLInputElement | null
			return input ? JSON.parse(atob(input.value)) : ''
		})
		await openDashboardSwitcher(page)
		const sections = sidebar(page).locator(
			'.dashboard-switcher-sidebar__section',
		)
		await expect(sections).toHaveCount(3)
		expect(
			await sections.evaluateAll((els) =>
				els.map((el) => el.getAttribute('data-section')),
			),
		).toEqual(['group', 'default', 'user'])
		await expect(
			sidebar(page).locator('.dashboard-switcher-sidebar__heading'),
		).toHaveText([groupName || 'Dashboards', 'Default', 'My Dashboards'])
		await expect(
			sidebar(page).locator('hr.dashboard-switcher-sidebar__divider'),
		).toHaveCount(2)

		// Each section holds what was seeded for it.
		const groupRows = sidebar(page).locator('[data-section="group"] li')
		await expect(groupRows).toHaveCount(2)
		for (const dash of groupDashboards) {
			await expect(groupRows.filter({ hasText: dash.name })).toHaveCount(1)
		}
		const userRows = sidebar(page).locator('[data-section="user"] li')
		await expect(userRows).toHaveCount(2)
		await expect(userRows.filter({ hasText: personalA.name })).toHaveCount(1)
		await expect(userRows.filter({ hasText: personalB.name })).toHaveCount(1)
	} finally {
		await context.close()
	}
})

// @e2e dashboard-switcher::personal-section-visible-when-allowed-even-with-empty-list
test('My Dashboards renders with only the Add-Dashboard card when the list is empty', async ({
	browser,
}) => {
	const sam = await freshMember('e2e-switcher-sam')
	expect((await visibleTo(sam)).filter((d) => d.source === 'user')).toHaveLength(0)
	const { context, page } = await open(browser, sam)
	try {
		await openDashboardSwitcher(page)
		const personal = sidebar(page).locator('[data-section="user"]')
		await expect(
			personal.locator('.dashboard-switcher-sidebar__heading'),
		).toHaveText('My Dashboards')
		await expect(personal.locator('li')).toHaveCount(0)
		await expect(personal.locator(ADD_CARD)).toHaveCount(1)
		await expect(personal.locator(ADD_CARD)).toBeVisible()
		await expect(personal.locator('button, [role="button"]')).toHaveCount(1)
	} finally {
		await context.close()
	}
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SWITCH-002 Switch click semantics
// ─────────────────────────────────────────────────────────────────────────────

// @e2e dashboard-switcher::click-a-personal-dashboard
test('clicking a personal row closes the sidebar and switches to it as a user dashboard', async ({
	browser,
}) => {
	const { context, page } = await open(browser, maria)
	try {
		await openDashboardSwitcher(page)
		const target = row(page, personalB.name)
		// The row's section is what the click emits as `source`.
		await expect(target).toHaveAttribute('data-source', 'user')

		// A `user` source takes the owned-dashboard path: activate, then load.
		const activated = page.waitForResponse(
			(res) =>
				res.request().method() === 'POST'
				&& res.url().includes(`/api/dashboard/${personalB.id}/activate`),
		)
		const loaded = page.waitForResponse(
			(res) =>
				res.request().method() === 'GET'
				&& new URL(res.url()).pathname.endsWith(
					`/api/dashboard/${personalB.id}`,
				),
		)
		const stored = lastUsedStored(page, personalB.uuid)
		await target.locator('.dashboard-switcher-sidebar__label').click()
		await expect(sidebar(page)).not.toHaveClass(/\bopen\b/)
		expect((await activated).status()).toBe(200)
		expect((await loaded).status()).toBe(200)
		await stored
		// The ordering of update:open(false) before switch() inside one tick
		// is pinned by src/components/Workspace/__tests__/
		// DashboardSwitcherSidebar.spec.js ("emits update:open(false) BEFORE
		// switch(id, source) on row click").
	} finally {
		await context.close()
	}
})

// @e2e dashboard-switcher::click-a-default-group-dashboard
test('clicking a Default row switches to it as a default dashboard, not a group one', async ({
	browser,
}) => {
	const d1 = (await visibleTo(maria)).find((d) => d.source === 'default')!
	expect(d1, 'every account sees the shipped default dashboard').toBeTruthy()
	const { context, page } = await open(browser, maria)
	try {
		const requests: string[] = []
		page.on('request', (req) =>
			requests.push(`${req.method()} ${new URL(req.url()).pathname}`),
		)
		await openDashboardSwitcher(page)
		const target = sidebar(page)
			.locator(`[data-section="default"] ${ROW}`)
			.filter({
				has: page.locator('.dashboard-switcher-sidebar__label', {
					hasText: d1.name,
				}),
			})
			.first()
		// The emitted source is the row's section, `default` — not `group`,
		// although the record is a group_shared dashboard.
		await expect(target).toHaveAttribute('data-source', 'default')
		await expect(
			sidebar(page)
				.locator(`[data-source="group"]`)
				.filter({ hasText: d1.name }),
		).toHaveCount(0)

		const loaded = page.waitForResponse(
			(res) =>
				res.request().method() === 'GET'
				&& new URL(res.url()).pathname.endsWith(`/api/dashboard/${d1.id}`),
		)
		const stored = lastUsedStored(page, d1.uuid)
		await target.locator('.dashboard-switcher-sidebar__label').click()
		await expect(sidebar(page)).not.toHaveClass(/\bopen\b/)
		expect((await loaded).status()).toBe(200)
		await stored
		// A default row is never treated as the caller's own: no legacy
		// activate call, which only a `user` source makes (stores/dashboard.js
		// switchDashboard).
		expect(
			requests.filter((r) => r.endsWith(`/api/dashboard/${d1.id}/activate`)),
		).toEqual([])
	} finally {
		await context.close()
	}
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SWITCH-003 Active item highlight
// ─────────────────────────────────────────────────────────────────────────────

// @e2e dashboard-switcher::active-highlight-follows-prop
test('the active dashboard, and only it, carries the active class', async ({
	browser,
}) => {
	const { context, page } = await open(browser, maria)
	try {
		await openDashboardSwitcher(page)
		await expect(row(page, personalA.name)).toHaveClass(/\bactive\b/)
		await expect(sidebar(page).locator(`${ROW}.active`)).toHaveCount(1)
		await expect(row(page, personalB.name)).not.toHaveClass(/\bactive\b/)
	} finally {
		await context.close()
	}
})

// @e2e dashboard-switcher::active-highlight-updates-on-prop-change
test('switching moves the active class from the old row to the new one', async ({
	browser,
}) => {
	const { context, page } = await open(browser, maria)
	try {
		await openDashboardSwitcher(page)
		await expect(row(page, personalA.name)).toHaveClass(/\bactive\b/)
		const stored = lastUsedStored(page, personalB.uuid)
		await row(page, personalB.name)
			.locator('.dashboard-switcher-sidebar__label')
			.click()
		await expect(sidebar(page)).not.toHaveClass(/\bopen\b/)
		await stored

		await openDashboardSwitcher(page)
		await expect(row(page, personalB.name)).toHaveClass(/\bactive\b/)
		await expect(row(page, personalA.name)).not.toHaveClass(/\bactive\b/)
		await expect(sidebar(page).locator(`${ROW}.active`)).toHaveCount(1)
	} finally {
		await context.close()
	}
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SWITCH-004 Personal dashboard delete affordance
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Open a row's options menu ("Dashboard menu") and return its Delete item.
 *
 * @param page the page.
 * @param target the row.
 * @return the Delete item.
 */
async function menuDelete(page: Page, target: Locator): Promise<Locator> {
	await target.getByRole('button', { name: 'Dashboard menu' }).click()
	return page.getByTestId('cog-delete')
}

// @e2e dashboard-switcher::delete-lives-in-the-row-options-menu
test('a personal row has no hover delete button; Delete is in its options menu', async ({ browser }) => {
	const { context, page } = await open(browser, maria)
	try {
		await openDashboardSwitcher(page)
		const target = row(page, personalB.name)
		await expect(target).toBeVisible()
		await target.hover()
		await expect(
			target.getByRole('button', { name: /^(delete|remove)/i }),
			'no inline delete button appears on hover',
		).toHaveCount(0)
		await expect(await menuDelete(page, target)).toBeVisible({ timeout: 5_000 })
	} finally {
		await context.close()
	}
})

// @e2e dashboard-switcher::delete-click-does-not-trigger-switch
test('choosing Delete in the options menu asks to delete and does not switch or close', async ({
	browser,
}) => {
	const { context, page } = await open(browser, maria)
	try {
		await openDashboardSwitcher(page)
		const requests: string[] = []
		page.on('request', (req) =>
			requests.push(`${req.method()} ${new URL(req.url()).pathname}`),
		)
		const target = row(page, personalB.name)
		await (await menuDelete(page, target)).click({ timeout: 5_000 })

		// deleteDashboard(id) reached the host: its confirmation opens.
		const dialog = page.getByRole('dialog', { name: /delete dashboard/i })
		await expect(dialog.first()).toBeVisible()
		await expect(dialog.first()).toContainText(personalB.name)
		// No switch: nothing loaded or activated B, and A is still active.
		expect(
			requests.filter((r) => r.includes(`/api/dashboard/${personalB.id}`)),
		).toEqual([])
		expect(await activeIdOf(maria)).toBe(personalA.id)
		// The sidebar is still open.
		await expect(sidebar(page)).toHaveClass(/\bopen\b/)
		await dialog
			.first()
			.getByRole('button', { name: /cancel/i })
			.click()
	} finally {
		await context.close()
	}
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SWITCH-005 / REQ-SWITCH-008 Create-dashboard affordance
//
// The inline "+ New Dashboard" row of REQ-SWITCH-005 was replaced by the
// "Add dashboard" card of REQ-SWITCH-008 with the same emit contract
// (DashboardSwitcherSidebar.vue header comment). Both scenarios are about the
// sidebar's create affordance, so both are asserted against the card, and the
// absence check also covers any row labelled "New Dashboard".
// ─────────────────────────────────────────────────────────────────────────────

// @e2e dashboard-switcher::create-button-hidden-when-feature-disabled
// @e2e dashboard-switcher::card-hidden-when-personal-dashboards-disabled
// @e2e dashboard-switcher::card-visible-with-personal-dashboards-enabled
test('the create card follows allowUserDashboards: below the list when on, absent when off', async ({
	browser,
}) => {
	// ON — the control arm, and REQ-SWITCH-008's visible scenario.
	let session = await open(browser, maria)
	try {
		const { page } = session
		await openDashboardSwitcher(page)
		const personal = sidebar(page).locator('[data-section="user"]')
		const card = personal.locator(ADD_CARD)
		await expect(card).toBeVisible()
		await expect(card).toHaveText(/^\s*Add dashboard\s*$/)
		await expect(card.locator('.plus-icon')).toBeVisible()
		const lastRow = (await personal.locator('li').last().boundingBox())!
		const cardBox = (await card.boundingBox())!
		expect(cardBox.y).toBeGreaterThanOrEqual(lastRow.y + lastRow.height - 1)
	} finally {
		await session.context.close()
	}

	// OFF — the same account, the same dashboards; only the flag differs.
	await setAllowUserDashboards(false)
	session = await open(browser, maria)
	try {
		const { page } = session
		await openDashboardSwitcher(page)
		// The section still renders (Maria has dashboards), so the absence
		// below is about the card, not a missing section.
		await expect(sidebar(page).locator('[data-section="user"] li')).toHaveCount(
			2,
		)
		await expect(sidebar(page).locator(ADD_CARD)).toHaveCount(0)
		await expect(
			sidebar(page).getByRole('button', {
				name: /add dashboard|new dashboard/i,
			}),
		).toHaveCount(0)
		await expect(
			sidebar(page)
				.locator(ROW)
				.filter({ hasText: /new dashboard/i }),
		).toHaveCount(0)
	} finally {
		await session.context.close()
		await setAllowUserDashboards(true)
	}
})

// @e2e dashboard-switcher::create-button-click
// @e2e dashboard-switcher::click-invokes-create-flow
test('clicking the create card closes the sidebar and the shell creates a dashboard', async ({
	browser,
}) => {
	const kim = await freshMember('e2e-switcher-kim')
	expect((await visibleTo(kim)).filter((d) => d.source === 'user')).toHaveLength(0)
	const { context, page } = await open(browser, kim)
	try {
		await openDashboardSwitcher(page)
		// The shell's create flow (Views.vue onSidebarCreateDashboard) makes
		// the personal copy through the fork route.
		const created = page.waitForResponse(
			(res) =>
				res.request().method() === 'POST'
				&& /\/api\/dashboards\/[^/]+\/fork$/.test(
					new URL(res.url()).pathname,
				),
		)
		await sidebar(page).locator(ADD_CARD).click()
		await expect(sidebar(page)).not.toHaveClass(/\bopen\b/)
		expect((await created).status()).toBeLessThan(300)
		await expect
			.poll(
				async () =>
					(await visibleTo(kim)).filter((d) => d.source === 'user').length,
			)
			.toBe(1)
		// The emit order (update:open(false), then create-dashboard() with no
		// payload) is pinned by DashboardSwitcherSidebar.spec.js ("emits
		// update:open(false) THEN create-dashboard on Add-Dashboard card click").
	} finally {
		await context.close()
	}
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SWITCH-006 Slide-in animation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * The sidebar's computed horizontal translation, in pixels.
 *
 * @param page the page.
 * @return the translation.
 */
async function translateX(page: Page): Promise<number> {
	return sidebar(page).evaluate(
		(el) => new DOMMatrix(getComputedStyle(el).transform).m41,
	)
}

// @e2e dashboard-switcher::closed-state-is-off-screen
test('closed, the sidebar is translated by its full width, off-screen', async ({
	browser,
}) => {
	const { context, page } = await open(browser, maria)
	try {
		const panel = sidebar(page)
		await expect(panel).toBeAttached()
		await expect(panel).not.toHaveClass(/\bopen\b/)
		// `width: 280px` (plus a 1px border when the box is content-box).
		const width = await panel.evaluate((el) => (el as HTMLElement).offsetWidth)
		expect(width).toBeGreaterThanOrEqual(280)
		expect(width).toBeLessThanOrEqual(281)
		// translateX(-100%) resolves to a matrix moving it its own width left.
		expect(await panel.evaluate((el) => getComputedStyle(el).transform)).toBe(
			`matrix(1, 0, 0, 1, -${width}, 0)`,
		)
		const box = (await panel.boundingBox())!
		expect(box.x + box.width).toBeLessThanOrEqual(0.5)
		await expect(panel).not.toBeInViewport()
		await expect(panel).toHaveAttribute('aria-hidden', 'true')
	} finally {
		await context.close()
	}
})

// @e2e dashboard-switcher::open-state-slides-in
test('opening animates the transform to translateX(0) over 250ms ease', async ({
	browser,
}) => {
	const { context, page } = await open(browser, maria)
	try {
		const panel = sidebar(page)
		const style = await panel.evaluate((el) => {
			const cs = getComputedStyle(el)
			return {
				property: cs.transitionProperty,
				duration: cs.transitionDuration,
				timing: cs.transitionTimingFunction,
			}
		})
		expect(style.property).toContain('transform')
		expect(style.duration).toBe('0.25s')
		expect(style.timing).toBe('ease')

		// Sample the transform on every frame while the panel opens.
		await page
			.locator(`${MORE_MENU} [data-testid="launchpad-more-menu-trigger"]`)
			.click()
		await page.evaluate(() => {
			const el = document.querySelector('.dashboard-switcher-sidebar')!
			const samples: Array<{ t: number; x: number }> = []
			const w = window as unknown as { lpSlide: Promise<typeof samples> }
			w.lpSlide = new Promise((resolve) => {
				const start = performance.now()
				const tick = () => {
					const t = performance.now()
					samples.push({
						t,
						x: new DOMMatrix(getComputedStyle(el).transform).m41,
					})
					if (t - start < 3_000) {
						requestAnimationFrame(tick)
					} else {
						resolve(samples)
					}
				}
				requestAnimationFrame(tick)
			})
		})
		await page
			.locator('[data-testid="launchpad-more-menu-item-all-dashboards"]')
			.click()
		const samples = await page.evaluate(
			() =>
				(
					window as unknown as {
						lpSlide: Promise<Array<{ t: number; x: number }>>
					}
				).lpSlide,
		)

		const first = samples.findIndex((s) => s.x > -279.5)
		const last = samples.findIndex((s) => s.x >= -0.5)
		expect(first, 'the panel started moving').toBeGreaterThanOrEqual(0)
		expect(last, 'the panel reached translateX(0)').toBeGreaterThanOrEqual(first)
		// Animated, not a jump: frames in between, at intermediate positions.
		const between = samples
			.slice(first, last)
			.filter((s) => s.x > -279.5 && s.x < -0.5)
		expect(between.length).toBeGreaterThan(2)
		const elapsed = samples[last].t - samples[Math.max(first - 1, 0)].t
		expect(elapsed).toBeGreaterThan(150)
		expect(elapsed).toBeLessThan(500)

		await expect(panel).toHaveClass(/\bopen\b/)
		expect(await translateX(page)).toBe(0)
		await expect(panel).toBeInViewport()
		expect((await panel.boundingBox())!.x).toBe(0)
	} finally {
		await context.close()
	}
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SWITCH-007 Icon rendering via shared renderer
// ─────────────────────────────────────────────────────────────────────────────

// @e2e dashboard-switcher::mixed-built-in-and-custom-icons
test('a built-in, a URL and an empty icon each render through the shared icon renderer', async ({
	browser,
}) => {
	const ina = await freshMember('e2e-switcher-ina')
	const star = await createPersonal(ina, `E2E Icon star ${RUN}`, 'Star')
	const url = await createPersonal(
		ina,
		`E2E Icon url ${RUN}`,
		'/apps/launchpad/resource/x.png',
	)
	const none = await createPersonal(ina, `E2E Icon none ${RUN}`)
	const { context, page } = await open(browser, ina)
	try {
		await openDashboardSwitcher(page)
		const icon = (name: string) =>
			row(page, name).locator('.dashboard-switcher-sidebar__icon')

		// 'Star' is a registry key: the MDI Star component.
		await expect(icon(star.name).locator('.star-icon svg')).toBeVisible()
		await expect(icon(star.name).locator('img')).toHaveCount(0)
		// A URL renders as an image of that source, at the icon size.
		const img = icon(url.name).locator('img')
		await expect(img).toHaveAttribute('src', '/apps/launchpad/resource/x.png')
		await expect(img).toHaveAttribute('width', '20')
		await expect(icon(url.name).locator('svg')).toHaveCount(0)
		// No icon falls back to the default glyph (ViewDashboard).
		await expect(
			icon(none.name).locator('.view-dashboard-icon svg'),
		).toBeVisible()
		await expect(icon(none.name).locator('img')).toHaveCount(0)
		// One renderer per row: exactly one rendered icon element each.
		for (const name of [star.name, url.name, none.name]) {
			await expect(icon(name).locator(':scope > *')).toHaveCount(1)
		}
	} finally {
		await context.close()
	}

	// The template delegates to CnDashboardIcon and does not branch on the
	// icon kind itself.
	const source = fs.readFileSync(
		path.resolve(
			__dirname,
			'../../src/components/Workspace/DashboardSwitcherSidebar.vue',
		),
		'utf8',
	)
	const template = source.slice(
		source.indexOf('<template>'),
		source.indexOf('<script>'),
	)
	expect(template).toContain('<CnDashboardIcon :name="dashboard.icon"')
	expect(template).not.toMatch(
		/v-(else-)?if="[^"]*(iconUrl|isCustomIconUrl|dashboard\.icon)/,
	)
	expect(template).not.toMatch(/<img\b/)
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SWITCH-009 Persistent sidebar footer
// ─────────────────────────────────────────────────────────────────────────────

// @e2e dashboard-switcher::footer-renders-both-brand-logos-with-safe-target-attributes
// @e2e dashboard-switcher::footer-documentation-link-uses-the-same-url-as-the-gear-menu-link-did
test('the footer links both brands safely and Documentation to the old gear-menu URL', async ({
	browser,
}) => {
	const { context, page } = await open(browser, maria)
	try {
		await openDashboardSwitcher(page)
		const footer = sidebar(page).locator('.dashboard-switcher-sidebar-footer')
		await expect(footer).toBeVisible()
		for (const [alt, host] of [
			['Sendent', 'sendent.com'],
			['Conduction', 'conduction.nl'],
		]) {
			const link = footer
				.locator('a')
				.filter({ has: page.locator(`img[alt="${alt}"]`) })
			await expect(link).toHaveCount(1)
			await expect(link).toHaveAttribute('target', '_blank')
			await expect(link).toHaveAttribute('rel', 'noopener noreferrer')
			expect(new URL((await link.getAttribute('href'))!).host).toBe(host)
		}
		await expect(
			footer.locator('a[target="_blank"]:not([rel="noopener noreferrer"])'),
		).toHaveCount(0)

		// Clicked, Documentation opens the gear menu's old destination. The
		// external site is stubbed so the run does not depend on it.
		await context.route('https://launchpad.conduction.nl/**', (route) =>
			route.fulfill({
				status: 200,
				contentType: 'text/html',
				body: '<p>docs</p>',
			}),
		)
		const docs = footer.getByRole('link', { name: /Documentation/ })
		await expect(docs).toHaveAttribute('href', DOCS_URL)
		const [popup] = await Promise.all([
			context.waitForEvent('page'),
			docs.click(),
		])
		await popup.waitForURL(DOCS_URL)
		expect(popup.url()).toBe(DOCS_URL)
	} finally {
		await context.close()
	}
})

// @e2e dashboard-switcher::footer-stays-visible-while-list-scrolls
test('the footer stays in view while 30+ dashboards scroll', async ({ browser }) => {
	test.setTimeout(180_000)
	const lee = await freshMember('e2e-switcher-lee')
	for (let i = 0; i < 32; i++) {
		await createPersonal(lee, `E2E Many ${String(i).padStart(2, '0')} ${RUN}`)
	}
	const { context, page } = await open(browser, lee)
	try {
		await openDashboardSwitcher(page)
		const body = sidebar(page).locator('.dashboard-switcher-sidebar__body')
		const footer = sidebar(page).locator('.dashboard-switcher-sidebar-footer')
		await expect(footer).toBeInViewport()

		// CONTROL: the list really overflows and really scrolls.
		const scrolled = await body.evaluate((el) => {
			el.scrollTop = el.scrollHeight
			return { top: el.scrollTop, overflow: el.scrollHeight - el.clientHeight }
		})
		expect(scrolled.overflow).toBeGreaterThan(0)
		expect(scrolled.top).toBeGreaterThan(0)

		await expect(footer).toBeVisible()
		await expect(footer).toBeInViewport({ ratio: 1 })
		const panel = (await sidebar(page).boundingBox())!
		const box = (await footer.boundingBox())!
		expect(box.y + box.height).toBeLessThanOrEqual(panel.y + panel.height + 1)
		expect(box.y).toBeGreaterThanOrEqual(panel.y)
	} finally {
		await context.close()
	}
})
