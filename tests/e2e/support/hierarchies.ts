/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Dashboard hierarchies for the filter-bus specs (REQ-CWF-011): the
 * nl-administrative descriptor as the dashboard stores it, and the call that
 * stores hierarchies on a dashboard through the dashboard API.
 */

import type { APIRequestContext, APIResponse } from '@playwright/test'

import { expect } from '@playwright/test'

const DASHBOARDS = '/index.php/apps/launchpad/api/dashboard'

/** The nl-administrative preset as the dashboard stores it. */
export const NL_ADMINISTRATIVE = {
	id: 'nl-administrative',
	levels: [
		{ dimension: 'provincieCode', label: 'Province', parentDimension: null },
		{ dimension: 'gemeenteCode', label: 'Municipality', parentDimension: 'provincieCode' },
		{ dimension: 'wijkCode', label: 'District', parentDimension: 'gemeenteCode' },
	],
}

/**
 * Send `hierarchies` in a dashboard update.
 *
 * @param api a context acting as the user under test.
 * @param dashboardId the dashboard.
 * @param hierarchies the descriptors (anything, so refusals can be probed).
 * @return the response.
 */
export async function putHierarchies(api: APIRequestContext, dashboardId: number, hierarchies: unknown): Promise<APIResponse> {
	return api.put(`${DASHBOARDS}/${dashboardId}`, { data: { hierarchies } })
}

/**
 * Store hierarchies on a dashboard and fail unless the API accepts them.
 *
 * @param api a context that may edit the dashboard.
 * @param dashboardId the dashboard.
 * @param hierarchies the descriptors.
 * @return nothing.
 */
export async function storeHierarchies(api: APIRequestContext, dashboardId: number, hierarchies: object[]): Promise<void> {
	const res = await putHierarchies(api, dashboardId, hierarchies)
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * The hierarchies a dashboard read returns.
 *
 * @param api a context that may view the dashboard.
 * @param dashboardId the dashboard.
 * @return the stored descriptors.
 */
export async function readHierarchies(api: APIRequestContext, dashboardId: number): Promise<unknown> {
	const res = await api.get(`${DASHBOARDS}/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return (body.dashboard ?? body.data?.dashboard ?? body).hierarchies
}
