/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What the admin settings do to dashboards: the personal-dashboard switch, the
 * multiple-dashboards switch, the default permission level and the default
 * grid columns, and that changing any of them leaves existing dashboards alone
 * (admin-settings REQ-ASET-003, REQ-ASET-004, REQ-ASET-005, REQ-ASET-006,
 * REQ-ASET-009).
 *
 * 🔴 EVERY REFUSAL IS MADE BY AN ORDINARY ACCOUNT AND PAIRED WITH ITS ALLOWED
 * CASE. A 403 from a broken endpoint looks exactly like a 403 from a working
 * guard, so each refused create or fork is followed, in the same test, by the
 * same call succeeding once the setting allows it. "Nothing was written" is
 * read back from the account's own dashboard list, not assumed from the status.
 *
 * Accounts, groups and templates are made per test and removed in `afterAll`;
 * the settings are put back in `afterEach`, because the instance is shared.
 *
 * Endpoint names: the spec writes `POST /api/dashboard/active`; the route is
 * `POST /api/dashboards/active` (appinfo/routes.php), which is what is called.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	listItems,
	readSettings,
	restoreSettings,
	writeSettings,
} from './support/adminSettings.ts'
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

const DISABLED_ENVELOPE = {
	status: 'error',
	error: 'personal_dashboards_disabled',
	message: 'Personal dashboards are not enabled by your administrator',
}

let admin: APIRequestContext
let baseline: Record<string, unknown> | null = null
const principals: Principal[] = []
const groups: string[] = []
const templates: number[] = []
const adminDashboards: number[] = []
const groupDashboards: Array<{ groupId: string; uuid: string }> = []

/**
 * A fresh ordinary account, removed in `afterAll`.
 *
 * @param prefix a readable prefix.
 * @return the principal.
 */
async function freshUser(prefix: string): Promise<Principal> {
	const user = await createPrincipal(admin, prefix)
	principals.push(user)
	await assertActingAs(user.api, user.username)
	return user
}

/**
 * A fresh group, removed in `afterAll`.
 *
 * @param prefix a readable prefix.
 * @return the group id.
 */
async function freshGroup(prefix: string): Promise<string> {
	const id = await createGroup(admin, `${prefix}-${Date.now()}`)
	groups.push(id)
	return id
}

/**
 * Create a personal dashboard and require 201.
 *
 * @param api the owner's context.
 * @param name the dashboard name.
 * @param extra more body fields.
 * @return the created dashboard as the server serialised it.
 */
async function createDashboard(
	api: APIRequestContext,
	name: string,
	extra: Record<string, unknown> = {},
): Promise<Record<string, unknown>> {
	const res = await api.post(`${API}/dashboard`, { data: { name, ...extra } })
	expect(res.status(), await res.text()).toBe(201)
	return (await res.json()).dashboard as Record<string, unknown>
}

/**
 * The ids of a user's own dashboards.
 *
 * @param api the user's context.
 * @return the dashboard ids.
 */
async function ownDashboardIds(api: APIRequestContext): Promise<number[]> {
	const res = await api.get(`${API}/dashboards`)
	expect(res.status(), await res.text()).toBe(200)
	return listItems(await res.json()).map((d) => Number(d.id))
}

/**
 * One dashboard as its owner reads it.
 *
 * @param api the owner's context.
 * @param id the dashboard id.
 * @return the `show` answer.
 */
async function showDashboard(
	api: APIRequestContext,
	id: number,
): Promise<{
	dashboard: Record<string, unknown>
	placements: unknown[]
	permissionLevel: string
}> {
	const res = await api.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	return await res.json()
}

/**
 * An admin template targeting groups, removed in `afterAll`.
 *
 * @param body the create body.
 * @return the template as the server serialised it.
 */
async function createTemplate(
	body: Record<string, unknown>,
): Promise<Record<string, unknown>> {
	const res = await admin.post(`${API}/admin/templates`, { data: body })
	expect(res.status(), await res.text()).toBe(201)
	const template = (await res.json()) as Record<string, unknown>
	templates.push(Number(template.id))
	return template
}

/**
 * Have a user who owns nothing open LaunchPad's API once, which is where a
 * targeted template is copied for them (admin-templates REQ-TMPL-019).
 *
 * @param user the member.
 * @return the dashboard GET /api/dashboard answers with.
 */
async function firstVisit(
	user: Principal,
): Promise<{ dashboard: Record<string, unknown>; permissionLevel: string }> {
	const res = await user.api.get(`${API}/dashboard`)
	expect(res.status(), await res.text()).toBe(200)
	return await res.json()
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	baseline = await readSettings(admin)
	// A quota left behind by another spec would turn a create into a 409 and
	// read as a failure of the setting under test.
	await writeSettings(admin, { maxDashboardsPerUser: 0 })
})

test.afterEach(async () => {
	await restoreSettings(admin, baseline)
	await writeSettings(admin, { maxDashboardsPerUser: 0 })
})

test.afterAll(async () => {
	await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	for (const id of templates) {
		await admin.delete(`${API}/admin/templates/${id}`).catch(() => undefined)
	}
	for (const { groupId, uuid } of groupDashboards) {
		await admin
			.delete(`${API}/dashboards/group/${encodeURIComponent(groupId)}/${uuid}`)
			.catch(() => undefined)
	}
	for (const id of adminDashboards) {
		await admin.delete(`${API}/dashboard/${id}`).catch(() => undefined)
	}
	for (const user of principals) {
		await removePrincipal(admin, user)
	}
	for (const id of groups) {
		await removeGroup(admin, id)
	}
	await restoreSettings(admin, baseline)
	await admin.dispose()
})

test.describe('REQ-ASET-003 the personal-dashboard switch', () => {
	// @e2e admin-settings::flag-off-blocks-personal-dashboard-creation
	test('with the switch off a user is refused POST /api/dashboard with the stable envelope', async () => {
		const alice = await freshUser('e2e-aset-alice')
		await writeSettings(admin, { allowUserDash: false, allowMultiDash: true })

		const refused = await alice.api.post(`${API}/dashboard`, {
			data: { name: 'My Test' },
		})
		expect(refused.status()).toBe(403)
		expect(await refused.json()).toEqual(DISABLED_ENVELOPE)
		expect(
			await ownDashboardIds(alice.api),
			'a refused create inserted a dashboard',
		).toEqual([])

		await writeSettings(admin, { allowUserDash: true })
		await createDashboard(alice.api, 'My Test')
		expect(await ownDashboardIds(alice.api)).toHaveLength(1)
	})

	// @e2e admin-settings::flag-off-blocks-fork
	test('with the switch off a user is refused a fork of a group dashboard they can read', async () => {
		const alice = await freshUser('e2e-aset-forker')
		const group = await freshGroup('e2e-aset-fork')
		await addToGroup(admin, alice.username, group)

		const made = await admin.post(
			`${API}/dashboards/group/${encodeURIComponent(group)}`,
			{
				data: { name: `E2E shared ${Date.now()}` },
			},
		)
		expect(made.status(), await made.text()).toBe(201)
		const shared = (await made.json()).dashboard as { uuid: string }
		groupDashboards.push({ groupId: group, uuid: shared.uuid })
		const published = await admin.post(
			`${API}/dashboards/${shared.uuid}/publish`,
		)
		expect(published.status(), await published.text()).toBe(200)

		const visible = await alice.api.get(`${API}/dashboards/visible`)
		expect(visible.status()).toBe(200)
		expect(
			listItems(await visible.json()).map((d) => d.uuid),
			'precondition: the member cannot read the group dashboard',
		).toContain(shared.uuid)

		await writeSettings(admin, { allowUserDash: false, allowMultiDash: true })
		const refused = await alice.api.post(
			`${API}/dashboards/${shared.uuid}/fork`,
			{ data: {} },
		)
		expect(refused.status()).toBe(403)
		expect(await refused.json()).toEqual(DISABLED_ENVELOPE)
		expect(
			await ownDashboardIds(alice.api),
			'a refused fork created a dashboard',
		).toEqual([])

		await writeSettings(admin, { allowUserDash: true })
		const forked = await alice.api.post(
			`${API}/dashboards/${shared.uuid}/fork`,
			{ data: {} },
		)
		expect(forked.status(), await forked.text()).toBe(201)
		expect(await ownDashboardIds(alice.api)).toHaveLength(1)
	})

	// @e2e admin-settings::flag-off-does-not-break-existing-personal-dashboards
	test('with the switch off existing personal dashboards stay readable, editable, activatable and deletable', async () => {
		const alice = await freshUser('e2e-aset-keeper')
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		const p1 = await createDashboard(alice.api, `E2E P1 ${Date.now()}`)
		const p2 = await createDashboard(alice.api, `E2E P2 ${Date.now()}`)

		await writeSettings(admin, { allowUserDash: false })

		const visible = await alice.api.get(`${API}/dashboards/visible`)
		expect(visible.status()).toBe(200)
		const uuids = listItems(await visible.json()).map((d) => d.uuid)
		expect(uuids).toContain(p1.uuid)
		expect(uuids).toContain(p2.uuid)

		const renamed = await alice.api.put(`${API}/dashboard/${p1.id}`, {
			data: { name: 'Renamed P1' },
		})
		expect(renamed.status(), await renamed.text()).toBe(200)
		expect((await showDashboard(alice.api, Number(p1.id))).dashboard.name).toBe(
			'Renamed P1',
		)

		const active = await alice.api.post(`${API}/dashboards/active`, {
			data: { uuid: p2.uuid },
		})
		expect(active.status(), await active.text()).toBe(200)
		const activated = await alice.api.post(`${API}/dashboard/${p1.id}/activate`)
		expect(activated.status(), await activated.text()).toBe(200)

		const created = await alice.api.post(`${API}/dashboard`, {
			data: { name: 'Third' },
		})
		expect(created.status()).toBe(403)
		expect((await created.json()).error).toBe('personal_dashboards_disabled')
		const forked = await alice.api.post(`${API}/dashboards/${p1.uuid}/fork`, {
			data: {},
		})
		expect(forked.status()).toBe(403)
		expect((await forked.json()).error).toBe('personal_dashboards_disabled')

		const deleted = await alice.api.delete(`${API}/dashboard/${p2.id}`)
		expect(deleted.status(), await deleted.text()).toBe(200)
		expect(await ownDashboardIds(alice.api)).toEqual([Number(p1.id)])
	})

	// @e2e admin-settings::toggling-does-not-destructively-mutate-data
	test('switching off and on again leaves a dashboard and its widgets exactly as they were', async () => {
		const alice = await freshUser('e2e-aset-toggle')
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		const p1 = await createDashboard(alice.api, `E2E Toggle ${Date.now()}`)
		const widget = await alice.api.post(`${API}/dashboard/${p1.id}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 0,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 2,
				content: { text: 'kept', contentMode: 'markdown' },
			},
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)
		expect(
			(await alice.api.post(`${API}/dashboard/${p1.id}/activate`)).status(),
		).toBe(200)

		const before = await showDashboard(alice.api, Number(p1.id))
		expect(before.dashboard.isActive).toBe(1)

		await writeSettings(admin, { allowUserDash: false })
		await writeSettings(admin, { allowUserDash: true })

		const after = await showDashboard(alice.api, Number(p1.id))
		expect(
			after.dashboard,
			'toggling the switch changed the dashboard row',
		).toEqual(before.dashboard)
		expect(
			after.placements,
			'toggling the switch changed the widget placements',
		).toEqual(before.placements)
		expect(after.dashboard.isActive).toBe(1)
	})

	// @e2e admin-settings::admins-can-always-create-dashboards
	test('with the switch off an administrator can still create a dashboard', async () => {
		const alice = await freshUser('e2e-aset-control')
		await writeSettings(admin, { allowUserDash: false, allowMultiDash: true })

		// Control: the switch really is off for an ordinary account.
		const refused = await alice.api.post(`${API}/dashboard`, {
			data: { name: 'Not me' },
		})
		expect(refused.status()).toBe(403)

		// SUSPECTED BUG: DashboardApiController::denyCreate()
		// (lib/Controller/DashboardApiController.php:512-527) calls
		// DashboardService::assertPersonalDashboardsAllowed()
		// (lib/Service/DashboardService.php:3176), which has no administrator
		// bypass, before the role check that would let an admin through. An
		// admin is answered 403 personal_dashboards_disabled like anyone else.
		const res = await admin.post(`${API}/dashboard`, {
			data: { name: 'Admin Dashboard' },
		})
		const body = await res.json()
		if (body?.dashboard?.id !== undefined) {
			adminDashboards.push(Number(body.dashboard.id))
		}
		expect(res.status(), JSON.stringify(body)).toBe(201)
		expect(body.dashboard.name).toBe('Admin Dashboard')
	})
})

test.describe('REQ-ASET-004 the multiple-dashboards switch', () => {
	// @e2e admin-settings::second-dashboard-creation-blocked
	test('with multiple dashboards off a user who has one is refused a second', async () => {
		const alice = await freshUser('e2e-aset-single')
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		await createDashboard(alice.api, `E2E Only ${Date.now()}`)

		await writeSettings(admin, { allowMultiDash: false })
		const refused = await alice.api.post(`${API}/dashboard`, {
			data: { name: 'Second Dashboard' },
		})
		expect(refused.status()).toBe(403)
		expect(await refused.json()).toEqual({
			error: 'Multiple dashboards not allowed',
		})
		expect(await ownDashboardIds(alice.api)).toHaveLength(1)

		await writeSettings(admin, { allowMultiDash: true })
		await createDashboard(alice.api, 'Second Dashboard')
		expect(await ownDashboardIds(alice.api)).toHaveLength(2)
	})

	// @e2e admin-settings::first-dashboard-creation-allowed
	test('with multiple dashboards off a user with none may create their first', async () => {
		const bob = await freshUser('e2e-aset-bob')
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: false })
		expect(await ownDashboardIds(bob.api)).toEqual([])

		const res = await bob.api.post(`${API}/dashboard`, {
			data: { name: 'My Dashboard' },
		})
		expect(res.status(), await res.text()).toBe(201)
		expect(await ownDashboardIds(bob.api)).toHaveLength(1)
	})

	// @e2e admin-settings::multiple-dashboards-allowed-default
	test('with multiple dashboards on a user with three may create a fourth', async () => {
		const alice = await freshUser('e2e-aset-many')
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		for (const n of [1, 2, 3]) {
			await createDashboard(alice.api, `E2E Many ${n}`)
		}

		const res = await alice.api.post(`${API}/dashboard`, {
			data: { name: 'Fourth Dashboard' },
		})
		expect(res.status(), await res.text()).toBe(201)
		expect(await ownDashboardIds(alice.api)).toHaveLength(4)
	})

	// @e2e admin-settings::existing-dashboards-preserved-when-setting-is-disabled
	test('turning multiple dashboards off keeps all three, blocks a fourth and allows deleting down to one', async () => {
		const alice = await freshUser('e2e-aset-three')
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		const made: number[] = []
		for (const n of [1, 2, 3]) {
			made.push(
				Number((await createDashboard(alice.api, `E2E Three ${n}`)).id),
			)
		}

		await writeSettings(admin, { allowMultiDash: false })

		expect((await ownDashboardIds(alice.api)).sort()).toEqual([...made].sort())
		const refused = await alice.api.post(`${API}/dashboard`, {
			data: { name: 'Fourth' },
		})
		expect(refused.status()).toBe(403)
		expect((await ownDashboardIds(alice.api)).sort()).toEqual([...made].sort())

		for (const id of made.slice(1)) {
			const res = await alice.api.delete(`${API}/dashboard/${id}`)
			expect(res.status(), await res.text()).toBe(200)
		}
		expect(await ownDashboardIds(alice.api)).toEqual([made[0]])
	})

	// @e2e admin-settings::template-distribution-overrides-multiple-dashboard-restriction
	test('with multiple dashboards off a template for the group still reaches a user who has one', async () => {
		const alice = await freshUser('e2e-aset-tmpl-multi')
		const group = await freshGroup('e2e-aset-tmpl-multi')
		await addToGroup(admin, alice.username, group)
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		await createDashboard(alice.api, `E2E Own ${Date.now()}`)

		await writeSettings(admin, { allowMultiDash: false })
		const template = await createTemplate({
			name: `E2E Distributed ${Date.now()}`,
			targetGroups: [group],
			permissionLevel: 'add_only',
		})

		// The distribution runs when the member opens LaunchPad.
		const visit = await alice.api.get(`${API}/dashboard`)
		expect(visit.status(), await visit.text()).toBe(200)

		// SUSPECTED BUG: DashboardService::templateStepsAside()
		// (lib/Service/DashboardService.php:2186-2190) skips the template for
		// anyone who already owns a dashboard, so the copy is never made and
		// alice keeps one dashboard. The scenario says the restriction applies
		// only to user-initiated creation and the copy MUST still arrive.
		const res = await alice.api.get(`${API}/dashboards`)
		const items = listItems(await res.json())
		expect(items.map((d) => d.basedOnTemplate)).toContain(Number(template.id))
		expect(items).toHaveLength(2)
	})
})

test.describe('REQ-ASET-005 the default permission level', () => {
	// @e2e admin-settings::default-permission-level-applied-to-new-dashboard
	test('a user-created dashboard is full although the default is add_only', async () => {
		const alice = await freshUser('e2e-aset-perm')
		await writeSettings(admin, {
			allowUserDash: true,
			allowMultiDash: true,
			defaultPermLevel: 'add_only',
		})
		expect((await readSettings(admin)).defaultPermissionLevel).toBe('add_only')

		const created = await createDashboard(alice.api, 'My Dashboard')
		expect(created.permissionLevel).toBe('full')
		// The dashboard has a level of its own, so the admin default is not the
		// one that applies to it.
		expect(
			(await showDashboard(alice.api, Number(created.id))).permissionLevel,
		).toBe('full')
	})

	// @e2e admin-settings::default-permission-does-not-affect-template-copies
	test('a template copy keeps the template level, not the default', async () => {
		const member = await freshUser('e2e-aset-tmpl-perm')
		const group = await freshGroup('e2e-aset-tmpl-perm')
		await addToGroup(admin, member.username, group)
		await writeSettings(admin, {
			allowUserDash: true,
			allowMultiDash: true,
			defaultPermLevel: 'view_only',
		})
		const template = await createTemplate({
			name: `E2E Full template ${Date.now()}`,
			targetGroups: [group],
			permissionLevel: 'full',
		})

		const visit = await firstVisit(member)
		expect(
			visit.dashboard.basedOnTemplate,
			'precondition: no copy of the template was made',
		).toBe(Number(template.id))
		expect(visit.dashboard.permissionLevel).toBe('full')
		expect(visit.permissionLevel).toBe('full')
	})

	// @e2e admin-settings::admin-can-override-default-for-individual-templates
	test('a template may be created full while the default is add_only', async () => {
		await writeSettings(admin, { defaultPermLevel: 'add_only' })
		const template = await createTemplate({
			name: `E2E Override ${Date.now()}`,
			permissionLevel: 'full',
		})
		expect(template.permissionLevel).toBe('full')

		const read = await admin.get(`${API}/admin/templates/${template.id}`)
		expect(read.status(), await read.text()).toBe(200)
		expect((await read.json()).template.permissionLevel).toBe('full')
	})

	// @e2e admin-settings::permission-resolution-chain
	test('the template level wins over the dashboard level, which wins over the default', async () => {
		const member = await freshUser('e2e-aset-chain')
		const group = await freshGroup('e2e-aset-chain')
		await addToGroup(admin, member.username, group)
		await writeSettings(admin, {
			allowUserDash: true,
			allowMultiDash: true,
			defaultPermLevel: 'view_only',
		})

		// The copy takes the template level as its own: full.
		const template = await createTemplate({
			name: `E2E Chain ${Date.now()}`,
			targetGroups: [group],
			permissionLevel: 'full',
		})
		const copy = (await firstVisit(member)).dashboard
		expect(
			copy.basedOnTemplate,
			'precondition: no copy of the template was made',
		).toBe(Number(template.id))
		expect(copy.permissionLevel).toBe('full')

		// Now the template says add_only, the copy still says full and the
		// default says view_only: (1) the template is checked first.
		const updated = await admin.put(`${API}/admin/templates/${template.id}`, {
			data: { permissionLevel: 'add_only' },
		})
		expect(updated.status(), await updated.text()).toBe(200)
		const viaTemplate = await showDashboard(member.api, Number(copy.id))
		expect(viaTemplate.dashboard.permissionLevel).toBe('full')
		expect(viaTemplate.permissionLevel).toBe('add_only')

		// (2) before (3): a dashboard with no template and a level of its own
		// resolves to that level, not to the default.
		const own = await createDashboard(member.api, `E2E Own level ${Date.now()}`)
		expect(
			(await showDashboard(member.api, Number(own.id))).permissionLevel,
		).toBe('full')
	})
})

test.describe('REQ-ASET-006 the default grid columns', () => {
	// @e2e admin-settings::default-grid-columns-applied
	test('a new dashboard takes the default grid columns', async () => {
		const alice = await freshUser('e2e-aset-grid')
		await writeSettings(admin, {
			allowUserDash: true,
			allowMultiDash: true,
			defaultGridCols: 8,
		})
		expect((await readSettings(admin)).defaultGridColumns).toBe(8)

		const created = await createDashboard(alice.api, 'My Dashboard')
		// SUSPECTED BUG: DashboardFactory::DEFAULT_OPTIONS hardcodes
		// gridColumns 12 (lib/Service/DashboardFactory.php:37) and
		// DashboardService::factoryOptions() (lib/Service/DashboardService.php:809)
		// never passes the admin default, so every new dashboard gets 12.
		expect(created.gridColumns).toBe(8)
	})

	// @e2e admin-settings::explicit-grid-columns-overrides-default
	test('an explicit gridColumns in the create body wins over the default', async () => {
		const alice = await freshUser('e2e-aset-grid-explicit')
		await writeSettings(admin, {
			allowUserDash: true,
			allowMultiDash: true,
			defaultGridCols: 8,
		})

		const created = await createDashboard(alice.api, 'My Dashboard', {
			gridColumns: 12,
		})
		expect(created.gridColumns).toBe(12)
		expect(
			(await showDashboard(alice.api, Number(created.id))).dashboard
				.gridColumns,
		).toBe(12)
	})

	// @e2e admin-settings::default-grid-columns-for-template-copies
	test('a template copy keeps the template grid columns, not the default', async () => {
		const member = await freshUser('e2e-aset-tmpl-grid')
		const group = await freshGroup('e2e-aset-tmpl-grid')
		await addToGroup(admin, member.username, group)
		await writeSettings(admin, {
			allowUserDash: true,
			allowMultiDash: true,
			defaultGridCols: 8,
		})
		const template = await createTemplate({
			name: `E2E Grid template ${Date.now()}`,
			targetGroups: [group],
			permissionLevel: 'add_only',
		})
		const set = await admin.put(`${API}/admin/templates/${template.id}`, {
			data: { gridColumns: 12 },
		})
		expect(set.status(), await set.text()).toBe(200)

		const copy = (await firstVisit(member)).dashboard
		expect(
			copy.basedOnTemplate,
			'precondition: no copy of the template was made',
		).toBe(Number(template.id))
		expect(copy.gridColumns).toBe(12)
	})
})

test.describe('REQ-ASET-009 changing a setting leaves existing dashboards alone', () => {
	// @e2e admin-settings::changing-default-permission-level-does-not-modify-existing-dashboards
	test('changing the default permission level to view_only leaves a full dashboard full', async () => {
		const alice = await freshUser('e2e-aset-keep-perm')
		await writeSettings(admin, {
			allowUserDash: true,
			allowMultiDash: true,
			defaultPermLevel: 'add_only',
		})
		const created = await createDashboard(
			alice.api,
			`E2E Keep level ${Date.now()}`,
		)
		expect(created.permissionLevel).toBe('full')

		await writeSettings(admin, { defaultPermLevel: 'view_only' })

		const after = await showDashboard(alice.api, Number(created.id))
		expect(after.dashboard.permissionLevel).toBe('full')
		expect(after.permissionLevel).toBe('full')
	})

	// @e2e admin-settings::changing-grid-columns-default-does-not-modify-existing-dashboards
	test('changing the default grid columns to 8 leaves a 12-column dashboard at 12 and applies to new ones', async () => {
		const alice = await freshUser('e2e-aset-keep-grid')
		await writeSettings(admin, {
			allowUserDash: true,
			allowMultiDash: true,
			defaultGridCols: 12,
		})
		const existing = await createDashboard(
			alice.api,
			`E2E Keep grid ${Date.now()}`,
		)
		expect(existing.gridColumns).toBe(12)

		await writeSettings(admin, { defaultGridCols: 8 })

		expect(
			(await showDashboard(alice.api, Number(existing.id))).dashboard
				.gridColumns,
		).toBe(12)

		// SUSPECTED BUG: the same one as "Default grid columns applied" above;
		// a new dashboard is created with a hardcoded 12.
		const fresh = await createDashboard(alice.api, `E2E New grid ${Date.now()}`)
		expect(fresh.gridColumns).toBe(8)
	})

	// @e2e admin-settings::disabling-user-dashboards-does-not-delete-existing-dashboards
	test("turning personal dashboards off keeps every user's dashboards and only blocks new ones", async () => {
		// Three accounts stand in for the scenario's fifty: each one is checked
		// the same way, and fifty accounts would take minutes to provision.
		await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
		const owners: Array<{ user: Principal; id: number }> = []
		for (const n of [1, 2, 3]) {
			const user = await freshUser(`e2e-aset-owner${n}`)
			owners.push({
				user,
				id: Number((await createDashboard(user.api, `E2E Owned ${n}`)).id),
			})
		}

		await writeSettings(admin, { allowUserDash: false })

		for (const { user, id } of owners) {
			expect(
				await ownDashboardIds(user.api),
				`${user.username} lost a dashboard`,
			).toEqual([id])
			expect((await showDashboard(user.api, id)).dashboard.id).toBe(id)
			const refused = await user.api.post(`${API}/dashboard`, {
				data: { name: 'New one' },
			})
			expect(refused.status()).toBe(403)
			expect((await refused.json()).error).toBe('personal_dashboards_disabled')
		}
	})
})
