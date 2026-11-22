/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for the `iframe` (embedded page) dashboard widget, written for
 * e2e-coverage-programme task 4.17. One verdict per scenario of
 * openspec/specs/iframe-embed-widget/spec.md.
 *
 * WHAT IS REAL AND WHAT IS STAGED
 * ===============================
 * The allow-list is the real `iframe_allowed_hosts` app value, written through
 * the provisioning API and put back afterwards. The CSP is read from the real
 * response header of the page. Placements are seeded over the placement API or
 * added through the Add Widget modal, and always removed with their dashboard.
 *
 * No test depends on the internet:
 *  - Hosts that must never resolve use the reserved `.invalid` TLD, so the
 *    server's framing probe (`POST /api/iframe/framable`) fails the same way on
 *    every runner and the widget shows its fallback card.
 *  - Tests that need a LIVE frame serve the target page with `page.route()`.
 *    The server's probe of such a host cannot reach it, so those tests also
 *    answer the probe in the browser with what the server answers for a target
 *    that permits framing. The probe itself is exercised un-stubbed by the
 *    X-Frame-Options test, against a fixture server this file starts.
 *  - That fixture server must be reachable by Nextcloud. Set
 *    IFRAME_FIXTURE_HOST (falls back to LIVE_TILE_FIXTURE_HOST, then
 *    127.0.0.1). A Nextcloud that refuses local addresses
 *    (`allow_local_remote_servers` off) cannot reach it; that test then skips
 *    and says so.
 *
 * Three tests are written to their scenario and are expected to FAIL against
 * the product: each carries a "SUSPECTED BUG" comment naming the file and line
 * that disagrees. They are left running (not fixme) on purpose, so the defect
 * stays visible until it is fixed.
 *
 * @spec openspec/specs/iframe-embed-widget/spec.md
 */

import type { APIRequestContext, Page, Route } from '@playwright/test'
import type { Server } from 'node:http'
import type { AddressInfo } from 'node:net'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { createServer } from 'node:http'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { closeSidebar, gotoLaunchPad, openSidebar } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

const API = '/index.php/apps/launchpad/api'
const HOSTS_CONFIG =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/iframe_allowed_hosts'
const FRAMABLE_ROUTE = '**/apps/launchpad/api/iframe/framable'
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? process.env.NEXTCLOUD_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? process.env.NEXTCLOUD_PASS ?? 'admin',
}
const FIXTURE_HOST =
	process.env.IFRAME_FIXTURE_HOST
	|| process.env.LIVE_TILE_FIXTURE_HOST
	|| '127.0.0.1'
const STAMP = Date.now().toString(36)

/** Never resolves (RFC 2606), so no runner can reach it. */
const STATUS_HOST = 'status.e2e.invalid'
const STATUS_URL = `https://${STATUS_HOST}/board`
const OTHER_HOST = 'other.e2e.invalid'
const EVIL_HOST = 'evil.e2e.invalid'

const BLOCKED_TEXT = 'This site does not allow itself to be embedded.'

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

let admin: APIRequestContext
let previousHosts: string | null = null
let fixture: Server
let fixtureOrigin = ''

/**
 * The current app value of the allow-list, or null when unset. Nextcloud
 * answers a missing key with an empty string, which this suite never writes.
 */
async function readHosts(): Promise<string | null> {
	const res = await admin.get(`${HOSTS_CONFIG}?format=json`)
	if (!res.ok()) {
		return null
	}
	const data = (await res.json())?.ocs?.data
	return typeof data?.data === 'string' && data.data !== '' ? data.data : null
}

/**
 * Write the allow-list, or delete the app value entirely when `hosts` is null
 * (the "no setting has been configured" state).
 *
 * @param hosts The hostnames, or null to unset the key.
 */
async function setHosts(hosts: string[] | null): Promise<void> {
	if (hosts === null) {
		const res = await admin.delete(`${HOSTS_CONFIG}?format=json`)
		expect(
			res.ok(),
			`unset iframe_allowed_hosts -> ${res.status()}`,
		).toBeTruthy()
		expect(await readHosts(), 'the key is gone').toBeNull()
		return
	}
	const res = await admin.post(`${HOSTS_CONFIG}?format=json`, {
		form: { value: JSON.stringify(hosts) },
	})
	expect(res.ok(), `set iframe_allowed_hosts -> ${res.status()}`).toBeTruthy()
}

/**
 * Seed an iframe placement over the placement API and return its id.
 *
 * @param dashboard The dashboard to place it on.
 * @param content The widget content blob.
 * @param gridY The row to place it on.
 */
async function seedIframe(
	dashboard: SeededDashboard,
	content: Record<string, unknown>,
	gridY = 0,
): Promise<number> {
	return seedPlacement(dashboard, 'iframe', content, gridY)
}

/**
 * Seed any placement and return its id.
 *
 * @param dashboard The dashboard to place it on.
 * @param widgetId The widget type.
 * @param content The widget content blob.
 * @param gridY The row to place it on.
 */
async function seedPlacement(
	dashboard: SeededDashboard,
	widgetId: string,
	content: Record<string, unknown>,
	gridY: number,
): Promise<number> {
	const res = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: { widgetId, gridX: 0, gridY, gridWidth: 6, gridHeight: 5, content },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(
		body.widget?.id ?? body.placement?.id ?? body.data?.id ?? body.id,
	)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/** The iframe placements currently stored on a dashboard. */
async function iframePlacements(
	dashboard: SeededDashboard,
): Promise<Array<Record<string, any>>> {
	const res = await admin.get(`${API}/dashboard/${dashboard.id}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const placements: Array<Record<string, any>> =
		body.placements ?? body.dashboard?.placements ?? []
	return placements.filter((p) => p.widgetId === 'iframe')
}

/** The source list of one CSP directive, from a raw header value. */
function directive(header: string | undefined, name: string): string[] {
	const part = (header ?? '')
		.split(';')
		.map((p) => p.trim())
		.find(
			(p) =>
				p.toLowerCase().startsWith(`${name} `) || p.toLowerCase() === name,
		)
	return part ? part.split(/\s+/).slice(1) : []
}

/** The `frame-src` sources of the document a navigation answered with. */
async function frameSrcOf(page: Page, path: string): Promise<string[]> {
	const res = await page.goto(path)
	expect(res, `no response for ${path}`).not.toBeNull()
	expect(res!.status(), `${path} answered ${res!.status()}`).toBeLessThan(400)
	return directive(res!.headers()['content-security-policy'], 'frame-src')
}

/**
 * Answer the server's framing probe in the browser with what it answers for a
 * target that permits framing (or refuses it, with `framable: false`).
 *
 * @param page The page.
 * @param framable The answer.
 */
async function answerProbe(page: Page, framable: boolean): Promise<void> {
	await page.unroute(FRAMABLE_ROUTE).catch(() => undefined)
	await page.route(FRAMABLE_ROUTE, (route) =>
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify(
				framable
					? { framable: true, reason: 'ok' }
					: { framable: false, reason: 'x_frame_options' },
			),
		}),
	)
}

/**
 * Serve a small HTML page for every request under `origin`.
 *
 * @param page The page.
 * @param origin The https origin to serve.
 * @param marker Text the served page carries.
 */
async function serveTarget(
	page: Page,
	origin: string,
	marker: string,
): Promise<void> {
	await page.route(`${origin}/**`, (route) =>
		route.fulfill({
			status: 200,
			contentType: 'text/html',
			body: `<!doctype html><html><body><h1>${marker}</h1></body></html>`,
		}),
	)
}

/** Open the LaunchPad start page and wait for one placement to mount. */
async function openDashboardWith(page: Page, placementId: number) {
	await gotoLaunchPad(page)
	const cell = page.locator(`[data-placement-id="${placementId}"]`)
	await expect(cell).toBeAttached({ timeout: 30_000 })
	return cell.locator('.iframe-widget')
}

/**
 * Open the Add Widget modal for one dashboard (sidebar row cog → "Add custom
 * widget…") with "Embedded page" chosen, and return the dialog.
 *
 * @param page The page, on the start page.
 * @param dashboard The dashboard whose row to use.
 */
async function openIframeForm(page: Page, dashboard: SeededDashboard) {
	await openSidebar(page)
	const row = page
		.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.filter({ hasText: dashboard.name })
		.first()
	await expect(row).toBeVisible({ timeout: 10_000 })
	await row.locator('.dashboard-row-actions button').first().click()
	await page.getByRole('menuitem', { name: /^add widget$/i }).click()
	await page.locator('[data-testid="widget-picker-type-iframe"]').click()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await expect(dialog.locator('.iframe-widget-form')).toBeVisible()
	return dialog
}

let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
	admin = await request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass, send: 'always' },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	previousHosts = await readHosts()

	fixture = createServer((req, res) => {
		const html = '<!doctype html><html><body><p>fixture</p></body></html>'
		if (req.url?.startsWith('/deny')) {
			res.writeHead(200, {
				'Content-Type': 'text/html',
				'X-Frame-Options': 'DENY',
			})
		} else if (req.url?.startsWith('/ancestors')) {
			res.writeHead(200, {
				'Content-Type': 'text/html',
				'Content-Security-Policy': "frame-ancestors 'none'",
			})
		} else {
			res.writeHead(404)
		}
		res.end(html)
	})
	await new Promise<void>((resolve) =>
		fixture.listen(0, '0.0.0.0', () => resolve()),
	)
	fixtureOrigin = `http://${FIXTURE_HOST}:${(fixture.address() as AddressInfo).port}`
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

test.afterAll(async () => {
	if (admin) {
		if (previousHosts === null) {
			await admin.delete(`${HOSTS_CONFIG}?format=json`).catch(() => undefined)
		} else {
			await admin
				.post(`${HOSTS_CONFIG}?format=json`, {
					form: { value: previousHosts },
				})
				.catch(() => undefined)
		}
		await admin.dispose()
	}
	await new Promise<void>((resolve) => fixture?.close(() => resolve()))
})

// ---------------------------------------------------------------------------
// REQ-IFRAME-001 / REQ-IFRAME-002: saving through the Add Widget modal
// ---------------------------------------------------------------------------

// @e2e iframe-embed-widget::config-stored-in-widgetcontent
// @e2e iframe-embed-widget::allowed-host-saves
test('an allow-listed URL saves through the modal and lands in the placement content', async ({
	page,
}) => {
	await setHosts([STATUS_HOST])
	const dashboard = await seedActiveDashboard(admin, `Iframe save ${STAMP}`)
	try {
		await gotoLaunchPad(page)
		const dialog = await openIframeForm(page, dashboard)
		const form = dialog.locator('.iframe-widget-form')

		await form.getByLabel('URL', { exact: true }).fill(STATUS_URL)
		await form.getByLabel('Title', { exact: true }).fill(`Status ${STAMP}`)
		// Leaving the URL field ran the server's allow-list check.
		await expect(form.getByText('This host is allow-listed.')).toBeVisible({
			timeout: 10_000,
		})
		await expect(form.locator('.iframe-widget-form__warning')).toHaveCount(0)

		// Aspect ratio 16:9, as in the scenario's example blob.
		await form.locator('.v-select .vs__dropdown-toggle').click()
		await page.getByRole('option', { name: '16:9', exact: true }).click()

		const add = dialog.getByRole('button', { name: /^add$/i })
		await expect(add).toBeEnabled({ timeout: 5_000 })
		await add.click()
		await expect(dialog).not.toBeVisible({ timeout: 10_000 })
		await closeSidebar(page)

		// The save succeeded: exactly one iframe placement, whose whole config
		// sits in the placement's content blob — the existing widget-content
		// column, so no migration or new column is involved.
		const placements = await iframePlacements(dashboard)
		expect(placements, JSON.stringify(placements)).toHaveLength(1)
		const content = placements[0].content
		expect(content).toEqual({
			url: STATUS_URL,
			title: `Status ${STAMP}`,
			height: 400,
			aspect: '16:9',
			sandbox: ['allow-scripts', 'allow-same-origin'],
			allowListChecked: true,
		})
	} finally {
		await removeSeededDashboard(admin, dashboard)
	}
})

// @e2e iframe-embed-widget::allow-list-default-is-empty-and-denies-all
test('with no allow-list configured every URL is refused', async ({ page }) => {
	await setHosts(null)
	const dashboard = await seedActiveDashboard(admin, `Iframe empty ${STAMP}`)
	try {
		// An empty list means "embed nothing": the server refuses any host.
		for (const url of [
			STATUS_URL,
			'https://example.org/',
			`http://${FIXTURE_HOST}/`,
		]) {
			const res = await admin.post(`${API}/iframe/validate-url`, {
				data: { config: { url, title: 'Any' } },
			})
			expect(res.status()).toBe(200)
			const verdict = await res.json()
			expect(verdict.valid, url).toBe(false)
			expect(verdict.errors, url).toContain('host_not_allowed')
		}

		await gotoLaunchPad(page)
		const dialog = await openIframeForm(page, dashboard)
		const form = dialog.locator('.iframe-widget-form')
		await form.getByLabel('URL', { exact: true }).fill(STATUS_URL)
		await form.getByLabel('Title', { exact: true }).fill('Status')

		await expect(form.locator('.iframe-widget-form__warning')).toHaveText(
			`The host ${STATUS_HOST} is not on the allow-list.`,
			{ timeout: 10_000 },
		)
		const add = dialog.getByRole('button', { name: /^add$/i })
		await expect(add).toBeDisabled()
		await expect(add).toHaveAttribute(
			'title',
			`The host ${STATUS_HOST} is not on the allow-list.`,
		)

		await dialog.getByRole('button', { name: /^cancel$/i }).click()
		await expect(dialog).not.toBeVisible({ timeout: 10_000 })
		expect(await iframePlacements(dashboard)).toHaveLength(0)
	} finally {
		await removeSeededDashboard(admin, dashboard)
	}
})

// @e2e iframe-embed-widget::disallowed-host-rejected-at-save
test('a host off the allow-list is refused at save, naming the host, and nothing is stored', async ({
	page,
}) => {
	await setHosts([STATUS_HOST])
	const dashboard = await seedActiveDashboard(admin, `Iframe evil ${STAMP}`)
	const evilUrl = `https://${EVIL_HOST}/`
	try {
		await gotoLaunchPad(page)
		const dialog = await openIframeForm(page, dashboard)
		const form = dialog.locator('.iframe-widget-form')
		await form.getByLabel('URL', { exact: true }).fill(evilUrl)
		await form.getByLabel('Title', { exact: true }).fill('Evil')

		const warning = form.locator('.iframe-widget-form__warning')
		await expect(warning).toBeVisible({ timeout: 10_000 })
		await expect(warning, 'the error names the disallowed host').toContainText(
			EVIL_HOST,
		)
		await expect(dialog.getByRole('button', { name: /^add$/i })).toBeDisabled()
		await dialog.getByRole('button', { name: /^cancel$/i }).click()
		expect(
			await iframePlacements(dashboard),
			'not created through the UI',
		).toHaveLength(0)

		// Create: the placement API itself refuses the host.
		const created = await admin.post(
			`${API}/dashboard/${dashboard.id}/widgets`,
			{
				data: {
					widgetId: 'iframe',
					gridX: 0,
					gridY: 0,
					gridWidth: 6,
					gridHeight: 5,
					content: {
						url: evilUrl,
						title: 'Evil',
						sandbox: ['allow-scripts'],
					},
				},
			},
		)
		expect(created.status(), await created.text()).toBeGreaterThanOrEqual(400)
		expect(await created.text()).toContain(EVIL_HOST)
		expect(
			await iframePlacements(dashboard),
			'not created over the API',
		).toHaveLength(0)

		// Update: an allowed placement cannot be moved to the host either.
		const id = await seedIframe(dashboard, {
			url: STATUS_URL,
			title: 'Status',
			sandbox: [],
		})
		const updated = await admin.put(`${API}/widgets/${id}`, {
			data: { content: { url: evilUrl, title: 'Status', sandbox: [] } },
		})
		expect(updated.status(), await updated.text()).toBeGreaterThanOrEqual(400)
		const after = await iframePlacements(dashboard)
		expect(after).toHaveLength(1)
		expect(after[0].content.url, 'the placement is not updated').toBe(STATUS_URL)
	} finally {
		await removeSeededDashboard(admin, dashboard)
	}
})

// @e2e iframe-embed-widget::host-removed-after-configuration-is-refused-at-render
test('a placement whose host was taken off the allow-list renders no frame', async ({
	page,
}) => {
	await setHosts([STATUS_HOST])
	const dashboard = await seedActiveDashboard(admin, `Iframe removed ${STAMP}`)
	try {
		const id = await seedIframe(dashboard, {
			url: STATUS_URL,
			title: `Removed ${STAMP}`,
			sandbox: ['allow-scripts'],
			allowListChecked: true,
		})
		await setHosts([OTHER_HOST])
		await serveTarget(page, `https://${STATUS_HOST}`, 'LIVE TARGET')

		const widget = await openDashboardWith(page, id)
		await expect(
			widget.getByText(
				'This embed is no longer permitted by your administrator',
			),
		).toBeVisible({ timeout: 30_000 })
		await expect(widget.locator('iframe')).toHaveCount(0)
	} finally {
		await removeSeededDashboard(admin, dashboard)
	}
})

// ---------------------------------------------------------------------------
// REQ-IFRAME-003: frame-src
// ---------------------------------------------------------------------------

// @e2e iframe-embed-widget::allow-listed-host-is-added-to-frame-src
// @e2e iframe-embed-widget::non-allow-listed-hosts-are-never-added
test('frame-src gains exactly the allow-listed host and never a wildcard', async ({
	page,
}) => {
	// Baseline: what frame-src holds with nothing allow-listed. Other parts of
	// the instance (Nextcloud's own 'self', the video widget's player origins
	// added by PageController::buildWorkspaceCsp) are in it either way.
	await setHosts([])
	const baseline = await frameSrcOf(page, '/index.php/apps/launchpad/')
	expect(baseline).not.toContain(`https://${STATUS_HOST}`)

	await setHosts([STATUS_HOST])
	const withHost = await frameSrcOf(page, '/index.php/apps/launchpad/')
	expect(withHost).toContain(`https://${STATUS_HOST}`)

	// What the allow-list contributed is the listed host and nothing else.
	const added = withHost.filter((source) => !baseline.includes(source))
	expect(added).toEqual([`https://${STATUS_HOST}`])
	for (const absent of [EVIL_HOST, OTHER_HOST]) {
		expect(withHost.join(' ')).not.toContain(absent)
	}
	// No wildcard source of any shape.
	expect(withHost.filter((source) => source.includes('*'))).toEqual([])
})

// SUSPECTED BUG: CspListener (lib/Listener/CspListener.php:97) adds the hosts through
// AddContentSecurityPolicyEvent, which Nextcloud merges into the default policy of
// every response of every app; the listener never checks which app is answering, so the
// instance CSP is loosened for unrelated apps too.
// @e2e iframe-embed-widget::csp-contribution-is-scoped-to-launchpad-pages
test('the allow-listed host is in frame-src on LaunchPad pages only', async ({
	page,
}) => {
	await setHosts([STATUS_HOST])
	expect(await frameSrcOf(page, '/index.php/apps/launchpad/')).toContain(
		`https://${STATUS_HOST}`,
	)
	for (const path of ['/index.php/settings/user']) {
		const sources = await frameSrcOf(page, path)
		expect(
			sources,
			`${path} must not carry the LaunchPad embed host`,
		).not.toContain(`https://${STATUS_HOST}`)
	}
})

// ---------------------------------------------------------------------------
// REQ-IFRAME-001 / 003 / 004: the frame itself
// ---------------------------------------------------------------------------

// @e2e iframe-embed-widget::a-power-bi-report-as-a-live-tile
test('a Power BI report renders live in a sandboxed frame and falls back when refused', async ({
	page,
}) => {
	const powerBi = 'app.powerbi.com'
	const reportUrl = `https://${powerBi}/reportEmbed?reportId=e2e-${STAMP}`
	const title = 'Rapport Sociaal domein'
	await setHosts([powerBi])
	const dashboard = await seedActiveDashboard(admin, `Management ${STAMP}`)
	try {
		const id = await seedIframe(dashboard, {
			url: reportUrl,
			title,
			height: 400,
			aspect: 'none',
			sandbox: ['allow-scripts', 'allow-same-origin'],
			allowListChecked: true,
		})
		await serveTarget(page, `https://${powerBi}`, `Report body ${STAMP}`)
		await answerProbe(page, true)

		// The page's own CSP lets the frame load.
		const res = await page.goto('/index.php/apps/launchpad/')
		expect(
			directive(res!.headers()['content-security-policy'], 'frame-src'),
		).toContain(`https://${powerBi}`)

		const widget = await openDashboardWith(page, id)
		const frame = widget.locator('iframe.iframe-widget__frame')
		await expect(frame).toBeVisible({ timeout: 30_000 })
		await expect(frame).toHaveAttribute('title', title)
		await expect(frame).toHaveAttribute('src', reportUrl)
		await expect(frame).toHaveAttribute(
			'sandbox',
			'allow-scripts allow-same-origin',
		)
		await expect(
			page
				.frameLocator(`[data-placement-id="${id}"] iframe`)
				.getByRole('heading'),
		).toHaveText(`Report body ${STAMP}`, { timeout: 15_000 })

		// Power BI refusing framing: the fallback, not an empty frame.
		await answerProbe(page, false)
		await page.reload()
		const fallback = page.locator(
			`[data-placement-id="${id}"] .iframe-widget__fallback`,
		)
		await expect(fallback).toBeVisible({ timeout: 30_000 })
		await expect(fallback).toContainText(title)
		const link = fallback.getByRole('link')
		await expect(link).toHaveText('Open in new tab')
		await expect(link).toHaveAttribute('href', reportUrl)
		await expect(page.locator(`[data-placement-id="${id}"] iframe`)).toHaveCount(
			0,
		)
	} finally {
		await removeSeededDashboard(admin, dashboard)
	}
})

// @e2e iframe-embed-widget::sandbox-attribute-is-always-present
test('the frame is always sandboxed with the toggled tokens and never allow-top-navigation', async ({
	page,
}) => {
	await setHosts([STATUS_HOST])
	const dashboard = await seedActiveDashboard(admin, `Iframe sandbox ${STAMP}`)
	try {
		// A placement written past the form, carrying the forbidden tokens.
		const tampered = await seedIframe(dashboard, {
			url: `${STATUS_URL}?tampered`,
			title: 'Tampered',
			sandbox: [
				'allow-scripts',
				'allow-top-navigation',
				'allow-top-navigation-by-user-activation',
			],
		})
		// A placement with every toggle off.
		const bare = await seedIframe(
			dashboard,
			{ url: `${STATUS_URL}?bare`, title: 'Bare', sandbox: [] },
			6,
		)
		await serveTarget(page, `https://${STATUS_HOST}`, 'Status board')
		await answerProbe(page, true)
		await gotoLaunchPad(page)

		// The author toggles: same-origin off, forms on. No top-navigation
		// toggle is offered at all.
		const dialog = await openIframeForm(page, dashboard)
		const form = dialog.locator('.iframe-widget-form')
		await form.getByLabel('URL', { exact: true }).fill(`${STATUS_URL}?toggled`)
		await form.getByLabel('Title', { exact: true }).fill('Toggled')
		const switches = form
			.locator('.iframe-widget-form__sandbox')
			.getByRole('switch')
		await expect(switches).toHaveCount(4)
		await expect(form.locator('.iframe-widget-form__sandbox')).not.toContainText(
			/top/i,
		)
		await form.getByText('Allow same-origin', { exact: true }).click()
		await form.getByText('Allow forms', { exact: true }).click()
		await expect(
			form.getByRole('switch', { name: 'Allow same-origin' }),
		).not.toBeChecked()
		await expect(form.getByRole('switch', { name: 'Allow forms' })).toBeChecked()
		await expect(form.getByText('This host is allow-listed.')).toBeVisible({
			timeout: 10_000,
		})
		const add = dialog.getByRole('button', { name: /^add$/i })
		await expect(add).toBeEnabled()
		await add.click()
		await expect(dialog).not.toBeVisible({ timeout: 10_000 })
		await closeSidebar(page)

		const stored = (await iframePlacements(dashboard)).find(
			(p) => p.content?.title === 'Toggled',
		)
		expect(stored, 'the toggled placement was saved').toBeTruthy()

		/** The sorted sandbox tokens of one placement's frame, once it is there. */
		const tokens = async (placementId: number): Promise<string[]> => {
			const frame = page.locator(`[data-placement-id="${placementId}"] iframe`)
			await expect(frame).toHaveAttribute('sandbox', /.*/, { timeout: 30_000 })
			const value = (await frame.getAttribute('sandbox')) ?? ''
			return value.split(/\s+/).filter(Boolean).sort()
		}

		expect(await tokens(Number(stored!.id))).toEqual([
			'allow-forms',
			'allow-scripts',
		])
		expect(await tokens(tampered)).toEqual(['allow-scripts'])
		await expect(
			page.locator(`[data-placement-id="${bare}"] iframe`),
		).toHaveAttribute('sandbox', '')
		expect(await tokens(bare)).toEqual([])

		// No frame on the page grants top navigation.
		const all = await page
			.locator('.iframe-widget iframe')
			.evaluateAll((frames) => frames.map((f) => f.getAttribute('sandbox')))
		expect(all.length).toBeGreaterThanOrEqual(3)
		for (const value of all) {
			expect(value, 'sandbox attribute present').not.toBeNull()
			expect(value).not.toContain('allow-top-navigation')
		}
	} finally {
		await removeSeededDashboard(admin, dashboard)
	}
})

// @e2e iframe-embed-widget::accessible-frame-title
test('the frame exposes the configured title to screen readers', async ({
	page,
}) => {
	await setHosts([STATUS_HOST])
	const dashboard = await seedActiveDashboard(admin, `Iframe title ${STAMP}`)
	const title = `Service status ${STAMP}`
	try {
		const id = await seedIframe(dashboard, {
			url: STATUS_URL,
			title,
			sandbox: ['allow-scripts'],
		})
		await serveTarget(page, `https://${STATUS_HOST}`, 'Status board')
		await answerProbe(page, true)

		const widget = await openDashboardWith(page, id)
		const frame = widget.locator('iframe')
		await expect(frame).toHaveAttribute('title', title, { timeout: 30_000 })
		// The accessible name a screen reader announces is that title.
		await expect(page.getByTitle(title, { exact: true })).toHaveCount(1)
		await expect(widget.getByTitle(title)).toBeVisible({ timeout: 15_000 })
	} finally {
		await removeSeededDashboard(admin, dashboard)
	}
})

// @e2e iframe-embed-widget::target-sends-x-frame-options-deny
test('a target that refuses framing gets the fallback card, not a blank frame', async ({
	page,
}) => {
	await setHosts([FIXTURE_HOST])
	const targets = [
		{
			url: `${fixtureOrigin}/deny`,
			title: `Denied ${STAMP}`,
			reason: 'x_frame_options',
		},
		{
			url: `${fixtureOrigin}/ancestors`,
			title: `Ancestors ${STAMP}`,
			reason: 'frame_ancestors',
		},
	]
	// The server must be able to reach the fixture to see its headers.
	for (const target of targets) {
		const probe = await admin.post(`${API}/iframe/framable`, {
			data: { url: target.url },
		})
		expect(probe.status()).toBe(200)
		const verdict = await probe.json()
		test.skip(
			verdict.reason === 'unreachable',
			`Nextcloud could not reach ${target.url}; set IFRAME_FIXTURE_HOST to an address it reaches, or allow local remote servers`,
		)
		expect(verdict).toEqual({ framable: false, reason: target.reason })
	}

	const dashboard = await seedActiveDashboard(admin, `Iframe deny ${STAMP}`)
	try {
		const ids: number[] = []
		for (const [i, target] of targets.entries()) {
			ids.push(
				await seedIframe(
					dashboard,
					{
						url: target.url,
						title: target.title,
						sandbox: ['allow-scripts'],
					},
					i * 6,
				),
			)
		}
		await gotoLaunchPad(page)
		for (const [i, target] of targets.entries()) {
			const cell = page.locator(`[data-placement-id="${ids[i]}"]`)
			const fallback = cell.locator('.iframe-widget__fallback')
			await expect(fallback).toBeVisible({ timeout: 30_000 })
			await expect(
				fallback.locator('.iframe-widget__fallback-title'),
			).toHaveText(target.title)
			await expect(
				fallback.locator('.iframe-widget__fallback-text'),
			).toContainText(BLOCKED_TEXT)
			const link = fallback.getByRole('link')
			await expect(link).toHaveText('Open in new tab')
			await expect(link).toHaveAttribute('href', target.url)
			await expect(link).toHaveAttribute('target', '_blank')
			// No silent blank frame left behind.
			await expect(cell.locator('iframe')).toHaveCount(0)
		}
	} finally {
		await removeSeededDashboard(admin, dashboard)
	}
})

// @e2e iframe-embed-widget::fallback-state-is-not-colour-only-and-is-keyboard-reachable
test('the fallback card carries an icon and text and its link works from the keyboard', async ({
	page,
}) => {
	await setHosts([STATUS_HOST])
	const dashboard = await seedActiveDashboard(admin, `Iframe keyboard ${STAMP}`)
	const title = `Unreachable ${STAMP}`
	try {
		// The host never resolves, so the server's probe fails and the card shows.
		const id = await seedIframe(dashboard, {
			url: STATUS_URL,
			title,
			sandbox: [],
		})
		await page.context().route(`https://${STATUS_HOST}/**`, (route: Route) =>
			route.fulfill({
				status: 200,
				contentType: 'text/html',
				body: '<!doctype html><title>opened</title><p>opened in a tab</p>',
			}),
		)

		await gotoLaunchPad(page)
		const fallback = page.locator(
			`[data-placement-id="${id}"] .iframe-widget__fallback`,
		)
		await expect(fallback).toBeVisible({ timeout: 30_000 })

		// An icon and a text label, not colour alone.
		await expect(fallback.locator('svg').first()).toBeVisible()
		await expect(fallback.locator('.iframe-widget__fallback-title')).toHaveText(
			title,
		)
		await expect(
			fallback.locator('.iframe-widget__fallback-text'),
		).not.toHaveText('')
		await expect(fallback).toHaveAttribute('role', 'status')

		// The link is focusable, says it opens a new tab, and Enter opens it.
		const link = fallback.getByRole('link', {
			name: `Open ${title} in a new tab`,
		})
		await expect(link).toBeVisible()
		expect(await link.getAttribute('tabindex')).not.toBe('-1')
		await link.focus()
		await expect(link).toBeFocused()
		const opened = page.context().waitForEvent('page')
		await page.keyboard.press('Enter')
		const tab = await opened
		await tab.waitForLoadState()
		expect(tab.url()).toBe(STATUS_URL)
		await expect(tab.getByText('opened in a tab')).toBeVisible()
		await tab.close()
		// The dashboard itself did not navigate away.
		expect(page.url()).toContain('/apps/launchpad')
	} finally {
		await page
			.context()
			.unroute(`https://${STATUS_HOST}/**`)
			.catch(() => undefined)
		await removeSeededDashboard(admin, dashboard)
	}
})

// @e2e iframe-embed-widget::loading-and-generic-error-states
test('a loading indicator shows first, and network or URL errors end in the fallback card', async ({
	page,
}) => {
	await setHosts([STATUS_HOST])
	const dashboard = await seedActiveDashboard(admin, `Iframe loading ${STAMP}`)
	const marker = `Still here ${STAMP}`
	try {
		const unreachable = await seedIframe(dashboard, {
			url: STATUS_URL,
			title: 'Network failure',
			sandbox: [],
		})
		const invalid = await seedIframe(
			dashboard,
			{ url: 'not a url', title: 'Invalid URL', sandbox: [] },
			6,
		)
		await seedPlacement(
			dashboard,
			'text',
			{ text: marker, contentMode: 'markdown' },
			12,
		)

		// Hold the server's probe until the loading state has been seen; the
		// real answer is passed through untouched.
		let release: () => void = () => undefined
		const held = new Promise<void>((resolve) => {
			release = resolve
		})
		await page.route(FRAMABLE_ROUTE, async (route) => {
			await held
			await route.continue()
		})
		const crashes: string[] = []
		page.on('pageerror', (error) => crashes.push(error.message))

		await gotoLaunchPad(page)
		for (const id of [unreachable, invalid]) {
			const state = page.locator(
				`[data-placement-id="${id}"] .iframe-widget__state`,
			)
			await expect(state).toBeVisible({ timeout: 30_000 })
			await expect(state).toContainText('Loading…')
			await expect(
				page.locator(`[data-placement-id="${id}"] .iframe-widget__fallback`),
			).toHaveCount(0)
		}
		release()

		for (const [id, title] of [
			[unreachable, 'Network failure'],
			[invalid, 'Invalid URL'],
		] as const) {
			const cell = page.locator(`[data-placement-id="${id}"]`)
			const fallback = cell.locator('.iframe-widget__fallback')
			await expect(fallback).toBeVisible({ timeout: 30_000 })
			await expect(fallback).toContainText(title)
			await expect(fallback.getByRole('link')).toHaveText('Open in new tab')
			await expect(cell.locator('.iframe-widget__state')).toHaveCount(0)
			await expect(cell.locator('iframe')).toHaveCount(0)
		}
		// The rest of the dashboard still renders.
		await expect(page.getByText(marker)).toBeVisible()
		await expect(page.locator(MORE_MENU)).toBeVisible()
		expect(
			crashes.filter((m) => /iframe/i.test(m)),
			'no iframe-widget error reached the page',
		).toEqual([])
	} finally {
		await removeSeededDashboard(admin, dashboard)
	}
})
