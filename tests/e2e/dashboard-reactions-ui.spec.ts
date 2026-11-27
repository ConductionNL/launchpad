/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the reactions bar on the workspace page
 * (openspec/specs/dashboard-reactions, REQ-RXN-010), written for the
 * e2e-coverage-programme.
 *
 * Pieter is a throwaway account that owns the dashboard "Team" (seeded
 * through the API, made his active dashboard, and shared view-only with a
 * colleague who has already reacted with 👍, so the count starts at 1).
 * Pieter reacts through the bar itself.
 *
 * The global toggle is a Nextcloud app value with no LaunchPad endpoint, so
 * it is written through Nextcloud's provisioning API and restored in
 * afterAll. There is no way to switch reactions off for ONE dashboard (no
 * endpoint, form or command writes `reactionsEnabled`; see the exclusions in
 * the spec), so "Board has reactions switched off" is a dashboard whose
 * reactions are off because the global toggle is.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-reactions::add-and-remove
 *   @e2e dashboard-reactions::reactions-off
 *
 * @spec openspec/specs/dashboard-reactions/spec.md
 */

import type {
	APIRequestContext,
	Browser,
	BrowserContext,
	Page,
} from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	adminContext,
	API,
	assertActingAs,
	createPrincipal,
	publishForRecipients,
	removePrincipal,
	shareWith,
} from './support/principals.ts'

const RUN = Date.now()
const APPCONFIG = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad'
const KEY_ENABLED = 'reactions_enabled_default'
const BAR = '[data-testid="dashboard-reactions"]'

type Dashboard = { id: number; uuid: string; name: string }

let admin: APIRequestContext
let pieter: Principal | null = null
let colleague: Principal | null = null
let allowBefore: boolean | null = null
let enabledBefore: string | null = null
const seeded: Dashboard[] = []

/**
 * Write the global reactions toggle.
 *
 * @param on the new state.
 * @return nothing.
 */
async function globalToggle(on: boolean): Promise<void> {
	const res = await admin.post(`${APPCONFIG}/${KEY_ENABLED}?format=json`, {
		form: { value: on ? '1' : '0' },
	})
	expect(res.status(), await res.text()).toBe(200)
}

/**
 * Create a dashboard as Pieter and make it his active one.
 *
 * @param label the name's label.
 * @return the dashboard.
 */
async function pietersDashboard(label: string): Promise<Dashboard> {
	const name = `E2E ${label} ${RUN}`
	const res = await pieter!.api.post(`${API}/dashboard`, { data: { name } })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const dash = (await res.json()).dashboard
	const made = { id: Number(dash.id), uuid: String(dash.uuid), name }
	seeded.push(made)
	const activate = await pieter!.api.post(`${API}/dashboard/${made.id}/activate`)
	expect(activate.status(), await activate.text()).toBeLessThan(300)
	const active = await pieter!.api.post(`${API}/dashboards/default`, {
		data: { uuid: made.uuid },
	})
	expect(active.status(), await active.text()).toBeLessThan(300)
	return made
}

/**
 * The summary as a principal reads it.
 *
 * @param who the principal.
 * @param uuid the dashboard.
 * @return the summary.
 */
async function summaryOf(
	who: Principal,
	uuid: string,
): Promise<{ counts: Record<string, number>; mine: string[]; enabled: boolean }> {
	const res = await who.api.get(`${API}/dashboards/${uuid}/reactions`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Log Pieter in and open LaunchPad on his active dashboard, waiting until
 * the bar's own summary request has answered.
 *
 * @param browser the browser.
 * @param uuid the dashboard expected to open.
 * @return the context, the page and the summary the page received.
 */
async function openAsPieter(
	browser: Browser,
	uuid: string,
): Promise<{ context: BrowserContext; page: Page; enabled: boolean }> {
	const { context, page } = await loginAs(
		browser,
		pieter!.username,
		pieter!.password,
	)
	await context.addInitScript(() => {
		try {
			window.localStorage.setItem('cn-walkthrough-seen:launchpad', '999.0.0')
			window.localStorage.setItem('cn-support-dialog-shown:launchpad', '1')
		} catch {
			// The server-side preference set in beforeAll is the real mechanism.
		}
	})
	const answered = page.waitForResponse(
		(r) =>
			r.url().includes(`/api/dashboards/${uuid}/reactions`)
			&& r.request().method() === 'GET',
		{ timeout: 60_000 },
	)
	await page.goto('/index.php/apps/launchpad')
	await expect(
		page.locator(
			`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${uuid}"]`,
		),
	).toBeVisible({ timeout: 30_000 })
	const response = await answered
	expect(response.status()).toBe(200)
	return { context, page, enabled: (await response.json()).enabled === true }
}

test.beforeAll(async () => {
	admin = await adminContext()

	const settings = await admin.get(`${API}/admin/settings`)
	expect(settings.status(), await settings.text()).toBe(200)
	allowBefore = (await settings.json()).allowUserDashboards === true
	const enable = await admin.put(`${API}/admin/settings`, {
		data: { allowUserDash: true },
	})
	expect(enable.status(), await enable.text()).toBe(200)

	const keys = await admin.get(`${APPCONFIG}?format=json`)
	expect(keys.status(), await keys.text()).toBe(200)
	if (((await keys.json()).ocs.data.data as string[]).includes(KEY_ENABLED)) {
		const value = await admin.get(`${APPCONFIG}/${KEY_ENABLED}?format=json`)
		enabledBefore = String((await value.json()).ocs.data.data)
	}
	await globalToggle(true)

	pieter = await createPrincipal(admin, 'e2e-rxn-pieter')
	colleague = await createPrincipal(admin, 'e2e-rxn-colleague')
	await assertActingAs(pieter.api, pieter.username)
	await assertActingAs(colleague.api, colleague.username)
	const seen = await pieter.api.put(`${API}/preferences/support-dialog-seen`, {
		data: { value: '1' },
	})
	expect(seen.status(), await seen.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	for (const dash of seeded) {
		await pieter?.api.delete(`${API}/dashboard/${dash.id}`)
	}
	await removePrincipal(admin, pieter)
	await removePrincipal(admin, colleague)
	if (enabledBefore === null) {
		await admin.delete(`${APPCONFIG}/${KEY_ENABLED}?format=json`)
	} else {
		await admin.post(`${APPCONFIG}/${KEY_ENABLED}?format=json`, {
			form: { value: enabledBefore },
		})
	}
	if (allowBefore !== null) {
		await admin.put(`${API}/admin/settings`, {
			data: { allowUserDash: allowBefore },
		})
	}
	await admin.dispose()
})

test.describe('REQ-RXN-010 a viewer reacts from the dashboard', () => {
	test('Pieter clicks 👍 to add his reaction, and again to take it back', async ({
		browser,
	}) => {
		// @e2e dashboard-reactions::add-and-remove
		const team = await pietersDashboard('Team')
		await publishForRecipients(pieter!.api, team.id)
		await shareWith(pieter!.api, team.id, colleague!.username, 'view_only')
		const seededReaction = await colleague!.api.post(
			`${API}/dashboards/${team.uuid}/reactions`,
			{
				data: { emoji: '👍' },
			},
		)
		expect(seededReaction.status(), await seededReaction.text()).toBe(200)

		const { context, page, enabled } = await openAsPieter(browser, team.uuid)
		try {
			expect(enabled).toBe(true)
			const bar = page.locator(BAR)
			await expect(bar).toBeVisible()
			const thumbs = bar.locator('[data-testid="reaction-👍"]')
			await expect(thumbs.locator('.dashboard-reactions__count')).toHaveText(
				'1',
			)
			await expect(thumbs).toHaveAttribute('aria-pressed', 'false')

			const added = page.waitForResponse(
				(r) =>
					r.url().includes(`/api/dashboards/${team.uuid}/reactions`)
					&& r.request().method() === 'POST',
			)
			await thumbs.click()
			expect((await added).status()).toBe(200)
			await expect(thumbs.locator('.dashboard-reactions__count')).toHaveText(
				'2',
			)
			await expect(thumbs).toHaveAttribute('aria-pressed', 'true')
			await expect(thumbs).toHaveClass(/dashboard-reactions__chip--mine/)

			const removed = page.waitForResponse(
				(r) =>
					r.url().includes(`/api/dashboards/${team.uuid}/reactions/`)
					&& r.request().method() === 'DELETE',
			)
			await thumbs.click()
			expect((await removed).status()).toBe(204)
			await expect(thumbs.locator('.dashboard-reactions__count')).toHaveText(
				'1',
			)
			await expect(thumbs).toHaveAttribute('aria-pressed', 'false')
			await expect(thumbs).not.toHaveClass(/dashboard-reactions__chip--mine/)
		} finally {
			await context.close()
		}

		const after = await summaryOf(pieter!, team.uuid)
		expect(after.counts).toEqual({ '👍': 1 })
		expect(after.mine).toEqual([])
	})

	test('with reactions off, Pieter sees no reactions bar', async ({ browser }) => {
		// @e2e dashboard-reactions::reactions-off
		const board = await pietersDashboard('Board')
		await globalToggle(false)
		try {
			const { context, page, enabled } = await openAsPieter(
				browser,
				board.uuid,
			)
			try {
				// The page asked, was told "off", and drew nothing.
				expect(enabled).toBe(false)
				await expect(page.locator(BAR)).toHaveCount(0)
				await expect(page.locator('[data-testid^="reaction-"]')).toHaveCount(
					0,
				)
			} finally {
				await context.close()
			}
		} finally {
			await globalToggle(true)
		}
	})
})
