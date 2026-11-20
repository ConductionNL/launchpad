/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Role feature permissions (openspec/specs/role-feature-permissions/spec.md,
 * REQ-RFP-001 .. REQ-RFP-010): which widgets a user's Nextcloud groups let
 * them see, add and read, which layout a first-time user is seeded with, and
 * who may manage the rows that decide both.
 *
 * WHAT THE PRODUCT ACTUALLY IS, AS OPPOSED TO WHAT THE SPEC'S DATA MODEL SAYS.
 *  - The rows live in LaunchPad's own tables, not in OpenRegister. The only
 *    way in is the admin API in `RoleFeaturePermissionApiController`
 *    (`/api/role-feature-permissions`, `/api/role-layout-defaults`, upsert by
 *    groupId / groupId+widgetId, 201 on every save).
 *  - The allow-list is resolved by `RoleFeaturePermissionService::
 *    getAllowedWidgetIds()`, which only looks at groups that are in the
 *    `group_order` admin setting. So every group this file makes is put at
 *    the front of `group_order`, and the original order is put back after.
 *  - Nextcloud admins are never restricted (break-glass), so every probe is
 *    a throwaway ordinary account over basic auth (`support/principals.ts`).
 *  - The spec names widgets such as `analytics_dashboard` that no instance
 *    registers. A filter that drops an unknown id cannot be seen to work, so
 *    the filtering tests take REAL widget ids from the instance's registry
 *    (the admin's unfiltered `GET /api/widgets`) and stand them in for the
 *    spec's names. Layout seeding never consults the registry, so the
 *    layout tests use the spec's own ids.
 *  - Every group gets a unique, stamped id, so the restrictive `default` row
 *    other specs install (`ensureDefaultWidgetRestriction`) and the demo rows
 *    the repair step seeds never decide an outcome here. The REQ-RFP-009 and
 *    REQ-RFP-010 "unconfigured" tests remove EVERY row for their duration and
 *    put each one back afterwards.
 *
 * REFUSALS are probed by an ordinary account, checked for the exact status
 * the controller returns, re-read as someone who can see the result, and
 * paired with a principal for whom the same request succeeds.
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { RoleFeaturePermissionRow } from './fixtures/role-feature-permissions.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	deleteRoleFeaturePermission,
	deleteRoleLayoutDefault,
	listRoleFeaturePermissions,
	listRoleLayoutDefaults,
	readGroupOrder,
	removeFromGroup,
	restoreRoleFeaturePermissions,
	saveRoleFeaturePermission,
	saveRoleLayoutDefault,
	writeGroupOrder,
} from './fixtures/role-feature-permissions.ts'
import { loginAs } from './fixtures/secondary-user.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	addToGroup,
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

test.setTimeout(120_000)

const STAMP = `${Date.now()}`
const PREFIX = `e2e-rfp-${STAMP}`

/** One group per role the scenarios name, plus private ones for layout tests. */
const G = {
	managers: `${PREFIX}-managers`,
	medewerkers: `${PREFIX}-medewerkers`,
	hrm: `${PREFIX}-hrm`,
	denyManagers: `${PREFIX}-deny-managers`,
	allStaff: `${PREFIX}-all-staff`,
	layout: `${PREFIX}-layout`,
	layoutUpdate: `${PREFIX}-layout-update`,
	layoutKeep: `${PREFIX}-layout-keep`,
	order: `${PREFIX}-order`,
	orderUpdate: `${PREFIX}-order-update`,
	slot: `${PREFIX}-slot`,
	initial: `${PREFIX}-initial`,
	rldCreate: `${PREFIX}-rld-create`,
	unconfigured: `${PREFIX}-unconfigured`,
}

let admin: APIRequestContext
let originalGroupOrder: string[] = []
const principals: Principal[] = []
const dashboards: { api: APIRequestContext; id: number }[] = []

/** The instance's full widget registry, as the unrestricted admin sees it. */
let registry: { id: string; title: string }[] = []
/** Stands in for `analytics_dashboard` / `system_monitor`: a real widget the employee role does not get. */
let RESTRICTED = ''
/** A real widget every role here gets (stands in for `activity`). */
let SHARED = ''
/** A real widget only the employee role gets (stands in for `notes` / `calendar`). */
let EMPLOYEE_ONLY = ''

function titleOf(id: string): string {
	const found = registry.find((w) => w.id === id)
	if (found === undefined) {
		throw new Error(`widget ${id} is not in the registry`)
	}
	return found.title
}

/** The registry ids a role should see, in registry order. */
function registryIdsIn(ids: string[]): string[] {
	return registry.map((w) => w.id).filter((id) => ids.includes(id))
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	await allowPersonalDashboards(admin, true)

	const all = await admin.get(`${API}/widgets`)
	expect(all.status(), await all.text()).toBe(200)
	registry = ((await all.json()) as any[]).map((w) => ({
		id: String(w.id),
		title: String(w.title),
	}))
	const distinctTitles = new Set(registry.map((w) => w.title))
	expect(
		registry.length,
		'the filtering tests need at least three registered dashboard widgets',
	).toBeGreaterThanOrEqual(3)
	expect(
		distinctTitles.size,
		'widget titles must tell the widgets apart in the picker',
	).toBe(registry.length)
	;[RESTRICTED, SHARED, EMPLOYEE_ONLY] = registry.slice(0, 3).map((w) => w.id)

	for (const groupId of Object.values(G)) {
		await createGroup(admin, groupId)
	}

	// `getAllowedWidgetIds()` and the layout seeding only read groups that
	// are in group_order, and the first match wins: ours go first. The
	// unconfigured group is in it on purpose, so REQ-RFP-009 s.2 is about a
	// missing row and not about a missing group_order entry.
	originalGroupOrder = await readGroupOrder(admin)
	await writeGroupOrder(admin, [
		G.managers,
		G.medewerkers,
		G.hrm,
		G.denyManagers,
		G.allStaff,
		G.layout,
		G.layoutUpdate,
		G.layoutKeep,
		G.order,
		G.orderUpdate,
		G.slot,
		G.initial,
		G.rldCreate,
		G.unconfigured,
		...originalGroupOrder.filter((g) => !g.startsWith(PREFIX)),
	])

	// The two roles most scenarios are about. managers does NOT carry the
	// employee-only widget, so a union across both is observable.
	await saveRoleFeaturePermission(admin, {
		groupId: G.medewerkers,
		name: 'Medewerkers (e2e)',
		allowedWidgets: [SHARED, EMPLOYEE_ONLY],
	})
	await saveRoleFeaturePermission(admin, {
		groupId: G.managers,
		name: 'Managers (e2e)',
		allowedWidgets: [SHARED, RESTRICTED],
	})
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	for (const { api, id } of dashboards) {
		await api.delete(`${API}/dashboard/${id}`).catch(() => undefined)
	}
	for (const row of await listRoleFeaturePermissions(admin)) {
		if (row.groupId.startsWith(PREFIX)) {
			await deleteRoleFeaturePermission(admin, row.id)
		}
	}
	for (const row of await listRoleLayoutDefaults(admin)) {
		if (row.groupId.startsWith(PREFIX)) {
			await deleteRoleLayoutDefault(admin, row.id)
		}
	}
	await writeGroupOrder(admin, originalGroupOrder)
	for (const p of principals) {
		await removePrincipal(admin, p)
	}
	for (const groupId of Object.values(G)) {
		await removeGroup(admin, groupId)
	}
	await admin.dispose()
})

// ─── helpers ─────────────────────────────────────────────────────────────

/** A throwaway account in the given groups, proven to be who it says. */
async function member(prefix: string, ...groups: string[]): Promise<Principal> {
	const p = await createPrincipal(admin, `${PREFIX.slice(0, 12)}-${prefix}`)
	principals.push(p)
	for (const g of groups) {
		await addToGroup(admin, p.username, g)
	}
	await assertActingAs(p.api, p.username)
	return p
}

/** The widget ids `GET /api/widgets` returns for this context, asserting the 200. */
async function widgetIds(api: APIRequestContext): Promise<string[]> {
	const res = await api.get(`${API}/widgets`)
	expect(res.status(), await res.text()).toBe(200)
	return ((await res.json()) as any[]).map((w) => String(w.id))
}

/** `GET /api/widgets/items` for one widget id, the way `api.getWidgetItems` sends it. */
async function widgetItems(api: APIRequestContext, widgetId: string) {
	return api.get(`${API}/widgets/items?widgets[]=${encodeURIComponent(widgetId)}`)
}

/**
 * The user's effective dashboard (`GET /api/dashboard`). For a first-time
 * user this is the call that provisions their dashboard and, with role
 * layout defaults, seeds it.
 */
async function effectiveDashboard(
	p: Principal,
): Promise<{ dashboard: any; placements: any[] }> {
	const res = await p.api.get(`${API}/dashboard`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	if (body.dashboard?.id && body.dashboard?.userId === p.username) {
		if (!dashboards.some((d) => d.id === Number(body.dashboard.id))) {
			dashboards.push({ api: p.api, id: Number(body.dashboard.id) })
		}
	}
	return { dashboard: body.dashboard, placements: body.placements ?? [] }
}

/** Placements of a role-seeded first dashboard, insisting it is the user's own. */
async function seededPlacements(p: Principal): Promise<any[]> {
	const { dashboard, placements } = await effectiveDashboard(p)
	expect(
		dashboard?.userId,
		'a first-time user with role layout defaults must get a dashboard of their own',
	).toBe(p.username)
	return placements
}

/** A personal dashboard the user owns and has active, so the picker is theirs to open. */
async function ownDashboard(p: Principal, name: string): Promise<number> {
	const created = await p.api.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body
	const id = Number(dash.id)
	dashboards.push({ api: p.api, id })
	const activate = await p.api.post(`${API}/dashboard/${id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const pref = await p.api.post(`${API}/dashboards/active`, {
		data: { uuid: dash.uuid },
	})
	expect(pref.status(), await pref.text()).toBeLessThan(300)
	return id
}

const PICKER_WIDGETS =
	'.widget-picker__list > button.widget-picker__widget .widget-picker__widget-title'

/**
 * Open the card library (More → Add widget) on the start page and return
 * the titles of the Nextcloud widgets it lists. Only the widget buttons
 * that sit directly in the list count; the catalogue, office pack and
 * discovered-apps sections reuse the same classes inside their own sections.
 */
async function pickerWidgetTitles(page: Page, mustList: string): Promise<string[]> {
	await page.goto('/index.php/apps/launchpad/')
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
	await page.locator('[data-testid="cn-grouped-menu-item-add-widget"]').click()
	const picker = page.locator('.widget-picker')
	await expect(picker).toBeVisible({ timeout: 15_000 })
	// Wait until the list has rendered, so an absence below is a real one.
	await expect(
		picker.locator(PICKER_WIDGETS, { hasText: mustList }).first(),
	).toBeVisible({
		timeout: 15_000,
	})
	return (await picker.locator(PICKER_WIDGETS).allTextContents()).map((t) =>
		t.trim(),
	)
}

async function browserAs(browser: Browser, p: Principal) {
	return loginAs(browser, p.username, p.password)
}

/** The decoded `allowedWidgets` initial-state value on the page. */
async function initialAllowedWidgets(page: Page): Promise<unknown> {
	const input = page.locator('#initial-state-launchpad-allowedWidgets')
	await expect(input).toHaveCount(1, { timeout: 30_000 })
	const raw = await input.getAttribute('value')
	expect(raw, 'the allowedWidgets initial state carries no value').not.toBeNull()
	return JSON.parse(Buffer.from(String(raw), 'base64').toString('utf8'))
}

/**
 * Run `body` with no RoleFeaturePermission rows at all, then put every row
 * back. Used only by the "unconfigured installation" scenarios.
 */
async function withoutAnyPermissionRows(body: () => Promise<void>): Promise<void> {
	const snapshot: RoleFeaturePermissionRow[] =
		await listRoleFeaturePermissions(admin)
	try {
		for (const row of snapshot) {
			await deleteRoleFeaturePermission(admin, row.id)
		}
		expect(await listRoleFeaturePermissions(admin)).toEqual([])
		await body()
	} finally {
		const left = await listRoleFeaturePermissions(admin)
		for (const row of left) {
			await deleteRoleFeaturePermission(admin, row.id)
		}
		await restoreRoleFeaturePermissions(admin, snapshot)
	}
}

// ─── REQ-RFP-001: feature presence by role ───────────────────────────────

// @e2e role-feature-permissions::employee-role-user-does-not-see-admin-only-widgets
test('an employee-role user gets only the employee widgets from GET /api/widgets', async () => {
	const jan = await member('jan', G.medewerkers)
	const ids = await widgetIds(jan.api)
	expect(ids).toEqual(registryIdsIn([SHARED, EMPLOYEE_ONLY]))
	expect(ids).not.toContain(RESTRICTED)

	// Control: the filter is per role, not a blanket cut. A manager gets it.
	const manager = await member('mgr', G.managers)
	expect(await widgetIds(manager.api)).toContain(RESTRICTED)
})

// @e2e role-feature-permissions::admin-updates-role-permissions-affected-user-sees-updated-set-on-reload
test('an admin widening a role shows the user the new widget on reload, in the API and the card library', async ({
	browser,
}) => {
	await saveRoleFeaturePermission(admin, {
		groupId: G.hrm,
		allowedWidgets: [SHARED],
	})
	const fatima = await member('fatima', G.hrm)
	await ownDashboard(fatima, `RFP hrm ${STAMP}`)
	expect(await widgetIds(fatima.api)).not.toContain(EMPLOYEE_ONLY)

	// EMPLOYEE_ONLY stands in for `user_status`.
	await saveRoleFeaturePermission(admin, {
		groupId: G.hrm,
		allowedWidgets: [SHARED, EMPLOYEE_ONLY],
	})
	expect(await widgetIds(fatima.api)).toContain(EMPLOYEE_ONLY)

	const { context, page } = await browserAs(browser, fatima)
	try {
		const titles = await pickerWidgetTitles(page, titleOf(EMPLOYEE_ONLY))
		expect(titles).toContain(titleOf(EMPLOYEE_ONLY))
	} finally {
		await context.close()
	}
})

// @e2e role-feature-permissions::direct-url-request-to-restricted-feature-returns-403
test("a direct request for a restricted widget's items is refused with 403 and no content", async () => {
	// The spec's example URL is `?widgetId=analytics_dashboard`. The real
	// endpoint reads `widgets[]` (WidgetApiController::getItems and
	// src/services/api.js getWidgetItems); with `widgetId=` it would see no
	// widget at all and answer 200 with nothing, so the real parameter is used.
	const pieter = await member('pieter', G.medewerkers)
	const refused = await widgetItems(pieter.api, RESTRICTED)
	expect(refused.status(), await refused.text()).toBe(403)
	const body = await refused.json()
	expect(body).toEqual({ message: 'Not authorized' })
	expect(body).not.toHaveProperty(RESTRICTED)

	// The audit entry is a `role_permission_denied` warning written to the
	// Nextcloud log (WidgetApiController::deniedWidgetIds), which no response
	// exposes; it is pinned by tests/Unit/Controller/WidgetApiControllerRoleTest.php.

	// Control: the same request from a role that has the widget is served.
	const manager = await member('mgr403', G.managers)
	const served = await widgetItems(manager.api, RESTRICTED)
	expect(served.status(), await served.text()).toBe(200)
})

// ─── REQ-RFP-002: role default layout ────────────────────────────────────

// @e2e role-feature-permissions::new-user-receives-role-appropriate-default-layout
test("a first-time user is seeded from their role's layout defaults, compulsory flag kept", async () => {
	await saveRoleLayoutDefault(admin, {
		groupId: G.layout,
		widgetId: 'analytics_dashboard',
		gridX: 0,
		gridY: 0,
		gridWidth: 8,
		gridHeight: 6,
		sortOrder: 1,
		isCompulsory: true,
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.layout,
		widgetId: 'activity',
		gridX: 8,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 6,
		sortOrder: 2,
		isCompulsory: false,
	})

	const noor = await member('noor', G.layout)
	const placements = await seededPlacements(noor)
	expect(placements).toHaveLength(2)

	const analytics = placements.find((pl) => pl.widgetId === 'analytics_dashboard')
	const activity = placements.find((pl) => pl.widgetId === 'activity')
	expect(analytics).toMatchObject({
		gridX: 0,
		gridY: 0,
		gridWidth: 8,
		gridHeight: 6,
		sortOrder: 1,
		isCompulsory: 1,
	})
	expect(activity).toMatchObject({
		gridX: 8,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 6,
		sortOrder: 2,
		isCompulsory: 0,
	})
})

// @e2e role-feature-permissions::admin-updates-role-defaults-new-user-receives-updated-layout
test('a role default added later reaches the next new user and leaves earlier users alone', async () => {
	await saveRoleLayoutDefault(admin, {
		groupId: G.layoutUpdate,
		widgetId: 'activity',
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 6,
		sortOrder: 1,
	})
	const earlier = await member('early', G.layoutUpdate)
	const before = await seededPlacements(earlier)
	expect(before.map((pl) => pl.widgetId)).toEqual(['activity'])

	await saveRoleLayoutDefault(admin, {
		groupId: G.layoutUpdate,
		widgetId: 'user_status',
		gridX: 0,
		gridY: 7,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 3,
	})

	const later = await member('later', G.layoutUpdate)
	const seeded = await seededPlacements(later)
	expect(seeded.find((pl) => pl.widgetId === 'user_status')).toMatchObject({
		gridX: 0,
		gridY: 7,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 3,
	})

	// The earlier user's dashboard is exactly what it was.
	const after = (await effectiveDashboard(earlier)).placements
	expect(after.map((pl) => [pl.id, pl.widgetId, pl.gridX, pl.gridY])).toEqual(
		before.map((pl) => [pl.id, pl.widgetId, pl.gridX, pl.gridY]),
	)
})

// @e2e role-feature-permissions::existing-personal-customisations-are-preserved-when-role-defaults-change
test("changing role defaults never touches an existing user's customised placements", async () => {
	await saveRoleLayoutDefault(admin, {
		groupId: G.layoutKeep,
		widgetId: 'activity',
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 1,
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.layoutKeep,
		widgetId: 'notes',
		gridX: 4,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 2,
	})

	const sem = await member('sem', G.layoutKeep)
	const seeded = await seededPlacements(sem)
	expect(seeded).toHaveLength(2)

	// sem makes both placements their own.
	for (const [index, pl] of seeded.entries()) {
		const moved = await sem.api.put(`${API}/widgets/${pl.id}`, {
			data: { gridX: 2 + index * 5, gridY: 9, gridWidth: 3, gridHeight: 3 },
		})
		expect(moved.status(), await moved.text()).toBe(200)
	}
	const customised = (await effectiveDashboard(sem)).placements
	const shape = (list: any[]) =>
		list
			.map((pl) => ({
				id: pl.id,
				widgetId: pl.widgetId,
				gridX: pl.gridX,
				gridY: pl.gridY,
				gridWidth: pl.gridWidth,
				gridHeight: pl.gridHeight,
			}))
			.sort((a, b) => a.id - b.id)
	expect(shape(customised).map((pl) => pl.gridY)).toEqual([9, 9])

	// The admin moves one default and adds another.
	await saveRoleLayoutDefault(admin, {
		groupId: G.layoutKeep,
		widgetId: 'activity',
		gridX: 8,
		gridY: 8,
		gridWidth: 2,
		gridHeight: 2,
		sortOrder: 1,
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.layoutKeep,
		widgetId: 'user_status',
		gridX: 0,
		gridY: 12,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 3,
	})

	const reloaded = (await effectiveDashboard(sem)).placements
	expect(shape(reloaded)).toEqual(shape(customised))
})

// ─── REQ-RFP-003: role-based card library ────────────────────────────────

// @e2e role-feature-permissions::manager-only-card-is-hidden-for-employee-role-user
test('the card library of an employee-role user does not list the manager-only widget', async ({
	browser,
}) => {
	const annemarie = await member('annemarie', G.medewerkers)
	expect(await widgetIds(annemarie.api)).not.toContain(RESTRICTED)
	await ownDashboard(annemarie, `RFP employee ${STAMP}`)

	const { context, page } = await browserAs(browser, annemarie)
	try {
		const titles = await pickerWidgetTitles(page, titleOf(SHARED))
		expect(titles).toContain(titleOf(SHARED))
		expect(titles).not.toContain(titleOf(RESTRICTED))
		// Absent from the DOM, not merely hidden (spec NFR, accessibility).
		await expect(
			page.locator(PICKER_WIDGETS, { hasText: titleOf(RESTRICTED) }),
		).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e role-feature-permissions::user-holds-a-role-granting-card-access-only-permitted-cards-listed
test('the card library of a manager lists the manager widget and nothing outside the role', async ({
	browser,
}) => {
	const mark = await member('mark', G.managers)
	await ownDashboard(mark, `RFP manager ${STAMP}`)

	const { context, page } = await browserAs(browser, mark)
	try {
		const titles = await pickerWidgetTitles(page, titleOf(RESTRICTED))
		expect(titles).toContain(titleOf(RESTRICTED))
		expect([...titles].sort()).toEqual(
			registryIdsIn([SHARED, RESTRICTED]).map(titleOf).sort(),
		)
	} finally {
		await context.close()
	}
})

// @e2e role-feature-permissions::admin-revokes-role-previously-visible-cards-no-longer-accessible
test('removing a user from the granting group drops the widget and hides, not deletes, its placement', async () => {
	const jan = await member('janrevoke', G.managers, G.medewerkers)
	const dashboardId = await ownDashboard(jan, `RFP revoke ${STAMP}`)
	expect(await widgetIds(jan.api)).toContain(RESTRICTED)

	const placed = await jan.api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: RESTRICTED,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		},
	})
	expect(placed.status(), await placed.text()).toBe(201)
	const placementId = Number((await placed.json()).id)

	await removeFromGroup(admin, jan.username, G.managers)

	expect(await widgetIds(jan.api)).not.toContain(RESTRICTED)

	const shown = await jan.api.get(`${API}/dashboard/${dashboardId}`)
	expect(shown.status(), await shown.text()).toBe(200)
	const placement = ((await shown.json()).placements ?? []).find(
		(pl: any) => Number(pl.id) === placementId,
	)
	expect(placement, 'the placement must be kept, not deleted').toBeDefined()
	// SUSPECTED BUG: nothing in the render path consults the allow-list —
	// the only consumers of RoleFeaturePermissionService are the widget list,
	// getItems, addWidget, the MCP add tool and the initial state
	// (grep isWidgetAllowed|getAllowedWidgetIds in lib/). The placement comes
	// back with isVisible 1 (DashboardApiController::show, WidgetPlacement::
	// jsonSerialize), where the spec says 0 at render time.
	expect(placement.isVisible).toBe(0)
})

// ─── REQ-RFP-004: role-and-priority ordering ─────────────────────────────

// @e2e role-feature-permissions::new-dashboard-respects-role-priority-weights
test("a seeded dashboard follows the role's priority order", async () => {
	await saveRoleFeaturePermission(admin, {
		groupId: G.order,
		allowedWidgets: ['analytics_dashboard', 'activity', 'user_status'],
		priorityWeights: { analytics_dashboard: 10, activity: 8, user_status: 6 },
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.order,
		widgetId: 'user_status',
		gridX: 8,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 3,
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.order,
		widgetId: 'analytics_dashboard',
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 1,
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.order,
		widgetId: 'activity',
		gridX: 4,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 2,
	})

	const user = await member('order', G.order)
	const placements = await seededPlacements(user)
	const bySort = [...placements].sort((a, b) => a.sortOrder - b.sortOrder)
	expect(bySort.map((pl) => [pl.widgetId, pl.sortOrder])).toEqual([
		['analytics_dashboard', 1],
		['activity', 2],
		['user_status', 3],
	])
	const byGrid = [...placements].sort(
		(a, b) => a.gridY - b.gridY || a.gridX - b.gridX,
	)
	expect(byGrid.map((pl) => pl.widgetId)).toEqual([
		'analytics_dashboard',
		'activity',
		'user_status',
	])
})

// @e2e role-feature-permissions::admin-updates-priority-weights-next-loaded-dashboard-reflects-new-order
test("raising a widget's weight and sort order puts it first for the next new user", async () => {
	await saveRoleFeaturePermission(admin, {
		groupId: G.orderUpdate,
		allowedWidgets: ['analytics_dashboard', 'activity'],
		priorityWeights: { analytics_dashboard: 10, activity: 8 },
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.orderUpdate,
		widgetId: 'analytics_dashboard',
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 1,
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.orderUpdate,
		widgetId: 'activity',
		gridX: 4,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 2,
	})

	await saveRoleFeaturePermission(admin, {
		groupId: G.orderUpdate,
		priorityWeights: { analytics_dashboard: 10, activity: 12 },
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.orderUpdate,
		widgetId: 'activity',
		sortOrder: 1,
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.orderUpdate,
		widgetId: 'analytics_dashboard',
		sortOrder: 2,
	})

	const user = await member('reorder', G.orderUpdate)
	const placements = await seededPlacements(user)
	expect(placements.find((pl) => pl.widgetId === 'activity')?.sortOrder).toBe(1)
	expect(
		placements.find((pl) => pl.widgetId === 'analytics_dashboard')?.sortOrder,
	).toBe(2)
})

// @e2e role-feature-permissions::unpermitted-high-priority-widget-is-hidden-next-eligible-widget-takes-its-slot
test('a layout default the role may not use is not rendered, and the next widget takes the first slot', async () => {
	// The only way an unpermitted widget reaches an employee's dashboard is a
	// layout default for their own group, so that is what is set up: the
	// restricted widget first (sortOrder 1), an allowed one second. The
	// managers default the scenario mentions is added too.
	await saveRoleFeaturePermission(admin, {
		groupId: G.slot,
		allowedWidgets: [SHARED],
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.managers,
		widgetId: RESTRICTED,
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 1,
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.slot,
		widgetId: RESTRICTED,
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 1,
	})
	await saveRoleLayoutDefault(admin, {
		groupId: G.slot,
		widgetId: SHARED,
		gridX: 0,
		gridY: 4,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 2,
	})

	const jan = await member('janslot', G.slot)
	expect(await widgetIds(jan.api)).not.toContain(RESTRICTED)

	const placements = await seededPlacements(jan)
	const visible = placements
		.filter((pl) => pl.isVisible !== 0)
		.sort(
			(a, b) =>
				a.sortOrder - b.sortOrder || a.gridY - b.gridY || a.gridX - b.gridX,
		)
	// SUSPECTED BUG: RoleFeaturePermissionService::seedLayoutFromRoleDefaults()
	// copies every RoleLayoutDefault row without asking isWidgetAllowed(), and
	// nothing hides the placement at render time, so the restricted widget is
	// seeded visible in the first slot.
	expect(visible.map((pl) => pl.widgetId)).not.toContain(RESTRICTED)
	expect(visible[0]?.widgetId).toBe(SHARED)
})

// ─── REQ-RFP-005: multi-group precedence ─────────────────────────────────

// @e2e role-feature-permissions::user-sees-role-default-layout-matching-primary-highest-priority-group
test("a member of two roles gets the primary role's widgets widened by the other role's", async () => {
	const noor = await member('noormulti', G.medewerkers, G.managers)
	const ids = await widgetIds(noor.api)
	expect(ids).toEqual(registryIdsIn([SHARED, RESTRICTED, EMPLOYEE_ONLY]))

	// Control: each role alone gives less, so the union is real.
	const onlyManager = await member('onlymgr', G.managers)
	expect(await widgetIds(onlyManager.api)).not.toContain(EMPLOYEE_ONLY)
})

// @e2e role-feature-permissions::user-in-multiple-groups-deny-wins-rule-applies
test("a deny from any of a user's roles beats an allow from another", async () => {
	// RESTRICTED stands in for `system_monitor`.
	await saveRoleFeaturePermission(admin, {
		groupId: G.denyManagers,
		allowedWidgets: [SHARED, RESTRICTED],
	})
	await saveRoleFeaturePermission(admin, {
		groupId: G.allStaff,
		allowedWidgets: [EMPLOYEE_ONLY],
		deniedWidgets: [RESTRICTED],
	})

	const priya = await member('priya', G.denyManagers, G.allStaff)
	const ids = await widgetIds(priya.api)
	expect(ids).not.toContain(RESTRICTED)
	expect(ids).toEqual(registryIdsIn([SHARED, EMPLOYEE_ONLY]))

	// Control: the allowing role alone does show it.
	const onlyAllow = await member('onlyallow', G.denyManagers)
	expect(await widgetIds(onlyAllow.api)).toContain(RESTRICTED)
})

// @e2e role-feature-permissions::active-role-changes-mid-session-dashboard-re-renders-with-new-permissions
test('joining a higher-priority role mid-session shows its widgets after a reload', async ({
	browser,
}) => {
	const sem = await member('semjoin', G.medewerkers)
	await ownDashboard(sem, `RFP join ${STAMP}`)

	const { context, page } = await browserAs(browser, sem)
	try {
		const before = await pickerWidgetTitles(page, titleOf(SHARED))
		expect(before).not.toContain(titleOf(RESTRICTED))

		await addToGroup(admin, sem.username, G.managers)

		// The same browser session, reloaded.
		await page.reload()
		const res = await page.request.get(`${API}/widgets`)
		expect(res.status()).toBe(200)
		const ids = ((await res.json()) as any[]).map((w) => String(w.id))
		expect(ids).toEqual(registryIdsIn([SHARED, RESTRICTED, EMPLOYEE_ONLY]))

		const after = await pickerWidgetTitles(page, titleOf(RESTRICTED))
		expect(after).toContain(titleOf(RESTRICTED))
	} finally {
		await context.close()
	}
})

// ─── REQ-RFP-006: role, interest and journey ─────────────────────────────

// @e2e role-feature-permissions::user-assigned-a-role-sees-only-widgets-permitted-for-that-role
test("an employee's widget set stays the role's, whatever they add or request", async () => {
	const henk = await member('henk', G.medewerkers)
	const expected = registryIdsIn([SHARED, EMPLOYEE_ONLY])
	expect(await widgetIds(henk.api)).toEqual(expected)

	// Interest: henk places a widget he may use, and asks for one he may not.
	const dashboardId = await ownDashboard(henk, `RFP henk ${STAMP}`)
	const placed = await henk.api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: { widgetId: SHARED, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 4 },
	})
	expect(placed.status(), await placed.text()).toBe(201)
	expect((await widgetItems(henk.api, RESTRICTED)).status()).toBe(403)

	// Neither widened the set.
	expect(await widgetIds(henk.api)).toEqual(expected)
})

// @e2e role-feature-permissions::user-expresses-interest-without-a-matching-role-access-denied-audit-logged
test('interest in a widget without the role is refused, and nothing is saved or served', async () => {
	const jan = await member('janinterest', G.medewerkers)
	const dashboardId = await ownDashboard(jan, `RFP interest ${STAMP}`)

	// A saved preference: placing the widget on his own dashboard.
	const saved = await jan.api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: RESTRICTED,
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 4,
		},
	})
	expect(saved.status(), await saved.text()).toBe(403)

	// A direct request for its content.
	const content = await widgetItems(jan.api, RESTRICTED)
	expect(content.status(), await content.text()).toBe(403)
	expect(await content.json()).toEqual({ message: 'Not authorized' })

	// Nothing landed, read back as the admin.
	const asAdmin = await admin.get(`${API}/dashboard/${dashboardId}`)
	expect(asAdmin.status(), await asAdmin.text()).toBe(200)
	const placements = (await asAdmin.json()).placements ?? []
	expect(placements.map((pl: any) => pl.widgetId)).not.toContain(RESTRICTED)

	// Control: the same save for a widget the role has succeeds.
	const allowed = await jan.api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: { widgetId: SHARED, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 4 },
	})
	expect(allowed.status(), await allowed.text()).toBe(201)

	// SUSPECTED BUG (not observable here): the spec wants an audit entry with
	// reason "interest_without_role". The only entry written is a log warning
	// with reason "role_permission_denied" (WidgetApiController::
	// deniedWidgetIds), and only on getItems, not on addWidget;
	// `git log --all -S interest_without_role -- lib src appinfo` is empty.
})

// @e2e role-feature-permissions::active-role-changes-dashboard-reflects-new-roles-permissions-after-refresh
test("after joining a higher-priority role, the next GET /api/widgets is the new role's set", async () => {
	const noor = await member('noorjourney', G.medewerkers)
	const before = await widgetIds(noor.api)
	expect(before).toEqual(registryIdsIn([SHARED, EMPLOYEE_ONLY]))

	await addToGroup(admin, noor.username, G.managers)

	const after = await widgetIds(noor.api)
	expect(after).toContain(RESTRICTED)
	expect(after).toEqual(registryIdsIn([SHARED, RESTRICTED, EMPLOYEE_ONLY]))
	expect(after).not.toEqual(before)
})

// ─── REQ-RFP-007: admin CRUD for role-feature-permissions ────────────────

// @e2e role-feature-permissions::admin-creates-a-role-feature-permission
test('an admin creates a role-feature-permission and can read it back', async () => {
	const groupId = `${PREFIX}-communicatie`
	const created = await admin.post(`${API}/role-feature-permissions`, {
		data: {
			groupId,
			name: 'Communicatie widget-rechten',
			allowedWidgets: ['activity', 'notes', 'calendar'],
			deniedWidgets: [],
			priorityWeights: { calendar: 10, activity: 8 },
		},
	})
	expect(created.status(), await created.text()).toBe(201)
	const body = await created.json()
	expect(typeof body.id).toBe('number')
	expect(body).toMatchObject({
		groupId,
		name: 'Communicatie widget-rechten',
		allowedWidgets: ['activity', 'notes', 'calendar'],
		deniedWidgets: [],
		priorityWeights: { calendar: 10, activity: 8 },
	})

	const listed = await listRoleFeaturePermissions(admin)
	expect(listed.find((row) => row.id === body.id)).toMatchObject({ groupId })
})

// @e2e role-feature-permissions::non-admin-user-cannot-create-or-modify-role-feature-permissions
test('an ordinary user cannot create or delete a role-feature-permission', async () => {
	const jan = await member('jannoadmin', G.medewerkers)
	const groupId = `${PREFIX}-forbidden`
	const payload = { groupId, name: 'not allowed', allowedWidgets: [RESTRICTED] }

	const create = await jan.api.post(`${API}/role-feature-permissions`, {
		data: payload,
	})
	expect(create.status(), await create.text()).toBe(403)

	const existing = (await listRoleFeaturePermissions(admin)).find(
		(row) => row.groupId === G.medewerkers,
	)
	expect(existing).toBeDefined()
	const remove = await jan.api.delete(
		`${API}/role-feature-permissions/${existing!.id}`,
	)
	expect(remove.status(), await remove.text()).toBe(403)

	// Re-read as the admin: nothing created, nothing removed, and jan still
	// does not get the widget he tried to grant himself.
	const after = await listRoleFeaturePermissions(admin)
	expect(after.some((row) => row.groupId === groupId)).toBe(false)
	expect(after.find((row) => row.id === existing!.id)).toMatchObject({
		groupId: G.medewerkers,
	})
	expect(await widgetIds(jan.api)).not.toContain(RESTRICTED)

	// Control: the admin may.
	const asAdmin = await admin.post(`${API}/role-feature-permissions`, {
		data: payload,
	})
	expect(asAdmin.status(), await asAdmin.text()).toBe(201)
})

// @e2e role-feature-permissions::admin-lists-all-role-feature-permissions
test('an admin lists every role-feature-permission with its widget fields', async () => {
	await saveRoleFeaturePermission(admin, {
		groupId: G.hrm,
		allowedWidgets: [SHARED],
	})
	const listed = await admin.get(`${API}/role-feature-permissions`)
	expect(listed.status(), await listed.text()).toBe(200)
	const rows = (await listed.json()) as any[]

	for (const groupId of [G.medewerkers, G.managers, G.hrm]) {
		const row = rows.find((r) => r.groupId === groupId)
		expect(row, `no row for ${groupId}`).toBeDefined()
		expect(Array.isArray(row.allowedWidgets)).toBe(true)
		expect(Array.isArray(row.deniedWidgets)).toBe(true)
		expect(row.priorityWeights).not.toBeNull()
		expect(typeof row.priorityWeights).toBe('object')
		expect(Array.isArray(row.priorityWeights)).toBe(false)
	}
	expect(rows.find((r) => r.groupId === G.managers).allowedWidgets).toEqual([
		SHARED,
		RESTRICTED,
	])
})

// ─── REQ-RFP-008: admin CRUD for role-layout-defaults ────────────────────

// @e2e role-feature-permissions::admin-creates-a-role-layout-default
test('an admin creates a role-layout-default and the next member is seeded with it', async () => {
	const created = await admin.post(`${API}/role-layout-defaults`, {
		data: {
			groupId: G.rldCreate,
			widgetId: 'user_status',
			name: 'Manager — gebruikersstatus',
			gridX: 0,
			gridY: 7,
			gridWidth: 4,
			gridHeight: 4,
			sortOrder: 3,
			isCompulsory: false,
		},
	})
	expect(created.status(), await created.text()).toBe(201)
	expect(await created.json()).toMatchObject({
		groupId: G.rldCreate,
		widgetId: 'user_status',
		gridY: 7,
	})

	const joiner = await member('joiner', G.rldCreate)
	const placements = await seededPlacements(joiner)
	expect(placements.find((pl) => pl.widgetId === 'user_status')).toMatchObject({
		gridX: 0,
		gridY: 7,
		gridWidth: 4,
		gridHeight: 4,
	})
})

// @e2e role-feature-permissions::non-admin-user-cannot-manage-role-layout-defaults
test('an ordinary user cannot create a role-layout-default', async () => {
	const sem = await member('semnoadmin', G.medewerkers)
	const payload = {
		groupId: G.medewerkers,
		widgetId: `e2e-forbidden-${STAMP}`,
		name: 'not allowed',
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 4,
		sortOrder: 1,
	}

	const refused = await sem.api.post(`${API}/role-layout-defaults`, {
		data: payload,
	})
	expect(refused.status(), await refused.text()).toBe(403)
	expect(
		(await listRoleLayoutDefaults(admin)).some(
			(row) => row.widgetId === payload.widgetId,
		),
	).toBe(false)

	// Control: the admin may.
	const asAdmin = await admin.post(`${API}/role-layout-defaults`, {
		data: payload,
	})
	expect(asAdmin.status(), await asAdmin.text()).toBe(201)
	const row = await asAdmin.json()
	await deleteRoleLayoutDefault(admin, Number(row.id))
})

// ─── REQ-RFP-009: unconfigured installations ─────────────────────────────

// @e2e role-feature-permissions::no-permissions-configured-all-widgets-visible
test('with no role-feature-permission rows at all, every user gets the full widget list', async () => {
	const anyone = await member('anyone')
	const priorDefault = (await listRoleFeaturePermissions(admin)).filter(
		(row) => row.groupId === 'default',
	)

	// Control first: a restrictive `default` row does cut this user's list,
	// so the full list below is the absence of rows and not a broken filter.
	await saveRoleFeaturePermission(admin, {
		groupId: 'default',
		allowedWidgets: [SHARED],
	})
	try {
		expect(await widgetIds(anyone.api)).toEqual([SHARED])
		await withoutAnyPermissionRows(async () => {
			const res = await anyone.api.get(`${API}/widgets`)
			expect(res.status()).toBe(200)
			expect(((await res.json()) as any[]).map((w) => String(w.id))).toEqual(
				registry.map((w) => w.id),
			)
		})
	} finally {
		// Put `default` back to exactly what it was before this test.
		for (const row of (await listRoleFeaturePermissions(admin)).filter(
			(r) => r.groupId === 'default',
		)) {
			await deleteRoleFeaturePermission(admin, row.id)
		}
		await restoreRoleFeaturePermissions(admin, priorDefault)
	}
})

// @e2e role-feature-permissions::permissions-exist-for-some-groups-but-not-the-users-group
test('a user whose group has no row, with no default row, gets the full widget list', async () => {
	const jan = await member('janunconf', G.unconfigured)
	const defaults = (await listRoleFeaturePermissions(admin)).filter(
		(row) => row.groupId === 'default',
	)
	try {
		for (const row of defaults) {
			await deleteRoleFeaturePermission(admin, row.id)
		}
		const rows = await listRoleFeaturePermissions(admin)
		expect(rows.some((row) => row.groupId === G.managers)).toBe(true)
		expect(
			rows.some(
				(row) => row.groupId === G.unconfigured || row.groupId === 'default',
			),
		).toBe(false)

		expect(await widgetIds(jan.api)).toEqual(registry.map((w) => w.id))
		// Control: a configured role in the same instance is still filtered.
		const manager = await member('mgrunconf', G.managers)
		expect(await widgetIds(manager.api)).toEqual(
			registryIdsIn([SHARED, RESTRICTED]),
		)
	} finally {
		await restoreRoleFeaturePermissions(admin, defaults)
	}
})

// ─── REQ-RFP-010: initial-state allow-list ───────────────────────────────

// @e2e role-feature-permissions::initial-state-includes-allowedwidgets-for-role-configured-user
test("the page's initial state carries the role's allowedWidgets, and the card library uses it", async ({
	browser,
}) => {
	await saveRoleFeaturePermission(admin, {
		groupId: G.initial,
		allowedWidgets: [SHARED, RESTRICTED],
	})
	const noor = await member('noorinit', G.initial)
	await ownDashboard(noor, `RFP initial ${STAMP}`)

	const { context, page } = await browserAs(browser, noor)
	try {
		const listCalls: string[] = []
		page.on('request', (req) => {
			if (
				new URL(req.url()).pathname.endsWith('/apps/launchpad/api/widgets')
			) {
				listCalls.push(req.url())
			}
		})
		const titles = await pickerWidgetTitles(page, titleOf(SHARED))

		expect(await initialAllowedWidgets(page)).toEqual(
			[SHARED, RESTRICTED].sort(),
		)
		expect([...titles].sort()).toEqual(
			registryIdsIn([SHARED, RESTRICTED]).map(titleOf).sort(),
		)
		// SUSPECTED BUG: nothing in src/ reads the `allowedWidgets` initial
		// state; the card library is fed by `GET /api/widgets`
		// (src/stores/widgets.js loadAvailableWidgets), the extra call the
		// spec says the initial state exists to avoid.
		expect(listCalls, 'the card library fetched GET /api/widgets').toEqual([])
	} finally {
		await context.close()
	}
})

// @e2e role-feature-permissions::initial-state-includes-null-for-unconfigured-installation
test('with nothing configured the initial state carries null and the card library shows every widget', async ({
	browser,
}) => {
	const anyone = await member('anyoneinit')
	await ownDashboard(anyone, `RFP null ${STAMP}`)

	await withoutAnyPermissionRows(async () => {
		const { context, page } = await browserAs(browser, anyone)
		try {
			const titles = await pickerWidgetTitles(page, registry[0].title)
			expect(await initialAllowedWidgets(page)).toBeNull()
			expect([...titles].sort()).toEqual(registry.map((w) => w.title).sort())
		} finally {
			await context.close()
		}
	})
})
