/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the Prometheus metrics and health endpoints
 * (openspec/specs/prometheus-metrics), written for e2e-coverage-programme
 * task 5.7.
 *
 * `GET /api/metrics` and `GET /api/health` are HTTP surfaces, so most tests
 * here read them over HTTP: the exact status, the exact Content-Type, the
 * metric names, HELP/TYPE lines, labels and values. The counts are proven to
 * be live by seeding a dashboard, an admin template and a widget placement and
 * watching the matching sample move by exactly one. The admin-only rule is
 * probed by an ordinary logged-in account, and the admin succeeds in the same
 * test. The two Health panel scenarios drive the admin page's Operations tab.
 *
 * Scenarios that need a broken database, an empty instance, a missing app
 * version, OpenRegister absent or tens of thousands of rows carry an
 * `@e2e exclude` with the reason in the spec instead.
 *
 * Gate-19 @e2e traceability:
 *   @e2e prometheus-metrics::metrics-endpoint-returns-valid-prometheus-format
 *   @e2e prometheus-metrics::metrics-endpoint-requires-admin-authentication
 *   @e2e prometheus-metrics::metrics-endpoint-accessible-without-csrf-token
 *   @e2e prometheus-metrics::metric-names-types-and-labels-are-preserved
 *   @e2e prometheus-metrics::info-metric-reports-versions
 *   @e2e prometheus-metrics::info-metric-reads-app-version-from-config
 *   @e2e prometheus-metrics::up-metric-when-healthy
 *   @e2e prometheus-metrics::up-metric-always-returns-1-if-endpoint-is-reachable
 *   @e2e prometheus-metrics::dashboard-counts-by-type
 *   @e2e prometheus-metrics::widget-placement-count
 *   @e2e prometheus-metrics::healthy-status
 *   @e2e prometheus-metrics::health-check-is-reachable-without-a-login-session
 *   @e2e prometheus-metrics::concurrent-scrapes
 *   @e2e prometheus-metrics::a-healthy-app
 *   @e2e prometheus-metrics::a-degraded-app
 *
 * @spec openspec/specs/prometheus-metrics/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, request, test } from '@playwright/test'
import { adminTab } from './support/adminTab.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	adminContext,
	allowPersonalDashboards,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const METRICS = `${API}/metrics`
const HEALTH = `${API}/health`
const CONTENT_TYPE = 'text/plain; version=0.0.4; charset=utf-8'
const SETTINGS_PAGE = `${BASE_URL}/index.php/settings/admin/launchpad`
const STAMP = Date.now()

/** The HELP and TYPE lines every metric family must keep (REQ-PROM-001). */
const FAMILIES: Array<{ name: string; help: string; type: string }> = [
	{ name: 'launchpad_info', help: 'Application information', type: 'gauge' },
	{ name: 'launchpad_up', help: 'Whether the application is up', type: 'gauge' },
	{ name: 'launchpad_dashboards_total', help: 'Total dashboards by type', type: 'gauge' },
	{ name: 'launchpad_widgets_total', help: 'Total number of widget placements', type: 'gauge' },
	{ name: 'launchpad_tiles_total', help: 'Total number of tiles', type: 'gauge' },
]

/** One sample line of the Prometheus 0.0.4 text format: name, optional labels, value. */
const SAMPLE_LINE = /^([a-zA-Z_:][a-zA-Z0-9_:]*)(\{(?:[a-zA-Z_][a-zA-Z0-9_]*="(?:[^"\\]|\\.)*"(?:,[a-zA-Z_][a-zA-Z0-9_]*="(?:[^"\\]|\\.)*")*)?\})? (-?(?:\d+(?:\.\d+)?(?:[eE][+-]?\d+)?|NaN|\+Inf|-Inf))$/

let admin: APIRequestContext
let member: Principal | null = null
const createdDashboards: number[] = []
const createdTemplates: number[] = []

interface Scrape {
	status: number
	contentType: string
	body: string
}

/**
 * Read the metrics endpoint once.
 *
 * @param api the context to read it as.
 * @return the status, the Content-Type header and the body.
 */
async function scrape(api: APIRequestContext): Promise<Scrape> {
	const res = await api.get(METRICS, { maxRedirects: 0 })
	return {
		status: res.status(),
		contentType: res.headers()['content-type'] ?? '',
		body: await res.text(),
	}
}

/**
 * Read the metrics as the admin and insist on a 200.
 *
 * @return the body.
 */
async function adminMetrics(): Promise<string> {
	const result = await scrape(admin)
	expect(result.status, result.body).toBe(200)
	return result.body
}

/**
 * The value of one sample, or null when the body has no such sample.
 *
 * @param body a metrics body.
 * @param metric the metric name.
 * @param labels the exact label block, e.g. `{type="user"}`, or '' for none.
 * @return the numeric value, or null.
 */
function sampleValue(body: string, metric: string, labels = ''): number | null {
	const prefix = `${metric}${labels} `
	const line = body.split('\n').find((candidate) => candidate.startsWith(prefix))
	if (line === undefined) {
		return null
	}
	return Number(line.slice(prefix.length))
}

/**
 * Fail unless every line of a body is valid Prometheus 0.0.4 text exposition:
 * HELP and TYPE comments, then samples of the family they announce.
 *
 * @param body a metrics body.
 * @return the metric family names, in order.
 */
function assertExpositionFormat(body: string): string[] {
	const families: string[] = []
	const typed = new Set<string>()
	const helped = new Set<string>()
	for (const line of body.split('\n')) {
		if (line === '') {
			continue
		}
		const help = /^# HELP ([a-zA-Z_:][a-zA-Z0-9_:]*) .*$/.exec(line)
		if (help) {
			helped.add(help[1])
			families.push(help[1])
			continue
		}
		const type = /^# TYPE ([a-zA-Z_:][a-zA-Z0-9_:]*) (counter|gauge|histogram|summary|untyped)$/.exec(line)
		if (type) {
			typed.add(type[1])
			continue
		}
		const sample = SAMPLE_LINE.exec(line)
		expect(sample, `not a Prometheus sample line: ${JSON.stringify(line)}`).not.toBeNull()
		const family = sample![1]
		expect(helped.has(family), `${family} has a sample before its HELP line`).toBe(true)
		expect(typed.has(family), `${family} has a sample before its TYPE line`).toBe(true)
	}
	for (const family of families) {
		expect(typed.has(family), `${family} has HELP but no TYPE`).toBe(true)
	}
	return families
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	admin = await adminContext()
	await assertActingAs(admin, ADMIN_USER)
	member = await createPrincipal(admin, 'e2e-prom-member')
	await assertActingAs(member.api, member.username)
})

test.afterAll(async () => {
	for (const id of createdDashboards) {
		await admin.delete(`${API}/dashboard/${id}`).catch(() => undefined)
		await member?.api.delete(`${API}/dashboard/${id}`).catch(() => undefined)
	}
	for (const id of createdTemplates) {
		await admin.delete(`${API}/admin/templates/${id}`).catch(() => undefined)
	}
	await removePrincipal(admin, member)
	await admin.dispose()
})

// @e2e prometheus-metrics::metrics-endpoint-returns-valid-prometheus-format
test('an admin reads the metrics as Prometheus 0.0.4 text', async () => {
	const result = await scrape(admin)

	expect(result.status, result.body).toBe(200)
	expect(result.contentType).toBe(CONTENT_TYPE)
	const families = assertExpositionFormat(result.body)
	expect(families.length, 'the body holds no metric family at all').toBeGreaterThan(0)
})

// @e2e prometheus-metrics::metrics-endpoint-requires-admin-authentication
test('an ordinary logged-in account is refused the metrics and the admin is not', async () => {
	const refused = await scrape(member!.api)
	expect(refused.status, refused.body).toBe(403)
	expect(refused.body, 'a refusal must expose no metric').not.toContain('launchpad_')
	expect(refused.body).not.toContain('# HELP')
	expect(refused.body).not.toContain('# TYPE')

	// The same request as the admin succeeds, so the 403 is the rule and not a broken endpoint.
	const allowed = await scrape(admin)
	expect(allowed.status, allowed.body).toBe(200)
	expect(allowed.body).toContain('launchpad_up 1')
})

// @e2e prometheus-metrics::metrics-endpoint-accessible-without-csrf-token
test('a session-authenticated read without a CSRF token still returns the metrics', async ({ page }) => {
	// `page.request` carries the admin's browser SESSION cookie (global setup)
	// and nothing else: no requesttoken, no OCS-APIRequest header, no basic
	// auth. That is the exact request Nextcloud's CSRF check would refuse on a
	// route without #[NoCSRFRequired].
	const res = await page.request.get(`${BASE_URL}${METRICS}`, { maxRedirects: 0 })
	const body = await res.text()

	expect(res.status(), body).toBe(200)
	expect(res.headers()['content-type']).toBe(CONTENT_TYPE)
	expect(body).toContain('# TYPE launchpad_up gauge')
	expect(body).toContain('launchpad_up 1')
})

// @e2e prometheus-metrics::metric-names-types-and-labels-are-preserved
test('every metric keeps its name, HELP, TYPE and labels, and the body ends with a newline', async () => {
	const body = await adminMetrics()

	for (const family of FAMILIES) {
		expect(body).toContain(`# HELP ${family.name} ${family.help}\n# TYPE ${family.name} ${family.type}\n`)
	}
	expect(body).toMatch(/^launchpad_info\{version="[^"]*",php_version="[^"]*",nextcloud_version="[^"]*"\} 1$/m)
	expect(body).toMatch(/^launchpad_up 1$/m)
	expect(body).toMatch(/^launchpad_dashboards_total\{type="[^"]+"\} \d+$/m)
	expect(body).toMatch(/^launchpad_widgets_total \d+$/m)
	expect(body).toMatch(/^launchpad_tiles_total \d+$/m)
	expect(body.endsWith('\n'), 'the body must end with a newline').toBe(true)
})

// @e2e prometheus-metrics::info-metric-reports-versions
test('the info metric reports the app, PHP and Nextcloud versions with value 1', async () => {
	const body = await adminMetrics()

	expect(body).toContain('# HELP launchpad_info Application information\n# TYPE launchpad_info gauge\n')
	const info = /^launchpad_info\{version="([^"]*)",php_version="([^"]*)",nextcloud_version="([^"]*)"\} (\S+)$/m.exec(body)
	expect(info, 'no launchpad_info sample with the three version labels').not.toBeNull()
	const [, version, phpVersion, nextcloudVersion, value] = info!

	const app = await admin.get('/ocs/v1.php/cloud/apps/launchpad?format=json')
	expect(app.status(), await app.text()).toBe(200)
	expect(version).toBe(String((await app.json()).ocs.data.version))

	expect(phpVersion).toMatch(/^\d+\.\d+\.\d+/)
	const status = await (await admin.get('/status.php')).json()
	expect(nextcloudVersion).toBe(String(status.version))

	expect(value).toBe('1')
})

// @e2e prometheus-metrics::info-metric-reads-app-version-from-config
test('the info metric reads the installed app version and the system version', async () => {
	const body = await adminMetrics()
	const info = /^launchpad_info\{version="([^"]*)",php_version="([^"]*)",nextcloud_version="([^"]*)"\} 1$/m.exec(body)
	expect(info, 'no launchpad_info sample').not.toBeNull()

	// IConfig::getAppValue('launchpad', 'installed_version')
	const installed = await admin.get(
		'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/installed_version?format=json',
	)
	expect(installed.status(), await installed.text()).toBe(200)
	expect(info![1]).toBe(String((await installed.json()).ocs.data.data))

	// PHP_VERSION: three numeric parts, possibly with a distribution suffix.
	expect(info![2]).toMatch(/^\d+\.\d+\.\d+\S*$/)

	// IConfig::getSystemValueString('version'), which /status.php reports verbatim.
	const status = await (await admin.get('/status.php')).json()
	expect(info![3]).toBe(String(status.version))
})

// @e2e prometheus-metrics::up-metric-when-healthy
test('the up metric is a gauge with value 1 on a healthy app', async () => {
	const health = await admin.get(HEALTH)
	expect(health.status(), await health.text()).toBe(200)
	expect((await health.json()).status).toBe('ok')

	const body = await adminMetrics()
	expect(body).toContain('# HELP launchpad_up Whether the application is up\n# TYPE launchpad_up gauge\nlaunchpad_up 1\n')
})

// @e2e prometheus-metrics::up-metric-always-returns-1-if-endpoint-is-reachable
test('every reachable scrape reports up as exactly 1', async () => {
	for (let i = 0; i < 3; i++) {
		const body = await adminMetrics()
		const lines = body.split('\n').filter((line) => /^launchpad_up[ {]/.test(line))
		expect(lines).toEqual(['launchpad_up 1'])
	}
})

// @e2e prometheus-metrics::dashboard-counts-by-type
test('a new personal dashboard and a new admin template each move their own type count by one', async () => {
	await allowPersonalDashboards(admin, true)
	const before = await adminMetrics()
	expect(before).toContain('# HELP launchpad_dashboards_total Total dashboards by type\n# TYPE launchpad_dashboards_total gauge\n')
	const usersBefore = sampleValue(before, 'launchpad_dashboards_total', '{type="user"}') ?? 0
	const templatesBefore = sampleValue(before, 'launchpad_dashboards_total', '{type="admin_template"}') ?? 0

	const dash = await member!.api.post(`${API}/dashboard`, { data: { name: `E2E metrics dashboard ${STAMP}` } })
	expect(dash.status(), await dash.text()).toBeLessThan(300)
	const dashBody = await dash.json()
	createdDashboards.push(Number((dashBody.dashboard ?? dashBody.data?.dashboard ?? dashBody).id))

	const template = await admin.post(`${API}/admin/templates`, { data: { name: `E2E metrics template ${STAMP}` } })
	expect(template.status(), await template.text()).toBe(201)
	const templateBody = await template.json()
	createdTemplates.push(Number((templateBody.data ?? templateBody).id))

	const after = await adminMetrics()
	expect(after).toContain('# HELP launchpad_dashboards_total Total dashboards by type\n# TYPE launchpad_dashboards_total gauge\n')
	expect(sampleValue(after, 'launchpad_dashboards_total', '{type="user"}')).toBe(usersBefore + 1)
	expect(sampleValue(after, 'launchpad_dashboards_total', '{type="admin_template"}')).toBe(templatesBefore + 1)
})

// @e2e prometheus-metrics::widget-placement-count
test('a new widget placement moves the widget count by one', async () => {
	await allowPersonalDashboards(admin, true)
	const dash = await member!.api.post(`${API}/dashboard`, { data: { name: `E2E metrics widgets ${STAMP}` } })
	expect(dash.status(), await dash.text()).toBeLessThan(300)
	const dashBody = await dash.json()
	const dashboardId = Number((dashBody.dashboard ?? dashBody.data?.dashboard ?? dashBody).id)
	createdDashboards.push(dashboardId)

	const before = await adminMetrics()
	const widgetsBefore = sampleValue(before, 'launchpad_widgets_total')
	expect(widgetsBefore, 'no launchpad_widgets_total sample').not.toBeNull()

	const widget = await member!.api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			content: { text: 'metrics', contentMode: 'markdown' },
		},
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)

	const after = await adminMetrics()
	expect(after).toContain('# HELP launchpad_widgets_total Total number of widget placements\n# TYPE launchpad_widgets_total gauge\n')
	expect(sampleValue(after, 'launchpad_widgets_total')).toBe(widgetsBefore! + 1)
})

// @e2e prometheus-metrics::healthy-status
test('the health check answers 200 with the ADR-006 shape and a passing database check', async () => {
	const res = await admin.get(HEALTH)
	expect(res.status(), await res.text()).toBe(200)
	expect(res.headers()['content-type']).toContain('application/json')

	const body = await res.json()
	expect(body.status).toBe('ok')
	expect(body.app).toBe('launchpad')
	expect(typeof body.version).toBe('string')
	expect(body.version).not.toBe('')
	expect(body.checks.database).toBe('ok')
})

// @e2e prometheus-metrics::health-check-is-reachable-without-a-login-session
test('an anonymous probe with no session and no CSRF token gets an answer, not a login redirect', async () => {
	const probe = await request.newContext({ baseURL: BASE_URL, storageState: undefined })
	try {
		const res = await probe.get(HEALTH, { maxRedirects: 0 })
		const text = await res.text()

		expect(res.status(), text).toBe(200)
		expect(res.headers().location, 'the probe was redirected').toBeUndefined()
		expect(text).not.toContain('/login')
		const body = JSON.parse(text)
		expect(body.status).toBe('ok')
		expect(body.app).toBe('launchpad')
	} finally {
		await probe.dispose()
	}
})

// @e2e prometheus-metrics::concurrent-scrapes
test('overlapping scrapes all complete with the same metrics', async () => {
	const results = await Promise.all(Array.from({ length: 4 }, () => scrape(admin)))

	for (const result of results) {
		expect(result.status, result.body).toBe(200)
		expect(result.contentType).toBe(CONTENT_TYPE)
		assertExpositionFormat(result.body)
		expect(result.body).toContain('launchpad_up 1')
	}
	// Nothing writes between the scrapes, so a lock or a half-filled cache
	// would show up as one body differing from the others.
	for (const result of results.slice(1)) {
		expect(result.body).toBe(results[0].body)
	}
})

// @e2e prometheus-metrics::a-healthy-app
test('the Operations tab says Healthy when the health check answers ok', async ({ page }) => {
	const health = await admin.get(HEALTH)
	expect((await health.json()).status).toBe('ok')

	await page.goto(SETTINGS_PAGE, { waitUntil: 'domcontentloaded' })
	await adminTab(page, 'operations').click({ timeout: 30_000 })
	const badge = page.locator('[data-test="health-panel"] [data-test="health-badge"]')
	await expect(badge).toHaveText('Healthy', { timeout: 30_000 })
})

// @e2e prometheus-metrics::a-degraded-app
test('the Operations tab says Degraded when the health check reports the database unreachable', async ({ page }) => {
	// The database cannot be taken down under a shared instance, so the panel
	// is handed the answer the health check gives when it is: HTTP 503 with a
	// failing database check. The panel, its request and its wording are real.
	await page.route('**/apps/launchpad/api/health', (route) =>
		route.fulfill({
			status: 503,
			contentType: 'application/json',
			body: JSON.stringify({
				status: 'error',
				app: 'launchpad',
				version: 'e2e',
				checks: { database: 'error' },
			}),
		}),
	)

	await page.goto(SETTINGS_PAGE, { waitUntil: 'domcontentloaded' })
	await adminTab(page, 'operations').click({ timeout: 30_000 })
	const badge = page.locator('[data-test="health-panel"] [data-test="health-badge"]')
	await expect(badge).toHaveText('Degraded', { timeout: 30_000 })
})
