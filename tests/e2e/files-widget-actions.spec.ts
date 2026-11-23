/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for uploading into and deleting from the files
 * widget as a viewer who may do both (openspec/specs/files-widget,
 * REQ-FLS-007 and REQ-FLS-008). Written for e2e-coverage-programme.
 *
 * Uploads go through the widget's own "Upload File" button (Playwright's file
 * chooser) or a real drop of a DataTransfer onto the widget. Every result is
 * checked twice: in the widget, and in the admin's home over WebDAV, so a
 * row that renders without a file behind it does not pass. Deletes are
 * confirmed in the widget's dialog and then looked up in the trash bin.
 *
 * Where the spec asks for UI the widget may not draw yet (per-file upload
 * progress), the assertion is `expect.soft`, so the clauses after it are
 * still measured and reported in the same run.
 *
 * Gate-19 @e2e traceability:
 *   @e2e files-widget::upload-button-appears-when-allowed
 *   @e2e files-widget::drag-drop-upload-multiple-files
 *   @e2e files-widget::upload-progress-indication
 *   @e2e files-widget::upload-conflict-handling
 *   @e2e files-widget::delete-button-appears-when-allowed
 *   @e2e files-widget::delete-with-confirmation
 *   @e2e files-widget::delete-moves-to-trash
 *   @e2e files-widget::delete-folder-recursively
 *
 * @spec openspec/specs/files-widget/spec.md
 */

import type { APIRequestContext, Locator, Page, Request, Route } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import {
	ADMIN,
	apiAs,
	crumbs,
	davExists,
	filesWidget,
	mkdirs,
	nextContents,
	openOnWidget,
	purge,
	putFile,
	readFile,
	removePlacements,
	rowNames,
	seedFilesPlacement,
	trashEntries,
} from './support/filesWidget.ts'

const RUN = Date.now()
const ROOT = `E2E-FW-actions-${RUN}`

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
const placements: number[] = []
const trashed: string[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await apiAs()
	dashboard = await seedActiveDashboard(admin, `E2E Files actions ${RUN}`)
	await mkdirs(admin, ADMIN.user, ROOT)
})

test.afterEach(async () => {
	await removePlacements(admin, placements)
	for (const name of trashed.splice(0)) {
		for (const href of await trashEntries(admin, ADMIN.user, name)) {
			await admin.fetch(new URL(href, 'http://x').pathname, { method: 'DELETE' })
		}
	}
})

test.afterAll(async () => {
	await purge(admin, ADMIN.user, ROOT)
	await removeSeededDashboard(admin, dashboard)
	await admin.dispose()
})

/**
 * Seed a folder below ROOT with files, and place a widget on it.
 *
 * @param folder the folder name.
 * @param files name → content.
 * @param content extra widget content.
 * @return the folder path and the placement id.
 */
async function folderWidget(
	folder: string,
	files: Record<string, string>,
	content: Record<string, unknown>,
): Promise<{ dir: string, id: number }> {
	const dir = `${ROOT}/${folder}`
	await mkdirs(admin, ADMIN.user, dir)
	for (const [name, body] of Object.entries(files)) {
		if (name.endsWith('/')) {
			await mkdirs(admin, ADMIN.user, `${dir}/${name}`)
		} else {
			await putFile(admin, ADMIN.user, `${dir}/${name}`, body)
		}
	}
	const id = await seedFilesPlacement(admin, dashboard!.id, { folderPath: `/${dir}`, ...content })
	placements.push(id)
	return { dir, id }
}

/**
 * Hold the widget's upload requests for a while, so the in-progress state is
 * on screen long enough to look at.
 *
 * @param page the page.
 * @param ms how long to hold each request.
 * @return the upload requests seen, as they arrive.
 */
async function slowUploads(page: Page, ms: number): Promise<Request[]> {
	const seen: Request[] = []
	await page.route((url) => /\/api\/widgets\/files\/\d+\/upload$/.test(url.pathname), async (route: Route) => {
		seen.push(route.request())
		await new Promise((resolve) => setTimeout(resolve, ms))
		await route.continue()
	})
	return seen
}

/**
 * Wait for the widget's next upload response.
 *
 * @param page the page.
 * @param id the placement.
 * @return the response.
 */
function nextUpload(page: Page, id: number) {
	return page.waitForResponse((r) => r.url().includes(`/api/widgets/files/${id}/upload`), { timeout: 120_000 })
}

// @e2e files-widget::upload-button-appears-when-allowed
test('with allowUpload and write permission the Upload button is shown', async ({ page }) => {
	const { id } = await folderWidget('upload-shown', { 'existing.txt': 'x' }, { allowUpload: true })

	const response = await openOnWidget(page, id)
	expect((await response.json()).items[0].canEdit).toBe(true)
	await expect(filesWidget(page, id).getByRole('button', { name: 'Upload File' })).toBeVisible()
})

// @e2e files-widget::drag-drop-upload-multiple-files
test('dropping three files uploads all three, each with its own progress, and lists them', async ({ page }) => {
	const { dir, id } = await folderWidget('dropped', { 'existing.txt': 'x' }, { allowUpload: true })
	const names = [`drop-a-${RUN}.txt`, `drop-b-${RUN}.txt`, `drop-c-${RUN}.txt`]

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	await expect(widget.getByRole('button', { name: 'Upload File' })).toBeVisible()

	const uploads = await slowUploads(page, 8_000)
	const done = nextUpload(page, id)
	await widget.evaluate((el, files) => {
		const transfer = new DataTransfer()
		for (const name of files) {
			transfer.items.add(new File([`dropped ${name}`], name, { type: 'text/plain' }))
		}
		for (const type of ['dragenter', 'dragover', 'drop']) {
			el.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: transfer }))
		}
	}, names)

	// All three are accepted for upload.
	await expect.poll(() => uploads.length, { timeout: 10_000 }).toBeGreaterThan(0)
	const sent = uploads.map((r) => r.postDataBuffer()?.toString('latin1') ?? '').join('\n')
	for (const name of names) {
		expect(sent).toContain(`filename="${name}"`)
	}

	// Each file shows its own progress while the upload is in flight.
	for (const name of names) {
		await expect.soft(
			widget.locator('[role="progressbar"], progress, [class*="progress"]').filter({ hasText: name }),
			`progress for ${name}`,
		).toHaveCount(1, { timeout: 2_000 })
	}

	expect((await done).status()).toBe(200)
	await expect(widget.locator('.cn-files-widget__row')).toHaveCount(4, { timeout: 20_000 })
	expect((await rowNames(widget)).sort()).toEqual([...names, 'existing.txt'].sort())
	for (const name of names) {
		expect(await davExists(admin, ADMIN.user, `${dir}/${name}`), name).toBe(true)
	}
})

/**
 * One file's entry in the upload list (the widget's upload status region),
 * where its name and its status sit side by side.
 *
 * @param widget the files widget.
 * @param name the file name.
 * @return the list item.
 */
function uploadEntry(widget: Locator, name: string): Locator {
	return widget.getByRole('status').getByRole('listitem').filter({ hasText: name })
}

// @e2e files-widget::upload-progress-indication
test('a 50 MB upload shows how far it is and which files are queued, uploading or done', async ({ page }, testInfo) => {
	const { dir, id } = await folderWidget('progress', { 'existing.txt': 'x' }, { allowUpload: true })
	const big = testInfo.outputPath(`big-${RUN}.bin`)
	fs.writeFileSync(big, Buffer.alloc(50_000_000, 0x61))
	const small = testInfo.outputPath(`small-${RUN}.txt`)
	fs.writeFileSync(small, 'small')

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	await slowUploads(page, 10_000)
	const done = nextUpload(page, id)

	const chooser = page.waitForEvent('filechooser')
	await widget.getByRole('button', { name: 'Upload File' }).click()
	await (await chooser).setFiles([big, small])

	// A progress bar or an "x/50 MB uploaded" figure is on screen…
	await expect.soft(
		widget.locator('[role="progressbar"], progress').or(widget.getByText(/\d+(\.\d+)?\s*\/\s*(47\.7|50)(\.0)?\s*MB/)).first(),
	).toBeVisible({ timeout: 3_000 })
	// …and each file says where it is in the queue.
	for (const name of [`big-${RUN}.bin`, `small-${RUN}.txt`]) {
		await expect.soft(
			uploadEntry(widget, name).filter({ hasText: /queued|in progress|uploading|completed|uploaded|sent|done/i }),
			`status of ${name}`,
		).toBeVisible({ timeout: 3_000 })
	}

	expect((await done).status()).toBe(200)
	await expect(widget.locator('.cn-files-widget__row')).toHaveCount(3, { timeout: 30_000 })
	expect(await davExists(admin, ADMIN.user, `${dir}/big-${RUN}.bin`)).toBe(true)
	expect(await davExists(admin, ADMIN.user, `${dir}/small-${RUN}.txt`)).toBe(true)
	// Once finished, both read as completed.
	for (const name of [`big-${RUN}.bin`, `small-${RUN}.txt`]) {
		await expect.soft(
			uploadEntry(widget, name).filter({ hasText: /completed|uploaded|done/i }),
			`completed state of ${name}`,
		).toBeVisible({ timeout: 3_000 })
	}
})

// @e2e files-widget::upload-conflict-handling
test('uploading report.pdf over an existing report.pdf keeps both, the new one as report (1).pdf', async ({ page }, testInfo) => {
	const { dir, id } = await folderWidget('conflict', { 'report.pdf': 'original' }, { allowUpload: true })
	const upload = testInfo.outputPath('report.pdf')
	fs.writeFileSync(upload, 'replacement')

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	const done = nextUpload(page, id)
	const chooser = page.waitForEvent('filechooser')
	await widget.getByRole('button', { name: 'Upload File' }).click()
	await (await chooser).setFiles(upload)
	const response = await done
	expect(response.status()).toBe(200)
	expect((await response.json()).uploaded.map((u: any) => u.name)).toEqual(['report (1).pdf'])

	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['report (1).pdf', 'report.pdf'], { timeout: 20_000 })
	expect((await readFile(admin, ADMIN.user, `${dir}/report.pdf`)).toString()).toBe('original')
	expect((await readFile(admin, ADMIN.user, `${dir}/report (1).pdf`)).toString()).toBe('replacement')
})

// @e2e files-widget::delete-button-appears-when-allowed
test('with allowDelete and delete permission every item carries a delete button', async ({ page }) => {
	const { id } = await folderWidget('delete-shown', { 'report.pdf': 'r', 'notes.txt': 'n', 'archive/': '' }, { allowDelete: true })

	const response = await openOnWidget(page, id)
	for (const item of (await response.json()).items) {
		expect(item.canDelete, item.name).toBe(true)
	}
	const widget = filesWidget(page, id)
	for (const name of ['archive', 'notes.txt', 'report.pdf']) {
		await expect(widget.getByRole('button', { name: `Delete ${name}`, exact: true })).toBeVisible()
	}
})

// @e2e files-widget::delete-with-confirmation
test('the delete button asks first, and Cancel deletes nothing', async ({ page }) => {
	const { dir, id } = await folderWidget('confirm', { 'report.pdf': 'r', 'keep.txt': 'k' }, { allowDelete: true })

	await openOnWidget(page, id)
	const deletes: string[] = []
	page.on('request', (r) => {
		if (r.method() === 'DELETE') {
			deletes.push(r.url())
		}
	})
	const widget = filesWidget(page, id)
	await widget.getByRole('button', { name: 'Delete report.pdf', exact: true }).click()

	const dialog = page.getByRole('dialog', { name: 'Delete file' })
	await expect(dialog).toBeVisible()
	await expect(dialog.getByText('Are you sure you want to delete report.pdf?')).toBeVisible()
	await expect(dialog.getByRole('button', { name: 'Delete', exact: true })).toBeVisible()
	await dialog.getByRole('button', { name: 'Cancel' }).click()
	await expect(dialog).toBeHidden()

	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['keep.txt', 'report.pdf'])
	expect(deletes).toEqual([])
	expect(await davExists(admin, ADMIN.user, `${dir}/report.pdf`)).toBe(true)
})

// @e2e files-widget::delete-moves-to-trash
test('a confirmed delete moves the file to the trash bin and it stays gone after a refresh', async ({ page }) => {
	const name = `trash-me-${RUN}.pdf`
	trashed.push(name)
	const { dir, id } = await folderWidget('trash', { [name]: 'bye', 'keep.txt': 'k' }, { allowDelete: true })

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	await widget.getByRole('button', { name: `Delete ${name}`, exact: true }).click()
	const dialog = page.getByRole('dialog', { name: 'Delete file' })
	const deleted = page.waitForResponse((r) => r.request().method() === 'DELETE' && r.url().includes(`/api/widgets/files/${id}/files/`))
	await dialog.getByRole('button', { name: 'Delete', exact: true }).click()
	expect((await deleted).status()).toBe(200)

	// Gone from the listing at once…
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['keep.txt'])
	// …gone after a refresh…
	await openOnWidget(page, id)
	expect(await rowNames(filesWidget(page, id))).toEqual(['keep.txt'])
	// …and in the trash bin rather than destroyed.
	expect(await davExists(admin, ADMIN.user, `${dir}/${name}`)).toBe(false)
	expect(await trashEntries(admin, ADMIN.user, name)).toHaveLength(1)
})

// @e2e files-widget::delete-folder-recursively
test('deleting a subfolder removes it with everything inside it', async ({ page }) => {
	const folder = `old-project-${RUN}`
	trashed.push(folder)
	const { dir, id } = await folderWidget('recursive', {
		'keep.txt': 'k',
		[`${folder}/`]: '',
		[`${folder}/plan.txt`]: 'plan',
		[`${folder}/drafts/`]: '',
		[`${folder}/drafts/draft.txt`]: 'draft',
	}, { allowDelete: true })

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	await widget.getByRole('button', { name: `Delete ${folder}`, exact: true }).click()
	const deleted = page.waitForResponse((r) => r.request().method() === 'DELETE' && r.url().includes(`/api/widgets/files/${id}/files/`))
	await page.getByRole('dialog', { name: 'Delete file' }).getByRole('button', { name: 'Delete', exact: true }).click()
	expect((await deleted).status()).toBe(200)

	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['keep.txt'])
	expect(await crumbs(widget)).not.toContain(folder)
	const refresh = nextContents(page, id)
	await widget.locator('.cn-files-widget__crumb--root').click()
	expect((await (await refresh).json()).items.map((i: any) => i.name)).toEqual(['keep.txt'])

	for (const p of [folder, `${folder}/plan.txt`, `${folder}/drafts`, `${folder}/drafts/draft.txt`]) {
		expect(await davExists(admin, ADMIN.user, `${dir}/${p}`), p).toBe(false)
	}
})
