/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for forking a visible dashboard into a personal
 * copy (openspec/specs/dashboards, REQ-DASH-020 and REQ-DASH-022), written
 * for the e2e-coverage-programme.
 *
 * `POST /api/dashboards/{uuid}/fork` is an API contract (status, envelope,
 * the copy's fields), so it is driven through the API as a throwaway,
 * non-admin member of a throwaway group. The source is a published
 * group-shared dashboard the admin seeds with three text widgets and a tile;
 * a placement is compared field by field with its clone, leaving out only
 * what a copy must not share (its id, its dashboard, its timestamps and its
 * edit version).
 *
 * WHAT STAYS BEHIND. The last group-shared dashboard of a group cannot be
 * deleted (REQ-DASH-014), so one row per group stays behind in a group that
 * no longer has members; and there is no endpoint that removes an uploaded
 * resource, so the one tiny image REQ-DASH-022 uploads stays in app data.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::fork-a-group-shared-dashboard
 *   @e2e dashboards::fork-uses-a-default-name-when-none-provided
 *   @e2e dashboards::fork-is-gated-on-admin-setting
 *   @e2e dashboards::cannot-fork-a-dashboard-you-cannot-read
 *   @e2e dashboards::forking-a-personal-dashboard-creates-an-independent-duplicate
 *   @e2e dashboards::shared-resource-reference
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type {
	CreatedGroupDashboard,
	DashboardRow,
	DashboardSettings,
	PlacementRow,
} from './support/dashboardsSpec.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import {
	addTextWidget,
	allowPersonalDashboardsFreely,
	createGroupDashboard,
	createPersonal,
	listPersonal,
	removeGroupDashboards,
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

/** What a clone must NOT share with its source placement. */
const OWN_FIELDS = ['id', 'dashboardId', 'createdAt', 'updatedAt', 'version']

let admin: APIRequestContext
let settingsBefore: DashboardSettings | null = null
const principals: Principal[] = []
const ncGroups: string[] = []
const created: CreatedGroupDashboard[] = []

let marketing = ''
let alice: Principal
let source: DashboardRow

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
 * A throwaway Nextcloud group with the admin in it, so the admin can read its
 * dashboards back through `GET /api/dashboard/{id}`.
 *
 * @param prefix a readable prefix.
 * @return the group id.
 */
async function freshGroup(prefix: string): Promise<string> {
	const groupId = await createGroup(
		admin,
		`${prefix}-${RUN}-${Math.floor(Math.random() * 10_000)}`,
	)
	ncGroups.push(groupId)
	await addToGroup(admin, ADMIN_USER, groupId)
	return groupId
}

/**
 * A published group-shared dashboard, remembered for cleanup.
 *
 * @param groupId the group.
 * @param name the name.
 * @return the dashboard.
 */
async function groupDashboard(groupId: string, name: string): Promise<DashboardRow> {
	const dashboard = await createGroupDashboard(admin, groupId, name)
	created.push({ groupId, uuid: dashboard.uuid })
	return dashboard
}

/**
 * Add a tile to a dashboard as the admin.
 *
 * @param dashboardId the dashboard.
 * @param tile the tile fields, as `POST /api/dashboard/{id}/tile` names them.
 * @return the placement.
 */
async function addTile(
	dashboardId: number,
	tile: Record<string, unknown>,
): Promise<PlacementRow> {
	const res = await admin.post(`${API}/dashboard/${dashboardId}/tile`, {
		data: tile,
	})
	expect(res.status(), await res.text()).toBe(201)
	return (await res.json()) as PlacementRow
}

/**
 * A placement without the fields a clone must not share, for comparison.
 *
 * @param placement the placement.
 * @return the shared part.
 */
function sharedPart(placement: PlacementRow): Record<string, unknown> {
	const copy: Record<string, unknown> = { ...placement }
	for (const field of OWN_FIELDS) {
		delete copy[field]
	}
	return copy
}

/**
 * The shared parts of a dashboard's placements, in a stable order.
 *
 * @param placements the placements.
 * @return the shared parts, sorted.
 */
function clonable(placements: PlacementRow[]): Array<Record<string, unknown>> {
	return placements
		.map(sharedPart)
		.sort((left, right) =>
			JSON.stringify(left).localeCompare(JSON.stringify(right)),
		)
}

/**
 * Fork a dashboard as a principal.
 *
 * @param who the principal.
 * @param uuid the source.
 * @param name the name, or undefined for an empty body.
 * @return the response.
 */
async function fork(who: Principal, uuid: string, name?: string) {
	return who.api.post(
		`${API}/dashboards/${uuid}/fork`,
		name === undefined ? {} : { data: { name } },
	)
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	settingsBefore = await allowPersonalDashboardsFreely(admin)

	marketing = await freshGroup('e2e-dash-fork')
	alice = await fresh('e2e-dash-forker')
	await addToGroup(admin, alice.username, marketing)

	source = await groupDashboard(marketing, `Marketing Overview ${RUN}`)
	await addTextWidget(admin, source.id, 'one', { gridX: 0, gridY: 0 })
	await addTextWidget(admin, source.id, 'two', { gridX: 4, gridY: 0 })
	await addTextWidget(admin, source.id, 'three', { gridX: 8, gridY: 0 })
	await addTile(source.id, {
		title: `E2E Tile ${RUN}`,
		icon: 'icon-link',
		iconType: 'class',
		bgColor: '#123456',
		textColor: '#fedcba',
		linkType: 'url',
		linkValue: 'https://example.invalid/fork',
		gridX: 0,
		gridY: 4,
	})
	expect((await showDashboard(admin, source.id)).placements).toHaveLength(4)
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

test.describe('REQ-DASH-020 fork a visible dashboard', () => {
	// @e2e dashboards::fork-a-group-shared-dashboard
	test("a member's fork is an active personal deep copy and the source is untouched", async () => {
		const before = await showDashboard(admin, source.id)
		const earlier = (
			await createPersonal(alice.api, { name: `E2E Earlier ${RUN}` })
		).dashboard

		const res = await fork(alice, source.uuid, 'My Marketing')
		expect(res.status(), await res.text()).toBe(201)
		const body = await res.json()
		expect(body.status).toBe('success')
		const copy = body.dashboard as DashboardRow
		expect(copy).toMatchObject({
			userId: alice.username,
			type: 'user',
			groupId: null,
			isDefault: 0,
			isActive: 1,
			gridColumns: before.dashboard.gridColumns,
			name: 'My Marketing',
		})
		expect(copy.id).not.toBe(source.id)
		expect(copy.uuid).not.toBe(source.uuid)

		// The copy's placements: the same four, with their own ids, on the copy.
		const forked = await showDashboard(alice.api, copy.id)
		expect(forked.placements).toHaveLength(4)
		expect(forked.placements.every((p) => p.dashboardId === copy.id)).toBe(true)
		const sourceIds = before.placements.map((p) => p.id)
		expect(forked.placements.some((p) => sourceIds.includes(p.id))).toBe(false)
		expect(clonable(forked.placements)).toEqual(clonable(before.placements))

		// Her other dashboards were deactivated.
		const rows = await listPersonal(alice.api)
		expect(
			rows.filter((row) => row.isActive === 1).map((row) => row.id),
		).toEqual([copy.id])
		expect(rows.find((row) => row.id === earlier.id)?.isActive).toBe(0)

		// The source is unchanged.
		const after = await showDashboard(admin, source.id)
		expect(after.dashboard).toMatchObject({
			name: before.dashboard.name,
			type: 'group_shared',
			groupId: marketing,
			userId: null,
			isDefault: before.dashboard.isDefault,
		})
		expect(after.placements).toEqual(before.placements)
	})

	// @e2e dashboards::fork-uses-a-default-name-when-none-provided
	test('a fork without a name is called "My copy of <source>"', async () => {
		const res = await fork(alice, source.uuid)
		expect(res.status(), await res.text()).toBe(201)
		const copy = (await res.json()).dashboard as DashboardRow
		expect(copy.name).toBe(`My copy of ${source.name}`)
		expect((await showDashboard(alice.api, copy.id)).dashboard.name).toBe(
			`My copy of ${source.name}`,
		)
	})

	// @e2e dashboards::fork-is-gated-on-admin-setting
	test('with personal dashboards off the fork is refused with the stable 403 envelope', async () => {
		const before = (await listPersonal(alice.api)).map((row) => row.id)
		await writeSettings(admin, { allowUserDash: false })
		try {
			const refused = await fork(alice, source.uuid, `E2E Refused fork ${RUN}`)
			expect(refused.status(), await refused.text()).toBe(403)
			expect(await refused.json()).toEqual({
				status: 'error',
				error: 'personal_dashboards_disabled',
				message: 'Personal dashboards are not enabled by your administrator',
			})
		} finally {
			await writeSettings(admin, { allowUserDash: true })
		}
		expect((await listPersonal(alice.api)).map((row) => row.id)).toEqual(before)

		const allowed = await fork(alice, source.uuid, `E2E Allowed fork ${RUN}`)
		expect(allowed.status(), await allowed.text()).toBe(201)
	})

	// @e2e dashboards::cannot-fork-a-dashboard-you-cannot-read
	test('a dashboard of a group she is not in is 404, as if it did not exist', async () => {
		const executives = await freshGroup('e2e-dash-exec')
		const hidden = await groupDashboard(executives, `E2E Executives ${RUN}`)
		// It exists, published, for its own group.
		const exists = await admin.get(
			`${API}/dashboards/group/${executives}/${hidden.uuid}`,
		)
		expect(exists.status(), await exists.text()).toBe(200)
		const before = (await listPersonal(alice.api)).map((row) => row.id)

		const refused = await fork(alice, hidden.uuid)
		expect(refused.status(), await refused.text()).toBe(404)
		const unknown = await fork(alice, '00000000-0000-4000-8000-000000000000')
		expect(unknown.status()).toBe(404)
		expect(await refused.json()).toEqual(await unknown.json())
		expect((await listPersonal(alice.api)).map((row) => row.id)).toEqual(before)

		const allowed = await fork(alice, source.uuid, `E2E Readable fork ${RUN}`)
		expect(allowed.status(), await allowed.text()).toBe(201)
	})

	// @e2e dashboards::forking-a-personal-dashboard-creates-an-independent-duplicate
	test('a fork of her own dashboard is a deep clone that changes independently', async () => {
		const owner = await fresh('e2e-dash-selffork')
		const original = (
			await createPersonal(owner.api, { name: `E2E Original ${RUN}` })
		).dashboard
		// Down to two placements, as in the scenario.
		const seeded = (await showDashboard(owner.api, original.id)).placements
		for (const placement of seeded) {
			const removed = await owner.api.delete(`${API}/widgets/${placement.id}`)
			expect(removed.status(), await removed.text()).toBeLessThan(300)
		}
		await addTextWidget(owner.api, original.id, 'left', { gridX: 0, gridY: 0 })
		await addTextWidget(owner.api, original.id, 'right', { gridX: 6, gridY: 0 })
		const before = await showDashboard(owner.api, original.id)
		expect(before.placements).toHaveLength(2)

		const res = await fork(owner, original.uuid)
		expect(res.status(), await res.text()).toBe(201)
		const copy = (await res.json()).dashboard as DashboardRow
		const cloned = await showDashboard(owner.api, copy.id)
		expect(cloned.placements).toHaveLength(2)
		expect(clonable(cloned.placements)).toEqual(clonable(before.placements))

		// Change the copy: the original keeps its name and layout.
		const moveCopy = await owner.api.put(`${API}/dashboard/${copy.id}`, {
			data: {
				name: `E2E Copy changed ${RUN}`,
				placements: [
					{
						id: cloned.placements[0].id,
						gridX: 2,
						gridY: 9,
						gridWidth: 2,
						gridHeight: 2,
					},
				],
			},
		})
		expect(moveCopy.status(), await moveCopy.text()).toBe(200)
		const originalNow = await showDashboard(owner.api, original.id)
		expect(originalNow.dashboard.name).toBe(original.name)
		expect(originalNow.placements).toEqual(before.placements)

		// Change the original: the copy keeps its own state.
		const copyNow = await showDashboard(owner.api, copy.id)
		const moveOriginal = await owner.api.put(`${API}/dashboard/${original.id}`, {
			data: {
				name: `E2E Original changed ${RUN}`,
				placements: [
					{
						id: before.placements[1].id,
						gridX: 1,
						gridY: 12,
						gridWidth: 3,
						gridHeight: 3,
					},
				],
			},
		})
		expect(moveOriginal.status(), await moveOriginal.text()).toBe(200)
		const copyAfter = await showDashboard(owner.api, copy.id)
		expect(copyAfter.dashboard.name).toBe(`E2E Copy changed ${RUN}`)
		expect(copyAfter.placements).toEqual(copyNow.placements)
	})
})

test.describe('REQ-DASH-022 forks share uploaded resources', () => {
	// @e2e dashboards::shared-resource-reference
	test("the copy's tile keeps the resource URL and no new file is stored", async () => {
		const png = fs
			.readFileSync(path.join(__dirname, 'fixtures', 'tiny.png'))
			.toString('base64')
		const upload = await admin.post(`${API}/resources`, {
			data: { base64: `data:image/png;base64,${png}` },
		})
		expect(upload.status(), await upload.text()).toBe(200)
		const resource = await upload.json()
		expect(String(resource.url)).toMatch(
			/^\/apps\/launchpad\/resource\/.+\.png$/,
		)

		const withIcon = await groupDashboard(marketing, `E2E Resource ${RUN}`)
		const tile = await addTile(withIcon.id, {
			title: `E2E Icon tile ${RUN}`,
			icon: resource.url,
			iconType: 'url',
			linkType: 'url',
			linkValue: 'https://example.invalid/icon',
			gridX: 0,
			gridY: 0,
		})
		expect(tile.tileIcon).toBe(resource.url)

		const listResources = async () => {
			const res = await alice.api.get(`${API}/resources`)
			expect(res.status(), await res.text()).toBe(200)
			return ((await res.json()).resources as Array<{ name: string }>)
				.map((r) => r.name)
				.sort()
		}
		const filesBefore = await listResources()
		expect(filesBefore).toContain(resource.name)

		const res = await fork(alice, withIcon.uuid, `E2E Resource fork ${RUN}`)
		expect(res.status(), await res.text()).toBe(201)
		const copy = (await res.json()).dashboard as DashboardRow
		const placements = (await showDashboard(alice.api, copy.id)).placements
		expect(placements).toHaveLength(1)
		expect(placements[0]).toMatchObject({
			tileIcon: resource.url,
			tileIconType: 'url',
		})

		expect(await listResources()).toEqual(filesBefore)
	})
})
