/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What happens to shared dashboards when an account is deleted
 * (dashboard-sharing REQ-SHARE-012 and REQ-SHARE-013).
 *
 * These scenarios used to be excluded as "UserDeletedEvent-driven, no
 * interactive UI surface". But nothing about them is out of reach: an
 * administrator deleting an account over the OCS provisioning API is the
 * real trigger (Nextcloud dispatches `UserDeletedEvent` inside that request,
 * and `UserDeletedListener` handles it before the response returns), and
 * every outcome is something a surviving user can read back:
 *
 *   - who owns the dashboard now: the new owner's `GET /api/dashboard/{id}`
 *     says `isOwner: true`, and a remaining recipient's says `sharedBy`;
 *   - which share rows survived: the new owner can list them;
 *   - whether the dashboard was deleted: a remaining recipient who could
 *     read it before gets 404 after, and the orphan counters of the cleanup
 *     dry run (`support/shareApi.ts` `orphanCount`) show its share rows and
 *     placements went with it rather than being left behind;
 *   - the ownership-transfer notification: the new owner's feed.
 *
 * Each test provisions its own owner and recipients, because each deletes
 * some of them. Uids are prefixed so their alphabetical order is chosen, not
 * left to timestamps, wherever the selection rule depends on it.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from '../support/principals.ts'
import type { OwnDashboard, SharingSettings } from '../support/shareApi.ts'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from '../fixtures/role-feature-permissions.ts'
import { transferNotifications } from '../support/notifications.ts'
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
	deleteAccount,
	listShares,
	nextSecond,
	orphanCount,
	readSharingSettings,
	share,
	shareTuples,
	writeSharingSettings,
} from '../support/shareApi.ts'

const STAMP = Date.now()

// Each test provisions up to six accounts and deletes one, at several
// seconds per account on the stack, so the 60 s default is too short.
test.describe.configure({ timeout: 180_000 })

let admin: APIRequestContext
let prior: SharingSettings | null = null

/** Every account and group a test made, so `afterAll` can remove what is left. */
const principals: Principal[] = []
const groups: string[] = []

let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	admin = await adminContext()
	prior = await readSharingSettings(admin)
	await writeSharingSettings(admin, {
		allowUserDashboards: true,
		sharingAllowedGroups: [],
		sharingRecipientScope: 'everyone',
	})
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

test.afterAll(async () => {
	// removePrincipal tolerates an account a test already deleted.
	for (const p of principals) {
		await removePrincipal(admin, p)
	}
	for (const gid of groups) {
		await removeGroup(admin, gid)
	}
	if (prior !== null) {
		await writeSharingSettings(admin, prior)
	}
	await admin.dispose()
})

/** A plain account, registered for cleanup and checked to be who it says. */
async function person(prefix: string): Promise<Principal> {
	const p = await createPrincipal(admin, prefix)
	principals.push(p)
	await assertActingAs(p.api, p.username)
	return p
}

/** A group with the given members, registered for cleanup. */
async function groupOf(gid: string, members: Principal[]): Promise<string> {
	groups.push(await createGroup(admin, gid))
	for (const m of members) {
		await addToGroup(admin, m.username, gid)
	}
	return gid
}

/** GET /api/dashboard/{id} as a principal. */
async function readDashboard(p: Principal, id: number) {
	return p.api.get(`${API}/dashboard/${id}`)
}

/** The owner-side view of a dashboard: status, isOwner, sharedBy, level. */
async function viewOf(p: Principal, id: number) {
	const res = await readDashboard(p, id)
	const body = res.status() === 200 ? await res.json() : {}
	return {
		status: res.status(),
		isOwner: body.isOwner,
		sharedBy: body.sharedBy,
		permissionLevel: body.permissionLevel,
		ownerId: body.dashboard?.userId,
	}
}

/**
 * Delete the owner's account; their context is disposed first. The owner
 * leaves the cleanup list, because disposing a context a second time throws
 * and would fail the afterAll.
 */
async function deleteOwner(owner: Principal): Promise<void> {
	await owner.api.dispose()
	await deleteAccount(admin, owner.username)
	principals.splice(principals.indexOf(owner), 1)
}

/** A dashboard owned by `owner`. */
async function dashboardOf(owner: Principal, label: string): Promise<OwnDashboard> {
	return createOwnDashboard(owner.api, `E2E deletion ${label} ${STAMP}`)
}

test.describe('REQ-SHARE-012 cascade and retention on user deletion', () => {
	// @e2e dashboard-sharing::owner-deletion-preserves-dashboard-with-a-full-level-user-share
	test('deleting the owner hands the dashboard to the full-level recipient and keeps the other share', async () => {
		const alice = await person('e2e-udel1-alice')
		const bob = await person('e2e-udel1-bob')
		const carol = await person('e2e-udel1-carol')
		const dash = await dashboardOf(alice, 'user-full')
		await share(alice.api, dash.id, 'user', bob.username, 'full')
		await share(alice.api, dash.id, 'user', carol.username, 'view_only')

		await deleteOwner(alice)

		const asBob = await viewOf(bob, dash.id)
		expect(asBob.status, 'the dashboard still exists').toBe(200)
		expect(asBob.ownerId, 'dashboards.user_id is now bob').toBe(bob.username)
		expect(asBob.isOwner).toBe(true)

		// bob owns it, so he may list its shares: his own row is gone,
		// carol's is still there.
		expect(shareTuples(await listShares(bob.api, dash.id))).toEqual([
			`user:${carol.username}:view_only`,
		])
		const asCarol = await viewOf(carol, dash.id)
		expect(asCarol.status).toBe(200)
		expect(asCarol.permissionLevel).toBe('view_only')
		expect(asCarol.sharedBy).toBe(bob.username)
	})

	// @e2e dashboard-sharing::owner-deletion-preserves-dashboard-via-group-full-level-share
	test('deleting the owner hands the dashboard to the first member of a full-level group, and the group share stays', async () => {
		const alice = await person('e2e-udel2-alice')
		const dave = await person('e2e-udel2-a-dave')
		const eve = await person('e2e-udel2-b-eve')
		const leads = await groupOf(`e2e-udel2-leads-${STAMP}`, [eve, dave])
		const dash = await dashboardOf(alice, 'group-full')
		await share(alice.api, dash.id, 'group', leads, 'full')

		await deleteOwner(alice)

		const asDave = await viewOf(dave, dash.id)
		expect(asDave.status, 'the dashboard still exists').toBe(200)
		expect(asDave.ownerId, 'the alphabetically-first member owns it').toBe(
			dave.username,
		)
		expect(asDave.isOwner).toBe(true)
		expect(
			shareTuples(await listShares(dave.api, dash.id)),
			'a group share is not consumed by the transfer',
		).toEqual([`group:${leads}:full`])
		// eve still reaches it through the group, now shared by dave.
		const asEve = await viewOf(eve, dash.id)
		expect(asEve.status).toBe(200)
		expect(asEve.sharedBy).toBe(dave.username)
	})

	// @e2e dashboard-sharing::owner-deletion-deletes-dashboard-when-no-admin-pool
	test('deleting the owner of a dashboard nobody holds full on deletes it, its placements and its share', async () => {
		const alice = await person('e2e-udel3-alice')
		const bob = await person('e2e-udel3-bob')
		const dash = await dashboardOf(alice, 'no-pool')
		await addWidgets(alice.api, dash.id, 2)
		await share(alice.api, dash.id, 'user', bob.username, 'view_only')
		// CONTROL: bob reads it, so his 404 afterwards means it is gone.
		expect((await viewOf(bob, dash.id)).status).toBe(200)

		const orphanShares = await orphanCount(admin, 'expired_share_tokens')
		const orphanPlacements = await orphanCount(
			admin,
			'orphaned_widget_placements',
		)

		await deleteOwner(alice)

		expect(
			(await viewOf(bob, dash.id)).status,
			'the dashboard must be deleted',
		).toBe(404)
		expect(
			await orphanCount(admin, 'orphaned_widget_placements'),
			'its placements must be deleted with it, not left behind',
		).toBe(orphanPlacements)
		expect(
			await orphanCount(admin, 'expired_share_tokens'),
			'its share row to bob must be deleted with it, not left behind',
		).toBe(orphanShares)
	})

	// @e2e dashboard-sharing::recipient-deletion-cleans-up-that-users-shares
	test('deleting a recipient removes their three share rows and nothing else', async () => {
		const owner = await person('e2e-udel4-owner')
		const bob = await person('e2e-udel4-bob')
		const carol = await person('e2e-udel4-carol')
		const dashboards = [
			await dashboardOf(owner, 'recipient-5'),
			await dashboardOf(owner, 'recipient-7'),
			await dashboardOf(owner, 'recipient-9'),
		]
		for (const dash of dashboards) {
			await share(owner.api, dash.id, 'user', bob.username, 'view_only')
			await share(owner.api, dash.id, 'user', carol.username, 'full')
		}
		const orphanShares = await orphanCount(admin, 'expired_share_tokens')

		await deleteOwner(bob)

		for (const dash of dashboards) {
			const view = await viewOf(owner, dash.id)
			expect(view.status, 'the dashboards themselves are untouched').toBe(200)
			expect(view.ownerId).toBe(owner.username)
			expect(
				shareTuples(await listShares(owner.api, dash.id)),
				"only bob's row goes; carol's stays as it was",
			).toEqual([`user:${carol.username}:full`])
		}
		expect(
			await orphanCount(admin, 'expired_share_tokens'),
			'no share row is left dangling',
		).toBe(orphanShares)
	})

	// @e2e dashboard-sharing::group-share-where-every-member-is-gone-falls-through-to-delete
	test('a full-level group whose every member was deleted is no admin pool, so the dashboard is deleted', async () => {
		const alice = await person('e2e-udel5-alice')
		const ghost = await person('e2e-udel5-ghost')
		const watcher = await person('e2e-udel5-watcher')
		const ghosts = await groupOf(`e2e-udel5-ghosts-${STAMP}`, [ghost])
		const dash = await dashboardOf(alice, 'ghosts')
		await share(alice.api, dash.id, 'group', ghosts, 'full')
		// A view-only watcher, who is no admin pool, to observe the outcome.
		await share(alice.api, dash.id, 'user', watcher.username, 'view_only')
		expect((await viewOf(watcher, dash.id)).status).toBe(200)

		await deleteOwner(ghost)
		await deleteOwner(alice)

		expect(
			(await viewOf(watcher, dash.id)).status,
			'with every member of the full-level group gone the dashboard must be deleted',
		).toBe(404)
	})
})

test.describe('REQ-SHARE-013 deterministic new-owner selection', () => {
	// @e2e dashboard-sharing::user-share-earliest-created-wins
	test('among full-level user shares the earliest created wins, not the first alphabetically', async () => {
		const alice = await person('e2e-udel6-alice')
		const bob = await person('e2e-udel6-b-bob')
		const carol = await person('e2e-udel6-c-carol')
		const dave = await person('e2e-udel6-d-dave')
		const dash = await dashboardOf(alice, 'earliest')
		// dave first, then bob, then carol: alphabetical order would pick bob.
		await share(alice.api, dash.id, 'user', dave.username, 'full')
		await nextSecond()
		await share(alice.api, dash.id, 'user', bob.username, 'full')
		await nextSecond()
		await share(alice.api, dash.id, 'user', carol.username, 'full')

		await deleteOwner(alice)

		const asDave = await viewOf(dave, dash.id)
		expect(asDave.ownerId, 'the earliest-created full share wins').toBe(
			dave.username,
		)
		expect(asDave.isOwner).toBe(true)
		for (const other of [bob, carol]) {
			const view = await viewOf(other, dash.id)
			expect(view.isOwner).toBe(false)
			expect(view.permissionLevel).toBe('full')
		}
	})

	// @e2e dashboard-sharing::falls-back-to-alphabetic-group-member-when-no-user-shares
	test('with only group shares, the first member of the alphabetically-first group wins', async () => {
		const alice = await person('e2e-udel7-alice')
		// zeta holds the alphabetically-first uid of all five, so a rule that
		// took the first member across every group would pick mark.
		const mark = await person('e2e-udel7-a-mark')
		const lily = await person('e2e-udel7-l-lily')
		const alex = await person('e2e-udel7-b-alex')
		const bob = await person('e2e-udel7-c-bob')
		const victor = await person('e2e-udel7-v-victor')
		// zeta is created and shared first, so creation order would pick it.
		const zeta = await groupOf(`e2e-udel7-zeta-team-${STAMP}`, [mark, lily])
		const alpha = await groupOf(`e2e-udel7-alpha-team-${STAMP}`, [
			victor,
			bob,
			alex,
		])
		const dash = await dashboardOf(alice, 'groups')
		await share(alice.api, dash.id, 'group', zeta, 'full')
		await nextSecond()
		await share(alice.api, dash.id, 'group', alpha, 'full')

		await deleteOwner(alice)

		const asAlex = await viewOf(alex, dash.id)
		expect(
			asAlex.ownerId,
			'alex: the alphabetically-first member of the alphabetically-first group',
		).toBe(alex.username)
		expect(asAlex.isOwner).toBe(true)
		expect((await viewOf(mark, dash.id)).isOwner).toBe(false)
	})

	// @e2e dashboard-sharing::ownership-transfer-notification-is-published-to-the-new-owner-only
	test('the new owner, and only the new owner, is told the dashboard is now theirs', async () => {
		const alice = await person('e2e-udel8-alice')
		const bob = await person('e2e-udel8-bob')
		const carol = await person('e2e-udel8-carol')
		const dash = await dashboardOf(alice, 'transfer-notice')
		await share(alice.api, dash.id, 'user', bob.username, 'full')
		await share(alice.api, dash.id, 'user', carol.username, 'view_only')
		expect(
			await transferNotifications(bob.api, dash.id),
			'CONTROL: none before',
		).toHaveLength(0)

		await deleteOwner(alice)

		expect(
			(await viewOf(bob, dash.id)).ownerId,
			'CONTROL: bob took it over',
		).toBe(bob.username)
		const got = await transferNotifications(bob.api, dash.id)
		expect(got, 'exactly one transfer notification').toHaveLength(1)
		expect(got[0].user).toBe(bob.username)
		expect(got[0].object_type).toBe('dashboard')
		expect(String(got[0].object_id)).toBe(String(dash.id))
		// subjectParameters [dashboardName], as rendered.
		expect(got[0].subjectRich).toBe(`**${dash.name}** is now yours`)
		expect(
			await transferNotifications(carol.api, dash.id),
			'a remaining recipient is not told',
		).toHaveLength(0)
	})
})
