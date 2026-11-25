/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The work packages widget (work-packages-widget REQ-WP-001..004) on a
 * throwaway account's own dashboard. Launchpad's `/api/work-packages` is
 * answered by a route stub where the spec needs OpenProject data, because
 * the e2e instance has no OpenProject; one test asks the real endpoint and
 * checks the picker against its answer.
 *
 * Gate-19 @e2e traceability:
 *   @e2e work-packages-widget::pieters-work-packages
 *   @e2e work-packages-widget::not-connected
 *   @e2e work-packages-widget::work-packages-of-the-team
 *   @e2e work-packages-widget::no-integration
 *
 * @spec openspec/changes/work-packages-widget/specs/work-packages-widget/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import { contextFor } from './support/principals.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const ENDPOINT = '**/apps/launchpad/api/work-packages**'

const TODAY = new Date().toISOString().slice(0, 10)
const IN_A_WEEK = new Date(Date.now() + 7 * 86_400_000).toISOString().slice(0, 10)

const WORK_PACKAGES = [
	{ id: 12, subject: 'Welstandsadvies verwerken', status: 'In uitvoering', project: 'Havenpark', assignee: 'Pieter Jansen', dueDate: TODAY, url: 'https://op.zuiddrecht.example/work_packages/12' },
	{ id: 31, subject: 'Raming herinrichting Stadshaven', status: 'Wacht op reactie', project: 'Stadshaven fase 2', assignee: 'Sanne de Vries', dueDate: IN_A_WEEK, url: 'https://op.zuiddrecht.example/work_packages/31' },
]

type Account = { username: string; password: string }

let account: Account | null = null
let dashboardId = 0

/**
 * An API context as the administrator, or as the given account.
 *
 * @param who the account; the administrator when omitted.
 * @return the context; the caller disposes it.
 */
async function apiAs(who?: Account): Promise<APIRequestContext> {
	return contextFor(who?.username ?? ADMIN.user, who?.password ?? ADMIN.pass)
}

/**
 * Put one work packages widget on the account's dashboard, replacing any before it.
 *
 * @param content the widget content.
 */
async function placeWidget(content: Record<string, unknown>): Promise<void> {
	const api = await apiAs(account!)
	try {
		const current = await (await api.get(`${API}/dashboard/${dashboardId}`)).json()
		for (const placement of current.placements ?? current.data?.placements ?? []) {
			await api.delete(`${API}/widgets/${placement.id}`)
		}
		const widget = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
			data: { widgetId: 'work-packages', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 6, content },
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)
	} finally {
		await api.dispose()
	}
}

/**
 * Answer the work packages endpoint.
 *
 * @param page the page.
 * @param body the answer.
 */
async function stubEndpoint(page: Page, body: Record<string, unknown>): Promise<void> {
	await page.route(ENDPOINT, (route) => route.fulfill({ json: body }))
}

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}

	account = await provisionThrowawayUser('e2e-wp')
	const api = await apiAs(account)
	try {
		const created = await api.post(`${API}/dashboard`, { data: { name: 'E2E werkpakketten', widgets: 'none' } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		dashboardId = Number(dash.id)
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

// @e2e work-packages-widget::pieters-work-packages
test('the widget lists the work packages as links with status and due date', async ({ browser }) => {
	await placeWidget({ scope: 'mine', project: '', limit: 4 })
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		await stubEndpoint(page, { state: 'ok', workPackages: WORK_PACKAGES })
		await page.goto('/index.php/apps/launchpad')
		const widget = page.locator('[data-testid="work-packages-widget"]')
		await expect(widget.getByRole('link')).toHaveText(['Welstandsadvies verwerken', 'Raming herinrichting Stadshaven'])
		await expect(widget.getByRole('link', { name: 'Welstandsadvies verwerken' })).toHaveAttribute('href', WORK_PACKAGES[0].url)
		await expect(widget).toContainText('In uitvoering')
		await expect(widget).toContainText('Wacht op reactie')
		await expect(widget.locator('[data-testid="cn-status-list-due"]')).toHaveCount(2)
	} finally {
		await context.close()
	}
})

// @e2e work-packages-widget::not-connected
test('a person without a connected account gets a link to connect OpenProject', async ({ browser }) => {
	await placeWidget({ scope: 'mine', project: '', limit: 4 })
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		await stubEndpoint(page, { state: 'not-connected', workPackages: [], connectUrl: '/settings/user/openproject' })
		await page.goto('/index.php/apps/launchpad')
		const notice = page.locator('[data-testid="work-packages-not-connected"]')
		await expect(notice).toContainText('Connect your OpenProject account to see your work packages.')
		await expect(notice.getByRole('link', { name: 'Connect OpenProject' })).toHaveAttribute('href', /settings\/user\/openproject$/)
	} finally {
		await context.close()
	}
})

// @e2e work-packages-widget::work-packages-of-the-team
test('a project list asks for the project and names each assignee', async ({ browser }) => {
	await placeWidget({ scope: 'project', project: 'havenpark', limit: 6 })
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		let asked = ''
		await page.route(ENDPOINT, (route) => {
			asked = route.request().url()
			return route.fulfill({ json: { state: 'ok', workPackages: WORK_PACKAGES } })
		})
		await page.goto('/index.php/apps/launchpad')
		const widget = page.locator('[data-testid="work-packages-widget"]')
		await expect(widget).toContainText('Havenpark · Pieter Jansen')
		expect(asked).toContain('scope=project')
		expect(asked).toContain('project=havenpark')
	} finally {
		await context.close()
	}
})

// @e2e work-packages-widget::no-integration
test('the real endpoint answers a known state, and without the integration the picker leaves the widget out', async ({ browser }) => {
	const api = await apiAs(account!)
	const state: string = await (async () => {
		try {
			const res = await api.get(`${API}/work-packages`)
			expect(res.status(), await res.text()).toBe(200)
			return (await res.json()).state
		} finally {
			await api.dispose()
		}
	})()
	expect(['ok', 'not-installed', 'not-connected', 'error']).toContain(state)

	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		await page.goto('/index.php/apps/launchpad')
		const more = page.locator(MORE_MENU)
		await expect(more).toBeVisible({ timeout: 30_000 })
		await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
		await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
		const picker = page.getByRole('dialog', { name: /add widget/i }).first()
		await expect(picker.locator('[data-testid="widget-picker-section-launchpad"]')).toBeVisible()
		const entry = picker.locator('[data-testid="widget-picker-type-work-packages"]')
		await expect(entry).toHaveCount(state === 'not-installed' ? 0 : 1)
	} finally {
		await context.close()
	}
})
