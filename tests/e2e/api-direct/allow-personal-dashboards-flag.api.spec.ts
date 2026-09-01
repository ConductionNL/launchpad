/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * API-direct HTTP-contract coverage for the allow_user_dashboards runtime
 * flag (REQ-ASET-003 extended, REQ-ASET-015) — the defense-in-depth 403
 * enforcement that fires server-side regardless of the UI.
 *
 * These assertions are on raw /api responses (status codes + JSON error
 * envelope), NOT the rendered UI, so per the gate-19 program they live
 * under api-direct/ (excluded from the Playwright UI gate) and their
 * contract coverage is provided by Newman
 * (tests/integration/launchpad.postman_collection.json). The `@e2e`
 * annotations are retained so the gate-19 traceability check still
 * registers these scenarios as covered.
 *
 * Scenarios covered:
 *   @e2e allow-personal-dashboards-flag::direct-create-post-returns-403
 *   @e2e allow-personal-dashboards-flag::direct-fork-post-returns-403
 *
 * @spec openspec/changes/allow-personal-dashboards-flag/tasks.md#task-3.5
 */

import type { request } from '@playwright/test'
import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from '../support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const SETTINGS_URL = `${BASE}/index.php/apps/launchpad/api/admin/settings`
const CREATE_URL = `${BASE}/index.php/apps/launchpad/api/dashboard`
function FORK_URL(uuid: string) {
	return `${BASE}/index.php/apps/launchpad/api/dashboards/${encodeURIComponent(uuid)}/fork`
}

/** Make an authenticated request context using HTTP Basic auth. */
async function adminApi(playwright: {
	request: { newContext: typeof request.newContext }
}): Promise<APIRequestContext> {
	return playwright.request.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/** Toggle the allow_user_dashboards admin flag. */
async function setAllowUserDashboards(
	api: APIRequestContext,
	enabled: boolean,
): Promise<void> {
	const res = await api.put(SETTINGS_URL, { data: { allowUserDash: enabled } })
	if (!res.ok()) {
		console.warn(
			`setAllowUserDashboards(${enabled}): PUT returned ${res.status()}`,
		)
	}
}

test.describe('allow-personal-dashboards-flag — API-level 403 enforcement', () => {
	let api: APIRequestContext

	test.beforeAll(async ({ playwright }) => {
		api = await adminApi({ request: playwright.request })
		// Disable the flag for all tests in this group.
		await setAllowUserDashboards(api, false)
	})

	test.afterAll(async () => {
		// Restore to enabled so other specs are not affected.
		await setAllowUserDashboards(api, true)
		await api.dispose()
	})

	// @e2e allow-personal-dashboards-flag::direct-create-post-returns-403
	test('POST /api/dashboard returns 403 with personal_dashboards_disabled when flag is off', async () => {
		const res = await api.post(CREATE_URL, {
			data: { name: 'E2E Test Dashboard' },
		})

		// Flag is off — creation MUST be blocked.
		expect(res.status()).toBe(403)

		const body = (await res.json()) as Record<string, unknown>
		const payload = (body.data ?? body) as Record<string, unknown>
		expect(payload.error).toBe('personal_dashboards_disabled')
		expect(payload.status).toBe('error')
	})

	// @e2e allow-personal-dashboards-flag::direct-fork-post-returns-403
	test('POST /api/dashboards/{uuid}/fork returns 403 with personal_dashboards_disabled when flag is off', async () => {
		// The flag check in forkAsPersonal() fires BEFORE the existence check
		// for the source UUID, so any UUID triggers the 403 when the flag is off.
		const fakeUuid = '00000000-0000-0000-0000-000000000001'

		const res = await api.post(FORK_URL(fakeUuid), { data: {} })

		// Flag is off — fork MUST be blocked regardless of UUID.
		expect(res.status()).toBe(403)

		const body = (await res.json()) as Record<string, unknown>
		const payload = (body.data ?? body) as Record<string, unknown>
		expect(payload.error).toBe('personal_dashboards_disabled')
		expect(payload.status).toBe('error')
	})
})
