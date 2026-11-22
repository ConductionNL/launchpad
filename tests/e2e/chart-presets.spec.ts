/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The pie chart and graph presets (chart-presets REQ-CHARTP-001..003) on a
 * throwaway account's own dashboard, filled with entered values so the spec
 * does not depend on register data.
 *
 * Gate-19 @e2e traceability:
 *   @e2e chart-presets::cases-per-status
 *   @e2e chart-presets::applications-and-decisions-per-month
 *   @e2e chart-presets::entered-values
 *
 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { contextFor } from './support/principals.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'

type Account = { username: string; password: string }

let account: Account | null = null

/**
 * An API context as the administrator, or as the given account.
 *
 * @param who the account; the administrator when omitted.
 * @return the context; the caller disposes it.
 */
async function apiAs(who?: Account): Promise<APIRequestContext> {
	return contextFor(who?.username ?? ADMIN.user, who?.password ?? ADMIN.pass)
}

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}

	account = await provisionThrowawayUser('e2e-charts')
	const api = await apiAs(account)
	try {
		const created = await api.post(`${API}/dashboard`, { data: { name: 'E2E grafieken', widgets: 'none' } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		const pie = await api.post(`${API}/dashboard/${dash.id}/widgets`, {
			data: {
				widgetId: 'pie-chart',
				gridX: 0,
				gridY: 0,
				gridWidth: 6,
				gridHeight: 8,
				content: {
					chartKind: 'donut',
					source: 'manual',
					labels: ['In behandeling', 'Wacht op aanvrager', 'Wacht op advies', 'Afgerond deze maand'],
					series: [138, 74, 46, 54],
					showTotal: true,
					legendValues: true,
					legendPosition: 'right',
				},
			},
		})
		expect(pie.status(), await pie.text()).toBeLessThan(300)
		const graph = await api.post(`${API}/dashboard/${dash.id}/widgets`, {
			data: {
				widgetId: 'graph',
				gridX: 6,
				gridY: 0,
				gridWidth: 6,
				gridHeight: 8,
				content: {
					chartKind: 'bar',
					source: 'manual',
					categories: ['apr', 'mei', 'jun', 'jul', 'aug', 'sep'],
					series: [
						{ name: 'Aanvragen', type: 'column', data: [112, 98, 130, 121, 142, 136] },
						{ name: 'Besluiten', type: 'line', data: [95, 104, 118, 110, 125, 140] },
					],
					legendPosition: 'bottom',
				},
			},
		})
		expect(graph.status(), await graph.text()).toBeLessThan(300)
		await api.post(`${API}/dashboard/${dash.id}/activate`)
		await api.post(`${API}/dashboards/active`, { data: { uuid: dash.uuid } })
	} finally {
		await api.dispose()
	}
})

test.afterAll(async () => {
	if (account) {
		await deprovisionUser(account.username)
	}
})

// @e2e chart-presets::cases-per-status
// @e2e chart-presets::applications-and-decisions-per-month
// @e2e chart-presets::entered-values
test('entered values draw a donut with its total and a graph with bars and a line, without reading a register', async ({ browser }) => {
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		const registerReads: string[] = []
		page.on('request', (request) => {
			if (request.url().includes('/apps/openregister/api/objects/')) {
				registerReads.push(request.url())
			}
		})
		await page.goto('/index.php/apps/launchpad')

		const charts = page.locator('.apexcharts-canvas')
		await expect(charts).toHaveCount(2, { timeout: 30_000 })
		const donut = page.locator('.apexcharts-canvas').filter({ has: page.locator('.apexcharts-pie') })
		await expect(donut.locator('.apexcharts-pie-series path')).toHaveCount(4)
		await expect(donut.locator('.apexcharts-datalabels-group')).toContainText('312')
		await expect(donut.locator('.apexcharts-legend')).toContainText('In behandeling')

		const graph = page.locator('.apexcharts-canvas').filter({ hasNot: page.locator('.apexcharts-pie') })
		await expect(graph.locator('.apexcharts-bar-series path')).toHaveCount(6)
		await expect(graph.locator('.apexcharts-line-series path').first()).toBeVisible()
		await expect(graph.locator('.apexcharts-legend')).toContainText('Aanvragen')
		await expect(graph.locator('.apexcharts-legend')).toContainText('Besluiten')
		await expect(graph.locator('.apexcharts-xaxis')).toContainText('apr')

		expect(registerReads).toEqual([])
	} finally {
		await context.close()
	}
})
