/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for the clock and weather ambient widgets
 * (openspec/specs/clock-weather-widgets/spec.md). Written for
 * e2e-coverage-programme task 4.14: one verdict per scenario.
 *
 * CLOCK
 * =====
 * The clock is client-side only (ClockWidget.vue). Its form is driven through
 * the Add Widget modal (More menu, "Add widget" → "Custom widget") and what it saved is
 * read back from `GET /api/dashboard/{id}`. Time-dependent assertions run on
 * Playwright's clock API, in a browser whose own zone (America/Los_Angeles)
 * differs from every zone a placement is configured with, so a widget that
 * ignored its configured zone would show the wrong hour.
 *
 * WEATHER
 * =======
 * The browser only ever calls `GET /api/weather/{placementId}`; Nextcloud makes
 * the provider call itself (WeatherService::fetchFromProviderUrl). So the
 * provider is a small HTTP server this spec starts, and the admin-configured
 * provider URL template (`weather_provider_url`) points at it. The server
 * records every request it gets, which is how "no new upstream fetch" and
 * "the key is sent server-side only" are measured. Nextcloud has to reach it:
 * set WEATHER_FIXTURE_HOST (or LIVE_TILE_FIXTURE_HOST, as live-tile-lists
 * does) to an address Nextcloud reaches this runner on, default 127.0.0.1.
 * A Nextcloud that refuses local addresses (`allow_local_remote_servers` off)
 * cannot fetch it, and the tests that need a reading skip and say so.
 *
 * WHAT THE PRODUCT DOES NOT DO (YET)
 * - discovery: no `launchpad_clock` / `launchpad_weather` widget is
 *   registered with Nextcloud's dashboard (no IWidget provider in lib/, no
 *   registerWidget call); the types live only in LaunchPad's own registry
 *   under the ids `clock` and `weather` (src/constants/widgetRegistry.js).
 *   The scenario is excluded as never built in spec.md; the test below is
 *   kept as `test.fixme`, untagged, for whoever builds the provider.
 * - defaults: an unset clock follows the DEVICE zone, not the user's
 *   Nextcloud zone (ClockWidget.vue DEFAULT_CONTENT `timezone: ''`). The
 *   test is written to the scenario and is expected to be red until fixed.
 */

import type {
	APIRequestContext,
	Browser,
	BrowserContext,
	Page,
} from '@playwright/test'
import type { IncomingMessage, Server, ServerResponse } from 'node:http'
import type { AddressInfo } from 'node:net'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { createServer } from 'node:http'
import {
	deprovisionUser,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { installBundleOverride } from './support/bundleOverride.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

const API = '/index.php/apps/launchpad/api'
const APP_CONFIG = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad'
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const FIXTURE_HOST =
	process.env.WEATHER_FIXTURE_HOST
	|| process.env.LIVE_TILE_FIXTURE_HOST
	|| '127.0.0.1'
const STAMP = Date.now().toString(36)
const API_KEY = `e2e-weather-key-${STAMP}`

/** Thursday 15 January 2026, 13:05:30 UTC — 14:05:30 in Amsterdam. */
const T0 = new Date('2026-01-15T13:05:30Z')
const BROWSER_ZONE = 'America/Los_Angeles'

/** An option of an open NcSelect (vue-select) list, appended to the body. */
const DROPDOWN_OPTION = '.vs__dropdown-menu [role="option"]'

/** The eight keys of the public reading (REQ-WEATHER-001). */
const READING_KEYS = [
	'condition',
	'conditionText',
	'fetchedAt',
	'language',
	'location',
	'stale',
	'tempValue',
	'units',
]

type Credentials = { username: string; password: string }
type Hit = { location: string; query: URLSearchParams; userAgent: string }
type Mode = 'ok' | 'fail' | 'nodesc'

let admin: APIRequestContext

/*
 * A basic-auth API context with an explicitly empty jar, so the project's
 * stored admin session is never sent along with another user's credentials.
 */
async function apiAs(user: string, pass: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
			Authorization: `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`,
		},
	})
}

/**
 * Give a provisioned account a language and a locale.
 *
 * @param username The account.
 * @param language A Nextcloud language code, e.g. `nl`.
 * @param locale A Nextcloud locale, e.g. `nl_NL`.
 */
async function setLanguageAndLocale(
	username: string,
	language: string,
	locale: string,
): Promise<void> {
	for (const [key, value] of [
		['language', language],
		['locale', locale],
	]) {
		const res = await admin.put(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}`,
			{ form: { key, value } },
		)
		expect(res.ok(), `set ${key} for ${username}: ${res.status()}`).toBeTruthy()
	}
}

/**
 * Create a personal dashboard as `api`'s user and make it their active one.
 * The admin must have allowed personal dashboards first.
 *
 * @param api A context authenticated as the owner.
 * @param name The dashboard name.
 */
async function seedOwnDashboard(
	api: APIRequestContext,
	name: string,
): Promise<SeededDashboard> {
	// A new account's first request also sets up its home folder.
	const created = await api.post(`${API}/dashboard`, {
		data: { name },
		timeout: 60_000,
	})
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	const id = Number(dash.id)
	const activate = await api.post(`${API}/dashboard/${id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const preference = await api.post(`${API}/dashboards/active`, {
		data: { uuid: dash.uuid },
	})
	expect(preference.status(), await preference.text()).toBeLessThan(300)
	return { id, uuid: dash.uuid, name }
}

/**
 * Add a placement over the API and return its id.
 *
 * @param api A context authenticated as the dashboard's owner.
 * @param dashboardId The dashboard.
 * @param widgetId The widget type, `clock` or `weather`.
 * @param content The placement content.
 * @param row The grid row, so placements do not collide.
 */
async function addPlacement(
	api: APIRequestContext,
	dashboardId: number,
	widgetId: string,
	content: Record<string, unknown>,
	row = 0,
): Promise<number> {
	const res = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId,
			gridX: 0,
			gridY: row * 4,
			gridWidth: 4,
			gridHeight: 4,
			content,
		},
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const id = Number(
		body.widget?.id ?? body.placement?.id ?? body.data?.id ?? body.id,
	)
	expect(id, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
	return id
}

/**
 * The placements of a dashboard as its owner reads them back.
 *
 * @param api A context authenticated as the owner.
 * @param dashboardId The dashboard.
 */
async function placementsOf(
	api: APIRequestContext,
	dashboardId: number,
): Promise<Array<Record<string, any>>> {
	const res = await api.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.placements ?? body.dashboard?.placements ?? []
}

/**
 * Log in through the login form in a browser context set to `timezoneId`.
 * Nextcloud stores the zone the login form reports as the user's timezone.
 *
 * @param browser The Playwright browser.
 * @param user The account to log in as.
 * @param timezoneId The browser's IANA zone.
 */
async function loginInZone(
	browser: Browser,
	user: Credentials,
	timezoneId: string,
): Promise<{ context: BrowserContext; page: Page }> {
	const context = await browser.newContext({
		baseURL: BASE_URL,
		storageState: undefined,
		timezoneId,
	})
	await installBundleOverride(context)
	const page = await context.newPage()
	await page.goto('/index.php/login')
	await page.locator('input[name="user"]').fill(user.username)
	await page.locator('input[name="password"]').fill(user.password)
	await page.locator('button[type="submit"]').first().click({ noWaitAfter: true })
	await page.waitForSelector('#header, header.header', { timeout: 45_000 })
	return { context, page }
}

/**
 * Open the Add Widget modal for the active dashboard from the More menu.
 *
 * @param page A page on the LaunchPad workspace.
 */
async function openAddCustomWidget(page: Page, type = 'clock') {
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await page
		.locator('[data-testid="launchpad-more-menu-item-add-widget"]')
		.click()
	await page.locator(`[data-testid="widget-picker-type-${type}"]`).click()
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * Pick an option in one of the form's NcSelect controls.
 *
 * @param page The page (the options list is appended to the body).
 * @param dialog The Add Widget dialog.
 * @param label The select's visible label.
 * @param option The option's text.
 */
async function pick(
	page: Page,
	dialog: ReturnType<Page['locator']>,
	label: string,
	option: string,
): Promise<void> {
	const input = dialog.getByLabel(label, { exact: true })
	await input.click()
	await input.fill(option)
	await page
		.locator(DROPDOWN_OPTION)
		.getByText(option, { exact: true })
		.first()
		.click()
}

/**
 * Add the clock the dialog is filled in for and return the stored content of
 * the new placement, as `GET /api/dashboard/{id}` answers it.
 *
 * @param page The page.
 * @param dialog The filled-in Add Widget dialog.
 * @param dashboardId The active dashboard.
 */
async function submitClock(
	page: Page,
	dialog: ReturnType<Page['locator']>,
	dashboardId: number,
): Promise<Record<string, unknown>> {
	const before = (await placementsOf(admin, dashboardId)).map((p) => p.id)
	const created = page.waitForResponse(
		(r) =>
			r.request().method() === 'POST'
			&& r.url().includes(`/api/dashboard/${dashboardId}/widgets`),
	)
	await dialog.getByRole('button', { name: /^add$/i }).click()
	expect((await created).status()).toBeLessThan(300)
	await expect(dialog).not.toBeVisible({ timeout: 8_000 })

	let added: Record<string, any> | undefined
	await expect
		.poll(async () => {
			added = (await placementsOf(admin, dashboardId)).find(
				(p) => p.widgetId === 'clock' && !before.includes(p.id),
			)
			return added !== undefined
		})
		.toBe(true)
	return added!.content as Record<string, unknown>
}

test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await apiAs(ADMIN.user, ADMIN.pass)
})

test.afterAll(async () => {
	await admin?.dispose()
})

test.describe('clock widget', () => {
	test.use({ timezoneId: BROWSER_ZONE })

	let dashboard: SeededDashboard | null = null

	test.beforeAll(async () => {
		dashboard = await seedActiveDashboard(admin, `Klok ${STAMP}`)
	})

	test.afterAll(async () => {
		await removeSeededDashboard(admin, dashboard)
	})

	// NEVER BUILT: nothing registers `launchpad_clock` or `launchpad_weather`
	// with OCP\Dashboard\IManager — there is no widget provider class in lib/
	// and no registerWidget call; LaunchPad's own picker offers the types as
	// `clock` and `weather` (src/constants/widgetRegistry.js:245, :271). The
	// scenario `widget-appears-in-discovery` is excluded in spec.md for that
	// reason; tag this test again once the provider exists.
	test.fixme('the clock and the weather widget are listed with their id, title and icon', async ({
		page,
	}) => {
		const requested: string[] = []
		page.on('request', (r) => requested.push(r.url()))

		await gotoLaunchPad(page)
		const dialog = await openAddCustomWidget(page)
		const type = dialog.getByLabel(/widget type/i)
		await expect(type.locator('option', { hasText: 'Clock' })).toHaveCount(1)
		await expect(type.locator('option', { hasText: 'Weather' })).toHaveCount(1)
		await type.selectOption({ label: 'Clock' })
		await expect(dialog.getByLabel('Timezone', { exact: true })).toBeVisible()
		expect(
			requested.filter((u) => /clock/i.test(new URL(u).pathname)),
			'discovering the clock fetches nothing',
		).toEqual([])

		const listed = await admin.get(
			'/ocs/v2.php/apps/dashboard/api/v1/widgets?format=json',
		)
		expect(listed.status(), await listed.text()).toBe(200)
		const widgets = (await listed.json()).ocs.data as Record<
			string,
			{ id: string; title: string; icon_class?: string; icon_url?: string }
		>
		for (const id of ['launchpad_clock', 'launchpad_weather']) {
			const widget = widgets[id]
			expect(widget, `${id} is registered`).toBeTruthy()
			expect(widget.id).toBe(id)
			expect(widget.title.trim()).not.toBe('')
			expect(`${widget.icon_class ?? ''}${widget.icon_url ?? ''}`).not.toBe('')
		}
	})

	// @e2e clock-weather-widgets::digital-style-configuration
	test('a digital, 24-hour Amsterdam clock with a date is stored as exactly those four fields', async ({
		page,
	}) => {
		await gotoLaunchPad(page)
		const dialog = await openAddCustomWidget(page, 'clock')

		await pick(page, dialog, 'Style', 'Digital')
		await pick(page, dialog, 'Hour format', '24-hour')
		await pick(page, dialog, 'Timezone', 'Europe/Amsterdam')
		const showDate = dialog
			.getByRole('switch', { name: 'Show date' })
			.or(dialog.getByRole('checkbox', { name: 'Show date' }))
		await expect(showDate).toBeChecked()

		const content = await submitClock(page, dialog, dashboard!.id)
		expect(content).toEqual({
			style: 'digital',
			hourFormat: '24h',
			timezone: 'Europe/Amsterdam',
			showDate: true,
		})
	})

	// @e2e clock-weather-widgets::analog-style-configuration
	test('an analog New York clock is stored as style and timezone only, picked from IANA zones', async ({
		page,
	}) => {
		await gotoLaunchPad(page)
		const dialog = await openAddCustomWidget(page, 'clock')
		await pick(page, dialog, 'Style', 'Analog')
		await expect(dialog.getByLabel('Hour format', { exact: true })).toHaveCount(
			0,
		)

		// The timezone picker lists IANA identifiers.
		const zone = dialog.getByLabel('Timezone', { exact: true })
		await zone.click()
		const options = page.locator(DROPDOWN_OPTION)
		await expect(options.first()).toBeVisible()
		// textContent, not innerText: the narrow dropdown wraps long names.
		const names = (await options.allTextContents()).map((s) => s.trim())
		expect(names.length).toBeGreaterThan(10)
		for (const iana of ['Europe/Amsterdam', 'America/New_York', 'Asia/Tokyo']) {
			expect(names).toContain(iana)
		}
		for (const name of names) {
			expect(name).toMatch(/^(UTC|[A-Z][A-Za-z_-]+(\/[A-Za-z0-9_+-]+)+)$/)
		}
		await page.keyboard.press('Escape')

		await pick(page, dialog, 'Timezone', 'America/New_York')
		const content = await submitClock(page, dialog, dashboard!.id)
		expect(content).toEqual({ style: 'analog', timezone: 'America/New_York' })
	})

	// @e2e clock-weather-widgets::defaults-when-unset
	test('a clock with no config is digital, dated, and in the user’s own zone and hour format', async ({
		browser,
	}) => {
		const member = await provisionThrowawayUser('e2e-clock')
		const own = await apiAs(member.username, member.password)
		let viewer: BrowserContext | null = null
		try {
			await setLanguageAndLocale(member.username, 'en', 'en_US')
			await admin.put(`${API}/admin/settings`, {
				data: { allowUserDash: true },
			})
			const dash = await seedOwnDashboard(own, `Klok standaard ${STAMP}`)
			const id = await addPlacement(own, dash.id, 'clock', {})

			// Log in from Tokyo, so Nextcloud stores Asia/Tokyo as the user's
			// zone, then look from a browser in Los Angeles.
			const login = await loginInZone(browser, member, 'Asia/Tokyo')
			const state = await login.context.storageState()
			await login.context.close()
			viewer = await browser.newContext({
				baseURL: BASE_URL,
				storageState: state,
				timezoneId: BROWSER_ZONE,
			})
			await installBundleOverride(viewer)
			const page = await viewer.newPage()
			await page.clock.setFixedTime(T0)
			await page.goto('/index.php/apps/launchpad/')

			const tile = page.locator(`[data-placement-id="${id}"]`)
			await expect(tile.locator('.clock-widget--digital')).toBeVisible({
				timeout: 30_000,
			})
			// 22:05:30 in Tokyo, 12-hour as en_US writes it; Los Angeles would
			// read 05:05:30 AM.
			await expect(tile.locator('.clock-widget__time')).toHaveText(
				/^10:05:30\s?PM$/i,
			)
			await expect(tile.locator('.clock-widget__date')).toHaveText(
				'Thursday, January 15, 2026',
			)
		} finally {
			await viewer?.close()
			await own.dispose()
			await deprovisionUser(member.username)
		}
	})

	// @e2e clock-weather-widgets::digital-time-honours-timezone-and-format
	test('an Amsterdam clock shows Amsterdam time in 24 hours, ticks every second, and 12h shows PM', async ({
		page,
	}) => {
		const h24 = await addPlacement(
			admin,
			dashboard!.id,
			'clock',
			{
				style: 'digital',
				hourFormat: '24h',
				timezone: 'Europe/Amsterdam',
				showDate: false,
			},
			10,
		)
		const h12 = await addPlacement(
			admin,
			dashboard!.id,
			'clock',
			{
				style: 'digital',
				hourFormat: '12h',
				timezone: 'Europe/Amsterdam',
				showDate: false,
			},
			11,
		)

		await page.clock.install({ time: new Date(T0.getTime() - 5 * 60_000) })
		await gotoLaunchPad(page)
		await page.clock.pauseAt(T0)

		const time24 = page.locator(
			`[data-placement-id="${h24}"] .clock-widget__time`,
		)
		const time12 = page.locator(
			`[data-placement-id="${h12}"] .clock-widget__time`,
		)
		// The browser is in Los Angeles (05:05); the tile must read Amsterdam.
		await expect(time24).toHaveText(/^14:05:\d{2}$/)
		await expect(time12).toHaveText(/^02:05:\d{2}\s?PM$/i)

		// At least once per second.
		const shown = (await time24.innerText()).trim()
		await page.clock.runFor(1000)
		await expect(time24).not.toHaveText(shown)
		await page.clock.fastForward('01:00')
		await expect(time24).toHaveText(/^14:06:\d{2}$/)
	})

	// @e2e clock-weather-widgets::locale-aware-date
	test('a Dutch user’s clock writes the weekday and month in Dutch', async ({
		browser,
	}) => {
		const dutch = await provisionThrowawayUser('e2e-klok')
		await setLanguageAndLocale(dutch.username, 'nl', 'nl_NL')
		await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		const own = await apiAs(dutch.username, dutch.password)
		const { context, page } = await loginInZone(browser, dutch, BROWSER_ZONE)
		try {
			const dash = await seedOwnDashboard(own, `Klok datum ${STAMP}`)
			const id = await addPlacement(own, dash.id, 'clock', {
				style: 'digital',
				hourFormat: '24h',
				timezone: 'Europe/Amsterdam',
				showDate: true,
			})
			await page.clock.setFixedTime(T0)
			await page.goto('/index.php/apps/launchpad/')
			const date = page.locator(
				`[data-placement-id="${id}"] .clock-widget__date`,
			)
			await expect(date).toBeVisible({ timeout: 30_000 })
			await expect(date).toHaveText(/donderdag/)
			await expect(date).toHaveText(/15 januari 2026/)
			await expect(date).not.toHaveText(/Thursday|January/)
		} finally {
			await context.close()
			await own.dispose()
			await deprovisionUser(dutch.username)
		}
	})

	// @e2e clock-weather-widgets::analog-clock-is-accessible
	test('an analog clock exposes the current time as text, not by its face alone', async ({
		page,
	}) => {
		const id = await addPlacement(
			admin,
			dashboard!.id,
			'clock',
			{
				style: 'analog',
				timezone: 'Europe/Amsterdam',
			},
			12,
		)
		await page.clock.setFixedTime(T0)
		await gotoLaunchPad(page)

		const tile = page.locator(`[data-placement-id="${id}"]`)
		const face = tile.getByRole('img', { name: /(14|02):05:30/ })
		await expect(face).toBeVisible({ timeout: 30_000 })
		await expect(face).toHaveClass(/clock-widget__analog/)
		// The drawing itself is hidden from assistive technology: the label is
		// the only way the time is conveyed, and it is there.
		await expect(face.locator('svg')).toHaveAttribute('aria-hidden', 'true')
		await expect(face).toHaveAttribute('aria-label', /(14|02):05:30/)
	})
})

test.describe('weather widget', () => {
	const hits: Hit[] = []
	const modes = new Map<string, Mode>()
	const previous: Record<string, string | null> = {}
	let server: Server
	let fixtureOrigin = ''
	let reachable = false
	let dashboard: SeededDashboard | null = null
	let dutch: Credentials | null = null
	let american: Credentials | null = null
	let row = 0

	const PROVIDER_URL_KEY = 'weather_provider_url'
	const PROVIDER_API_KEY_KEY = 'weather_provider_api_key'
	const TTL_KEY = 'weather_cache_ttl_seconds'
	const UNREACHABLE = `Nextcloud could not fetch the weather fixture on ${FIXTURE_HOST}; set WEATHER_FIXTURE_HOST to an address it reaches, or allow local remote servers`

	/** Read one LaunchPad app value, null when unset. */
	async function readConfig(key: string): Promise<string | null> {
		const res = await admin.get(`${APP_CONFIG}/${key}?format=json`)
		if (!res.ok()) {
			return null
		}
		const data = (await res.json())?.ocs?.data
		return typeof data?.data === 'string' ? data.data : null
	}

	/** Write one LaunchPad app value, or delete it for null. */
	async function writeConfig(key: string, value: string | null): Promise<void> {
		const res =
			value === null
				? await admin.delete(`${APP_CONFIG}/${key}?format=json`)
				: await admin.post(`${APP_CONFIG}/${key}?format=json`, {
						form: { value },
					})
		expect(res.ok(), `write ${key} -> ${res.status()}`).toBeTruthy()
	}

	/** The fixture requests made for one location. */
	function hitsFor(location: string): Hit[] {
		return hits.filter((h) => h.location === location)
	}

	/** Answer one provider request in the OpenWeatherMap shape. */
	function answer(req: IncomingMessage, res: ServerResponse) {
		const url = new URL(req.url ?? '/', 'http://fixture')
		const location = url.searchParams.get('q') ?? ''
		hits.push({
			location,
			query: url.searchParams,
			userAgent: String(req.headers['user-agent'] ?? ''),
		})
		const mode = modes.get(location) ?? 'ok'
		if (mode === 'fail') {
			res.writeHead(500, { 'Content-Type': 'application/json' })
			res.end('{"cod":500}')
			return
		}
		const imperial = url.searchParams.get('units') === 'imperial'
		const description =
			url.searchParams.get('lang') === 'nl' ? 'lichte regen' : 'light rain'
		res.writeHead(200, { 'Content-Type': 'application/json' })
		res.end(
			JSON.stringify({
				name: location,
				main: { temp: imperial ? 54.3 : 12.4 },
				weather: [
					mode === 'nodesc'
						? { main: 'Rain' }
						: { main: 'Rain', description },
				],
			}),
		)
	}

	/** Seed a weather placement on the admin's dashboard. */
	async function weatherTile(
		location: string,
		unitsOverride = 'metric',
	): Promise<number> {
		row += 1
		return addPlacement(
			admin,
			dashboard!.id,
			'weather',
			{ location, unitsOverride },
			row,
		)
	}

	test.beforeAll(async () => {
		server = createServer(answer)
		await new Promise<void>((resolve) =>
			server.listen(0, '0.0.0.0', () => resolve()),
		)
		const { port } = server.address() as AddressInfo
		fixtureOrigin = `http://${FIXTURE_HOST}:${port}`

		for (const key of [PROVIDER_URL_KEY, PROVIDER_API_KEY_KEY, TTL_KEY]) {
			previous[key] = await readConfig(key)
		}
		await writeConfig(
			PROVIDER_URL_KEY,
			`${fixtureOrigin}/data/2.5/weather?q={location}&appid={apiKey}&units={units}&lang={lang}`,
		)
		await writeConfig(PROVIDER_API_KEY_KEY, API_KEY)
		await writeConfig(TTL_KEY, null)

		dutch = await provisionThrowawayUser('e2e-weer-nl')
		await setLanguageAndLocale(dutch.username, 'nl', 'nl_NL')
		american = await provisionThrowawayUser('e2e-weer-us')
		await setLanguageAndLocale(american.username, 'en', 'en_US')

		dashboard = await seedActiveDashboard(admin, `Weer ${STAMP}`)
		const probe = await weatherTile(`Probe-${STAMP}`)
		const res = await admin.get(`${API}/weather/${probe}`)
		reachable = res.status() === 200 && hitsFor(`Probe-${STAMP}`).length > 0
	})

	test.afterAll(async () => {
		await removeSeededDashboard(admin, dashboard)
		for (const key of [PROVIDER_URL_KEY, PROVIDER_API_KEY_KEY, TTL_KEY]) {
			await writeConfig(key, previous[key] ?? null).catch(() => undefined)
		}
		for (const user of [dutch, american]) {
			if (user !== null) {
				await deprovisionUser(user.username)
			}
		}
		await new Promise<void>((resolve) => server?.close(() => resolve()))
	})

	// @e2e clock-weather-widgets::browser-fetches-via-the-placement-endpoint-key-never-exposed
	test('the tile reads its placement endpoint, which answers the eight fields and no key or provider URL', async ({
		page,
	}) => {
		test.skip(!reachable, UNREACHABLE)
		const location = `Utrecht-${STAMP}`
		const id = await weatherTile(location)
		const browserCalls: string[] = []
		page.on('request', (r) => browserCalls.push(r.url()))

		// The read fires only once the shell has hydrated, which gotoLaunchPad
		// allows up to 20s (twice), so the wait must outlast that.
		const reading = page.waitForResponse(
			(r) => r.url().includes(`/apps/launchpad/api/weather/${id}`),
			{ timeout: 45_000 },
		)
		await gotoLaunchPad(page)
		const res = await reading
		expect(res.request().method()).toBe('GET')
		expect(res.status()).toBe(200)
		const text = await res.text()
		const body = JSON.parse(text)
		expect(Object.keys(body).sort()).toEqual(READING_KEYS)
		expect(body.location).toBe(location)
		expect(typeof body.tempValue).toBe('number')
		expect(body.stale).toBe(false)

		expect(text, 'the provider key never leaves the server').not.toContain(
			API_KEY,
		)
		expect(text).not.toContain(fixtureOrigin)
		expect(text).not.toContain('/data/2.5/weather')
		expect(text).not.toContain('appid')
		expect(
			browserCalls.filter((u) => u.startsWith(fixtureOrigin)),
			'the browser never calls the provider',
		).toEqual([])
		await expect(
			page.locator(`[data-placement-id="${id}"] .weather-widget__location`),
		).toHaveText(location)
	})

	// @e2e clock-weather-widgets::caller-authorization
	test('a user who may not view the dashboard gets 403 and no fetch; the owner gets the reading', async () => {
		const location = `Zeist-${STAMP}`
		const id = await weatherTile(location)
		const outsider = await provisionThrowawayUser('e2e-weer-buiten')
		const api = await apiAs(outsider.username, outsider.password)
		try {
			const refused = await api.get(`${API}/weather/${id}`, {
				timeout: 60_000,
			})
			expect(refused.status(), await refused.text()).toBe(403)
			expect(await refused.json()).toEqual({
				status: 'error',
				error: 'forbidden',
			})
			expect(hitsFor(location), 'no fetch for a refused caller').toHaveLength(
				0,
			)
		} finally {
			await api.dispose()
			await deprovisionUser(outsider.username)
		}

		test.skip(!reachable, UNREACHABLE)
		const allowed = await admin.get(`${API}/weather/${id}`)
		expect(allowed.status(), await allowed.text()).toBe(200)
		expect(hitsFor(location)).toHaveLength(1)
	})

	// @e2e clock-weather-widgets::fallback-to-configurable-provider-url
	test('without weather_status the reading comes from the configured URL, called by Nextcloud with the key', async () => {
		test.skip(!reachable, UNREACHABLE)
		const apps = await admin.get(
			'/ocs/v1.php/cloud/apps?filter=enabled&format=json',
		)
		expect(apps.ok()).toBeTruthy()
		const wasEnabled = ((await apps.json()).ocs.data.apps as string[]).includes(
			'weather_status',
		)
		if (wasEnabled) {
			const off = await admin.delete(
				'/ocs/v1.php/cloud/apps/weather_status?format=json',
			)
			expect(
				off.ok(),
				`disable weather_status -> ${off.status()}`,
			).toBeTruthy()
		}
		try {
			const location = `Arnhem-${STAMP}`
			const id = await weatherTile(location)
			const res = await admin.get(`${API}/weather/${id}`)
			expect(res.status(), await res.text()).toBe(200)
			const text = await res.text()
			expect(JSON.parse(text).tempValue).toBe(12.4)
			expect(text).not.toContain(API_KEY)

			const calls = hitsFor(location)
			expect(calls).toHaveLength(1)
			expect(calls[0].query.get('appid'), 'the key is sent server-side').toBe(
				API_KEY,
			)
			expect(calls[0].userAgent, 'sent by Nextcloud’s HTTP client').toMatch(
				/Nextcloud/i,
			)
		} finally {
			if (wasEnabled) {
				await admin.post('/ocs/v1.php/cloud/apps/weather_status?format=json')
			}
		}
	})

	// @e2e clock-weather-widgets::cached-within-ttl
	test('a second read inside the TTL is the cached reading, not a new upstream fetch', async () => {
		test.skip(!reachable, UNREACHABLE)
		const location = `Leiden-${STAMP}`
		const id = await weatherTile(location)
		const first = await admin.get(`${API}/weather/${id}`)
		expect(first.status(), await first.text()).toBe(200)
		const fresh = await first.json()
		expect(fresh.stale).toBe(false)
		expect(hitsFor(location)).toHaveLength(1)

		const second = await admin.get(`${API}/weather/${id}`)
		expect(second.status(), await second.text()).toBe(200)
		const cached = await second.json()
		expect(cached.stale).toBe(false)
		expect(cached.fetchedAt).toBe(fresh.fetchedAt)
		expect(cached).toEqual(fresh)
		expect(hitsFor(location), 'no new upstream fetch').toHaveLength(1)
	})

	// @e2e clock-weather-widgets::upstream-failure-degrades-gracefully
	test('a failing provider serves the last reading as stale, and with none the tile shows an error state', async ({
		page,
	}) => {
		test.skip(!reachable, UNREACHABLE)
		const errors: Error[] = []
		page.on('pageerror', (e) => errors.push(e))

		const known = `Delft-${STAMP}`
		const knownId = await weatherTile(known)
		const first = await admin.get(`${API}/weather/${knownId}`)
		expect(first.status(), await first.text()).toBe(200)
		const fresh = await first.json()

		const unknown = `Gouda-${STAMP}`
		modes.set(known, 'fail')
		modes.set(unknown, 'fail')
		const unknownId = await weatherTile(unknown)
		try {
			// Age the cached reading past a one-second TTL.
			await writeConfig(TTL_KEY, '1')
			await new Promise((resolve) => setTimeout(resolve, 2_100))

			const degraded = await admin.get(`${API}/weather/${knownId}`)
			expect(degraded.status(), await degraded.text()).toBe(200)
			const stale = await degraded.json()
			expect(stale.stale).toBe(true)
			expect(stale.tempValue).toBe(fresh.tempValue)
			expect(stale.fetchedAt).toBe(fresh.fetchedAt)
			expect(hitsFor(known).length, 'the provider was tried').toBeGreaterThan(
				1,
			)

			const failed = await admin.get(`${API}/weather/${unknownId}`)
			expect(failed.status()).toBe(502)
			expect(await failed.json()).toEqual({
				status: 'error',
				error: 'weather_unavailable',
			})

			await gotoLaunchPad(page)
			const staleTile = page.locator(`[data-placement-id="${knownId}"]`)
			await expect(staleTile.locator('.weather-widget__badge')).toHaveText(
				'Showing last known reading',
				{ timeout: 30_000 },
			)
			await expect(staleTile.locator('.weather-widget__temp')).toHaveText(
				'12°C',
			)

			const errorTile = page.locator(`[data-placement-id="${unknownId}"]`)
			const state = errorTile.locator('.weather-widget__state--error')
			await expect(state).toBeVisible({ timeout: 30_000 })
			await expect(state).toContainText(/weather/i)
			await expect(state.getByRole('button', { name: 'Retry' })).toBeVisible()
			await expect(
				page.locator(MORE_MENU),
				'the page did not crash',
			).toBeVisible()
			expect(errors).toEqual([])
		} finally {
			await writeConfig(TTL_KEY, null)
		}
	})

	// @e2e clock-weather-widgets::units-follow-the-user-locale
	test('a Dutch locale reads metric and a US locale imperial, each stated in the answer', async () => {
		test.skip(!reachable, UNREACHABLE)
		for (const [user, units, temp] of [
			[dutch, 'metric', 12.4],
			[american, 'imperial', 54.3],
		] as Array<[Credentials, string, number]>) {
			const api = await apiAs(user.username, user.password)
			try {
				const dash = await seedOwnDashboard(api, `Weer ${units} ${STAMP}`)
				const location = `Rotterdam-${units}-${STAMP}`
				const id = await addPlacement(api, dash.id, 'weather', {
					location,
					unitsOverride: '',
				})
				const res = await api.get(`${API}/weather/${id}`)
				expect(res.status(), await res.text()).toBe(200)
				const body = await res.json()
				expect(body.units).toBe(units)
				expect(body.tempValue).toBe(temp)
				expect(hitsFor(location)[0].query.get('units')).toBe(units)
			} finally {
				await api.dispose()
			}
		}
	})

	// @e2e clock-weather-widgets::author-override-of-units
	test('an imperial override wins over a metric viewer locale', async () => {
		test.skip(!reachable, UNREACHABLE)
		const api = await apiAs(dutch!.username, dutch!.password)
		try {
			const dash = await seedOwnDashboard(api, `Weer override ${STAMP}`)
			const location = `Nijmegen-${STAMP}`
			const id = await addPlacement(api, dash.id, 'weather', {
				location,
				unitsOverride: 'imperial',
			})
			const res = await api.get(`${API}/weather/${id}`)
			expect(res.status(), await res.text()).toBe(200)
			const body = await res.json()
			expect(body.units).toBe('imperial')
			expect(body.tempValue).toBe(54.3)
			expect(hitsFor(location)[0].query.get('units')).toBe('imperial')
		} finally {
			await api.dispose()
		}
	})

	// @e2e clock-weather-widgets::forecast-language-follows-the-locale
	test('a Dutch user gets a Dutch condition with language nl, and English when the provider has none', async ({
		browser,
	}) => {
		test.skip(!reachable, UNREACHABLE)
		const api = await apiAs(dutch!.username, dutch!.password)
		const { context, page } = await loginInZone(browser, dutch!, BROWSER_ZONE)
		try {
			const dash = await seedOwnDashboard(api, `Weer taal ${STAMP}`)
			const localised = `Haarlem-${STAMP}`
			const bare = `Zwolle-${STAMP}`
			modes.set(bare, 'nodesc')
			const localisedId = await addPlacement(api, dash.id, 'weather', {
				location: localised,
				unitsOverride: '',
			})
			const bareId = await addPlacement(
				api,
				dash.id,
				'weather',
				{ location: bare, unitsOverride: '' },
				1,
			)

			const res = await api.get(`${API}/weather/${localisedId}`)
			expect(res.status(), await res.text()).toBe(200)
			const body = await res.json()
			expect(body.language).toBe('nl')
			expect(body.conditionText).toBe('Lichte regen')
			expect(
				hitsFor(localised)[0].query.get('lang'),
				'requested in Dutch',
			).toBe('nl')

			const fallback = await (await api.get(`${API}/weather/${bareId}`)).json()
			expect(fallback.conditionText, 'English is the fallback').toBe('Rain')

			await page.goto('/index.php/apps/launchpad/')
			await expect(
				page.locator(
					`[data-placement-id="${localisedId}"] .weather-widget__condition-text`,
				),
			).toHaveText('Lichte regen', { timeout: 30_000 })
		} finally {
			await context.close()
			await api.dispose()
		}
	})

	// @e2e clock-weather-widgets::condition-is-not-conveyed-by-icon-or-colour-alone
	test('the condition has an icon and a text label, and the temperature a label with its unit', async ({
		page,
	}) => {
		test.skip(!reachable, UNREACHABLE)
		const id = await weatherTile(`Breda-${STAMP}`)
		await gotoLaunchPad(page)
		const tile = page.locator(`[data-placement-id="${id}"]`)

		const icon = tile.locator('.weather-widget__icon')
		await expect(icon).toBeVisible({ timeout: 30_000 })
		await expect(icon.locator('svg')).toHaveCount(1)
		await expect(tile.locator('.weather-widget__condition-text')).toHaveText(
			'Light rain',
		)

		const temp = tile.locator('.weather-widget__temp')
		await expect(temp).toHaveText('12°C')
		await expect(temp).toHaveAttribute('aria-label', '12 degrees Celsius')
	})
})
