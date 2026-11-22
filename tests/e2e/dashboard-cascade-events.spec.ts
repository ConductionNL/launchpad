/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What disappears with a dashboard, a user or a group
 * (openspec/specs/dashboard-cascade-events), written for e2e-coverage-programme
 * task 2.5.
 *
 * The cascade has no control of its own: it is what `DELETE /api/dashboard/{id}`
 * and Nextcloud's user and group deletion (OCS provisioning API) leave behind.
 * So every test deletes through those HTTP surfaces and then reads back, as the
 * people concerned, what is still there.
 *
 * HOW A ROW NOBODY CAN READ ANY MORE IS COUNTED. A placement or share whose
 * dashboard is gone has no endpoint left that returns it. The orphaned-data
 * cleanup counts exactly those rows, and its dry run counts live inside a
 * rolled-back transaction (the scan is cached for 300s, so it would compare a
 * number with itself). A before/after pair of dry-run counts therefore shows
 * whether a delete left rows behind, as tests/e2e/ci/dashboard-share-api.spec.ts
 * does for shares.
 *
 * Every dashboard here belongs to a throwaway account, so the admin's own
 * active dashboard is never moved. Personal dashboards are switched on for the
 * file and restored afterwards; the role-feature widget allow-list is pinned
 * so the `activity` widget is addable by a plain account.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-cascade-events::event-is-not-dispatched-when-validation-rejects-the-delete
 *   @e2e dashboard-cascade-events::non-cascade-delete-with-children-is-rejected-before-event-dispatch
 *   @e2e dashboard-cascade-events::widget-placements-are-deleted-on-dashboard-delete
 *   @e2e dashboard-cascade-events::tree-listener-recursively-cascades-to-child-dashboards
 *   @e2e dashboard-cascade-events::personal-dashboards-are-deleted-on-user-deletion
 *   @e2e dashboard-cascade-events::role-assignments-are-removed-on-user-deletion
 *   @e2e dashboard-cascade-events::lifecycle-listeners-are-registered-for-nc-events
 *   @e2e dashboard-cascade-events::analytics-opt-out-preference-is-removed-on-user-deletion
 *
 * @spec openspec/specs/dashboard-cascade-events/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import {
	adminContext,
	API,
	assertActingAs,
	assignRole,
	contextFor,
	createGroup,
	createPrincipal,
	publishForRecipients,
	removeGroup,
	removePrincipal,
	removeRole,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const SETTINGS = `${API}/admin/settings`
const PURGE = `${API}/admin/cleanup/purge`
const STAMP = `E2E Cascade ${Date.now()}`

/** A widget the pinned role-feature allow-list lets a plain account add. */
const WIDGET = 'activity'

type Dash = { id: number; uuid: string }

let admin: APIRequestContext
let owner: Principal | null = null
let bob: Principal | null = null
let priorAllowUserDash = true

/** Dashboards to remove in afterAll, tolerating ones a test already deleted. */
const created: Array<{ api: APIRequestContext; id: number }> = []

/**
 * Create a dashboard as `api`, optionally under a parent.
 *
 * @param api the owner's context.
 * @param label a readable suffix.
 * @param parentUuid the parent, for a child dashboard.
 * @return its id and uuid.
 */
async function createDashboard(
	api: APIRequestContext,
	label: string,
	parentUuid?: string,
): Promise<Dash> {
	const res = await api.post(`${API}/dashboard`, {
		data: parentUuid
			? { name: `${STAMP} ${label}`, parentUuid }
			: { name: `${STAMP} ${label}` },
	})
	expect(res.status(), await res.text()).toBe(201)
	const dash = (await res.json()).dashboard
	const out = { id: Number(dash.id), uuid: String(dash.uuid) }
	created.push({ api, id: out.id })
	await clearSeededWidgets(api, out.id)
	return out
}

/**
 * Put one widget on a dashboard.
 *
 * @param api the owner's context.
 * @param dashboardId the dashboard.
 * @param gridY the row, so several placements do not collide.
 * @return the placement id.
 */
async function addPlacement(
	api: APIRequestContext,
	dashboardId: number,
	gridY = 0,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: { widgetId: WIDGET, gridX: 0, gridY, gridWidth: 4, gridHeight: 2 },
	})
	expect(res.status(), await res.text()).toBe(201)
	return Number((await res.json()).id)
}

/**
 * The placement ids a dashboard shows its owner.
 *
 * @param api the owner's context.
 * @param id the dashboard.
 * @return the ids.
 */
async function placementIds(api: APIRequestContext, id: number): Promise<number[]> {
	const res = await api.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	return ((await res.json()).placements as Array<{ id: number }>).map((p) =>
		Number(p.id),
	)
}

/**
 * Share a dashboard with a user at view_only (not `full`, so a user deletion
 * deletes rather than transfers it).
 *
 * @param api the owner's context.
 * @param id the dashboard.
 * @param username the recipient.
 */
async function shareWithUser(
	api: APIRequestContext,
	id: number,
	username: string,
): Promise<void> {
	await publishForRecipients(api, Number(id))
	const res = await api.post(`${API}/dashboard/${id}/shares`, {
		data: {
			shareType: 'user',
			shareWith: username,
			permissionLevel: 'view_only',
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
}

/**
 * The dashboard ids a user sees, shares folded in.
 *
 * @param api the user's context.
 * @return the ids.
 */
async function visibleIds(api: APIRequestContext): Promise<number[]> {
	const res = await api.get(`${API}/dashboards/visible`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const items = (body.items ?? (Array.isArray(body) ? body : [])) as Array<{
		id: number
	}>
	return items.map((d) => Number(d.id))
}

/**
 * Live count of rows a category would purge, from a rolled-back dry run.
 *
 * @param category the cleanup category.
 * @return the count.
 */
async function orphanCount(category: string): Promise<number> {
	const res = await admin.post(PURGE, {
		data: { categories: [category], dryRun: true },
	})
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	expect(body.dryRun).toBe(true)
	return Number(body.purgedByCategory[category])
}

/**
 * Create a public share and return its token.
 *
 * @param api the owner's context.
 * @param uuid the dashboard.
 * @return the token.
 */
async function publicShare(api: APIRequestContext, uuid: string): Promise<string> {
	const res = await api.post(`${API}/dashboards/${uuid}/public-share`, {
		data: {},
	})
	expect(res.status(), await res.text()).toBe(201)
	return String((await res.json()).token)
}

/**
 * The status a visitor gets for a public-share token.
 *
 * @param token the token.
 * @return the HTTP status of its data endpoint.
 */
async function publicShareStatus(token: string): Promise<number> {
	const res = await admin.get(`/index.php/apps/launchpad/s/${token}/data`)
	return res.status()
}

/**
 * Create a Nextcloud account with a known password.
 *
 * @param username the uid.
 * @param password the password.
 */
async function provision(username: string, password: string): Promise<void> {
	const res = await admin.post('/ocs/v1.php/cloud/users', {
		form: { userid: username, password },
	})
	expect(res.ok(), `could not provision ${username}: ${res.status()}`).toBe(true)
}

/**
 * Delete a Nextcloud account through the provisioning API, as an admin does.
 *
 * @param username the uid.
 */
async function deleteAccount(username: string): Promise<void> {
	const res = await admin.delete(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}`,
	)
	expect(res.status(), await res.text()).toBe(200)
}

test.describe.configure({ mode: 'serial' })

test.describe('dashboard cascade events', () => {
	let restoreWidgetRestriction: (() => Promise<void>) | null = null

	test.beforeAll(async () => {
		admin = await adminContext()
		await assertActingAs(admin, ADMIN_USER)

		const before = await admin.get(SETTINGS)
		expect(before.status(), await before.text()).toBe(200)
		priorAllowUserDash = (await before.json()).allowUserDashboards === true
		const enable = await admin.put(SETTINGS, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
		restoreWidgetRestriction = await ensureDefaultWidgetRestriction()

		owner = await createPrincipal(admin, 'e2e-cascade-owner')
		bob = await createPrincipal(admin, 'e2e-cascade-bob')
		await assertActingAs(owner.api, owner.username)
		await assertActingAs(bob.api, bob.username)
	})

	test.afterAll(async () => {
		await restoreWidgetRestriction?.()
	})

	test.afterAll(async () => {
		// Dashboards of a deleted account went with it; their context is
		// disposed, so a failed delete here is expected and ignored.
		for (const { api, id } of created.reverse()) {
			try {
				await api.delete(`${API}/dashboard/${id}?cascade=true`)
			} catch {
				// already gone with its owner
			}
		}
		await removePrincipal(admin, owner)
		await removePrincipal(admin, bob)
		if (priorAllowUserDash === false) {
			await admin.put(SETTINGS, { data: { allowUserDash: false } })
		}
		await admin.dispose()
	})

	test('deleting a dashboard removes its placements and leaves other dashboards alone', async () => {
		// @e2e dashboard-cascade-events::widget-placements-are-deleted-on-dashboard-delete
		const api = owner!.api
		const doomed = await createDashboard(api, 'placements doomed')
		const kept = await createDashboard(api, 'placements kept')
		const doomedPlacements = []
		for (let i = 0; i < 5; i++) {
			doomedPlacements.push(await addPlacement(api, doomed.id, i * 2))
		}
		const keptPlacement = await addPlacement(api, kept.id)

		// CONTROL: the five placements exist before the delete.
		expect((await placementIds(api, doomed.id)).sort()).toEqual(
			[...doomedPlacements].sort(),
		)
		const orphansBefore = await orphanCount('orphaned_widget_placements')

		const res = await api.delete(`${API}/dashboard/${doomed.id}`)
		expect(res.status(), await res.text()).toBe(200)
		expect((await api.get(`${API}/dashboard/${doomed.id}`)).status()).toBe(404)

		// None of the five placements stayed behind without their dashboard.
		expect(await orphanCount('orphaned_widget_placements')).toBe(orphansBefore)
		// The other dashboard keeps its placement.
		expect(await placementIds(api, kept.id)).toEqual([keptPlacement])
	})

	test('a delete refused for having children touches nothing', async () => {
		// @e2e dashboard-cascade-events::event-is-not-dispatched-when-validation-rejects-the-delete
		const api = owner!.api
		const parent = await createDashboard(api, 'refused parent')
		const child = await createDashboard(api, 'refused child', parent.uuid)
		const placement = await addPlacement(api, parent.id)
		await shareWithUser(api, parent.id, bob!.username)
		const token = await publicShare(api, parent.uuid)

		// CONTROLS: every piece of dependent data is live before the attempt.
		expect(await publicShareStatus(token)).toBe(200)
		expect(await visibleIds(bob!.api)).toContain(parent.id)

		const res = await api.delete(`${API}/dashboard/${parent.id}`)
		// 409 is the product's validation refusal ("HTTP 400 or equivalent").
		expect(res.status(), await res.text()).toBe(409)
		const body = await res.json()
		expect(body.error).toBe('dashboard_has_children')
		expect(body.childCount).toBe(1)

		// No listener ran: the public share still serves (PublicSharesListener
		// would have revoked it), bob still sees it (DashboardSharesListener
		// would have deleted his share), the placement is still there.
		expect(await publicShareStatus(token)).toBe(200)
		expect(await visibleIds(bob!.api)).toContain(parent.id)
		expect(await placementIds(api, parent.id)).toEqual([placement])
		expect((await api.get(`${API}/dashboard/${child.id}`)).status()).toBe(200)
	})

	test('a non-cascade delete of a parent is refused with 409 before anything changes', async () => {
		// @e2e dashboard-cascade-events::non-cascade-delete-with-children-is-rejected-before-event-dispatch
		const api = owner!.api
		const parent = await createDashboard(api, 'guard parent')
		const child = await createDashboard(api, 'guard child', parent.uuid)
		const parentToken = await publicShare(api, parent.uuid)
		const childToken = await publicShare(api, child.uuid)
		const childPlacement = await addPlacement(api, child.id)

		const res = await api.delete(`${API}/dashboard/${parent.id}`)
		expect(res.status(), await res.text()).toBe(409)
		const body = await res.json()
		expect(JSON.stringify(body)).toMatch(/children/i)

		// Neither parent nor child had its event dispatched.
		expect(await publicShareStatus(parentToken)).toBe(200)
		expect(await publicShareStatus(childToken)).toBe(200)
		expect((await api.get(`${API}/dashboard/${parent.id}`)).status()).toBe(200)
		expect(await placementIds(api, child.id)).toEqual([childPlacement])
	})

	test('a cascade delete runs the full cleanup for every child', async () => {
		// @e2e dashboard-cascade-events::tree-listener-recursively-cascades-to-child-dashboards
		const api = owner!.api
		const parent = await createDashboard(api, 'tree parent')
		const c1 = await createDashboard(api, 'tree child 1', parent.uuid)
		const c2 = await createDashboard(api, 'tree child 2', parent.uuid)
		for (const child of [c1, c2]) {
			await addPlacement(api, child.id, 0)
			await addPlacement(api, child.id, 2)
			// A child's user share is removed only by DashboardSharesListener
			// handling THAT child's event: nothing else deletes share rows.
			await shareWithUser(api, child.id, bob!.username)
		}

		const seen = await visibleIds(bob!.api)
		expect(seen).toContain(c1.id)
		expect(seen).toContain(c2.id)
		const placementsBefore = await orphanCount('orphaned_widget_placements')
		const sharesBefore = await orphanCount('expired_share_tokens')

		const res = await api.delete(`${API}/dashboard/${parent.id}?cascade=true`)
		expect(res.status(), await res.text()).toBe(200)

		for (const d of [parent, c1, c2]) {
			expect((await api.get(`${API}/dashboard/${d.id}`)).status()).toBe(404)
		}
		const after = await visibleIds(bob!.api)
		expect(after).not.toContain(c1.id)
		expect(after).not.toContain(c2.id)
		// Each child's own listener stack ran: no share and no placement of
		// either child is left pointing at a dashboard that is gone.
		expect(await orphanCount('expired_share_tokens')).toBe(sharesBefore)
		expect(await orphanCount('orphaned_widget_placements')).toBe(
			placementsBefore,
		)
	})

	test("deleting a user deletes the dashboards they own and nobody else's", async () => {
		// @e2e dashboard-cascade-events::personal-dashboards-are-deleted-on-user-deletion
		const alice = await createPrincipal(admin, 'e2e-cascade-alice')
		let aliceGone = false
		try {
			await assertActingAs(alice.api, alice.username)
			const owned: Dash[] = []
			for (let i = 1; i <= 3; i++) {
				const d = await createDashboard(alice.api, `alice ${i}`)
				await addPlacement(alice.api, d.id)
				await shareWithUser(alice.api, d.id, bob!.username)
				owned.push(d)
			}
			const bobsOwn = await createDashboard(bob!.api, 'bob own')
			const bobsPlacement = await addPlacement(bob!.api, bobsOwn.id)

			// CONTROL: bob sees all three of alice's dashboards.
			const before = await visibleIds(bob!.api)
			for (const d of owned) {
				expect(before).toContain(d.id)
			}
			const placementsBefore = await orphanCount('orphaned_widget_placements')
			const sharesBefore = await orphanCount('expired_share_tokens')

			await alice.api.dispose()
			await deleteAccount(alice.username)
			aliceGone = true

			const after = await visibleIds(bob!.api)
			for (const d of owned) {
				expect(after).not.toContain(d.id)
			}
			// Their dependent data went with them.
			expect(await orphanCount('orphaned_widget_placements')).toBe(
				placementsBefore,
			)
			expect(await orphanCount('expired_share_tokens')).toBe(sharesBefore)
			// Another user's dashboard is untouched.
			expect(after).toContain(bobsOwn.id)
			expect(await placementIds(bob!.api, bobsOwn.id)).toEqual([bobsPlacement])
		} finally {
			if (!aliceGone) {
				await removePrincipal(admin, alice)
			}
		}
	})

	test('deleting a user or a group removes their role assignments only', async () => {
		// @e2e dashboard-cascade-events::role-assignments-are-removed-on-user-deletion
		// @e2e dashboard-cascade-events::lifecycle-listeners-are-registered-for-nc-events
		const alice = await createPrincipal(admin, 'e2e-cascade-roles-a')
		const carol = await createPrincipal(admin, 'e2e-cascade-roles-c')
		const doomedGroup = await createGroup(
			admin,
			`e2e-cascade-doomed-${Date.now()}`,
		)
		const keptGroup = await createGroup(admin, `e2e-cascade-kept-${Date.now()}`)
		const ids: number[] = []
		let aliceGone = false
		let groupGone = false
		try {
			ids.push(
				await assignRole(admin, { userId: alice.username, role: 'editor' }),
			)
			ids.push(
				await assignRole(admin, { userId: alice.username, role: 'viewer' }),
			)
			const carolRole = await assignRole(admin, {
				userId: carol.username,
				role: 'viewer',
			})
			ids.push(carolRole)
			ids.push(
				await assignRole(admin, { groupId: doomedGroup, role: 'viewer' }),
			)
			const keptGroupRole = await assignRole(admin, {
				groupId: keptGroup,
				role: 'viewer',
			})
			ids.push(keptGroupRole)

			const list = async (): Promise<
				Array<{ id: number; userId: string | null; groupId: string | null }>
			> => {
				const res = await admin.get(`${API}/admin/roles`)
				expect(res.status(), await res.text()).toBe(200)
				return res.json()
			}
			// CONTROL: both of alice's rows and the group's row exist.
			const before = await list()
			expect(before.filter((r) => r.userId === alice.username)).toHaveLength(2)
			expect(before.filter((r) => r.groupId === doomedGroup)).toHaveLength(1)

			await alice.api.dispose()
			await deleteAccount(alice.username)
			aliceGone = true
			const del = await admin.delete(
				`/ocs/v1.php/cloud/groups/${encodeURIComponent(doomedGroup)}`,
			)
			expect(del.status(), await del.text()).toBe(200)
			groupGone = true

			const after = await list()
			// UserDeletedListener ran: alice's two rows are gone.
			expect(after.filter((r) => r.userId === alice.username)).toEqual([])
			// GroupDeletedListener ran: the group's row is gone.
			expect(after.filter((r) => r.groupId === doomedGroup)).toEqual([])
			// Everyone else's stay.
			expect(after.map((r) => Number(r.id))).toContain(carolRole)
			expect(after.map((r) => Number(r.id))).toContain(keptGroupRole)
		} finally {
			for (const id of ids) {
				await removeRole(admin, id).catch(() => undefined)
			}
			if (!aliceGone) {
				await removePrincipal(admin, alice)
			}
			if (!groupGone) {
				await removeGroup(admin, doomedGroup)
			}
			await removeGroup(admin, keptGroup)
			await removePrincipal(admin, carol)
		}
	})

	test("a deleted user's analytics opt-out does not survive for a new account with the same id", async () => {
		// @e2e dashboard-cascade-events::analytics-opt-out-preference-is-removed-on-user-deletion
		const username = `e2e-cascade-optout-${Date.now()}`
		const password = `Probe-${Math.random().toString(36).slice(2)}A1!`
		await provision(username, password)
		let api = await contextFor(username, password)
		let exists = true
		try {
			await assertActingAs(api, username)
			const set = await api.put(`${API}/analytics/me`, {
				data: { optedOut: true },
			})
			expect(set.status(), await set.text()).toBe(200)
			const stored = await api.get(`${API}/analytics/me`)
			expect((await stored.json()).optedOut).toBe(true)

			await api.dispose()
			await deleteAccount(username)
			exists = false

			// A new account that receives the same id starts opted in.
			await provision(username, password)
			exists = true
			api = await contextFor(username, password)
			await assertActingAs(api, username)
			const fresh = await api.get(`${API}/analytics/me`)
			expect(fresh.status(), await fresh.text()).toBe(200)
			expect((await fresh.json()).optedOut).toBe(false)
		} finally {
			await api.dispose().catch(() => undefined)
			if (exists) {
				await admin.delete(
					`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}`,
				)
			}
		}
	})
})
