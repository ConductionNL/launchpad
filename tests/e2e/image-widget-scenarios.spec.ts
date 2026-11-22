/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the image widget
 * (openspec/specs/image-widget), one scenario at a time, written for the
 * e2e-coverage-programme change.
 *
 * `image-widget.spec.ts` already drives the Add Widget form (upload, typed
 * URL, fit options, upload error, empty-URL validation) and the click-through
 * link. This file covers what that one does not: how a PLACED cell renders,
 * and what happens to a file-backed cell when its Nextcloud file goes away.
 *
 * HOW THE CELLS ARE MADE
 * ======================
 * Every test seeds its own dashboard through the API, with exactly the
 * `content` its scenario names (`POST /api/dashboard/{id}/widgets`, the same
 * payload the Add Widget modal sends), opens LaunchPad and reads the rendered
 * DOM. A seeded cell renders through the same WidgetRenderer -> CnImageWidget
 * path as one made by hand, and a seed can say things the form cannot (a
 * `null` url, no `fit` at all) which several scenarios start from.
 *
 * Image bytes are generated in memory (support/pngFixture.ts). Where a
 * scenario names an app resource path or an external URL, `page.route`
 * answers it, so nothing depends on outbound internet and nothing is left in
 * the resource store. Where a scenario is about a Nextcloud FILE, the file is
 * real: uploaded over WebDAV, referenced by its real `fileId`, and deleted,
 * restored or unshared for real. The image picker that would set such a
 * reference is not built (see the `@e2e exclude` lines in the spec), so the
 * reference is seeded in the shape the spec's data model gives for
 * `sourceType: 'files'`: `fileId`, `filePath` and the core preview URL in
 * `url`.
 *
 * Everything a test seeds is removed in `afterEach`, whether it passed or not.
 *
 * Gate-19 @e2e traceability:
 *   @e2e image-widget::cover-fit-fills-the-cell
 *   @e2e image-widget::contain-fit-preserves-aspect-ratio
 *   @e2e image-widget::allow-listed-external-image-loads
 *   @e2e image-widget::external-image-from-a-host-not-on-the-list-is-blocked
 *   @e2e image-widget::an-entry-that-is-not-a-host-name-is-refused
 *   @e2e image-widget::default-fit-is-cover
 *   @e2e image-widget::empty-url-shows-placeholder
 *   @e2e image-widget::null-url-shows-placeholder
 *   @e2e image-widget::placeholder-uses-maxcontrast-token
 *   @e2e image-widget::broken-external-url-falls-back
 *   @e2e image-widget::broken-url-does-not-crash-grid
 *   @e2e image-widget::sourcetype-persists-across-updates
 *   @e2e image-widget::sourcetype-included-in-api-response
 *   @e2e image-widget::file-path-survives-widget-title-and-style-updates
 *   @e2e image-widget::deleted-file-shows-broken-image-fallback
 *   @e2e image-widget::deleted-file-retains-placement-metadata
 *   @e2e image-widget::preview-url-handles-file-not-found-gracefully
 *   @e2e image-widget::broken-image-state-persists-until-file-is-restored
 *   @e2e image-widget::viewer-cannot-access-file-returns-null
 *   @e2e image-widget::permission-revoked-shows-fallback-without-crash
 *
 * @spec openspec/specs/image-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode, openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import { solidPng } from './support/pngFixture.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const APP = '/index.php/apps/launchpad'
const API = `${APP}/api`
const DAV = '/remote.php/dav'
const SHARES = '/ocs/v2.php/apps/files_sharing/api/v1/shares'
const IMAGE_HOSTS = `${API}/admin/image-hosts`
const RUN = Date.now()

// A same-origin asset that every Nextcloud serves, for the "other" cell that
// must keep working next to a broken one.
const LOGO = '/core/img/logo/logo.svg'

const FAILED = 'Image failed to load'

type Account = { username: string; password: string }
type ImageContent = Record<string, unknown>
type Seed = {
	content: ImageContent
	gridX?: number
	gridY?: number
	gridWidth?: number
	gridHeight?: number
}

let admin: APIRequestContext
let seeded: SeededDashboard[] = []
let files: string[] = []
let fileShares: number[] = []
let counter = 0
let previousImageHosts: string[] | null = null

/**
 * A basic-auth API context with an explicitly empty cookie jar, so the
 * project's stored admin session is never sent along with it.
 *
 * @param user the account name.
 * @param pass its password.
 * @return the context; the caller disposes it.
 */
async function apiAs(user: string, pass: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
			Authorization: `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`,
		},
	})
}

/** A name no other test or run uses. */
function unique(stem: string): string {
	counter += 1
	return `e2e-img-${stem}-${RUN}-${counter}`
}

/**
 * Seed an active dashboard holding the given image placements.
 *
 * @param seeds one entry per image cell, in placement order.
 * @return the dashboard and each placement's id, in the same order.
 */
async function seedImages(
	seeds: Seed[],
): Promise<{ dashboard: SeededDashboard; ids: number[] }> {
	const dashboard = await seedActiveDashboard(admin, unique('dash'))
	seeded.push(dashboard)
	const ids: number[] = []
	for (const [index, seed] of seeds.entries()) {
		const res = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
			data: {
				widgetId: 'image',
				gridX: seed.gridX ?? index * 4,
				gridY: seed.gridY ?? 0,
				gridWidth: seed.gridWidth ?? 4,
				gridHeight: seed.gridHeight ?? 4,
				content: seed.content,
			},
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
		const body = await res.json()
		const id = Number(body.id ?? body.data?.id)
		expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
		ids.push(id)
	}
	return { dashboard, ids }
}

/**
 * Read one placement back the way the app reads it: from the dashboard.
 *
 * @param dashboardId the dashboard holding it.
 * @param placementId the placement.
 * @return the serialised placement, or undefined when it is gone.
 */
async function readPlacement(
	dashboardId: number,
	placementId: number,
): Promise<Record<string, unknown> | undefined> {
	const res = await admin.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const placements = (body.placements ?? body.data?.placements ?? []) as Array<
		Record<string, unknown>
	>
	return placements.find((p) => Number(p.id) === placementId)
}

/** The rendered image cell of a placement. */
function imageCell(page: Page, placementId: number): Locator {
	return page.locator(`[data-placement-id="${placementId}"] .cn-image-widget`)
}

/** Open LaunchPad and wait until the given placement's image cell is there. */
async function openWorkspace(page: Page, placementId: number): Promise<Locator> {
	await page.goto(APP)
	const cell = imageCell(page, placementId)
	await expect(cell).toBeAttached({ timeout: 30_000 })
	return cell
}

/** Whether an `<img>` decoded real pixels (as opposed to being broken or blocked). */
async function decoded(img: Locator): Promise<boolean> {
	return img.evaluate((el) => {
		const image = el as HTMLImageElement
		return image.complete && image.naturalWidth > 0
	})
}

/** Answer requests for `url` with a PNG of the given size. */
async function servePng(page: Page, url: string | RegExp, width: number, height: number) {
	const body = solidPng(width, height)
	await page.route(url, (route) =>
		route.fulfill({ status: 200, contentType: 'image/png', body }),
	)
}

/** Collect uncaught page exceptions for the life of the page. */
function collectPageErrors(page: Page): string[] {
	const errors: string[] = []
	page.on('pageerror', (err) => errors.push(err.message))
	return errors
}

/**
 * Assert that a cell shows the broken-image fallback of REQ-IMG-004: no
 * `<img>`, the placeholder, and the "Image failed to load" annotation.
 */
async function expectFailedFallback(cell: Locator): Promise<void> {
	await expect(cell.locator('.cn-image-widget__placeholder-label')).toHaveText(
		FAILED,
		{ timeout: 15_000 },
	)
	await expect(cell.locator('.cn-image-widget__placeholder')).toBeVisible()
	await expect(cell.locator('img')).toHaveCount(0)
}

// ── Nextcloud Files, over WebDAV ─────────────────────────────────────────

/**
 * Upload a PNG to the root of a user's Files and return its file id.
 *
 * @param api that user's context.
 * @param user the user.
 * @param name the file name.
 * @return the Nextcloud file id.
 */
async function uploadPng(api: APIRequestContext, user: string, name: string): Promise<number> {
	const put = await api.put(`${DAV}/files/${user}/${name}`, {
		headers: { 'Content-Type': 'image/png' },
		data: solidPng(64, 48),
	})
	expect(put.status(), await put.text()).toBeLessThan(300)
	files.push(name)
	const id = await fileIdOf(api, user, name)
	expect(id, `no file id for ${name}`).not.toBeNull()
	return id as number
}

/** The file id of a file in a user's Files, or null when it is not there. */
async function fileIdOf(api: APIRequestContext, user: string, name: string): Promise<number | null> {
	const res = await api.fetch(`${DAV}/files/${user}/${name}`, {
		method: 'PROPFIND',
		headers: { Depth: '0', 'Content-Type': 'application/xml' },
		data: '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">'
			+ '<d:prop><oc:fileid/></d:prop></d:propfind>',
	})
	if (res.status() === 404) {
		return null
	}
	expect(res.status(), await res.text()).toBe(207)
	const match = /<oc:fileid>(\d+)<\/oc:fileid>/.exec(await res.text())
	return match ? Number(match[1]) : null
}

/** The trashbin entries (their DAV hrefs) that came from the given file name. */
async function trashEntries(api: APIRequestContext, user: string, name: string): Promise<string[]> {
	const res = await api.fetch(`${DAV}/trashbin/${user}/trash/`, {
		method: 'PROPFIND',
		headers: { Depth: '1' },
	})
	if (res.status() !== 207) {
		return []
	}
	const hrefs = [...(await res.text()).matchAll(/<d:href>([^<]+)<\/d:href>/g)].map((m) => m[1])
	return hrefs.filter((href) => decodeURIComponent(href.split('/').filter(Boolean).pop() ?? '').startsWith(`${name}.d`))
}

/** The core preview URL for a file id — what the spec's data model stores in `url`. */
function previewUrl(fileId: number): string {
	return `/index.php/core/preview?fileId=${fileId}&x=256&y=256&a=1`
}

/** File-mode content for a real file. */
function fileContent(fileId: number, name: string): ImageContent {
	return {
		sourceType: 'files',
		fileId,
		filePath: `/${name}`,
		url: previewUrl(fileId),
		alt: name,
		link: '',
		fit: 'cover',
	}
}

/** Remove a file from the admin's Files and from the admin's trashbin. */
async function purgeAdminFile(name: string): Promise<void> {
	await admin.delete(`${DAV}/files/${ADMIN.user}/${name}`)
	for (const href of await trashEntries(admin, ADMIN.user, name)) {
		await admin.delete(href)
	}
}

/**
 * Replace the image host allow-list (REQ-IMG-006) through the admin API,
 * remembering the list it replaces so `afterEach` puts it back.
 *
 * @param hosts the host names image widgets may load from.
 */
async function allowImageHosts(hosts: string[]): Promise<void> {
	if (previousImageHosts === null) {
		const current = await admin.get(IMAGE_HOSTS)
		expect(current.status(), await current.text()).toBe(200)
		previousImageHosts = ((await current.json()).hosts ?? []) as string[]
	}
	const res = await admin.put(IMAGE_HOSTS, { data: { hosts } })
	expect(res.status(), await res.text()).toBe(200)
}

test.beforeAll(async () => {
	admin = await apiAs(ADMIN.user, ADMIN.pass)
})

test.afterEach(async () => {
	for (const id of fileShares) {
		await admin.delete(`${SHARES}/${id}?format=json`)
	}
	for (const name of files) {
		await purgeAdminFile(name)
	}
	for (const dashboard of seeded) {
		await removeSeededDashboard(admin, dashboard)
	}
	if (previousImageHosts !== null) {
		await admin.put(IMAGE_HOSTS, { data: { hosts: previousImageHosts } })
		previousImageHosts = null
	}
	fileShares = []
	files = []
	seeded = []
})

test.afterAll(async () => {
	await admin?.dispose()
})

test.describe('image widget — rendering a placed cell', () => {
	test.describe.configure({ timeout: 90_000 })

	// @e2e image-widget::cover-fit-fills-the-cell
	test('REQ-IMG-001: fit cover renders object-fit cover at 100% in an overflow-hidden cell', async ({
		page,
	}) => {
		const name = unique('cover')
		await servePng(page, `**/apps/launchpad/resource/${name}.png`, 300, 120)
		const { ids } = await seedImages([
			{ content: { url: `/apps/launchpad/resource/${name}.png`, fit: 'cover' } },
		])

		const cell = await openWorkspace(page, ids[0])
		const img = cell.locator('img.cn-image-widget__img')
		await expect(img).toBeVisible({ timeout: 15_000 })
		await expect.poll(() => decoded(img), { timeout: 10_000 }).toBe(true)

		const style = await img.evaluate((el) => ({
			inlineFit: (el as HTMLElement).style.objectFit,
			computedFit: getComputedStyle(el).objectFit,
			width: (el as HTMLElement).style.width,
			height: (el as HTMLElement).style.height,
		}))
		expect(style.inlineFit, 'the fit must be an inline object-fit on the <img>').toBe('cover')
		expect(style.computedFit).toBe('cover')
		expect(style.width).toBe('100%')
		expect(style.height).toBe('100%')

		expect(
			await cell.evaluate((el) => getComputedStyle(el).overflow),
			'the cell wrapper must clip an over-fit image',
		).toBe('hidden')

		// 100% is not just written down: the image really fills its cell.
		const imgBox = await img.boundingBox()
		const cellBox = await cell.boundingBox()
		expect(imgBox && cellBox).toBeTruthy()
		expect(cellBox!.width).toBeGreaterThan(0)
		expect(cellBox!.height).toBeGreaterThan(0)
		expect(Math.abs(imgBox!.width - cellBox!.width)).toBeLessThanOrEqual(1)
		expect(Math.abs(imgBox!.height - cellBox!.height)).toBeLessThanOrEqual(1)
	})

	// @e2e image-widget::contain-fit-preserves-aspect-ratio
	// @e2e image-widget::allow-listed-external-image-loads
	test('REQ-IMG-001: fit contain on an external wide image letterboxes it', async ({
		page,
	}) => {
		// The scenario's own URL: an EXTERNAL http(s) image, which the data
		// model allows. `page.route` answers it, so no internet is needed —
		// but the browser still applies the page's Content-Security-Policy
		// before any request leaves, so a policy that does not allow the
		// image's origin shows up here as an image that never decodes.
		const external = 'https://example.com/wide.png'
		await servePng(page, external, 800, 40)
		// REQ-IMG-006: external image hosts are fail-closed, so the
		// administrator allow-lists this one first (restored afterwards).
		await allowImageHosts(['example.com'])
		const csp: string[] = []
		page.on('console', (msg) => {
			if (/content security policy/i.test(msg.text())) {
				csp.push(msg.text())
			}
		})

		const { ids } = await seedImages([{ content: { url: external, fit: 'contain' } }])
		const cell = await openWorkspace(page, ids[0])
		const img = cell.locator('img.cn-image-widget__img')

		await expect
			.poll(async () => (await img.count()) > 0 && (await decoded(img)), {
				timeout: 15_000,
				message: 'the external image must load and stay an <img>',
			})
			.toBe(true)
			.catch((err: Error) => {
				throw new Error(`${err.message}\nCSP reports: ${csp.join(' | ') || 'none'}`)
			})

		const geometry = await img.evaluate((el) => {
			const image = el as HTMLImageElement
			const box = image.getBoundingClientRect()
			const scale = Math.min(box.width / image.naturalWidth, box.height / image.naturalHeight)
			return {
				fit: getComputedStyle(image).objectFit,
				inlineFit: image.style.objectFit,
				boxW: box.width,
				boxH: box.height,
				drawnW: image.naturalWidth * scale,
				drawnH: image.naturalHeight * scale,
				imageRatio: image.naturalWidth / image.naturalHeight,
			}
		})
		expect(geometry.inlineFit).toBe('contain')
		expect(geometry.fit).toBe('contain')
		// The premise: a 20:1 image cannot match the cell's own ratio.
		expect(Math.abs(geometry.imageRatio - geometry.boxW / geometry.boxH)).toBeGreaterThan(0.5)
		// Contain draws the whole image inside the box, so one axis leaves
		// empty bands — the letterbox.
		const bandH = geometry.boxH - geometry.drawnH
		const bandW = geometry.boxW - geometry.drawnW
		expect(Math.max(bandH, bandW), 'contain must leave visible letterbox bands').toBeGreaterThan(10)
	})

	// @e2e image-widget::default-fit-is-cover
	test('REQ-IMG-001: content with no fit field renders object-fit cover', async ({
		page,
	}) => {
		const name = unique('nofit')
		await servePng(page, `**/apps/launchpad/resource/${name}.png`, 120, 300)
		// No `fit` key at all — not an empty string.
		const { dashboard, ids } = await seedImages([
			{ content: { url: `/apps/launchpad/resource/${name}.png` } },
		])
		const stored = await readPlacement(dashboard.id, ids[0])
		expect(
			(stored?.content as ImageContent | undefined)?.fit,
			'the premise is a placement with NO fit field',
		).toBeUndefined()

		const cell = await openWorkspace(page, ids[0])
		const img = cell.locator('img.cn-image-widget__img')
		await expect(img).toBeVisible({ timeout: 15_000 })
		expect(await img.evaluate((el) => (el as HTMLElement).style.objectFit)).toBe('cover')
		expect(await img.evaluate((el) => getComputedStyle(el).objectFit)).toBe('cover')
	})

	// @e2e image-widget::empty-url-shows-placeholder
	test('REQ-IMG-002: an empty url renders the 48px camera placeholder and no <img>', async ({
		page,
	}) => {
		const { ids } = await seedImages([{ content: { url: '', alt: '', link: '', fit: 'cover' } }])
		const cell = await openWorkspace(page, ids[0])

		const placeholder = cell.locator('.cn-image-widget__placeholder')
		await expect(placeholder).toBeVisible({ timeout: 15_000 })
		await expect(cell.locator('img')).toHaveCount(0)
		await expect(cell.locator('.cn-image-widget__placeholder-label')).toHaveText('No image')

		// A CAMERA, not just any 48px glyph: the MDI icon components tag
		// each icon's wrapper with `<name>-icon`.
		const camera = placeholder.locator('.camera-icon svg')
		await expect(
			camera,
			'the placeholder icon must be the camera glyph',
		).toHaveCount(1)
		await expect(camera).toHaveAttribute('width', '48')
		await expect(camera).toHaveAttribute('height', '48')
	})

	// @e2e image-widget::null-url-shows-placeholder
	test('REQ-IMG-002: a null url renders the placeholder and no <img>', async ({ page }) => {
		const { dashboard, ids } = await seedImages([{ content: { url: null, fit: 'cover' } }])
		const stored = await readPlacement(dashboard.id, ids[0])
		expect(
			(stored?.content as ImageContent | undefined)?.url,
			'the premise is a placement whose url is null',
		).toBeNull()

		const cell = await openWorkspace(page, ids[0])
		await expect(cell.locator('.cn-image-widget__placeholder')).toBeVisible({ timeout: 15_000 })
		await expect(cell.locator('.cn-image-widget__placeholder-label')).toHaveText('No image')
		await expect(cell.locator('img')).toHaveCount(0)
	})

	// @e2e image-widget::placeholder-uses-maxcontrast-token
	test('REQ-IMG-002: the placeholder is maxcontrast-coloured and centred in the cell', async ({
		page,
	}) => {
		const { ids } = await seedImages([{ content: { url: '' } }])
		const cell = await openWorkspace(page, ids[0])
		const placeholder = cell.locator('.cn-image-widget__placeholder')
		await expect(placeholder).toBeVisible({ timeout: 15_000 })

		const colour = await placeholder.evaluate((el) => {
			// Resolve the token in the same cascade the placeholder sits in.
			const probe = document.createElement('span')
			probe.style.color = 'var(--color-text-maxcontrast)'
			el.appendChild(probe)
			const token = getComputedStyle(probe).color
			probe.remove()
			return {
				declared: (el as HTMLElement).style.color,
				computed: getComputedStyle(el).color,
				token,
				tokenDefined: getComputedStyle(el).getPropertyValue('--color-text-maxcontrast').trim() !== '',
			}
		})
		expect(colour.declared.replace(/\s+/g, '')).toBe('var(--color-text-maxcontrast)')
		expect(colour.tokenDefined, 'the theme must define --color-text-maxcontrast').toBe(true)
		expect(colour.computed).toBe(colour.token)

		// Centred: the placeholder fills the cell, and the icon + label group
		// sits on the cell's centre on both axes.
		const geometry = await placeholder.evaluate((el) => {
			const outer = el.getBoundingClientRect()
			const kids = Array.from(el.children).map((child) => child.getBoundingClientRect())
			const top = Math.min(...kids.map((r) => r.top))
			const bottom = Math.max(...kids.map((r) => r.bottom))
			const left = Math.min(...kids.map((r) => r.left))
			const right = Math.max(...kids.map((r) => r.right))
			return {
				outer: { x: outer.left, y: outer.top, w: outer.width, h: outer.height },
				dx: (left + right) / 2 - (outer.left + outer.width / 2),
				dy: (top + bottom) / 2 - (outer.top + outer.height / 2),
			}
		})
		const cellBox = await cell.boundingBox()
		expect(cellBox).toBeTruthy()
		expect(Math.abs(geometry.outer.w - cellBox!.width)).toBeLessThanOrEqual(1)
		expect(Math.abs(geometry.outer.h - cellBox!.height)).toBeLessThanOrEqual(1)
		expect(Math.abs(geometry.dx), 'horizontally centred').toBeLessThanOrEqual(2)
		expect(Math.abs(geometry.dy), 'vertically centred').toBeLessThanOrEqual(2)
	})
})

test.describe('image widget — broken-image fallback', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e image-widget::broken-external-url-falls-back
	test('REQ-IMG-004: a 404 external image swaps to the fallback and the grid keeps working', async ({
		page,
	}) => {
		const missing = 'https://example.com/missing.png'
		await page.route(missing, (route) =>
			route.fulfill({ status: 404, contentType: 'text/plain', body: 'not found' }),
		)
		const errors = collectPageErrors(page)
		const { ids } = await seedImages([
			{ content: { url: missing } },
			{ content: { url: LOGO, alt: 'logo' } },
		])
		const [brokenId, otherId] = ids

		const broken = await openWorkspace(page, brokenId)
		await expectFailedFallback(broken)

		// The other widget still renders...
		const other = imageCell(page, otherId)
		const otherImg = other.locator('img.cn-image-widget__img')
		await expect(otherImg).toBeVisible({ timeout: 15_000 })
		await expect.poll(() => decoded(otherImg), { timeout: 10_000 }).toBe(true)

		// ...and still responds: in edit mode the grid's context menu opens on
		// it and its Edit action opens the editor pre-filled with that cell's
		// own content.
		await enterEditMode(page)
		await page.locator(`[data-placement-id="${otherId}"]`).dispatchEvent('contextmenu')
		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 5_000 })
		await menu.locator('[data-testid="ctx-edit"]').click()
		const editor = page.getByRole('dialog', { name: /edit widget/i }).first()
		await expect(editor).toBeVisible({ timeout: 10_000 })
		await expect(editor.getByLabel(/image url/i)).toHaveValue(LOGO)
		await page.keyboard.press('Escape')
		await expect(editor).not.toBeVisible({ timeout: 8_000 })

		await expectFailedFallback(broken)
		expect(errors, 'the error event must not surface as an uncaught exception').toEqual([])
	})

	// @e2e image-widget::broken-url-does-not-crash-grid
	test('REQ-IMG-004: a broken image raises no exception and other cells can still be moved and edited', async ({
		page,
	}) => {
		// A real 404 from this instance's own resource route — no mocking.
		const errors = collectPageErrors(page)
		const { dashboard, ids } = await seedImages([
			{ content: { url: `/apps/launchpad/resource/${unique('missing')}.png` }, gridX: 0, gridWidth: 4 },
			{ content: { url: LOGO, alt: 'logo' }, gridX: 4, gridWidth: 3 },
		])
		const [brokenId, otherId] = ids

		const broken = await openWorkspace(page, brokenId)
		await expectFailedFallback(broken)

		await enterEditMode(page)

		// Drag, the keyboard way: move the other cell one column right.
		const item = page.locator(`.grid-stack-item[gs-id="${otherId}"]`)
		await expect(item).toBeVisible({ timeout: 8_000 })
		const startX = Number(await item.getAttribute('gs-x'))
		await item.focus()
		await page.keyboard.press('Enter')
		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 5_000 })
		await page.locator('[data-testid="ctx-move"]').focus()
		await page.keyboard.press('Enter')
		const panel = page.locator('[data-test="widget-move-panel"]')
		await expect(panel).toBeVisible({ timeout: 5_000 })
		await page.keyboard.press('ArrowRight')
		await page.keyboard.press('Enter')
		await expect(panel).not.toBeVisible({ timeout: 5_000 })
		await expect
			.poll(async () => Number(await item.getAttribute('gs-x')), { timeout: 5_000 })
			.toBeGreaterThan(startX)
		await expect
			.poll(async () => Number((await readPlacement(dashboard.id, otherId))?.gridX), {
				timeout: 10_000,
				message: 'the move must be saved, not just drawn',
			})
			.toBeGreaterThan(startX)

		// Edit on the other cell still opens its editor.
		await page.locator(`[data-placement-id="${otherId}"]`).dispatchEvent('contextmenu')
		await expect(menu).toBeVisible({ timeout: 5_000 })
		await menu.locator('[data-testid="ctx-edit"]').click()
		const editor = page.getByRole('dialog', { name: /edit widget/i }).first()
		await expect(editor).toBeVisible({ timeout: 10_000 })
		await expect(editor.getByLabel(/image url/i)).toHaveValue(LOGO)
		await page.keyboard.press('Escape')
		await expect(editor).not.toBeVisible({ timeout: 8_000 })

		await expectFailedFallback(broken)
		expect(errors, 'no uncaught exception may be raised').toEqual([])
	})
})

test.describe('image widget — file-mode fields survive the placement API', () => {
	// @e2e image-widget::sourcetype-persists-across-updates
	test('REQ-IMP-001: a title update leaves sourceType "files" in place', async () => {
		const content = {
			sourceType: 'files',
			fileId: 12345,
			filePath: '/Photos/sunset.png',
			url: previewUrl(12345),
			alt: '',
			link: '',
			fit: 'cover',
		}
		const { dashboard, ids } = await seedImages([{ content }])

		const title = `Renamed ${RUN}`
		const put = await admin.put(`${API}/widgets/${ids[0]}`, { data: { customTitle: title } })
		expect(put.status(), await put.text()).toBeLessThan(300)

		const after = await readPlacement(dashboard.id, ids[0])
		expect(after?.customTitle, 'the update itself must have landed').toBe(title)
		expect((after?.content as ImageContent).sourceType).toBe('files')
	})

	// @e2e image-widget::sourcetype-included-in-api-response
	test('REQ-IMP-001: the placement in the API response carries "sourceType": "upload"', async () => {
		const { dashboard, ids } = await seedImages([
			{
				content: {
					sourceType: 'upload',
					url: `/apps/launchpad/resource/${unique('uploaded')}.png`,
					alt: '',
					link: '',
					fit: 'cover',
				},
			},
		])

		const res = await admin.get(`${API}/dashboard/${dashboard.id}`)
		expect(res.status()).toBe(200)
		const body = await res.json()
		const placement = ((body.placements ?? []) as Array<Record<string, unknown>>).find(
			(p) => Number(p.id) === ids[0],
		)
		expect(placement, 'the seeded placement must be in the response').toBeTruthy()
		// As JSON on the wire, not only after some client-side normalisation.
		expect(JSON.stringify(placement!.content)).toContain('"sourceType":"upload"')
	})

	// @e2e image-widget::file-path-survives-widget-title-and-style-updates
	test('REQ-IMP-003: title and style updates leave fileId and filePath unchanged', async () => {
		const content = {
			sourceType: 'files',
			fileId: 12345,
			filePath: '/Photos/sunset.png',
			url: previewUrl(12345),
			alt: '',
			link: '',
			fit: 'cover',
		}
		const { dashboard, ids } = await seedImages([{ content }])

		const title = await admin.put(`${API}/widgets/${ids[0]}`, {
			data: { customTitle: `Sunset ${RUN}` },
		})
		expect(title.status(), await title.text()).toBeLessThan(300)
		const style = await admin.put(`${API}/widgets/${ids[0]}`, {
			data: { styleConfig: { backgroundColor: '#123456' } },
		})
		expect(style.status(), await style.text()).toBeLessThan(300)

		const after = await readPlacement(dashboard.id, ids[0])
		expect(after?.customTitle).toBe(`Sunset ${RUN}`)
		expect((after?.styleConfig as Record<string, unknown>)?.backgroundColor).toBe('#123456')
		const stored = after?.content as ImageContent
		expect(stored.fileId).toBe(12345)
		expect(stored.filePath).toBe('/Photos/sunset.png')
	})
})

test.describe('image widget — a file-mode cell whose file goes away', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e image-widget::deleted-file-shows-broken-image-fallback
	// @e2e image-widget::deleted-file-retains-placement-metadata
	// @e2e image-widget::preview-url-handles-file-not-found-gracefully
	test('REQ-IMP-005/006: deleting the file keeps the reference and the cell falls back', async ({
		page,
	}) => {
		const name = `${unique('sunset')}.png`
		const fileId = await uploadPng(admin, ADMIN.user, name)
		const { dashboard, ids } = await seedImages([{ content: fileContent(fileId, name) }])

		// The reference was good before the file went: the preview served it.
		const before = await admin.get(previewUrl(fileId))
		expect(before.status(), 'the preview must work while the file exists').toBe(200)

		const del = await admin.delete(`${DAV}/files/${ADMIN.user}/${name}`)
		expect(del.status(), await del.text()).toBeLessThan(300)
		expect(await fileIdOf(admin, ADMIN.user, name)).toBeNull()

		// REQ-IMP-006: the placement is not auto-deleted and its reference is
		// untouched.
		const after = await readPlacement(dashboard.id, ids[0])
		expect(after, 'the placement must still exist after its file is deleted').toBeTruthy()
		expect((after!.content as ImageContent).fileId).toBe(fileId)
		expect((after!.content as ImageContent).filePath).toBe(`/${name}`)
		expect((after!.content as ImageContent).sourceType).toBe('files')

		// Next page load: the preview 404s, the <img> errors, the cell falls back.
		const errors = collectPageErrors(page)
		const preview = page.waitForResponse(
			(r) => r.url().includes('/core/preview') && r.url().includes(`fileId=${fileId}`),
			{ timeout: 30_000 },
		)
		const cell = await openWorkspace(page, ids[0])
		expect((await preview).status(), 'the preview of a deleted file must 404').toBe(404)
		await expectFailedFallback(cell)
		expect(errors, 'a missing file must not throw').toEqual([])
	})

	// @e2e image-widget::broken-image-state-persists-until-file-is-restored
	test('REQ-IMP-005: a restored file shows again after a refresh, not before', async ({
		page,
	}) => {
		const trash = await admin.fetch(`${DAV}/trashbin/${ADMIN.user}/trash/`, {
			method: 'PROPFIND',
			headers: { Depth: '0' },
		})
		test.skip(trash.status() === 404, 'the files_trashbin app is not enabled, so no file can be restored')

		const name = `${unique('restore')}.png`
		const fileId = await uploadPng(admin, ADMIN.user, name)
		const { ids } = await seedImages([{ content: fileContent(fileId, name) }])
		const del = await admin.delete(`${DAV}/files/${ADMIN.user}/${name}`)
		expect(del.status(), await del.text()).toBeLessThan(300)

		const cell = await openWorkspace(page, ids[0])
		await expectFailedFallback(cell)

		// Restore it from the trashbin, with the same file id.
		const [entry] = await trashEntries(admin, ADMIN.user, name)
		expect(entry, `${name} must be in the trashbin`).toBeTruthy()
		const restore = await admin.fetch(entry, {
			method: 'MOVE',
			headers: {
				Destination: `${BASE_URL}${DAV}/trashbin/${ADMIN.user}/restore/${encodeURIComponent(name)}`,
			},
		})
		expect(restore.status(), await restore.text()).toBeLessThan(300)
		expect(await fileIdOf(admin, ADMIN.user, name), 'the premise: same file id after restore').toBe(fileId)

		// Without a refresh the cell does not re-validate on its own.
		await page.waitForTimeout(3_000)
		await expectFailedFallback(cell)

		// A refresh re-attempts the preview, and now it loads.
		await page.reload()
		const img = imageCell(page, ids[0]).locator('img.cn-image-widget__img')
		await expect(img).toBeVisible({ timeout: 30_000 })
		await expect.poll(() => decoded(img), { timeout: 15_000 }).toBe(true)
	})
})

test.describe('image widget — a file-mode cell seen by someone who may not read the file', () => {
	test.describe.configure({ timeout: 150_000 })

	let viewer: Account | null = null
	let viewerApi: APIRequestContext

	test.beforeAll(async () => {
		viewer = await provisionThrowawayUser('e2e-img-viewer')
		viewerApi = await apiAs(viewer.username, viewer.password)
	})

	test.afterAll(async () => {
		await viewerApi?.dispose()
		if (viewer !== null) {
			await deprovisionUser(viewer.username)
		}
	})

	/**
	 * Seed an admin dashboard holding the given cells, share it read-only with
	 * the viewer, and make it the viewer's active dashboard.
	 */
	async function seedForViewer(seeds: Seed[]): Promise<number[]> {
		const { dashboard, ids } = await seedImages(seeds)
		const share = await admin.post(`${API}/dashboard/${dashboard.id}/shares`, {
			data: { shareType: 'user', shareWith: viewer!.username, permissionLevel: 'view_only' },
		})
		expect(share.status(), await share.text()).toBeLessThan(300)
		// A new account's first request also sets up its home folder, which takes a while.
		const active = await viewerApi.post(`${API}/dashboards/active`, {
			data: { uuid: dashboard.uuid },
			timeout: 60_000,
		})
		expect(active.status(), await active.text()).toBeLessThan(300)
		return ids
	}

	// @e2e image-widget::viewer-cannot-access-file-returns-null
	test('REQ-IMP-004: a file the viewer may not read 404s on preview and the cell falls back', async ({
		browser,
	}) => {
		// The admin's own file, never shared: the viewer has no permission on it.
		const name = `${unique('private')}.png`
		const fileId = await uploadPng(admin, ADMIN.user, name)
		expect((await admin.get(previewUrl(fileId))).status(), 'the owner can preview it').toBe(200)
		const [cellId] = await seedForViewer([{ content: fileContent(fileId, name) }])

		const { context, page } = await loginAs(browser, viewer!.username, viewer!.password)
		try {
			const preview = page.waitForResponse(
				(r) => r.url().includes('/core/preview') && r.url().includes(`fileId=${fileId}`),
				{ timeout: 30_000 },
			)
			const cell = await openWorkspace(page, cellId)
			expect(
				(await preview).status(),
				'the client-side preview request must be refused with 404, not 403',
			).toBe(404)
			await expectFailedFallback(cell)
		} finally {
			await context.close()
		}
	})

	// @e2e image-widget::permission-revoked-shows-fallback-without-crash
	test('REQ-IMP-005: after a file share is revoked the cell falls back and the rest still works', async ({
		browser,
	}) => {
		const name = `${unique('shared')}.png`
		const fileId = await uploadPng(admin, ADMIN.user, name)
		const fileShare = await admin.post(`${SHARES}?format=json`, {
			form: { path: `/${name}`, shareType: '0', shareWith: viewer!.username, permissions: '1' },
		})
		expect(fileShare.status(), await fileShare.text()).toBeLessThan(300)
		const shareId = Number((await fileShare.json()).ocs.data.id)
		fileShares.push(shareId)

		const [cellId, otherId] = await seedForViewer([
			{ content: fileContent(fileId, name) },
			{ content: { url: LOGO, alt: 'logo' } },
		])

		// Previously accessible: the viewer could preview it through the share.
		await expect
			.poll(async () => (await viewerApi.get(previewUrl(fileId))).status(), {
				timeout: 30_000,
				message: 'the viewer must be able to preview the file while it is shared',
			})
			.toBe(200)

		const revoke = await admin.delete(`${SHARES}/${shareId}?format=json`)
		expect(revoke.status(), await revoke.text()).toBeLessThan(300)
		fileShares = fileShares.filter((id) => id !== shareId)
		expect((await viewerApi.get(previewUrl(fileId))).status()).not.toBe(200)

		// The next page load, in a fresh browser — a browser that already
		// held the preview in its own HTTP cache would not ask the server
		// again, and that cache is Nextcloud's, not the widget's.
		const { context, page } = await loginAs(browser, viewer!.username, viewer!.password)
		try {
			const errors = collectPageErrors(page)
			const cell = await openWorkspace(page, cellId)
			await expectFailedFallback(cell)

			// The surrounding widget still renders its image...
			const otherImg = imageCell(page, otherId).locator('img.cn-image-widget__img')
			await expect(otherImg).toBeVisible({ timeout: 15_000 })
			await expect.poll(() => decoded(otherImg), { timeout: 10_000 }).toBe(true)

			// ...and the workspace around the grid still responds.
			await openDashboardSwitcher(page)

			await expectFailedFallback(cell)
			expect(errors, 'a revoked file must not throw').toEqual([])
		} finally {
			await context.close()
		}
	})
})

test.describe('image widget — external image host allow-list', () => {
	test.describe.configure({ timeout: 90_000 })

	// @e2e image-widget::external-image-from-a-host-not-on-the-list-is-blocked
	test('REQ-IMG-006: an external image from a host not on the list is blocked and falls back', async ({
		page,
	}) => {
		await allowImageHosts(['example.com'])
		// Answered by `page.route`, so only the page's CSP can stop it.
		const external = 'https://example.net/photo.png'
		await servePng(page, external, 64, 48)
		const csp: string[] = []
		page.on('console', (msg) => {
			if (/content security policy/i.test(msg.text())) {
				csp.push(msg.text())
			}
		})

		const { ids } = await seedImages([{ content: { url: external, fit: 'cover' } }])
		const cell = await openWorkspace(page, ids[0])

		await expectFailedFallback(cell)
		expect(csp.join(' | '), 'the browser must name img-src for the refused image').toMatch(/img-src/)
	})

	// @e2e image-widget::an-entry-that-is-not-a-host-name-is-refused
	test('REQ-IMG-006: an entry that is not a host name is refused and nothing is stored', async () => {
		await allowImageHosts(['example.com'])

		const res = await admin.put(IMAGE_HOSTS, { data: { hosts: ['https://example.org'] } })
		expect(res.status()).toBe(400)
		expect((await res.json()).message).toContain('https://example.org')

		const stored = await admin.get(IMAGE_HOSTS)
		expect((await stored.json()).hosts).toEqual(['example.com'])
	})
})
