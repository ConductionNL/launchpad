/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A dashboard exported and imported again keeps its widgets' configuration
 * (REQ-EXIM-004).
 *
 * 🔴 WHY THIS EXISTS: AN IMPORT THAT REPORTED SUCCESS AND LOST EVERYTHING
 * ======================================================================
 * Export writes each widget placement whole. The importer used to keep only
 * its grid, style and title, so a dashboard that went out and came back had
 * the right number of widgets in the right places, every one of them
 * unconfigured: text widgets with no text, object-lists with no register,
 * nc-widget proxies pointing at nothing, tiles without their type or link.
 * The import answered "Imported 1 dashboards, skipped 0" all the same.
 *
 * Behind that sat a worse defect. The importer set no `created_at` or
 * `updated_at`, and both are NOT NULL on the dashboards and placements tables,
 * so on a real database the insert failed and the dashboard was reported as
 * skipped: no import had landed a dashboard at all. The first run of this spec
 * against the unfixed code measured exactly that, on PostgreSQL:
 * `importedDashboardCount: 0`, SQLSTATE 23502 on `created_at`. The content
 * loss was hidden behind it; the unit tests mock the mapper and saw neither.
 *
 * It mattered more than a backup feature usually does, because the store
 * install path (launchpad#607) hands its payload to the same importer: every
 * dashboard installed from a registry arrived unconfigured too.
 *
 * So the assertion is not the import report. It is the imported text widget
 * RENDERING the text it was exported with.
 *
 * WHICH SURFACE, EXACTLY. The import goes through the product's own admin
 * page: Beheer ▸ Operations, its file input and its "Upload archive" button,
 * the same controls an administrator uses. The export goes through the same
 * endpoint that page's "Download all dashboards" button calls
 * (`POST /api/admin/export`), narrowed to `scope=dashboard`. The button only
 * offers the SITE scope, and importing a site archive back would duplicate
 * every dashboard on the instance, which on a shared or CI instance means
 * every other spec's fixtures.
 *
 * WHY THE SOURCE IS DELETED BEFORE THE RENDER CHECK. The source dashboard
 * carries the same marker text. Deleting it first means the only place the
 * marker can come from is the imported copy, so a green render cannot be the
 * source dashboard showing through.
 *
 * @spec openspec/specs/dashboard-export-import/spec.md
 */

import type { APIRequestContext, request } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const SETTINGS = `${API}/admin/settings`
const SETTINGS_PAGE = `${BASE}/index.php/settings/admin/launchpad`
const APP_URL = '/index.php/apps/launchpad'

const STAMP = Date.now()
const DASHBOARD_NAME = `E2E RoundTrip ${STAMP}`
// Plain words only: the text widget renders markdown, and the marker has to
// come out of the renderer unchanged to be findable on the page.
const MARKER = `Round trip marker ${STAMP}`

/** An admin-authenticated request context using HTTP Basic auth. */
async function adminApi(playwright: {
	request: { newContext: typeof request.newContext }
}): Promise<APIRequestContext> {
	return playwright.request.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

test.describe('dashboard export and import — widget configuration survives', () => {
	let api: APIRequestContext
	let allowUserDashboardsBefore: unknown
	let previousActiveUuid: string | null = null
	let sourceId: number | null = null
	let sourceUuid = ''
	let archive: Buffer | null = null
	const importedIds: number[] = []

	test.beforeAll(async ({ playwright }) => {
		api = await adminApi({ request: playwright.request })

		const settings = await api.get(SETTINGS)
		expect(settings.status(), await settings.text()).toBe(200)
		allowUserDashboardsBefore = (await settings.json()).allowUserDashboards

		const active = await api.get(`${API}/dashboard`)
		if (active.ok()) {
			const uuid = (await active.json())?.dashboard?.uuid
			previousActiveUuid = typeof uuid === 'string' ? uuid : null
		}

		// Personal dashboards must be allowed to create the source, and a second
		// one must be allowed for the imported copy.
		const allow = await api.put(SETTINGS, {
			data: { allowUserDash: true, allowMultiDash: true },
		})
		expect(allow.status(), await allow.text()).toBe(200)

		const created = await api.post(`${API}/dashboard`, {
			data: { name: DASHBOARD_NAME },
		})
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body
		sourceId = Number(dash.id)
		sourceUuid = String(dash.uuid)

		const widget = await api.post(`${API}/dashboard/${sourceId}/widgets`, {
			data: {
				widgetId: 'text',
				gridX: 0,
				gridY: 0,
				gridWidth: 6,
				gridHeight: 3,
				content: { text: MARKER, contentMode: 'markdown' },
			},
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)

		const exported = await api.post(
			`${API}/admin/export?scope=dashboard&dashboardUuid=${sourceUuid}`,
		)
		expect(exported.status(), await exported.text()).toBe(200)
		archive = await exported.body()
		// A ZIP starts with "PK". Anything else is an error page served as 200.
		expect(archive.subarray(0, 2).toString()).toBe('PK')
	})

	/*
	 * 🔴 CLEANUP IN `afterAll`. The test creates a source dashboard and imports
	 * a copy of it, and switches the admin's active dashboard. A failure
	 * halfway must not leave either dashboard behind for later specs, or the
	 * active preference pointing at one of them.
	 */
	test.afterAll(async () => {
		if (api === undefined) {
			return
		}
		for (const id of [sourceId, ...importedIds]) {
			if (id !== null) {
				await api.delete(`${API}/dashboard/${id}`)
			}
		}
		if (previousActiveUuid !== null) {
			await api.post(`${API}/dashboards/active`, {
				data: { uuid: previousActiveUuid },
			})
		}
		if (typeof allowUserDashboardsBefore === 'boolean') {
			await api.put(SETTINGS, {
				data: { allowUserDash: allowUserDashboardsBefore },
			})
		}
		await api.dispose()
	})

	// @e2e dashboard-export-import::an-exported-dashboard-imported-again-keeps-every-widgets-configuration
	test('a dashboard imported through the admin page renders its widgets configured', async ({
		page,
	}) => {
		// Two page loads of a heavy shell, measured at up to a minute each on
		// the shared dev instance; the default budget holds one.
		test.slow()
		expect(archive, 'beforeAll produced no export archive').not.toBeNull()

		// ── Import through the admin page ────────────────────────────────────
		await page
			.context()
			.setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
		await page.goto(SETTINGS_PAGE, { waitUntil: 'domcontentloaded' })
		await page.locator('[data-test="tab-operations"]').click({ timeout: 30_000 })

		await page.locator('[data-test="import-file-input"]').setInputFiles({
			name: `launchpad-roundtrip-${STAMP}.zip`,
			mimeType: 'application/zip',
			buffer: archive!,
		})

		const importResponse = page.waitForResponse(
			(res) =>
				res.url().includes('/api/admin/import')
				&& res.request().method() === 'POST',
		)
		await page.locator('[data-test="import-submit"]').click()
		const report = await (await importResponse).json()
		expect(
			report.importedDashboardCount,
			`the import did not bring the dashboard back: ${JSON.stringify(report)}`,
		).toBe(1)
		await expect(page.locator('.launchpad-export-import__result')).toBeVisible()

		// ── Find the imported copy: same name, fresh UUID ────────────────────
		const list = await api.get(`${API}/dashboards`)
		expect(list.status(), await list.text()).toBe(200)
		const copies = ((await list.json()).items ?? []).filter(
			(d: { name: string; uuid: string }) =>
				d.name === DASHBOARD_NAME && d.uuid !== sourceUuid,
		)
		for (const copy of copies) {
			importedIds.push(Number(copy.id))
		}
		expect(copies, 'exactly one imported copy should exist').toHaveLength(1)
		const imported = copies[0]

		/*
		 * THE STORED FACT. Before the fix the imported text widget existed with
		 * an empty `content`, so this names the defect precisely: the widget
		 * arrived, its configuration did not.
		 */
		const detail = await api.get(`${API}/dashboard/${imported.id}`)
		expect(detail.status(), await detail.text()).toBe(200)
		const textWidgets = ((await detail.json()).placements ?? []).filter(
			(p: { widgetId: string }) => p.widgetId === 'text',
		)
		expect(
			textWidgets,
			'the imported dashboard has no text widget',
		).toHaveLength(1)
		expect(
			textWidgets[0].content?.text,
			'the text widget was imported but its configuration was dropped',
		).toBe(MARKER)

		// ── The rendered fact ────────────────────────────────────────────────
		// Delete the source first: from here on, the marker can only come from
		// the imported copy.
		const removed = await api.delete(`${API}/dashboard/${sourceId}`)
		expect(removed.status(), await removed.text()).toBeLessThan(300)
		sourceId = null

		const activate = await api.post(`${API}/dashboards/active`, {
			data: { uuid: imported.uuid },
		})
		expect(activate.status(), await activate.text()).toBeLessThan(300)

		await page.goto(APP_URL, { waitUntil: 'domcontentloaded' })
		await page.waitForSelector(
			'.launchpad-floating-controls, .workspace-shell',
			{
				timeout: 30_000,
			},
		)
		await expect(
			page.getByText(MARKER),
			'the imported text widget does not render the text it was exported with',
		).toBeVisible({ timeout: 20_000 })
	})
})
