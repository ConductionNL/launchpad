/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for group-shared dashboards: the type, the
 * `default` sentinel, the visible-to-user union, the group-scoped CRUD
 * endpoints and the per-group default flag (openspec/specs/dashboards,
 * REQ-DASH-011..017), written for the e2e-coverage-programme.
 *
 * These scenarios are the HTTP contract of `/api/dashboards/group/...` and
 * `/api/dashboards/visible`, so they are driven through the API. Members are
 * throwaway, non-admin accounts in throwaway Nextcloud groups; every refusal is
 * probed by such a member, re-read by the admin, and paired with the admin
 * succeeding. A new group-shared dashboard starts as a draft (REQ-DASH-031),
 * which members who are not administrators do not see (REQ-DASH-036), so the
 * dashboards a member must see are published while seeding.
 *
 * WHAT STAYS BEHIND. The last group-shared dashboard of a group other than
 * `default` cannot be deleted (REQ-DASH-014, the guard under test) and nothing
 * else removes it, so `afterAll` leaves one row per group used here, each in a
 * group that no longer has members. Dashboards in `default` are all removed.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::create-a-group-shared-dashboard
 *   @e2e dashboards::non-admin-cannot-create-a-group-shared-dashboard
 *   @e2e dashboards::group-shared-dashboard-appears-for-every-group-member
 *   @e2e dashboards::group-shared-dashboards-are-read-only-for-non-admins
 *   @e2e dashboards::direct-mutation-via-personal-endpoint-is-rejected
 *   @e2e dashboards::default-group-dashboard-visible-to-user-with-no-matching-groups
 *   @e2e dashboards::default-is-not-a-real-nextcloud-group
 *   @e2e dashboards::default-group-dashboard-carries-source-default-not-source-group
 *   @e2e dashboards::source-field-discriminates-origin
 *   @e2e dashboards::user-with-no-personal-dashboards-still-gets-visible-result
 *   @e2e dashboards::admin-gets-group-shared-dashboards-as-sourcegroup-even-though-they-own-them
 *   @e2e dashboards::update-propagates-immediately
 *   @e2e dashboards::update-on-a-group-shared-dashboard-rejects-userid-field-changes
 *   @e2e dashboards::groupid-mismatch-between-path-and-record-returns-404
 *   @e2e dashboards::get-apidashboards-remains-backward-compatible
 *   @e2e dashboards::group-shared-dashboard-serialisation-includes-groupid
 *   @e2e dashboards::setting-default-flips-others-off
 *   @e2e dashboards::default-cannot-be-set-across-groups
 *   @e2e dashboards::setting-non-existent-dashboard-as-default
 *   @e2e dashboards::non-admin-cannot-set-default
 *   @e2e dashboards::transaction-safety-under-concurrent-calls
 *   @e2e dashboards::create-then-no-default
 *   @e2e dashboards::create-payload-cannot-smuggle-isdefault
 *   @e2e dashboards::put-cannot-flip-the-default-off
 *   @e2e dashboards::put-cannot-flip-the-default-on
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type {
	CreatedGroupDashboard,
	DashboardRow,
	DashboardSettings,
} from './support/dashboardsSpec.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	addTextWidget,
	allowPersonalDashboardsFreely,
	createGroupDashboard,
	createPersonal,
	listGroup,
	listPersonal,
	listVisible,
	openWorkspace,
	removeGroupDashboards,
	setStartDashboard,
	showDashboard,
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
const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'

let admin: APIRequestContext
let settingsBefore: DashboardSettings | null = null
const principals: Principal[] = []
const ncGroups: string[] = []
/** Every group-shared dashboard created here, for cleanup. */
const created: CreatedGroupDashboard[] = []

/** The shared fixture: group G with member bob and published dashboard D1. */
let groupG = ''
let bob: Principal
let d1: DashboardRow

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

/**
 * A throwaway, non-admin account.
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
 * A throwaway Nextcloud group, optionally with the admin in it.
 *
 * @param prefix a readable prefix.
 * @param withAdmin whether the admin joins (so the admin's visible set holds its rows).
 * @return the group id.
 */
async function freshGroup(prefix: string, withAdmin = false): Promise<string> {
	const groupId = await createGroup(
		admin,
		`${prefix}-${RUN}-${Math.floor(Math.random() * 10_000)}`,
	)
	ncGroups.push(groupId)
	if (withAdmin) {
		await addToGroup(admin, ADMIN_USER, groupId)
	}
	return groupId
}

/**
 * A group id with no Nextcloud group behind it: group-shared dashboards accept
 * any id, and nobody can be a member.
 *
 * @param prefix a readable prefix.
 * @return the id.
 */
function bareGroupId(prefix: string): string {
	return `${prefix}-${RUN}-${Math.floor(Math.random() * 10_000)}`
}

/**
 * Create a group-shared dashboard and remember its group for cleanup.
 *
 * @param groupId the group.
 * @param name the name.
 * @param publish whether to publish it.
 * @return the dashboard.
 */
async function groupDashboard(
	groupId: string,
	name: string,
	publish = true,
): Promise<DashboardRow> {
	const dashboard = await createGroupDashboard(admin, groupId, name, publish)
	created.push({ groupId, uuid: dashboard.uuid })
	return dashboard
}

/**
 * Remember a group-shared dashboard created by a raw request, for cleanup.
 *
 * @param groupId its group.
 * @param dashboard the dashboard.
 * @return the dashboard.
 */
function track(groupId: string, dashboard: DashboardRow): DashboardRow {
	created.push({ groupId, uuid: dashboard.uuid })
	return dashboard
}

/**
 * The `isDefault` flag of each dashboard of a group, by uuid.
 *
 * @param groupId the group.
 * @return uuid → isDefault.
 */
async function defaultFlags(groupId: string): Promise<Record<string, unknown>> {
	const flags: Record<string, unknown> = {}
	for (const row of await listGroup(admin, groupId)) {
		flags[row.uuid] = row.isDefault
	}
	return flags
}

/**
 * Promote a dashboard to its group's default, as the admin.
 *
 * @param groupId the group.
 * @param uuid the dashboard.
 * @return nothing.
 */
async function promote(groupId: string, uuid: string): Promise<void> {
	const res = await admin.post(
		`${API}/dashboards/group/${encodeURIComponent(groupId)}/default`,
		{
			data: { uuid },
		},
	)
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * The visible row with this uuid.
 *
 * @param rows the visible set.
 * @param uuid the dashboard.
 * @return the rows with that uuid (normally exactly one).
 */
function withUuid(rows: DashboardRow[], uuid: string): DashboardRow[] {
	return rows.filter((row) => row.uuid === uuid)
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	settingsBefore = await allowPersonalDashboardsFreely(admin)

	groupG = await freshGroup('e2e-dash-g', true)
	bob = await fresh('e2e-dash-member')
	await addToGroup(admin, bob.username, groupG)
	d1 = await groupDashboard(groupG, `E2E D1 ${RUN}`)
})

test.afterAll(async () => {
	await removeGroupDashboards(admin, created)
	for (const principal of principals) {
		await removePrincipal(admin, principal)
	}
	for (const groupId of ncGroups) {
		await removeGroup(admin, groupId)
	}
	if (settingsBefore !== null) {
		await writeSettings(admin, settingsBefore)
	}
	await admin.dispose()
})

test.describe('REQ-DASH-011 the group-shared type', () => {
	// @e2e dashboards::create-a-group-shared-dashboard
	test('an admin creates a group_shared dashboard with no owner', async () => {
		const res = await admin.post(`${API}/dashboards/group/${groupG}`, {
			data: { name: 'Marketing Overview' },
		})
		expect(res.status(), await res.text()).toBe(201)
		const made = track(groupG, (await res.json()).dashboard as DashboardRow)
		expect(made).toMatchObject({
			name: 'Marketing Overview',
			type: 'group_shared',
			groupId: groupG,
			userId: null,
		})
		expect(made.id).toEqual(expect.any(Number))

		// Re-read, as the admin who belongs to the group.
		const stored = withUuid(await listVisible(admin), made.uuid)
		expect(stored).toHaveLength(1)
		expect(stored[0]).toMatchObject({
			type: 'group_shared',
			groupId: groupG,
			userId: null,
		})
	})

	// @e2e dashboards::non-admin-cannot-create-a-group-shared-dashboard
	test('a member who is no admin is refused with 403 and nothing is created', async () => {
		const name = `E2E Refused ${RUN}`
		const before = (await listGroup(admin, groupG)).map((row) => row.uuid)

		const refused = await bob.api.post(`${API}/dashboards/group/${groupG}`, {
			data: { name },
		})
		expect(refused.status(), await refused.text()).toBe(403)
		const after = await listGroup(admin, groupG)
		expect(after.map((row) => row.uuid)).toEqual(before)
		expect(after.map((row) => row.name)).not.toContain(name)

		const allowed = await admin.post(`${API}/dashboards/group/${groupG}`, {
			data: { name },
		})
		expect(allowed.status(), await allowed.text()).toBe(201)
		track(groupG, (await allowed.json()).dashboard as DashboardRow)
		expect((await listGroup(admin, groupG)).map((row) => row.name)).toContain(
			name,
		)
	})

	// @e2e dashboards::group-shared-dashboard-appears-for-every-group-member
	test('every member of the group sees it in /visible', async () => {
		const second = await fresh('e2e-dash-member2')
		await addToGroup(admin, second.username, groupG)
		for (const member of [bob, second]) {
			const rows = withUuid(await listVisible(member.api), d1.uuid)
			expect(rows, `${member.username} does not see D1`).toHaveLength(1)
			expect(rows[0].source).toBe('group')
		}
	})

	// @e2e dashboards::group-shared-dashboards-are-read-only-for-non-admins
	test("a member's PUT on the group endpoint is refused with 403", async () => {
		const refused = await bob.api.put(
			`${API}/dashboards/group/${groupG}/${d1.uuid}`,
			{
				data: { name: 'Hacked' },
			},
		)
		expect(refused.status(), await refused.text()).toBe(403)
		expect(withUuid(await listGroup(admin, groupG), d1.uuid)[0].name).toBe(
			d1.name,
		)

		const renamed = `E2E D1 renamed ${RUN}`
		const allowed = await admin.put(
			`${API}/dashboards/group/${groupG}/${d1.uuid}`,
			{
				data: { name: renamed },
			},
		)
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect(withUuid(await listGroup(admin, groupG), d1.uuid)[0].name).toBe(
			renamed,
		)
		d1 = { ...d1, name: renamed }
	})

	// @e2e dashboards::direct-mutation-via-personal-endpoint-is-rejected
	test("a member's PUT on the personal endpoint is refused with 403", async () => {
		const refused = await bob.api.put(`${API}/dashboard/${d1.id}`, {
			data: { name: 'Hacked' },
		})
		expect(refused.status(), await refused.text()).toBe(403)
		expect(withUuid(await listGroup(admin, groupG), d1.uuid)[0].name).toBe(
			d1.name,
		)

		// The same endpoint works for him on a dashboard he owns.
		const own = await createPersonal(bob.api, { name: `E2E Bob own ${RUN}` })
		const allowed = await bob.api.put(`${API}/dashboard/${own.dashboard.id}`, {
			data: { name: `E2E Bob renamed ${RUN}` },
		})
		expect(allowed.status(), await allowed.text()).toBe(200)
	})
})

test.describe('REQ-DASH-012/013 the default sentinel and the visible union', () => {
	let dDefault: DashboardRow

	// @e2e dashboards::default-is-not-a-real-nextcloud-group
	test('"default" is accepted although no Nextcloud group of that id exists', async () => {
		const groups = await admin.get(
			'/ocs/v1.php/cloud/groups?search=default&format=json',
		)
		expect(groups.status()).toBe(200)
		expect((await groups.json()).ocs.data.groups).not.toContain('default')

		const res = await admin.post(`${API}/dashboards/group/default`, {
			data: { name: `E2E Welcome ${RUN}` },
		})
		expect(res.status(), await res.text()).toBe(201)
		dDefault = track('default', (await res.json()).dashboard as DashboardRow)
		expect(dDefault).toMatchObject({
			type: 'group_shared',
			groupId: 'default',
			userId: null,
		})

		const published = await admin.post(
			`${API}/dashboards/${dDefault.uuid}/publish`,
		)
		expect(published.status(), await published.text()).toBe(200)
		expect(
			withUuid(await listGroup(admin, 'default'), dDefault.uuid),
		).toHaveLength(1)
	})

	// @e2e dashboards::default-group-dashboard-visible-to-user-with-no-matching-groups
	test('a user in another group sees the default dashboard but not the group one', async () => {
		const engineering = await freshGroup('e2e-dash-eng')
		const support = await freshGroup('e2e-dash-support')
		const carol = await fresh('e2e-dash-carol')
		const engineer = await fresh('e2e-dash-engineer')
		await addToGroup(admin, carol.username, support)
		await addToGroup(admin, engineer.username, engineering)
		const dEng = await groupDashboard(engineering, `E2E Eng ${RUN}`)

		const carols = await listVisible(carol.api)
		expect(withUuid(carols, dDefault.uuid)).toHaveLength(1)
		expect(withUuid(carols, dEng.uuid)).toHaveLength(0)
		// D-eng is real and published: its own member sees it.
		expect(withUuid(await listVisible(engineer.api), dEng.uuid)).toHaveLength(1)
	})

	// @e2e dashboards::default-group-dashboard-carries-source-default-not-source-group
	test('a default dashboard is tagged "default" even for a member of a real group', async () => {
		const rows = withUuid(await listVisible(bob.api), dDefault.uuid)
		expect(rows).toHaveLength(1)
		expect(rows[0].source).toBe('default')
	})

	// @e2e dashboards::source-field-discriminates-origin
	test('each visible dashboard carries exactly one source, and the personal one is "user"', async () => {
		const alice = await fresh('e2e-dash-sources')
		const g1 = await freshGroup('e2e-dash-src1')
		const g2 = await freshGroup('e2e-dash-src2')
		await addToGroup(admin, alice.username, g1)
		await addToGroup(admin, alice.username, g2)
		const personal = (
			await createPersonal(alice.api, { name: `E2E Mine ${RUN}` })
		).dashboard
		const first = await groupDashboard(g1, `E2E Src one ${RUN}`)
		const second = await groupDashboard(g2, `E2E Src two ${RUN}`)

		const rows = await listVisible(alice.api)
		for (const row of rows) {
			expect(['user', 'group', 'default']).toContain(row.source)
		}
		// Beside the instance's own default-group dashboards, exactly hers.
		const notDefault = rows.filter((row) => row.source !== 'default')
		expect(notDefault.map((row) => [row.uuid, row.source]).sort()).toEqual(
			[
				[personal.uuid, 'user'],
				[first.uuid, 'group'],
				[second.uuid, 'group'],
			].sort(),
		)
		const defaults = rows.filter((row) => row.source === 'default')
		expect(defaults.map((row) => row.uuid)).toContain(dDefault.uuid)
		expect(rows).toHaveLength(3 + defaults.length)
		expect(new Set(rows.map((row) => row.uuid)).size).toBe(rows.length)
	})

	// @e2e dashboards::user-with-no-personal-dashboards-still-gets-visible-result
	test("a user who owns nothing gets his group's dashboard and the default one", async () => {
		const dave = await fresh('e2e-dash-dave')
		const engineering = await freshGroup('e2e-dash-dave-eng')
		await addToGroup(admin, dave.username, engineering)
		const dEng = await groupDashboard(engineering, `E2E Dave eng ${RUN}`)

		const rows = await listVisible(dave.api)
		expect(rows.filter((row) => row.source === 'user')).toEqual([])
		expect(
			rows.filter((row) => row.source === 'group').map((row) => row.uuid),
		).toEqual([dEng.uuid])
		const defaults = rows.filter((row) => row.source === 'default')
		expect(defaults.map((row) => row.uuid)).toContain(dDefault.uuid)
		expect(rows).toHaveLength(1 + defaults.length)
	})

	// @e2e dashboards::admin-gets-group-shared-dashboards-as-sourcegroup-even-though-they-own-them
	test('the admin who made a group dashboard sees it as "group", with no owner', async () => {
		const rows = withUuid(await listVisible(admin), d1.uuid)
		expect(rows).toHaveLength(1)
		expect(rows[0]).toMatchObject({
			source: 'group',
			userId: null,
			type: 'group_shared',
		})
	})
})

test.describe('REQ-DASH-014 group-scoped endpoints', () => {
	// @e2e dashboards::update-propagates-immediately
	test("a layout change by the admin is what a member's next page load serves", async () => {
		const placement = await addTextWidget(admin, d1.id, 'propagates', {
			gridX: 0,
			gridY: 30,
		})
		await setStartDashboard(bob.api, d1.uuid)
		const before = await openWorkspace(bob.api)
		expect(before.state.activeDashboardId).toBe(d1.uuid)

		const moved = {
			id: placement.id,
			gridX: 5,
			gridY: 33,
			gridWidth: 3,
			gridHeight: 3,
		}
		const res = await admin.put(`${API}/dashboards/group/${groupG}/${d1.uuid}`, {
			data: { placements: [moved] },
		})
		expect(res.status(), await res.text()).toBe(200)

		const after = await openWorkspace(bob.api)
		expect(after.status).toBe(200)
		expect(after.state.activeDashboardId).toBe(d1.uuid)
		expect(after.state.dashboardSource).toBe('group')
		const served = (after.state.layout as Array<Record<string, unknown>>).find(
			(p) => p.id === placement.id,
		)
		expect(
			served,
			"the member is not served the admin's placement row",
		).toBeDefined()
		expect(served).toMatchObject({ ...moved, dashboardId: d1.id })
		// No per-user copy: nothing of his is based on D1.
		expect((await listPersonal(bob.api)).map((row) => row.name)).not.toContain(
			d1.name,
		)
	})

	// @e2e dashboards::update-on-a-group-shared-dashboard-rejects-userid-field-changes
	test('a userId in the update body is ignored', async () => {
		const res = await admin.put(`${API}/dashboards/group/${groupG}/${d1.uuid}`, {
			data: { userId: bob.username },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).dashboard).toMatchObject({
			userId: null,
			type: 'group_shared',
			groupId: groupG,
		})

		const stored = withUuid(await listVisible(admin), d1.uuid)[0]
		expect(stored).toMatchObject({ userId: null, type: 'group_shared' })
		expect(withUuid(await listVisible(bob.api), d1.uuid)[0].source).toBe('group')
	})

	// @e2e dashboards::groupid-mismatch-between-path-and-record-returns-404
	test('a dashboard asked for under another group is 404', async () => {
		const other = bareGroupId('e2e-dash-other')
		const mismatch = await admin.get(
			`${API}/dashboards/group/${other}/${d1.uuid}`,
		)
		expect(mismatch.status(), await mismatch.text()).toBe(404)

		const right = await admin.get(`${API}/dashboards/group/${groupG}/${d1.uuid}`)
		expect(right.status(), await right.text()).toBe(200)
		expect((await right.json()).dashboard.uuid).toBe(d1.uuid)
	})

	// @e2e dashboards::get-apidashboards-remains-backward-compatible
	test('the legacy listing holds only personal dashboards', async () => {
		const alice = await fresh('e2e-dash-legacy')
		const shared = await freshGroup('e2e-dash-legacy')
		await addToGroup(admin, alice.username, shared)
		const own = [
			(await createPersonal(alice.api, { name: `E2E Legacy 1 ${RUN}` }))
				.dashboard,
			(await createPersonal(alice.api, { name: `E2E Legacy 2 ${RUN}` }))
				.dashboard,
		]
		const group = [
			await groupDashboard(shared, `E2E Shared 1 ${RUN}`),
			await groupDashboard(shared, `E2E Shared 2 ${RUN}`),
			await groupDashboard(shared, `E2E Shared 3 ${RUN}`),
		]
		// The three are visible to her.
		const visible = (await listVisible(alice.api)).map((row) => row.uuid)
		for (const dashboard of group) {
			expect(visible).toContain(dashboard.uuid)
		}

		const legacy = await listPersonal(alice.api)
		expect(legacy.map((row) => row.uuid).sort()).toEqual(
			own.map((d) => d.uuid).sort(),
		)
	})

	// @e2e dashboards::group-shared-dashboard-serialisation-includes-groupid
	test('group-shared payloads carry their groupId; personal and template ones carry null', async () => {
		const made = await groupDashboard(groupG, `E2E Serialise ${RUN}`)
		expect(made.groupId).toBe(groupG)
		const updated = await admin.put(
			`${API}/dashboards/group/${groupG}/${made.uuid}`,
			{
				data: { description: 'serialise' },
			},
		)
		expect(updated.status()).toBe(200)
		expect((await updated.json()).dashboard.groupId).toBe(groupG)
		expect(withUuid(await listVisible(admin), made.uuid)[0].groupId).toBe(groupG)
		expect(withUuid(await listVisible(bob.api), d1.uuid)[0].groupId).toBe(groupG)

		const personal = (
			await createPersonal(bob.api, { name: `E2E Null group ${RUN}` })
		).dashboard
		expect(personal).toHaveProperty('groupId', null)
		expect((await showDashboard(bob.api, personal.id)).dashboard).toHaveProperty(
			'groupId',
			null,
		)
		const template = await admin.post(`${API}/admin/templates`, {
			data: { name: `E2E Null tpl ${RUN}` },
		})
		expect(template.status(), await template.text()).toBe(201)
		const templateBody = await template.json()
		try {
			expect(templateBody).toHaveProperty('groupId', null)
		} finally {
			await admin.delete(`${API}/admin/templates/${templateBody.id}`)
		}
	})

	// The group-scoped GET and list run in dashboards-group-serialisation.spec.ts,
	// outside serial mode, because they are expected red.
})

test.describe('REQ-DASH-015..017 the per-group default', () => {
	const marketing = bareGroupId('e2e-dash-def')
	let a: DashboardRow
	let b: DashboardRow
	let c: DashboardRow

	test.beforeAll(async () => {
		a = await groupDashboard(marketing, `E2E A ${RUN}`, false)
		b = await groupDashboard(marketing, `E2E B ${RUN}`, false)
		c = await groupDashboard(marketing, `E2E C ${RUN}`, false)
		await promote(marketing, a.uuid)
	})

	// @e2e dashboards::setting-default-flips-others-off
	test('promoting C clears A and leaves B off', async () => {
		expect(await defaultFlags(marketing)).toEqual({
			[a.uuid]: 1,
			[b.uuid]: 0,
			[c.uuid]: 0,
		})
		const res = await admin.post(
			`${API}/dashboards/group/${marketing}/default`,
			{ data: { uuid: c.uuid } },
		)
		expect(res.status(), await res.text()).toBe(200)
		expect(await res.json()).toEqual({
			status: 'ok',
			groupId: marketing,
			uuid: c.uuid,
		})
		expect(await defaultFlags(marketing)).toEqual({
			[a.uuid]: 0,
			[b.uuid]: 0,
			[c.uuid]: 1,
		})
	})

	// @e2e dashboards::default-cannot-be-set-across-groups
	test("a dashboard of another group cannot be made this group's default", async () => {
		const sales = bareGroupId('e2e-dash-sales')
		const s = await groupDashboard(sales, `E2E Sales ${RUN}`, false)
		await promote(sales, s.uuid)
		const marketingBefore = await defaultFlags(marketing)

		const res = await admin.post(`${API}/dashboards/group/${sales}/default`, {
			data: { uuid: a.uuid },
		})
		expect(res.status(), await res.text()).toBe(404)
		expect(await defaultFlags(marketing)).toEqual(marketingBefore)
		expect(await defaultFlags(sales)).toEqual({ [s.uuid]: 1 })
	})

	// @e2e dashboards::setting-non-existent-dashboard-as-default
	test('a uuid that matches nothing in the group is 404', async () => {
		const before = await defaultFlags(marketing)
		const res = await admin.post(
			`${API}/dashboards/group/${marketing}/default`,
			{
				data: { uuid: '00000000-0000-4000-8000-000000000000' },
			},
		)
		expect(res.status(), await res.text()).toBe(404)
		expect(await defaultFlags(marketing)).toEqual(before)

		const empty = bareGroupId('e2e-dash-empty')
		const none = await admin.post(`${API}/dashboards/group/${empty}/default`, {
			data: { uuid: a.uuid },
		})
		expect(none.status(), await none.text()).toBe(404)
	})

	// @e2e dashboards::non-admin-cannot-set-default
	test('a member who is no admin is refused with 403 and no flag moves', async () => {
		const before = await defaultFlags(marketing)
		expect(before[a.uuid]).toBe(0)

		const refused = await bob.api.post(
			`${API}/dashboards/group/${marketing}/default`,
			{
				data: { uuid: a.uuid },
			},
		)
		expect(refused.status(), await refused.text()).toBe(403)
		expect(await defaultFlags(marketing)).toEqual(before)

		await promote(marketing, a.uuid)
		expect(await defaultFlags(marketing)).toEqual({
			[a.uuid]: 1,
			[b.uuid]: 0,
			[c.uuid]: 0,
		})
	})

	// @e2e dashboards::transaction-safety-under-concurrent-calls
	test('two concurrent promotions leave exactly one default', async () => {
		await promote(marketing, a.uuid)
		let succeeded: string[]
		const second = await adminContext()
		try {
			const [toB, toC] = await Promise.all([
				admin.post(`${API}/dashboards/group/${marketing}/default`, {
					data: { uuid: b.uuid },
				}),
				second.post(`${API}/dashboards/group/${marketing}/default`, {
					data: { uuid: c.uuid },
				}),
			])
			// The scenario fixes the end state, not the answers; at least one
			// promotion must have gone through.
			succeeded = [
				toB.status() === 200 ? b.uuid : null,
				toC.status() === 200 ? c.uuid : null,
			].filter((uuid): uuid is string => uuid !== null)
		} finally {
			await second.dispose()
		}
		expect(succeeded.length).toBeGreaterThan(0)

		const flags = await defaultFlags(marketing)
		const defaults = Object.entries(flags)
			.filter(([, flag]) => flag === 1)
			.map(([uuid]) => uuid)
		expect(defaults).toHaveLength(1)
		expect([b.uuid, c.uuid]).toContain(defaults[0])
		if (succeeded.length === 1) {
			expect(defaults[0]).toBe(succeeded[0])
		}
		expect(flags[a.uuid]).toBe(0)
		expect(Object.values(flags).filter((flag) => flag === 0)).toHaveLength(2)
	})

	// @e2e dashboards::create-then-no-default
	test('the first dashboard of an empty group is created non-default', async () => {
		const empty = bareGroupId('e2e-dash-first')
		const res = await admin.post(`${API}/dashboards/group/${empty}`, {
			data: { name: 'First' },
		})
		expect(res.status(), await res.text()).toBe(201)
		const first = track(empty, (await res.json()).dashboard as DashboardRow)
		expect(first.isDefault).toBe(0)

		const rows = await listGroup(admin, empty)
		expect(rows.map((row) => [row.uuid, row.isDefault])).toEqual([
			[first.uuid, 0],
		])
	})

	// @e2e dashboards::create-payload-cannot-smuggle-isdefault
	test('an isDefault in the create body is ignored', async () => {
		const empty = bareGroupId('e2e-dash-sneaky')
		const res = await admin.post(`${API}/dashboards/group/${empty}`, {
			data: { name: 'Sneaky', isDefault: 1 },
		})
		expect(res.status(), await res.text()).toBe(201)
		const sneaky = track(empty, (await res.json()).dashboard as DashboardRow)
		expect(sneaky.isDefault).toBe(0)
		expect(
			(await listGroup(admin, empty)).map((row) => [row.uuid, row.isDefault]),
		).toEqual([[sneaky.uuid, 0]])
	})

	// @e2e dashboards::put-cannot-flip-the-default-off
	test('a PUT renames the default but cannot clear its flag', async () => {
		await promote(marketing, a.uuid)
		const res = await admin.put(
			`${API}/dashboards/group/${marketing}/${a.uuid}`,
			{
				data: { name: 'Renamed', isDefault: 0 },
			},
		)
		expect(res.status(), await res.text()).toBe(200)

		const stored = withUuid(await listGroup(admin, marketing), a.uuid)[0]
		expect(stored).toMatchObject({ name: 'Renamed', isDefault: 1 })
	})

	// @e2e dashboards::put-cannot-flip-the-default-on
	test('a PUT renames another dashboard but cannot make it the default', async () => {
		const res = await admin.put(
			`${API}/dashboards/group/${marketing}/${b.uuid}`,
			{
				data: { name: 'Renamed B', isDefault: 1 },
			},
		)
		expect(res.status(), await res.text()).toBe(200)

		const rows = await listGroup(admin, marketing)
		expect(withUuid(rows, b.uuid)[0]).toMatchObject({
			name: 'Renamed B',
			isDefault: 0,
		})
		expect(withUuid(rows, a.uuid)[0].isDefault).toBe(1)
	})
})
