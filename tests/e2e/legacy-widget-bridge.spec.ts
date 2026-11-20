/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the legacy widget bridge
 * (openspec/specs/legacy-widget-bridge), written for e2e-coverage-programme
 * task 2.9.
 *
 * The bridge is client-side, but it is not invisible to a browser: it owns
 * `window.OCA.Dashboard.register`, and LaunchPad's grid mounts whatever that
 * captured into a legacy placement's container. So each test does what a
 * pre-v2 Nextcloud widget bundle does — calls `OCA.Dashboard.register(id, cb)`
 * on the booted workspace page — and asserts what the grid then does with it.
 *
 * How a placement takes the legacy path: `WidgetRenderer` mounts a widget
 * through the bridge when the placement's widget descriptor (from
 * `GET /api/widgets`) has no `itemApiVersions`. The instance has no such
 * widget installed, so the descriptor list is extended in the browser with
 * two fake legacy descriptors; the placements themselves are real rows,
 * seeded through `POST /api/dashboard/{id}/widgets` as the admin (admins may
 * add any widget id, RoleFeaturePermissionService::isWidgetAllowed).
 *
 * The GIVEN clauses about what existed before the bridge was constructed are
 * made deterministic by prepending a few statements to the LaunchPad entry
 * bundle (`launchpad-main.js`), which runs them before any of its modules
 * evaluate, i.e. before the bridge singleton is constructed.
 *
 * Gate-19 @e2e traceability:
 *   @e2e legacy-widget-bridge::capture-a-regular-widget-callback
 *   @e2e legacy-widget-bridge::initialise-oca-namespace-when-missing
 *   @e2e legacy-widget-bridge::mount-a-registered-widget
 *   @e2e legacy-widget-bridge::registered-callback-throws-during-rendering
 *
 * The status-widget, query and polling scenarios are excluded in the spec with
 * their reasons: the bridge singleton is module-private, `mountStatusWidget`
 * and `pollForCallback` have no caller in src/, and the query methods only
 * steer WidgetRenderer internally.
 *
 * @spec openspec/specs/legacy-widget-bridge/spec.md
 */

import type {
	APIRequestContext,
	APIResponse,
	ConsoleMessage,
	Page,
} from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'
const RUN = Date.now()
const OK_ID = `e2e_legacy_ok_${RUN}`
const BROKEN_ID = `e2e_legacy_broken_${RUN}`
const OK_TITLE = `E2E legacy widget ${RUN}`
const TEXT_MARKER = `Legacy bridge neighbour ${RUN}`

/** The LaunchPad workspace entry bundle, under any webroot or cache-buster. */
const ENTRY_BUNDLE = '**/launchpad-main.js*'

/**
 * The upstream response's headers, minus the ones that describe the ORIGINAL
 * body: `route.fetch()` hands back a decoded body, so forwarding its
 * `content-encoding` / `content-length` would make the browser misread the
 * rewritten one.
 *
 * @param response the fetched upstream response.
 * @return headers safe to fulfill a rewritten body with.
 */
function rewrittenBodyHeaders(response: APIResponse): Record<string, string> {
	const headers = { ...response.headers() }
	delete headers['content-encoding']
	delete headers['content-length']
	return headers
}

let admin: APIRequestContext
let dashboardId = 0
let userDashboardsBefore: boolean | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * Prepend `prelude` to the LaunchPad entry bundle for this page.
 *
 * @param page the page, before its first navigation.
 * @param prelude plain statements to run before the bundle's modules.
 * @return nothing.
 */
async function prependToEntryBundle(page: Page, prelude: string): Promise<void> {
	await page.route(ENTRY_BUNDLE, async (route) => {
		const response = await route.fetch()
		const body = await response.text()
		await route.fulfill({
			status: response.status(),
			headers: rewrittenBodyHeaders(response),
			body: `${prelude}\n${body}`,
		})
	})
}

/**
 * Extend the available-widget catalogue with the two fake legacy widgets.
 * A descriptor without `itemApiVersions` is what routes a placement through
 * the bridge instead of the v1/v2 items API.
 *
 * @param page the page, before its first navigation.
 * @return nothing.
 */
async function offerLegacyDescriptors(page: Page): Promise<void> {
	await page.route(
		(url) => url.pathname.endsWith('/apps/launchpad/api/widgets'),
		async (route) => {
			const response = await route.fetch()
			const real = await response.json()
			const list = Array.isArray(real) ? real : []
			const legacy = (id: string, title: string) => ({
				id,
				title,
				order: 0,
				iconClass: 'icon-category-dashboard',
				iconUrl: '',
				widgetUrl: null,
				itemApiVersions: [],
			})
			await route.fulfill({
				status: response.status(),
				headers: rewrittenBodyHeaders(response),
				json: [
					...list,
					legacy(OK_ID, OK_TITLE),
					legacy(BROKEN_ID, 'E2E broken'),
				],
			})
		},
	)
}

/**
 * Open the workspace and wait until the legacy placement's mount container
 * is in the DOM (the renderer then retries the bridge for ~15 s).
 *
 * @param page the page.
 * @param widgetId the legacy placement's widget id.
 * @return the container locator.
 */
async function openLegacyContainer(page: Page, widgetId: string) {
	await page.goto('/index.php/apps/launchpad/')
	const container = page.locator(
		`[data-widget-id="${widgetId}"] .widget-renderer__legacy`,
	)
	await expect(container).toBeAttached({ timeout: 30_000 })
	return container
}

/**
 * Put a sentinel child into the container, so clearing is observable.
 *
 * @param page the page.
 * @param widgetId the legacy placement's widget id.
 * @return nothing.
 */
async function plantSentinel(page: Page, widgetId: string): Promise<void> {
	await page.evaluate((id) => {
		const el = document.querySelector(
			`[data-widget-id="${id}"] .widget-renderer__legacy`,
		)
		const sentinel = document.createElement('span')
		sentinel.className = 'e2e-sentinel'
		sentinel.textContent = 'stale content'
		el!.appendChild(sentinel)
	}, widgetId)
}

/**
 * Register a painting callback the way a legacy widget bundle does, recording
 * every invocation on `window.__e2eCalls`.
 *
 * @param page the page.
 * @param widgetId the id to register.
 * @return nothing.
 */
async function registerPaintingCallback(
	page: Page,
	widgetId: string,
): Promise<void> {
	await page.evaluate((id) => {
		const w = window as unknown as Record<string, any>
		w.__e2eCalls = []
		w.__e2eCb = function (container: HTMLElement, meta: any) {
			w.__e2eCalls.push({
				argc: arguments.length,
				childNodesAtCall: container.childNodes.length,
				isLegacyContainer: container.classList.contains(
					'widget-renderer__legacy',
				),
				placementWidgetId:
					(container.closest('[data-widget-id]') as HTMLElement | null)
						?.dataset.widgetId ?? null,
				metaKeys: meta ? Object.keys(meta) : null,
				widgetId: meta?.widget?.id ?? null,
				widgetTitle: meta?.widget?.title ?? null,
			})
			const p = document.createElement('p')
			p.className = 'e2e-painted'
			p.textContent = `painted by ${id}`
			container.appendChild(p)
		}
		w.OCA.Dashboard.register(id, w.__e2eCb)
	}, widgetId)
}

test.beforeAll(async () => {
	admin = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: {
			username: ADMIN.user,
			password: ADMIN.pass,
			send: 'always',
		},
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})

	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status(), await settings.text()).toBe(200)
	userDashboardsBefore = Boolean((await settings.json()).allowUserDashboards)
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBeLessThan(300)

	const created = await admin.post(`${API}/dashboard`, {
		data: { name: `E2E legacy bridge ${RUN}` },
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	dashboardId = Number(dash.id)
	expect(dashboardId, JSON.stringify(body)).toBeTruthy()

	const placements: Array<Record<string, unknown>> = [
		{ widgetId: OK_ID, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 4 },
		{ widgetId: BROKEN_ID, gridX: 4, gridY: 0, gridWidth: 4, gridHeight: 4 },
		{
			widgetId: 'text',
			gridX: 8,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 3,
			content: { text: TEXT_MARKER, contentMode: 'markdown' },
		},
	]
	for (const data of placements) {
		const placed = await admin.post(`${API}/dashboard/${dashboardId}/widgets`, {
			data,
		})
		expect(placed.status(), await placed.text()).toBe(201)
	}

	const activate = await admin.post(`${API}/dashboard/${dashboardId}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const preference = await admin.post(`${API}/dashboards/active`, {
		data: { uuid: dash.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	if (dashboardId !== 0) {
		await admin.delete(`${API}/dashboard/${dashboardId}`)
	}
	if (userDashboardsBefore !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: userDashboardsBefore },
		})
	}
	await admin.dispose()
})

// @e2e legacy-widget-bridge::capture-a-regular-widget-callback
test('a legacy register() call is captured for mounting and still reaches the registrar installed before the bridge', async ({
	page,
}) => {
	// GIVEN: a register/registerStatus pair already installed when the bridge
	// singleton is constructed (the prelude runs before the bundle's modules).
	await prependToEntryBundle(
		page,
		[
			'window.OCA = window.OCA || {};',
			'window.OCA.Dashboard = window.OCA.Dashboard || {};',
			'window.__e2ePrevRegister = [];',
			'window.__e2ePrevRegisterStatus = [];',
			'window.OCA.Dashboard.register = function (a, b) { window.__e2ePrevRegister.push([a, b]); };',
			'window.OCA.Dashboard.registerStatus = function (a, b) { window.__e2ePrevRegisterStatus.push([a, b]); };',
		].join('\n'),
	)
	await offerLegacyDescriptors(page)
	const container = await openLegacyContainer(page, OK_ID)

	// WHEN: the legacy app registers.
	await registerPaintingCallback(page, OK_ID)

	// THEN: the pair is stored in the bridge's widget map — observable because
	// the grid mounts exactly that callback into the placement.
	await expect(container.locator('.e2e-painted')).toHaveText(
		`painted by ${OK_ID}`,
		{
			timeout: 30_000,
		},
	)
	const calls = await page.evaluate(() => (window as any).__e2eCalls)
	expect(calls).toHaveLength(1)

	// AND: the previously installed register handler got the same arguments.
	const forwarded = await page.evaluate((id) => {
		const w = window as any
		const hits = w.__e2ePrevRegister.filter((c: unknown[]) => c[0] === id)
		return { count: hits.length, sameCallback: hits[0]?.[1] === w.__e2eCb }
	}, OK_ID)
	expect(forwarded).toEqual({ count: 1, sameCallback: true })

	// Not cited (the status map itself is unobservable), but the forwarding
	// half of the status-widget scenario is asserted here while we have a spy.
	const statusForwarded = await page.evaluate(() => {
		const w = window as any
		const cb = () => {}
		w.OCA.Dashboard.registerStatus('e2e_status', cb)
		const hits = w.__e2ePrevRegisterStatus.filter(
			(c: unknown[]) => c[0] === 'e2e_status',
		)
		return hits.length === 1 && hits[0][1] === cb
	})
	expect(statusForwarded).toBe(true)
})

// @e2e legacy-widget-bridge::initialise-oca-namespace-when-missing
// @e2e legacy-widget-bridge::mount-a-registered-widget
test('with no OCA.Dashboard at boot the bridge creates it, and a registered legacy widget is mounted into a cleared container', async ({
	page,
}) => {
	// GIVEN: window.OCA.Dashboard does not exist when the bridge is constructed.
	await prependToEntryBundle(
		page,
		[
			'if (window.OCA) { delete window.OCA.Dashboard; }',
			'window.__e2eDashboardAtEntry = Boolean(window.OCA && window.OCA.Dashboard);',
		].join('\n'),
	)
	await offerLegacyDescriptors(page)
	const container = await openLegacyContainer(page, OK_ID)

	// THEN: OCA and OCA.Dashboard exist, carrying the bridge's overrides — and
	// only the bridge's members, so nothing else created the namespace.
	const namespace = await page.evaluate(() => {
		const w = window as any
		const dash = w.OCA?.Dashboard
		return {
			missingAtEntry: w.__e2eDashboardAtEntry === false,
			isObject: typeof dash === 'object' && dash !== null,
			register: typeof dash?.register,
			registerStatus: typeof dash?.registerStatus,
			keys: dash ? Object.keys(dash).sort() : [],
		}
	})
	expect(namespace.missingAtEntry).toBe(true)
	expect(namespace.isObject).toBe(true)
	expect(namespace.register).toBe('function')
	expect(namespace.registerStatus).toBe('function')
	expect(namespace.keys).toEqual(
		['callbacks', 'getWidget', 'register', 'registerStatus', 'widgets'].sort(),
	)

	// GIVEN (mount scenario): the container already holds content.
	await plantSentinel(page, OK_ID)
	await expect(container.locator('.e2e-sentinel')).toHaveCount(1)

	// WHEN: the legacy widget registers after boot.
	await registerPaintingCallback(page, OK_ID)

	// AND (namespace scenario): the registration is still captured — the bridge
	// exposes it where the nc-widget renderer looks, and the grid mounts it.
	const exposed = await page.evaluate((id) => {
		const w = window as any
		return {
			callbacks: w.OCA.Dashboard.callbacks[id] === w.__e2eCb,
			widgets: w.OCA.Dashboard.widgets[id]?.callback === w.__e2eCb,
			getWidget: w.OCA.Dashboard.getWidget(id)?.callback === w.__e2eCb,
		}
	}, OK_ID)
	expect(exposed).toEqual({ callbacks: true, widgets: true, getWidget: true })

	// THEN: the container was cleared before the callback ran …
	await expect(container.locator('.e2e-painted')).toBeVisible({ timeout: 30_000 })
	await expect(container.locator('.e2e-sentinel')).toHaveCount(0)
	const calls = await page.evaluate(() => (window as any).__e2eCalls)
	expect(calls).toHaveLength(1)
	const [call] = calls
	expect(call.childNodesAtCall).toBe(0)

	// AND: … the callback was invoked as (containerEl, { widget: widgetMetadata })
	// with this placement's own container and its widget descriptor …
	expect(call.argc).toBe(2)
	expect(call.isLegacyContainer).toBe(true)
	expect(call.placementWidgetId).toBe(OK_ID)
	expect(call.metaKeys).toEqual(['widget'])
	expect(call.widgetId).toBe(OK_ID)
	expect(call.widgetTitle).toBe(OK_TITLE)

	// AND: … and what it painted is what the container shows.
	await expect(container).toHaveText(`painted by ${OK_ID}`)
})

// @e2e legacy-widget-bridge::registered-callback-throws-during-rendering
test('a legacy callback that throws is caught and logged with its id, and the rest of the dashboard still renders', async ({
	page,
}) => {
	const boom = `e2e-legacy-boom-${RUN}`
	const errors: ConsoleMessage[] = []
	const pageErrors: Error[] = []
	page.on('console', (msg) => {
		if (msg.type() === 'error') {
			errors.push(msg)
		}
	})
	page.on('pageerror', (err) => pageErrors.push(err))

	await offerLegacyDescriptors(page)
	await openLegacyContainer(page, BROKEN_ID)

	// GIVEN: a registered callback that throws on invocation.
	await page.evaluate(
		({ id, message }) => {
			const w = window as any
			w.__e2eBrokenCalls = 0
			w.OCA.Dashboard.register(id, () => {
				w.__e2eBrokenCalls += 1
				throw new Error(message)
			})
		},
		{ id: BROKEN_ID, message: boom },
	)

	// WHEN: the grid mounts it.
	await expect
		.poll(() => page.evaluate(() => (window as any).__e2eBrokenCalls), {
			timeout: 30_000,
		})
		.toBe(1)

	// THEN: the error is caught and logged together with the widget id.
	await expect
		.poll(
			() =>
				errors.some((msg) => {
					const text = msg.text()
					return (
						text.includes('[WidgetBridge] Error mounting legacy widget:')
						&& text.includes(BROKEN_ID)
					)
				}),
			{ timeout: 10_000 },
		)
		.toBe(true)

	// AND: it did not propagate — no uncaught page error carries it, and the
	// neighbouring widget on the same dashboard still renders.
	expect(pageErrors.filter((err) => err.message.includes(boom))).toEqual([])
	await expect(
		page.locator('.cn-text-widget').filter({ hasText: TEXT_MARKER }).first(),
	).toBeVisible({ timeout: 15_000 })
})
