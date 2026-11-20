/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the workspace page's active-dashboard
 * resolution chain and the active-dashboard preference
 * (openspec/specs/dashboards, REQ-DASH-016, REQ-DASH-018, REQ-DASH-019),
 * written for the e2e-coverage-programme.
 *
 * "She opens the workspace page" is the request a person's browser makes:
 * `GET /index.php/apps/launchpad/` as that person. What the page resolved is
 * what it hands the frontend in its initial state (`activeDashboardId`,
 * `dashboardSource`), read from the served HTML so the SPA cannot rewrite the
 * preference before it is observed (see support/dashboardsSpec.ts). One
 * scenario is also checked in the browser, through the dashboard switcher.
 *
 * The chain needs a published default dashboard in the `default` group
 * (step 3); the instance normally has one and, when it does not, one is made
 * and removed again. A user's primary group comes from the admin group order
 * (REQ-ASET-012), which is extended for the duration of the file and put back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::first-dashboard-in-a-group-is-not-auto-promoted
 *   @e2e dashboards::honoured-user-preference
 *   @e2e dashboards::stale-preference-is-silently-cleared
 *   @e2e dashboards::group-default-wins-over-default-group-default
 *   @e2e dashboards::falls-through-to-default-group-when-primary-group-has-no-dashboards
 *   @e2e dashboards::save-preference
 *   @e2e dashboards::empty-uuid-clears-the-preference
 *   @e2e dashboards::no-existence-check-on-write
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type {
	CreatedGroupDashboard,
	DashboardSettings,
} from './support/dashboardsSpec.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	allowPersonalDashboardsFreely,
	APP,
	createGroupDashboard,
	createPersonal,
	ensureDefaultGroupDefault,
	expectNoDefaultTemplate,
	listGroup,
	listPersonal,
	openWorkspace,
	readGroupOrder,
	removeFromGroup,
	removeGroupDashboards,
	setActivePreference,
	writeGroupOrder,
	writeSettings,
} from './support/dashboardsSpec.ts'
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
const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'

let admin: APIRequestContext
let settingsBefore: DashboardSettings | null = null
let groupOrderBefore: string[] | null = null
let restoreDefault: (() => Promise<void>) | null = null
/** The published default dashboard of the `default` group: step 3 of the chain. */
let defaultGroupDefault = ''
const principals: Principal[] = []
const ncGroups: string[] = []
const created: CreatedGroupDashboard[] = []

/** Primary groups for this file, added to the admin group order. */
const PRIMARY = {
	withDefault: `e2e-dash-prim-def-${RUN}`,
	empty: `e2e-dash-prim-empty-${RUN}`,
	firstOnly: `e2e-dash-prim-first-${RUN}`,
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

/**
 * A throwaway, non-admin account.
 *
 * @param prefix a readable prefix.
 * @return the principal.
 */
async function fresh(prefix: string): Promise<Principal> {
	const principal = await createPrincipal(admin, prefix)
	principals.push(principal)
	await assertActingAs(principal.api, principal.username)
	return principal
}

/**
 * What the workspace page resolved for this principal.
 *
 * @param principal who opens the page.
 * @return the page status, the active dashboard uuid and its source.
 */
async function resolved(
	principal: Principal,
): Promise<{ status: number; uuid: unknown; source: unknown }> {
	const { status, state } = await openWorkspace(principal.api)
	return { status, uuid: state.activeDashboardId, source: state.dashboardSource }
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	settingsBefore = await allowPersonalDashboardsFreely(admin)

	for (const groupId of Object.values(PRIMARY)) {
		ncGroups.push(await createGroup(admin, groupId))
	}
	groupOrderBefore = await readGroupOrder(admin)
	await writeGroupOrder(admin, [...groupOrderBefore, ...Object.values(PRIMARY)])

	const ensured = await ensureDefaultGroupDefault(
		admin,
		`E2E Default for all ${RUN}`,
	)
	defaultGroupDefault = ensured.uuid
	restoreDefault = ensured.restore
})

test.afterAll(async () => {
	await removeGroupDashboards(admin, created)
	if (restoreDefault !== null) {
		await restoreDefault()
	}
	if (groupOrderBefore !== null) {
		await writeGroupOrder(admin, groupOrderBefore)
	}
	for (const principal of principals) {
		await removePrincipal(admin, principal)
	}
	for (const groupId of ncGroups) {
		await removeGroup(admin, groupId)
	}
	if (settingsBefore !== null) {
		await writeSettings(admin, settingsBefore)
	}
	await admin.dispose()
})

test.describe('REQ-DASH-016 a new group dashboard is not promoted', () => {
	// @e2e dashboards::first-dashboard-in-a-group-is-not-auto-promoted
	test('the first dashboard of a group stays non-default and the chain does not treat it as one', async () => {
		await expectNoDefaultTemplate(admin)
		expect(await listGroup(admin, PRIMARY.firstOnly)).toEqual([])
		const first = await createGroupDashboard(
			admin,
			PRIMARY.firstOnly,
			`E2E First of group ${RUN}`,
		)
		created.push({ groupId: PRIMARY.firstOnly, uuid: first.uuid })
		expect(first.isDefault).toBe(0)

		const member = await fresh('e2e-dash-firstonly')
		await addToGroup(admin, member.username, PRIMARY.firstOnly)
		const page = await resolved(member)
		expect(page.status).toBe(200)
		// Promoted, it would win at step 2 with source 'group'. Not promoted,
		// the default group's default (step 3) answers before the group's
		// first-by-sortOrder dashboard (step 4).
		expect(page).toMatchObject({ uuid: defaultGroupDefault, source: 'default' })
		expect(
			(await listGroup(admin, PRIMARY.firstOnly)).map((row) => [
				row.uuid,
				row.isDefault,
			]),
		).toEqual([[first.uuid, 0]])
	})
})

test.describe("REQ-DASH-018 the workspace page's active dashboard", () => {
	// @e2e dashboards::honoured-user-preference
	test('a preference for a personal dashboard wins, with source "user"', async ({
		browser,
	}) => {
		const alice = await fresh('e2e-dash-pref')
		const x = (await createPersonal(alice.api, { name: `E2E Preferred ${RUN}` }))
			.dashboard
		// Without the preference the chain would land on the default group's
		// dashboard (step 3) before her own (step 6).
		await setActivePreference(alice.api, x.uuid)

		const page = await resolved(alice)
		expect(page).toMatchObject({ status: 200, uuid: x.uuid, source: 'user' })

		const { context, page: browserPage } = await loginAs(
			browser,
			alice.username,
			alice.password,
		)
		try {
			await browserPage.goto(`${APP}/`)
			await openDashboardSwitcher(browserPage)
			await expect(
				browserPage
					.locator('.dashboard-switcher-sidebar__item')
					.filter({ hasText: x.name }),
			).toHaveClass(/\bactive\b/)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboards::stale-preference-is-silently-cleared
	test('a preference for a dashboard she can no longer see is cleared and the chain goes on', async () => {
		await expectNoDefaultTemplate(admin)
		const alice = await fresh('e2e-dash-stale')
		const group = await createGroup(admin, `e2e-dash-stale-${RUN}`)
		ncGroups.push(group)
		await addToGroup(admin, alice.username, group)
		const y = await createGroupDashboard(admin, group, `E2E Stale ${RUN}`)
		created.push({ groupId: group, uuid: y.uuid })
		await setActivePreference(alice.api, y.uuid)
		// While she can see it, the preference is honoured.
		expect(await resolved(alice)).toMatchObject({
			status: 200,
			uuid: y.uuid,
			source: 'group',
		})

		await removeFromGroup(admin, alice.username, group)
		const stale = await resolved(alice)
		expect(stale.status).toBe(200)
		expect(stale).toMatchObject({ uuid: defaultGroupDefault, source: 'default' })

		// Seeing it again does not bring it back: the preference is gone.
		await addToGroup(admin, alice.username, group)
		const after = await resolved(alice)
		expect(after).toMatchObject({
			status: 200,
			uuid: defaultGroupDefault,
			source: 'default',
		})
	})

	// @e2e dashboards::group-default-wins-over-default-group-default
	test("the primary group's default beats the default group's default", async () => {
		const e = await createGroupDashboard(
			admin,
			PRIMARY.withDefault,
			`E2E Engineering default ${RUN}`,
		)
		created.push({ groupId: PRIMARY.withDefault, uuid: e.uuid })
		const promoted = await admin.post(
			`${API}/dashboards/group/${PRIMARY.withDefault}/default`,
			{
				data: { uuid: e.uuid },
			},
		)
		expect(promoted.status(), await promoted.text()).toBe(200)

		const bob = await fresh('e2e-dash-engdefault')
		await addToGroup(admin, bob.username, PRIMARY.withDefault)
		expect(await listPersonal(bob.api)).toEqual([])

		expect(await resolved(bob)).toMatchObject({
			status: 200,
			uuid: e.uuid,
			source: 'group',
		})
	})

	// @e2e dashboards::falls-through-to-default-group-when-primary-group-has-no-dashboards
	test("a primary group with no dashboards falls through to the default group's default", async () => {
		await expectNoDefaultTemplate(admin)
		expect(await listGroup(admin, PRIMARY.empty)).toEqual([])
		const carol = await fresh('e2e-dash-support')
		await addToGroup(admin, carol.username, PRIMARY.empty)

		expect(await resolved(carol)).toMatchObject({
			status: 200,
			uuid: defaultGroupDefault,
			source: 'default',
		})
	})
})

test.describe('REQ-DASH-019 the active-dashboard preference', () => {
	// @e2e dashboards::save-preference
	test('the preference is saved and answered with {status: "success"}', async () => {
		const alice = await fresh('e2e-dash-save')
		const res = await alice.api.post(`${API}/dashboards/active`, {
			data: { uuid: 'abc-123' },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect(await res.json()).toEqual({ status: 'success' })

		// What was saved is what the next page load opens.
		const x = (await createPersonal(alice.api, { name: `E2E Saved ${RUN}` }))
			.dashboard
		const saved = await alice.api.post(`${API}/dashboards/active`, {
			data: { uuid: x.uuid },
		})
		expect(saved.status()).toBe(200)
		expect(await saved.json()).toEqual({ status: 'success' })
		expect(await resolved(alice)).toMatchObject({
			status: 200,
			uuid: x.uuid,
			source: 'user',
		})
	})

	// @e2e dashboards::empty-uuid-clears-the-preference
	test('an empty uuid clears the preference and the chain resumes from step 2', async () => {
		const alice = await fresh('e2e-dash-clear')
		const x = (await createPersonal(alice.api, { name: `E2E Cleared ${RUN}` }))
			.dashboard
		await setActivePreference(alice.api, x.uuid)
		expect(await resolved(alice)).toMatchObject({ uuid: x.uuid, source: 'user' })

		const res = await alice.api.post(`${API}/dashboards/active`, {
			data: { uuid: '' },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect(await resolved(alice)).toMatchObject({
			status: 200,
			uuid: defaultGroupDefault,
			source: 'default',
		})
	})

	// @e2e dashboards::no-existence-check-on-write
	test('a uuid that matches nothing is accepted, and the page still renders', async () => {
		await expectNoDefaultTemplate(admin)
		const alice = await fresh('e2e-dash-nocheck')
		const res = await alice.api.post(`${API}/dashboards/active`, {
			data: { uuid: 'does-not-exist' },
		})
		expect(res.status(), await res.text()).toBe(200)
		expect(await res.json()).toEqual({ status: 'success' })

		expect(await resolved(alice)).toMatchObject({
			status: 200,
			uuid: defaultGroupDefault,
			source: 'default',
		})
	})
})
