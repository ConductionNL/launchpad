/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for moving around inside the files widget:
 * the breadcrumb, descending into folders, opening files in the Files app,
 * several widgets on one dashboard, and the in-widget search
 * (openspec/specs/files-widget, REQ-FLS-001, -005, -006 and -011).
 * Written for e2e-coverage-programme.
 *
 * Every navigation is a click on the widget's own rows and crumbs; the
 * `currentPath` of the request the widget then makes is read off the wire,
 * so "refetches /Documents/Marketing" is observed rather than inferred.
 *
 * Gate-19 @e2e traceability:
 *   @e2e files-widget::multiple-instances-allowed
 *   @e2e files-widget::navigation-state-isolated-per-widget-instance
 *   @e2e files-widget::breadcrumb-displays-current-path
 *   @e2e files-widget::click-breadcrumb-to-jump-up
 *   @e2e files-widget::click-folder-to-descend
 *   @e2e files-widget::root-folder-breadcrumb
 *   @e2e files-widget::click-file-to-open-in-files-app
 *   @e2e files-widget::click-folder-does-not-deep-link
 *   @e2e files-widget::empty-search-results
 *   @e2e files-widget::in-widget-search-is-client-side-substring-filter
 *   @e2e files-widget::search-preserves-sort-order
 *   @e2e files-widget::search-filters-both-files-and-folders
 *
 * @spec openspec/specs/files-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
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
	placementsOf,
	purge,
	putFile,
	removePlacements,
	requestedPath,
	rowNames,
	seedFilesPlacement,
} from './support/filesWidget.ts'

const RUN = Date.now()
const ROOT = `E2E-FW-nav-${RUN}`

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
const placements: number[] = []

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await apiAs()
	dashboard = await seedActiveDashboard(admin, `E2E Files navigation ${RUN}`)
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
 * Write files (and the folders they sit in) below ROOT.
 *
 * @param paths ROOT-relative paths; a trailing slash makes a folder.
 * @param mtimes optional mtimes by path.
 * @return nothing.
 */
async function seed(paths: string[], mtimes: Record<string, number> = {}): Promise<void> {
	for (const p of paths) {
		if (p.endsWith('/')) {
			await mkdirs(admin, ADMIN.user, `${ROOT}/${p}`)
			continue
		}
		const dir = p.split('/').slice(0, -1).join('/')
		await mkdirs(admin, ADMIN.user, `${ROOT}/${dir}`)
		await putFile(admin, ADMIN.user, `${ROOT}/${p}`, p, mtimes[p])
	}
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

/**
 * Click a folder row and wait for the contents request it causes.
 *
 * @param page the page.
 * @param id the placement.
 * @param name the folder's name.
 * @return the `currentPath` the widget asked for.
 */
async function openFolder(page: Page, id: number, name: string): Promise<string | null> {
	const request = nextContents(page, id)
	await rowButton(filesWidget(page, id), name).click()
	const path = requestedPath(await request)
	await expect(filesWidget(page, id).getByText('Loading folder…')).toHaveCount(0)
	return path
}

/**
 * The clickable name of a row.
 *
 * @param widget the widget.
 * @param name the item name.
 * @return the row's name control.
 */
function rowButton(widget: Locator, name: string): Locator {
	return widget
		.locator('.cn-files-widget__row')
		.filter({ has: widget.page().locator('.cn-files-widget__row-label', { hasText: new RegExp(`^${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`) }) })
		.locator('.cn-files-widget__row-name')
}

// @e2e files-widget::multiple-instances-allowed
// @e2e files-widget::navigation-state-isolated-per-widget-instance
test('two files widgets on one dashboard keep their own configuration and their own path', async ({ page }) => {
	await seed(['multi-a/a-root.txt', 'multi-a/subfolder/a-inner.txt', 'multi-b/b-root.txt', 'multi-b/bsub/b-inner.txt'])
	const a = await place({ folderPath: `/${ROOT}/multi-a`, viewMode: 'list' }, 12)
	const b = await place({ folderPath: `/${ROOT}/multi-b`, viewMode: 'grid', sortDescending: true }, 22)

	// Both coexist, each with the configuration it was given.
	const stored = await placementsOf(admin, dashboard!.id)
	expect(stored.find((p) => Number(p.id) === a)?.content).toMatchObject({ folderPath: `/${ROOT}/multi-a`, viewMode: 'list' })
	expect(stored.find((p) => Number(p.id) === b)?.content).toMatchObject({ folderPath: `/${ROOT}/multi-b`, viewMode: 'grid', sortDescending: true })

	const bFirst = nextContents(page, b)
	await openOnWidget(page, a)
	await bFirst
	const widgetA = filesWidget(page, a)
	const widgetB = filesWidget(page, b)
	await expect(widgetB.locator('.cn-files-widget__row')).toHaveCount(2)
	expect(await rowNames(widgetA)).toEqual(['a-root.txt', 'subfolder'])
	expect(await rowNames(widgetB)).toEqual(['bsub', 'b-root.txt'])
	await expect(widgetB.locator('.cn-files-widget__list')).toHaveClass(/--grid/)

	let bRequests = 0
	page.on('request', (r) => {
		if (r.url().includes(`/api/widgets/files/${b}/contents`)) {
			bRequests++
		}
	})
	expect(await openFolder(page, a, 'subfolder')).toBe('/subfolder')
	await expect(widgetA.locator('.cn-files-widget__row-label')).toHaveText(['a-inner.txt'])
	expect(await crumbs(widgetA)).toEqual(['Root', 'subfolder'])

	// Widget B did not move and did not even refetch.
	expect(await crumbs(widgetB)).toEqual(['Root'])
	await expect(widgetB.locator('.cn-files-widget__crumb--root')).toHaveAttribute('aria-current', 'page')
	expect(await rowNames(widgetB)).toEqual(['bsub', 'b-root.txt'])
	expect(bRequests).toBe(0)

	// And B navigates on its own without disturbing A.
	expect(await openFolder(page, b, 'bsub')).toBe('/bsub')
	expect(await crumbs(widgetB)).toEqual(['Root', 'bsub'])
	expect(await crumbs(widgetA)).toEqual(['Root', 'subfolder'])
})

// @e2e files-widget::breadcrumb-displays-current-path
test('after descending to Documents/Marketing/Q2 the breadcrumb reads Root / Documents / Marketing / Q2', async ({ page }) => {
	await seed(['crumbs/Documents/Marketing/Q2/q2.txt', 'crumbs/Documents/Marketing/m.txt'])
	const id = await place({ folderPath: `/${ROOT}/crumbs` })

	await openOnWidget(page, id)
	expect(await openFolder(page, id, 'Documents')).toBe('/Documents')
	expect(await openFolder(page, id, 'Marketing')).toBe('/Documents/Marketing')
	expect(await openFolder(page, id, 'Q2')).toBe('/Documents/Marketing/Q2')

	const widget = filesWidget(page, id)
	expect(await crumbs(widget)).toEqual(['Root', 'Documents', 'Marketing', 'Q2'])
	await expect(widget.locator('.cn-files-widget__separator')).toHaveText(['/', '/', '/'])
	const segments = widget.locator('.cn-files-widget__breadcrumb').getByRole('button')
	await expect(segments).toHaveCount(4)
	for (const segment of await segments.all()) {
		await expect(segment).toBeEnabled()
	}
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['q2.txt'])
})

// @e2e files-widget::click-breadcrumb-to-jump-up
test('clicking Marketing in the breadcrumb jumps up to /Documents/Marketing', async ({ page }) => {
	await seed(['jump/Documents/Marketing/Q2/q2.txt', 'jump/Documents/Marketing/m.txt'])
	const id = await place({ folderPath: `/${ROOT}/jump` })

	await openOnWidget(page, id)
	await openFolder(page, id, 'Documents')
	await openFolder(page, id, 'Marketing')
	await openFolder(page, id, 'Q2')

	const widget = filesWidget(page, id)
	const up = nextContents(page, id)
	await widget.locator('.cn-files-widget__breadcrumb').getByRole('button', { name: 'Marketing', exact: true }).click()
	expect(requestedPath(await up)).toBe('/Documents/Marketing')
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['m.txt', 'Q2'])
	expect(await crumbs(widget)).toEqual(['Root', 'Documents', 'Marketing'])
})

// @e2e files-widget::click-folder-to-descend
test('clicking the Marketing folder descends and reapplies the sort and the MIME filter', async ({ page }) => {
	await seed([
		'descend/Documents/top.pdf',
		'descend/Documents/Marketing/a.pdf',
		'descend/Documents/Marketing/b.pdf',
		'descend/Documents/Marketing/c.txt',
	])
	const id = await place({
		folderPath: `/${ROOT}/descend`,
		mimeTypeFilter: ['application/pdf'],
		sortBy: 'name',
		sortDescending: true,
	})

	await openOnWidget(page, id)
	expect(await openFolder(page, id, 'Documents')).toBe('/Documents')
	const widget = filesWidget(page, id)
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['top.pdf', 'Marketing'])

	expect(await openFolder(page, id, 'Marketing')).toBe('/Documents/Marketing')
	expect(await crumbs(widget)).toEqual(['Root', 'Documents', 'Marketing'])
	// Descending by name, PDFs only: c.txt is filtered out in the new folder too.
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['b.pdf', 'a.pdf'])
})

// @e2e files-widget::root-folder-breadcrumb
test('at the configured root the breadcrumb shows Root, and Root never leaves it', async ({ page }) => {
	await seed(['rootcrumb/top.txt', 'rootcrumb/inner/deep.txt'])
	const id = await place({ folderPath: `/${ROOT}/rootcrumb` })

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	expect(await crumbs(widget)).toEqual(['Root'])
	const root = widget.locator('.cn-files-widget__crumb--root')
	await expect(root).toHaveAttribute('aria-current', 'page')

	// Pressed at the root: still the configured root.
	const same = nextContents(page, id)
	await root.click()
	expect(requestedPath(await same)).toBe('/')
	expect(await crumbs(widget)).toEqual(['Root'])
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['inner', 'top.txt'])

	// Pressed below the root: back to the configured root.
	await openFolder(page, id, 'inner')
	expect(await crumbs(widget)).toEqual(['Root', 'inner'])
	const reset = nextContents(page, id)
	await root.click()
	expect(requestedPath(await reset)).toBe('/')
	expect(await crumbs(widget)).toEqual(['Root'])
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['inner', 'top.txt'])
})

// @e2e files-widget::click-file-to-open-in-files-app
test('clicking a file opens it in the Files app in a new tab, by its file id', async ({ page }) => {
	await seed(['deeplink/Marketing/budget.xlsx'])
	const fileId = await fileIdOf(admin, ADMIN.user, `${ROOT}/deeplink/Marketing/budget.xlsx`)
	const id = await place({ folderPath: `/${ROOT}/deeplink` })

	await openOnWidget(page, id)
	await openFolder(page, id, 'Marketing')

	const popup = page.waitForEvent('popup')
	await rowButton(filesWidget(page, id), 'budget.xlsx').click()
	const files = await popup
	await files.waitForLoadState('domcontentloaded')
	await files.waitForURL(/\/apps\/files\//, { timeout: 30_000 })

	// `/apps/files/?fileid=<id>` or the Files app's own canonical
	// `/apps/files/files/<id>` form of the same address.
	const url = new URL(files.url())
	const byQuery = url.searchParams.get('fileid') === String(fileId)
	const byPath = url.pathname.replace(/\/+$/, '').endsWith(`/apps/files/files/${fileId}`)
	expect(byQuery || byPath, url.toString()).toBe(true)

	// The Files app resolves the id to the file's own folder and opens it in
	// its standard way. Which viewer then covers the list (an office suite for
	// an .xlsx, when one is installed) is the Files app's business, so the
	// folder it resolved to is read from its address, not from a list row.
	await expect.poll(
		() => new URL(files.url()).searchParams.get('dir'),
		{ timeout: 30_000 },
	).toBe(`/${ROOT}/deeplink/Marketing`)
	await files.close()

	// The widget page itself did not navigate away.
	expect(page.url()).toMatch(/\/apps\/launchpad/)
})

// @e2e files-widget::click-folder-does-not-deep-link
test('clicking a folder browses inside the widget and opens no tab', async ({ page }) => {
	await seed(['nolink/Campaigns/flyer.txt', 'nolink/readme.txt'])
	const folderId = await fileIdOf(admin, ADMIN.user, `${ROOT}/nolink/Campaigns`)
	const id = await place({ folderPath: `/${ROOT}/nolink` })

	await openOnWidget(page, id)
	const opened: string[] = []
	page.context().on('page', (p) => opened.push(p.url()))

	const widget = filesWidget(page, id)
	await expect(rowButton(widget, 'Campaigns')).not.toHaveAttribute('href', /.+/)
	expect(await openFolder(page, id, 'Campaigns')).toBe('/Campaigns')
	expect(await crumbs(widget)).toEqual(['Root', 'Campaigns'])
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['flyer.txt'])

	await page.waitForTimeout(1_500)
	expect(opened, `a tab opened for folder ${folderId}`).toEqual([])
	expect(page.url()).toMatch(/\/apps\/launchpad/)
})

// @e2e files-widget::empty-search-results
test('a search with no match says so, and clearing it brings the listing back', async ({ page }) => {
	await seed(['search-empty/budget.pdf', 'search-empty/report.txt', 'search-empty/notes.md'])
	const id = await place({ folderPath: `/${ROOT}/search-empty` })

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	const search = widget.getByRole('searchbox', { name: 'Search this folder' })
	await search.fill('invoice')
	await expect(widget.locator('.cn-files-widget__row')).toHaveCount(0)
	await expect(widget.getByText('No files matching \'invoice\'')).toBeVisible()

	await search.fill('')
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['budget.pdf', 'notes.md', 'report.txt'])
})

// @e2e files-widget::in-widget-search-is-client-side-substring-filter
test('searching for budget keeps both budget files, in any letter case, without asking the server', async ({ page }) => {
	await seed(['search-sub/marketing_budget.xlsx', 'search-sub/budget_2024.xlsx', 'search-sub/Q2_report.pdf'])
	const id = await place({ folderPath: `/${ROOT}/search-sub` })

	await openOnWidget(page, id)
	let requests = 0
	page.on('request', (r) => {
		if (r.url().includes(`/api/widgets/files/${id}/contents`)) {
			requests++
		}
	})

	const widget = filesWidget(page, id)
	const search = widget.getByRole('searchbox', { name: 'Search this folder' })
	for (const query of ['budget', 'BUDGET', 'Budget']) {
		await search.fill(query)
		await expect(widget.locator('.cn-files-widget__row-label'), query).toHaveText(['budget_2024.xlsx', 'marketing_budget.xlsx'])
		await expect(rowButton(widget, 'Q2_report.pdf')).toHaveCount(0)
	}
	expect(requests).toBe(0)
})

// @e2e files-widget::search-preserves-sort-order
test('a search keeps the newest-first order of what it matches', async ({ page }) => {
	const mtimes = {
		'search-sort/a-budget.txt': 1_609_459_200,
		'search-sort/d-budget.txt': 1_640_995_200,
		'search-sort/b-budget.txt': 1_672_531_200,
		'search-sort/c-other.txt': 1_704_067_200,
	}
	await seed(Object.keys(mtimes), mtimes)
	const id = await place({ folderPath: `/${ROOT}/search-sort`, sortBy: 'modified', sortDescending: true })

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['c-other.txt', 'b-budget.txt', 'd-budget.txt', 'a-budget.txt'])
	await widget.getByRole('searchbox', { name: 'Search this folder' }).fill('budget')
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['b-budget.txt', 'd-budget.txt', 'a-budget.txt'])
})

// @e2e files-widget::search-filters-both-files-and-folders
test('a search matches folders as well as files', async ({ page }) => {
	await seed(['search-both/marketing/inside.txt', 'search-both/marketing_plan.pdf', 'search-both/other.txt'])
	const id = await place({ folderPath: `/${ROOT}/search-both` })

	await openOnWidget(page, id)
	const widget = filesWidget(page, id)
	await widget.getByRole('searchbox', { name: 'Search this folder' }).fill('market')
	await expect(widget.locator('.cn-files-widget__row-label')).toHaveText(['marketing', 'marketing_plan.pdf'])
	await expect(widget.locator('.cn-files-widget__row--folder .cn-files-widget__row-label')).toHaveText(['marketing'])
})
