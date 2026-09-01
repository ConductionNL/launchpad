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

import { request as pwRequest } from '@playwright/test'
import { BASE_URL as BASE } from '../support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const PERMS_URL = `${BASE}/index.php/apps/launchpad/api/role-feature-permissions`

/** Build an admin API context (HTTP Basic auth + OCS header). */
async function adminApi(): Promise<APIRequestContext> {
	return pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/**
 * Ensure a RESTRICTIVE `default` role-feature-permission row is in force
 * (allowed: activity + recommendations only). Combined with the admin
 * break-glass bypass, this lets the widget-add specs prove that an NC admin
 * can still add ANY widget type to their own dashboard despite the
 * restriction — exercising the real fix rather than side-stepping it.
 *
 * Idempotent: upserts the `default` row by groupId. Safe to call in
 * `beforeAll` of every widget-adding spec.
 */
export async function ensureDefaultWidgetRestriction(): Promise<void> {
	const api = await adminApi()
	try {
		await api.post(PERMS_URL, {
			data: {
				groupId: 'default',
				name: 'Default (e2e restriction)',
				allowedWidgets: ['activity', 'recommendations'],
			},
		})
	} finally {
		await api.dispose()
	}
}
