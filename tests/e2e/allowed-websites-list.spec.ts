/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * One list of allowed websites with a purpose per site (allowed-websites-list,
 * REQ-ASET-021): on the General tab the administrator adds a host with the
 * purpose Fetch and saves; a live tile on that host then passes the save-time
 * check and lands on the dashboard, while an embedded page on the same host is
 * refused, by the server and in the Add Widget form.
 *
 * The host lives under `.invalid` (RFC 2606), so nothing is ever fetched from
 * it: the test proves the allow-list verdicts, not an upstream reading. The
 * stored list is read before the run and put back afterwards; the dashboard
 * the tile is placed on is removed with it.
 *
 * Written from the change's scenarios; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/specs/admin-settings/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { closeSidebar, gotoLaunchPad, openSidebar } from './fixtures/widget-flow.ts'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { adminContext, API } from './support/principals.ts'

const SETTINGS_PAGE = `${BASE_URL}/index.php/settings/admin/launchpad`
const SITES = `${API}/admin/allowed-sites`
const STAMP = Date.now().toString(36)
/** The KCC API of the scenario, made unique per run and never resolvable. */
const KCC_HOST = `kcc-api-${STAMP}.e2e.invalid`
const KCC_URL = `https://${KCC_HOST}/wachttijd`

type Site = { host: string; embed: boolean; fetch: boolean }

let admin: APIRequestContext
let previousSites: Site[] = []
let dashboard: SeededDashboard | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * The stored allowed websites.
 *
 * @return the sites as the server keeps them.
 */
async function readSites(): Promise<Site[]> {
	const res = await admin.get(SITES)
	expect(res.status(), await res.text()).toBe(200)
	return ((await res.json()).sites ?? []) as Site[]
}

/**
 * Open the Add Widget modal on the seeded dashboard's own row with one widget
 * type chosen.
 *
 * @param page the page, on the workspace.
 * @param type the widget type in the picker.
 * @param formClass the class of the type's form.
 * @return the dialog and the form inside it.
 */
async function openWidgetForm(
	page: Page,
	type: string,
	formClass: string,
): Promise<{ dialog: Locator; form: Locator }> {
	await openSidebar(page)
	const row = page
		.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.filter({ hasText: dashboard!.name })
		.first()
	await expect(row).toBeVisible({ timeout: 10_000 })
	await row.locator('.dashboard-row-actions button').first().click()
	await page.getByRole('menuitem', { name: /^add widget$/i }).click()
	await page.locator(`[data-testid="widget-picker-type-${type}"]`).click()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	const form = dialog.locator(formClass)
	await expect(form).toBeVisible({ timeout: 10_000 })
	return { dialog, form }
}

test.beforeAll(async () => {
	admin = await adminContext()
	previousSites = await readSites()
	dashboard = await seedActiveDashboard(admin, `E2E Toegestane websites ${STAMP}`)
})

test.afterAll(async () => {
	if (admin) {
		await admin
			.put(SITES, { data: { sites: previousSites } })
			.catch(() => undefined)
		await removeSeededDashboard(admin, dashboard).catch(() => undefined)
		await admin.dispose()
	}
})

// @e2e admin-settings::the-kcc-api-may-be-called-not-embedded
test('a host added with Fetch takes a live tile, and an embed on the same host is refused', async ({
	page,
}) => {
	// The administrator adds the host with the purpose Fetch and saves.
	await page.goto(SETTINGS_PAGE)
	await adminTab(page, 'general').click()
	const block = page.getByTestId('admin-allowed-sites')
	await expect(block).toBeVisible({ timeout: 30_000 })

	const addForm = block.locator('form.allowed-sites__add')
	await addForm
		.locator(
			'input[data-testid="admin-allowed-site-input"], [data-testid="admin-allowed-site-input"] input',
		)
		.first()
		.fill(`https://${KCC_HOST.toUpperCase()}/wachttijd`)
	await addForm.locator('.v-select .vs__dropdown-toggle').click()
	await page.getByRole('option', { name: 'Fetch', exact: true }).click()
	await block.getByTestId('admin-allowed-site-add').click()

	const saved = page.waitForResponse(
		(res) =>
			res.url().includes('/api/admin/allowed-sites')
			&& res.request().method() === 'PUT',
	)
	await block.getByTestId('admin-allowed-sites-save').click()
	const response = await saved
	expect(response.status(), await response.text()).toBe(200)
	await expect(block.getByTestId('admin-allowed-sites-error')).toHaveCount(0)

	// Stored lower case, without scheme or path, for fetching only.
	const stored = (await readSites()).find((site) => site.host === KCC_HOST)
	expect(stored).toEqual({ host: KCC_HOST, embed: false, fetch: true })

	// After a reload the block lists it with its purpose.
	await page.reload()
	await adminTab(page, 'general').click()
	const row = page
		.getByTestId('admin-allowed-site')
		.filter({ hasText: KCC_HOST })
	await expect(row).toBeVisible({ timeout: 30_000 })
	await expect(row.locator('.vs__selected')).toHaveText('Fetch')

	// A live tile on that host passes the save-time check and is saved.
	await gotoLaunchPad(page)
	const tile = await openWidgetForm(page, 'livetile', '.live-tile-widget-form')
	await tile.form.getByLabel('Value expression', { exact: true }).fill('$.wachttijd')
	const url = tile.form.getByLabel('URL', { exact: true })
	await url.fill(KCC_URL)
	const checked = page.waitForResponse((r) =>
		r.url().includes('/api/livetile/validate-source'),
	)
	await url.press('Tab')
	const verdict = await (await checked).json()
	expect(verdict.valid, JSON.stringify(verdict)).toBe(true)
	await expect(tile.form.getByText('This host is not on the allow-list.')).toHaveCount(0)
	const add = tile.dialog.getByRole('button', { name: /^add$/i })
	await expect(add).toBeEnabled({ timeout: 10_000 })
	await add.click()
	await expect(tile.dialog).not.toBeVisible({ timeout: 10_000 })
	await closeSidebar(page)

	const read = await admin.get(`${API}/dashboard/${dashboard!.id}`)
	expect(read.status(), await read.text()).toBe(200)
	const body = await read.json()
	const placements = (body.placements ?? body.dashboard?.placements ?? []) as Array<
		Record<string, any>
	>
	const tiles = placements.filter((p) => p.widgetId === 'livetile')
	expect(tiles, JSON.stringify(placements)).toHaveLength(1)
	expect(tiles[0].content).toMatchObject({ sourceMode: 'url', url: KCC_URL })

	// An embedded page on the same host is refused: by the server…
	const embed = await admin.post(`${API}/iframe/validate-url`, {
		data: { config: { url: KCC_URL, title: 'Wachttijd' } },
	})
	expect(embed.status(), await embed.text()).toBe(200)
	const refusal = await embed.json()
	expect(refusal.valid).toBe(false)
	expect(refusal.errors).toContain('host_not_allowed')

	// …and in the Add Widget form, which will not save it.
	const frame = await openWidgetForm(page, 'iframe', '.iframe-widget-form')
	await frame.form.getByLabel('URL', { exact: true }).fill(KCC_URL)
	await frame.form.getByLabel('Title', { exact: true }).fill('Wachttijd')
	await expect(frame.form.locator('.iframe-widget-form__warning')).toHaveText(
		`The host ${KCC_HOST} is not on the allow-list.`,
		{ timeout: 10_000 },
	)
	await expect(frame.dialog.getByRole('button', { name: /^add$/i })).toBeDisabled()
	await frame.dialog.getByRole('button', { name: /^cancel$/i }).click()
	await expect(frame.dialog).not.toBeVisible({ timeout: 10_000 })
})
