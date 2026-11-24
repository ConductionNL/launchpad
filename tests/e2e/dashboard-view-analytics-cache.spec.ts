/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the dashboard-view-analytics scenarios about
 * the unique-viewer dedup cache (openspec/specs/dashboard-view-analytics),
 * written for e2e-coverage-programme task 5.1.
 *
 * Both were excluded as "the cache is internal; no API exposes it". No HTTP
 * route does, but the test can run PHP on the instance with Nextcloud booted
 * (`support/instanceDb.ts`), and from there open the same distributed cache
 * the web process writes to, through `OCP\ICacheFactory`. That is only true
 * when the distributed cache is shared between processes (Redis or
 * Memcached, as in `docker/docker-compose.yml`). On an instance whose
 * distributed cache is per-process (APCu, the in-memory array cache), the
 * CLI process cannot see the web process's entries, so the two tests skip
 * there and say why, rather than pass without having looked.
 *
 * What is judged:
 *   - after a real view event, the cache holds exactly the key the spec
 *     names, `launchpad_anlt_{dashboardUuid}_{viewerHash}`, where the hash is
 *     SHA-256 over the user id and the stored `analytics_dailysalt`;
 *   - the entry's TTL is the seconds left until the next UTC midnight, not a
 *     fixed 86400;
 *   - the stored view row carries neither the raw user id nor the hash;
 *   - whether a view counts as a new unique viewer is decided by that cache
 *     entry: a repeat view does not count, and the same repeat view counts
 *     again once only the cache entry is removed.
 *
 * The spec writes the hash as `SHA256(userId || dailySalt)`. `||` is read
 * here as concatenation, with or without the single `|` separator
 * `lib/Service/UniqueViewerDedup.php` puts between the two; the test asserts
 * that exactly one of those keys exists, and logs which.
 *
 * Every row and cache entry this file writes belongs to a dashboard it
 * created, and is deleted afterwards. `analytics_enabled` and the admin's own
 * opt-out are read first and put back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-view-analytics::dedup-cache-key-is-per-dashboard-and-viewer-hash
 *   @e2e dashboard-view-analytics::hash-stored-in-cache-not-in-database
 *
 * @spec openspec/specs/dashboard-view-analytics/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { DashboardRow } from './support/dashboardHierarchy.ts'

import { expect, test } from '@playwright/test'
import {
	allowManyPersonalDashboards,
	createDashboard,
} from './support/dashboardHierarchy.ts'
import { deleteRows, instancePhp, selectRows } from './support/instanceDb.ts'
import { appValue } from './support/occ.ts'
import { adminContext, API } from './support/principals.ts'

const RUN = Date.now()
const STAMP = `E2E ANLT cache ${RUN}`

const VIEWS = 'launchpad_dashboard_views'
const ENABLED_KEY =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/analytics_enabled'
const SALT_KEY = 'analytics_dailysalt'
const NAMESPACE = 'launchpad_anlt_'

/** What the instance says about one candidate dedup key. */
interface CacheProbe {
	/** The class of the distributed cache, e.g. `OC\Memcache\Redis`. */
	cacheClass: string
	/** Whether that cache is shared between processes. */
	shared: boolean
	/** Seconds until the next UTC midnight, measured on the instance. */
	secondsToMidnight: number
	/** Per candidate key: whether it exists, and its TTL (null if unreadable). */
	keys: Record<string, { present: boolean; ttl: number | null }>
}

let admin: APIRequestContext
let adminId = ''
let restoreSettings: (() => Promise<void>) | null = null
let enabledBefore: string | null = null
let optedOutBefore = false
const dashboards: DashboardRow[] = []
const cacheKeys: string[] = []
let seq = 0

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await adminContext()
	restoreSettings = await allowManyPersonalDashboards(admin)

	const who = await admin.get('/ocs/v2.php/cloud/user?format=json', {
		headers: { 'OCS-APIRequest': 'true' },
	})
	expect(who.status(), await who.text()).toBe(200)
	adminId = String((await who.json())?.ocs?.data?.id ?? '')
	expect(adminId, 'the admin context has a user id').not.toBe('')

	const enabled = await admin.get(`${ENABLED_KEY}?format=json`)
	const data = enabled.ok() ? (await enabled.json())?.ocs?.data?.data : null
	enabledBefore = typeof data === 'string' && data !== '' ? data : null
	const on = await admin.post(`${ENABLED_KEY}?format=json`, {
		form: { value: '1' },
	})
	expect(on.ok(), `analytics_enabled=1 -> ${on.status()}`).toBe(true)

	const me = await admin.get(`${API}/analytics/me`)
	expect(me.status(), await me.text()).toBe(200)
	optedOutBefore = (await me.json()).optedOut === true
	if (optedOutBefore) {
		await setOptOut(false)
	}
})

test.afterAll(async () => {
	if (cacheKeys.length > 0) {
		await removeCacheKeys(cacheKeys).catch(() => undefined)
	}
	for (const d of dashboards) {
		await deleteRows(VIEWS, { dashboard_uuid: d.uuid }).catch(() => undefined)
		await admin.delete(`${API}/dashboard/${d.id}`).catch(() => undefined)
	}
	if (optedOutBefore) {
		await setOptOut(true).catch(() => undefined)
	}
	if (enabledBefore === null) {
		await admin.delete(`${ENABLED_KEY}?format=json`).catch(() => undefined)
	} else {
		await admin
			.post(`${ENABLED_KEY}?format=json`, { form: { value: enabledBefore } })
			.catch(() => undefined)
	}
	if (restoreSettings !== null) {
		await restoreSettings()
	}
	await admin.dispose()
})

// ── helpers ────────────────────────────────────────────────────────────

/**
 * Store the admin's own opt-out through the endpoint the settings switch uses.
 *
 * @param optedOut the value.
 * @return nothing.
 */
async function setOptOut(optedOut: boolean): Promise<void> {
	const res = await admin.put(`${API}/analytics/me`, { data: { optedOut } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * A fresh admin-owned dashboard, so every row and key read back belongs to
 * this test.
 *
 * @param label a readable part of the name.
 * @return the dashboard.
 */
async function newDashboard(label: string): Promise<DashboardRow> {
	seq += 1
	const d = await createDashboard(admin, { name: `${STAMP} ${label} ${seq}` })
	dashboards.push(d)
	return d
}

/**
 * Post a view event as the admin, which must be accepted with 204.
 *
 * @param uuid the dashboard.
 * @return nothing.
 */
async function viewOk(uuid: string): Promise<void> {
	const res = await admin.post(`${API}/dashboards/${uuid}/view-event`, {
		data: {},
		headers: { 'Content-Type': 'application/json' },
	})
	expect(res.status(), await res.text()).toBe(204)
}

/**
 * The SHA-256 hex digest of a string, computed in the test, not on the
 * instance, so the product's own hashing is not reused to check itself.
 *
 * @param value the input.
 * @return 64 hex characters.
 */
async function sha256(value: string): Promise<string> {
	const { createHash } = await import('node:crypto')
	return createHash('sha256').update(value, 'utf8').digest('hex')
}

/**
 * The dedup keys the spec allows for a viewer of a dashboard today: the
 * namespace, the dashboard uuid and SHA-256 over user id and today's salt,
 * concatenated plainly or with one `|` between them.
 *
 * @param uuid the dashboard.
 * @return { salt, hashes, keys } with hashes and keys in the same order.
 */
async function candidateKeys(
	uuid: string,
): Promise<{ salt: string; hashes: string[]; keys: string[] }> {
	const salt = (await appValue(SALT_KEY)) ?? ''
	expect(
		salt,
		'launchpad.analytics_dailysalt is set once a view was hashed',
	).toMatch(/^[0-9a-f]{64}$/)
	const hashes = [
		await sha256(`${adminId}${salt}`),
		await sha256(`${adminId}|${salt}`),
	]
	return { salt, hashes, keys: hashes.map((h) => `${NAMESPACE}${uuid}_${h}`) }
}

/**
 * Look the given keys up in the instance's distributed cache, opened with an
 * empty prefix so the key is read exactly as the spec writes it.
 *
 * @param keys the full keys.
 * @return what the instance reports.
 */
async function probeCache(keys: string[]): Promise<CacheProbe> {
	return instancePhp<CacheProbe>(
		`
$cache = \\OCP\\Server::get(\\OCP\\ICacheFactory::class)->createDistributed('');
$class = get_class($cache);
$shared = ($cache instanceof \\OC\\Memcache\\Redis) || ($cache instanceof \\OC\\Memcache\\Memcached);
$now = new \\DateTimeImmutable('now', new \\DateTimeZone('UTC'));
$midnight = $now->modify('tomorrow')->setTime(0, 0, 0);
$keys = [];
foreach ($in['keys'] as $k) {
	$present = $cache->hasKey($k);
	$ttl = null;
	if ($present && method_exists($cache, 'getTTL')) {
		$t = $cache->getTTL($k);
		$ttl = is_int($t) ? $t : null;
	}
	$keys[$k] = ['present' => $present, 'ttl' => $ttl];
}
$out = [
	'cacheClass' => $class,
	'shared' => $shared,
	'secondsToMidnight' => $midnight->getTimestamp() - $now->getTimestamp(),
	'keys' => $keys,
];
`,
		{ keys },
	)
}

/**
 * Remove keys from the instance's distributed cache.
 *
 * @param keys the full keys.
 * @return nothing.
 */
async function removeCacheKeys(keys: string[]): Promise<void> {
	await instancePhp(
		`
$cache = \\OCP\\Server::get(\\OCP\\ICacheFactory::class)->createDistributed('');
foreach ($in['keys'] as $k) {
	$cache->remove($k);
}
$out = true;
`,
		{ keys },
	)
}

/**
 * Skip when the instance's distributed cache is not shared between processes
 * (a CLI process cannot read what the web process stored), or when UTC
 * midnight is so close that the salt and the bucket may roll over mid-test.
 *
 * @return nothing.
 */
async function requireSharedCache(): Promise<void> {
	const probe = await probeCache([])
	test.skip(
		!probe.shared,
		`the distributed cache is ${probe.cacheClass}, which is per-process: a CLI process cannot see the web process's dedup entries. Run with Redis or Memcached (docker/docker-compose.yml).`,
	)
	test.skip(
		probe.secondsToMidnight < 300,
		'too close to UTC midnight: the bucket and the salt would roll over mid-test',
	)
}

/**
 * The single stored view row of a dashboard today.
 *
 * @param uuid the dashboard.
 * @return the row as stored.
 */
async function todaysRow(
	uuid: string,
): Promise<Record<string, string | number | null>> {
	const rows = await selectRows(VIEWS, { dashboard_uuid: uuid })
	expect(rows.length, `one view row for ${uuid}: ${JSON.stringify(rows)}`).toBe(1)
	return rows[0]
}

// ── tests ──────────────────────────────────────────────────────────────

test.describe('dashboard-view-analytics: the unique-viewer dedup cache', () => {
	// @e2e dashboard-view-analytics::dedup-cache-key-is-per-dashboard-and-viewer-hash
	test('a view stores launchpad_anlt_{uuid}_{hash} in the distributed cache, expiring at the next UTC midnight', async () => {
		await requireSharedCache()
		const d = await newDashboard('key')
		const other = await newDashboard('other')

		await viewOk(d.uuid)

		const { keys } = await candidateKeys(d.uuid)
		cacheKeys.push(...keys)
		const otherKeys = (await candidateKeys(other.uuid)).keys
		const probe = await probeCache([...keys, ...otherKeys])

		const present = keys.filter((k) => probe.keys[k].present)
		expect(
			present.length,
			`exactly one of the spec's key forms is in the cache: ${JSON.stringify(probe.keys)}`,
		).toBe(1)
		test.info().annotations.push({
			type: 'hash form',
			description:
				present[0] === keys[1]
					? 'sha256(userId|salt)'
					: 'sha256(userId salt)',
		})

		// The key is per dashboard: viewing one dashboard marks nothing for another.
		for (const k of otherKeys) {
			expect(
				probe.keys[k].present,
				`no dedup entry for a dashboard that was not viewed (${k})`,
			).toBe(false)
		}

		const ttl = probe.keys[present[0]].ttl
		expect(ttl, `${probe.cacheClass} reports the entry's TTL`).not.toBeNull()
		// The TTL was set when the view was recorded, a few seconds before the
		// probe measured the time to midnight, so it may be slightly less.
		expect(
			ttl as number,
			'TTL is no longer than the time left until UTC midnight',
		).toBeLessThanOrEqual(probe.secondsToMidnight + 5)
		expect(
			ttl as number,
			'TTL is the time left until UTC midnight, measured at the view',
		).toBeGreaterThanOrEqual(probe.secondsToMidnight - 120)
		if (probe.secondsToMidnight < 86_000) {
			expect(ttl, 'not a fixed 86400-second TTL').not.toBe(86_400)
		}
	})

	// @e2e dashboard-view-analytics::hash-stored-in-cache-not-in-database
	test('the viewer hash lives in the cache only, and that cache entry alone decides uniqueness', async () => {
		await requireSharedCache()
		const d = await newDashboard('hash')

		await viewOk(d.uuid)
		const { salt, hashes, keys } = await candidateKeys(d.uuid)
		cacheKeys.push(...keys)

		let row = await todaysRow(d.uuid)
		expect(
			[Number(row.view_count), Number(row.unique_viewer_count)],
			'first view counted as unique',
		).toEqual([1, 1])

		// The hash was computed from the stored analytics_dailysalt: its key is in the cache.
		const first = await probeCache(keys)
		const present = keys.filter((k) => first.keys[k].present)
		expect(
			present.length,
			`a dedup entry keyed on SHA-256(userId, ${SALT_KEY}): ${JSON.stringify(first.keys)}`,
		).toBe(1)
		const hash = hashes[keys.indexOf(present[0])]

		// Neither the raw user id nor the hash (nor the salt) is in the row.
		for (const [column, value] of Object.entries(row)) {
			const text = String(value ?? '')
			expect(
				text.includes(hash),
				`column ${column} holds no viewer hash`,
			).toBe(false)
			expect(text.includes(salt), `column ${column} holds no salt`).toBe(false)
			expect(text, `column ${column} holds no raw user id`).not.toBe(adminId)
		}
		expect(
			Object.keys(row)
				.map((c) => c.toLowerCase())
				.filter(
					(c) =>
						/user|hash|viewer_id|actor/.test(c)
						&& c !== 'unique_viewer_count',
				),
			'the views table has no per-user column',
		).toEqual([])

		// A repeat view finds the hash in the cache and does not count again.
		await viewOk(d.uuid)
		row = await todaysRow(d.uuid)
		expect(
			[Number(row.view_count), Number(row.unique_viewer_count)],
			'repeat view is not unique',
		).toEqual([2, 1])

		// Remove only the cache entry: the same viewer, same day, same salt, now
		// counts again. Nothing in the database remembered them.
		await removeCacheKeys(keys)
		expect(await appValue(SALT_KEY), 'the salt did not change').toBe(salt)
		await viewOk(d.uuid)
		row = await todaysRow(d.uuid)
		expect(
			[Number(row.view_count), Number(row.unique_viewer_count)],
			'with the cache entry gone the viewer is unique again',
		).toEqual([3, 2])

		const again = await probeCache(keys)
		expect(
			again.keys[present[0]].present,
			'and the entry was written back',
		).toBe(true)
	})
})
