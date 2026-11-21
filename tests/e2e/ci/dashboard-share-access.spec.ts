/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What a share lets a recipient do, and what stays the owner's alone
 * (dashboard-sharing REQ-SHARE-003, REQ-SHARE-005, REQ-SHARE-007,
 * REQ-SHARE-010), probed over HTTP with real, least-privileged accounts.
 *
 * These scenarios used to carry exclusions saying owner-only enforcement "has
 * no distinct UI state". That is true and beside the point: every one of them
 * is a statement about what the SERVER answers a second, logged-in user, and
 * that is exactly what an HTTP probe with that user's own credentials
 * measures. Each refusal below is probed by a plain account that holds the
 * strongest share the scenario allows, the owner re-reads afterwards to show
 * nothing was written, and the owner (also a plain account) succeeds at the
 * same call in the same test so a broken endpoint cannot pass as a refusal.
 *
 * Accounts come from `support/principals.ts` (basic auth, identity checked
 * with `assertActingAs`); dashboards are created by the owner herself, so the
 * owner is never an administrator and no admin bypass can colour a result.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from '../support/principals.ts'
import type { OwnDashboard, SharingSettings } from '../support/shareApi.ts'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from '../fixtures/role-feature-permissions.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from '../support/principals.ts'
import {
	addWidgets,
	createOwnDashboard,
	listShares,
	orphanCount,
	readSharingSettings,
	share,
	shareTuples,
	writeSharingSettings,
} from '../support/shareApi.ts'

const STAMP = Date.now()

let admin: APIRequestContext
let prior: SharingSettings | null = null
let alice: Principal | null = null
let bob: Principal | null = null
let carol: Principal | null = null
let marketing: string | null = null

let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	admin = await adminContext()
	prior = await readSharingSettings(admin)
	// The owners build their own dashboards, and no sharing limit may be the
	// reason a share below is refused.
	await writeSharingSettings(admin, {
		allowUserDashboards: true,
		sharingAllowedGroups: [],
		sharingRecipientScope: 'everyone',
	})
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()

	alice = await createPrincipal(admin, 'e2e-sacc-alice')
	bob = await createPrincipal(admin, 'e2e-sacc-bob')
	carol = await createPrincipal(admin, 'e2e-sacc-carol')
	for (const p of [alice, bob, carol]) {
		await assertActingAs(p.api, p.username)
	}
	marketing = await createGroup(admin, `e2e-sacc-marketing-${STAMP}`)
	await addToGroup(admin, alice.username, marketing)
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

test.afterAll(async () => {
	// Deleting an owner deletes the dashboards nobody holds `full` on, and
	// hands the rest on; removing every account here leaves nothing behind.
	await removePrincipal(admin, alice)
	await removePrincipal(admin, bob)
	await removePrincipal(admin, carol)
	await removeGroup(admin, marketing)
	if (prior !== null) {
		await writeSharingSettings(admin, prior)
	}
	await admin.dispose()
})

/** A fresh dashboard owned by `owner`, named for the test that made it. */
async function dashboardOf(owner: Principal, label: string): Promise<OwnDashboard> {
	return createOwnDashboard(owner.api, `E2E access ${label} ${STAMP}`)
}

/** GET /api/dashboard/{id} as a principal. */
async function readDashboard(p: Principal, id: number) {
	return p.api.get(`${API}/dashboard/${id}`)
}

/** `id -> isActive` for the caller's OWN dashboards (`GET /api/dashboards`). */
async function activeFlags(p: Principal): Promise<Record<string, unknown>> {
	const res = await p.api.get(`${API}/dashboards`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const items: Array<Record<string, unknown>> = Array.isArray(body)
		? body
		: (body.items ?? [])
	return Object.fromEntries(items.map((d) => [String(d.id), d.isActive]))
}

test.describe('REQ-SHARE-003 loading a shared dashboard', () => {
	// @e2e dashboard-sharing::recipient-loads-a-shared-dashboard
	test('a view-only recipient loads the dashboard with all its placements and its own level', async () => {
		const dash = await dashboardOf(alice!, 'load')
		await addWidgets(alice!.api, dash.id, 4)
		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')
		// A new dashboard starts with the default widget bundle, so the four
		// added here come on top of what the owner herself reads.
		const asOwner = await readDashboard(alice!, dash.id)
		expect(asOwner.status(), await asOwner.text()).toBe(200)
		const ownerIds = ((await asOwner.json()).placements as Array<{ id: number }>)
			.map((p) => Number(p.id))
			.sort((a, b) => a - b)
		expect(
			ownerIds.length,
			'CONTROL: the four added widgets are on the dashboard',
		).toBeGreaterThanOrEqual(4)

		const res = await readDashboard(bob!, dash.id)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()

		expect(Number(body.dashboard?.id), 'the body must carry the dashboard').toBe(
			dash.id,
		)
		expect(body.dashboard?.name).toBe(dash.name)
		expect(
			(body.placements as Array<{ id: number }>)
				.map((p) => Number(p.id))
				.sort((a, b) => a - b),
			'every placement the owner has must come with it',
		).toEqual(ownerIds)
		expect(
			body.permissionLevel,
			"the level is the share's, not the owner's",
		).toBe('view_only')
		expect(body.isOwner).toBe(false)
		expect(body.sharedBy).toBe(alice!.username)
	})

	// @e2e dashboard-sharing::non-recipient-is-denied
	test('a logged-in user the dashboard is not shared with is refused with 403', async () => {
		const dash = await dashboardOf(alice!, 'deny')
		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')

		// The allowed principal, in the same test: the endpoint does serve
		// this dashboard to someone it is shared with.
		const allowed = await readDashboard(bob!, dash.id)
		expect(allowed.status(), await allowed.text()).toBe(200)

		const refused = await readDashboard(carol!, dash.id)
		expect(refused.status(), await refused.text()).toBe(403)
		const text = await refused.text()
		expect(text, 'a refusal must not leak the dashboard').not.toContain(
			dash.name,
		)
	})
})

test.describe('REQ-SHARE-005 owner-only metadata and lifecycle', () => {
	// @e2e dashboard-sharing::full-level-recipient-cannot-rename
	test('a full-level recipient cannot rename the dashboard; its owner can', async () => {
		const dash = await dashboardOf(alice!, 'rename')
		await share(alice!.api, dash.id, 'user', bob!.username, 'full')

		// CONTROL: bob really holds `full`, the strongest level there is, so
		// the refusal below is about renaming being the owner's alone.
		const asBob = await readDashboard(bob!, dash.id)
		expect(asBob.status(), await asBob.text()).toBe(200)
		expect((await asBob.json()).permissionLevel).toBe('full')

		const refused = await bob!.api.put(`${API}/dashboard/${dash.id}`, {
			data: { name: 'New Name' },
		})
		expect(refused.status(), await refused.text()).toBe(403)

		const after = await readDashboard(alice!, dash.id)
		expect(after.status(), await after.text()).toBe(200)
		expect(
			(await after.json()).dashboard?.name,
			'a refused rename must leave the name as it was',
		).toBe(dash.name)

		// The owner, a plain account too, may.
		const renamed = `${dash.name} renamed`
		const allowed = await alice!.api.put(`${API}/dashboard/${dash.id}`, {
			data: { name: renamed },
		})
		expect(allowed.status(), await allowed.text()).toBeLessThan(300)
		const reread = await readDashboard(alice!, dash.id)
		expect((await reread.json()).dashboard?.name).toBe(renamed)
	})

	// @e2e dashboard-sharing::full-level-recipient-cannot-delete
	test('a full-level recipient cannot delete the dashboard; its owner can', async () => {
		const dash = await dashboardOf(alice!, 'delete')
		await share(alice!.api, dash.id, 'user', bob!.username, 'full')

		const asBob = await readDashboard(bob!, dash.id)
		expect((await asBob.json()).permissionLevel).toBe('full')

		// SUSPECTED BUG: the spec says 403. `DashboardService::deleteDashboard()`
		// throws a plain `Exception('Access denied')` for a non-owner
		// (lib/Service/DashboardService.php:1020-1022), which
		// `DashboardApiController::delete()` hands to `ResponseHelper::error()`
		// with its default 400 (lib/Controller/ResponseHelper.php:100-113).
		const refused = await bob!.api.delete(`${API}/dashboard/${dash.id}`)
		expect(refused.status(), await refused.text()).toBe(403)

		const still = await readDashboard(alice!, dash.id)
		expect(
			still.status(),
			'a refused delete must leave the dashboard in place',
		).toBe(200)

		const allowed = await alice!.api.delete(`${API}/dashboard/${dash.id}`)
		expect(allowed.status(), await allowed.text()).toBeLessThan(300)
		const gone = await readDashboard(alice!, dash.id)
		expect(gone.status()).toBe(404)
	})

	// @e2e dashboard-sharing::activation-by-recipient-is-a-soft-success
	test('a recipient activating a shared dashboard gets 200 and no active flag moves', async () => {
		const dash = await dashboardOf(alice!, 'activate')
		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')

		const aliceBefore = await activeFlags(alice!)
		const bobBefore = await activeFlags(bob!)
		// CONTROL: the owner's row is the active one, so a flag that moved
		// would show.
		expect(
			aliceBefore[String(dash.id)],
			"CONTROL: the owner's new dashboard is her active one",
		).toBeTruthy()

		const res = await bob!.api.post(`${API}/dashboard/${dash.id}/activate`)
		expect(res.status(), await res.text()).toBe(200)
		expect(Number((await res.json()).dashboard?.id)).toBe(dash.id)

		expect(
			await activeFlags(alice!),
			"the owner's active flags must not change",
		).toEqual(aliceBefore)
		expect(
			await activeFlags(bob!),
			"the recipient's own active flags must not change",
		).toEqual(bobBefore)
	})
})

test.describe('REQ-SHARE-007 cascade on dashboard delete', () => {
	// @e2e dashboard-sharing::shares-are-removed-when-the-dashboard-is-deleted
	test('deleting a dashboard with three shares leaves no share row behind', async () => {
		const dash = await dashboardOf(alice!, 'cascade')
		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')
		await share(alice!.api, dash.id, 'user', carol!.username, 'full')
		await share(alice!.api, dash.id, 'group', marketing!, 'view_only')
		expect(
			await listShares(alice!.api, dash.id),
			'CONTROL: three shares exist',
		).toHaveLength(3)

		const orphansBefore = await orphanCount(admin, 'expired_share_tokens')

		const deleted = await alice!.api.delete(`${API}/dashboard/${dash.id}`)
		expect(deleted.status(), await deleted.text()).toBeLessThan(300)

		// The dashboard row is gone.
		expect((await readDashboard(alice!, dash.id)).status()).toBe(404)
		// The share API has nothing to list for it.
		const shareList = await alice!.api.get(`${API}/dashboard/${dash.id}/shares`)
		expect(shareList.status()).toBe(404)
		// …and the three rows went with it rather than becoming orphans. A
		// share list cannot tell the two apart; the orphan count can.
		expect(
			await orphanCount(admin, 'expired_share_tokens'),
			'deleting the dashboard left share rows pointing at nothing',
		).toBe(orphansBefore)
	})
})

test.describe('REQ-SHARE-010 revoke every share granted to one recipient', () => {
	// @e2e dashboard-sharing::owner-revokes-a-recipient-across-all-their-dashboards
	test('the owner revokes bob on her two dashboards and nothing on a dashboard she only receives', async () => {
		const five = await dashboardOf(alice!, 'revoke-5')
		const seven = await dashboardOf(alice!, 'revoke-7')
		await share(alice!.api, five.id, 'user', bob!.username, 'view_only')
		await share(alice!.api, five.id, 'user', carol!.username, 'view_only')
		await share(alice!.api, seven.id, 'user', bob!.username, 'full')

		// Dashboard 9 is carol's, shared with alice's group at `full` and,
		// so the rule has something to get wrong, with bob as well.
		const nine = await dashboardOf(carol!, 'revoke-9')
		await share(carol!.api, nine.id, 'group', marketing!, 'full')
		await share(carol!.api, nine.id, 'user', bob!.username, 'view_only')
		const nineBefore = shareTuples(await listShares(carol!.api, nine.id))

		// CONTROL: alice reaches 9 at full through her group, which is the
		// strongest claim she could have to touching its shares.
		const aliceOnNine = await readDashboard(alice!, nine.id)
		expect((await aliceOnNine.json()).permissionLevel).toBe('full')

		const res = await alice!.api.delete(
			`${API}/sharees/user/${encodeURIComponent(bob!.username)}`,
		)
		expect(res.status(), await res.text()).toBe(200)
		expect(
			(await res.json()).deleted,
			'the count of rows actually removed',
		).toBe(2)

		expect(shareTuples(await listShares(alice!.api, five.id))).toEqual([
			`user:${carol!.username}:view_only`,
		])
		expect(await listShares(alice!.api, seven.id)).toHaveLength(0)
		expect(
			shareTuples(await listShares(carol!.api, nine.id)),
			"a dashboard alice does not own keeps every share, bob's included",
		).toEqual(nineBefore)

		// And bob, who lost both, no longer reaches 5 or 7 but still reaches 9.
		expect((await readDashboard(bob!, five.id)).status()).not.toBe(200)
		expect((await readDashboard(bob!, seven.id)).status()).not.toBe(200)
		expect((await readDashboard(bob!, nine.id)).status()).toBe(200)
	})
})
