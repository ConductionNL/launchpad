/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Seven dashboards scenarios (openspec/specs/dashboards/spec.md) that were
 * cited only by `test.fixme`, which gate-19 does not count. They now run.
 *
 * They sit in their own file, without serial mode, because the files they
 * came from (dashboards-crud, dashboards-tree, dashboards-group-shared and
 * dashboards-group-admin) run serially: a red there would skip every test
 * after it. Each test makes its own throwaway accounts and groups.
 *
 * Expected green, because the product changed after the fixme was written:
 *   - another user's delete and cascade delete: DashboardService::
 *     deleteDashboard() now throws ForbiddenException, which
 *     DashboardApiController::delete() answers 403;
 *   - grid columns on update: DashboardApiController::update() now reads
 *     `gridColumns` (REQ-DASH-038) and stores 6, 8 or 12 without reflowing.
 *
 * The refusals carry their reason: another user's activate is a 403
 * (ForbiddenException), a second "Work" takes the slug `work-2`
 * (freeRootSlug), and the last dashboard of a group is refused with the
 * guard's own message, which src/stores/groupDashboards.js turns into
 * "Last dashboard cannot be deleted".
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::delete-another-users-dashboard
 *   @e2e dashboards::activate-another-users-dashboard
 *   @e2e dashboards::update-grid-columns-on-a-dashboard-with-existing-widgets
 *   @e2e dashboards::duplicate-dashboard-names-allowed
 *   @e2e dashboards::user-cannot-delete-another-users-dashboard-subtree
 *   @e2e dashboards::group-shared-dashboard-cannot-be-deleted-while-it-is-the-last-one-in-the-group
 *   @e2e dashboards::admin-deletes-the-last-dashboard-in-a-group
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRow } from './support/dashboardsSpec.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { adminTab } from './support/adminTab.ts'
import { readDashboard, visibleRows } from './support/dashboardHierarchy.ts'
import {
	addTextWidget,
	allowPersonalDashboardsFreely,
	createGroupDashboard,
	createPersonal,
	listGroup,
	listPersonal,
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
const SETTINGS = '/index.php/apps/launchpad/admin'

let admin: APIRequestContext
let settingsBefore: Awaited<ReturnType<typeof allowPersonalDashboardsFreely>> | null = null
const principals: Principal[] = []
const groups: string[] = []

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
 * A throwaway Nextcloud group, removed in afterAll.
 *
 * @param label the readable part of the id.
 * @return the group id.
 */
async function freshGroup(label: string): Promise<string> {
	const id = await createGroup(
		admin,
		`e2e-dash-guard-${label}-${RUN}-${Math.floor(Math.random() * 10_000)}`,
	)
	groups.push(id)
	return id
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

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	settingsBefore = await allowPersonalDashboardsFreely(admin)
})

test.afterAll(async () => {
	for (const id of groups) {
		for (const dash of await listGroup(admin, id).catch(() => [])) {
			await admin.delete(`${API}/dashboards/group/${id}/${dash.uuid}`)
		}
		await removeGroup(admin, id)
	}
	for (const principal of principals) {
		await removePrincipal(admin, principal)
	}
	if (settingsBefore !== null) {
		await writeSettings(admin, settingsBefore)
	}
	await admin?.dispose()
})

test.describe('REQ-DASH-004 / REQ-DASH-005 / REQ-DASH-006 ownership and columns', () => {
	// @e2e dashboards::delete-another-users-dashboard
	test("another user's delete is refused with 403 and deletes nothing", async () => {
		const alice = await fresh('e2e-dash-del-own')
		const bob = await fresh('e2e-dash-del-other')
		const { dashboard } = await createPersonal(alice.api, {
			name: `E2E Keep ${RUN}`,
		})

		const refused = await bob.api.delete(`${API}/dashboard/${dashboard.id}`)
		expect(refused.status(), await refused.text()).toBe(403)
		expect((await showDashboard(alice.api, dashboard.id)).dashboard.id).toBe(
			dashboard.id,
		)
		expect((await listPersonal(alice.api)).map((row) => row.id)).toContain(
			dashboard.id,
		)

		const allowed = await alice.api.delete(`${API}/dashboard/${dashboard.id}`)
		expect(allowed.status(), await allowed.text()).toBe(200)
	})

	// @e2e dashboards::activate-another-users-dashboard
	test("another user's activate is refused with 403", async () => {
		const alice = await fresh('e2e-dash-act-own')
		const bob = await fresh('e2e-dash-act-other')
		const older = await createPersonal(alice.api, { name: `E2E Older ${RUN}` })
		const newer = await createPersonal(alice.api, { name: `E2E Newer ${RUN}` })

		const refused = await bob.api.post(
			`${API}/dashboard/${older.dashboard.id}/activate`,
		)
		expect(refused.status(), await refused.text()).toBe(403)
		const rows = await listPersonal(alice.api)
		expect(byId(rows, older.dashboard.id).isActive).toBe(0)
		expect(byId(rows, newer.dashboard.id).isActive).toBe(1)

		const allowed = await alice.api.post(
			`${API}/dashboard/${older.dashboard.id}/activate`,
		)
		expect(allowed.status(), await allowed.text()).toBe(200)
	})

	// @e2e dashboards::update-grid-columns-on-a-dashboard-with-existing-widgets
	test('grid columns change to 6 and the widgets stay where they were', async () => {
		const alice = await fresh('e2e-dash-cols')
		const { dashboard } = await createPersonal(alice.api, {
			name: `E2E Columns ${RUN}`,
		})
		await addTextWidget(alice.api, dashboard.id, 'wide', { gridX: 8, gridY: 20 })
		const before = await showDashboard(alice.api, dashboard.id)
		expect(before.dashboard.gridColumns).toBe(12)
		expect(before.placements.length).toBeGreaterThanOrEqual(4)

		const res = await alice.api.put(`${API}/dashboard/${dashboard.id}`, {
			data: { gridColumns: 6 },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).dashboard.gridColumns).toBe(6)

		const after = await showDashboard(alice.api, dashboard.id)
		expect(after.dashboard.gridColumns).toBe(6)
		// No reflow: every placement keeps its position (the scenario's NOTE),
		// including the one at column 8, beyond the new count.
		const position = (p: Record<string, unknown>) => [
			p.id,
			p.gridX,
			p.gridY,
			p.gridWidth,
			p.gridHeight,
		]
		expect(after.placements.map(position)).toEqual(
			before.placements.map(position),
		)
	})
})

test.describe('REQ-DASH-007 dashboard names', () => {
	// @e2e dashboards::duplicate-dashboard-names-allowed
	test('a second dashboard with the same name is created alongside the first', async () => {
		const alice = await fresh('e2e-dash-dupe')
		const first = await createPersonal(alice.api, { name: 'Work' })
		const res = await alice.api.post(`${API}/dashboard`, {
			data: { name: 'Work' },
		})
		expect(res.status(), await res.text()).toBe(201)
		const second = (await res.json()).dashboard as DashboardRow

		expect(second.id).not.toBe(first.dashboard.id)
		expect(second.uuid).not.toBe(first.dashboard.uuid)
		const rows = await listPersonal(alice.api)
		expect(
			rows
				.filter((row) => row.name === 'Work')
				.map((row) => row.id)
				.sort(),
		).toEqual([first.dashboard.id, second.id].sort())
	})
})

test.describe('REQ-DASH-030 cascade delete', () => {
	// @e2e dashboards::user-cannot-delete-another-users-dashboard-subtree
	test('another user deleting a subtree is refused with 403 and nothing is deleted', async () => {
		const alice = await fresh('e2e-dash-tree-own')
		const bob = await fresh('e2e-dash-tree-other')
		const { dashboard: tree } = await createPersonal(alice.api, {
			name: `E2E Foreign delete ${RUN}`,
		})
		const { dashboard: kid } = await createPersonal(alice.api, {
			name: 'Kid',
			parentUuid: tree.uuid,
		})

		const refused = await bob.api.delete(
			`${API}/dashboard/${tree.id}?cascade=true`,
		)
		expect(refused.status(), await refused.text()).toBe(403)
		expect((await readDashboard(alice.api, tree.id)).status).toBe(200)
		expect((await readDashboard(alice.api, kid.id)).status).toBe(200)

		// Control: the owner may.
		const allowed = await alice.api.delete(
			`${API}/dashboard/${tree.id}?cascade=true`,
		)
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect((await readDashboard(alice.api, tree.id)).status).toBe(404)
		expect((await readDashboard(alice.api, kid.id)).status).toBe(404)
	})
})

test.describe('REQ-DASH-014 / REQ-DASH-015 the last dashboard of a group', () => {
	// @e2e dashboards::group-shared-dashboard-cannot-be-deleted-while-it-is-the-last-one-in-the-group
	test('deleting the only dashboard of a group is refused with 400 and its reason', async () => {
		const marketing = await freshGroup('last-api')
		const only = await createGroupDashboard(admin, marketing, `E2E Only ${RUN}`)

		const refused = await admin.delete(
			`${API}/dashboards/group/${marketing}/${only.uuid}`,
		)
		expect(refused.status(), await refused.text()).toBe(400)
		expect(await refused.json()).toEqual({
			error: 'Cannot delete the only dashboard in the group',
		})
		expect((await listGroup(admin, marketing)).map((row) => row.uuid)).toEqual([
			only.uuid,
		])

		// With a second one beside it, the same delete goes through.
		await createGroupDashboard(admin, marketing, `E2E Second ${RUN}`)
		const allowed = await admin.delete(
			`${API}/dashboards/group/${marketing}/${only.uuid}`,
		)
		expect(allowed.status(), await allowed.text()).toBe(200)
	})

	// @e2e dashboards::admin-deletes-the-last-dashboard-in-a-group
	test('deleting the last dashboard of a group is refused with a toast that explains the guard', async ({
		page,
	}) => {
		const bob = await fresh('e2e-dash-guard-member')
		const gid = await freshGroup('last-ui')
		await addToGroup(admin, bob.username, gid)
		const only = await createGroupDashboard(admin, gid, `E2E Last one ${RUN}`)

		await page.goto(SETTINGS)
		const tab = adminTab(page, 'group-dashboards')
		await expect(tab).toBeVisible({ timeout: 30_000 })
		await tab.click()
		await expect(page.locator('[data-test="group-dashboards-list"]')).toBeVisible({
			timeout: 20_000,
		})
		await page.locator(`[data-test="group-dashboards-manage-${gid}"]`).click()
		const manage = page.locator('[data-test="manage-group-dashboards-modal"]')
		await manage.locator(`[data-test="mgd-delete-${only.uuid}"]`).click()
		const answered = page.waitForResponse(
			(res) =>
				res.request().method() === 'DELETE'
				&& res.url().endsWith(`/api/dashboards/group/${gid}/${only.uuid}`),
		)
		await page
			.getByRole('dialog', { name: 'Delete dashboard' })
			.getByRole('button', { name: 'Delete' })
			.click()
		expect((await answered).status()).toBe(400)

		// @nextcloud/dialogs draws its toasts without the old `.toastify` class.
		await expect(
			page.getByText('Last dashboard cannot be deleted', { exact: true }),
		).toBeVisible()
		expect((await listGroup(admin, gid)).map((dash) => dash.uuid)).toEqual([
			only.uuid,
		])
		expect((await visibleRows(bob.api)).map((row) => row.uuid)).toContain(
			only.uuid,
		)
	})
})
