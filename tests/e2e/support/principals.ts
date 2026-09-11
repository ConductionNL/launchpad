/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Principals for the access-control specs: real accounts, real groups, real
 * LaunchPad role assignments, and a request context per principal.
 *
 * 🔴 WHY A REFUSAL NEEDS A PRINCIPAL, NOT A LOGGED-OUT REQUEST. An anonymous
 * request is refused by Nextcloud before LaunchPad is reached, so it proves
 * nothing about LaunchPad's own authorization. Every refusal in these specs is
 * therefore probed by the LEAST privileged principal that should still be
 * refused: an ordinary signed-in user, or, where roles are involved, a member
 * of the group who lacks the role. A superuser succeeding says nothing about
 * whether anyone else is kept out.
 *
 * 🔴 WHY ACCOUNTS ARE PROVISIONED OVER BASIC AUTH WITH AN EXPLICIT HEADER.
 * Nextcloud expires password confirmation thirty minutes after login, so a
 * `beforeAll` that creates accounts through the admin's browser SESSION starts
 * answering 403 purely because the suite ran late in a long run. Basic auth
 * carries the password on every call and never expires, so provisioning is
 * independent of how long the suite has been running.
 */

import type { APIRequestContext } from '@playwright/test'

import { request as pwRequest } from '@playwright/test'
import { BASE_URL as BASE } from './baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

/** The LaunchPad API root, as every spec spells it. */
export const API = '/index.php/apps/launchpad/api'

/** One principal: an account plus a request context that acts as it. */
export interface Principal {
	username: string
	password: string
	api: APIRequestContext
}

/** A request context acting as `username`, over basic auth. */
export async function contextFor(
	username: string,
	password: string,
): Promise<APIRequestContext> {
	return pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username, password },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/** A request context acting as the Nextcloud admin. */
export async function adminContext(): Promise<APIRequestContext> {
	return contextFor(ADMIN.user, ADMIN.pass)
}

/**
 * Create a throwaway account and a context that acts as it.
 *
 * @param api an admin context (provisioning is admin-only).
 * @param prefix a readable prefix; a stamp keeps parallel runs apart.
 * @return the principal, ready to probe with.
 */
export async function createPrincipal(
	api: APIRequestContext,
	prefix: string,
): Promise<Principal> {
	const username = `${prefix}-${Date.now()}-${Math.floor(Math.random() * 10_000)}`
	const password = `Probe-${Math.random().toString(36).slice(2)}A1!`

	const res = await api.post('/ocs/v1.php/cloud/users', {
		form: { userid: username, password },
	})
	if (!res.ok()) {
		throw new Error(`could not provision ${username}: ${res.status()}`)
	}

	return { username, password, api: await contextFor(username, password) }
}

/** Delete an account, tolerating one that is already gone. */
export async function removePrincipal(
	api: APIRequestContext,
	principal: Principal | null,
): Promise<void> {
	if (principal === null) {
		return
	}
	await principal.api.dispose()
	await api.delete(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(principal.username)}`,
	)
}

/** Create a Nextcloud group. */
export async function createGroup(
	api: APIRequestContext,
	groupId: string,
): Promise<string> {
	const res = await api.post('/ocs/v1.php/cloud/groups', {
		form: { groupid: groupId },
	})
	if (!res.ok()) {
		throw new Error(`could not create group ${groupId}: ${res.status()}`)
	}
	return groupId
}

/** Put a user in a group. */
export async function addToGroup(
	api: APIRequestContext,
	username: string,
	groupId: string,
): Promise<void> {
	const res = await api.post(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}/groups`,
		{ form: { groupid: groupId } },
	)
	if (!res.ok()) {
		throw new Error(
			`could not add ${username} to ${groupId}: ${res.status()}`,
		)
	}
}

/** Delete a group, tolerating one that is already gone. */
export async function removeGroup(
	api: APIRequestContext,
	groupId: string | null,
): Promise<void> {
	if (groupId === null) {
		return
	}
	await api.delete(`/ocs/v1.php/cloud/groups/${encodeURIComponent(groupId)}`)
}

/**
 * Assign a LaunchPad role to a user or a group.
 *
 * @param api an admin context.
 * @param target exactly one of `userId` or `groupId`, plus the role.
 * @return the assignment id, for cleanup.
 */
export async function assignRole(
	api: APIRequestContext,
	target: { userId?: string; groupId?: string; role: string },
): Promise<number> {
	const res = await api.post(`${API}/admin/roles`, { data: target })
	if (res.status() !== 201) {
		throw new Error(
			`could not assign ${target.role}: ${res.status()} ${await res.text()}`,
		)
	}
	const body = await res.json()
	return Number(body.id ?? body.data?.id ?? body.assignment?.id)
}

/** Remove a role assignment, tolerating one that is already gone. */
export async function removeRole(
	api: APIRequestContext,
	id: number | null,
): Promise<void> {
	if (id === null || Number.isNaN(id)) {
		return
	}
	await api.delete(`${API}/admin/roles/${id}`)
}

/**
 * Create a dashboard owned by the admin, with one text widget on it.
 *
 * The admin owns it so the specs can share it at a chosen level: a user's own
 * dashboard is always `full`, so a restricted level has to come from
 * somewhere else.
 *
 * @param api an admin context.
 * @param name the dashboard name.
 * @return its id, uuid and the placement id of its widget.
 */
export async function seedDashboardWithWidget(
	api: APIRequestContext,
	name: string,
): Promise<{ id: number; uuid: string; placementId: number }> {
	const created = await api.post(`${API}/dashboard`, { data: { name } })
	if (created.status() >= 300) {
		throw new Error(`could not create ${name}: ${await created.text()}`)
	}
	const dashboard = (await created.json()).dashboard ?? {}

	const widget = await api.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			content: { text: 'seeded', contentMode: 'markdown' },
		},
	})
	if (widget.status() >= 300) {
		throw new Error(`could not seed a widget: ${await widget.text()}`)
	}
	const placement = await widget.json()

	return {
		id: Number(dashboard.id),
		uuid: String(dashboard.uuid),
		placementId: Number(placement.id ?? placement.data?.id),
	}
}

/**
 * Share a dashboard with a user at a permission level.
 *
 * @param api an admin context (the owner).
 * @param dashboardId the dashboard.
 * @param username the recipient.
 * @param permissionLevel `view_only`, `add_only` or `full`.
 * @return nothing; throws when the share is refused.
 */
export async function shareWith(
	api: APIRequestContext,
	dashboardId: number,
	username: string,
	permissionLevel: string,
): Promise<void> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/shares`, {
		data: { shareType: 'user', shareWith: username, permissionLevel },
	})
	if (res.status() >= 300) {
		throw new Error(
			`could not share ${dashboardId} with ${username} at ${permissionLevel}: `
				+ `${res.status()} ${await res.text()}`,
		)
	}
}

/** Allow personal dashboards, which several refusals depend on NOT being the cause. */
export async function allowPersonalDashboards(
	api: APIRequestContext,
	allowed: boolean,
): Promise<void> {
	const res = await api.put(`${API}/admin/settings`, {
		data: { allowUserDash: allowed, allowMultiDash: true },
	})
	if (res.status() !== 200) {
		throw new Error(`could not set allowUserDashboards: ${res.status()}`)
	}
}
