/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the dashboard and widget quotas
 * (openspec/specs/dashboard-quota-limits), written for e2e-coverage-programme
 * task 2.8.
 *
 * The quotas are admin settings (`PUT /api/admin/settings`), so every
 * scenario sets them over the admin API, acts as a throwaway ordinary account
 * ("alice") and asserts the exact 409 body, the quota envelope on
 * `GET /api/dashboards`, or what the dashboard switcher shows.
 *
 * QUOTAS ARE INSTANCE-WIDE. The product has no per-user or per-group scope
 * for them, so a limit set here binds every account on the shared instance
 * while it is set. Therefore: the settings this file touches are read in
 * beforeAll and written back in afterAll; every test that sets a limit puts
 * the quotas back to 0 (unlimited) in its own `finally`; and every account
 * that is measured against a limit is a fresh throwaway, so the counts are
 * exactly the dashboards this file made.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-quota-limits::defaults-preserve-unlimited-behaviour
 *   @e2e dashboard-quota-limits::admin-sets-quota-values
 *   @e2e dashboard-quota-limits::quota-values-are-validated-and-clamped
 *   @e2e dashboard-quota-limits::non-admin-cannot-change-quotas
 *   @e2e dashboard-quota-limits::creation-blocked-at-the-limit
 *   @e2e dashboard-quota-limits::creation-allowed-below-the-limit
 *   @e2e dashboard-quota-limits::deleting-a-dashboard-frees-quota-immediately
 *   @e2e dashboard-quota-limits::boolean-flag-interaction-is-most-restrictive-wins
 *   @e2e dashboard-quota-limits::group-scope-dashboards-do-not-count-against-the-user
 *   @e2e dashboard-quota-limits::adding-a-widget-at-the-limit
 *   @e2e dashboard-quota-limits::adding-a-widget-below-the-limit
 *   @e2e dashboard-quota-limits::removing-a-placement-frees-quota-immediately
 *   @e2e dashboard-quota-limits::admins-are-bound-by-quotas-for-their-own-personal-dashboards
 *   @e2e dashboard-quota-limits::lowering-the-dashboard-limit-below-usage
 *   @e2e dashboard-quota-limits::converging-back-under-the-limit
 *   @e2e dashboard-quota-limits::raising-a-limit-back-to-zero-restores-unlimited
 *   @e2e dashboard-quota-limits::list-response-carries-quota-status
 *   @e2e dashboard-quota-limits::create-button-disabled-at-the-limit
 *   @e2e dashboard-quota-limits::unlimited-instances-show-no-quota-ui
 *   @e2e dashboard-quota-limits::race-between-ui-state-and-server-enforcement
 *
 * @spec openspec/specs/dashboard-quota-limits/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const RUN = Date.now()
const SETTINGS = `${API}/admin/settings`
const ADD_BUTTON = '[data-testid="add-dashboard-button"]'
const QUOTA_HINT = '[data-testid="dashboard-quota-hint"]'

interface Settings {
	maxDashboardsPerUser: number
	maxWidgetsPerDashboard: number
	allowUserDashboards: boolean
	allowMultipleDashboards: boolean
}

interface Dash {
	id: number
	uuid: string
}

let admin: APIRequestContext
let previous: Settings | null = null
const principals: Principal[] = []

/**
 * Read the admin settings.
 *
 * @return the quota-related settings.
 */
async function readSettings(): Promise<Settings> {
	const res = await admin.get(SETTINGS)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Write settings as the admin and insist on 200.
 *
 * @param data the partial settings.
 * @return nothing.
 */
async function writeSettings(data: Record<string, unknown>): Promise<void> {
	const res = await admin.put(SETTINGS, { data })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Set the two quotas; personal dashboards stay allowed and multiple.
 *
 * @param maxDashboardsPerUser the dashboard quota.
 * @param maxWidgetsPerDashboard the widget quota.
 * @return nothing.
 */
async function setQuotas(
	maxDashboardsPerUser: number,
	maxWidgetsPerDashboard: number,
): Promise<void> {
	await writeSettings({
		maxDashboardsPerUser,
		maxWidgetsPerDashboard,
		allowUserDash: true,
		allowMultiDash: true,
	})
}

/** Back to unlimited, as every test leaves the instance. */
async function unlimited(): Promise<void> {
	await setQuotas(0, 0)
}

/**
 * A fresh ordinary account, removed in afterAll.
 *
 * @param prefix readable prefix.
 * @return the principal.
 */
async function freshUser(prefix: string): Promise<Principal> {
	const who = await createPrincipal(admin, prefix)
	principals.push(who)
	await assertActingAs(who.api, who.username)
	return who
}

/**
 * POST a personal dashboard as `ctx`, returning the raw response.
 *
 * @param ctx who creates.
 * @param name the name.
 * @return the response.
 */
function postDashboard(ctx: APIRequestContext, name: string) {
	return ctx.post(`${API}/dashboard`, { data: { name } })
}

/**
 * Create a personal dashboard and insist it was created.
 *
 * @param ctx who creates.
 * @param name the name.
 * @return the dashboard.
 */
async function createDashboard(ctx: APIRequestContext, name: string): Promise<Dash> {
	const res = await postDashboard(ctx, name)
	expect(res.status(), await res.text()).toBe(201)
	const d = (await res.json()).dashboard
	return { id: Number(d.id), uuid: String(d.uuid) }
}

/**
 * Create `count` personal dashboards.
 *
 * @param ctx who creates.
 * @param count how many.
 * @param label a name prefix.
 * @return the dashboards.
 */
async function createDashboards(
	ctx: APIRequestContext,
	count: number,
	label: string,
): Promise<Dash[]> {
	const out: Dash[] = []
	for (let i = 0; i < count; i++) {
		out.push(await createDashboard(ctx, `${label} ${i + 1} ${RUN}`))
	}
	return out
}

/**
 * The caller's personal dashboards list, in the envelope shape.
 *
 * @param ctx who lists.
 * @return the items and the quota envelope.
 */
async function listMine(ctx: APIRequestContext): Promise<{
	items: Array<{ id: number; uuid: string }>
	quota: Record<string, number>
}> {
	const res = await ctx.get(`${API}/dashboards`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	expect(Array.isArray(body), 'the list must be the {items, quota} envelope').toBe(
		false,
	)
	return body
}

/**
 * Add one text widget, returning the raw response.
 *
 * @param ctx who adds.
 * @param dashboardId the dashboard.
 * @return the response.
 */
function postWidget(ctx: APIRequestContext, dashboardId: number) {
	return ctx.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridWidth: 2,
			gridHeight: 1,
			content: { text: 'quota', contentMode: 'markdown' },
		},
	})
}

/**
 * Add one tile, returning the raw response.
 *
 * @param ctx who adds.
 * @param dashboardId the dashboard.
 * @return the response.
 */
function postTile(ctx: APIRequestContext, dashboardId: number) {
	return ctx.post(`${API}/dashboard/${dashboardId}/tile`, {
		data: {
			title: `Quota tile ${RUN}`,
			linkType: 'url',
			linkValue: 'https://example.invalid/quota',
			gridWidth: 2,
			gridHeight: 1,
		},
	})
}

/**
 * The placements on a dashboard, as its owner reads them.
 *
 * @param ctx the owner.
 * @param id the dashboard.
 * @return the placements.
 */
async function placements(
	ctx: APIRequestContext,
	id: number,
): Promise<Array<{ id: number }>> {
	const res = await ctx.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).placements ?? []
}

/**
 * Bring a dashboard to exactly `target` placements: surplus ones (a new
 * dashboard carries the default bundle) are removed, missing ones are
 * added as text widgets.
 *
 * @param ctx the owner.
 * @param id the dashboard.
 * @param target the placement count wanted.
 * @return nothing.
 */
async function fillTo(
	ctx: APIRequestContext,
	id: number,
	target: number,
): Promise<void> {
	const existing = await placements(ctx, id)
	for (const surplus of existing.slice(target)) {
		const res = await ctx.delete(`${API}/widgets/${surplus.id}`)
		expect(res.status(), await res.text()).toBeLessThan(300)
	}
	for (let i = Math.min(existing.length, target); i < target; i++) {
		const res = await postWidget(ctx, id)
		expect(res.status(), await res.text()).toBe(201)
	}
	expect((await placements(ctx, id)).length).toBe(target)
}

/**
 * The exact 409 body the spec names.
 *
 * @param res the response.
 * @param quota `dashboards` or `widgets`.
 * @param limit the limit.
 * @param current the count.
 * @return nothing.
 */
async function expectQuotaExceeded(
	res: { status(): number; json(): Promise<unknown>; text(): Promise<string> },
	quota: string,
	limit: number,
	current: number,
): Promise<void> {
	expect(res.status(), await res.text()).toBe(409)
	expect(await res.json()).toEqual({
		error: 'quota_exceeded',
		quota,
		limit,
		current,
	})
}

/**
 * Log a principal in, open LaunchPad and the dashboard switcher.
 *
 * @param browser the browser fixture.
 * @param who the account.
 * @return the page; close its context when done.
 */
async function switcherAs(browser: Browser, who: Principal): Promise<Page> {
	const { page } = await loginAs(browser, who.username, who.password)
	await page.goto('/index.php/apps/launchpad')
	await openDashboardSwitcher(page)
	return page
}

test.describe.configure({ mode: 'serial' })
// A fresh account's first request builds its home, and several tests make
// forty placements; the default budget is a UI budget.
test.setTimeout(240_000)

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	const s = await readSettings()
	previous = {
		maxDashboardsPerUser: s.maxDashboardsPerUser,
		maxWidgetsPerDashboard: s.maxWidgetsPerDashboard,
		allowUserDashboards: s.allowUserDashboards,
		allowMultipleDashboards: s.allowMultipleDashboards,
	}
	await unlimited()
})

test.afterAll(async () => {
	if (previous !== null) {
		await admin.put(SETTINGS, {
			data: {
				maxDashboardsPerUser: previous.maxDashboardsPerUser,
				maxWidgetsPerDashboard: previous.maxWidgetsPerDashboard,
				allowUserDash: previous.allowUserDashboards,
				allowMultiDash: previous.allowMultipleDashboards,
			},
		})
	}
	// Deleting an account removes its personal dashboards with it.
	for (const who of principals) {
		await removePrincipal(admin, who)
	}
	await admin.dispose()
})

test.describe('quota settings (REQ-QUOTA-001)', () => {
	// @e2e dashboard-quota-limits::defaults-preserve-unlimited-behaviour
	test('at 0 the settings read 0 and nothing is limited', async () => {
		// A shared instance cannot promise the keys were never written; 0 is
		// the value a never-set key reads as, and the state this asserts.
		await unlimited()
		const s = await readSettings()
		expect(s.maxDashboardsPerUser).toBe(0)
		expect(s.maxWidgetsPerDashboard).toBe(0)

		const alice = await freshUser('quota-default')
		const dashes = await createDashboards(alice.api, 6, 'Unlimited')
		const listed = await listMine(alice.api)
		expect(listed.items).toHaveLength(6)
		expect(listed.quota.maxDashboards).toBe(0)
		expect(listed.quota.maxWidgetsPerDashboard).toBe(0)

		const start = (await placements(alice.api, dashes[0].id)).length
		for (let i = 0; i < 12; i++) {
			const res = await postWidget(alice.api, dashes[0].id)
			expect(res.status(), await res.text()).toBe(201)
		}
		expect((await placements(alice.api, dashes[0].id)).length).toBe(start + 12)
	})

	// @e2e dashboard-quota-limits::admin-sets-quota-values
	test('the admin sets both quotas and a fresh read returns them in camelCase', async () => {
		try {
			const res = await admin.put(SETTINGS, {
				data: { maxDashboardsPerUser: 5, maxWidgetsPerDashboard: 40 },
			})
			expect(res.status(), await res.text()).toBe(200)

			// A new context, so nothing is served from the writer's session.
			const reader = await adminContext()
			try {
				const body = await (await reader.get(SETTINGS)).json()
				expect(body.maxDashboardsPerUser).toBe(5)
				expect(body.maxWidgetsPerDashboard).toBe(40)
				expect(body).not.toHaveProperty('max_dashboards_per_user')
			} finally {
				await reader.dispose()
			}
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::quota-values-are-validated-and-clamped
	test('negative, fractional, non-numeric and huge values never persist as such', async () => {
		try {
			const cases: Array<[unknown, number]> = [
				[-3, 0],
				[2.5, 2],
				['abc', 0],
				[20_000, 10_000],
			]
			for (const [sent, clamped] of cases) {
				await setQuotas(7, 7)
				const res = await admin.put(SETTINGS, {
					data: { maxDashboardsPerUser: sent },
				})
				// Rejecting and clamping are both allowed; failing is not.
				expect([200, 400], `PUT ${JSON.stringify(sent)}`).toContain(
					res.status(),
				)
				const stored = (await readSettings()).maxDashboardsPerUser
				expect(Number.isInteger(stored), `stored ${stored}`).toBe(true)
				expect(stored).toBeGreaterThanOrEqual(0)
				expect(stored).toBeLessThanOrEqual(10_000)
				// Rejected leaves the old 7; clamped stores the clamp.
				expect([7, clamped], `after ${JSON.stringify(sent)}`).toContain(
					stored,
				)
			}
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::non-admin-cannot-change-quotas
	test('an ordinary account is refused exactly as for any other setting', async () => {
		const alice = await freshUser('quota-nonadmin')
		const quota = await alice.api.put(SETTINGS, {
			data: { maxDashboardsPerUser: 1, maxWidgetsPerDashboard: 1 },
		})
		const other = await alice.api.put(SETTINGS, {
			data: { allowUserDash: false },
		})
		expect(quota.status()).toBe(403)
		expect(other.status()).toBe(quota.status())

		const s = await readSettings()
		expect(s.maxDashboardsPerUser).toBe(0)
		expect(s.maxWidgetsPerDashboard).toBe(0)
		expect(s.allowUserDashboards).toBe(true)

		// The admin's identical request goes through.
		try {
			await writeSettings({
				maxDashboardsPerUser: 1,
				maxWidgetsPerDashboard: 1,
			})
			const after = await readSettings()
			expect(after.maxDashboardsPerUser).toBe(1)
			expect(after.maxWidgetsPerDashboard).toBe(1)
		} finally {
			await unlimited()
		}
	})
})

test.describe('dashboard count (REQ-QUOTA-002, REQ-QUOTA-004)', () => {
	// @e2e dashboard-quota-limits::creation-blocked-at-the-limit
	test('at 5 of 5 a sixth is refused with the exact 409 and nothing is created', async () => {
		const alice = await freshUser('quota-blocked')
		const dashes = await createDashboards(alice.api, 5, 'Blocked')
		try {
			await setQuotas(5, 0)
			await expectQuotaExceeded(
				await postDashboard(alice.api, `Sixth ${RUN}`),
				'dashboards',
				5,
				5,
			)
			// The fork path goes through the same check.
			await expectQuotaExceeded(
				await alice.api.post(`${API}/dashboards/${dashes[0].uuid}/fork`, {
					data: {},
				}),
				'dashboards',
				5,
				5,
			)
			const listed = await listMine(alice.api)
			expect(listed.items).toHaveLength(5)
			expect(listed.quota.dashboardsUsed).toBe(5)
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::creation-allowed-below-the-limit
	test('at 4 of 5 the fifth is created normally', async () => {
		const alice = await freshUser('quota-below')
		await createDashboards(alice.api, 4, 'Below')
		try {
			await setQuotas(5, 0)
			const res = await postDashboard(alice.api, `Fifth ${RUN}`)
			expect(res.status(), await res.text()).toBe(201)
			const created = (await res.json()).dashboard
			const listed = await listMine(alice.api)
			expect(listed.items.map((d) => d.uuid)).toContain(created.uuid)
			expect(listed.quota.dashboardsUsed).toBe(5)
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::deleting-a-dashboard-frees-quota-immediately
	test('deleting one at the limit lets the next creation through at once', async () => {
		const alice = await freshUser('quota-free')
		const dashes = await createDashboards(alice.api, 5, 'Free')
		try {
			await setQuotas(5, 0)
			expect((await postDashboard(alice.api, `Refused ${RUN}`)).status()).toBe(
				409,
			)

			const del = await alice.api.delete(`${API}/dashboard/${dashes[4].id}`)
			expect(del.status(), await del.text()).toBeLessThan(300)
			const res = await postDashboard(alice.api, `After delete ${RUN}`)
			expect(res.status(), await res.text()).toBe(201)
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::boolean-flag-interaction-is-most-restrictive-wins
	test('allowMultipleDashboards=false caps at one even with a quota of 5', async () => {
		const alice = await freshUser('quota-bool')
		await createDashboards(alice.api, 1, 'Only')
		try {
			await writeSettings({
				maxDashboardsPerUser: 5,
				maxWidgetsPerDashboard: 0,
				allowUserDash: true,
				allowMultiDash: false,
			})
			const res = await postDashboard(alice.api, `Second ${RUN}`)
			expect([403, 409], await res.text()).toContain(res.status())
			if (res.status() === 409) {
				expect((await res.json()).limit).toBe(1)
			}
			const listed = await listMine(alice.api)
			expect(listed.items).toHaveLength(1)
			// The numeric quota does not widen the boolean cap.
			expect(listed.quota.maxDashboards).toBe(1)
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::group-scope-dashboards-do-not-count-against-the-user
	test('three group dashboards next to five personal ones count as five', async () => {
		const alice = await freshUser('quota-group')
		const group = await createGroup(admin, `quota-group-${RUN}`)
		const groupDashes: string[] = []
		try {
			await addToGroup(admin, alice.username, group)
			await createDashboards(alice.api, 5, 'Personal')
			for (let i = 0; i < 3; i++) {
				const res = await admin.post(
					`${API}/dashboards/group/${encodeURIComponent(group)}`,
					{ data: { name: `Group ${i + 1} ${RUN}` } },
				)
				expect(res.status(), await res.text()).toBe(201)
				const uuid = (await res.json()).dashboard.uuid
				groupDashes.push(uuid)
				// A new dashboard is a draft, hidden from members until published.
				const published = await admin.post(`${API}/dashboards/${uuid}/publish`)
				expect(published.status(), await published.text()).toBe(200)
			}
			await setQuotas(5, 0)

			// The three are routed to her.
			const visible = await alice.api.get(`${API}/dashboards/visible`)
			expect(visible.status()).toBe(200)
			const visibleUuids = ((await visible.json()).items ?? []).map(
				(d: { uuid: string }) => d.uuid,
			)
			for (const uuid of groupDashes) {
				expect(visibleUuids).toContain(uuid)
			}

			const listed = await listMine(alice.api)
			expect(listed.quota.dashboardsUsed).toBe(5)
			await expectQuotaExceeded(
				await postDashboard(alice.api, `Sixth ${RUN}`),
				'dashboards',
				5,
				5,
			)
		} finally {
			await unlimited()
			for (const uuid of groupDashes) {
				await admin.delete(
					`${API}/dashboards/group/${encodeURIComponent(group)}/${uuid}`,
				)
			}
			await removeGroup(admin, group)
		}
	})

	// @e2e dashboard-quota-limits::admins-are-bound-by-quotas-for-their-own-personal-dashboards
	test('an administrator at 5 of 5 is refused a sixth like anyone', async () => {
		const carol = await freshUser('quota-carol')
		await addToGroup(admin, carol.username, 'admin')
		await createDashboards(carol.api, 5, 'Carol')
		try {
			await setQuotas(5, 0)
			await expectQuotaExceeded(
				await postDashboard(carol.api, `Carol sixth ${RUN}`),
				'dashboards',
				5,
				5,
			)
			expect((await listMine(carol.api)).items).toHaveLength(5)
		} finally {
			await unlimited()
		}
	})
})

test.describe('widget count (REQ-QUOTA-003)', () => {
	// @e2e dashboard-quota-limits::adding-a-widget-at-the-limit
	test('at 40 of 40 a widget and a tile are both refused and nothing lands', async () => {
		const alice = await freshUser('quota-widgets-at')
		const dash = await createDashboard(alice.api, `Widgets at ${RUN}`)
		await fillTo(alice.api, dash.id, 40)
		try {
			await setQuotas(0, 40)
			await expectQuotaExceeded(
				await postWidget(alice.api, dash.id),
				'widgets',
				40,
				40,
			)
			await expectQuotaExceeded(
				await postTile(alice.api, dash.id),
				'widgets',
				40,
				40,
			)
			expect((await placements(alice.api, dash.id)).length).toBe(40)
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::adding-a-widget-below-the-limit
	test('at 39 of 40 a widget is created normally', async () => {
		const alice = await freshUser('quota-widgets-below')
		const dash = await createDashboard(alice.api, `Widgets below ${RUN}`)
		await fillTo(alice.api, dash.id, 39)
		try {
			await setQuotas(0, 40)
			const res = await postWidget(alice.api, dash.id)
			expect(res.status(), await res.text()).toBe(201)
			expect((await placements(alice.api, dash.id)).length).toBe(40)
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::removing-a-placement-frees-quota-immediately
	test('removing one placement at the limit lets the next widget through', async () => {
		const alice = await freshUser('quota-widgets-free')
		const dash = await createDashboard(alice.api, `Widgets free ${RUN}`)
		await fillTo(alice.api, dash.id, 40)
		try {
			await setQuotas(0, 40)
			expect((await postWidget(alice.api, dash.id)).status()).toBe(409)

			const victim = (await placements(alice.api, dash.id))[0]
			const del = await alice.api.delete(`${API}/widgets/${victim.id}`)
			expect(del.status(), await del.text()).toBeLessThan(300)

			const res = await postWidget(alice.api, dash.id)
			expect(res.status(), await res.text()).toBe(201)
			expect((await placements(alice.api, dash.id)).length).toBe(40)
		} finally {
			await unlimited()
		}
	})
})

test.describe('grandfathering (REQ-QUOTA-005)', () => {
	// @e2e dashboard-quota-limits::lowering-the-dashboard-limit-below-usage
	test('lowering the limit to 5 under 8 hides and blocks nothing but new ones', async () => {
		const alice = await freshUser('quota-grandfather')
		const dashes = await createDashboards(alice.api, 8, 'Grandfathered')
		try {
			await setQuotas(5, 0)

			const listed = await listMine(alice.api)
			expect(listed.items.map((d) => d.uuid).sort()).toEqual(
				dashes.map((d) => d.uuid).sort(),
			)
			for (const d of dashes) {
				expect(
					(await alice.api.get(`${API}/dashboard/${d.id}`)).status(),
				).toBe(200)
			}

			await expectQuotaExceeded(
				await postDashboard(alice.api, `Ninth ${RUN}`),
				'dashboards',
				5,
				8,
			)

			// Editable: a rename and a new widget on an over-quota dashboard.
			const renamed = await alice.api.put(`${API}/dashboard/${dashes[0].id}`, {
				data: { name: `Renamed ${RUN}` },
			})
			expect(renamed.status(), await renamed.text()).toBe(200)
			const shown = await (
				await alice.api.get(`${API}/dashboard/${dashes[0].id}`)
			).json()
			expect(shown.dashboard.name).toBe(`Renamed ${RUN}`)
			expect((await postWidget(alice.api, dashes[0].id)).status()).toBe(201)

			// Deletable.
			const del = await alice.api.delete(`${API}/dashboard/${dashes[7].id}`)
			expect(del.status(), await del.text()).toBeLessThan(300)
			expect((await listMine(alice.api)).items).toHaveLength(7)
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::converging-back-under-the-limit
	test('at 8 over a limit of 5, deleting down to 4 lets a creation through', async () => {
		const alice = await freshUser('quota-converge')
		const dashes = await createDashboards(alice.api, 8, 'Converge')
		try {
			await setQuotas(5, 0)
			expect(
				(await postDashboard(alice.api, `Too many ${RUN}`)).status(),
			).toBe(409)
			for (const d of dashes.slice(4)) {
				const del = await alice.api.delete(`${API}/dashboard/${d.id}`)
				expect(del.status(), await del.text()).toBeLessThan(300)
			}
			expect((await listMine(alice.api)).quota.dashboardsUsed).toBe(4)

			const res = await postDashboard(alice.api, `Back under ${RUN}`)
			expect(res.status(), await res.text()).toBe(201)
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::raising-a-limit-back-to-zero-restores-unlimited
	test('setting a quota back to 0 lifts it at once', async () => {
		const alice = await freshUser('quota-zero')
		const dashes = await createDashboards(alice.api, 3, 'Zero')
		await fillTo(alice.api, dashes[0].id, 3)
		try {
			await setQuotas(2, 2)
			expect((await postDashboard(alice.api, `Over ${RUN}`)).status()).toBe(
				409,
			)
			expect((await postWidget(alice.api, dashes[0].id)).status()).toBe(409)

			await unlimited()
			expect(
				(await postDashboard(alice.api, `Unlimited again ${RUN}`)).status(),
			).toBe(201)
			expect((await postWidget(alice.api, dashes[0].id)).status()).toBe(201)
			const listed = await listMine(alice.api)
			expect(listed.quota).toEqual({
				maxDashboards: 0,
				dashboardsUsed: 4,
				maxWidgetsPerDashboard: 0,
			})
		} finally {
			await unlimited()
		}
	})
})

test.describe('quota in the list and the switcher (REQ-QUOTA-006)', () => {
	// @e2e dashboard-quota-limits::list-response-carries-quota-status
	test('the list envelope carries maxDashboards, dashboardsUsed and maxWidgetsPerDashboard', async () => {
		const alice = await freshUser('quota-envelope')
		const dashes = await createDashboards(alice.api, 3, 'Envelope')
		try {
			await setQuotas(5, 0)
			const listed = await listMine(alice.api)
			expect(listed.quota).toEqual({
				maxDashboards: 5,
				dashboardsUsed: 3,
				maxWidgetsPerDashboard: 0,
			})
			expect(listed.items.map((d) => d.uuid).sort()).toEqual(
				dashes.map((d) => d.uuid).sort(),
			)
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::create-button-disabled-at-the-limit
	test('at 5 of 5 "Add dashboard" is disabled and says why, in English and Dutch', async ({
		browser,
	}) => {
		const alice = await freshUser('quota-ui-limit')
		await createDashboards(alice.api, 5, 'Full')
		try {
			await setQuotas(5, 0)

			const page = await switcherAs(browser, alice)
			try {
				const button = page.locator(ADD_BUTTON)
				await expect(button).toBeVisible({ timeout: 10_000 })
				await expect(button).toBeDisabled()
				await expect(button).toHaveAttribute(
					'title',
					'You have reached the limit of 5 dashboards',
				)
				await expect(page.locator(QUOTA_HINT)).toHaveText(
					'You have reached the limit of 5 dashboards',
				)
			} finally {
				await page.context().close()
			}

			const lang = await admin.put(
				`/ocs/v1.php/cloud/users/${encodeURIComponent(alice.username)}`,
				{ form: { key: 'language', value: 'nl' } },
			)
			expect(lang.ok(), await lang.text()).toBe(true)
			const nl = await switcherAs(browser, alice)
			try {
				const button = nl.locator(ADD_BUTTON)
				await expect(button).toBeDisabled({ timeout: 10_000 })
				await expect(button).toContainText('Dashboard toevoegen')
				await expect(button).toHaveAttribute(
					'title',
					'Je hebt de limiet van 5 dashboards bereikt',
				)
				await expect(nl.locator(QUOTA_HINT)).toHaveText(
					'Je hebt de limiet van 5 dashboards bereikt',
				)
			} finally {
				await nl.context().close()
			}
		} finally {
			await unlimited()
		}
	})

	// @e2e dashboard-quota-limits::unlimited-instances-show-no-quota-ui
	test('with both quotas at 0 the switcher shows no quota state at all', async ({
		browser,
	}) => {
		await unlimited()
		const alice = await freshUser('quota-ui-none')
		// Well past any limit the spec uses, so a stale limit would show.
		await createDashboards(alice.api, 6, 'Plenty')

		const page = await switcherAs(browser, alice)
		try {
			const button = page.locator(ADD_BUTTON)
			await expect(button).toBeVisible({ timeout: 10_000 })
			await expect(button).toBeEnabled()
			await expect(button).not.toHaveAttribute('title', /.+/)
			await expect(button).toHaveAttribute('aria-label', 'Add dashboard')
			await expect(page.locator(QUOTA_HINT)).toHaveCount(0)
			await expect(page.getByText(/reached the limit/i)).toHaveCount(0)
		} finally {
			await page.context().close()
		}
	})

	// @e2e dashboard-quota-limits::race-between-ui-state-and-server-enforcement
	test('a button left enabled from below the limit gets the 409, a clear message and a refresh', async ({
		browser,
	}) => {
		const alice = await freshUser('quota-ui-race')
		const dashes = await createDashboards(alice.api, 4, 'Race')
		const activate = await alice.api.post(`${API}/dashboards/active`, {
			data: { uuid: dashes[0].uuid },
		})
		expect(activate.status(), await activate.text()).toBeLessThan(300)
		try {
			await setQuotas(5, 0)
			const page = await switcherAs(browser, alice)
			try {
				const button = page.locator(ADD_BUTTON)
				await expect(button).toBeEnabled({ timeout: 10_000 })

				// "Another tab": the fifth is made behind this page's back.
				await createDashboard(alice.api, `Other tab ${RUN}`)

				const refused = page.waitForResponse(
					(r) =>
						r.request().method() === 'POST'
						&& /\/api\/(dashboard|dashboards\/[^/]+\/fork)(\?|$)/.test(
							r.url(),
						),
				)
				await button.click()
				const res = await refused
				expect(res.status()).toBe(409)
				expect((await res.json()).error).toBe('quota_exceeded')

				await expect(
					page.locator('.toastify').filter({
						hasText: 'You have reached the limit of 5 dashboards',
					}),
				).toBeVisible({ timeout: 10_000 })

				// The envelope was read again, so the affordance now disables.
				await openDashboardSwitcher(page)
				await expect(page.locator(ADD_BUTTON)).toBeDisabled({
					timeout: 10_000,
				})
				expect((await listMine(alice.api)).items).toHaveLength(5)
			} finally {
				await page.context().close()
			}
		} finally {
			await unlimited()
		}
	})
})
