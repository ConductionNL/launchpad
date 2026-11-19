/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The group order: which Nextcloud groups LaunchPad routes on, and in what
 * order (admin-settings REQ-ASET-012, REQ-ASET-013, REQ-ASET-014).
 *
 * `POST /api/admin/groups` replaces the order; `GET /api/admin/groups` answers
 * `{active, inactive, allKnown}`. Both are admin-only. The refusals are probed
 * with an ordinary logged-in account, assert the exact 403 or 400, re-read the
 * order as the admin to show it did not move, and have the admin make the same
 * write successfully in the same test.
 *
 * 🔴 THE ORDER IS PUT BACK AFTER EVERY TEST. It decides every user's primary
 * group, and with it which group dashboard and template they land on, for the
 * whole suite sharing this instance. Groups made here are stamped and removed
 * in `afterAll`.
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	GROUPS,
	initialStateFromHtml,
	readGroups,
	writeGroupOrder,
} from './support/adminSettings.ts'
import {
	addToGroup,
	adminContext,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const STAMP = `${Date.now()}`

let admin: APIRequestContext
let alice: Principal | null = null
let orderBefore: string[] | null = null
const made: string[] = []

/**
 * A stamped group, removed in `afterAll`.
 *
 * @param name a readable name.
 * @return the group id.
 */
async function group(name: string): Promise<string> {
	const id = await createGroup(admin, `e2e-${name}-${STAMP}`)
	made.push(id)
	return id
}

/**
 * Lower-case ASCII letters only, the way PHP's `strtolower` does.
 *
 * @param value the text.
 * @return the text with A-Z lowered.
 */
function asciiLower(value: string): string {
	return value.replace(/[A-Z]/g, (c) => c.toLowerCase())
}

/**
 * The primary group the workspace page routes a user on, read from the page
 * the user is served.
 *
 * @param user the user.
 * @return the `primaryGroup` initial-state value.
 */
async function primaryGroupOf(user: Principal): Promise<unknown> {
	const res = await user.api.get('/index.php/apps/launchpad/')
	expect(res.status()).toBe(200)
	return initialStateFromHtml(await res.text(), 'primaryGroup')
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	orderBefore = (await readGroups(admin)).active
	alice = await createPrincipal(admin, 'e2e-aset-groups')
	await assertActingAs(alice.api, alice.username)
})

test.afterEach(async () => {
	if (orderBefore !== null) {
		await admin.post(GROUPS, { data: { groups: orderBefore } })
	}
})

test.afterAll(async () => {
	if (orderBefore !== null) {
		await admin.post(GROUPS, { data: { groups: orderBefore } })
	}
	await removePrincipal(admin, alice)
	for (const id of made) {
		await removeGroup(admin, id)
	}
	await admin.dispose()
})

test.describe('REQ-ASET-012 storing the order', () => {
	// @e2e admin-settings::persist-ordered-list
	test('an ordered list is stored and read back in that order', async () => {
		const ordered = [
			await group('engineering'),
			await group('all-staff'),
			await group('marketing'),
		]

		const res = await admin.post(GROUPS, { data: { groups: ordered } })
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).groupOrder).toEqual(ordered)

		expect((await readGroups(admin)).active).toEqual(ordered)
	})

	// @e2e admin-settings::empty-list-clears-active-groups
	test('an empty list clears the order and every user routes on the default group', async () => {
		const routed = await group('routed')
		await addToGroup(admin, alice!.username, routed)

		// Control: with the group in the order, alice routes on it.
		await writeGroupOrder(admin, [routed])
		expect(await primaryGroupOf(alice!)).toBe(routed)

		const res = await admin.post(GROUPS, { data: { groups: [] } })
		expect(res.status(), await res.text()).toBe(200)
		expect((await readGroups(admin)).active).toEqual([])
		expect(await primaryGroupOf(alice!)).toBe('default')
	})

	// @e2e admin-settings::replace-wholesale-not-merge
	test('a new list replaces the old one, it is not merged into it', async () => {
		const a = await group('a')
		const b = await group('b')
		const c = await group('c')
		await writeGroupOrder(admin, [a, b, c])
		expect((await readGroups(admin)).active).toEqual([a, b, c])

		await writeGroupOrder(admin, [c, b])

		const active = (await readGroups(admin)).active
		expect(active).toEqual([c, b])
		expect(active).not.toContain(a)
	})
})

test.describe('REQ-ASET-013 the three lists', () => {
	// @e2e admin-settings::lists-are-disjoint-and-exhaustive
	test('active and inactive are disjoint and together are every known group', async () => {
		const [a, b, c, d] = [
			await group('la'),
			await group('lb'),
			await group('lc'),
			await group('ld'),
		]
		await writeGroupOrder(admin, [b, d])

		const lists = await readGroups(admin)
		expect(lists.active).toEqual([b, d])
		expect(lists.inactive).toEqual(expect.arrayContaining([a, c]))
		expect(lists.inactive).not.toContain(b)
		expect(lists.inactive).not.toContain(d)

		const known = lists.allKnown.map((g) => g.id)
		for (const id of [a, b, c, d]) {
			const entry = lists.allKnown.find((g) => g.id === id)
			expect(entry, `${id} is missing from allKnown`).toBeDefined()
			expect(typeof entry!.displayName).toBe('string')
		}

		const union = new Set([...lists.active, ...lists.inactive])
		expect([...union].sort()).toEqual([...new Set(known)].sort())
		expect(lists.active.filter((id) => lists.inactive.includes(id))).toEqual([])
	})

	// @e2e admin-settings::order-in-active-matches-admin-chosen-order
	test('active keeps the admin order and inactive is sorted by display name', async () => {
		const chosen = [
			await group('zebra'),
			await group('alpha'),
			await group('marigold'),
		]
		await writeGroupOrder(admin, chosen)

		const lists = await readGroups(admin)
		expect(lists.active).toEqual(chosen)

		const names = lists.inactive.map((id) => {
			const entry = lists.allKnown.find((g) => g.id === id)
			return asciiLower(entry?.displayName ?? id)
		})
		for (let i = 1; i < names.length; i++) {
			expect(
				names[i - 1] <= names[i],
				`inactive is not sorted by display name: "${names[i - 1]}" before "${names[i]}"`,
			).toBe(true)
		}
	})

	// @e2e admin-settings::empty-grouporder-all-groups-inactive
	test('with an empty order every group is inactive', async () => {
		const a = await group('ea')
		const b = await group('eb')
		await writeGroupOrder(admin, [])

		const lists = await readGroups(admin)
		expect(lists.active).toEqual([])
		expect(lists.inactive).toEqual(expect.arrayContaining([a, b]))
	})

	// @e2e admin-settings::stale-group-ids-surface-in-active-list
	test('a deleted group stays in active, and is in neither allKnown nor inactive', async () => {
		const kept = await group('kept')
		const gone = await createGroup(admin, `e2e-deleted-group-${STAMP}`)
		await writeGroupOrder(admin, [gone, kept])
		await removeGroup(admin, gone)

		const lists = await readGroups(admin)
		expect(lists.active).toContain(gone)
		expect(lists.active).toEqual([gone, kept])
		expect(lists.allKnown.map((g) => g.id)).not.toContain(gone)
		expect(lists.inactive).not.toContain(gone)
	})
})

test.describe('REQ-ASET-014 who may change it, and what is accepted', () => {
	// @e2e admin-settings::non-admin-post-rejected
	test('an ordinary user is refused POST with 403 and the order does not move', async () => {
		const first = await group('np-first')
		const second = await group('np-second')
		await writeGroupOrder(admin, [first])

		const refused = await alice!.api.post(GROUPS, { data: { groups: [second] } })
		expect(refused.status()).toBe(403)
		expect(
			(await readGroups(admin)).active,
			'a refused POST still wrote',
		).toEqual([first])

		// The admin making the same write succeeds, so the endpoint works.
		await writeGroupOrder(admin, [second])
		expect((await readGroups(admin)).active).toEqual([second])
	})

	// @e2e admin-settings::non-admin-get-rejected
	test('an ordinary user is refused GET with 403', async () => {
		const known = await group('ng-known')

		const refused = await alice!.api.get(GROUPS)
		expect(refused.status()).toBe(403)
		expect(await refused.text(), 'the refusal leaked a group id').not.toContain(
			known,
		)

		const allowed = await admin.get(GROUPS)
		expect(allowed.status()).toBe(200)
		expect(
			((await allowed.json()).allKnown as Array<{ id: string }>).map(
				(g) => g.id,
			),
		).toContain(known)
	})

	// @e2e admin-settings::missing-groups-key-rejected
	test('a body without "groups" is refused with 400 and the order does not move', async () => {
		const kept = await group('mk')
		await writeGroupOrder(admin, [kept])

		const res = await admin.post(GROUPS, { data: {} })
		expect(res.status()).toBe(400)
		expect((await readGroups(admin)).active).toEqual([kept])
	})

	// @e2e admin-settings::non-string-element-rejected
	test('a list with a number in it is refused with 400 and the order does not move', async () => {
		const kept = await group('ns')
		await writeGroupOrder(admin, [kept])

		const res = await admin.post(GROUPS, {
			data: { groups: [`engineering-${STAMP}`, 42, `marketing-${STAMP}`] },
		})
		expect(res.status()).toBe(400)
		expect((await readGroups(admin)).active).toEqual([kept])
	})

	// @e2e admin-settings::duplicate-ids-deduplicated
	test('duplicate ids are dropped, the first occurrence kept', async () => {
		const [a, b, c] = [await group('da'), await group('db'), await group('dc')]

		const res = await admin.post(GROUPS, { data: { groups: [a, b, a, c] } })
		expect(res.status(), await res.text()).toBe(200)
		expect((await res.json()).groupOrder).toEqual([a, b, c])
		expect((await readGroups(admin)).active).toEqual([a, b, c])
	})

	// @e2e admin-settings::unknown-ids-accepted
	test('an id that is not a Nextcloud group is accepted and stored', async () => {
		const real = await group('engineering-known')
		const unknown = `does-not-exist-${STAMP}`

		const res = await admin.post(GROUPS, { data: { groups: [unknown, real] } })
		expect(res.status(), await res.text()).toBe(200)
		expect((await readGroups(admin)).active).toEqual([unknown, real])
	})
})
