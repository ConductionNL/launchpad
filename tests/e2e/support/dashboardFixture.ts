/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Seed a dashboard for specs that need the workspace shell to actually render.
 *
 * WHY THIS EXISTS
 * ===============
 * `tests/e2e/seed.sh` creates exactly one thing: the `e2e-grantee` USER. It
 * creates no dashboard. On a fresh instance — which is what CI provisions —
 * LaunchPad therefore renders its empty state ("No dashboards available"),
 * and every control that lives in the workspace shell is simply absent.
 *
 * That is why `active-dashboard-resolution`, `add-widget-modal` and
 * `allow-personal-dashboards-flag` were red. Measured in CI run 32308042394,
 * their failures all read like this:
 *
 *     expect(locator).toBeVisible() failed
 *     Locator: locator('.launchpad-sidebar-toggle').first()
 *     Error: element(s) not found
 *
 * `.launchpad-sidebar-toggle` lives in `.launchpad-floating-controls`, which
 * only renders once a dashboard is active. The specs were depending on
 * AMBIENT STATE — a dashboard some earlier spec happened to leave behind —
 * which is why they passed on a warm rig and failed on a cold one.
 *
 * `tile-quick-search.spec.ts` already builds its own dashboard in `beforeAll`
 * and is green on both. This module generalises that so the fix is one
 * function rather than four copies.
 *
 * ACTIVATION IS TWO MECHANISMS, NOT ONE
 * =====================================
 * `POST /api/dashboard/{id}/activate` sets the legacy id-based `is_active`
 * column. `POST /api/dashboards/active` sets a per-user UUID PREFERENCE, and
 * the shell resolves its dashboard through THAT. Calling only the first one
 * loads whatever dashboard a previous spec's preference points at — measured,
 * and the reason tile-quick-search calls both.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect } from '@playwright/test'

const SETTINGS = '/index.php/apps/launchpad/api/admin/settings'
const DASHBOARDS = '/index.php/apps/launchpad/api/dashboard'

export interface SeededDashboard {
	id: number
	uuid: string
	name: string
}

/**
 * Create a dashboard and make it the active one for the calling user.
 *
 * Enables `allowUserDashboards` first, because creating a personal dashboard
 * is refused with 403 `personal_dashboards_disabled` when the flag is off —
 * and a spec that later toggles the flag still needs the dashboard to exist.
 *
 * @param api an admin-authenticated request context.
 * @param name display name for the dashboard.
 * @return the seeded dashboard's id, uuid and name.
 */
export async function seedActiveDashboard(
	api: APIRequestContext,
	name: string,
): Promise<SeededDashboard> {
	const enable = await api.put(SETTINGS, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)

	const created = await api.post(DASHBOARDS, { data: { name } })
	expect(created.status(), await created.text()).toBeLessThan(300)

	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const id = Number(dash.id)
	expect(id, `no dashboard id in ${JSON.stringify(body)}`).toBeTruthy()

	const activate = await api.post(`${DASHBOARDS}/${id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)

	const preference = await api.post(
		'/index.php/apps/launchpad/api/dashboards/active',
		{ data: { uuid: dash.uuid } },
	)
	expect(preference.status(), await preference.text()).toBeLessThan(300)

	return { id, uuid: dash.uuid, name }
}

/**
 * Delete a seeded dashboard. Safe to call with a partially-created fixture.
 *
 * @param api an admin-authenticated request context.
 * @param dashboard the value returned by `seedActiveDashboard`, or null.
 * @return nothing.
 */
export async function removeSeededDashboard(
	api: APIRequestContext,
	dashboard: SeededDashboard | null,
): Promise<void> {
	if (!dashboard?.id) {
		return
	}
	await api.delete(`${DASHBOARDS}/${dashboard.id}`)
}
