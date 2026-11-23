/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the dashboard reactions API
 * (openspec/specs/dashboard-reactions, REQ-RXN-001..009), written for the
 * e2e-coverage-programme.
 *
 * Every scenario here IS an HTTP contract, so the tests drive the four
 * reaction routes in appinfo/routes.php as real, signed-in principals over
 * basic auth:
 *
 *   - `owner`    a throwaway account that owns each dashboard ("Alice Smith");
 *   - `viewer`   a throwaway account the owner shares it with at `view_only`,
 *                the least privileged principal that may react ("Bob Jones");
 *   - `carol`, `dave`  further `view_only` sharees;
 *   - `outsider` a throwaway ordinary account with no share, the least
 *                privileged principal every refusal is about.
 *
 * The two admin settings are Nextcloud app values with no LaunchPad endpoint
 * of their own, so they are written the way an administrator's tooling does:
 * through Nextcloud's provisioning API
 * (`/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/<key>`).
 * Both are recorded in beforeAll and restored (or removed again) in afterAll.
 * Reactions are off by default (ReactionService::isReactionsEnabledByDefault),
 * so beforeAll switches the global toggle on.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-reactions::user-adds-a-reaction-to-a-dashboard-they-can-view
 *   @e2e dashboard-reactions::user-cannot-react-to-a-dashboard-they-cannot-view
 *   @e2e dashboard-reactions::user-attempts-to-react-with-non-whitelisted-emoji
 *   @e2e dashboard-reactions::user-re-posts-the-same-emoji-idempotent
 *   @e2e dashboard-reactions::user-can-react-with-multiple-distinct-emojis-to-the-same-dashboard
 *   @e2e dashboard-reactions::user-removes-a-reaction-they-made
 *   @e2e dashboard-reactions::user-attempts-to-remove-a-reaction-they-did-not-make
 *   @e2e dashboard-reactions::user-cannot-remove-another-users-reaction
 *   @e2e dashboard-reactions::user-retrieves-reactions-on-a-dashboard-they-can-view
 *   @e2e dashboard-reactions::user-cannot-view-reactions-if-they-cannot-view-the-dashboard
 *   @e2e dashboard-reactions::no-reactions-yet-on-a-dashboard
 *   @e2e dashboard-reactions::user-retrieves-reactors-for-a-specific-emoji
 *   @e2e dashboard-reactions::pagination-with-cursor
 *   @e2e dashboard-reactions::emoji-with-no-reactors
 *   @e2e dashboard-reactions::user-cannot-view-reactors-if-they-cannot-view-the-dashboard
 *   @e2e dashboard-reactions::admin-disables-reactions-globally
 *   @e2e dashboard-reactions::admin-re-enables-reactions-globally
 *   @e2e dashboard-reactions::dashboards-created-before-global-toggle-change-inherit-the-toggle-state
 *   @e2e dashboard-reactions::null-reactionsenabled-field-follows-global-setting
 *   @e2e dashboard-reactions::admin-updates-the-allowed-emoji-list
 *   @e2e dashboard-reactions::empty-emoji-in-whitelist
 *   @e2e dashboard-reactions::default-allowed-emoji-list
 *   @e2e dashboard-reactions::user-in-a-group-cannot-react-if-group-permissions-revoked
 *   @e2e dashboard-reactions::user-with-full-permission-can-react
 *
 * (`user-cannot-react-to-a-dashboard-they-cannot-view` is the heading of both
 * a REQ-RXN-001 and a REQ-RXN-008 scenario; its one test asserts both.)
 *
 * @spec openspec/specs/dashboard-reactions/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	contextFor,
	createGroup,
	createPrincipal,
	publishForRecipients,
	removeGroup,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const RUN = Date.now()
const APPCONFIG = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad'
const KEY_ENABLED = 'reactions_enabled_default'
const KEY_ALLOWED = 'reactions_allowed_emojis'
const DEFAULT_ALLOWED = ['👍', '❤️', '🎉', '😂', '🤔', '😢']

type Summary = {
	counts: Record<string, number>
	mine: string[]
	enabled: boolean
	allowed?: string[]
}
type Reactor = { userId: string; displayName: string; reactedAt: string }
type ReactorPage = { items: Reactor[]; nextCursor: string | null; total: number }
type Dashboard = { id: number; uuid: string }

let admin: APIRequestContext
let owner: Principal | null = null
let viewer: Principal | null = null
let carol: Principal | null = null
let dave: Principal | null = null
let outsider: Principal | null = null
let allowBefore: boolean | null = null
const configBefore: Record<string, string | null> = {}
const seeded: Dashboard[] = []

/**
 * The launchpad app-config keys that are set.
 *
 * @return the keys.
 */
async function appKeys(): Promise<string[]> {
	const res = await admin.get(`${APPCONFIG}?format=json`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).ocs.data.data as string[]
}

/**
 * Write one launchpad app value.
 *
 * @param key the key.
 * @param value the value.
 * @return nothing.
 */
async function setAppValue(key: string, value: string): Promise<void> {
	const res = await admin.post(`${APPCONFIG}/${key}?format=json`, {
		form: { value },
	})
	expect(res.status(), `${key}=${value}: ${await res.text()}`).toBe(200)
}

/**
 * Remove one launchpad app value, so the product falls back to its default.
 *
 * @param key the key.
 * @return nothing.
 */
async function deleteAppValue(key: string): Promise<void> {
	const res = await admin.delete(`${APPCONFIG}/${key}?format=json`)
	expect(res.status(), `delete ${key}: ${await res.text()}`).toBe(200)
}

/**
 * Put one app value back the way beforeAll found it.
 *
 * @param key the key.
 * @return nothing.
 */
async function restoreAppValue(key: string): Promise<void> {
	const before = configBefore[key]
	if (before === undefined) {
		return
	}
	if (before === null) {
		await deleteAppValue(key)
	} else {
		await setAppValue(key, before)
	}
}

/**
 * Turn the global reactions toggle on or off.
 *
 * @param on the new state.
 * @return nothing.
 */
async function globalToggle(on: boolean): Promise<void> {
	await setAppValue(KEY_ENABLED, on ? '1' : '0')
}

/**
 * Set a user's display name.
 *
 * @param username the account.
 * @param name the display name.
 * @return nothing.
 */
async function setDisplayName(username: string, name: string): Promise<void> {
	const res = await admin.put(
		`/ocs/v2.php/cloud/users/${encodeURIComponent(username)}?format=json`,
		{ form: { key: 'displayname', value: name } },
	)
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * A fresh personal dashboard of the owner, shared view-only with viewer,
 * carol and dave, so each test reads counts nobody else touched.
 *
 * @param label a readable label.
 * @return the dashboard.
 */
async function freshDashboard(label: string): Promise<Dashboard> {
	const res = await owner!.api.post(`${API}/dashboard`, {
		data: { name: `E2E Reactions ${label} ${RUN}` },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const dash = (await res.json()).dashboard
	const made = { id: Number(dash.id), uuid: String(dash.uuid) }
	seeded.push(made)
	for (const sharee of [viewer!, carol!, dave!]) {
		await publishForRecipients(owner!.api, made.id)
		await shareWith(owner!.api, made.id, sharee.username, 'view_only')
	}
	return made
}

/**
 * POST a reaction.
 *
 * @param who the principal.
 * @param uuid the dashboard.
 * @param body the body (normally `{ emoji }`).
 * @return the status and body.
 */
async function react(
	who: Principal,
	uuid: string,
	body: Record<string, string>,
): Promise<{ status: number; body: Record<string, unknown>; date: string }> {
	const res = await who.api.post(`${API}/dashboards/${uuid}/reactions`, {
		data: body,
	})
	return {
		status: res.status(),
		body: await res.json().catch(() => ({})),
		date: res.headers().date,
	}
}

/**
 * POST a reaction and expect it accepted.
 *
 * @param who the principal.
 * @param uuid the dashboard.
 * @param emoji the emoji.
 * @return the summary answered.
 */
async function reactOk(
	who: Principal,
	uuid: string,
	emoji: string,
): Promise<Summary> {
	const res = await react(who, uuid, { emoji })
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	return res.body as unknown as Summary
}

/**
 * DELETE a reaction.
 *
 * @param who the principal.
 * @param uuid the dashboard.
 * @param emoji the emoji.
 * @return the status.
 */
async function unreact(
	who: Principal,
	uuid: string,
	emoji: string,
): Promise<number> {
	const res = await who.api.delete(
		`${API}/dashboards/${uuid}/reactions/${encodeURIComponent(emoji)}`,
	)
	return res.status()
}

/**
 * GET the reactions summary.
 *
 * @param who the principal.
 * @param uuid the dashboard.
 * @return the status and body.
 */
async function summary(
	who: Principal,
	uuid: string,
): Promise<{ status: number; body: Summary }> {
	const res = await who.api.get(`${API}/dashboards/${uuid}/reactions`)
	return { status: res.status(), body: await res.json().catch(() => ({})) }
}

/**
 * GET the summary and expect 200.
 *
 * @param who the principal.
 * @param uuid the dashboard.
 * @return the summary.
 */
async function summaryOk(who: Principal, uuid: string): Promise<Summary> {
	const res = await summary(who, uuid)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	return res.body
}

/**
 * The three fields the scenarios specify, with `mine` in a stable order.
 *
 * @param s a summary.
 * @return counts, mine (sorted) and enabled.
 */
function core(s: Summary): {
	counts: Record<string, number>
	mine: string[]
	enabled: boolean
} {
	return { counts: s.counts, mine: [...s.mine].sort(), enabled: s.enabled }
}

/**
 * GET the reactors of one emoji.
 *
 * @param who the principal.
 * @param uuid the dashboard.
 * @param emoji the emoji.
 * @param cursor an optional cursor.
 * @return the status and page.
 */
async function reactors(
	who: Principal,
	uuid: string,
	emoji: string,
	cursor?: string,
): Promise<{ status: number; body: ReactorPage }> {
	const query = cursor === undefined ? '' : `?cursor=${encodeURIComponent(cursor)}`
	const res = await who.api.get(
		`${API}/dashboards/${uuid}/reactions/${encodeURIComponent(emoji)}/users${query}`,
	)
	return { status: res.status(), body: await res.json().catch(() => ({})) }
}

/**
 * The `reactionsEnabled` column of a dashboard, as its owner reads it.
 *
 * @param id the dashboard id.
 * @return the value.
 */
async function reactionsColumn(id: number): Promise<unknown> {
	const res = await owner!.api.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).dashboard.reactionsEnabled
}

/**
 * Sort reactions in a stable order, for comparing sets.
 *
 * @param list emoji or ids.
 * @return a sorted copy.
 */
function sorted(list: string[]): string[] {
	return [...list].sort()
}

/**
 * Run an async step over items, at most `width` at a time.
 *
 * @param items the items.
 * @param width the batch size.
 * @param step the step.
 * @return nothing.
 */
async function inBatches<T>(
	items: T[],
	width: number,
	step: (item: T) => Promise<void>,
): Promise<void> {
	for (let i = 0; i < items.length; i += width) {
		await Promise.all(items.slice(i, i + width).map(step))
	}
}

test.beforeAll(async () => {
	admin = await adminContext()

	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status(), await settings.text()).toBe(200)
	allowBefore = (await settings.json()).allowUserDashboards === true
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)

	const keys = await appKeys()
	for (const key of [KEY_ENABLED, KEY_ALLOWED]) {
		if (keys.includes(key)) {
			const res = await admin.get(`${APPCONFIG}/${key}?format=json`)
			expect(res.status(), await res.text()).toBe(200)
			configBefore[key] = String((await res.json()).ocs.data.data)
		} else {
			configBefore[key] = null
		}
	}
	await globalToggle(true)
	if (configBefore[KEY_ALLOWED] !== null) {
		await deleteAppValue(KEY_ALLOWED)
	}

	owner = await createPrincipal(admin, 'e2e-rxn-owner')
	viewer = await createPrincipal(admin, 'e2e-rxn-viewer')
	carol = await createPrincipal(admin, 'e2e-rxn-carol')
	dave = await createPrincipal(admin, 'e2e-rxn-dave')
	outsider = await createPrincipal(admin, 'e2e-rxn-outsider')
	for (const who of [owner, viewer, carol, dave, outsider]) {
		await assertActingAs(who.api, who.username)
	}
	await setDisplayName(owner.username, 'Alice Smith')
	await setDisplayName(viewer.username, 'Bob Jones')
	await setDisplayName(carol.username, 'Carol Brown')
})

test.afterAll(async () => {
	for (const dash of seeded) {
		await owner?.api.delete(`${API}/dashboard/${dash.id}`)
	}
	for (const who of [owner, viewer, carol, dave, outsider]) {
		await removePrincipal(admin, who)
	}
	await restoreAppValue(KEY_ENABLED)
	await restoreAppValue(KEY_ALLOWED)
	if (allowBefore !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: allowBefore },
		})
	}
	await admin.dispose()
})

test.describe('REQ-RXN-001 add reaction', () => {
	test('a viewer adds 👍 and gets the summary back', async () => {
		// @e2e dashboard-reactions::user-adds-a-reaction-to-a-dashboard-they-can-view
		const dash = await freshDashboard('Add')

		const res = await react(viewer!, dash.uuid, { emoji: '👍' })
		expect(res.status, JSON.stringify(res.body)).toBe(200)
		expect(core(res.body as unknown as Summary)).toEqual({
			counts: { '👍': 1 },
			mine: ['👍'],
			enabled: true,
		})

		// The record: this dashboard, this user, this emoji, stamped now.
		const page = await reactors(owner!, dash.uuid, '👍')
		expect(page.status).toBe(200)
		expect(page.body.total).toBe(1)
		expect(page.body.items).toHaveLength(1)
		expect(page.body.items[0]).toMatchObject({
			userId: viewer!.username,
			displayName: 'Bob Jones',
		})
		const stamped = Date.parse(
			`${page.body.items[0].reactedAt.replace(' ', 'T')}Z`,
		)
		expect(Math.abs(Date.parse(res.date) - stamped)).toBeLessThan(120_000)
	})

	test('an account without view access cannot react with any body', async () => {
		// @e2e dashboard-reactions::user-cannot-react-to-a-dashboard-they-cannot-view
		const dash = await freshDashboard('No view')

		const heart = await react(outsider!, dash.uuid, { emoji: '❤️' })
		expect(heart.status, JSON.stringify(heart.body)).toBe(403)
		// REQ-RXN-008: "with any body" — an empty one is refused the same way.
		const empty = await react(outsider!, dash.uuid, {})
		expect(empty.status, JSON.stringify(empty.body)).toBe(403)
		const thumbs = await react(outsider!, dash.uuid, { emoji: '👍' })
		expect(thumbs.status, JSON.stringify(thumbs.body)).toBe(403)

		expect(core(await summaryOk(owner!, dash.uuid))).toEqual({
			counts: {},
			mine: [],
			enabled: true,
		})

		// A viewer of the same dashboard is let through.
		expect((await reactOk(viewer!, dash.uuid, '❤️')).counts).toEqual({ '❤️': 1 })
	})

	test('🚀 is not on the allowed list and is refused with 400', async () => {
		// @e2e dashboard-reactions::user-attempts-to-react-with-non-whitelisted-emoji
		const dash = await freshDashboard('Rocket')
		expect((await summaryOk(viewer!, dash.uuid)).allowed).toEqual(
			DEFAULT_ALLOWED,
		)

		const res = await react(viewer!, dash.uuid, { emoji: '🚀' })
		expect(res.status, JSON.stringify(res.body)).toBe(400)
		expect(res.body.error).toBe('Emoji not allowed')
		expect((await summaryOk(owner!, dash.uuid)).counts).toEqual({})
		expect((await reactors(owner!, dash.uuid, '🚀')).body.total).toBe(0)

		expect((await reactOk(viewer!, dash.uuid, '👍')).counts).toEqual({ '👍': 1 })
	})

	test('posting 👍 twice keeps one reaction', async () => {
		// @e2e dashboard-reactions::user-re-posts-the-same-emoji-idempotent
		const dash = await freshDashboard('Twice')
		const first = await reactOk(viewer!, dash.uuid, '👍')

		const second = await react(viewer!, dash.uuid, { emoji: '👍' })
		expect(second.status, JSON.stringify(second.body)).toBe(200)
		expect(second.body).toEqual(first)
		expect((second.body as unknown as Summary).counts).toEqual({ '👍': 1 })

		expect((await summaryOk(owner!, dash.uuid)).counts).toEqual({ '👍': 1 })
		expect((await reactors(owner!, dash.uuid, '👍')).body.total).toBe(1)
	})

	test('👍 then ❤️ gives two reactions of the same user', async () => {
		// @e2e dashboard-reactions::user-can-react-with-multiple-distinct-emojis-to-the-same-dashboard
		const dash = await freshDashboard('Two emoji')
		await reactOk(viewer!, dash.uuid, '👍')

		const res = await reactOk(viewer!, dash.uuid, '❤️')
		expect(core(res)).toEqual({
			counts: { '👍': 1, '❤️': 1 },
			mine: sorted(['👍', '❤️']),
			enabled: true,
		})
		const hearts = await reactors(owner!, dash.uuid, '❤️')
		expect(hearts.body.items.map((r) => r.userId)).toEqual([viewer!.username])
	})
})

test.describe('REQ-RXN-002 remove reaction', () => {
	test("DELETE 👍 removes the viewer's reaction with 204", async () => {
		// @e2e dashboard-reactions::user-removes-a-reaction-they-made
		const dash = await freshDashboard('Remove')
		await reactOk(viewer!, dash.uuid, '👍')

		expect(await unreact(viewer!, dash.uuid, '👍')).toBe(204)
		expect(core(await summaryOk(viewer!, dash.uuid))).toEqual({
			counts: {},
			mine: [],
			enabled: true,
		})
		expect((await reactors(owner!, dash.uuid, '👍')).body.total).toBe(0)
	})

	test('DELETE of a reaction never made is 204 and changes nothing', async () => {
		// @e2e dashboard-reactions::user-attempts-to-remove-a-reaction-they-did-not-make
		const dash = await freshDashboard('Remove none')
		await reactOk(owner!, dash.uuid, '👍')

		// 204 and not 500: the controller logs only on its failure path
		// (DashboardReactionApiController::reactionFailure), which answers 500,
		// so a 204 is the proof that no database error was logged.
		expect(await unreact(viewer!, dash.uuid, '👍')).toBe(204)
		expect(core(await summaryOk(owner!, dash.uuid))).toEqual({
			counts: { '👍': 1 },
			mine: ['👍'],
			enabled: true,
		})
		expect((await summaryOk(viewer!, dash.uuid)).mine).toEqual([])
	})

	test("another user's DELETE leaves the owner's ❤️ in place", async () => {
		// @e2e dashboard-reactions::user-cannot-remove-another-users-reaction
		const dash = await freshDashboard('Remove other')
		await reactOk(owner!, dash.uuid, '❤️')

		expect(await unreact(viewer!, dash.uuid, '❤️')).toBe(204)
		expect(core(await summaryOk(owner!, dash.uuid))).toEqual({
			counts: { '❤️': 1 },
			mine: ['❤️'],
			enabled: true,
		})
		const hearts = await reactors(owner!, dash.uuid, '❤️')
		expect(hearts.body.items.map((r) => r.userId)).toEqual([owner!.username])
	})
})

test.describe('REQ-RXN-003 reactions summary', () => {
	test("counts aggregate everyone; mine is only the caller's", async () => {
		// @e2e dashboard-reactions::user-retrieves-reactions-on-a-dashboard-they-can-view
		const dash = await freshDashboard('Summary')
		await reactOk(owner!, dash.uuid, '👍')
		await reactOk(owner!, dash.uuid, '🎉')
		await reactOk(viewer!, dash.uuid, '👍')
		await reactOk(viewer!, dash.uuid, '❤️')
		await reactOk(carol!, dash.uuid, '👍')
		await reactOk(carol!, dash.uuid, '🎉')

		const res = await summary(owner!, dash.uuid)
		expect(res.status).toBe(200)
		expect(core(res.body)).toEqual({
			counts: { '👍': 3, '❤️': 1, '🎉': 2 },
			mine: sorted(['👍', '🎉']),
			enabled: true,
		})
	})

	test('an account without view access cannot read the summary', async () => {
		// @e2e dashboard-reactions::user-cannot-view-reactions-if-they-cannot-view-the-dashboard
		const dash = await freshDashboard('Summary refused')
		await reactOk(viewer!, dash.uuid, '👍')

		const refused = await summary(outsider!, dash.uuid)
		expect(refused.status, JSON.stringify(refused.body)).toBe(403)
		expect(refused.body).not.toHaveProperty('counts')

		expect((await summaryOk(owner!, dash.uuid)).counts).toEqual({ '👍': 1 })
	})

	test('a dashboard nobody reacted to answers empty counts', async () => {
		// @e2e dashboard-reactions::no-reactions-yet-on-a-dashboard
		const dash = await freshDashboard('Empty')

		const res = await summary(viewer!, dash.uuid)
		expect(res.status).toBe(200)
		expect(core(res.body)).toEqual({ counts: {}, mine: [], enabled: true })
	})
})

test.describe('REQ-RXN-004 reactors by emoji', () => {
	test('dave lists who reacted with 👍, oldest first', async () => {
		// @e2e dashboard-reactions::user-retrieves-reactors-for-a-specific-emoji
		const dash = await freshDashboard('Reactors')
		await reactOk(owner!, dash.uuid, '👍')
		await reactOk(viewer!, dash.uuid, '👍')
		await reactOk(carol!, dash.uuid, '👍')

		const res = await reactors(dave!, dash.uuid, '👍')
		expect(res.status).toBe(200)
		expect(res.body.nextCursor).toBeNull()
		expect(res.body.total).toBe(3)
		expect(
			res.body.items.map(({ userId, displayName }) => ({
				userId,
				displayName,
			})),
		).toEqual([
			{ userId: owner!.username, displayName: 'Alice Smith' },
			{ userId: viewer!.username, displayName: 'Bob Jones' },
			{ userId: carol!.username, displayName: 'Carol Brown' },
		])
		const stamps = res.body.items.map((r) => r.reactedAt)
		for (const stamp of stamps) {
			expect(stamp).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/)
		}
		expect([...stamps].sort()).toEqual(stamps)
	})

	test('150 reactors come back as 100, then 50 after cursor=100', async () => {
		// @e2e dashboard-reactions::pagination-with-cursor
		test.setTimeout(600_000)
		const group = await createGroup(admin, `e2e-rxn-page-${RUN}`)
		const members: { username: string; password: string }[] = []
		let groupDash: string | null = null
		try {
			await addToGroup(admin, viewer!.username, group)
			for (let i = 0; i < 150; i++) {
				members.push({
					username: `e2e-rxn-p${i}-${RUN}`,
					password: `Probe-${Math.random().toString(36).slice(2)}A1!`,
				})
			}
			await inBatches(members, 10, async (m) => {
				const res = await admin.post('/ocs/v1.php/cloud/users?format=json', {
					data: {
						userid: m.username,
						password: m.password,
						groups: [group],
					},
				})
				// OCS v1 answers HTTP 200 either way; the verdict is in the meta.
				expect(res.status(), await res.text()).toBe(200)
				expect((await res.json()).ocs.meta.statuscode, m.username).toBe(100)
			})
			const made = await admin.post(`${API}/dashboards/group/${group}`, {
				data: { name: `E2E Reactions page ${RUN}` },
			})
			expect(made.status(), await made.text()).toBe(201)
			groupDash = String((await made.json()).dashboard.uuid)
			const uuid = groupDash

			await inBatches(members, 10, async (m) => {
				const api = await contextFor(m.username, m.password)
				try {
					const res = await api.post(
						`${API}/dashboards/${uuid}/reactions`,
						{
							data: { emoji: '🎉' },
						},
					)
					expect(res.status(), await res.text()).toBe(200)
				} finally {
					await api.dispose()
				}
			})

			const first = await reactors(viewer!, uuid, '🎉')
			expect(first.status).toBe(200)
			expect(first.body.items).toHaveLength(100)
			expect(first.body.total).toBe(150)
			expect(first.body.nextCursor).toBe('100')

			const second = await reactors(viewer!, uuid, '🎉', '100')
			expect(second.status).toBe(200)
			expect(second.body.items).toHaveLength(50)
			expect(second.body.total).toBe(150)
			expect(second.body.nextCursor).toBeNull()

			// Rows 100..149 are exactly the ones the first page did not carry.
			const ids = [...first.body.items, ...second.body.items].map(
				(r) => r.userId,
			)
			expect(new Set(ids).size).toBe(150)
			expect(sorted(ids)).toEqual(sorted(members.map((m) => m.username)))
		} finally {
			if (groupDash !== null) {
				await admin.delete(`${API}/dashboards/group/${group}/${groupDash}`)
			}
			await inBatches(members, 10, async (m) => {
				await admin.delete(
					`/ocs/v1.php/cloud/users/${encodeURIComponent(m.username)}`,
				)
			})
			await removeGroup(admin, group)
		}
	})

	test('an emoji nobody used has no reactors', async () => {
		// @e2e dashboard-reactions::emoji-with-no-reactors
		const dash = await freshDashboard('No reactors')
		await reactOk(owner!, dash.uuid, '👍')

		const res = await reactors(viewer!, dash.uuid, '😢')
		expect(res.status).toBe(200)
		expect(res.body).toEqual({ items: [], nextCursor: null, total: 0 })
	})

	test('an account without view access cannot list reactors', async () => {
		// @e2e dashboard-reactions::user-cannot-view-reactors-if-they-cannot-view-the-dashboard
		const dash = await freshDashboard('Reactors refused')
		await reactOk(viewer!, dash.uuid, '👍')

		const refused = await reactors(outsider!, dash.uuid, '👍')
		expect(refused.status, JSON.stringify(refused.body)).toBe(403)
		expect(refused.body).not.toHaveProperty('items')

		expect((await reactors(dave!, dash.uuid, '👍')).body.total).toBe(1)
	})
})

test.describe('REQ-RXN-005 global toggle', () => {
	test('with the toggle off, the summary is disabled and POST is 403', async () => {
		// @e2e dashboard-reactions::admin-disables-reactions-globally
		const dash = await freshDashboard('Global off')
		await reactOk(viewer!, dash.uuid, '👍')

		await globalToggle(false)
		try {
			expect(core(await summaryOk(owner!, dash.uuid))).toEqual({
				counts: {},
				mine: [],
				enabled: false,
			})
			const refused = await react(viewer!, dash.uuid, { emoji: '❤️' })
			expect(refused.status, JSON.stringify(refused.body)).toBe(403)
			expect(refused.body.error).toBe('Reactions are disabled')
		} finally {
			await globalToggle(true)
		}
		// Nothing was written while it was off; the earlier 👍 was only hidden.
		expect((await summaryOk(owner!, dash.uuid)).counts).toEqual({ '👍': 1 })
	})

	test('with the toggle back on, POST creates the reaction', async () => {
		// @e2e dashboard-reactions::admin-re-enables-reactions-globally
		const dash = await freshDashboard('Global on')
		await reactOk(owner!, dash.uuid, '❤️')
		await globalToggle(false)
		await globalToggle(true)

		const res = await react(viewer!, dash.uuid, { emoji: '👍' })
		expect(res.status, JSON.stringify(res.body)).toBe(200)
		expect((res.body as unknown as Summary).mine).toEqual(['👍'])
		// The reaction made before the toggle went off is still there.
		expect((await summaryOk(owner!, dash.uuid)).counts).toEqual({
			'❤️': 1,
			'👍': 1,
		})
	})

	test('a dashboard made while on follows the toggle when it goes off', async () => {
		// @e2e dashboard-reactions::dashboards-created-before-global-toggle-change-inherit-the-toggle-state
		const dash = await freshDashboard('Global old')
		expect(await reactionsColumn(dash.id)).toBeNull()
		expect((await summaryOk(viewer!, dash.uuid)).enabled).toBe(true)

		await globalToggle(false)
		try {
			expect((await summaryOk(viewer!, dash.uuid)).enabled).toBe(false)
		} finally {
			await globalToggle(true)
		}
	})
})

test.describe('REQ-RXN-006 per-dashboard toggle', () => {
	test('a dashboard with reactionsEnabled NULL follows the global setting', async () => {
		// @e2e dashboard-reactions::null-reactionsenabled-field-follows-global-setting
		const dash = await freshDashboard('Null')
		expect(await reactionsColumn(dash.id)).toBeNull()

		const res = await summary(carol!, dash.uuid)
		expect(res.status).toBe(200)
		expect(res.body.enabled).toBe(true)
	})
})

test.describe('REQ-RXN-007 allowed emoji list', () => {
	test('😂 is refused once the list is 👍 ❤️ 🎉', async () => {
		// @e2e dashboard-reactions::admin-updates-the-allowed-emoji-list
		const dash = await freshDashboard('Allowed')
		await setAppValue(KEY_ALLOWED, JSON.stringify(['👍', '❤️', '🎉']))
		try {
			expect((await summaryOk(viewer!, dash.uuid)).allowed).toEqual([
				'👍',
				'❤️',
				'🎉',
			])
			const refused = await react(viewer!, dash.uuid, { emoji: '😂' })
			expect(refused.status, JSON.stringify(refused.body)).toBe(400)
			expect(refused.body.error).toBe('Emoji not allowed')

			expect((await reactOk(viewer!, dash.uuid, '🎉')).counts).toEqual({
				'🎉': 1,
			})
			expect((await summaryOk(owner!, dash.uuid)).counts).toEqual({ '🎉': 1 })
		} finally {
			await deleteAppValue(KEY_ALLOWED)
		}
	})

	test('an empty list refuses every emoji but the summary still reads', async () => {
		// @e2e dashboard-reactions::empty-emoji-in-whitelist
		const dash = await freshDashboard('Allowed empty')
		await reactOk(owner!, dash.uuid, '❤️')
		await setAppValue(KEY_ALLOWED, '[]')
		try {
			const refused = await react(viewer!, dash.uuid, { emoji: '👍' })
			expect(refused.status, JSON.stringify(refused.body)).toBe(400)
			expect(refused.body.error).toBe('Emoji not allowed')

			const res = await summary(viewer!, dash.uuid)
			expect(res.status).toBe(200)
			expect(core(res.body)).toEqual({
				counts: { '❤️': 1 },
				mine: [],
				enabled: true,
			})
			expect(res.body.allowed).toEqual([])
		} finally {
			await deleteAppValue(KEY_ALLOWED)
		}
	})

	test('without an admin list, 👍 is accepted and the default six apply', async () => {
		// @e2e dashboard-reactions::default-allowed-emoji-list
		expect(await appKeys()).not.toContain(KEY_ALLOWED)
		const dash = await freshDashboard('Allowed default')

		const res = await reactOk(viewer!, dash.uuid, '👍')
		expect(res.counts).toEqual({ '👍': 1 })
		expect(res.allowed).toEqual(DEFAULT_ALLOWED)
		expect(
			(await reactors(owner!, dash.uuid, '👍')).body.items.map(
				(r) => r.userId,
			),
		).toEqual([viewer!.username])
	})
})

test.describe('REQ-RXN-008 permission enforcement', () => {
	test('leaving the group takes away the right to react', async () => {
		// @e2e dashboard-reactions::user-in-a-group-cannot-react-if-group-permissions-revoked
		const group = await createGroup(admin, `e2e-rxn-group-${RUN}`)
		const member = await createPrincipal(admin, 'e2e-rxn-member')
		let uuid: string | null = null
		try {
			await assertActingAs(member.api, member.username)
			await addToGroup(admin, member.username, group)
			const made = await admin.post(`${API}/dashboards/group/${group}`, {
				data: { name: `E2E Reactions group ${RUN}` },
			})
			expect(made.status(), await made.text()).toBe(201)
			uuid = String((await made.json()).dashboard.uuid)

			// While in the group, bob can react.
			expect((await reactOk(member, uuid, '👍')).counts).toEqual({ '👍': 1 })

			const left = await admin.delete(
				`/ocs/v1.php/cloud/users/${encodeURIComponent(member.username)}/groups?format=json&groupid=${encodeURIComponent(group)}`,
			)
			expect(left.status(), await left.text()).toBe(200)
			expect((await left.json()).ocs.meta.statuscode).toBe(100)

			const refused = await react(member, uuid, { emoji: '❤️' })
			expect(refused.status, JSON.stringify(refused.body)).toBe(403)
			const adminView = await admin.get(`${API}/dashboards/${uuid}/reactions`)
			expect(adminView.status(), await adminView.text()).toBe(200)
			expect((await adminView.json()).counts).toEqual({ '👍': 1 })
		} finally {
			if (uuid !== null) {
				await admin.delete(`${API}/dashboards/group/${group}/${uuid}`)
			}
			await removePrincipal(admin, member)
			await removeGroup(admin, group)
		}
	})

	test('the owner of a personal dashboard can react', async () => {
		// @e2e dashboard-reactions::user-with-full-permission-can-react
		const dash = await freshDashboard('Owner')
		const own = await owner!.api.get(`${API}/dashboard/${dash.id}`)
		expect(own.status(), await own.text()).toBe(200)
		expect((await own.json()).permissionLevel).toBe('full')

		const res = await react(owner!, dash.uuid, { emoji: '🎉' })
		expect(res.status, JSON.stringify(res.body)).toBe(200)
		expect(core(res.body as unknown as Summary)).toEqual({
			counts: { '🎉': 1 },
			mine: ['🎉'],
			enabled: true,
		})
		expect(
			(await reactors(viewer!, dash.uuid, '🎉')).body.items.map(
				(r) => r.userId,
			),
		).toEqual([owner!.username])
	})
})
