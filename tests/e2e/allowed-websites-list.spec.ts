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
 * The mirror case (a host added with Embed takes an embedded page and refuses
 * a live tile) and the upgrade merge are below it. The embedded page cannot be
 * reached from any runner, so that test answers the server's framing probe and
 * serves the target page in the browser, as iframe-embed-widget.spec.ts does;
 * the allow-list verdicts and the page's frame-src are the server's own. The
 * upgrade test runs the app's post-migration repair steps through
 * `occ maintenance:repair`, which is what an upgrade runs, after putting the
 * legacy per-feature lists and the merge flag back to their pre-upgrade state.
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
import { describeRun, runOcc } from './support/occ.ts'
import { adminContext, API } from './support/principals.ts'
import { chooseAndAdd } from './support/widgetPicker.ts'

const SETTINGS_PAGE = `${BASE_URL}/index.php/apps/launchpad/admin`
const SITES = `${API}/admin/allowed-sites`
const STAMP = Date.now().toString(36)
/** The KCC API of the scenario, made unique per run and never resolvable. */
const KCC_HOST = `kcc-api-${STAMP}.e2e.invalid`
const KCC_URL = `https://${KCC_HOST}/wachttijd`
/** The Power BI host of the scenario, made unique per run and never resolvable. */
const POWERBI_HOST = `app-powerbi-${STAMP}.e2e.invalid`
const POWERBI_URL = `https://${POWERBI_HOST}/view?r=e2e-${STAMP}`
/** The hosts the upgrade test puts on the legacy lists. */
const UPGRADE_KCC_HOST = `kcc-api-upgrade-${STAMP}.e2e.invalid`
const UPGRADE_POWERBI_HOST = `app-powerbi-upgrade-${STAMP}.e2e.invalid`
const FRAMABLE_ROUTE = '**/apps/launchpad/api/iframe/framable'
/** The legacy list keys the upgrade merges (MergeAllowedSites::LEGACY_LISTS). */
const LEGACY_LIVETILE_KEY = 'livetile_allowed_hosts'
const LEGACY_EMBED_KEY = 'iframe_allowed_hosts'
/** Set once the merge ran (MergeAllowedSites::DONE_KEY). */
const MERGED_FLAG_KEY = 'allowed_sites_merged'

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
 * The placements stored on the seeded dashboard.
 *
 * @return the placements, as the admin reads them.
 */
async function placements(): Promise<Array<Record<string, any>>> {
	const read = await admin.get(`${API}/dashboard/${dashboard!.id}`)
	expect(read.status(), await read.text()).toBe(200)
	const body = await read.json()
	return (body.placements ?? body.dashboard?.placements ?? []) as Array<
		Record<string, any>
	>
}

/**
 * Add a host with one purpose in the Allowed websites block and save it.
 *
 * @param page the page.
 * @param typed what the administrator types into the Website field.
 * @param purpose the purpose label: Embed, Fetch or Embed and fetch.
 * @return the Allowed websites block.
 */
async function addSiteInUi(page: Page, typed: string, purpose: string): Promise<Locator> {
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
		.fill(typed)
	await addForm.locator('.v-select .vs__dropdown-toggle').click()
	await page.getByRole('option', { name: purpose, exact: true }).click()
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
	return block
}

/**
 * The value of one launchpad app setting, or null when it is not set.
 *
 * @param key the app config key.
 * @return the stored value.
 */
async function readAppValue(key: string): Promise<string | null> {
	const result = await runOcc('config:app:get', 'launchpad', key)
	// occ answers an unset key with exit code 1 and nothing on stdout.
	if (result.code === 1 && result.stdout.trim() === '') {
		return null
	}
	expect(result.code, describeRun(result)).toBe(0)
	return result.stdout.replace(/\r?\n$/, '')
}

/**
 * Write one launchpad app setting, or delete it when `value` is null.
 *
 * @param key the app config key.
 * @param value the value, or null to unset it.
 */
async function writeAppValue(key: string, value: string | null): Promise<void> {
	const result = value === null
		? await runOcc('config:app:delete', 'launchpad', key)
		: await runOcc('config:app:set', 'launchpad', key, `--value=${value}`)
	expect(result.code, describeRun(result)).toBe(0)
	expect(await readAppValue(key), `${key} after the write`).toBe(value)
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
	await chooseAndAdd(page.locator(`[data-testid="widget-picker-type-${type}"]`))
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

// @e2e admin-settings::power-bi-may-be-embedded-not-called
test('a host added with Embed takes an embedded page, and a live tile on the same host is refused', async ({
	page,
}) => {
	const block = await addSiteInUi(page, `https://${POWERBI_HOST}/view`, 'Embed')

	// Stored lower case, without scheme or path, for embedding only.
	const stored = (await readSites()).find((site) => site.host === POWERBI_HOST)
	expect(stored).toEqual({ host: POWERBI_HOST, embed: true, fetch: false })
	await expect(
		block.getByTestId('admin-allowed-site').filter({ hasText: POWERBI_HOST }).locator('.vs__selected'),
	).toHaveText('Embed')

	// The server accepts an embedded page on the host…
	const accepted = await admin.post(`${API}/iframe/validate-url`, {
		data: { config: { url: POWERBI_URL, title: 'Rapportage' } },
	})
	expect(accepted.status(), await accepted.text()).toBe(200)
	const acceptance = await accepted.json()
	expect(acceptance.valid, JSON.stringify(acceptance)).toBe(true)

	// …and the workspace's own CSP lets the frame load.
	const workspace = await page.goto('/index.php/apps/launchpad/')
	const frameSrc = (workspace!.headers()['content-security-policy'] ?? '')
		.split(';')
		.map((part) => part.trim())
		.find((part) => part.toLowerCase().startsWith('frame-src '))
	expect(frameSrc ?? '', 'frame-src of the workspace').toContain(`https://${POWERBI_HOST}`)

	// The author places the embedded page through the Add Widget form: it saves.
	await gotoLaunchPad(page)
	const frame = await openWidgetForm(page, 'iframe', '.iframe-widget-form')
	await frame.form.getByLabel('URL', { exact: true }).fill(POWERBI_URL)
	// Leaving the URL field asks the server about the host.
	const urlChecked = page.waitForResponse((r) =>
		r.url().includes('/api/iframe/validate-url'),
	)
	await frame.form.getByLabel('Title', { exact: true }).fill('Rapportage')
	await urlChecked
	await expect(frame.form.getByText('This host is allow-listed.')).toBeVisible({
		timeout: 10_000,
	})
	await expect(frame.form.locator('.iframe-widget-form__warning')).toHaveCount(0)
	const add = frame.dialog.getByRole('button', { name: /^add$/i })
	await expect(add).toBeEnabled({ timeout: 10_000 })
	await add.click()
	await expect(frame.dialog).not.toBeVisible({ timeout: 10_000 })
	await closeSidebar(page)

	const embeds = (await placements()).filter(
		(p) => p.widgetId === 'iframe' && p.content?.url === POWERBI_URL,
	)
	expect(embeds, 'the embedded page is stored once').toHaveLength(1)

	// It renders. No runner reaches the host, so the target page is served in
	// the browser and the framing probe answers as for a framable target.
	await page.route(`https://${POWERBI_HOST}/**`, (route) =>
		route.fulfill({
			status: 200,
			contentType: 'text/html',
			body: `<!doctype html><html><body><h1>Report ${STAMP}</h1></body></html>`,
		}),
	)
	await page.route(FRAMABLE_ROUTE, (route) =>
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ framable: true, reason: 'ok' }),
		}),
	)
	await gotoLaunchPad(page)
	const cell = page.locator(`[data-placement-id="${embeds[0].id}"]`)
	await expect(cell).toBeAttached({ timeout: 30_000 })
	const iframe = cell.locator('iframe.iframe-widget__frame')
	await expect(iframe).toBeVisible({ timeout: 30_000 })
	await expect(iframe).toHaveAttribute('src', POWERBI_URL)
	await expect(
		page.frameLocator(`[data-placement-id="${embeds[0].id}"] iframe`).getByText(`Report ${STAMP}`),
	).toBeVisible({ timeout: 30_000 })
	await page.unroute(FRAMABLE_ROUTE)
	await page.unroute(`https://${POWERBI_HOST}/**`)

	// A live tile on the same host is refused at save time: by the server…
	const tileUrl = `https://${POWERBI_HOST}/api/value`
	const refused = await admin.post(`${API}/livetile/validate-source`, {
		data: { config: { sourceMode: 'url', url: tileUrl, valueExpression: '$.value' } },
	})
	expect(refused.status(), await refused.text()).toBe(200)
	const refusal = await refused.json()
	expect(refusal.valid, JSON.stringify(refusal)).toBe(false)
	expect(refusal.errors).toContain('host_not_allowed')

	// …and in the Add Widget form, which says why; nothing is stored.
	const tile = await openWidgetForm(page, 'livetile', '.live-tile-widget-form')
	await tile.form.getByLabel('Value expression', { exact: true }).fill('$.value')
	const url = tile.form.getByLabel('URL', { exact: true })
	await url.fill(tileUrl)
	const checked = page.waitForResponse((r) =>
		r.url().includes('/api/livetile/validate-source'),
	)
	await url.press('Tab')
	const verdict = await (await checked).json()
	expect(verdict.valid, JSON.stringify(verdict)).toBe(false)
	await expect(tile.form.getByText('This host is not on the allow-list.')).toBeVisible({
		timeout: 10_000,
	})
	await tile.dialog.getByRole('button', { name: /^cancel$/i }).click()
	await expect(tile.dialog).not.toBeVisible({ timeout: 10_000 })

	const tiles = (await placements()).filter(
		(p) => p.widgetId === 'livetile' && String(p.content?.url ?? '').includes(POWERBI_HOST),
	)
	expect(tiles, 'no live tile on the embed-only host').toHaveLength(0)
})

// @e2e admin-settings::existing-lists-survive-the-upgrade
test('the upgrade merges the per-feature lists into the allowed websites with their purpose', async ({
	page,
}) => {
	test.setTimeout(300_000)

	// What the instance holds now, to put back afterwards.
	const before = {
		[LEGACY_LIVETILE_KEY]: await readAppValue(LEGACY_LIVETILE_KEY),
		[LEGACY_EMBED_KEY]: await readAppValue(LEGACY_EMBED_KEY),
		[MERGED_FLAG_KEY]: await readAppValue(MERGED_FLAG_KEY),
	}
	const legacyWith = (key: string, host: string): string => {
		let hosts: unknown
		try {
			hosts = JSON.parse(before[key] ?? '[]')
		} catch {
			hosts = []
		}
		return JSON.stringify([...(Array.isArray(hosts) ? hosts : []), host])
	}

	try {
		// Before the upgrade: the hosts are on the legacy lists only, and the
		// merge has not run.
		await writeAppValue(LEGACY_LIVETILE_KEY, legacyWith(LEGACY_LIVETILE_KEY, UPGRADE_KCC_HOST))
		await writeAppValue(LEGACY_EMBED_KEY, legacyWith(LEGACY_EMBED_KEY, UPGRADE_POWERBI_HOST))
		await writeAppValue(MERGED_FLAG_KEY, null)
		const notYet = (await readSites()).map((site) => site.host)
		expect(notYet).not.toContain(UPGRADE_KCC_HOST)
		expect(notYet).not.toContain(UPGRADE_POWERBI_HOST)

		// The upgrade: Nextcloud runs the app's post-migration repair steps.
		const repair = await runOcc('maintenance:repair')
		expect(repair.code, describeRun(repair)).toBe(0)
		expect(await readAppValue(MERGED_FLAG_KEY), 'the merge marked itself done').toBe('1')

		// The stored list carries both hosts with the purpose they had.
		const sites = await readSites()
		expect(sites.find((site) => site.host === UPGRADE_KCC_HOST)).toEqual({
			host: UPGRADE_KCC_HOST,
			embed: false,
			fetch: true,
		})
		expect(sites.find((site) => site.host === UPGRADE_POWERBI_HOST)).toEqual({
			host: UPGRADE_POWERBI_HOST,
			embed: true,
			fetch: false,
		})

		// The Allowed websites block lists them so.
		await page.goto(SETTINGS_PAGE)
		await adminTab(page, 'general').click()
		const block = page.getByTestId('admin-allowed-sites')
		await expect(block).toBeVisible({ timeout: 30_000 })
		const kccRow = block.getByTestId('admin-allowed-site').filter({ hasText: UPGRADE_KCC_HOST })
		await expect(kccRow).toBeVisible({ timeout: 30_000 })
		await expect(kccRow.locator('.vs__selected')).toHaveText('Fetch')
		const powerBiRow = block
			.getByTestId('admin-allowed-site')
			.filter({ hasText: UPGRADE_POWERBI_HOST })
		await expect(powerBiRow).toBeVisible()
		await expect(powerBiRow.locator('.vs__selected')).toHaveText('Embed')
	} finally {
		// The allowed websites themselves are put back by afterAll.
		for (const [key, value] of Object.entries(before)) {
			await writeAppValue(key, value).catch(() => undefined)
		}
	}
})
