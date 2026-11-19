/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the `video` widget specs (openspec/specs/video-widget):
 * `video-widget.spec.ts` (hosted players, the form, aspect ratio, empty and
 * error states) and `video-widget-files.spec.ts` (Nextcloud Files sources).
 *
 * WHAT IS SEEDED AND HOW
 * ======================
 * A placement is seeded with `POST /api/dashboard/{id}/widgets`, the same
 * payload `support/principals.ts::seedDashboardWithWidget` sends, with
 * `widgetId: 'video'` and the scenario's `content`. Everything a scenario
 * asserts is then read from the rendered cell, found by the grid's own
 * `data-placement-id` attribute, or read back with `GET /api/dashboard/{id}`.
 *
 * Files for the `nc-file` source are uploaded over WebDAV as the admin, their
 * numeric id read back with PROPFIND, and deleted (and purged from the trash
 * bin) by the spec that uploaded them.
 *
 * NO EXTERNAL HOST IS REACHED
 * ===========================
 * The CI fixture has no outbound internet. `stubExternalPlayers()` answers
 * every request to the player hosts with an empty page, so the iframe's `src`
 * attribute, sandbox and geometry are asserted without YouTube or Vimeo ever
 * being contacted. Content-Security-Policy is enforced by the browser BEFORE
 * the request reaches the route handler, so a stubbed frame still reports a
 * CSP violation if the policy would have blocked it.
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { MORE_MENU } from './dashboardSwitcher.ts'

/** The LaunchPad API root. */
export const API = '/index.php/apps/launchpad/api'

/** The admin account, as every spec spells it. */
export const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? process.env.ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? process.env.ADMIN_PASSWORD ?? 'admin',
}

/** A placement's content blob, as stored and as read back. */
export type VideoContent = Record<string, unknown>

/** A placement as `GET /api/dashboard/{id}` returns it. */
export interface Placement {
	id: number
	widgetId: string
	content: VideoContent | null
	[key: string]: unknown
}

/** Hosts the specs must never reach; every request to them gets an empty page. */
const EXTERNAL_PLAYER =
	/^https?:\/\/([a-z0-9-]+\.)*(youtube\.com|youtube-nocookie\.com|youtu\.be|vimeo\.com|ytimg\.com|vimeocdn\.com|example\.com|example\.org)(:\d+)?\//i

/**
 * Answer every request to a video platform with an empty HTML page.
 *
 * @param page the page, before it navigates.
 * @return nothing.
 */
export async function stubExternalPlayers(page: Page): Promise<void> {
	await page.route(EXTERNAL_PLAYER, (route) =>
		route.fulfill({
			status: 200,
			contentType: 'text/html',
			body: '<!doctype html><title>stubbed player</title>',
		}))
}

/**
 * Seed one video placement on a dashboard.
 *
 * @param api a context acting as the dashboard's owner.
 * @param dashboardId the dashboard.
 * @param content the placement's `content` blob.
 * @param size grid width and height; a 6x4 cell by default.
 * @param size.w grid width in columns.
 * @param size.h grid height in rows.
 * @return the new placement's id.
 */
export async function seedVideo(
	api: APIRequestContext,
	dashboardId: number,
	content: VideoContent,
	size: { w: number, h: number } = { w: 6, h: 4 },
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'video',
			gridX: 0,
			gridY: 0,
			gridWidth: size.w,
			gridHeight: size.h,
			content,
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id ?? body.placement?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * Remove placements, tolerating ones that are already gone.
 *
 * @param api a context acting as the owner.
 * @param ids the placement ids.
 * @return nothing.
 */
export async function removePlacements(api: APIRequestContext, ids: number[]): Promise<void> {
	for (const id of ids.splice(0)) {
		await api.delete(`${API}/widgets/${id}`)
	}
}

/**
 * The placements on a dashboard, read back through the API.
 *
 * @param api a context acting as the owner.
 * @param dashboardId the dashboard.
 * @return the placements.
 */
export async function placementsOf(api: APIRequestContext, dashboardId: number): Promise<Placement[]> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (body.placements ?? body.dashboard?.placements ?? []) as Placement[]
}

/**
 * One placement's stored content, read back through the API.
 *
 * @param api a context acting as the owner.
 * @param dashboardId the dashboard.
 * @param placementId the placement.
 * @return its content blob.
 */
export async function storedContent(
	api: APIRequestContext,
	dashboardId: number,
	placementId: number,
): Promise<VideoContent> {
	const placement = (await placementsOf(api, dashboardId)).find((p) => Number(p.id) === placementId)
	expect(placement, `placement ${placementId} is not on dashboard ${dashboardId}`).toBeTruthy()
	return (placement!.content ?? {}) as VideoContent
}

/**
 * The grid cell of a placement.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the cell.
 */
export function cellOf(page: Page, placementId: number): Locator {
	return page.locator(`[data-placement-id="${placementId}"]`)
}

/**
 * The rendered video widget inside a placement's cell, once it is attached.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the `.cn-video-widget` root.
 */
export async function videoWidgetOf(page: Page, placementId: number): Promise<Locator> {
	const widget = cellOf(page, placementId).locator('.cn-video-widget')
	await expect(widget, `placement ${placementId} did not render a video widget`).toBeAttached({ timeout: 20_000 })
	return widget
}

/**
 * Open LaunchPad and wait for the workspace shell.
 *
 * @param page the page.
 * @return nothing.
 */
export async function openWorkspace(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
}

/**
 * Open the Add Widget modal on the ACTIVE dashboard through the More menu and
 * pick the "Video" type.
 *
 * @param page the page, on the workspace.
 * @return the dialog.
 */
export async function openVideoForm(page: Page): Promise<Locator> {
	await page.locator(MORE_MENU).locator('[data-testid="cn-grouped-menu-trigger"]').click()
	await page.locator('[data-testid="cn-grouped-menu-item-add-custom-widget"]').click()
	// Found by its type select rather than by its title, so the helper works
	// for a Dutch account too ("Widget toevoegen").
	const dialog = page.getByRole('dialog')
		.filter({ has: page.locator('[data-testid="widget-type-select"]') })
		.first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await dialog.locator('[data-testid="widget-type-select"]').selectOption({ label: 'Video' })
	await expect(dialog.locator('.cn-video-form')).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Press Add on an open video form and return the placement it created.
 *
 * @param page the page.
 * @param dialog the open Add Widget dialog.
 * @param api a context acting as the dashboard owner.
 * @param dashboardId the active dashboard.
 * @return the new placement, as read back.
 */
export async function submitVideoForm(
	page: Page,
	dialog: Locator,
	api: APIRequestContext,
	dashboardId: number,
): Promise<Placement> {
	const before = new Set((await placementsOf(api, dashboardId)).map((p) => Number(p.id)))
	const add = dialog.locator('[data-testid="add-widget-save"]')
	await expect(add).toBeEnabled({ timeout: 5_000 })
	await add.click()
	await expect(dialog).not.toBeVisible({ timeout: 10_000 })

	let created: Placement | undefined
	await expect.poll(async () => {
		created = (await placementsOf(api, dashboardId))
			.find((p) => p.widgetId === 'video' && !before.has(Number(p.id)))
		return created !== undefined
	}, { message: 'the Add button did not create a video placement on the active dashboard', timeout: 10_000 }).toBe(true)
	return created!
}

/**
 * Add a video widget through the modal with only a URL typed.
 *
 * @param page the page, on the workspace.
 * @param api a context acting as the owner.
 * @param dashboardId the active dashboard.
 * @param url what the person types into "Video URL".
 * @return the new placement.
 */
export async function addVideoByUrl(
	page: Page,
	api: APIRequestContext,
	dashboardId: number,
	url: string,
): Promise<Placement> {
	const dialog = await openVideoForm(page)
	await dialog.getByLabel(/^video url/i).fill(url)
	return submitVideoForm(page, dialog, api, dashboardId)
}

/**
 * One of the Video form's switches (Autoplay, Muted, Loop, Show controls).
 *
 * @param dialog the open dialog.
 * @param label the switch's visible label.
 * @return the switch (click it to toggle) and its input (to read its state).
 */
export function switchIn(dialog: Locator, label: string): { toggle: Locator, input: Locator } {
	const toggle = dialog
		.locator('.cn-video-form .checkbox-radio-switch')
		.filter({ hasText: new RegExp(`^\\s*${label}\\s*$`) })
		.first()
	return { toggle, input: toggle.locator('input').first() }
}

/**
 * Open a placement's edit-mode cog and choose "Edit widget". The dashboard
 * must already be in edit mode.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the Edit Widget dialog.
 */
export async function openEditDialog(page: Page, placementId: number): Promise<Locator> {
	await cellOf(page, placementId).locator('[data-testid="cn-widget-edit-cog"] button').first().click()
	await page.locator('[data-testid="cn-widget-edit-cog-edit"]').first().click()
	const dialog = page.getByRole('dialog', { name: /edit widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await expect(dialog.locator('.cn-video-form')).toBeVisible({ timeout: 10_000 })
	return dialog
}

/** Geometry of one element, as `getBoundingClientRect` reports it. */
export interface Box {
	x: number
	y: number
	width: number
	height: number
}

/**
 * The bounding box of an element (whether or not it is scrolled into view).
 *
 * @param locator the element.
 * @return its box.
 */
export async function boxOf(locator: Locator): Promise<Box> {
	return locator.evaluate((el) => {
		const r = el.getBoundingClientRect()
		return { x: r.x, y: r.y, width: r.width, height: r.height }
	})
}

// ── Nextcloud Files (WebDAV) ────────────────────────────────────────────

/**
 * Upload a file into the admin's home over WebDAV and return its file id.
 *
 * @param api a context acting as the admin.
 * @param name the file name (top level of the admin's files).
 * @param body the file's bytes.
 * @return its numeric file id and the MIME type Nextcloud gave it.
 */
export async function uploadFile(
	api: APIRequestContext,
	name: string,
	body: Buffer,
): Promise<{ fileId: number, mimeType: string }> {
	const url = `/remote.php/dav/files/${encodeURIComponent(ADMIN.user)}/${encodeURIComponent(name)}`
	const put = await api.put(url, { data: body, headers: { 'Content-Type': 'application/octet-stream' } })
	expect(put.status(), await put.text()).toBeLessThan(300)

	const found = await api.fetch(url, {
		method: 'PROPFIND',
		headers: { Depth: '0', 'Content-Type': 'application/xml' },
		data: '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">'
			+ '<d:prop><oc:fileid/><d:getcontenttype/></d:prop></d:propfind>',
	})
	expect(found.status(), await found.text()).toBe(207)
	const xml = await found.text()
	const fileId = Number(/<oc:fileid>(\d+)<\/oc:fileid>/.exec(xml)?.[1])
	const mimeType = /<d:getcontenttype>([^<]+)<\/d:getcontenttype>/.exec(xml)?.[1] ?? ''
	expect(fileId, `no file id for ${name} in ${xml}`).toBeGreaterThan(0)
	return { fileId, mimeType }
}

/**
 * Delete a file from the admin's home (it moves to the trash bin).
 *
 * @param api a context acting as the admin.
 * @param name the file name.
 * @return nothing.
 */
export async function deleteFile(api: APIRequestContext, name: string): Promise<void> {
	await api.delete(`/remote.php/dav/files/${encodeURIComponent(ADMIN.user)}/${encodeURIComponent(name)}`)
}

/**
 * Permanently remove the admin's trash-bin entries whose name contains a stamp.
 *
 * @param api a context acting as the admin.
 * @param stamp the run stamp every uploaded file name carries.
 * @return nothing.
 */
export async function purgeTrash(api: APIRequestContext, stamp: string): Promise<void> {
	const root = `/remote.php/dav/trashbin/${encodeURIComponent(ADMIN.user)}/trash`
	const listed = await api.fetch(root, { method: 'PROPFIND', headers: { Depth: '1' } })
	if (listed.status() !== 207) {
		return
	}
	const hrefs = [...(await listed.text()).matchAll(/<d:href>([^<]+)<\/d:href>/g)].map((m) => m[1])
	for (const href of hrefs) {
		if (decodeURIComponent(href).includes(stamp)) {
			await api.delete(href)
		}
	}
}

/**
 * Share one of the admin's files with a user, read-only.
 *
 * @param api a context acting as the admin.
 * @param name the file name.
 * @param username the recipient.
 * @return the share id.
 */
export async function shareFileWith(api: APIRequestContext, name: string, username: string): Promise<number> {
	const res = await api.post('/ocs/v2.php/apps/files_sharing/api/v1/shares?format=json', {
		form: { path: `/${name}`, shareType: '0', shareWith: username, permissions: '1' },
	})
	expect(res.status(), await res.text()).toBe(200)
	const id = Number((await res.json()).ocs?.data?.id)
	expect(id).toBeGreaterThan(0)
	return id
}

/**
 * Revoke a file share.
 *
 * @param api a context acting as the admin.
 * @param shareId the share.
 * @return nothing.
 */
export async function unshareFile(api: APIRequestContext, shareId: number): Promise<void> {
	const res = await api.delete(`/ocs/v2.php/apps/files_sharing/api/v1/shares/${shareId}?format=json`)
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * The content blob the Video form writes for a Nextcloud file, with the
 * scenario's overrides.
 *
 * @param fileId the file id.
 * @param overrides fields that differ from the defaults.
 * @return the content blob.
 */
export function ncFileContent(fileId: number, overrides: VideoContent = {}): VideoContent {
	return {
		sourceType: 'nc-file',
		videoUrl: `/index.php/apps/files/?fileid=${fileId}`,
		fileId,
		autoplay: false,
		muted: true,
		loop: false,
		controls: true,
		aspectRatio: '16:9',
		posterUrl: '',
		...overrides,
	}
}
