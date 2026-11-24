/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard editing lock (REQ-LOCK-001..008), written for
 * e2e-coverage-programme task 2.6.
 *
 * TWO SESSIONS AGAINST ONE DASHBOARD. Every lock scenario is about one person
 * holding the lock and another meeting it. Here those are real, separate
 * accounts, each with its own basic-auth request context (`contextFor`):
 * alice owns the dashboard and shares it at `full` with bob and carol, so a
 * refusal is never "bob cannot see the dashboard" but "bob does not hold the
 * lock". The administrator is the Nextcloud admin. "A second browser tab" is
 * a second context for the same account.
 *
 * WHAT HAS NO PAGE ANY MORE. The workspace page stopped taking the lock in
 * building-edit-a-dashboard-together (594fe5c90): edit mode announces a
 * presence instead. The lock endpoints stayed, so they are an HTTP contract
 * and are asserted as one, exact status and body included.
 *
 * WHERE A DELETED LOCK IS SEEN. After a dashboard is deleted, every lock read
 * answers 404 because the dashboard is gone, which would pass whether or not
 * the lock row was removed. So the cascade tests export the dashboard first
 * and import it back with its UUID preserved: a lock row the delete left
 * behind would then be served again for the restored dashboard.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-locking::first-user-acquires-lock-on-unlocked-dashboard
 *   @e2e dashboard-locking::same-user-with-two-browser-tabs-re-entrant-acquire
 *   @e2e dashboard-locking::lock-owner-extends-lease-with-heartbeat
 *   @e2e dashboard-locking::non-owner-cannot-heartbeat
 *   @e2e dashboard-locking::heartbeat-on-non-existent-lock
 *   @e2e dashboard-locking::lock-owner-releases-lock
 *   @e2e dashboard-locking::non-owner-cannot-release-another-users-lock
 *   @e2e dashboard-locking::release-non-existent-lock
 *   @e2e dashboard-locking::get-active-lock
 *   @e2e dashboard-locking::get-lock-when-none-exists
 *   @e2e dashboard-locking::cached-displayname-is-stale
 *   @e2e dashboard-locking::admin-acquires-lock-after-force-release-normal-flow
 *   @e2e dashboard-locking::non-admin-cannot-force-release
 *   @e2e dashboard-locking::three-users-race-to-acquire-lock
 *   @e2e dashboard-locking::heartbeat-race-does-not-cause-corruption
 *   @e2e dashboard-locking::delete-dashboard-with-active-lock
 *   @e2e dashboard-locking::lock-is-not-a-blocker-for-dashboard-deletion
 *
 * @spec openspec/specs/dashboard-locking/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	assertActingAs,
	contextFor,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const STAMP = `E2E Lock ${Date.now()}`
const ALICE_NAME = `Alice Smith ${Date.now()}`

interface Dash {
	id: number
	uuid: string
}

interface Lock {
	id: number
	dashboardUuid: string
	userId?: string
	displayName: string
	acquiredAt: string
	lastHeartbeat: string
	expiresAt: string
	lockTimeoutSec: number
}

let admin: APIRequestContext
let alice: Principal | null = null
let bob: Principal | null = null
let carol: Principal | null = null
let originalAllowUserDash: unknown = null
const dashboards: Dash[] = []

/** The lock URL of a dashboard. */
function lockUrl(dash: Dash): string {
	return `${API}/dashboards/${dash.uuid}/lock`
}

/** Wait, in real time. Lock timestamps have one-second resolution. */
async function sleep(ms: number): Promise<void> {
	await new Promise((resolve) => setTimeout(resolve, ms))
}

/**
 * A dashboard alice owns, shared at `full` with bob and carol.
 *
 * @param label a name suffix.
 * @return the dashboard.
 */
async function aliceDashboard(label: string): Promise<Dash> {
	const res = await alice!.api.post(`${API}/dashboard`, {
		data: { name: `${STAMP} ${label}` },
	})
	expect(res.status(), await res.text()).toBe(201)
	const body = await res.json()
	const dash = { id: Number(body.dashboard.id), uuid: String(body.dashboard.uuid) }
	dashboards.push(dash)
	await publishForRecipients(alice!.api, dash.id)
	await shareWith(alice!.api, dash.id, bob!.username, 'full')
	await shareWith(alice!.api, dash.id, carol!.username, 'full')
	return dash
}

/**
 * Acquire the lock as `api`, asserting 200.
 *
 * @param api the acquirer.
 * @param dash the dashboard.
 * @return the lock.
 */
async function acquire(api: APIRequestContext, dash: Dash): Promise<Lock> {
	const res = await api.post(lockUrl(dash))
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Read the lock as `api`.
 *
 * @param api the reader.
 * @param dash the dashboard.
 * @return the status and the body.
 */
async function readLock(
	api: APIRequestContext,
	dash: Dash,
): Promise<{ status: number; body: Record<string, unknown> | null }> {
	const res = await api.get(lockUrl(dash))
	const text = await res.text()
	return { status: res.status(), body: text === '' ? null : JSON.parse(text) }
}

/**
 * The id of alice's dashboard with this uuid, read from her list.
 *
 * @param uuid the dashboard uuid.
 * @return the id, or null when she has none with it.
 */
async function aliceDashboardIdByUuid(uuid: string): Promise<number | null> {
	const res = await alice!.api.get(`${API}/dashboards`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	// `GET /api/dashboards` answers `{items, quota}` (REQ-QUOTA-006), or the
	// bare list without the quota service. Reading only `dashboards` / `data`
	// found nothing, so the cascade tests failed on "the import must restore
	// alice's dashboard" although the import had landed it.
	const list: Array<{ id: number; uuid: string }> = Array.isArray(body)
		? body
		: (body.items ?? body.dashboards ?? body.data ?? [])
	expect(Array.isArray(list), JSON.stringify(body)).toBe(true)
	const hit = list.find((d) => d.uuid === uuid)
	return hit ? Number(hit.id) : null
}

/**
 * Export one dashboard as a ZIP through the admin export endpoint.
 *
 * @param uuid the dashboard uuid.
 * @return the archive bytes.
 */
async function exportDashboard(uuid: string): Promise<Buffer> {
	const res = await admin.post(`${API}/admin/export`, {
		data: { scope: 'dashboard', dashboardUuid: uuid },
	})
	expect(res.status()).toBe(200)
	return res.body()
}

/**
 * Import an archive with its UUIDs preserved, asserting one dashboard landed.
 *
 * @param archive the ZIP bytes.
 * @return nothing.
 */
async function importPreservingUuids(archive: Buffer): Promise<void> {
	const res = await admin.post(`${API}/admin/import?preserveUuids=true`, {
		multipart: {
			file: {
				name: 'lock-cascade.zip',
				mimeType: 'application/zip',
				buffer: archive,
			},
		},
	})
	expect(res.status(), await res.text()).toBe(200)
	expect((await res.json()).importedDashboardCount).toBe(1)
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status()).toBe(200)
	originalAllowUserDash = (await settings.json()).allowUserDashboards ?? null
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)

	alice = await createPrincipal(admin, 'e2e-lock-alice')
	bob = await createPrincipal(admin, 'e2e-lock-bob')
	carol = await createPrincipal(admin, 'e2e-lock-carol')
	for (const p of [alice, bob, carol]) {
		await assertActingAs(p.api, p.username)
	}
	// alice's display name, which the lock caches at acquire time.
	const named = await admin.put(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(alice.username)}`,
		{
			form: { key: 'displayname', value: ALICE_NAME },
		},
	)
	expect(named.status(), await named.text()).toBe(200)
})

test.afterAll(async () => {
	for (const dash of dashboards) {
		await alice?.api.delete(lockUrl(dash)).catch(() => undefined)
		await alice?.api.delete(`${API}/dashboard/${dash.id}`).catch(() => undefined)
	}
	await removePrincipal(admin, alice)
	await removePrincipal(admin, bob)
	await removePrincipal(admin, carol)
	if (originalAllowUserDash !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: originalAllowUserDash },
		})
	}
	await admin.dispose()
})

test.describe('REQ-LOCK-001 acquire', () => {
	test('the first user gets a lock carrying her id, cached name and timestamps, and can edit', async () => {
		// @e2e dashboard-locking::first-user-acquires-lock-on-unlocked-dashboard
		const dash = await aliceDashboard('first')
		expect((await readLock(alice!.api, dash)).status).toBe(404)

		const lock = await acquire(alice!.api, dash)
		expect(lock).toMatchObject({
			dashboardUuid: dash.uuid,
			userId: alice!.username,
			displayName: ALICE_NAME,
		})
		expect(lock.id).toBeGreaterThan(0)
		expect(lock.acquiredAt).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/)
		expect(lock.lastHeartbeat).toBe(lock.acquiredAt)

		// The record exists: a colleague reads the same lock.
		const seen = await readLock(bob!.api, dash)
		expect(seen.status).toBe(200)
		expect(seen.body).toMatchObject({ id: lock.id, userId: alice!.username })

		// And alice can edit the dashboard.
		const edit = await alice!.api.put(`${API}/dashboard/${dash.id}`, {
			data: { name: `${STAMP} first edited` },
		})
		expect(edit.status(), await edit.text()).toBe(200)
	})

	test('a second user is refused with 409 and no lock is created (banner clause excluded)', async () => {
		// Not cited: "Second user encounters lock held by first user" also asks
		// for a read-only banner the page no longer shows; see the spec's
		// exclusion. The HTTP half is asserted here all the same.
		const dash = await aliceDashboard('second user')
		const held = await acquire(alice!.api, dash)

		const res = await bob!.api.post(lockUrl(dash))
		expect(res.status(), await res.text()).toBe(409)
		const body = await res.json()
		expect(body.lock).toMatchObject({
			id: held.id,
			displayName: ALICE_NAME,
			lastHeartbeat: held.lastHeartbeat,
		})

		const after = await readLock(alice!.api, dash)
		expect(after.body).toMatchObject({ id: held.id, userId: alice!.username })
	})

	test('the same user acquiring again from a second tab gets 200 and a refreshed heartbeat', async () => {
		// @e2e dashboard-locking::same-user-with-two-browser-tabs-re-entrant-acquire
		const dash = await aliceDashboard('two tabs')
		const tab2 = await contextFor(alice!.username, alice!.password)
		try {
			const first = await acquire(alice!.api, dash)
			await sleep(1_500)

			const second = await acquire(tab2, dash)
			expect(second.id).toBe(first.id)
			expect(second.userId).toBe(alice!.username)
			expect(second.acquiredAt).toBe(first.acquiredAt)
			expect(second.lastHeartbeat > first.lastHeartbeat).toBe(true)

			// tab-2's session is valid: it may extend the lease.
			const beat = await tab2.put(lockUrl(dash))
			expect(beat.status(), await beat.text()).toBe(200)
			expect((await beat.json()).userId).toBe(alice!.username)
		} finally {
			await tab2.dispose()
		}
	})
})

test.describe('REQ-LOCK-002 heartbeat', () => {
	test("the owner's heartbeat moves lastHeartbeat and leaves acquiredAt alone", async () => {
		// @e2e dashboard-locking::lock-owner-extends-lease-with-heartbeat
		const dash = await aliceDashboard('heartbeat')
		const t0 = await acquire(alice!.api, dash)
		await sleep(1_500)

		const res = await alice!.api.put(lockUrl(dash))
		expect(res.status(), await res.text()).toBe(200)
		const t1: Lock = await res.json()
		expect(t1.id).toBe(t0.id)
		expect(t1.lastHeartbeat > t0.lastHeartbeat).toBe(true)
		expect(t1.acquiredAt).toBe(t0.acquiredAt)

		// Stored, not only answered.
		expect((await readLock(bob!.api, dash)).body).toMatchObject({
			lastHeartbeat: t1.lastHeartbeat,
		})
	})

	test('a heartbeat from someone else is refused with 403 and changes nothing', async () => {
		// @e2e dashboard-locking::non-owner-cannot-heartbeat
		const dash = await aliceDashboard('foreign heartbeat')
		const held = await acquire(alice!.api, dash)
		await sleep(1_500)

		const res = await bob!.api.put(lockUrl(dash))
		expect(res.status(), await res.text()).toBe(403)

		const after = await readLock(alice!.api, dash)
		expect(after.body).toMatchObject({
			id: held.id,
			userId: alice!.username,
			lastHeartbeat: held.lastHeartbeat,
		})

		// Control: the owner's heartbeat on the same lock succeeds.
		expect((await alice!.api.put(lockUrl(dash))).status()).toBe(200)
	})

	test('a heartbeat with no lock answers 404 "Lock not found; call acquire first"', async () => {
		// @e2e dashboard-locking::heartbeat-on-non-existent-lock
		const dash = await aliceDashboard('heartbeat nothing')
		const res = await alice!.api.put(lockUrl(dash))
		expect(res.status(), await res.text()).toBe(404)
		expect((await res.json()).error).toBe('Lock not found; call acquire first')
		expect((await readLock(alice!.api, dash)).status).toBe(404)
	})
})

test.describe('REQ-LOCK-003 release', () => {
	test('the owner releases with 204 and a colleague can acquire at once', async () => {
		// @e2e dashboard-locking::lock-owner-releases-lock
		const dash = await aliceDashboard('release')
		await acquire(alice!.api, dash)

		const res = await alice!.api.delete(lockUrl(dash))
		expect(res.status()).toBe(204)
		expect((await readLock(alice!.api, dash)).status).toBe(404)

		const bobs = await acquire(bob!.api, dash)
		expect(bobs.userId).toBe(bob!.username)
	})

	test("someone else's release is refused with 403 and its reason, and the lock stays", async () => {
		// @e2e dashboard-locking::non-owner-cannot-release-another-users-lock
		const dash = await aliceDashboard('foreign release')
		const held = await acquire(alice!.api, dash)

		const res = await bob!.api.delete(lockUrl(dash))
		expect(res.status(), await res.text()).toBe(403)
		expect((await res.json()).error).toBe(
			'Only the lock owner or an admin can release this lock',
		)

		expect((await readLock(alice!.api, dash)).body).toMatchObject({
			id: held.id,
			userId: alice!.username,
		})

		// Control: the owner may release it.
		expect((await alice!.api.delete(lockUrl(dash))).status()).toBe(204)
	})

	test("an administrator's release of another user's lock answers 204 (log clause excluded)", async () => {
		// Not cited: "Admin can release any lock" also requires an audit log
		// line, which cannot be read over HTTP; see the spec's exclusion.
		const dash = await aliceDashboard('admin release')
		await acquire(alice!.api, dash)
		const res = await admin.delete(lockUrl(dash))
		expect(res.status()).toBe(204)
		expect((await readLock(alice!.api, dash)).status).toBe(404)
	})

	test('releasing when there is no lock answers 204', async () => {
		// @e2e dashboard-locking::release-non-existent-lock
		const dash = await aliceDashboard('release nothing')
		const res = await alice!.api.delete(lockUrl(dash))
		expect(res.status()).toBe(204)
		expect((await readLock(alice!.api, dash)).status).toBe(404)
	})
})

test.describe('REQ-LOCK-004 reading the lock', () => {
	test('a colleague reads the holder, the cached name and the heartbeat, with a 900 s lease', async () => {
		// @e2e dashboard-locking::get-active-lock
		const dash = await aliceDashboard('get active')
		const held = await acquire(alice!.api, dash)

		const { status, body } = await readLock(bob!.api, dash)
		expect(status).toBe(200)
		expect(body).toMatchObject({
			userId: alice!.username,
			displayName: ALICE_NAME,
			lastHeartbeat: held.lastHeartbeat,
			lockTimeoutSec: 900,
		})
		// What the client shows as the implied expiry: lastHeartbeat + 900 s.
		const parse = (s: string): number => Date.parse(`${s.replace(' ', 'T')}Z`)
		expect(
			parse(String(body!.expiresAt)) - parse(String(body!.lastHeartbeat)),
		).toBe(900_000)
	})

	test('with no lock the read answers 404 and the colleague can start editing', async ({
		browser,
	}) => {
		// @e2e dashboard-locking::get-lock-when-none-exists
		test.setTimeout(120_000)
		const dash = await aliceDashboard('get none')
		const { status, body } = await readLock(bob!.api, dash)
		expect(status).toBe(404)
		expect(body).toMatchObject({ error: 'Lock not found' })

		// The page treats the dashboard as unlocked: bob enters edit mode and
		// no lock banner stands in his way.
		for (const pref of ['default', 'active']) {
			const res = await bob!.api.post(`${API}/dashboards/${pref}`, {
				data: { uuid: dash.uuid },
			})
			expect(res.status(), await res.text()).toBe(200)
		}
		const { context, page } = await loginAs(
			browser,
			bob!.username,
			bob!.password,
		)
		try {
			await page.goto('/index.php/apps/launchpad/')
			await enterEditMode(page)
			await expect(
				page.locator('[data-testid="edit-lock-banner"]'),
			).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	test('the lock keeps the display name from acquire time after a rename', async () => {
		// @e2e dashboard-locking::cached-displayname-is-stale
		const dash = await aliceDashboard('stale name')
		await acquire(alice!.api, dash)

		const renamed = `${ALICE_NAME} renamed`
		try {
			const rename = await admin.put(
				`/ocs/v1.php/cloud/users/${encodeURIComponent(alice!.username)}`,
				{ form: { key: 'displayname', value: renamed } },
			)
			expect(rename.status(), await rename.text()).toBe(200)
			// The rename took.
			const user = await admin.get(
				`/ocs/v1.php/cloud/users/${encodeURIComponent(alice!.username)}?format=json`,
			)
			expect((await user.json()).ocs.data.displayname).toBe(renamed)

			const { body } = await readLock(bob!.api, dash)
			expect(body).toMatchObject({
				displayName: ALICE_NAME,
				userId: alice!.username,
			})
		} finally {
			await admin.put(
				`/ocs/v1.php/cloud/users/${encodeURIComponent(alice!.username)}`,
				{
					form: { key: 'displayname', value: ALICE_NAME },
				},
			)
		}
	})
})

test.describe('REQ-LOCK-006 administrator override', () => {
	test('after a force-release the administrator acquires the lock through the normal path', async () => {
		// @e2e dashboard-locking::admin-acquires-lock-after-force-release-normal-flow
		const dash = await aliceDashboard('force then acquire')
		await acquire(alice!.api, dash)

		const forced = await admin.post(`${lockUrl(dash)}/force-release`)
		expect(forced.status(), await forced.text()).toBe(200)
		// Force-release does not hand the lock over.
		expect((await readLock(alice!.api, dash)).status).toBe(404)

		const mine = await acquire(admin, dash)
		expect(mine.userId).toBe(ADMIN_USER)
		expect((await readLock(alice!.api, dash)).body).toMatchObject({
			userId: ADMIN_USER,
		})
		expect((await admin.delete(lockUrl(dash))).status()).toBe(204)
	})

	test("a non-admin's force-release is refused with 403 and the lock stays", async () => {
		// @e2e dashboard-locking::non-admin-cannot-force-release
		const dash = await aliceDashboard('force refused')
		const held = await acquire(alice!.api, dash)

		const res = await bob!.api.post(`${lockUrl(dash)}/force-release`)
		expect(res.status(), await res.text()).toBe(403)
		expect((await readLock(alice!.api, dash)).body).toMatchObject({
			id: held.id,
			userId: alice!.username,
			lastHeartbeat: held.lastHeartbeat,
		})

		// Control: an administrator's force-release on the same lock works.
		expect((await admin.post(`${lockUrl(dash)}/force-release`)).status()).toBe(
			200,
		)
		expect((await readLock(alice!.api, dash)).status).toBe(404)
	})
})

test.describe('REQ-LOCK-007 contention', () => {
	test('three users racing for one lock: exactly one 200, two 409 naming the winner', async () => {
		// @e2e dashboard-locking::three-users-race-to-acquire-lock
		const dash = await aliceDashboard('race')
		const racers = [alice!, bob!, carol!]
		const responses = await Promise.all(
			racers.map((p) => p.api.post(lockUrl(dash))),
		)
		const statuses = responses.map((r) => r.status())

		expect(
			statuses.filter((s) => s === 200),
			JSON.stringify(statuses),
		).toHaveLength(1)
		expect(
			statuses.filter((s) => s === 409),
			JSON.stringify(statuses),
		).toHaveLength(2)

		const winnerIndex = statuses.indexOf(200)
		const winner = racers[winnerIndex]
		const won: Lock = await responses[winnerIndex].json()
		expect(won.userId).toBe(winner.username)
		const winnerName = winner === alice ? ALICE_NAME : winner.username
		expect(won.displayName).toBe(winnerName)
		for (const [i, res] of responses.entries()) {
			if (i === winnerIndex) {
				continue
			}
			// The losers are told about the winner's lock, the same one.
			const body = await res.json()
			expect(body.lock).toMatchObject({ id: won.id, displayName: winnerName })
		}

		expect((await readLock(alice!.api, dash)).body).toMatchObject({
			id: won.id,
			userId: winner.username,
		})
		expect((await winner.api.delete(lockUrl(dash))).status()).toBe(204)
	})

	test('two simultaneous heartbeats both succeed and leave one consistent lock', async () => {
		// @e2e dashboard-locking::heartbeat-race-does-not-cause-corruption
		const dash = await aliceDashboard('heartbeat race')
		const held = await acquire(alice!.api, dash)
		await sleep(1_500)

		const [a, b] = await Promise.all([
			alice!.api.put(lockUrl(dash)),
			alice!.api.put(lockUrl(dash)),
		])
		expect(a.status(), await a.text()).toBe(200)
		expect(b.status(), await b.text()).toBe(200)
		const beats: Lock[] = [await a.json(), await b.json()]

		const after = await readLock(bob!.api, dash)
		expect(after.status).toBe(200)
		const stored = after.body as unknown as Lock
		// One lock, the same one, still alice's, acquiredAt untouched.
		expect(stored.id).toBe(held.id)
		expect(beats.map((l) => l.id)).toEqual([held.id, held.id])
		expect(stored.userId).toBe(alice!.username)
		expect(stored.acquiredAt).toBe(held.acquiredAt)
		// The stored heartbeat moved, and is one of the two that were written.
		expect(stored.lastHeartbeat > held.lastHeartbeat).toBe(true)
		expect(beats.map((l) => l.lastHeartbeat)).toContain(stored.lastHeartbeat)
		// Still exclusive: a colleague is refused.
		expect((await bob!.api.post(lockUrl(dash))).status()).toBe(409)
	})
})

test.describe('REQ-LOCK-008 a deleted dashboard takes its lock with it', () => {
	test('deleting a dashboard with an active lock deletes the lock', async () => {
		// @e2e dashboard-locking::delete-dashboard-with-active-lock
		const dash = await aliceDashboard('cascade')
		await acquire(alice!.api, dash)
		const archive = await exportDashboard(dash.uuid)

		const del = await alice!.api.delete(`${API}/dashboard/${dash.id}`)
		expect(del.status(), await del.text()).toBe(200)
		// The dashboard record is gone.
		expect((await alice!.api.get(`${API}/dashboard/${dash.id}`)).status()).toBe(
			404,
		)

		// Bring the same UUID back: a lock row left behind would be served again.
		await importPreservingUuids(archive)
		const restoredId = await aliceDashboardIdByUuid(dash.uuid)
		expect(
			restoredId,
			"the import must restore alice's dashboard under its uuid",
		).not.toBeNull()
		dashboards.push({ id: restoredId!, uuid: dash.uuid })

		const { status } = await readLock(alice!.api, dash)
		expect(status).toBe(404)
		// And it is free to lock again.
		expect((await acquire(alice!.api, dash)).userId).toBe(alice!.username)
	})

	test("a colleague's lock does not stop the owner deleting, is released, and his next save fails", async () => {
		// @e2e dashboard-locking::lock-is-not-a-blocker-for-dashboard-deletion
		const dash = await aliceDashboard('cascade foreign')
		await acquire(bob!.api, dash)
		const archive = await exportDashboard(dash.uuid)

		const del = await alice!.api.delete(`${API}/dashboard/${dash.id}`)
		expect(del.status(), await del.text()).toBe(200)

		// bob's next save on the dashboard that no longer exists.
		const save = await bob!.api.put(`${API}/dashboard/${dash.id}`, {
			data: { name: `${STAMP} bob saves` },
		})
		expect(save.status(), await save.text()).toBe(404)
		expect(await save.text()).toContain('Dashboard not found')

		// bob's lock went with the dashboard.
		await importPreservingUuids(archive)
		const restoredId = await aliceDashboardIdByUuid(dash.uuid)
		expect(restoredId).not.toBeNull()
		dashboards.push({ id: restoredId!, uuid: dash.uuid })
		expect((await readLock(alice!.api, dash)).status).toBe(404)
		expect((await acquire(alice!.api, dash)).userId).toBe(alice!.username)
	})
})
