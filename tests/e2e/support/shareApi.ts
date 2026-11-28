/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Small, real-API helpers for the dashboard-sharing specs.
 *
 * Every call goes through the routes in `appinfo/routes.php`
 * (`dashboardApi#*`, `dashboardShareApi#*`, `admin#*`,
 * `adminCleanup#purge`), with the principal the caller passes in. None of them
 * asserts the outcome of the call it makes except where the helper's name
 * says it must succeed: the specs assert statuses themselves.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'
import { API } from './principals.ts'

/** A dashboard a spec created, with what it needs to find it again. */
export interface OwnDashboard {
	id: number
	uuid: string
	name: string
}

/** The dashboard-sharing admin settings a spec may change and must put back. */
export interface SharingSettings {
	allowUserDashboards: boolean
	sharingAllowedGroups: string[]
	sharingRecipientScope: string
}

/**
 * Read the admin settings this family of specs touches.
 *
 * @param admin an admin context.
 * @return the current values.
 */
export async function readSharingSettings(
	admin: APIRequestContext,
): Promise<SharingSettings> {
	const res = await admin.get(`${API}/admin/settings`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return {
		allowUserDashboards: body.allowUserDashboards === true,
		sharingAllowedGroups: Array.isArray(body.sharingAllowedGroups)
			? body.sharingAllowedGroups.map(String)
			: [],
		sharingRecipientScope:
			typeof body.sharingRecipientScope === 'string'
				? body.sharingRecipientScope
				: 'everyone',
	}
}

/**
 * Write the sharing admin settings, failing loudly when refused.
 *
 * @param admin an admin context.
 * @param settings the values to write; absent keys are left as they are.
 * @return nothing.
 */
export async function writeSharingSettings(
	admin: APIRequestContext,
	settings: Partial<SharingSettings>,
): Promise<void> {
	const data: Record<string, unknown> = {}
	if (settings.allowUserDashboards !== undefined) {
		data.allowUserDash = settings.allowUserDashboards
	}
	if (settings.sharingAllowedGroups !== undefined) {
		data.sharingAllowedGroups = settings.sharingAllowedGroups
	}
	if (settings.sharingRecipientScope !== undefined) {
		data.sharingRecipientScope = settings.sharingRecipientScope
	}
	const res = await admin.put(`${API}/admin/settings`, { data })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Create a personal dashboard as the caller and make it their active one.
 *
 * Personal dashboards must be allowed first (see `writeSharingSettings`).
 *
 * @param api a context acting as the future owner.
 * @param name the dashboard name.
 * @return its id, uuid and name.
 */
export async function createOwnDashboard(
	api: APIRequestContext,
	name: string,
): Promise<OwnDashboard> {
	const created = await api.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const id = Number(dash.id)
	expect(id, `no dashboard id in ${JSON.stringify(body)}`).toBeTruthy()

	const activate = await api.post(`${API}/dashboard/${id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const preference = await api.post(`${API}/dashboards/default`, {
		data: { uuid: dash.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)

	return { id, uuid: String(dash.uuid), name }
}

/**
 * Add `count` widgets to a dashboard, stacked so they never collide.
 *
 * `activity` is used because `ensureDefaultWidgetRestriction()` allows it for
 * plain accounts; see the ALLOWED_WIDGET note in `ci/dashboard-share-api.spec.ts`.
 *
 * @param api a context that may add widgets (the owner).
 * @param dashboardId the dashboard.
 * @param count how many.
 * @return nothing; throws when one is refused.
 */
export async function addWidgets(
	api: APIRequestContext,
	dashboardId: number,
	count: number,
): Promise<void> {
	for (let i = 0; i < count; i++) {
		const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
			data: {
				widgetId: 'activity',
				gridX: 0,
				gridY: i * 2,
				gridWidth: 4,
				gridHeight: 2,
			},
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
	}
}

/**
 * POST one share. Returns the raw response so a refusal can be asserted.
 *
 * @param api a context acting as the caller.
 * @param dashboardId the dashboard.
 * @param shareType `user` or `group`.
 * @param shareWith the recipient id.
 * @param permissionLevel `view_only`, `add_only` or `full`.
 * @return the response.
 */
export async function postShare(
	api: APIRequestContext,
	dashboardId: number,
	shareType: 'user' | 'group',
	shareWith: string,
	permissionLevel: string,
) {
	return api.post(`${API}/dashboard/${dashboardId}/shares`, {
		data: { shareType, shareWith, permissionLevel },
	})
}

/**
 * POST one share and require the 201 the controller answers on success.
 *
 * @param api a context acting as the owner.
 * @param dashboardId the dashboard.
 * @param shareType `user` or `group`.
 * @param shareWith the recipient id.
 * @param permissionLevel `view_only`, `add_only` or `full`.
 * @return the serialized share.
 */
export async function share(
	api: APIRequestContext,
	dashboardId: number,
	shareType: 'user' | 'group',
	shareWith: string,
	permissionLevel: string,
): Promise<Record<string, unknown>> {
	const res = await postShare(
		api,
		dashboardId,
		shareType,
		shareWith,
		permissionLevel,
	)
	expect(res.status(), await res.text()).toBe(201)
	return res.json()
}

/**
 * The share rows of a dashboard, as its owner reads them.
 *
 * @param api a context acting as the owner.
 * @param dashboardId the dashboard.
 * @return the rows.
 */
export async function listShares(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Array<Record<string, unknown>>> {
	const res = await api.get(`${API}/dashboard/${dashboardId}/shares`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return Array.isArray(body) ? body : (body.data ?? [])
}

/** `shareType:shareWith:permissionLevel` per row, sorted, for exact comparison. */
export function shareTuples(rows: Array<Record<string, unknown>>): string[] {
	return rows
		.map((r) => `${r.shareType}:${r.shareWith}:${r.permissionLevel}`)
		.sort()
}

/**
 * How many orphaned rows one cleanup category counts right now.
 *
 * Read through a DRY-RUN purge, not the scan, because the scan serves a
 * cached result for 300 seconds; see `orphanedShareCount()` in
 * `ci/dashboard-share-api.spec.ts`. A dry run counts live inside a rolled-back
 * transaction and deletes nothing.
 *
 * @param admin an admin context.
 * @param category `expired_share_tokens` (share rows without a dashboard) or
 *   `orphaned_widget_placements` (placements without a dashboard).
 * @return the count.
 */
export async function orphanCount(
	admin: APIRequestContext,
	category: 'expired_share_tokens' | 'orphaned_widget_placements',
): Promise<number> {
	const res = await admin.post(`${API}/admin/cleanup/purge`, {
		data: { categories: [category], dryRun: true },
	})
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	expect(body.dryRun, 'the orphan count must come from a dry run').toBe(true)
	return Number(body.purgedByCategory?.[category] ?? 0)
}

/**
 * Delete a Nextcloud account as the admin, and require that it went.
 *
 * Deleting an account is what fires `UserDeletedEvent`, which LaunchPad's
 * `UserDeletedListener` handles in the same request, so the effects are
 * readable as soon as this returns.
 *
 * @param admin an admin context.
 * @param username the account.
 * @return nothing.
 */
export async function deleteAccount(
	admin: APIRequestContext,
	username: string,
): Promise<void> {
	const res = await admin.delete(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}?format=json`,
	)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	expect(body.ocs?.meta?.statuscode, JSON.stringify(body)).toBe(100)
}

/**
 * Wait just over one second, so the next row gets a later `created_at`.
 *
 * `created_at` is stored as `Y-m-d H:i:s`, so two shares written inside the
 * same second tie, and an ordering by it would be decided by the database.
 *
 * @return nothing.
 */
export async function nextSecond(): Promise<void> {
	await new Promise((resolve) => setTimeout(resolve, 1_100))
}
