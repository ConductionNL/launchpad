/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The six scenarios of the open change dashboard-period-and-download
 * (REQ-DPD-001 to REQ-DPD-003, a delta on `dashboards`): the line under the
 * title, the period the dated widgets follow, and Download.
 *
 * dashboard-download.spec.ts covers the download half too, but it anchors to
 * the spec path without a scenario slug, so gate-19 credits it with none of
 * the six. This file cites each one and drives it.
 *
 * THE FIXTURE
 * ===========
 * Pieter is a plain member of the group Bedrijfsvoering, so a group-shared
 * dashboard of that group is view-only for him (REQ-DASH-014,
 * PermissionService::groupSharedPermissionLevel()). Managementinformatie is
 * such a dashboard, with:
 *   - "Zaken per status", an `object-list` whose source names
 *     `periodField: "startDate"`. It reads OpenRegister from the browser, so
 *     the spec answers that read itself (as runtime-or-consumption does): the
 *     rows it returns name the `startDate[gte]` the request carried, and
 *     their number depends on the period. The counting itself is
 *     OpenRegister's job; LaunchPad's is to put the period on the query,
 *     and that is what a red here means.
 *   - "Rapport Sociaal domein", an iframe on a host that never resolves
 *     (RFC 2606), standing in for the Power BI embed.
 * Mijn werkplek is Pieter's own personal dashboard, made his default; no
 * widget on it names a period field.
 *
 * Pieter reads in Dutch, because the scenarios quote the Dutch words.
 *
 * Not serial on purpose: "A view-only group dashboard" is expected red (see
 * below) and a serial file would skip every test after it.
 *
 * EXPECTED RED
 * ============
 * a-view-only-group-dashboard: DashboardPageHeader::kindLabel() returns ''
 * for a group dashboard, so "Groepsdashboard van Bedrijfsvoering" is never
 * shown; only personal and shared dashboards get a kind. The test asserts the
 * scenario, so it stays red until the header names the group.
 *
 * The scenarios word the last change as "bijgewerkt 10:45"; the shipped nl
 * catalogue says "Laatst aangepast vandaag 10:45". The tests assert the time,
 * not that wording, which is a translation choice and not this gate's to settle.
 *
 * Written in the cloud lane without a live instance; not yet run.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::a-view-only-group-dashboard
 *   @e2e dashboards::pieters-own-default-dashboard
 *   @e2e dashboards::pieter-looks-at-the-quarter
 *   @e2e dashboards::a-dashboard-without-dated-sources
 *   @e2e dashboards::pieter-downloads-the-quarter
 *   @e2e dashboards::the-embedded-report
 *
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md
 */

import type { APIRequestContext, Locator, Page, Request } from '@playwright/test'
import type { DashboardSettings } from './support/dashboardsSpec.ts'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	allowPersonalDashboardsFreely,
	createGroupDashboard,
	removeGroupDashboards,
	setActivePreference,
	writeSettings,
} from './support/dashboardsSpec.ts'
import { isOrListUrl, OR_REGISTER, OR_SCHEMA, setLanguage } from './support/orWidget.ts'
import {
	addToGroup,
	adminContext,
	API,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const HOSTS_CONFIG =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/iframe_allowed_hosts'

/** Never resolves (RFC 2606), so no runner reaches it. */
const EMBED_HOST = 'powerbi.e2e.invalid'
const EMBED_URL = `https://${EMBED_HOST}/rapport`
const EMBED_TITLE = 'Rapport Sociaal domein'
const LIST_TITLE = 'Zaken per status'

/** How many rows the stand-in OpenRegister returns per period. */
const ROWS_PER_PERIOD: Record<string, number> = { month: 1, quarter: 3, year: 7, none: 12 }

/** A row of the stand-in answer: `Zaak <n> vanaf <startDate[gte]>`. */
const ROW_TEXT = /^Zaak \d+ vanaf /

test.setTimeout(150_000)

let admin: APIRequestContext
let reader: Principal | null = null
let groupId: string | null = null
let settingsBefore: DashboardSettings | null = null
let hostsBefore: string | null = null
let management: { id: number; uuid: string; name: string } | null = null
let workplace: { id: number; uuid: string; name: string } | null = null

/**
 * The iframe allow-list as stored, or null when unset.
 *
 * @return the JSON string.
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
 * Store the iframe allow-list, or unset it.
 *
 * @param value the JSON string, or null to unset.
 * @return nothing.
 */
async function writeHosts(value: string | null): Promise<void> {
	const res = value === null
		? await admin.delete(`${HOSTS_CONFIG}?format=json`)
		: await admin.post(`${HOSTS_CONFIG}?format=json`, { form: { value } })
	expect(res.ok(), `iframe_allowed_hosts -> ${res.status()}`).toBeTruthy()
}

/**
 * Place a widget as the admin.
 *
 * @param dashboardId the dashboard.
 * @param data the placement body.
 * @return nothing.
 */
async function place(dashboardId: number, data: Record<string, unknown>): Promise<void> {
	const res = await admin.post(`${API}/dashboard/${dashboardId}/widgets`, { data })
	expect(res.status(), await res.text()).toBeLessThan(300)
}

/**
 * The first day of the period holding today, as `YYYY-MM-DD`: the spec's
 * "the period's start". In the runner's local time, which is the browser's:
 * playwright.config.ts sets no `timezoneId`.
 *
 * @param period `month`, `quarter` or `year`.
 * @return the date.
 */
function periodStart(period: string): string {
	const now = new Date()
	const month = period === 'year' ? 0 : period === 'quarter' ? Math.floor(now.getMonth() / 3) * 3 : now.getMonth()
	return `${now.getFullYear()}-${String(month + 1).padStart(2, '0')}-01`
}

/**
 * How many cases the stand-in register holds from a date on. In the first
 * month of a quarter the month and the quarter start on the same day, and
 * then they hold the same cases, as OpenRegister's answer would.
 *
 * @param from the `startDate[gte]` asked, or null for none.
 * @return the row count.
 */
function rowsFrom(from: string | null): number {
	const period = ['month', 'quarter', 'year'].find((p) => from === periodStart(p)) ?? 'none'
	return ROWS_PER_PERIOD[period]
}

/**
 * Answer the object-list's OpenRegister reads: rows that name the
 * `startDate[gte]` the request carried, as many as that period holds.
 *
 * @param page the reader's page, before its first navigation.
 * @return the list reads seen, in order.
 */
async function standInRegister(page: Page): Promise<Request[]> {
	const seen: Request[] = []
	await page.route((url) => isOrListUrl(url), async (route) => {
		seen.push(route.request())
		const query = new URL(route.request().url()).searchParams
		const from = query.get('startDate[gte]')
		const results = Array.from({ length: rowsFrom(from) }, (_, i) => ({
			id: `zaak-${from ?? 'altijd'}-${i + 1}`,
			title: `Zaak ${i + 1} vanaf ${from ?? 'altijd'}`,
			startDate: from ?? '2020-01-01',
		}))
		await route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ results, total: results.length, page: 1, pages: 1 }),
		})
	})
	await page.route(
		(url) => url.pathname.endsWith(`/apps/openregister/api/schemas/${OR_SCHEMA}`),
		(route) => route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({
				id: 1,
				slug: OR_SCHEMA,
				title: 'Zaak',
				properties: {
					title: { type: 'string', title: 'Title' },
					startDate: { type: 'string', format: 'date', title: 'Startdatum' },
				},
			}),
		}),
	)
	return seen
}

/**
 * Replace the print dialog with a recorder of what it would print: the
 * document title, the list rows and the embed's print note. A real print
 * dialog cannot be driven headless; the print styles are checked with
 * `emulateMedia` instead, since a stubbed print() runs under screen styles.
 *
 * @param page the reader's page, before its first navigation.
 * @return nothing.
 */
async function recordPrints(page: Page): Promise<void> {
	await page.addInitScript(() => {
		type Printed = { title: string; rows: string; note: string }
		const w = window as unknown as { __prints: Printed[] }
		w.__prints = []
		window.print = () => {
			const list = document.querySelector('.cn-object-list-widget')
			const note = document.querySelector('[data-testid="iframe-widget-print-note"]')
			w.__prints.push({
				title: document.title,
				rows: list instanceof HTMLElement ? list.innerText : '',
				note: note instanceof HTMLElement ? (note.textContent ?? '').trim() : '',
			})
		}
	})
}

/**
 * The page header of the open dashboard, once it shows the given name.
 *
 * @param page the reader's page.
 * @param name the dashboard name.
 * @return the header.
 */
async function headerOf(page: Page, name: string): Promise<Locator> {
	const header = page.getByTestId('dashboard-page-header')
	await expect(header.locator('h1')).toHaveText(name, { timeout: 45_000 })
	return header
}

/**
 * Open a dashboard as Pieter.
 *
 * @param page Pieter's page.
 * @param dashboard the dashboard.
 * @param search an optional query string, e.g. `?period=quarter`.
 * @return its header.
 */
async function open(page: Page, dashboard: { uuid: string; name: string }, search = ''): Promise<Locator> {
	await setActivePreference(reader!.api, dashboard.uuid)
	await page.goto(`${APP}/${search}`)
	return headerOf(page, dashboard.name)
}

/**
 * The "Zaken per status" list on the page.
 *
 * @param page the page.
 * @return its locator.
 */
function caseList(page: Page): Locator {
	return page.locator('.cn-object-list-widget').first()
}

test.beforeAll(async () => {
	admin = await adminContext()
	const stamp = `${Date.now().toString(36)}${Math.floor(Math.random() * 1000)}`
	settingsBefore = await allowPersonalDashboardsFreely(admin)
	hostsBefore = await readHosts()
	const hosts = new Set<string>(hostsBefore ? JSON.parse(hostsBefore) : [])
	hosts.add(EMBED_HOST)
	await writeHosts(JSON.stringify([...hosts]))

	groupId = await createGroup(admin, `Bedrijfsvoering-${stamp}`)
	reader = await createPrincipal(admin, 'pieter')
	await addToGroup(admin, reader.username, groupId)
	await setLanguage(reader, 'nl')

	const name = `Managementinformatie ${stamp}`
	const row = await createGroupDashboard(admin, groupId, name)
	management = { id: Number(row.id), uuid: String(row.uuid), name }
	await place(management.id, {
		widgetId: 'object-list',
		gridX: 0,
		gridY: 0,
		gridWidth: 6,
		gridHeight: 6,
		customTitle: LIST_TITLE,
		content: {
			register: OR_REGISTER,
			schema: OR_SCHEMA,
			periodField: 'startDate',
			columns: [{ key: 'title', label: 'Title' }],
			limit: 25,
		},
	})
	await place(management.id, {
		widgetId: 'iframe',
		gridX: 6,
		gridY: 0,
		gridWidth: 6,
		gridHeight: 6,
		content: { url: EMBED_URL, title: EMBED_TITLE, sandbox: [] },
	})

	// Mijn werkplek: Pieter's own, his default, with a text widget only.
	const wpName = `Mijn werkplek ${stamp}`
	const created = await reader.api.post(`${API}/dashboard`, { data: { name: wpName } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const wp = (await created.json()).dashboard
	workplace = { id: Number(wp.id), uuid: String(wp.uuid), name: wpName }
	const text = await reader.api.post(`${API}/dashboard/${workplace.id}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			content: { text: 'Goedemorgen', contentMode: 'markdown' },
		},
	})
	expect(text.status(), await text.text()).toBeLessThan(300)
	const pinned = await reader.api.post(`${API}/dashboards/default`, { data: { uuid: workplace.uuid } })
	expect(pinned.status(), await pinned.text()).toBe(200)
	expect((await pinned.json()).uuid).toBe(workplace.uuid)
})

test.afterAll(async () => {
	if (management !== null && groupId !== null) {
		await removeGroupDashboards(admin, [{ groupId, uuid: management.uuid }])
	}
	await removePrincipal(admin, reader)
	await removeGroup(admin, groupId)
	if (settingsBefore !== null) {
		await writeSettings(admin, settingsBefore)
	}
	await writeHosts(hostsBefore)
	await admin.dispose()
})

test('a view-only group dashboard names its group, the reader\'s rights and the last change', async ({ browser }) => {
	// @e2e dashboards::a-view-only-group-dashboard
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	try {
		await standInRegister(page)
		const header = await open(page, management!)
		const meta = header.locator('.launchpad-page-header__meta')
		await expect(meta).toBeVisible()
		// The kind of dashboard, naming the group (EXPECTED RED, see the header).
		await expect(header.getByTestId('dashboard-page-header-kind')).toHaveText(
			`Groepsdashboard van ${groupId}`,
			{ timeout: 15_000 },
		)
		// Then what he may do, then when it last changed: today, so a time.
		await expect(meta.locator('span').last()).toHaveText(/^alleen bekijken · .*\b\d{1,2}[:.]\d{2}$/)
		await expect(meta).not.toContainText('opent als eerste')
	} finally {
		await context.close()
	}
})

test('Pieter\'s own default dashboard says it is personal and opens first', async ({ browser }) => {
	// @e2e dashboards::pieters-own-default-dashboard
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	try {
		const header = await open(page, workplace!)
		await expect(header.getByTestId('dashboard-page-header-kind')).toHaveText(
			'Persoonlijk dashboard · opent als eerste',
			{ timeout: 15_000 },
		)
		// Followed by when it last changed (today: a time), and no rights
		// words: he has full rights on his own dashboard.
		const line = header.locator('.launchpad-page-header__meta > span').last()
		await expect(line).toHaveText(/\b\d{1,2}[:.]\d{2}$/)
		await expect(line).not.toContainText('alleen bekijken')
		await expect(line).not.toContainText('u mag widgets toevoegen')
	} finally {
		await context.close()
	}
})

test('a dashboard without dated sources shows no period choice', async ({ browser }) => {
	// @e2e dashboards::a-dashboard-without-dated-sources
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	try {
		// The control exists on a dashboard with a dated source, so its
		// absence below is about the dashboard and not a missing feature.
		await standInRegister(page)
		const dated = await open(page, management!)
		await expect(dated.getByTestId('dashboard-period')).toBeVisible({ timeout: 15_000 })

		const header = await open(page, workplace!)
		await expect(header.getByTestId('dashboard-download')).toBeVisible()
		await expect(page.getByText('Goedemorgen').first()).toBeVisible({ timeout: 20_000 })
		await expect(header.getByTestId('dashboard-period')).toHaveCount(0)
		await expect(page.getByRole('radio', { name: 'Kwartaal' })).toHaveCount(0)
	} finally {
		await context.close()
	}
})

test('choosing Kwartaal narrows the dated list to this quarter, keeps it in the address and leaves the embed alone', async ({ browser }) => {
	// @e2e dashboards::pieter-looks-at-the-quarter
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	try {
		const reads = await standInRegister(page)
		const writes: string[] = []
		page.on('request', (r) => {
			if (r.method() !== 'GET' && new URL(r.url()).pathname.includes('/apps/launchpad/api/')) {
				writes.push(`${r.method()} ${r.url()}`)
			}
		})
		const header = await open(page, management!)
		const period = header.getByTestId('dashboard-period')
		await expect(period).toBeVisible({ timeout: 15_000 })
		// This month first.
		await expect(period.getByRole('radio', { name: 'Deze maand' })).toHaveAttribute('aria-checked', 'true')
		const list = caseList(page)
		await expect(list.getByText(ROW_TEXT).first()).toBeVisible({ timeout: 30_000 })

		const embed = page.locator('.iframe-widget').first()
		await expect(embed).toBeAttached({ timeout: 20_000 })
		const embedText = await embed.innerText()
		await embed.evaluate((el) => {
			(el as unknown as { __e2eMark?: number }).__e2eMark = 1
		})
		const readsBefore = reads.length
		const writesBefore = writes.length

		await period.getByRole('radio', { name: 'Kwartaal' }).click()

		const quarter = periodStart('quarter')
		// The list asked OpenRegister for cases started this quarter...
		await expect.poll(() => reads.length, { timeout: 20_000 }).toBeGreaterThan(readsBefore)
		const last = new URL(reads[reads.length - 1].url()).searchParams
		expect(last.get('startDate[gte]'), 'the period start on the query').toBe(quarter)
		expect(last.get('startDate[lte]'), 'the period runs until now').toBeTruthy()
		// ...and shows only those.
		await expect(list.getByText(ROW_TEXT)).toHaveCount(rowsFrom(quarter), { timeout: 20_000 })
		await expect(list.getByText(`Zaak 1 vanaf ${quarter}`)).toBeVisible()

		// The address carries the period, so a copied link opens with it.
		await expect.poll(() => new URL(page.url()).searchParams.get('period')).toBe('quarter')

		// The embed names no period field: the same element, the same content.
		expect(
			await embed.evaluate((el) => (el as unknown as { __e2eMark?: number }).__e2eMark),
			'the embed was not re-mounted',
		).toBe(1)
		expect(await embed.innerText()).toBe(embedText)

		// Choosing a period never writes the stored dashboard.
		expect(writes.slice(writesBefore)).toEqual([])
	} finally {
		await context.close()
	}
})

test('Pieter downloads the quarter: the print carries the quarter\'s figures and nothing is created', async ({ browser }) => {
	// @e2e dashboards::pieter-downloads-the-quarter
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	try {
		await standInRegister(page)
		await recordPrints(page)
		const exportsBefore = await reader!.api.get(`${API}/exports`)
		const exportsBeforeBody = exportsBefore.ok() ? await exportsBefore.text() : null
		const sent: string[] = []
		page.on('request', (r) => {
			const path = new URL(r.url()).pathname
			if (path.includes('/apps/launchpad/api/exports') || (r.method() !== 'GET' && path.includes('/apps/launchpad/api/'))) {
				sent.push(`${r.method()} ${path}`)
			}
		})

		const header = await open(page, management!)
		await expect(header.locator('.launchpad-page-header__meta')).toContainText('alleen bekijken')
		await header.getByTestId('dashboard-period').getByRole('radio', { name: 'Kwartaal' }).click()
		const quarter = periodStart('quarter')
		const list = caseList(page)
		await expect(list.getByText(ROW_TEXT)).toHaveCount(rowsFrom(quarter), { timeout: 30_000 })
		const sentBefore = sent.length

		await header.getByTestId('dashboard-download').click()

		await expect.poll(
			() => page.evaluate(() => (window as unknown as { __prints: unknown[] }).__prints.length),
		).toBe(1)
		const printed = await page.evaluate(
			() => (window as unknown as { __prints: Array<{ title: string; rows: string }> }).__prints[0],
		)
		// The PDF is saved under the dashboard's name...
		expect(printed.title).toBe(management!.name)
		// ...with the quarter's figures, as on screen.
		expect(printed.rows).toContain(`Zaak ${rowsFrom(quarter)} vanaf ${quarter}`)
		expect(printed.rows).not.toContain(`Zaak ${rowsFrom(quarter) + 1} vanaf`)

		// No scheduled export or run: nothing was sent for the download.
		expect(sent.slice(sentBefore)).toEqual([])
		const exportsAfter = await reader!.api.get(`${API}/exports`)
		expect(exportsAfter.status()).toBe(exportsBefore.status())
		if (exportsBeforeBody !== null) {
			expect(await exportsAfter.text(), 'the reader\'s exports are unchanged').toBe(exportsBeforeBody)
		}
	} finally {
		await context.close()
	}
})

test('the downloaded dashboard names the embedded report with a line that it is not included', async ({ browser }) => {
	// @e2e dashboards::the-embedded-report
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	try {
		await standInRegister(page)
		await recordPrints(page)
		const header = await open(page, management!)
		await expect(page.locator('.iframe-widget').first()).toBeAttached({ timeout: 20_000 })
		const expected = `${EMBED_TITLE}: ingesloten pagina's zitten niet in de download`

		// On screen the note is not drawn.
		const note = page.getByTestId('iframe-widget-print-note')
		await expect(note).toBeHidden()

		// Download prints the page with the note in it...
		await header.getByTestId('dashboard-download').click()
		await expect.poll(
			() => page.evaluate(() => (window as unknown as { __prints: unknown[] }).__prints.length),
		).toBe(1)
		const printed = await page.evaluate(
			() => (window as unknown as { __prints: Array<{ title: string; note: string }> }).__prints[0],
		)
		expect(printed.title).toBe(management!.name)
		expect(printed.note).toBe(expected)

		// ...and under the print styles the PDF names the embed and leaves the
		// embedded page itself out.
		await page.emulateMedia({ media: 'print' })
		await expect(note).toBeVisible()
		await expect(note).toHaveText(expected)
		const embedded = page.locator('.iframe-widget__frame, .iframe-widget__fallback, .iframe-widget__state')
		const shown = await embedded.evaluateAll((els) => els.filter((el) => getComputedStyle(el).display !== 'none').length)
		expect(shown, 'no part of the embedded page is printed').toBe(0)
	} finally {
		await context.close()
	}
})
