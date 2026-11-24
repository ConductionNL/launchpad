/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin template REST contract (admin-templates REQ-TMPL-001..004,
 * -008, -009): create, list, read, update and delete through
 * `/api/admin/templates`, the single-default invariant, and the refusals an
 * ordinary signed-in account meets.
 *
 * These scenarios ARE an API contract (status codes, envelopes, fields), so
 * they are driven through the API. Every refusal is probed by a throwaway
 * non-admin account over basic auth, re-read as the admin to prove nothing
 * was written, and paired with the admin succeeding in the same test.
 *
 * Templates carrying widgets are seeded through the dashboard importer: the
 * widget API has no path to a template (see support/adminTemplates.ts).
 *
 * Making a template the default changes what a FRESH account lands on. Tests
 * run serially with one worker, and afterAll hands the default flag back to
 * whichever template held it before.
 *
 * Gate-19 @e2e traceability:
 *   @e2e admin-templates::create-a-template-targeting-specific-groups
 *   @e2e admin-templates::create-a-default-template-for-all-users
 *   @e2e admin-templates::non-admin-user-cannot-create-templates
 *   @e2e admin-templates::create-template-with-uuid-generation
 *   @e2e admin-templates::list-all-templates
 *   @e2e admin-templates::non-admin-cannot-list-templates
 *   @e2e admin-templates::templates-filtered-from-user-dashboard-list
 *   @e2e admin-templates::mark-template-as-default
 *   @e2e admin-templates::non-admin-cannot-update-templates
 *   @e2e admin-templates::delete-a-template-with-no-user-copies
 *   @e2e admin-templates::non-admin-cannot-delete-templates
 *   @e2e admin-templates::delete-non-template-dashboard-via-template-endpoint
 *   @e2e admin-templates::delete-the-default-template
 *   @e2e admin-templates::set-a-template-as-default-when-no-default-exists
 *   @e2e admin-templates::set-a-template-as-default-when-another-is-already-default
 *   @e2e admin-templates::remove-default-status-from-the-only-default-template
 *   @e2e admin-templates::get-template-with-its-placements
 *   @e2e admin-templates::get-non-template-dashboard-via-template-endpoint
 *   @e2e admin-templates::get-template-with-no-placements
 *
 * @spec openspec/specs/admin-templates/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { TemplateRow } from './support/adminTemplates.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	activeDashboard,
	createTemplate,
	deleteTemplates,
	getTemplate,
	importTemplate,
	listTemplates,
	readAllowUserDashboards,
	rememberDefaultTemplate,
	restoreDefaultTemplate,
	setAllowUserDashboards,
	textWidget,
} from './support/adminTemplates.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const STAMP = `E2E Tmpl ${Date.now()}`

let admin: APIRequestContext
let alice: Principal | null = null
let defaultBefore: number | null = null
let allowBefore: boolean | null = null
const made: number[] = []
const userDashboards: number[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	defaultBefore = await rememberDefaultTemplate(admin)
	allowBefore = await readAllowUserDashboards(admin)
	// Alice makes personal dashboards of her own below.
	await setAllowUserDashboards(admin, true)
	alice = await createPrincipal(admin, 'e2e-tmpl-alice')
	await assertActingAs(alice.api, alice.username)
})

test.afterAll(async () => {
	await deleteTemplates(admin, made)
	for (const id of userDashboards) {
		await admin.delete(`${API}/dashboard/${id}`)
	}
	await restoreDefaultTemplate(admin, defaultBefore)
	if (allowBefore !== null) {
		await setAllowUserDashboards(admin, allowBefore)
	}
	await removePrincipal(admin, alice)
	await admin.dispose()
})

/** Create a template as the admin and remember it for cleanup. */
async function seed(data: Record<string, unknown>): Promise<TemplateRow> {
	const row = await createTemplate(admin, data)
	made.push(row.id)
	return row
}

/** The admin's view of one template's row, by id. */
async function rowOf(id: number): Promise<TemplateRow | undefined> {
	return (await listTemplates(admin)).find((t) => t.id === id)
}

/** The templates currently marked default. */
async function defaults(): Promise<TemplateRow[]> {
	return (await listTemplates(admin)).filter((t) => Number(t.isDefault) === 1)
}

const UUID_V4 =
	/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/

test.describe('creating a template (REQ-TMPL-001)', () => {
	// @e2e admin-templates::create-a-template-targeting-specific-groups
	test('the admin creates a group-targeted template and gets the full object back with 201', async () => {
		const res = await admin.post(`${API}/admin/templates`, {
			data: {
				name: `${STAMP} Marketing`,
				description: 'Standard dashboard for the marketing team',
				targetGroups: ['marketing', 'communications'],
				isDefault: false,
				permissionLevel: 'add_only',
			},
		})
		expect(res.status()).toBe(201)
		const body = (await res.json()) as TemplateRow
		made.push(body.id)

		expect(body.type).toBe('admin_template')
		expect(body.userId).toBeNull()
		expect(body.gridColumns).toBe(12)
		expect(body.name).toBe(`${STAMP} Marketing`)
		expect(body.description).toBe('Standard dashboard for the marketing team')
		expect(body.targetGroups).toEqual(['marketing', 'communications'])
		expect(body.isDefault).toBe(0)
		expect(body.permissionLevel).toBe('add_only')
		expect(typeof body.id).toBe('number')
		expect(body.uuid).toMatch(UUID_V4)

		// Re-read: it is stored as returned.
		const stored = await rowOf(body.id)
		expect(stored?.type).toBe('admin_template')
		expect(stored?.userId).toBeNull()
		expect(stored?.gridColumns).toBe(12)
		expect(stored?.targetGroups).toEqual(['marketing', 'communications'])
	})

	// @e2e admin-templates::create-a-default-template-for-all-users
	test('a default template clears the previous default and reaches a user in no group', async () => {
		const earlier = await seed({
			name: `${STAMP} Earlier default`,
			isDefault: true,
			permissionLevel: 'view_only',
			targetGroups: [],
		})
		expect(Number((await rowOf(earlier.id))?.isDefault)).toBe(1)

		const res = await admin.post(`${API}/admin/templates`, {
			data: {
				name: `${STAMP} Company`,
				isDefault: true,
				permissionLevel: 'view_only',
				targetGroups: [],
			},
		})
		expect(res.status()).toBe(201)
		const company = (await res.json()) as TemplateRow
		made.push(company.id)
		expect(company.isDefault).toBe(1)

		// clearDefaultTemplates(): exactly one default, and it is the new one.
		const now = await defaults()
		expect(now.map((t) => t.id)).toEqual([company.id])
		expect(Number((await rowOf(earlier.id))?.isDefault)).toBe(0)

		// Distributed regardless of group membership: an account in no group
		// at all gets a copy of it on its first access.
		await setAllowUserDashboards(admin, true)
		const nobody = await createPrincipal(admin, 'e2e-tmpl-nogroup')
		try {
			const first = await activeDashboard(nobody.api)
			expect(first.status).toBe(200)
			expect(first.dashboard.basedOnTemplate).toBe(company.id)
			expect(first.dashboard.userId).toBe(nobody.username)
		} finally {
			await removePrincipal(admin, nobody)
			// Hand the flag on so later tests start from "no default of ours".
			await admin.put(`${API}/admin/templates/${company.id}`, {
				data: { isDefault: false },
			})
		}
	})

	// @e2e admin-templates::non-admin-user-cannot-create-templates
	test('an ordinary account is refused with 403 and nothing is created; the admin may', async () => {
		const name = `${STAMP} Refused create`
		const refused = await alice!.api.post(`${API}/admin/templates`, {
			data: { name, permissionLevel: 'add_only' },
		})
		expect(refused.status()).toBe(403)
		expect(
			(await listTemplates(admin)).filter((t) => t.name === name),
		).toHaveLength(0)

		const allowed = await seed({ name, permissionLevel: 'add_only' })
		expect((await listTemplates(admin)).filter((t) => t.name === name)).toEqual([
			expect.objectContaining({ id: allowed.id }),
		])
	})

	// @e2e admin-templates::create-template-with-uuid-generation
	test('every new template carries a distinct UUID v4', async () => {
		const a = await seed({ name: `${STAMP} Uuid A` })
		const b = await seed({ name: `${STAMP} Uuid B` })
		expect(a.uuid).toMatch(UUID_V4)
		expect(b.uuid).toMatch(UUID_V4)
		expect(a.uuid).not.toBe(b.uuid)

		// Unique across ALL dashboards the admin can see, not just templates.
		const visible = await (await admin.get(`${API}/dashboards/visible`)).json()
		const everyUuid = [
			...(await listTemplates(admin)).map((t) => t.uuid),
			...(visible.items as Array<{ uuid: string }>).map((d) => d.uuid),
		]
		expect(everyUuid.filter((u) => u === a.uuid)).toHaveLength(1)
		expect(everyUuid.filter((u) => u === b.uuid)).toHaveLength(1)
	})
})

test.describe('listing templates (REQ-TMPL-002)', () => {
	// @e2e admin-templates::list-all-templates
	test('the admin lists every template with all its fields', async () => {
		const marketing = await seed({
			name: `${STAMP} List Marketing`,
			targetGroups: ['marketing'],
		})
		const company = await seed({
			name: `${STAMP} List Company`,
			isDefault: true,
		})
		const engineering = await seed({
			name: `${STAMP} List Engineering`,
			targetGroups: ['engineering'],
		})
		try {
			const res = await admin.get(`${API}/admin/templates`)
			expect(res.status()).toBe(200)
			const rows = (await res.json()) as TemplateRow[]
			expect(Array.isArray(rows)).toBe(true)
			const mine = rows.filter((t) =>
				[marketing.id, company.id, engineering.id].includes(t.id),
			)
			expect(mine).toHaveLength(3)

			for (const row of mine) {
				for (const field of [
					'id',
					'uuid',
					'name',
					'description',
					'targetGroups',
					'isDefault',
					'permissionLevel',
					'gridColumns',
					'type',
					'basedOnTemplate',
					'isActive',
					'createdAt',
					'updatedAt',
				]) {
					expect(row, `field ${field}`).toHaveProperty(field)
				}
				expect(row.type).toBe('admin_template')
			}
			expect(mine.find((t) => t.id === company.id)?.isDefault).toBe(1)
			expect(mine.find((t) => t.id === marketing.id)?.targetGroups).toEqual([
				'marketing',
			])
		} finally {
			await admin.put(`${API}/admin/templates/${company.id}`, {
				data: { isDefault: false },
			})
		}
	})

	// @e2e admin-templates::non-admin-cannot-list-templates
	test('an ordinary account is refused with 403; the admin is answered', async () => {
		const refused = await alice!.api.get(`${API}/admin/templates`)
		expect(refused.status()).toBe(403)
		expect(JSON.stringify(await refused.json().catch(() => ({})))).not.toContain(
			STAMP,
		)

		const allowed = await admin.get(`${API}/admin/templates`)
		expect(allowed.status()).toBe(200)
	})

	// @e2e admin-templates::templates-filtered-from-user-dashboard-list
	test("a user's dashboard list holds only their own dashboards, no template", async () => {
		await seed({ name: `${STAMP} Hidden 1` })
		await seed({ name: `${STAMP} Hidden 2` })
		await seed({ name: `${STAMP} Hidden 3` })
		for (const n of [1, 2]) {
			const res = await alice!.api.post(`${API}/dashboard`, {
				data: { name: `${STAMP} Alice ${n}` },
			})
			expect(res.status()).toBeLessThan(300)
			userDashboards.push(Number((await res.json()).dashboard.id))
		}

		const res = await alice!.api.get(`${API}/dashboards`)
		expect(res.status()).toBe(200)
		const body = await res.json()
		const items = (body.items ?? body) as TemplateRow[]
		expect(items.filter((d) => d.type === 'admin_template')).toHaveLength(0)
		expect(items.every((d) => d.userId === alice!.username)).toBe(true)
		expect(items.map((d) => d.name).sort()).toEqual(
			expect.arrayContaining([`${STAMP} Alice 1`, `${STAMP} Alice 2`]),
		)
		expect(items.some((d) => String(d.name).startsWith(`${STAMP} Hidden`))).toBe(
			false,
		)
	})
})

test.describe('updating a template (REQ-TMPL-003, -008)', () => {
	// @e2e admin-templates::mark-template-as-default
	// @e2e admin-templates::set-a-template-as-default-when-another-is-already-default
	test('marking a template default takes the flag from the one that had it', async () => {
		const one = await seed({ name: `${STAMP} New Dashboard` })
		const two = await seed({
			name: `${STAMP} Company Dashboard`,
			isDefault: true,
		})
		expect((await defaults()).map((t) => t.id)).toEqual([two.id])

		const res = await admin.put(`${API}/admin/templates/${one.id}`, {
			data: { isDefault: true },
		})
		expect(res.status()).toBe(200)
		expect((await res.json()).isDefault).toBe(1)

		expect(Number((await rowOf(one.id))?.isDefault)).toBe(1)
		expect(Number((await rowOf(two.id))?.isDefault)).toBe(0)
		expect((await defaults()).map((t) => t.id)).toEqual([one.id])
	})

	// @e2e admin-templates::remove-default-status-from-the-only-default-template
	test('clearing the only default leaves no default at all', async () => {
		const only = await seed({ name: `${STAMP} Only default`, isDefault: true })
		expect((await defaults()).map((t) => t.id)).toEqual([only.id])

		const res = await admin.put(`${API}/admin/templates/${only.id}`, {
			data: { isDefault: false },
		})
		expect(res.status()).toBe(200)
		expect((await res.json()).isDefault).toBe(0)
		expect(Number((await rowOf(only.id))?.isDefault)).toBe(0)
		expect(await defaults()).toEqual([])
	})

	// @e2e admin-templates::set-a-template-as-default-when-no-default-exists
	test('with no default, a template set as default becomes it and nothing else changes', async () => {
		expect(await defaults()).toEqual([])
		const bystander = await seed({
			name: `${STAMP} Bystander`,
			targetGroups: ['g-x'],
		})
		const before = await rowOf(bystander.id)

		const fresh = await seed({ name: `${STAMP} First default`, isDefault: true })
		expect(fresh.isDefault).toBe(1)
		expect((await defaults()).map((t) => t.id)).toEqual([fresh.id])

		const after = await rowOf(bystander.id)
		expect(after?.isDefault).toBe(before?.isDefault)
		expect(after?.targetGroups).toEqual(before?.targetGroups)
		expect(after?.updatedAt).toBe(before?.updatedAt)

		await admin.put(`${API}/admin/templates/${fresh.id}`, {
			data: { isDefault: false },
		})
	})

	// @e2e admin-templates::non-admin-cannot-update-templates
	test('an ordinary account is refused with 403 and the template is unchanged; the admin may', async () => {
		const row = await seed({
			name: `${STAMP} Guarded`,
			permissionLevel: 'add_only',
		})
		const refused = await alice!.api.put(`${API}/admin/templates/${row.id}`, {
			data: { name: `${STAMP} Hijacked`, permissionLevel: 'full' },
		})
		expect(refused.status()).toBe(403)
		const unchanged = await rowOf(row.id)
		expect(unchanged?.name).toBe(`${STAMP} Guarded`)
		expect(unchanged?.permissionLevel).toBe('add_only')

		const allowed = await admin.put(`${API}/admin/templates/${row.id}`, {
			data: { name: `${STAMP} Guarded renamed` },
		})
		expect(allowed.status()).toBe(200)
		expect((await rowOf(row.id))?.name).toBe(`${STAMP} Guarded renamed`)
	})
})

test.describe('reading one template (REQ-TMPL-009)', () => {
	// @e2e admin-templates::get-template-with-its-placements
	test('the template comes back with its placements under a separate key', async () => {
		const widgets = [0, 1, 2, 3, 4, 5].map((i) =>
			textWidget(`${STAMP} p${i}`, {
				x: (i % 3) * 4,
				y: Math.floor(i / 3) * 2,
			}),
		)
		const row = await importTemplate(admin, `${STAMP} With six`, { widgets })
		made.push(row.id)

		const res = await admin.get(`${API}/admin/templates/${row.id}`)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(Object.keys(body).sort()).toEqual(['placements', 'template'])
		expect(body.template.id).toBe(row.id)
		expect(body.template.type).toBe('admin_template')
		expect(body.placements).toHaveLength(6)
		expect(
			body.placements.every(
				(p: { dashboardId: number }) => p.dashboardId === row.id,
			),
		).toBe(true)
		expect(
			body.placements
				.map((p: { customTitle: string }) => p.customTitle)
				.sort(),
		).toEqual(widgets.map((w) => w.customTitle).sort())
	})

	// @e2e admin-templates::get-template-with-no-placements
	test('a template without widgets comes back with an empty placements array', async () => {
		const row = await seed({ name: `${STAMP} Empty` })
		const res = await admin.get(`${API}/admin/templates/${row.id}`)
		expect(res.status()).toBe(200)
		const body = await res.json()
		expect(body.template.id).toBe(row.id)
		expect(body.placements).toEqual([])
	})

	// @e2e admin-templates::get-non-template-dashboard-via-template-endpoint
	// The response does not repeat "Not an admin template": ResponseHelper::error
	// answers `{error: 'Operation failed'}` (lib/Controller/ResponseHelper.php:100-113),
	// so the refusal is what a client can observe.
	test('reading a user dashboard through the template endpoint is refused', async () => {
		const created = await alice!.api.post(`${API}/dashboard`, {
			data: { name: `${STAMP} Alice read` },
		})
		const userDashboard = Number((await created.json()).dashboard.id)
		userDashboards.push(userDashboard)

		const res = await admin.get(`${API}/admin/templates/${userDashboard}`)
		expect(res.status()).toBe(404)
		const body = await res.json()
		expect(body).toHaveProperty('error')
		expect(body).not.toHaveProperty('template')
		expect(body).not.toHaveProperty('placements')
	})
})

test.describe('deleting a template (REQ-TMPL-004)', () => {
	// @e2e admin-templates::delete-a-template-with-no-user-copies
	// Placement rows have no read path of their own; the template endpoint is
	// how they are read, and after the delete it answers 404.
	test('the admin deletes a template with widgets and it is gone with them', async () => {
		const row = await importTemplate(admin, `${STAMP} Doomed`, {
			widgets: [textWidget(`${STAMP} doomed w`, { x: 0, y: 0 })],
		})
		made.push(row.id)
		expect((await getTemplate(admin, row.id)).placements).toHaveLength(1)

		const res = await admin.delete(`${API}/admin/templates/${row.id}`)
		expect(res.status()).toBe(200)
		expect(await res.json()).toEqual({ status: 'ok' })

		expect((await admin.get(`${API}/admin/templates/${row.id}`)).status()).toBe(
			404,
		)
		expect(await rowOf(row.id)).toBeUndefined()
	})

	// @e2e admin-templates::non-admin-cannot-delete-templates
	test('an ordinary account is refused with 403 and the template stays; the admin may', async () => {
		const row = await seed({ name: `${STAMP} Undeletable` })
		const refused = await alice!.api.delete(`${API}/admin/templates/${row.id}`)
		expect(refused.status()).toBe(403)
		expect(await rowOf(row.id)).toBeDefined()

		const allowed = await admin.delete(`${API}/admin/templates/${row.id}`)
		expect(allowed.status()).toBe(200)
		expect(await rowOf(row.id)).toBeUndefined()
	})

	// @e2e admin-templates::delete-non-template-dashboard-via-template-endpoint
	// The body does not name the reason (ResponseHelper::error answers
	// `{error: 'Operation failed'}`); the refusal and the untouched dashboard
	// are what a client can observe.
	test('deleting a user dashboard through the template endpoint is refused and it survives', async () => {
		const created = await alice!.api.post(`${API}/dashboard`, {
			data: { name: `${STAMP} Alice keep` },
		})
		const userDashboard = Number((await created.json()).dashboard.id)
		userDashboards.push(userDashboard)

		const res = await admin.delete(`${API}/admin/templates/${userDashboard}`)
		expect(res.status()).toBe(400)
		expect(await res.json()).toHaveProperty('error')

		const still = await alice!.api.get(`${API}/dashboard/${userDashboard}`)
		expect(still.status()).toBe(200)
		expect((await still.json()).dashboard.name).toBe(`${STAMP} Alice keep`)
	})

	// @e2e admin-templates::delete-the-default-template
	test('deleting the default leaves no default and a new account gets no template', async () => {
		const only = await seed({ name: `${STAMP} Doomed default`, isDefault: true })
		expect((await defaults()).map((t) => t.id)).toEqual([only.id])

		const res = await admin.delete(`${API}/admin/templates/${only.id}`)
		expect(res.status()).toBe(200)
		expect(await rowOf(only.id)).toBeUndefined()
		expect(await defaults()).toEqual([])

		await setAllowUserDashboards(admin, true)
		const fresh = await createPrincipal(admin, 'e2e-tmpl-after-default')
		try {
			const first = await activeDashboard(fresh.api)
			expect(first.status).toBe(200)
			expect(first.dashboard.basedOnTemplate ?? null).toBeNull()
			expect(first.dashboard.type).not.toBe('admin_template')
		} finally {
			await removePrincipal(admin, fresh)
		}
	})
})
