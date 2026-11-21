/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for how LaunchPad consumes OpenRegister at
 * runtime (openspec/specs/runtime-or-consumption), written for
 * e2e-coverage-programme task 5.12.
 *
 * The OR-data widget is nc-vue's `object-list` (see support/orWidget.ts): a
 * throwaway non-admin account gets one personal dashboard holding one list of
 * `launchpad/dashboard` objects, and each test watches the browser's request
 * to OpenRegister, or plays OpenRegister's answer with `page.route()` when the
 * scenario's GIVEN is a failure. The CI fixture has OpenRegister installed and
 * enabled, so every scenario whose GIVEN is "OR absent" is excluded in the
 * spec, not here.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-or-consumption::manifestjson-dependencies-array-is-empty
 *   @e2e runtime-or-consumption::widget-fetches-or-data-when-or-is-enabled
 *   @e2e runtime-or-consumption::widget-passes-current-user-locale
 *   @e2e runtime-or-consumption::widget-falls-back-to-en-when-lang-attribute-is-absent
 *   @e2e runtime-or-consumption::or-5xx-widget-degrades-to-empty-state
 *   @e2e runtime-or-consumption::or-recovers-after-transient-failure
 *   @e2e runtime-or-consumption::widget-uses-nextcloudaxios
 *
 * @spec openspec/specs/runtime-or-consumption/spec.md
 */

import type { Page, Request, Response } from '@playwright/test'
import type { SeededUser } from './support/filterBus.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	API,
	apiAs,
	provisionUserWithDashboard,
	putAdminSettings,
	removeUser,
} from './support/filterBus.ts'
import {
	addObjectList,
	degradedLabel,
	isOrListUrl,
	objectListWidget,
	openRegisterEnabled,
	RAW_ERROR,
	readServedManifest,
	readShippedManifest,
	setLanguage,
} from './support/orWidget.ts'

const APP = '/index.php/apps/launchpad'
const RUN = Date.now()
const ROW_MARKER = `E2E OR row ${RUN}`

let owner: SeededUser | null = null
let allowBefore: boolean | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(180_000)

test.beforeAll(async () => {
	test.skip(
		!(await openRegisterEnabled()),
		'OpenRegister is not enabled here; these scenarios need it present',
	)
	const admin = await apiAs()
	try {
		const settings = await admin.get(`${API}/admin/settings`)
		allowBefore = (await settings.json()).allowUserDashboards === true
	} finally {
		await admin.dispose()
	}
	owner = await provisionUserWithDashboard('e2e-orc')
	await addObjectList(owner, owner.dashboardId)
})

test.afterAll(async () => {
	await removeUser(owner)
	if (allowBefore !== null) {
		await putAdminSettings({ allowUserDash: allowBefore })
	}
})

/**
 * Record every request the page makes to the object-list's OR collection.
 *
 * @param page the page, before it navigates.
 * @return the live list of requests.
 */
function recordOrRequests(page: Page): Request[] {
	const seen: Request[] = []
	page.on('request', (req) => {
		if (isOrListUrl(new URL(req.url()))) {
			seen.push(req)
		}
	})
	return seen
}

/**
 * Open LaunchPad and wait until the object-list widget is on the page.
 *
 * @param page the page.
 * @return nothing.
 */
async function openDashboard(page: Page): Promise<void> {
	await page.goto(APP)
	await expect(objectListWidget(page)).toBeVisible({ timeout: 30_000 })
}

/**
 * Wait until at least `count` OR requests have been seen.
 *
 * @param seen the recorded requests.
 * @param count how many.
 * @return nothing.
 */
async function awaitOrRequests(seen: Request[], count = 1): Promise<void> {
	await expect
		.poll(() => seen.length, {
			message: 'the object-list widget must issue its OpenRegister request',
			timeout: 30_000,
		})
		.toBeGreaterThanOrEqual(count)
}

// @e2e runtime-or-consumption::manifestjson-dependencies-array-is-empty
test('the shipped and the served manifest declare no dependencies', async ({ page }) => {
	await page.goto(APP)
	const shipped = await readShippedManifest(page)
	expect(
		shipped.dependencies,
		'src/manifest.json must declare `dependencies: []` (REQ-OR-001)',
	).toEqual([])
	const shippedIds = JSON.stringify(shipped.dependencies)
	expect(shippedIds).not.toContain('openregister')
	expect(shippedIds).not.toContain('openconnector')

	// The runtime manifest REPLACES the bundled one at boot (ADR-036
	// Decision 8), so the declaration nc-vue actually reads is this one.
	const admin = await apiAs()
	try {
		const served = await readServedManifest(admin)
		expect(
			served.dependencies,
			'GET /api/manifest must declare `dependencies: []` too',
		).toEqual([])
		const servedIds = JSON.stringify(served.dependencies)
		expect(servedIds).not.toContain('openregister')
		expect(servedIds).not.toContain('openconnector')
	} finally {
		await admin.dispose()
	}
})

// @e2e runtime-or-consumption::widget-fetches-or-data-when-or-is-enabled
test('with OR enabled the widget fetches OR data and passes _lang', async ({ browser }) => {
	const { context, page } = await loginAs(browser, owner!.username, owner!.password)
	try {
		const seen = recordOrRequests(page)
		await openDashboard(page)
		// `useOrFeatureDetect().enabled.value === true` has no DOM of its own;
		// its observable consequence is that the gated request is issued.
		await awaitOrRequests(seen)
		const lang = new URL(seen[0].url()).searchParams.get('_lang')
		expect(lang, 'every OR data request must carry ?_lang= (REQ-OR-003)').toBeTruthy()
	} finally {
		await context.close()
	}
})

// @e2e runtime-or-consumption::widget-passes-current-user-locale
test('a Dutch user\'s OR request carries _lang=nl and gets Dutch back', async ({ browser }) => {
	await setLanguage(owner!, 'nl')
	const { context, page } = await loginAs(browser, owner!.username, owner!.password)
	try {
		const seen = recordOrRequests(page)
		const answers: Response[] = []
		page.on('response', (res) => {
			if (isOrListUrl(new URL(res.url()))) {
				answers.push(res)
			}
		})
		await page.goto(APP)
		await expect(page.locator('html')).toHaveAttribute('lang', 'nl')
		await expect(objectListWidget(page)).toBeVisible({ timeout: 30_000 })
		await awaitOrRequests(seen)

		expect(new URL(seen[0].url()).searchParams.get('_lang')).toBe('nl')

		await expect.poll(() => answers.length, { timeout: 30_000 }).toBeGreaterThan(0)
		// OpenRegister's LanguageMiddleware states the language it rendered
		// translatable fields in; `nl` is the Dutch translation.
		expect(answers[0].headers()['content-language'] ?? '').toMatch(/^nl\b/)
	} finally {
		await context.close()
		await setLanguage(owner!, 'en')
	}
})

// @e2e runtime-or-consumption::widget-falls-back-to-en-when-lang-attribute-is-absent
test('without an html lang attribute the OR request sends _lang=en', async ({ browser }) => {
	const { context, page } = await loginAs(browser, owner!.username, owner!.password)
	try {
		// Serve the LaunchPad page with its <html lang="..."> removed; every
		// other request passes untouched.
		await page.route('**/index.php/apps/launchpad**', async (route) => {
			if (route.request().resourceType() !== 'document') {
				await route.fallback()
				return
			}
			const res = await route.fetch()
			const html = (await res.text()).replace(/<html([^>]*?)\slang="[^"]*"/i, '<html$1')
			await route.fulfill({ response: res, body: html })
		})
		const seen = recordOrRequests(page)
		await page.goto(APP)
		await expect(page.locator('html')).not.toHaveAttribute('lang')
		await expect(objectListWidget(page)).toBeVisible({ timeout: 30_000 })
		await awaitOrRequests(seen)

		expect(new URL(seen[0].url()).searchParams.get('_lang')).toBe('en')
	} finally {
		await context.close()
	}
})

// @e2e runtime-or-consumption::or-5xx-widget-degrades-to-empty-state
test('an OR 500 degrades the widget to a labelled state, warned, no rejection', async ({
	browser,
}) => {
	const { context, page } = await loginAs(browser, owner!.username, owner!.password)
	try {
		const warnings: string[] = []
		const pageErrors: string[] = []
		page.on('console', (msg) => {
			if (msg.type() === 'warning') {
				warnings.push(msg.text())
			}
		})
		page.on('pageerror', (err) => pageErrors.push(err.message))
		await page.route(
			(url) => isOrListUrl(url),
			(route) =>
				route.fulfill({
					status: 500,
					contentType: 'application/json',
					body: '{"message":"forced by runtime-or-consumption.spec.ts"}',
				}),
		)
		const seen = recordOrRequests(page)
		await openDashboard(page)
		await awaitOrRequests(seen)

		const widget = objectListWidget(page)
		const label = degradedLabel(widget)
		await expect(label, 'the widget must render its empty state').toBeVisible({
			timeout: 30_000,
		})
		await expect(label).not.toHaveText('')
		await expect(widget).not.toContainText(RAW_ERROR)
		await expect(widget).not.toContainText('forced by runtime-or-consumption.spec.ts')

		await expect
			.poll(() => warnings.filter((w) => /fail|error/i.test(w)).length, {
				message: 'the OR failure must be logged at console.warn level',
			})
			.toBeGreaterThan(0)
		expect(pageErrors, 'no unhandled Promise rejection may surface').toEqual([])
	} finally {
		await context.close()
	}
})

// @e2e runtime-or-consumption::or-recovers-after-transient-failure
test('after a 503 the widget fetches again on return and renders OR data', async ({
	browser,
}) => {
	const { context, page } = await loginAs(browser, owner!.username, owner!.password)
	try {
		// The route plays OpenRegister: unavailable first, healthy after.
		let healthy = false
		await page.route(
			(url) => isOrListUrl(url),
			(route) =>
				healthy
					? route.fulfill({
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify({
							results: [{ id: `e2e-${RUN}`, title: ROW_MARKER }],
							total: 1,
							page: 1,
							pages: 1,
						}),
					})
					: route.fulfill({
						status: 503,
						contentType: 'application/json',
						body: '{"message":"unavailable"}',
					}),
		)
		const seen = recordOrRequests(page)
		await openDashboard(page)
		await awaitOrRequests(seen)
		const widget = objectListWidget(page)
		await expect(degradedLabel(widget)).toBeVisible({ timeout: 30_000 })
		await expect(widget).not.toContainText(ROW_MARKER)

		healthy = true
		const before = seen.length
		await page.goto('/index.php/settings/user')
		await openDashboard(page)

		await awaitOrRequests(seen, before + 1)
		await expect(objectListWidget(page)).toContainText(ROW_MARKER, { timeout: 30_000 })
		await expect(degradedLabel(objectListWidget(page))).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e runtime-or-consumption::widget-uses-nextcloudaxios
test('the OR request carries the session CSRF token and no Authorization header', async ({
	browser,
}) => {
	const { context, page } = await loginAs(browser, owner!.username, owner!.password)
	try {
		const seen = recordOrRequests(page)
		await openDashboard(page)
		await awaitOrRequests(seen)

		const headers = await seen[0].allHeaders()
		// @nextcloud/axios' client sets exactly these two defaults; a bare
		// axios or fetch call sends neither.
		const token = await page.evaluate(() => document.head.dataset.requesttoken ?? '')
		expect(headers.requesttoken, 'the CSRF token must be sent').toBeTruthy()
		expect([token, await page.evaluate(() =>
			(window as unknown as { OC?: { requestToken?: string } }).OC?.requestToken ?? '')])
			.toContain(headers.requesttoken)
		expect(headers['x-requested-with']).toBe('XMLHttpRequest')
		expect(headers.authorization, 'no custom Authorization header').toBeUndefined()
	} finally {
		await context.close()
	}
})
