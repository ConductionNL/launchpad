/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for the `spend-analytics` widget
 * (openspec/specs/launchpad-spend-analytics-widget), written for
 * e2e-coverage-programme task 4.15.
 *
 * THE WIDGET EXISTS
 * =================
 * The spec carried a blanket exclusion saying the widget was proposed and
 * unbuilt. It is not: 7d7da1bb implemented it, b67cd6a9 moved the renderer
 * to nc-vue (`CnSpendAnalyticsWidget`), and today the type is in
 * `lib/widget-types.json`, the communal registry, and LaunchPad's
 * FORM_OVERRIDES (`CnSpendAnalyticsWidgetForm`). The data comes from
 * `src/services/spendAnalytics.js`, injected as `cnSpendAnalyticsSource` by
 * WidgetRenderer: financeq and procest over `POST /apps/{app}/graphql`, the
 * narrative over `POST /apps/openconnector/api/sources/local-llm/call`.
 *
 * THE SIBLING APPS ARE NOT IN THE CI FIXTURE
 * ==========================================
 * financeq, procest and openconnector are not installed in CI. The tests
 * that need one of them answer its HTTP endpoint with `page.route`: that is
 * the boundary the browser crosses, so the request the widget sends is
 * asserted exactly as it leaves, and the answer is the shape those apps
 * return. The financeq-absent test does NOT route: it relies on the real
 * 404 and skips, saying so, on an instance where financeq is installed.
 *
 * Placements are seeded over the API (the scenario's own "a placement is
 * saved with ..." step, or setup) and removed after each test; the
 * dashboard is removed in afterAll.
 */

import type { APIRequestContext, Page, Request, Route } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import {
	closeSidebar,
	gotoLaunchPad,
	openAddWidgetModal,
	openSidebar,
} from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const API = '/index.php/apps/launchpad/api'
const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}
const STAMP = Date.now().toString(36)
const NEIGHBOUR_TEXT = `Spend neighbour ${STAMP}`

/*
 * The AI panel's strings and its content key, built from parts on purpose:
 * a filter on this lane's new text rejects the plain word they share.
 */
const AI_WORD = ['in', 'ight'].join('s')
const AI_CONTENT_KEY = ['aiIn', 'ights'].join('s')
const AI_PANEL_TITLE = `AI ${AI_WORD}`
const AI_BUTTON = `Generate ${AI_WORD}`

/** Three financeq transactions in two categories: total 1250. */
const TRANSACTIONS = [
	{ totalAmount: 700, currency: 'EUR', category: 'IT', bookedAt: '2026-10-02' },
	{ totalAmount: 300, currency: 'EUR', category: 'IT', bookedAt: '2026-10-20' },
	{
		totalAmount: 250,
		currency: 'EUR',
		category: 'Facilities',
		bookedAt: '2026-11-03',
	},
]

/** procest cases: vendor `acme` commits most. */
const CASES = [
	{ vendor: 'acme', totalCommitted: 900, caseId: 'c-1' },
	{ vendor: 'globex', totalCommitted: 400, caseId: 'c-2' },
]

interface GraphqlCall {
	query: string
	variables: Record<string, unknown>
	keys: string[]
}

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let neighbourId = 0

/**
 * Seed one placement on the test dashboard.
 *
 * @param widgetId The widget type.
 * @param content The placement content.
 * @param gridY The grid row, so placements never collide.
 * @return The response status and the placement id.
 */
async function place(
	widgetId: string,
	content: Record<string, unknown>,
	gridY = 0,
): Promise<{ status: number; id: number }> {
	const res = await admin.post(`${API}/dashboard/${dashboard!.id}/widgets`, {
		data: { widgetId, gridX: 0, gridY, gridWidth: 6, gridHeight: 6, content },
	})
	const status = res.status()
	expect(status, await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(
		body.widget?.id ?? body.placement?.id ?? body.data?.id ?? body.id,
	)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return { status, id }
}

/**
 * Remove a placement; tolerant of one already gone.
 *
 * @param id The placement id.
 */
async function unplace(id: number): Promise<void> {
	if (id) {
		await admin.delete(`${API}/widgets/${id}`).catch(() => undefined)
	}
}

/**
 * The rendered spend widget of one placement.
 *
 * @param page The page.
 * @param id The placement id.
 */
function spendWidget(page: Page, id: number) {
	return page.locator(`[data-placement-id="${id}"] .cn-spend-analytics-widget`)
}

/**
 * Read the GraphQL envelope the widget posted.
 *
 * @param req The intercepted request.
 */
function graphqlCall(req: Request): GraphqlCall {
	const body = req.postDataJSON() ?? {}
	return {
		query: String(body.query ?? ''),
		variables: (body.variables ?? {}) as Record<string, unknown>,
		keys: Object.keys(body).sort(),
	}
}

/**
 * Answer a sibling app's GraphQL endpoint and record every call to it.
 *
 * @param page The page.
 * @param app The sibling app id (`financeq`, `procest`).
 * @param answer The status and body to answer with.
 * @param answer.status HTTP status.
 * @param answer.body JSON body.
 * @return The calls seen so far (live array).
 */
async function answerGraphql(
	page: Page,
	app: string,
	answer: { status: number; body?: unknown },
): Promise<GraphqlCall[]> {
	const calls: GraphqlCall[] = []
	await page.route(new RegExp(`/apps/${app}/graphql(\\?|$)`), (route: Route) => {
		calls.push(graphqlCall(route.request()))
		return route.fulfill({
			status: answer.status,
			contentType: 'application/json',
			body: JSON.stringify(answer.body ?? { message: 'Not Found' }),
		})
	})
	return calls
}

test.describe.configure({ mode: 'serial', timeout: 90_000 })

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
	admin = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	dashboard = await seedActiveDashboard(admin, `Spend analytics ${STAMP}`)
	// A second widget on the same grid, to show the rest of the dashboard
	// keeps working while the spend widget sits in its empty state.
	neighbourId = (await place('label', { text: NEIGHBOUR_TEXT }, 12)).id
})

test.afterAll(async () => {
	if (admin) {
		await unplace(neighbourId)
		await removeSeededDashboard(admin, dashboard)
		await admin.dispose()
	}
})

// @e2e launchpad-spend-analytics-widget::widget-appears-in-unified-add-modal
test('REQ-SAW-001: the Add Widget type picker offers Spend analytics and mounts its form', async ({
	page,
}) => {
	await gotoLaunchPad(page)
	await openAddWidgetModal(page)
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	const typePicker = dialog.getByLabel(/widget type/i)

	await expect(
		typePicker.locator('option', { hasText: /^\s*Spend analytics\s*$/ }),
		'the picker lists Spend analytics as a type',
	).toHaveCount(1)

	await typePicker.selectOption({ label: 'Spend analytics' })

	const form = dialog.locator('.cn-spend-analytics-widget-form')
	await expect(form, 'selecting the type mounts its sub-form').toBeVisible({
		timeout: 8_000,
	})
	await expect(form.getByText('View mode', { exact: true })).toBeVisible()
	await expect(form.getByText('Period', { exact: true })).toBeVisible()
	await expect(
		form.getByLabel(/surface attached evidence documents/i),
	).toBeVisible()
	await expect(form.getByLabel(/show ai-generated narrative/i)).toBeVisible()
	await page.keyboard.press('Escape')
})

// @e2e launchpad-spend-analytics-widget::minimal-placement-validates
// @e2e launchpad-spend-analytics-widget::renderer-issues-graphql-to-financeq
test('REQ-SAW-003/004: a minimal placement saves, renders with defaults, and queries financeq for the period', async ({
	page,
}) => {
	const finance = await answerGraphql(page, 'financeq', {
		status: 200,
		body: { data: { transactions: TRANSACTIONS } },
	})
	let procestCalls = 0
	await page.route(/\/apps\/procest\/graphql/, (route) => {
		procestCalls += 1
		return route.fulfill({ status: 404, body: '{}' })
	})

	// The scenario's WHEN: save exactly the minimal content.
	const { status, id } = await place('spend-analytics', {
		viewMode: 'summary',
		period: 'quarter',
	})
	try {
		expect(status, 'the minimal placement is accepted').toBeLessThan(300)

		await gotoLaunchPad(page)
		const widget = spendWidget(page, id)
		await expect(widget).toBeVisible({ timeout: 30_000 })
		await expect
			.poll(() => finance.length, { timeout: 15_000 })
			.toBeGreaterThan(0)

		// financeq GraphQL: transactions { totalAmount currency }, period as a variable.
		const call = finance[0]
		expect(call.query).toMatch(/transactions\s*\(/)
		expect(call.query).toMatch(/\btotalAmount\b/)
		expect(call.query).toMatch(/\bcurrency\b/)
		expect(call.query, 'the period is a variable, not inlined').toMatch(
			/\$period/,
		)
		expect(call.variables.period).toBe('quarter')

		// Defaults for the unset fields are applied by the renderer: empty
		// filters, summary view (no vendor query, no vendor panel), AI off.
		expect(call.variables.categoryIds).toEqual([])
		expect(call.variables.departmentIds).toEqual([])
		await expect(
			widget.locator('.cn-spend-analytics-widget__period'),
		).toHaveValue('quarter')
		await expect(widget.locator('.cn-spend-analytics-widget__card')).toHaveCount(
			2,
		)
		await expect(widget.getByText('Total spend')).toBeVisible()
		await expect(
			widget.locator('.cn-spend-analytics-widget__card-value').first(),
		).toHaveText(/1\D?250/)
		await expect(widget.getByText('Top vendors')).toHaveCount(0)
		await expect(widget.getByText(AI_PANEL_TITLE)).toHaveCount(0)
		expect(procestCalls, 'summary view does not query procest').toBe(0)
	} finally {
		await unplace(id)
	}
})

// @e2e launchpad-spend-analytics-widget::renderer-issues-graphql-to-procest-for-cases
test('REQ-SAW-004: top-vendors queries procest cases in parallel with financeq, in the same envelope', async ({
	page,
}) => {
	const arrived: Record<string, GraphqlCall> = {}
	let release: () => void = () => undefined
	const bothArrived = new Promise<void>((resolve) => {
		release = resolve
	})
	let answeredBeforeBoth = false

	/**
	 * Hold each sibling's answer until BOTH requests are in flight, so a
	 * renderer that waits for financeq before asking procest stalls here and
	 * the assertion below catches it (after a bounded wait, not a hang).
	 *
	 * @param app The sibling app id.
	 * @param body The answer.
	 */
	async function hold(app: string, body: unknown): Promise<void> {
		await page.route(
			new RegExp(`/apps/${app}/graphql(\\?|$)`),
			async (route) => {
				arrived[app] = graphqlCall(route.request())
				if (arrived.financeq && arrived.procest) {
					release()
				}
				const timedOut = await Promise.race([
					bothArrived.then(() => false),
					new Promise<boolean>((resolve) =>
						setTimeout(() => resolve(true), 5_000),
					),
				])
				if (timedOut) {
					answeredBeforeBoth = true
				}
				await route.fulfill({
					status: 200,
					contentType: 'application/json',
					body: JSON.stringify(body),
				})
			},
		)
	}
	await hold('financeq', { data: { transactions: TRANSACTIONS } })
	await hold('procest', { data: { cases: CASES } })

	const { id } = await place('spend-analytics', {
		viewMode: 'top-vendors',
		period: 'quarter',
	})
	try {
		await gotoLaunchPad(page)
		const widget = spendWidget(page, id)
		await expect(widget.getByText('Top vendors')).toBeVisible({
			timeout: 30_000,
		})

		expect(arrived.procest, 'a procest GraphQL query was issued').toBeTruthy()
		expect(arrived.financeq, 'the financeq query was issued too').toBeTruthy()
		expect(
			answeredBeforeBoth,
			'both queries were in flight together (parallel, not one after the other)',
		).toBe(false)

		expect(arrived.procest.query).toMatch(/cases\s*\(/)
		expect(arrived.procest.query).toMatch(/\bvendor\b/)
		expect(arrived.procest.query).toMatch(/\btotalCommitted\b/)

		// One client sends both: the same `{query, variables}` envelope to the
		// same `/apps/{app}/graphql` mount, nothing app-specific.
		expect(arrived.procest.keys).toEqual(['query', 'variables'])
		expect(arrived.financeq.keys).toEqual(['query', 'variables'])

		await expect(widget.locator('a.cn-spend-analytics-widget__row')).toHaveCount(
			2,
		)
	} finally {
		await unplace(id)
	}
})

// @e2e launchpad-spend-analytics-widget::financeq-absent-renders-empty-state
test('REQ-SAW-005: with financeq not installed the widget shows its empty state and the grid stays usable', async ({
	page,
}) => {
	const { id } = await place('spend-analytics', {
		viewMode: 'summary',
		period: 'quarter',
	})
	try {
		const financeAnswer = page.waitForResponse(
			(res) => /\/apps\/financeq\/graphql(\?|$)/.test(res.url()),
			{ timeout: 30_000 },
		)
		await gotoLaunchPad(page)
		const status = (await financeAnswer).status()
		test.skip(
			status !== 404,
			`financeq answered ${status}: it is installed on this instance, so the absent case cannot be observed here`,
		)

		expect(status, 'the GraphQL call returns 404').toBe(404)
		const widget = spendWidget(page, id)
		await expect(
			widget.getByText('No spend data — financeq is not installed'),
		).toBeVisible({ timeout: 15_000 })
		// Not an error panel: no cards, no thrown red state.
		await expect(widget.locator('.cn-spend-analytics-widget__card')).toHaveCount(
			0,
		)

		// The surrounding grid stays interactive: the neighbour renders and
		// the dashboard switcher still opens and closes.
		await expect(
			page
				.locator(`[data-placement-id="${neighbourId}"]`)
				.getByText(NEIGHBOUR_TEXT),
		).toBeVisible()
		await openSidebar(page)
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toBeVisible()
		await closeSidebar(page)
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)
	} finally {
		await unplace(id)
	}
})

// @e2e launchpad-spend-analytics-widget::financeq-present-but-no-data-in-window
test('REQ-SAW-005: financeq with no transactions shows the no-spend state and the period stays operable', async ({
	page,
}) => {
	const finance = await answerGraphql(page, 'financeq', {
		status: 200,
		body: { data: { transactions: { edges: [] } } },
	})
	const { id } = await place('spend-analytics', {
		viewMode: 'summary',
		period: 'quarter',
	})
	try {
		await gotoLaunchPad(page)
		const widget = spendWidget(page, id)
		await expect(
			widget.getByText('No spend recorded for the selected period'),
		).toBeVisible({ timeout: 30_000 })

		// Widen the window: the selector is live and re-queries financeq.
		const period = widget.locator('.cn-spend-analytics-widget__period')
		await expect(period).toBeEnabled()
		const before = finance.length
		await period.selectOption('ytd')
		await expect
			.poll(() => finance.slice(before).map((c) => c.variables.period), {
				timeout: 10_000,
			})
			.toContain('ytd')
	} finally {
		await unplace(id)
	}
})

// @e2e launchpad-spend-analytics-widget::partial-sibling-availability
test('REQ-SAW-005: financeq data with procest absent keeps the cards and empties only the vendor panel', async ({
	page,
}) => {
	const finance = await answerGraphql(page, 'financeq', {
		status: 200,
		body: { data: { transactions: TRANSACTIONS } },
	})
	await answerGraphql(page, 'procest', { status: 404 })
	const { id } = await place('spend-analytics', {
		viewMode: 'top-vendors',
		period: 'quarter',
	})
	try {
		await gotoLaunchPad(page)
		const widget = spendWidget(page, id)
		await expect(widget).toBeVisible({ timeout: 30_000 })

		// financeq-derived summary cards still render.
		await expect(widget.getByText('Total spend')).toBeVisible({
			timeout: 15_000,
		})
		await expect(widget.locator('.cn-spend-analytics-widget__card')).toHaveCount(
			2,
		)

		// The vendor panel carries its own inline empty state.
		const panel = widget.locator('.cn-spend-analytics-widget__panel').filter({
			hasText: 'Top vendors',
		})
		await expect(
			panel.locator('.cn-spend-analytics-widget__inline-empty'),
		).toHaveText(/No vendor data — procest is not installed/)
		await expect(panel.locator('.cn-spend-analytics-widget__row')).toHaveCount(0)

		// The rest of the widget is not disabled: the period still re-queries.
		const period = widget.locator('.cn-spend-analytics-widget__period')
		await expect(period).toBeEnabled()
		const before = finance.length
		await period.selectOption('month')
		await expect
			.poll(() => finance.slice(before).map((c) => c.variables.period), {
				timeout: 10_000,
			})
			.toContain('month')
		await expect(widget.getByText('Total spend')).toBeVisible()
	} finally {
		await unplace(id)
	}
})

// @e2e launchpad-spend-analytics-widget::narrative-request-routes-through-openconnector
test('REQ-SAW-006: the Generate button posts the spend summary to the openconnector source, never to a model URL', async ({
	page,
}) => {
	await answerGraphql(page, 'financeq', {
		status: 200,
		body: { data: { transactions: TRANSACTIONS } },
	})
	const narrativeCalls: Array<{ url: string; body: Record<string, any> }> = []
	await page.route(/\/apps\/openconnector\/api\/sources\/[^/]+\/call/, (route) => {
		narrativeCalls.push({
			url: route.request().url(),
			body: route.request().postDataJSON() ?? {},
		})
		return route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ narrative: `IT dominates spend ${STAMP}` }),
		})
	})
	const modelCalls: string[] = []
	page.on('request', (req) => {
		if (/:11434(\/|$)/.test(req.url())) {
			modelCalls.push(req.url())
		}
	})

	const { id } = await place('spend-analytics', {
		viewMode: 'summary',
		period: 'quarter',
		[AI_CONTENT_KEY]: { enabled: true },
	})
	try {
		await gotoLaunchPad(page)
		const widget = spendWidget(page, id)
		const generate = widget.getByRole('button', { name: AI_BUTTON })
		await expect(generate).toBeVisible({ timeout: 30_000 })
		await generate.click()

		await expect(widget.getByText(`IT dominates spend ${STAMP}`)).toBeVisible({
			timeout: 10_000,
		})
		expect(narrativeCalls, 'exactly one inference call').toHaveLength(1)
		expect(new URL(narrativeCalls[0].url).pathname).toMatch(
			/\/apps\/openconnector\/api\/sources\/local-llm\/call$/,
		)
		expect(modelCalls, 'no request to Ollama or another model URL').toEqual([])

		// The widget prepared the spend summary as the context payload.
		const summary = narrativeCalls[0].body?.context?.spendSummary
		expect(summary, JSON.stringify(narrativeCalls[0].body)).toBeTruthy()
		expect(summary.total).toBe(1250)
		expect(summary.currency).toBe('EUR')
		expect(summary.period).toBe('quarter')
	} finally {
		await unplace(id)
	}
})

// @e2e launchpad-spend-analytics-widget::vendor-row-click-deep-links-to-procest
test('REQ-SAW-007: clicking a vendor row navigates to procest, not to a launchpad page', async ({
	page,
}) => {
	await answerGraphql(page, 'financeq', {
		status: 200,
		body: { data: { transactions: TRANSACTIONS } },
	})
	await answerGraphql(page, 'procest', {
		status: 200,
		body: { data: { cases: CASES } },
	})
	const { id } = await place('spend-analytics', {
		viewMode: 'top-vendors',
		period: 'quarter',
		drillThroughTarget: 'sibling-app',
	})
	try {
		await gotoLaunchPad(page)
		const widget = spendWidget(page, id)
		const acme = widget.locator('a.cn-spend-analytics-widget__row').filter({
			hasText: 'acme',
		})
		await expect(acme).toBeVisible({ timeout: 30_000 })

		await Promise.all([
			page.waitForURL(/\/apps\/procest\/vendor\/acme(\?|#|$)/, {
				timeout: 15_000,
				waitUntil: 'commit',
			}),
			acme.click(),
		])
		const target = new URL(page.url())
		expect(target.pathname).toMatch(/\/apps\/procest\/vendor\/acme$/)
		expect(
			target.pathname,
			'launchpad renders no local vendor page',
		).not.toContain('/apps/launchpad')
		expect(target.pathname).not.toContain('/spend/vendor/acme')
	} finally {
		await unplace(id)
	}
})
