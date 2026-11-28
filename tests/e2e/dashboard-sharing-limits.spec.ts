/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Administrators decide who may share and with whom (dashboard-sharing
 * REQ-SHARE-014): the `sharingAllowedGroups` and `sharingRecipientScope`
 * admin settings, enforced on every share the server creates and reflected in
 * the sharee picker of the dashboard's Sharing tab.
 *
 * Each limit is shown twice, the way a person meets it and the way the server
 * keeps it: Pieter logs in, opens the Sharing tab of his own dashboard and
 * types a name into "Share with users and groups", and the picker offers
 * nobody it should not; then the same share is sent straight to the API with
 * his own credentials and is refused with 403, and his share list reads back
 * empty. In every test an allowed principal makes the same request and
 * succeeds, so a broken endpoint cannot pass as a refusal.
 *
 * Noor, the administrator of the scenarios, is the admin account. The two
 * settings are read in `beforeAll`, every test sets the values it needs, and
 * `afterAll` writes the originals back so the rest of the job shares as it
 * did before.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */

import type { APIRequestContext, Browser, Locator, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'
import type { OwnDashboard, SharingSettings } from './support/shareApi.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { openDashboardSettings, switchToDashboard } from './support/dashboardSwitcher.ts'
import {
	addToGroup,
	adminContext,
	API,
	assertActingAs,
	createGroup,
	createPrincipal,
	publishForRecipients,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'
import {
	createOwnDashboard,
	listShares,
	postShare,
	readSharingSettings,
	shareTuples,
	writeSharingSettings,
} from './support/shareApi.ts'

const STAMP = Date.now()

let admin: APIRequestContext
let prior: SharingSettings | null = null
let pieter: Principal | null = null
let ella: Principal | null = null
let jan: Principal | null = null
let kim: Principal | null = null
let communicatie: string | null = null
let team: string | null = null
let pieterDash: OwnDashboard | null = null
let janDash: OwnDashboard | null = null

test.beforeAll(async () => {
	admin = await adminContext()
	prior = await readSharingSettings(admin)
	await writeSharingSettings(admin, {
		allowUserDashboards: true,
		sharingAllowedGroups: [],
		sharingRecipientScope: 'everyone',
	})

	pieter = await createPrincipal(admin, 'e2e-slim-pieter')
	ella = await createPrincipal(admin, 'e2e-slim-ella')
	jan = await createPrincipal(admin, 'e2e-slim-jan')
	kim = await createPrincipal(admin, 'e2e-slim-kim')
	for (const p of [pieter, ella, jan, kim]) {
		await assertActingAs(p.api, p.username)
	}

	// "Communicatie": jan is in it, pieter is not.
	communicatie = await createGroup(admin, `e2e-slim-communicatie-${STAMP}`)
	await addToGroup(admin, jan.username, communicatie)
	// pieter and kim share a group; ella shares none with pieter.
	team = await createGroup(admin, `e2e-slim-team-${STAMP}`)
	await addToGroup(admin, pieter.username, team)
	await addToGroup(admin, kim.username, team)

	pieterDash = await createOwnDashboard(pieter.api, `E2E Pieter ${STAMP}`)
	janDash = await createOwnDashboard(jan.api, `E2E Jan ${STAMP}`)
	await publishForRecipients(pieter.api, pieterDash.id)
	await publishForRecipients(jan.api, janDash.id)
})

test.afterAll(async () => {
	if (prior !== null) {
		await writeSharingSettings(admin, prior)
	}
	for (const p of [pieter, ella, jan, kim]) {
		await removePrincipal(admin, p)
	}
	await removeGroup(admin, communicatie)
	await removeGroup(admin, team)
	await admin.dispose()
})

/** Remove every share on a dashboard, so each test starts from none. */
async function clearShares(owner: Principal, dash: OwnDashboard): Promise<void> {
	const res = await owner.api.put(`${API}/dashboard/${dash.id}/shares`, {
		data: { shares: [] },
	})
	expect(res.status(), await res.text()).toBe(200)
}

/** The user ids the sharee search offers the caller for a query. */
async function offeredUsers(p: Principal, query: string): Promise<string[]> {
	const res = await p.api.get(`${API}/sharees?query=${encodeURIComponent(query)}`)
	expect(res.status(), await res.text()).toBe(200)
	return ((await res.json()).users ?? []).map((u: { id: string }) => String(u.id))
}

/**
 * Log in as Pieter and open the Sharing tab of his own dashboard, through
 * the switcher row's cog, the way `dashboard-sharing.spec.ts` does it.
 *
 * @return the page and the sharing panel.
 */
async function openPieterSharingTab(browser: Browser) {
	const { context, page } = await loginAs(
		browser,
		pieter!.username,
		pieter!.password,
	)
	await page.goto('/index.php/apps/launchpad')
	await switchToDashboard(page, pieterDash!.name)
	await openDashboardSettings(page)
	await page
		.locator('[data-testid="dashboard-name-input"]')
		.waitFor({ state: 'visible', timeout: 8_000 })
	await page.locator('[data-test="config-tab-sharing"]').click()
	const panel = page.locator('[data-test="config-panel-sharing"]')
	await expect(panel).toBeVisible({ timeout: 5_000 })
	return { context, page, panel }
}

/**
 * Type a query into the sharee picker and wait for the search it fires.
 *
 * @return the options the picker shows for that user id.
 */
async function searchInPicker(page: Page, panel: Locator, query: string) {
	const combobox = panel.getByLabel(/share with users and groups/i).first()
	await combobox.click()
	const searched = page.waitForResponse(
		(r) =>
			r.url().includes('/api/sharees?query=')
			&& r.url().includes(encodeURIComponent(query)),
	)
	await combobox.fill(query)
	const response = await searched
	expect(response.status()).toBe(200)
	return page.locator('.sharee-option', { hasText: query })
}

// @e2e dashboard-sharing::a-member-outside-the-sharing-groups-cannot-share
test('a member outside the sharing groups is offered nobody and is refused by the API', async ({
	browser,
}) => {
	test.setTimeout(120_000)
	await clearShares(pieter!, pieterDash!)
	await clearShares(jan!, janDash!)
	await writeSharingSettings(admin, {
		sharingAllowedGroups: [communicatie!],
		sharingRecipientScope: 'everyone',
	})

	// In the browser: Pieter cannot add a share. The tab says why and offers
	// no picker at all (sharing-policy-board-sync), so nobody can be chosen.
	const { context, panel } = await openPieterSharingTab(browser)
	try {
		await expect(panel.getByText(/does not allow you to share/i)).toBeVisible()
		await expect(
			panel.getByLabel(/share with users and groups/i),
			'Pieter may not share, so no share picker is offered',
		).toHaveCount(0)
		await expect(panel.locator('.dashboard-config__share')).toHaveCount(0)
	} finally {
		await context.close()
	}
	expect(await offeredUsers(pieter!, ella!.username)).toHaveLength(0)

	// Over the API: refused with 403, and nothing was written.
	const refused = await postShare(
		pieter!.api,
		pieterDash!.id,
		'user',
		ella!.username,
		'view_only',
	)
	expect(refused.status(), await refused.text()).toBe(403)
	expect(
		await listShares(pieter!.api, pieterDash!.id),
		'a refused share leaves no row',
	).toHaveLength(0)

	// The allowed principal: jan, in "Communicatie", is offered Ella and
	// shares with her.
	expect(await offeredUsers(jan!, ella!.username)).toContain(ella!.username)
	const allowed = await postShare(
		jan!.api,
		janDash!.id,
		'user',
		ella!.username,
		'view_only',
	)
	expect(allowed.status(), await allowed.text()).toBe(201)
	expect(shareTuples(await listShares(jan!.api, janDash!.id))).toEqual([
		`user:${ella!.username}:view_only`,
	])
})

// @e2e dashboard-sharing::recipients-limited-to-shared-groups
test('under the shared-groups scope Ella is not offered and a share naming her is refused', async ({
	browser,
}) => {
	test.setTimeout(120_000)
	await clearShares(pieter!, pieterDash!)
	await writeSharingSettings(admin, {
		sharingAllowedGroups: [],
		sharingRecipientScope: 'shared-groups',
	})

	const { context, page, panel } = await openPieterSharingTab(browser)
	try {
		const notOffered = await searchInPicker(page, panel, ella!.username)
		await expect(notOffered, 'Ella shares no group with Pieter').toHaveCount(0)
		// CONTROL, same picker: kim shares a group with him and IS offered,
		// so the empty result above is the scope and not a dead picker.
		const offered = await searchInPicker(page, panel, kim!.username)
		await expect(offered.first()).toBeVisible({ timeout: 8_000 })
	} finally {
		await context.close()
	}
	expect(await offeredUsers(pieter!, ella!.username)).not.toContain(ella!.username)

	const refused = await postShare(
		pieter!.api,
		pieterDash!.id,
		'user',
		ella!.username,
		'view_only',
	)
	expect(refused.status(), await refused.text()).toBe(403)
	expect(
		await listShares(pieter!.api, pieterDash!.id),
		'a refused share leaves no row',
	).toHaveLength(0)

	// The allowed recipient, in the same test.
	const allowed = await postShare(
		pieter!.api,
		pieterDash!.id,
		'user',
		kim!.username,
		'view_only',
	)
	expect(allowed.status(), await allowed.text()).toBe(201)
	expect(shareTuples(await listShares(pieter!.api, pieterDash!.id))).toEqual([
		`user:${kim!.username}:view_only`,
	])
})

// @e2e dashboard-sharing::no-limits-set
test('with both settings empty Pieter shares with Ella as before', async () => {
	await clearShares(pieter!, pieterDash!)
	await writeSharingSettings(admin, {
		sharingAllowedGroups: [],
		sharingRecipientScope: 'everyone',
	})
	const read = await readSharingSettings(admin)
	expect(read.sharingAllowedGroups, 'CONTROL: no sharing groups are set').toEqual(
		[],
	)
	expect(read.sharingRecipientScope, 'CONTROL: recipients are not limited').toBe(
		'everyone',
	)

	expect(await offeredUsers(pieter!, ella!.username)).toContain(ella!.username)
	const created = await postShare(
		pieter!.api,
		pieterDash!.id,
		'user',
		ella!.username,
		'view_only',
	)
	expect(created.status(), await created.text()).toBe(201)
	expect(shareTuples(await listShares(pieter!.api, pieterDash!.id))).toEqual([
		`user:${ella!.username}:view_only`,
	])
	// And it works as a share: Ella reaches the dashboard.
	const asElla = await ella!.api.get(`${API}/dashboard/${pieterDash!.id}`)
	expect(asElla.status(), await asElla.text()).toBe(200)
	expect((await asElla.json()).permissionLevel).toBe('view_only')
})
