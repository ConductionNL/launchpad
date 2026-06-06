/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared e2e fixture helper: ensure no restrictive `default`
 * role-feature-permission profile is in force, so the widget-add UI flows
 * can place any registered widget type during a test run.
 *
 * BACKGROUND / KNOWN APP BUG (flagged 2026-06-06):
 *   `RoleFeaturePermissionService::getAllowedWidgetIds()` falls back to the
 *   explicit `default` role-feature-permission row for any user whose
 *   groups have no matching profile — INCLUDING Nextcloud admins. Unlike
 *   `ActionAuthService::requireAction()` and
 *   `PermissionService::resolveAccessLevel()` (which both short-circuit for
 *   admins), `isWidgetAllowed()` has no admin bypass. With the demo-seeded
 *   `default` row (allowed: activity, recommendations only),
 *   `WidgetApiController::addWidget()` returns 403 {"error":"Access denied"}
 *   even when the admin adds a widget to their OWN personal dashboard.
 *
 *   The proper product fix is to grant NC admins the same allow-all bypass
 *   in `isWidgetAllowed()`/`getAllowedWidgetIds()`. Until that lands, the
 *   widget-add UI tests remove any restrictive `default` profile up front
 *   so the real add-widget flow can be exercised end-to-end.
 */

import { request as pwRequest, type APIRequestContext } from '@playwright/test'

const BASE = (process.env.NC_BASE_URL ?? 'http://localhost:8080').replace(/\/$/, '')
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const PERMS_URL = `${BASE}/index.php/apps/mydash/api/role-feature-permissions`

/** Build an admin API context (HTTP Basic auth + OCS header). */
async function adminApi(): Promise<APIRequestContext> {
	return pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/**
 * Remove any `default` role-feature-permission row so that
 * `getAllowedWidgetIds()` resolves to null (no restriction) for the admin.
 *
 * Idempotent: a missing `default` row is a no-op. Safe to call in
 * `beforeAll` of every widget-adding spec.
 */
export async function clearDefaultWidgetRestriction(): Promise<void> {
	const api = await adminApi()
	try {
		const listResp = await api.get(PERMS_URL)
		if (!listResp.ok()) {
			return
		}
		const perms = await listResp.json() as Array<{ id: number; groupId: string }>
		const defaults = perms.filter((p) => p.groupId === 'default')
		for (const p of defaults) {
			await api.delete(`${PERMS_URL}/${p.id}`)
		}
	} finally {
		await api.dispose()
	}
}
