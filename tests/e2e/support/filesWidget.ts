/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Seeding and reading helpers for the files-widget specs
 * (openspec/specs/files-widget).
 *
 * The files widget reads the VIEWER's Nextcloud files live, so every test
 * needs real files. They are written over WebDAV
 * (`/remote.php/dav/files/<user>/...`), the same way a desktop client would,
 * and a placement pointing at them is created through
 * `POST /api/dashboard/{id}/widgets` with `widgetId: 'files'` — the payload
 * `support/principals.ts::seedDashboardWithWidget` uses. Everything here only
 * seeds, reads back or tears down; what a test asserts is what the widget
 * renders and what the widget's own requests answer.
 *
 * Every request context is basic-auth with an EMPTY cookie jar. The project's
 * stored admin session would otherwise ride along and make a "reader" request
 * as the admin — see the warning in support/principals.ts.
 */

import type { APIRequestContext, Locator, Page, Response } from '@playwright/test'

import { expect, request } from '@playwright/test'
import { BASE_URL } from './baseUrl.ts'

export const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

export const API = '/index.php/apps/launchpad/api'
const SHARES = '/ocs/v2.php/apps/files_sharing/api/v1/shares'

/** Nextcloud share permission bits. */
export const PERM = { READ: 1, UPDATE: 2, CREATE: 4, DELETE: 8, ALL_FOLDER: 15 }

export interface Account {
	username: string
	password: string
}

/**
 * A basic-auth context acting as one account and nobody else.
 *
 * @param who the account; the administrator when omitted.
 * @return the context; the caller disposes it.
 */
export async function apiAs(who?: Account): Promise<APIRequestContext> {
	const user = who?.username ?? ADMIN.user
	const pass = who?.password ?? ADMIN.pass
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`,
		},
		timeout: 60_000,
	})
}

/**
 * The WebDAV URL of a path in a user's home.
 *
 * @param user the owner of the home.
 * @param path a home-relative path, with or without a leading slash.
 * @return the URL path.
 */
export function davUrl(user: string, path: string): string {
	const segments = path.split('/').filter((s) => s !== '').map(encodeURIComponent)
	return `/remote.php/dav/files/${encodeURIComponent(user)}/${segments.join('/')}`
}

/**
 * Create a folder (and its parents) in a user's home.
 *
 * @param api a context acting as the owner.
 * @param user the owner.
 * @param path the folder path.
 * @return nothing.
 */
export async function mkdirs(api: APIRequestContext, user: string, path: string): Promise<void> {
	const parts = path.split('/').filter((s) => s !== '')
	for (let i = 1; i <= parts.length; i++) {
		const res = await api.fetch(davUrl(user, parts.slice(0, i).join('/')), { method: 'MKCOL' })
		// 405 = already exists, which is fine for a parent.
		expect([201, 405], `MKCOL ${parts.slice(0, i).join('/')}`).toContain(res.status())
	}
}

/**
 * Write a file into a user's home.
 *
 * @param api a context acting as the owner.
 * @param user the owner.
 * @param path the file path.
 * @param body the content.
 * @param mtime optional modification time, in seconds since the epoch.
 * @return nothing.
 */
export async function putFile(
	api: APIRequestContext,
	user: string,
	path: string,
	body: string | Buffer = 'e2e',
	mtime?: number,
): Promise<void> {
	const headers: Record<string, string> = { 'Content-Type': 'application/octet-stream' }
	if (mtime !== undefined) {
		headers['X-OC-Mtime'] = String(mtime)
	}
	const res = await api.put(davUrl(user, path), { data: body, headers })
	expect([201, 204], `PUT ${path}: ${await res.text()}`).toContain(res.status())
}

/**
 * Whether a path exists in a user's home.
 *
 * @param api a context acting as the owner.
 * @param user the owner.
 * @param path the path.
 * @return true when PROPFIND finds it.
 */
export async function davExists(api: APIRequestContext, user: string, path: string): Promise<boolean> {
	const res = await api.fetch(davUrl(user, path), { method: 'PROPFIND', headers: { Depth: '0' } })
	return res.status() === 207
}

/**
 * The Nextcloud file id of a path.
 *
 * @param api a context acting as the owner.
 * @param user the owner.
 * @param path the path.
 * @return the file id.
 */
export async function fileIdOf(api: APIRequestContext, user: string, path: string): Promise<number> {
	const res = await api.fetch(davUrl(user, path), {
		method: 'PROPFIND',
		headers: { Depth: '0', 'Content-Type': 'application/xml' },
		data: '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">'
			+ '<d:prop><oc:fileid/></d:prop></d:propfind>',
	})
	expect(res.status(), `PROPFIND ${path}`).toBe(207)
	const match = (await res.text()).match(/<oc:fileid>(\d+)<\/oc:fileid>/)
	expect(match, `no fileid for ${path}`).not.toBeNull()
	return Number(match![1])
}

/**
 * The bytes of a file in a user's home.
 *
 * @param api a context acting as the owner.
 * @param user the owner.
 * @param path the path.
 * @return the content.
 */
export async function readFile(api: APIRequestContext, user: string, path: string): Promise<Buffer> {
	const res = await api.get(davUrl(user, path))
	expect(res.status(), `GET ${path}`).toBe(200)
	return res.body()
}

/**
 * Delete a path from a user's home, tolerating one that is already gone.
 *
 * @param api a context acting as the owner.
 * @param user the owner.
 * @param path the path.
 * @return nothing.
 */
export async function davRemove(api: APIRequestContext, user: string, path: string): Promise<void> {
	await api.fetch(davUrl(user, path), { method: 'DELETE' })
}

/**
 * The trash entries of a user whose original name is `name`, as hrefs.
 *
 * @param api a context acting as the owner.
 * @param user the owner.
 * @param name the original file name.
 * @return the WebDAV hrefs of the matching trash entries.
 */
export async function trashEntries(api: APIRequestContext, user: string, name: string): Promise<string[]> {
	const res = await api.fetch(`/remote.php/dav/trashbin/${encodeURIComponent(user)}/trash`, {
		method: 'PROPFIND',
		headers: { Depth: '1', 'Content-Type': 'application/xml' },
		data: '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:nc="http://nextcloud.org/ns">'
			+ '<d:prop><nc:trashbin-filename/></d:prop></d:propfind>',
	})
	expect(res.status(), 'PROPFIND trashbin').toBe(207)
	const xml = await res.text()
	const hrefs: string[] = []
	for (const block of xml.split(/<d:response>/).slice(1)) {
		const href = block.match(/<d:href>([^<]+)<\/d:href>/)?.[1]
		const original = block.match(/<nc:trashbin-filename>([^<]*)<\/nc:trashbin-filename>/)?.[1]
		if (href && original === name) {
			hrefs.push(href)
		}
	}
	return hrefs
}

/**
 * Create a placement of the files widget on a dashboard.
 *
 * @param api a context acting as the dashboard's owner.
 * @param dashboardId the dashboard.
 * @param content the widget content (folderPath, viewMode, …).
 * @param gridY the row to place it on; below the default bundle by default.
 * @return the placement id.
 */
export async function seedFilesPlacement(
	api: APIRequestContext,
	dashboardId: number,
	content: Record<string, unknown>,
	gridY = 12,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: { widgetId: 'files', gridX: 0, gridY, gridWidth: 12, gridHeight: 8, content },
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
 * @param api a context acting as the dashboard's owner.
 * @param ids the placement ids.
 * @return nothing.
 */
export async function removePlacements(api: APIRequestContext, ids: number[]): Promise<void> {
	for (const id of ids.splice(0)) {
		await api.delete(`${API}/widgets/${id}`)
	}
}

/**
 * The placements of a dashboard, read back from the server.
 *
 * @param api a context that may read the dashboard.
 * @param dashboardId the dashboard.
 * @return the placements.
 */
export async function placementsOf(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Array<Record<string, any>>> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.placements ?? body.data?.placements ?? []
}

/**
 * Make a dashboard the one LaunchPad opens for the calling account. Both the
 * legacy `is_active` column and the per-user preference are set; see
 * support/dashboardFixture.ts for why one is not enough.
 *
 * @param api a context acting as the account.
 * @param dashboard the dashboard's id and uuid.
 * @param dashboard.id the dashboard id.
 * @param dashboard.uuid the dashboard uuid.
 * @return nothing.
 */
export async function activate(api: APIRequestContext, dashboard: { id: number, uuid: string }): Promise<void> {
	const legacy = await api.post(`${API}/dashboard/${dashboard.id}/activate`)
	expect(legacy.status(), await legacy.text()).toBeLessThan(300)
	const active = await api.post(`${API}/dashboards/active`, { data: { uuid: dashboard.uuid } })
	expect(active.status(), await active.text()).toBeLessThan(300)
}

/**
 * Create a personal dashboard as an account and make it the one LaunchPad opens.
 *
 * @param api a context acting as the account.
 * @param name the dashboard name.
 * @return the dashboard's id and uuid.
 */
export async function seedOwnActiveDashboard(
	api: APIRequestContext,
	name: string,
): Promise<{ id: number, uuid: string }> {
	const created = await api.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const seeded = { id: Number(dash.id), uuid: String(dash.uuid) }
	await activate(api, seeded)
	return seeded
}

/**
 * Share a path of the admin's home with a user.
 *
 * @param api an admin context.
 * @param path the admin-home path.
 * @param shareWith the recipient.
 * @param permissions the share permission bits.
 * @return the share id.
 */
export async function shareWithUser(
	api: APIRequestContext,
	path: string,
	shareWith: string,
	permissions: number,
): Promise<number> {
	const res = await api.post(`${SHARES}?format=json`, {
		form: { path, shareType: '0', shareWith, permissions: String(permissions) },
	})
	expect(res.status(), await res.text()).toBe(200)
	return Number((await res.json()).ocs.data.id)
}

/**
 * Change the permissions of a share.
 *
 * @param api an admin context.
 * @param shareId the share.
 * @param permissions the new permission bits.
 * @return nothing.
 */
export async function setSharePermissions(
	api: APIRequestContext,
	shareId: number,
	permissions: number,
): Promise<void> {
	const res = await api.put(`${SHARES}/${shareId}?format=json`, {
		form: { permissions: String(permissions) },
	})
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Delete a share, tolerating one that is already gone.
 *
 * @param api an admin context.
 * @param shareId the share.
 * @return nothing.
 */
export async function unshare(api: APIRequestContext, shareId: number): Promise<void> {
	await api.delete(`${SHARES}/${shareId}?format=json`)
}

/**
 * The files widget of one placement.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the widget's root element.
 */
export function filesWidget(page: Page, placementId: number): Locator {
	return page.locator(`[data-placement-id="${placementId}"] .cn-files-widget`)
}

/**
 * The visible item names of a files widget, in rendered order.
 *
 * @param widget the widget.
 * @return the names.
 */
export async function rowNames(widget: Locator): Promise<string[]> {
	return (await widget.locator('.cn-files-widget__row-label').allTextContents()).map((s) => s.trim())
}

/**
 * The breadcrumb segments of a files widget, as rendered text.
 *
 * @param widget the widget.
 * @return the crumb labels, root first.
 */
export async function crumbs(widget: Locator): Promise<string[]> {
	return (await widget.locator('.cn-files-widget__crumb').allTextContents()).map((s) => s.trim())
}

/**
 * Wait for the next contents request of a placement.
 *
 * @param page the page.
 * @param placementId the placement.
 * @return the response.
 */
export function nextContents(page: Page, placementId: number): Promise<Response> {
	return page.waitForResponse(
		(r) => r.url().includes(`/api/widgets/files/${placementId}/contents`)
			&& r.request().method() === 'GET',
		{ timeout: 30_000 },
	)
}

/**
 * The current-path parameter a contents request was made for.
 *
 * @param response the contents response.
 * @return the `currentPath` query value.
 */
export function requestedPath(response: Response): string | null {
	return new URL(response.url()).searchParams.get('currentPath')
}

/**
 * Open LaunchPad on the active dashboard and wait for a placement's first
 * contents response.
 *
 * @param page the page, logged in.
 * @param placementId the placement to wait for.
 * @return the first contents response.
 */
export async function openOnWidget(page: Page, placementId: number): Promise<Response> {
	const first = nextContents(page, placementId)
	await page.goto('/index.php/apps/launchpad')
	const response = await first
	await expect(filesWidget(page, placementId)).toBeVisible({ timeout: 30_000 })
	await expect(filesWidget(page, placementId).getByText('Loading folder…')).toHaveCount(0)
	return response
}

/**
 * Open the Add Widget dialog for the active dashboard through the More menu,
 * the way a person does, and choose the Files type.
 *
 * @param page the page, logged in as someone who may edit the dashboard.
 * @return the dialog.
 */
export async function openAddFilesWidget(page: Page): Promise<Locator> {
	const dialog = await openAddWidgetDialog(page)
	await dialog.getByTestId('widget-type-select').selectOption('files')
	await expect(dialog.getByRole('button', { name: /^browse/i })).toBeVisible()
	return dialog
}

/**
 * Open the Add Widget dialog for the active dashboard through the More menu.
 *
 * @param page the page, logged in as someone who may edit the dashboard.
 * @return the dialog.
 */
export async function openAddWidgetDialog(page: Page): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad')
	const more = page.locator('[data-testid="launchpad-more-menu"]')
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
	await page.locator('[data-testid="cn-grouped-menu-item-add-widget"]').click()
	await page.locator('[data-testid="widget-picker-custom-widget"]').click()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Pick an option of one of the dialog's NcSelect controls.
 *
 * @param page the page.
 * @param dialog the Add Widget dialog.
 * @param label the select's label.
 * @param option the option's label.
 * @return nothing.
 */
export async function chooseSelectOption(
	page: Page,
	dialog: Locator,
	label: string,
	option: string,
): Promise<void> {
	await dialog.getByLabel(label, { exact: true }).click()
	await page.getByRole('option', { name: option, exact: true }).click()
}

/**
 * Press Add, wait for the dialog to close, and read back the files placement
 * it created.
 *
 * @param page the page.
 * @param dialog the Add Widget dialog.
 * @param api a context that may read the dashboard.
 * @param dashboardId the dashboard the dialog adds to.
 * @return the new placement, as the server stores it.
 */
export async function addAndReadBack(
	page: Page,
	dialog: Locator,
	api: APIRequestContext,
	dashboardId: number,
): Promise<Record<string, any>> {
	const before = new Set((await placementsOf(api, dashboardId)).map((p) => Number(p.id)))
	const add = dialog.getByRole('button', { name: /^add$/i })
	await expect(add).toBeEnabled({ timeout: 10_000 })
	await add.click()
	await expect(dialog).toBeHidden({ timeout: 15_000 })
	let created: Record<string, any> | undefined
	await expect.poll(async () => {
		created = (await placementsOf(api, dashboardId)).find((p) => !before.has(Number(p.id)) && p.widgetId === 'files')
		return created !== undefined
	}, { timeout: 15_000 }).toBe(true)
	return created!
}

/**
 * Delete a path and purge it from the owner's trash, so nothing is left behind.
 *
 * @param api a context acting as the owner.
 * @param user the owner.
 * @param path the path.
 * @return nothing.
 */
export async function purge(api: APIRequestContext, user: string, path: string): Promise<void> {
	await davRemove(api, user, path)
	const name = path.split('/').filter((s) => s !== '').pop() ?? ''
	for (const href of await trashEntries(api, user, name)) {
		await api.fetch(new URL(href, BASE_URL).pathname, { method: 'DELETE' })
	}
}
