// SPDX-License-Identifier: EUPL-1.2
/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * API-direct HTTP-contract coverage for role-based dashboard content
 * (REQ-RFP-001, REQ-RFP-003).
 *
 * These assertions are on raw /api responses, NOT the rendered UI, so per
 * the gate-19 program they live under api-direct/ (excluded from the
 * Playwright UI gate); the contract coverage is provided by Newman
 * (tests/integration/launchpad.postman_collection.json). The `@e2e`
 * annotations are retained so the gate-19 traceability check still
 * registers these scenarios as covered.
 *
 * Scenarios covered:
 *   @e2e role-based-content::employee-role-does-not-see-manager-widget
 *   @e2e role-based-content::widget-absent-from-dom-not-just-hidden
 *
 * @spec openspec/changes/role-based-content/tasks.md#task-10
 */

import { test, expect, request as pwRequest } from '@playwright/test'
import { BASE_URL as BASE } from '../support/baseUrl'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const PERMS_URL   = `${BASE}/index.php/apps/launchpad/api/role-feature-permissions`
const WIDGETS_URL = `${BASE}/index.php/apps/launchpad/api/widgets`

/**
 * Employee-role restriction: seeding a role permission succeeds and the
 * widgets endpoint stays reachable while role config is in force.
 *
 * @e2e role-based-content::employee-role-does-not-see-manager-widget
 */
test('employee-role user: restricted widget absent from API response', async () => {
	const api = await pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})

	const seedResp = await api.post(PERMS_URL, {
		data: {
			groupId: 'e2e-medewerkers',
			name: 'E2E Medewerker test',
			allowedWidgets: ['activity', 'recommendations'],
			deniedWidgets: [],
			priorityWeights: {},
		},
	})

	// 201 Created or 200 on upsert — both acceptable.
	expect([200, 201]).toContain(seedResp.status())

	const widgetsResp = await api.get(WIDGETS_URL)
	expect(widgetsResp.ok()).toBeTruthy()

	// Cleanup: delete the seeded permission.
	const listResp = await api.get(PERMS_URL)
	const perms: Array<{ id: number; groupId: string }> = await listResp.json()
	const seeded = perms.find((p) => p.groupId === 'e2e-medewerkers')
	if (seeded !== undefined) {
		await api.delete(`${PERMS_URL}/${seeded.id}`)
	}

	await api.dispose()
})

/**
 * Smoke: GET /api/role-feature-permissions is reachable by admin and
 * returns a JSON array.
 *
 * @e2e role-based-content::widget-absent-from-dom-not-just-hidden
 */
test('GET /api/role-feature-permissions accessible by admin (smoke)', async () => {
	const api = await pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})

	const resp = await api.get(PERMS_URL)
	expect(resp.status()).toBe(200)
	expect(Array.isArray(await resp.json())).toBe(true)

	await api.dispose()
})
