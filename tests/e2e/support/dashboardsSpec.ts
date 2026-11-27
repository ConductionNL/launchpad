/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared seeding and reading helpers for the `dashboards-*.spec.ts` files
 * (openspec/specs/dashboards). Nothing here asserts a scenario: every helper
 * either seeds state through the product's own API or reads back what the
 * product answered, so the specs can keep their assertions in the test body.
 *
 * WHY THE WORKSPACE PAGE IS READ OVER HTTP. REQ-DASH-018 is about what the
 * page RESOLVES when it renders, and PageController hands that to the
 * frontend as initial state (`activeDashboardId`, `dashboardSource`,
 * `layout`). Reading the served HTML, rather than a running SPA, keeps the
 * frontend from writing its own `active_dashboard_uuid` preference on boot,
 * which would otherwise make "the preference was cleared" indistinguishable
 * from "the page overwrote it".
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'
import { API } from './principals.ts'

/** The LaunchPad page root. */
export const APP = '/index.php/apps/launchpad'

/** A dashboard as the API serialises it. */
export type DashboardRow = Record<string, unknown> & {
	id: number
	uuid: string
	name: string
}

/** A widget placement as the API serialises it. */
export type PlacementRow = Record<string, unknown> & { id: number }

/** The settings the dashboards specs depend on, as the admin API names them. */
export interface DashboardSettings {
	allowUserDash: boolean
	allowMultiDash: boolean
	maxDashboardsPerUser?: number
}

/**
 * The rows of a listing endpoint. `GET /api/dashboards` and
 * `GET /api/dashboards/visible` answer `{items, quota}` since
 * dashboard-quota-limits REQ-QUOTA-006, and a bare array without the quota
 * service; both shapes carry the same rows.
 *
 * @param body the decoded response body.
 * @return the rows.
 */
export function rowsOf(body: unknown): DashboardRow[] {
	if (Array.isArray(body)) {
		return body as DashboardRow[]
	}
	const items = (body as { items?: unknown } | null)?.items
	expect(
		Array.isArray(items),
		`no dashboard rows in ${JSON.stringify(body)}`,
	).toBe(true)
	return items as DashboardRow[]
}

/**
 * Read the current values of the settings these specs flip.
 *
 * @param admin an admin context.
 * @return the values, in the names `PUT /api/admin/settings` takes.
 */
export async function readSettings(
	admin: APIRequestContext,
): Promise<DashboardSettings> {
	const res = await admin.get(`${API}/admin/settings`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const settings: DashboardSettings = {
		allowUserDash: body.allowUserDashboards === true,
		allowMultiDash: body.allowMultipleDashboards !== false,
	}
	if (typeof body.maxDashboardsPerUser === 'number') {
		settings.maxDashboardsPerUser = body.maxDashboardsPerUser
	}
	return settings
}

/**
 * Write settings.
 *
 * @param admin an admin context.
 * @param values the values to write.
 * @return nothing.
 */
export async function writeSettings(
	admin: APIRequestContext,
	values: Partial<DashboardSettings>,
): Promise<void> {
	const res = await admin.put(`${API}/admin/settings`, { data: values })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Personal dashboards on, several per user, no per-user quota: the state the
 * create / fork scenarios assume. Returns what was there, for `writeSettings`
 * in `afterAll`.
 *
 * @param admin an admin context.
 * @return the settings as they were.
 */
export async function allowPersonalDashboardsFreely(
	admin: APIRequestContext,
): Promise<DashboardSettings> {
	const before = await readSettings(admin)
	const wanted: Partial<DashboardSettings> = {
		allowUserDash: true,
		allowMultiDash: true,
	}
	if (before.maxDashboardsPerUser !== undefined) {
		wanted.maxDashboardsPerUser = 0
	}
	await writeSettings(admin, wanted)
	return before
}

/**
 * Create a personal dashboard as the given principal.
 *
 * @param api the principal's context.
 * @param data the create body.
 * @return the created dashboard and its placements.
 */
export async function createPersonal(
	api: APIRequestContext,
	data: Record<string, unknown>,
): Promise<{ dashboard: DashboardRow; placements: PlacementRow[] }> {
	const res = await api.post(`${API}/dashboard`, { data })
	expect(res.status(), await res.text()).toBe(201)
	return res.json()
}

/**
 * The caller's personal dashboards (`GET /api/dashboards`).
 *
 * @param api the caller's context.
 * @return the rows.
 */
export async function listPersonal(api: APIRequestContext): Promise<DashboardRow[]> {
	const res = await api.get(`${API}/dashboards`)
	expect(res.status(), await res.text()).toBe(200)
	return rowsOf(await res.json())
}

/**
 * The dashboards visible to the caller (`GET /api/dashboards/visible`).
 *
 * @param api the caller's context.
 * @return the rows, each with its `source`.
 */
export async function listVisible(api: APIRequestContext): Promise<DashboardRow[]> {
	const res = await api.get(`${API}/dashboards/visible`)
	expect(res.status(), await res.text()).toBe(200)
	return rowsOf(await res.json())
}

/**
 * One dashboard with its placements, as the caller may read it
 * (`GET /api/dashboard/{id}`).
 *
 * @param api the caller's context.
 * @param id the dashboard id.
 * @return the envelope.
 */
export async function showDashboard(
	api: APIRequestContext,
	id: number,
): Promise<{
	dashboard: DashboardRow
	placements: PlacementRow[]
	permissionLevel: string
}> {
	const res = await api.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Add a text widget to a dashboard.
 *
 * @param api a context that may edit the dashboard.
 * @param dashboardId the dashboard.
 * @param text the widget text.
 * @param grid where it goes.
 * @param grid.gridX column.
 * @param grid.gridY row.
 * @return the placement.
 */
export async function addTextWidget(
	api: APIRequestContext,
	dashboardId: number,
	text: string,
	grid: { gridX: number; gridY: number },
): Promise<PlacementRow> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: grid.gridX,
			gridY: grid.gridY,
			gridWidth: 4,
			gridHeight: 2,
			content: { text, contentMode: 'markdown' },
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	return (body.placement ?? body.data ?? body) as PlacementRow
}

/**
 * Create a group-shared dashboard as the admin, published by default:
 * every new dashboard starts as a draft (REQ-DASH-031) and a draft is hidden
 * from members who are not administrators (REQ-DASH-036).
 *
 * @param admin an admin context.
 * @param groupId the group.
 * @param name the name.
 * @param publish whether to publish it.
 * @return the dashboard as the create endpoint answered it.
 */
export async function createGroupDashboard(
	admin: APIRequestContext,
	groupId: string,
	name: string,
	publish = true,
): Promise<DashboardRow> {
	const res = await admin.post(
		`${API}/dashboards/group/${encodeURIComponent(groupId)}`,
		{
			data: { name },
		},
	)
	expect(res.status(), await res.text()).toBe(201)
	const dashboard = (await res.json()).dashboard as DashboardRow
	if (publish) {
		const published = await admin.post(
			`${API}/dashboards/${dashboard.uuid}/publish`,
		)
		expect(published.status(), await published.text()).toBe(200)
	}
	return dashboard
}

/**
 * The group-shared dashboards of a group, as the admin lists them.
 *
 * @param admin an admin context.
 * @param groupId the group.
 * @return the rows.
 */
export async function listGroup(
	admin: APIRequestContext,
	groupId: string,
): Promise<DashboardRow[]> {
	const res = await admin.get(
		`${API}/dashboards/group/${encodeURIComponent(groupId)}`,
	)
	expect(res.status(), await res.text()).toBe(200)
	return rowsOf(await res.json())
}

/** One group-shared dashboard a spec created, for cleanup. */
export interface CreatedGroupDashboard {
	groupId: string
	uuid: string
}

/**
 * Remove the group-shared dashboards a spec created, and nothing else: the
 * `default` group holds the instance's own dashboards too. The last dashboard
 * of a group other than `default` cannot be deleted (REQ-DASH-014) and there
 * is no other way to remove it, so one row per such group stays behind, in a
 * group nobody belongs to.
 *
 * @param admin an admin context.
 * @param created the dashboards to remove.
 * @return nothing.
 */
export async function removeGroupDashboards(
	admin: APIRequestContext,
	created: CreatedGroupDashboard[],
): Promise<void> {
	for (const { groupId, uuid } of created) {
		await admin.delete(
			`${API}/dashboards/group/${encodeURIComponent(groupId)}/${uuid}`,
		)
	}
}

/**
 * Take a user out of a group.
 *
 * @param admin an admin context.
 * @param username the user.
 * @param groupId the group.
 * @return nothing.
 */
export async function removeFromGroup(
	admin: APIRequestContext,
	username: string,
	groupId: string,
): Promise<void> {
	const res = await admin.delete(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}/groups`,
		{ form: { groupid: groupId } },
	)
	expect(
		res.ok(),
		`could not remove ${username} from ${groupId}: ${res.status()}`,
	).toBe(true)
}

/**
 * Persist the caller's active-dashboard preference (REQ-DASH-019).
 *
 * @param api the caller's context.
 * @param uuid the dashboard uuid, or '' to clear.
 * @return nothing.
 */
export async function setActivePreference(
	api: APIRequestContext,
	uuid: string,
): Promise<void> {
	const res = await api.post(`${API}/dashboards/active`, { data: { uuid } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Choose the caller's start dashboard (Set as default, `default_dashboard_uuid`):
 * the dashboard `/apps/launchpad/` opens on (REQ-DASH-018 step 1). The
 * last-used preference ({@link setActivePreference}) no longer decides that.
 *
 * @param api the caller's context.
 * @param uuid the dashboard uuid, or '' to clear.
 * @return nothing.
 * @spec openspec/changes/archive/2026-11-27-start-page-stays-vandaag/specs/dashboards/spec.md
 */
export async function setStartDashboard(
	api: APIRequestContext,
	uuid: string,
): Promise<void> {
	const res = await api.post(`${API}/dashboards/default`, { data: { uuid } })
	expect(res.status(), await res.text()).toBeLessThan(300)
}

/**
 * Open the workspace page as the caller and decode its initial state.
 *
 * @param api the caller's context.
 * @return the page's HTTP status and its `launchpad` initial-state values by key.
 */
export async function openWorkspace(
	api: APIRequestContext,
): Promise<{ status: number; state: Record<string, unknown> }> {
	const res = await api.get(`${APP}/`, { headers: { Accept: 'text/html' } })
	const html = await res.text()
	const state: Record<string, unknown> = {}
	for (const match of html.matchAll(
		/<input[^>]*id="initial-state-launchpad-([^"]+)"[^>]*value="([^"]*)"/g,
	)) {
		state[match[1]] = JSON.parse(
			Buffer.from(match[2], 'base64').toString('utf8'),
		)
	}
	return { status: res.status(), state }
}

/**
 * Make sure the `default` group has a published default dashboard visible to
 * everyone, and say which one it is. An instance normally has one already
 * (SeedDefaultDashboard); when it does not, one is created and promoted, and
 * `restore` puts the group back as it was.
 *
 * @param admin an admin context.
 * @param name the name for a dashboard created here.
 * @return its uuid and the cleanup.
 */
export async function ensureDefaultGroupDefault(
	admin: APIRequestContext,
	name: string,
): Promise<{ uuid: string; restore: () => Promise<void> }> {
	const rows = await listGroup(admin, 'default')
	const usable = rows.find(
		(row) =>
			row.isDefault === 1
			&& ['published', ''].includes(
				String(row.publicationStatus ?? 'published'),
			),
	)
	if (usable !== undefined) {
		return { uuid: usable.uuid, restore: async () => {} }
	}

	const previous = rows.find((row) => row.isDefault === 1)
	const created = await createGroupDashboard(admin, 'default', name)
	const promoted = await admin.post(`${API}/dashboards/group/default/default`, {
		data: { uuid: created.uuid },
	})
	expect(promoted.status(), await promoted.text()).toBe(200)

	return {
		uuid: created.uuid,
		restore: async () => {
			if (previous !== undefined) {
				await admin.post(`${API}/dashboards/group/default/default`, {
					data: { uuid: previous.uuid },
				})
			}
			await admin.delete(`${API}/dashboards/group/default/${created.uuid}`)
		},
	}
}

/**
 * Fail unless no admin template is marked default: a default template with no
 * target groups is copied for a user who owns nothing and whose primary group
 * has no default dashboard (REQ-TMPL-019), which would answer a resolution
 * scenario before the rung it is about.
 *
 * @param admin an admin context.
 * @return nothing.
 */
export async function expectNoDefaultTemplate(
	admin: APIRequestContext,
): Promise<void> {
	const res = await admin.get(`${API}/admin/templates`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const templates = (
		Array.isArray(body) ? body : (body.templates ?? body.items ?? [])
	) as DashboardRow[]
	expect(
		templates
			.filter((template) => template.isDefault === 1)
			.map((template) => template.name),
		'a default admin template on this instance would be copied before this rung is reached',
	).toEqual([])
}

/**
 * The admin-configured group priority list (REQ-ASET-012), which decides a
 * user's primary group.
 *
 * @param admin an admin context.
 * @return the ordered group ids.
 */
export async function readGroupOrder(admin: APIRequestContext): Promise<string[]> {
	const res = await admin.get(`${API}/admin/groups`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).active as string[]
}

/**
 * Replace the group priority list.
 *
 * @param admin an admin context.
 * @param groups the ordered group ids.
 * @return nothing.
 */
export async function writeGroupOrder(
	admin: APIRequestContext,
	groups: string[],
): Promise<void> {
	const res = await admin.post(`${API}/admin/groups`, { data: { groups } })
	expect(res.status(), await res.text()).toBe(200)
}

/** UUID v4, as DashboardFactory::generateUuid() emits it. */
export const UUID_V4 =
	/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/

/** The `Y-m-d H:i:s` timestamp format of REQ-DASH-010. */
export const TIMESTAMP = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/

/** The fields REQ-DASH-010 requires on every serialised dashboard. */
export const DASHBOARD_FIELDS = [
	'id',
	'uuid',
	'userId',
	'name',
	'description',
	'type',
	'basedOnTemplate',
	'gridColumns',
	'permissionLevel',
	'targetGroups',
	'isDefault',
	'isActive',
	'createdAt',
	'updatedAt',
]
