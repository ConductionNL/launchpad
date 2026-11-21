/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the workspace shell
 * (openspec/specs/runtime-shell), written for e2e-coverage-programme.
 *
 * Every test seeds what it reads: its own dashboard, its own group and its own
 * throwaway accounts, stamped per run and removed in afterAll. The edit-gate
 * and first-visit scenarios need an account that is NOT the suite's admin, or
 * an administrator who has never opened LaunchPad, so they provision one over
 * basic auth (support/principals.ts) and log it in in a fresh browser context
 * (fixtures/secondary-user.ts).
 *
 * Where the surfaces live (read from the source, not guessed):
 *  - the "action menu" of a dashboard is the per-row cog in the switcher
 *    (src/components/Workspace/DashboardRowActions.vue, aria-label
 *    "Dashboard menu"); the start page's "More" menu
 *    (DashboardMoreMenu.vue) carries the same edit entries under the same
 *    gate, so the non-edit test checks both;
 *  - the widget context menu is WidgetContextMenu.vue
 *    (`data-testid="widget-context-menu"`), opened from
 *    `useGridManager.onWidgetRightClick`, which calls `preventDefault()` only
 *    when it opens;
 *  - the backdrop is SidebarBackdrop.vue (`.launchpad-sidebar-backdrop`),
 *    teleported to body beside the sidebar by Views.vue;
 *  - the support note is nextcloud-vue's CnSupportDialog
 *    (`data-testid-modal="cn-support-dialog"`, title "Support {appName}"),
 *    mounted by CnAppRoot unless App.vue passes `:supportDialog="false"`;
 *  - both tours are nextcloud-vue's CnWalkthrough (`.cn-walkthrough`), from
 *    src/manifest.json `walkthrough`; the admin page mounts its own in
 *    src/components/admin/AdminSettings.vue.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::non-edit-user-has-no-edit-affordances-visible
 *   @e2e runtime-shell::canedit-user-sees-full-edit-menu
 *   @e2e runtime-shell::backdrop-closes-sidebar-on-click
 *   @e2e runtime-shell::click-on-the-sidebar-itself-does-not-close-it
 *   @e2e runtime-shell::a-members-first-visit-shows-no-support-note
 *   @e2e runtime-shell::an-administrators-first-visit-keeps-the-note
 *   @e2e runtime-shell::a-first-visit-starts-the-tour
 *   @e2e runtime-shell::opening-dashboards-advances-the-tour
 *   @e2e runtime-shell::a-finished-tour-stays-closed
 *   @e2e runtime-shell::an-administrators-first-visit-to-the-admin-page
 *   @e2e runtime-shell::a-finished-admin-tour-stays-closed
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

import type { APIRequestContext, Browser, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { MORE_MENU, openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import {
	ADMIN_TOUR_KEY,
	quietFirstVisit,
	readPreference,
	SUPPORT_NOTE_KEY,
	WORKSPACE_TOUR_KEY,
} from './support/firstVisit.ts'
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
const ADMIN_PAGE_URL = '/index.php/settings/admin/launchpad'
const RUN = Date.now()

const ROW = '.dashboard-switcher-sidebar__item'
const SIDEBAR = '.dashboard-switcher-sidebar'
const CONTEXT_MENU = '[data-testid="widget-context-menu"]'
const TOUR = '.cn-walkthrough'
const TOUR_CARD = '.cn-walkthrough__card'

/** The support note, by its accessible name or by the library's test id. */
function supportNote(page: Page): Locator {
	return page
		.getByRole('dialog', { name: /support launchpad/i })
		.or(page.locator('[data-testid-modal="cn-support-dialog"]'))
}

/**
 * Add one text widget to a dashboard, as the admin.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @return the placement id.
 */
async function addTextWidget(
	api: APIRequestContext,
	dashboardId: number,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			content: { text: `seeded ${RUN}`, contentMode: 'markdown' },
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id ?? body.placement?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * Open LaunchPad and wait until the dashboard view has rendered its controls.
 *
 * @param page the page.
 * @return nothing.
 */
async function openLaunchPad(page: Page): Promise<void> {
	await page.goto(APP_URL)
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
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
 * Record, for every right-click from now on, whether the page suppressed the
 * browser's own menu. A bubble-phase listener on `window` runs after every
 * handler on the widget, so it sees the final `defaultPrevented`.
 *
 * @param page the page.
 * @return nothing.
 */
async function recordContextMenus(page: Page): Promise<void> {
	await page.evaluate(() => {
		const w = window as unknown as { lpContextMenus: boolean[] }
		w.lpContextMenus = []
		window.addEventListener('contextmenu', (event) => {
			w.lpContextMenus.push(event.defaultPrevented)
		})
	})
}

/**
 * The `defaultPrevented` of every right-click recorded so far.
 *
 * @param page the page.
 * @return one boolean per right-click.
 */
async function recordedContextMenus(page: Page): Promise<boolean[]> {
	return page.evaluate(
		() => (window as unknown as { lpContextMenus: boolean[] }).lpContextMenus,
	)
}

/**
 * Close the switcher sidebar with its own close button.
 *
 * @param page the page.
 * @return nothing.
 */
async function closeSwitcher(page: Page): Promise<void> {
	await page.locator('.dashboard-switcher-sidebar__close').click()
	await expect(page.locator(SIDEBAR)).not.toHaveClass(/\bopen\b/)
}

/**
 * Log an account in, in its own browser context.
 *
 * @param browser the browser.
 * @param who the account.
 * @return the context and page.
 */
async function sessionOf(browser: Browser, who: Principal) {
	return loginAs(browser, who.username, who.password, { tour: true })
}

/**
 * Provision a throwaway administrator: an ordinary account in Nextcloud's
 * `admin` group, which is what makes `isAdmin` true for LaunchPad.
 *
 * @param admin an admin context.
 * @param prefix the account name prefix.
 * @return the principal.
 */
async function createAdministrator(
	admin: APIRequestContext,
	prefix: string,
): Promise<Principal> {
	const who = await createPrincipal(admin, prefix)
	await addToGroup(admin, who.username, 'admin')
	return who
}

/**
 * Open a page and wait until a first-visit preference (a tour's completion
 * key, or the support note's flag) has been read from the server, which is
 * the moment the library decides whether to open it, plus a beat for that
 * decision to render.
 *
 * @param page the page.
 * @param url where to go.
 * @param key the preference key.
 * @return nothing.
 */
async function gotoAndSettle(page: Page, url: string, key: string): Promise<void> {
	const read = page.waitForResponse(
		(res) =>
			res.request().method() === 'GET'
			&& res.url().includes(`/api/preferences/${key}`),
		{ timeout: 30_000 },
	)
	await page.goto(url)
	expect((await read).status()).toBe(200)
	// The overlay mounts on the next render after the read resolves.
	await page.waitForTimeout(1_500)
}

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SHELL-002 Edit affordances gated by canEdit
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-SHELL-002: edit affordances gated by canEdit', () => {
	let admin: APIRequestContext
	let member: Principal | null = null
	let group: string | null = null
	let groupDashboard: { id: number; uuid: string } | null = null
	let groupPlacement = 0
	let adminDashboard: SeededDashboard | null = null
	let adminPlacement = 0
	const GROUP_DASH = `E2E Shell group ${RUN}`
	const ADMIN_DASH = `E2E Shell admin ${RUN}`

	test.beforeAll(async () => {
		admin = await adminContext()

		// A member of a fresh group, viewing that group's shared dashboard.
		// `createGroupShared` stores `view_only` (DashboardService), so the
		// member's dashboardSource is `group` and nothing grants them edit.
		group = await createGroup(admin, `e2e-shell-${RUN}`)
		member = await createPrincipal(admin, 'e2e-shell-member')
		await assertActingAs(member.api, member.username)
		await addToGroup(admin, member.username, group)
		const created = await admin.post(`${API}/dashboards/group/${group}`, {
			data: { name: GROUP_DASH },
		})
		expect(created.status(), await created.text()).toBe(201)
		const dash = (await created.json()).dashboard
		groupDashboard = { id: Number(dash.id), uuid: String(dash.uuid) }
		groupPlacement = await addTextWidget(admin, groupDashboard.id)
		await quietFirstVisit(member.api)
		const active = await member.api.post(`${API}/dashboards/active`, {
			data: { uuid: groupDashboard.uuid },
		})
		expect(active.status(), await active.text()).toBeLessThan(300)

		// The admin's own dashboard, active for the admin session.
		adminDashboard = await seedActiveDashboard(admin, ADMIN_DASH)
		adminPlacement = await addTextWidget(admin, adminDashboard.id)
	})

	test.afterAll(async () => {
		if (groupDashboard && group) {
			await admin.delete(
				`${API}/dashboards/group/${group}/${groupDashboard.uuid}`,
			)
		}
		await removeSeededDashboard(admin, adminDashboard)
		await removePrincipal(admin, member)
		await removeGroup(admin, group)
		await admin.dispose()
	})

	// @e2e runtime-shell::non-edit-user-has-no-edit-affordances-visible
	test('a member viewing a group dashboard gets no edit entries and the native context menu', async ({
		browser,
	}) => {
		const { context, page } = await sessionOf(browser, member!)
		try {
			await openLaunchPad(page)
			// CONTROL: the member really is on the seeded group dashboard.
			const placement = page.locator(`[data-placement-id="${groupPlacement}"]`)
			await expect(placement).toBeVisible({ timeout: 15_000 })

			// The More menu: its "This dashboard" group renders (Set as
			// default is offered to everyone), the edit entries do not.
			await page
				.locator(`${MORE_MENU} [data-testid="cn-grouped-menu-trigger"]`)
				.click()
			const panel = page.locator('[data-testid="cn-grouped-menu-panel"]')
			await expect(
				panel.locator('[data-testid="cn-grouped-menu-item-set-default"]'),
			).toBeVisible()
			for (const id of ['edit', 'add-widget', 'add-custom-widget', 'config']) {
				await expect(
					panel.locator(`[data-testid="cn-grouped-menu-item-${id}"]`),
				).toHaveCount(0)
			}
			await expect(panel).not.toContainText(
				/Add custom widget|Save dashboard|Dashboard configuration/,
			)
			await page.keyboard.press('Escape')

			// The dashboard's own action menu, the row cog in the switcher.
			await openDashboardSwitcher(page)
			await row(page, GROUP_DASH)
				.locator('.dashboard-row-actions button')
				.first()
				.click()
			await expect(
				page.getByRole('menuitem', {
					name: /Set as default|Default dashboard/,
				}),
			).toBeVisible()
			await expect(
				page.getByRole('menuitem', { name: /Add custom widget/ }),
			).toHaveCount(0)
			await expect(
				page.getByRole('menuitem', {
					name: /Save dashboard|Edit dashboard/,
				}),
			).toHaveCount(0)
			await expect(
				page.getByRole('menuitem', { name: /Dashboard configuration/ }),
			).toHaveCount(0)
			await page.keyboard.press('Escape')
			await closeSwitcher(page)

			// Right-click falls through to the browser: nothing suppresses the
			// native menu and LaunchPad's own popover does not open.
			await recordContextMenus(page)
			await placement.click({ button: 'right' })
			expect(await recordedContextMenus(page)).toEqual([false])
			await expect(page.locator(CONTEXT_MENU)).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	// @e2e runtime-shell::canedit-user-sees-full-edit-menu
	test('an admin sees every edit entry and the widget context menu', async ({
		page,
	}) => {
		// expect.soft throughout: each clause is its own finding, and one
		// missing entry must not hide whether the others are there.
		await openLaunchPad(page)
		const placement = page.locator(`[data-placement-id="${adminPlacement}"]`)
		await expect(placement).toBeVisible({ timeout: 15_000 })

		await openDashboardSwitcher(page)
		await row(page, ADMIN_DASH)
			.locator('.dashboard-row-actions button')
			.first()
			.click()
		// CONTROL: the menu is open.
		await expect(
			page.getByRole('menuitem', { name: /Set as default|Default dashboard/ }),
		).toBeVisible()
		// The edit entry reads "Edit dashboard" outside edit mode and
		// "Save dashboard" inside it.
		for (const name of [
			/Add custom widget…/,
			/Save dashboard|Edit dashboard/,
			/Dashboard configuration…/,
		]) {
			await expect
				.soft(
					page.getByRole('menuitem', { name }),
					`action menu entry ${name}`,
				)
				.toBeVisible()
		}
		await page.keyboard.press('Escape')
		// Documentation moved out of the row menu into the switcher's footer.
		await expect
			.soft(page.locator(SIDEBAR).getByRole('link', { name: /Documentation/ }))
			.toBeVisible()
		await closeSwitcher(page)

		await recordContextMenus(page)
		await placement.click({ button: 'right' })
		expect
			.soft(
				await recordedContextMenus(page),
				'the right-click must be taken over by LaunchPad (preventDefault)',
			)
			.toEqual([true])
		await expect.soft(page.locator(CONTEXT_MENU)).toBeVisible()
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SHELL-006 Sidebar backdrop
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-SHELL-006: sidebar backdrop', () => {
	let admin: APIRequestContext
	let dashboard: SeededDashboard | null = null

	test.beforeAll(async () => {
		admin = await adminContext()
		dashboard = await seedActiveDashboard(admin, `E2E Shell backdrop ${RUN}`)
	})

	test.afterAll(async () => {
		await removeSeededDashboard(admin, dashboard)
		await admin.dispose()
	})

	test.beforeEach(async ({ page }) => {
		await openLaunchPad(page)
		await openDashboardSwitcher(page)
	})

	// @e2e runtime-shell::backdrop-closes-sidebar-on-click
	test('a click on the backdrop closes the sidebar', async ({ page }) => {
		const backdrop = page.locator('.launchpad-sidebar-backdrop')
		await expect(backdrop).toBeVisible()
		const sidebar = page.locator(SIDEBAR)
		const sidebarBox = (await sidebar.boundingBox())!
		const backdropBox = (await backdrop.boundingBox())!
		// A point of the backdrop well clear of the 280px panel and of the
		// top-right controls.
		await page.mouse.click(
			sidebarBox.x + sidebarBox.width + 200,
			backdropBox.y + backdropBox.height / 2,
		)
		await expect(sidebar).not.toHaveClass(/\bopen\b/)
		await expect(backdrop).toHaveCount(0)
	})

	// @e2e runtime-shell::click-on-the-sidebar-itself-does-not-close-it
	test('a click on the sidebar panel itself leaves it open', async ({ page }) => {
		const sidebar = page.locator(SIDEBAR)
		await expect(sidebar).toHaveClass(/\bopen\b/)
		// The panel's heading: inside the panel, and not a control.
		await page.locator('.dashboard-switcher-sidebar__title').click()
		await expect(sidebar).toHaveClass(/\bopen\b/)
		await expect(page.locator('.launchpad-sidebar-backdrop')).toBeVisible()
		// CONTROL: the same session closes it from the backdrop, so the click
		// above was not simply ignored by a sidebar that never closes.
		const box = (await sidebar.boundingBox())!
		await page.mouse.click(box.x + box.width + 200, box.y + box.height / 2)
		await expect(sidebar).not.toHaveClass(/\bopen\b/)
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SHELL-008 The first-open support note is for administrators
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-SHELL-008: the support note is for administrators', () => {
	let admin: APIRequestContext
	const people: Principal[] = []

	test.beforeAll(async () => {
		admin = await adminContext()
	})

	test.afterAll(async () => {
		for (const who of people) {
			await removePrincipal(admin, who)
		}
		await admin.dispose()
	})

	// @e2e runtime-shell::a-members-first-visit-shows-no-support-note
	test("a member's first visit opens no support note", async ({ browser }) => {
		const pieter = await createPrincipal(admin, 'e2e-shell-pieter')
		people.push(pieter)
		await assertActingAs(pieter.api, pieter.username)
		// Never opened LaunchPad: nothing recorded for the note.
		expect(await readPreference(pieter.api, SUPPORT_NOTE_KEY)).toBeNull()

		const { context, page } = await sessionOf(browser, pieter)
		try {
			await page.goto(APP_URL)
			// CONTROL, and the readiness check: the first-visit tour opens.
			// CnAppRoot withholds it while the note is open, so by the time
			// it shows, the note has had its chance.
			await expect(
				page.locator(`${TOUR_CARD} .cn-walkthrough__title`),
			).toHaveText('Welcome to Launchpad', { timeout: 30_000 })
			// The overlay layer renders the note on its own schedule; give a
			// late note the time to arrive before asserting it never did.
			await page.waitForTimeout(1_500)
			await expect(supportNote(page)).toHaveCount(0)
			// The note records itself as seen the moment it shows, so a note
			// that flashed and closed would have left this behind.
			expect(await readPreference(pieter.api, SUPPORT_NOTE_KEY)).toBeNull()
		} finally {
			await context.close()
		}
	})

	// @e2e runtime-shell::an-administrators-first-visit-keeps-the-note
	test("an administrator's first visit opens the note, once", async ({
		browser,
	}) => {
		const ruben = await createAdministrator(admin, 'e2e-shell-ruben')
		people.push(ruben)
		expect(await readPreference(ruben.api, SUPPORT_NOTE_KEY)).toBeNull()

		const { context, page } = await sessionOf(browser, ruben)
		try {
			await page.goto(APP_URL)
			const note = supportNote(page)
			await expect(note.first()).toBeVisible({ timeout: 30_000 })
			await expect(
				page.locator('[data-testid-modal="cn-support-dialog"]').first(),
			).toContainText(/Support Launchpad/i)
			await page.keyboard.press('Escape')
			await expect(note).toHaveCount(0)
			await expect
				.poll(() => readPreference(ruben.api, SUPPORT_NOTE_KEY))
				.toBe('1')
		} finally {
			await context.close()
		}

		// Once: a new browser (no local mirror) on the same account does not
		// get it again. The library reads the server flag before deciding,
		// so wait for that read rather than for the page alone.
		const again = await sessionOf(browser, ruben)
		try {
			await gotoAndSettle(again.page, APP_URL, SUPPORT_NOTE_KEY)
			await expect(again.page.locator(MORE_MENU)).toBeVisible()
			await expect(supportNote(again.page)).toHaveCount(0)
		} finally {
			await again.context.close()
		}
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SHELL-010 First-visit tour
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Walk the Workspace tour to its end the way a person does: Next on the
 * welcome, Dashboards in the menu, Finish on the Flows step.
 *
 * @param page the page, with the welcome step open.
 * @return nothing.
 */
async function finishWorkspaceTour(page: Page): Promise<void> {
	const card = page.locator(TOUR_CARD)
	await card.getByRole('button', { name: /next/i }).click()
	await expect(card).toContainText('Open Dashboards from the menu.')
	await page.locator('[data-testid="cn-nav-entry-dashboards"]').click()
	await expect(card).toContainText('Flows automate the repetitive parts')
	await card.getByRole('button', { name: /finish/i }).click()
	await expect(page.locator(TOUR)).toHaveCount(0)
}

test.describe('REQ-SHELL-010: the Workspace first-visit tour', () => {
	let admin: APIRequestContext
	const people: Principal[] = []
	let railSuppressedBefore = false

	test.beforeAll(async () => {
		admin = await adminContext()
		// The tour's second step points at the navigation panel, which the
		// option "Start page without navigation panel" removes. Make sure the
		// panel is there, and put the option back afterwards.
		const res = await admin.get(`${API}/admin/settings`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		railSuppressedBefore = Boolean(
			(body.data ?? body).startPageWithoutNavigation,
		)
		if (railSuppressedBefore) {
			const put = await admin.put(`${API}/admin/settings`, {
				data: { startPageWithoutNavigation: false },
			})
			expect(put.status(), await put.text()).toBeLessThan(300)
		}
	})

	test.afterAll(async () => {
		if (railSuppressedBefore) {
			await admin.put(`${API}/admin/settings`, {
				data: { startPageWithoutNavigation: true },
			})
		}
		for (const who of people) {
			await removePrincipal(admin, who)
		}
		await admin.dispose()
	})

	/**
	 * A member who has never opened LaunchPad.
	 *
	 * @return the principal.
	 */
	async function freshMember(): Promise<Principal> {
		const who = await createPrincipal(admin, 'e2e-shell-tour')
		people.push(who)
		await assertActingAs(who.api, who.username)
		expect(await readPreference(who.api, WORKSPACE_TOUR_KEY)).toBeNull()
		return who
	}

	// @e2e runtime-shell::a-first-visit-starts-the-tour
	test('a first visit opens the welcome step in the centre of the Workspace page', async ({
		browser,
	}) => {
		const pieter = await freshMember()
		const { context, page } = await sessionOf(browser, pieter)
		try {
			await page.goto(APP_URL)
			const card = page.locator(TOUR_CARD)
			await expect(card.locator('.cn-walkthrough__title')).toHaveText(
				'Welcome to Launchpad',
				{ timeout: 30_000 },
			)
			// On the Workspace page: the `/` route, whose view is WorkspaceApp.
			expect(new URL(page.url()).pathname).toMatch(/\/apps\/launchpad\/?$/)
			await expect(page.locator('.workspace-shell')).toBeAttached()

			// In the centre: the centred layout, over a full-page dim, and
			// geometrically in the middle of the viewport.
			await expect(card).toHaveClass(/cn-walkthrough__card--centered/)
			await expect(page.locator('.cn-walkthrough__dim--full')).toBeVisible()
			const box = (await card.boundingBox())!
			const viewport = page.viewportSize()!
			const dx = Math.abs(box.x + box.width / 2 - viewport.width / 2)
			const dy = Math.abs(box.y + box.height / 2 - viewport.height / 2)
			expect(dx).toBeLessThan(viewport.width * 0.1)
			expect(dy).toBeLessThan(viewport.height * 0.15)
		} finally {
			await context.close()
		}
	})

	// @e2e runtime-shell::opening-dashboards-advances-the-tour
	test('clicking Dashboards in the menu moves the tour on to the Flows step', async ({
		browser,
	}) => {
		const pieter = await freshMember()
		const { context, page } = await sessionOf(browser, pieter)
		try {
			await page.goto(APP_URL)
			const card = page.locator(TOUR_CARD)
			await expect(card.locator('.cn-walkthrough__title')).toHaveText(
				'Welcome to Launchpad',
				{ timeout: 30_000 },
			)
			await card.getByRole('button', { name: /next/i }).click()
			await expect(card).toContainText('Open Dashboards from the menu.')
			// CONTROL: the step waits for the click; it has not moved on alone.
			await expect(card).not.toContainText('Flows automate')

			await page.locator('[data-testid="cn-nav-entry-dashboards"]').click()
			await expect(card).toContainText(
				'Flows automate the repetitive parts of this app.',
			)
			await expect(card).toContainText('Open Flows from the menu')
		} finally {
			await context.close()
		}
	})

	// @e2e runtime-shell::a-finished-tour-stays-closed
	test('a finished tour does not start again, in this browser or another', async ({
		browser,
	}) => {
		const pieter = await freshMember()
		const first = await sessionOf(browser, pieter)
		try {
			await first.page.goto(APP_URL)
			// CONTROL: the tour does start for this account before it is done.
			await expect(first.page.locator(TOUR)).toBeVisible({ timeout: 30_000 })
			await finishWorkspaceTour(first.page)
			// Completion is stored per user, against a version.
			await expect
				.poll(() => readPreference(pieter.api, WORKSPACE_TOUR_KEY))
				.toBeTruthy()

			await gotoAndSettle(first.page, APP_URL, WORKSPACE_TOUR_KEY)
			await expect(first.page.locator(MORE_MENU)).toBeVisible()
			await expect(first.page.locator(TOUR)).toHaveCount(0)
		} finally {
			await first.context.close()
		}

		const second = await sessionOf(browser, pieter)
		try {
			await gotoAndSettle(second.page, APP_URL, WORKSPACE_TOUR_KEY)
			await expect(second.page.locator(MORE_MENU)).toBeVisible()
			await expect(second.page.locator(TOUR)).toHaveCount(0)
		} finally {
			await second.context.close()
		}
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-SHELL-011 The admin area has its own first-visit tour
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-SHELL-011: the admin page tour', () => {
	let admin: APIRequestContext
	const people: Principal[] = []

	test.beforeAll(async () => {
		admin = await adminContext()
	})

	test.afterAll(async () => {
		for (const who of people) {
			await removePrincipal(admin, who)
		}
		await admin.dispose()
	})

	/**
	 * An administrator who has never opened the LaunchPad admin page.
	 *
	 * @return the principal.
	 */
	async function freshAdministrator(): Promise<Principal> {
		const noor = await createAdministrator(admin, 'e2e-shell-noor')
		people.push(noor)
		expect(await readPreference(noor.api, ADMIN_TOUR_KEY)).toBeNull()
		return noor
	}

	// @e2e runtime-shell::an-administrators-first-visit-to-the-admin-page
	test("an administrator's first visit starts the admin tour on the general settings", async ({
		browser,
	}) => {
		const noor = await freshAdministrator()
		const { context, page } = await sessionOf(browser, noor)
		try {
			await page.goto(ADMIN_PAGE_URL)
			const card = page.locator(TOUR_CARD)
			await expect(card.locator('.cn-walkthrough__title')).toHaveText(
				'General settings',
				{ timeout: 30_000 },
			)
			// On the general settings tab, spotlighting its settings card.
			await expect(adminTab(page, 'general')).toHaveAttribute(
				'aria-selected',
				'true',
			)
			const section = page.locator('[data-testid="admin-default-settings"]')
			await expect(section).toBeVisible()
			const ring = (await page.locator('.cn-walkthrough__ring').boundingBox())!
			const target = (await section.boundingBox())!
			expect(ring.x).toBeLessThan(target.x + target.width)
			expect(ring.x + ring.width).toBeGreaterThan(target.x)
			expect(ring.y).toBeLessThan(target.y + target.height)
			expect(ring.y + ring.height).toBeGreaterThan(target.y)
		} finally {
			await context.close()
		}
	})

	// @e2e runtime-shell::a-finished-admin-tour-stays-closed
	test('a finished admin tour does not start again', async ({ browser }) => {
		const noor = await freshAdministrator()
		const first = await sessionOf(browser, noor)
		try {
			await first.page.goto(ADMIN_PAGE_URL)
			const card = first.page.locator(TOUR_CARD)
			const title = card.locator('.cn-walkthrough__title')
			// CONTROL: the tour starts for her before she finishes it.
			await expect(title).toHaveText('General settings', { timeout: 30_000 })
			for (const next of [
				'Templates',
				'Roles and permissions',
				'Sharing',
				'Operations',
			]) {
				await card.getByRole('button', { name: /next/i }).click()
				await expect(title).toHaveText(next)
			}
			await card.getByRole('button', { name: /finish/i }).click()
			await expect(first.page.locator(TOUR)).toHaveCount(0)
			await expect
				.poll(() => readPreference(noor.api, ADMIN_TOUR_KEY))
				.toBeTruthy()

			await gotoAndSettle(first.page, ADMIN_PAGE_URL, ADMIN_TOUR_KEY)
			await expect(
				first.page.locator('[data-testid="admin-default-settings"]'),
			).toBeVisible()
			await expect(first.page.locator(TOUR)).toHaveCount(0)
		} finally {
			await first.context.close()
		}

		// Stored per user, not per browser.
		const second = await sessionOf(browser, noor)
		try {
			await gotoAndSettle(second.page, ADMIN_PAGE_URL, ADMIN_TOUR_KEY)
			await expect(
				second.page.locator('[data-testid="admin-default-settings"]'),
			).toBeVisible()
			await expect(second.page.locator(TOUR)).toHaveCount(0)
		} finally {
			await second.context.close()
		}
	})
})
