/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The assigned cases widget (assigned-cases-widget REQ-CASES-001..004) on a
 * throwaway account's own dashboard. OpenRegister's object list is answered
 * by a route stub with the demo cases, so the spec does not depend on demo
 * data; whether dossiq is on the instance decides which half runs.
 *
 * Gate-19 @e2e traceability:
 *   @e2e assigned-cases-widget::pieters-cases
 *   @e2e assigned-cases-widget::counts
 *   @e2e assigned-cases-widget::default-source
 *   @e2e assigned-cases-widget::no-dossiq
 *
 * @spec openspec/changes/assigned-cases-widget/specs/assigned-cases-widget/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { contextFor } from './support/principals.ts'
import { chooseAndAdd } from './support/widgetPicker.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const OBJECTS = '**/apps/openregister/api/objects/dossiq/case**'

const TODAY = new Date().toISOString().slice(0, 10)
const LATER = new Date(Date.now() + 16 * 86_400_000).toISOString().slice(0, 10)
const IN_BEHANDELING = { id: 's1', name: 'In behandeling', colour: 'purple' }
const WACHT = { id: 's2', name: 'Wacht op aanvrager', colour: 'orange' }

const CASES = [
	{ id: 'c1', title: 'Kapvergunning Molenweg 4', identifier: 'Z-2026-0412', deadline: TODAY, status: IN_BEHANDELING },
	{ id: 'c2', title: 'Dakkapel Kerkstraat 12', identifier: 'Z-2026-0440', deadline: LATER, status: WACHT },
	{ id: 'c3', title: 'Evenement Stadshaven', identifier: 'Z-2026-0431', deadline: LATER, status: IN_BEHANDELING },
]

type Account = { username: string; password: string }

let account: Account | null = null
let dossiqId = ''

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
		for (const candidate of ['dossiq', 'procest']) {
			const info = await admin.get(`/ocs/v2.php/cloud/apps/${candidate}?format=json`, { headers: { 'OCS-APIRequest': 'true' } })
			if (info.ok() && (await info.json())?.ocs?.data?.active !== false) {
				dossiqId = candidate
				break
			}
		}
	} finally {
		await admin.dispose()
	}

	account = await provisionThrowawayUser('e2e-cases')
	const api = await apiAs(account)
	try {
		const created = await api.post(`${API}/dashboard`, { data: { name: 'E2E zaken', widgets: 'none' } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		const widget = await api.post(`${API}/dashboard/${dash.id}/widgets`, {
			data: { widgetId: 'assigned-cases', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 8, content: { register: 'dossiq', schema: 'case', limit: 2 } },
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)
		await api.post(`${API}/dashboard/${dash.id}/activate`)
		await api.post(`${API}/dashboards/default`, { data: { uuid: dash.uuid } })
	} finally {
		await api.dispose()
	}
})

test.afterAll(async () => {
	if (account) {
		await deprovisionUser(account.username)
	}
})

// @e2e assigned-cases-widget::pieters-cases
// @e2e assigned-cases-widget::counts
test('the viewer\'s cases show with counts per status, deadline and status', async ({ browser }) => {
	test.skip(dossiqId === '', 'dossiq is not on this instance; the no-dossiq test covers it')
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		let asked = ''
		await page.route(OBJECTS, (route) => {
			asked = route.request().url()
			return route.fulfill({ json: { results: CASES, total: CASES.length } })
		})
		await page.goto('/index.php/apps/launchpad')
		const widget = page.locator('[data-testid="assigned-cases-widget"]')
		await expect(widget.getByRole('link', { name: 'Kapvergunning Molenweg 4' })).toHaveAttribute('href', new RegExp(`/apps/${dossiqId}/cases/c1$`))
		await expect(widget.locator('[data-testid="cn-status-list-item"]')).toHaveCount(2)
		await expect(widget.locator('[data-testid="cn-status-list-count-s1"]')).toContainText('2')
		await expect(widget.locator('[data-testid="cn-status-list-count-s2"]')).toContainText('1')
		await expect(widget).toContainText('Z-2026-0412')
		await expect(widget.locator('[data-testid="assigned-cases-all"]')).toHaveText('All 3 open cases')
		expect(decodeURIComponent(asked)).toContain(`assignee=${account!.username}`)
		expect(decodeURIComponent(asked)).toContain('_order[deadline]=asc')
	} finally {
		await context.close()
	}
})

// @e2e assigned-cases-widget::default-source
test('the form names dossiq\'s register and schema', async ({ browser }) => {
	test.skip(dossiqId === '', 'dossiq is not on this instance, so the picker does not offer the widget')
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		await page.goto('/index.php/apps/launchpad')
		await page.locator('[data-testid="launchpad-more-menu"] [data-testid="launchpad-more-menu-trigger"]').click()
		await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
		await chooseAndAdd(page.locator('[data-testid="widget-picker-type-assigned-cases"]'))
		await expect(page.locator('[data-testid="assigned-cases-form-register"] input')).toHaveValue('dossiq')
		await expect(page.locator('[data-testid="assigned-cases-form-schema"] input')).toHaveValue('case')
	} finally {
		await context.close()
	}
})

// @e2e assigned-cases-widget::no-dossiq
test('without dossiq the widget is not shown and not offered', async ({ browser }) => {
	test.skip(dossiqId !== '', 'dossiq is on this instance')
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		await page.goto('/index.php/apps/launchpad')
		await expect(page.locator('[data-testid="launchpad-more-menu"]')).toBeVisible({ timeout: 30_000 })
		await expect(page.locator('[data-testid="assigned-cases-widget"]')).toHaveCount(0)
		await page.locator('[data-testid="launchpad-more-menu"] [data-testid="launchpad-more-menu-trigger"]').click()
		await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
		await expect(page.locator('[data-testid="widget-picker-section-launchpad"]')).toBeVisible()
		await expect(page.locator('[data-testid="widget-picker-type-assigned-cases"]')).toHaveCount(0)
	} finally {
		await context.close()
	}
})
