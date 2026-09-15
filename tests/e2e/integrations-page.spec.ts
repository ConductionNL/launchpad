/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Integrations page over integriq's connection registry
 * (adopt-connection-registry, hydra connection-registry D8 and D9).
 *
 * WHERE THE ROWS COME FROM. The rows are integriq's `app_connection` objects,
 * synced from LaunchPad's `lib/Settings/connections.json`, with `app` equal to
 * `launchpad`. LaunchPad writes no row: a registry save asks integriq to
 * resolve again, a store search reports what it met, and integriq decides the
 * status. So this spec needs integriq installed and synced, and reads the rows
 * from `/apps/openregister/api/objects/integriq/app_connection?app=launchpad`.
 *
 * `app` is a BARE filter key. The objects endpoint reads `filter[app]` as a
 * filter on nothing and answers the empty set without an error.
 *
 * WHAT A RED HERE USUALLY MEANS. An empty list in the first test means
 * integriq has not synced the declaration, or refused it whole. A registry row
 * that stays on Error after a save means integriq does not yet retire older
 * observations on a refresh (hydra#674).
 *
 * Every write here is LaunchPad's `registry_url`, read first and put back in a
 * `finally`. The URL saved is an `.invalid` host, which no search can reach.
 *
 * Written, not yet run: the CI instance needs integriq with hydra#674 first
 * (tasks.md 5.1).
 *
 * Locale: nothing forces the E2E language, so statuses are read from the API
 * and rows are found by their declared titles, which are not translated.
 *
 * @e2e openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#the-page-lists-only-the-rows-of-launchpad
 * @e2e openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#add-integration-goes-to-integriq
 * @e2e openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#a-saved-registry-url-reads-configured
 * @e2e openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#a-registry-that-cannot-be-reached-reads-error
 */
import type { APIRequestContext, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL as BASE } from './support/baseUrl.ts'

/** LaunchPad's app root, relative to the base URL. */
const APP = '/index.php/apps/launchpad'

/** Integriq's objects endpoint for LaunchPad's connection rows. */
const CONNECTIONS_API = '/index.php/apps/openregister/api/objects/integriq/app_connection?app=launchpad&_limit=50'

/** LaunchPad's registry settings, the one writer of the registry keys. */
const STORE_CONFIG_API = `${APP}/api/store/config`

/** LaunchPad's dashboard store search. */
const STORE_SEARCH_API = `${APP}/api/store/items`

/** The admin account the suite logs in with. */
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

/** The declared keys and titles, in declared order. */
const DECLARED = [
	{ key: 'dashboard-registry', title: 'Dashboard registry' },
	{ key: 'weather', title: 'Weather provider' },
	{ key: 'news-feeds', title: 'News feeds' },
	{ key: 'ics-calendars', title: 'Calendar feeds' },
	{ key: 'live-tiles', title: 'Live tiles' },
	{ key: 'health-ping', title: 'Health ping' },
]

/** A registry host nothing answers on. */
const UNREACHABLE_REGISTRY = 'https://registry.example.invalid/index.php'

/**
 * An admin API context with HTTP Basic auth, which passes the CSRF check.
 *
 * @return The request context.
 */
async function adminApi(): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
}

/**
 * LaunchPad's connection rows, keyed by connection key.
 *
 * @param api An admin request context.
 * @return The rows by key.
 */
async function rowsByKey(api: APIRequestContext): Promise<Record<string, Record<string, unknown>>> {
	const res = await api.get(CONNECTIONS_API)
	expect(res.ok(), `list integriq/app_connection -> ${res.status()}`).toBeTruthy()
	const body = await res.json()
	const byKey: Record<string, Record<string, unknown>> = {}
	for (const row of (body.results ?? []) as Record<string, unknown>[]) {
		// A row from another app here means the bare filter was dropped.
		expect(String(row.app), 'a connection row from another app').toBe('launchpad')
		byKey[String(row.key)] = row
	}
	return byKey
}

/**
 * The registry row's status and message as one string, or '' when it cannot be read.
 *
 * Reads without asserting: a throw inside `expect.poll` ends the poll instead
 * of retrying it.
 *
 * @param api An admin request context.
 * @return `{status} {statusMessage}`.
 */
async function registryRow(api: APIRequestContext): Promise<string> {
	const list = await api.get(CONNECTIONS_API)
	const rows = list.ok() ? ((await list.json()).results ?? []) : []
	const row = rows.find((r: Record<string, unknown>) => r.key === 'dashboard-registry' && r.app === 'launchpad')
	return `${String(row?.status ?? '')} ${String(row?.statusMessage ?? '')}`
}

/**
 * Run a block with `registry_url` saved, and put the previous value back afterwards.
 *
 * @param api An admin request context.
 * @param url The registry URL to save.
 * @param block What to do while it is saved.
 */
async function withRegistryUrl(api: APIRequestContext, url: string, block: () => Promise<void>): Promise<void> {
	const before = await api.get(STORE_CONFIG_API)
	expect(before.ok(), `registry config read -> ${before.status()}`).toBeTruthy()
	const previous = String((await before.json())?.registryUrl ?? '')

	try {
		const saved = await api.put(STORE_CONFIG_API, { data: { registryUrl: url } })
		expect(saved.ok(), `registry config save -> ${saved.status()}`).toBeTruthy()
		expect((await saved.json()).registryUrl).toBe(url)
		await block()
	} finally {
		await api.put(STORE_CONFIG_API, { data: { registryUrl: previous } })
	}
}

/**
 * Open the Integrations page the way its menu entry does, with the preset.
 *
 * @param page The Playwright page.
 */
async function openIntegrations(page: Page): Promise<void> {
	await page.goto(`${APP}/settings/integrations?app=launchpad`, { timeout: 60_000 })
	await expect(page.locator('.cn-index-page')).toBeVisible({ timeout: 30_000 })
}

test.describe('Integrations over the connection registry', () => {
	let api: APIRequestContext

	test.beforeAll(async () => {
		api = await adminApi()
	})

	test.afterAll(async () => {
		await api.dispose()
	})

	test('lists the six declared connections, all of them LaunchPad\'s', async ({ page }) => {
		const byKey = await rowsByKey(api)
		expect(Object.keys(byKey).sort()).toEqual(DECLARED.map((d) => d.key).sort())
		expect(String(byKey['dashboard-registry']?.settingsUrl ?? '')).toBe('/settings/admin/launchpad?tab=sharing#section-dashboard-registry')

		await openIntegrations(page)
		for (const { title } of DECLARED) {
			await expect(page.getByRole('row', { name: new RegExp(title, 'i') })).toHaveCount(1)
		}
	})

	test('reads Configured once a registry URL is saved', async () => {
		await withRegistryUrl(api, UNREACHABLE_REGISTRY, async () => {
			await expect
				.poll(() => registryRow(api), { timeout: 15_000 })
				.toBe('configured Required settings are filled.')
		})
	})

	test('reads Error naming the host once a search cannot reach the registry', async () => {
		await withRegistryUrl(api, UNREACHABLE_REGISTRY, async () => {
			// One search. Its own answer is not under test: it answers the
			// unreachable outcome, as it did before this change. What it reports is.
			const search = await api.get(STORE_SEARCH_API, { failOnStatusCode: false })
			expect(search.status(), `store search -> ${search.status()}`).toBe(200)
			expect((await search.json()).outcome).toBe('store_unreachable')

			await expect
				.poll(() => registryRow(api), { timeout: 15_000 })
				.toBe('error The last search could not reach the dashboard registry at registry.example.invalid.')
		})
	})

	test('sends Add integration to integriq instead of offering a form', async ({ page }) => {
		await openIntegrations(page)

		// No generic Add button: a row nothing declared has nothing to check.
		await expect(page.locator('[data-testid="cn-cta-primary"]')).toHaveCount(0)

		// The action lives in the overflow menu. English and Dutch are the two
		// catalogues this change ships, and nothing forces the E2E locale.
		await page.locator('[data-testid="cn-actions"] button').first().click()
		await Promise.all([
			page.waitForURL(/\/apps\/integriq\/connections\?app=launchpad&link=1$/, { timeout: 30_000 }),
			page.getByRole('menuitem', { name: /Add integration|Integratie toevoegen/i }).click(),
		])
	})
})
