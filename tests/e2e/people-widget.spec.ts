/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the People widget's directory contract
 * (openspec/specs/people-widget), written for e2e-coverage-programme: the
 * paginated `GET /api/people` endpoint, profile visibility, birthdates, group
 * filtering and avatar URLs, read through the real endpoint against real
 * accounts.
 *
 * The file provisions its own people and groups, so every membership it
 * asserts is one it created:
 *
 *   alice   in mgmt + prod, email (local scope), role, organisation, birthdate
 *   carol   in prod
 *   dave    in mgmt, no birthdate
 *   erin    in mgmt, DISABLED
 *   charlie an admin in `own`, with a private biography and headline
 *   bob     an ordinary colleague in no group: the viewer
 *
 * bob can only list people because the spec grants `people-widget.get-users`
 * to everyone for its duration; see support/peopleWidget.ts for why.
 *
 * Gate-19 @e2e traceability:
 *   @e2e people-widget::fetch-first-page-of-users
 *   @e2e people-widget::fetch-second-page-via-offset
 *   @e2e people-widget::last-page-returns-hasmore-false
 *   @e2e people-widget::pagination-still-returns-correct-total-and-hasmore
 *   @e2e people-widget::limit-exceeds-maximum-returns-400
 *   @e2e people-widget::invalid-offset-or-limit-type-handled
 *   @e2e people-widget::viewer-can-see-visible-email-future
 *   @e2e people-widget::admin-viewing-their-own-profile-sees-all-fields
 *   @e2e people-widget::missing-birthdate-returns-omitted-field
 *   @e2e people-widget::upcoming-birthday-returned-as-iso-string
 *   @e2e people-widget::missing-birthdate-omits-field
 *   @e2e people-widget::showbirthdays-false-omits-birthdate
 *   @e2e people-widget::empty-filters-returns-all-visible-users
 *   @e2e people-widget::single-group-filter
 *   @e2e people-widget::multiple-group-values-union
 *   @e2e people-widget::group-filtered-resolution-is-unaffected
 *   @e2e people-widget::unknown-group-name-handled-gracefully
 *   @e2e people-widget::users-in-group-appear-with-full-group-list
 *   @e2e people-widget::avatar-url-for-existing-user
 *   @e2e people-widget::avatar-url-for-user-without-avatar
 *
 * @spec openspec/specs/people-widget/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { PeoplePage, Person } from './support/peopleWidget.ts'

import { expect, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { removeSeededDashboard } from './support/dashboardFixture.ts'
import {
	editAccount,
	entryOf,
	getPeople,
	grantPeopleListing,
	groupFilter,
	isPeopleRequest,
	mustEditAccount,
	mustGetPeople,
	provisionPerson,
	removeGroups,
	removePeople,
	restoreActionMatrix,
	seedPeopleDashboard,
	squarePng,
	stampedGroup,
	walkDirectory,
} from './support/peopleWidget.ts'
import { addToGroup, adminContext } from './support/principals.ts'

const STAMP = `${Date.now()}`
const ALICE_BIRTHDATE_ISO = '1990-06-10'

let admin: APIRequestContext
let matrixBefore: Record<string, string[]> | null = null
let mgmt = ''
let prod = ''
let own = ''
let alice: Person | null = null
let carol: Person | null = null
let dave: Person | null = null
let erin: Person | null = null
let charlie: Person | null = null
let bob: Person | null = null
/** How alice's birthdate was accepted by Nextcloud: the locale shape, or ISO. */
let aliceBirthdateSentAs = ''

/**
 * The uids in a page, in order.
 *
 * @param page the page.
 * @return the uids.
 */
function uids(page: PeoplePage): string[] {
	return page.users.map((user) => String(user.uid))
}

test.beforeAll(async () => {
	test.setTimeout(300_000)
	admin = await adminContext()
	matrixBefore = await grantPeopleListing(admin)

	mgmt = await stampedGroup(admin, 'e2e-ppl-mgmt')
	prod = await stampedGroup(admin, 'e2e-ppl-prod')
	own = await stampedGroup(admin, 'e2e-ppl-own')

	alice = await provisionPerson(admin, 'e2e-ppl-alice', {
		displayName: 'Alice Smith',
		email: `alice-${STAMP}@example.com`,
		groups: [mgmt, prod],
		self: {
			emailScope: 'v2-local',
			role: 'Product Manager',
			organisation: 'Product',
		},
	})
	// The scenario stores the birthdate in a locale shape (DD-MM-YYYY). Send
	// it that way; should Nextcloud's provisioning API refuse that shape, fall
	// back to ISO so the rest of the file still has a birthdate to read, and
	// say so in the report. Normalisation of stored locale shapes is pinned by
	// tests/Unit/Service/PeopleWidgetServiceTest.php::testBirthdateIsNormalisedToIso.
	const asLocale = await editAccount(alice.api, alice.username, 'birthdate', '10-06-1990')
	if (asLocale.status === 200) {
		aliceBirthdateSentAs = '10-06-1990'
	} else {
		await mustEditAccount(alice.api, alice.username, 'birthdate', ALICE_BIRTHDATE_ISO)
		aliceBirthdateSentAs = ALICE_BIRTHDATE_ISO
	}
	// Reachable by colleagues on this instance, so bob's reads can see it.
	await mustEditAccount(alice.api, alice.username, 'birthdateScope', 'v2-local')

	carol = await provisionPerson(admin, 'e2e-ppl-carol', {
		displayName: 'Carol Jansen',
		email: `carol-${STAMP}@example.com`,
		groups: [prod],
	})
	dave = await provisionPerson(admin, 'e2e-ppl-dave', {
		displayName: 'Dave de Boer',
		email: `dave-${STAMP}@example.com`,
		groups: [mgmt],
	})
	erin = await provisionPerson(admin, 'e2e-ppl-erin', {
		displayName: 'Erin Uitgeschakeld',
		groups: [mgmt],
	})
	const disabled = await admin.put(
		`/ocs/v2.php/cloud/users/${encodeURIComponent(erin.username)}/disable?format=json`,
	)
	expect(disabled.status(), await disabled.text()).toBe(200)

	charlie = await provisionPerson(admin, 'e2e-ppl-charlie', {
		displayName: 'Charlie Beheer',
		groups: [own],
		self: {
			biography: `Charlie keeps the servers running ${STAMP}`,
			biographyScope: 'v2-private',
			headline: 'Keeper of the servers',
			headlineScope: 'v2-private',
		},
	})
	await addToGroup(admin, charlie.username, 'admin')

	bob = await provisionPerson(admin, 'e2e-ppl-bob', {
		displayName: 'Bob Collega',
		email: `bob-${STAMP}@example.com`,
	})
})

test.afterAll(async () => {
	test.setTimeout(120_000)
	await removePeople(admin, [alice, carol, dave, erin, charlie, bob])
	await removeGroups(admin, [mgmt, prod, own].filter(Boolean))
	await restoreActionMatrix(admin, matrixBefore)
	await admin.dispose()
})

test.describe('REQ-PPL-003: paginated users endpoint', () => {
	// @e2e people-widget::fetch-first-page-of-users
	test('the first page holds `limit` users, hasMore and the full total, with every non-empty field', async () => {
		const page = await mustGetPeople(admin, {
			filters: groupFilter(mgmt, prod),
			limit: 2,
			offset: 0,
		})
		// mgmt + prod hold alice, carol, dave (erin is disabled): 3 in all.
		expect(uids(page)).toEqual([alice!.username, carol!.username])
		expect(page.hasMore).toBe(true)
		expect(page.total).toBe(3)

		const entry = entryOf(page, alice!.username)!
		expect(entry).toMatchObject({
			uid: alice!.username,
			displayName: 'Alice Smith',
			email: alice!.email,
			role: 'Product Manager',
			organisation: 'Product',
			birthdate: ALICE_BIRTHDATE_ISO,
		})
		expect(typeof entry.avatarUrl).toBe('string')
		expect(entry.groups).toEqual(expect.arrayContaining([mgmt, prod]))

		// REQ-PPL-004: no field is sent with an empty or null value.
		for (const user of page.users) {
			for (const [key, value] of Object.entries(user)) {
				expect(value, `${String(user.uid)}.${key}`).not.toBeNull()
				expect(value, `${String(user.uid)}.${key}`).not.toBe('')
			}
		}

		// The unfiltered directory pages the same way.
		const directory = await mustGetPeople(admin, { limit: 2, offset: 0 })
		expect(directory.users).toHaveLength(2)
		expect(directory.hasMore).toBe(true)
		expect(directory.total).toBeGreaterThan(2)
	})

	// @e2e people-widget::fetch-second-page-via-offset
	test('the next offset returns the next users, with no overlap', async () => {
		const first = await mustGetPeople(admin, {
			filters: groupFilter(mgmt, prod),
			limit: 2,
			offset: 0,
		})
		const second = await mustGetPeople(admin, {
			filters: groupFilter(mgmt, prod),
			limit: 2,
			offset: 2,
		})
		expect(uids(second)).toEqual([dave!.username])
		expect(uids(first).filter((uid) => uids(second).includes(uid))).toEqual([])

		const dirFirst = await mustGetPeople(admin, { limit: 2, offset: 0 })
		const dirSecond = await mustGetPeople(admin, { limit: 2, offset: 2 })
		expect(dirSecond.users.length).toBeGreaterThan(0)
		expect(uids(dirFirst).filter((uid) => uids(dirSecond).includes(uid))).toEqual([])
	})

	// @e2e people-widget::last-page-returns-hasmore-false
	// @e2e people-widget::pagination-still-returns-correct-total-and-hasmore
	test('the last page says hasMore false, and the total is what the pages add up to', async () => {
		const last = await mustGetPeople(admin, {
			filters: groupFilter(mgmt, prod),
			limit: 2,
			offset: 2,
		})
		expect(last.users).toHaveLength(1)
		expect(last.hasMore).toBe(false)
		expect(last.total).toBe(3)

		// Walk the whole unfiltered directory (the bounded display-name path
		// of REQ-PPL-013). Every page reports the same total, hasMore is true
		// exactly until the pages add up to it, and no one is listed twice.
		const pages = await walkDirectory(admin)
		const total = pages[0].total
		const seen: string[] = []
		pages.forEach((page, index) => {
			expect(page.total, `page ${index} total`).toBe(total)
			seen.push(...uids(page))
			expect(page.hasMore, `page ${index} hasMore`).toBe(seen.length < total)
		})
		expect(pages[pages.length - 1].hasMore).toBe(false)
		expect(seen).toHaveLength(total)
		expect(new Set(seen).size).toBe(total)

		// `total` counts enabled users only, per excludeDisabled.
		expect(seen).toContain(alice!.username)
		expect(seen).not.toContain(erin!.username)
		const withDisabled = await walkDirectory(admin, { excludeDisabled: 0 })
		const seenAll = withDisabled.flatMap(uids)
		expect(seenAll).toContain(erin!.username)
		expect(withDisabled[0].total).toBe(seenAll.length)
		expect(withDisabled[0].total).toBeGreaterThan(total)
	})

	// @e2e people-widget::limit-exceeds-maximum-returns-400
	test('a limit over 100 is refused with 400 and a message naming the maximum', async () => {
		const atMax = await getPeople(admin, { limit: 100, offset: 0 })
		expect(atMax.status).toBe(200)

		const over = await getPeople(admin, { limit: 200 })
		expect(over.status).toBe(400)
		expect(typeof over.body.error).toBe('string')
		expect(String(over.body.error)).toMatch(/\b100\b/)
	})

	// @e2e people-widget::invalid-offset-or-limit-type-handled
	test('a negative offset or a non-integer limit is refused with 400 and a message that names it', async () => {
		const negative = await getPeople(admin, { limit: 10, offset: -1 })
		expect(negative.status).toBe(400)
		expect(String(negative.body.error ?? '')).toMatch(/offset/i)

		const notANumber = await getPeople(admin, { limit: 'abc', offset: 0 })
		expect(notANumber.status).toBe(400)
		expect(String(notANumber.body.error ?? '')).toMatch(/limit/i)
	})
})

test.describe('REQ-PPL-004: profile visibility', () => {
	// @e2e people-widget::viewer-can-see-visible-email-future
	test('a colleague sees an email whose scope reaches the instance', async () => {
		const page = await mustGetPeople(bob!.api, { filters: groupFilter(mgmt) })
		expect(entryOf(page, alice!.username)?.email).toBe(alice!.email)
	})

	// @e2e people-widget::admin-viewing-their-own-profile-sees-all-fields
	test('an admin sees their own private fields; a colleague does not', async () => {
		const asSelf = await mustGetPeople(charlie!.api, { filters: groupFilter(own) })
		const self = entryOf(asSelf, charlie!.username)
		expect(self, 'charlie is in his own group').toBeTruthy()
		expect(self!.biography).toBe(`Charlie keeps the servers running ${STAMP}`)
		expect(self!.headline).toBe('Keeper of the servers')

		// The same entry read by someone else: the private fields are gone,
		// so the first half is the owner rule and not a field that always shows.
		const asColleague = await mustGetPeople(bob!.api, { filters: groupFilter(own) })
		const other = entryOf(asColleague, charlie!.username)!
		expect(Object.keys(other)).not.toContain('biography')
		expect(Object.keys(other)).not.toContain('headline')
	})

	// @e2e people-widget::missing-birthdate-returns-omitted-field
	test('a person without a birthdate has no birthdate key at all', async () => {
		const page = await mustGetPeople(bob!.api, {
			filters: groupFilter(mgmt),
			showBirthdays: 1,
		})
		const entry = entryOf(page, dave!.username)
		expect(entry).toBeTruthy()
		expect(Object.keys(entry!)).not.toContain('birthdate')
		// Control: someone who has one gets it on the same request.
		expect(entryOf(page, alice!.username)?.birthdate).toBe(ALICE_BIRTHDATE_ISO)
	})
})

test.describe('REQ-PPL-005: birthdates', () => {
	// @e2e people-widget::upcoming-birthday-returned-as-iso-string
	test('a birthdate is returned as an ISO 8601 date', async () => {
		test.info().annotations.push({
			type: 'birthdate sent as',
			description: aliceBirthdateSentAs,
		})
		const page = await mustGetPeople(admin, { filters: groupFilter(mgmt) })
		expect(entryOf(page, alice!.username)?.birthdate).toBe(ALICE_BIRTHDATE_ISO)
	})

	// @e2e people-widget::missing-birthdate-omits-field
	test('whatever the request, a person without a birthdate never carries the key', async () => {
		const requests: Array<[APIRequestContext, Record<string, string | number | unknown[]>]> = [
			[admin, { filters: groupFilter(mgmt) }],
			[admin, { filters: groupFilter(mgmt), showBirthdays: 0 }],
			[bob!.api, { filters: groupFilter(mgmt), showBirthdays: 1 }],
			[bob!.api, { filters: groupFilter(mgmt, prod), sortBy: 'group' }],
			[admin, { filters: groupFilter(mgmt), q: 'Dave de' }],
		]
		for (const [api, params] of requests) {
			const page = await mustGetPeople(api, params)
			const entry = entryOf(page, dave!.username)
			expect(entry, `dave listed for ${JSON.stringify(params)}`).toBeTruthy()
			expect(Object.keys(entry!), JSON.stringify(params)).not.toContain('birthdate')
		}
	})

	// @e2e people-widget::showbirthdays-false-omits-birthdate
	test('a placement with showBirthdays false never receives a birthdate', async ({ page }) => {
		// The endpoint honours the switch when asked...
		const off = await mustGetPeople(admin, { filters: groupFilter(mgmt), showBirthdays: 0 })
		for (const user of off.users) {
			expect(Object.keys(user), String(user.uid)).not.toContain('birthdate')
		}
		const on = await mustGetPeople(admin, { filters: groupFilter(mgmt), showBirthdays: 1 })
		expect(entryOf(on, alice!.username)?.birthdate).toBe(ALICE_BIRTHDATE_ISO)

		// ...and the widget must ask: what reaches the browser for a placement
		// configured with showBirthdays false carries no birthdate.
		let dashboard: SeededDashboard | null = null
		try {
			dashboard = await seedPeopleDashboard(admin, `E2E People birthdays ${STAMP}`, [
				{ content: { layout: 'card', filters: groupFilter(mgmt), showBirthdays: false } },
			])
			const listed = page.waitForResponse((res) => isPeopleRequest(res.url()), {
				timeout: 30_000,
			})
			await gotoLaunchPad(page)
			const response = await listed
			const text = await response.text()
			expect(text, 'alice is in the list the widget fetched').toContain(alice!.username)
			expect(text).not.toContain('"birthdate"')
			expect(text).not.toContain(ALICE_BIRTHDATE_ISO)
		} finally {
			await removeSeededDashboard(admin, dashboard)
		}
	})
})

test.describe('REQ-PPL-006: group filtering', () => {
	// @e2e people-widget::empty-filters-returns-all-visible-users
	test('an empty filter list returns every enabled user the viewer may see', async () => {
		const pages = await walkDirectory(bob!.api, { filters: '[]' })
		const seen = pages.flatMap(uids)
		for (const person of [alice, carol, dave, charlie, bob]) {
			expect(seen, person!.displayName).toContain(person!.username)
		}
		expect(seen, 'excludeDisabled defaults to on').not.toContain(erin!.username)
	})

	// @e2e people-widget::single-group-filter
	test('a single group value returns that group only, each with all their groups', async () => {
		const page = await mustGetPeople(admin, { filters: groupFilter(mgmt) })
		expect(uids(page).sort()).toEqual([alice!.username, dave!.username].sort())
		expect(uids(page)).not.toContain(carol!.username)
		expect(entryOf(page, alice!.username)?.groups).toEqual(expect.arrayContaining([mgmt, prod]))
	})

	// @e2e people-widget::multiple-group-values-union
	// @e2e people-widget::group-filtered-resolution-is-unaffected
	test('several group values return their union, with a shared member listed once', async () => {
		const page = await mustGetPeople(admin, { filters: groupFilter(mgmt, prod) })
		const listed = uids(page)
		expect([...listed].sort()).toEqual(
			[alice!.username, carol!.username, dave!.username].sort(),
		)
		expect(listed.filter((uid) => uid === alice!.username)).toHaveLength(1)
		expect(page.total).toBe(3)
	})

	// @e2e people-widget::unknown-group-name-handled-gracefully
	test('an unknown group yields nobody, with 200, and leaves the other values alone', async () => {
		const unknown = `e2e-ppl-nonexistent-${STAMP}`
		const none = await getPeople(admin, { filters: groupFilter(unknown) })
		expect(none.status).toBe(200)
		expect(none.body.users).toEqual([])
		expect(none.body.total).toBe(0)
		expect(none.body.hasMore).toBe(false)

		const mixed = await mustGetPeople(admin, { filters: groupFilter(unknown, prod) })
		expect(uids(mixed).sort()).toEqual([alice!.username, carol!.username].sort())
	})

	// @e2e people-widget::users-in-group-appear-with-full-group-list
	test('a member matched through one group still lists all of their groups', async () => {
		const page = await mustGetPeople(admin, { filters: groupFilter(mgmt) })
		const groups = (entryOf(page, alice!.username)?.groups ?? []) as string[]
		expect([...groups].sort()).toEqual([mgmt, prod].sort())
	})
})

test.describe('REQ-PPL-007: avatar URLs', () => {
	/**
	 * Fetch an avatar URL from the people list through the test's base URL.
	 *
	 * The URL is absolute and may carry the instance's configured host, which
	 * is not necessarily the one this runner reaches it by, so the path and
	 * query are replayed against the base URL.
	 *
	 * @param avatarUrl the URL from the people list.
	 * @return the response.
	 */
	async function fetchAvatar(avatarUrl: string) {
		const url = new URL(avatarUrl)
		return admin.get(`${url.pathname}${url.search}`)
	}

	// @e2e people-widget::avatar-url-for-existing-user
	test('an uploaded avatar is served from the absolute 128 px avatar URL', async () => {
		const upload = await alice!.api.post('/index.php/avatar/', {
			multipart: {
				'files[]': { name: 'avatar.png', mimeType: 'image/png', buffer: squarePng(64) },
			},
		})
		expect(upload.status(), await upload.text()).toBe(200)
		expect((await upload.json()).status, 'a square image is taken without cropping').toBe('success')

		const page = await mustGetPeople(admin, { filters: groupFilter(mgmt) })
		const avatarUrl = String(entryOf(page, alice!.username)?.avatarUrl ?? '')
		expect(avatarUrl).toMatch(/^https?:\/\//)
		expect(new URL(avatarUrl).pathname).toMatch(
			new RegExp(`/avatar/${alice!.username}/128$`),
		)

		const avatar = await fetchAvatar(avatarUrl)
		expect(avatar.status()).toBe(200)
		expect(avatar.headers()['content-type'] ?? '').toMatch(/^image\//)
		expect(avatar.headers()['x-nc-iscustomavatar']).toBe('1')
	})

	// @e2e people-widget::avatar-url-for-user-without-avatar
	test('a person without an avatar still gets an avatar URL that serves a placeholder', async () => {
		const page = await mustGetPeople(admin, { filters: groupFilter(mgmt) })
		const avatarUrl = String(entryOf(page, dave!.username)?.avatarUrl ?? '')
		expect(avatarUrl).toMatch(/^https?:\/\//)
		expect(new URL(avatarUrl).pathname).toMatch(new RegExp(`/avatar/${dave!.username}/128$`))

		const avatar = await fetchAvatar(avatarUrl)
		expect(avatar.status()).toBe(200)
		expect(avatar.headers()['content-type'] ?? '').toMatch(/^image\//)
		expect(avatar.headers()['x-nc-iscustomavatar']).toBe('0')
	})
})
