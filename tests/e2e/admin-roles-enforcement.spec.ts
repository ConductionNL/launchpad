/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What each LaunchPad role lets its holder do (REQ-ROLE-001, REQ-ROLE-002,
 * REQ-ROLE-003, REQ-ROLE-007, REQ-ROLE-008): the Dashboard Admin role on the
 * admin API and on other people's dashboards, the Editor on group dashboards
 * in and out of its groups, and the Viewer refused every dashboard mutation
 * while still reading and reacting.
 *
 * The role rows themselves, and how a role is resolved, are in
 * `admin-roles.spec.ts`.
 *
 * 🔴 THE CAST, AND WHY EACH ONE IS A REAL LOGGED-IN ACCOUNT. Every probe goes
 * through a basic-auth context from `support/principals.ts`, checked with
 * `assertActingAs` before use, because a context that silently rides the
 * admin's session would make every refusal below meaningless:
 *
 *  - `plain`   member of the engineering group, no role: the control that
 *              shows a refusal is about the role and not the route;
 *  - `bob`     Editor (direct row), member of engineering only;
 *  - `charlie` Viewer (direct row), member of engineering. Two personal
 *              dashboards are created for him BEFORE the Viewer row exists,
 *              so the "even what he owns" scenarios have something to probe;
 *  - `alice`   LaunchPad Admin (direct row), member of no test group and not
 *              an NC admin. The scenarios about "a user with role admin" are
 *              probed with her: the NC admin passes every check by being one,
 *              so a success by the NC admin proves nothing about the role.
 *
 * Every refusal asserts the exact status, re-reads as an owner or the admin to
 * prove nothing was written, and is paired in the same test with a principal
 * who succeeds at the same request.
 *
 * ENDPOINT SPELLING. The spec writes `/api/dashboards/{uuid}`; the product's
 * routes are `GET|PUT|DELETE /api/dashboard/{id}` (numeric id) for any
 * dashboard, `POST /api/dashboard` to create a personal one,
 * `POST /api/dashboards/group/{groupId}` to create a group one,
 * `PUT /api/dashboards/{uuid}/metadata` for metadata values,
 * `POST /api/admin/demo-showcases/{id}/install` for demo data and
 * `POST /api/dashboard/{id}/widgets` for a widget. The tests use those.
 *
 * WHAT AN "EDIT" IS HERE. `themeAccent`: it is a layout-class change, so it
 * goes through `PermissionService::canEditDashboard()`, which is where the
 * role rules live. A name-only change goes through the owner-only metadata
 * check instead (REQ-PERM-007) and would not exercise the role at all.
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import {
	addToGroup,
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
	removeRole,
} from './support/principals.ts'
import {
	ADMIN_USER,
	grantRole,
	myRole,
	readGroupDashboard,
	removeGroupDashboard,
	seedGroupDashboard,
	SOURCE,
} from './support/roles.ts'

const STAMP = `${Date.now()}`
const SHOWCASE = 'horizon-labs'
const REACTIONS_DEFAULT =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/reactions_enabled_default'

interface Dash {
	id: number
	uuid: string
}

interface GroupDash extends Dash {
	groupId: string
}

test.describe('what each LaunchPad role may do', () => {
	let admin: APIRequestContext
	let plain: Principal | null = null
	let bob: Principal | null = null
	let charlie: Principal | null = null
	let alice: Principal | null = null
	let engineering: string | null = null
	let sales: string | null = null
	let engDash: GroupDash | null = null
	let salesDash: GroupDash | null = null
	let bobDash: Dash | null = null
	let plainDash: Dash | null = null
	let charlieDash: Dash | null = null
	let charlieSpare: Dash | null = null
	const roleIds: number[] = []
	const extraDashboards: Array<{ api: APIRequestContext; id: number }> = []
	const extraGroupDashboards: GroupDash[] = []

	/** Create a personal dashboard as `api`; throws unless 201. */
	async function personalDashboard(
		api: APIRequestContext,
		name: string,
	): Promise<Dash> {
		const res = await api.post(`${API}/dashboard`, { data: { name } })
		if (res.status() !== 201) {
			throw new Error(
				`could not create ${name}: ${res.status()} ${await res.text()}`,
			)
		}
		const dashboard = (await res.json()).dashboard
		return { id: Number(dashboard.id), uuid: String(dashboard.uuid) }
	}

	/** A dashboard as `api` reads it through `GET /api/dashboard/{id}`. */
	async function read(api: APIRequestContext, id: number) {
		const res = await api.get(`${API}/dashboard/${id}`)
		expect(res.status(), await res.text()).toBe(200)
		return (await res.json()) as {
			dashboard: Record<string, unknown>
			placements: Array<Record<string, unknown>>
		}
	}

	/** How many personal dashboards `api` owns. */
	async function ownedCount(api: APIRequestContext): Promise<number> {
		const res = await api.get(`${API}/dashboards`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		return ((body.items ?? body) as unknown[]).length
	}

	/** The admin's view of a demo showcase's install state. */
	async function showcaseState(): Promise<{
		isInstalled: boolean
		installedDashboardUuid: string | null
	}> {
		const res = await admin.get(`${API}/admin/demo-showcases`)
		expect(res.status(), await res.text()).toBe(200)
		const entry = ((await res.json()) as Array<Record<string, unknown>>).find(
			(s) => s.id === SHOWCASE,
		)
		expect(entry, `showcase ${SHOWCASE} must be bundled`).toBeTruthy()
		return {
			isInstalled: entry!.isInstalled === true,
			installedDashboardUuid:
				(entry!.installedDashboardUuid as string | null) ?? null,
		}
	}

	/** The metadata field definitions every logged-in user can read. */
	async function fieldKeys(api: APIRequestContext): Promise<string[]> {
		const res = await api.get(`${API}/metadata-fields`)
		expect(res.status(), await res.text()).toBe(200)
		return ((await res.json()).fields as Array<{ key: string }>).map(
			(f) => f.key,
		)
	}

	/** Delete a metadata field by key as the admin, values and all. */
	async function dropField(key: string): Promise<void> {
		const res = await admin.get(`${API}/admin/metadata-fields`)
		if (res.status() !== 200) {
			return
		}
		const field = (
			(await res.json()).fields as Array<{ id: number; key: string }>
		).find((f) => f.key === key)
		if (field !== undefined) {
			await admin.delete(
				`${API}/admin/metadata-fields/${field.id}?cascade=true`,
			)
		}
	}

	test.beforeAll(async () => {
		admin = await adminContext()
		await assertActingAs(admin, ADMIN_USER)
		// Personal dashboards allowed, so no refusal below can be the
		// admin flag rather than the role.
		await allowPersonalDashboards(admin, true)

		engineering = `e2e-rolex-eng-${STAMP}`
		sales = `e2e-rolex-sales-${STAMP}`
		await createGroup(admin, engineering)
		await createGroup(admin, sales)

		plain = await createPrincipal(admin, 'e2e-rolex-plain')
		bob = await createPrincipal(admin, 'e2e-rolex-bob')
		charlie = await createPrincipal(admin, 'e2e-rolex-charlie')
		alice = await createPrincipal(admin, 'e2e-rolex-alice')
		for (const p of [plain, bob, charlie, alice]) {
			await assertActingAs(p.api, p.username)
		}
		for (const p of [plain, bob, charlie]) {
			await addToGroup(admin, p.username, engineering)
		}

		// Charlie's own dashboards exist before he becomes a Viewer.
		charlieDash = await personalDashboard(
			charlie.api,
			`E2E rolex charlie ${STAMP}`,
		)
		charlieSpare = await personalDashboard(
			charlie.api,
			`E2E rolex charlie spare ${STAMP}`,
		)
		bobDash = await personalDashboard(bob.api, `E2E rolex bob ${STAMP}`)
		plainDash = await personalDashboard(plain.api, `E2E rolex plain ${STAMP}`)

		roleIds.push(
			await grantRole(admin, { userId: bob.username, role: 'editor' }),
		)
		roleIds.push(
			await grantRole(admin, { userId: charlie.username, role: 'viewer' }),
		)
		roleIds.push(
			await grantRole(admin, { userId: alice.username, role: 'admin' }),
		)

		engDash = await seedGroupDashboard(
			admin,
			engineering,
			`E2E rolex eng ${STAMP}`,
		)
		salesDash = await seedGroupDashboard(
			admin,
			sales,
			`E2E rolex sales ${STAMP}`,
		)

		// The cast is who it claims to be, role-wise, before anything is probed.
		expect((await myRole(bob.api)).body).toEqual({
			role: 'editor',
			source: SOURCE.user,
		})
		expect((await myRole(charlie.api)).body).toEqual({
			role: 'viewer',
			source: SOURCE.user,
		})
		expect((await myRole(alice.api)).body).toEqual({
			role: 'admin',
			source: SOURCE.user,
		})
		expect((await myRole(plain.api)).body).toEqual({ role: null, source: null })
	})

	/*
	 * Everything above is removed here. The two group dashboards cannot always
	 * be deleted (the last dashboard of a group is protected), so they are left
	 * behind only as rows of a group that no longer exists, reachable by no one.
	 */
	test.afterAll(async () => {
		if (admin === undefined) {
			return
		}
		for (const { api, id } of extraDashboards) {
			await api.delete(`${API}/dashboard/${id}`).catch(() => undefined)
		}
		for (const id of roleIds) {
			await removeRole(admin, id)
		}
		for (const extra of extraGroupDashboards) {
			await removeGroupDashboard(admin, extra)
		}
		await removeGroupDashboard(admin, engDash)
		await removeGroupDashboard(admin, salesDash)
		for (const [p, d] of [
			[plain, plainDash],
			[bob, bobDash],
			[charlie, charlieDash],
			[charlie, charlieSpare],
		] as const) {
			if (p !== null && d !== null) {
				await p.api.delete(`${API}/dashboard/${d.id}`).catch(() => undefined)
			}
		}
		for (const p of [plain, bob, charlie, alice]) {
			await removePrincipal(admin, p)
		}
		await removeGroup(admin, engineering)
		await removeGroup(admin, sales)
		await admin.dispose()
	})

	// ── REQ-ROLE-001: the Dashboard Admin role ─────────────────────────────

	// @e2e admin-roles::non-admin-user-assigned-dashboard-admin-role
	test('a non-admin with the admin role is admin from the user row and reaches the LaunchPad admin API', async () => {
		const { status, body } = await myRole(alice!.api)
		expect(status).toBe(200)
		expect(body).toEqual({ role: 'admin', source: SOURCE.user })

		// SUSPECTED BUG: every LaunchPad admin route is guarded by
		// `#[AuthorizedAdminSetting(LaunchPadAdmin::class)]`, which only an NC
		// admin (or a delegated NC admin group) passes. The LaunchPad admin
		// role is never consulted, so alice gets 403 on all four.
		const reached: Record<string, number> = {}
		for (const path of [
			'settings',
			'demo-showcases',
			'metadata-fields',
			'roles',
		]) {
			reached[path] = (await alice!.api.get(`${API}/admin/${path}`)).status()
		}
		expect(reached).toEqual({
			settings: 200,
			'demo-showcases': 200,
			'metadata-fields': 200,
			roles: 200,
		})
	})

	// @e2e admin-roles::dashboard-admin-can-edit-any-dashboard
	test("the admin role edits someone else's personal dashboard and a group dashboard it is not in", async () => {
		const accent = '#0b3d91'

		const personal = await alice!.api.put(`${API}/dashboard/${plainDash!.id}`, {
			data: { themeAccent: accent },
		})
		expect(personal.status(), await personal.text()).toBe(200)
		expect((await read(plain!.api, plainDash!.id)).dashboard.themeAccent).toBe(
			accent,
		)

		const group = await alice!.api.put(`${API}/dashboard/${engDash!.id}`, {
			data: { themeAccent: accent },
		})
		expect(group.status(), await group.text()).toBe(200)
		expect((await readGroupDashboard(admin, engDash!)).themeAccent).toBe(accent)
	})

	// @e2e admin-roles::admin-can-edit-any-groupshared-dashboard
	test('the admin role edits a group dashboard of a group it is not a member of', async () => {
		const accent = '#5b1a8a'
		const res = await alice!.api.put(`${API}/dashboard/${salesDash!.id}`, {
			data: { themeAccent: accent },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await readGroupDashboard(admin, salesDash!)).themeAccent).toBe(
			accent,
		)
	})

	// @e2e admin-roles::dashboard-admin-can-manage-metadata-field-definitions
	test('the admin role creates a metadata field definition that every dashboard can use', async () => {
		const key = `role_admin_field_${STAMP}`
		try {
			// SUSPECTED BUG: AuthorizedAdminSetting refuses alice with 403.
			const res = await alice!.api.post(`${API}/admin/metadata-fields`, {
				data: { key, label: 'Role admin field', type: 'text' },
			})
			expect(res.status(), await res.text()).toBe(201)

			// Global, not group-scoped: another user sees it and fills it in
			// on their own dashboard.
			expect(await fieldKeys(plain!.api)).toContain(key)
			const set = await plain!.api.put(
				`${API}/dashboards/${plainDash!.uuid}/metadata`,
				{
					data: { metadata: { [key]: 'set by plain' } },
				},
			)
			expect(set.status(), await set.text()).toBe(200)
		} finally {
			await dropField(key)
		}
	})

	// @e2e admin-roles::dashboard-admin-can-install-demo-data
	test('the admin role installs a demo showcase, which lands for everyone', async () => {
		const before = await showcaseState()
		try {
			// SUSPECTED BUG: AuthorizedAdminSetting refuses alice with 403.
			const res = await alice!.api.post(
				`${API}/admin/demo-showcases/${SHOWCASE}/install`,
			)
			expect([200, 201], await res.text()).toContain(res.status())
			const uuid = String((await res.json()).installedDashboardUuid)

			expect((await showcaseState()).isInstalled).toBe(true)
			// Installed into the default group: an ordinary user sees it.
			const visible = await plain!.api.get(`${API}/dashboards/visible`)
			expect(visible.status()).toBe(200)
			const body = await visible.json()
			const uuids = ((body.items ?? body) as Array<{ uuid: string }>).map(
				(d) => d.uuid,
			)
			expect(uuids).toContain(uuid)
		} finally {
			if (!before.isInstalled) {
				await admin.delete(`${API}/admin/demo-showcases/${SHOWCASE}`)
			}
		}
	})

	// @e2e admin-roles::dashboard-admin-can-edit-organization-navigation
	test('the admin role replaces the organisation navigation, and every user sees the change', async () => {
		const lang = 'en'
		const snapshot = await admin.get(`${API}/admin/org-navigation?lang=${lang}`)
		expect(snapshot.status(), await snapshot.text()).toBe(200)
		const previous = (await snapshot.json()).tree ?? []

		const nodeId = randomUUID()
		const label = `E2E role admin nav ${STAMP}`
		try {
			// SUSPECTED BUG: AuthorizedAdminSetting refuses alice with 403.
			const res = await alice!.api.put(`${API}/admin/org-navigation`, {
				data: {
					lang,
					tree: [{ id: nodeId, label, url: '/apps/files', children: [] }],
				},
			})
			expect(res.status(), await res.text()).toBe(200)

			const seen = await plain!.api.get(
				`${API}/admin/org-navigation?lang=${lang}`,
			)
			expect(seen.status(), await seen.text()).toBe(200)
			const tree = (await seen.json()).tree as Array<{
				id: string
				label: string
			}>
			expect(tree.map((n) => n.id)).toContain(nodeId)
		} finally {
			// Restore what the admin could see before. A node hidden from the
			// admin by its group visibility could not be read back, so on an
			// instance that has such nodes this restore is lossy; the CI
			// fixture has none.
			await admin.put(`${API}/admin/org-navigation`, {
				data: { lang, tree: previous },
			})
		}
	})

	// ── REQ-ROLE-002 / 007: the Editor role ────────────────────────────────

	// @e2e admin-roles::dashboard-editor-creates-a-new-groupshared-dashboard
	test('an editor creates a group dashboard in their own group and owns it', async () => {
		// SUSPECTED BUG: `createGroup` is AuthorizedAdminSetting-guarded and
		// `createGroupShared()` requires an NC admin, so bob gets 403; and a
		// group dashboard is created with userId NULL, so nobody "owns" one.
		const res = await bob!.api.post(`${API}/dashboards/group/${engineering}`, {
			data: { name: `E2E rolex bob group ${STAMP}` },
		})
		const text = await res.text()
		if (res.status() === 201) {
			const made = JSON.parse(text).dashboard
			extraGroupDashboards.push({
				id: Number(made.id),
				uuid: String(made.uuid),
				groupId: engineering!,
			})
		}
		expect(res.status(), text).toBe(201)
		const dashboard = JSON.parse(text).dashboard
		expect(dashboard.type).toBe('group_shared')
		expect(dashboard.groupId).toBe(engineering)
		expect(dashboard.userId).toBe(bob!.username)
	})

	// @e2e admin-roles::dashboard-editor-can-edit-groupshared-dashboard-in-their-group
	// @e2e admin-roles::editor-in-correct-group-can-edit
	test('an editor edits a group dashboard of their own group, and the edit is persisted', async () => {
		const accent = '#7a1f1f'
		const res = await bob!.api.put(`${API}/dashboard/${engDash!.id}`, {
			data: { themeAccent: accent },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await readGroupDashboard(admin, engDash!)).themeAccent).toBe(accent)
	})

	// @e2e admin-roles::dashboard-editor-cannot-edit-groupshared-dashboard-outside-their-groups
	// @e2e admin-roles::editor-without-group-membership-cannot-edit
	test('an editor cannot edit a group dashboard of a group they are not in', async () => {
		const accent = '#1f5f2a'
		const before =
			(await readGroupDashboard(admin, salesDash!)).themeAccent ?? null

		const res = await bob!.api.put(`${API}/dashboard/${salesDash!.id}`, {
			data: { themeAccent: accent },
		})
		expect(res.status(), await res.text()).toBe(403)
		expect(
			(await readGroupDashboard(admin, salesDash!)).themeAccent ?? null,
		).toBe(before)

		// Control: the same request on a dashboard bob may edit goes through.
		const allowed = await bob!.api.put(`${API}/dashboard/${bobDash!.id}`, {
			data: { themeAccent: accent },
		})
		expect(allowed.status(), await allowed.text()).toBe(200)
	})

	// @e2e admin-roles::dashboard-editor-can-create-personal-dashboard
	test('an editor creates a personal dashboard that is theirs and invisible to others', async () => {
		const res = await bob!.api.post(`${API}/dashboard`, {
			data: { name: `E2E rolex bob personal ${STAMP}` },
		})
		expect(res.status(), await res.text()).toBe(201)
		const dashboard = (await res.json()).dashboard
		extraDashboards.push({ api: bob!.api, id: Number(dashboard.id) })

		expect(dashboard.userId).toBe(bob!.username)
		expect((await read(bob!.api, Number(dashboard.id))).dashboard.uuid).toBe(
			dashboard.uuid,
		)
		// plain shares a group with bob and still cannot see it.
		const other = await plain!.api.get(`${API}/dashboard/${dashboard.id}`)
		expect(other.status(), await other.text()).toBe(404)
	})

	// @e2e admin-roles::editor-can-edit-personal-dashboard-within-their-own-context
	test('an editor edits their own personal dashboard', async () => {
		const accent = '#00468b'
		const res = await bob!.api.put(`${API}/dashboard/${bobDash!.id}`, {
			data: { themeAccent: accent },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect((await read(bob!.api, bobDash!.id)).dashboard.themeAccent).toBe(
			accent,
		)
	})

	// @e2e admin-roles::dashboard-editor-can-edit-metadata-field-values-not-definitions
	test('an editor fills in a metadata value but cannot define a new field', async () => {
		const key = `priority_${STAMP}`
		const forbidden = `editor_field_${STAMP}`
		const created = await admin.post(`${API}/admin/metadata-fields`, {
			data: { key, label: 'Priority', type: 'text' },
		})
		expect(created.status(), await created.text()).toBe(201)
		try {
			const set = await bob!.api.put(
				`${API}/dashboards/${bobDash!.uuid}/metadata`,
				{
					data: { metadata: { [key]: 'High' } },
				},
			)
			expect(set.status(), await set.text()).toBe(200)
			const got = await bob!.api.get(
				`${API}/dashboards/${bobDash!.uuid}/metadata`,
			)
			expect(got.status(), await got.text()).toBe(200)
			expect((await got.json())[key]).toBe('High')

			const define = await bob!.api.post(`${API}/admin/metadata-fields`, {
				data: { key: forbidden, label: 'Editor field', type: 'text' },
			})
			expect(define.status(), await define.text()).toBe(403)
			expect(await fieldKeys(admin)).not.toContain(forbidden)
		} finally {
			await dropField(key)
			await dropField(forbidden)
		}
	})

	// @e2e admin-roles::dashboard-editor-cannot-install-demo-data
	test('an editor cannot install demo data; the NC admin can', async () => {
		const before = await showcaseState()
		try {
			const res = await bob!.api.post(
				`${API}/admin/demo-showcases/${SHOWCASE}/install`,
			)
			expect(res.status(), await res.text()).toBe(403)
			expect(await showcaseState()).toEqual(before)

			// Control: the same request by the NC admin is accepted.
			const allowed = await admin.post(
				`${API}/admin/demo-showcases/${SHOWCASE}/install`,
			)
			expect([200, 201], await allowed.text()).toContain(allowed.status())
			expect((await showcaseState()).isInstalled).toBe(true)
		} finally {
			if (!before.isInstalled) {
				await admin.delete(`${API}/admin/demo-showcases/${SHOWCASE}`)
			}
		}
	})

	// @e2e admin-roles::dashboard-editor-cannot-add-metadata-field-definitions
	test('an editor cannot define a metadata field; the NC admin can', async () => {
		const key = `editor_def_${STAMP}`
		try {
			const res = await bob!.api.post(`${API}/admin/metadata-fields`, {
				data: { key, label: 'Editor definition', type: 'text' },
			})
			expect(res.status(), await res.text()).toBe(403)
			expect(await fieldKeys(admin)).not.toContain(key)

			const allowed = await admin.post(`${API}/admin/metadata-fields`, {
				data: { key, label: 'Editor definition', type: 'text' },
			})
			expect(allowed.status(), await allowed.text()).toBe(201)
			expect(await fieldKeys(admin)).toContain(key)
		} finally {
			await dropField(key)
		}
	})

	// ── REQ-ROLE-003 / 008: the Viewer role ────────────────────────────────

	// @e2e admin-roles::dashboard-viewer-can-read-dashboards
	// @e2e admin-roles::viewer-can-read-dashboard
	test('a viewer reads a group dashboard of their group, widgets included', async () => {
		const widget = await admin.post(`${API}/dashboard/${engDash!.id}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 0,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 2,
				content: { text: `seeded ${STAMP}`, contentMode: 'markdown' },
			},
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)
		const placementId = Number((await widget.json()).id)

		const seen = await read(charlie!.api, engDash!.id)
		expect(seen.dashboard.uuid).toBe(engDash!.uuid)
		expect(seen.placements.map((p) => Number(p.id))).toContain(placementId)
	})

	// @e2e admin-roles::dashboard-viewer-cannot-create-dashboard
	// @e2e admin-roles::viewer-cannot-create-dashboard
	test('a viewer cannot create a dashboard of any scope', async () => {
		const before = await ownedCount(charlie!.api)

		const personal = await charlie!.api.post(`${API}/dashboard`, {
			data: { name: `E2E rolex charlie new ${STAMP}` },
		})
		expect(personal.status(), await personal.text()).toBe(403)
		const group = await charlie!.api.post(
			`${API}/dashboards/group/${engineering}`,
			{
				data: { name: `E2E rolex charlie group ${STAMP}` },
			},
		)
		expect(group.status(), await group.text()).toBe(403)

		expect(await ownedCount(charlie!.api)).toBe(before)

		// Control: a role-less member of the same group may create one.
		const allowed = await plain!.api.post(`${API}/dashboard`, {
			data: { name: `E2E rolex plain extra ${STAMP}` },
		})
		expect(allowed.status(), await allowed.text()).toBe(201)
		extraDashboards.push({
			api: plain!.api,
			id: Number((await allowed.json()).dashboard.id),
		})
	})

	// @e2e admin-roles::dashboard-viewer-cannot-create-personal-dashboard
	// @e2e admin-roles::viewer-cannot-create-personal-dashboard
	test('a viewer who already owns personal dashboards still cannot create another', async () => {
		const before = await ownedCount(charlie!.api)
		expect(
			before,
			'charlie owns dashboards from before the role',
		).toBeGreaterThanOrEqual(2)

		const res = await charlie!.api.post(`${API}/dashboard`, {
			data: { name: `E2E rolex charlie personal ${STAMP}` },
		})
		expect(res.status(), await res.text()).toBe(403)
		expect(await ownedCount(charlie!.api)).toBe(before)

		const allowed = await plain!.api.post(`${API}/dashboard`, {
			data: { name: `E2E rolex plain personal ${STAMP}` },
		})
		expect(allowed.status(), await allowed.text()).toBe(201)
		extraDashboards.push({
			api: plain!.api,
			id: Number((await allowed.json()).dashboard.id),
		})
	})

	// @e2e admin-roles::dashboard-viewer-cannot-edit-dashboard
	// @e2e admin-roles::viewer-cannot-edit-even-in-their-group
	test('a viewer cannot edit a group dashboard of their own group', async () => {
		const accent = '#3d2b00'
		const before =
			(await readGroupDashboard(admin, engDash!)).themeAccent ?? null

		const res = await charlie!.api.put(`${API}/dashboard/${engDash!.id}`, {
			data: { themeAccent: accent },
		})
		expect(res.status(), await res.text()).toBe(403)
		expect((await readGroupDashboard(admin, engDash!)).themeAccent ?? null).toBe(
			before,
		)

		// Control: the same request on a dashboard its sender may edit.
		const allowed = await bob!.api.put(`${API}/dashboard/${bobDash!.id}`, {
			data: { themeAccent: accent },
		})
		expect(allowed.status(), await allowed.text()).toBe(200)
	})

	// @e2e admin-roles::viewer-cannot-edit-dashboard
	test('a viewer cannot edit even a dashboard they own: neither its layout nor its name', async () => {
		const accent = '#2b2b6b'
		const original = await read(charlie!.api, charlieDash!.id)

		const layout = await charlie!.api.put(
			`${API}/dashboard/${charlieDash!.id}`,
			{
				data: { themeAccent: accent },
			},
		)
		expect(layout.status(), await layout.text()).toBe(403)

		const rename = await charlie!.api.put(
			`${API}/dashboard/${charlieDash!.id}`,
			{
				data: { name: `renamed by a viewer ${STAMP}` },
			},
		)
		expect(rename.status(), await rename.text()).toBe(403)

		const after = await read(charlie!.api, charlieDash!.id)
		expect(after.dashboard.themeAccent ?? null).toBe(
			original.dashboard.themeAccent ?? null,
		)
		expect(after.dashboard.name).toBe(original.dashboard.name)

		const allowed = await plain!.api.put(`${API}/dashboard/${plainDash!.id}`, {
			data: { name: `E2E rolex plain renamed ${STAMP}` },
		})
		expect(allowed.status(), await allowed.text()).toBe(200)
	})

	// @e2e admin-roles::dashboard-viewer-cannot-delete-dashboard
	test('a viewer cannot delete a group dashboard they can read', async () => {
		const res = await charlie!.api.delete(`${API}/dashboard/${engDash!.id}`)
		expect(res.status(), await res.text()).toBe(403)
		expect((await readGroupDashboard(admin, engDash!)).uuid).toBe(engDash!.uuid)
		expect((await read(charlie!.api, engDash!.id)).dashboard.uuid).toBe(
			engDash!.uuid,
		)
	})

	// @e2e admin-roles::viewer-cannot-delete-dashboard
	test('a viewer cannot delete even a dashboard they own', async () => {
		const res = await charlie!.api.delete(`${API}/dashboard/${charlieSpare!.id}`)
		expect(res.status(), await res.text()).toBe(403)
		expect((await read(charlie!.api, charlieSpare!.id)).dashboard.uuid).toBe(
			charlieSpare!.uuid,
		)

		// Control: a role-less owner deletes their own.
		const spare = await personalDashboard(
			plain!.api,
			`E2E rolex plain doomed ${STAMP}`,
		)
		const allowed = await plain!.api.delete(`${API}/dashboard/${spare.id}`)
		expect(allowed.status(), await allowed.text()).toBe(200)
	})

	// @e2e admin-roles::viewer-cannot-install-widget
	test('a viewer cannot add a widget, even to their own dashboard', async () => {
		const body = {
			widgetId: 'text',
			gridX: 0,
			gridY: 8,
			gridWidth: 2,
			gridHeight: 2,
		}
		const before = (await read(charlie!.api, charlieDash!.id)).placements.length

		const res = await charlie!.api.post(
			`${API}/dashboard/${charlieDash!.id}/widgets`,
			{
				data: body,
			},
		)
		expect(res.status(), await res.text()).toBe(403)
		expect((await read(charlie!.api, charlieDash!.id)).placements.length).toBe(
			before,
		)

		const allowed = await plain!.api.post(
			`${API}/dashboard/${plainDash!.id}/widgets`,
			{
				data: body,
			},
		)
		expect(allowed.status(), await allowed.text()).toBeLessThan(300)
	})

	// @e2e admin-roles::dashboard-viewer-can-react-and-comment-if-enabled
	// @e2e admin-roles::viewer-can-react-and-comment-if-feature-enabled
	test('a viewer may react to a dashboard of their group when reactions are on', async () => {
		const readDefault = async (): Promise<string | null> => {
			const res = await admin.get(`${REACTIONS_DEFAULT}?format=json`)
			if (!res.ok()) {
				return null
			}
			const data = (await res.json())?.ocs?.data?.data
			return typeof data === 'string' && data !== '' ? data : null
		}
		const previous = await readDefault()
		const on = await admin.post(`${REACTIONS_DEFAULT}?format=json`, {
			form: { value: '1' },
		})
		expect(on.ok(), `enable reactions -> ${on.status()}`).toBe(true)
		try {
			const res = await charlie!.api.post(
				`${API}/dashboards/${engDash!.uuid}/reactions`,
				{
					data: { emoji: '👍' },
				},
			)
			expect(res.status(), await res.text()).toBe(200)
			const summary = await res.json()
			expect(summary.enabled).toBe(true)
			expect(summary.mine).toContain('👍')

			const again = await charlie!.api.get(
				`${API}/dashboards/${engDash!.uuid}/reactions`,
			)
			expect(again.status(), await again.text()).toBe(200)
			expect((await again.json()).mine).toContain('👍')
		} finally {
			await charlie!.api
				.delete(
					`${API}/dashboards/${engDash!.uuid}/reactions/${encodeURIComponent('👍')}`,
				)
				.catch(() => undefined)
			if (previous === null) {
				await admin.delete(`${REACTIONS_DEFAULT}?format=json`)
			} else {
				await admin.post(`${REACTIONS_DEFAULT}?format=json`, {
					form: { value: previous },
				})
			}
		}
	})
})
