/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A personal dashboard with no permission level of its own and no template:
 * the state the effective-level chain falls back from to the admin default
 * (admin-settings REQ-ASET-005, permissions REQ-PERM-008).
 *
 * No endpoint writes it. `DashboardFactory` always stores `full` and a
 * template copy takes the template's level, so the level is emptied in the
 * row (`support/instanceDb.ts`). The widgets are added while the level is
 * still `full`, so the adds cannot be refused by the state under test, and
 * one of them is marked compulsory in the row, because only a compulsory
 * widget tells add_only (keeps it) from full (lets it go).
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './principals.ts'

import { expect } from '@playwright/test'
import { writeSettings } from './adminSettings.ts'
import { selectRows, updateRows } from './instanceDb.ts'
import { API } from './principals.ts'

/** The dashboard and its two text widgets. */
export interface EmptyLevelDashboard {
	/** The dashboard id. */
	id: number
	/** A placement marked compulsory. */
	locked: number
	/** An ordinary placement. */
	loose: number
}

/**
 * Create the dashboard for `owner`. Turns personal dashboards on as the
 * admin; the caller restores the settings.
 *
 * @param admin an admin context.
 * @param owner the user who owns it.
 * @return the dashboard and its placements.
 */
export async function emptyLevelDashboard(
	admin: APIRequestContext,
	owner: Principal,
): Promise<EmptyLevelDashboard> {
	await writeSettings(admin, { allowUserDash: true, allowMultiDash: true })
	const created = await owner.api.post(`${API}/dashboard`, {
		data: { name: `E2E empty level ${Date.now()}` },
	})
	expect(created.status(), await created.text()).toBe(201)
	const dashboard = (await created.json()).dashboard
	const id = Number(dashboard.id)
	expect(dashboard.basedOnTemplate ?? null, 'a user dashboard has no template').toBeNull()
	expect(dashboard.permissionLevel, 'DashboardFactory writes full').toBe('full')

	const add = async (y: number): Promise<number> => {
		const res = await owner.api.post(`${API}/dashboard/${id}/widgets`, {
			data: { widgetId: 'text', gridX: 0, gridY: y, gridWidth: 2, gridHeight: 2 },
		})
		expect(res.status(), await res.text()).toBe(201)
		const body = await res.json()
		return Number(body.id ?? body.data?.id)
	}
	const locked = await add(0)
	const loose = await add(4)

	expect(
		await updateRows('launchpad_widget_placements', { is_compulsory: 1 }, { id: locked }),
	).toBe(1)
	expect(
		await updateRows('launchpad_dashboards', { permission_level: '' }, { id }),
	).toBe(1)
	const [row] = await selectRows('launchpad_dashboards', { id })
	expect(row.permission_level ?? '', 'the stored level must now be empty').toBe('')
	expect(row.based_on_template ?? null).toBeNull()

	return { id, locked, loose }
}

/**
 * The placement ids of a dashboard as `who` reads it.
 *
 * @param who a principal who may read it.
 * @param id the dashboard.
 * @return the placement ids.
 */
export async function placementIds(who: Principal, id: number): Promise<number[]> {
	const res = await who.api.get(`${API}/dashboard/${id}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return ((body.placements ?? []) as Array<{ id: number }>).map((p) => Number(p.id))
}
