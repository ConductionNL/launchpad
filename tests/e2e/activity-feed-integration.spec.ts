/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for LaunchPad's Nextcloud Activity integration
 * (openspec/specs/activity-feed-integration), written for
 * e2e-coverage-programme task 5.4.
 *
 * Every test performs a real LaunchPad action over its HTTP API (create,
 * update, share, publish, schedule a dashboard) and then reads what Nextcloud's
 * Activity app recorded, through its own OCS feed
 * (`/ocs/v2.php/apps/activity/api/v2/activity`) as each recipient, or through
 * the initial state of the Activity settings pages. Recipients are throwaway
 * accounts created over basic auth; their feeds are read as them, so a row in
 * a feed is a row addressed to that user.
 *
 * 🔴 THE ACTIVITY APP MUST BE ENABLED. It is not part of the nextcloud/server
 * git checkout, so an instance built from it does not have it. This file does
 * NOT skip in that case: `beforeAll` fails every test with a message naming
 * the fix, because a skipped activity suite reads as a passing one.
 *
 * Rows are matched to a dashboard by its UUID, in `object_id` (where the spec
 * puts it) or `object_name` (where the product puts it), so a test that
 * asserts a count is not thrown off by which slot the UUID landed in.
 *
 * Gate-19 @e2e traceability:
 *   @e2e activity-feed-integration::extension-resolves-from-di-container
 *   @e2e activity-feed-integration::extension-is-listed-in-nc-activity-admin-settings
 *   @e2e activity-feed-integration::geticon-returns-non-empty-url-for-all-known-types
 *   @e2e activity-feed-integration::correct-fields-populated-on-ievent
 *   @e2e activity-feed-integration::publish-called-after-domain-action-is-persisted
 *   @e2e activity-feed-integration::self-action-on-personal-dashboard-writes-no-row
 *   @e2e activity-feed-integration::no-fan-out-for-personal-dashboards
 *   @e2e activity-feed-integration::share-with-two-users-produces-three-rows
 *   @e2e activity-feed-integration::replacing-the-share-list-tells-the-sharer-once
 *   @e2e activity-feed-integration::user-turns-off-push-for-dashboardshared
 *   @e2e activity-feed-integration::actor-row-uses-first-person-subject-recipient-rows-use-secondthird-person
 *   @e2e activity-feed-integration::sharing-with-a-user-who-already-has-the-dashboard-does-not-skip-their-row
 *   @e2e activity-feed-integration::update-on-group-shared-dashboard-fans-out-to-all-members
 *   @e2e activity-feed-integration::actor-who-is-also-a-group-member-does-not-receive-a-duplicate-row
 *   @e2e activity-feed-integration::group-with-zero-members-produces-zero-activity-rows
 *   @e2e activity-feed-integration::first-global-event-triggers-full-fan-out
 *   @e2e activity-feed-integration::different-event-type-on-same-dashboard-is-not-suppressed
 *   @e2e activity-feed-integration::default-notification-preferences-for-a-new-user
 *   @e2e activity-feed-integration::opt-out-state-is-per-user-and-per-type
 *   @e2e activity-feed-integration::missing-translation-key-falls-back-to-english
 *   @e2e activity-feed-integration::placeholder-substitution-does-not-leave-unresolved-tokens
 *   @e2e activity-feed-integration::group-dashboard-published
 *   @e2e activity-feed-integration::ten-edits-in-one-morning
 *
 * @spec openspec/specs/activity-feed-integration/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	addToGroup,
	adminContext,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const FEED = '/ocs/v2.php/apps/activity/api/v2/activity'
const RUN = Date.now()

/** The 13 event types the spec's catalogue names (REQ-ACT-002). */
const SPEC_EVENT_TYPES = [
	'dashboard_created',
	'dashboard_updated',
	'dashboard_deleted',
	'dashboard_published',
	'dashboard_unpublished',
	'dashboard_scheduled',
	'dashboard_shared',
	'dashboard_public_share_created',
	'dashboard_commented',
	'dashboard_reacted',
	'dashboard_restored',
	'dashboard_lock_overridden',
	'dashboard_role_changed',
]

/**
 * The types a person can switch per channel, with their mail default
 * (REQ-DIGE-001). Only the types LaunchPad emits are registered as settings;
 * the other catalogue types are never sent, so offering them would be a
 * switch that does nothing.
 */
const SETTING_MAIL_DEFAULTS: Record<string, boolean> = {
	dashboard_shared: true,
	dashboard_published: true,
	dashboard_updated: false,
	dashboard_acknowledged: false,
}

/** One entry of the Activity app's OCS feed. */
type FeedRow = {
	activity_id: number
	app: string
	type: string
	user: string
	subject: string
	message: string
	object_type: string
	object_id: number | string
	object_name: string
	link: string
	icon: string
	datetime: string
}

/** One activity type as the Activity settings page offers it. */
type SettingEntry = {
	desc: string
	email: boolean | number
	notification: boolean | number
	methods: string[]
}

/** The `activity_groups` initial state of an Activity settings page. */
type ActivityGroups = Record<
	string,
	{ name: string; activities: Record<string, SettingEntry> }
>

/** A dashboard the file created, by the ids the API needs. */
type Dash = { id: number; uuid: string; name: string; groupId?: string }

let admin: APIRequestContext
const cleanups: Array<() => Promise<unknown>> = []

/**
 * Read a user's Activity feed, newest first.
 *
 * The Activity app answers 304 with no body when the feed is empty, which is
 * read as an empty list. Any other non-200 is thrown, never swallowed: a feed
 * that cannot be read must not look like a feed with zero rows.
 *
 * @param api the context of the user whose feed this is.
 * @return the feed rows.
 */
async function feed(api: APIRequestContext): Promise<FeedRow[]> {
	const res = await api.get(`${FEED}?format=json&limit=100&sort=desc`, {
		headers: { 'OCS-APIRequest': 'true' },
	})
	if (res.status() === 304) {
		return []
	}
	if (res.status() !== 200) {
		throw new Error(
			`the activity feed answered ${res.status()}: ${await res.text()}`,
		)
	}
	return ((await res.json())?.ocs?.data ?? []) as FeedRow[]
}

/**
 * The LaunchPad rows in a user's feed about one dashboard.
 *
 * @param api the context of the user whose feed this is.
 * @param dash the dashboard.
 * @param type an event type to narrow to, or every type when omitted.
 * @return the matching rows.
 */
async function rowsAbout(
	api: APIRequestContext,
	dash: Dash,
	type?: string,
): Promise<FeedRow[]> {
	return (await feed(api)).filter(
		(row) => row.app === 'launchpad'
			&& (row.object_name === dash.uuid || String(row.object_id) === dash.uuid)
			&& (type === undefined || row.type === type),
	)
}

/**
 * Fail the whole file, loudly, unless the Activity app is answering.
 *
 * @param api an admin context.
 * @return nothing; throws when the feed endpoint is not there.
 */
async function requireActivityApp(api: APIRequestContext): Promise<void> {
	const res = await api.get(`${FEED}?format=json&limit=1`)
	if (res.status() !== 200 && res.status() !== 304) {
		throw new Error(
			`Nextcloud's Activity app is not answering (${FEED} → ${res.status()}). `
				+ 'Every assertion in this file reads its feed. Enable it with '
				+ '`occ app:enable activity`; in CI add nextcloud/activity to the '
				+ 'workflow\'s `additional-apps`, because the nextcloud/server '
				+ 'checkout does not contain it.',
		)
	}
}

/**
 * Create a personal dashboard owned by the admin.
 *
 * @param name the dashboard name.
 * @return the dashboard.
 */
async function personalDashboard(name: string): Promise<Dash> {
	const res = await admin.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = (await res.json()).dashboard ?? {}
	const dash = { id: Number(body.id), uuid: String(body.uuid), name }
	cleanups.push(() => admin.delete(`${API}/dashboard/${dash.id}`))
	return dash
}

/**
 * Create a group-shared dashboard for a group (or the `default` sentinel).
 *
 * @param groupId the group.
 * @param name the dashboard name.
 * @return the dashboard.
 */
async function groupDashboard(groupId: string, name: string): Promise<Dash> {
	const res = await admin.post(`${API}/dashboards/group/${groupId}`, {
		data: { name },
	})
	expect(res.status(), await res.text()).toBe(201)
	const body = (await res.json()).dashboard ?? {}
	const dash = { id: Number(body.id), uuid: String(body.uuid), name, groupId }
	cleanups.push(() => admin.delete(`${API}/dashboards/group/${groupId}/${dash.uuid}`))
	return dash
}

/**
 * Save a group-shared dashboard as the admin.
 *
 * @param dash the dashboard.
 * @param patch the fields to change.
 * @return nothing; asserts the save answered 200.
 */
async function updateGroupDashboard(
	dash: Dash,
	patch: Record<string, unknown>,
): Promise<void> {
	const res = await admin.put(
		`${API}/dashboards/group/${dash.groupId}/${dash.uuid}`,
		{ data: patch },
	)
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Save a personal dashboard as the admin.
 *
 * @param dash the dashboard.
 * @param patch the fields to change.
 * @return nothing; asserts the save answered 200.
 */
async function updatePersonalDashboard(
	dash: Dash,
	patch: Record<string, unknown>,
): Promise<void> {
	const res = await admin.put(`${API}/dashboard/${dash.id}`, { data: patch })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Share a dashboard the admin owns with a user or a group.
 *
 * @param dash the dashboard.
 * @param shareType `user` or `group`.
 * @param shareWith the recipient id.
 * @return nothing; asserts the share answered 201.
 */
async function share(
	dash: Dash,
	shareType: 'user' | 'group',
	shareWith: string,
): Promise<void> {
	const res = await admin.post(`${API}/dashboard/${dash.id}/shares`, {
		data: { shareType, shareWith, permissionLevel: 'view_only' },
	})
	expect(res.status(), await res.text()).toBe(201)
}

/**
 * A throwaway account, removed in `afterAll`.
 *
 * @param prefix a readable prefix.
 * @return the principal.
 */
async function user(prefix: string): Promise<Principal> {
	const principal = await createPrincipal(admin, prefix)
	await assertActingAs(principal.api, principal.username)
	cleanups.push(() => removePrincipal(admin, principal))
	return principal
}

/**
 * A fresh group holding the given accounts, removed in `afterAll`.
 *
 * @param members the account ids to put in it.
 * @return the group id.
 */
async function group(members: string[]): Promise<string> {
	const groupId = await createGroup(
		admin,
		`e2e-act-${RUN}-${Math.floor(Math.random() * 100_000)}`,
	)
	cleanups.push(() => removeGroup(admin, groupId))
	for (const member of members) {
		await addToGroup(admin, member, groupId)
	}
	return groupId
}

/**
 * Read the `activity_groups` initial state of an Activity settings page.
 *
 * Both the personal page (`/settings/user/notifications`) and the admin page
 * (`/settings/admin/activity`) hand the Vue form its data this way, so the
 * table a person sees is exactly this structure.
 *
 * @param api the context to read the page as.
 * @param path the settings page path.
 * @return the groups, keyed by group id.
 */
async function activityGroups(
	api: APIRequestContext,
	path: string,
): Promise<ActivityGroups> {
	const res = await api.get(path)
	expect(res.status(), `GET ${path}`).toBe(200)
	const html = await res.text()
	const match = html.match(
		/id="initial-state-activity-activity_groups"\s+value="([^"]+)"/,
	)
	if (match === null) {
		throw new Error(
			`${path} carries no activity_groups initial state; the Activity `
				+ 'settings form is not on that page.',
		)
	}
	return JSON.parse(Buffer.from(match[1], 'base64').toString('utf8'))
}

test.describe('activity-feed-integration', () => {
	test.beforeAll(async () => {
		admin = await adminContext()
		await assertActingAs(admin, ADMIN.user)
		await requireActivityApp(admin)
	})

	test.afterAll(async () => {
		for (const cleanup of cleanups.reverse()) {
			await cleanup().catch(() => undefined)
		}
		await admin?.dispose()
	})

	// @e2e activity-feed-integration::extension-resolves-from-di-container
	test('a LaunchPad event is parsed by the registered provider and reaches the feed', async () => {
		const recipient = await user('act-di')
		const dash = await personalDashboard(`E2E Act DI ${RUN}`)

		await share(dash, 'user', recipient.username)

		// NC Activity drops an event no provider parses, so a parsed row in the
		// recipient's feed is the provider having been resolved and run.
		const rows = await rowsAbout(recipient.api, dash, 'dashboard_shared')
		expect(rows).toHaveLength(1)
		expect(rows[0].app).toBe('launchpad')
		expect(rows[0].subject).toContain(dash.name)
		expect(rows[0].subject).not.toBe('dashboard_shared')
		expect(rows[0].icon).not.toBe('')
	})

	// @e2e activity-feed-integration::extension-is-listed-in-nc-activity-admin-settings
	test('the admin Activity settings list LaunchPad with every event type it sends', async () => {
		const groups = await activityGroups(admin, '/index.php/settings/admin/activity')

		const launchpad = groups.launchpad
		expect(launchpad, `groups: ${Object.keys(groups).join(', ')}`).toBeDefined()
		expect(launchpad.name).toBe('LaunchPad')
		expect(Object.keys(launchpad.activities).sort()).toEqual(
			Object.keys(SETTING_MAIL_DEFAULTS).sort(),
		)
		for (const type of Object.keys(SETTING_MAIL_DEFAULTS)) {
			const entry = launchpad.activities[type]
			expect(entry, `${type} is not offered for opt-out`).toBeDefined()
			expect(entry.desc).not.toBe('')
			expect(entry.methods).toEqual(expect.arrayContaining(['email', 'notification']))
		}
	})

	// @e2e activity-feed-integration::geticon-returns-non-empty-url-for-all-known-types
	test('every known type has an absolute icon URL that serves an SVG from LaunchPad', async () => {
		const recipient = await user('act-icon')
		const dash = await personalDashboard(`E2E Act Icon ${RUN}`)
		await share(dash, 'user', recipient.username)

		const [row] = await rowsAbout(recipient.api, dash, 'dashboard_shared')
		expect(row, 'no dashboard_shared row to read an icon from').toBeDefined()
		expect(row.icon).toMatch(/^https?:\/\/[^/]+\/.*apps\/launchpad\/img\/activity\/dashboard_shared\.svg$/)

		const sharedPath = new URL(row.icon).pathname
		for (const type of SPEC_EVENT_TYPES) {
			const path = sharedPath.replace('dashboard_shared.svg', `${type}.svg`)
			const res = await admin.get(path)
			expect(res.status(), path).toBe(200)
			expect(res.headers()['content-type'] ?? '', path).toContain('svg')
			expect(await res.text(), path).toContain('<svg')
		}
	})

	// @e2e activity-feed-integration::correct-fields-populated-on-ievent
	test('an updated event carries app, type, object type, the dashboard UUID and its link', async () => {
		const groupId = await group([(await user('act-fields')).username])
		const dash = await groupDashboard(groupId, `E2E Act Fields ${RUN}`)

		await updateGroupDashboard(dash, { name: `${dash.name} v2` })

		const rows = await rowsAbout(admin, dash, 'dashboard_updated')
		expect(rows).toHaveLength(1)
		expect(rows[0].app).toBe('launchpad')
		expect(rows[0].type).toBe('dashboard_updated')
		expect(rows[0].object_type).toBe('launchpad_dashboard')
		// Nextcloud's object_id is an integer column, so the UUID the spec puts
		// there can only travel in object_name.
		expect(rows[0].object_name).toBe(dash.uuid)
		expect(rows[0].link).toContain('/apps/launchpad')
		expect(rows[0].link).toContain(dash.uuid)
	})

	// @e2e activity-feed-integration::publish-called-after-domain-action-is-persisted
	test('the updated row names the dashboard as it is after the save', async () => {
		const member = await user('act-after')
		const groupId = await group([member.username])
		const dash = await groupDashboard(groupId, `E2E Act Before ${RUN}`)
		const renamed = `E2E Act After ${RUN}`

		await updateGroupDashboard(dash, { name: renamed })

		const rows = await rowsAbout(member.api, dash, 'dashboard_updated')
		expect(rows).toHaveLength(1)
		expect(rows[0].subject).toContain(renamed)
		expect(rows[0].subject).not.toContain(dash.name)
	})

	// @e2e activity-feed-integration::self-action-on-personal-dashboard-writes-no-row
	test('updating your own private dashboard writes no row', async () => {
		const dash = await personalDashboard(`E2E Act Self ${RUN}`)

		await updatePersonalDashboard(dash, { name: `${dash.name} v2` })

		expect(await rowsAbout(admin, dash, 'dashboard_updated')).toHaveLength(0)
	})

	// @e2e activity-feed-integration::no-fan-out-for-personal-dashboards
	test('adding a widget to an unshared personal dashboard tells nobody, the owner included', async () => {
		const bystander = await user('act-nofan')
		const dash = await personalDashboard(`E2E Act NoFan ${RUN}`)

		const widget = await admin.post(`${API}/dashboard/${dash.id}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 0,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 2,
				content: { text: 'activity', contentMode: 'markdown' },
			},
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)

		expect(await rowsAbout(admin, dash, 'dashboard_updated')).toHaveLength(0)
		expect(await rowsAbout(bystander.api, dash)).toHaveLength(0)
	})

	test.describe('one share with two users', () => {
		let bob: Principal
		let carol: Principal
		let dash: Dash

		test.beforeAll(async () => {
			bob = await user('act-bob')
			carol = await user('act-carol')
			dash = await personalDashboard(`E2E Act Marketing ${RUN}`)

			const res = await admin.put(`${API}/dashboard/${dash.id}/shares`, {
				data: {
					shares: [
						{ shareType: 'user', shareWith: bob.username, permissionLevel: 'view_only' },
						{ shareType: 'user', shareWith: carol.username, permissionLevel: 'view_only' },
					],
				},
			})
			expect(res.status(), await res.text()).toBe(200)
		})

		// @e2e activity-feed-integration::share-with-two-users-produces-three-rows
		// @e2e activity-feed-integration::replacing-the-share-list-tells-the-sharer-once
		test('writes three dashboard_shared rows: the sharer, bob and carol', async () => {
			expect(await rowsAbout(admin, dash, 'dashboard_shared')).toHaveLength(1)
			expect(await rowsAbout(bob.api, dash, 'dashboard_shared')).toHaveLength(1)
			expect(await rowsAbout(carol.api, dash, 'dashboard_shared')).toHaveLength(1)
		})

		// @e2e activity-feed-integration::actor-row-uses-first-person-subject-recipient-rows-use-secondthird-person
		test('the sharer reads it in the first person, a recipient as shared with you', async () => {
			const [own] = await rowsAbout(admin, dash, 'dashboard_shared')
			expect(own?.subject).toBe(
				`You shared dashboard ${dash.name} with ${bob.username}, ${carol.username}`,
			)

			const [bobs] = await rowsAbout(bob.api, dash, 'dashboard_shared')
			expect(bobs?.subject).toBe(`${ADMIN.user} shared dashboard ${dash.name} with you`)
		})
	})

	// @e2e activity-feed-integration::sharing-with-a-user-who-already-has-the-dashboard-does-not-skip-their-row
	test('a direct share still tells a user who already sees the dashboard through a group', async () => {
		const carol = await user('act-dup')
		const groupId = await group([carol.username])
		const dash = await personalDashboard(`E2E Act Dup ${RUN}`)

		await share(dash, 'group', groupId)
		const before = (await rowsAbout(carol.api, dash, 'dashboard_shared')).length

		await share(dash, 'user', carol.username)

		expect(await rowsAbout(carol.api, dash, 'dashboard_shared')).toHaveLength(before + 1)
	})

	// @e2e activity-feed-integration::update-on-group-shared-dashboard-fans-out-to-all-members
	test('an admin update on a group dashboard writes one row each to the admin and every member', async () => {
		const members = [await user('act-ga'), await user('act-gb'), await user('act-gc')]
		const groupId = await group(members.map((m) => m.username))
		const dash = await groupDashboard(groupId, `E2E Act Campaigns ${RUN}`)

		await updateGroupDashboard(dash, { description: 'fan out' })

		expect(await rowsAbout(admin, dash, 'dashboard_updated')).toHaveLength(1)
		for (const member of members) {
			expect(
				await rowsAbout(member.api, dash, 'dashboard_updated'),
				member.username,
			).toHaveLength(1)
		}
	})

	// @e2e activity-feed-integration::actor-who-is-also-a-group-member-does-not-receive-a-duplicate-row
	test('an actor who is also a member gets one row, not two', async () => {
		const members = [await user('act-ma'), await user('act-mb')]
		const groupId = await group([ADMIN.user, ...members.map((m) => m.username)])
		const dash = await groupDashboard(groupId, `E2E Act Member ${RUN}`)

		await updateGroupDashboard(dash, { description: 'member actor' })

		expect(await rowsAbout(admin, dash, 'dashboard_updated')).toHaveLength(1)
		for (const member of members) {
			expect(
				await rowsAbout(member.api, dash, 'dashboard_updated'),
				member.username,
			).toHaveLength(1)
		}
	})

	// @e2e activity-feed-integration::group-with-zero-members-produces-zero-activity-rows
	test('updating the dashboard of an empty group succeeds and writes no row', async () => {
		const groupId = await group([])
		const dash = await groupDashboard(groupId, `E2E Act Empty ${RUN}`)

		// updateGroupDashboard asserts the 200: no exception reached the caller.
		await updateGroupDashboard(dash, { description: 'nobody home' })

		expect(await rowsAbout(admin, dash)).toHaveLength(0)
	})

	test.describe('a default-group dashboard', () => {
		let dash: Dash
		let people: Principal[]

		test.beforeAll(async () => {
			people = [await user('act-all-a'), await user('act-all-b')]
			dash = await groupDashboard('default', `E2E Act Everyone ${RUN}`)
		})

		// @e2e activity-feed-integration::first-global-event-triggers-full-fan-out
		test('the first update fans out one row to every user', async () => {
			await updateGroupDashboard(dash, { description: 'first' })

			expect(await rowsAbout(admin, dash, 'dashboard_updated')).toHaveLength(1)
			for (const person of people) {
				expect(
					await rowsAbout(person.api, dash, 'dashboard_updated'),
					person.username,
				).toHaveLength(1)
			}
		})

		// @e2e activity-feed-integration::different-event-type-on-same-dashboard-is-not-suppressed
		test('a publication right after an update still fans out', async () => {
			// Make sure the update debounce is live, whatever ran before.
			await updateGroupDashboard(dash, { description: 'again' })

			const unpublish = await admin.post(`${API}/dashboards/${dash.uuid}/unpublish`)
			expect(unpublish.status(), await unpublish.text()).toBe(200)
			const publish = await admin.post(`${API}/dashboards/${dash.uuid}/publish`)
			expect(publish.status(), await publish.text()).toBe(200)

			expect(await rowsAbout(admin, dash, 'dashboard_published')).toHaveLength(1)
			for (const person of people) {
				expect(
					await rowsAbout(person.api, dash, 'dashboard_published'),
					person.username,
				).toHaveLength(1)
			}
		})
	})

	// @e2e activity-feed-integration::default-notification-preferences-for-a-new-user
	test('a new user gets LaunchPad events in the stream, mail and push only for shares and publications', async () => {
		const frank = await user('act-frank')
		const groups = await activityGroups(frank.api, '/index.php/settings/user/notifications')

		const activities = groups.launchpad?.activities ?? {}
		for (const [type, mail] of Object.entries(SETTING_MAIL_DEFAULTS)) {
			expect(activities[type], `${type} has no preference`).toBeDefined()
			expect(Boolean(activities[type].email), `${type} email`).toBe(mail)
			expect(Boolean(activities[type].notification), `${type} push`).toBe(mail)
		}

		// Activity 7 writes every event to the stream; its settings page only
		// switches mail and push. In-app on means the row reaches the stream.
		const dash = await personalDashboard(`E2E Act Frank ${RUN}`)
		await share(dash, 'user', frank.username)
		expect(await rowsAbout(frank.api, dash, 'dashboard_shared')).toHaveLength(1)
	})

	// @e2e activity-feed-integration::user-turns-off-push-for-dashboardshared
	test('frank turning off push for dashboard_shared still gets the row in his stream', async () => {
		const frank = await user('act-nopush')
		const path = '/index.php/settings/user/notifications'
		const form: Record<string, string> = {}
		for (const g of Object.values(await activityGroups(frank.api, path))) {
			for (const [id, entry] of Object.entries(g.activities)) {
				const notify = id === 'dashboard_shared' ? false : Boolean(entry.notification)
				form[`${id}_notification`] = notify ? '1' : '0'
				form[`${id}_email`] = entry.email ? '1' : '0'
			}
		}
		const saved = await frank.api.post('/index.php/apps/activity/settings', { form })
		expect(saved.status(), await saved.text()).toBe(200)
		const stored = (await activityGroups(frank.api, path)).launchpad.activities
		expect(Boolean(stored.dashboard_shared.notification)).toBe(false)

		const dash = await personalDashboard(`E2E Act NoPush ${RUN}`)
		await share(dash, 'user', frank.username)

		expect(await rowsAbout(frank.api, dash, 'dashboard_shared')).toHaveLength(1)
	})

	// @e2e activity-feed-integration::opt-out-state-is-per-user-and-per-type
	test('frank turning on push for dashboard_updated changes only his push, both streams get the row', async () => {
		const frank = await user('act-optout')
		const grace = await user('act-grace')
		const groupId = await group([frank.username, grace.username])

		// Save frank's settings the way the form does: every type keeps its
		// current values except dashboard_updated, whose push box is ticked.
		const path = '/index.php/settings/user/notifications'
		const form: Record<string, string> = {}
		for (const g of Object.values(await activityGroups(frank.api, path))) {
			for (const [id, entry] of Object.entries(g.activities)) {
				const notify = id === 'dashboard_updated' ? true : Boolean(entry.notification)
				form[`${id}_notification`] = notify ? '1' : '0'
				form[`${id}_email`] = entry.email ? '1' : '0'
			}
		}
		const saved = await frank.api.post('/index.php/apps/activity/settings', { form })
		expect(saved.status(), await saved.text()).toBe(200)
		const stored = (await activityGroups(frank.api, path)).launchpad.activities
		expect(Boolean(stored.dashboard_updated.notification)).toBe(true)

		const dash = await groupDashboard(groupId, `E2E Act OptOut ${RUN}`)
		await updateGroupDashboard(dash, { description: 'opt-out' })

		expect(await rowsAbout(frank.api, dash, 'dashboard_updated')).toHaveLength(1)
		expect(await rowsAbout(grace.api, dash, 'dashboard_updated')).toHaveLength(1)
		const graces = (await activityGroups(grace.api, path)).launchpad.activities
		expect(Boolean(graces.dashboard_updated.notification)).toBe(false)
	})

	// @e2e activity-feed-integration::missing-translation-key-falls-back-to-english
	test('a user in a language LaunchPad has no translation for reads the English subject', async () => {
		const reader = await user('act-ja')
		// LaunchPad ships no `ja` catalogue (l10n/ has no ja.*); Nextcloud does.
		const lang = await admin.put(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(reader.username)}`,
			{ form: { key: 'language', value: 'ja' } },
		)
		expect(lang.status(), await lang.text()).toBe(200)
		const me = await reader.api.get('/ocs/v1.php/cloud/user?format=json')
		expect((await me.json())?.ocs?.data?.language).toBe('ja')

		const dash = await personalDashboard(`E2E Act Lang ${RUN}`)
		await share(dash, 'user', reader.username)

		const [row] = await rowsAbout(reader.api, dash, 'dashboard_shared')
		expect(row?.subject).toMatch(
			new RegExp(`^${ADMIN.user} shared dashboard ${dash.name} with `),
		)
	})

	// @e2e activity-feed-integration::placeholder-substitution-does-not-leave-unresolved-tokens
	test('an updated subject is fully substituted: actor, verb, dashboard', async () => {
		const member = await user('act-tokens')
		const groupId = await group([member.username])
		const dash = await groupDashboard(groupId, `E2E Act Tokens ${RUN}`)

		await updateGroupDashboard(dash, { description: 'tokens' })

		const [row] = await rowsAbout(member.api, dash, 'dashboard_updated')
		expect(row?.subject).toBe(`${ADMIN.user} updated dashboard ${dash.name}`)
		expect(row?.subject).not.toMatch(/\{(actor|dashboard)\}/)
	})

	// @e2e activity-feed-integration::group-dashboard-published
	test('a scheduled group dashboard, once surfaced, tells each member once and its scheduler never', async () => {
		test.setTimeout(120_000)
		const members = [await user('act-sched-a'), await user('act-sched-b')]
		const groupId = await group(members.map((m) => m.username))
		const dash = await groupDashboard(groupId, `E2E Act Organisatie ${RUN}`)

		const publishAt = new Date(Date.now() + 5_000).toISOString()
		const scheduled = await admin.post(`${API}/dashboards/${dash.uuid}/schedule`, {
			data: { publishAt },
		})
		expect(scheduled.status(), await scheduled.text()).toBe(200)

		await new Promise((resolve) => setTimeout(resolve, 8_000))
		// Surface it: each member reads the dashboards visible to them.
		for (const member of members) {
			const visible = await member.api.get(`${API}/dashboards/visible`)
			expect(visible.status(), member.username).toBe(200)
		}

		for (const member of members) {
			expect(
				await rowsAbout(member.api, dash, 'dashboard_published'),
				member.username,
			).toHaveLength(1)
		}
		expect(await rowsAbout(admin, dash, 'dashboard_published')).toHaveLength(0)
	})

	// @e2e activity-feed-integration::ten-edits-in-one-morning
	test('ten edits to a shared dashboard show as one update entry', async () => {
		test.setTimeout(120_000)
		const pieter = await user('act-pieter')
		const dash = await personalDashboard(`E2E Act Vergunningen ${RUN}`)
		await share(dash, 'user', pieter.username)

		for (let edit = 1; edit <= 10; edit++) {
			await updatePersonalDashboard(dash, { description: `edit ${edit}` })
		}

		const rows = await rowsAbout(pieter.api, dash, 'dashboard_updated')
		expect(rows).toHaveLength(1)
		expect(rows[0].subject).toBe(`${ADMIN.user} updated dashboard ${dash.name}`)
	})
})

