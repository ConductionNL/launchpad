/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The demo seed of the boards (REQ-DEMO-010): `occ launchpad:demo:load`
 * creates the Zuiddrecht users, groups and boards; Pieter opens on Vandaag;
 * a second run adds nothing and changes no board.
 *
 * a-first-visit: after a run with --retire-showcases `nieuw` opens on the
 * first-visit page. Live on 26 Nov 2026 she opened on a "My Dashboard" the
 * read had provisioned for her (allowUserDashboards on); a read now leaves
 * that choice to her (REQ-SHELL-005), so the test runs with personal
 * dashboards allowed, the setting the starter offer needs.
 *
 * The seed writes fixed users and fixed dashboard uuids, so the file runs
 * serially and each test starts from a run of the seed itself.
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { readAllowUserDashboards, setAllowUserDashboards } from './support/adminTemplates.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { runOcc } from './support/occ.ts'

const APP = '/index.php/apps/launchpad'
const API = '/index.php/apps/launchpad/api'
// DemoSeedService::DEFAULT_PASSWORD, the password the seed gives the users it creates.
const PASSWORD = 'Zuiddrecht-demo-2026!'

test.describe.configure({ mode: 'serial' })

/**
 * Run the seed and require it to succeed.
 *
 * @param args Extra options.
 * @return Its output.
 */
async function loadSeed(...args: string[]): Promise<string> {
	const run = await runOcc('launchpad:demo:load', ...args)
	expect(run.code, `${run.stdout}\n${run.stderr}`).toBe(0)
	return run.stdout
}

/**
 * An API context signed in as a seeded user.
 *
 * @param username The user.
 * @return The context; the caller disposes it.
 */
async function apiAs(username: string): Promise<APIRequestContext> {
	// Both load-bearing (see support/principals.ts): without them the context
	// rides the admin session of the config's storageState.
	return request.newContext({
		baseURL: BASE_URL,
		storageState: undefined,
		httpCredentials: { username, password: PASSWORD, send: 'always' },
		// Dashboard names are translated per request; the seed writes Dutch.
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', 'Accept-Language': 'nl' },
	})
}

/**
 * Every dashboard a user sees, with its widgets as `widgetId@x,y,wxh`, sorted.
 *
 * @param api The user's context.
 * @return uuid to name and placements.
 */
async function boardsOf(api: APIRequestContext): Promise<Record<string, { name: string; placements: string[] }>> {
	const visible = await api.get(`${API}/dashboards/visible`)
	expect(visible.status(), await visible.text()).toBe(200)
	const body = await visible.json()
	const items = (body.items ?? body) as Array<{ id: number; uuid: string; name: string }>
	const out: Record<string, { name: string; placements: string[] }> = {}
	for (const item of items) {
		const res = await api.get(`${API}/dashboard/${item.id}`)
		expect(res.status(), await res.text()).toBe(200)
		const placements = ((await res.json()).placements ?? []) as Array<Record<string, unknown>>
		out[item.uuid] = {
			name: item.name,
			placements: placements
				.map((p) => `${p.widgetId}@${p.gridX},${p.gridY},${p.gridWidth}x${p.gridHeight}`)
				.sort(),
		}
	}
	return out
}

test.beforeAll(async () => {
	await loadSeed()
	// The boards are named in Dutch and translated per user language; the
	// scenarios read the Dutch names, so Pieter gets the Dutch interface.
	const lang = await runOcc('user:setting', 'pieter', 'core', 'lang', 'nl')
	expect(lang.code, lang.stderr).toBe(0)
})

// @e2e demo-data-showcases::pieter-opens-launchpad
test('Pieter opens LaunchPad on Vandaag, with the other boards in his list', async ({ browser }) => {
	const api = await apiAs('pieter')
	try {
		const active = await api.get(`${API}/dashboard`)
		expect(active.status(), await active.text()).toBe(200)
		expect((await active.json()).dashboard?.name).toBe('Vandaag')
		const names = Object.values(await boardsOf(api)).map((b) => b.name)
		expect(names).toEqual(expect.arrayContaining(['Mijn werkplek', 'Intranet Zuiddrecht', 'Team Vergunningen', 'Managementinformatie']))
	} finally {
		await api.dispose()
	}

	const { context, page } = await loginAs(browser, 'pieter', PASSWORD)
	try {
		await page.goto(APP)
		await expect(page.getByRole('heading', { level: 2, name: 'Vandaag', exact: true })).toBeVisible({ timeout: 30_000 })
	} finally {
		await context.close()
	}
})

// @e2e demo-data-showcases::running-the-seed-twice
test('a second run adds no user, group or board and leaves every board as it was', async () => {
	const api = await apiAs('pieter')
	try {
		const before = await boardsOf(api)
		const users = (await runOcc('user:list', '--output=json')).stdout
		const groups = (await runOcc('group:list', '--output=json')).stdout

		const out = await loadSeed()
		expect(out, 'no user is created again').toMatch(/^Users created: none\.$/m)
		expect(out, 'no group is created again').toMatch(/^Groups created: none\.$/m)
		expect(out, 'no catalogue entry is added again').toMatch(/^Catalogue: 0 added,/m)
		// Every board line reads "updated", none "created".
		expect(out, 'no board is created again').not.toMatch(/\) created:/)
		expect(out).toMatch(/\) updated:/)

		expect(JSON.parse((await runOcc('user:list', '--output=json')).stdout)).toEqual(JSON.parse(users))
		expect(Object.keys(JSON.parse((await runOcc('group:list', '--output=json')).stdout)).sort())
			.toEqual(Object.keys(JSON.parse(groups)).sort())
		expect(await boardsOf(api)).toEqual(before)
	} finally {
		await api.dispose()
	}
})

/**
 * An API context signed in as the suite's administrator.
 *
 * @return The context; the caller disposes it.
 */
async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		storageState: undefined,
		httpCredentials: {
			username: process.env.NC_ADMIN_USER ?? 'admin',
			password: process.env.NC_ADMIN_PASS ?? 'admin',
			send: 'always',
		},
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

// @e2e demo-data-showcases::a-first-visit
// @e2e dashboards::first-time-user-without-a-template-or-role-layout-chooses-on-the-first-visit-page
test('after a run with --retire-showcases nieuw opens on the first-visit page with the starter widgets', async ({ browser }) => {
	const admin = await adminApi()
	const allowedBefore = await readAllowUserDashboards(admin)
	try {
		await setAllowUserDashboards(admin, true)
		const out = await loadSeed('--retire-showcases')
		expect(out).toMatch(/^First visit reset for nieuw/m)
		const lang = await runOcc('user:setting', 'nieuw', 'core', 'lang', 'nl')
		expect(lang.code, lang.stderr).toBe(0)

		const { context, page } = await loginAs(browser, 'nieuw', PASSWORD)
		try {
			await page.goto(APP)
			await expect(page.getByRole('heading', { level: 2, name: 'U heeft nog geen dashboard', exact: true })).toBeVisible({ timeout: 30_000 })
			await expect(page.getByTestId('workspace-starter-widgets')).toBeVisible()
			await expect(page.getByTestId('workspace-create-with-starters')).toBeVisible()
		} finally {
			await context.close()
		}

		// Opening the page chose nothing for her: she owns no dashboard yet.
		const api = await apiAs('nieuw')
		try {
			const visible = await api.get(`${API}/dashboards/visible`)
			expect(visible.status(), await visible.text()).toBe(200)
			const body = await visible.json()
			const owned = ((body.items ?? body) as Array<{ source?: string }>).filter((d) => d.source === 'user')
			expect(owned).toEqual([])
		} finally {
			await api.dispose()
		}
	} finally {
		await setAllowUserDashboards(admin, allowedBefore)
		await admin.dispose()
	}
})
