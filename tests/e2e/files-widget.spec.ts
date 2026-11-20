/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the files widget
 * (openspec/specs/files-widget): how it is found in the picker and how a
 * person configures it. Written for e2e-coverage-programme.
 *
 * The files type is offered by the Add Widget dialog (More → "Add custom
 * widget…"). Each configuration scenario is driven through that dialog's own
 * controls and then read back from `GET /api/dashboard/{id}`, the way the
 * placement is fetched when the dashboard loads. Where a scenario names a
 * default, the default is checked twice: what the form stores when the field
 * is left alone, and what the widget renders for a placement whose content
 * does not carry the field at all.
 *
 * The scenarios on listing, navigation, search, upload, delete and access
 * live in files-widget-listing, files-widget-navigation, files-widget-actions
 * and files-widget-access.
 *
 * Gate-19 @e2e traceability:
 *   @e2e files-widget::widget-appears-in-picker
 *   @e2e files-widget::widget-registration-survives-app-reload
 *   @e2e files-widget::config-for-folder-path
 *   @e2e files-widget::config-for-view-mode
 *   @e2e files-widget::config-for-thumbnails
 *   @e2e files-widget::config-for-mime-type-filter
 *   @e2e files-widget::config-for-sorting
 *   @e2e files-widget::config-is-json-serializable
 *
 * @spec openspec/specs/files-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import {
	addAndReadBack,
	ADMIN,
	apiAs,
	chooseSelectOption,
	fileIdOf,
	filesWidget,
	mkdirs,
	openAddFilesWidget,
	openAddWidgetDialog,
	openOnWidget,
	placementsOf,
	purge,
	putFile,
	removePlacements,
	rowNames,
	seedFilesPlacement,
} from './support/filesWidget.ts'

const RUN = Date.now()
const ROOT = `E2E-FW-config-${RUN}`
const PNG = fs.readFileSync(path.join(__dirname, 'fixtures', 'tiny.png'))

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
const placements: number[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	admin = await apiAs()
	dashboard = await seedActiveDashboard(admin, `E2E Files config ${RUN}`)
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
 * Add a files widget through the dialog and remember it for cleanup.
 *
 * @param page the page.
 * @param configure what to do in the dialog before pressing Add.
 * @return the stored placement.
 */
async function addThroughDialog(
	page: Page,
	configure: (dialog: Locator) => Promise<void> = async () => {},
): Promise<Record<string, any>> {
	const dialog = await openAddFilesWidget(page)
	await configure(dialog)
	const placement = await addAndReadBack(page, dialog, admin, dashboard!.id)
	placements.push(Number(placement.id))
	return placement
}

// @e2e files-widget::widget-appears-in-picker
test('the picker offers a Files widget by its title, and it can be placed', async ({ page }) => {
	const dialog = await openAddWidgetDialog(page)
	const option = dialog.getByTestId('widget-type-option-files')
	await expect(option).toHaveCount(1)
	await expect(option).toHaveText('Files')

	await dialog.getByTestId('widget-type-select').selectOption('files')
	await expect(dialog.getByText('Folder', { exact: true })).toBeVisible()
	const placement = await addAndReadBack(page, dialog, admin, dashboard!.id)
	placements.push(Number(placement.id))
	expect(placement.widgetId).toBe('files')
	await expect(filesWidget(page, Number(placement.id))).toBeVisible({ timeout: 15_000 })
})

// @e2e files-widget::widget-registration-survives-app-reload
test('the Files widget is still offered after a reload and in a context with a cold cache', async ({ browser, page }) => {
	let dialog = await openAddWidgetDialog(page)
	await expect(dialog.getByTestId('widget-type-option-files')).toHaveText('Files')

	await page.reload()
	dialog = await openAddWidgetDialog(page)
	await expect(dialog.getByTestId('widget-type-option-files')).toHaveText('Files')

	// A new browser context has an empty HTTP cache: the bundle and the
	// registry are loaded from scratch.
	const cold = await browser.newContext()
	try {
		const coldPage = await cold.newPage()
		const coldDialog = await openAddWidgetDialog(coldPage)
		await expect(coldDialog.getByTestId('widget-type-option-files')).toHaveText('Files')
		await coldDialog.getByTestId('widget-type-select').selectOption('files')
		await expect(coldDialog.getByRole('button', { name: /^browse/i })).toBeVisible()
	} finally {
		await cold.close()
	}
})

// @e2e files-widget::config-for-folder-path
test('picking a folder in the dialog stores it by its persistent file id', async ({ page }) => {
	const picked = `${ROOT}/Picked`
	await mkdirs(admin, ADMIN.user, picked)
	await putFile(admin, ADMIN.user, `${picked}/inside-picked.txt`, 'picked')
	const pickedId = await fileIdOf(admin, ADMIN.user, picked)

	const placement = await addThroughDialog(page, async (dialog) => {
		await dialog.getByRole('button', { name: /^browse/i }).click()
		const picker = page.getByRole('dialog', { name: /choose a folder/i })
		await expect(picker).toBeVisible({ timeout: 15_000 })
		await picker.locator(`[data-testid="file-list-row"][data-filename="${ROOT}"]`).click()
		await picker.locator('[data-testid="file-list-row"][data-filename="Picked"]').click()
		await picker.getByRole('button', { name: /^choose/i }).click()
		await expect(picker).toBeHidden({ timeout: 10_000 })
		await expect(dialog.locator('.cn-files-widget-form__folder-path')).toHaveText(`/${picked}`)
	})

	// fileId is the preferred reference, and it survives renames.
	expect(placement.content.fileId).toBe(pickedId)

	// The configured folder is what the widget shows.
	await openOnWidget(page, Number(placement.id))
	expect(await rowNames(filesWidget(page, Number(placement.id)))).toEqual(['inside-picked.txt'])

	// At least one of folderPath / fileId (the spec's data model). The form
	// keeps the path beside the id to show it; when both are stored they name
	// the same folder, and fileId is the one the widget resolves.
	const content = placement.content
	expect(content.fileId ?? content.folderPath, JSON.stringify(content)).toBeTruthy()
	if (content.folderPath) {
		expect(content.folderPath, 'the path names the picked folder').toBe(`/${picked}`)
	}
})

// @e2e files-widget::config-for-view-mode
test('the view mode is stored as list, grid or tree and defaults to list', async ({ page }) => {
	await mkdirs(admin, ADMIN.user, `${ROOT}/views`)
	await putFile(admin, ADMIN.user, `${ROOT}/views/a.txt`, 'a')

	const untouched = await addThroughDialog(page)
	expect(untouched.content.viewMode).toBe('list')

	for (const [label, value] of [['Grid', 'grid'], ['Tree', 'tree'], ['List', 'list']]) {
		const stored = await addThroughDialog(page, async (dialog) => {
			await chooseSelectOption(page, dialog, 'View mode', label)
		})
		expect(stored.content.viewMode, label).toBe(value)
	}

	// A placement whose content carries no viewMode renders as a list.
	const bare = await seedFilesPlacement(admin, dashboard!.id, { folderPath: `/${ROOT}/views` })
	placements.push(bare)
	await openOnWidget(page, bare)
	const list = filesWidget(page, bare).locator('.cn-files-widget__list')
	await expect(list).toBeVisible()
	await expect(list).not.toHaveClass(/cn-files-widget__list--grid/)
})

// @e2e files-widget::config-for-thumbnails
test('thumbnails default to on, can be switched off, and are not drawn in tree mode', async ({ page }) => {
	const folder = `${ROOT}/thumbs`
	await mkdirs(admin, ADMIN.user, folder)
	await putFile(admin, ADMIN.user, `${folder}/picture.png`, PNG)

	const untouched = await addThroughDialog(page)
	expect(untouched.content.showThumbnails).toBe(true)
	const off = await addThroughDialog(page, async (dialog) => {
		await dialog.getByLabel('Show thumbnails').uncheck()
	})
	expect(off.content.showThumbnails).toBe(false)

	const thumb = (id: number) => filesWidget(page, id).locator('img.cn-files-widget__row-thumb')
	const byDefault = await seedFilesPlacement(admin, dashboard!.id, { folderPath: `/${folder}` }, 12)
	const switchedOff = await seedFilesPlacement(admin, dashboard!.id, { folderPath: `/${folder}`, showThumbnails: false }, 20)
	const grid = await seedFilesPlacement(admin, dashboard!.id, { folderPath: `/${folder}`, viewMode: 'grid', showThumbnails: true }, 28)
	const tree = await seedFilesPlacement(admin, dashboard!.id, { folderPath: `/${folder}`, viewMode: 'tree', showThumbnails: true }, 36)
	placements.push(byDefault, switchedOff, grid, tree)

	await openOnWidget(page, byDefault)
	for (const id of [switchedOff, grid, tree]) {
		await expect(filesWidget(page, id).locator('.cn-files-widget__row')).toHaveCount(1, { timeout: 20_000 })
	}
	await expect(thumb(byDefault)).toHaveAttribute('src', /preview/)
	await expect(thumb(grid)).toHaveAttribute('src', /preview/)
	await expect(thumb(switchedOff)).toHaveCount(0)
	// Tree mode ignores showThumbnails.
	await expect(thumb(tree)).toHaveCount(0)
})

// @e2e files-widget::config-for-mime-type-filter
test('the MIME filter is stored as an array, and empty or absent filters nothing', async ({ page }) => {
	const folder = `${ROOT}/mime`
	await mkdirs(admin, ADMIN.user, folder)
	await putFile(admin, ADMIN.user, `${folder}/image.png`, PNG)
	await putFile(admin, ADMIN.user, `${folder}/document.pdf`, '%PDF-1.4')
	await putFile(admin, ADMIN.user, `${folder}/notes.txt`, 'notes')

	const untouched = await addThroughDialog(page)
	expect(untouched.content.mimeTypeFilter).toEqual([])

	const filtered = await addThroughDialog(page, async (dialog) => {
		await dialog.getByLabel('MIME type filter (comma separated)').fill('image/*, application/pdf, text/plain')
	})
	expect(filtered.content.mimeTypeFilter).toEqual(['image/*', 'application/pdf', 'text/plain'])

	const empty = await seedFilesPlacement(admin, dashboard!.id, { folderPath: `/${folder}`, mimeTypeFilter: [] }, 12)
	const absent = await seedFilesPlacement(admin, dashboard!.id, { folderPath: `/${folder}` }, 20)
	placements.push(empty, absent)
	await openOnWidget(page, empty)
	await expect(filesWidget(page, absent).locator('.cn-files-widget__row')).toHaveCount(3, { timeout: 20_000 })
	for (const id of [empty, absent]) {
		expect((await rowNames(filesWidget(page, id))).sort()).toEqual(['document.pdf', 'image.png', 'notes.txt'])
	}
})

// @e2e files-widget::config-for-sorting
test('the sort field and direction are stored, defaulting to name ascending', async ({ page }) => {
	const untouched = await addThroughDialog(page)
	expect(untouched.content.sortBy).toBe('name')
	expect(untouched.content.sortDescending).toBe(false)

	for (const [label, value] of [['Modified', 'modified'], ['Size', 'size'], ['Type', 'type']]) {
		const stored = await addThroughDialog(page, async (dialog) => {
			await chooseSelectOption(page, dialog, 'Sort by', label)
			await dialog.getByLabel('Sort descending').check()
		})
		expect(stored.content.sortBy, label).toBe(value)
		expect(stored.content.sortDescending, label).toBe(true)
	}
})

// @e2e files-widget::config-is-json-serializable
test('a stored configuration comes back as a clean object, whitespace and non-ASCII included', async ({ page }) => {
	const unicode = `${ROOT}/Ünïcødé – map`
	await mkdirs(admin, ADMIN.user, unicode)
	await putFile(admin, ADMIN.user, `${unicode}/café.txt`, 'koffie')

	const typed = await addThroughDialog(page, async (dialog) => {
		await dialog.getByLabel('MIME type filter (comma separated)').fill('   image/* ,   text/plain   ,')
	})
	const seeded = await seedFilesPlacement(admin, dashboard!.id, {
		folderPath: `/${unicode}`,
		viewMode: 'list',
		showThumbnails: false,
		mimeTypeFilter: ['text/plain'],
		allowUpload: false,
		allowDelete: false,
		sortBy: 'name',
		sortDescending: false,
	})
	placements.push(seeded)

	const stored = await placementsOf(admin, dashboard!.id)
	const fromDialog = stored.find((p) => Number(p.id) === Number(typed.id))!
	const fromApi = stored.find((p) => Number(p.id) === seeded)!

	for (const content of [fromDialog.content, fromApi.content]) {
		expect(typeof content).toBe('object')
		expect(Array.isArray(content)).toBe(false)
		expect(Array.isArray(content.mimeTypeFilter)).toBe(true)
		for (const value of Object.values(content)) {
			if (typeof value === 'string') {
				expect(value).toBe(value.trim())
			}
		}
		for (const entry of content.mimeTypeFilter) {
			expect(entry).toBe(entry.trim())
		}
	}
	expect(fromDialog.content.mimeTypeFilter).toEqual(['image/*', 'text/plain'])
	expect(fromApi.content).toEqual({
		folderPath: `/${unicode}`,
		viewMode: 'list',
		showThumbnails: false,
		mimeTypeFilter: ['text/plain'],
		allowUpload: false,
		allowDelete: false,
		sortBy: 'name',
		sortDescending: false,
	})

	// The non-ASCII path resolves to the real folder on the server.
	await openOnWidget(page, seeded)
	expect(await rowNames(filesWidget(page, seeded))).toEqual(['café.txt'])
})

// @e2e files-widget::widget-appears-in-picker
// Last in this serial file: the picker's native <select> draws no icon yet
// (CnAddWidgetModal in nextcloud-vue), and that gap must not skip the rest.
test('the Files entry in the picker shows an icon', async ({ page }) => {
	const dialog = await openAddWidgetDialog(page)
	await expect(dialog.getByTestId('widget-type-option-files')).toHaveText('Files')
	// The registry entry declares `Folder`; the picker has to draw it.
	await expect(
		dialog.locator('.cn-add-widget-modal__type').locator('svg, img, [class*="icon"]').first(),
	).toBeVisible()
})
