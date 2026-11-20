/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the office tile pack
 * (integrations-office-tiles, task 5): place the pack from the add flow,
 * create a document twice from its "New ..." tile and find two files, the
 * first one unchanged; and an unknown creator is refused.
 *
 * Needs an office app that registers file creators (Nextcloud Office). On an
 * instance without one the pack has no "New ..." tile and the creation test
 * is skipped with that reason; the refusal test runs everywhere.
 *
 * The add flow is opened through "More" → "Add widget".
 * Accounts are throwaway non-admin accounts logged in from an empty storage
 * state.
 *
 * Gate-19 @e2e traceability:
 *   @e2e office-tiles::pack-placed-and-document-created-twice
 *   @e2e office-tiles::unknown-creator-refused
 *
 * @spec openspec/changes/integrations-office-tiles/specs/office-tiles/spec.md
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
const FILE_NAME = `Notulen ${Date.now()}`

type Account = { username: string; password: string }
type PackTile = { title: string; linkType: string; linkValue: string }

let account: Account | null = null

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
 * The account's pack as the server builds it.
 *
 * @return the tiles.
 */
async function packTiles(): Promise<PackTile[]> {
	const api = await apiAs(account!)
	try {
		const res = await api.get(`${API}/office-tiles`)
		expect(res.status(), await res.text()).toBe(200)
		return (await res.json()).tiles ?? []
	} finally {
		await api.dispose()
	}
}

/**
 * The WebDAV etag of a file of the account, or null when it does not exist.
 *
 * @param path the path below the account's root.
 * @return the etag or null.
 */
async function etagOf(path: string): Promise<string | null> {
	const api = await apiAs(account!)
	try {
		const url = `/remote.php/dav/files/${encodeURIComponent(account!.username)}/${path
			.replace(/^\/+/, '')
			.split('/')
			.map(encodeURIComponent)
			.join('/')}`
		const res = await api.fetch(url, {
			method: 'PROPFIND',
			headers: { Depth: '0', 'Content-Type': 'application/xml' },
			data: '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:getetag/></d:prop></d:propfind>',
		})
		if (res.status() === 404) {
			return null
		}
		expect(res.status(), await res.text()).toBe(207)
		const match = (await res.text()).match(/<d:getetag>([^<]*)<\/d:getetag>/)
		return match ? match[1] : ''
	} finally {
		await api.dispose()
	}
}

/**
 * Click a "New ..." tile, name the file and confirm; returns the created path.
 *
 * @param page the page.
 * @param title the tile's title.
 * @return the path the server reports.
 */
async function createFromTile(page: Page, title: string): Promise<string> {
	await page.goto('/index.php/apps/launchpad')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await page.locator('.launchpad-grid-item').getByText(title, { exact: true }).first().click()
	const dialog = page.getByRole('dialog').filter({ has: page.getByRole('textbox', { name: 'File name' }) })
	await expect(dialog).toBeVisible({ timeout: 10_000 })
	await dialog.getByRole('textbox', { name: 'File name' }).fill(FILE_NAME)
	// The new file opens in its editor, which navigates away and drops the
	// response body, so the body is read while the request passes through.
	let resolveCreated: (value: { status: number; body: string }) => void = () => {}
	const created = new Promise<{ status: number; body: string }>((resolve) => {
		resolveCreated = resolve
	})
	await page.route('**/api/office-tiles/create', async (route) => {
		const response = await route.fetch()
		resolveCreated({ status: response.status(), body: await response.text() })
		await route.fulfill({ response })
	})
	await dialog.getByRole('button', { name: 'Create' }).click()
	const response = await created
	await page.unroute('**/api/office-tiles/create')
	expect(response.status, response.body).toBe(201)
	const body = JSON.parse(response.body)
	expect(body.url, 'the new file opens in its editor').toBeTruthy()
	return String(body.path)
}

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}
	account = await provisionThrowawayUser('e2e-office')
	const api = await apiAs(account)
	try {
		const created = await api.post(`${API}/dashboard`, { data: { name: 'E2E Mijn werkplek' } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		const activate = await api.post(`${API}/dashboard/${dash.id}/activate`)
		expect(activate.status(), await activate.text()).toBeLessThan(300)
		const preference = await api.post(`${API}/dashboards/active`, { data: { uuid: dash.uuid } })
		expect(preference.status(), await preference.text()).toBeLessThan(300)
	} finally {
		await api.dispose()
	}
})

test.afterAll(async () => {
	if (account) {
		await deprovisionUser(account.username)
	}
})

// @e2e office-tiles::pack-placed-and-document-created-twice
test('the pack is placed from the add flow and a document created twice gives two files', async ({
	browser,
}) => {
	const tiles = await packTiles()
	const creators = tiles.filter((tile) => tile.linkType === 'create')
	test.skip(creators.length === 0, 'no office app on this instance registers a file creator')
	// Nextcloud Text registers a "Text document" creator too; the task is about the office app's.
	const officeCreators = creators.filter((tile) => !tile.linkValue.startsWith('text:'))
	test.skip(officeCreators.length === 0, 'no office app on this instance registers a file creator')
	const newDocument = officeCreators.find((tile) => /document/i.test(tile.title)) ?? officeCreators[0]

	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		// Place the pack: one container "Office" with a tile per creator and per enabled app.
		await page.goto('/index.php/apps/launchpad')
		const more = page.locator(MORE_MENU)
		await expect(more).toBeVisible({ timeout: 30_000 })
		await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
		await page.locator('[data-testid="cn-grouped-menu-item-add-widget"]').click()
		const picker = page.getByRole('dialog', { name: /add widget/i }).first()
		await expect(picker).toBeVisible({ timeout: 10_000 })
		const placed = page.waitForResponse((res) =>
			/\/api\/dashboard\/\d+\//.test(res.url()) && res.request().method() === 'POST',
		)
		await picker.locator('[data-testid="widget-picker-office-pack"]').click()
		expect((await placed).status()).toBeLessThan(300)
		await page.keyboard.press('Escape')

		const container = page.locator('.launchpad-grid-item').filter({ hasText: 'Office' }).last()
		await expect(container).toBeVisible({ timeout: 10_000 })
		for (const tile of tiles) {
			await expect(container).toContainText(tile.title)
		}

		// Create the same document twice: the second one gets a free name.
		const first = await createFromTile(page, newDocument.title)
		const firstEtag = await etagOf(first)
		expect(firstEtag, `${first} exists`).not.toBeNull()

		const second = await createFromTile(page, newDocument.title)
		expect(second).not.toBe(first)
		expect(second).toMatch(/\(2\)\.[a-z0-9]+$/i)
		expect(await etagOf(second), `${second} exists`).not.toBeNull()
		expect(await etagOf(first), 'the first file is unchanged').toBe(firstEtag)
	} finally {
		await context.close()
	}
})

// @e2e office-tiles::unknown-creator-refused
test('an unknown creator is refused with 400 and creates nothing', async () => {
	const api = await apiAs(account!)
	const name = `Nooit ${Date.now()}`
	try {
		const res = await api.post(`${API}/office-tiles/create`, {
			data: { creatorId: 'no-such-creator', name, folder: 'Documents' },
		})
		expect(res.status(), await res.text()).toBe(400)
	} finally {
		await api.dispose()
	}
	for (const extension of ['docx', 'odt', 'xlsx', 'ods', 'pptx', 'odp', 'md', 'txt']) {
		expect(await etagOf(`Documents/${name}.${extension}`)).toBeNull()
	}
})
