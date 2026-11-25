/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for personal dashboard CRUD, activation, the
 * personal resolution chain and serialisation (openspec/specs/dashboards,
 * REQ-DASH-001..010), written for the e2e-coverage-programme.
 *
 * These scenarios ARE the REST contract of `/api/dashboard(s)`: status codes,
 * envelopes and the stored fields. They are driven through the API, as the
 * account the scenario names: a throwaway, non-admin user per scenario, so a
 * count ("exactly one active", "an empty array") is measured on an account
 * nothing else touches. Every refusal is probed by an ordinary logged-in user,
 * re-read as the owner, and paired with the owner succeeding.
 *
 * The scenarios this file used to hold as `test.fixme` now run in
 * dashboards-refusals-and-guards.spec.ts (another user's delete and
 * activate, grid columns on update, duplicate names), outside this file's
 * serial mode, or carry a never-built exclusion in the spec
 * (create-a-dashboard-with-invalid-grid-columns, name-length-validation).
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::create-a-dashboard-with-default-settings
 *   @e2e dashboards::create-a-dashboard-with-custom-settings
 *   @e2e dashboards::create-a-dashboard-without-a-name
 *   @e2e dashboards::list-dashboards-for-a-user-with-multiple-dashboards
 *   @e2e dashboards::list-dashboards-for-a-user-with-no-dashboards
 *   @e2e dashboards::dashboards-are-user-scoped
 *   @e2e dashboards::get-the-active-dashboard
 *   @e2e dashboards::no-active-dashboard-exists-but-user-has-dashboards
 *   @e2e dashboards::first-time-user-triggers-template-distribution
 *   @e2e dashboards::update-dashboard-name-and-description
 *   @e2e dashboards::update-another-users-dashboard
 *   @e2e dashboards::update-permissionlevel-on-a-user-dashboard
 *   @e2e dashboards::batch-update-placement-positions-via-dashboard-update
 *   @e2e dashboards::delete-a-dashboard
 *   @e2e dashboards::delete-the-active-dashboard
 *   @e2e dashboards::delete-the-last-remaining-dashboard
 *   @e2e dashboards::delete-does-not-check-permission-level
 *   @e2e dashboards::activate-a-dashboard
 *   @e2e dashboards::activate-an-already-active-dashboard
 *   @e2e dashboards::only-one-active-dashboard-per-user
 *   @e2e dashboards::empty-name-defaults-to-my-dashboard
 *   @e2e dashboards::users-cannot-create-admintemplate-type-dashboards
 *   @e2e dashboards::admin-creates-a-template-dashboard
 *   @e2e dashboards::template-derived-dashboards-have-type-user
 *   @e2e dashboards::active-dashboard-found-immediately
 *   @e2e dashboards::no-active-dashboard-but-existing-dashboards
 *   @e2e dashboards::no-dashboards-at-all-with-template-available
 *   @e2e dashboards::dashboard-object-includes-all-fields
 *   @e2e dashboards::null-fields-are-included-in-serialization
 *   @e2e dashboards::timestamp-format-consistency
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRow, DashboardSettings } from './support/dashboardsSpec.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	addTextWidget,
	allowPersonalDashboardsFreely,
	createPersonal,
	DASHBOARD_FIELDS,
	listPersonal,
	showDashboard,
	TIMESTAMP,
	UUID_V4,
	writeSettings,
} from './support/dashboardsSpec.ts'
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

const RUN = Date.now()

let admin: APIRequestContext
let settingsBefore: DashboardSettings | null = null
const principals: Principal[] = []
const groups: string[] = []
const templates: number[] = []

test.describe.configure({ mode: 'serial' })
// A brand-new account's first request builds its home folder.
test.setTimeout(180_000)

/**
 * A throwaway, non-admin account that owns nothing yet.
 *
 * @param prefix a readable prefix.
 * @return the principal.
 */
async function fresh(prefix: string): Promise<Principal> {
	const principal = await createPrincipal(admin, prefix)
	principals.push(principal)
	await assertActingAs(principal.api, principal.username)
	return principal
}

/**
 * A throwaway Nextcloud group.
 *
 * @param prefix a readable prefix.
 * @return the group id.
 */
async function freshGroup(prefix: string): Promise<string> {
	const groupId = await createGroup(
		admin,
		`${prefix}-${RUN}-${Math.floor(Math.random() * 10_000)}`,
	)
	groups.push(groupId)
	return groupId
}

/**
 * An admin template handed to a group, as the admin creates it.
 *
 * @param name the template name.
 * @param data the other template fields.
 * @return the template as created.
 */
async function createTemplate(
	name: string,
	data: Record<string, unknown>,
): Promise<DashboardRow> {
	const res = await admin.post(`${API}/admin/templates`, {
		data: { name, ...data },
	})
	expect(res.status(), await res.text()).toBe(201)
	const template = (await res.json()) as DashboardRow
	templates.push(template.id)
	return template
}

/**
 * The one row of a listing with this id.
 *
 * @param rows the listing.
 * @param id the dashboard id.
 * @return the row.
 */
function byId(rows: DashboardRow[], id: number): DashboardRow {
	const row = rows.find((candidate) => candidate.id === id)
	expect(
		row,
		`dashboard ${id} is not in ${JSON.stringify(rows.map((r) => r.id))}`,
	).toBeDefined()
	return row!
}

/**
 * Wait long enough for a `Y-m-d H:i:s` timestamp to move on.
 *
 * @return nothing.
 */
async function nextSecond(): Promise<void> {
	await new Promise((resolve) => setTimeout(resolve, 1_100))
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	settingsBefore = await allowPersonalDashboardsFreely(admin)
})

test.afterAll(async () => {
	for (const id of templates) {
		await admin.delete(`${API}/admin/templates/${id}`)
	}
	for (const principal of principals) {
		await removePrincipal(admin, principal)
	}
	for (const groupId of groups) {
		await removeGroup(admin, groupId)
	}
	if (settingsBefore !== null) {
		await writeSettings(admin, settingsBefore)
	}
	await admin.dispose()
})

test.describe('REQ-DASH-001 create a personal dashboard', () => {
	// @e2e dashboards::create-a-dashboard-with-default-settings
	test("a new dashboard is the user's, full, 12 columns, and the only active one", async () => {
		const alice = await fresh('e2e-dash-create')
		const earlier = await createPersonal(alice.api, {
			name: `E2E Earlier ${RUN}`,
		})

		const res = await alice.api.post(`${API}/dashboard`, {
			data: { name: 'My Work Dashboard' },
		})
		expect(res.status(), await res.text()).toBe(201)
		const created = (await res.json()).dashboard as DashboardRow
		for (const field of DASHBOARD_FIELDS) {
			expect(created).toHaveProperty(field)
		}
		expect(created.id).toEqual(expect.any(Number))
		expect(created.uuid).toMatch(UUID_V4)
		expect(created).toMatchObject({
			name: 'My Work Dashboard',
			userId: alice.username,
			type: 'user',
			isActive: 1,
			gridColumns: 12,
			permissionLevel: 'full',
		})

		// Re-read: stored as answered, and the earlier dashboard was deactivated.
		const rows = await listPersonal(alice.api)
		expect(byId(rows, created.id)).toMatchObject({
			uuid: created.uuid,
			userId: alice.username,
			type: 'user',
			isActive: 1,
			gridColumns: 12,
			permissionLevel: 'full',
		})
		expect(byId(rows, earlier.dashboard.id).isActive).toBe(0)
		expect(
			rows.filter((row) => row.isActive === 1).map((row) => row.id),
		).toEqual([created.id])
	})

	// @e2e dashboards::create-a-dashboard-with-custom-settings
	test('name and description are stored; grid columns stay 12', async () => {
		const bob = await fresh('e2e-dash-custom')
		// The scenario's body names no column count: custom columns are not
		// part of creating a dashboard, so the default of 12 applies.
		const { dashboard } = await createPersonal(bob.api, {
			name: 'Analytics',
			description: 'Data overview',
		})
		expect(dashboard).toMatchObject({
			name: 'Analytics',
			description: 'Data overview',
			gridColumns: 12,
		})

		const stored = await showDashboard(bob.api, dashboard.id)
		expect(stored.dashboard).toMatchObject({
			name: 'Analytics',
			description: 'Data overview',
			gridColumns: 12,
		})
	})

	// @e2e dashboards::create-a-dashboard-without-a-name
	// @e2e dashboards::empty-name-defaults-to-my-dashboard
	test('a dashboard created without a name is called "My Dashboard"', async () => {
		const alice = await fresh('e2e-dash-noname')
		const res = await alice.api.post(`${API}/dashboard`, { data: {} })
		expect(res.status(), await res.text()).toBe(201)
		const created = (await res.json()).dashboard as DashboardRow
		expect(created.name).toBe('My Dashboard')

		const rows = await listPersonal(alice.api)
		expect(rows.map((row) => [row.id, row.name])).toEqual([
			[created.id, 'My Dashboard'],
		])
	})
})

test.describe("REQ-DASH-002 list the user's dashboards", () => {
	// @e2e dashboards::list-dashboards-for-a-user-with-multiple-dashboards
	test('all three dashboards come back with every field, the active one marked', async () => {
		const alice = await fresh('e2e-dash-list')
		const work = await createPersonal(alice.api, { name: 'Work' })
		const personal = await createPersonal(alice.api, { name: 'Personal' })
		const analytics = await createPersonal(alice.api, { name: 'Analytics' })
		const activate = await alice.api.post(
			`${API}/dashboard/${work.dashboard.id}/activate`,
		)
		expect(activate.status(), await activate.text()).toBe(200)

		const res = await alice.api.get(`${API}/dashboards`)
		expect(res.status()).toBe(200)
		const body = await res.json()
		const rows = (Array.isArray(body) ? body : body.items) as DashboardRow[]
		expect(rows.map((row) => row.id).sort()).toEqual(
			[work, personal, analytics].map((d) => d.dashboard.id).sort(),
		)
		for (const row of rows) {
			for (const field of DASHBOARD_FIELDS.filter((f) => f !== 'userId')) {
				expect(row, `${row.name} lacks ${field}`).toHaveProperty(field)
			}
		}
		expect(byId(rows, work.dashboard.id).isActive).toBe(1)
		expect(byId(rows, personal.dashboard.id).isActive).toBe(0)
		expect(byId(rows, analytics.dashboard.id).isActive).toBe(0)
	})

	// @e2e dashboards::list-dashboards-for-a-user-with-no-dashboards
	test('a user who never created one gets an empty list', async () => {
		const bob = await fresh('e2e-dash-empty')
		const res = await bob.api.get(`${API}/dashboards`)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(Array.isArray(body) ? body : body.items).toEqual([])
	})

	// @e2e dashboards::dashboards-are-user-scoped
	test("the list holds only the caller's own dashboards, never another user's or a template", async () => {
		const alice = await fresh('e2e-dash-scope-a')
		const bob = await fresh('e2e-dash-scope-b')
		const group = await freshGroup('e2e-dash-scope')
		await addToGroup(admin, alice.username, group)
		const template = await createTemplate(`E2E Scope template ${RUN}`, {
			targetGroups: [group],
		})

		const mine = [
			await createPersonal(alice.api, { name: `E2E Scope A1 ${RUN}` }),
			await createPersonal(alice.api, { name: `E2E Scope A2 ${RUN}` }),
			await createPersonal(alice.api, { name: `E2E Scope A3 ${RUN}` }),
		].map((d) => d.dashboard.id)
		const bobs = (await createPersonal(bob.api, { name: `E2E Scope B1 ${RUN}` }))
			.dashboard.id

		const rows = await listPersonal(alice.api)
		expect(rows.map((row) => row.id).sort()).toEqual([...mine].sort())
		expect(rows.map((row) => row.id)).not.toContain(bobs)
		expect(rows.map((row) => row.id)).not.toContain(template.id)
		expect(rows.every((row) => row.type === 'user')).toBe(true)
		// Bob's dashboard is real and his.
		expect((await listPersonal(bob.api)).map((row) => row.id)).toEqual([bobs])
	})
})

test.describe('REQ-DASH-003 get the active dashboard', () => {
	// @e2e dashboards::get-the-active-dashboard
	test('answers the active dashboard, its placements and the effective level', async () => {
		const alice = await fresh('e2e-dash-active')
		const work = await createPersonal(alice.api, { name: 'Work' })
		await addTextWidget(alice.api, work.dashboard.id, 'active', {
			gridX: 0,
			gridY: 20,
		})
		const placementIds = (
			await showDashboard(alice.api, work.dashboard.id)
		).placements.map((p) => p.id)
		expect(placementIds.length).toBeGreaterThan(0)

		const res = await alice.api.get(`${API}/dashboard`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(body.dashboard).toMatchObject({
			id: work.dashboard.id,
			name: 'Work',
			isActive: 1,
		})
		expect(Array.isArray(body.placements)).toBe(true)
		expect(body.placements.map((p: { id: number }) => p.id).sort()).toEqual(
			[...placementIds].sort(),
		)
		expect(
			body.placements.every(
				(p: { dashboardId: number }) => p.dashboardId === work.dashboard.id,
			),
		).toBe(true)
		expect(body.permissionLevel).toBe('full')
	})

	// @e2e dashboards::no-active-dashboard-exists-but-user-has-dashboards
	// @e2e dashboards::no-active-dashboard-but-existing-dashboards
	test('with dashboards but none active, the first one is activated and answered', async () => {
		const bob = await fresh('e2e-dash-noactive')
		const first = await createPersonal(bob.api, { name: `E2E First ${RUN}` })
		const second = await createPersonal(bob.api, { name: `E2E Second ${RUN}` })
		const third = await createPersonal(bob.api, { name: `E2E Third ${RUN}` })
		// Deleting the active one leaves the other two inactive (REQ-DASH-005).
		const removed = await bob.api.delete(
			`${API}/dashboard/${third.dashboard.id}`,
		)
		expect(removed.status(), await removed.text()).toBe(200)
		const before = await listPersonal(bob.api)
		expect(before.map((row) => row.isActive)).toEqual([0, 0])
		expect(before.map((row) => row.id).sort()).toEqual(
			[first.dashboard.id, second.dashboard.id].sort(),
		)

		const res = await bob.api.get(`${API}/dashboard`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(body.dashboard.id).toBe(before[0].id)
		expect(body.dashboard.isActive).toBe(1)

		const after = await listPersonal(bob.api)
		expect(
			after.filter((row) => row.isActive === 1).map((row) => row.id),
		).toEqual([before[0].id])
		expect(after).toHaveLength(2)
	})

	// @e2e dashboards::first-time-user-triggers-template-distribution
	// @e2e dashboards::no-dashboards-at-all-with-template-available
	// @e2e dashboards::template-derived-dashboards-have-type-user
	test('a first-time member of a targeted group gets an active personal copy of the template', async () => {
		const carol = await fresh('e2e-dash-tpl')
		const group = await freshGroup('e2e-dash-tpl')
		await addToGroup(admin, carol.username, group)

		// The shipped template, as the Templates page installs it, so the copy
		// has placements to carry.
		const installed = await admin.post(
			`${API}/admin/templates/shipped/mijn-werkdag/install`,
			{
				data: { force: true },
			},
		)
		expect(installed.status(), await installed.text()).toBeLessThan(300)
		const templateId = Number((await installed.json()).id)
		templates.push(templateId)
		const targeted = await admin.put(`${API}/admin/templates/${templateId}`, {
			data: { targetGroups: [group] },
		})
		expect(targeted.status(), await targeted.text()).toBe(200)
		const source = await admin.get(`${API}/admin/templates/${templateId}`)
		expect(source.status()).toBe(200)
		const sourceBody = await source.json()
		expect(sourceBody.template.type).toBe('admin_template')
		expect(sourceBody.placements.length).toBeGreaterThan(0)

		expect(await listPersonal(carol.api)).toEqual([])
		const res = await carol.api.get(`${API}/dashboard`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(body.dashboard).toMatchObject({
			type: 'user',
			userId: carol.username,
			basedOnTemplate: templateId,
			isActive: 1,
		})
		expect(body.dashboard.id).not.toBe(templateId)
		expect(body.placements).toHaveLength(sourceBody.placements.length)
		expect(
			body.placements.every(
				(p: { dashboardId: number }) => p.dashboardId === body.dashboard.id,
			),
		).toBe(true)

		// Re-read: one personal copy, active, and the template is still a template.
		const rows = await listPersonal(carol.api)
		expect(
			rows.map((row) => [row.id, row.type, row.basedOnTemplate, row.isActive]),
		).toEqual([[body.dashboard.id, 'user', templateId, 1]])
		const again = await admin.get(`${API}/admin/templates/${templateId}`)
		expect((await again.json()).template.type).toBe('admin_template')
	})
})

test.describe('REQ-DASH-004 update a dashboard', () => {
	// @e2e dashboards::update-dashboard-name-and-description
	test('name and description change and updatedAt moves on', async () => {
		const alice = await fresh('e2e-dash-update')
		const { dashboard } = await createPersonal(alice.api, { name: 'Work' })
		await nextSecond()

		const res = await alice.api.put(`${API}/dashboard/${dashboard.id}`, {
			data: { name: 'Updated Work', description: 'New desc' },
		})
		expect(res.status(), await res.text()).toBe(200)
		const updated = (await res.json()).dashboard as DashboardRow
		expect(updated).toMatchObject({
			id: dashboard.id,
			name: 'Updated Work',
			description: 'New desc',
		})
		expect(String(updated.updatedAt) > String(dashboard.updatedAt)).toBe(true)

		const stored = (await showDashboard(alice.api, dashboard.id)).dashboard
		expect(stored).toMatchObject({
			name: 'Updated Work',
			description: 'New desc',
			updatedAt: updated.updatedAt,
		})
	})

	// @e2e dashboards::update-another-users-dashboard
	test("another user's rename is refused with 403 and changes nothing", async () => {
		const alice = await fresh('e2e-dash-upd-own')
		const bob = await fresh('e2e-dash-upd-other')
		const { dashboard } = await createPersonal(alice.api, {
			name: `E2E Alice ${RUN}`,
		})

		const refused = await bob.api.put(`${API}/dashboard/${dashboard.id}`, {
			data: { name: 'Hacked' },
		})
		expect(refused.status(), await refused.text()).toBe(403)
		expect((await showDashboard(alice.api, dashboard.id)).dashboard.name).toBe(
			`E2E Alice ${RUN}`,
		)

		const allowed = await alice.api.put(`${API}/dashboard/${dashboard.id}`, {
			data: { name: `E2E Alice renamed ${RUN}` },
		})
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect((await showDashboard(alice.api, dashboard.id)).dashboard.name).toBe(
			`E2E Alice renamed ${RUN}`,
		)
	})

	// @e2e dashboards::update-permissionlevel-on-a-user-dashboard
	test('a permissionLevel in the update body is ignored', async () => {
		const alice = await fresh('e2e-dash-perm')
		const { dashboard } = await createPersonal(alice.api, {
			name: `E2E Level ${RUN}`,
		})
		expect(dashboard.permissionLevel).toBe('full')

		const res = await alice.api.put(`${API}/dashboard/${dashboard.id}`, {
			data: { permissionLevel: 'view_only' },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).dashboard.permissionLevel).toBe('full')

		const stored = await showDashboard(alice.api, dashboard.id)
		expect(stored.dashboard.permissionLevel).toBe('full')
		expect(stored.permissionLevel).toBe('full')
	})

	// @e2e dashboards::batch-update-placement-positions-via-dashboard-update
	test('a placements array in the update moves every placement in one save', async () => {
		const alice = await fresh('e2e-dash-batch')
		const { dashboard } = await createPersonal(alice.api, {
			name: `E2E Batch ${RUN}`,
		})
		await addTextWidget(alice.api, dashboard.id, 'one', { gridX: 0, gridY: 20 })
		await addTextWidget(alice.api, dashboard.id, 'two', { gridX: 4, gridY: 20 })
		const before = await showDashboard(alice.api, dashboard.id)
		expect(before.placements.length).toBeGreaterThanOrEqual(2)

		const moves = before.placements.map((placement, index) => ({
			id: placement.id,
			gridX: (index % 3) * 4,
			gridY: 40 + index * 3,
			gridWidth: 3,
			gridHeight: 2,
		}))
		const res = await alice.api.put(`${API}/dashboard/${dashboard.id}`, {
			data: { placements: moves },
		})
		expect(res.status(), await res.text()).toBe(200)

		const after = await showDashboard(alice.api, dashboard.id)
		const position = (p: Record<string, unknown>) => ({
			id: p.id,
			gridX: p.gridX,
			gridY: p.gridY,
			gridWidth: p.gridWidth,
			gridHeight: p.gridHeight,
		})
		const byPlacement = (a: { id: unknown }, b: { id: unknown }) =>
			Number(a.id) - Number(b.id)
		expect(after.placements.map(position).sort(byPlacement)).toEqual(
			[...moves].sort(byPlacement),
		)
	})
})

test.describe('REQ-DASH-005 delete a dashboard', () => {
	// @e2e dashboards::delete-a-dashboard
	test('the dashboard and every placement on it are gone', async () => {
		const alice = await fresh('e2e-dash-delete')
		const { dashboard } = await createPersonal(alice.api, {
			name: `E2E Delete ${RUN}`,
		})
		await addTextWidget(alice.api, dashboard.id, 'gone', { gridX: 0, gridY: 20 })
		const placements = (await showDashboard(alice.api, dashboard.id)).placements
		expect(placements.length).toBeGreaterThanOrEqual(3)
		// The placement probe answers for a placement that exists.
		const probe = await alice.api.post(`${API}/tile-click/${placements[0].id}`)
		expect(probe.status(), await probe.text()).toBe(204)

		const res = await alice.api.delete(`${API}/dashboard/${dashboard.id}`)
		expect(res.status(), await res.text()).toBe(200)
		expect(await res.json()).toEqual({ status: 'ok' })

		expect(
			(await alice.api.get(`${API}/dashboard/${dashboard.id}`)).status(),
		).toBe(404)
		expect((await listPersonal(alice.api)).map((row) => row.id)).not.toContain(
			dashboard.id,
		)
		for (const placement of placements) {
			const gone = await alice.api.post(`${API}/tile-click/${placement.id}`)
			expect(
				gone.status(),
				`placement ${placement.id} outlived its dashboard`,
			).toBe(404)
		}
	})

	// @e2e dashboards::delete-the-active-dashboard
	test('deleting the active dashboard does not activate another one', async () => {
		const alice = await fresh('e2e-dash-del-active')
		const other = await createPersonal(alice.api, { name: `E2E Other ${RUN}` })
		const active = await createPersonal(alice.api, { name: `E2E Active ${RUN}` })
		expect(active.dashboard.isActive).toBe(1)

		const res = await alice.api.delete(`${API}/dashboard/${active.dashboard.id}`)
		expect(res.status(), await res.text()).toBe(200)

		const rows = await listPersonal(alice.api)
		expect(rows.map((row) => [row.id, row.isActive])).toEqual([
			[other.dashboard.id, 0],
		])
	})

	// @e2e dashboards::delete-the-last-remaining-dashboard
	test('deleting the only dashboard leaves an empty list', async () => {
		const alice = await fresh('e2e-dash-del-last')
		const { dashboard } = await createPersonal(alice.api, {
			name: `E2E Only ${RUN}`,
		})
		expect((await listPersonal(alice.api)).map((row) => row.id)).toEqual([
			dashboard.id,
		])

		const res = await alice.api.delete(`${API}/dashboard/${dashboard.id}`)
		expect(res.status(), await res.text()).toBe(200)
		expect(await listPersonal(alice.api)).toEqual([])
	})

	// @e2e dashboards::delete-does-not-check-permission-level
	test('a view-only copy of a template can still be deleted by its owner', async () => {
		const alice = await fresh('e2e-dash-del-view')
		const group = await freshGroup('e2e-dash-viewonly')
		await addToGroup(admin, alice.username, group)
		const template = await createTemplate(`E2E View-only ${RUN}`, {
			targetGroups: [group],
			permissionLevel: 'view_only',
		})

		const copy = await alice.api.get(`${API}/dashboard`)
		expect(copy.status(), await copy.text()).toBe(200)
		const body = await copy.json()
		expect(body.dashboard).toMatchObject({
			userId: alice.username,
			basedOnTemplate: template.id,
		})
		expect(body.permissionLevel).toBe('view_only')

		const res = await alice.api.delete(`${API}/dashboard/${body.dashboard.id}`)
		expect(res.status(), await res.text()).toBe(200)
		expect((await listPersonal(alice.api)).map((row) => row.id)).not.toContain(
			body.dashboard.id,
		)
	})
})

test.describe('REQ-DASH-006 activate a dashboard', () => {
	// @e2e dashboards::activate-a-dashboard
	test('activating one dashboard deactivates the other', async () => {
		const alice = await fresh('e2e-dash-activate')
		const personal = await createPersonal(alice.api, { name: 'Personal' })
		const work = await createPersonal(alice.api, { name: 'Work' })
		expect(byId(await listPersonal(alice.api), work.dashboard.id).isActive).toBe(
			1,
		)

		const res = await alice.api.post(
			`${API}/dashboard/${personal.dashboard.id}/activate`,
		)
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).dashboard).toMatchObject({
			id: personal.dashboard.id,
			isActive: 1,
		})

		const rows = await listPersonal(alice.api)
		expect(byId(rows, personal.dashboard.id).isActive).toBe(1)
		expect(byId(rows, work.dashboard.id).isActive).toBe(0)
	})

	// @e2e dashboards::activate-an-already-active-dashboard
	test('activating the active dashboard again is a 200 no-op', async () => {
		const alice = await fresh('e2e-dash-reactivate')
		const work = await createPersonal(alice.api, { name: 'Work' })
		const res = await alice.api.post(
			`${API}/dashboard/${work.dashboard.id}/activate`,
		)
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).dashboard).toMatchObject({
			id: work.dashboard.id,
			isActive: 1,
		})
		expect(
			(await listPersonal(alice.api)).map((row) => [row.id, row.isActive]),
		).toEqual([[work.dashboard.id, 1]])
	})

	// @e2e dashboards::only-one-active-dashboard-per-user
	test('of five dashboards exactly the activated one is active', async () => {
		const alice = await fresh('e2e-dash-one-active')
		const ids: number[] = []
		for (let index = 1; index <= 5; index++) {
			ids.push(
				(
					await createPersonal(alice.api, {
						name: `E2E Five ${index} ${RUN}`,
					})
				).dashboard.id,
			)
		}
		const target = ids[2]
		const res = await alice.api.post(`${API}/dashboard/${target}/activate`)
		expect(res.status(), await res.text()).toBe(200)

		const rows = await listPersonal(alice.api)
		expect(rows).toHaveLength(5)
		expect(
			rows.filter((row) => row.isActive === 1).map((row) => row.id),
		).toEqual([target])
		expect(rows.filter((row) => row.isActive === 0)).toHaveLength(4)
	})
})

test.describe('REQ-DASH-007 dashboard names', () => {
})

test.describe('REQ-DASH-008 dashboard types', () => {
	// @e2e dashboards::users-cannot-create-admintemplate-type-dashboards
	test('a type in the create body is ignored: the dashboard is a user dashboard', async () => {
		const alice = await fresh('e2e-dash-faketpl')
		const { dashboard } = await createPersonal(alice.api, {
			name: 'Fake Template',
			type: 'admin_template',
		})
		expect(dashboard.type).toBe('user')
		expect((await showDashboard(alice.api, dashboard.id)).dashboard.type).toBe(
			'user',
		)

		const listed = await admin.get(`${API}/admin/templates`)
		expect(listed.status()).toBe(200)
		const ids = ((await listed.json()) as DashboardRow[]).map(
			(template) => template.id,
		)
		expect(ids).not.toContain(dashboard.id)
	})

	// @e2e dashboards::admin-creates-a-template-dashboard
	test("an admin template is an admin_template and never shows in a user's list", async () => {
		const alice = await fresh('e2e-dash-realtpl')
		const group = await freshGroup('e2e-dash-realtpl')
		await addToGroup(admin, alice.username, group)
		const own = await createPersonal(alice.api, { name: `E2E Own ${RUN}` })

		const template = await createTemplate(`E2E Company ${RUN}`, {
			targetGroups: [group],
		})
		expect(template.type).toBe('admin_template')
		const stored = await admin.get(`${API}/admin/templates/${template.id}`)
		expect(stored.status()).toBe(200)
		expect((await stored.json()).template.type).toBe('admin_template')

		const rows = await listPersonal(alice.api)
		expect(rows.map((row) => row.id)).toEqual([own.dashboard.id])
	})
})

test.describe('REQ-DASH-009 personal resolution chain', () => {
	// @e2e dashboards::active-dashboard-found-immediately
	test('an active dashboard is answered as is, with no template copy made', async () => {
		const alice = await fresh('e2e-dash-immediate')
		const group = await freshGroup('e2e-dash-immediate')
		await addToGroup(admin, alice.username, group)
		const work = await createPersonal(alice.api, { name: 'Work' })
		// A template that WOULD apply to her, had the chain gone past step 1.
		await createTemplate(`E2E Would apply ${RUN}`, { targetGroups: [group] })

		const res = await alice.api.get(`${API}/dashboard`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(body.dashboard).toMatchObject({
			id: work.dashboard.id,
			isActive: 1,
			basedOnTemplate: null,
		})

		const rows = await listPersonal(alice.api)
		expect(rows.map((row) => row.id)).toEqual([work.dashboard.id])
	})
})

test.describe('REQ-DASH-010 serialisation', () => {
	// @e2e dashboards::dashboard-object-includes-all-fields
	test('every endpoint serialises every field', async () => {
		const alice = await fresh('e2e-dash-fields')
		const created = await createPersonal(alice.api, {
			name: `E2E Fields ${RUN}`,
		})
		const shown = await showDashboard(alice.api, created.dashboard.id)
		const active = await alice.api.get(`${API}/dashboard`)
		expect(active.status()).toBe(200)
		const activated = await alice.api.post(
			`${API}/dashboard/${created.dashboard.id}/activate`,
		)
		expect(activated.status()).toBe(200)
		const updated = await alice.api.put(
			`${API}/dashboard/${created.dashboard.id}`,
			{
				data: { description: 'fields' },
			},
		)
		expect(updated.status()).toBe(200)

		const payloads: Array<[string, unknown]> = [
			['POST /api/dashboard', created.dashboard],
			['GET /api/dashboard/{id}', shown.dashboard],
			['GET /api/dashboard', (await active.json()).dashboard],
			[
				'POST /api/dashboard/{id}/activate',
				(await activated.json()).dashboard,
			],
			['PUT /api/dashboard/{id}', (await updated.json()).dashboard],
			[
				'GET /api/dashboards',
				byId(await listPersonal(alice.api), created.dashboard.id),
			],
		]
		for (const [where, payload] of payloads) {
			for (const field of DASHBOARD_FIELDS) {
				expect(payload, `${where} lacks ${field}`).toHaveProperty(field)
			}
		}
	})

	// @e2e dashboards::null-fields-are-included-in-serialization
	test('a null description and basedOnTemplate are present as null', async () => {
		const alice = await fresh('e2e-dash-nulls')
		const { dashboard } = await createPersonal(alice.api, {
			name: `E2E Nulls ${RUN}`,
		})
		for (const payload of [
			dashboard,
			(await showDashboard(alice.api, dashboard.id)).dashboard,
		]) {
			expect(payload).toHaveProperty('description', null)
			expect(payload).toHaveProperty('basedOnTemplate', null)
		}
	})

	// @e2e dashboards::timestamp-format-consistency
	test('createdAt and updatedAt are Y-m-d H:i:s everywhere', async () => {
		const alice = await fresh('e2e-dash-stamps')
		const { dashboard } = await createPersonal(alice.api, {
			name: `E2E Stamps ${RUN}`,
		})
		const updated = await alice.api.put(`${API}/dashboard/${dashboard.id}`, {
			data: { name: `E2E Stamps 2 ${RUN}` },
		})
		expect(updated.status()).toBe(200)
		for (const payload of [
			dashboard,
			(await updated.json()).dashboard,
			(await showDashboard(alice.api, dashboard.id)).dashboard,
			byId(await listPersonal(alice.api), dashboard.id),
		]) {
			expect(payload.createdAt).toMatch(TIMESTAMP)
			expect(payload.updatedAt).toMatch(TIMESTAMP)
		}
	})
})
