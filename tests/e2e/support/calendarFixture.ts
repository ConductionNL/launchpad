/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Real calendar data for the calendar-widget specs.
 *
 * WHY CALDAV AND NOT A STUB
 * =========================
 * The widget reads internal events through `OCP\Calendar\IManager::search()`,
 * which the `dav` app backs. `dav` is part of Nextcloud server and is always
 * enabled, so the CI fixture has it even though the Calendar *app* (the UI)
 * is not installed. Each spec therefore creates its own calendar over CalDAV
 * (MKCALENDAR), writes events into it (PUT of an .ics), and deletes it again
 * with `X-NC-CalDAV-No-Trashbin` so nothing lingers in the trash bin.
 *
 * The calendar KEY the widget stores is not the CalDAV URI: it is the key
 * `GET /api/widgets/calendar/calendars` lists (the config-form picker's
 * source), so `calendarKey()` reads it from there, as the product does.
 *
 * External ICS feeds cannot be served by the fixture: the SSRF guard accepts
 * only HTTPS hosts that resolve to public addresses, which rules out anything
 * the CI runner could host. Specs use `https://localhost/...` when they need
 * an external source that is guaranteed to fail.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'

/** The LaunchPad API root. */
export const API = '/index.php/apps/launchpad/api'

/** A URL the SSRF guard refuses on every instance: it resolves to loopback. */
export const FAILING_ICS = 'https://localhost/launchpad-e2e-unreachable.ics'

/** One calendar created for a spec. */
export interface SeededCalendar {
	owner: string
	uri: string
	name: string
	color: string
}

/** One event to write into a calendar. */
export interface EventSpec {
	uid: string
	summary: string
	/** `YYYYMMDDTHHMMSSZ`, or `YYYYMMDD` when `allDay`. */
	start: string
	end: string
	allDay?: boolean
	rrule?: string
	exdate?: string
	location?: string
	description?: string
	klass?: 'PUBLIC' | 'PRIVATE' | 'CONFIDENTIAL'
}

/**
 * The CalDAV collection URL of a calendar.
 *
 * @param owner the user id.
 * @param uri the calendar uri.
 * @return the path.
 */
function calendarPath(owner: string, uri: string): string {
	return `/remote.php/dav/calendars/${encodeURIComponent(owner)}/${encodeURIComponent(uri)}/`
}

/**
 * Create a calendar for `owner`, acting as `owner`.
 *
 * @param api a context acting as the owner (basic auth).
 * @param owner the owner's user id.
 * @param name the display name; make it unique.
 * @param color the calendar colour.
 * @return the calendar.
 */
export async function createCalendar(
	api: APIRequestContext,
	owner: string,
	name: string,
	color = '#0082c9',
): Promise<SeededCalendar> {
	const uri = `e2e-cal-${Date.now().toString(36)}-${Math.floor(Math.random() * 1e6)}`
	const body = '<?xml version="1.0" encoding="UTF-8"?>'
		+ '<c:mkcalendar xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:a="http://apple.com/ns/ical/">'
		+ '<d:set><d:prop>'
		+ `<d:displayname>${name}</d:displayname>`
		+ `<a:calendar-color>${color}</a:calendar-color>`
		+ '<c:supported-calendar-component-set><c:comp name="VEVENT"/></c:supported-calendar-component-set>'
		+ '</d:prop></d:set></c:mkcalendar>'
	const res = await api.fetch(calendarPath(owner, uri), {
		method: 'MKCALENDAR',
		headers: { 'Content-Type': 'application/xml; charset=utf-8' },
		data: body,
	})
	expect(res.status(), `MKCALENDAR ${uri}: ${await res.text()}`).toBe(201)
	return { owner, uri, name, color }
}

/**
 * Delete a calendar, skipping the CalDAV trash bin. Tolerates one already gone.
 *
 * @param api a context acting as the owner.
 * @param calendar the calendar, or null.
 * @return nothing.
 */
export async function deleteCalendar(
	api: APIRequestContext,
	calendar: SeededCalendar | null,
): Promise<void> {
	if (calendar === null) {
		return
	}
	await api.delete(calendarPath(calendar.owner, calendar.uri), {
		headers: { 'X-NC-CalDAV-No-Trashbin': '1' },
	})
}

/**
 * The iCalendar text of one event.
 *
 * @param event the event.
 * @return a VCALENDAR with one VEVENT, CRLF line endings.
 */
export function ics(event: EventSpec): string {
	const date = event.allDay ? ';VALUE=DATE' : ''
	const lines = [
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//LaunchPad e2e//calendar-widget//EN',
		'BEGIN:VEVENT',
		`UID:${event.uid}`,
		'DTSTAMP:20260401T000000Z',
		`DTSTART${date}:${event.start}`,
		`DTEND${date}:${event.end}`,
		`SUMMARY:${event.summary}`,
	]
	if (event.rrule) {
		lines.push(`RRULE:${event.rrule}`)
	}
	if (event.exdate) {
		lines.push(`EXDATE${date}:${event.exdate}`)
	}
	if (event.location) {
		lines.push(`LOCATION:${event.location}`)
	}
	if (event.description) {
		lines.push(`DESCRIPTION:${event.description}`)
	}
	if (event.klass) {
		lines.push(`CLASS:${event.klass}`)
	}
	lines.push('END:VEVENT', 'END:VCALENDAR', '')
	return lines.join('\r\n')
}

/**
 * Write one event into a calendar.
 *
 * @param api a context acting as the owner.
 * @param calendar the calendar.
 * @param event the event.
 * @return the object's path, for reading it back.
 */
export async function putEvent(
	api: APIRequestContext,
	calendar: SeededCalendar,
	event: EventSpec,
): Promise<string> {
	const path = `${calendarPath(calendar.owner, calendar.uri)}${encodeURIComponent(event.uid)}.ics`
	const res = await api.put(path, {
		headers: { 'Content-Type': 'text/calendar; charset=utf-8' },
		data: ics(event),
	})
	expect(res.status(), `PUT ${event.uid}: ${await res.text()}`).toBe(201)
	return path
}

/**
 * The key the widget stores for a calendar, as the config-form picker lists it.
 *
 * @param api a context acting as the user who will read the calendar.
 * @param name the calendar's display name (a sharee may see it suffixed).
 * @return the key.
 */
export async function calendarKey(api: APIRequestContext, name: string): Promise<string> {
	const res = await api.get(`${API}/widgets/calendar/calendars`)
	expect(res.status(), await res.text()).toBe(200)
	const calendars: Array<{ key: string, name: string }> = (await res.json()).calendars ?? []
	const match = calendars.find((c) => c.name === name)
		?? calendars.find((c) => c.name.startsWith(name))
	expect(
		match,
		`calendar "${name}" is not among ${JSON.stringify(calendars)}: the dav calendar provider did not list it`,
	).toBeTruthy()
	return String(match!.key)
}

/**
 * Create a dashboard as the calling user (not activated).
 *
 * @param api the user's context.
 * @param name the name.
 * @return its id and uuid.
 */
export async function createDashboard(
	api: APIRequestContext,
	name: string,
): Promise<{ id: number, uuid: string }> {
	const res = await api.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	return { id: Number(dash.id), uuid: String(dash.uuid) }
}

/**
 * Place a calendar widget on a dashboard (seeding only).
 *
 * @param api a context that may edit the dashboard.
 * @param dashboardId the dashboard.
 * @param content the placement content.
 * @param gridY the row to place it on.
 * @return the placement id.
 */
export async function placeCalendar(
	api: APIRequestContext,
	dashboardId: number,
	content: Record<string, unknown>,
	gridY = 0,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: { widgetId: 'calendar', gridX: 0, gridY, gridWidth: 12, gridHeight: 10, content },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id ?? body.placement?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * The calendar placements of a dashboard, read back from the API.
 *
 * @param api a context that may view the dashboard.
 * @param dashboardId the dashboard.
 * @return the placements whose widgetId is `calendar`.
 */
export async function calendarPlacements(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Array<Record<string, any>>> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const placements: Array<Record<string, any>> = body.placements ?? body.dashboard?.placements ?? []
	return placements.filter((p) => p.widgetId === 'calendar')
}

/**
 * GET the events endpoint.
 *
 * @param api the caller.
 * @param placementId the placement.
 * @param from the window start.
 * @param to the window end.
 * @return status and parsed body.
 */
export async function getEvents(
	api: APIRequestContext,
	placementId: number,
	from = '2026-05-01T00:00:00Z',
	to = '2026-05-31T23:59:59Z',
): Promise<{ status: number, body: any }> {
	const res = await api.get(`${API}/widgets/calendar/${placementId}/events`, {
		params: { from, to },
	})
	const text = await res.text()
	let body: any = text
	try {
		body = JSON.parse(text)
	} catch {
		// Not JSON; the caller's assertion reports the raw text.
	}
	return { status: res.status(), body }
}

/**
 * The events of one title in an events-endpoint body.
 *
 * @param body the endpoint body.
 * @param title the event title.
 * @return the matching events.
 */
export function titled(body: any, title: string): Array<Record<string, any>> {
	return (body?.events ?? []).filter((e: Record<string, any>) => e.title === title)
}
