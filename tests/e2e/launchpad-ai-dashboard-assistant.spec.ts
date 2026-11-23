/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The `ai-assistant` widget: ask Hermiq about the dashboard.
 *
 * Written for e2e-coverage-programme task 4.16 (capability
 * launchpad-ai-dashboard-assistant), replacing the spec-level exclusion that
 * said a live answer needs a running Hermiq with a model.
 *
 * WHY page.route() AND NOT A REAL HERMIQ
 * =====================================
 * The widget calls Hermiq FROM THE BROWSER: `GET /apps/hermiq/api/chat/health`
 * on mount, then `POST /apps/hermiq/api/chat/stream` (nc-vue's
 * useAiChatStream, an SSE stream of `token` frames ending in `final`). Every
 * byte launchpad sends or reads therefore crosses the page, and page.route()
 * can stand in for Hermiq and its model: it records the request the widget
 * really sent and answers with a scripted SSE body. What these tests prove is
 * launchpad's side of the contract — where the question goes, what the body
 * carries, how the answer renders — not which model Hermiq would pick.
 *
 * Every test seeds its own dashboard (made active for the admin) holding the
 * placements it needs, over the API, and deletes it afterwards.
 */

import type { APIRequestContext, Page, Request, Route } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import { closeSidebar, gotoLaunchPad, openSidebar } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const API = '/index.php/apps/launchpad/api'
const STAMP = `${Date.now()}`

const HEALTH = /\/apps\/hermiq\/api\/chat\/health/
const STREAM = /\/apps\/hermiq\/api\/chat\/stream/
const ANY_HERMIQ = /\/apps\/hermiq\//

const WIDGET = '[data-testid="ai-assistant"]'
const INPUT = '[data-testid="ai-assistant-input"]'
const SEND = '[data-testid="ai-assistant-send"]'
const SUMMARISE = '[data-testid="ai-assistant-summarise"]'

/*
 * The admin over basic auth, with an explicitly empty jar so the project's
 * stored browser session is not sent along.
 */
async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
	})
}

interface Placement {
	widgetId: string
	gridX: number
	gridY: number
	gridWidth: number
	gridHeight: number
	content: Record<string, unknown>
}

/**
 * Seed an active dashboard holding the given placements.
 *
 * @param api the admin API context.
 * @param name the dashboard name.
 * @param placements the placements to add.
 * @return the seeded dashboard.
 */
async function seedDashboard(
	api: APIRequestContext,
	name: string,
	placements: Placement[],
): Promise<SeededDashboard> {
	const dashboard = await seedActiveDashboard(api, name)
	for (const placement of placements) {
		const added = await api.post(`${API}/dashboard/${dashboard.id}/widgets`, {
			data: placement,
		})
		expect(added.status(), await added.text()).toBeLessThan(300)
	}
	return dashboard
}

/** An assistant placement of `w` by `h` cells. */
function assistant(w: number, h: number, agentUuid = ''): Placement {
	return {
		widgetId: 'ai-assistant',
		gridX: 0,
		gridY: 0,
		gridWidth: w,
		gridHeight: h,
		content: { agentUuid },
	}
}

/** Answer Hermiq's health probe with 200. */
async function hermiqHealthy(page: Page): Promise<void> {
	await page.route(HEALTH, (route) =>
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: '{"status":"ok"}',
		}),
	)
}

/** An SSE body: one `token` frame per delta, then `final`. */
function sse(deltas: string[], turn: number): string {
	const frames = deltas.map(
		(delta) => `event: token\ndata: ${JSON.stringify({ delta })}\n\n`,
	)
	frames.push(
		`event: final\ndata: ${JSON.stringify({ messageId: `m-${STAMP}-${turn}`, conversationUuid: `c-${STAMP}` })}\n\n`,
	)
	return frames.join('')
}

/** Fulfil a stream request with the given token deltas. */
async function answer(route: Route, deltas: string[], turn: number): Promise<void> {
	await route.fulfill({
		status: 200,
		headers: {
			'Content-Type': 'text/event-stream',
			'Cache-Control': 'no-cache',
		},
		body: sse(deltas, turn),
	})
}

/**
 * Stand in for Hermiq's model: every stream request is recorded and answered
 * with `Answer <n>` split over two token frames.
 *
 * @param page the page.
 * @return the recorded request bodies, in order.
 */
async function scriptedHermiq(page: Page): Promise<Array<Record<string, unknown>>> {
	const bodies: Array<Record<string, unknown>> = []
	await hermiqHealthy(page)
	await page.route(STREAM, async (route) => {
		bodies.push(route.request().postDataJSON())
		const n = bodies.length
		await answer(route, ['Answer ', `${n}`], n)
	})
	return bodies
}

/**
 * Type a question and send it with the widget's own Ask button.
 *
 * @param page the page.
 * @param question the question.
 */
async function ask(page: Page, question: string): Promise<void> {
	const widget = page.locator(WIDGET)
	await widget.getByLabel('Your question').fill(question)
	await widget.locator(SEND).click()
}

let api: APIRequestContext

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	api = await adminApi()
})

test.afterAll(async () => {
	await api.dispose()
})

// @e2e launchpad-ai-dashboard-assistant::widget-registered-and-discoverable
test('REQ-ADA-001: ai-assistant is a listed widget type the Add Widget modal offers with a form and a renderer', async ({
	page,
}) => {
	// lib/widget-types.json is the list the PHP side reads (it is not served),
	// so it is read from the checkout the instance runs.
	const types = JSON.parse(
		fs.readFileSync(
			path.resolve(__dirname, '../../lib/widget-types.json'),
			'utf8',
		),
	).types as string[]
	expect(types).toContain('ai-assistant')

	const name = `E2E assistant add ${STAMP}`
	let dashboard: SeededDashboard | null = null
	try {
		dashboard = await seedDashboard(api, name, [])
		await hermiqHealthy(page)
		await gotoLaunchPad(page)

		// listWidgetTypes() is what the modal's type picker offers.
		await openSidebar(page)
		const row = page.locator(
			`.dashboard-switcher-sidebar__item[data-source="user"][aria-label="${name}"]`,
		)
		await expect(row).toBeVisible({ timeout: 10_000 })
		await row.locator('.dashboard-row-actions button').first().click()
		await page.getByRole('menuitem', { name: /^add widget$/i }).click()
		await page.locator('[data-testid="widget-picker-type-ai-assistant"]').click()
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await expect(dialog).toBeVisible({ timeout: 10_000 })

		// ...with a form: the assistant's own settings form appears.
		await expect(dialog.getByLabel('Hermiq agent (optional)')).toBeVisible()
		await expect(dialog).toContainText(
			"Leave empty to use Hermiq's default agent.",
		)

		const add = dialog.getByRole('button', { name: /^add$/i })
		await expect(add).toBeEnabled({ timeout: 5_000 })
		await add.click()
		await expect(dialog).not.toBeVisible({ timeout: 10_000 })
		await closeSidebar(page)

		// ...and a renderer: the placement renders the assistant on the grid.
		const widget = page.locator(WIDGET)
		await expect(widget).toBeVisible({ timeout: 15_000 })
		await expect(widget.getByLabel('Your question')).toBeVisible()
	} finally {
		await removeSeededDashboard(api, dashboard)
	}
})

// @e2e launchpad-ai-dashboard-assistant::question-streams-through-hermiq
// @e2e launchpad-ai-dashboard-assistant::no-tools-from-launchpad
test('REQ-ADA-004/005: a question goes to Hermiq with the dashboard as context, streams in, and sends no tools', async ({
	page,
}) => {
	let dashboard: SeededDashboard | null = null
	try {
		dashboard = await seedDashboard(api, `E2E assistant stream ${STAMP}`, [
			assistant(4, 4),
		])
		await hermiqHealthy(page)

		// Hold the stream open until the test has seen the in-progress turn.
		const sent: Request[] = []
		let release: () => void = () => {}
		const held = new Promise<void>((resolve) => {
			release = resolve
		})
		await page.route(STREAM, async (route) => {
			sent.push(route.request())
			await held
			await answer(route, ['Two cases ', 'are ', 'overdue.'], 1)
		})

		await gotoLaunchPad(page)
		const widget = page.locator(WIDGET)
		await expect(widget.locator(INPUT)).toBeEnabled({ timeout: 15_000 })

		await ask(page, 'Which of my cases are overdue?')

		// While the stream is open the turn is in progress: the question is in
		// the conversation, an assistant bubble is waiting and the input is shut.
		await expect.poll(() => sent.length, { timeout: 10_000 }).toBe(1)
		const messages = widget.locator('.ai-assistant__messages')
		await expect(messages).toHaveAttribute('aria-live', 'polite')
		await expect(messages.locator('.ai-assistant__message--user')).toHaveText([
			'Which of my cases are overdue?',
		])
		await expect(
			messages.locator('.ai-assistant__message--assistant'),
		).toHaveText(['Thinking…'])
		await expect(widget.locator(INPUT)).toBeDisabled()

		release()

		// The answer is the token frames put together, so it came from the stream.
		await expect(
			messages.locator('.ai-assistant__message--assistant'),
		).toHaveText(['Two cases are overdue.'], {
			timeout: 10_000,
		})
		await expect(widget.locator(INPUT)).toBeEnabled()

		// The request went to Hermiq's stream endpoint, with the dashboard as context.
		const req = sent[0]
		expect(req.method()).toBe('POST')
		expect(new URL(req.url()).pathname).toMatch(
			/\/apps\/hermiq\/api\/chat\/stream$/,
		)
		const body = req.postDataJSON() as Record<string, unknown>
		const pathname = await page.evaluate(() => window.location.pathname)
		expect(body.message).toBe('Which of my cases are overdue?')
		expect(body.context).toEqual(
			expect.objectContaining({
				appId: 'launchpad',
				pageKind: 'dashboard',
				objectUuid: dashboard.uuid,
				route: pathname,
			}),
		)

		// REQ-ADA-005: message, context and agentUuid, and no tools field.
		expect(Object.keys(body)).toEqual(
			expect.arrayContaining(['message', 'context', 'agentUuid']),
		)
		expect(body).not.toHaveProperty('tools')
	} finally {
		await removeSeededDashboard(api, dashboard)
	}
})

// @e2e launchpad-ai-dashboard-assistant::default-agent
test('REQ-ADA-002: a placement with an empty agentUuid asks with agentUuid "" and shows the default agent\'s answer', async ({
	page,
}) => {
	let dashboard: SeededDashboard | null = null
	try {
		dashboard = await seedDashboard(api, `E2E assistant agent ${STAMP}`, [
			assistant(4, 4, ''),
		])
		const bodies = await scriptedHermiq(page)
		await gotoLaunchPad(page)
		const widget = page.locator(WIDGET)
		await expect(widget.locator(INPUT)).toBeEnabled({ timeout: 15_000 })

		await ask(page, 'What is on this dashboard?')
		await expect(widget.locator('.ai-assistant__message--assistant')).toHaveText(
			['Answer 1'],
			{ timeout: 10_000 },
		)

		expect(bodies).toHaveLength(1)
		expect(bodies[0]).toHaveProperty('agentUuid', '')
	} finally {
		await removeSeededDashboard(api, dashboard)
	}
})

// @e2e launchpad-ai-dashboard-assistant::hermiq-absent
test('REQ-ADA-004: without Hermiq the widget offers no input and says why, and the rest of the dashboard works', async ({
	page,
}) => {
	const labelText = `Still here ${STAMP}`
	let dashboard: SeededDashboard | null = null
	try {
		dashboard = await seedDashboard(api, `E2E assistant absent ${STAMP}`, [
			assistant(4, 4),
			{
				widgetId: 'label',
				gridX: 4,
				gridY: 0,
				gridWidth: 4,
				gridHeight: 2,
				content: {
					text: labelText,
					fontSize: '16px',
					color: '',
					backgroundColor: '',
					fontWeight: 'bold',
				},
			},
		])
		// The health probe does not answer 2xx.
		await page.route(HEALTH, (route) =>
			route.fulfill({ status: 404, body: 'Not Found' }),
		)
		const streamed: string[] = []
		page.on('request', (r) => {
			if (STREAM.test(r.url())) {
				streamed.push(r.url())
			}
		})

		await gotoLaunchPad(page)
		const widget = page.locator(WIDGET)
		await expect(
			widget.locator('[data-testid="ai-assistant-unavailable"]'),
		).toHaveText(
			'The assistant needs the Hermiq app, which is not available here.',
			{ timeout: 15_000 },
		)
		await expect(widget.locator(INPUT)).toHaveCount(0)
		await expect(widget.locator(SEND)).toHaveCount(0)
		await expect(widget.locator(SUMMARISE)).toHaveCount(0)
		expect(streamed).toEqual([])

		// The rest of the dashboard: the other widget renders, and the
		// dashboard switcher still opens and closes.
		await expect(
			page.locator('.cn-label-widget').filter({ hasText: labelText }),
		).toBeVisible()
		await openSidebar(page)
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toBeVisible()
		await closeSidebar(page)
	} finally {
		await removeSeededDashboard(api, dashboard)
	}
})

// @e2e launchpad-ai-dashboard-assistant::large-placement-renders-full-chat
test('REQ-ADA-006: a 4 by 4 placement shows the question input and the conversation, one turn per question', async ({
	page,
}) => {
	let dashboard: SeededDashboard | null = null
	try {
		dashboard = await seedDashboard(api, `E2E assistant large ${STAMP}`, [
			assistant(4, 4),
		])
		const bodies = await scriptedHermiq(page)
		await gotoLaunchPad(page)
		const widget = page.locator(WIDGET)

		await expect(widget.getByLabel('Your question')).toBeVisible({
			timeout: 15_000,
		})
		await expect(widget.locator(INPUT)).toBeEnabled()
		await expect(widget.locator('.ai-assistant__messages')).toBeAttached()
		await expect(widget.locator(SUMMARISE)).toHaveCount(0)

		await ask(page, 'First question')
		await expect(widget.locator('.ai-assistant__message--assistant')).toHaveText(
			['Answer 1'],
			{ timeout: 10_000 },
		)
		await ask(page, 'Second question')
		await expect(widget.locator('.ai-assistant__message--assistant')).toHaveText(
			['Answer 1', 'Answer 2'],
			{
				timeout: 10_000,
			},
		)

		await expect(widget.locator('.ai-assistant__message--user')).toHaveText([
			'First question',
			'Second question',
		])
		await expect(widget.locator('.ai-assistant__messages')).toBeVisible()
		expect(bodies.map((b) => b.message)).toEqual([
			'First question',
			'Second question',
		])
	} finally {
		await removeSeededDashboard(api, dashboard)
	}
})

// @e2e launchpad-ai-dashboard-assistant::small-placement-renders-summary-mode
test('REQ-ADA-006: a 2 by 2 placement summarises the dashboard on each click and shows the answer in the cell', async ({
	page,
}) => {
	let dashboard: SeededDashboard | null = null
	try {
		dashboard = await seedDashboard(api, `E2E assistant small ${STAMP}`, [
			assistant(2, 2),
		])
		const bodies = await scriptedHermiq(page)
		await gotoLaunchPad(page)
		const widget = page.locator(WIDGET)

		const button = widget.getByRole('button', {
			name: 'Summarise this dashboard',
		})
		await expect(button).toBeEnabled({ timeout: 15_000 })
		await expect(widget.locator(INPUT)).toHaveCount(0)

		await button.click()
		await expect(widget.locator('.ai-assistant__summary')).toHaveText(
			'Answer 1',
			{ timeout: 10_000 },
		)
		expect(bodies).toHaveLength(1)
		expect(bodies[0].message).toBe('Summarise this dashboard for me.')

		// Each click asks again, and the cell shows the latest answer.
		await expect(button).toBeEnabled()
		await button.click()
		await expect(widget.locator('.ai-assistant__summary')).toHaveText(
			'Answer 2',
			{ timeout: 10_000 },
		)
		expect(bodies).toHaveLength(2)
		expect(bodies[1].message).toBe('Summarise this dashboard for me.')
	} finally {
		await removeSeededDashboard(api, dashboard)
	}
})

// @e2e launchpad-ai-dashboard-assistant::history-scoped-to-session
test('REQ-ADA-007: after three turns a reload starts the widget empty and asks for no history', async ({
	page,
}) => {
	let dashboard: SeededDashboard | null = null
	try {
		dashboard = await seedDashboard(api, `E2E assistant history ${STAMP}`, [
			assistant(4, 4),
		])
		await scriptedHermiq(page)
		await gotoLaunchPad(page)
		const widget = page.locator(WIDGET)
		await expect(widget.locator(INPUT)).toBeEnabled({ timeout: 15_000 })

		for (const n of [1, 2, 3]) {
			await ask(page, `Question ${n}`)
			await expect(
				widget.locator('.ai-assistant__message--assistant').last(),
			).toHaveText(`Answer ${n}`, {
				timeout: 10_000,
			})
			await expect(widget.locator(INPUT)).toBeEnabled()
		}
		await expect(widget.locator('.ai-assistant__message')).toHaveCount(6)

		// Record every request after the reload: Hermiq may only be probed for
		// health, and nothing may ask any backend for chat history.
		const after: string[] = []
		page.on('request', (r) => after.push(r.url()))
		await page.reload()
		await expect(widget.locator(INPUT)).toBeEnabled({ timeout: 20_000 })
		await expect(widget.locator('.ai-assistant__message')).toHaveCount(0)

		const hermiq = after.filter((url) => ANY_HERMIQ.test(url))
		expect(hermiq.length).toBeGreaterThan(0)
		expect(hermiq.filter((url) => !HEALTH.test(url))).toEqual([])
		expect(
			after.filter((url) =>
				/chat\/history|ai\/conversation|assistant\/messages|\/conversations/.test(
					url,
				),
			),
		).toEqual([])
	} finally {
		await removeSeededDashboard(api, dashboard)
	}
})
