/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The administrator's half of the public start page (dashboard-public-share
 * REQ-PSP-001): picking a public link on the Sharing tab of the LaunchPad
 * admin settings and switching the start page on, and the refusal of a link
 * protected by a password.
 *
 * Drives `PublicStartPageSettings.vue` on `/settings/admin/launchpad` as the
 * admin (Noor), and reads the result back from `GET /api/admin/public-start`.
 * Each test seeds its own dashboards and public links, and puts the start
 * page setting back the way it found it before it ends: while the start page
 * is on, every logged-out visit of the plain login page is sent to it, which
 * would change what the specs after this one meet.
 *
 * @spec openspec/specs/dashboard-public-share/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { adminTab } from './support/adminTab.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = Date.now()

let admin: APIRequestContext
let before: { enabled: boolean; token: string } | null = null
let priorAllowUserDash = true
const dashboards: number[] = []

test.beforeAll(async () => {
	admin = await adminContext()
	const current = await admin.get(`${API}/admin/public-start`)
	expect(current.status(), await current.text()).toBe(200)
	const body = await current.json()
	before = { enabled: body.enabled === true, token: String(body.token ?? '') }

	const settings = await admin.get(`${API}/admin/settings`)
	priorAllowUserDash = (await settings.json()).allowUserDashboards === true
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)
})

test.afterAll(async () => {
	await restoreStartPage()
	for (const id of dashboards) {
		await admin.delete(`${API}/dashboard/${id}`)
	}
	if (!priorAllowUserDash) {
		await admin.put(`${API}/admin/settings`, { data: { allowUserDash: false } })
	}
	await admin.dispose()
})

/** Put the start page setting back to what `beforeAll` read. */
async function restoreStartPage(): Promise<void> {
	if (before === null) {
		return
	}
	// The original link may have gone bad since; switching off with no link
	// is always accepted, so fall back to that rather than leave it on.
	const res = await admin.put(`${API}/admin/public-start`, { data: before })
	if (res.status() !== 200) {
		await admin.put(`${API}/admin/public-start`, {
			data: { enabled: false, token: '' },
		})
	}
}

/** The start page status as the server holds it. */
async function startStatus(): Promise<Record<string, unknown>> {
	const res = await admin.get(`${API}/admin/public-start`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/** A dashboard with a public link; returns its link token. */
async function dashboardWithLink(name: string, password?: string): Promise<string> {
	const created = await admin.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboards.push(Number(dash.id))
	const link = await admin.post(`${API}/dashboards/${dash.uuid}/public-share`, {
		data: password === undefined ? {} : { password },
	})
	expect(link.status(), await link.text()).toBe(201)
	const body = await link.json()
	expect(body.passwordRequired, 'CONTROL: the link is protected as asked').toBe(
		password !== undefined,
	)
	return String(body.token)
}

/** Open the Sharing tab of the LaunchPad admin settings. */
async function openSharingTab(page: Page): Promise<Locator> {
	await page.goto('/index.php/settings/admin/launchpad')
	const tab = adminTab(page, 'sharing')
	await expect(tab).toBeVisible({ timeout: 20_000 })
	await tab.click()
	const section = page.locator('[data-test="public-start-settings"]')
	await expect(section).toBeVisible({ timeout: 10_000 })
	return section
}

/** Open the "Public link" picker and return its options. */
async function openLinkPicker(page: Page, section: Locator): Promise<Locator> {
	await section.locator('[data-test="public-start-link"] input').first().click()
	const options = page.locator('.vs__dropdown-option')
	await expect(options.first()).toBeVisible({ timeout: 10_000 })
	return options
}

/**
 * Turn an `NcCheckboxRadioSwitch` on through the span a person clicks; its
 * `data-test` lands on the visually hidden input (see the note on
 * `activateSwitch` in `conditional-visibility-editor.spec.ts`).
 */
async function switchOn(input: Locator): Promise<void> {
	if (await input.isChecked()) {
		return
	}
	const id = await input.getAttribute('id')
	expect(id, 'the switch input must carry an id').toBeTruthy()
	await input.page().locator(`[id="${id}-label"]`).click()
	await expect(input).toBeChecked()
}

// @e2e dashboard-public-share::library-start-page
test('Noor picks the library link on the Sharing tab, switches it on, and the tab names it', async ({
	page,
}) => {
	const name = `Bibliotheek Noord ${STAMP}`
	const token = await dashboardWithLink(name)
	// Start from "off" so the switch and the status line are this test's doing.
	const off = await admin.put(`${API}/admin/public-start`, {
		data: { enabled: false, token: '' },
	})
	expect(off.status(), await off.text()).toBe(200)

	try {
		const section = await openSharingTab(page)
		await expect(
			section.locator('[data-test="public-start-status"]'),
		).toHaveCount(0)

		const options = await openLinkPicker(page, section)
		await options.filter({ hasText: name }).first().click()
		await switchOn(section.locator('[data-test="public-start-enabled"]'))

		const saved = page.waitForResponse(
			(r) =>
				r.url().includes('/api/admin/public-start')
				&& r.request().method() === 'PUT',
		)
		await section.locator('[data-test="public-start-save"]').click()
		expect((await saved).status()).toBe(200)

		await expect(
			section.locator('[data-test="public-start-status"]'),
		).toHaveText(`Public start page: ${name}`, { timeout: 10_000 })

		// …and it is what the server holds, not only what the tab shows.
		const status = await startStatus()
		expect(status.enabled).toBe(true)
		expect(status.token).toBe(token)
		expect(status.active).toBe(true)

		// It survives a reload of the admin page.
		const reloaded = await openSharingTab(page)
		await expect(
			reloaded.locator('[data-test="public-start-status"]'),
		).toHaveText(`Public start page: ${name}`, { timeout: 10_000 })
	} finally {
		await restoreStartPage()
	}
})

// @e2e dashboard-public-share::protected-link-refused
test('a link protected by a password is not offered, and saving it directly answers 400', async ({
	page,
}) => {
	const openName = `Bibliotheek Open ${STAMP}`
	const protectedName = `Bibliotheek Beschermd ${STAMP}`
	const openToken = await dashboardWithLink(openName)
	const protectedToken = await dashboardWithLink(
		protectedName,
		'Protected-Link-9!',
	)
	const off = await admin.put(`${API}/admin/public-start`, {
		data: { enabled: false, token: '' },
	})
	expect(off.status(), await off.text()).toBe(200)

	try {
		// Not offered in the picker, next to a link that is.
		const section = await openSharingTab(page)
		const options = await openLinkPicker(page, section)
		await expect(
			options.filter({ hasText: openName }),
			'CONTROL: an open link is offered',
		).toHaveCount(1)
		await expect(
			options.filter({ hasText: protectedName }),
			'a protected link must not be offered',
		).toHaveCount(0)

		const candidates = ((await startStatus()).candidates ?? []) as Array<{
			token: string
		}>
		const offered = candidates.map((c) => c.token)
		expect(offered).toContain(openToken)
		expect(offered).not.toContain(protectedToken)

		// A direct request to save it is refused with 400, and nothing moves.
		const refused = await admin.put(`${API}/admin/public-start`, {
			data: { enabled: true, token: protectedToken },
		})
		expect(refused.status(), await refused.text()).toBe(400)
		const afterRefusal = await startStatus()
		expect(
			afterRefusal.enabled,
			'a refused save must not switch the start page on',
		).toBe(false)
		expect(afterRefusal.token, 'a refused save must not store the link').toBe('')

		// The same request with the open link is accepted.
		const accepted = await admin.put(`${API}/admin/public-start`, {
			data: { enabled: true, token: openToken },
		})
		expect(accepted.status(), await accepted.text()).toBe(200)
		expect((await startStatus()).active).toBe(true)
	} finally {
		await restoreStartPage()
	}
})
