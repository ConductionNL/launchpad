/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for which sources the calendar widget's events
 * endpoint reads (openspec/specs/calendar-widget, REQ-CAL-006 / 007), written
 * for e2e-coverage-programme. Split from calendar-widget.spec.ts to keep each
 * file readable; the setup is the same: calendars and events over CalDAV
 * (`support/calendarFixture.ts`), placements through the widget API, and real
 * signed-in principals over basic auth.
 *
 * The allow-list scenarios (REQ-CAL-006) need the Nextcloud server to resolve
 * a public hostname, because the SSRF guard runs before the allow-list and
 * refuses a host that does not resolve. They use the IANA documentation hosts
 * (example.com / example.org). `beforeAll` probes that once WITHOUT any fetch
 * (the probe host is outside a one-entry allow-list); a server that cannot
 * resolve them skips those tests and says so. The logging those scenarios
 * mention is not readable from the fixture (it logs at INFO, below the
 * instance's log level); the `failures` entry naming the URL is what the
 * tests assert instead.
 *
 * Gate-19 @e2e traceability:
 *   @e2e calendar-widget::allow-list-is-empty-default
 *   @e2e calendar-widget::allow-list-is-populated
 *   @e2e calendar-widget::disallowed-url-is-silently-skipped
 *   @e2e calendar-widget::allow-list-hostname-matching-is-case-insensitive
 *   @e2e calendar-widget::subdomain-matching-is-not-applied
 *   @e2e calendar-widget::user-can-read-internal-calendar-events
 *   @e2e calendar-widget::private-events-are-absent
 *   @e2e calendar-widget::shared-calendar-with-read-permission
 *   @e2e calendar-widget::shared-calendar-without-permission
 *
 * @spec openspec/specs/calendar-widget/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededCalendar } from './support/calendarFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	API,
	calendarKey,
	createCalendar,
	createDashboard,
	deleteCalendar,
	getEvents,
	placeCalendar,
	putEvent,
	titled,
} from './support/calendarFixture.ts'
import {
	adminContext,
	allowPersonalDashboards,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const STAMP = Date.now().toString(36)
const ALLOW_LIST = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/calendar_widget_allowed_ics_hosts'

let admin: APIRequestContext

test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	await allowPersonalDashboards(admin, true)
})

test.afterAll(async () => {
	await admin?.dispose()
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-006 — the external ICS host allow-list
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-CAL-006 external ICS allow-list', () => {
	test.describe.configure({ mode: 'serial' })

	let previous: string | null = null
	let dnsUnavailable = false
	let dashboardId = 0

	/**
	 * Set the allow-list (the app value the service reads), or clear it.
	 *
	 * @param hosts the hostnames, or null to remove the setting.
	 * @return nothing.
	 */
	async function setAllowList(hosts: string[] | null): Promise<void> {
		if (hosts === null) {
			const res = await admin.delete(`${ALLOW_LIST}?format=json`)
			expect(res.status(), await res.text()).toBeLessThan(300)
			return
		}
		const res = await admin.post(`${ALLOW_LIST}?format=json`, {
			form: { value: JSON.stringify(hosts) },
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
	}

	/**
	 * The `failures` entries naming a URL, after one events fetch.
	 *
	 * @param urls the placement's external URLs.
	 * @return the body and a lookup of failures per URL.
	 */
	async function fetchWith(urls: string[]): Promise<{ body: any, failuresFor: (url: string) => string[] }> {
		const placementId = await placeCalendar(admin, dashboardId, { externalIcsUrls: urls })
		const { status, body } = await getEvents(admin, placementId)
		expect(status, JSON.stringify(body)).toBe(200)
		const failures: string[] = Array.isArray(body.failures) ? body.failures.map(String) : []
		return { body, failuresFor: (url: string) => failures.filter((f) => f.endsWith(` ${url}`)) }
	}

	test.beforeAll(async () => {
		const res = await admin.get(`${ALLOW_LIST}?format=json`)
		const value = res.ok() ? (await res.json())?.ocs?.data?.data : null
		previous = typeof value === 'string' && value !== '' ? value : null

		const dash = await createDashboard(admin, `E2E Calendar allow-list ${STAMP}`)
		dashboardId = dash.id

		// Probe, without fetching anything: with a one-entry list that does
		// not name it, a host that RESOLVES is refused by the allow-list,
		// and one that does not is refused earlier by the SSRF guard.
		await setAllowList(['probe.invalid'])
		const probe = 'https://www.example.com/launchpad-e2e-probe.ics'
		const { failuresFor } = await fetchWith([probe])
		dnsUnavailable = failuresFor(probe).some((f) => f.includes('rejected URL'))
	})

	test.beforeEach(() => {
		test.skip(
			dnsUnavailable,
			'the Nextcloud server cannot resolve public hostnames, so the SSRF guard refuses every external URL before the allow-list is consulted',
		)
	})

	test.afterAll(async () => {
		await setAllowList(previous === null ? null : JSON.parse(previous))
		if (dashboardId !== 0) {
			await admin.delete(`${API}/dashboard/${dashboardId}`)
		}
	})

	/**
	 * Whether the service went past every refusal and tried the URL: a
	 * fetch or parse failure for it (the documentation hosts serve no ICS).
	 *
	 * @param failures the failures naming the URL.
	 * @return true when it was fetched.
	 */
	function wasFetched(failures: string[]): boolean {
		return failures.every((f) => !/not in allow-list|rejected URL/.test(f))
			&& failures.some((f) => /(fetch|parse) failed/.test(f))
	}

	// @e2e calendar-widget::allow-list-is-empty-default
	test('with no allow-list set, a URL on any host is allowed and fetched', async () => {
		await setAllowList(null)
		const url = `https://www.example.org/launchpad-e2e-${STAMP}.ics`
		const { failuresFor } = await fetchWith([url])
		expect(wasFetched(failuresFor(url)), JSON.stringify(failuresFor(url))).toBe(true)

		// An empty array means the same as no setting.
		await setAllowList([])
		const again = await fetchWith([url])
		expect(wasFetched(again.failuresFor(url)), JSON.stringify(again.failuresFor(url))).toBe(true)
	})

	// @e2e calendar-widget::allow-list-is-populated
	test('a listed host is fetched; an unlisted one is skipped', async () => {
		await setAllowList(['www.example.com', 'feeds.internal.org'])
		const listed = `https://www.example.com/launchpad-e2e-${STAMP}.ics`
		const unlisted = `https://www.example.org/launchpad-e2e-${STAMP}.ics`
		const { failuresFor } = await fetchWith([listed, unlisted])

		expect(wasFetched(failuresFor(listed)), JSON.stringify(failuresFor(listed))).toBe(true)
		expect(failuresFor(unlisted)).toEqual([`external: not in allow-list ${unlisted}`])
	})

	// @e2e calendar-widget::disallowed-url-is-silently-skipped
	test('an unlisted URL is skipped without a fetch and the answer is still 200', async () => {
		await setAllowList(['www.example.com'])
		const untrusted = `https://www.example.org/untrusted-${STAMP}.ics`
		const { body, failuresFor } = await fetchWith([untrusted])

		// Refused at the allow-list, i.e. before any request was made, and
		// contributes nothing to the events.
		expect(failuresFor(untrusted)).toEqual([`external: not in allow-list ${untrusted}`])
		expect(body.events).toEqual([])
	})

	// @e2e calendar-widget::allow-list-hostname-matching-is-case-insensitive
	test('a mixed-case allow-list entry matches the lower-case host', async () => {
		await setAllowList(['WWW.Example.COM'])
		const url = `https://www.example.com/launchpad-e2e-case-${STAMP}.ics`
		const { failuresFor } = await fetchWith([url])
		expect(wasFetched(failuresFor(url)), JSON.stringify(failuresFor(url))).toBe(true)
	})

	// @e2e calendar-widget::subdomain-matching-is-not-applied
	test('listing a domain does not admit its subdomains', async () => {
		await setAllowList(['example.com'])
		const url = `https://www.example.com/launchpad-e2e-sub-${STAMP}.ics`
		const { failuresFor } = await fetchWith([url])
		expect(failuresFor(url)).toEqual([`external: not in allow-list ${url}`])
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-007 — view-time access control
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-CAL-007 view-time access control', () => {
	test.describe.configure({ mode: 'serial' })

	let alice: Principal | null = null
	let bob: Principal | null = null
	let aliceOwn: SeededCalendar | null = null
	let bobShared: SeededCalendar | null = null
	let bobPrivate: SeededCalendar | null = null
	let sharedEventPath = ''
	let aliceDashboard = 0
	let bobDashboard = 0

	test.beforeAll(async () => {
		alice = await createPrincipal(admin, 'cal-reader')
		bob = await createPrincipal(admin, 'cal-sharer')
		await assertActingAs(alice.api, alice.username)
		await assertActingAs(bob.api, bob.username)

		aliceOwn = await createCalendar(alice.api, alice.username, `E2E Alice own ${STAMP}`)
		await putEvent(alice.api, aliceOwn, {
			uid: `alice-own-${STAMP}`,
			summary: 'Alice dentist',
			start: '20260512T080000Z',
			end: '20260512T090000Z',
		})

		bobShared = await createCalendar(bob.api, bob.username, `E2E Bob team ${STAMP}`)
		sharedEventPath = await putEvent(bob.api, bobShared, {
			uid: `bob-public-${STAMP}`,
			summary: 'Team retro',
			start: '20260514T100000Z',
			end: '20260514T110000Z',
			klass: 'PUBLIC',
		})
		await putEvent(bob.api, bobShared, {
			uid: `bob-private-${STAMP}`,
			summary: 'Bob private appointment',
			start: '20260514T130000Z',
			end: '20260514T140000Z',
			klass: 'PRIVATE',
		})

		bobPrivate = await createCalendar(bob.api, bob.username, `E2E Bob diary ${STAMP}`)
		await putEvent(bob.api, bobPrivate, {
			uid: `bob-diary-${STAMP}`,
			summary: 'Bob unshared diary entry',
			start: '20260515T100000Z',
			end: '20260515T110000Z',
		})

		// Share the team calendar with alice, read-only (no <o:read-write/>).
		const share = await bob.api.post(
			`/remote.php/dav/calendars/${encodeURIComponent(bob.username)}/${bobShared.uri}/`,
			{
				headers: { 'Content-Type': 'application/xml; charset=utf-8' },
				data: '<?xml version="1.0" encoding="utf-8"?>'
					+ '<o:share xmlns:d="DAV:" xmlns:o="http://owncloud.org/ns"><o:set>'
					+ `<d:href>principal:principals/users/${alice.username}</d:href>`
					+ '</o:set></o:share>',
			},
		)
		expect(share.status(), await share.text()).toBeLessThan(300)

		aliceDashboard = (await createDashboard(alice.api, `E2E Alice calendar ${STAMP}`)).id
		bobDashboard = (await createDashboard(bob.api, `E2E Bob calendar ${STAMP}`)).id
	})

	test.afterAll(async () => {
		if (alice !== null && aliceDashboard !== 0) {
			await alice.api.delete(`${API}/dashboard/${aliceDashboard}`)
		}
		if (bob !== null && bobDashboard !== 0) {
			await bob.api.delete(`${API}/dashboard/${bobDashboard}`)
		}
		if (alice !== null) {
			await deleteCalendar(alice.api, aliceOwn)
		}
		if (bob !== null) {
			await deleteCalendar(bob.api, bobShared)
			await deleteCalendar(bob.api, bobPrivate)
		}
		await removePrincipal(admin, alice)
		await removePrincipal(admin, bob)
	})

	// @e2e calendar-widget::user-can-read-internal-calendar-events
	test('the viewer reads her own calendar through her own placement', async () => {
		const key = await calendarKey(alice!.api, aliceOwn!.name)
		const placementId = await placeCalendar(alice!.api, aliceDashboard, { internalCalendars: [key] })

		const { status, body } = await getEvents(alice!.api, placementId)
		expect(status, JSON.stringify(body)).toBe(200)
		const titles = (body.events ?? []).map((e: Record<string, any>) => e.title)
		expect(titles).toContain('Alice dentist')
		// Only what she can read: none of bob's events leak in.
		expect(titles).not.toContain('Team retro')
		expect(titles).not.toContain('Bob unshared diary entry')
	})

	// @e2e calendar-widget::private-events-are-absent
	test('a private event in a calendar shared with the viewer is absent for her', async () => {
		// Control: the owner sees both events, so the private one exists and
		// is inside the window.
		const ownerKey = await calendarKey(bob!.api, bobShared!.name)
		const ownerPlacement = await placeCalendar(bob!.api, bobDashboard, { internalCalendars: [ownerKey] })
		const asOwner = await getEvents(bob!.api, ownerPlacement)
		expect(asOwner.status).toBe(200)
		expect(titled(asOwner.body, 'Bob private appointment')).toHaveLength(1)

		const key = await calendarKey(alice!.api, bobShared!.name)
		const placementId = await placeCalendar(alice!.api, aliceDashboard, { internalCalendars: [key] }, 10)
		const { status, body } = await getEvents(alice!.api, placementId)
		expect(status, JSON.stringify(body)).toBe(200)
		expect(titled(body, 'Bob private appointment')).toHaveLength(0)
		expect(titled(body, 'Team retro')).toHaveLength(1)
	})

	// @e2e calendar-widget::shared-calendar-with-read-permission
	test('events of a read-only shared calendar are included and left unmodified', async () => {
		const before = await bob!.api.get(sharedEventPath)
		expect(before.status()).toBe(200)
		const etagBefore = before.headers().etag
		const bodyBefore = await before.text()

		const key = await calendarKey(alice!.api, bobShared!.name)
		const placementId = await placeCalendar(alice!.api, aliceDashboard, { internalCalendars: [key] }, 20)
		const { status, body } = await getEvents(alice!.api, placementId)
		expect(status, JSON.stringify(body)).toBe(200)
		const [retro] = titled(body, 'Team retro')
		expect(retro, JSON.stringify(body.events)).toBeTruthy()
		expect(retro.calendarId).toBe(key)

		const after = await bob!.api.get(sharedEventPath)
		expect(after.headers().etag).toBe(etagBefore)
		expect(await after.text()).toBe(bodyBefore)
	})

	// @e2e calendar-widget::shared-calendar-without-permission
	test('a configured calendar the viewer cannot read yields nothing and no error', async () => {
		const ownKey = await calendarKey(alice!.api, aliceOwn!.name)
		const unsharedKey = await calendarKey(bob!.api, bobPrivate!.name)
		const placementId = await placeCalendar(
			alice!.api,
			aliceDashboard,
			{ internalCalendars: [unsharedKey, ownKey] },
			30,
		)

		const { status, body } = await getEvents(alice!.api, placementId)
		expect(status, JSON.stringify(body)).toBe(200)
		expect(titled(body, 'Bob unshared diary entry')).toHaveLength(0)
		// The rest of the placement still works, and nothing is surfaced as
		// an error.
		expect(titled(body, 'Alice dentist')).toHaveLength(1)
		expect(body.failures ?? []).toEqual([])
	})
})
