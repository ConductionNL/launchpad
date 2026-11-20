/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Seeding helpers for the dashboards specs that cover the tree, publication,
 * look and API-contract requirements (openspec/specs/dashboards/spec.md,
 * REQ-DASH-023 onwards).
 *
 * Every helper goes through LaunchPad's own HTTP API as a real principal (see
 * principals.ts for why a context is basic-auth only). Nothing here asserts a
 * scenario; it only builds the GIVEN.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'
import { API } from './principals.ts'

/** A dashboard as the API serialises it, with the fields these specs read. */
export interface DashboardRow {
	id: number
	uuid: string
	name: string
	description: string | null
	userId: string | null
	parentUuid: string | null
	slug: string | null
	sortOrder: number
	publicationStatus: string
	publishAt: string | null
	publishedAt: string | null
	unpublishAt: string | null
	themeAccent?: string | null
	themeBackgroundColor?: string | null
	customCss?: string | null
	source?: string
	[key: string]: unknown
}

/** One node of GET /api/dashboards/tree. */
export interface TreeNode {
	uuid: string
	name: string
	slug: string | null
	sortOrder: number
	children: TreeNode[]
}

/** The admin settings this family of specs depends on, as GET answers them. */
interface PersonalSettings {
	allowUserDash: boolean
	allowMultiDash: boolean
	maxDashboardsPerUser: number
}

/**
 * Read a boolean admin setting the way the service stores it.
 *
 * @param value the raw value.
 * @param fallback the value when absent.
 * @return the boolean.
 */
function truthy(value: unknown, fallback: boolean): boolean {
	if (value === undefined || value === null || value === '') {
		return fallback
	}
	return (
		value === true
		|| value === 1
		|| value === '1'
		|| value === 'true'
		|| value === 'yes'
	)
}

/**
 * Allow personal and multiple dashboards without a quota, and hand back a
 * function that puts the three settings back as they were.
 *
 * @param admin an admin context.
 * @return the restore function.
 */
export async function allowManyPersonalDashboards(
	admin: APIRequestContext,
): Promise<() => Promise<void>> {
	const before = await admin.get(`${API}/admin/settings`)
	expect(before.status(), await before.text()).toBe(200)
	const raw = await before.json()
	const previous: PersonalSettings = {
		allowUserDash: truthy(raw.allowUserDashboards, false),
		allowMultiDash: truthy(raw.allowMultipleDashboards, true),
		maxDashboardsPerUser: Number(raw.maxDashboardsPerUser ?? 0),
	}

	const set = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true, allowMultiDash: true, maxDashboardsPerUser: 0 },
	})
	expect(set.status(), await set.text()).toBe(200)

	return async () => {
		await admin.put(`${API}/admin/settings`, { data: previous })
	}
}

/**
 * POST /api/dashboard as the given principal.
 *
 * @param api the principal's context.
 * @param body the request body.
 * @return the status, the parsed body and the dashboard when one came back.
 */
export async function postDashboard(
	api: APIRequestContext,
	body: Record<string, unknown>,
): Promise<{
	status: number
	body: Record<string, unknown>
	dashboard: DashboardRow | null
}> {
	const res = await api.post(`${API}/dashboard`, { data: body })
	const parsed = (await res.json().catch(() => ({}))) as Record<string, unknown>
	const dashboard = (parsed.dashboard ?? null) as DashboardRow | null
	return { status: res.status(), body: parsed, dashboard }
}

/**
 * Create a dashboard and insist that it was created.
 *
 * @param api the principal's context.
 * @param body the request body.
 * @return the created dashboard.
 */
export async function createDashboard(
	api: APIRequestContext,
	body: Record<string, unknown>,
): Promise<DashboardRow> {
	const created = await postDashboard(api, body)
	expect(created.status, JSON.stringify(created.body)).toBe(201)
	expect(created.dashboard, JSON.stringify(created.body)).not.toBeNull()
	return created.dashboard as DashboardRow
}

/**
 * GET /api/dashboard/{id} as the given principal.
 *
 * @param api the principal's context.
 * @param id the dashboard id.
 * @return the status and the parsed body.
 */
export async function readDashboard(
	api: APIRequestContext,
	id: number,
): Promise<{
	status: number
	body: Record<string, unknown>
	dashboard: DashboardRow | null
}> {
	const res = await api.get(`${API}/dashboard/${id}`)
	const parsed = (await res.json().catch(() => ({}))) as Record<string, unknown>
	return {
		status: res.status(),
		body: parsed,
		dashboard: (parsed.dashboard ?? null) as DashboardRow | null,
	}
}

/**
 * PUT /api/dashboard/{id} as the given principal.
 *
 * @param api the principal's context.
 * @param id the dashboard id.
 * @param body the patch.
 * @return the status and the parsed body.
 */
export async function putDashboard(
	api: APIRequestContext,
	id: number,
	body: Record<string, unknown>,
): Promise<{ status: number; body: Record<string, unknown> }> {
	const res = await api.put(`${API}/dashboard/${id}`, { data: body })
	return {
		status: res.status(),
		body: (await res.json().catch(() => ({}))) as Record<string, unknown>,
	}
}

/**
 * The dashboards GET /api/dashboards/visible answers for a principal.
 *
 * @param api the principal's context.
 * @return the rows, whichever envelope the listing used.
 */
export async function visibleRows(api: APIRequestContext): Promise<DashboardRow[]> {
	const res = await api.get(`${API}/dashboards/visible`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (Array.isArray(body) ? body : body.items) as DashboardRow[]
}

/**
 * GET /api/dashboards/tree for a principal.
 *
 * @param api the principal's context.
 * @return the root nodes.
 */
export async function readTree(api: APIRequestContext): Promise<TreeNode[]> {
	const res = await api.get(`${API}/dashboards/tree`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (Array.isArray(body) ? body : body.children) as TreeNode[]
}

/**
 * GET /api/dashboards/by-path/{path} for a principal.
 *
 * @param api the principal's context.
 * @param path the slug chain, as it goes in the URL.
 * @return the status and the parsed body.
 */
export async function byPath(
	api: APIRequestContext,
	path: string,
): Promise<{
	status: number
	body: Record<string, unknown>
	dashboard: Record<string, unknown> | null
}> {
	const res = await api.get(`${API}/dashboards/by-path/${path}`)
	const parsed = (await res.json().catch(() => ({}))) as Record<string, unknown>
	return {
		status: res.status(),
		body: parsed,
		dashboard: (parsed.dashboard ?? null) as Record<string, unknown> | null,
	}
}

/**
 * Delete a dashboard and its subtree, tolerating one that is already gone.
 *
 * @param api the owner's context.
 * @param id the dashboard id, or null.
 * @return nothing.
 */
export async function removeTree(
	api: APIRequestContext,
	id: number | null | undefined,
): Promise<void> {
	if (id === null || id === undefined) {
		return
	}
	await api.delete(`${API}/dashboard/${id}?cascade=true`)
}

/**
 * POST one of the publication actions as a principal.
 *
 * @param api the principal's context.
 * @param uuid the dashboard.
 * @param action publish, unpublish or schedule.
 * @param body the schedule body, if any.
 * @return the status and the parsed body.
 */
export async function publication(
	api: APIRequestContext,
	uuid: string,
	action: 'publish' | 'unpublish' | 'schedule',
	body?: Record<string, unknown>,
): Promise<{
	status: number
	body: Record<string, unknown>
	dashboard: DashboardRow | null
}> {
	const res = await api.post(
		`${API}/dashboards/${uuid}/${action}`,
		body ? { data: body } : {},
	)
	const parsed = (await res.json().catch(() => ({}))) as Record<string, unknown>
	return {
		status: res.status(),
		body: parsed,
		dashboard: (parsed.dashboard ?? null) as DashboardRow | null,
	}
}

/** The server's storage format for timestamps. */
export const STAMP = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/

/**
 * Parse a stored `Y-m-d H:i:s` timestamp. Nextcloud runs PHP in UTC, so the
 * stored value is read as UTC.
 *
 * @param stamp the stored value.
 * @return milliseconds since the epoch.
 */
export function parseStamp(stamp: string): number {
	return Date.parse(`${stamp.replace(' ', 'T')}Z`)
}

/**
 * The offset between the server's clock and this machine's, measured by
 * publishing a scratch dashboard: its `publishedAt` is the server's now.
 *
 * Seconds-scale schedule tests use it so that "five seconds from now" means
 * five seconds on the server, whatever the two clocks say.
 *
 * @param api a principal's context that may create dashboards.
 * @param name a unique name for the scratch dashboard.
 * @return server time minus local time, in milliseconds.
 */
export async function serverClockOffset(
	api: APIRequestContext,
	name: string,
): Promise<number> {
	const scratch = await createDashboard(api, { name })
	try {
		const before = Date.now()
		const published = await publication(api, scratch.uuid, 'publish')
		const after = Date.now()
		expect(published.status, JSON.stringify(published.body)).toBe(200)
		const server = parseStamp(String(published.dashboard?.publishedAt))
		return server - Math.round((before + after) / 2)
	} finally {
		await removeTree(api, scratch.id)
	}
}
