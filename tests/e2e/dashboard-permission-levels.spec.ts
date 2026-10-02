/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What each permission level lets a real user do (REQ-PERM-001, REQ-PERM-002,
 * REQ-PERM-003, REQ-PERM-005, REQ-PERM-006, REQ-PERM-009, REQ-PERM-010,
 * REQ-PERM-011).
 *
 * 🔴 EVERY REFUSAL IS PROBED BY A SIGNED-IN USER WHO SHOULD BE REFUSED, and
 * each one is checked three ways:
 *
 *  1. the exact status, 403, not "something other than 200";
 *  2. a re-read AS ADMIN, proving the write did not land. A 403 on a route
 *     that wrote anyway is indistinguishable from a real refusal if you only
 *     look at the status;
 *  3. a control in the same test where the SAME operation succeeds for a
 *     principal who should be allowed, so a refusal cannot be a broken
 *     endpoint refusing everyone.
 *
 * WHERE THE LEVELS COME FROM. A dashboard a user creates is always `full`, so
 * a restricted level has to be given to them. The product's way of doing that
 * is a share: the admin owns a dashboard and shares it at `view_only`,
 * `add_only` or `full`, and `PermissionService::resolveAccessLevel()` reads
 * the share's level for the recipient. The other path the spec describes, a
 * user's own copy of an admin template, cannot be arranged: the only code that
 * creates one (`tryCreateFromTemplate`) is pre-empted on every install by the
 * seeded default dashboard, so those scenarios are pinned in
 * `PermissionServiceLevelsTest` instead.
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
	seedDashboardWithWidget,
	shareWith,
} from './support/principals.ts'

const STAMP = `E2E Perm ${Date.now()}`

interface Fixture {
	id: number
	uuid: string
	placementId: number
}

test.describe('permission levels, probed as the user who has them', () => {
	let admin: APIRequestContext
	let alice: Principal | null = null
	let bob: Principal | null = null
	const viewOnly: Fixture[] = []
	const addOnly: Fixture[] = []
	const full: Fixture[] = []

	test.beforeAll(async () => {
		admin = await adminContext()
		await allowPersonalDashboards(admin, true)

		alice = await createPrincipal(admin, 'e2e-perm-alice')
		bob = await createPrincipal(admin, 'e2e-perm-bob')

		// Prove each probe is the user it names before probing with it.
		await assertActingAs(alice.api, alice.username)
		await assertActingAs(bob.api, bob.username)

		// One dashboard per level, owned by the admin and shared with alice.
		// bob gets nothing: he is the stranger REQ-PERM-010 is about.
		for (const [level, into] of [
			['view_only', viewOnly],
			['add_only', addOnly],
			['full', full],
		] as const) {
			const seeded = await seedDashboardWithWidget(admin, `${STAMP} ${level}`)
			await shareWith(admin, seeded.id, alice.username, level)
			into.push(seeded)
		}
	})

	/*
	 * 🔴 EVERY ACCOUNT AND DASHBOARD THIS FILE CREATES IS REMOVED HERE. A
	 * throwaway account left behind keeps its share, and the next run's
	 * "nothing landed" re-read would then be reading another run's state.
	 */
	test.afterAll(async () => {
		if (admin === undefined) {
			return
		}
		for (const fixture of [...viewOnly, ...addOnly, ...full]) {
			await admin.delete(`${API}/dashboard/${fixture.id}`)
		}
		await removePrincipal(admin, alice)
		await removePrincipal(admin, bob)
		await admin.dispose()
	})

	/** The placements on a dashboard, read back as the admin who owns it. */
	async function placementsAsAdmin(id: number): Promise<any[]> {
		const res = await admin.get(`${API}/dashboard/${id}`)
		expect(res.status(), await res.text()).toBe(200)
		return (await res.json()).placements ?? []
	}

	// @e2e permissions::view-only-user-cannot-add-widgets
	// @e2e permissions::view-only-user-cannot-add-tiles
	// @e2e permissions::api-rejects-widget-addition-on-view-only-dashboard
	test('a view-only user cannot add a widget or a tile, and nothing lands', async () => {
		const target = viewOnly[0]
		const before = (await placementsAsAdmin(target.id)).length

		const widget = await alice!.api.post(
			`${API}/dashboard/${target.id}/widgets`,
			{
				data: {
					widgetId: 'text',
					gridX: 0,
					gridY: 6,
					gridWidth: 2,
					gridHeight: 2,
				},
			},
		)
		expect(
			widget.status(),
			`a view-only user added a widget: ${await widget.text()}`,
		).toBe(403)

		const tile = await alice!.api.post(`${API}/dashboard/${target.id}/tile`, {
			data: {
				tileType: 'shortcut',
				tileTitle: 'nope',
				tileLinkType: 'url',
				tileLinkValue: '/',
			},
		})
		expect(
			tile.status(),
			`a view-only user added a tile: ${await tile.text()}`,
		).toBe(403)

		expect(
			(await placementsAsAdmin(target.id)).length,
			'a refused add must not create a placement',
		).toBe(before)

		// Control: the same call succeeds for someone who may make it, so the
		// 403s above are about alice's level and not a broken route.
		const allowed = await alice!.api.post(
			`${API}/dashboard/${full[0].id}/widgets`,
			{
				data: {
					widgetId: 'text',
					gridX: 0,
					gridY: 6,
					gridWidth: 2,
					gridHeight: 2,
				},
			},
		)
		expect(
			allowed.status(),
			`the same request was refused at full permission too: ${await allowed.text()}`,
		).toBeLessThan(300)
	})

	// @e2e permissions::view-only-user-cannot-modify-widgets
	// @e2e permissions::view-only-user-cannot-style-widgets
	test('a view-only user cannot restyle a widget, and the widget is unchanged', async () => {
		const target = viewOnly[0]

		const res = await alice!.api.put(`${API}/widgets/${target.placementId}`, {
			data: { customTitle: 'taken over' },
		})
		expect(
			res.status(),
			`a view-only user restyled a widget: ${await res.text()}`,
		).toBe(403)

		const after = await placementsAsAdmin(target.id)
		const placement = after.find((p) => Number(p.id) === target.placementId)
		expect(
			placement?.customTitle ?? null,
			'a refused restyle must leave the widget alone',
		).not.toBe('taken over')

		// Control: the same restyle at add_only is allowed.
		const allowed = await alice!.api.put(
			`${API}/widgets/${addOnly[0].placementId}`,
			{
				data: { customTitle: 'renamed by alice' },
			},
		)
		expect(allowed.status(), await allowed.text()).toBeLessThan(300)
	})

	// @e2e permissions::view-only-user-cannot-delete-widgets
	test('a view-only user cannot delete a widget, and it is still there', async () => {
		const target = viewOnly[0]

		const res = await alice!.api.delete(`${API}/widgets/${target.placementId}`)
		expect(
			res.status(),
			`a view-only user deleted a widget: ${await res.text()}`,
		).toBe(403)

		const after = await placementsAsAdmin(target.id)
		expect(
			after.some((p) => Number(p.id) === target.placementId),
			'a refused delete must leave the widget in place',
		).toBe(true)
	})

	// @e2e permissions::add-only-user-can-add-widgets
	// @e2e permissions::add-only-user-can-edit-widget-settings
	// @e2e permissions::add-only-user-can-remove-non-compulsory-widgets
	// @e2e permissions::add-only-user-can-style-widgets
	// @e2e permissions::user-added-widgets-are-never-compulsory
	test('an add-only user can add, restyle and remove a widget they added', async () => {
		const target = addOnly[0]

		const added = await alice!.api.post(
			`${API}/dashboard/${target.id}/widgets`,
			{
				data: {
					widgetId: 'text',
					gridX: 4,
					gridY: 0,
					gridWidth: 2,
					gridHeight: 2,
				},
			},
		)
		expect(added.status(), await added.text()).toBeLessThan(300)
		const placementId = Number((await added.json()).id)

		// REQ-PERM-004: a widget a user adds is never compulsory, which is what
		// lets them remove it again on an add-only dashboard.
		const fresh = (await placementsAsAdmin(target.id)).find(
			(p) => Number(p.id) === placementId,
		)
		expect(Number(fresh?.isCompulsory ?? 1)).toBe(0)

		const styled = await alice!.api.put(`${API}/widgets/${placementId}`, {
			data: { customTitle: 'mine', showTitle: 0 },
		})
		expect(styled.status(), await styled.text()).toBeLessThan(300)

		const removed = await alice!.api.delete(`${API}/widgets/${placementId}`)
		expect(removed.status(), await removed.text()).toBeLessThan(300)
		expect(
			(await placementsAsAdmin(target.id)).some(
				(p) => Number(p.id) === placementId,
			),
			'the widget alice removed must be gone',
		).toBe(false)
	})

	// @e2e permissions::non-owner-cannot-modify-widgets-regardless-of-permission
	// @e2e permissions::dashboard-ownership-check
	// @e2e permissions::placement-ownership-check-via-dashboard
	test('a user with no share is refused on a full dashboard, which its owner can edit', async () => {
		const target = full[0]
		const before = await placementsAsAdmin(target.id)

		// bob is signed in and ordinary: the least privileged principal who
		// should be refused. The dashboard is `full`, so only the missing
		// relationship can be the reason.
		const styled = await bob!.api.put(`${API}/widgets/${target.placementId}`, {
			data: { customTitle: 'bob was here' },
		})
		expect(
			styled.status(),
			`a stranger restyled a widget: ${await styled.text()}`,
		).toBe(403)

		const removed = await bob!.api.delete(`${API}/widgets/${target.placementId}`)
		expect(
			removed.status(),
			`a stranger deleted a widget: ${await removed.text()}`,
		).toBe(403)

		const added = await bob!.api.post(`${API}/dashboard/${target.id}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 8,
				gridY: 0,
				gridWidth: 2,
				gridHeight: 2,
			},
		})
		expect(
			added.status(),
			`a stranger added a widget: ${await added.text()}`,
		).toBe(403)

		const after = await placementsAsAdmin(target.id)
		expect(after.length, 'a stranger must not change anything').toBe(
			before.length,
		)
		expect(
			after.find((p) => Number(p.id) === target.placementId)?.customTitle
				?? null,
		).not.toBe('bob was here')

		// Control: alice, who holds a full share on the same dashboard, can.
		const allowed = await alice!.api.put(
			`${API}/widgets/${target.placementId}`,
			{
				data: { customTitle: 'alice may' },
			},
		)
		expect(allowed.status(), await allowed.text()).toBeLessThan(300)
	})

	// @e2e permissions::full-permission-is-the-default-for-user-created-dashboards
	// @e2e permissions::user-tries-to-downgrade-permission-level
	test('a dashboard a user creates is full, and they cannot write a level onto it', async () => {
		const created = await alice!.api.post(`${API}/dashboard`, {
			data: { name: `${STAMP} alice own` },
		})
		expect(created.status(), await created.text()).toBeLessThan(300)
		const own = (await created.json()).dashboard ?? {}

		try {
			expect(
				own.permissionLevel,
				'a dashboard a user creates must be full',
			).toBe('full')

			// REQ-PERM-005: the level is not hers to set.
			const escalate = await alice!.api.put(`${API}/dashboard/${own.id}`, {
				data: { name: `${STAMP} alice own`, permissionLevel: 'view_only' },
			})
			expect(escalate.status(), await escalate.text()).toBeLessThan(300)

			const reread = await alice!.api.get(`${API}/dashboard/${own.id}`)
			expect(
				(await reread.json()).dashboard?.permissionLevel,
				'the permission level must ignore what the user sent',
			).toBe('full')
		} finally {
			await admin.delete(`${API}/dashboard/${own.id}`)
		}
	})

	// @e2e permissions::regular-user-cannot-edit-admin-template-dashboard
	// @e2e permissions::admin-templates-have-no-userid
	test('an ordinary user cannot edit an admin template, which an admin can', async () => {
		const created = await admin.post(`${API}/admin/templates`, {
			data: { name: `${STAMP} template` },
		})
		expect(created.status(), await created.text()).toBeLessThan(300)
		const template = await created.json()
		const templateId = Number(template.id ?? template.dashboard?.id)

		try {
			expect(
				template.userId ?? template.dashboard?.userId ?? null,
				'an admin template belongs to nobody',
			).toBeNull()

			const res = await alice!.api.put(`${API}/dashboard/${templateId}`, {
				data: { name: `${STAMP} taken over` },
			})
			expect(
				res.status(),
				`an ordinary user edited an admin template: ${await res.text()}`,
			).toBe(403)

			const after = await admin.get(`${API}/dashboard/${templateId}`)
			expect((await after.json()).dashboard?.name).toBe(`${STAMP} template`)

			// Control: the admin may rename it.
			const allowed = await admin.put(`${API}/dashboard/${templateId}`, {
				data: { name: `${STAMP} template renamed` },
			})
			expect(allowed.status(), await allowed.text()).toBeLessThan(300)
		} finally {
			await admin.delete(`${API}/admin/templates/${templateId}`)
		}
	})
})
