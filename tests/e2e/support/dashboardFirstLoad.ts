/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard store's first load comes from the page's initial state
 * (`dashboardFirstLoad`, dashboard-first-load-from-initial-state): the
 * visible list and the active dashboard the page already computed. Only a
 * page without that key asks `GET /api/dashboards/visible` and
 * `GET /api/dashboard` (src/stores/dashboard.js). A test that stubs or
 * rewrites those answers therefore also drops the key from the page, so the
 * answer it shapes is what the page reads.
 */

import type { Page } from '@playwright/test'

/**
 * Serve LaunchPad pages without their initial-state first dashboard load, so
 * the store fetches the visible list and the active dashboard over the API.
 *
 * @param page the page, before it navigates.
 * @return nothing.
 */
export async function dashboardsFromApi(page: Page): Promise<void> {
	await page.route(
		(url) => url.pathname.includes('/apps/launchpad') && !url.pathname.includes('/api/'),
		async (route) => {
			if (route.request().resourceType() !== 'document') {
				await route.fallback()
				return
			}
			const response = await route.fetch()
			const html = (await response.text()).replace(
				/<input[^>]*id="initial-state-launchpad-dashboardFirstLoad"[^>]*>/,
				'',
			)
			// route.fetch() hands back a decoded body, so the original
			// encoding and length no longer describe it.
			const headers = { ...response.headers() }
			delete headers['content-encoding']
			delete headers['content-length']
			await route.fulfill({ status: response.status(), headers, body: html })
		},
	)
}
