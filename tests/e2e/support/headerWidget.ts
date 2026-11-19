/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the header-widget specs (openspec/specs/header-widget).
 *
 * The header type is nc-vue's `CnHeaderWidget`, registered as widget type
 * `header` in the communal dashboard registry; LaunchPad renders it through
 * `WidgetRenderer` inside `WidgetWrapper`, whose root carries
 * `data-testid="widget-placement-<id>"`. A placement is seeded with
 * `POST /api/dashboard/{id}/widgets` and its `content` blob is the widget's
 * configuration, exactly what the Add Widget modal would write.
 *
 * WHY THE IMAGES ARE SAME-ORIGIN
 * ==============================
 * The workspace page sends its own Content-Security-Policy
 * (`PageController::buildWorkspaceCsp`), whose `img-src` admits the
 * instance itself, `data:` and a handful of video/map hosts. An image on
 * `https://example.com` is refused by the browser before any request is made,
 * which fires the same `error` event a 404 does. A spec that used such a URL
 * would therefore pass every "falls back" test for the wrong reason and fail
 * every "loads" test for the wrong reason. Every image here is served by the
 * instance under test (or by a real file in the admin's own Files), so a load
 * and a failure are each caused by what the test says causes them.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { deflateSync } from 'zlib'
import { BASE_URL } from './baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './dashboardFixture.ts'

export const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

export const API = '/index.php/apps/launchpad/api'

/** A same-origin SVG every Nextcloud ships; loads without outbound internet. */
export const LOGO_URL = `${BASE_URL}/core/img/logo/logo.svg`

/**
 * The admin over basic auth, with an explicitly empty jar so the project's
 * stored browser session is not sent along.
 *
 * @return the context; the caller disposes it.
 */
export async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
		timeout: 60_000,
	})
}

export interface SeedGrid {
	gridX?: number
	gridY?: number
	gridWidth?: number
	gridHeight?: number
}

/**
 * Place a header widget on a dashboard through the API.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @param content the header's content blob.
 * @param grid where it goes; full width, four rows by default.
 * @return the new placement id.
 */
export async function seedHeader(
	api: APIRequestContext,
	dashboardId: number,
	content: Record<string, unknown>,
	grid: SeedGrid = {},
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'header',
			gridX: grid.gridX ?? 0,
			gridY: grid.gridY ?? 0,
			gridWidth: grid.gridWidth ?? 12,
			gridHeight: grid.gridHeight ?? 4,
			content,
		},
	})
	expect(res.status(), await res.text()).toBe(201)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/** A placement as `GET /api/dashboard/{id}` returns it. */
export interface PlacementRow {
	id: number
	widgetId: string
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	content: unknown
}

/**
 * Read a dashboard back: its grid column count and its placements.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @return the dashboard's columns and placements, plus the raw body text.
 */
export async function readDashboard(
	api: APIRequestContext,
	dashboardId: number,
): Promise<{ gridColumns: number; placements: PlacementRow[]; raw: string }> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	const raw = await res.text()
	expect(res.status(), raw).toBe(200)
	const body = JSON.parse(raw)
	const data = body.data ?? body
	return {
		gridColumns: Number(data.dashboard?.gridColumns ?? 12),
		placements: (data.placements ?? []) as PlacementRow[],
		raw,
	}
}

/**
 * The header placements on a dashboard, oldest first.
 *
 * @param api an admin context.
 * @param dashboardId the dashboard.
 * @return the header placements.
 */
export async function headerPlacements(
	api: APIRequestContext,
	dashboardId: number,
): Promise<PlacementRow[]> {
	const { placements } = await readDashboard(api, dashboardId)
	return placements
		.filter((p) => p.widgetId === 'header')
		.sort((a, b) => Number(a.id) - Number(b.id))
}

/**
 * The rendered `.cn-header-widget` root of a placement.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the banner locator.
 */
export function banner(page: Page, placementId: number): Locator {
	return page.locator(`[data-testid="widget-placement-${placementId}"] .cn-header-widget`)
}

/**
 * Open the workspace and wait until the shell has rendered.
 *
 * @param page the page.
 * @return nothing.
 */
export async function openWorkspace(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator('[data-testid="launchpad-more-menu"]')).toBeVisible({ timeout: 30_000 })
}

/**
 * A computed style property of the first element a locator matches.
 *
 * @param locator the element.
 * @param property the CSS property name, kebab-case.
 * @return the computed value.
 */
export async function cssOf(locator: Locator, property: string): Promise<string> {
	return locator.evaluate(
		(el, prop) => window.getComputedStyle(el).getPropertyValue(prop),
		property,
	)
}

/**
 * Convert `#rrggbb` to the `rgb(r, g, b)` form `getComputedStyle` reports.
 *
 * @param hex the colour.
 * @return the computed-style spelling.
 */
export function rgb(hex: string): string {
	const m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex)
	if (m === null) {
		throw new Error(`not a #rrggbb colour: ${hex}`)
	}
	return `rgb(${Number.parseInt(m[1], 16)}, ${Number.parseInt(m[2], 16)}, ${Number.parseInt(m[3], 16)})`
}

/**
 * Parse an `rgb()` / `rgba()` computed colour into channels.
 *
 * @param value the computed colour.
 * @return the channels, alpha defaulting to 1.
 */
export function channels(value: string): { r: number; g: number; b: number; a: number } {
	const m = /rgba?\(\s*([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\s*\)/.exec(value)
	if (m === null) {
		throw new Error(`not an rgb() colour: ${value}`)
	}
	return {
		r: Number(m[1]),
		g: Number(m[2]),
		b: Number(m[3]),
		a: m[4] === undefined ? 1 : Number(m[4]),
	}
}

/**
 * WCAG 2.1 contrast ratio between two opaque computed colours.
 *
 * @param a one colour, computed spelling.
 * @param b the other.
 * @return the ratio, 1 to 21.
 */
export function contrastRatio(a: string, b: string): number {
	const lum = (value: string): number => {
		const c = channels(value)
		const lin = [c.r, c.g, c.b].map((v) => {
			const s = v / 255
			return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
		})
		return 0.2126 * lin[0] + 0.7152 * lin[1] + 0.0722 * lin[2]
	}
	const [hi, lo] = [lum(a), lum(b)].sort((x, y) => y - x)
	return (hi + 0.05) / (lo + 0.05)
}

/** CRC-32 as PNG chunks need it. */
function crc32(buf: Buffer): number {
	let crc = 0xffffffff
	for (const byte of buf) {
		crc ^= byte
		for (let k = 0; k < 8; k++) {
			crc = (crc >>> 1) ^ (0xedb88320 & -(crc & 1))
		}
	}
	return (crc ^ 0xffffffff) >>> 0
}

/**
 * A solid-colour RGB PNG, so the preview route has a real image to scale.
 *
 * @param width pixels.
 * @param height pixels.
 * @param color `#rrggbb`.
 * @return the PNG bytes.
 */
export function solidPng(width: number, height: number, color: string): Buffer {
	const { r, g, b } = channels(rgb(color))
	const chunk = (type: string, data: Buffer): Buffer => {
		const len = Buffer.alloc(4)
		len.writeUInt32BE(data.length)
		const body = Buffer.concat([Buffer.from(type, 'ascii'), data])
		const crc = Buffer.alloc(4)
		crc.writeUInt32BE(crc32(body))
		return Buffer.concat([len, body, crc])
	}
	const ihdr = Buffer.alloc(13)
	ihdr.writeUInt32BE(width, 0)
	ihdr.writeUInt32BE(height, 4)
	ihdr[8] = 8 // bit depth
	ihdr[9] = 2 // colour type: truecolour
	const row = Buffer.alloc(1 + width * 3)
	for (let x = 0; x < width; x++) {
		row[1 + x * 3] = r
		row[2 + x * 3] = g
		row[3 + x * 3] = b
	}
	const raw = Buffer.concat(Array.from({ length: height }, () => row))
	return Buffer.concat([
		Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
		chunk('IHDR', ihdr),
		chunk('IDAT', deflateSync(raw)),
		chunk('IEND', Buffer.alloc(0)),
	])
}

/**
 * Upload a file into the admin's own Files over WebDAV and return its file id.
 *
 * @param api an admin context.
 * @param name the file name in the admin's root.
 * @param bytes the content.
 * @return the Nextcloud file id.
 */
export async function uploadAdminFile(
	api: APIRequestContext,
	name: string,
	bytes: Buffer,
): Promise<number> {
	const url = `/remote.php/dav/files/${encodeURIComponent(ADMIN.user)}/${encodeURIComponent(name)}`
	const put = await api.put(url, { data: bytes, headers: { 'Content-Type': 'image/png' } })
	expect(put.status(), await put.text()).toBeLessThan(300)
	const find = await api.fetch(url, {
		method: 'PROPFIND',
		headers: { Depth: '0', 'Content-Type': 'application/xml' },
		data: '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">'
			+ '<d:prop><oc:fileid/></d:prop></d:propfind>',
	})
	const xml = await find.text()
	expect(find.status(), xml).toBe(207)
	const m = /<oc:fileid>(\d+)<\/oc:fileid>/.exec(xml)
	expect(m, `no oc:fileid in ${xml}`).not.toBeNull()
	return Number(m![1])
}

/**
 * Remove a file from the admin's Files, tolerating one that is already gone.
 *
 * @param api an admin context.
 * @param name the file name in the admin's root.
 * @return nothing.
 */
export async function removeAdminFile(api: APIRequestContext, name: string): Promise<void> {
	await api.delete(`/remote.php/dav/files/${encodeURIComponent(ADMIN.user)}/${encodeURIComponent(name)}`)
}

/** The probe `<img>` a banner uses to detect a failing background image. */
export function probe(b: Locator): Locator {
	return b.locator('img.cn-header-widget__probe')
}

/**
 * The background-image a banner paints.
 *
 * @param b the banner.
 * @return the computed `background-image`.
 */
export function bg(b: Locator): Promise<string> {
	return cssOf(b, 'background-image')
}

/**
 * Wait until a banner's background image has actually decoded.
 *
 * @param b the banner.
 * @return nothing.
 */
export async function expectImageLoaded(b: Locator): Promise<void> {
	await expect
		.poll(
			() => probe(b).evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0),
			{ message: 'the background image must load and decode', timeout: 15_000 },
		)
		.toBe(true)
}

/**
 * Assert a banner shows its solid colour and nothing of a failed image: no
 * CSS image, no `<img>` left behind, no alert and no error text.
 *
 * @param b the banner.
 * @param color the configured backgroundColor, `#rrggbb`.
 * @return nothing.
 */
export async function expectColourOnly(b: Locator, color: string): Promise<void> {
	await expect
		.poll(() => bg(b), {
			message: 'a failed image must leave no CSS background image',
			timeout: 15_000,
		})
		.toBe('none')
	expect(await cssOf(b, 'background-color')).toBe(rgb(color))
	await expect(b.locator('img'), 'no broken-image <img> may remain').toHaveCount(0)
	await expect(b.getByRole('alert')).toHaveCount(0)
	await expect(b).not.toContainText(/error|failed|could not|kan niet/i)
}

/**
 * Open the workspace and return the rendered banners, once each is visible.
 *
 * @param page the page.
 * @param ids the placements, in order.
 * @return their banner locators.
 */
export async function render(page: Page, ...ids: number[]): Promise<Locator[]> {
	await openWorkspace(page)
	const banners = ids.map((id) => banner(page, id))
	for (const b of banners) {
		await expect(b).toBeVisible({ timeout: 20_000 })
	}
	return banners
}

/** What a header spec file gets from {@link headerSuite}. */
export interface HeaderSuite {
	/** The admin API context. */
	api: () => APIRequestContext
	/** This test's dashboard. */
	dashboard: () => SeededDashboard
	/** The id of a real PNG in the admin's own Files, when asked for. */
	fileId: () => number
	/**
	 * Seed a full-width header on this test's dashboard.
	 *
	 * @param content the content blob.
	 * @param gridY the row it starts on.
	 * @param gridHeight its height in rows.
	 * @return the placement id.
	 */
	place: (content: Record<string, unknown>, gridY?: number, gridHeight?: number) => Promise<number>
}

/**
 * Register the hooks every rendering spec needs: an admin API context, an
 * optional real image in the admin's Files, and a fresh dashboard per test
 * (active for the admin, whose session the browser carries) that is deleted
 * after the test. Call once, at the top level of a spec file.
 *
 * @param options `withFile` uploads a 640x320 PNG and exposes its file id.
 * @return accessors for the seeded state.
 */
export function headerSuite(options: { withFile?: boolean } = {}): HeaderSuite {
	const run = Date.now()
	const fileName = `e2e-header-banner-${run}.png`
	let api: APIRequestContext | null = null
	let dash: SeededDashboard | null = null
	let fileId = 0
	let seeded = 0

	test.beforeAll(async () => {
		api = await adminApi()
		if (options.withFile === true) {
			// A real image in the admin's own Files: the preview route has
			// something genuine to scale, and only its owner can read it.
			fileId = await uploadAdminFile(api, fileName, solidPng(640, 320, '#2a7ab0'))
		}
	})

	test.afterAll(async () => {
		if (api === null) {
			return
		}
		if (options.withFile === true) {
			await removeAdminFile(api, fileName)
		}
		await api.dispose()
	})

	test.beforeEach(async () => {
		seeded++
		dash = await seedActiveDashboard(api!, `E2E Header ${run} #${seeded}`)
	})

	test.afterEach(async () => {
		await removeSeededDashboard(api!, dash)
		dash = null
	})

	return {
		api: () => api!,
		dashboard: () => dash!,
		fileId: () => fileId,
		place: (content, gridY = 0, gridHeight = 4) =>
			seedHeader(api!, dash!.id, content, { gridY, gridHeight }),
	}
}
