/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the `widgets` capability specs
 * (tests/e2e/widgets-*.spec.ts, openspec/specs/widgets/spec.md).
 *
 * Every helper takes the request context of the principal it acts as, so a
 * spec decides who does what. Nothing here asserts a scenario; it seeds and
 * re-reads.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'
import { API } from './principals.ts'

/** A placement as `GET /api/dashboard/{id}` and the widget routes serialise it. */
export interface Placement {
	id: number
	dashboardId: number
	widgetId: string
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	isCompulsory: number
	isVisible: number
	showTitle: number
	sortOrder: number
	customTitle: string | null
	customIcon: string | null
	styleConfig: Record<string, unknown>
	content: Record<string, unknown>
	tileType?: string | null
	tileTitle?: string | null
	tileLinkType?: string | null
	tileLinkValue?: string | null
	tileBackgroundColor?: string | null
	tileTextColor?: string | null
	tileIcon?: string | null
	tileIconType?: string | null
}

/** A dashboard a spec created, by id and uuid. */
export interface OwnDashboard {
	id: number
	uuid: string
}

/**
 * Create a personal dashboard as the calling principal.
 *
 * @param api the principal's context.
 * @param name a unique name.
 * @return its id and uuid.
 */
export async function createOwnDashboard(
	api: APIRequestContext,
	name: string,
): Promise<OwnDashboard> {
	const created = await api.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body
	return { id: Number(dash.id), uuid: String(dash.uuid) }
}

/**
 * Make a dashboard the calling principal's active one (both mechanisms; see
 * support/dashboardFixture.ts for why it takes two calls).
 *
 * @param api the principal's context.
 * @param dashboard the dashboard.
 * @return nothing.
 */
export async function activate(
	api: APIRequestContext,
	dashboard: OwnDashboard,
): Promise<void> {
	const legacy = await api.post(`${API}/dashboard/${dashboard.id}/activate`)
	expect(legacy.status(), await legacy.text()).toBeLessThan(300)
	const preference = await api.post(`${API}/dashboards/active`, {
		data: { uuid: dashboard.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)
}

/**
 * Re-read every placement of a dashboard as the given principal.
 *
 * @param api the principal's context (normally the owner).
 * @param dashboardId the dashboard.
 * @return the placements, as the server holds them now.
 */
export async function placementsOf(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Placement[]> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	return ((await res.json()).placements ?? []) as Placement[]
}

/**
 * Re-read one placement of a dashboard; undefined when it is gone.
 *
 * @param api the principal's context.
 * @param dashboardId the dashboard.
 * @param placementId the placement.
 * @return the placement, or undefined.
 */
export async function placementOf(
	api: APIRequestContext,
	dashboardId: number,
	placementId: number,
): Promise<Placement | undefined> {
	return (await placementsOf(api, dashboardId)).find((p) => p.id === placementId)
}

/**
 * Seed a widget placement and return it (seeding only: asserts 201).
 *
 * @param api the principal's context.
 * @param dashboardId the dashboard.
 * @param body the `POST /api/dashboard/{id}/widgets` body.
 * @return the created placement.
 */
export async function seedPlacement(
	api: APIRequestContext,
	dashboardId: number,
	body: Record<string, unknown>,
): Promise<Placement> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: body,
	})
	expect(res.status(), await res.text()).toBe(201)
	return (await res.json()) as Placement
}

/**
 * Seed a launcher tile placement (seeding only: asserts 201).
 *
 * @param api the principal's context.
 * @param dashboardId the dashboard.
 * @param body the `POST /api/dashboard/{id}/tile` body.
 * @return the created tile placement.
 */
export async function seedTile(
	api: APIRequestContext,
	dashboardId: number,
	body: Record<string, unknown>,
): Promise<Placement> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/tile`, {
		data: body,
	})
	expect(res.status(), await res.text()).toBe(201)
	return (await res.json()) as Placement
}

/**
 * Whether two grid rectangles overlap.
 *
 * @param a one placement.
 * @param b another.
 * @return true when they share at least one cell.
 */
export function overlaps(
	a: Pick<Placement, 'gridX' | 'gridY' | 'gridWidth' | 'gridHeight'>,
	b: Pick<Placement, 'gridX' | 'gridY' | 'gridWidth' | 'gridHeight'>,
): boolean {
	return (
		a.gridX < b.gridX + b.gridWidth
		&& b.gridX < a.gridX + a.gridWidth
		&& a.gridY < b.gridY + b.gridHeight
		&& b.gridY < a.gridY + a.gridHeight
	)
}

/** Read `allowUserDashboards` so a spec can put it back afterwards. */
export async function readAllowUserDashboards(
	admin: APIRequestContext,
): Promise<boolean | null> {
	const res = await admin.get(`${API}/admin/settings`)
	if (!res.ok()) {
		return null
	}
	const body = await res.json()
	return body.allowUserDashboards === true
}

/** Put `allowUserDashboards` back to what it was; a null leaves it alone. */
export async function restoreAllowUserDashboards(
	admin: APIRequestContext,
	before: boolean | null,
): Promise<void> {
	if (before === null) {
		return
	}
	await admin.put(`${API}/admin/settings`, { data: { allowUserDash: before } })
}
