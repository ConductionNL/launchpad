/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the files widget seen by someone whose
 * rights on the folder are not the author's (openspec/specs/files-widget,
 * REQ-FLS-002 upload/delete flags, REQ-FLS-004, -007, -008 and -009).
 * Written for e2e-coverage-programme.
 *
 * The rights come from plain Nextcloud sharing, which every instance has: the
 * admin owns a folder and shares it with a throwaway reader at a chosen
 * permission level, then changes or revokes that share mid-test. The reader
 * logs in through the real login form and places the widget on a dashboard
 * of their own (so LaunchPad's own dashboard permissions are never the
 * reason for a refusal). Refusals are probed as that reader, with the exact
 * status the spec names, and the admin then re-reads the folder over WebDAV to
 * confirm nothing changed.
 *
 * Gate-19 @e2e traceability:
 *   @e2e files-widget::config-for-upload-and-delete-permissions
 *   @e2e files-widget::user-cannot-read-folder
 *   @e2e files-widget::user-loses-permission-after-widget-creation
 *   @e2e files-widget::uploaddelete-checks-at-action-time
 *   @e2e files-widget::upload-button-hidden-when-not-allowed
 *   @e2e files-widget::delete-button-hidden-when-not-allowed
 *   @e2e files-widget::delete-on-permission-denied-folder
 *   @e2e files-widget::folder-deleted
 *   @e2e files-widget::share-revoked-after-widget-creation
 *   @e2e files-widget::file-in-configured-folder-deleted-folder-exists
 *   @e2e files-widget::upload-fails-gracefully
 *
 * @spec openspec/specs/files-widget/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Page } from '@playwright/test'
import type { Account } from './support/filesWidget.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import { deprovisionUser, loginAs, provisionThrowawayUser } from './fixtures/secondary-user.ts'
import {
	activate,
	addAndReadBack,
	ADMIN,
	API,
	apiAs,
	davExists,
	fileIdOf,
	filesWidget,
	mkdirs,
	nextContents,
	openAddFilesWidget,
	openOnWidget,
	PERM,
	purge,
	putFile,
	removePlacements,
	rowNames,
	seedFilesPlacement,
	seedOwnActiveDashboard,
	setSharePermissions,
	shareWithUser,
	unshare,
} from './support/filesWidget.ts'

const RUN = Date.now()
const ROOT = `E2E-FW-access-${RUN}`

let admin: APIRequestContext
let reader: Account | null = null
let readerApi: APIRequestContext
let adminDash: { id: number, uuid: string }
let readerDash: { id: number, uuid: string }
const adminPlacements: number[] = []
const readerPlacements: number[] = []
const shares: number[] = []
const extraDashboards: number[] = []
const sessions: BrowserContext[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await apiAs()
	const flag = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(flag.status(), await flag.text()).toBeLessThan(300)
	adminDash = await seedOwnActiveDashboard(admin, `E2E Files access admin ${RUN}`)
	await mkdirs(admin, ADMIN.user, ROOT)

	reader = await provisionThrowawayUser('e2e-files-reader')
	readerApi = await apiAs(reader)
	readerDash = await seedOwnActiveDashboard(readerApi, `E2E Files access reader ${RUN}`)
})

test.afterEach(async () => {
	for (const s of sessions.splice(0)) {
		await s.close()
	}
	await removePlacements(admin, adminPlacements)
	await removePlacements(readerApi, readerPlacements)
	for (const id of shares.splice(0)) {
		await unshare(admin, id)
	}
	for (const id of extraDashboards.splice(0)) {
		await admin.delete(`${API}/dashboard/${id}`)
	}
})

test.afterAll(async () => {
	await purge(admin, ADMIN.user, ROOT)
	await admin.delete(`${API}/dashboard/${adminDash.id}`)
	await readerApi?.dispose()
	if (reader !== null) {
		await deprovisionUser(reader.username)
	}
	await admin.dispose()
})

/**
 * Create a folder of the admin's, below ROOT, with files in it.
 *
 * @param name the folder name; also its name in the reader's home once shared.
 * @param files the file names to write.
 * @return the admin-home path of the folder, without a leading slash.
 */
async function adminFolder(name: string, files: string[]): Promise<string> {
	const dir = `${ROOT}/${name}`
	await mkdirs(admin, ADMIN.user, dir)
	for (const file of files) {
		await putFile(admin, ADMIN.user, `${dir}/${file}`, `content of ${file}`)
	}
	return dir
}

/**
 * Share an admin folder with the reader, remembering the share for cleanup.
 *
 * @param dir the admin-home path.
 * @param permissions the share permission bits.
 * @return the share id.
 */
async function shareToReader(dir: string, permissions: number): Promise<number> {
	const id = await shareWithUser(admin, `/${dir}`, reader!.username, permissions)
	shares.push(id)
	return id
}

/**
 * Place a files widget on the reader's own dashboard.
 *
 * @param content the widget content.
 * @return the placement id.
 */
async function readerWidget(content: Record<string, unknown>): Promise<number> {
	const id = await seedFilesPlacement(readerApi, readerDash.id, content)
	readerPlacements.push(id)
	return id
}

/**
 * Log the reader in, with their own dashboard as the one LaunchPad opens.
 *
 * @param browser the browser.
 * @param dashboard the dashboard to open; the reader's own by default.
 * @param dashboard.id its id.
 * @param dashboard.uuid its uuid.
 * @return the reader's page.
 */
async function readerPage(browser: Browser, dashboard: { id: number, uuid: string } = readerDash): Promise<Page> {
	const api = await apiAs(reader!)
	try {
		await api.post(`${API}/dashboards/active`, { data: { uuid: dashboard.uuid } })
	} finally {
		await api.dispose()
	}
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	sessions.push(context)
	return page
}

// @e2e files-widget::config-for-upload-and-delete-permissions
test('upload and delete default to off, can be switched on, and only show where the viewer has the right', async ({ browser, page }) => {
	await activate(admin, adminDash)

	// The dialog stores both flags, false when left alone…
	let dialog = await openAddFilesWidget(page)
	const untouched = await addAndReadBack(page, dialog, admin, adminDash.id)
	adminPlacements.push(Number(untouched.id))
	expect(untouched.content.allowUpload).toBe(false)
	expect(untouched.content.allowDelete).toBe(false)
	// …and true when switched on.
	dialog = await openAddFilesWidget(page)
	await dialog.getByLabel('Allow upload').check()
	await dialog.getByLabel('Allow delete').check()
	const enabled = await addAndReadBack(page, dialog, admin, adminDash.id)
	adminPlacements.push(Number(enabled.id))
	expect(enabled.content.allowUpload).toBe(true)
	expect(enabled.content.allowDelete).toBe(true)

	// The admin owns the folder: with the flags on, both affordances show.
	const own = await adminFolder('flags-own', ['mine.txt'])
	const on = await seedFilesPlacement(admin, adminDash.id, { folderPath: `/${own}`, allowUpload: true, allowDelete: true }, 30)
	const unset = await seedFilesPlacement(admin, adminDash.id, { folderPath: `/${own}` }, 40)
	adminPlacements.push(on, unset)
	const pending = nextContents(page, unset)
	await openOnWidget(page, on)
	await pending
	await expect(filesWidget(page, on).getByRole('button', { name: 'Upload File' })).toBeVisible()
	await expect(filesWidget(page, on).getByRole('button', { name: 'Delete mine.txt' })).toBeVisible()
	// With neither flag stored, both default to false.
	await expect(filesWidget(page, unset).locator('.cn-files-widget__row')).toHaveCount(1)
	await expect(filesWidget(page, unset).getByRole('button', { name: 'Delete mine.txt' })).toHaveCount(0)
	await expect(filesWidget(page, unset).getByRole('button', { name: 'Upload File' })).toHaveCount(0)

	// A flag does not grant a right: a read-only viewer gets neither.
	const ro = await adminFolder(`flags-ro-${RUN}`, ['theirs.txt'])
	await shareToReader(ro, PERM.READ)
	const readers = await readerWidget({ folderPath: `/flags-ro-${RUN}`, allowUpload: true, allowDelete: true })
	const readerView = await readerPage(browser)
	await openOnWidget(readerView, readers)
	const widget = filesWidget(readerView, readers)
	expect(await rowNames(widget)).toEqual(['theirs.txt'])
	await expect(widget.getByRole('button', { name: 'Upload File' })).toHaveCount(0)
	await expect(widget.getByRole('button', { name: /^Delete / })).toHaveCount(0)
})

// @e2e files-widget::user-cannot-read-folder
test('a viewer who cannot read the configured folder gets 403 no_access and the access-denied state', async ({ browser }) => {
	const dir = await adminFolder(`private-${RUN}`, ['secret-plan.txt'])
	const folderId = await fileIdOf(admin, ADMIN.user, dir)

	// A dashboard of the admin's, shared with the reader at full rights, so the
	// dashboard itself is not what refuses them.
	const created = await admin.post(`${API}/dashboard`, { data: { name: `E2E Files private ${RUN}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	extraDashboards.push(Number(dash.id))
	const share = await admin.post(`${API}/dashboard/${dash.id}/shares`, {
		data: { shareType: 'user', shareWith: reader!.username, permissionLevel: 'full' },
	})
	expect(share.status(), await share.text()).toBeLessThan(300)
	const id = await seedFilesPlacement(admin, Number(dash.id), { fileId: folderId, folderPath: `/${dir}` })

	// Another user — the owner — can read it.
	const owner = await admin.get(`${API}/widgets/files/${id}/contents`)
	expect(owner.status()).toBe(200)
	expect((await owner.json()).items.map((i: any) => i.name)).toEqual(['secret-plan.txt'])

	const page = await readerPage(browser, { id: Number(dash.id), uuid: String(dash.uuid) })
	try {
		const response = await openOnWidget(page, id)
		expect(response.status()).toBe(403)
		expect(await response.json()).toMatchObject({ error: 'no_access', message: 'You don\'t have access to this folder' })
		const widget = filesWidget(page, id)
		await expect(widget.getByText('You don\'t have access to this folder.')).toBeVisible()
		// Nothing about the folder is disclosed.
		await expect(widget).not.toContainText('secret-plan.txt')
		await expect(widget).not.toContainText(`private-${RUN}`)
	} finally {
		await activate(readerApi, readerDash)
	}
})

// @e2e files-widget::user-loses-permission-after-widget-creation
test('when the owner revokes read access, the next load answers 403 no_access and the widget says so', async ({ browser }) => {
	const name = `revoked-path-${RUN}`
	const dir = await adminFolder(name, ['was-visible.txt'])
	const shareId = await shareToReader(dir, PERM.READ)
	const id = await readerWidget({ folderPath: `/${name}` })

	const page = await readerPage(browser)
	await openOnWidget(page, id)
	expect(await rowNames(filesWidget(page, id))).toEqual(['was-visible.txt'])

	await unshare(admin, shareId)
	const response = await openOnWidget(page, id)
	expect(response.status()).toBe(403)
	expect((await response.json()).error).toBe('no_access')
	const widget = filesWidget(page, id)
	await expect(widget.getByText('You don\'t have access to this folder.')).toBeVisible()
	await expect(widget.locator('.cn-files-widget__row')).toHaveCount(0)
})

// @e2e files-widget::uploaddelete-checks-at-action-time
test('on a readable but read-only folder neither upload nor delete is offered, and a forced DELETE is 403', async ({ browser }) => {
	const name = `readonly-${RUN}`
	const dir = await adminFolder(name, ['kept.txt'])
	await shareToReader(dir, PERM.READ)
	const id = await readerWidget({ folderPath: `/${name}`, allowUpload: true, allowDelete: true })
	const fileId = await fileIdOf(readerApi, reader!.username, `${name}/kept.txt`)

	const page = await readerPage(browser)
	const response = await openOnWidget(page, id)
	expect((await response.json()).items).toMatchObject([{ name: 'kept.txt', canEdit: false, canDelete: false }])
	const widget = filesWidget(page, id)
	expect(await rowNames(widget)).toEqual(['kept.txt'])
	await expect(widget.getByRole('button', { name: 'Upload File' })).toHaveCount(0)
	await expect(widget.getByRole('button', { name: /^Delete / })).toHaveCount(0)

	// Submitting the delete anyway is refused by the backend…
	const forced = await readerApi.delete(`${API}/widgets/files/${id}/files/${fileId}`)
	expect(forced.status()).toBe(403)
	// …and the file is still there.
	expect(await davExists(admin, ADMIN.user, `${dir}/kept.txt`)).toBe(true)
})

// @e2e files-widget::upload-button-hidden-when-not-allowed
test('the Upload button is absent when allowUpload is false, and when the viewer cannot write', async ({ browser, page }) => {
	await activate(admin, adminDash)
	const own = await adminFolder('upload-off', ['mine.txt'])
	const off = await seedFilesPlacement(admin, adminDash.id, { folderPath: `/${own}`, allowUpload: false })
	adminPlacements.push(off)
	const ownResponse = await openOnWidget(page, off)
	expect((await ownResponse.json()).items[0].canEdit).toBe(true)
	await expect(filesWidget(page, off).locator('.cn-files-widget__row')).toHaveCount(1)
	await expect(filesWidget(page, off).getByRole('button', { name: 'Upload File' })).toHaveCount(0)

	const name = `upload-ro-${RUN}`
	await shareToReader(await adminFolder(name, ['theirs.txt']), PERM.READ)
	const id = await readerWidget({ folderPath: `/${name}`, allowUpload: true })
	const readerView = await readerPage(browser)
	await openOnWidget(readerView, id)
	await expect(filesWidget(readerView, id).locator('.cn-files-widget__row')).toHaveCount(1)
	await expect(filesWidget(readerView, id).getByRole('button', { name: 'Upload File' })).toHaveCount(0)
})

// @e2e files-widget::delete-button-hidden-when-not-allowed
test('no delete button when allowDelete is false, nor on a file the viewer may not delete', async ({ browser, page }) => {
	await activate(admin, adminDash)
	const own = await adminFolder('delete-off', ['mine.txt'])
	const off = await seedFilesPlacement(admin, adminDash.id, { folderPath: `/${own}`, allowDelete: false })
	adminPlacements.push(off)
	const ownResponse = await openOnWidget(page, off)
	expect((await ownResponse.json()).items[0].canDelete).toBe(true)
	await expect(filesWidget(page, off).locator('.cn-files-widget__row')).toHaveCount(1)
	await expect(filesWidget(page, off).getByRole('button', { name: /^Delete / })).toHaveCount(0)

	const name = `delete-ro-${RUN}`
	await shareToReader(await adminFolder(name, ['theirs.txt']), PERM.READ)
	const id = await readerWidget({ folderPath: `/${name}`, allowDelete: true })
	const readerView = await readerPage(browser)
	const response = await openOnWidget(readerView, id)
	expect((await response.json()).items[0].canDelete).toBe(false)
	await expect(filesWidget(readerView, id).locator('.cn-files-widget__row')).toHaveCount(1)
	await expect(filesWidget(readerView, id).getByRole('button', { name: /^Delete / })).toHaveCount(0)
})

// @e2e files-widget::delete-on-permission-denied-folder
test('a delete after the folder turned read-only is refused with 403 and the widget says why', async ({ browser }) => {
	const name = `turned-ro-${RUN}`
	const dir = await adminFolder(name, ['doomed.txt'])
	const shareId = await shareToReader(dir, PERM.ALL_FOLDER)
	const id = await readerWidget({ folderPath: `/${name}`, allowDelete: true })

	const page = await readerPage(browser)
	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	const button = widget.getByRole('button', { name: 'Delete doomed.txt', exact: true })
	await expect(button).toBeVisible()

	// The folder becomes read-only while the widget is on screen.
	await setSharePermissions(admin, shareId, PERM.READ)

	await button.click()
	const refused = page.waitForResponse((r) => r.request().method() === 'DELETE' && r.url().includes(`/api/widgets/files/${id}/files/`))
	await page.getByRole('dialog', { name: 'Delete file' }).getByRole('button', { name: 'Delete', exact: true }).click()
	expect((await refused).status()).toBe(403)
	expect(await davExists(admin, ADMIN.user, `${dir}/doomed.txt`)).toBe(true)
	await expect(widget.getByText(/no longer have permission to delete/i)).toBeVisible()
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['doomed.txt'])
})

// @e2e files-widget::folder-deleted
test('when another user deletes the configured folder the widget shows "Folder no longer exists"', async ({ browser }) => {
	const name = `deleted-${RUN}`
	const dir = await adminFolder(name, ['inside.txt'])
	await shareToReader(dir, PERM.READ)
	const folderId = await fileIdOf(readerApi, reader!.username, name)
	const id = await readerWidget({ fileId: folderId })

	const page = await readerPage(browser)
	const errors: string[] = []
	page.on('pageerror', (e) => errors.push(e.message))
	await openOnWidget(page, id)
	expect(await rowNames(filesWidget(page, id))).toEqual(['inside.txt'])

	await purge(admin, ADMIN.user, dir)
	const response = await openOnWidget(page, id)
	expect(response.status()).toBe(404)
	expect((await response.json()).error).toBe('folder_not_found')
	const widget = filesWidget(page, id)
	await expect(widget.getByText('Folder no longer exists.')).toBeVisible()
	await expect(widget.getByText('Failed to load folder contents.')).toHaveCount(0)
	await expect(widget).not.toContainText(/exception|stack|\.php/i)
	expect(errors).toEqual([])
})

// @e2e files-widget::share-revoked-after-widget-creation
test('when a shared folder is unshared, the widget answers 403 and says the viewer has no access', async ({ browser }) => {
	const name = `unshared-${RUN}`
	const dir = await adminFolder(name, ['shared-once.txt'])
	const shareId = await shareToReader(dir, PERM.READ)
	const folderId = await fileIdOf(readerApi, reader!.username, name)
	const id = await readerWidget({ fileId: folderId, folderPath: `/${name}` })

	const page = await readerPage(browser)
	await openOnWidget(page, id)
	expect(await rowNames(filesWidget(page, id))).toEqual(['shared-once.txt'])

	await unshare(admin, shareId)
	const response = await openOnWidget(page, id)
	expect(response.status()).toBe(403)
	expect((await response.json()).error).toBe('no_access')
	await expect(filesWidget(page, id).getByText('You don\'t have access to this folder.')).toBeVisible()
})

// @e2e files-widget::file-in-configured-folder-deleted-folder-exists
test('a file another user deleted is gone on refresh while its siblings stay', async ({ browser }) => {
	const name = `three-${RUN}`
	const dir = await adminFolder(name, ['one.txt', 'two.txt', 'three.txt'])
	await shareToReader(dir, PERM.READ)
	const id = await readerWidget({ folderPath: `/${name}` })

	const page = await readerPage(browser)
	await openOnWidget(page, id)
	expect(await rowNames(filesWidget(page, id))).toEqual(['one.txt', 'three.txt', 'two.txt'])

	await purge(admin, ADMIN.user, `${dir}/two.txt`)
	const refresh = nextContents(page, id)
	await filesWidget(page, id).locator('.cn-files-widget__crumb--root').click()
	expect((await (await refresh).json()).items.map((i: any) => i.name)).toEqual(['one.txt', 'three.txt'])
	await expect(filesWidget(page, id).locator('.cn-files-widget__row-label')).toHaveText(['one.txt', 'three.txt'])
})

// @e2e files-widget::upload-fails-gracefully
test('a file larger than the free space fails on its own, is reported, and leaves the listing intact', async ({ browser }, testInfo) => {
	const tight = await provisionThrowawayUser('e2e-files-quota')
	const tightApi = await apiAs(tight)
	try {
		await mkdirs(tightApi, tight.username, 'inbox')
		await putFile(tightApi, tight.username, 'inbox/existing.txt', 'already here')
		const info = await admin.get(`/ocs/v1.php/cloud/users/${encodeURIComponent(tight.username)}?format=json`)
		const used = Number((await info.json()).ocs.data.quota?.used ?? 0)
		const quota = await admin.put(`/ocs/v1.php/cloud/users/${encodeURIComponent(tight.username)}`, {
			form: { key: 'quota', value: String(used + 1_048_576) },
		})
		expect(quota.ok(), await quota.text()).toBe(true)

		const dash = await seedOwnActiveDashboard(tightApi, `E2E Files quota ${RUN}`)
		const id = await seedFilesPlacement(tightApi, dash.id, { folderPath: '/inbox', allowUpload: true })

		const big = testInfo.outputPath(`too-big-${RUN}.bin`)
		fs.writeFileSync(big, Buffer.alloc(3_000_000, 0x62))
		const small = testInfo.outputPath(`fits-${RUN}.txt`)
		fs.writeFileSync(small, 'fits')

		const { context, page } = await loginAs(browser, tight.username, tight.password)
		sessions.push(context)
		await openOnWidget(page, id)
		const widget = filesWidget(page, id)

		const done = page.waitForResponse((r) => r.url().includes(`/api/widgets/files/${id}/upload`), { timeout: 60_000 })
		const chooser = page.waitForEvent('filechooser')
		await widget.getByRole('button', { name: 'Upload File' }).click()
		await (await chooser).setFiles([big, small])
		const response = await done

		// The backend reports an error for the file that does not fit.
		const body = await response.json().catch(() => ({}))
		const refusedBig = response.status() >= 400
			|| (Array.isArray(body.errors) && body.errors.some((e: any) => e.name === `too-big-${RUN}.bin`))
		expect(refusedBig, `${response.status()} ${JSON.stringify(body)}`).toBe(true)

		// The other upload is not blocked, and nothing half-written is listed.
		await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['existing.txt', `fits-${RUN}.txt`], { timeout: 20_000 })
		expect(await davExists(tightApi, tight.username, `inbox/too-big-${RUN}.bin`)).toBe(false)
		expect(await davExists(tightApi, tight.username, `inbox/fits-${RUN}.txt`)).toBe(true)

		// The widget names the file that failed.
		await expect(widget.getByText(new RegExp(`too-big-${RUN}\\.bin`))).toBeVisible()
	} finally {
		await tightApi.dispose()
		await deprovisionUser(tight.username)
	}
})
