/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The first loads the workspace page carries in its initial state
 * (REQ-INIT-006..009, REQ-INIT-011), and what a caller the action matrix refuses gets
 * instead: no key on the page, and the bundle asks the endpoint itself and
 * meets the same refusal.
 *
 * Each refusal test narrows one action of the matrix to `admin` for its
 * duration and puts the matrix back exactly as it was (the matrix is global,
 * so the file runs serially).
 */

import type { APIRequestContext, Page, Response } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { mkdirs, putFile, removePlacements, seedFilesPlacement } from './support/filesWidget.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	removePrincipal,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const PREFIX = 'initial-state-launchpad-'
const ACTION_MATRIX = `${API}/admin/action-matrix`
const NAME = `E2E First loads ${Date.now()}`

type State = Record<string, unknown>
type Matrix = Record<string, string[]>

let admin: APIRequestContext
let pieter: Principal | null = null
let dash: { id: number; uuid: string }

test.describe.configure({ mode: 'serial' })

/**
 * The page's initial state, decoded from its hidden inputs.
 *
 * @param page The page.
 * @return Every key the page carries.
 */
async function initialState(page: Page): Promise<State> {
	return page.evaluate((prefix) => {
		const out: Record<string, unknown> = {}
		const inputs = document.querySelectorAll<HTMLInputElement>(`input[type="hidden"][id^="${prefix}"]`)
		for (const input of Array.from(inputs)) {
			const bytes = Uint8Array.from(atob(input.value), (c) => c.charCodeAt(0))
			out[input.id.slice(prefix.length)] = JSON.parse(new TextDecoder().decode(bytes))
		}
		return out
	}, PREFIX)
}

/**
 * The stored action matrix.
 *
 * @return The matrix, action to groups.
 */
async function readMatrix(): Promise<Matrix> {
	const res = await admin.get(ACTION_MATRIX)
	expect(res.status(), await res.text()).toBe(200)
	return (await res.json()).matrix as Matrix
}

/**
 * Store the action matrix.
 *
 * @param matrix The matrix.
 */
async function writeMatrix(matrix: Matrix): Promise<void> {
	const res = await admin.put(ACTION_MATRIX, { data: { matrix } })
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Run `body` while `action` is allowed to `admin` only, then restore the matrix.
 *
 * @param action The action key.
 * @param body What to run.
 */
async function whileRefused(action: string, body: () => Promise<void>): Promise<void> {
	const before = await readMatrix()
	// Action keys hold dots, which toHaveProperty reads as a path.
	expect(Object.keys(before), `the matrix knows ${action}`).toContain(action)
	await writeMatrix({ ...before, [action]: ['admin'] })
	try {
		await body()
	} finally {
		await writeMatrix(before)
	}
}

/**
 * Whether a response is a GET of exactly `path` under the app's API.
 *
 * @param res The response.
 * @param path The path below /api, e.g. `/tiles`.
 * @return Whether it matches.
 */
function isRead(res: Response, path: string): boolean {
	return res.request().method() === 'GET' && new URL(res.url()).pathname.endsWith(`/apps/launchpad/api${path}`)
}

test.beforeAll(async () => {
	admin = await adminContext()
	const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)

	pieter = await createPrincipal(admin, 'e2e-firstload')
	await assertActingAs(pieter.api, pieter.username)
	const created = await pieter.api.post(`${API}/dashboard`, { data: { name: NAME } })
	expect(created.status(), await created.text()).toBe(201)
	const body = (await created.json()).dashboard
	dash = { id: Number(body.id), uuid: String(body.uuid) }
	const active = await pieter.api.post(`${API}/dashboards/default`, { data: { uuid: dash.uuid } })
	expect(active.status(), await active.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	if (pieter && dash) {
		await pieter.api.delete(`${API}/dashboard/${dash.id}`)
	}
	await removePrincipal(admin, pieter)
	await admin.dispose()
})

// @e2e initial-state-contract::the-workspace-opens-without-asking-for-its-dashboards-again
test('the workspace opens from dashboardFirstLoad without asking for its dashboards', async ({ browser }) => {
	const { context, page } = await loginAs(browser, pieter!.username, pieter!.password)
	try {
		const asked: string[] = []
		page.on('request', (req) => {
			const path = new URL(req.url()).pathname
			if (req.method() === 'GET' && (path.endsWith('/apps/launchpad/api/dashboards/visible') || path.endsWith('/apps/launchpad/api/dashboard'))) {
				asked.push(path)
			}
		})
		const response = await page.goto(APP)
		expect(response?.status()).toBe(200)

		const state = await initialState(page)
		const firstLoad = state.dashboardFirstLoad as { visible: unknown; active: { dashboard?: { uuid?: string } } | null }
		expect(firstLoad, 'the page carries dashboardFirstLoad').toBeTruthy()
		expect(JSON.stringify(firstLoad.visible), 'the visible list names his dashboard').toContain(dash.uuid)
		expect(firstLoad.active?.dashboard?.uuid, 'the active dashboard is his').toBe(dash.uuid)

		// The store shows them: the workspace is up on his dashboard, named in
		// its heading (the store's active dashboard)...
		await expect(page.getByRole('heading', { level: 1, name: NAME })).toBeVisible({ timeout: 30_000 })
		await expect(page.locator('.grid-stack').first()).toBeAttached({ timeout: 30_000 })
		// ...and it never asked either endpoint.
		expect(asked).toEqual([])
	} finally {
		await context.close()
	}
})

// @e2e initial-state-contract::a-refused-caller-gets-no-first-load
test('a caller refused dashboard.visible gets no dashboardFirstLoad, and the store meets the same refusal', async ({ browser }) => {
	await whileRefused('dashboard.visible', async () => {
		const { context, page } = await loginAs(browser, pieter!.username, pieter!.password)
		try {
			const refused = page.waitForResponse((res) => isRead(res, '/dashboards/visible'), { timeout: 30_000 })
			const response = await page.goto(APP)
			expect(response?.status()).toBe(200)
			expect(await initialState(page)).not.toHaveProperty('dashboardFirstLoad')
			expect((await refused).status()).toBe(403)
		} finally {
			await context.close()
		}
	})
})

// @e2e initial-state-contract::a-refused-caller-gets-no-org-navigation
test('a caller refused the org-navigation position gets no orgNavigation, and the shell meets the same refusal', async ({ browser }) => {
	await whileRefused('admin-org-navigation.get-position', async () => {
		const { context, page } = await loginAs(browser, pieter!.username, pieter!.password)
		try {
			const refused = page.waitForResponse((res) => isRead(res, '/admin/org-navigation/position'), { timeout: 30_000 })
			const response = await page.goto(APP)
			expect(response?.status()).toBe(200)
			expect(await initialState(page)).not.toHaveProperty('orgNavigation')
			expect((await refused).status()).toBe(403)
		} finally {
			await context.close()
		}
	})
})

// @e2e initial-state-contract::a-refused-part-is-asked-for
test('a caller refused tile.index gets no tiles part, and the tile store meets the same refusal', async ({ browser }) => {
	await whileRefused('tile.index', async () => {
		const { context, page } = await loginAs(browser, pieter!.username, pieter!.password)
		try {
			const refused = page.waitForResponse((res) => isRead(res, '/tiles'), { timeout: 30_000 })
			const response = await page.goto(APP)
			expect(response?.status()).toBe(200)
			const state = await initialState(page)
			const bootReads = (state.workspaceBootReads ?? {}) as State
			expect(bootReads, 'the boot reads carry no tiles part').not.toHaveProperty('tiles')
			expect((await refused).status()).toBe(403)
		} finally {
			await context.close()
		}
	})
})

// @e2e initial-state-contract::a-refused-caller-gets-no-kept-manifest
test('a caller refused manifest.index gets no runtimeManifest, and the page meets the same refusal', async ({ browser }) => {
	await whileRefused('manifest.index', async () => {
		const { context, page } = await loginAs(browser, pieter!.username, pieter!.password)
		try {
			const refused = page.waitForResponse((res) => isRead(res, '/manifest'), { timeout: 30_000 })
			const response = await page.goto(APP)
			expect(response?.status()).toBe(200)
			expect(await initialState(page)).not.toHaveProperty('runtimeManifest')
			expect((await refused).status()).toBe(403)
		} finally {
			await context.close()
		}
	})
})

/** A carried read: the endpoint below /apps/launchpad, its query and its answer. */
type CarriedRead = { path: string, params?: Record<string, unknown>, data: unknown }

/** The view's first API reads REQ-INIT-011 says the page carries. */
function firstReadPaths(uuid: string, placementId: number): string[] {
	return [
		'/api/announcements',
		'/api/preferences/dismissed-notices',
		`/api/dashboards/${uuid}/reactions`,
		`/api/widgets/files/${placementId}/contents`,
	]
}

test.describe('the view\'s first API reads (REQ-INIT-011)', () => {
	const FILE = `first-read-${Date.now()}.txt`
	const FOLDER = `first-read-dir-${Date.now()}`
	const INNER = 'inside-the-folder.txt'
	let placementId = 0

	test.beforeAll(async () => {
		await putFile(pieter!.api, pieter!.username, `/${FILE}`, 'carried with the page')
		await mkdirs(pieter!.api, pieter!.username, `/${FOLDER}`)
		await putFile(pieter!.api, pieter!.username, `/${FOLDER}/${INNER}`, 'asked for later')
		placementId = await seedFilesPlacement(pieter!.api, dash.id, { folderPath: '/' }, 0)
	})

	test.afterAll(async () => {
		if (placementId) {
			await removePlacements(pieter!.api, [placementId])
		}
	})

	// @e2e initial-state-contract::the-workspace-opens-without-asking-for-its-banner-reactions-and-files
	// Pieter owns this dashboard, so the personal-layer read (only carried for
	// a dashboard somebody else owns) is not part of it; the PHPUnit builder
	// test pins that read.
	test('the workspace opens without asking for its banner, reactions and files', async ({ browser }) => {
		const { context, page } = await loginAs(browser, pieter!.username, pieter!.password)
		try {
			const gets: string[] = []
			page.on('request', (req) => {
				if (req.method() === 'GET') {
					gets.push(new URL(req.url()).pathname)
				}
			})
			const response = await page.goto(APP)
			expect(response?.status()).toBe(200)

			// The default widget bundle may add a Files widget of its own, so
			// the page carries at least these reads.
			const reads = ((await initialState(page)).workspaceApiReads ?? []) as CarriedRead[]
			const carried = reads.map((read) => read.path)
			expect(carried).toEqual(expect.arrayContaining(firstReadPaths(dash.uuid, placementId)))

			// The Files widget shows its first page from the carried answer (the
			// banner and reactions bar mount with it)...
			await page.waitForLoadState('load')
			await expect(page.getByText(FILE).first()).toBeVisible({ timeout: 30_000 })
			// ...and none of the carried endpoints was asked.
			const asked = gets.filter((path) => carried.some((read) => path.endsWith(`/apps/launchpad${read}`)))
			expect(asked).toEqual([])
		} finally {
			await context.close()
		}
	})

	// @e2e initial-state-contract::a-refused-read-is-asked-for
	test('a caller refused announcement.read gets no carried announcements, and the banner meets the same refusal', async ({ browser }) => {
		await whileRefused('announcement.read', async () => {
			const { context, page } = await loginAs(browser, pieter!.username, pieter!.password)
			try {
				const refused = page.waitForResponse((res) => isRead(res, '/announcements'), { timeout: 30_000 })
				const response = await page.goto(APP)
				expect(response?.status()).toBe(200)
				const reads = ((await initialState(page)).workspaceApiReads ?? []) as CarriedRead[]
				expect(reads.map((read) => read.path)).not.toContain('/api/announcements')
				expect((await refused).status()).toBe(403)
			} finally {
				await context.close()
			}
		})
	})

	// @e2e initial-state-contract::a-later-read-asks-the-server
	test('opening a folder in the Files widget asks the server for that page', async ({ browser }) => {
		const { context, page } = await loginAs(browser, pieter!.username, pieter!.password)
		try {
			const response = await page.goto(APP)
			expect(response?.status()).toBe(200)
			await expect(page.getByText(FILE).first()).toBeVisible({ timeout: 30_000 })

			// Opening a folder is a later read: the widget's client sends it.
			// Both Files widgets (the default bundle's and this file's) list the folder.
			const asked = page.waitForResponse((res) => res.request().method() === 'GET'
				&& /\/apps\/launchpad\/api\/widgets\/files\/\d+\/contents$/.test(new URL(res.url()).pathname)
				&& new URL(res.url()).searchParams.get('currentPath') === `/${FOLDER}`, { timeout: 30_000 })
			await page.getByText(FOLDER, { exact: true }).first().click()
			expect((await asked).status()).toBe(200)
			await expect(page.getByText(INNER).first()).toBeVisible({ timeout: 30_000 })
		} finally {
			await context.close()
		}
	})
})
