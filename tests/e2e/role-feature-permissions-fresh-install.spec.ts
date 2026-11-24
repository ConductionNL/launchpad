/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Role feature permissions, REQ-RFP-011: the `default` rule a fresh install
 * seeds lets every user add LaunchPad's own widgets
 * (openspec/specs/role-feature-permissions/spec.md).
 *
 * HOW A FRESH INSTALL IS STAGED. The rules are seeded by the post-migration
 * repair step `SeedRolePermissions` (appinfo/info.xml). It seeds only when the
 * role-feature-permission table is empty, which is exactly the state of a
 * fresh install; on any other instance it is a no-op. So the test snapshots
 * every rule and layout default, deletes every rule through the admin API,
 * and runs `occ maintenance:repair`, which runs the step the way an install
 * does. `role-feature-permissions-upgrade.spec.ts` stages the upgrade steps
 * the same way.
 *
 * CLEANUP. The seed also inserts five layout defaults whether or not they
 * exist, so every layout default that was not in the snapshot is deleted
 * afterwards, every seeded rule is deleted and the snapshot of the rules is
 * put back (the upsert is keyed by groupId, so only the ids change).
 */

import type { APIRequestContext } from '@playwright/test'
import type {
	RoleFeaturePermissionRow,
	RoleLayoutDefaultRow,
} from './fixtures/role-feature-permissions.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import {
	deleteRoleFeaturePermission,
	deleteRoleLayoutDefault,
	listRoleFeaturePermissions,
	listRoleLayoutDefaults,
	restoreRoleFeaturePermissions,
} from './fixtures/role-feature-permissions.ts'
import { describeRun, runOcc } from './support/occ.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

test.describe.configure({ mode: 'serial' })
test.setTimeout(240_000)

/** LaunchPad's own widget types, the list the seed adds to every rule. */
const OWN_TYPES: string[] = JSON.parse(
	fs.readFileSync(path.resolve(__dirname, '../../lib/widget-types.json'), 'utf8'),
).types

/** The Nextcloud widgets the seeded `default` rule allows (`WidgetTypeList::DEFAULT_RULE_DASHBOARD_WIDGETS`). */
const DEFAULT_DASHBOARD_WIDGETS = ['activity', 'recommendations']

/** The group rules the seed writes besides `default` (`SeedRolePermissions::SHIPPED_GROUP_RULES`). */
const SHIPPED_GROUPS = ['medewerkers', 'managers', 'ict-beheer', 'hrm']

/** A Nextcloud widget no seeded `default` rule allows, the control. */
const OUTSIDE_DEFAULT = 'notes'

let admin: APIRequestContext
const principals: Principal[] = []

/** Sorted copy, so a set comparison does not depend on stored order. */
function sorted(ids: string[]): string[] {
	return [...ids].sort()
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	expect(OWN_TYPES, 'lib/widget-types.json lists LaunchPad widget types').toContain('text')
	expect(DEFAULT_DASHBOARD_WIDGETS).not.toContain(OUTSIDE_DEFAULT)
	expect(OWN_TYPES).not.toContain(OUTSIDE_DEFAULT)
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	for (const p of principals) {
		await removePrincipal(admin, p).catch(() => undefined)
	}
	await admin.dispose()
})

// @e2e role-feature-permissions::a-fresh-install-lets-every-user-add-a-launchpad-widget
test('the default rule a fresh install seeds lets a user in no group add a text widget', async () => {
	const priorRules: RoleFeaturePermissionRow[] = await listRoleFeaturePermissions(admin)
	const priorLayoutIds = new Set(
		(await listRoleLayoutDefaults(admin)).map((row: RoleLayoutDefaultRow) => row.id),
	)
	const settingsRes = await admin.get(`${API}/admin/settings`)
	expect(settingsRes.status(), await settingsRes.text()).toBe(200)
	const priorSettings = await settingsRes.json()

	try {
		// GIVEN: an install with no rules at all, then the install's seed step.
		for (const row of priorRules) {
			await deleteRoleFeaturePermission(admin, row.id)
		}
		expect(
			await listRoleFeaturePermissions(admin),
			'every rule must be gone before the seed, or the seed is a no-op',
		).toEqual([])

		const repair = await runOcc('maintenance:repair')
		expect(repair.code, describeRun(repair)).toBe(0)

		// The seeded `default` rule is untouched: what the seed wrote.
		const seeded = await listRoleFeaturePermissions(admin)
		const rule = seeded.find((row) => row.groupId === 'default')
		expect(rule, 'the seed must write a default rule').toBeDefined()
		expect(sorted(rule!.allowedWidgets)).toEqual(
			sorted([...new Set([...DEFAULT_DASHBOARD_WIDGETS, ...OWN_TYPES])]),
		)
		expect(rule!.deniedWidgets).toEqual([])
		// The shipped group rules carry LaunchPad's own types as well.
		for (const groupId of SHIPPED_GROUPS) {
			const groupRule = seeded.find((row) => row.groupId === groupId)
			expect(groupRule, `the seed must write the ${groupId} rule`).toBeDefined()
			for (const type of OWN_TYPES) {
				expect(groupRule!.allowedWidgets, `${groupId} must allow ${type}`).toContain(type)
			}
		}

		// AND: sem belongs to no group, so the default rule is his.
		const personal = await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: true, allowMultiDash: true },
		})
		expect(personal.status(), await personal.text()).toBe(200)
		const sem = await createPrincipal(admin, 'e2e-rfp-fresh-sem')
		principals.push(sem)
		await assertActingAs(sem.api, sem.username)
		const created = await sem.api.post(`${API}/dashboard`, {
			data: { name: `RFP fresh install ${Date.now()}` },
		})
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dashboardId = Number((body.dashboard ?? body).id)
		expect(dashboardId).toBeGreaterThan(0)

		// WHEN: sem adds a text widget to his own dashboard.
		const added = await sem.api.post(`${API}/dashboard/${dashboardId}/widgets`, {
			data: { widgetId: 'text', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 4 },
		})

		// THEN: the widget is added.
		expect(added.status(), await added.text()).toBe(201)
		const placementId = Number((await added.json()).id)
		const read = await sem.api.get(`${API}/dashboard/${dashboardId}`)
		expect(read.status(), await read.text()).toBe(200)
		const placement = ((await read.json()).placements ?? []).find(
			(pl: any) => Number(pl.id) === placementId,
		)
		expect(placement, 'the text placement must be stored').toBeDefined()
		expect(placement.widgetId).toBe('text')
		expect(placement.isVisible !== 0 && placement.isVisible !== false).toBe(true)

		// Control: the seeded default rule is what decided. A widget outside
		// it is refused to the same user on the same dashboard.
		const outside = await sem.api.post(`${API}/dashboard/${dashboardId}/widgets`, {
			data: { widgetId: OUTSIDE_DEFAULT, gridX: 4, gridY: 0, gridWidth: 4, gridHeight: 4 },
		})
		expect(outside.status(), await outside.text()).toBe(403)
	} finally {
		try {
			for (const row of await listRoleFeaturePermissions(admin)) {
				await deleteRoleFeaturePermission(admin, row.id)
			}
			await restoreRoleFeaturePermissions(admin, priorRules)
			for (const row of await listRoleLayoutDefaults(admin)) {
				if (!priorLayoutIds.has(row.id)) {
					await deleteRoleLayoutDefault(admin, row.id)
				}
			}
			await admin.put(`${API}/admin/settings`, {
				data: {
					allowUserDash: priorSettings.allowUserDashboards,
					allowMultiDash: priorSettings.allowMultipleDashboards,
				},
			})
		} catch {
			// Cleanup: the next run's snapshot takes whatever is left.
		}
	}
})
