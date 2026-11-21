/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for what the files widget lists and how it
 * draws it (openspec/specs/files-widget, REQ-FLS-003 and REQ-FLS-010).
 * Written for e2e-coverage-programme.
 *
 * Each test writes the files it needs into the admin's home over WebDAV,
 * places a files widget pointing at them, opens LaunchPad and reads both the
 * widget's own `GET /api/widgets/files/{id}/contents` response (captured, not
 * re-requested) and the rendered rows.
 *
 * Gate-19 @e2e traceability:
 *   @e2e files-widget::fetch-folder-contents
 *   @e2e files-widget::pagination-with-cursor
 *   @e2e files-widget::mime-type-filter-applied
 *   @e2e files-widget::empty-folder
 *   @e2e files-widget::empty-folder-state
 *   @e2e files-widget::folder-not-found
 *   @e2e files-widget::no-mime-filter-all-files-shown
 *   @e2e files-widget::mime-filter-by-type
 *   @e2e files-widget::mime-filter-by-specific-mime-type
 *   @e2e files-widget::list-mode-renders-rows
 *   @e2e files-widget::grid-mode-without-thumbnails
 *   @e2e files-widget::sort-order-applied-per-view-mode
 *
 * @spec openspec/specs/files-widget/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import {
	ADMIN,
	apiAs,
	crumbs,
	fileIdOf,
	filesWidget,
	mkdirs,
	nextContents,
	openOnWidget,
	purge,
	putFile,
	removePlacements,
	requestedPath,
	rowNames,
	seedFilesPlacement,
} from './support/filesWidget.ts'

const RUN = Date.now()
const ROOT = `E2E-FW-listing-${RUN}`
const PNG = fs.readFileSync(path.join(__dirname, 'fixtures', 'tiny.png'))
/** 64x64: Nextcloud makes no preview of a 1x1 image, so the thumbnail test needs a larger one. */
const PREVIEWABLE_PNG = fs.readFileSync(path.join(__dirname, 'fixtures', 'preview.png'))
const ISO_8601 = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
const placements: number[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await apiAs()
	dashboard = await seedActiveDashboard(admin, `E2E Files listing ${RUN}`)
	await mkdirs(admin, ADMIN.user, ROOT)
})

test.afterEach(async () => {
	await removePlacements(admin, placements)
})

test.afterAll(async () => {
	await purge(admin, ADMIN.user, ROOT)
	await removeSeededDashboard(admin, dashboard)
	await admin.dispose()
})

/**
 * Write a folder of files into the admin's home.
 *
 * @param folder the folder, below ROOT.
 * @param files name → content (a Buffer, a string, or [content, mtime]).
 * @return the folder's home-relative path, with a leading slash.
 */
async function seedFolder(
	folder: string,
	files: Record<string, string | Buffer | [string, number]>,
): Promise<string> {
	const full = `${ROOT}/${folder}`
	await mkdirs(admin, ADMIN.user, full)
	for (const [name, value] of Object.entries(files)) {
		if (name.endsWith('/')) {
			await mkdirs(admin, ADMIN.user, `${full}/${name}`)
		} else if (Array.isArray(value)) {
			await putFile(admin, ADMIN.user, `${full}/${name}`, value[0], value[1])
		} else {
			await putFile(admin, ADMIN.user, `${full}/${name}`, value)
		}
	}
	return `/${full}`
}

/**
 * Place a files widget and remember it for cleanup.
 *
 * @param content the widget content.
 * @param gridY the row.
 * @return the placement id.
 */
async function place(content: Record<string, unknown>, gridY = 12): Promise<number> {
	const id = await seedFilesPlacement(admin, dashboard!.id, content, gridY)
	placements.push(id)
	return id
}

// @e2e files-widget::fetch-folder-contents
test('the contents response carries every item field and follows the configured sort', async ({ page }) => {
	const folder = await seedFolder('fetch', {
		'a.txt': 'abc',
		'bb.txt': 'x'.repeat(30),
		'sub/': '',
	})
	await putFile(admin, ADMIN.user, `${folder}/sub/inner.txt`, 'y'.repeat(100))
	const id = await place({ folderPath: folder, sortBy: 'size', sortDescending: true })

	const response = await openOnWidget(page, id)
	expect(response.status()).toBe(200)
	const body = await response.json()
	expect(body).toHaveProperty('items')
	expect(body).toHaveProperty('nextCursor', null)
	expect(body.items.map((i: any) => i.name)).toEqual(['sub', 'bb.txt', 'a.txt'])

	for (const item of body.items) {
		expect(typeof item.fileId).toBe('number')
		expect(typeof item.name).toBe('string')
		expect(typeof item.path).toBe('string')
		expect(typeof item.mimeType).toBe('string')
		expect(typeof item.size).toBe('number')
		expect(item.modifiedAt).toMatch(ISO_8601)
		expect(typeof item.isFolder).toBe('boolean')
		expect(item.thumbnailUrl === null || typeof item.thumbnailUrl === 'string').toBe(true)
		expect(typeof item.canEdit).toBe('boolean')
		expect(typeof item.canDelete).toBe('boolean')
	}
	const sizes = body.items.map((i: any) => i.size)
	expect(sizes).toEqual([...sizes].sort((a: number, b: number) => b - a))
	expect(body.items.find((i: any) => i.name === 'sub').isFolder).toBe(true)

	// The rows render in the same order.
	expect(await rowNames(filesWidget(page, id))).toEqual(['sub', 'bb.txt', 'a.txt'])

	// path is relative to the widget root, not the storage-internal path.
	for (const item of body.items) {
		expect(item.path, JSON.stringify(item)).toMatch(new RegExp(`^/?${item.name.replace(/\./g, '\\.')}$`))
	}
})

// @e2e files-widget::pagination-with-cursor
test('more than one page is fetched with the opaque cursor until it is null', async ({ page }) => {
	const files: Record<string, string> = {}
	for (let i = 1; i <= 60; i++) {
		files[`f-${String(i).padStart(3, '0')}.txt`] = String(i)
	}
	const folder = await seedFolder('paged', files)
	const id = await place({ folderPath: folder })

	const first = await openOnWidget(page, id)
	const firstUrl = new URL(first.url())
	expect(firstUrl.searchParams.get('limit')).toBe('50')
	expect(firstUrl.searchParams.get('cursor') ?? '').toBe('')
	const firstBody = await first.json()
	expect(firstBody.items).toHaveLength(50)
	expect(typeof firstBody.nextCursor).toBe('string')
	expect(firstBody.nextCursor).not.toBe('')

	const widget = filesWidget(page, id)
	await expect(widget.locator('.cn-files-widget__row')).toHaveCount(50)

	const second = nextContents(page, id)
	await widget.getByRole('button', { name: 'Load more' }).click()
	const secondResponse = await second
	const secondUrl = new URL(secondResponse.url())
	expect(secondUrl.searchParams.get('cursor')).toBe(firstBody.nextCursor)
	expect(secondUrl.searchParams.get('limit')).toBe('50')
	const secondBody = await secondResponse.json()
	expect(secondBody.items).toHaveLength(10)
	expect(secondBody.nextCursor).toBeNull()

	await expect(widget.locator('.cn-files-widget__row')).toHaveCount(60)
	await expect(widget.getByRole('button', { name: 'Load more' })).toHaveCount(0)
	expect(await rowNames(widget)).toEqual(Object.keys(files))
})

// @e2e files-widget::mime-type-filter-applied
test('an image/* filter returns only the image', async ({ page }) => {
	const folder = await seedFolder('mime-applied', {
		'image.png': PNG,
		'document.pdf': '%PDF-1.4',
		'notes.txt': 'notes',
	})
	const id = await place({ folderPath: folder, mimeTypeFilter: ['image/*'] })

	const response = await openOnWidget(page, id)
	expect((await response.json()).items.map((i: any) => i.name)).toEqual(['image.png'])
	expect(await rowNames(filesWidget(page, id))).toEqual(['image.png'])
})

// @e2e files-widget::empty-folder
// @e2e files-widget::empty-folder-state
test('an empty folder answers an empty page and the widget says so, with the breadcrumb still working', async ({ page }) => {
	const folder = await seedFolder('empty', {})
	const id = await place({ folderPath: folder })

	const response = await openOnWidget(page, id)
	expect(response.status()).toBe(200)
	expect(await response.json()).toEqual({ items: [], nextCursor: null })

	const widget = filesWidget(page, id)
	await expect(widget.getByText('This folder is empty.')).toBeVisible()
	await expect(widget.locator('.cn-files-widget__row')).toHaveCount(0)

	// Navigation and the breadcrumb still work: the root crumb is there, can
	// be pressed, and refetches the folder.
	expect(await crumbs(widget)).toEqual(['Root'])
	const refetch = nextContents(page, id)
	await widget.getByRole('button', { name: 'Root' }).click()
	expect(requestedPath(await refetch)).toBe('/')
	await expect(widget.getByText('This folder is empty.')).toBeVisible()
	await expect(widget.getByRole('searchbox', { name: 'Search this folder' })).toBeEditable()
})

// @e2e files-widget::folder-not-found
test('a folder that no longer exists answers 404 folder_not_found', async ({ page }) => {
	const id = await place({ folderPath: `/${ROOT}/never-created-${RUN}` })

	const response = await openOnWidget(page, id)
	expect(response.status()).toBe(404)
	expect(await response.json()).toMatchObject({
		error: 'folder_not_found',
		message: 'The configured folder no longer exists',
	})
	await expect(filesWidget(page, id).getByText('Folder no longer exists.')).toBeVisible()
})

// @e2e files-widget::no-mime-filter-all-files-shown
test('with an empty or missing MIME filter every file and folder is returned', async ({ page }) => {
	const folder = await seedFolder('no-filter', {
		'image.png': PNG,
		'document.pdf': '%PDF-1.4',
		'notes.txt': 'notes',
		'archive.zip': 'PK',
		'nested/': '',
	})
	const expected = ['archive.zip', 'document.pdf', 'image.png', 'nested', 'notes.txt']
	const empty = await place({ folderPath: folder, mimeTypeFilter: [] }, 12)
	const missing = await place({ folderPath: folder }, 22)

	const pending = nextContents(page, missing)
	const emptyResponse = await openOnWidget(page, empty)
	const missingResponse = await pending
	for (const response of [emptyResponse, missingResponse]) {
		expect((await response.json()).items.map((i: any) => i.name).sort()).toEqual(expected)
	}
	for (const id of [empty, missing]) {
		await expect(filesWidget(page, id).locator('.cn-files-widget__row')).toHaveCount(expected.length)
		expect((await rowNames(filesWidget(page, id))).sort()).toEqual(expected)
	}
})

// @e2e files-widget::mime-filter-by-type
test('an image/* + video/* filter returns the photo and the video only', async ({ page }) => {
	const folder = await seedFolder('by-type', {
		'photo.jpg': 'jpeg',
		'video.mp4': 'mp4',
		'document.pdf': '%PDF-1.4',
		'spreadsheet.xlsx': 'xlsx',
	})
	const id = await place({ folderPath: folder, mimeTypeFilter: ['image/*', 'video/*'] })

	const response = await openOnWidget(page, id)
	expect((await response.json()).items.map((i: any) => i.name)).toEqual(['photo.jpg', 'video.mp4'])
	expect(await rowNames(filesWidget(page, id))).toEqual(['photo.jpg', 'video.mp4'])
})

// @e2e files-widget::mime-filter-by-specific-mime-type
test('an application/pdf filter returns both PDFs and not the docx', async ({ page }) => {
	const folder = await seedFolder('by-mime', {
		'budget.pdf': '%PDF-1.4',
		'report.docx': 'docx',
		'notes.pdf': '%PDF-1.4',
	})
	const id = await place({ folderPath: folder, mimeTypeFilter: ['application/pdf'] })

	const response = await openOnWidget(page, id)
	expect((await response.json()).items.map((i: any) => i.name)).toEqual(['budget.pdf', 'notes.pdf'])
	expect(await rowNames(filesWidget(page, id))).toEqual(['budget.pdf', 'notes.pdf'])
})

// @e2e files-widget::list-mode-renders-rows
test('list mode draws a row per item with icon or thumbnail, name, date and size, and rows act on click', async ({ page }) => {
	const folder = await seedFolder('list-mode', {
		'picture.png': PREVIEWABLE_PNG,
		'notes.txt': 'hello',
		'sub/': '',
	})
	await putFile(admin, ADMIN.user, `${folder}/sub/deeper.txt`, 'deeper')
	const id = await place({ folderPath: folder, viewMode: 'list', showThumbnails: true })

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	await expect(widget.locator('.cn-files-widget__list')).not.toHaveClass(/cn-files-widget__list--grid/)
	const rows = widget.locator('.cn-files-widget__row')
	await expect(rows).toHaveCount(3)
	for (const name of ['notes.txt', 'picture.png', 'sub']) {
		const row = rows.filter({ has: page.locator('.cn-files-widget__row-label', { hasText: name }) })
		await expect(row.locator('.cn-files-widget__row-icon')).toBeVisible()
		await expect(row.locator('.cn-files-widget__row-modified')).toHaveText(/\d{4}-\d{2}-\d{2}/)
		await expect(row.locator('.cn-files-widget__row-size')).toBeAttached()
	}
	const notes = rows.filter({ hasText: 'notes.txt' })
	await expect(notes.locator('.cn-files-widget__row-size')).toHaveText('5 B')
	await expect(rows.filter({ hasText: 'picture.png' }).locator('img.cn-files-widget__row-thumb')).toBeVisible()

	// A file row opens the Files app in a new tab…
	const popup = page.waitForEvent('popup')
	await notes.locator('.cn-files-widget__row-name').click()
	const opened = await popup
	await opened.waitForLoadState()
	expect(opened.url()).toMatch(/\/apps\/files\//)
	await opened.close()

	// …and a folder row navigates inside the widget.
	const descend = nextContents(page, id)
	await rows.filter({ hasText: 'sub' }).locator('.cn-files-widget__row-name').click()
	expect(requestedPath(await descend)).toBe('/sub')
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['deeper.txt'])
})

// @e2e files-widget::grid-mode-without-thumbnails
test('grid mode without thumbnails draws tiles with a file-type icon and requests no previews', async ({ page }) => {
	const folder = await seedFolder('grid-plain', {
		'picture.png': PNG,
		'document.pdf': '%PDF-1.4',
		'clip.mp4': 'mp4',
	})
	const id = await place({ folderPath: folder, viewMode: 'grid', showThumbnails: false })

	const previews: string[] = []
	page.on('request', (r) => {
		if (r.url().includes('/core/preview')) {
			previews.push(r.url())
		}
	})
	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	await expect(widget.locator('.cn-files-widget__list')).toHaveClass(/cn-files-widget__list--grid/)
	const tiles = widget.locator('.cn-files-widget__row')
	await expect(tiles).toHaveCount(3)
	await expect(widget.locator('img')).toHaveCount(0)
	for (const icon of await widget.locator('.cn-files-widget__row-icon').allTextContents()) {
		expect(icon.trim()).toBe('📄')
	}
	// The picture's preview would come from /core/preview: nothing asked for one.
	// Other widgets on the page may load previews of their own files, so only
	// this folder's files count.
	const ids = await Promise.all(['picture.png', 'document.pdf', 'clip.mp4']
		.map((name) => fileIdOf(admin, ADMIN.user, `${folder}/${name}`)))
	const ours = previews.filter((u) => ids.includes(Number(new URL(u).searchParams.get('fileId'))))
	expect(ours).toEqual([])
})

// @e2e files-widget::sort-order-applied-per-view-mode
test('newest-first sorting is the same in list, grid and tree mode', async ({ page }) => {
	const folder = await seedFolder('sort-modes', {
		'old.txt': ['old', 1_577_880_000],
		'new.txt': ['new', 1_704_067_200],
		'mid.txt': ['mid', 1_640_995_200],
	})
	const config = { folderPath: folder, sortBy: 'modified', sortDescending: true }
	const list = await place({ ...config, viewMode: 'list' }, 12)
	const grid = await place({ ...config, viewMode: 'grid' }, 22)
	const tree = await place({ ...config, viewMode: 'tree' }, 32)

	await openOnWidget(page, list)
	for (const id of [list, grid, tree]) {
		await expect(filesWidget(page, id).locator('.cn-files-widget__row-label')).toHaveText(['new.txt', 'mid.txt', 'old.txt'], { timeout: 20_000 })
	}
})
