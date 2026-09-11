/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The site export an administrator downloads (REQ-EXIM-001, REQ-EXIM-003,
 * REQ-EXIM-009).
 *
 * The archive is taken the way an admin takes it: Beheer ▸ Operations, the
 * "Download all dashboards" button, and the file the browser saves. The API is
 * used only to seed the three kinds of dashboard the scenarios name and to
 * clean them up, because the admin page has no control for creating an admin
 * template or a group dashboard.
 *
 * WHY "EVERY DASHBOARD" IS COMPARED AGAINST SEEDED IDS AND A COUNT, NOT A
 * FIXED NUMBER. REQ-EXIM-003 is written as "5 personal, 2 admin templates and
 * 1 group_shared, so dashboardCount MUST be 8". No suite can arrange exactly
 * that on an instance other specs also use, and a test that demanded it would
 * be measuring the other specs' fixtures. What the scenario is really about is
 * that a site export leaves no dashboard out and that the manifest agrees with
 * the archive, so this seeds one dashboard of each type and asserts all three
 * are in the archive, and that `dashboardCount` equals the number of dashboard
 * files actually written.
 *
 * WHAT THE ARCHIVE DOES NOT CARRY. Metadata field definitions and asset bytes
 * are specified under REQ-EXIM-006 and REQ-EXIM-007 and were deferred when this
 * capability shipped; `metadata-fields.json` is always an empty list. This spec
 * asserts the file is present, which is what the format promises, and does not
 * pretend the contents are there.
 */

import type { APIRequestContext, Page, request } from '@playwright/test'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as os from 'os'
import * as path from 'path'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import {
	buildZip,
	dashboardEntries,
	readJsonEntry,
	readZip,
} from './support/zipArchive.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const SETTINGS_PAGE = `${BASE}/index.php/settings/admin/launchpad`
const STAMP = `E2E SiteExport ${Date.now()}`

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

/** Open Beheer ▸ Operations, where the export/import panel lives. */
async function openOperationsTab(page: Page): Promise<void> {
	await page
		.context()
		.setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
	await page.goto(SETTINGS_PAGE, {
		waitUntil: 'domcontentloaded',
		timeout: 60_000,
	})
	await page.locator('[data-test="tab-operations"]').click({ timeout: 30_000 })
	await expect(page.locator('[data-test="export-site-button"]')).toBeVisible({
		timeout: 20_000,
	})
}

/**
 * Press "Download all dashboards" and answer with the bytes the browser saved.
 *
 * @param page the admin page, already on the Operations tab.
 * @return the archive the admin ends up with on disk.
 */
async function downloadSiteExport(page: Page): Promise<Buffer> {
	const pending = page.waitForEvent('download', { timeout: 60_000 })
	await page.locator('[data-test="export-site-button"]').click()
	const download = await pending

	const target = path.join(
		fs.mkdtempSync(path.join(os.tmpdir(), 'launchpad-e2e-')),
		download.suggestedFilename(),
	)
	await download.saveAs(target)
	const bytes = fs.readFileSync(target)
	fs.rmSync(path.dirname(target), { recursive: true, force: true })

	return bytes
}

test.describe('the site export an administrator downloads', () => {
	let api: APIRequestContext
	const seeded: Array<{ kind: string; uuid: string; delete: string }> = []

	test.beforeAll(async ({ playwright }) => {
		api = await adminApi({ request: playwright.request })

		const allow = await api.put(`${API}/admin/settings`, {
			data: { allowUserDash: true, allowMultiDash: true },
		})
		expect(allow.status(), await allow.text()).toBe(200)

		// One dashboard of each kind REQ-EXIM-003 names.
		const personal = await api.post(`${API}/dashboard`, {
			data: { name: `${STAMP} personal` },
		})
		expect(personal.status(), await personal.text()).toBeLessThan(300)
		const personalBody = (await personal.json()).dashboard ?? {}
		seeded.push({
			kind: 'personal',
			uuid: String(personalBody.uuid),
			delete: `${API}/dashboard/${personalBody.id}`,
		})

		const template = await api.post(`${API}/admin/templates`, {
			data: { name: `${STAMP} template` },
		})
		expect(template.status(), await template.text()).toBeLessThan(300)
		const templateBody = await template.json()
		seeded.push({
			kind: 'admin_template',
			uuid: String(templateBody.uuid ?? templateBody.dashboard?.uuid),
			delete: `${API}/admin/templates/${templateBody.id ?? templateBody.dashboard?.id}`,
		})

		const group = await api.post(`${API}/dashboards/group/admin`, {
			data: { name: `${STAMP} group` },
		})
		expect(group.status(), await group.text()).toBeLessThan(300)
		const groupBody = (await group.json()).dashboard ?? {}
		seeded.push({
			kind: 'group_shared',
			uuid: String(groupBody.uuid),
			delete: `${API}/dashboards/group/admin/${groupBody.uuid}`,
		})
	})

	/*
	 * 🔴 REMOVE THE SEEDED DASHBOARDS IN `afterAll`. They are of three
	 * different kinds, and an admin template left behind is offered to every
	 * user of the instance by the template gallery, so a failure here would
	 * change what later specs see.
	 */
	test.afterAll(async () => {
		if (api === undefined) {
			return
		}
		for (const entry of seeded) {
			await api.delete(entry.delete)
		}
		await api.dispose()
	})

	// @e2e dashboard-export-import::site-export-includes-all-dashboards
	// @e2e dashboard-export-import::site-export-includes-all-dashboard-types
	test('holds one file per dashboard, of every kind, and a manifest that agrees', async ({
		page,
	}) => {
		test.slow()
		await openOperationsTab(page)
		const archive = readZip(await downloadSiteExport(page))

		const entries = dashboardEntries(archive)
		for (const entry of seeded) {
			expect(
				entries,
				`the site export left out the ${entry.kind} dashboard`,
			).toContain(`dashboards/${entry.uuid}.json`)
		}

		const manifestJson = readJsonEntry(archive, 'manifest.json')
		expect(manifestJson.scope).toBe('site')
		expect(
			manifestJson.dashboardCount,
			'the manifest must count what the archive actually holds',
		).toBe(entries.length)

		// The format promises this file; REQ-EXIM-006 governs its contents,
		// which are not implemented.
		expect(Object.keys(archive)).toContain('metadata-fields.json')

		// Every dashboard file must be readable and carry the state the format
		// describes: a half-written archive would still satisfy a name check.
		for (const entry of entries) {
			const dashboard = readJsonEntry(archive, entry)
			expect(String(dashboard.uuid ?? ''), `${entry} has no uuid`).not.toBe('')
			expect(String(dashboard.name ?? ''), `${entry} has no name`).not.toBe('')
			expect(Array.isArray(dashboard.widgets), `${entry} has no widgets`).toBe(
				true,
			)
		}

		// Paths inside the archive are relative (REQ-EXIM-001).
		for (const name of Object.keys(archive)) {
			expect(name.startsWith('/'), `${name} is an absolute path`).toBe(false)
		}
	})

	// @e2e dashboard-export-import::manifest-schema-version-enforces-forward-compatibility
	// @e2e dashboard-export-import::current-version-is-schemaversion-1
	test('declares schema version 1, and an archive claiming another is refused', async ({
		page,
	}) => {
		test.slow()
		await openOperationsTab(page)
		const bytes = await downloadSiteExport(page)
		const archive = readZip(bytes)

		expect(readJsonEntry(archive, 'manifest.json').schemaVersion).toBe(1)

		// Re-package this instance's own export under a version it cannot read.
		// Nothing else about the archive changes, so a refusal can only be the
		// version check.
		const claimingTwo = buildZip(
			Object.entries(archive).map(([name, content]) => ({
				name,
				content:
					name === 'manifest.json'
						? JSON.stringify({
								...readJsonEntry(archive, 'manifest.json'),
								schemaVersion: 2,
							})
						: content,
			})),
		)

		await page.locator('[data-test="import-file-input"]').setInputFiles({
			name: 'from-a-newer-launchpad.zip',
			mimeType: 'application/zip',
			buffer: claimingTwo,
		})
		const pending = page.waitForResponse(
			(res) =>
				res.url().includes('/api/admin/import')
				&& res.request().method() === 'POST',
			{ timeout: 60_000 },
		)
		await page.locator('[data-test="import-submit"]').click()
		const response = await pending

		expect(response.status()).toBe(400)
		expect((await response.json())?.error).toContain(
			'Unsupported manifest schema version: 2',
		)

		// The scenario names 2 and 0 as examples: a version BELOW the supported
		// one is not an older archive, it is a broken manifest, and it is
		// refused just the same.
		const claimingZero = buildZip(
			Object.entries(archive).map(([name, content]) => ({
				name,
				content:
					name === 'manifest.json'
						? JSON.stringify({
								...readJsonEntry(archive, 'manifest.json'),
								schemaVersion: 0,
							})
						: content,
			})),
		)
		await page.locator('[data-test="import-file-input"]').setInputFiles({
			name: 'version-zero.zip',
			mimeType: 'application/zip',
			buffer: claimingZero,
		})
		const pendingZero = page.waitForResponse(
			(res) =>
				res.url().includes('/api/admin/import')
				&& res.request().method() === 'POST',
			{ timeout: 60_000 },
		)
		await page.locator('[data-test="import-submit"]').click()
		const zeroResponse = await pendingZero

		expect(zeroResponse.status()).toBe(400)
		expect((await zeroResponse.json())?.error).toContain(
			'Unsupported manifest schema version: 0',
		)
	})
})
