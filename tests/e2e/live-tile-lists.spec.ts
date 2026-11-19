/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for integrations-live-tile-lists (task 5): a URL-mode live
 * tile shows a list from a fixture endpoint.
 *
 * The fixture is a small HTTP server this spec starts. It answers the
 * "Latest documents" shape (SharePoint's `value` array) with three documents,
 * an extra field the mapping does not name, and one script link.
 * The tile maps name, link and date, shows two rows, and the spec checks what
 * the server answers (mapped fields only, the limit kept) and what the
 * dashboard shows (two rows, an https link, no link for the script one).
 *
 * Nextcloud fetches the fixture itself, so it must reach the server: set
 * LIVE_TILE_FIXTURE_HOST to the address Nextcloud can reach this runner on
 * (default 127.0.0.1, right when both run on one machine as in CI). A
 * Nextcloud that refuses local addresses (`allow_local_remote_servers` off)
 * cannot fetch it; the spec then skips and says so rather than failing the
 * tile.
 *
 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md
 */
import type { APIRequestContext } from '@playwright/test'
import type { Server } from 'node:http'
import type { AddressInfo } from 'node:net'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { createServer } from 'node:http'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const API = '/index.php/apps/launchpad/api'
const CONFIG =
	'/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad/livetile_allowed_hosts'
const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}
const FIXTURE_HOST = process.env.LIVE_TILE_FIXTURE_HOST || '127.0.0.1'
const STAMP = Date.now().toString(36)

const DOCUMENTS = {
	value: [
		{
			name: `Raadsvoorstel begroting ${STAMP}.docx`,
			webUrl: 'https://intranet.example.org/sites/raad/begroting.docx',
			lastModifiedDateTime: '2026-11-16T09:12:00Z',
			internalNote: 'not for the tile',
		},
		{
			name: `Notulen commissie ${STAMP}.pdf`,
			webUrl: 'javascript:alert(1)',
			lastModifiedDateTime: '2026-11-15T14:30:00Z',
			internalNote: 'not for the tile',
		},
		{
			name: `Planning ${STAMP}.xlsx`,
			webUrl: 'https://intranet.example.org/sites/raad/planning.xlsx',
			lastModifiedDateTime: '2026-11-14T08:00:00Z',
			internalNote: 'not for the tile',
		},
	],
}

let server: Server
let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let placementId = 0
let previousHosts: string | null = null
let fixtureUrl = ''

/**
 * The app value of the live-tile host allow-list, or null when unset.
 */
async function readHosts(): Promise<string | null> {
	const res = await admin.get(`${CONFIG}?format=json`)
	if (!res.ok()) {
		return null
	}
	const data = (await res.json())?.ocs?.data
	return typeof data?.data === 'string' ? data.data : null
}

/**
 * Write the app value of the live-tile host allow-list.
 *
 * @param value The JSON list.
 */
async function writeHosts(value: string): Promise<void> {
	const res = await admin.post(`${CONFIG}?format=json`, { form: { value } })
	expect(res.ok(), `set livetile_allowed_hosts -> ${res.status()}`).toBeTruthy()
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	server = createServer((req, res) => {
		if (req.url?.startsWith('/documents.json')) {
			res.writeHead(200, { 'Content-Type': 'application/json' })
			res.end(JSON.stringify(DOCUMENTS))
			return
		}
		res.writeHead(404)
		res.end()
	})
	await new Promise<void>((resolve) =>
		server.listen(0, '0.0.0.0', () => resolve()),
	)
	const { port } = server.address() as AddressInfo
	fixtureUrl = `http://${FIXTURE_HOST}:${port}/documents.json`

	admin = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	previousHosts = await readHosts()
	await writeHosts(JSON.stringify([FIXTURE_HOST]))

	dashboard = await seedActiveDashboard(admin, `Live tile lists ${STAMP}`)
	const widget = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
		data: {
			widgetId: 'livetile',
			gridX: 0,
			gridY: 0,
			gridWidth: 6,
			gridHeight: 6,
			content: {
				label: 'Latest documents',
				sourceMode: 'url',
				url: fixtureUrl,
				valueExpr: '$.value',
				display: 'list',
				limit: 2,
				mapping: [
					{ label: 'Name', path: 'name', role: 'title' },
					{ label: 'Link', path: 'webUrl', role: 'link' },
					{ label: 'Changed', path: 'lastModifiedDateTime', role: 'date' },
				],
			},
		},
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)
	const body = await widget.json()
	placementId = Number(
		body.widget?.id ?? body.placement?.id ?? body.data?.id ?? body.id,
	)
	expect(placementId, `no placement id in ${JSON.stringify(body)}`).toBeTruthy()
})

test.afterAll(async () => {
	if (admin) {
		await removeSeededDashboard(admin, dashboard)
		await writeHosts(previousHosts ?? '[]').catch(() => undefined)
		await admin.dispose()
	}
	await new Promise<void>((resolve) => server?.close(() => resolve()))
})

// @e2e live-data-tile-widget::personal-data-in-the-payload-stays-on-the-server
// @e2e live-data-tile-widget::script-link-shown-as-text
test('the server maps the fixture into two rows of the named fields', async () => {
	const res = await admin.get(`${API}/livetile/${placementId}`)
	expect(res.status(), await res.text()).toBe(200)
	const reading = await res.json()
	const text = JSON.stringify(reading)
	// A source Nextcloud cannot reach answers an empty, stale reading.
	test.skip(
		reading.fetchedAt === null && !Array.isArray(reading.items),
		`Nextcloud could not fetch ${fixtureUrl}; set LIVE_TILE_FIXTURE_HOST to an address it reaches, or allow local remote servers`,
	)

	expect(reading.display).toBe('list')
	expect(reading.items, 'the limit of two is kept').toHaveLength(2)
	expect(
		text,
		'a field the mapping does not name never leaves the server',
	).not.toContain('internalNote')
	expect(text).not.toContain('not for the tile')
	expect(text, 'the source URL is not in the answer').not.toContain(fixtureUrl)

	const first = reading.items[0] as Array<{
		label: string
		role: string
		value: unknown
	}>
	expect(first.map((f) => f.role)).toEqual(['title', 'link', 'date'])
	expect(first.find((f) => f.role === 'title')?.value).toBe(
		DOCUMENTS.value[0].name,
	)
	expect(first.find((f) => f.role === 'link')?.value).toBe(
		DOCUMENTS.value[0].webUrl,
	)
	const second = reading.items[1] as Array<{ role: string; value: unknown }>
	expect(
		second.find((f) => f.role === 'link')?.value ?? null,
		'a script link is dropped',
	).toBeNull()
})

// @e2e live-data-tile-widget::script-link-shown-as-text
test('the dashboard shows the list with the https link only', async ({ page }) => {
	const probe = await (await admin.get(`${API}/livetile/${placementId}`)).json()
	test.skip(!Array.isArray(probe.items), `Nextcloud could not fetch ${fixtureUrl}`)

	await gotoLaunchPad(page)
	const tile = page
		.locator('.live-tile-widget')
		.filter({ hasText: 'Latest documents' })
		.first()
	await expect(tile).toBeVisible({ timeout: 30_000 })
	const rows = tile.locator('.live-tile-widget__list-item')
	await expect(rows).toHaveCount(2, { timeout: 30_000 })

	const link = rows.nth(0).locator('a.live-tile-widget__list-title')
	await expect(link).toHaveText(DOCUMENTS.value[0].name)
	await expect(link).toHaveAttribute('href', DOCUMENTS.value[0].webUrl)
	await expect(rows.nth(1).locator('a')).toHaveCount(0)
	await expect(rows.nth(1)).toContainText(DOCUMENTS.value[1].name)
	await expect(tile).not.toContainText(DOCUMENTS.value[2].name)
	await expect(tile).not.toContainText('not for the tile')
})
