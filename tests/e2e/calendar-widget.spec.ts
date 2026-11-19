/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the calendar widget's events endpoint
 * (openspec/specs/calendar-widget, REQ-CAL-003 / 004), written for
 * e2e-coverage-programme.
 *
 * These scenarios are about what `GET /api/widgets/calendar/{id}/events`
 * answers, so they call it directly. Everything behind it is real: calendars
 * and events are written over CalDAV (`support/calendarFixture.ts`), the
 * placements through LaunchPad's own widget API, and the accounts are real,
 * signed-in principals over basic auth.
 *
 * Gate-19 @e2e traceability:
 *   @e2e calendar-widget::fetch-events-for-date-range
 *   @e2e calendar-widget::no-events-returns-empty-array
 *   @e2e calendar-widget::missing-placement-returns-404
 *   @e2e calendar-widget::ownership-check
 *   @e2e calendar-widget::invalid-date-range-returns-400
 *   @e2e calendar-widget::endpoint-requires-no-csrf-token
 *   @e2e calendar-widget::rrule-expansion-for-internal-events
 *   @e2e calendar-widget::all-day-recurring-events
 *   @e2e calendar-widget::exceptions-to-recurring-series
 *
 * @spec openspec/specs/calendar-widget/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededCalendar } from './support/calendarFixture.ts'

import { expect, test } from '@playwright/test'
import {
	API,
	calendarKey,
	createCalendar,
	createDashboard,
	deleteCalendar,
	FAILING_ICS,
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

let admin: APIRequestContext

/** What each test created, removed in `afterEach` whatever the outcome. */
const created: {
	calendars: Array<{ api: APIRequestContext, calendar: SeededCalendar }>
	dashboards: Array<{ api: APIRequestContext, id: number }>
} = { calendars: [], dashboards: [] }

/**
 * A calendar of the admin's, removed after the test.
 *
 * @param label a readable part of the name.
 * @return the calendar.
 */
async function adminCalendar(label: string): Promise<SeededCalendar> {
	const calendar = await createCalendar(admin, ADMIN_USER, `E2E ${label} ${STAMP}`)
	created.calendars.push({ api: admin, calendar })
	return calendar
}

/**
 * A dashboard owned by the calling context, removed after the test.
 *
 * @param api the owner.
 * @param label a readable part of the name.
 * @return the dashboard id.
 */
async function dashboardOf(api: APIRequestContext, label: string): Promise<number> {
	const { id } = await createDashboard(api, `E2E Calendar ${label} ${STAMP}`)
	created.dashboards.push({ api, id })
	return id
}

test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	await allowPersonalDashboards(admin, true)
})

test.afterEach(async () => {
	for (const { api, id } of created.dashboards.splice(0)) {
		await api.delete(`${API}/dashboard/${id}`)
	}
	for (const { api, calendar } of created.calendars.splice(0)) {
		await deleteCalendar(api, calendar)
	}
})

test.afterAll(async () => {
	await admin?.dispose()
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-003 — the events endpoint
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-CAL-003 events endpoint', () => {
	// @e2e calendar-widget::fetch-events-for-date-range
	test('returns the merged events of the range, each with the full event shape', async () => {
		const calendar = await adminCalendar('Range')
		await putEvent(admin, calendar, {
			uid: `range-in-${STAMP}`,
			summary: 'Quarterly planning',
			start: '20260510T090000Z',
			end: '20260510T100000Z',
			location: 'Room 4',
			description: 'Bring the numbers',
		})
		await putEvent(admin, calendar, {
			uid: `range-out-${STAMP}`,
			summary: 'June offsite',
			start: '20260615T090000Z',
			end: '20260615T100000Z',
		})
		const key = await calendarKey(admin, calendar.name)
		const dashboardId = await dashboardOf(admin, 'Range')
		const placementId = await placeCalendar(admin, dashboardId, {
			internalCalendars: [key],
			externalIcsUrls: [FAILING_ICS],
		})

		const { status, body } = await getEvents(admin, placementId, '2026-05-01', '2026-05-31')
		expect(status, JSON.stringify(body)).toBe(200)
		expect(Array.isArray(body.events), JSON.stringify(body)).toBe(true)

		// Filtered to the requested range.
		expect(titled(body, 'June offsite')).toHaveLength(0)
		const [event] = titled(body, 'Quarterly planning')
		expect(event, JSON.stringify(body.events)).toBeTruthy()

		expect(event.uid).toBe(`range-in-${STAMP}`)
		expect(typeof event.title).toBe('string')
		expect(new Date(event.start).toISOString()).toBe('2026-05-10T09:00:00.000Z')
		expect(new Date(event.end).toISOString()).toBe('2026-05-10T10:00:00.000Z')
		expect(event.allDay).toBe(false)
		expect(event.location).toBe('Room 4')
		expect(event.description).toBe('Bring the numbers')
		expect(event.calendarId).toBe(key)
		expect(typeof event.calendarName).toBe('string')
		expect(event.source).toBe('internal')
		// "color (string, hex or NC theme var)": the calendar was created
		// with a colour, so there is one to report.
		expect(typeof event.color, JSON.stringify(event)).toBe('string')
		expect(String(event.color)).toMatch(/^(#[0-9a-f]{3,8}|var\(--[\w-]+\))$/i)
	})

	// @e2e calendar-widget::no-events-returns-empty-array
	test('a configured calendar with nothing in the range answers 200 with no events', async () => {
		const calendar = await adminCalendar('Empty')
		const key = await calendarKey(admin, calendar.name)
		const dashboardId = await dashboardOf(admin, 'Empty')
		const placementId = await placeCalendar(admin, dashboardId, { internalCalendars: [key] })

		const { status, body } = await getEvents(admin, placementId)
		expect(status, JSON.stringify(body)).toBe(200)
		expect(body.events).toEqual([])
	})

	// @e2e calendar-widget::missing-placement-returns-404
	test('a placement id that does not exist answers 404', async () => {
		// A well-formed range, so the answer is about the placement and not
		// about the missing `from`/`to` (which the 400 scenario covers).
		const { status, body } = await getEvents(admin, 2_000_000_000)
		expect(status, JSON.stringify(body)).toBe(404)
	})

	// @e2e calendar-widget::ownership-check
	test('another signed-in user is refused with 403; the owner is answered', async () => {
		const alice = await createPrincipal(admin, 'cal-alice')
		const bob = await createPrincipal(admin, 'cal-bob')
		try {
			await assertActingAs(alice.api, alice.username)
			await assertActingAs(bob.api, bob.username)
			const dashboardId = await dashboardOf(alice.api, 'Owned')
			const placementId = await placeCalendar(alice.api, dashboardId, {
				internalCalendars: ['not-a-calendar'],
			})

			const asBob = await getEvents(bob.api, placementId)
			expect(asBob.status, JSON.stringify(asBob.body)).toBe(403)
			expect(asBob.body?.events).toBeUndefined()

			// The endpoint works for the owner, so the 403 is a refusal and
			// not a broken route.
			const asAlice = await getEvents(alice.api, placementId)
			expect(asAlice.status, JSON.stringify(asAlice.body)).toBe(200)
		} finally {
			for (const { api, id } of created.dashboards.splice(0)) {
				await api.delete(`${API}/dashboard/${id}`)
			}
			await removePrincipal(admin, alice)
			await removePrincipal(admin, bob)
		}
	})

	// @e2e calendar-widget::invalid-date-range-returns-400
	test('an unparseable or missing bound answers 400 with an error detail', async () => {
		const dashboardId = await dashboardOf(admin, 'Range400')
		const placementId = await placeCalendar(admin, dashboardId, { internalCalendars: ['x'] })
		const url = `${API}/widgets/calendar/${placementId}/events`

		const invalid = await admin.get(url, { params: { from: 'invalid', to: '2026-05-31' } })
		expect(invalid.status()).toBe(400)
		const detail = await invalid.json()
		expect(typeof detail.error).toBe('string')
		expect(detail.error.length).toBeGreaterThan(0)

		// Both bounds are required.
		const noTo = await admin.get(url, { params: { from: '2026-05-01' } })
		expect(noTo.status()).toBe(400)
		const noFrom = await admin.get(url, { params: { to: '2026-05-31' } })
		expect(noFrom.status()).toBe(400)

		// A well-formed range on the same placement is answered.
		const valid = await admin.get(url, { params: { from: '2026-05-01', to: '2026-05-31' } })
		expect(valid.status()).toBe(200)
	})

	// @e2e calendar-widget::endpoint-requires-no-csrf-token
	test('repeated browser-session calls without a request token are answered', async ({ page }) => {
		const dashboardId = await dashboardOf(admin, 'Csrf')
		const placementId = await placeCalendar(admin, dashboardId, { internalCalendars: ['x'] })

		// The admin's browser session (the project's storageState). The
		// fetch carries the session cookie but neither a `requesttoken` nor
		// `OCS-APIRequest`, so Nextcloud's CSRF check applies unless the
		// method is marked #[NoCSRFRequired]: without it this answers 412.
		await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
		const statuses = await page.evaluate(async (url) => {
			const out: number[] = []
			for (let i = 0; i < 3; i++) {
				const res = await fetch(url, { credentials: 'same-origin' })
				out.push(res.status)
			}
			return out
		}, `/index.php/apps/launchpad/api/widgets/calendar/${placementId}/events?from=2026-05-01&to=2026-05-31`)
		expect(statuses).toEqual([200, 200, 200])
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// REQ-CAL-004 — recurring events, through the internal (CalDAV) source
// ─────────────────────────────────────────────────────────────────────────────

test.describe('REQ-CAL-004 recurring event expansion', () => {
	// @e2e calendar-widget::rrule-expansion-for-internal-events
	test('a daily COUNT=5 series comes back as five instances', async () => {
		const calendar = await adminCalendar('Daily')
		await putEvent(admin, calendar, {
			uid: `standup-${STAMP}`,
			summary: 'Daily standup',
			start: '20260501T090000Z',
			end: '20260501T091500Z',
			rrule: 'FREQ=DAILY;COUNT=5',
		})
		const key = await calendarKey(admin, calendar.name)
		const placementId = await placeCalendar(admin, await dashboardOf(admin, 'Daily'), {
			internalCalendars: [key],
		})

		const { status, body } = await getEvents(admin, placementId, '2026-05-01', '2026-05-31')
		expect(status).toBe(200)
		const instances = titled(body, 'Daily standup')
		expect(instances, JSON.stringify(body.events)).toHaveLength(5)
		const starts = instances.map((e) => new Date(e.start).toISOString())
		expect(new Set(starts).size).toBe(5)
		expect(starts.sort()).toEqual([
			'2026-05-01T09:00:00.000Z',
			'2026-05-02T09:00:00.000Z',
			'2026-05-03T09:00:00.000Z',
			'2026-05-04T09:00:00.000Z',
			'2026-05-05T09:00:00.000Z',
		])
		// Every instance shares the series' uid root.
		for (const instance of instances) {
			expect(String(instance.uid)).toContain(`standup-${STAMP}`)
		}
	})

	// @e2e calendar-widget::all-day-recurring-events
	test('instances of an all-day series stay all-day with valid ISO dates', async () => {
		const calendar = await adminCalendar('AllDay')
		await putEvent(admin, calendar, {
			uid: `allday-${STAMP}`,
			summary: 'Office closed',
			start: '20260504',
			end: '20260505',
			allDay: true,
			rrule: 'FREQ=WEEKLY;COUNT=3',
		})
		const key = await calendarKey(admin, calendar.name)
		const placementId = await placeCalendar(admin, await dashboardOf(admin, 'AllDay'), {
			internalCalendars: [key],
		})

		const { status, body } = await getEvents(admin, placementId, '2026-05-01', '2026-05-31')
		expect(status).toBe(200)
		const instances = titled(body, 'Office closed')
		expect(instances, JSON.stringify(body.events)).toHaveLength(3)
		for (const instance of instances) {
			expect(instance.allDay, JSON.stringify(instance)).toBe(true)
			expect(String(instance.start)).toMatch(/^\d{4}-\d{2}-\d{2}/)
			expect(Number.isNaN(new Date(instance.start).getTime())).toBe(false)
		}
		expect(instances.map((e) => String(e.start).slice(0, 10)).sort())
			.toEqual(['2026-05-04', '2026-05-11', '2026-05-18'])
	})

	// @e2e calendar-widget::exceptions-to-recurring-series
	test('an EXDATE removes exactly that instance', async () => {
		const calendar = await adminCalendar('Exdate')
		await putEvent(admin, calendar, {
			uid: `exdate-${STAMP}`,
			summary: 'Coaching',
			start: '20260511T130000Z',
			end: '20260511T140000Z',
			rrule: 'FREQ=DAILY;COUNT=5',
			exdate: '20260513T130000Z',
		})
		const key = await calendarKey(admin, calendar.name)
		const placementId = await placeCalendar(admin, await dashboardOf(admin, 'Exdate'), {
			internalCalendars: [key],
		})

		const { status, body } = await getEvents(admin, placementId, '2026-05-01', '2026-05-31')
		expect(status).toBe(200)
		const days = titled(body, 'Coaching').map((e) => new Date(e.start).toISOString().slice(0, 10)).sort()
		expect(days, JSON.stringify(body.events)).toEqual([
			'2026-05-11',
			'2026-05-12',
			'2026-05-14',
			'2026-05-15',
		])
	})
})
