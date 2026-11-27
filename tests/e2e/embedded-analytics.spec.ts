/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for embedded analytics (openspec change
 * `embedded-analytics`, task 35): an embed token issued in Admin settings ▸
 * Embeds, the public render route framed by another website, its CSP and
 * framing headers, read-only enforcement, filter pinning, tenant theming,
 * rate limiting and revocation.
 *
 * HOW THE EMBED IS FRAMED
 * =======================
 * An embed is only meaningful inside a page from ANOTHER origin, so this file
 * starts two tiny host pages on 127.0.0.1 (random ports). `/` is an <iframe>
 * on the render route; `/sdk` loads the SDK bundle the instance serves
 * (`/embed-sdk/embed-sdk.umd.js`, built by `npm run build:embed-sdk`, which
 * `npm run build` runs) and renders through `LaunchPadEmbed.render()`. The
 * first host is listed in the tokens' `hostOrigins`, the second never is. Only the browser loads them; Nextcloud never calls back,
 * so no fixture host has to be reachable from the server. The pages are
 * viewed from an EMPTY browser context: an embed is anonymous, and the admin
 * session the suite logs in with must not be what makes it render.
 *
 * WHAT IS SEEDED
 * ==============
 * One dashboard (with a text widget added to its default placements) and one
 * tenant theme, both over the admin API and removed afterwards. Every test
 * issues its own token, because the rate-limit bucket and the revocation
 * state belong to a token. There is no delete route for tokens, so every
 * token this file issues is revoked in `afterAll`.
 *
 * WHAT THE TESTS RELY ON
 * ======================
 * - A drill-down is a visitor opening a tile in an embed whose scope allows
 *   `drillDown`; the embed relays it to the host page as a `drillDown`
 *   event. The public grid has no filter widgets, so no `filterApplied`
 *   event exists to test; the filter interaction the product has, request
 *   filters on the data route limited to `allowedFilters` under the pinned
 *   context, is tested.
 * - Read-only enforcement (REQ-EMB-004 4.1) is a 403 `read_only_token` on a
 *   public write route, here `POST /s/{token}/unlock`, where LaunchPad's
 *   embed middleware runs; on a route that needs a login Nextcloud answers
 *   401 to an embed Bearer token before LaunchPad runs, and nothing is
 *   written.
 *
 * Gate-19 @e2e traceability:
 *   @e2e embedded-analytics::11-create-token-and-receive-jwt-once
 *   @e2e embedded-analytics::12-subsequent-get-returns-metadata-but-not-jwt
 *   @e2e embedded-analytics::21-valid-non-expired-token-renders-widget
 *   @e2e embedded-analytics::22-subject-mismatch-returns-403-without-leaking-existence
 *   @e2e embedded-analytics::25-invalid-jwt-signature-returns-401
 *   @e2e embedded-analytics::26-missing-token-returns-401
 *   @e2e embedded-analytics::31-valid-origin-renders-with-csp-frame-ancestors-header
 *   @e2e embedded-analytics::32-invalid-origin-blocked-by-csp-server-side-sec-fetch-site-check
 *   @e2e embedded-analytics::35-x-frame-options-fallback-for-legacy-browsers
 *   @e2e embedded-analytics::41-read-mode-token-rejects-write-operation
 *   @e2e embedded-analytics::47-scope-enforcement-independent-of-frontend-state
 *   @e2e embedded-analytics::410-api-middleware-integration-point
 *   @e2e embedded-analytics::49-filter-context-pinning-persisted-with-token
 *   @e2e embedded-analytics::51-css-variables-injected-into-root
 *   @e2e embedded-analytics::61-sdk-initialization-and-iframe-creation
 *   @e2e embedded-analytics::62-postmessage-handshake-for-token-injection
 *   @e2e embedded-analytics::67-umd-build-for-vanilla-js
 *   @e2e embedded-analytics::71-token-bucket-rate-limiting-with-burst
 *   @e2e embedded-analytics::73-429-response-format
 *   @e2e embedded-analytics::710-rate-limit-headers-in-all-responses
 *   @e2e embedded-analytics::93-render-route-workers-reject-revoked-token-within-60-seconds
 *   @e2e embedded-analytics::910-soft-revocation-can-be-undone
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */

import type { APIRequestContext, APIResponse, Browser, BrowserContext, Page } from '@playwright/test'
import type { Server } from 'node:http'
import type { AddressInfo } from 'node:net'

import { expect, request, test } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { createServer } from 'node:http'
import { BASE_URL } from './support/baseUrl.ts'
import { adminContext, API } from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const SETTINGS_PAGE = `${BASE_URL}/index.php/apps/launchpad/admin`
const STAMP = Date.now().toString(36)
const TEXT_MARKER = `Embed marker ${STAMP}`

/** Colours of the seeded theme; text and primary pass 4.5:1 on the surface. */
const THEME = {
	'--c-primary': '#0b3d91',
	'--c-surface': '#fdf6e3',
	'--c-text': '#1a1a1a',
	'--c-border': '#8a8a8a',
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/** A host page server on 127.0.0.1. */
interface HostPage {
	origin: string
	server: Server
}

/** An issued token: its record id and the JWT shown once. */
interface Issued {
	id: string
	jwt: string
}

let admin: APIRequestContext
let anon: APIRequestContext
let dashboard: { id: number; uuid: string } | null = null
let themeId: string | null = null
let allowedHost: HostPage
let foreignHost: HostPage
const issuedIds: string[] = []

/**
 * Escape a value for a double-quoted HTML attribute.
 *
 * @param value the raw value.
 * @return the escaped value.
 */
function attr(value: string): string {
	return value
		.replace(/&/g, '&amp;')
		.replace(/"/g, '&quot;')
		.replace(/</g, '&lt;')
}

/**
 * The SDK host page: loads the UMD bundle from `?sdk=`, renders the embed
 * described by the JSON in `?config=` into `#host`, and records the render
 * outcome in `window.embedState` and every `drillDown` payload in
 * `window.embedEvents`.
 */
const SDK_PAGE = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>SDK host page</title></head>'
	+ '<body><div id="host" style="width:900px;height:700px"></div>'
	+ '<script>window.embedEvents = []; window.embedState = "loading";</script>'
	+ '<script src="{SDK}"></script>'
	+ '<script>'
	+ 'const config = JSON.parse(new URLSearchParams(location.search).get("config"));'
	+ 'window.LaunchPadEmbed.render(document.getElementById("host"), config).then('
	+ '(embed) => { window.embedState = "ready"; embed.on("drillDown", (payload) => window.embedEvents.push(payload)); },'
	+ '(failure) => { window.embedState = "failed: " + failure.message; });'
	+ '</script></body></html>'

/**
 * Start a host page on 127.0.0.1 at a random port. `GET /?src=<url>` answers
 * a page holding one iframe on that url, as a website embedding LaunchPad
 * would; `GET /sdk?sdk=<url>&config=<json>` embeds through the SDK.
 *
 * @return the server and the origin it serves.
 */
async function startHostPage(): Promise<HostPage> {
	const server = createServer((req, res) => {
		const url = new URL(req.url ?? '/', 'http://127.0.0.1')
		res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' })
		if (url.pathname === '/sdk') {
			res.end(SDK_PAGE.replace('{SDK}', attr(url.searchParams.get('sdk') ?? '')))
			return
		}
		const src = url.searchParams.get('src') ?? ''
		res.end(
			'<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Host page</title></head>'
				+ `<body><iframe id="embed" title="Embedded dashboard" width="900" height="700" src="${attr(src)}"></iframe></body></html>`,
		)
	})
	await new Promise<void>((resolve) => server.listen(0, '127.0.0.1', resolve))
	const { port } = server.address() as AddressInfo
	return { origin: `http://127.0.0.1:${port}`, server }
}

/**
 * Stop a host page server.
 *
 * @param host the host page, or undefined when it never started.
 * @return nothing.
 */
async function stopHostPage(host: HostPage | undefined): Promise<void> {
	if (host) {
		await new Promise<void>((resolve) => host.server.close(() => resolve()))
	}
}

/**
 * The render route path of the seeded dashboard.
 *
 * @param suffix appended to the path, e.g. `/data`.
 * @return the path below the instance root.
 */
function embedPath(suffix = ''): string {
	return `${APP}/embed/dashboard/${dashboard?.uuid}${suffix}`
}

/**
 * The absolute render URL of the seeded dashboard with a query token.
 *
 * @param jwt the token.
 * @return the URL an iframe loads.
 */
function renderUrl(jwt: string): string {
	return `${BASE_URL}${embedPath()}?token=${encodeURIComponent(jwt)}`
}

/**
 * Issue a token for the seeded dashboard over the admin API.
 *
 * @param overrides fields that replace the read-only defaults.
 * @return the token's id and JWT.
 */
async function issueToken(overrides: Record<string, unknown> = {}): Promise<Issued> {
	const res = await admin.post(`${API}/embed-tokens`, {
		data: {
			name: `e2e embed ${STAMP} ${issuedIds.length}`,
			description: 'Issued by embedded-analytics.spec.ts',
			subject: { type: 'dashboard', id: dashboard?.uuid },
			scope: { mode: 'read', allowedFilters: [], allowedActions: [] },
			hostOrigins: [allowedHost.origin],
			...overrides,
		},
	})
	const text = await res.text()
	expect(res.status(), `POST /api/embed-tokens: ${text}`).toBe(201)
	const body = JSON.parse(text)
	issuedIds.push(String(body.id))
	expect(body.jwt, text).toMatch(/^[\w-]+\.[\w-]+\.[\w-]+$/)
	return { id: String(body.id), jwt: String(body.jwt) }
}

/**
 * Load the data of the seeded dashboard with a Bearer token, as the SDK and
 * the embed page do.
 *
 * @param jwt the token.
 * @param query extra query parameters.
 * @return the response.
 */
async function loadData(jwt: string, query: Record<string, string> = {}): Promise<APIResponse> {
	const search = new URLSearchParams(query).toString()
	return anon.get(`${embedPath('/data')}${search ? `?${search}` : ''}`, {
		headers: { Authorization: `Bearer ${jwt}` },
	})
}

/**
 * A browser page with no session, on a host page framing `src`.
 *
 * @param browser the browser.
 * @param host the host page to open.
 * @param src the iframe's url.
 * @return the context (the caller closes it) and the loaded page.
 */
async function openHostPage(
	browser: Browser,
	host: HostPage,
	src: string,
): Promise<{ context: BrowserContext; page: Page }> {
	const context = await browser.newContext({ storageState: { cookies: [], origins: [] } })
	const page = await context.newPage()
	await page.goto(`${host.origin}/?src=${encodeURIComponent(src)}`)
	return { context, page }
}

test.beforeAll(async () => {
	admin = await adminContext()
	anon = await request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
	})
	allowedHost = await startHostPage()
	foreignHost = await startHostPage()

	const created = await admin.post(`${API}/dashboard`, { data: { name: `Embed e2e ${STAMP}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboard = { id: Number(dash.id), uuid: String(dash.uuid) }

	const widget = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: { widgetId: 'text', gridX: 0, gridY: 4, gridWidth: 6, gridHeight: 2, content: { text: TEXT_MARKER } },
	})
	expect(widget.status(), await widget.text()).toBe(201)

	const theme = await admin.post(`${API}/tenant-themes`, {
		data: { name: `e2e theme ${STAMP}`, cssVariables: THEME },
	})
	expect(theme.status(), await theme.text()).toBeLessThan(300)
	themeId = String((await theme.json()).id)
})

test.afterAll(async () => {
	for (const id of issuedIds) {
		await admin.post(`${API}/embed-tokens/${id}/revoke`, { data: { reason: 'no_longer_needed' } })
	}
	if (themeId) {
		await admin.delete(`${API}/tenant-themes/${themeId}?force=true`)
	}
	if (dashboard) {
		await admin.delete(`${API}/dashboard/${dashboard.id}`)
	}
	await stopHostPage(allowedHost)
	await stopHostPage(foreignHost)
	await anon?.dispose()
	await admin?.dispose()
})

// @e2e embedded-analytics::11-create-token-and-receive-jwt-once
// @e2e embedded-analytics::12-subsequent-get-returns-metadata-but-not-jwt
test('an admin issues an embed token in Admin settings and sees its JWT once', async ({ page }) => {
	const name = `e2e UI token ${STAMP}`
	const query = new URLSearchParams({
		tab: 'embeds',
		embedType: 'dashboard',
		embedId: String(dashboard?.uuid),
		embedDashboard: String(dashboard?.id),
	})
	// REQ-ADM-032: the embeds are a card of Algemeen; the old tab address
	// opens it with the dashboard's pre-selection kept.
	await page.goto(`${SETTINGS_PAGE}/embeds?${query}`)
	await expect(page).toHaveURL((url) => /\/admin(\/general)?$/.test(url.pathname)
		&& url.searchParams.get('section') === 'embeds'
		&& url.searchParams.get('embedType') === 'dashboard')

	const form = page.locator('[data-test="embed-token-form"]')
	await expect(form, 'a dashboard link opens the create form').toBeVisible({ timeout: 30_000 })
	await form.getByLabel('Name', { exact: true }).fill(name)
	await form.getByLabel('Origin, e.g. https://www.example.com').fill(allowedHost.origin)
	await form.getByRole('button', { name: 'Add', exact: true }).click()
	await expect(form.locator('[data-test="embed-token-origins"]')).toContainText(allowedHost.origin)
	await form.getByRole('button', { name: 'Create token' }).click()

	const jwt = page.locator('[data-test="embed-token-jwt"]')
	const failure = page.locator('[data-test="embed-token-error"]')
	await expect(jwt.or(failure)).toBeVisible({ timeout: 30_000 })
	await expect(failure, 'the manager reported a failed save').toHaveCount(0)
	const shown = (await jwt.textContent())?.trim() ?? ''
	expect(shown).toMatch(/^[\w-]+\.[\w-]+\.[\w-]+$/)

	const list = await admin.get(`${API}/embed-tokens`)
	expect(list.status(), await list.text()).toBe(200)
	const record = ((await list.json()).results as Array<Record<string, unknown>>).find((t) => t.name === name)
	expect(record, 'the issued token is in the token list').toBeTruthy()
	issuedIds.push(String(record?.id))
	expect(record?.subject).toMatchObject({ type: 'dashboard', id: dashboard?.uuid })
	expect(record?.hostOrigins).toEqual([allowedHost.origin])

	const detail = await admin.get(`${API}/embed-tokens/${record?.id}`)
	expect(detail.status(), await detail.text()).toBe(200)
	const metadata = await detail.json()
	expect(metadata, 'the JWT is never returned again').not.toHaveProperty('jwt')
	expect(metadata).not.toHaveProperty('tokenHash')

	const rendered = await anon.get(`${embedPath()}?token=${encodeURIComponent(shown)}`)
	expect(rendered.status(), 'the JWT shown in the UI renders the dashboard').toBe(200)
})

// @e2e embedded-analytics::21-valid-non-expired-token-renders-widget
// @e2e embedded-analytics::31-valid-origin-renders-with-csp-frame-ancestors-header
test('a website on an allowed origin frames the dashboard read-only', async ({ browser }) => {
	const token = await issueToken()
	const context = await browser.newContext({ storageState: { cookies: [], origins: [] } })
	try {
		const page = await context.newPage()
		const rendered = page.waitForResponse((r) => r.url().startsWith(`${BASE_URL}${embedPath()}?`))
		await page.goto(`${allowedHost.origin}/?src=${encodeURIComponent(renderUrl(token.jwt))}`)
		expect((await rendered).status()).toBe(200)

		const frame = page.frameLocator('#embed')
		const grid = frame.locator('.public-dashboard-grid--embed')
		await expect(grid).toBeVisible({ timeout: 30_000 })
		await expect(grid).toHaveAttribute('data-embed-mode', 'read')
		await expect(grid).toContainText(TEXT_MARKER)
		// Without `interact` a tile does not link out of the embed.
		const tiles = frame.locator('a.public-share-view__tile')
		expect(await tiles.count(), 'the default dashboard has tiles').toBeGreaterThan(0)
		await expect(frame.locator('a.public-share-view__tile[href]')).toHaveCount(0)
		// No theme on this token: the tenant variables are not set.
		const primary = await frame.locator('html').evaluate((el) => getComputedStyle(el).getPropertyValue('--c-primary').trim())
		expect(primary).toBe('')
	} finally {
		await context.close()
	}
})

// @e2e embedded-analytics::35-x-frame-options-fallback-for-legacy-browsers
// @e2e embedded-analytics::710-rate-limit-headers-in-all-responses
test('the render route sends frame-ancestors from the host origins, X-Frame-Options and nosniff', async () => {
	const token = await issueToken()
	const res = await anon.get(`${embedPath()}?token=${encodeURIComponent(token.jwt)}`)
	expect(res.status(), await res.text()).toBe(200)
	const headers = res.headers()
	expect(headers['content-type']).toContain('text/html')

	const ancestors = (headers['content-security-policy'] ?? '')
		.split(';')
		.map((directive) => directive.trim())
		.find((directive) => directive.startsWith('frame-ancestors'))
	expect(ancestors, headers['content-security-policy']).toBeTruthy()
	expect(ancestors?.split(/\s+/)).toContain(allowedHost.origin)
	expect(ancestors).not.toContain('*')
	expect(ancestors).not.toContain(foreignHost.origin)

	expect(headers['x-frame-options']).toMatch(/SAMEORIGIN/i)
	expect(headers['x-content-type-options']).toBe('nosniff')
	expect(Number(headers['ratelimit-limit'])).toBeGreaterThan(0)
	expect(headers['ratelimit-remaining']).toMatch(/^\d+$/)
	expect(headers['ratelimit-reset']).toMatch(/^\d+$/)
})

// @e2e embedded-analytics::32-invalid-origin-blocked-by-csp-server-side-sec-fetch-site-check
test('a website the token does not list cannot frame the dashboard', async ({ browser }) => {
	const token = await issueToken()

	// The browser: the host page has loaded, iframe included, and the frame
	// holds no embed page (`#app-embed` is in the server-rendered HTML).
	const { context, page } = await openHostPage(browser, foreignHost, renderUrl(token.jwt))
	try {
		await expect(page.frameLocator('#embed').locator('#app-embed')).toHaveCount(0)
	} finally {
		await context.close()
	}

	// The server: a cross-site load whose Referer is not a host origin.
	const res = await anon.get(`${embedPath()}?token=${encodeURIComponent(token.jwt)}`, {
		headers: { 'Sec-Fetch-Site': 'cross-site', Referer: `${foreignHost.origin}/page` },
	})
	expect(res.status()).toBe(403)
	expect(await res.json()).toEqual({
		error: 'origin_mismatch',
		message: 'This iframe cannot be embedded on this origin',
	})
})

// @e2e embedded-analytics::22-subject-mismatch-returns-403-without-leaking-existence
// @e2e embedded-analytics::25-invalid-jwt-signature-returns-401
// @e2e embedded-analytics::26-missing-token-returns-401
test('a missing, forged or foreign token is refused', async () => {
	const missing = await anon.get(embedPath())
	expect(missing.status()).toBe(401)
	expect(missing.headers()['www-authenticate']).toBe('Bearer')
	expect((await missing.json()).error).toBe('missing_token')

	// A well-formed JWT naming LaunchPad as issuer, signed by nobody.
	const segment = (value: object): string => Buffer.from(JSON.stringify(value)).toString('base64url')
	const forged = `${segment({ alg: 'RS256', kid: 'e2e' })}.${segment({ iss: 'launchpad', jti: 'e2e', sub: `dashboard:${dashboard?.uuid}` })}.${Buffer.from('signature').toString('base64url')}`
	const invalid = await anon.get(`${embedPath()}?token=${forged}`)
	expect(invalid.status()).toBe(401)
	expect(invalid.headers()['www-authenticate']).toContain('error="invalid_token"')
	expect((await invalid.json()).error).toBe('invalid_token')

	const token = await issueToken()
	const elsewhere = await anon.get(`${APP}/embed/dashboard/${randomUUID()}?token=${encodeURIComponent(token.jwt)}`)
	expect(elsewhere.status()).toBe(403)
	expect(await elsewhere.json()).toEqual({
		error: 'scope_mismatch',
		message: 'The provided token does not grant access to this resource',
	})
})

// @e2e embedded-analytics::41-read-mode-token-rejects-write-operation
// @e2e embedded-analytics::47-scope-enforcement-independent-of-frontend-state
// @e2e embedded-analytics::410-api-middleware-integration-point
test('a read-only token cannot write, whatever the frontend sends', async () => {
	const token = await issueToken()
	const bearer = { Authorization: `Bearer ${token.jwt}` }

	// The public write route: LaunchPad's middleware refuses before the controller.
	const unlock = await anon.post(`${APP}/s/e2e${STAMP}/unlock`, {
		headers: bearer,
		form: { password: 'irrelevant' },
	})
	expect(unlock.status()).toBe(403)
	expect(await unlock.json()).toEqual({
		error: 'read_only_token',
		message: 'This token does not permit write operations. Token scope: mode=read',
	})

	// A route that needs a login, called as tampered embed JavaScript would:
	// an embed token is no Nextcloud login, so Nextcloud answers 401.
	const before = await admin.get(`${API}/dashboard/${dashboard?.id}`)
	const countBefore = ((await before.json()).placements ?? []).length
	const added = await anon.post(`${API}/dashboard/${dashboard?.id}/widgets`, {
		headers: { ...bearer, 'OCS-APIRequest': 'true' },
		data: { widgetId: 'text', gridX: 0, gridY: 9, gridWidth: 2, gridHeight: 2, content: { text: 'written by an embed' } },
	})
	expect(added.status(), `Nextcloud refuses an embed token on a login route: ${await added.text()}`).toBe(401)
	const after = await admin.get(`${API}/dashboard/${dashboard?.id}`)
	expect(((await after.json()).placements ?? []).length, 'no placement was added').toBe(countBefore)
})

// @e2e embedded-analytics::49-filter-context-pinning-persisted-with-token
test('filters a viewer applies are limited to the allowed ones, under the pinned context', async ({ browser }) => {
	const subject = { type: 'dashboard', id: dashboard?.uuid, filterContext: { gemeente: 'Amsterdam' } }
	const interactive = await issueToken({
		subject,
		scope: { mode: 'read-with-interactions', allowedFilters: ['status'], allowedActions: ['interact', 'drillDown'] },
	})
	const viewerFilters = {
		'filters[status]': 'open',
		'filters[gemeente]': 'Rotterdam',
		'filters[afdeling]': 'Burgerzaken',
	}

	const drilled = await loadData(interactive.jwt, viewerFilters)
	expect(drilled.status(), await drilled.text()).toBe(200)
	const body = await drilled.json()
	expect(body.scope).toEqual({ mode: 'read-with-interactions', allowedActions: ['interact', 'drillDown'], allowedFilters: ['status'] })
	expect(body.filters, 'status applied, gemeente stays pinned, afdeling dropped').toEqual({ status: 'open', gemeente: 'Amsterdam' })

	const reloaded = await loadData(interactive.jwt)
	expect((await reloaded.json()).filters, 'a new load starts from the pinned context').toEqual({ gemeente: 'Amsterdam' })

	const readOnly = await issueToken({ subject })
	const ignored = await loadData(readOnly.jwt, viewerFilters)
	expect((await ignored.json()).filters, 'a read token takes no viewer filters').toEqual({ gemeente: 'Amsterdam' })

	// A write that names no allowed action is refused even for an interactive token.
	const unlock = await anon.post(`${APP}/s/e2e${STAMP}/unlock`, {
		headers: { Authorization: `Bearer ${interactive.jwt}` },
		form: { password: 'irrelevant' },
	})
	expect(unlock.status()).toBe(403)
	expect((await unlock.json()).error).toBe('action_not_in_scope')

	// In the frame the interactive scope shows: tiles link out, in a new tab.
	const { context, page } = await openHostPage(browser, allowedHost, renderUrl(interactive.jwt))
	try {
		const frame = page.frameLocator('#embed')
		await expect(frame.locator('.public-dashboard-grid--embed')).toHaveAttribute('data-embed-mode', 'read-with-interactions', { timeout: 30_000 })
		const tile = frame.locator('a.public-share-view__tile').first()
		await expect(tile).toHaveAttribute('href', /.+/)
		await expect(tile).toHaveAttribute('target', '_blank')
	} finally {
		await context.close()
	}
})

// @e2e embedded-analytics::61-sdk-initialization-and-iframe-creation
// @e2e embedded-analytics::62-postmessage-handshake-for-token-injection
// @e2e embedded-analytics::67-umd-build-for-vanilla-js
test('a drill-down in the embed reaches the host page through the SDK', async ({ browser }) => {
	const sdk = `${BASE_URL}${APP}/embed-sdk/embed-sdk.umd.js`
	const bundle = await anon.get(sdk)
	expect(bundle.status(), `the instance serves the SDK; build it with \`npm run build\` (or \`npm run build:embed-sdk\`): ${sdk}`).toBe(200)

	const token = await issueToken({
		scope: { mode: 'read-with-interactions', allowedFilters: [], allowedActions: ['drillDown'] },
	})
	const config = {
		baseUrl: `${BASE_URL}${APP}`,
		token: token.jwt,
		subjectType: 'dashboard',
		subjectId: dashboard?.uuid,
	}
	const context = await browser.newContext({ storageState: { cookies: [], origins: [] } })
	try {
		const page = await context.newPage()
		const query = new URLSearchParams({ sdk, config: JSON.stringify(config) })
		await page.goto(`${allowedHost.origin}/sdk?${query}`)
		await expect.poll(() => page.evaluate(() => (window as unknown as { embedState: string }).embedState), {
			timeout: 30_000,
		}).toBe('ready')

		const tile = page.frameLocator('#host iframe').locator('a.public-share-view__tile').first()
		await expect(tile).toHaveAttribute('href', '#')
		const title = (await tile.textContent())?.trim()
		await tile.click()

		const events = (): Promise<Array<Record<string, unknown>>> =>
			page.evaluate(() => (window as unknown as { embedEvents: Array<Record<string, unknown>> }).embedEvents)
		await expect.poll(async () => (await events()).length, { timeout: 10_000 }).toBe(1)
		const [event] = await events()
		expect(event).toMatchObject({ title, source: 'user-interaction' })
		expect(typeof event.placementId).toBe('number')
		const placements = await admin.get(`${API}/dashboard/${dashboard?.id}`)
		const ids = ((await placements.json()).placements as Array<{ id: number }>).map((p) => Number(p.id))
		expect(ids, 'the event names a placement of the embedded dashboard').toContain(event.placementId)
		expect(page.url(), 'the host page did not navigate').toContain(`${allowedHost.origin}/sdk`)
	} finally {
		await context.close()
	}
})

// @e2e embedded-analytics::51-css-variables-injected-into-root
test('the tenant theme colours the embed through :root CSS variables', async ({ browser }) => {
	const token = await issueToken({ tenantThemeId: themeId })

	const res = await anon.get(`${embedPath()}?token=${encodeURIComponent(token.jwt)}`)
	expect(res.status()).toBe(200)
	const html = await res.text()
	expect(html).toContain(':root {')
	for (const [name, value] of Object.entries(THEME)) {
		expect(html).toContain(`${name}: ${value};`)
	}

	const { context, page } = await openHostPage(browser, allowedHost, renderUrl(token.jwt))
	try {
		const frame = page.frameLocator('#embed')
		const widget = frame.locator('.public-share-view__widget', { hasText: TEXT_MARKER })
		await expect(widget).toBeVisible({ timeout: 30_000 })
		const root = await frame.locator('html').evaluate((el) => {
			const style = getComputedStyle(el)
			return {
				primary: style.getPropertyValue('--c-primary').trim(),
				surface: style.getPropertyValue('--c-surface').trim(),
			}
		})
		expect(root).toEqual({ primary: THEME['--c-primary'], surface: THEME['--c-surface'] })
		await expect(widget).toHaveCSS('color', 'rgb(26, 26, 26)')
		await expect(widget).toHaveCSS('background-color', 'rgb(253, 246, 227)')
	} finally {
		await context.close()
	}
})

// @e2e embedded-analytics::71-token-bucket-rate-limiting-with-burst
// @e2e embedded-analytics::73-429-response-format
// @e2e embedded-analytics::710-rate-limit-headers-in-all-responses
test('a token answers 429 once its burst is spent', async () => {
	// One request a minute with a burst of two: the third request in a row
	// finds the bucket empty (refill is one request per 60 s).
	const token = await issueToken({ rateLimitPolicy: { requestsPerMinute: 1, burstSize: 2 } })

	const first = await loadData(token.jwt)
	expect(first.status(), await first.text()).toBe(200)
	expect(first.headers()['ratelimit-limit']).toBe('1')
	expect(first.headers()['ratelimit-remaining']).toBe('1')

	const second = await loadData(token.jwt)
	expect(second.status(), await second.text()).toBe(200)
	expect(second.headers()['ratelimit-remaining']).toBe('0')

	const third = await loadData(token.jwt)
	expect(third.status()).toBe(429)
	const retryAfter = Number(third.headers()['retry-after'])
	expect(retryAfter).toBeGreaterThan(0)
	expect(retryAfter).toBeLessThanOrEqual(60)
	expect(third.headers()['ratelimit-limit']).toBe('1')
	expect(third.headers()['ratelimit-remaining']).toBe('0')
	const body = await third.json()
	expect(body).toMatchObject({ error: 'rate_limit_exceeded', retryAfter })
	expect(body.message).toBe(`Rate limit exceeded. The token allows 1 requests per minute. Try again in ${retryAfter} seconds.`)
	expect(body.resetAt).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/)

	const render = await anon.get(`${embedPath()}?token=${encodeURIComponent(token.jwt)}`)
	expect(render.status(), 'the render route spends the same bucket').toBe(429)
})

// @e2e embedded-analytics::93-render-route-workers-reject-revoked-token-within-60-seconds
// @e2e embedded-analytics::910-soft-revocation-can-be-undone
test('a revoked token stops rendering within 60 seconds, and renders again once restored', async () => {
	const token = await issueToken()
	const render = (): Promise<APIResponse> => anon.get(`${embedPath()}?token=${encodeURIComponent(token.jwt)}`)

	// Rendering first caches the token as valid, the state revocation must beat.
	expect((await render()).status()).toBe(200)

	const revokedAt = Date.now()
	const revoke = await admin.post(`${API}/embed-tokens/${token.id}/revoke`, {
		data: { reason: 'compromised', notes: 'embedded-analytics e2e' },
	})
	expect(revoke.status(), await revoke.text()).toBe(200)
	expect((await revoke.json()).revokedAt).toBeTruthy()

	await expect.poll(async () => {
		const res = await render()
		return `${res.status()} ${(await res.json().catch(() => ({}))).error ?? ''}`
	}, { timeout: 60_000, intervals: [500, 1_000, 2_000, 5_000] }).toBe('401 token_revoked')
	expect(Date.now() - revokedAt).toBeLessThan(60_000)

	const refused = await render()
	expect(refused.headers()['www-authenticate']).toContain('error="invalid_token"')
	expect(await refused.json()).toEqual({ error: 'token_revoked', message: 'The provided token has been revoked' })

	const restore = await admin.post(`${API}/embed-tokens/${token.id}/unrevoke`)
	expect(restore.status(), await restore.text()).toBe(200)
	await expect.poll(async () => (await render()).status(), { timeout: 60_000, intervals: [500, 1_000, 2_000, 5_000] }).toBe(200)
})
