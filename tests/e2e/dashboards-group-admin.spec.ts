/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Group-shared dashboards from the administrator's side: the Beheer ▸ Group
 * dashboards tab (REQ-DASH-015 Admin group-management UI) and the rule that
 * group-shared CRUD is for administrators only (REQ-DASH-014 Group-shared
 * dashboard CRUD is admin-only and declared as such), in
 * openspec/specs/dashboards/spec.md.
 *
 * Each test makes its own Nextcloud group through the provisioning API and
 * removes it afterwards. The tab is driven as the administrator through its
 * own buttons and dialogs. Refusals are probed with Bob, an ordinary account
 * that IS a member of the group (so membership is not what refuses him),
 * re-read as the administrator, and paired with the administrator succeeding.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::admin-opens-the-group-dashboards-tab
 *   @e2e dashboards::admin-creates-a-group-shared-dashboard
 *   @e2e dashboards::admin-deletes-the-last-dashboard-in-a-group
 *   @e2e dashboards::non-admin-cannot-open-the-tab
 *   @e2e dashboards::non-admin-cannot-create-a-group-shared-dashboard
 *   @e2e dashboards::non-admin-cannot-update-delete-or-set-default-a-group-shared-dashboard
 *   @e2e dashboards::admin-can-perform-all-four-group-shared-operations
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { DashboardRow } from './support/dashboardHierarchy.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import { visibleRows } from './support/dashboardHierarchy.ts'
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

const T = Date.now()
const SETTINGS = '/index.php/apps/launchpad/admin'

let admin: APIRequestContext
let bob: Principal | null = null
const groups: string[] = []

/**
 * A fresh group with Bob in it.
 *
 * @param label the readable part of the id.
 * @return the group id.
 */
async function group(label: string): Promise<string> {
	const id = await createGroup(admin, `e2e-gd-${label}-${T}`)
	groups.push(id)
	await addToGroup(admin, bob!.username, id)
	return id
}

/**
 * Create a group-shared dashboard as the administrator.
 *
 * @param groupId the group.
 * @param name the name.
 * @return the dashboard.
 */
async function groupDashboard(groupId: string, name: string): Promise<DashboardRow> {
	const res = await admin.post(`${API}/dashboards/group/${groupId}`, {
		data: { name },
	})
	expect(res.status(), await res.text()).toBe(201)
	return (await res.json()).dashboard as DashboardRow
}

/**
 * The group's dashboards, as the administrator lists them.
 *
 * @param groupId the group.
 * @return the dashboards.
 */
async function listGroup(groupId: string): Promise<DashboardRow[]> {
	const res = await admin.get(`${API}/dashboards/group/${groupId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (Array.isArray(body) ? body : body.data) as DashboardRow[]
}

/**
 * Open the Group dashboards tab as the administrator.
 *
 * @param page the admin page.
 * @return nothing.
 */
async function openTab(page: Page): Promise<void> {
	await page.goto(SETTINGS)
	const tab = adminTab(page, 'group-dashboards')
	await expect(tab).toBeVisible({ timeout: 30_000 })
	await tab.click()
	await expect(page.locator('[data-test="group-dashboards-list"]')).toBeVisible({
		timeout: 20_000,
	})
}

test.describe('dashboards — group dashboards for administrators (REQ-DASH-015, REQ-DASH-014)', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(120_000)

	test.beforeAll(async () => {
		admin = await adminContext()
		bob = await createPrincipal(admin, 'e2e-gd-bob')
		await assertActingAs(bob.api, bob.username)
	})

	test.afterAll(async () => {
		for (const id of groups) {
			for (const dash of await listGroup(id).catch(() => [])) {
				await admin.delete(`${API}/dashboards/group/${id}/${dash.uuid}`)
			}
			await removeGroup(admin, id)
		}
		await removePrincipal(admin, bob)
		await admin?.dispose()
	})

	// ── REQ-DASH-015 Admin group-management UI ──────────────────────────

	// @e2e dashboards::admin-opens-the-group-dashboards-tab
	test('the tab lists the default row first, then one row per group, each with its count and View / Create / Manage', async ({
		page,
	}) => {
		const gid = await group('tab')
		await groupDashboard(gid, `E2E Tab one ${T}`)
		const defaultCount = (await listGroup('default')).length

		await openTab(page)
		const rows = page.locator('[data-test="group-dashboards-list"] > li')
		await expect(rows.first()).toHaveAttribute(
			'data-test',
			'group-dashboards-row-default',
		)

		const ocs = await admin.get('/ocs/v1.php/cloud/groups?format=json')
		const allGroups = (await ocs.json()).ocs.data.groups as string[]
		for (const id of allGroups) {
			await expect(
				page.locator(`[data-test="group-dashboards-row-${id}"]`),
				id,
			).toHaveCount(1)
		}
		await expect(rows).toHaveCount(allGroups.length + 1)

		await expect(
			page.locator(`[data-test="group-dashboards-count-${gid}"]`),
		).toHaveText('1')
		await expect(
			page.locator('[data-test="group-dashboards-count-default"]'),
		).toHaveText(String(defaultCount))
		for (const id of ['default', gid]) {
			await expect(
				page.locator(`[data-test="group-dashboards-view-${id}"]`),
			).toHaveText('View')
			await expect(
				page.locator(`[data-test="group-dashboards-create-${id}"]`),
			).toBeVisible()
			await expect(
				page.locator(`[data-test="group-dashboards-manage-${id}"]`),
			).toHaveText('Manage')
		}
	})

	// @e2e dashboards::admin-creates-a-group-shared-dashboard
	test('"Create" opens the dialog, submitting POSTs to the group, and the new dashboard is counted and listed', async ({
		page,
	}) => {
		const gid = await group('create')
		await groupDashboard(gid, `E2E Existing ${T}`)
		const name = `E2E Made in the tab ${T}`

		await openTab(page)
		await expect(
			page.locator(`[data-test="group-dashboards-count-${gid}"]`),
		).toHaveText('1')
		await page.locator(`[data-test="group-dashboards-create-${gid}"]`).click()

		const dialog = page.locator('[data-test="create-group-dashboard-modal"]')
		await expect(dialog).toBeVisible()
		await expect(
			page.getByRole('dialog').filter({
				has: page.locator('input[data-test="create-group-dashboard-name"]'),
			}),
		).toBeVisible()
		for (const field of ['name', 'icon', 'layout', 'default']) {
			await expect(
				page
					.locator(`[data-test="create-group-dashboard-${field}"]`)
					.first(),
				field,
			).toBeVisible()
		}

		await page
			.locator('input[data-test="create-group-dashboard-name"]')
			.fill(name)
		const posted = page.waitForRequest(
			(req) =>
				req.method() === 'POST'
				&& new URL(req.url()).pathname.endsWith(
					`/api/dashboards/group/${gid}`,
				),
		)
		const answered = page.waitForResponse(
			(res) =>
				res.request().method() === 'POST'
				&& new URL(res.url()).pathname.endsWith(
					`/api/dashboards/group/${gid}`,
				),
		)
		await page.locator('[data-test="create-group-dashboard-submit"]').click()
		expect((await posted).postDataJSON()).toMatchObject({ name })
		expect((await answered).status()).toBe(201)

		await expect(dialog).toBeHidden()
		await expect(
			page.locator(`[data-test="group-dashboards-count-${gid}"]`),
		).toHaveText('2')
		const made = (await listGroup(gid)).find((dash) => dash.name === name)
		expect(made).toBeDefined()

		await page.locator(`[data-test="group-dashboards-manage-${gid}"]`).click()
		const manage = page.locator('[data-test="manage-group-dashboards-modal"]')
		await expect(
			manage.locator(`[data-test="mgd-row-${made!.uuid}"]`),
		).toContainText(name)
	})

	// Not a scenario verdict on its own: the server half of the scenario below,
	// which runs while the toast half is a suspected bug.
	test('the last dashboard of a group cannot be deleted: 400, and it stays for its members', async () => {
		const gid = await group('lastapi')
		const only = await groupDashboard(gid, `E2E Only one ${T}`)
		expect(
			(await admin.post(`${API}/dashboards/${only.uuid}/publish`)).status(),
		).toBe(200)

		const refused = await admin.delete(
			`${API}/dashboards/group/${gid}/${only.uuid}`,
		)
		expect(refused.status()).toBe(400)
		expect((await listGroup(gid)).map((dash) => dash.uuid)).toEqual([only.uuid])
		expect((await visibleRows(bob!.api)).map((row) => row.uuid)).toContain(
			only.uuid,
		)
	})

	// @e2e dashboards::admin-deletes-the-last-dashboard-in-a-group
	test.fixme('deleting the last dashboard of a group is refused with a toast that explains the guard', async ({
		page,
	}) => {
		// SUSPECTED BUG: the guard reaches the browser without its reason.
		// DashboardService::deleteGroupShared() throws a plain
		// Exception(ERR_LAST_IN_GROUP) (lib/Service/DashboardService.php:1503),
		// which DashboardApiController::deleteGroup() turns into
		// 400 {"error": "Operation failed"} (lib/Controller/DashboardApiController.php:1326).
		// The store only explains the guard for error code
		// `last_group_dashboard` (src/stores/groupDashboards.js:33,199-203), so
		// the admin is shown the generic "Failed to delete dashboard".
		const gid = await group('last')
		const only = await groupDashboard(gid, `E2E Last one ${T}`)
		expect(
			(await admin.post(`${API}/dashboards/${only.uuid}/publish`)).status(),
		).toBe(200)

		await openTab(page)
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

		await expect(
			page
				.locator('.toastify')
				.filter({ hasText: 'Last dashboard cannot be deleted' }),
		).toBeVisible()
		expect((await listGroup(gid)).map((dash) => dash.uuid)).toEqual([only.uuid])
		expect((await visibleRows(bob!.api)).map((row) => row.uuid)).toContain(
			only.uuid,
		)
	})

	// @e2e dashboards::non-admin-cannot-open-the-tab
	test('a non-administrator gets no Group dashboards tab, and the admin endpoints answer 403', async ({
		browser,
		page,
	}) => {
		const gid = await group('noadmin')
		const { context, page: bobPage } = await loginAs(
			browser,
			bob!.username,
			bob!.password,
		)
		try {
			await bobPage.goto(SETTINGS)
			await bobPage.waitForLoadState('domcontentloaded')
			await expect(
				bobPage.locator('[data-test="tab-group-dashboards"]'),
			).toHaveCount(0)
			await expect(adminTab(bobPage, 'group-dashboards')).toHaveCount(0)
		} finally {
			await context.close()
		}

		expect((await bob!.api.get(`${API}/admin/groups`)).status()).toBe(403)
		const create = await bob!.api.post(`${API}/dashboards/group/${gid}`, {
			data: { name: `E2E Bob ${T}` },
		})
		expect(create.status()).toBe(403)
		expect(await listGroup(gid)).toEqual([])

		// Control: the administrator reaches both.
		expect((await admin.get(`${API}/admin/groups`)).status()).toBe(200)
		await openTab(page)
		await expect(
			page.locator(`[data-test="group-dashboards-row-${gid}"]`),
		).toBeVisible()
	})

	// ── REQ-DASH-014 Group-shared dashboard CRUD is admin-only ───────────

	// @e2e dashboards::non-admin-cannot-create-a-group-shared-dashboard
	test('a non-admin member creating a group-shared dashboard is refused 403 and nothing is made', async () => {
		const gid = await group('create403')
		const name = `E2E Bob create ${T}`

		const refused = await bob!.api.post(`${API}/dashboards/group/${gid}`, {
			data: { name },
		})
		expect(refused.status()).toBe(403)
		expect((await listGroup(gid)).map((dash) => dash.name)).not.toContain(name)

		const allowed = await admin.post(`${API}/dashboards/group/${gid}`, {
			data: { name },
		})
		expect(allowed.status()).toBe(201)
		expect((await listGroup(gid)).map((dash) => dash.name)).toContain(name)
	})

	// @e2e dashboards::non-admin-cannot-update-delete-or-set-default-a-group-shared-dashboard
	test('a non-admin member updating, deleting or setting the default is refused 403 and nothing changes', async () => {
		const gid = await group('mutate403')
		const first = await groupDashboard(gid, `E2E Keep first ${T}`)
		const second = await groupDashboard(gid, `E2E Keep second ${T}`)
		const before = await listGroup(gid)

		const update = await bob!.api.put(
			`${API}/dashboards/group/${gid}/${first.uuid}`,
			{ data: { name: 'Bob was here' } },
		)
		expect(update.status()).toBe(403)
		const remove = await bob!.api.delete(
			`${API}/dashboards/group/${gid}/${second.uuid}`,
		)
		expect(remove.status()).toBe(403)
		const setDefault = await bob!.api.post(
			`${API}/dashboards/group/${gid}/default`,
			{ data: { uuid: second.uuid } },
		)
		expect(setDefault.status()).toBe(403)

		const after = await listGroup(gid)
		expect(after.map((dash) => [dash.uuid, dash.name, dash.isDefault])).toEqual(
			before.map((dash) => [dash.uuid, dash.name, dash.isDefault]),
		)

		// Control: the administrator may make the same update.
		const allowed = await admin.put(
			`${API}/dashboards/group/${gid}/${first.uuid}`,
			{ data: { name: `E2E Renamed ${T}` } },
		)
		expect(allowed.status()).toBe(200)
	})

	// @e2e dashboards::admin-can-perform-all-four-group-shared-operations
	test('the administrator creates, updates, sets the default and deletes, with the usual payloads', async () => {
		const gid = await group('admin4')

		const created = await admin.post(`${API}/dashboards/group/${gid}`, {
			data: { name: `E2E Four ${T}` },
		})
		expect(created.status()).toBe(201)
		const createdBody = await created.json()
		expect(Object.keys(createdBody)).toEqual(['dashboard'])
		const one = createdBody.dashboard as DashboardRow
		expect(one).toMatchObject({
			name: `E2E Four ${T}`,
			type: 'group_shared',
			groupId: gid,
		})
		const two = await groupDashboard(gid, `E2E Four second ${T}`)

		const updated = await admin.put(
			`${API}/dashboards/group/${gid}/${one.uuid}`,
			{ data: { name: `E2E Four renamed ${T}` } },
		)
		expect(updated.status()).toBe(200)
		const updatedBody = await updated.json()
		expect(Object.keys(updatedBody)).toEqual(['dashboard'])
		expect(updatedBody.dashboard).toMatchObject({
			uuid: one.uuid,
			name: `E2E Four renamed ${T}`,
		})

		const flagged = await admin.post(`${API}/dashboards/group/${gid}/default`, {
			data: { uuid: two.uuid },
		})
		expect(flagged.status()).toBe(200)
		expect(await flagged.json()).toEqual({
			status: 'ok',
			groupId: gid,
			uuid: two.uuid,
		})
		expect(
			(await listGroup(gid)).find((dash) => dash.uuid === two.uuid)?.isDefault,
		).toBeTruthy()

		const deleted = await admin.delete(
			`${API}/dashboards/group/${gid}/${one.uuid}`,
		)
		expect(deleted.status()).toBe(200)
		expect(await deleted.json()).toEqual({ status: 'ok' })
		expect((await listGroup(gid)).map((dash) => dash.uuid)).toEqual([two.uuid])
	})
})
