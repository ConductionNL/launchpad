/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The group-scoped reads of a group-shared dashboard and their `groupId`
 * (openspec/specs/dashboards/spec.md, REQ-DASH-014, scenario "Group-shared
 * dashboard serialisation includes `groupId`").
 *
 * The scenario says ANY endpoint. dashboards-group-shared.spec.ts asserts the
 * create, update and `/visible` payloads and the null on personal and template
 * payloads. The two group-scoped reads were asserted there only by a
 * `test.fixme`, which gate-19 does not count and which never ran, so the
 * scenario's first THEN had no running test for these endpoints. It runs here,
 * in its own file without serial mode, because dashboards-group-shared runs
 * serially and a red there would skip every test after it.
 *
 * EXPECTED RED until the product is fixed: DashboardApiController::getGroup()
 * and ::listGroup() answer Dashboard::toViewerArray(), which unsets `groupId`
 * (with userId and targetGroups) for every caller, the admin included.
 *
 * WHAT STAYS BEHIND. The last group-shared dashboard of a group cannot be
 * deleted (REQ-DASH-014), so one row stays in a group that afterAll empties
 * and removes.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::group-shared-dashboard-serialisation-includes-groupid
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRow } from './support/dashboardsSpec.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	allowPersonalDashboardsFreely,
	createGroupDashboard,
	listGroup,
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
let settingsBefore: Awaited<
	ReturnType<typeof allowPersonalDashboardsFreely>
> | null = null
let member: Principal | null = null
let groupId = ''
let dashboard: DashboardRow

// A brand-new account's first request builds its home folder.
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	settingsBefore = await allowPersonalDashboardsFreely(admin)

	groupId = await createGroup(
		admin,
		`e2e-dash-serial-${RUN}-${Math.floor(Math.random() * 10_000)}`,
	)
	member = await createPrincipal(admin, 'e2e-dash-serial')
	await assertActingAs(member.api, member.username)
	await addToGroup(admin, member.username, groupId)
	dashboard = await createGroupDashboard(admin, groupId, `E2E Serialise ${RUN}`)
	// The create answer carries it, which shows the record holds the group.
	expect(dashboard.groupId).toBe(groupId)
})

test.afterAll(async () => {
	if (groupId !== '') {
		await admin.delete(
			`${API}/dashboards/group/${encodeURIComponent(groupId)}/${dashboard?.uuid}`,
		)
		await removeGroup(admin, groupId)
	}
	await removePrincipal(admin, member)
	if (settingsBefore !== null) {
		await writeSettings(admin, settingsBefore)
	}
	await admin?.dispose()
})

test.describe('REQ-DASH-014 groupId on the group-scoped reads', () => {
	// @e2e dashboards::group-shared-dashboard-serialisation-includes-groupid
	// EXPECTED RED: getGroup() answers toViewerArray(), which unsets groupId.
	test('the group-scoped GET carries the groupId, for the admin and for a member', async () => {
		const url = `${API}/dashboards/group/${encodeURIComponent(groupId)}/${dashboard.uuid}`
		for (const api of [admin, member!.api]) {
			const res = await api.get(url)
			expect(res.status(), await res.text()).toBe(200)
			const body = (await res.json()).dashboard
			expect(body.uuid).toBe(dashboard.uuid)
			expect(body).toHaveProperty('groupId', groupId)
		}
	})

	// @e2e dashboards::group-shared-dashboard-serialisation-includes-groupid
	// EXPECTED RED: listGroup() maps every row through toViewerArray().
	test('the group-scoped list carries the groupId on every row', async () => {
		const rows = await listGroup(admin, groupId)
		const mine = rows.filter((row) => row.uuid === dashboard.uuid)
		expect(mine).toHaveLength(1)
		for (const row of rows) {
			expect(row).toHaveProperty('groupId', groupId)
		}
	})
})
