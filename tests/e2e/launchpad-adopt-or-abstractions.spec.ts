/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the OR-abstraction rules LaunchPad adopted
 * (openspec/specs/launchpad-adopt-or-abstractions), written for
 * e2e-coverage-programme task 5.8.
 *
 * `manifest-loads-on-app-boot` is cited by manifest-boot.spec.ts. This file
 * covers the three further scenarios with a running outcome on the CI fixture
 * (OpenRegister present): the shipped manifest's `dependencies` key, an
 * OR-backed widget meeting a 503, and an OR-backed widget writing a
 * translatable property. The OR-backed widget is nc-vue's `object-list`; see
 * support/orWidget.ts for why that is the one to drive.
 *
 * Gate-19 @e2e traceability:
 *   @e2e launchpad-adopt-or-abstractions::manifest-declares-no-install-time-or-dependency
 *   @e2e launchpad-adopt-or-abstractions::or-returns-5xx
 *   @e2e launchpad-adopt-or-abstractions::translation-target-on-or-write
 *
 * @spec openspec/specs/launchpad-adopt-or-abstractions/spec.md
 */

import type { Locator, Page, Request } from '@playwright/test'
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
	isOrListUrl,
	isOrObjectUrl,
	objectListWidget,
	openRegisterEnabled,
	OR_SCHEMA,
	readShippedManifest,
	setLanguage,
} from './support/orWidget.ts'

const APP = '/index.php/apps/launchpad'
const RUN = Date.now()
const ROW_ID = `e2e-row-${RUN}`
const OLD_TITLE = `E2E title before ${RUN}`
const NEW_TITLE = `E2E title after ${RUN}`

/** The default OR-widget poll interval the spec names (60 s), plus slack. */
const POLL_WINDOW_MS = 75_000

let allowBefore: boolean | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(240_000)

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const settings = await admin.get(`${API}/admin/settings`)
		allowBefore = (await settings.json()).allowUserDashboards === true
	} finally {
		await admin.dispose()
	}
})

test.afterAll(async () => {
	if (allowBefore !== null) {
		await putAdminSettings({ allowUserDash: allowBefore })
	}
})

/**
 * Open LaunchPad and wait until the object-list widget is on the page.
 *
 * @param page the page.
 * @return the widget.
 */
async function openWidget(page: Page): Promise<Locator> {
	await page.goto(APP)
	const widget = objectListWidget(page)
	await expect(widget).toBeVisible({ timeout: 30_000 })
	return widget
}

// @e2e launchpad-adopt-or-abstractions::manifest-declares-no-install-time-or-dependency
test('src/manifest.json declares an empty dependencies array', async ({ page }) => {
	await page.goto(APP)
	const manifest = await readShippedManifest(page)
	expect(Array.isArray(manifest.dependencies), 'dependencies must be an array').toBe(true)
	expect(manifest.dependencies, 'manifest.dependencies MUST be []').toEqual([])
	// The reviewer rule's enforceable form: no Conduction app id, in either
	// the string or the `{ id }` object shape.
	const declared = JSON.stringify(manifest.dependencies)
	expect(declared).not.toContain('openregister')
	expect(declared).not.toContain('openconnector')
})

test.describe('an OR-backed widget', () => {
	let owner: SeededUser | null = null

	test.beforeAll(async () => {
		test.skip(
			!(await openRegisterEnabled()),
			'OpenRegister is not enabled here; these scenarios need it present',
		)
		owner = await provisionUserWithDashboard('e2e-oradopt')
		await addObjectList(owner, owner.dashboardId, { editable: true })
		await setLanguage(owner, 'en')
	})

	test.afterAll(async () => {
		await removeUser(owner)
	})

	// @e2e launchpad-adopt-or-abstractions::or-returns-5xx
	test('a 503 shows a polite "Temporarily unavailable" state and retries on the next poll', async ({
		browser,
	}) => {
		const { context, page } = await loginAs(browser, owner!.username, owner!.password)
		try {
			const pageErrors: string[] = []
			page.on('pageerror', (err) => pageErrors.push(err.message))
			const polls: Request[] = []
			page.on('request', (req) => {
				if (isOrListUrl(new URL(req.url()))) {
					polls.push(req)
				}
			})
			await page.route(
				(url) => isOrListUrl(url),
				(route) =>
					route.fulfill({
						status: 503,
						contentType: 'application/json',
						body: '{"message":"forced by launchpad-adopt-or-abstractions.spec.ts"}',
					}),
			)
			const widget = await openWidget(page)
			await expect.poll(() => polls.length, { timeout: 30_000 }).toBeGreaterThan(0)

			const notice = widget.locator('[aria-live="polite"]', {
				hasText: 'Temporarily unavailable',
			})
			await expect(notice.first()).toBeVisible({ timeout: 30_000 })

			// The next poll: one more request inside the default 60 s interval.
			const first = polls.length
			await expect
				.poll(() => polls.length, {
					message: 'the widget must retry on its next poll interval (default 60 s)',
					timeout: POLL_WINDOW_MS,
					intervals: [5_000],
				})
				.toBeGreaterThan(first)

			// Not crashed: the dashboard and the widget are still there.
			await expect(page.locator('[data-testid="launchpad-more-menu"]')).toBeVisible()
			await expect(widget).toBeVisible()
			expect(pageErrors).toEqual([])
		} finally {
			await context.close()
		}
	})

	// @e2e launchpad-adopt-or-abstractions::translation-target-on-or-write
	test('editing a title PATCHes with X-Translation-Target-Language and a plain string', async ({
		browser,
	}) => {
		const { context, page } = await loginAs(browser, owner!.username, owner!.password)
		try {
			// OpenRegister is played by the route so the row, its schema and
			// the write are deterministic and nothing lands in the shared
			// register.
			const row = { id: ROW_ID, title: OLD_TITLE }
			await page.route(
				(url) => isOrListUrl(url),
				(route) =>
					route.fulfill({
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify({ results: [row], total: 1, page: 1, pages: 1 }),
					}),
			)
			await page.route(
				(url) => url.pathname.endsWith(`/apps/openregister/api/schemas/${OR_SCHEMA}`),
				(route) =>
					route.fulfill({
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify({
							id: 1,
							slug: OR_SCHEMA,
							title: 'Dashboard',
							properties: { title: { type: 'string', title: 'Title' } },
						}),
					}),
			)
			const writes: Request[] = []
			await page.route(
				(url) => isOrObjectUrl(url, ROW_ID),
				async (route) => {
					if (route.request().method() === 'PATCH') {
						writes.push(route.request())
					}
					await route.fulfill({
						status: 200,
						contentType: 'application/json',
						body: JSON.stringify({ ...row, title: NEW_TITLE }),
					})
				},
			)

			const widget = await openWidget(page)
			const line = widget.locator('tr', { hasText: OLD_TITLE })
			await expect(line).toBeVisible({ timeout: 30_000 })

			// One action, so it may sit inline; under the board look it is in
			// the row's menu.
			const inlineEdit = line.getByRole('button', { name: 'Edit', exact: true })
			if ((await inlineEdit.count()) > 0) {
				await inlineEdit.first().click()
			} else {
				await line.locator('button[aria-haspopup]').first().click()
				await page.getByRole('menuitem', { name: 'Edit' }).click()
			}

			const dialog = page.getByRole('dialog')
			const field = dialog.getByRole('textbox', { name: /Title/ }).first()
			await expect(field).toHaveValue(OLD_TITLE, { timeout: 15_000 })
			await field.fill(NEW_TITLE)
			await dialog.getByRole('button', { name: 'Save', exact: true }).click()

			await expect.poll(() => writes.length, { timeout: 15_000 }).toBe(1)
			const write = writes[0]
			const headers = await write.allHeaders()
			expect(
				headers['x-translation-target-language'],
				'an OR write from a LaunchPad dashboard must name its target language',
			).toBe('en')
			const body = write.postDataJSON()
			expect(typeof body.title, 'title must be a plain string, not a language map').toBe(
				'string',
			)
			expect(body.title).toBe(NEW_TITLE)
		} finally {
			await context.close()
		}
	})
})
