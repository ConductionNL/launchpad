/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin cleanup HTTP contract (openspec/specs/orphaned-data-cleanup),
 * written for e2e-coverage-programme task 2.4.
 *
 * `GET /api/admin/cleanup/scan` and `POST /api/admin/cleanup/purge` have no
 * control in the admin page: they are an HTTP contract, so these tests call
 * them directly and assert the exact status and envelope each scenario names.
 *
 * WHY NO TEST SEEDS AN ORPHAN. Every delete path the product exposes over HTTP
 * cascades (dashboard deletion removes its shares, placements and rules, see
 * openspec/specs/dashboard-cascade-events), and a lock only counts as expired
 * fifteen minutes after its last heartbeat. So the instance may hold zero
 * orphans. The tests are written so that they hold for any orphan count, and
 * prove the behaviour through what a count cannot fake: the cache flag and its
 * `cachedAt`, the dry-run's would-be counts being found again by the real
 * purge that follows, and seeded live data surviving every purge.
 *
 * THE CACHE IS INSTANCE-WIDE. One entry (`launchpad.cleanup.scan`) serves every
 * admin. Each test that needs a known cache state first forces it: a real
 * purge of `expired_locks` (harmless: those rows are dead by definition)
 * empties it, and a scan right after fills it.
 *
 * Gate-19 @e2e traceability:
 *   @e2e orphaned-data-cleanup::get-apiadmincleanupscan-returns-current-orphan-counts
 *   @e2e orphaned-data-cleanup::subsequent-scan-api-call-returns-cached-results
 *   @e2e orphaned-data-cleanup::scan-results-are-cached-for-5-minutes
 *   @e2e orphaned-data-cleanup::cache-is-invalidated-after-purge
 *   @e2e orphaned-data-cleanup::real-purge-invalidates-cache
 *   @e2e orphaned-data-cleanup::dry-run-purge-does-not-invalidate-cache
 *   @e2e orphaned-data-cleanup::non-admin-user-is-denied-access
 *   @e2e orphaned-data-cleanup::purge-categories-via-api
 *   @e2e orphaned-data-cleanup::purge-with-dryruntrue
 *   @e2e orphaned-data-cleanup::empty-categories-array-purges-all-categories
 *   @e2e orphaned-data-cleanup::unknown-category-yields-http-400
 *   @e2e orphaned-data-cleanup::purge-api-returns-per-category-counts
 *
 * @spec openspec/specs/orphaned-data-cleanup/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
	seedDashboardWithWidget,
	shareWith,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const SCAN = `${API}/admin/cleanup/scan`
const PURGE = `${API}/admin/cleanup/purge`
const STAMP = `E2E Cleanup ${Date.now()}`
/** What Nextcloud answers a non-admin on an `AuthorizedAdminSetting` route. */
const NOT_ADMIN = 'Logged in account must be an admin, a sub admin or gotten special right to access this setting'
/** What Nextcloud answers a request without a session. */
const NOT_LOGGED_IN = 'Current user is not logged in'

/** The four shipped categories, in registration order (CategoryRegistryService). */
const SHIPPED = [
	'expired_locks',
	'expired_share_tokens',
	'orphaned_widget_placements',
	'orphaned_conditional_rules',
]

type ScanEnvelope = {
	byCategory: Record<string, number>
	totalRows: number
	durationMs: number
	dryRun: boolean
	scannedAt: string
	skipped: string[]
	cached: boolean
	cachedAt: string | null
}

type PurgeEnvelope = {
	purgedByCategory: Record<string, number>
	totalRows: number
	durationMs: number
	dryRun: boolean
	skipped: string[]
}

let admin: APIRequestContext
let member: Principal | null = null

/**
 * Scan as the admin and return the parsed envelope.
 *
 * @return the scan envelope.
 */
async function scan(): Promise<ScanEnvelope> {
	const res = await admin.get(SCAN)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Purge as the admin and return the parsed envelope.
 *
 * @param categories the category filter.
 * @param dryRun the dry-run flag.
 * @return the purge envelope.
 */
async function purge(categories: string[], dryRun: boolean): Promise<PurgeEnvelope> {
	const res = await admin.post(PURGE, { data: { categories, dryRun } })
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/** Empty the scan cache: any real purge invalidates it (REQ-CLN-010). */
async function forceEmptyCache(): Promise<void> {
	await purge(['expired_locks'], false)
}

/**
 * Assert the purge envelope shape the spec names, exactly.
 *
 * @param body the purge response body.
 * @param dryRun the dryRun flag it must carry.
 */
function expectPurgeEnvelope(body: PurgeEnvelope, dryRun: boolean): void {
	expect(Object.keys(body).sort()).toEqual([
		'dryRun',
		'durationMs',
		'purgedByCategory',
		'skipped',
		'totalRows',
	])
	expect(body.dryRun).toBe(dryRun)
	expect(typeof body.durationMs).toBe('number')
	expect(Array.isArray(body.skipped)).toBe(true)
	const sum = Object.values(body.purgedByCategory).reduce((a, b) => a + b, 0)
	expect(body.totalRows).toBe(sum)
}

test.describe.configure({ mode: 'serial' })

test.describe('orphaned-data cleanup API', () => {
	test.beforeAll(async () => {
		admin = await adminContext()
		await assertActingAs(admin, ADMIN_USER)
		member = await createPrincipal(admin, 'e2e-cleanup')
		await assertActingAs(member.api, member.username)
	})

	test.afterAll(async () => {
		await removePrincipal(admin, member)
		await admin.dispose()
	})

	test('a scan without a cache entry is fresh, and the next one is served from the cache', async () => {
		// @e2e orphaned-data-cleanup::get-apiadmincleanupscan-returns-current-orphan-counts
		await forceEmptyCache()

		const fresh = await scan()
		expect(Object.keys(fresh).sort()).toEqual([
			'byCategory',
			'cached',
			'cachedAt',
			'dryRun',
			'durationMs',
			'scannedAt',
			'skipped',
			'totalRows',
		])
		expect(fresh.cached).toBe(false)
		expect(fresh.cachedAt).toBeNull()
		expect(fresh.dryRun).toBe(false)
		expect(typeof fresh.durationMs).toBe('number')
		expect(fresh.scannedAt).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/)
		expect(fresh.skipped).toEqual([])
		expect(Object.keys(fresh.byCategory)).toEqual(SHIPPED)
		for (const count of Object.values(fresh.byCategory)) {
			expect(Number.isInteger(count) && count >= 0).toBe(true)
		}
		expect(fresh.totalRows).toBe(
			Object.values(fresh.byCategory).reduce((a, b) => a + b, 0),
		)

		// @e2e orphaned-data-cleanup::subsequent-scan-api-call-returns-cached-results
		// @e2e orphaned-data-cleanup::scan-results-are-cached-for-5-minutes
		const again = await scan()
		expect(again.cached).toBe(true)
		expect(again.cachedAt).toBe(fresh.scannedAt)
		expect(again.scannedAt).toBe(fresh.scannedAt)
		expect(again.byCategory).toEqual(fresh.byCategory)
		expect(again.totalRows).toBe(fresh.totalRows)
	})

	test('a real purge empties the scan cache', async () => {
		// @e2e orphaned-data-cleanup::cache-is-invalidated-after-purge
		// @e2e orphaned-data-cleanup::real-purge-invalidates-cache
		await scan()
		const primed = await scan()
		expect(primed.cached).toBe(true)

		await purge(['expired_locks'], false)

		const after = await scan()
		expect(after.cached).toBe(false)
		expect(after.cachedAt).toBeNull()
	})

	test('a dry-run purge leaves the scan cache as it was', async () => {
		// @e2e orphaned-data-cleanup::dry-run-purge-does-not-invalidate-cache
		await forceEmptyCache()
		const first = await scan()
		expect(first.cached).toBe(false)

		const dry = await purge([], true)
		expect(dry.dryRun).toBe(true)

		const after = await scan()
		expect(after.cached).toBe(true)
		expect(after.cachedAt).toBe(first.scannedAt)
	})

	test('an ordinary signed-in user is refused the scan with Nextcloud\'s 403', async () => {
		// @e2e orphaned-data-cleanup::non-admin-user-is-denied-access
		const refused = await member!.api.get(SCAN)
		expect(refused.status(), await refused.text()).toBe(403)
		expect(await refused.json()).toEqual({ message: NOT_ADMIN })

		// The admin, on the same endpoint, is served.
		const allowed = await admin.get(SCAN)
		expect(allowed.status()).toBe(200)
		expect(Object.keys((await allowed.json()).byCategory)).toEqual(SHIPPED)
	})

	test('an unauthenticated caller is refused the scan with Nextcloud\'s 401', async () => {
		// @e2e orphaned-data-cleanup::non-admin-user-is-denied-access
		const anon = await request.newContext({
			baseURL: BASE_URL,
			storageState: undefined,
			extraHTTPHeaders: {
				'OCS-APIRequest': 'true',
				Accept: 'application/json',
			},
		})
		try {
			const refused = await anon.get(SCAN)
			expect(refused.status(), await refused.text()).toBe(401)
			expect(await refused.json()).toEqual({ message: NOT_LOGGED_IN })
		} finally {
			await anon.dispose()
		}
	})

	test('an ordinary signed-in user is refused the purge with Nextcloud\'s 403 and nothing is purged', async () => {
		// @e2e orphaned-data-cleanup::non-admin-user-is-denied-access
		await forceEmptyCache()
		const primed = await scan()
		expect(primed.cached).toBe(false)

		const refused = await member!.api.post(PURGE, {
			data: { categories: [], dryRun: false },
		})
		expect(refused.status(), await refused.text()).toBe(403)
		expect(await refused.json()).toEqual({ message: NOT_ADMIN })

		// A purge that ran would have emptied the cache; it is still the same entry.
		const reread = await scan()
		expect(reread.cached).toBe(true)
		expect(reread.cachedAt).toBe(primed.scannedAt)

		// The admin's identical request is served.
		const allowed = await admin.post(PURGE, {
			data: { categories: [], dryRun: true },
		})
		expect(allowed.status()).toBe(200)
	})

	test('purging named categories removes their orphans and answers the full envelope', async () => {
		// @e2e orphaned-data-cleanup::purge-categories-via-api
		// @e2e orphaned-data-cleanup::purge-api-returns-per-category-counts
		const body = await purge(['expired_locks', 'expired_share_tokens'], false)
		expectPurgeEnvelope(body, false)
		expect(Object.keys(body.purgedByCategory)).toEqual([
			'expired_locks',
			'expired_share_tokens',
		])
		expect(body.skipped).toEqual([])

		// The purge emptied the cache, so this scan counts afresh: none left.
		const after = await scan()
		expect(after.cached).toBe(false)
		expect(after.byCategory.expired_locks).toBe(0)
		expect(after.byCategory.expired_share_tokens).toBe(0)
	})

	test('a dry-run purge reports what it would remove and removes nothing', async () => {
		// @e2e orphaned-data-cleanup::purge-with-dryruntrue
		const seeded = await seedDashboardWithWidget(admin, `${STAMP} dry-run`)
		try {
			await shareWith(admin, seeded.id, member!.username, 'view_only')

			const dry = await purge([], true)
			expectPurgeEnvelope(dry, true)
			expect(Object.keys(dry.purgedByCategory)).toEqual(SHIPPED)

			// Everything the dry run counted is still there for a real purge to
			// find: had the dry run deleted, these counts would be zero.
			const real = await purge([], false)
			expect(real.purgedByCategory).toEqual(dry.purgedByCategory)

			// Live data was never an orphan and survives both.
			const show = await admin.get(`${API}/dashboard/${seeded.id}`)
			expect(show.status()).toBe(200)
			const placements = (await show.json()).placements as Array<{
				id: number
			}>
			expect(placements.map((p) => Number(p.id))).toContain(seeded.placementId)
			const shares = await admin.get(`${API}/dashboard/${seeded.id}/shares`)
			expect(shares.status()).toBe(200)
			const list = (await shares.json()) as Array<{ shareWith: string }>
			expect(list.map((s) => s.shareWith)).toContain(member!.username)
		} finally {
			await admin.delete(`${API}/dashboard/${seeded.id}`)
		}
	})

	test('an empty categories list purges every registered category', async () => {
		// @e2e orphaned-data-cleanup::empty-categories-array-purges-all-categories
		// @e2e orphaned-data-cleanup::purge-api-returns-per-category-counts
		const body = await purge([], false)
		expectPurgeEnvelope(body, false)
		expect(Object.keys(body.purgedByCategory)).toEqual(SHIPPED)

		const after = await scan()
		expect(after.cached).toBe(false)
		expect(after.totalRows).toBe(0)
	})

	test('an unknown category is refused with 400 and the valid names', async () => {
		// @e2e orphaned-data-cleanup::unknown-category-yields-http-400
		await forceEmptyCache()
		const primed = await scan()

		const res = await admin.post(PURGE, {
			data: {
				categories: ['expired_locks', `no_such_category_${Date.now()}`],
				dryRun: false,
			},
		})
		expect(res.status(), await res.text()).toBe(400)
		const body = await res.json()
		expect(typeof body.error).toBe('string')
		expect(body.error.length).toBeGreaterThan(0)
		expect(body.validCategories).toEqual(SHIPPED)

		// Nothing ran, not even the valid name next to it: the cache is intact.
		const reread = await scan()
		expect(reread.cached).toBe(true)
		expect(reread.cachedAt).toBe(primed.scannedAt)
	})
})
