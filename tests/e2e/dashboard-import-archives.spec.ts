/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Importing archives through the admin page (REQ-EXIM-004, REQ-EXIM-005,
 * REQ-EXIM-008, REQ-EXIM-011).
 *
 * Every test here uploads through the controls an administrator uses: Beheer ▸
 * Operations, its file input, its "Preserve original dashboard UUIDs" switch
 * and its "Upload archive" button. The API is used only to seed a dashboard
 * worth colliding with and to clean up afterwards.
 *
 * WHY THE ARCHIVES ARE BUILT IN THE TEST. These scenarios are all about
 * archives that are wrong in one specific way: no manifest, a manifest that is
 * not JSON, a schema version this LaunchPad cannot read, one dashboard file out
 * of ten corrupt. An export cannot produce those, so `support/zipArchive.ts`
 * writes them byte by byte.
 *
 * WHAT IS ASSERTED, AND IN WHICH ORDER. The response the page received, then
 * what the page shows the admin, then what the instance actually holds. A
 * refusal that reports 400 while creating dashboards anyway, or one the admin
 * is never told the reason for, both pass a status-code check and fail here.
 */

import type { APIRequestContext, Page, request } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import { buildZip, dashboardPayload, manifest } from './support/zipArchive.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const SETTINGS_PAGE = `${BASE}/index.php/settings/admin/launchpad`

/** Every dashboard this file creates carries this prefix, so cleanup is exact. */
const STAMP = `E2E ImportArchive ${Date.now()}`

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
	await expect(page.locator('[data-test="import-file-input"]')).toBeVisible({
		timeout: 20_000,
	})
}

interface ImportOutcome {
	status: number
	body: any
	contentType: string
}

/**
 * Upload one archive through the page and answer with what came back.
 *
 * @param page the admin page, already on the Operations tab.
 * @param archive the archive bytes.
 * @param options `preserveUuids` ticks the switch before uploading.
 * @return the status, parsed body and request content type.
 */
async function importThroughThePage(
	page: Page,
	archive: Buffer,
	options: { preserveUuids?: boolean; filename?: string } = {},
): Promise<ImportOutcome> {
	await page.locator('[data-test="import-file-input"]').setInputFiles({
		name: options.filename ?? 'archive.zip',
		mimeType: 'application/zip',
		buffer: archive,
	})

	if (options.preserveUuids === true) {
		await page
			.locator('.launchpad-export-import input[type="checkbox"]')
			.first()
			.check()
	}

	const pending = page.waitForResponse(
		(res) =>
			res.url().includes('/api/admin/import')
			&& res.request().method() === 'POST',
		{ timeout: 60_000 },
	)
	await page.locator('[data-test="import-submit"]').click()
	const response = await pending

	let body: any
	try {
		body = await response.json()
	} catch {
		body = null
	}

	return {
		status: response.status(),
		body,
		contentType: response.request().headers()['content-type'] ?? '',
	}
}

test.describe('importing an archive through the admin page', () => {
	let api: APIRequestContext
	let seededUuid = ''
	let seededName = ''

	test.beforeAll(async ({ playwright }) => {
		api = await adminApi({ request: playwright.request })

		// Personal dashboards must be allowed, and more than one of them,
		// because most of these tests import a copy alongside an original.
		const allow = await api.put(`${API}/admin/settings`, {
			data: { allowUserDash: true, allowMultiDash: true },
		})
		expect(allow.status(), await allow.text()).toBe(200)

		seededName = `${STAMP} original`
		const created = await api.post(`${API}/dashboard`, {
			data: { name: seededName },
		})
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		seededUuid = String((body.dashboard ?? body).uuid)
	})

	/*
	 * 🔴 CLEANUP AFTER EVERY TEST, NOT AT THE END OF ONE. Each test imports
	 * dashboards, and a test that fails halfway still leaves them behind: the
	 * next test's "how many dashboards are there now" assertion would then be
	 * measuring the previous failure. Sweeping by name prefix removes the
	 * copies (an import keeps the name and mints a new UUID) without touching
	 * anything another spec owns.
	 */
	test.afterEach(async () => {
		const list = await api.get(`${API}/dashboards`)
		if (!list.ok()) {
			return
		}
		for (const dashboard of (await list.json()).items ?? []) {
			const name = String(dashboard.name ?? '')
			if (name.startsWith(STAMP) && String(dashboard.uuid) !== seededUuid) {
				await api.delete(`${API}/dashboard/${dashboard.id}`)
			}
		}
	})

	test.afterAll(async () => {
		if (api === undefined) {
			return
		}
		const list = await api.get(`${API}/dashboards`)
		if (list.ok()) {
			for (const dashboard of (await list.json()).items ?? []) {
				if (String(dashboard.name ?? '').startsWith(STAMP)) {
					await api.delete(`${API}/dashboard/${dashboard.id}`)
				}
			}
		}
		await api.dispose()
	})

	/** How many dashboards the admin owns whose name starts with the stamp. */
	async function stampedDashboards(): Promise<
		Array<{ id: number; uuid: string; name: string }>
	> {
		const list = await api.get(`${API}/dashboards`)
		expect(list.status(), await list.text()).toBe(200)
		return ((await list.json()).items ?? []).filter((d: { name: string }) =>
			String(d.name ?? '').startsWith(STAMP),
		)
	}

	// @e2e dashboard-export-import::import-valid-zip-creates-new-dashboards
	// @e2e dashboard-export-import::import-multipart-file-upload
	test('a valid archive imports every dashboard in it, with fresh UUIDs', async ({
		page,
	}) => {
		const names = [`${STAMP} one`, `${STAMP} two`, `${STAMP} three`]
		const archive = buildZip([
			{ name: 'manifest.json', content: manifest({ dashboardCount: 3 }) },
			...names.map((name, index) => ({
				name: `dashboards/fixed-uuid-${index}.json`,
				content: dashboardPayload(`fixed-uuid-${index}`, name),
			})),
		])

		await openOperationsTab(page)
		const outcome = await importThroughThePage(page, archive)

		expect(outcome.status).toBe(200)
		expect(outcome.body).toMatchObject({
			importedDashboardCount: 3,
			skippedDashboardCount: 0,
			errors: [],
		})
		// REQ-EXIM-004's multipart scenario: the page sends the archive as a
		// multipart form, and the server takes the file out of it.
		expect(outcome.contentType).toContain('multipart/form-data')

		await expect(page.locator('.launchpad-export-import__result')).toContainText(
			'3',
		)

		const created = (await stampedDashboards()).filter((d) =>
			names.includes(d.name),
		)
		expect(created, 'all three dashboards must exist afterwards').toHaveLength(3)
		for (const dashboard of created) {
			expect(
				dashboard.uuid.startsWith('fixed-uuid-'),
				`imported dashboard kept the archive's UUID ${dashboard.uuid}; `
					+ 'the default is a fresh one',
			).toBe(false)
		}
	})

	// @e2e dashboard-export-import::missing-manifestjson-fails-import
	test('an archive without a manifest is refused, and the page says why', async ({
		page,
	}) => {
		const before = (await stampedDashboards()).length
		const archive = buildZip([
			{
				name: 'dashboards/no-manifest.json',
				content: dashboardPayload('no-manifest', `${STAMP} orphan`),
			},
		])

		await openOperationsTab(page)
		const outcome = await importThroughThePage(page, archive)

		expect(outcome.status).toBe(400)
		expect(outcome.body?.error).toBe('manifest.json not found in archive')
		await expect(page.locator('.launchpad-export-import__error')).toContainText(
			'manifest.json not found in archive',
		)
		expect(
			(await stampedDashboards()).length,
			'a refused import must create nothing',
		).toBe(before)
	})

	// @e2e dashboard-export-import::reject-manifest-with-invalid-json
	// @e2e dashboard-export-import::reject-zip-missing-required-manifest-fields
	test('a manifest that is broken or incomplete is refused by name', async ({
		page,
	}) => {
		await openOperationsTab(page)

		const notJson = await importThroughThePage(
			page,
			buildZip([{ name: 'manifest.json', content: '{ "schemaVersion": ' }]),
		)
		expect(notJson.status).toBe(400)
		expect(notJson.body?.error).toBe('manifest.json is not valid JSON')
		await expect(page.locator('.launchpad-export-import__error')).toContainText(
			'manifest.json is not valid JSON',
		)

		const missingFields = await importThroughThePage(
			page,
			buildZip([
				{
					name: 'manifest.json',
					content: JSON.stringify({ exportedBy: 'admin' }),
				},
			]),
		)
		expect(missingFields.status).toBe(400)
		// The message has to say WHICH fields, or the admin cannot fix the file.
		expect(missingFields.body?.error).toContain('schemaVersion')
		expect(missingFields.body?.error).toContain('scope')
	})

	// @e2e dashboard-export-import::reject-unsupported-schema-version
	// @e2e dashboard-export-import::version-mismatch-does-not-corrupt-existing-data
	test('an archive from another schema version is refused and changes nothing', async ({
		page,
	}) => {
		const before = await stampedDashboards()

		await openOperationsTab(page)
		const newer = await importThroughThePage(
			page,
			buildZip([
				{ name: 'manifest.json', content: manifest({ schemaVersion: 2 }) },
				{
					name: 'dashboards/from-the-future.json',
					content: dashboardPayload('from-the-future', `${STAMP} future`),
				},
			]),
		)

		expect(newer.status).toBe(400)
		expect(newer.body?.error).toBe(
			'Unsupported manifest schema version: 2. Only version 1 is supported. '
				+ 'Upgrade LaunchPad to import archives of version 2.',
		)
		// REQ-EXIM-009 asks for the admin to be TOLD to upgrade, which means on
		// the page, not only in a response body they never see.
		await expect(page.locator('.launchpad-export-import__error')).toContainText(
			'Upgrade LaunchPad',
		)

		const older = await importThroughThePage(
			page,
			buildZip([
				{ name: 'manifest.json', content: manifest({ schemaVersion: 0 }) },
			]),
		)
		expect(older.status).toBe(400)
		expect(older.body?.error).toContain('Unsupported manifest schema version: 0')

		const after = await stampedDashboards()
		expect(
			after.map((d) => d.uuid).sort(),
			'the instance must be untouched by a refused import',
		).toEqual(before.map((d) => d.uuid).sort())
	})

	// @e2e dashboard-export-import::zip-file-is-not-a-valid-zip-archive
	test('a file that is not a ZIP at all is refused', async ({ page }) => {
		await openOperationsTab(page)
		const outcome = await importThroughThePage(
			page,
			Buffer.from('I am a text file, not an archive.\n', 'utf8'),
			{ filename: 'notes.zip' },
		)

		expect(outcome.status).toBe(400)
		expect(outcome.body?.error).toBe('Uploaded file is not a valid ZIP archive')
		await expect(page.locator('.launchpad-export-import__error')).toContainText(
			'not a valid ZIP archive',
		)
	})

	// @e2e dashboard-export-import::dashboard-json-missing-required-fields
	test('a dashboard missing a required field is skipped and the rest are imported', async ({
		page,
	}) => {
		const keeper = `${STAMP} keeper`
		const archive = buildZip([
			{ name: 'manifest.json', content: manifest({ dashboardCount: 2 }) },
			{
				name: 'dashboards/nameless.json',
				content: JSON.stringify({ uuid: 'nameless-uuid', widgets: [] }),
			},
			{
				name: 'dashboards/keeper.json',
				content: dashboardPayload('keeper-uuid', keeper),
			},
		])

		await openOperationsTab(page)
		const outcome = await importThroughThePage(page, archive)

		expect(outcome.status).toBe(200)
		expect(outcome.body?.importedDashboardCount).toBe(1)
		expect(outcome.body?.skippedDashboardCount).toBe(1)
		expect(outcome.body?.errors?.[0]).toMatchObject({
			type: 'invalidDashboard',
			uuid: 'nameless-uuid',
			message: 'Missing required field: name',
		})
		await expect(page.locator('.launchpad-export-import__result')).toContainText(
			'Missing required field: name',
		)

		const created = (await stampedDashboards()).filter((d) => d.name === keeper)
		expect(created, 'the valid dashboard must still arrive').toHaveLength(1)
	})

	// @e2e dashboard-export-import::invalid-json-in-dashboard-file-skips-that-dashboard
	// @e2e dashboard-export-import::partial-import-on-multi-dashboard-failure
	test('a corrupt dashboard file is skipped, named, and does not stop the batch', async ({
		page,
	}) => {
		await openOperationsTab(page)

		const three = buildZip([
			{ name: 'manifest.json', content: manifest({ dashboardCount: 3 }) },
			{
				name: 'dashboards/good-a.json',
				content: dashboardPayload('good-a', `${STAMP} good a`),
			},
			{ name: 'dashboards/broken.json', content: '{"uuid": "broken", ' },
			{
				name: 'dashboards/good-b.json',
				content: dashboardPayload('good-b', `${STAMP} good b`),
			},
		])
		const outcome = await importThroughThePage(page, three)

		expect(outcome.status).toBe(200)
		expect(outcome.body?.importedDashboardCount).toBe(2)
		expect(outcome.body?.skippedDashboardCount).toBe(1)
		expect(
			outcome.body?.errors?.[0]?.message,
			'the error must identify the corrupt dashboard file',
		).toBe('dashboards/broken.json is not valid JSON')

		// REQ-EXIM-011: the same in a larger batch — one bad file, the other
		// nine still land, each in its own transaction.
		const ten = buildZip([
			{ name: 'manifest.json', content: manifest({ dashboardCount: 10 }) },
			...Array.from({ length: 10 }, (_unused, index) =>
				index === 4
					? {
							name: 'dashboards/batch-5.json',
							content: '{"uuid": "batch-5", "name": ',
						}
					: {
							name: `dashboards/batch-${index + 1}.json`,
							content: dashboardPayload(
								`batch-${index + 1}`,
								`${STAMP} batch ${index + 1}`,
							),
						},
			),
		])
		const batch = await importThroughThePage(page, ten)

		expect(batch.status).toBe(200)
		expect(batch.body?.importedDashboardCount).toBe(9)
		expect(batch.body?.skippedDashboardCount).toBe(1)
		expect(batch.body?.errors?.[0]?.uuid).toBe('batch-5')
	})

	// @e2e dashboard-export-import::fresh-uuids-by-default-safe-re-import
	test('re-importing a dashboard that still exists makes a copy beside it', async ({
		page,
	}) => {
		const archive = buildZip([
			{ name: 'manifest.json', content: manifest() },
			{
				name: `dashboards/${seededUuid}.json`,
				content: dashboardPayload(seededUuid, `${STAMP} re-import`),
			},
		])

		await openOperationsTab(page)
		const outcome = await importThroughThePage(page, archive)

		expect(outcome.status).toBe(200)
		expect(outcome.body?.importedDashboardCount).toBe(1)

		const all = await stampedDashboards()
		const original = all.filter((d) => d.uuid === seededUuid)
		const copy = all.filter((d) => d.name === `${STAMP} re-import`)
		expect(original, 'the original dashboard must survive').toHaveLength(1)
		expect(copy, 'the copy must exist').toHaveLength(1)
		expect(
			copy[0].uuid,
			'the copy must have a fresh UUID, not the one in the archive',
		).not.toBe(seededUuid)
	})

	// @e2e dashboard-export-import::preserve-uuids-with-collision-detection
	test('preserving UUIDs refuses an archive whose dashboard already exists', async ({
		page,
	}) => {
		const before = await stampedDashboards()
		const archive = buildZip([
			{ name: 'manifest.json', content: manifest() },
			{
				name: `dashboards/${seededUuid}.json`,
				content: dashboardPayload(seededUuid, `${STAMP} collider`),
			},
		])

		await openOperationsTab(page)
		const outcome = await importThroughThePage(page, archive, {
			preserveUuids: true,
		})

		expect(outcome.status).toBe(409)
		expect(outcome.body).toMatchObject({
			importedDashboardCount: 0,
			skippedDashboardCount: 0,
		})
		expect(outcome.body?.errors?.[0]).toMatchObject({
			type: 'uuidCollision',
			dashboard: seededUuid,
		})
		expect(outcome.body?.errors?.[0]?.message).toContain('already exists')
		// The admin sees the collision listed, not a bare failure.
		await expect(page.locator('.launchpad-export-import__result')).toContainText(
			'already exists',
		)

		expect(
			(await stampedDashboards()).map((d) => d.uuid).sort(),
			'nothing may be imported when a UUID collides',
		).toEqual(before.map((d) => d.uuid).sort())
	})

	// @e2e dashboard-export-import::multiple-uuid-collisions-reported-together
	test('every colliding dashboard is listed, and none of the archive is imported', async ({
		page,
	}) => {
		// Three dashboards that exist, two that do not: the archive collides on
		// three of five.
		const existing = [seededUuid]
		for (const suffix of ['second', 'third']) {
			const created = await api.post(`${API}/dashboard`, {
				data: { name: `${STAMP} ${suffix}` },
			})
			expect(created.status(), await created.text()).toBeLessThan(300)
			existing.push(String(((await created.json()).dashboard ?? {}).uuid))
		}

		const before = await stampedDashboards()
		const archive = buildZip([
			{ name: 'manifest.json', content: manifest({ dashboardCount: 5 }) },
			...existing.map((uuid, index) => ({
				name: `dashboards/${uuid}.json`,
				content: dashboardPayload(uuid, `${STAMP} collide ${index}`),
			})),
			...['fresh-one', 'fresh-two'].map((uuid) => ({
				name: `dashboards/${uuid}.json`,
				content: dashboardPayload(uuid, `${STAMP} ${uuid}`),
			})),
		])

		await openOperationsTab(page)
		const outcome = await importThroughThePage(page, archive, {
			preserveUuids: true,
		})

		expect(outcome.status).toBe(409)
		expect(
			(outcome.body?.errors ?? [])
				.map((e: { dashboard: string }) => e.dashboard)
				.sort(),
			'all three colliding UUIDs must be reported together',
		).toEqual([...existing].sort())
		expect(outcome.body?.importedDashboardCount).toBe(0)

		expect(
			(await stampedDashboards()).map((d) => d.uuid).sort(),
			'a collision is all-or-nothing: the two fresh dashboards must not land',
		).toEqual(before.map((d) => d.uuid).sort())
	})
})
