/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace reads its widget catalogue from the page's initial state
 * (`widgets`, REQ-RFP-010) and asks `GET /api/widgets` only when that is
 * empty (src/stores/widgets.js, loadAvailableWidgets). A test that stubs
 * `GET /api/widgets` to decide what the picker is given therefore also drops
 * the catalogue from the initial state, so the stub is what the page reads.
 */

import type { Page } from '@playwright/test'

/**
 * Serve the workspace page without its initial-state widget catalogue, so
 * the page fetches the catalogue over `GET /api/widgets`.
 *
 * @param page the page, before it navigates.
 * @return nothing.
 */
export async function catalogueFromApi(page: Page): Promise<void> {
	await page.route(
		(url) => url.pathname.includes('/apps/launchpad') && !url.pathname.includes('/api/'),
		async (route) => {
			if (route.request().resourceType() !== 'document') {
				await route.fallback()
				return
			}
			const response = await route.fetch()
			const html = (await response.text()).replace(
				/<input[^>]*id="initial-state-launchpad-widgets"[^>]*>/,
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
