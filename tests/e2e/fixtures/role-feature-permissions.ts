/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared e2e fixture helper for the role-feature-permission widget allow-list.
 *
 * FIXED 2026-06-07 (admin widget-permission bypass):
 *   `RoleFeaturePermissionService::getAllowedWidgetIds()` used to fall back
 *   to the explicit `default` role-feature-permission row for any user whose
 *   groups had no matching profile — INCLUDING Nextcloud admins. Unlike
 *   `ActionAuthService::requireAction()` and
 *   `PermissionService::resolveAccessLevel()` (which both short-circuit for
 *   admins), `isWidgetAllowed()` had no admin bypass, so with the demo-seeded
 *   `default` row (allowed: activity, recommendations only),
 *   `WidgetApiController::addWidget()` returned 403 {"error":"Access denied"}
 *   even when the admin added a widget to their OWN personal dashboard.
 *
 *   `getAllowedWidgetIds()`/`isWidgetAllowed()` now short-circuit for NC
 *   admins. To PROVE that, the widget-add UI specs now INSTALL a restrictive
 *   `default` row up front (instead of clearing it) and then exercise the
 *   real add-widget flow as the admin — a green run means the admin bypass
 *   is working.
 */

import type { APIRequestContext } from '@playwright/test'

import * as fs from 'fs'
import * as path from 'path'
import { adminContext } from '../support/principals.ts'

const RESTRICTION_NAME = 'Default (e2e restriction)'

/**
 * The `default` rule LaunchPad ships (`SeedRolePermissions`): the two
 * Nextcloud dashboard widgets plus every type in `lib/widget-types.json`.
 */
function shippedDefaultRule(): Partial<RoleFeaturePermissionRow> & { groupId: string } {
	const types = JSON.parse(
		fs.readFileSync(path.resolve(__dirname, '../../../lib/widget-types.json'), 'utf8'),
	).types as string[]
	return {
		groupId: 'default',
		name: 'Standaard widget-rechten',
		description: 'Basistoegang voor gebruikers zonder specifieke groepsindeling',
		allowedWidgets: [...new Set(['activity', 'recommendations', ...types])],
		deniedWidgets: [],
		priorityWeights: { recommendations: 10, activity: 8 },
	}
}

/**
 * Build an admin API context. It sends basic auth on every request: an OCS
 * request without credentials is answered 401 without a challenge, so a
 * context that waits for one never authenticates, and the restore in
 * `afterAll` failed silently.
 */
async function adminApi(): Promise<APIRequestContext> {
	return adminContext()
}

/**
 * Ensure a RESTRICTIVE `default` role-feature-permission row is in force
 * (allowed: activity + recommendations only). Combined with the admin
 * break-glass bypass, this lets the widget-add specs prove that an NC admin
 * can still add ANY widget type to their own dashboard despite the
 * restriction — exercising the real fix rather than side-stepping it.
 *
 * The row is scoped to the calling spec: the returned function puts back the
 * `default` row that was there before (or deletes the row when there was
 * none). Call it in the matching `afterAll`, otherwise every later spec that
 * adds other widgets as a plain user gets 403. A `default` row that is itself
 * a restriction an earlier run left behind is put back as the shipped rule.
 *
 * @return a function that restores the previous `default` row.
 */
export async function ensureDefaultWidgetRestriction(): Promise<() => Promise<void>> {
	const api = await adminApi()
	let prior: RoleFeaturePermissionRow | null = null
	try {
		prior = (await listRoleFeaturePermissions(api))
			.find((row) => row.groupId === 'default') ?? null
		await saveRoleFeaturePermission(api, {
			groupId: 'default',
			name: RESTRICTION_NAME,
			allowedWidgets: ['activity', 'recommendations'],
		})
	} finally {
		await api.dispose()
	}

	return async () => {
		const restoreApi = await adminApi()
		try {
			if (prior !== null && prior.name === RESTRICTION_NAME) {
				await saveRoleFeaturePermission(restoreApi, shippedDefaultRule())
				return
			}
			if (prior !== null) {
				await restoreRoleFeaturePermissions(restoreApi, [prior])
				return
			}
			const current = (await listRoleFeaturePermissions(restoreApi))
				.find((row) => row.groupId === 'default')
			if (current !== undefined) {
				await deleteRoleFeaturePermission(restoreApi, current.id)
			}
		} finally {
			await restoreApi.dispose()
		}
	}
}

/**
 * Put back the shipped `default` rule when the `default` row is a
 * restriction a killed run left behind. `ensureDefaultWidgetRestriction`
 * restores the row in its spec's `afterAll`; a run that dies before that
 * leaves every later non-admin widget add answering 403 "Access denied".
 *
 * @return true when a leftover restriction was put back.
 */
export async function healLeftoverWidgetRestriction(): Promise<boolean> {
	const api = await adminApi()
	try {
		const current = (await listRoleFeaturePermissions(api))
			.find((row) => row.groupId === 'default')
		if (current === undefined || current.name !== RESTRICTION_NAME) {
			return false
		}
		await saveRoleFeaturePermission(api, shippedDefaultRule())
		return true
	} finally {
		await api.dispose()
	}
}

/*
 * ─────────────────────────────────────────────────────────────────────────
 * Helpers for `role-feature-permissions.spec.ts`.
 *
 * The rows live in LaunchPad's own tables (`RoleFeaturePermissionMapper`,
 * `RoleLayoutDefaultMapper`), not in OpenRegister, whatever the spec's Data
 * Model says. The only way in is the admin API in
 * `RoleFeaturePermissionApiController`, so everything here goes through it,
 * with an admin context built by `support/principals.ts`.
 * ─────────────────────────────────────────────────────────────────────────
 */

const RFP_PATH = '/index.php/apps/launchpad/api/role-feature-permissions'
const RLD_PATH = '/index.php/apps/launchpad/api/role-layout-defaults'
const GROUP_ORDER_PATH = '/index.php/apps/launchpad/api/admin/groups'

/** A RoleFeaturePermission row as `GET /api/role-feature-permissions` returns it. */
export interface RoleFeaturePermissionRow {
	id: number
	name: string
	description: string | null
	groupId: string
	allowedWidgets: string[]
	deniedWidgets: string[]
	priorityWeights: Record<string, number>
}

/** A RoleLayoutDefault row as `GET /api/role-layout-defaults` returns it. */
export interface RoleLayoutDefaultRow {
	id: number
	name: string
	groupId: string
	widgetId: string
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	sortOrder: number
	isCompulsory: boolean
}

/**
 * Upsert a RoleFeaturePermission (keyed by `groupId`) and insist on the 201
 * the controller answers for both insert and update.
 */
export async function saveRoleFeaturePermission(
	admin: APIRequestContext,
	body: Partial<RoleFeaturePermissionRow> & { groupId: string },
): Promise<RoleFeaturePermissionRow> {
	const res = await admin.post(RFP_PATH, {
		data: { name: `e2e ${body.groupId}`, ...body },
	})
	if (res.status() !== 201) {
		throw new Error(
			`could not save the permission for ${body.groupId}: ${res.status()} ${await res.text()}`,
		)
	}
	return res.json()
}

/** Upsert a RoleLayoutDefault (keyed by `groupId` + `widgetId`). */
export async function saveRoleLayoutDefault(
	admin: APIRequestContext,
	body: Partial<RoleLayoutDefaultRow> & { groupId: string; widgetId: string },
): Promise<RoleLayoutDefaultRow> {
	const res = await admin.post(RLD_PATH, {
		data: { name: `e2e ${body.groupId}/${body.widgetId}`, ...body },
	})
	if (res.status() !== 201) {
		throw new Error(
			`could not save the layout default ${body.groupId}/${body.widgetId}: ${res.status()} ${await res.text()}`,
		)
	}
	return res.json()
}

/** Every RoleFeaturePermission row, read as the admin. */
export async function listRoleFeaturePermissions(
	admin: APIRequestContext,
): Promise<RoleFeaturePermissionRow[]> {
	const res = await admin.get(RFP_PATH)
	if (res.status() !== 200) {
		throw new Error(
			`could not list permissions: ${res.status()} ${await res.text()}`,
		)
	}
	return res.json()
}

/** Every RoleLayoutDefault row, read as the admin. */
export async function listRoleLayoutDefaults(
	admin: APIRequestContext,
): Promise<RoleLayoutDefaultRow[]> {
	const res = await admin.get(RLD_PATH)
	if (res.status() !== 200) {
		throw new Error(
			`could not list layout defaults: ${res.status()} ${await res.text()}`,
		)
	}
	return res.json()
}

/** Delete a RoleFeaturePermission row by id, tolerating one already gone. */
export async function deleteRoleFeaturePermission(
	admin: APIRequestContext,
	id: number,
): Promise<void> {
	await admin.delete(`${RFP_PATH}/${id}`)
}

/** Delete a RoleLayoutDefault row by id, tolerating one already gone. */
export async function deleteRoleLayoutDefault(
	admin: APIRequestContext,
	id: number,
): Promise<void> {
	await admin.delete(`${RLD_PATH}/${id}`)
}

/**
 * Put back permission rows taken with {@link listRoleFeaturePermissions}.
 * The upsert is keyed by `groupId`, so the ids change and nothing else does.
 */
export async function restoreRoleFeaturePermissions(
	admin: APIRequestContext,
	rows: RoleFeaturePermissionRow[],
): Promise<void> {
	for (const row of rows) {
		await saveRoleFeaturePermission(admin, {
			groupId: row.groupId,
			name: row.name ?? '',
			description: row.description,
			allowedWidgets: row.allowedWidgets ?? [],
			deniedWidgets: row.deniedWidgets ?? [],
			priorityWeights: row.priorityWeights ?? {},
		})
	}
}

/** The persisted `group_order` admin setting. */
export async function readGroupOrder(admin: APIRequestContext): Promise<string[]> {
	const res = await admin.get(GROUP_ORDER_PATH)
	if (res.status() !== 200) {
		throw new Error(
			`could not read group_order: ${res.status()} ${await res.text()}`,
		)
	}
	return (await res.json()).active ?? []
}

/** Replace the `group_order` admin setting wholesale. */
export async function writeGroupOrder(
	admin: APIRequestContext,
	groups: string[],
): Promise<void> {
	const res = await admin.post(GROUP_ORDER_PATH, { data: { groups } })
	if (res.status() !== 200) {
		throw new Error(
			`could not write group_order: ${res.status()} ${await res.text()}`,
		)
	}
}

/** Take a user out of a Nextcloud group. */
export async function removeFromGroup(
	admin: APIRequestContext,
	username: string,
	groupId: string,
): Promise<void> {
	const res = await admin.delete(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}/groups`,
		{ params: { groupid: groupId } },
	)
	if (!res.ok()) {
		throw new Error(
			`could not remove ${username} from ${groupId}: ${res.status()}`,
		)
	}
}
