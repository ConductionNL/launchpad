/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Who hears about a share, and what they read (dashboard-sharing REQ-SHARE-008,
 * the notification clauses of REQ-SHARE-009, and REQ-SHARE-011's English
 * rendering).
 *
 * These scenarios used to be excluded as "server-side dispatch with no UI
 * surface". A notification has a reader, though: the recipient's own
 * notifications feed (`/ocs/v2.php/apps/notifications/api/v2/notifications`),
 * which serves each notification already rendered by LaunchPad's `Notifier`,
 * together with the dashboard it is about. Each test below seeds its own
 * dashboard and counts, per recipient, the LaunchPad notifications whose
 * `object_id` is that dashboard (see `support/notifications.ts`), before and
 * after the call the scenario is about. "Nothing is published" is then a
 * count that did not move, next to a control in the same test where the same
 * recipient's count DID move, so a feed that never fills cannot pass it.
 *
 * The owner, alice, is a plain account, so the sharer parameter is her uid.
 * Every recipient's language is set to English up front, because the rendered
 * copy is what is compared.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from '../support/principals.ts'
import type { OwnDashboard, SharingSettings } from '../support/shareApi.ts'

import { expect, test } from '@playwright/test'
import { shareNotifications } from '../support/notifications.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from '../support/principals.ts'
import {
	createOwnDashboard,
	listShares,
	readSharingSettings,
	share,
	shareTuples,
	writeSharingSettings,
} from '../support/shareApi.ts'

const STAMP = Date.now()

let admin: APIRequestContext
let prior: SharingSettings | null = null
let alice: Principal | null = null
let bob: Principal | null = null
let carol: Principal | null = null
let dave: Principal | null = null
let erin: Principal | null = null
let marketing: string | null = null
let sales: string | null = null

/** Set an account's language, so the rendered copy is predictable. */
async function setLanguage(username: string, language: string): Promise<void> {
	const res = await admin.put(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}`,
		{ form: { key: 'language', value: language } },
	)
	expect(
		res.ok(),
		`could not set ${username}'s language: ${res.status()}`,
	).toBeTruthy()
}

test.beforeAll(async () => {
	admin = await adminContext()
	prior = await readSharingSettings(admin)
	await writeSharingSettings(admin, {
		allowUserDashboards: true,
		sharingAllowedGroups: [],
		sharingRecipientScope: 'everyone',
	})

	alice = await createPrincipal(admin, 'e2e-snot-alice')
	bob = await createPrincipal(admin, 'e2e-snot-bob')
	carol = await createPrincipal(admin, 'e2e-snot-carol')
	dave = await createPrincipal(admin, 'e2e-snot-dave')
	erin = await createPrincipal(admin, 'e2e-snot-erin')
	for (const p of [alice, bob, carol, dave, erin]) {
		await assertActingAs(p.api, p.username)
		await setLanguage(p.username, 'en')
	}

	// marketing: bob, carol, dave AND the sharer, so "alice is not told"
	// is a rule being kept rather than an accident of membership.
	marketing = await createGroup(admin, `e2e-snot-marketing-${STAMP}`)
	for (const p of [alice, bob, carol, dave]) {
		await addToGroup(admin, p.username, marketing)
	}
	// sales: erin only, for the bulk-replace removal.
	sales = await createGroup(admin, `e2e-snot-sales-${STAMP}`)
	await addToGroup(admin, erin.username, sales)
})

test.afterAll(async () => {
	for (const p of [alice, bob, carol, dave, erin]) {
		await removePrincipal(admin, p)
	}
	await removeGroup(admin, marketing)
	await removeGroup(admin, sales)
	if (prior !== null) {
		await writeSharingSettings(admin, prior)
	}
	await admin.dispose()
})

/** A fresh dashboard owned by alice. */
async function dashboardOf(label: string): Promise<OwnDashboard> {
	return createOwnDashboard(alice!.api, `Q3 Plan ${label} ${STAMP}`)
}

/** How many share notifications a principal holds about one dashboard. */
async function count(p: Principal, dash: OwnDashboard): Promise<number> {
	return (await shareNotifications(p.api, dash.id)).length
}

test.describe('REQ-SHARE-008 notify on add and on upgrade only', () => {
	// @e2e dashboard-sharing::user-share-publishes-one-notification
	test('a new user share publishes exactly one notification to its recipient', async () => {
		const dash = await dashboardOf('user')
		expect(await count(bob!, dash), 'CONTROL: nothing before the share').toBe(0)

		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')

		const got = await shareNotifications(bob!.api, dash.id)
		expect(got, 'exactly one notification').toHaveLength(1)
		const [entry] = got
		expect(entry.app).toBe('launchpad')
		expect(entry.user).toBe(bob!.username)
		expect(entry.object_type).toBe('dashboard')
		expect(String(entry.object_id)).toBe(String(dash.id))
		// subjectParameters [sharer, dashboard name, level], as rendered.
		expect(entry.subjectRich).toBe(
			`${alice!.username} shared **${dash.name}** with you`,
		)
		expect(entry.message).toBe('View-only access')

		expect(await count(alice!, dash), 'the sharer is not told').toBe(0)
	})

	// @e2e dashboard-sharing::group-share-fans-out-per-current-member
	test('a group share notifies each current member once, and never the sharer', async () => {
		const dash = await dashboardOf('group')

		await share(alice!.api, dash.id, 'group', marketing!, 'full')

		for (const member of [bob!, carol!, dave!]) {
			const got = await shareNotifications(member.api, dash.id)
			expect(got, `${member.username} must get exactly one`).toHaveLength(1)
			expect(got[0].message).toBe('Full access')
		}
		expect(
			await count(alice!, dash),
			'the sharer is a member and still not told',
		).toBe(0)
		// CONTROL: someone outside the group hears nothing, so the fan-out is
		// the group's membership and not everyone.
		expect(await count(erin!, dash)).toBe(0)
	})

	// @e2e dashboard-sharing::level-upgrade-publishes-a-notification
	test('upgrading a share to full publishes one more notification, naming the new level', async () => {
		const dash = await dashboardOf('upgrade')
		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')
		expect(await count(bob!, dash)).toBe(1)

		await share(alice!.api, dash.id, 'user', bob!.username, 'full')

		const got = await shareNotifications(bob!.api, dash.id)
		expect(got, 'one new notification for the upgrade').toHaveLength(2)
		expect(got[1].message, 'subjectParameters[2] is the new level').toBe(
			'Full access',
		)
	})

	// @e2e dashboard-sharing::level-downgrade-is-silent
	test('downgrading a share publishes nothing', async () => {
		const dash = await dashboardOf('downgrade')
		await share(alice!.api, dash.id, 'user', bob!.username, 'full')
		// CONTROL: this recipient's feed does fill when a notification is due.
		expect(await count(bob!, dash)).toBe(1)

		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')

		expect(
			shareTuples(await listShares(alice!.api, dash.id)),
			'CONTROL: the downgrade itself took',
		).toEqual([`user:${bob!.username}:view_only`])
		expect(await count(bob!, dash), 'a downgrade must be silent').toBe(1)
	})

	// @e2e dashboard-sharing::no-op-upsert-is-silent
	test('re-sending the same share changes nothing and publishes nothing', async () => {
		const dash = await dashboardOf('noop')
		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')
		expect(await count(bob!, dash)).toBe(1)
		const [before] = await listShares(alice!.api, dash.id)

		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')

		const after = await listShares(alice!.api, dash.id)
		expect(after, 'still one row').toHaveLength(1)
		expect(after[0].id, 'the same row').toBe(before.id)
		expect(after[0].createdAt, 'created_at unchanged').toBe(before.createdAt)
		expect(after[0].permissionLevel).toBe('view_only')
		expect(await count(bob!, dash), 'a no-op upsert must be silent').toBe(1)
	})

	// @e2e dashboard-sharing::revocation-is-silent
	test('removing a share deletes the row and publishes nothing', async () => {
		const dash = await dashboardOf('revoke')
		const created = await share(
			alice!.api,
			dash.id,
			'user',
			bob!.username,
			'view_only',
		)
		expect(await count(bob!, dash)).toBe(1)

		const res = await alice!.api.delete(`${API}/dashboard/share/${created.id}`)
		expect(res.status(), await res.text()).toBe(204)

		expect(
			await listShares(alice!.api, dash.id),
			'the row is gone',
		).toHaveLength(0)
		expect(await count(bob!, dash), 'a revocation must be silent').toBe(1)
	})
})

test.describe('REQ-SHARE-009 bulk replace, notifications included', () => {
	// @e2e dashboard-sharing::replace-adds-upgrades-and-removes-in-one-call
	test('one PUT upgrades bob, adds dave, drops carol and sales, and tells only bob and dave', async () => {
		const dash = await dashboardOf('replace')
		await share(alice!.api, dash.id, 'user', bob!.username, 'view_only')
		await share(alice!.api, dash.id, 'user', carol!.username, 'view_only')
		await share(alice!.api, dash.id, 'group', sales!, 'view_only')
		const before = {
			bob: await count(bob!, dash),
			carol: await count(carol!, dash),
			dave: await count(dave!, dash),
			erin: await count(erin!, dash),
		}
		expect(before, 'CONTROL: each first share told its recipient').toEqual({
			bob: 1,
			carol: 1,
			dave: 0,
			erin: 1,
		})

		const res = await alice!.api.put(`${API}/dashboard/${dash.id}/shares`, {
			data: {
				shares: [
					{
						shareType: 'user',
						shareWith: bob!.username,
						permissionLevel: 'full',
					},
					{
						shareType: 'user',
						shareWith: dave!.username,
						permissionLevel: 'view_only',
					},
				],
			},
		})
		expect(res.status(), await res.text()).toBe(200)

		expect(shareTuples(await listShares(alice!.api, dash.id))).toEqual(
			[
				`user:${bob!.username}:full`,
				`user:${dave!.username}:view_only`,
			].sort(),
		)

		const bobFeed = await shareNotifications(bob!.api, dash.id)
		expect(bobFeed, 'bob is told about the upgrade').toHaveLength(2)
		expect(bobFeed[1].message).toBe('Full access')
		const daveFeed = await shareNotifications(dave!.api, dash.id)
		expect(daveFeed, 'dave is told about the new share').toHaveLength(1)
		expect(daveFeed[0].message).toBe('View-only access')
		expect(
			await count(carol!, dash),
			'carol was removed, and removal is silent',
		).toBe(1)
		expect(
			await count(erin!, dash),
			'the sales group was removed, and removal is silent',
		).toBe(1)
	})

	// @e2e dashboard-sharing::idempotent-re-put-publishes-nothing
	test('replaying the same PUT changes no row and publishes nothing', async () => {
		const dash = await dashboardOf('replay')
		const payload = {
			shares: [
				{
					shareType: 'user',
					shareWith: bob!.username,
					permissionLevel: 'full',
				},
				{
					shareType: 'user',
					shareWith: dave!.username,
					permissionLevel: 'view_only',
				},
			],
		}
		const first = await alice!.api.put(`${API}/dashboard/${dash.id}/shares`, {
			data: payload,
		})
		expect(first.status(), await first.text()).toBe(200)
		const rowsBefore = await listShares(alice!.api, dash.id)
		// CONTROL: the first PUT did notify both, so the feeds work.
		expect(await count(bob!, dash)).toBe(1)
		expect(await count(dave!, dash)).toBe(1)

		const second = await alice!.api.put(`${API}/dashboard/${dash.id}/shares`, {
			data: payload,
		})
		expect(second.status(), await second.text()).toBe(200)

		const shape = (rows: Array<Record<string, unknown>>) =>
			rows.map((r) => `${r.id}:${shareTuples([r])[0]}:${r.createdAt}`).sort()
		expect(
			shape(await listShares(alice!.api, dash.id)),
			'no row may change',
		).toEqual(shape(rowsBefore))
		expect(await count(bob!, dash), 'nothing new for bob').toBe(1)
		expect(await count(dave!, dash), 'nothing new for dave').toBe(1)
	})
})

test.describe('REQ-SHARE-011 the notifier renders the share', () => {
	// @e2e dashboard-sharing::notifier-renders-share-notification-in-english
	test('an English recipient reads "<sharer> shared **<name>** with you", "Full access", and a deep link', async () => {
		const dash = await dashboardOf('render')
		await share(alice!.api, dash.id, 'user', carol!.username, 'full')

		const got = await shareNotifications(carol!.api, dash.id)
		expect(got).toHaveLength(1)
		const [entry] = got
		expect(entry.subjectRich).toBe(
			`${alice!.username} shared **${dash.name}** with you`,
		)
		expect(entry.subject, 'the parsed subject carries no markup').toBe(
			`${alice!.username} shared ${dash.name} with you`,
		)
		expect(entry.message).toBe('Full access')
		// linkToRouteAbsolute('launchpad.page.index') . '?dashboard=' . <uuid>
		expect(entry.link).toMatch(
			new RegExp(`^https?://[^?]+/apps/launchpad/\\?dashboard=${dash.uuid}$`),
		)
	})
})
