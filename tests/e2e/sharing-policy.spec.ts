/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Administrators can stop sharing and limit public links (dashboard-sharing
 * REQ-SHARE-015, board LpBeheerDelen), through the HTTP contract:
 * `PUT /api/admin/sharing-policy` sets the policy, the share and public-link
 * create routes enforce it, and Pieter's Sharing tab offers what the policy
 * allows (the workspace initial state carries it).
 *
 * The policy is read first and restored in afterAll.
 *
 * Written from the change's scenarios; runs in the nightly against a live
 * instance (it is not run in the cloud sandbox).
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-sharing::nobody-may-share
 *   @e2e dashboard-sharing::a-public-link-needs-an-expiry-date
 *   @e2e dashboard-sharing::public-links-off
 *   @e2e dashboard-sharing::pieter-leaves
 *   @e2e dashboard-sharing::no-manager
 *   @e2e dashboard-sharing::no-setting
 *
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md
 */

import type { APIRequestContext, Browser } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, request, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { visibleDashboards } from './support/adminTemplates.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { openDashboardSettings, switchToDashboard } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
} from './support/principals.ts'
import {
	createOwnDashboard,
	deleteAccount,
	listShares,
	readSharingSettings,
	share,
	shareTuples,
	writeSharingSettings,
} from './support/shareApi.ts'

const RUN = Date.now()
const FAR_EXPIRY = '2099-12-31T23:59:59Z'

let admin: APIRequestContext
let pieter: Principal | null = null
let ella: Principal | null = null
let before: { sharingAllowed: string; publicLinksAllowed: boolean } | null = null
let dashboard: { id: number; uuid: string } | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/** Set the sharing policy as the administrator. */
async function setPolicy(data: Record<string, unknown>): Promise<void> {
	const res = await admin.put(`${API}/admin/sharing-policy`, { data })
	expect(res.status(), await res.text()).toBe(200)
}

test.beforeAll(async () => {
	admin = await adminContext()
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status(), 'the admin settings must be readable').toBe(200)
	const body = await settings.json()
	const data = body.data ?? body
	before = {
		sharingAllowed: data.sharingAllowed ?? 'everyone',
		publicLinksAllowed: data.publicLinksAllowed !== false,
	}
	await allowPersonalDashboards(admin, true)

	pieter = await createPrincipal(admin, 'e2e-policy-pieter')
	ella = await createPrincipal(admin, 'e2e-policy-ella')
	const created = await pieter.api.post(`${API}/dashboard`, {
		data: { name: `E2E Sharing policy ${RUN}` },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboard = { id: Number(dash.id), uuid: String(dash.uuid) }
})

test.afterAll(async () => {
	if (before !== null) {
		await admin.put(`${API}/admin/sharing-policy`, { data: before })
	}
	if (dashboard !== null && pieter !== null) {
		await pieter.api.delete(`${API}/dashboard/${dashboard.id}`)
	}
	await removePrincipal(admin, pieter)
	await removePrincipal(admin, ella)
	await admin.dispose()
})

// @e2e dashboard-sharing::nobody-may-share
test('with Nobody, a member\'s share request is refused with 403 and an administrator may still share', async () => {
	const odd = await admin.put(`${API}/admin/sharing-policy`, { data: { sharingAllowed: 'friends' } })
	expect(odd.status(), 'an unknown choice is refused').toBe(400)

	await setPolicy({ sharingAllowed: 'none' })
	const refused = await pieter!.api.post(`${API}/dashboard/${dashboard!.id}/shares`, {
		data: { shareType: 'user', shareWith: ella!.username, permissionLevel: 'view_only' },
	})
	expect(refused.status(), await refused.text()).toBe(403)

	const link = await pieter!.api.post(`${API}/dashboards/${dashboard!.uuid}/public-share`, {
		data: { expiresAt: FAR_EXPIRY },
	})
	expect(link.status(), 'someone who may not share creates no public link').toBe(403)

	await setPolicy({ sharingAllowed: 'everyone' })
	const allowed = await pieter!.api.post(`${API}/dashboard/${dashboard!.id}/shares`, {
		data: { shareType: 'user', shareWith: ella!.username, permissionLevel: 'view_only' },
	})
	expect(allowed.status(), await allowed.text()).toBeLessThan(300)
})

// @e2e dashboard-sharing::a-public-link-needs-an-expiry-date
test('a public link without an expiry date is refused with 400; with one it is created', async () => {
	await setPolicy({ sharingAllowed: 'everyone', publicLinksAllowed: true })

	const without = await pieter!.api.post(`${API}/dashboards/${dashboard!.uuid}/public-share`, {
		data: {},
	})
	expect(without.status(), await without.text()).toBe(400)

	const withDate = await pieter!.api.post(`${API}/dashboards/${dashboard!.uuid}/public-share`, {
		data: { expiresAt: FAR_EXPIRY },
	})
	expect(withDate.status(), await withDate.text()).toBe(201)
	expect((await withDate.json()).expiresAt).not.toBeNull()
})

// @e2e dashboard-sharing::public-links-off
test('with public links off no link is created, and a link made before keeps working', async () => {
	await setPolicy({ publicLinksAllowed: true })
	const made = await pieter!.api.post(`${API}/dashboards/${dashboard!.uuid}/public-share`, {
		data: { expiresAt: FAR_EXPIRY },
	})
	expect(made.status(), await made.text()).toBe(201)
	const token = String((await made.json()).token)

	await setPolicy({ publicLinksAllowed: false })
	const refused = await pieter!.api.post(`${API}/dashboards/${dashboard!.uuid}/public-share`, {
		data: { expiresAt: FAR_EXPIRY },
	})
	expect(refused.status(), await refused.text()).toBe(403)
	const asAdmin = await admin.post(`${API}/dashboards/${dashboard!.uuid}/public-share`, {
		data: { expiresAt: FAR_EXPIRY },
	})
	expect(asAdmin.status(), 'an administrator creates none either').toBe(403)

	const anon = await request.newContext({ baseURL: BASE_URL, storageState: undefined })
	const data = await anon.get(`/index.php/apps/launchpad/s/${token}/data`)
	expect(data.status(), 'the earlier link still opens').toBe(200)
	await anon.dispose()
})

/** Make `boss` the manager of `who` through the provisioning API. */
async function setManager(who: Principal, boss: Principal): Promise<void> {
	const res = await admin.put(`/ocs/v1.php/cloud/users/${encodeURIComponent(who.username)}`, {
		form: { key: 'manager', value: boss.username },
		headers: { 'OCS-APIRequest': 'true' },
	})
	expect(res.ok(), `setting the manager answered ${res.status()}`).toBe(true)
}

/** A dashboard of `who` named `name`; answers its uuid. */
async function ownDashboard(who: Principal, name: string): Promise<string> {
	const res = await who.api.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBeLessThan(300)
	return String((await res.json()).dashboard.uuid)
}

/**
 * The uuids of the dashboards `who` can see; the leaver's dashboards were not
 * shared, so seeing one means owning it.
 */
async function visibleUuids(who: Principal): Promise<string[]> {
	return (await visibleDashboards(who.api)).map((row) => String(row.uuid))
}

// @e2e dashboard-sharing::pieter-leaves
// @e2e dashboard-sharing::no-manager
test('a leaver\'s dashboard goes to their manager, otherwise to the named person', async () => {
	const marieke = await createPrincipal(admin, 'e2e-policy-marieke')
	const femke = await createPrincipal(admin, 'e2e-policy-femke')
	const leaver = await createPrincipal(admin, 'e2e-policy-leaver')
	const sanne = await createPrincipal(admin, 'e2e-policy-sanne')
	try {
		await setManager(leaver, marieke)
		const leaverBoard = await ownDashboard(leaver, `E2E Leaver ${RUN}`)
		const sanneBoard = await ownDashboard(sanne, `E2E No manager ${RUN}`)
		const set = await admin.put(`${API}/admin/leaver-handover`, {
			data: { manager: true, fallbackUid: femke.username },
		})
		expect(set.status(), await set.text()).toBe(200)

		await removePrincipal(admin, leaver)
		await removePrincipal(admin, sanne)

		expect(await visibleUuids(marieke)).toContain(leaverBoard)
		expect(await visibleUuids(femke)).toContain(sanneBoard)
	} finally {
		await admin.put(`${API}/admin/leaver-handover`, {
			data: { manager: false, fallbackUid: '' },
		})
		await removePrincipal(admin, marieke)
		await removePrincipal(admin, femke)
	}
})

/** The stored leaver handover, read from the admin settings. */
async function readHandover(): Promise<{ manager: boolean; fallbackUid: string }> {
	const res = await admin.get(`${API}/admin/settings`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const handover = (body.data ?? body).leaverHandover ?? {}
	return {
		manager: handover.manager === true,
		fallbackUid: typeof handover.fallbackUid === 'string' ? handover.fallbackUid : '',
	}
}

/** GET /api/dashboard/{id} as `who`: its status and, on 200, its owner. */
async function readAs(who: Principal, id: number): Promise<{ status: number; ownerId?: string }> {
	const res = await who.api.get(`${API}/dashboard/${id}`)
	if (res.status() !== 200) {
		return { status: res.status() }
	}
	const body = await res.json()
	return { status: 200, ownerId: body.dashboard?.userId }
}

// @e2e dashboard-sharing::no-setting
test('with no handover set, a leaver\'s dashboards follow REQ-SHARE-012, not their manager', async () => {
	const priorHandover = await readHandover()
	const priorSharing = await readSharingSettings(admin)
	const marieke = await createPrincipal(admin, 'e2e-policy-nset-marieke')
	const bob = await createPrincipal(admin, 'e2e-policy-nset-bob')
	const carol = await createPrincipal(admin, 'e2e-policy-nset-carol')
	const leaver = await createPrincipal(admin, 'e2e-policy-nset-leaver')
	let leaverGone = false
	try {
		await setPolicy({ sharingAllowed: 'everyone' })
		await writeSharingSettings(admin, {
			allowUserDashboards: true,
			sharingAllowedGroups: [],
			sharingRecipientScope: 'everyone',
		})
		// The leaver HAS a manager, so a handover that ignored "not set" would
		// hand both dashboards to Marieke; REQ-SHARE-012 must decide instead.
		await setManager(leaver, marieke)
		const cleared = await admin.put(`${API}/admin/leaver-handover`, {
			data: { manager: false, fallbackUid: '' },
		})
		expect(cleared.status(), await cleared.text()).toBe(200)
		expect(await readHandover(), 'no handover is set').toEqual({ manager: false, fallbackUid: '' })

		// Kept: Bob holds full, so he is the admin pool and becomes the owner.
		const kept = await createOwnDashboard(leaver.api, `E2E No setting kept ${RUN}`)
		await publishForRecipients(leaver.api, kept.id)
		await share(leaver.api, kept.id, 'user', bob.username, 'full')
		await share(leaver.api, kept.id, 'user', carol.username, 'view_only')
		// Dropped: only a view_only share, so no admin pool and it is deleted.
		const dropped = await createOwnDashboard(leaver.api, `E2E No setting dropped ${RUN}`)
		await publishForRecipients(leaver.api, dropped.id)
		await share(leaver.api, dropped.id, 'user', carol.username, 'view_only')
		// CONTROL: Carol reads both before, so a 404 afterwards means deleted.
		expect((await readAs(carol, kept.id)).status).toBe(200)
		expect((await readAs(carol, dropped.id)).status).toBe(200)

		await leaver.api.dispose()
		await deleteAccount(admin, leaver.username)
		leaverGone = true

		const asBob = await readAs(bob, kept.id)
		expect(asBob.status, 'the dashboard with a full share is retained').toBe(200)
		expect(asBob.ownerId, 'it goes to the full-level recipient, not the manager').toBe(bob.username)
		expect(
			shareTuples(await listShares(bob.api, kept.id)),
			'Bob\'s own share is consumed and Carol\'s stays',
		).toEqual([`user:${carol.username}:view_only`])
		expect((await readAs(marieke, kept.id)).status, 'the manager is given nothing').not.toBe(200)
		expect(await visibleUuids(marieke)).not.toContain(dropped.uuid)

		expect(
			(await readAs(carol, dropped.id)).status,
			'the dashboard with no admin pool is deleted',
		).toBe(404)
	} finally {
		await admin.put(`${API}/admin/leaver-handover`, { data: priorHandover })
		await writeSharingSettings(admin, priorSharing)
		if (!leaverGone) {
			await removePrincipal(admin, leaver)
		}
		await removePrincipal(admin, marieke)
		await removePrincipal(admin, bob)
		await removePrincipal(admin, carol)
	}
})

/**
 * Log in as Pieter and open the Sharing tab of his dashboard through the
 * switcher row's cog, the way dashboard-sharing-limits.spec.ts does it.
 */
async function openPieterSharingTab(browser: Browser, name: string) {
	const { context, page } = await loginAs(browser, pieter!.username, pieter!.password)
	await page.goto('/index.php/apps/launchpad')
	await switchToDashboard(page, name)
	await openDashboardSettings(page)
	await page.locator('[data-testid="dashboard-name-input"]').waitFor({ state: 'visible', timeout: 8_000 })
	await page.locator('[data-test="config-tab-sharing"]').click()
	const panel = page.locator('[data-test="config-panel-sharing"]')
	await expect(panel).toBeVisible({ timeout: 5_000 })
	return { context, panel }
}

// @e2e dashboard-sharing::nobody-may-share
test('with Nobody the Sharing tab offers no add and no public link; with links off no new link', async ({ browser }) => {
	test.setTimeout(120_000)
	const name = `E2E Sharing policy ${RUN}`

	await setPolicy({ sharingAllowed: 'none', publicLinksAllowed: true })
	const nobody = await openPieterSharingTab(browser, name)
	try {
		await expect(nobody.panel.locator('[data-test="share-not-allowed-hint"]')).toBeVisible()
		await expect(nobody.panel.getByLabel(/share with users and groups/i)).toHaveCount(0)
		await expect(nobody.panel.locator('[data-test="public-share-create"]')).toHaveCount(0)
	} finally {
		await nobody.context.close()
	}

	await setPolicy({ sharingAllowed: 'everyone', publicLinksAllowed: false })
	const off = await openPieterSharingTab(browser, name)
	try {
		await expect(off.panel.locator('[data-test="share-not-allowed-hint"]')).toHaveCount(0)
		await expect(off.panel.locator('[data-test="public-share-off-hint"]')).toBeVisible()
		await expect(off.panel.locator('[data-test="public-share-create"]')).toHaveCount(0)
	} finally {
		await off.context.close()
		await setPolicy({ publicLinksAllowed: true })
	}
})
