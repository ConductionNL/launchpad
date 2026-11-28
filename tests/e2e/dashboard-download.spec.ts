/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A reader can download the dashboard (dashboard-period-and-download,
 * REQ-DPD-003): a colleague with view-only rights chooses Download in the
 * header and the browser's print dialog opens on the dashboard, named after
 * it; nothing is sent to the server for it. The kiosk view does not offer it.
 *
 * The download renders in the reader's own browser (decision 24 Nov 2026,
 * option (b)), so the spec replaces `window.print` with a recorder: a real
 * print dialog cannot be driven headless.
 *
 * Written in the cloud lane without a live instance; it runs in the nightly.
 *
 * @e2e openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { downloadFromMore } from './support/dashboardSwitcher.ts'
import {
	adminContext,
	API,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
	seedDashboardWithWidget,
	shareWith,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const NAME = `Managementinformatie ${Date.now().toString(36)}`

let admin: APIRequestContext
let reader: Principal | null = null
let dashboard: { id: number, uuid: string, slug: string } | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * Replace the print dialog with a recorder of the title it would print under.
 *
 * @param page the reader's page, before its first navigation.
 * @return nothing.
 */
async function recordPrints(page: Page): Promise<void> {
	await page.addInitScript(() => {
		const w = window as unknown as { __prints: string[] }
		w.__prints = []
		window.print = () => {
			w.__prints.push(document.title)
			// A real print dialog fires afterprint when it closes; the stub does too.
			window.dispatchEvent(new Event('afterprint'))
		}
	})
}

test.beforeAll(async () => {
	admin = await adminContext()
	const seeded = await seedDashboardWithWidget(admin, NAME)
	await publishForRecipients(admin, seeded.id)
	reader = await createPrincipal(admin, 'pieter')
	await shareWith(admin, seeded.id, reader.username, 'view_only')
	const read = await admin.get(`${API}/dashboard/${seeded.id}`)
	expect(read.status(), await read.text()).toBe(200)
	const body = await read.json()
	dashboard = { id: seeded.id, uuid: seeded.uuid, slug: String((body.dashboard ?? body).slug ?? seeded.uuid) }
})

test.afterAll(async () => {
	if (dashboard !== null) {
		await admin.delete(`${API}/dashboard/${dashboard.id}`)
	}
	await removePrincipal(admin, reader)
	await admin.dispose()
})

test('a view-only reader downloads the dashboard from More, and nothing is sent to the server', async ({ browser }) => {
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	try {
		await recordPrints(page)
		const downloads: string[] = []
		page.on('request', (request) => {
			if (/\/download(\?|$)/.test(new URL(request.url()).pathname + new URL(request.url()).search)) {
				downloads.push(request.url())
			}
		})
		await page.goto(`${APP}/${dashboard!.slug}`)
		const header = page.getByTestId('dashboard-page-header')
		await expect(header.locator('h1')).toHaveText(NAME, { timeout: 30_000 })
		await expect(header).toContainText('view only')

		await downloadFromMore(page)

		await expect.poll(() => page.evaluate(() => (window as unknown as { __prints: string[] }).__prints))
			.toEqual([NAME])
		// The page title is back once the dialog is closed.
		await expect.poll(() => page.title()).not.toBe(NAME)
		expect(downloads, 'the PDF is made in the browser; no download request').toEqual([])
	} finally {
		await context.close()
	}
})

test('the printed page leaves out the top bar and the More menu', async ({ browser }) => {
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	try {
		await page.goto(`${APP}/${dashboard!.slug}`)
		const header = page.getByTestId('dashboard-page-header')
		await expect(header.locator('h1')).toHaveText(NAME, { timeout: 30_000 })
		await page.emulateMedia({ media: 'print' })
		await expect(header.locator('h1')).toBeVisible()
		await expect(header.getByTestId('dashboard-download')).toHaveCount(0)
		await expect(page.getByTestId('launchpad-more-menu')).toBeHidden()
		await expect(page.locator('#header')).toBeHidden()
	} finally {
		await context.close()
	}
})

test('the kiosk view does not offer Download', async ({ browser }) => {
	const { context, page } = await loginAs(browser, reader!.username, reader!.password)
	try {
		await page.goto(`${APP}/${dashboard!.slug}?kiosk=1`)
		await expect(page.locator('#launchpad-app.launchpad-app--kiosk')).toBeVisible({ timeout: 30_000 })
		await expect(page.getByTestId('dashboard-download')).toHaveCount(0)
		await expect(page.getByTestId('launchpad-more-menu')).toHaveCount(0)
	} finally {
		await context.close()
	}
})
