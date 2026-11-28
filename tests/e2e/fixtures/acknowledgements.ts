/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared seeding for the dashboard-acknowledgements e2e specs.
 *
 * Every helper here goes through the product's own HTTP contract, as the
 * principal it names, so a seed that the product refuses fails loudly at the
 * call that was refused instead of leaving a spec to assert against a state
 * that was never built.
 *
 * TWO WAYS AN ANNOUNCEMENT REACHES MORE THAN ONE PERSON
 * =====================================================
 * An announcement is one `announcementKey` carried by several placements.
 * The product copies a placement's acknowledgement fields, key included, in
 * exactly two places:
 *
 *  - `TemplateService::clonePlacement()` when a member of a template's target
 *    group gets their personal copy of an admin template;
 *  - `WidgetPlacementMapper::cloneToDashboard()` when somebody forks a
 *    dashboard they can see (`POST /api/dashboards/{uuid}/fork`).
 *
 * With a template the audience is the template's target groups; without one
 * it is the owners of the dashboards that hold a copy
 * (`AcknowledgementService::resolveAudience()`), and the author is the owner
 * of the first placement that carried the key
 * (`PermissionService::announcementAuthor()`).
 */

import type { APIRequestContext, APIResponse } from '@playwright/test'
import type { Principal } from '../support/principals.ts'

import { expect } from '@playwright/test'

/** The LaunchPad API root. */
export const API = '/index.php/apps/launchpad/api'

/** The receipt endpoint. */
export const ACKS = `${API}/acknowledgements`

/** A placement as the API serialises it, with the fields these specs read. */
export interface AckPlacement {
	id: number
	dashboardId: number
	widgetId: string
	content?: { text?: string } | null
	requiresAcknowledgement: number
	acknowledgementPrompt: string | null
	acknowledgementDeadline: string | null
	reacknowledgeOnChange: number
	acknowledgementContentVersion: number
	announcementKey: string | null
}

/** A dashboard id + uuid. */
export interface DashboardRef {
	id: number
	uuid: string
}

/**
 * A calendar date `days` away from today, as `YYYY-MM-DD`.
 *
 * @param days offset in days, negative for the past.
 * @return the date.
 */
export function isoDate(days: number): string {
	const d = new Date()
	d.setDate(d.getDate() + days)
	return d.toISOString().slice(0, 10)
}

/**
 * Read `allowUserDashboards`, so a spec can switch it on and put it back.
 *
 * @param admin an admin context.
 * @return the current value.
 */
export async function readUserDashboardsFlag(
	admin: APIRequestContext,
): Promise<boolean> {
	const res = await admin.get(`${API}/admin/settings`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).allowUserDashboards === true
}

/**
 * Set `allowUserDashboards` (a personal dashboard and a fork both need it).
 *
 * @param admin an admin context.
 * @param allowed the value.
 * @return nothing.
 */
export async function setUserDashboardsFlag(
	admin: APIRequestContext,
	allowed: boolean,
): Promise<void> {
	const res = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: allowed },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
}

/**
 * Create a personal dashboard as `owner`.
 *
 * @param owner the principal.
 * @param name the dashboard name.
 * @return its id and uuid.
 */
export async function createPersonalDashboard(
	owner: Principal,
	name: string,
): Promise<DashboardRef> {
	const res = await owner.api.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const dash = body.dashboard ?? body
	return { id: Number(dash.id), uuid: String(dash.uuid) }
}

/**
 * Put a markdown text widget on a dashboard.
 *
 * @param api the acting context (needs add rights on the dashboard).
 * @param dashboardId the dashboard.
 * @param text the widget's text, which is also how a spec finds it again.
 * @param gridX the column it starts in.
 * @return the new placement id.
 */
export async function addTextWidget(
	api: APIRequestContext,
	dashboardId: number,
	text: string,
	gridX: number,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 3,
			content: { text, contentMode: 'markdown' },
		},
	})
	expect(res.status(), await res.text()).toBe(201)
	return Number((await res.json()).id)
}

/**
 * The placements of a dashboard, as the given principal reads them.
 *
 * @param api the reading context.
 * @param dashboardId the dashboard.
 * @return its placements.
 */
export async function placementsOf(
	api: APIRequestContext,
	dashboardId: number,
): Promise<AckPlacement[]> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (body.placements ?? body.dashboard?.placements ?? []) as AckPlacement[]
}

/**
 * The one placement on a dashboard whose text widget carries `text`.
 *
 * @param api the reading context.
 * @param dashboardId the dashboard.
 * @param text the widget text.
 * @return the placement.
 */
export async function placementWithText(
	api: APIRequestContext,
	dashboardId: number,
	text: string,
): Promise<AckPlacement> {
	const all = await placementsOf(api, dashboardId)
	const hits = all.filter((p) => p.content?.text === text)
	expect(hits, JSON.stringify(all)).toHaveLength(1)
	return hits[0]
}

/**
 * `PUT /api/widgets/{id}` with the given body.
 *
 * @param api the acting context.
 * @param placementId the placement.
 * @param data the body.
 * @return the response.
 */
export async function updatePlacement(
	api: APIRequestContext,
	placementId: number,
	data: Record<string, unknown>,
): Promise<APIResponse> {
	return api.put(`${API}/widgets/${placementId}`, { data })
}

/**
 * Share a dashboard with a user (the caller must own it).
 *
 * @param owner the owner's context.
 * @param dashboardId the dashboard.
 * @param username the recipient.
 * @param permissionLevel the level.
 * @return nothing.
 */
export async function share(
	owner: APIRequestContext,
	dashboardId: number,
	username: string,
	permissionLevel: 'view_only' | 'add_only' | 'full',
): Promise<void> {
	const res = await owner.post(`${API}/dashboard/${dashboardId}/shares`, {
		data: { shareType: 'user', shareWith: username, permissionLevel },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
}

/**
 * Make a dashboard the principal's active one (the per-user preference the
 * workspace resolves through).
 *
 * @param api the principal's context.
 * @param uuid the dashboard uuid.
 * @return nothing.
 */
export async function activate(api: APIRequestContext, uuid: string): Promise<void> {
	const res = await api.post(`${API}/dashboards/default`, { data: { uuid } })
	expect(res.status(), await res.text()).toBeLessThan(300)
}

/**
 * Fork a visible dashboard as `who` and make the fork their active one.
 *
 * @param who the principal.
 * @param sourceUuid the dashboard to fork.
 * @param name the fork's name.
 * @return the fork.
 */
export async function forkAndActivate(
	who: Principal,
	sourceUuid: string,
	name: string,
): Promise<DashboardRef> {
	const res = await who.api.post(`${API}/dashboards/${sourceUuid}/fork`, {
		data: { name },
	})
	expect(res.status(), await res.text()).toBe(201)
	const dash = (await res.json()).dashboard
	const ref = { id: Number(dash.id), uuid: String(dash.uuid) }
	await activate(who.api, ref.uuid)
	return ref
}

/**
 * `POST /api/acknowledgements`.
 *
 * @param api the acting context.
 * @param body the body.
 * @return the response.
 */
export async function postAcknowledgement(
	api: APIRequestContext,
	body: Record<string, unknown>,
): Promise<APIResponse> {
	return api.post(ACKS, { data: body })
}

/** The pending list as `GET /api/acknowledgements/pending` returns it. */
export interface PendingList {
	count: number
	items: Array<{
		placementId: number
		announcementKey: string
		contentVersion: number
		deadline: string | null
		prompt: string | null
	}>
}

/**
 * The calling user's outstanding items.
 *
 * @param api the user's context.
 * @return the list.
 */
export async function pendingFor(api: APIRequestContext): Promise<PendingList> {
	const res = await api.get(`${ACKS}/pending`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()) as PendingList
}

/** The read-receipt report as `GET /api/acknowledgements/report/{key}` returns it. */
export interface Report {
	announcementKey: string
	contentVersion: number
	deadline: string | null
	overdue: boolean
	acknowledgedCount: number
	pendingCount: number
	pending: string[]
	acknowledged: Array<{ userId: string; acknowledgedAt: string }>
	rows: Array<{
		userId: string
		status: 'acknowledged' | 'pending'
		acknowledgedAt: string | null
	}>
}

/**
 * Read the report as a principal allowed to (asserts the 200).
 *
 * @param api the reading context.
 * @param key the announcement key.
 * @return the report.
 */
export async function reportOf(
	api: APIRequestContext,
	key: string,
): Promise<Report> {
	const res = await api.get(`${ACKS}/report/${key}`)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()) as Report
}

/** The forced-delivery prompt over one placement. */
export function promptSelector(placementId: number): string {
	return `[data-placement-id="${placementId}"] [data-testid="acknowledgement-prompt"]`
}
