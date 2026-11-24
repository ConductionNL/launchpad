/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the bulk-operations panel on Beheer ▸
 * Operations (openspec/specs/dashboard-bulk-operations, REQ-BULK-010),
 * written for e2e-coverage-programme task 2.1.
 *
 * Everything an administrator does here goes through the panel's own
 * controls (`DashboardBulkOperations.vue`): the header and row checkboxes,
 * the Actions select, Apply, the confirmation modal, its dry-run checkbox and
 * OK. The API is used only to seed dashboards before the page loads, to
 * delete one behind the page's back (so the summary has a per-uuid error to
 * enumerate), and to clean up.
 *
 * 🔴 SELECT-ALL IS NEVER FOLLOWED BY A REAL RUN. The table lists every
 * dashboard the admin can see on a shared instance, including the instance
 * default. The select-all test only checks and clears the boxes; every real
 * run selects named rows one by one.
 *
 * The HTTP contract of the four endpoints is covered in
 * `dashboard-bulk-operations.spec.ts`.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-bulk-operations::administrator-selects-multiple-dashboards-via-checkboxes
 *   @e2e dashboard-bulk-operations::actions-dropdown-enabled-only-when-rows-are-selected
 *   @e2e dashboard-bulk-operations::each-action-shows-a-confirmation-modal-with-dry-run-toggle
 *   @e2e dashboard-bulk-operations::response-summary-after-action-completes
 *   @e2e dashboard-bulk-operations::dry-run-response-shows-would-counts-no-list-refresh
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { adminContext, API } from './support/principals.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const SETTINGS_PAGE = `${BASE_URL}/index.php/apps/launchpad/admin`
const PREFIX = `E2E BulkUI ${Date.now()}`
const PANEL = '[data-test="bulk-ops"]'

interface Dash {
	id: number
	uuid: string
	name: string
}

let admin: APIRequestContext
const created: Dash[] = []
let settingsBefore: Record<string, unknown> | null = null

test.describe.configure({ mode: 'serial' })

/**
 * Create a dashboard owned by the admin.
 *
 * @param label a readable suffix for the name.
 * @param parentUuid an optional parent.
 * @return the dashboard.
 */
async function createDash(label: string, parentUuid?: string): Promise<Dash> {
	const name = `${PREFIX} ${label}`
	const data: Record<string, unknown> = { name }
	if (parentUuid !== undefined) {
		data.parentUuid = parentUuid
	}
	const res = await admin.post(`${API}/dashboard`, { data })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const dash = (await res.json()).dashboard
	const out = { id: Number(dash.id), uuid: String(dash.uuid), name }
	created.push(out)
	return out
}

/** Read a dashboard as the admin; null once it is gone. */
async function readDash(id: number): Promise<any> {
	const res = await admin.get(`${API}/dashboard/${id}`)
	if (res.status() === 404) {
		return null
	}
	expect(res.status()).toBe(200)
	return (await res.json()).dashboard
}

/** Open Beheer ▸ Operations and wait for the bulk table to list `names`. */
async function openPanel(page: Page, names: string[]): Promise<Locator> {
	await page
		.context()
		.setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	await page.goto(SETTINGS_PAGE, {
		waitUntil: 'domcontentloaded',
		timeout: 60_000,
	})
	await adminTab(page, 'operations').click({ timeout: 30_000 })
	const panel = page.locator(PANEL)
	await expect(panel.locator('[data-test="bulk-ops-table"]')).toBeVisible({
		timeout: 20_000,
	})
	for (const name of names) {
		await expect(row(panel, name), `row for ${name}`).toBeVisible({
			timeout: 20_000,
		})
	}
	return panel
}

/** The table row of a dashboard, by its unique name. */
function row(panel: Locator, name: string): Locator {
	return panel
		.locator('[data-test="bulk-ops-table"] tbody tr')
		.filter({ hasText: name })
}

/** The selection checkbox of a dashboard's row. */
function rowBox(panel: Locator, name: string): Locator {
	return row(panel, name).locator('[data-test="bulk-ops-row-select"]')
}

/** Pick an action and press Apply; answers the open modal. */
async function openModal(panel: Locator, action: string): Promise<Locator> {
	await panel.locator('[data-test="bulk-ops-action-select"]').selectOption(action)
	await panel.locator('[data-test="bulk-ops-run"]').click()
	const modal = panel.locator('[data-test="bulk-ops-modal"]')
	await expect(modal).toBeVisible()
	return modal
}

test.beforeAll(async () => {
	admin = await adminContext()
	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status()).toBe(200)
	settingsBefore = await settings.json()
	const allow = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDashboards: true, allowMultipleDashboards: true },
	})
	expect(allow.status(), await allow.text()).toBe(200)
})

test.afterAll(async () => {
	for (const dash of [...created].reverse()) {
		await admin.delete(`${API}/dashboard/${dash.id}`)
	}
	if (settingsBefore !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: {
				allowUserDashboards: settingsBefore.allowUserDashboards === true,
				allowMultipleDashboards:
					settingsBefore.allowMultipleDashboards !== false,
			},
		})
	}
	await admin.dispose()
})

test('the header checkbox selects every row; a row checkbox toggles only itself', async ({
	page,
}) => {
	// @e2e dashboard-bulk-operations::administrator-selects-multiple-dashboards-via-checkboxes
	const a = await createDash('select A')
	const b = await createDash('select B')
	const panel = await openPanel(page, [a.name, b.name])
	const allRows = panel.locator('[data-test="bulk-ops-row-select"]')
	const header = panel.locator('[data-test="bulk-ops-select-all"]')

	await header.check()
	const total = await allRows.count()
	expect(total).toBeGreaterThanOrEqual(2)
	for (let i = 0; i < total; i++) {
		await expect(allRows.nth(i)).toBeChecked()
	}

	// One row's checkbox clears that row and no other.
	await rowBox(panel, a.name).uncheck()
	await expect(rowBox(panel, a.name)).not.toBeChecked()
	await expect(rowBox(panel, b.name)).toBeChecked()
	await expect(
		panel.locator('[data-test="bulk-ops-row-select"]:checked'),
	).toHaveCount(total - 1)
	await rowBox(panel, a.name).check()
	await expect(rowBox(panel, a.name)).toBeChecked()

	// Clear everything again; nothing is ever run from a select-all.
	await header.uncheck()
	await expect(
		panel.locator('[data-test="bulk-ops-row-select"]:checked'),
	).toHaveCount(0)
})

test('the Actions dropdown is disabled until a row is selected', async ({
	page,
}) => {
	// @e2e dashboard-bulk-operations::actions-dropdown-enabled-only-when-rows-are-selected
	const a = await createDash('actions A')
	const panel = await openPanel(page, [a.name])
	const select = panel.locator('[data-test="bulk-ops-action-select"]')

	await expect(
		panel.locator('[data-test="bulk-ops-row-select"]:checked'),
	).toHaveCount(0)
	await expect(select).toBeDisabled()

	await rowBox(panel, a.name).check()
	await expect(select).toBeEnabled()

	const options = select.locator('option:not([value=""])')
	await expect(options).toHaveCount(4)
	const labels = (await options.allTextContents()).map((s) => s.trim())
	expect(labels[0]).toBe('Delete')
	expect(labels[1]).toMatch(/^Move to(…|\.\.\.)$/)
	expect(labels[2]).toBe('Set status')
	expect(labels[3]).toBe('Reindex')
	expect(
		await options.evaluateAll((els) =>
			els.map((el) => (el as HTMLOptionElement).value),
		),
	).toEqual(['delete', 'move', 'status', 'reindex'])
})

test('every action opens a modal with a dry-run toggle and calls its own endpoint', async ({
	page,
}) => {
	// @e2e dashboard-bulk-operations::each-action-shows-a-confirmation-modal-with-dry-run-toggle
	const parent = await createDash('modal parent')
	const child = await createDash('modal child', parent.uuid)
	const panel = await openPanel(page, [parent.name, child.name])
	await rowBox(panel, parent.name).check()
	await rowBox(panel, child.name).check()
	const selected = [parent.uuid, child.uuid]

	// The toggle is there and unchecked for each action.
	for (const action of ['delete', 'move', 'status', 'reindex']) {
		const modal = await openModal(panel, action)
		const dry = modal.locator('[data-test="bulk-ops-dryrun"]')
		await expect(dry).toBeVisible()
		await expect(dry).not.toBeChecked()
		await expect(modal).toContainText('Dry run (preview only)')
		await modal.getByRole('button', { name: 'Cancel' }).click()
		await expect(modal).toBeHidden()
	}

	// OK sends the matching endpoint with the chosen options, and the
	// backend's per-uuid result is surfaced. Every run here is a dry run.
	const cases: Array<{
		action: string
		endpoint: string
		fill?: (modal: Locator) => Promise<void>
		body: Record<string, unknown>
	}> = [
		{ action: 'delete', endpoint: 'bulk-delete', body: { dryRun: true } },
		{
			action: 'move',
			endpoint: 'bulk-move',
			fill: (modal) =>
				modal
					.locator('[data-test="bulk-ops-parent-uuid"]')
					.fill(parent.uuid),
			body: { dryRun: true, parentUuid: parent.uuid },
		},
		{
			action: 'status',
			endpoint: 'bulk-status',
			fill: (modal) =>
				modal
					.locator('[data-test="bulk-ops-status-select"]')
					.selectOption('published')
					.then(() => undefined),
			body: { dryRun: true, publicationStatus: 'published' },
		},
		{ action: 'reindex', endpoint: 'bulk-reindex', body: { dryRun: true } },
	]
	for (const c of cases) {
		const modal = await openModal(panel, c.action)
		await modal.locator('[data-test="bulk-ops-dryrun"]').check()
		if (c.fill) {
			await c.fill(modal)
		}
		const pending = page.waitForResponse(
			(res) =>
				res.url().includes(`/api/admin/dashboards/${c.endpoint}`)
				&& res.request().method() === 'POST',
		)
		await modal.locator('[data-test="bulk-ops-confirm"]').click()
		const response = await pending
		expect(response.status(), c.endpoint).toBe(200)
		const sent = response.request().postDataJSON()
		expect(sent.dashboardUuids).toEqual(selected)
		expect(sent).toMatchObject(c.body)

		const result = await response.json()
		const summary = panel.locator('[data-test="bulk-ops-result"]')
		const would = Object.entries(result).find(
			([key]) => key.startsWith('would') && key !== 'wouldSkipCount',
		)?.[1]
		await expect(summary).toContainText(
			`PREVIEW: would change ${would} dashboards.`,
		)
		for (const err of result.errors) {
			await expect(summary).toContainText(`${err.uuid}: ${err.reason}`)
		}
	}
	// Two of the four carried per-uuid errors, so the enumeration above
	// was exercised: the parent has a child, and moving it under itself is
	// a cycle while the child already sits there.
	expect(await readDash(parent.id)).not.toBeNull()
	expect((await readDash(child.id)).parentUuid).toBe(parent.uuid)
})

test('a real run shows counts and errors, then refreshes and clears the selection', async ({
	page,
}) => {
	// @e2e dashboard-bulk-operations::response-summary-after-action-completes
	const a = await createDash('summary A')
	const b = await createDash('summary B')
	const gone = await createDash('summary gone')
	const panel = await openPanel(page, [a.name, b.name, gone.name])
	for (const dash of [a, b, gone]) {
		await rowBox(panel, dash.name).check()
	}
	// Deleted behind the page, so the reindex reports it per uuid.
	const del = await admin.delete(`${API}/dashboard/${gone.id}`)
	expect(del.status(), await del.text()).toBeLessThan(300)

	const modal = await openModal(panel, 'reindex')
	const pending = page.waitForResponse(
		(res) =>
			res.url().includes('/api/admin/dashboards/bulk-reindex')
			&& res.request().method() === 'POST',
	)
	const refresh = page.waitForRequest(
		(req) =>
			req.url().includes('/api/dashboards/visible') && req.method() === 'GET',
	)
	await modal.locator('[data-test="bulk-ops-confirm"]').click()
	const response = await pending
	expect(response.status()).toBe(200)
	expect(await response.json()).toEqual({
		reindexedCount: 2,
		errors: [{ uuid: gone.uuid, reason: 'not_found' }],
		dryRun: false,
	})

	const summary = panel.locator('[data-test="bulk-ops-result"]')
	await expect(summary).toContainText('Changed 2 dashboards. Skipped 0.')
	await expect(summary.locator('li')).toHaveCount(1)
	await expect(summary.locator('li')).toHaveText(`${gone.uuid}: not_found`)

	await refresh
	await expect(row(panel, gone.name)).toHaveCount(0)
	await expect(row(panel, a.name)).toBeVisible()
	await expect(
		panel.locator('[data-test="bulk-ops-row-select"]:checked'),
	).toHaveCount(0)
	await expect(
		panel.locator('[data-test="bulk-ops-action-select"]'),
	).toBeDisabled()
})

test('a dry run shows the preview line and does not reload the list', async ({
	page,
}) => {
	// @e2e dashboard-bulk-operations::dry-run-response-shows-would-counts-no-list-refresh
	const a = await createDash('preview A')
	const b = await createDash('preview B')
	const panel = await openPanel(page, [a.name, b.name])
	await rowBox(panel, a.name).check()
	await rowBox(panel, b.name).check()

	const reloads: string[] = []
	const modal = await openModal(panel, 'delete')
	await modal.locator('[data-test="bulk-ops-dryrun"]').check()
	const pending = page.waitForResponse(
		(res) =>
			res.url().includes('/api/admin/dashboards/bulk-delete')
			&& res.request().method() === 'POST',
	)
	page.on('request', (req) => {
		if (
			req.url().includes('/api/dashboards/visible')
			&& req.method() === 'GET'
		) {
			reloads.push(req.url())
		}
	})
	await modal.locator('[data-test="bulk-ops-confirm"]').click()
	const response = await pending
	expect(await response.json()).toEqual({
		wouldDeleteCount: 2,
		wouldSkipCount: 0,
		errors: [],
		dryRun: true,
	})

	await expect(panel.locator('[data-test="bulk-ops-result"]')).toContainText(
		'PREVIEW: would change 2 dashboards.',
	)
	// Give a refresh, had one been triggered, time to go out.
	await page.waitForTimeout(2_000)
	expect(reloads, 'a dry run must not reload the dashboard list').toEqual([])
	// The selection survives, which only holds when the list did not reload.
	await expect(rowBox(panel, a.name)).toBeChecked()
	await expect(rowBox(panel, b.name)).toBeChecked()
	expect(await readDash(a.id)).not.toBeNull()
	expect(await readDash(b.id)).not.toBeNull()
})
