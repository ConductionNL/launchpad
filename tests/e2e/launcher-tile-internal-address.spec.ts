/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A tile with an address on the office network (launcher-tile-internal-address):
 * the administrator lists the office networks, an author gives a tile a second
 * address, and the tile opens the address that fits the network the request
 * comes from (REQ-TIA-001, REQ-TIA-002, REQ-TIA-003).
 *
 * THE RUNNER'S RANGE IS THE OFFICE NETWORK. The server decides from the
 * request address, so this file reads the address the server sees for this
 * runner (`currentAddress` in the admin settings answer) and lists exactly that
 * address as the office network for the "at the office" leg. The "at home" leg
 * lists a documentation range (RFC 5737 / RFC 3849) the runner can never be in.
 * The office networks are put back to what they were afterwards.
 *
 * Every non-admin context starts from an empty storage state, so a viewer is
 * never silently the admin session from `use.storageState`.
 *
 * @e2e openspec/changes/launcher-tile-internal-address/specs/tiles/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const STAMP = `${Date.now()}`
const TILE_TITLE = `Zaaksysteem ${STAMP}`
const PUBLIC_URL = 'https://zaken.gemeente.nl/'
const INTERNAL_URL = 'http://zaken.intern/'
const SETTINGS_URL = `${BASE}/index.php/settings/admin/launchpad`

let admin: APIRequestContext
let viewer: Principal
let officeNetworksBefore: string[] = []
let runnerAddress = ''
let placementId = 0

/** The admin settings object, unwrapped from an optional `data` envelope. */
async function readSettings(): Promise<Record<string, unknown>> {
	const res = await admin.get(`${API}/admin/settings`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (body.data ?? body) as Record<string, unknown>
}

/** Store the office networks and assert the server accepted them. */
async function setOfficeNetworks(ranges: string[]): Promise<void> {
	const res = await admin.put(`${API}/admin/settings`, { data: { officeNetworks: ranges } })
	expect(res.status(), await res.text()).toBe(200)
}

/** A range that holds only the runner's own address. */
function runnerRange(): string {
	return runnerAddress.includes(':') ? `${runnerAddress}/128` : `${runnerAddress}/32`
}

/** A documentation range the runner's address can never fall in. */
function elsewhereRange(): string {
	return runnerAddress.includes(':') ? '2001:db8::/32' : '192.0.2.0/24'
}

/** Log the viewer in and open their dashboard; the caller closes the context. */
async function openDashboardAsViewer(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
	const session = await loginAs(browser, viewer.username, viewer.password)
	await session.page.goto('/index.php/apps/launchpad/')
	return session
}

/** Enter dashboard edit mode through the sidebar row menu, then close the sidebar. */
async function enterEditMode(page: Page): Promise<void> {
	await page.locator('.launchpad-sidebar-toggle').first().click()
	await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 8_000 })
	await page.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.first()
		.locator('.dashboard-row-actions button')
		.first()
		.click()
	await page.locator('[data-testid="cog-edit-dashboard"]').click()
	await page.waitForSelector('.launchpad-edit-mode', { timeout: 8_000 })
	const close = page.locator('.dashboard-switcher-sidebar__close').first()
	if (await close.isVisible().catch(() => false)) {
		await close.click()
	}
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, process.env.NC_ADMIN_USER ?? 'admin')

	const settings = await readSettings()
	officeNetworksBefore = Array.isArray(settings.officeNetworks) ? (settings.officeNetworks as string[]) : []
	runnerAddress = String(settings.currentAddress ?? '')
	expect(runnerAddress, 'the server did not report the runner address').not.toBe('')

	// The viewer builds their own dashboard, so personal dashboards must be allowed.
	await allowPersonalDashboards(admin, true)
	viewer = await createPrincipal(admin, 'e2e-tia-viewer')
	await assertActingAs(viewer.api, viewer.username)

	const created = await viewer.api.post(`${API}/dashboard`, { data: { name: `E2E office address ${STAMP}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dashboard = body.dashboard ?? body.data?.dashboard ?? body
	const activate = await viewer.api.post(`${API}/dashboard/${dashboard.id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const preference = await viewer.api.post(`${API}/dashboards/active`, { data: { uuid: dashboard.uuid } })
	expect(preference.status(), await preference.text()).toBeLessThan(300)

	const tile = await viewer.api.post(`${API}/dashboard/${dashboard.id}/tile`, {
		data: { title: TILE_TITLE, linkType: 'url', linkValue: PUBLIC_URL, gridX: 0, gridY: 0, gridWidth: 2, gridHeight: 2 },
	})
	expect(tile.status(), await tile.text()).toBe(201)
	placementId = Number((await tile.json()).id)
})

test.afterAll(async () => {
	await admin.put(`${API}/admin/settings`, { data: { officeNetworks: officeNetworksBefore } })
	if (viewer) {
		await removePrincipal(admin, viewer)
	}
	await admin.dispose()
})

// @e2e tiles::administrator-adds-the-office-range
test('the administrator adds an office range and reads whether their address is inside it', async ({ page }) => {
	await page.goto(SETTINGS_URL)
	await adminTab(page, 'general').click()
	const section = page.getByTestId('admin-office-networks')
	await expect(section).toBeVisible({ timeout: 30_000 })

	await section.getByTestId('admin-office-networks-input').fill('10.20.0.0/16')
	await section.getByTestId('admin-office-networks-save').click()

	await expect(section.getByTestId('admin-office-networks-input')).toHaveValue('10.20.0.0/16')
	await expect(section.getByTestId('admin-office-networks-current')).toContainText(/inside|outside/)
	expect((await readSettings()).officeNetworks).toEqual(['10.20.0.0/16'])
})

// @e2e tiles::malformed-range-is-refused
test('a malformed range is refused and the stored list is unchanged', async ({ page }) => {
	await setOfficeNetworks(['10.20.0.0/16'])

	const refused = await admin.put(`${API}/admin/settings`, { data: { officeNetworks: ['10.20.0.0/40'] } })
	expect(refused.status()).toBe(400)
	expect((await readSettings()).officeNetworks).toEqual(['10.20.0.0/16'])

	await page.goto(SETTINGS_URL)
	await adminTab(page, 'general').click()
	const section = page.getByTestId('admin-office-networks')
	await section.getByTestId('admin-office-networks-input').fill('10.20.0.0/40')
	await section.getByTestId('admin-office-networks-save').click()
	await expect(section.getByTestId('admin-office-networks-error')).toContainText('This is not a valid network range')
	expect((await readSettings()).officeNetworks).toEqual(['10.20.0.0/16'])
})

// @e2e tiles::author-adds-the-internal-address
test('the author adds the internal address and the tile keeps both addresses', async () => {
	const res = await viewer.api.put(`${API}/widgets/${placementId}`, {
		data: { content: { internalUrl: INTERNAL_URL } },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)

	const refused = await viewer.api.put(`${API}/widgets/${placementId}`, {
		data: { content: { internalUrl: 'javascript:alert(1)' } },
	})
	expect(refused.status()).toBe(400)

	const active = await viewer.api.get(`${API}/dashboard`)
	expect(active.status(), await active.text()).toBe(200)
	const body = await active.json()
	const placements = ((body.data ?? body).placements ?? []) as Array<Record<string, any>>
	const saved = placements.find((placement) => Number(placement.id) === placementId)
	expect(saved, `placement ${placementId} not on the active dashboard`).toBeTruthy()
	expect(saved!.tileLinkValue).toBe(PUBLIC_URL)
	const content = typeof saved!.content === 'string' ? JSON.parse(saved!.content) : saved!.content
	expect(content.internalUrl).toBe(INTERNAL_URL)
})

// @e2e tiles::at-the-office
test('on the office network the tile opens the internal address', async ({ browser }) => {
	await setOfficeNetworks([runnerRange()])
	const { context, page } = await openDashboardAsViewer(browser)
	try {
		const link = page.locator('.tile-widget__link').filter({ hasText: TILE_TITLE })
		await expect(link).toBeVisible({ timeout: 30_000 })
		await expect(link).toHaveAttribute('href', INTERNAL_URL)
	} finally {
		await context.close()
	}
})

// @e2e tiles::at-home
test('outside the office networks the tile opens its main address', async ({ browser }) => {
	await setOfficeNetworks([elsewhereRange()])
	const { context, page } = await openDashboardAsViewer(browser)
	try {
		const link = page.locator('.tile-widget__link').filter({ hasText: TILE_TITLE })
		await expect(link).toBeVisible({ timeout: 30_000 })
		await expect(link).toHaveAttribute('href', PUBLIC_URL)
	} finally {
		await context.close()
	}
})

// @e2e tiles::at-home
test('with no office networks set the tile opens its main address', async ({ browser }) => {
	await setOfficeNetworks([])
	const { context, page } = await openDashboardAsViewer(browser)
	try {
		const link = page.locator('.tile-widget__link').filter({ hasText: TILE_TITLE })
		await expect(link).toBeVisible({ timeout: 30_000 })
		await expect(link).toHaveAttribute('href', PUBLIC_URL)
	} finally {
		await context.close()
	}
})

// @e2e tiles::editor-shows-the-address-in-effect
test('on the office network the editor says the tile opens the internal address', async ({ browser }) => {
	await setOfficeNetworks([runnerRange()])
	const { context, page } = await openDashboardAsViewer(browser)
	try {
		const tile = page.locator('.tile-widget').filter({ hasText: TILE_TITLE })
		await expect(tile).toBeVisible({ timeout: 30_000 })
		await enterEditMode(page)
		await tile.getByRole('button', { name: 'Tile menu' }).click()
		await page.getByRole('menuitem', { name: 'Edit tile' }).click()
		await expect(page.getByLabel('Address on the office network')).toHaveValue(INTERNAL_URL)
		await expect(page.locator('.tile-editor__address-in-effect'))
			.toHaveText('You are on the office network: this tile opens the internal address')
	} finally {
		await context.close()
	}
})
