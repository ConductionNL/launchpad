/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The start page (REQ-DWB-008): the dashboard everyone starts on is board
 * LpVandaag, provisioned by the SeedDefaultDashboard repair step.
 *
 *   @e2e default-widget-bundle::a-fresh-install-starts-on-vandaag
 *
 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-008-the-start-page-is-the-vandaag-layout
 */

import { expect, request, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

/** The board's widgets, in sort order. */
const BOARD = [
	'header', 'weather', 'attention', 'section-heading', 'nc-widget', 'calendar', 'recent-work',
	'section-heading', 'announcements', 'people', 'section-heading', 'section-heading', 'app-tiles',
	'tile', 'tile', 'tile',
]

test.describe('start page (REQ-DWB-008)', () => {
	// @e2e default-widget-bundle::a-fresh-install-starts-on-vandaag
	test('the default-group start page is the Vandaag layout, and a new account opens on it', async ({ browser }) => {
		const api = await request.newContext({
			baseURL: BASE_URL,
			httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
			extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
		})
		let start: { id: number, uuid: string, name: string, icon: string, isDefault: number } | undefined
		try {
			const list = await api.get('/index.php/apps/launchpad/api/dashboards/visible')
			expect(list.ok(), `visible dashboards: ${list.status()}`).toBe(true)
			const body = await list.json()
			const rows = body.items ?? []
			start = rows.find((row: any) => row.slug === 'start' && row.groupId === 'default')
			expect(start, 'the default group carries the start page (slug start)').toBeTruthy()
			expect(start!.isDefault).toBe(1)
			expect(start!.icon).toBe('Home')

			const shown = await api.get(`/index.php/apps/launchpad/api/dashboard/${start!.id}`)
			expect(shown.ok(), `dashboard: ${shown.status()}`).toBe(true)
			const sorted = [...((await shown.json()).placements ?? [])].sort((a: any, b: any) => a.sortOrder - b.sortOrder)
			expect(sorted.map((p: any) => p.widgetId)).toEqual(BOARD)
			expect(sorted.filter((p: any) => p.widgetId === 'tile').map((p: any) => p.tileTitle))
				.toEqual(['Conduction', 'Sendent', 'Nextcloud'])
		} finally {
			await api.dispose()
		}

		// A brand-new account owns nothing and has chosen nothing: it opens on the start page.
		const { username, password } = await provisionThrowawayUser()
		try {
			const { context, page } = await loginAs(browser, username, password)
			try {
				await page.goto('/index.php/apps/launchpad')
				await expect(page).toHaveURL(/\/apps\/launchpad\/start/, { timeout: 20_000 })
				await expect(page.getByRole('heading', { level: 1, name: start!.name })).toBeVisible({ timeout: 20_000 })
				await expect(page.getByText(new RegExp(`(Good (morning|afternoon|evening)|Goed(emorgen|emiddag|enavond)), ${username}`)))
					.toBeVisible({ timeout: 20_000 })
			} finally {
				await context.close()
			}
		} finally {
			await deprovisionUser(username)
		}
	})
})
