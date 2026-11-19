/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the background feed refresh
 * (openspec/specs/background-job-feed-refresh), written for
 * e2e-coverage-programme task 5.2.
 *
 * Almost all of this capability runs inside the cron job and writes only to
 * `oc_launchpad_feed_cache`, which no HTTP endpoint reads. The part with an
 * HTTP outcome is the admin refresh-now endpoint (REQ-FRJ-010):
 * `POST /api/admin/feeds/refresh-now` runs the same sweep synchronously and
 * answers `{processedCount, successCount, failureCount, durationMs}`. Its
 * `processedCount` is the size of the discovered feed set, which also makes
 * feed discovery (REQ-FRJ-003) observable: add a placement, sweep again, and
 * the count moves by exactly the number of URLs that placement contributes.
 *
 * Every feed URL used here is `https://127.0.0.1/...`. The service's SSRF
 * guard (`UrlSafetyValidator::isSafe`) refuses loopback before any request is
 * made, so the sweep records a failure per URL and never leaves the instance.
 * That is also why the outbound-request scenarios (conditional GET, parsing,
 * User-Agent, proxy) are excluded in the spec: a local feed server can never
 * be fetched through that guard.
 *
 * Side effect: each URL swept gets a row in `oc_launchpad_feed_cache`. There is
 * no HTTP path that deletes those rows; they are left for the orphan cleanup.
 *
 * Gate-19 @e2e traceability:
 *   @e2e background-job-feed-refresh::discovery-ignores-non-news-widget-placements
 *   @e2e background-job-feed-refresh::full-refresh-triggered-by-admin
 *   @e2e background-job-feed-refresh::single-url-refresh-triggered-by-admin
 *   @e2e background-job-feed-refresh::non-admin-receives-http-403
 *   @e2e background-job-feed-refresh::invalid-feedurl-scheme-rejected
 *   @e2e background-job-feed-refresh::response-body-includes-timing-metadata
 *
 * @spec openspec/specs/background-job-feed-refresh/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const REFRESH = `${API}/admin/feeds/refresh-now`
const RUN = Date.now()

/** The batch size of one sweep; above it the counts span several calls. */
const BATCH_SIZE = 500

type Summary = {
	processedCount: number
	successCount: number
	failureCount: number
	durationMs: number
}

let admin: APIRequestContext
let dashboardId: number | null = null
let restoreUserDash = false
const placementIds: number[] = []

/**
 * A feed URL unique to this run, on loopback so the SSRF guard refuses it
 * without any outbound request.
 *
 * @param name a readable suffix.
 * @return the URL.
 */
function feedUrl(name: string): string {
	return `https://127.0.0.1/e2e-frj-${RUN}/${name}.xml`
}

/**
 * Assert a refresh-now body is the summary the spec names: four integers,
 * a non-negative duration, and successes plus failures equal to processed.
 *
 * @param body the parsed response body.
 * @return the body, typed.
 */
function expectSummary(body: unknown): Summary {
	const summary = body as Summary
	for (const key of ['processedCount', 'successCount', 'failureCount', 'durationMs'] as const) {
		expect(Number.isInteger(summary[key]), `${key} in ${JSON.stringify(body)}`).toBe(true)
	}
	expect(summary.durationMs).toBeGreaterThanOrEqual(0)
	expect(summary.successCount + summary.failureCount).toBe(summary.processedCount)
	return summary
}

/**
 * Run a full sweep as the admin and return its summary.
 *
 * @return the summary.
 */
async function sweep(): Promise<Summary> {
	const res = await admin.post(REFRESH)
	expect(res.status(), await res.text()).toBe(200)
	return expectSummary(await res.json())
}

/**
 * A full sweep that is known to cover the whole discovered set in one call,
 * so its `processedCount` is the size of that set.
 *
 * @return the summary.
 */
async function baselineSweep(): Promise<Summary> {
	// A first sweep finishes whatever batch an earlier run left half-done.
	await sweep()
	const summary = await sweep()
	expect(
		summary.processedCount,
		'the instance has more feeds than one sweep covers; processedCount is not the set size',
	).toBeLessThan(BATCH_SIZE)
	return summary
}

/**
 * Place a widget on the run's dashboard and give it feed URLs in the column
 * the refresh service discovers them from (`styleConfig.feedUrls`).
 *
 * @param widgetId the widget type id.
 * @param styleConfig the style config to store.
 * @return the placement id.
 */
async function placeWidget(widgetId: string, styleConfig: Record<string, unknown>): Promise<number> {
	const created = await admin.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: { widgetId, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 4 },
	})
	expect(created.status(), await created.text()).toBe(201)
	const body = await created.json()
	const id = Number(body.id ?? body.data?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	placementIds.push(id)

	const styled = await admin.put(`${API}/widgets/${id}`, { data: { styleConfig } })
	expect(styled.status(), await styled.text()).toBe(200)

	return id
}

test.describe.configure({ mode: 'serial' })

test.describe('background-job-feed-refresh: admin refresh-now', () => {
	test.beforeAll(async () => {
		admin = await adminContext()
		await assertActingAs(admin, ADMIN_USER)

		const name = `E2E Feed Refresh ${RUN}`
		let created = await admin.post(`${API}/dashboard`, { data: { name } })
		if (created.status() === 403) {
			// Personal dashboards are off on this instance; turn them on for
			// the run and put the flag back afterwards.
			const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
			expect(enable.status(), await enable.text()).toBeLessThan(300)
			restoreUserDash = true
			created = await admin.post(`${API}/dashboard`, { data: { name } })
		}
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		dashboardId = Number(dash.id)
		expect(dashboardId, `no dashboard id in ${JSON.stringify(body)}`).toBeTruthy()
	})

	test.afterAll(async () => {
		for (const id of placementIds) {
			await admin.delete(`${API}/widgets/${id}`)
		}
		if (dashboardId !== null) {
			await admin.delete(`${API}/dashboard/${dashboardId}`)
		}
		if (restoreUserDash) {
			await admin.put(`${API}/admin/settings`, { data: { allowUserDash: false } })
		}
		await admin.dispose()
	})

	// @e2e background-job-feed-refresh::full-refresh-triggered-by-admin
	test('a bodyless POST by the admin runs the sweep and answers 200 with the summary', async () => {
		const res = await admin.post(REFRESH)

		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		expect(Object.keys(body).sort()).toEqual(
			['durationMs', 'failureCount', 'processedCount', 'successCount'],
		)
		expectSummary(body)
	})

	// @e2e background-job-feed-refresh::single-url-refresh-triggered-by-admin
	test('a feedUrl query parameter refreshes exactly that one feed', async () => {
		const only = feedUrl('single')
		const res = await admin.post(`${REFRESH}?feedUrl=${encodeURIComponent(only)}`)

		expect(res.status(), await res.text()).toBe(200)
		const summary = expectSummary(await res.json())
		// Only the named feed is attempted, even though the instance-wide
		// set (which this URL is not part of) may hold many more.
		expect(summary.processedCount).toBe(1)
		expect(summary.successCount + summary.failureCount).toBe(1)
	})

	// @e2e background-job-feed-refresh::non-admin-receives-http-403
	test('a regular user is refused with 403 and no sweep runs; the admin is not', async () => {
		let alice: Principal | null = null
		try {
			alice = await createPrincipal(admin, 'e2e-frj-alice')
			await assertActingAs(alice.api, alice.username)

			const refused = await alice.api.post(REFRESH)
			expect(refused.status(), await refused.text()).toBe(403)
			const refusedBody = await refused.json().catch(() => ({}))
			// No refresh happened: there is no summary to report.
			expect(refusedBody).not.toHaveProperty('processedCount')

			const allowed = await admin.post(REFRESH)
			expect(allowed.status(), await allowed.text()).toBe(200)
			expectSummary(await allowed.json())
		} finally {
			await removePrincipal(admin, alice)
		}
	})

	// @e2e background-job-feed-refresh::invalid-feedurl-scheme-rejected
	test('an ftp:// feedUrl is refused with 400 naming HTTP/HTTPS, before any fetch', async () => {
		const res = await admin.post(`${REFRESH}?feedUrl=${encodeURIComponent('ftp://bad.com/rss')}`)

		expect(res.status(), await res.text()).toBe(400)
		const body = await res.json()
		const message = String(body.error ?? body.message ?? '')
		expect(message).toMatch(/http/i)
		expect(message).toMatch(/https/i)
		// Refused at validation: no summary, so nothing was fetched.
		expect(body).not.toHaveProperty('processedCount')
	})

	// @e2e background-job-feed-refresh::discovery-ignores-non-news-widget-placements
	test('feed URLs on a launchpad_links placement are not discovered; on launchpad_news they are', async () => {
		const before = await baselineSweep()

		await placeWidget('launchpad_links', { feedUrls: [feedUrl('links-only')] })
		const withLinks = await sweep()
		expect(
			withLinks.processedCount,
			'a launchpad_links placement must add no URL to the discovered set',
		).toBe(before.processedCount)

		await placeWidget('launchpad_news', { feedUrls: [feedUrl('news-only')] })
		const withNews = await sweep()
		expect(
			withNews.processedCount,
			'a launchpad_news placement with one URL must add exactly that URL',
		).toBe(before.processedCount + 1)
	})

	// @e2e background-job-feed-refresh::response-body-includes-timing-metadata
	test('a full refresh over 30 active feeds reports durationMs and counts that add up', async () => {
		const before = await baselineSweep()

		const urls = Array.from({ length: 30 }, (_, i) => feedUrl(`timing-${String(i).padStart(2, '0')}`))
		await placeWidget('launchpad_news', { feedUrls: urls })

		const summary = await sweep()
		expect(Number.isInteger(summary.durationMs)).toBe(true)
		expect(summary.durationMs).toBeGreaterThanOrEqual(0)
		// processedCount is the number of feeds attempted: everything that was
		// there before plus the 30 just placed.
		expect(summary.processedCount).toBe(before.processedCount + 30)
		expect(summary.successCount + summary.failureCount).toBe(summary.processedCount)
		// The 30 loopback feeds are refused by the SSRF guard, so each one is
		// a recorded failure rather than a silently dropped feed.
		expect(summary.failureCount).toBeGreaterThanOrEqual(30)
	})
})
