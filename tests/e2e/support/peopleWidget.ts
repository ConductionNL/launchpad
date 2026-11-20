/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Fixtures for the people-widget specs: people with known names, emails,
 * groups and profile values, dashboards holding a `people` placement, and a
 * reader for `GET /api/people`.
 *
 * WHY EVERY PERSON IS PROVISIONED, AND EVERY LIST IS GROUP-FILTERED
 * ================================================================
 * The CI instance holds whatever accounts other specs created and did not
 * remove, so "everyone in the directory" is not a known set. A group created
 * for one spec file is: its members are exactly the people this file put in
 * it. So every assertion about WHO is listed reads a group-filtered list, and
 * the unfiltered directory is only ever checked for consistency (pages do not
 * overlap, the total adds up), never for a fixed membership.
 *
 * WHY PROFILE VALUES ARE WRITTEN AS THE PERSON THEMSELF
 * =====================================================
 * Nextcloud lets a person set the visibility scope of their own profile
 * properties and nobody else's, so `biographyScope` and friends go through the
 * person's own context. Display name and email go through the admin, who may
 * always set them.
 *
 * WHY THE ACTION MATRIX IS WIDENED FOR SOME SPECS
 * ===============================================
 * `lib/actions.seed.json` ships `"people-widget.get-users": ["admin"]`, so on
 * a default install a non-admin who opens a dashboard with a People widget
 * gets HTTP 403 and "Failed to load users". The scenarios that are about what
 * a colleague ("bob", "Sanne") may see need a colleague who can list at all,
 * so those specs grant the action to everyone for their own duration and put
 * the admin's matrix back afterwards. The default itself is reported as a
 * suspected defect, not asserted away.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './dashboardFixture.ts'

import { expect } from '@playwright/test'
import { deflateSync } from 'zlib'
import { seedActiveDashboard } from './dashboardFixture.ts'
import { addToGroup, API, createGroup, createPrincipal } from './principals.ts'

/** The people-listing route, relative to the instance. */
export const PEOPLE_API = `${API}/people`

/** A provisioned person: credentials, a context acting as them, and their name. */
export interface Person {
	username: string
	password: string
	displayName: string
	email: string | null
	api: APIRequestContext
}

/** What to give a provisioned person. */
export interface PersonSpec {
	displayName: string
	email?: string
	groups?: string[]
	/** Profile properties and scopes, written by the person themself (`role`, `biographyScope`, ...). */
	self?: Record<string, string>
}

/** One page of `GET /api/people`. */
export interface PeoplePage {
	users: Array<Record<string, unknown>>
	total: number
	hasMore: boolean
}

/**
 * Edit one property of an account through the OCS provisioning API.
 *
 * Uses OCS v2 so a refusal is an HTTP status, not a 200 with an error inside.
 *
 * @param api the context that edits (the admin, or the person themself).
 * @param username the account to edit.
 * @param key the provisioning key (`displayname`, `email`, `role`, `biographyScope`, ...).
 * @param value the value.
 * @return the HTTP status and body, so a caller can tell a refusal apart.
 */
export async function editAccount(
	api: APIRequestContext,
	username: string,
	key: string,
	value: string,
): Promise<{ status: number, body: string }> {
	const res = await api.put(
		`/ocs/v2.php/cloud/users/${encodeURIComponent(username)}?format=json`,
		{ form: { key, value } },
	)
	return { status: res.status(), body: await res.text() }
}

/**
 * Edit an account property and fail loudly when Nextcloud refuses.
 *
 * @param api the context that edits.
 * @param username the account.
 * @param key the provisioning key.
 * @param value the value.
 * @return nothing; throws on refusal.
 */
export async function mustEditAccount(
	api: APIRequestContext,
	username: string,
	key: string,
	value: string,
): Promise<void> {
	const { status, body } = await editAccount(api, username, key, value)
	expect(status, `setting ${key} on ${username}: ${body}`).toBe(200)
}

/**
 * Provision a person with a known display name, email, groups and profile.
 *
 * @param admin an admin context.
 * @param prefix a readable username prefix.
 * @param spec what the person should look like.
 * @return the person.
 */
export async function provisionPerson(
	admin: APIRequestContext,
	prefix: string,
	spec: PersonSpec,
): Promise<Person> {
	const principal = await createPrincipal(admin, prefix)
	// Nextcloud rate-limits account edits per acting user (50 per 10 min),
	// so the account edits itself instead of spending the admin's budget.
	await mustEditAccount(principal.api, principal.username, 'displayname', spec.displayName)
	if (spec.email) {
		await mustEditAccount(principal.api, principal.username, 'email', spec.email)
	}
	for (const group of spec.groups ?? []) {
		await addToGroup(admin, principal.username, group)
	}
	for (const [key, value] of Object.entries(spec.self ?? {})) {
		await mustEditAccount(principal.api, principal.username, key, value)
	}
	return { ...principal, displayName: spec.displayName, email: spec.email ?? null }
}

/**
 * Delete people, tolerating ones that are already gone.
 *
 * @param admin an admin context.
 * @param people the people (nulls are skipped).
 * @return nothing.
 */
export async function removePeople(
	admin: APIRequestContext,
	people: Array<Person | null | undefined>,
): Promise<void> {
	for (const person of people) {
		if (!person) {
			continue
		}
		await person.api.dispose().catch(() => null)
		await admin.delete(`/ocs/v1.php/cloud/users/${encodeURIComponent(person.username)}`)
	}
}

/**
 * Create a group with a stamped id.
 *
 * @param admin an admin context.
 * @param prefix a readable prefix.
 * @return the group id.
 */
export async function stampedGroup(admin: APIRequestContext, prefix: string): Promise<string> {
	return createGroup(admin, `${prefix}-${Date.now()}-${Math.floor(Math.random() * 10_000)}`)
}

/**
 * Delete groups, tolerating ones that are already gone.
 *
 * @param admin an admin context.
 * @param groups the group ids.
 * @return nothing.
 */
export async function removeGroups(admin: APIRequestContext, groups: string[]): Promise<void> {
	for (const group of groups) {
		await admin.delete(`/ocs/v1.php/cloud/groups/${encodeURIComponent(group)}`)
	}
}

/**
 * The canonical group filter of REQ-PPL-002 / REQ-PPL-006.
 *
 * @param groups the group ids.
 * @return the filters array.
 */
export function groupFilter(...groups: string[]): Array<Record<string, unknown>> {
	return [{ fieldName: 'group', operator: 'in', values: groups }]
}

/**
 * GET /api/people with the given parameters.
 *
 * @param api the viewer's context.
 * @param params query parameters; `filters` may be given as an array.
 * @return the status and parsed body.
 */
export async function getPeople(
	api: APIRequestContext,
	params: Record<string, string | number | unknown[]>,
): Promise<{ status: number, body: Record<string, unknown> }> {
	const query = new URLSearchParams()
	for (const [key, value] of Object.entries(params)) {
		query.append(key, Array.isArray(value) ? JSON.stringify(value) : String(value))
	}
	const res = await api.get(`${PEOPLE_API}?${query.toString()}`)
	const text = await res.text()
	let body: Record<string, unknown>
	try {
		body = JSON.parse(text)
	} catch {
		body = { raw: text }
	}
	return { status: res.status(), body }
}

/**
 * GET /api/people and require HTTP 200 with the page envelope.
 *
 * @param api the viewer's context.
 * @param params query parameters.
 * @return the page.
 */
export async function mustGetPeople(
	api: APIRequestContext,
	params: Record<string, string | number | unknown[]>,
): Promise<PeoplePage> {
	const { status, body } = await getPeople(api, params)
	expect(status, `GET /api/people ${JSON.stringify(params)}: ${JSON.stringify(body)}`).toBe(200)
	expect(Array.isArray(body.users), 'the envelope carries a users array').toBe(true)
	return body as unknown as PeoplePage
}

/**
 * Walk every page of an unfiltered listing.
 *
 * @param api the viewer's context.
 * @param extra extra parameters (`excludeDisabled`, ...).
 * @return every page, in order.
 */
export async function walkDirectory(
	api: APIRequestContext,
	extra: Record<string, string | number> = {},
): Promise<PeoplePage[]> {
	const pages: PeoplePage[] = []
	let offset = 0
	for (let guard = 0; guard < 500; guard++) {
		const page = await mustGetPeople(api, { ...extra, limit: 100, offset })
		pages.push(page)
		if (!page.hasMore || page.users.length === 0) {
			return pages
		}
		offset += page.users.length
	}
	throw new Error('the directory walk did not end after 500 pages')
}

/**
 * The entry for one uid in a page, or undefined.
 *
 * @param page the page.
 * @param uid the user id.
 * @return the entry.
 */
export function entryOf(page: PeoplePage, uid: string): Record<string, unknown> | undefined {
	return page.users.find((user) => user.uid === uid)
}

/** The ADR-023 action that guards `GET /api/people`. */
const LIST_ACTION = 'people-widget.get-users'

/**
 * Let every logged-in user list people, for the duration of a spec.
 *
 * @param admin an admin context.
 * @return the matrix as it was, to hand to {@link restoreActionMatrix}.
 */
export async function grantPeopleListing(
	admin: APIRequestContext,
): Promise<Record<string, string[]>> {
	const res = await admin.get(`${API}/admin/action-matrix`)
	expect(res.status(), await res.text()).toBe(200)
	const original = ((await res.json()).matrix ?? {}) as Record<string, string[]>
	const put = await admin.put(`${API}/admin/action-matrix`, {
		data: { matrix: { ...original, [LIST_ACTION]: ['admin', '@all'] } },
	})
	expect(put.status(), await put.text()).toBe(200)
	return original
}

/**
 * Put the action matrix back the way {@link grantPeopleListing} found it.
 *
 * @param admin an admin context.
 * @param original the matrix it returned, or null when the grant never ran.
 * @return nothing.
 */
export async function restoreActionMatrix(
	admin: APIRequestContext,
	original: Record<string, string[]> | null,
): Promise<void> {
	if (original === null) {
		return
	}
	await admin.put(`${API}/admin/action-matrix`, { data: { matrix: original } })
}

/** One people placement to seed: its content and, optionally, its grid box. */
export interface PeoplePlacement {
	content: Record<string, unknown>
	gridX?: number
	gridY?: number
	gridWidth?: number
	gridHeight?: number
}

/**
 * Add a `people` placement to a dashboard.
 *
 * @param admin an admin context (the owner).
 * @param dashboardId the dashboard.
 * @param placement the content and grid box.
 * @return the placement id.
 */
export async function addPeoplePlacement(
	admin: APIRequestContext,
	dashboardId: number,
	placement: PeoplePlacement,
): Promise<number> {
	const res = await admin.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'people',
			gridX: placement.gridX ?? 0,
			gridY: placement.gridY ?? 0,
			gridWidth: placement.gridWidth ?? 12,
			gridHeight: placement.gridHeight ?? 8,
			content: placement.content,
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	return Number(body.id ?? body.data?.id ?? body.placement?.id)
}

/**
 * Create the admin's active dashboard holding the given people placements.
 *
 * @param admin an admin context.
 * @param name the dashboard name.
 * @param placements the placements.
 * @return the dashboard.
 */
export async function seedPeopleDashboard(
	admin: APIRequestContext,
	name: string,
	placements: PeoplePlacement[],
): Promise<SeededDashboard> {
	const dashboard = await seedActiveDashboard(admin, name)
	for (const placement of placements) {
		await addPeoplePlacement(admin, dashboard.id, placement)
	}
	return dashboard
}

/**
 * The rendered People widget, optionally of one layout.
 *
 * Matches both renderers that exist for the `people` type: nc-vue's
 * `CnPeopleWidget` (`.cn-people-widget`, what the registry dispatches to
 * today) and LaunchPad's own `PeopleWidget.vue` (`.people-widget`), so a
 * spec states the behaviour, not which component happens to be wired.
 *
 * @param page the page.
 * @param layout `card`, `grid` or `list`; any when omitted.
 * @return the widget root.
 */
export function peopleWidget(page: Page, layout?: string): Locator {
	if (layout) {
		return page.locator(`.cn-people-widget--${layout}, .people-widget--${layout}`).first()
	}
	return page.locator('.cn-people-widget, .people-widget').first()
}

/**
 * The person entries a widget renders.
 *
 * @param widget the widget root.
 * @return the list items.
 */
export function personItems(widget: Locator): Locator {
	return widget.locator('[role="listitem"]')
}

/**
 * The rendered entry of one person.
 *
 * @param widget the widget root.
 * @param displayName the person's display name.
 * @return the list item.
 */
export function personItem(widget: Locator, displayName: string): Locator {
	return personItems(widget).filter({ hasText: displayName })
}

/**
 * The profile link of one person (the card, the row, or the anchor in it).
 *
 * @param widget the widget root.
 * @param username the person's uid.
 * @return the anchor.
 */
export function profileLink(widget: Locator, username: string): Locator {
	return widget.locator(`a[href$="/u/${encodeURIComponent(username)}"]`).first()
}

/** The widget's search input. */
export function searchBox(widget: Locator): Locator {
	return widget.locator('input[type="search"]').first()
}

/** Whether a URL is a request to the people-listing route. */
export function isPeopleRequest(url: string): boolean {
	return /\/apps\/launchpad\/api\/people(\?|$)/.test(url)
}

/**
 * A solid-colour square PNG, for an avatar Nextcloud accepts without cropping.
 *
 * @param size the edge length in pixels.
 * @return the PNG bytes.
 */
export function squarePng(size = 64): Buffer {
	const crcTable: number[] = []
	for (let n = 0; n < 256; n++) {
		let c = n
		for (let k = 0; k < 8; k++) {
			c = (c & 1) ? 0xedb88320 ^ (c >>> 1) : c >>> 1
		}
		crcTable.push(c >>> 0)
	}
	const crc = (bytes: Buffer): number => {
		let c = 0xffffffff
		for (const byte of bytes) {
			c = crcTable[(c ^ byte) & 0xff] ^ (c >>> 8)
		}
		return (c ^ 0xffffffff) >>> 0
	}
	const chunk = (type: string, data: Buffer): Buffer => {
		const length = Buffer.alloc(4)
		length.writeUInt32BE(data.length)
		const typed = Buffer.concat([Buffer.from(type, 'ascii'), data])
		const sum = Buffer.alloc(4)
		sum.writeUInt32BE(crc(typed))
		return Buffer.concat([length, typed, sum])
	}
	const header = Buffer.alloc(13)
	header.writeUInt32BE(size, 0)
	header.writeUInt32BE(size, 4)
	header[8] = 8 // bit depth
	header[9] = 2 // truecolour RGB
	const row = Buffer.alloc(1 + size * 3)
	for (let x = 0; x < size; x++) {
		row[1 + x * 3] = 0x21
		row[2 + x * 3] = 0x6e
		row[3 + x * 3] = 0xb4
	}
	const raw = Buffer.concat(Array.from({ length: size }, () => row))
	return Buffer.concat([
		Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
		chunk('IHDR', header),
		chunk('IDAT', deflateSync(raw)),
		chunk('IEND', Buffer.alloc(0)),
	])
}
