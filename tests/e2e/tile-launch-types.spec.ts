/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Tiles that start a single sign-on app or a remote desktop
 * (launcher-tile-launch-types): an sso tile opens the administrator's launch
 * template with its app id, and an RDP tile downloads a connection file that
 * someone without access to the dashboard cannot fetch.
 *
 * The launch templates are put back to what they were afterwards.
 *
 * @e2e openspec/specs/tiles/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import * as fs from 'fs'
import { deprovisionUser, provisionThrowawayUser } from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const API = '/index.php/apps/launchpad/api'
const STAMP = `${Date.now()}`
const SSO_TITLE = `Salarisstrook ${STAMP}`
const RDP_TITLE = `Belastingen (oud) ${STAMP}`
const ENTRA = 'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso'

async function apiAs(user: string, pass: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`,
		},
	})
}

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let templatesBefore: unknown[] = []
let rdpTileId = 0

async function addTile(data: Record<string, unknown>): Promise<number> {
	const res = await admin.post(`${API}/dashboard/${dashboard!.id}/tile`, {
		data: { linkValue: '', gridWidth: 2, gridHeight: 2, gridY: 0, ...data },
	})
	expect(res.status(), await res.text()).toBe(201)
	return Number((await res.json()).id)
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await apiAs(ADMIN.user, ADMIN.pass)
	const settings = await admin.get(`${API}/admin/settings`)
	const current = await settings.json()
	templatesBefore = (current.data ?? current).ssoLaunchTemplates ?? []
	const set = await admin.put(`${API}/admin/settings`, {
		data: { ssoLaunchTemplates: [{ key: 'entra', name: 'Microsoft Entra ID', urlTemplate: ENTRA }] },
	})
	expect(set.status(), await set.text()).toBeLessThan(300)

	dashboard = await seedActiveDashboard(admin, `E2E launch types ${STAMP}`)
	await addTile({ title: SSO_TITLE, linkType: 'sso', gridX: 0, content: { sso: { template: 'entra', appId: 'a1b2c3' } } })
	rdpTileId = await addTile({
		title: RDP_TITLE,
		linkType: 'remote-desktop',
		gridX: 2,
		content: { remote: { mode: 'rdp', host: 'rds01.gemeente.local', remoteApp: '||Belastingen', gateway: 'rdgw.gemeente.nl' } },
	})
})

test.afterAll(async () => {
	await admin.put(`${API}/admin/settings`, { data: { ssoLaunchTemplates: templatesBefore } })
	await removeSeededDashboard(admin, dashboard)
	await admin.dispose()
})

// @e2e tiles::entra-id-app
test('a single sign-on tile opens the launch template with its app id', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/')
	const link = page.locator('.tile-widget__link').filter({ hasText: SSO_TITLE })
	await expect(link).toBeVisible({ timeout: 30_000 })
	await expect(link).toHaveAttribute('href', 'https://launcher.myapps.microsoft.com/api/signin/a1b2c3?tenantId=contoso')
	await expect(link).toHaveAttribute('target', '_blank')
})

// @e2e tiles::published-windows-program-through-a-gateway
test('an RDP tile downloads a connection file without credentials', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/')
	const link = page.locator('.tile-widget__link').filter({ hasText: RDP_TITLE })
	await expect(link).toBeVisible({ timeout: 30_000 })
	const [download] = await Promise.all([page.waitForEvent('download'), link.click()])
	expect(download.suggestedFilename()).toBe(`${RDP_TITLE}.rdp`)
	const file = fs.readFileSync(await download.path(), 'utf-8')
	expect(file).toContain('full address:s:rds01.gemeente.local')
	expect(file).toContain('remoteapplicationprogram:s:||Belastingen')
	expect(file).toContain('gatewayhostname:s:rdgw.gemeente.nl')
	expect(file.toLowerCase()).not.toContain('username')
	expect(file.toLowerCase()).not.toContain('password')
})

// @e2e tiles::someone-without-access-asks-for-the-file
test('someone who may not view the dashboard cannot fetch the file', async () => {
	const outsider = await provisionThrowawayUser('e2e-rdp-outsider')
	const api = await apiAs(outsider.username, outsider.password)
	try {
		const res = await api.get(`${API}/tiles/${rdpTileId}/rdp`, { timeout: 60_000 })
		expect(res.status()).toBe(403)
	} finally {
		await api.dispose()
		await deprovisionUser(outsider.username)
	}
})
