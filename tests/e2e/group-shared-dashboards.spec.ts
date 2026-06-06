/*
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * E2E tests for multi-scope-dashboards (REQ-DASH-011..014).
 *
 * Scenarios covered:
 *   T1 — Admin creates a group-shared dashboard via API; group member
 *        sees it in GET /api/dashboards/visible.
 *   T2 — User with zero group memberships still sees default-group rows
 *        in GET /api/dashboards/visible.
 *   T3 — Non-admin PUT to a group-shared dashboard returns 403.
 *   T4 — Admin renames a group-shared dashboard; the update propagates
 *        to GET /api/dashboards/visible on the next call.
 *
 * Prerequisites (fulfilled by the e2e docker fixture):
 *   - NC admin user: admin / admin
 *   - NC regular user: member / member  (member of group "e2e-test-group")
 *   - NC regular user: nonmember / nonmember  (member of no groups)
 *
 * Run manually:
 *   NC_BASE_URL=http://localhost:8080 \
 *   NC_ADMIN_USER=admin NC_ADMIN_PASS=admin \
 *   NC_MEMBER_USER=member NC_MEMBER_PASS=member \
 *   NC_NONMEMBER_USER=nonmember NC_NONMEMBER_PASS=nonmember \
 *     npx playwright test tests/e2e/group-shared-dashboards.spec.ts
 */

import { test, expect, request as pwRequest, type APIRequestContext, type APIResponse } from '@playwright/test'

// ─── helpers ──────────────────────────────────────────────────────────────────

const BASE  = (process.env.NC_BASE_URL       ?? 'http://localhost:8080').replace(/\/$/, '')
const ADMIN = { user: process.env.NC_ADMIN_USER      ?? 'admin',     pass: process.env.NC_ADMIN_PASS      ?? 'admin'     }
const MEMB  = { user: process.env.NC_MEMBER_USER     ?? 'member',    pass: process.env.NC_MEMBER_PASS     ?? 'member'    }
const NOMEM = { user: process.env.NC_NONMEMBER_USER  ?? 'nonmember', pass: process.env.NC_NONMEMBER_PASS  ?? 'nonmember' }

type Creds = { user: string; pass: string }

/** Make an API request context authenticated as the given user. */
async function apiAs(creds: Creds): Promise<APIRequestContext> {
	return pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: creds.user, password: creds.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/** Strip Nextcloud OCS envelope and return the inner `ocs.data` object. */
async function ocsData(res: APIResponse): Promise<unknown> {
	const body = await res.json().catch(() => ({})) as Record<string, unknown>
	const ocs = body.ocs as Record<string, unknown> | undefined
	return ocs?.data ?? body
}

// ─── fixtures ─────────────────────────────────────────────────────────────────

/** UUID of the group-shared dashboard created by T1, reused by T4. */
let createdUuid: string

const GROUP_ID = 'e2e-test-group'

// ─── tests ────────────────────────────────────────────────────────────────────

test.describe('Group-shared dashboards (REQ-DASH-011..014)', () => {

	// T1 ── admin creates, member sees it in /visible
	test('T1: admin creates group-shared dashboard and member sees it in /visible', async () => {
		const adminCtx  = await apiAs(ADMIN)
		const memberCtx = await apiAs(MEMB)

		// Admin creates a group-shared dashboard in GROUP_ID.
		const createRes = await adminCtx.post(
			`${BASE}/index.php/apps/mydash/api/dashboards/group/${GROUP_ID}`,
			{ data: { name: 'E2E Marketing Overview' } },
		)
		expect(createRes.status(), `POST /group/${GROUP_ID} should be 201`).toBe(201)

		const createBody = await createRes.json() as Record<string, unknown>
		const created = (createBody as { data?: { dashboard?: { uuid?: string } } }).data?.dashboard
		expect(created?.uuid, 'Created dashboard must have a UUID').toBeTruthy()
		createdUuid = created!.uuid as string

		// Member (in GROUP_ID) calls /visible and expects to find the new dashboard.
		const visibleRes = await memberCtx.get(`${BASE}/index.php/apps/mydash/api/dashboards/visible`)
		expect(visibleRes.status(), 'GET /visible should be 200 for member').toBe(200)

		const visibleBody = await visibleRes.json() as { data?: Array<{ uuid: string; source: string }> }
		const visibleList = visibleBody.data ?? []

		const found = visibleList.find(d => d.uuid === createdUuid)
		expect(found, `Dashboard ${createdUuid} must appear in member's /visible`).toBeTruthy()
		expect(found?.source, 'Source must be "group" for group-matched rows').toBe('group')

		await adminCtx.dispose()
		await memberCtx.dispose()
	})

	// T2 ── 0-group user still sees default-group rows
	test('T2: user with zero group memberships sees default-group dashboards in /visible', async () => {
		const adminCtx  = await apiAs(ADMIN)
		const nomemCtx  = await apiAs(NOMEM)

		// Admin creates a default-group dashboard.
		const createRes = await adminCtx.post(
			`${BASE}/index.php/apps/mydash/api/dashboards/group/default`,
			{ data: { name: 'E2E Welcome Dashboard' } },
		)
		expect(createRes.status(), 'POST /group/default should be 201').toBe(201)

		const createBody = await createRes.json() as { data?: { dashboard?: { uuid?: string } } }
		const defaultUuid = createBody.data?.dashboard?.uuid
		expect(defaultUuid, 'Default-group dashboard must have a UUID').toBeTruthy()

		// Nonmember (in no groups) calls /visible and expects the default-group row.
		const visibleRes = await nomemCtx.get(`${BASE}/index.php/apps/mydash/api/dashboards/visible`)
		expect(visibleRes.status(), 'GET /visible should be 200 for nonmember').toBe(200)

		const visibleBody = await visibleRes.json() as { data?: Array<{ uuid: string; source: string }> }
		const found = (visibleBody.data ?? []).find(d => d.uuid === defaultUuid)
		expect(found, `Default-group dashboard ${defaultUuid} must appear in nonmember's /visible`).toBeTruthy()
		expect(found?.source, 'Source must be "default" for default-group rows').toBe('default')

		// Clean up.
		await adminCtx.delete(`${BASE}/index.php/apps/mydash/api/dashboards/group/default/${defaultUuid}`)
		await adminCtx.dispose()
		await nomemCtx.dispose()
	})

	// T3 ── non-admin PUT returns 403
	test('T3: non-admin PUT to group-shared dashboard returns 403', async () => {
		// Ensure the dashboard from T1 was created before this test runs.
		test.skip(!createdUuid, 'T1 must run first to set createdUuid')

		const memberCtx = await apiAs(MEMB)

		const putRes = await memberCtx.put(
			`${BASE}/index.php/apps/mydash/api/dashboards/group/${GROUP_ID}/${createdUuid}`,
			{ data: { name: 'Attempted rename by non-admin' } },
		)
		expect(putRes.status(), 'Non-admin PUT should return 403').toBe(403)

		await memberCtx.dispose()
	})

	// T4 ── admin rename propagates to member on next /visible
	test('T4: admin rename propagates to member on next /visible call', async () => {
		test.skip(!createdUuid, 'T1 must run first to set createdUuid')

		const adminCtx  = await apiAs(ADMIN)
		const memberCtx = await apiAs(MEMB)

		const NEW_NAME = 'E2E Renamed Dashboard'

		// Admin updates the name.
		const putRes = await adminCtx.put(
			`${BASE}/index.php/apps/mydash/api/dashboards/group/${GROUP_ID}/${createdUuid}`,
			{ data: { name: NEW_NAME } },
		)
		expect(putRes.status(), 'Admin PUT should return 200').toBe(200)

		// Member fetches /visible — the rename must be visible on next load.
		const visibleRes = await memberCtx.get(`${BASE}/index.php/apps/mydash/api/dashboards/visible`)
		expect(visibleRes.status()).toBe(200)

		const visibleBody = await visibleRes.json() as { data?: Array<{ uuid: string; name: string }> }
		const found = (visibleBody.data ?? []).find(d => d.uuid === createdUuid)
		expect(found?.name, 'Renamed dashboard must have the new name in /visible').toBe(NEW_NAME)

		// Tear down the dashboard created by T1.
		await adminCtx.delete(
			`${BASE}/index.php/apps/mydash/api/dashboards/group/${GROUP_ID}/${createdUuid}`,
		)

		await adminCtx.dispose()
		await memberCtx.dispose()
	})

})
