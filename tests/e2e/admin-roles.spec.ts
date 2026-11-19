/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * LaunchPad roles: the role rows themselves, who may write them, how a user's
 * effective role is resolved, and what happens to the rows when a Nextcloud
 * account or group is deleted (REQ-ROLE-004, REQ-ROLE-005, REQ-ROLE-006,
 * REQ-ROLE-009, REQ-ROLE-010, REQ-ROLE-011).
 *
 * What each role then lets its holder DO with dashboards and the admin API is
 * in `admin-roles-enforcement.spec.ts`.
 *
 * 🔴 WHO MAY WRITE A ROLE ROW IS THE FIRST THING THIS FILE PROVES. A role row
 * is a privilege grant: anyone who can create one can make themselves a
 * LaunchPad admin. So the create and delete routes are probed by every kind of
 * non-admin that exists, an ordinary account, an Editor and a Viewer, each
 * also trying to raise themselves. Every refusal is checked for the exact
 * status (403), re-read as the admin to prove no row landed, and followed in
 * the same test by the admin doing the same thing successfully.
 *
 * HOW RESOLUTION IS OBSERVED. `RoleService::getEffectiveRole()` and
 * `getRoleSource()` have no route of their own; `GET /api/me/role` answers
 * both for the caller, so each resolution scenario is a real account with real
 * rows and real group memberships asking that route about itself. Where a
 * scenario says group rows are "not consulted", the group in play carries a
 * role that would WIN if it were consulted, so the answer can only be right if
 * the group really was skipped.
 *
 * Every account, group and role row is created here and removed in
 * `afterAll`, through basic-auth contexts from `support/principals.ts`.
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	addToGroup,
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	contextFor,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
	removeRole,
} from './support/principals.ts'
import {
	accountExists,
	ADMIN_USER,
	GRANTED_AT,
	GRANTED_BY,
	grantRole,
	groupMembers,
	groupsOf,
	listRoles,
	myRole,
	postRole,
	rolesOf,
	SOURCE,
} from './support/roles.ts'

const STAMP = `${Date.now()}`

test.describe('LaunchPad role rows and role resolution', () => {
	let admin: APIRequestContext

	// Every principal, group and role row made here, for afterAll.
	const principals: Principal[] = []
	const groups: string[] = []
	const roleIds: number[] = []

	/** Create a throwaway account and remember it for cleanup. */
	async function principal(prefix: string): Promise<Principal> {
		const p = await createPrincipal(admin, prefix)
		await assertActingAs(p.api, p.username)
		principals.push(p)
		return p
	}

	/** Create a throwaway group and remember it for cleanup. */
	async function group(prefix: string): Promise<string> {
		const id = `${prefix}-${STAMP}-${Math.floor(Math.random() * 10_000)}`
		await createGroup(admin, id)
		groups.push(id)
		return id
	}

	/** Grant a role as the admin and remember the row for cleanup. */
	async function grant(target: {
		userId?: string
		groupId?: string
		role: string
	}): Promise<number> {
		const id = await grantRole(admin, target)
		roleIds.push(id)
		return id
	}

	test.beforeAll(async () => {
		admin = await adminContext()
		await assertActingAs(admin, ADMIN_USER)
	})

	test.afterAll(async () => {
		if (admin === undefined) {
			return
		}
		for (const id of roleIds) {
			await removeRole(admin, id)
		}
		for (const p of principals) {
			await removePrincipal(admin, p)
		}
		for (const id of groups) {
			await removeGroup(admin, id)
		}
		await admin.dispose()
	})

	// ── REQ-ROLE-004: the rows, and who may write them ─────────────────────

	// @e2e admin-roles::role-assignment-is-stored-with-audit-fields
	test('only an NC admin may create or delete a role row, and the row carries its audit fields', async () => {
		const plain = await principal('e2e-role-plain')
		const editor = await principal('e2e-role-editor')
		const viewer = await principal('e2e-role-viewer')
		const target = await principal('e2e-role-target')
		await grant({ userId: editor.username, role: 'editor' })
		await grant({ userId: viewer.username, role: 'viewer' })

		// Every non-admin is refused, for someone else AND for themselves.
		for (const prober of [plain, editor, viewer]) {
			for (const body of [
				{ userId: target.username, role: 'editor' },
				{ userId: prober.username, role: 'admin' },
			]) {
				const res = await postRole(prober.api, body)
				expect(
					res.status(),
					`${prober.username} wrote a role row ${JSON.stringify(body)}: ${await res.text()}`,
				).toBe(403)
			}
			const list = await prober.api.get(`${API}/admin/roles`)
			expect(list.status(), 'a non-admin may not list role rows').toBe(403)
		}

		// Re-read as the admin: nothing landed, for the target or the probers.
		expect(await rolesOf(admin, { userId: target.username })).toEqual([])
		expect(await rolesOf(admin, { userId: plain.username })).toEqual([])
		expect(
			(await rolesOf(admin, { userId: editor.username })).map((r) => r.role),
		).toEqual(['editor'])
		expect(
			(await rolesOf(admin, { userId: viewer.username })).map((r) => r.role),
		).toEqual(['viewer'])

		// The allowed principal does the same thing, and the row carries its audit trail.
		const before = Date.now()
		const res = await postRole(admin, {
			userId: target.username,
			role: 'editor',
		})
		expect(res.status(), await res.text()).toBe(201)
		const row = await res.json()
		roleIds.push(Number(row.id))

		expect(
			Number.isInteger(row.id) && row.id > 0,
			'id is a generated integer',
		).toBe(true)
		expect(row.userId).toBe(target.username)
		expect(row.groupId).toBeNull()
		expect(row.role).toBe('editor')
		expect(row[GRANTED_BY]).toBe(ADMIN_USER)
		const at = Date.parse(String(row[GRANTED_AT]))
		expect(Number.isNaN(at), `unparseable timestamp ${row[GRANTED_AT]}`).toBe(
			false,
		)
		// "now": within a generous window either side of the request (clock skew).
		expect(Math.abs(at - before)).toBeLessThan(5 * 60_000)

		// Persisted, not just echoed: the listing carries the same row.
		const stored = (await rolesOf(admin, { userId: target.username }))[0]
		expect(stored).toMatchObject({
			id: row.id,
			userId: target.username,
			groupId: null,
			role: 'editor',
			[GRANTED_BY]: ADMIN_USER,
		})

		// Deleting a row is the admin's too: refused for a non-admin, row kept.
		const del = await plain.api.delete(`${API}/admin/roles/${row.id}`)
		expect(del.status(), await del.text()).toBe(403)
		expect(await rolesOf(admin, { userId: target.username })).toHaveLength(1)
		const allowed = await admin.delete(`${API}/admin/roles/${row.id}`)
		expect(allowed.status(), await allowed.text()).toBe(204)
		expect(await rolesOf(admin, { userId: target.username })).toEqual([])
	})

	// @e2e admin-roles::group-role-assignment-stores-group-id
	test('a group role row stores the group id and no user id', async () => {
		const groupId = await group('e2e-role-grp')

		const res = await postRole(admin, { groupId, role: 'editor' })
		expect(res.status(), await res.text()).toBe(201)
		const row = await res.json()
		roleIds.push(Number(row.id))

		expect(row.userId).toBeNull()
		expect(row.groupId).toBe(groupId)
		expect(row.role).toBe('editor')
		expect(row[GRANTED_BY]).toBe(ADMIN_USER)
		expect(Number.isNaN(Date.parse(String(row[GRANTED_AT])))).toBe(false)

		expect(await rolesOf(admin, { groupId })).toEqual([
			expect.objectContaining({
				id: row.id,
				userId: null,
				groupId,
				role: 'editor',
				[GRANTED_BY]: ADMIN_USER,
			}),
		])
	})

	// @e2e admin-roles::one-assignment-per-target-and-role
	test('a second row for the same target and role is refused with 409 and not inserted', async () => {
		const bob = await principal('e2e-role-dup')
		const groupId = await group('e2e-role-dupgrp')
		await grant({ userId: bob.username, role: 'editor' })
		await grant({ groupId, role: 'editor' })

		const dupUser = await postRole(admin, {
			userId: bob.username,
			role: 'editor',
		})
		expect(dupUser.status(), await dupUser.text()).toBe(409)
		const dupGroup = await postRole(admin, { groupId, role: 'editor' })
		expect(dupGroup.status(), await dupGroup.text()).toBe(409)

		expect(
			(await rolesOf(admin, { userId: bob.username })).filter(
				(r) => r.role === 'editor',
			),
		).toHaveLength(1)
		expect(
			(await rolesOf(admin, { groupId })).filter((r) => r.role === 'editor'),
		).toHaveLength(1)

		// The uniqueness is per (target, role): a different role is accepted.
		const other = await postRole(admin, { userId: bob.username, role: 'viewer' })
		expect(other.status(), await other.text()).toBe(201)
		roleIds.push(Number((await other.json()).id))
	})

	// @e2e admin-roles::user-and-group-assignments-are-distinct
	test("a user row never shows up among a group's rows, and the group row stands apart", async () => {
		const bob = await principal('e2e-role-distinct')
		const groupId = await group('e2e-role-distinctgrp')
		await addToGroup(admin, bob.username, groupId)
		const userRow = await grant({ userId: bob.username, role: 'editor' })
		const groupRow = await grant({ groupId, role: 'editor' })

		const forGroup = (await listRoles(admin)).filter(
			(r) => r.groupId === groupId && r.role === 'editor',
		)
		expect(forGroup.map((r) => r.id)).toEqual([groupRow])
		expect(forGroup[0].userId).toBeNull()
		expect(forGroup.some((r) => r.id === userRow)).toBe(false)

		const forUser = await rolesOf(admin, { userId: bob.username })
		expect(forUser.map((r) => r.id)).toEqual([userRow])
		expect(forUser[0].groupId).toBeNull()
	})

	// ── REQ-ROLE-005 / 006 / 009: resolution, observed through /api/me/role ─

	// @e2e admin-roles::nc-admin-automatically-has-dashboard-admin-role
	// @e2e admin-roles::nc-admin-queries-their-role
	test('the NC admin is a LaunchPad admin from "nc-admin", with no role row behind it', async () => {
		const { status, body } = await myRole(admin)
		expect(status).toBe(200)
		expect(body).toEqual({ role: 'admin', source: SOURCE.ncAdmin })

		// Implicit grant: no row exists for the admin account.
		expect(await rolesOf(admin, { userId: ADMIN_USER })).toEqual([])
	})

	// @e2e admin-roles::nc-admin-overrides-all-assignments
	// @e2e admin-roles::nc-admin-always-has-admin-role
	test('a viewer row on the NC admin changes nothing: still admin, from "nc-admin"', async () => {
		const existing = (await rolesOf(admin, { userId: ADMIN_USER })).find(
			(r) => r.role === 'viewer',
		)
		if (existing === undefined) {
			await grant({ userId: ADMIN_USER, role: 'viewer' })
		}
		expect(
			(await rolesOf(admin, { userId: ADMIN_USER })).map((r) => r.role),
		).toContain('viewer')

		const { status, body } = await myRole(admin)
		expect(status).toBe(200)
		expect(body).toEqual({ role: 'admin', source: SOURCE.ncAdmin })

		// And the NC admin is not restricted by it: a viewer may not create a
		// dashboard, the NC admin still may.
		await allowPersonalDashboards(admin, true)
		const created = await admin.post(`${API}/dashboard`, {
			data: { name: `E2E role override ${STAMP}` },
		})
		expect(created.status(), await created.text()).toBe(201)
		await admin.delete(`${API}/dashboard/${(await created.json()).dashboard.id}`)
	})

	// @e2e admin-roles::non-admin-with-direct-user-assignment
	test("a direct editor row wins as-is; the user's admin group is not consulted", async () => {
		const bob = await principal('e2e-role-direct-ed')
		const adminGroup = await group('e2e-role-admgrp')
		await addToGroup(admin, bob.username, adminGroup)
		await grant({ groupId: adminGroup, role: 'admin' })
		await grant({ userId: bob.username, role: 'editor' })

		const { status, body } = await myRole(bob.api)
		expect(status).toBe(200)
		// Had the admin group been consulted, "admin" would have won.
		expect(body).toEqual({ role: 'editor', source: SOURCE.user })
	})

	// @e2e admin-roles::direct-user-assignment-used-as-is-group-assignments-skipped
	test('a direct admin row is used as-is over an editor group row', async () => {
		const david = await principal('e2e-role-direct-adm')
		const editorGroup = await group('e2e-role-edgrp')
		await addToGroup(admin, david.username, editorGroup)
		await grant({ userId: david.username, role: 'admin' })
		await grant({ groupId: editorGroup, role: 'editor' })

		const { status, body } = await myRole(david.api)
		expect(status).toBe(200)
		expect(body).toEqual({ role: 'admin', source: SOURCE.user })
	})

	// @e2e admin-roles::direct-user-assignment-used-as-is-regardless-of-group-rank
	// @e2e admin-roles::user-with-direct-assignment
	test('a direct viewer row beats an admin group row: viewer, from the user row', async () => {
		const bob = await principal('e2e-role-direct-vw')
		const adminGroup = await group('e2e-role-admgrp2')
		await addToGroup(admin, bob.username, adminGroup)
		await grant({ groupId: adminGroup, role: 'admin' })
		await grant({ userId: bob.username, role: 'viewer' })

		const { status, body } = await myRole(bob.api)
		expect(status).toBe(200)
		expect(body).toEqual({ role: 'viewer', source: SOURCE.user })

		// The resolved role is what is enforced: the group's admin role does
		// not let this viewer create a dashboard.
		await allowPersonalDashboards(admin, true)
		const created = await bob.api.post(`${API}/dashboard`, {
			data: { name: `E2E role direct viewer ${STAMP}` },
		})
		expect(created.status(), await created.text()).toBe(403)
	})

	// @e2e admin-roles::multiple-group-memberships-highest-role-wins
	// @e2e admin-roles::highest-group-role-wins
	// @e2e admin-roles::user-with-multiple-group-assignments
	// @e2e admin-roles::user-queries-their-role-via-get-apimerole
	test('with only group rows, the highest group role wins and names its group', async () => {
		const charlie = await principal('e2e-role-multi')
		const engineering = await group('e2e-role-eng')
		const sales = await group('e2e-role-sales')
		await addToGroup(admin, charlie.username, engineering)
		await addToGroup(admin, charlie.username, sales)
		await grant({ groupId: engineering, role: 'editor' })
		await grant({ groupId: sales, role: 'viewer' })
		expect(await rolesOf(admin, { userId: charlie.username })).toEqual([])

		const { status, body } = await myRole(charlie.api)
		expect(status).toBe(200)
		expect(body).toEqual({ role: 'editor', source: SOURCE.group(engineering) })
	})

	// @e2e admin-roles::admin-role-is-highest-in-privilege-hierarchy
	test('group rows viewer, admin and editor resolve to admin', async () => {
		const user = await principal('e2e-role-rank')
		const viewers = await group('e2e-role-rank-vw')
		const admins = await group('e2e-role-rank-adm')
		const editors = await group('e2e-role-rank-ed')
		for (const g of [viewers, admins, editors]) {
			await addToGroup(admin, user.username, g)
		}
		await grant({ groupId: viewers, role: 'viewer' })
		await grant({ groupId: admins, role: 'admin' })
		await grant({ groupId: editors, role: 'editor' })

		const { status, body } = await myRole(user.api)
		expect(status).toBe(200)
		expect(body).toEqual({ role: 'admin', source: SOURCE.group(admins) })
	})

	// @e2e admin-roles::no-assignment-user-has-no-role
	// @e2e admin-roles::user-with-no-role-queries
	test('a user with no row and no role group has role null and source null, and falls back to the permission settings', async () => {
		const eve = await principal('e2e-role-none')
		expect(await rolesOf(admin, { userId: eve.username })).toEqual([])

		const { status, body } = await myRole(eve.api)
		expect(status).toBe(200)
		expect(body).toEqual({ role: null, source: null })

		// Falling back to the `permissions` behaviour: with personal
		// dashboards allowed, a role-less user may create one, exactly as
		// before roles existed.
		await allowPersonalDashboards(admin, true)
		const created = await eve.api.post(`${API}/dashboard`, {
			data: { name: `E2E role none ${STAMP}` },
		})
		expect(created.status(), await created.text()).toBe(201)
		await eve.api.delete(
			`${API}/dashboard/${(await created.json()).dashboard.id}`,
		)
	})

	// ── REQ-ROLE-010: account deletion ─────────────────────────────────────

	// @e2e admin-roles::user-deletion-removes-direct-assignments
	test('deleting an account removes its role rows; a new account of that name has no role', async () => {
		const bob = await principal('e2e-role-gone')
		await grant({ userId: bob.username, role: 'editor' })
		expect(await myRole(bob.api)).toMatchObject({ body: { role: 'editor' } })

		const deleted = await admin.delete(
			`/ocs/v2.php/cloud/users/${encodeURIComponent(bob.username)}?format=json`,
		)
		expect(deleted.status(), await deleted.text()).toBe(200)
		expect(await accountExists(admin, bob.username)).toBe(false)

		expect(await rolesOf(admin, { userId: bob.username })).toEqual([])

		// The id is free again; a fresh account under it must not inherit a role.
		const password = `Probe-${Math.random().toString(36).slice(2)}A1!`
		const again = await admin.post('/ocs/v1.php/cloud/users', {
			form: { userid: bob.username, password },
		})
		expect(again.ok(), await again.text()).toBe(true)
		const reborn = await contextFor(bob.username, password)
		principals.push({ username: bob.username, password, api: reborn })
		const { status, body } = await myRole(reborn)
		expect(status).toBe(200)
		expect(body).toEqual({ role: null, source: null })
	})

	// @e2e admin-roles::user-deletion-does-not-affect-group-assignments
	test('deleting a member leaves the group row, which still applies to the other members', async () => {
		const bob = await principal('e2e-role-leaver')
		const stayer = await principal('e2e-role-stayer')
		const engineering = await group('e2e-role-keep')
		await addToGroup(admin, bob.username, engineering)
		await addToGroup(admin, stayer.username, engineering)
		const groupRow = await grant({ groupId: engineering, role: 'editor' })

		const deleted = await admin.delete(
			`/ocs/v2.php/cloud/users/${encodeURIComponent(bob.username)}?format=json`,
		)
		expect(deleted.status(), await deleted.text()).toBe(200)

		expect(await rolesOf(admin, { groupId: engineering })).toEqual([
			expect.objectContaining({ id: groupRow, role: 'editor', userId: null }),
		])
		expect((await myRole(stayer.api)).body).toEqual({
			role: 'editor',
			source: SOURCE.group(engineering),
		})
	})

	// @e2e admin-roles::user-is-not-deleted-when-role-assignment-is-deleted
	test('deleting a role row leaves the account in place', async () => {
		const bob = await principal('e2e-role-kept')
		const rowId = await grant({ userId: bob.username, role: 'editor' })

		const res = await admin.delete(`${API}/admin/roles/${rowId}`)
		expect(res.status(), await res.text()).toBe(204)
		expect(await rolesOf(admin, { userId: bob.username })).toEqual([])

		expect(await accountExists(admin, bob.username)).toBe(true)
		// The account still logs in, and now has no role.
		await assertActingAs(bob.api, bob.username)
		expect((await myRole(bob.api)).body).toEqual({ role: null, source: null })
	})

	// ── REQ-ROLE-011: group deletion ───────────────────────────────────────

	// @e2e admin-roles::group-deletion-removes-group-assignments
	test('deleting a group removes its role rows, and the role stops applying', async () => {
		const member = await principal('e2e-role-grpgone')
		const engineering = await group('e2e-role-grpgone')
		await addToGroup(admin, member.username, engineering)
		await grant({ groupId: engineering, role: 'editor' })
		expect((await myRole(member.api)).body).toEqual({
			role: 'editor',
			source: SOURCE.group(engineering),
		})

		const deleted = await admin.delete(
			`/ocs/v2.php/cloud/groups/${encodeURIComponent(engineering)}?format=json`,
		)
		expect(deleted.status(), await deleted.text()).toBe(200)
		expect(await groupMembers(admin, engineering)).toBeNull()

		expect(await rolesOf(admin, { groupId: engineering })).toEqual([])
		expect((await myRole(member.api)).body).toEqual({ role: null, source: null })
	})

	// @e2e admin-roles::group-deletion-does-not-affect-member-accounts
	test("deleting a group keeps its members' accounts, drops the membership and recalculates their role", async () => {
		const bob = await principal('e2e-role-grpmember')
		const engineering = await group('e2e-role-grpkeep')
		await addToGroup(admin, bob.username, engineering)
		await grant({ groupId: engineering, role: 'editor' })

		const deleted = await admin.delete(
			`/ocs/v2.php/cloud/groups/${encodeURIComponent(engineering)}?format=json`,
		)
		expect(deleted.status(), await deleted.text()).toBe(200)

		expect(await accountExists(admin, bob.username)).toBe(true)
		await assertActingAs(bob.api, bob.username)
		expect(await groupsOf(admin, bob.username)).not.toContain(engineering)
		expect((await myRole(bob.api)).body).toEqual({ role: null, source: null })
	})

	// @e2e admin-roles::deletion-is-not-affected-by-member-state
	test("deleting a group's role row leaves the group and all its members", async () => {
		// Three members rather than the scenario's ten: the property is "the
		// members are untouched", and each extra account only adds provisioning time.
		const members = [
			await principal('e2e-role-m1'),
			await principal('e2e-role-m2'),
			await principal('e2e-role-m3'),
		]
		const engineering = await group('e2e-role-many')
		for (const m of members) {
			await addToGroup(admin, m.username, engineering)
		}
		const rowId = await grant({ groupId: engineering, role: 'editor' })

		const res = await admin.delete(`${API}/admin/roles/${rowId}`)
		expect(res.status(), await res.text()).toBe(204)
		expect(await rolesOf(admin, { groupId: engineering })).toEqual([])

		const after = await groupMembers(admin, engineering)
		expect(after, 'the group itself must remain').not.toBeNull()
		expect([...after!].sort()).toEqual(members.map((m) => m.username).sort())
		for (const m of members) {
			expect(await accountExists(admin, m.username)).toBe(true)
		}
	})
})
