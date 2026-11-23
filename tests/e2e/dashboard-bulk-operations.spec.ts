/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the four admin bulk endpoints
 * (openspec/specs/dashboard-bulk-operations, REQ-BULK-001..009 and 011),
 * written for e2e-coverage-programme task 2.1.
 *
 * These scenarios are an HTTP contract: `POST /api/admin/dashboards/bulk-*`
 * answered with a counter envelope, a 400 or a 403. Every test seeds real
 * dashboards through the API, calls the endpoint, asserts the exact status
 * and body the scenario names, and then RE-READS the instance to show what
 * was (or was not) written. A refusal that answers 403 while deleting anyway,
 * or a dry run that reports "would delete 3" and deletes 3, both pass a
 * status check and fail here. The admin UI over the same endpoints is
 * covered in `dashboard-bulk-operations-ui.spec.ts`.
 *
 * PRINCIPALS. The admin acts over basic auth (`adminContext`), never through
 * the browser session, so password confirmation cannot expire mid-run. The
 * "insufficient permissions" scenarios need a dashboard an ADMIN cannot act
 * on: a throwaway non-admin's private, unshared dashboard is exactly that
 * (PermissionService::resolveAccessLevel answers null for it). The same
 * throwaway account is the least-privileged principal for the non-admin
 * refusal.
 *
 * INSTANCE STATE. Personal dashboards are switched on for the run and the
 * `bulk_operation_max_per_request` app value is cleared so the default cap
 * of 500 applies; both are restored in afterAll.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-bulk-operations::delete-three-valid-dashboards
 *   @e2e dashboard-bulk-operations::bulk-delete-with-one-already-deleted-dashboard
 *   @e2e dashboard-bulk-operations::bulk-delete-parent-with-children-and-cascadefalse-default
 *   @e2e dashboard-bulk-operations::bulk-delete-parent-with-children-and-cascadetrue-opt-in
 *   @e2e dashboard-bulk-operations::bulk-delete-with-insufficient-permissions
 *   @e2e dashboard-bulk-operations::bulk-delete-request-exceeds-size-cap
 *   @e2e dashboard-bulk-operations::dry-run-bulk-delete
 *   @e2e dashboard-bulk-operations::move-three-dashboards-under-a-new-parent
 *   @e2e dashboard-bulk-operations::move-dashboards-to-root-null-parent
 *   @e2e dashboard-bulk-operations::bulk-move-detects-cycle-would-create-circular-parent-child
 *   @e2e dashboard-bulk-operations::bulk-move-with-no-op-parent-already-matches-target
 *   @e2e dashboard-bulk-operations::bulk-move-with-insufficient-permissions
 *   @e2e dashboard-bulk-operations::dry-run-bulk-move
 *   @e2e dashboard-bulk-operations::publish-three-draft-dashboards
 *   @e2e dashboard-bulk-operations::bulk-status-with-idempotency-dashboards-already-published
 *   @e2e dashboard-bulk-operations::schedule-dashboards-for-future-publish-date
 *   @e2e dashboard-bulk-operations::bulk-status-to-scheduled-without-publishat-date
 *   @e2e dashboard-bulk-operations::invalid-publication-status-value
 *   @e2e dashboard-bulk-operations::bulk-status-with-insufficient-permissions
 *   @e2e dashboard-bulk-operations::dry-run-bulk-status
 *   @e2e dashboard-bulk-operations::re-index-three-valid-dashboards
 *   @e2e dashboard-bulk-operations::bulk-re-index-with-insufficient-permissions
 *   @e2e dashboard-bulk-operations::dry-run-bulk-re-index
 *   @e2e dashboard-bulk-operations::re-index-request-exceeds-size-cap
 *   @e2e dashboard-bulk-operations::request-within-cap-is-accepted
 *   @e2e dashboard-bulk-operations::request-exceeds-cap-is-rejected
 *   @e2e dashboard-bulk-operations::admin-modifies-bulk-operation-cap
 *   @e2e dashboard-bulk-operations::cap-of-zero-or-negative-is-invalid
 *   @e2e dashboard-bulk-operations::delete-already-deleted-dashboard
 *   @e2e dashboard-bulk-operations::move-to-same-parent
 *   @e2e dashboard-bulk-operations::set-to-same-status
 *   @e2e dashboard-bulk-operations::dry-run-returns-wouldx-counts-instead-of-x-counts
 *   @e2e dashboard-bulk-operations::dry-run-validation-is-identical-to-real-run
 *   @e2e dashboard-bulk-operations::dryrun-defaults-to-false
 *   @e2e dashboard-bulk-operations::admin-has-full-permission-on-all-dashboards
 *   @e2e dashboard-bulk-operations::admin-lacks-permission-on-one-dashboard
 *   @e2e dashboard-bulk-operations::non-admin-user-cannot-call-bulk-endpoints
 *   @e2e dashboard-bulk-operations::permission-check-happens-before-size-validation
 *   @e2e dashboard-bulk-operations::dry-run-respects-same-permission-model
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
	seedDashboardWithWidget,
	shareWith,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const BULK = `${API}/admin/dashboards`
const RUN = Date.now()
const PREFIX = `E2E Bulk ${RUN}`
const CAP_CONFIG =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/bulk_operation_max_per_request'
const ACTIVITY = '/ocs/v2.php/apps/activity/api/v2/activity/all'
const LOG_DOWNLOAD = '/index.php/settings/admin/log/download'
const CAP_WARNING = 'Invalid bulk operation cap; falling back to default'

type Op = 'delete' | 'move' | 'status' | 'reindex'

interface Dash {
	id: number
	uuid: string
	name: string
}

interface BulkAnswer {
	status: number
	body: any
}

let admin: APIRequestContext
let probe: Principal | null = null
/** A dashboard the admin has NO access level on: the probe's own, unshared. */
let privateDash: Dash
const created: Dash[] = []
let settingsBefore: Record<string, unknown> | null = null
let capBefore: string | null = null

test.describe.configure({ mode: 'serial' })

/**
 * Create a dashboard owned by the admin, optionally under a parent.
 *
 * @param label a readable suffix for the name.
 * @param parentUuid the parent, or omitted for a root dashboard.
 * @return the created dashboard.
 */
async function createDash(label: string, parentUuid?: string): Promise<Dash> {
	const name = `${PREFIX} ${label}`
	const data: Record<string, unknown> = { name }
	if (parentUuid !== undefined) {
		data.parentUuid = parentUuid
	}
	const res = await admin.post(`${API}/dashboard`, { data })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const dash = (await res.json()).dashboard
	const out = { id: Number(dash.id), uuid: String(dash.uuid), name }
	expect(out.uuid, 'the create answer carries a uuid').toBeTruthy()
	created.push(out)
	return out
}

/**
 * Create several root dashboards.
 *
 * @param label a readable suffix shared by the batch.
 * @param count how many.
 * @return the dashboards, in creation order.
 */
async function createMany(label: string, count: number): Promise<Dash[]> {
	const out: Dash[] = []
	for (let i = 1; i <= count; i++) {
		out.push(await createDash(`${label} ${i}`))
	}
	return out
}

/**
 * Read a dashboard as `api`: its wire form, or null when the row is gone.
 *
 * @param api who reads.
 * @param id the dashboard id.
 * @return the serialized dashboard, or null on 404.
 */
async function readDash(api: APIRequestContext, id: number): Promise<any> {
	const res = await api.get(`${API}/dashboard/${id}`)
	if (res.status() === 404) {
		return null
	}
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).dashboard
}

/**
 * Call one bulk endpoint as `api`.
 *
 * @param api who calls.
 * @param op the operation.
 * @param data the JSON body.
 * @param query an optional query string, without the `?`.
 * @return the status and the parsed body.
 */
async function bulk(
	api: APIRequestContext,
	op: Op,
	data: Record<string, unknown>,
	query = '',
): Promise<BulkAnswer> {
	const url = `${BULK}/bulk-${op}${query === '' ? '' : `?${query}`}`
	const res = await api.post(url, { data, timeout: 120_000 })
	const text = await res.text()
	let body: any
	try {
		body = JSON.parse(text)
	} catch {
		body = text
	}
	return { status: res.status(), body }
}

/** UUIDs that name no dashboard at all. */
function fakeUuids(count: number): string[] {
	return Array.from({ length: count }, () => randomUUID())
}

/** The rows of a list endpoint, whether it answers bare or `{items}`. */
async function listUuids(api: APIRequestContext, path: string): Promise<string[]> {
	const res = await api.get(`${API}${path}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const rows = Array.isArray(body) ? body : (body.items ?? [])
	return rows.map((row: any) => String(row.uuid))
}

/** Wait past the one-second resolution of `updatedAt`. */
async function tick(): Promise<void> {
	await new Promise((resolve) => setTimeout(resolve, 1_200))
}

/** The id of the newest activity row the admin can see, 0 when none. */
async function latestActivityId(): Promise<number> {
	const res = await admin.get(`${ACTIVITY}?format=json&limit=1`)
	if (res.status() === 304) {
		return 0
	}
	expect(res.status(), 'the activity stream must be readable').toBe(200)
	const rows = (await res.json())?.ocs?.data ?? []
	return rows.length > 0 ? Number(rows[0].activity_id) : 0
}

/** The admin's activity rows newer than `since`, oldest first. */
async function activitiesSince(since: number): Promise<any[]> {
	const res = await admin.get(
		`${ACTIVITY}?format=json&since=${since}&sort=asc&limit=500`,
	)
	if (res.status() === 304) {
		return []
	}
	expect(res.status(), 'the activity stream must be readable').toBe(200)
	return (await res.json())?.ocs?.data ?? []
}

/** The raw app value of the cap, or null when unset. */
async function readCap(): Promise<string | null> {
	const res = await admin.get(`${CAP_CONFIG}?format=json`)
	if (!res.ok()) {
		return null
	}
	const value = (await res.json())?.ocs?.data?.data
	return typeof value === 'string' && value !== '' ? value : null
}

/** Set the cap app value, as an admin would with `occ config:app:set`. */
async function writeCap(value: string): Promise<void> {
	const res = await admin.post(`${CAP_CONFIG}?format=json`, { form: { value } })
	expect(res.ok(), `set the cap -> ${res.status()}`).toBe(true)
}

/** Remove the cap app value so the built-in default applies. */
async function clearCap(): Promise<void> {
	await admin.delete(`${CAP_CONFIG}?format=json`)
	expect(await readCap()).toBeNull()
}

/** How often the invalid-cap warning appears in nextcloud.log. */
async function capWarningCount(): Promise<number> {
	const res = await admin.get(LOG_DOWNLOAD, { timeout: 120_000 })
	expect(res.status(), 'the admin must be able to download the log').toBe(200)
	return (await res.text()).split(CAP_WARNING).length - 1
}

/**
 * Assert every dashboard still exists, read as the admin.
 *
 * @param dashboards the dashboards.
 * @return nothing.
 */
async function expectAllExist(dashboards: Dash[]): Promise<void> {
	for (const dash of dashboards) {
		expect(
			await readDash(admin, dash.id),
			`${dash.name} must exist`,
		).not.toBeNull()
	}
}

/**
 * Assert every dashboard is gone, read as the admin.
 *
 * @param dashboards the dashboards.
 * @return nothing.
 */
async function expectAllGone(dashboards: Dash[]): Promise<void> {
	for (const dash of dashboards) {
		expect(
			await readDash(admin, dash.id),
			`${dash.name} must be gone`,
		).toBeNull()
	}
}

/** The probe's private dashboard must still be there, read as its owner. */
async function expectPrivateIntact(): Promise<void> {
	expect(await readDash(probe!.api, privateDash.id)).not.toBeNull()
}

test.beforeAll(async () => {
	test.setTimeout(180_000)
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)

	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status()).toBe(200)
	settingsBefore = await settings.json()
	const allow = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDashboards: true, allowMultipleDashboards: true },
	})
	expect(allow.status(), await allow.text()).toBe(200)

	capBefore = await readCap()
	await clearCap()

	probe = await createPrincipal(admin, 'e2e-bulk')
	await assertActingAs(probe.api, probe.username)
	const res = await probe.api.post(`${API}/dashboard`, {
		data: { name: `${PREFIX} private` },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const dash = (await res.json()).dashboard
	privateDash = {
		id: Number(dash.id),
		uuid: String(dash.uuid),
		name: `${PREFIX} private`,
	}
	// The premise of every insufficient-permission scenario: the admin has
	// no access level on this dashboard.
	expect(await readDash(admin, privateDash.id)).toBeNull()
})

test.afterAll(async () => {
	for (const dash of [...created].reverse()) {
		await admin.delete(`${API}/dashboard/${dash.id}`)
	}
	if (probe !== null && privateDash) {
		await probe.api.delete(`${API}/dashboard/${privateDash.id}`)
	}
	await removePrincipal(admin, probe)
	if (capBefore === null) {
		await admin.delete(`${CAP_CONFIG}?format=json`)
	} else {
		await admin.post(`${CAP_CONFIG}?format=json`, { form: { value: capBefore } })
	}
	if (settingsBefore !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: {
				allowUserDashboards: settingsBefore.allowUserDashboards === true,
				allowMultipleDashboards:
					settingsBefore.allowMultipleDashboards !== false,
			},
		})
	}
	await admin.dispose()
})

test.describe('bulk delete (REQ-BULK-001, 007, 008)', () => {
	test('three valid dashboards are hard-deleted with one audit event', async () => {
		// @e2e dashboard-bulk-operations::delete-three-valid-dashboards
		const seeded = []
		for (let i = 1; i <= 3; i++) {
			const one = await seedDashboardWithWidget(admin, `${PREFIX} three ${i}`)
			created.push({
				id: one.id,
				uuid: one.uuid,
				name: `${PREFIX} three ${i}`,
			})
			seeded.push(one)
		}
		// Shared with a second user, so "not listed for ANY user" is a claim
		// about someone who could see it before.
		await publishForRecipients(admin, seeded[0].id)
		await shareWith(admin, seeded[0].id, probe!.username, 'view_only')
		expect(await listUuids(probe!.api, '/dashboards/visible')).toContain(
			seeded[0].uuid,
		)

		const since = await latestActivityId()
		const answer = await bulk(admin, 'delete', {
			dashboardUuids: seeded.map((d) => d.uuid),
		})

		expect(answer.status, JSON.stringify(answer.body)).toBe(200)
		expect(answer.body).toEqual({
			deletedCount: 3,
			skippedCount: 0,
			errors: [],
			dryRun: false,
		})

		for (const one of seeded) {
			expect(await readDash(admin, one.id)).toBeNull()
			// The placement row is gone too: the tile-click endpoint answers
			// 404 only when the placement itself no longer exists (an orphan
			// placement of a deleted dashboard still answers 204).
			const click = await admin.post(`${API}/tile-click/${one.placementId}`)
			expect(click.status()).toBe(404)
		}

		const uuids = seeded.map((d) => d.uuid)
		for (const path of ['/dashboards', '/dashboards/visible']) {
			const listed = await listUuids(admin, path)
			for (const uuid of uuids) {
				expect(listed).not.toContain(uuid)
			}
		}
		const probeListed = await listUuids(probe!.api, '/dashboards/visible')
		for (const uuid of uuids) {
			expect(probeListed).not.toContain(uuid)
		}

		const bulkEvents = (await activitiesSince(since)).filter(
			(row) => row.app === 'launchpad' && row.object_name === 'bulk-delete',
		)
		expect(
			bulkEvents,
			'exactly one bulk activity event, not three',
		).toHaveLength(1)
	})

	test('an already-deleted dashboard in the batch is skipped as already_deleted', async () => {
		// @e2e dashboard-bulk-operations::bulk-delete-with-one-already-deleted-dashboard
		const [one, two, three] = await createMany('half-gone', 3)
		const gone = await admin.delete(`${API}/dashboard/${two.id}`)
		expect(gone.status(), await gone.text()).toBeLessThan(300)
		expect(await readDash(admin, two.id)).toBeNull()

		const answer = await bulk(admin, 'delete', {
			dashboardUuids: [one.uuid, two.uuid, three.uuid],
		})

		expect(answer.status).toBe(200)
		expect(answer.body).toMatchObject({
			deletedCount: 2,
			skippedCount: 1,
			errors: [{ uuid: two.uuid, reason: 'already_deleted' }],
		})
		expect(answer.body.errors).toHaveLength(1)
		await expectAllGone([one, three])
	})

	test('a parent with children is skipped without cascade', async () => {
		// @e2e dashboard-bulk-operations::bulk-delete-parent-with-children-and-cascadefalse-default
		const parent = await createDash('nocascade parent')
		const children: Dash[] = []
		for (let i = 1; i <= 5; i++) {
			children.push(await createDash(`nocascade child ${i}`, parent.uuid))
		}

		const answer = await bulk(admin, 'delete', { dashboardUuids: [parent.uuid] })

		expect(answer.status).toBe(200)
		expect(answer.body).toMatchObject({ deletedCount: 0, skippedCount: 1 })
		expect(answer.body.errors).toEqual([
			{ uuid: parent.uuid, reason: 'dashboard_has_children', childCount: 5 },
		])
		await expectAllExist([parent, ...children])
		for (const child of children) {
			expect((await readDash(admin, child.id)).parentUuid).toBe(parent.uuid)
		}
	})

	test('cascade=true deletes the parent and every child', async () => {
		// @e2e dashboard-bulk-operations::bulk-delete-parent-with-children-and-cascadetrue-opt-in
		const parent = await createDash('cascade parent')
		const children: Dash[] = []
		for (let i = 1; i <= 5; i++) {
			children.push(await createDash(`cascade child ${i}`, parent.uuid))
		}

		const answer = await bulk(
			admin,
			'delete',
			{ dashboardUuids: [parent.uuid] },
			'cascade=true',
		)

		expect(answer.status, JSON.stringify(answer.body)).toBe(200)
		expect(answer.body).toMatchObject({
			deletedCount: 6,
			skippedCount: 0,
			errors: [],
		})
		await expectAllGone([parent, ...children])
	})

	test('one dashboard the admin cannot act on refuses the whole batch', async () => {
		// @e2e dashboard-bulk-operations::bulk-delete-with-insufficient-permissions
		// @e2e dashboard-bulk-operations::admin-lacks-permission-on-one-dashboard
		const mine = await createMany('denied-batch', 4)
		const batch = [
			mine[0].uuid,
			mine[1].uuid,
			privateDash.uuid,
			mine[2].uuid,
			mine[3].uuid,
		]

		const answer = await bulk(admin, 'delete', { dashboardUuids: batch })

		expect(answer.status).toBe(403)
		expect(answer.body.deniedUuids).toEqual([privateDash.uuid])
		// All-or-nothing: not even the four the admin may delete went.
		await expectAllExist(mine)
		await expectPrivateIntact()

		// The same admin, without the offending uuid, is allowed.
		const allowed = await bulk(admin, 'delete', {
			dashboardUuids: mine.map((d) => d.uuid),
		})
		expect(allowed.status).toBe(200)
		expect(allowed.body.deletedCount).toBe(4)
		await expectAllGone(mine)
	})

	test('a dry run deletes nothing and reports would-counts', async () => {
		// @e2e dashboard-bulk-operations::dry-run-bulk-delete
		// @e2e dashboard-bulk-operations::dry-run-returns-wouldx-counts-instead-of-x-counts
		const three = await createMany('dry-delete', 3)

		const answer = await bulk(
			admin,
			'delete',
			{ dashboardUuids: three.map((d) => d.uuid) },
			'dryRun=true',
		)

		expect(answer.status).toBe(200)
		expect(answer.body).toEqual({
			wouldDeleteCount: 3,
			wouldSkipCount: 0,
			errors: [],
			dryRun: true,
		})
		await expectAllExist(three)
		const listed = await listUuids(admin, '/dashboards')
		for (const dash of three) {
			expect(listed).toContain(dash.uuid)
		}
	})

	test('a dry run is refused exactly like the real run', async () => {
		// @e2e dashboard-bulk-operations::dry-run-validation-is-identical-to-real-run
		// @e2e dashboard-bulk-operations::dry-run-respects-same-permission-model
		const mine = await createMany('dry-denied', 2)
		const batch = [mine[0].uuid, privateDash.uuid, mine[1].uuid]

		const dry = await bulk(
			admin,
			'delete',
			{ dashboardUuids: batch },
			'dryRun=true',
		)
		const real = await bulk(admin, 'delete', { dashboardUuids: batch })

		expect(dry.status).toBe(403)
		expect(real.status).toBe(403)
		expect(dry.body.deniedUuids).toEqual([privateDash.uuid])
		expect(dry.body).toEqual(real.body)
		await expectAllExist(mine)
		await expectPrivateIntact()

		const allowed = await bulk(
			admin,
			'delete',
			{ dashboardUuids: mine.map((d) => d.uuid) },
			'dryRun=true',
		)
		expect(allowed.status).toBe(200)
		expect(allowed.body.wouldDeleteCount).toBe(2)
	})

	test('without dryRun the request is a real run', async () => {
		// @e2e dashboard-bulk-operations::dryrun-defaults-to-false
		const [dash] = await createMany('default-real', 1)

		const answer = await bulk(admin, 'delete', { dashboardUuids: [dash.uuid] })

		expect(answer.status).toBe(200)
		expect(answer.body.dryRun).toBe(false)
		expect(answer.body.deletedCount).toBe(1)
		expect(answer.body).not.toHaveProperty('wouldDeleteCount')
		expect(await readDash(admin, dash.id)).toBeNull()
	})

	test('a row that no longer exists is counted as skipped, already_deleted', async () => {
		// @e2e dashboard-bulk-operations::delete-already-deleted-dashboard
		const [dash] = await createMany('already-gone', 1)
		const gone = await admin.delete(`${API}/dashboard/${dash.id}`)
		expect(gone.status(), await gone.text()).toBeLessThan(300)

		const answer = await bulk(admin, 'delete', { dashboardUuids: [dash.uuid] })

		expect(answer.status).toBe(200)
		expect(answer.body).toEqual({
			deletedCount: 0,
			skippedCount: 1,
			errors: [{ uuid: dash.uuid, reason: 'already_deleted' }],
			dryRun: false,
		})
	})

	test('an admin acting on dashboards she owns gets 200 and the deletes', async () => {
		// @e2e dashboard-bulk-operations::admin-has-full-permission-on-all-dashboards
		const five = await createMany('full-permission', 5)

		const answer = await bulk(admin, 'delete', {
			dashboardUuids: five.map((d) => d.uuid),
		})

		expect(answer.status).toBe(200)
		expect(answer.body.deletedCount).toBe(5)
		await expectAllGone(five)
	})
})

test.describe('bulk move (REQ-BULK-002, 007)', () => {
	test('three dashboards move under a new parent', async () => {
		// @e2e dashboard-bulk-operations::move-three-dashboards-under-a-new-parent
		const oldParent = await createDash('move old-parent')
		const newParent = await createDash('move new-parent')
		const kids: Dash[] = []
		for (let i = 1; i <= 3; i++) {
			kids.push(await createDash(`move child ${i}`, oldParent.uuid))
		}

		const answer = await bulk(admin, 'move', {
			dashboardUuids: kids.map((d) => d.uuid),
			parentUuid: newParent.uuid,
		})

		expect(answer.status, JSON.stringify(answer.body)).toBe(200)
		expect(answer.body).toMatchObject({
			movedCount: 3,
			skippedCount: 0,
			errors: [],
		})
		for (const kid of kids) {
			expect((await readDash(admin, kid.id)).parentUuid).toBe(newParent.uuid)
		}
	})

	test('a null parent moves dashboards to the root', async () => {
		// @e2e dashboard-bulk-operations::move-dashboards-to-root-null-parent
		const parent = await createDash('root some-parent')
		const kids: Dash[] = []
		for (let i = 1; i <= 3; i++) {
			kids.push(await createDash(`root child ${i}`, parent.uuid))
		}

		const answer = await bulk(admin, 'move', {
			dashboardUuids: kids.map((d) => d.uuid),
			parentUuid: null,
		})

		expect(answer.status, JSON.stringify(answer.body)).toBe(200)
		expect(answer.body).toMatchObject({
			movedCount: 3,
			skippedCount: 0,
			errors: [],
		})
		for (const kid of kids) {
			expect((await readDash(admin, kid.id)).parentUuid).toBeNull()
		}
	})

	test('a move that would create a cycle is refused for that dashboard', async () => {
		// @e2e dashboard-bulk-operations::bulk-move-detects-cycle-would-create-circular-parent-child
		const a = await createDash('cycle A')
		const b = await createDash('cycle B', a.uuid)
		const c = await createDash('cycle C', b.uuid)

		const answer = await bulk(admin, 'move', {
			dashboardUuids: [a.uuid],
			parentUuid: c.uuid,
		})

		expect(answer.status).toBe(200)
		expect(answer.body).toMatchObject({ movedCount: 0, skippedCount: 0 })
		expect(answer.body.errors).toHaveLength(1)
		expect(answer.body.errors[0]).toMatchObject({
			uuid: a.uuid,
			reason: 'cycle_detected',
		})
		expect(typeof answer.body.errors[0].detail).toBe('string')
		expect(answer.body.errors[0].detail).not.toBe('')
		expect((await readDash(admin, a.id)).parentUuid).toBeNull()
	})

	test('moving to the parent a dashboard already has is a skipped no-op', async () => {
		// @e2e dashboard-bulk-operations::bulk-move-with-no-op-parent-already-matches-target
		// @e2e dashboard-bulk-operations::move-to-same-parent
		const target = await createDash('noop target-parent')
		const child = await createDash('noop child', target.uuid)
		const before = await readDash(admin, child.id)
		await tick()

		const answer = await bulk(admin, 'move', {
			dashboardUuids: [child.uuid],
			parentUuid: target.uuid,
		})

		expect(answer.status).toBe(200)
		expect(answer.body).toMatchObject({
			movedCount: 0,
			skippedCount: 1,
			errors: [{ uuid: child.uuid, reason: 'parent_already_matches' }],
		})
		expect(answer.body.errors).toHaveLength(1)
		const after = await readDash(admin, child.id)
		expect(after.parentUuid).toBe(target.uuid)
		// No write at all: a real move stamps updatedAt.
		expect(after.updatedAt).toBe(before.updatedAt)
	})

	test('one dashboard the admin cannot move refuses the whole move', async () => {
		// @e2e dashboard-bulk-operations::bulk-move-with-insufficient-permissions
		const parent = await createDash('denied-move parent')
		const mine = await createMany('denied-move', 2)

		const answer = await bulk(admin, 'move', {
			dashboardUuids: [mine[0].uuid, privateDash.uuid, mine[1].uuid],
			parentUuid: parent.uuid,
		})

		expect(answer.status).toBe(403)
		expect(answer.body.deniedUuids).toEqual([privateDash.uuid])
		for (const dash of mine) {
			expect((await readDash(admin, dash.id)).parentUuid).toBeNull()
		}
		expect((await readDash(probe!.api, privateDash.id)).parentUuid).toBeNull()

		const allowed = await bulk(admin, 'move', {
			dashboardUuids: mine.map((d) => d.uuid),
			parentUuid: parent.uuid,
		})
		expect(allowed.status).toBe(200)
		expect(allowed.body.movedCount).toBe(2)
	})

	test('a dry-run move writes no parent', async () => {
		// @e2e dashboard-bulk-operations::dry-run-bulk-move
		const parent = await createDash('dry-move parent')
		const three = await createMany('dry-move', 3)

		const answer = await bulk(
			admin,
			'move',
			{ dashboardUuids: three.map((d) => d.uuid), parentUuid: parent.uuid },
			'dryRun=true',
		)

		expect(answer.status).toBe(200)
		expect(answer.body).toEqual({
			wouldMoveCount: 3,
			wouldSkipCount: 0,
			errors: [],
			dryRun: true,
		})
		for (const dash of three) {
			expect((await readDash(admin, dash.id)).parentUuid).toBeNull()
		}
	})
})

test.describe('bulk publication status (REQ-BULK-003, 007)', () => {
	test('three drafts are published and stamped', async () => {
		// @e2e dashboard-bulk-operations::publish-three-draft-dashboards
		const three = await createMany('publish', 3)
		for (const dash of three) {
			expect((await readDash(admin, dash.id)).publicationStatus).toBe('draft')
		}

		const answer = await bulk(admin, 'status', {
			dashboardUuids: three.map((d) => d.uuid),
			publicationStatus: 'published',
		})

		expect(answer.status, JSON.stringify(answer.body)).toBe(200)
		expect(answer.body).toMatchObject({
			updatedCount: 3,
			skippedCount: 0,
			errors: [],
		})
		for (const dash of three) {
			const row = await readDash(admin, dash.id)
			expect(row.publicationStatus).toBe('published')
			expect(row.publishedAt).toBeTruthy()
		}
	})

	test('dashboards already published are all skipped', async () => {
		// @e2e dashboard-bulk-operations::bulk-status-with-idempotency-dashboards-already-published
		const three = await createMany('already-published', 3)
		for (const dash of three) {
			const res = await admin.post(`${API}/dashboards/${dash.uuid}/publish`)
			expect(res.status(), await res.text()).toBe(200)
		}

		const answer = await bulk(admin, 'status', {
			dashboardUuids: three.map((d) => d.uuid),
			publicationStatus: 'published',
		})

		expect(answer.status).toBe(200)
		expect(answer.body).toMatchObject({ updatedCount: 0, skippedCount: 3 })
		expect(answer.body.errors).toEqual(
			three.map((d) => ({ uuid: d.uuid, reason: 'status_already_matches' })),
		)
	})

	test('scheduling sets the status and the parsed publishAt', async () => {
		// @e2e dashboard-bulk-operations::schedule-dashboards-for-future-publish-date
		// The scenario's own example date (2026-06-15) is in the past by now,
		// and a past date is refused; the same format, years ahead, is what
		// the scenario means by "a future publish date".
		const three = await createMany('schedule', 3)

		const answer = await bulk(admin, 'status', {
			dashboardUuids: three.map((d) => d.uuid),
			publicationStatus: 'scheduled',
			publishAt: '2036-06-15T10:00:00Z',
		})

		expect(answer.status, JSON.stringify(answer.body)).toBe(200)
		expect(answer.body).toMatchObject({
			updatedCount: 3,
			skippedCount: 0,
			errors: [],
		})
		for (const dash of three) {
			const row = await readDash(admin, dash.id)
			expect(row.publicationStatus).toBe('scheduled')
			expect(row.publishAt).toBe('2036-06-15 10:00:00')
		}
	})

	test('scheduled without publishAt is refused with 400', async () => {
		// @e2e dashboard-bulk-operations::bulk-status-to-scheduled-without-publishat-date
		const two = await createMany('schedule-no-date', 2)

		const answer = await bulk(admin, 'status', {
			dashboardUuids: two.map((d) => d.uuid),
			publicationStatus: 'scheduled',
		})

		expect(answer.status).toBe(400)
		expect(answer.body.error).toBe(
			'publishAt is required when publicationStatus is "scheduled"',
		)
		for (const dash of two) {
			const row = await readDash(admin, dash.id)
			expect(row.publicationStatus).toBe('draft')
			expect(row.publishAt).toBeNull()
		}
	})

	test('an unknown status value is refused with 400', async () => {
		// @e2e dashboard-bulk-operations::invalid-publication-status-value
		const two = await createMany('invalid-status', 2)

		const answer = await bulk(admin, 'status', {
			dashboardUuids: two.map((d) => d.uuid),
			publicationStatus: 'invalid',
		})

		expect(answer.status).toBe(400)
		expect(answer.body.error).toBe(
			'publicationStatus must be one of: draft, published, scheduled',
		)
		for (const dash of two) {
			expect((await readDash(admin, dash.id)).publicationStatus).toBe('draft')
		}
	})

	test('one dashboard the admin cannot modify refuses the whole status change', async () => {
		// @e2e dashboard-bulk-operations::bulk-status-with-insufficient-permissions
		const mine = await createMany('denied-status', 2)

		const answer = await bulk(admin, 'status', {
			dashboardUuids: [mine[0].uuid, privateDash.uuid, mine[1].uuid],
			publicationStatus: 'published',
		})

		expect(answer.status).toBe(403)
		expect(answer.body.deniedUuids).toEqual([privateDash.uuid])
		for (const dash of mine) {
			expect((await readDash(admin, dash.id)).publicationStatus).toBe('draft')
		}
		expect((await readDash(probe!.api, privateDash.id)).publicationStatus).toBe(
			'draft',
		)

		const allowed = await bulk(admin, 'status', {
			dashboardUuids: mine.map((d) => d.uuid),
			publicationStatus: 'published',
		})
		expect(allowed.status).toBe(200)
		expect(allowed.body.updatedCount).toBe(2)
	})

	test('a dry-run status change writes no status', async () => {
		// @e2e dashboard-bulk-operations::dry-run-bulk-status
		const three = await createMany('dry-status', 3)

		const answer = await bulk(
			admin,
			'status',
			{
				dashboardUuids: three.map((d) => d.uuid),
				publicationStatus: 'published',
			},
			'dryRun=true',
		)

		expect(answer.status).toBe(200)
		expect(answer.body).toEqual({
			wouldUpdateCount: 3,
			wouldSkipCount: 0,
			errors: [],
			dryRun: true,
		})
		for (const dash of three) {
			expect((await readDash(admin, dash.id)).publicationStatus).toBe('draft')
		}
	})

	test('setting the status a dashboard already has is a skipped no-op', async () => {
		// @e2e dashboard-bulk-operations::set-to-same-status
		const [dash] = await createMany('same-status', 1)
		const publish = await admin.post(`${API}/dashboards/${dash.uuid}/publish`)
		expect(publish.status(), await publish.text()).toBe(200)
		const before = await readDash(admin, dash.id)
		await tick()

		const answer = await bulk(admin, 'status', {
			dashboardUuids: [dash.uuid],
			publicationStatus: 'published',
		})

		expect(answer.status).toBe(200)
		expect(answer.body).toMatchObject({
			updatedCount: 0,
			skippedCount: 1,
			errors: [{ uuid: dash.uuid, reason: 'status_already_matches' }],
		})
		expect(answer.body.errors).toHaveLength(1)
		const after = await readDash(admin, dash.id)
		expect(after.publicationStatus).toBe('published')
		expect(after.updatedAt).toBe(before.updatedAt)
	})
})

test.describe('bulk re-index (REQ-BULK-004)', () => {
	test('three dashboards are marked dirty', async () => {
		// @e2e dashboard-bulk-operations::re-index-three-valid-dashboards
		const three = await createMany('reindex', 3)
		const before = await Promise.all(three.map((d) => readDash(admin, d.id)))
		await tick()

		const answer = await bulk(admin, 'reindex', {
			dashboardUuids: three.map((d) => d.uuid),
		})

		expect(answer.status).toBe(200)
		expect(answer.body).toEqual({ reindexedCount: 3, errors: [], dryRun: false })
		for (let i = 0; i < three.length; i++) {
			const after = await readDash(admin, three[i].id)
			expect(after.updatedAt, 'marked dirty by touching updatedAt').not.toBe(
				before[i].updatedAt,
			)
		}
	})

	test('one dashboard the admin cannot access refuses the whole re-index', async () => {
		// @e2e dashboard-bulk-operations::bulk-re-index-with-insufficient-permissions
		const mine = await createMany('denied-reindex', 2)
		const before = await Promise.all(mine.map((d) => readDash(admin, d.id)))
		const privateBefore = await readDash(probe!.api, privateDash.id)
		await tick()

		const answer = await bulk(admin, 'reindex', {
			dashboardUuids: [mine[0].uuid, mine[1].uuid, privateDash.uuid],
		})

		expect(answer.status).toBe(403)
		expect(answer.body.deniedUuids).toEqual([privateDash.uuid])
		for (let i = 0; i < mine.length; i++) {
			expect((await readDash(admin, mine[i].id)).updatedAt).toBe(
				before[i].updatedAt,
			)
		}
		expect((await readDash(probe!.api, privateDash.id)).updatedAt).toBe(
			privateBefore.updatedAt,
		)

		const allowed = await bulk(admin, 'reindex', {
			dashboardUuids: mine.map((d) => d.uuid),
		})
		expect(allowed.status).toBe(200)
		expect(allowed.body.reindexedCount).toBe(2)
	})

	test('a dry-run re-index touches nothing', async () => {
		// @e2e dashboard-bulk-operations::dry-run-bulk-re-index
		const three = await createMany('dry-reindex', 3)
		const before = await Promise.all(three.map((d) => readDash(admin, d.id)))
		await tick()

		const answer = await bulk(
			admin,
			'reindex',
			{ dashboardUuids: three.map((d) => d.uuid) },
			'dryRun=true',
		)

		expect(answer.status).toBe(200)
		expect(answer.body).toEqual({
			wouldReindexCount: 3,
			errors: [],
			dryRun: true,
		})
		for (let i = 0; i < three.length; i++) {
			expect((await readDash(admin, three[i].id)).updatedAt).toBe(
				before[i].updatedAt,
			)
		}
	})

	test('a re-index over the cap is refused with 400', async () => {
		// @e2e dashboard-bulk-operations::re-index-request-exceeds-size-cap
		test.setTimeout(180_000)
		const three = await createMany('cap-reindex', 3)
		const before = await Promise.all(three.map((d) => readDash(admin, d.id)))
		await tick()

		const answer = await bulk(admin, 'reindex', {
			dashboardUuids: [...three.map((d) => d.uuid), ...fakeUuids(498)],
		})

		expect(answer.status).toBe(400)
		expect(answer.body.error).toBe(
			'Request contains 501 dashboards; maximum is 500 (configured by admin)',
		)
		for (let i = 0; i < three.length; i++) {
			expect((await readDash(admin, three[i].id)).updatedAt).toBe(
				before[i].updatedAt,
			)
		}
	})
})

test.describe('request size cap (REQ-BULK-006, 001)', () => {
	test.setTimeout(240_000)

	test('501 uuids are refused with the cap in the message, nothing deleted', async () => {
		// @e2e dashboard-bulk-operations::bulk-delete-request-exceeds-size-cap
		// @e2e dashboard-bulk-operations::request-exceeds-cap-is-rejected
		const three = await createMany('cap-delete', 3)

		const answer = await bulk(admin, 'delete', {
			dashboardUuids: [...three.map((d) => d.uuid), ...fakeUuids(498)],
		})

		expect(answer.status).toBe(400)
		expect(answer.body.error).toBe(
			'Request contains 501 dashboards; maximum is 500 (configured by admin)',
		)
		await expectAllExist(three)
	})

	test('exactly 500 uuids are accepted', async () => {
		// @e2e dashboard-bulk-operations::request-within-cap-is-accepted
		const three = await createMany('at-cap', 3)

		const answer = await bulk(admin, 'delete', {
			dashboardUuids: [...three.map((d) => d.uuid), ...fakeUuids(497)],
		})

		expect(answer.status, JSON.stringify(answer.body).slice(0, 300)).toBe(200)
		expect(answer.body.deletedCount).toBe(3)
		expect(answer.body.skippedCount).toBe(497)
		await expectAllGone(three)
	})

	test('a raised cap applies to the very next request', async () => {
		// @e2e dashboard-bulk-operations::admin-modifies-bulk-operation-cap
		const three = await createMany('raised-cap', 3)
		try {
			await writeCap('1000')

			const accepted = await bulk(admin, 'delete', {
				dashboardUuids: [...three.map((d) => d.uuid), ...fakeUuids(747)],
			})
			expect(
				accepted.status,
				JSON.stringify(accepted.body).slice(0, 300),
			).toBe(200)
			expect(accepted.body.deletedCount).toBe(3)
			expect(accepted.body.skippedCount).toBe(747)
			await expectAllGone(three)

			// And to every request after it: the new number is the limit.
			const over = await bulk(admin, 'delete', {
				dashboardUuids: fakeUuids(1001),
			})
			expect(over.status).toBe(400)
			expect(over.body.error).toBe(
				'Request contains 1001 dashboards; maximum is 1000 (configured by admin)',
			)
		} finally {
			await clearCap()
		}
	})

	test('a cap of zero falls back to 500 and logs a warning', async () => {
		// @e2e dashboard-bulk-operations::cap-of-zero-or-negative-is-invalid
		const three = await createMany('zero-cap', 3)
		try {
			await writeCap('0')
			const warningsBefore = await capWarningCount()

			const over = await bulk(admin, 'delete', {
				dashboardUuids: [...three.map((d) => d.uuid), ...fakeUuids(498)],
			})
			expect(over.status).toBe(400)
			expect(over.body.error).toBe(
				'Request contains 501 dashboards; maximum is 500 (configured by admin)',
			)
			await expectAllExist(three)

			const atDefault = await bulk(admin, 'delete', {
				dashboardUuids: [...three.map((d) => d.uuid), ...fakeUuids(497)],
			})
			expect(atDefault.status).toBe(200)
			expect(atDefault.body.deletedCount).toBe(3)

			expect(await capWarningCount()).toBeGreaterThan(warningsBefore)
		} finally {
			await clearCap()
		}
	})

	test('permissions are checked before the size cap', async () => {
		// @e2e dashboard-bulk-operations::permission-check-happens-before-size-validation
		const [mine] = await createMany('perm-before-size', 1)

		const answer = await bulk(admin, 'delete', {
			dashboardUuids: [mine.uuid, privateDash.uuid, ...fakeUuids(499)],
		})

		expect(answer.status, 'a 400 would mean size ran first').toBe(403)
		expect(answer.body.deniedUuids).toEqual([privateDash.uuid])
		await expectAllExist([mine])
		await expectPrivateIntact()
	})
})

test.describe('callers who are not administrators (REQ-BULK-011)', () => {
	test('a signed-in non-admin is refused on every endpoint', async () => {
		// @e2e dashboard-bulk-operations::non-admin-user-cannot-call-bulk-endpoints
		const [dash] = await createMany('non-admin', 1)
		const ops: Array<[Op, Record<string, unknown>]> = [
			['delete', { dashboardUuids: [dash.uuid] }],
			['move', { dashboardUuids: [dash.uuid], parentUuid: null }],
			[
				'status',
				{ dashboardUuids: [dash.uuid], publicationStatus: 'published' },
			],
			['reindex', { dashboardUuids: [dash.uuid] }],
		]
		const before = await readDash(admin, dash.id)
		await tick()

		for (const [op, data] of ops) {
			const refused = await bulk(probe!.api, op, data)
			expect(refused.status, `bulk-${op} as a non-admin`).toBe(403)
		}

		const after = await readDash(admin, dash.id)
		expect(after).not.toBeNull()
		expect(after.publicationStatus).toBe('draft')
		expect(after.updatedAt).toBe(before.updatedAt)

		const allowed = await bulk(admin, 'delete', { dashboardUuids: [dash.uuid] })
		expect(allowed.status).toBe(200)
		expect(allowed.body.deletedCount).toBe(1)
	})
})
