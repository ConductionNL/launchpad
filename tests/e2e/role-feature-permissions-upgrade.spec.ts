/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Role feature permissions, REQ-RFP-011: on upgrade, an untouched shipped
 * widget rule is widened to LaunchPad's own widget types and an edited one is
 * kept (openspec/specs/role-feature-permissions/spec.md).
 *
 * HOW AN UPGRADE IS STAGED. The widening is done by two post-migration repair
 * steps (`BroadenDefaultWidgetRule`, `BroadenShippedGroupWidgetRules`, both in
 * appinfo/info.xml). `occ maintenance:repair` runs every enabled app's
 * post-migration steps, which is the part of an upgrade these scenarios are
 * about; `allowed-websites-list.spec.ts` stages the REQ-ASET-021 merge the
 * same way. Each test writes the rule an older version seeded through the
 * admin API, runs the repair, and reads the rule back.
 *
 * WHY THE REAL GROUP IDS. The group step only looks at the four group ids it
 * shipped (`SeedRolePermissions::SHIPPED_GROUP_RULES`), so the hrm test uses
 * the group `hrm` itself, not a stamped one. Every row this file touches is
 * snapshotted first and put back in `finally`, and `hrm` is deleted again only
 * when this file created it.
 *
 * WHAT A USER IS SERVED. The rule decides which placements are rendered
 * (`isVisible`) on a dashboard someone else owns, so each test also follows a
 * non-admin through the upgrade: a `text` placement is hidden under the old
 * rule and shown under the widened one. Admins are never restricted, so the
 * viewer is always a throwaway ordinary account.
 */

import type { APIRequestContext } from '@playwright/test'
import type { RoleFeaturePermissionRow } from './fixtures/role-feature-permissions.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import {
	deleteRoleFeaturePermission,
	listRoleFeaturePermissions,
	readGroupOrder,
	restoreRoleFeaturePermissions,
	saveRoleFeaturePermission,
	writeGroupOrder,
} from './fixtures/role-feature-permissions.ts'
import { describeRun, runOcc } from './support/occ.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	publishForRecipients,
	removeGroup,
	removePrincipal,
	seedDashboardWithWidget,
	shareWith,
} from './support/principals.ts'

test.describe.configure({ mode: 'serial' })
test.setTimeout(240_000)

/** LaunchPad's own widget types, the list both repair steps add. */
const OWN_TYPES: string[] = JSON.parse(
	fs.readFileSync(path.resolve(__dirname, '../../lib/widget-types.json'), 'utf8'),
).types

/** The `default` rule older versions seeded (`WidgetTypeList::DEFAULT_RULE_DASHBOARD_WIDGETS`). */
const OLD_DEFAULT_ALLOWED = ['activity', 'recommendations']

/** The `hrm` rule older versions seeded (`SeedRolePermissions::SHIPPED_GROUP_RULES['hrm']`). */
const OLD_HRM_ALLOWED = ['activity', 'recommendations', 'notes', 'calendar', 'user_status']
const OLD_HRM_DENIED = ['system_monitor']

const HRM = 'hrm'

let admin: APIRequestContext
const principals: Principal[] = []
const dashboards: number[] = []

/** Sorted copy, so a set comparison does not depend on stored order. */
function sorted(ids: string[]): string[] {
	return [...ids].sort()
}

/** The rows for a group id, as the admin reads them now. */
async function ruleFor(groupId: string): Promise<RoleFeaturePermissionRow | undefined> {
	return (await listRoleFeaturePermissions(admin)).find((row) => row.groupId === groupId)
}

/**
 * Run the app's post-migration repair steps, which is what an upgrade runs,
 * and insist it finished.
 */
async function upgrade(): Promise<void> {
	const repair = await runOcc('maintenance:repair')
	expect(repair.code, describeRun(repair)).toBe(0)
}

/**
 * Put the snapshot of one group's rule back: restore it when there was one,
 * delete what is there when there was none. Never throws: it runs in cleanup.
 */
async function putBack(groupId: string, prior: RoleFeaturePermissionRow | undefined): Promise<void> {
	try {
		if (prior !== undefined) {
			await restoreRoleFeaturePermissions(admin, [prior])
			return
		}
		const current = await ruleFor(groupId)
		if (current !== undefined) {
			await deleteRoleFeaturePermission(admin, current.id)
		}
	} catch {
		// Cleanup: the next run's snapshot takes whatever is left.
	}
}

/** Whether a Nextcloud group exists, through the OCS group search. */
async function groupExists(groupId: string): Promise<boolean> {
	const res = await admin.get('/ocs/v2.php/cloud/groups', {
		params: { search: groupId, format: 'json' },
	})
	expect(res.status(), await res.text()).toBe(200)
	const groups: string[] = (await res.json()).ocs?.data?.groups ?? []
	return groups.includes(groupId)
}

/**
 * An admin-owned dashboard with one `text` placement (the seed helper's), published
 * for recipients so a viewer's read is the rendered one.
 *
 * @return the dashboard id and the `text` placement id.
 */
async function dashboardWithText(name: string): Promise<{ id: number; placementId: number }> {
	const seeded = await seedDashboardWithWidget(admin, name)
	dashboards.push(seeded.id)
	await publishForRecipients(admin, seeded.id)
	return { id: seeded.id, placementId: seeded.placementId }
}

/** Whether the viewer is served the placement as visible on that dashboard (REQ-RFP-001). */
async function servedVisible(viewer: APIRequestContext, dashboardId: number, placementId: number): Promise<boolean> {
	const res = await viewer.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const placement = ((await res.json()).placements ?? []).find(
		(pl: any) => Number(pl.id) === placementId,
	)
	// A placement the viewer's role does not permit comes back with
	// `isVisible` 0, or not at all for a view-only viewer (REQ-RFP-001), and
	// these viewers are view-only: absent counts as not visible.
	if (placement === undefined) {
		return false
	}
	return placement.isVisible !== 0 && placement.isVisible !== false
}

/** A throwaway ordinary account, proven to be who it says. */
async function viewer(prefix: string): Promise<Principal> {
	const p = await createPrincipal(admin, prefix)
	principals.push(p)
	await assertActingAs(p.api, p.username)
	return p
}

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')
	expect(OWN_TYPES, 'lib/widget-types.json lists LaunchPad widget types').toContain('text')
})

test.afterAll(async () => {
	if (admin === undefined) {
		return
	}
	for (const id of dashboards) {
		await admin.delete(`${API}/dashboard/${id}`).catch(() => undefined)
	}
	for (const p of principals) {
		await removePrincipal(admin, p).catch(() => undefined)
	}
	await admin.dispose()
})

// ─── REQ-RFP-011: the shipped default rule ───────────────────────────────

// @e2e role-feature-permissions::an-upgrade-widens-the-old-shipped-rule-and-keeps-an-edited-one
test('an upgrade widens the old shipped default rule to every LaunchPad widget type, and keeps an edited one', async () => {
	const prior = await ruleFor('default')
	try {
		// A user in no group falls back to the `default` rule.
		const sem = await viewer('e2e-rfp-up-sem')
		const dash = await dashboardWithText(`RFP upgrade default ${Date.now()}`)
		await shareWith(admin, dash.id, sem.username, 'view_only')

		// GIVEN: the rule older versions seeded, allowing only the two Nextcloud widgets.
		await saveRoleFeaturePermission(admin, {
			groupId: 'default',
			name: 'Standaard widget-rechten',
			allowedWidgets: OLD_DEFAULT_ALLOWED,
			deniedWidgets: [],
		})
		expect(sorted((await ruleFor('default'))!.allowedWidgets)).toEqual(sorted(OLD_DEFAULT_ALLOWED))
		expect(
			await servedVisible(sem.api, dash.id, dash.placementId),
			'under the old default rule a text placement is hidden from a user in no group',
		).toBe(false)

		// WHEN: LaunchPad is upgraded.
		await upgrade()

		// THEN: every LaunchPad widget type plus activity and recommendations.
		const widened = (await ruleFor('default'))!
		expect(sorted(widened.allowedWidgets)).toEqual(
			sorted([...new Set([...OLD_DEFAULT_ALLOWED, ...OWN_TYPES])]),
		)
		expect(widened.deniedWidgets).toEqual([])
		expect(
			await servedVisible(sem.api, dash.id, dash.placementId),
			'after the upgrade the widened default rule shows the text placement',
		).toBe(true)

		// AND: a default rule an administrator changed stays as they left it.
		const edited = ['activity', 'recommendations', 'clock']
		await saveRoleFeaturePermission(admin, {
			groupId: 'default',
			name: 'Standaard widget-rechten (edited)',
			allowedWidgets: edited,
			deniedWidgets: [],
		})
		await upgrade()
		const kept = (await ruleFor('default'))!
		expect(sorted(kept.allowedWidgets)).toEqual(sorted(edited))
		expect(kept.deniedWidgets).toEqual([])
		expect(kept.name).toBe('Standaard widget-rechten (edited)')
		expect(
			await servedVisible(sem.api, dash.id, dash.placementId),
			'the kept rule still does not allow text',
		).toBe(false)
	} finally {
		await putBack('default', prior)
	}
})

// ─── REQ-RFP-011: the shipped group rules ────────────────────────────────

// @e2e role-feature-permissions::an-upgrade-widens-the-shipped-group-rules-and-keeps-an-edited-one
test('an upgrade widens the old shipped hrm rule, keeps its denial, and keeps an edited one', async () => {
	const prior = await ruleFor(HRM)
	const hadGroup = await groupExists(HRM)
	const originalGroupOrder = await readGroupOrder(admin)
	try {
		if (!hadGroup) {
			await createGroup(admin, HRM)
		}
		expect(await groupExists(HRM)).toBe(true)
		// The allow-list only reads groups in group_order; hrm goes first.
		await writeGroupOrder(admin, [HRM, ...originalGroupOrder.filter((g) => g !== HRM)])

		// GIVEN: fatima is only in hrm and opens a group dashboard with a text widget.
		const fatima = await viewer('e2e-rfp-up-fatima')
		await addToGroup(admin, fatima.username, HRM)
		const dash = await dashboardWithText(`RFP upgrade hrm ${Date.now()}`)
		const grant = await admin.post(`${API}/dashboard/${dash.id}/shares`, {
			data: { shareType: 'group', shareWith: HRM, permissionLevel: 'view_only' },
		})
		expect(grant.status(), await grant.text()).toBeLessThan(300)

		// AND: the hrm rule as older versions seeded it.
		await saveRoleFeaturePermission(admin, {
			groupId: HRM,
			name: 'HRM widget-rechten',
			allowedWidgets: OLD_HRM_ALLOWED,
			deniedWidgets: OLD_HRM_DENIED,
		})
		expect(
			await servedVisible(fatima.api, dash.id, dash.placementId),
			'under the old hrm rule the text widget is hidden from fatima',
		).toBe(false)

		// WHEN: LaunchPad is upgraded.
		await upgrade()

		// THEN: every LaunchPad widget type besides the five, system_monitor still denied.
		const widened = (await ruleFor(HRM))!
		expect(sorted(widened.allowedWidgets)).toEqual(
			sorted([...new Set([...OLD_HRM_ALLOWED, ...OWN_TYPES])]),
		)
		expect(widened.deniedWidgets).toEqual(OLD_HRM_DENIED)

		// AND: fatima sees the text widget.
		expect(
			await servedVisible(fatima.api, dash.id, dash.placementId),
			'after the upgrade fatima must see the text widget',
		).toBe(true)

		// AND: an hrm rule an administrator changed stays as they left it. Here
		// the administrator dropped the denial, so the rule no longer holds
		// exactly what was shipped.
		await saveRoleFeaturePermission(admin, {
			groupId: HRM,
			name: 'HRM widget-rechten (edited)',
			allowedWidgets: OLD_HRM_ALLOWED,
			deniedWidgets: [],
		})
		await upgrade()
		const kept = (await ruleFor(HRM))!
		expect(sorted(kept.allowedWidgets)).toEqual(sorted(OLD_HRM_ALLOWED))
		expect(kept.deniedWidgets).toEqual([])
		expect(kept.name).toBe('HRM widget-rechten (edited)')
		expect(
			await servedVisible(fatima.api, dash.id, dash.placementId),
			'the kept hrm rule still does not allow text',
		).toBe(false)
	} finally {
		await putBack(HRM, prior)
		await writeGroupOrder(admin, originalGroupOrder).catch(() => undefined)
		if (!hadGroup) {
			await removeGroup(admin, HRM).catch(() => undefined)
		}
	}
})
