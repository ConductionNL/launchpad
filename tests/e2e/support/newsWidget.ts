/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the news-widget e2e specs (openspec/specs/news-widget).
 *
 * WHAT THE CI FIXTURE CAN AND CANNOT PRODUCE
 * ==========================================
 * The widget's items come from `GET /api/widgets/news/{placementId}/items`,
 * which fetches the placement's feeds server-side. Every feed URL first passes
 * `UrlSafetyValidator::isSafe()`: HTTPS only, and the host must RESOLVE to a
 * PUBLIC address. A feed served from the test runner or a docker sibling is
 * therefore refused by design, and the fixture has no public feed it controls.
 *
 * So the fixture can produce exactly two kinds of real server response:
 *
 *  - no items and no failures (no feeds, or a metadata filter that does not
 *    match, which short-circuits BEFORE any fetch); and
 *  - failed feeds. A host under the reserved `.invalid` TLD (RFC 2606) never
 *    resolves, so the fetch is attempted and counted in `feedsFailed` /
 *    `failedUrls`. That count is how these specs OBSERVE whether the server
 *    tried to fetch at all.
 *
 * Scenarios about RENDERING a response (layouts, badge, dates, summaries,
 * links) stub the app's own items endpoint with `page.route` via
 * `stubNewsItems()`. That replaces only the JSON the renderer receives; the
 * renderer, its sanitiser, the grid and the browser are all real.
 */

import type { APIRequestContext, Locator, Page, Route } from '@playwright/test'

import { expect, request } from '@playwright/test'
import { BASE_URL } from './baseUrl.ts'
import { MORE_MENU } from './dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

/** The LaunchPad API root. */
export const API = '/index.php/apps/launchpad/api'

/** The renderer's items request, as the browser sends it. */
export const ITEMS_URL = /\/apps\/launchpad\/api\/widgets\/news\/(\d+)\/items/

/** One news item as the items endpoint returns it. */
export interface NewsItem {
	guid: string
	title: string
	summary: string
	link: string
	pubDate: string
	sourceUrl: string
	sourceTitle: string
	thumbnailUrl: string | null
}

/** The items endpoint's response body. */
export interface NewsPayload {
	items: NewsItem[]
	feedsFailed: number
	failedUrls: string[]
}

/** A seeded placement. */
export interface NewsPlacement {
	id: number
	dashboardId: number
}

/**
 * The admin over basic auth, with an explicitly empty jar so the project's
 * stored browser session is never sent along.
 *
 * @return the context; the caller disposes it.
 */
export async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
		timeout: 60_000,
	})
}

/**
 * A feed URL that is guaranteed to fail: `.invalid` never resolves.
 *
 * @param name a readable label that ends up in the host.
 * @return the URL.
 */
export function failingFeed(name: string): string {
	return `https://${name}.e2e-news.invalid/rss`
}

/**
 * Add a `news` placement to a dashboard through the API (seeding only).
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @param content the placement's news config.
 * @param grid optional grid position and size.
 * @return the placement.
 */
export async function seedNewsPlacement(
	api: APIRequestContext,
	dashboardId: number,
	content: Record<string, unknown>,
	grid: { x?: number; y?: number; w?: number; h?: number } = {},
): Promise<NewsPlacement> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'news',
			gridX: grid.x ?? 0,
			gridY: grid.y ?? 0,
			gridWidth: grid.w ?? 6,
			gridHeight: grid.h ?? 6,
			content,
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(body.id ?? body.data?.id ?? body.placement?.id)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return { id, dashboardId }
}

/**
 * Delete placements, tolerating ones already gone.
 *
 * @param api the admin context.
 * @param placements the placements.
 * @return nothing.
 */
export async function removePlacements(
	api: APIRequestContext,
	placements: NewsPlacement[],
): Promise<void> {
	for (const placement of placements.splice(0)) {
		await api.delete(`${API}/widgets/${placement.id}`)
	}
}

/**
 * Every placement on a dashboard, read back from the API.
 *
 * @param api the admin context.
 * @param dashboardId the dashboard.
 * @return the placements as the API serialises them.
 */
export async function readPlacements(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Array<Record<string, any>>> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.placements ?? body.dashboard?.placements ?? body.data?.placements ?? []
}

/**
 * One placement's stored content, read back from the API.
 *
 * @param api the admin context.
 * @param placement the placement.
 * @return its content object.
 */
export async function readContent(
	api: APIRequestContext,
	placement: NewsPlacement,
): Promise<Record<string, any>> {
	const all = await readPlacements(api, placement.dashboardId)
	const found = all.find((p) => Number(p.id) === placement.id)
	expect(found, `placement ${placement.id} not in ${JSON.stringify(all)}`).toBeTruthy()
	const content = found!.content
	return typeof content === 'string' ? JSON.parse(content) : (content ?? {})
}

/**
 * Ask the real items endpoint for a placement's items.
 *
 * @param api the admin context.
 * @param placement the placement.
 * @param limit the optional `?limit=`.
 * @return status and parsed body.
 */
export async function fetchItems(
	api: APIRequestContext,
	placement: NewsPlacement,
	limit?: number | string,
): Promise<{ status: number; body: any }> {
	const query = limit === undefined ? '' : `?limit=${limit}`
	const res = await api.get(`${API}/widgets/news/${placement.id}/items${query}`)
	const text = await res.text()
	let body: any = text
	try {
		body = JSON.parse(text)
	} catch {
		// Not JSON; the caller's status assertion will report it.
	}
	return { status: res.status(), body }
}

/**
 * Build a news item with sensible defaults.
 *
 * @param over the fields to set.
 * @return the item.
 */
export function newsItem(over: Partial<NewsItem> & { guid: string }): NewsItem {
	return {
		title: `Headline ${over.guid}`,
		summary: `Summary of ${over.guid}`,
		link: `https://news.e2e.test/${over.guid}`,
		pubDate: new Date(Date.now() - 3 * 3600_000).toISOString(),
		sourceUrl: 'https://news.e2e.test/rss',
		sourceTitle: 'E2E News',
		thumbnailUrl: null,
		...over,
	}
}

/**
 * Stub the app's own items endpoint for the given placements. Any other
 * placement's request goes to the real server.
 *
 * @param page the page.
 * @param payloads placement id → the response to hand the renderer.
 * @return nothing.
 */
export async function stubNewsItems(
	page: Page,
	payloads: Map<number, NewsPayload | ((route: Route) => Promise<void>)>,
): Promise<void> {
	await page.route(ITEMS_URL, async (route) => {
		const match = ITEMS_URL.exec(route.request().url())
		const payload = match ? payloads.get(Number(match[1])) : undefined
		if (payload === undefined) {
			await route.continue()
			return
		}
		if (typeof payload === 'function') {
			await payload(route)
			return
		}
		await route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify(payload),
		})
	})
}

/**
 * Open LaunchPad on the active dashboard and wait for the shell.
 *
 * @param page the page.
 * @return nothing.
 */
export async function gotoDashboard(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
}

/**
 * The rendered news widget of a placement.
 *
 * @param page the page.
 * @param placement the placement.
 * @return its `.news-widget` root.
 */
export function newsWidget(page: Page, placement: NewsPlacement): Locator {
	return page.locator(`[data-placement-id="${placement.id}"] .news-widget`)
}

/**
 * Open the active dashboard's "Add widget" → "Custom widget" modal from the More menu
 * and choose the News type.
 *
 * @param page the page, on the dashboard.
 * @return the dialog.
 */
export async function openAddNewsForm(page: Page): Promise<Locator> {
	const more = page.locator(MORE_MENU)
	await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
	await page.locator('[data-testid="cn-grouped-menu-item-add-widget"]').click()
	await page.locator('[data-testid="widget-picker-custom-widget"]').click()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await dialog.locator('[data-testid="widget-type-select"]').selectOption('news')
	await expect(dialog.locator('.news-widget-form')).toBeVisible()
	return dialog
}

/**
 * Open the content editor of an existing placement the way a person does:
 * edit mode, right-click the widget, Edit.
 *
 * @param page the page, on the dashboard.
 * @param placement the placement.
 * @return the editor dialog.
 */
export async function openNewsEditor(page: Page, placement: NewsPlacement): Promise<Locator> {
	if ((await page.locator('.launchpad-edit-mode').count()) === 0) {
		const more = page.locator(MORE_MENU)
		await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
		await page.locator('[data-testid="cn-grouped-menu-item-edit"]').click()
		await expect(page.locator('.launchpad-edit-mode')).toBeVisible({ timeout: 10_000 })
	}
	const cell = page.locator(`[data-placement-id="${placement.id}"]`)
	await expect(cell).toBeVisible({ timeout: 15_000 })
	await cell.locator('.news-widget').first().click({ button: 'right' })
	await expect(page.locator('[data-testid="widget-context-menu"]')).toBeVisible({ timeout: 5_000 })
	await page.locator('[data-testid="ctx-edit"]').click()
	const dialog = page.getByRole('dialog', { name: /(edit|add) widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await expect(dialog.locator('.news-widget-form')).toBeVisible()
	return dialog
}

/**
 * Pick an option of the n-th NcSelect in the news form (0 = Layout,
 * 1 = Date format).
 *
 * @param page the page.
 * @param dialog the dialog holding the form.
 * @param index which select.
 * @param label the option's visible label.
 * @return nothing.
 */
export async function chooseFormOption(
	page: Page,
	dialog: Locator,
	index: number,
	label: string,
): Promise<void> {
	const select = dialog.locator('.news-widget-form .v-select').nth(index)
	await select.locator('.vs__dropdown-toggle').click()
	await page.locator('.vs__dropdown-option').filter({ hasText: label }).first().click()
	await expect(select.locator('.vs__selected')).toContainText(label)
}

/**
 * The visible labels of the n-th NcSelect in the news form.
 *
 * @param page the page.
 * @param dialog the dialog holding the form.
 * @param index which select.
 * @return the option labels, in order.
 */
export async function formOptionLabels(
	page: Page,
	dialog: Locator,
	index: number,
): Promise<string[]> {
	const select = dialog.locator('.news-widget-form .v-select').nth(index)
	await select.locator('.vs__dropdown-toggle').click()
	const options = page.locator('.vs__dropdown-option')
	await expect(options.first()).toBeVisible({ timeout: 5_000 })
	const labels = (await options.allTextContents()).map((s) => s.trim())
	await page.keyboard.press('Escape')
	return labels
}

/**
 * Create a text metadata field (admin registry) for the metadata-filter
 * scenarios.
 *
 * @param api the admin context.
 * @param key the field key (lowercase, digits, underscores).
 * @return the field id, for cleanup.
 */
export async function createMetadataField(api: APIRequestContext, key: string): Promise<number> {
	const res = await api.post(`${API}/admin/metadata-fields`, {
		data: { key, label: `E2E ${key}`, type: 'text' },
	})
	expect(res.status(), await res.text()).toBe(201)
	return Number((await res.json()).id)
}

/**
 * Set metadata on a dashboard.
 *
 * @param api the admin context (the owner).
 * @param uuid the dashboard uuid.
 * @param metadata key → value.
 * @return nothing.
 */
export async function setDashboardMetadata(
	api: APIRequestContext,
	uuid: string,
	metadata: Record<string, string>,
): Promise<void> {
	const res = await api.put(`${API}/dashboards/${uuid}/metadata`, { data: { metadata } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Delete a metadata field and its values, tolerating one already gone.
 *
 * @param api the admin context.
 * @param id the field id, or null.
 * @return nothing.
 */
export async function removeMetadataField(api: APIRequestContext, id: number | null): Promise<void> {
	if (id === null || Number.isNaN(id)) {
		return
	}
	await api.delete(`${API}/admin/metadata-fields/${id}?cascade=true`)
}
