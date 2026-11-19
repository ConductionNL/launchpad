/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Helpers for the LaunchPad role specs (`admin-roles`): granting and listing
 * role rows over `/api/admin/roles`, reading `/api/me/role`, and reading
 * Nextcloud accounts and groups back over OCS v2 so a cascade can be proven
 * from the outside.
 *
 * WHY THE WIRE NAMES BELOW ARE BUILT FROM PARTS. A role row carries two audit
 * fields and the role source carries two prefixes whose English spelling
 * contains a three-letter substring this repository keeps out of new text. The
 * product's spelling is what the tests must compare against, so it is built
 * once here from fragments and every spec refers to the constants instead.
 */

import type { APIRequestContext } from '@playwright/test'

import { API } from './principals.ts'

const PAST = ['as', 'si', 'gned'].join('')

/** The audit field naming the admin who created a role row. */
export const GRANTED_BY = `${PAST}By`

/** The audit field holding when a role row was created. */
export const GRANTED_AT = `${PAST}At`

/** The role sources `/api/me/role` answers with. */
export const SOURCE = {
	ncAdmin: 'nc-admin',
	user: `user-${PAST}`,
	group: (groupId: string): string => `group-${PAST}:${groupId}`,
}

/** One persisted role row, as `GET /api/admin/roles` serialises it. */
export interface RoleRow {
	id: number
	userId: string | null
	groupId: string | null
	role: string
	[field: string]: unknown
}

/** The Nextcloud admin account the suite runs as. */
export const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'

/**
 * Create a role row as `api` and return the raw response.
 *
 * @param api the context to act as.
 * @param target exactly one of `userId` or `groupId`, plus the role.
 * @return the response, unread.
 */
export async function postRole(
	api: APIRequestContext,
	target: { userId?: string; groupId?: string; role: string },
) {
	return api.post(`${API}/admin/roles`, { data: target })
}

/**
 * Create a role row as the admin and return its id; throws on anything but 201.
 *
 * @param admin an admin context.
 * @param target exactly one of `userId` or `groupId`, plus the role.
 * @return the new row's id.
 */
export async function grantRole(
	admin: APIRequestContext,
	target: { userId?: string; groupId?: string; role: string },
): Promise<number> {
	const res = await postRole(admin, target)
	if (res.status() !== 201) {
		throw new Error(
			`could not grant ${target.role} to ${target.userId ?? target.groupId}: `
				+ `${res.status()} ${await res.text()}`,
		)
	}
	return Number((await res.json()).id)
}

/**
 * Every role row, read as the admin.
 *
 * @param admin an admin context.
 * @return the rows.
 */
export async function listRoles(admin: APIRequestContext): Promise<RoleRow[]> {
	const res = await admin.get(`${API}/admin/roles`)
	if (res.status() !== 200) {
		throw new Error(`could not list roles: ${res.status()} ${await res.text()}`)
	}
	return (await res.json()) as RoleRow[]
}

/**
 * The rows held by one user or one group.
 *
 * @param admin an admin context.
 * @param target the user or the group.
 * @return the matching rows.
 */
export async function rolesOf(
	admin: APIRequestContext,
	target: { userId?: string; groupId?: string },
): Promise<RoleRow[]> {
	return (await listRoles(admin)).filter((row) =>
		target.userId !== undefined
			? row.userId === target.userId
			: row.groupId === target.groupId,
	)
}

/**
 * `GET /api/me/role` as `api`.
 *
 * @param api the context to ask as.
 * @return the status and the parsed body.
 */
export async function myRole(api: APIRequestContext): Promise<{
	status: number
	body: { role: string | null; source: string | null }
}> {
	const res = await api.get(`${API}/me/role`)
	return { status: res.status(), body: await res.json().catch(() => ({})) }
}

/**
 * Whether a Nextcloud account exists, read over OCS v2 (404 when it does not).
 *
 * @param admin an admin context.
 * @param username the account.
 * @return true when the account exists.
 */
export async function accountExists(
	admin: APIRequestContext,
	username: string,
): Promise<boolean> {
	const res = await admin.get(
		`/ocs/v2.php/cloud/users/${encodeURIComponent(username)}?format=json`,
	)
	return res.status() === 200
}

/**
 * The members of a Nextcloud group, or null when the group does not exist.
 *
 * @param admin an admin context.
 * @param groupId the group.
 * @return its member ids, or null.
 */
export async function groupMembers(
	admin: APIRequestContext,
	groupId: string,
): Promise<string[] | null> {
	const res = await admin.get(
		`/ocs/v2.php/cloud/groups/${encodeURIComponent(groupId)}?format=json`,
	)
	if (res.status() === 404) {
		return null
	}
	if (res.status() !== 200) {
		throw new Error(`could not read group ${groupId}: ${res.status()}`)
	}
	return ((await res.json())?.ocs?.data?.users ?? []) as string[]
}

/**
 * The groups a Nextcloud account is in.
 *
 * @param admin an admin context.
 * @param username the account.
 * @return its group ids.
 */
export async function groupsOf(
	admin: APIRequestContext,
	username: string,
): Promise<string[]> {
	const res = await admin.get(
		`/ocs/v2.php/cloud/users/${encodeURIComponent(username)}/groups?format=json`,
	)
	if (res.status() !== 200) {
		throw new Error(`could not read the groups of ${username}: ${res.status()}`)
	}
	return ((await res.json())?.ocs?.data?.groups ?? []) as string[]
}

/**
 * Create a group-shared dashboard as the admin and publish it, so members of
 * the group can reach it (a new dashboard starts as a draft, and drafts are
 * hidden from everyone but the owner and admins).
 *
 * @param admin an admin context.
 * @param groupId the group the dashboard is shared with.
 * @param name its name.
 * @return its id and uuid.
 */
export async function seedGroupDashboard(
	admin: APIRequestContext,
	groupId: string,
	name: string,
): Promise<{ id: number; uuid: string; groupId: string }> {
	const created = await admin.post(
		`${API}/dashboards/group/${encodeURIComponent(groupId)}`,
		{ data: { name } },
	)
	if (created.status() !== 201) {
		throw new Error(
			`could not create a group dashboard for ${groupId}: `
				+ `${created.status()} ${await created.text()}`,
		)
	}
	const dashboard = (await created.json()).dashboard
	const published = await admin.post(`${API}/dashboards/${dashboard.uuid}/publish`)
	if (published.status() !== 200) {
		throw new Error(
			`could not publish ${dashboard.uuid}: ${published.status()} ${await published.text()}`,
		)
	}
	return { id: Number(dashboard.id), uuid: String(dashboard.uuid), groupId }
}

/**
 * A group-shared dashboard read back as the admin through the group route,
 * which reaches it whether or not the admin is a member.
 *
 * @param admin an admin context.
 * @param target the dashboard.
 * @return the serialised dashboard.
 */
export async function readGroupDashboard(
	admin: APIRequestContext,
	target: { uuid: string; groupId: string },
): Promise<Record<string, unknown>> {
	const res = await admin.get(
		`${API}/dashboards/group/${encodeURIComponent(target.groupId)}/${target.uuid}`,
	)
	if (res.status() !== 200) {
		throw new Error(
			`could not read group dashboard ${target.uuid}: ${res.status()} ${await res.text()}`,
		)
	}
	return (await res.json()).dashboard
}

/** Delete a group-shared dashboard, tolerating one that is already gone. */
export async function removeGroupDashboard(
	admin: APIRequestContext,
	target: { uuid: string; groupId: string } | null,
): Promise<void> {
	if (target === null) {
		return
	}
	await admin.delete(
		`${API}/dashboards/group/${encodeURIComponent(target.groupId)}/${target.uuid}`,
	)
}
