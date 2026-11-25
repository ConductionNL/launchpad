/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The client-IP half of the public link (dashboard-public-share REQ-PSHR-007
 * view-count debouncing and REQ-PSHR-009 brute-force protection). Written for
 * e2e-coverage-programme task 1.5.
 *
 * These scenarios were excluded for two reasons, and neither holds:
 *
 *   - "the job has one runner, so one IP". Nextcloud takes the client address
 *     from `X-Forwarded-For` when the request comes from a `trusted_proxies`
 *     entry (`OC\AppFramework\Http\Request::getRemoteAddress()`). The suite
 *     already runs occ on the instance (`support/occ.ts`), so for the length of
 *     this file it trusts the private and loopback ranges the runner reaches
 *     the instance from, and every request below names its own client address.
 *     The documentation addresses it names (198.18.0.0/15, RFC 2544) are not in
 *     that list, so Nextcloud stops at them and takes them as the client.
 *   - "spending the IP-global bucket poisons every later unlock test". It is
 *     IP-global, which is exactly why a per-test address isolates it: each
 *     throttle test spends a fresh address, never the runner's own, and the
 *     addresses are reset with `occ security:bruteforce:reset` afterwards.
 *   - "a 65 s wait is longer than the 60 s per-test timeout". The timeout is
 *     per test; the two tests that must outwait a window raise their own.
 *
 * A control in the first test proves the spoofed address is honoured (a render
 * from a second address must count), so a fixture that ignores the header
 * fails loudly there rather than letting the throttle tests measure the
 * runner's own address.
 *
 * Counters are read as the owner from `GET /api/dashboards/{uuid}/public-shares`
 * (`PublicShare::jsonSerialize()` carries `viewCount`), as
 * `ci/public-share-view-count.spec.ts` does.
 *
 * Expect the throttle tests to be slow: Nextcloud sleeps every request to a
 * brute-force-protected route by the address's delay (doubling per attempt, up
 * to 25 s), so ten failures take minutes, not seconds.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-public-share::renders-from-different-ips-are-counted-separately
 *   @e2e dashboard-public-share::renders-beyond-the-60-second-window-reset-the-debounce
 *   @e2e dashboard-public-share::10-failed-unlocks-per-60-seconds-allowed
 *   @e2e dashboard-public-share::unlock-is-throttled-to-prevent-brute-force
 *   @e2e dashboard-public-share::throttle-is-ip-global-across-all-shares
 *   @e2e dashboard-public-share::throttle-resets-after-time-window
 *
 * @spec openspec/specs/dashboard-public-share/spec.md
 */

import type { APIRequestContext, APIResponse } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import { describeRun, runOcc } from './support/occ.ts'
import { adminContext, API } from './support/principals.ts'

/** Where the runner can reach the instance from: loopback and the private ranges. */
const RUNNER_RANGES = [
	'127.0.0.0/8',
	'::1',
	'10.0.0.0/8',
	'172.16.0.0/12',
	'192.168.0.0/16',
	'fc00::/7',
]

const UNLOCK_LIMIT = 10

let admin: APIRequestContext
let anonymous: APIRequestContext
let priorAllowUserDash = true
let priorTrustedProxies: string[] | null = null
const dashboards: number[] = []
const spentAddresses: string[] = []

/**
 * A client address no earlier run has spent: 198.18.0.0/15 is reserved for
 * benchmarking, is in no `RUNNER_RANGES` entry, and has room for 130 000.
 *
 * @return the address, recorded for the reset in afterAll.
 */
function freshAddress(): string {
	const n = Math.floor(Math.random() * 131_070) + 1
	const ip = `198.${18 + Math.floor(n / 65_536)}.${Math.floor(n / 256) % 256}.${n % 256}`
	spentAddresses.push(ip)
	return ip
}

/**
 * The headers that make one request come from `ip`.
 *
 * @param ip the client address.
 * @return the header map.
 */
function from(ip: string): Record<string, string> {
	return { 'X-Forwarded-For': ip }
}

/**
 * Pause on the runner.
 *
 * @param ms how long.
 * @return nothing.
 */
async function pause(ms: number): Promise<void> {
	await new Promise((resolve) => setTimeout(resolve, ms))
}

/**
 * Read `trusted_proxies`, or null when the instance sets none.
 *
 * @return the list.
 */
async function readTrustedProxies(): Promise<string[] | null> {
	const result = await runOcc('config:system:get', 'trusted_proxies', '--output=json')
	if (result.code !== 0) {
		return null
	}
	const parsed = JSON.parse(result.stdout.trim() || 'null')
	return Array.isArray(parsed) ? parsed.map(String) : null
}

/**
 * Write `trusted_proxies`, or delete it for null.
 *
 * @param list the entries.
 * @return nothing.
 */
async function writeTrustedProxies(list: string[] | null): Promise<void> {
	const deleted = await runOcc('config:system:delete', 'trusted_proxies')
	expect(deleted.code, describeRun(deleted)).toBe(0)
	for (const [index, entry] of (list ?? []).entries()) {
		const set = await runOcc(
			'config:system:set',
			'trusted_proxies',
			String(index),
			`--value=${entry}`,
		)
		expect(set.code, describeRun(set)).toBe(0)
	}
	// The web server reads config.php through opcache, which revalidates
	// every couple of seconds by default.
	await pause(3_000)
}

/**
 * A dashboard of the admin's with a public link, optionally password-gated.
 *
 * @param label a readable name.
 * @param password the link's password, if any.
 * @return the dashboard uuid and the link's id and token.
 */
async function linkedDashboard(
	label: string,
	password?: string,
): Promise<{ uuid: string; id: number; token: string }> {
	const created = await admin.post(`${API}/dashboard`, {
		data: { name: `E2E ${label} ${Date.now()}` },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboards.push(Number(dash.id))
	const data: Record<string, string> = { expiresAt: '2099-12-31T23:59:59Z' }
	if (password !== undefined) {
		data.password = password
	}
	const link = await admin.post(`${API}/dashboards/${dash.uuid}/public-share`, { data })
	expect(link.status(), await link.text()).toBe(201)
	const { id, token } = await link.json()
	return { uuid: String(dash.uuid), id: Number(id), token: String(token) }
}

/**
 * The owner's view of one link's counter.
 *
 * @param uuid the dashboard.
 * @param id the link.
 * @return its viewCount.
 */
async function viewCount(uuid: string, id: number): Promise<number> {
	const res = await admin.get(`${API}/dashboards/${uuid}/public-shares`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const rows: Array<Record<string, unknown>> = Array.isArray(body)
		? body
		: (body.data ?? [])
	const row = rows.find((r) => Number(r.id) === id)
	expect(row, "the link must be in its owner's list").toBeTruthy()
	return Number(row!.viewCount)
}

/**
 * Render a link anonymously, as the public page does.
 *
 * @param token the link.
 * @param ip the client address, or undefined for the runner's own.
 * @return nothing.
 */
async function render(token: string, ip?: string): Promise<void> {
	const res = await anonymous.get(`/index.php/apps/launchpad/s/${token}/data`, {
		headers: ip === undefined ? {} : from(ip),
	})
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * One unlock with a wrong password.
 *
 * @param token the link.
 * @param ip the client address.
 * @return the HTTP status.
 */
async function wrongUnlock(token: string, ip: string): Promise<number> {
	return (await wrongUnlockResponse(token, ip)).status()
}

/**
 * One unlock with a wrong password, the whole response.
 *
 * @param token the link.
 * @param ip the client address.
 * @return the response.
 */
async function wrongUnlockResponse(token: string, ip: string): Promise<APIResponse> {
	return anonymous.post(`/index.php/apps/launchpad/s/${token}/unlock`, {
		headers: from(ip),
		data: { password: 'Not-the-password-1' },
		timeout: 120_000,
	})
}

test.beforeAll(async () => {
	admin = await adminContext()
	anonymous = await request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
	})
	const settings = await admin.get(`${API}/admin/settings`)
	priorAllowUserDash = (await settings.json()).allowUserDashboards === true
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)

	priorTrustedProxies = await readTrustedProxies()
	await writeTrustedProxies([
		...new Set([...(priorTrustedProxies ?? []), ...RUNNER_RANGES]),
	])
})

test.afterAll(async () => {
	await writeTrustedProxies(priorTrustedProxies)
	for (const ip of spentAddresses) {
		await runOcc('security:bruteforce:reset', ip)
	}
	for (const id of dashboards) {
		await admin.delete(`${API}/dashboard/${id}`)
	}
	if (!priorAllowUserDash) {
		await admin.put(`${API}/admin/settings`, { data: { allowUserDash: false } })
	}
	await anonymous.dispose()
	await admin.dispose()
})

// @e2e dashboard-public-share::renders-from-different-ips-are-counted-separately
test('renders from two client addresses each count, while the same address inside the window does not', async () => {
	const { uuid, id, token } = await linkedDashboard('two addresses')
	const first = freshAddress()
	const second = freshAddress()

	expect(await viewCount(uuid, id), 'CONTROL: a new link has not been viewed').toBe(0)

	await render(token, first)
	expect(await viewCount(uuid, id), 'the first address counts').toBe(1)

	await render(token, first)
	expect(
		await viewCount(uuid, id),
		'CONTROL: the same address inside the window must not count again',
	).toBe(1)

	await render(token, second)
	expect(
		await viewCount(uuid, id),
		'a second address inside the same window must count on its own. If this '
			+ 'stays 1, the instance ignored X-Forwarded-For: the runner reaches it '
			+ `from outside ${RUNNER_RANGES.join(', ')}, or forwarded_for_headers is set`,
	).toBe(2)
})

// @e2e dashboard-public-share::renders-beyond-the-60-second-window-reset-the-debounce
test('a render 65 seconds after the last one counts again', async () => {
	test.setTimeout(150_000)
	const { uuid, id, token } = await linkedDashboard('debounce window')

	await render(token)
	expect(await viewCount(uuid, id), 'the first render counts').toBe(1)
	await render(token)
	expect(
		await viewCount(uuid, id),
		'CONTROL: a render inside the window must not count',
	).toBe(1)

	await pause(65_000)
	await render(token)
	expect(
		await viewCount(uuid, id),
		'a render after the 60-second window must count again',
	).toBe(2)
})

// @e2e dashboard-public-share::10-failed-unlocks-per-60-seconds-allowed
// @e2e dashboard-public-share::unlock-is-throttled-to-prevent-brute-force
test('ten wrong passwords from one address are answered 401, the eleventh 429 with Retry-After', async () => {
	test.setTimeout(480_000)
	const { token } = await linkedDashboard('unlock limit', 'Right-password-1')
	const ip = freshAddress()

	for (let attempt = 1; attempt <= UNLOCK_LIMIT; attempt++) {
		expect(
			await wrongUnlock(token, ip),
			`failed attempt ${attempt} of ${UNLOCK_LIMIT} must still be answered 401`,
		).toBe(401)
	}
	const eleventh = await wrongUnlockResponse(token, ip)
	expect(eleventh.status(), `attempt ${UNLOCK_LIMIT + 1} must be throttled`).toBe(429)
	expect(
		eleventh.headers()['retry-after'],
		'the 429 must tell the client when to retry',
	).toMatch(/^\d+$/)
})

// @e2e dashboard-public-share::throttle-is-ip-global-across-all-shares
test('ten wrong passwords on one link throttle the same address on another link', async () => {
	test.setTimeout(480_000)
	const a = await linkedDashboard('throttle share A', 'Right-password-A1')
	const b = await linkedDashboard('throttle share B', 'Right-password-B1')
	const ip = freshAddress()

	expect(
		await wrongUnlock(b.token, freshAddress()),
		'CONTROL: link B answers a fresh address with 401',
	).toBe(401)

	for (let attempt = 1; attempt <= UNLOCK_LIMIT; attempt++) {
		await wrongUnlock(a.token, ip)
	}
	expect(
		await wrongUnlock(b.token, ip),
		'the attempts on link A must count against the same address on link B',
	).toBe(429)
})

// @e2e dashboard-public-share::throttle-resets-after-time-window
test('an address throttled on unlock is accepted again after 60 quiet seconds', async () => {
	test.setTimeout(540_000)
	const { token } = await linkedDashboard('throttle reset', 'Right-password-1')
	const ip = freshAddress()

	let throttled = false
	for (let attempt = 1; attempt <= UNLOCK_LIMIT + 5 && !throttled; attempt++) {
		throttled = (await wrongUnlock(token, ip)) === 429
	}
	expect(throttled, `CONTROL: the address must be throttled within ${UNLOCK_LIMIT + 5} failures`).toBe(true)

	await pause(61_000)
	expect(
		await wrongUnlock(token, ip),
		'after 60 seconds without attempts the next one must be judged again (401), not throttled',
	).toBe(401)
})
