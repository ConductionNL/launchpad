/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The reactions bar at the foot of the dashboard (reactions-bar-at-the-foot,
 * REQ-RXN-010 as modified): on "Zuiddrecht intranet" the bar sits under the
 * last widget, over the full width, asks "What do you think of this
 * dashboard?" before the reactions and names the last change after them; a
 * click on an emoji adds the viewer's reaction, marked as theirs, and a
 * second click takes it back.
 *
 * Runs as the admin (the project's storage state) on a dashboard seeded for
 * the run, with its default widgets. The global reactions toggle is a
 * Nextcloud app value; it is switched on for the run and put back afterwards.
 *
 * Written from the change's scenarios; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/specs/dashboard-reactions/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = Date.now().toString(36)
const APPCONFIG = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/launchpad'
const KEY_ENABLED = 'reactions_enabled_default'

type Summary = {
	enabled: boolean
	allowed: string[]
	counts: Record<string, number>
	mine: string[]
}

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let enabledBefore: string | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * The reactions summary of the seeded dashboard, as the admin reads it.
 *
 * @return the summary.
 */
async function summary(): Promise<Summary> {
	const res = await admin.get(`${API}/dashboards/${dashboard!.uuid}/reactions`)
	expect(res.status(), await res.text()).toBe(200)
	return res.json()
}

/**
 * Write the global reactions toggle.
 *
 * @param value the stored value.
 * @return nothing.
 */
async function writeToggle(value: string): Promise<void> {
	const res = await admin.post(`${APPCONFIG}/${KEY_ENABLED}?format=json`, {
		form: { value },
	})
	expect(res.status(), await res.text()).toBe(200)
}

test.beforeAll(async () => {
	admin = await adminContext()
	const keys = await admin.get(`${APPCONFIG}?format=json`)
	expect(keys.status(), await keys.text()).toBe(200)
	if (((await keys.json()).ocs.data.data as string[]).includes(KEY_ENABLED)) {
		const value = await admin.get(`${APPCONFIG}/${KEY_ENABLED}?format=json`)
		enabledBefore = String((await value.json()).ocs.data.data)
	}
	await writeToggle('1')
	dashboard = await seedActiveDashboard(admin, `Zuiddrecht intranet ${STAMP}`)
})

test.afterAll(async () => {
	if (admin) {
		await removeSeededDashboard(admin, dashboard).catch(() => undefined)
		if (enabledBefore === null) {
			await admin.delete(`${APPCONFIG}/${KEY_ENABLED}?format=json`).catch(() => undefined)
		} else {
			await writeToggle(enabledBefore).catch(() => undefined)
		}
		await admin.dispose()
	}
})

// @e2e dashboard-reactions::add-and-remove
test('on Zuiddrecht intranet the reactions bar sits at the foot, and a reaction is added and taken back there', async ({
	page,
}) => {
	const before = await summary()
	expect(before.enabled).toBe(true)
	const emoji = before.allowed.find((e) => (before.counts[e] ?? 0) === 0)
	expect(emoji, `an allowed emoji nobody used yet in ${JSON.stringify(before)}`).toBeTruthy()

	// Since #2190 (REQ-INIT-011) the page carries the opening dashboard's
	// reactions read in its initial state and the bar mounts from it without
	// a GET; the bar's own request is only the fallback. Accept either.
	const path = `/api/dashboards/${dashboard!.uuid}/reactions`
	const asked: number[] = []
	page.on('response', (r) => {
		if (r.url().includes(path) && r.request().method() === 'GET') {
			asked.push(r.status())
		}
	})
	await page.goto('/index.php/apps/launchpad')
	const region = page.locator(
		`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${dashboard!.uuid}"]`,
	)
	await expect(region).toBeVisible({ timeout: 30_000 })
	const carried = await page.evaluate((wanted) => {
		const input = document.querySelector<HTMLInputElement>(
			'input[type="hidden"][id="initial-state-launchpad-workspaceApiReads"]',
		)
		if (input === null) {
			return false
		}
		const bytes = Uint8Array.from(atob(input.value), (c) => c.charCodeAt(0))
		const reads = JSON.parse(new TextDecoder().decode(bytes)) as Array<{ path: string }>
		return (reads ?? []).some((entry) => entry.path === wanted)
	}, path)
	if (!carried) {
		await expect.poll(() => asked, { timeout: 30_000 }).toContain(200)
	}

	const bar = page.getByTestId('dashboard-reactions')
	await expect(bar).toBeVisible()

	// The question first, the reactions, then the last change.
	const parts = bar.locator(':scope > *')
	await expect(parts.first()).toHaveAttribute('data-testid', 'dashboard-reactions-question')
	await expect(parts.first()).toHaveText('What do you think of this dashboard?')
	await expect(parts.last()).toHaveAttribute('data-testid', 'dashboard-reactions-last-change')
	await expect(parts.last()).toHaveText(/^Last updated (by .+, )?.+/)

	// At the foot: under the last widget and the page title, over the full width.
	const items = page.locator('.launchpad-grid-item')
	await expect(items.first()).toBeVisible({ timeout: 30_000 })
	await bar.scrollIntoViewIfNeeded()
	const barBox = (await bar.boundingBox())!
	const lowestWidget = Math.max(
		...(await items.evaluateAll((els) =>
			els.map((el) => el.getBoundingClientRect().bottom + window.scrollY),
		)),
	)
	const barTop = await bar.evaluate((el) => el.getBoundingClientRect().top + window.scrollY)
	expect(barTop).toBeGreaterThanOrEqual(lowestWidget - 1)
	const grid = (await items.first().locator('xpath=..').boundingBox())!
	expect(barBox.width).toBeGreaterThanOrEqual(grid.width * 0.9)

	// React with an emoji nobody used yet, from the foot of the page.
	const posted = page.waitForResponse(
		(r) =>
			r.url().includes(`/api/dashboards/${dashboard!.uuid}/reactions`)
			&& r.request().method() === 'POST',
	)
	await bar.getByTestId(`reaction-add-${emoji}`).click()
	expect((await posted).status()).toBe(200)
	const chip = bar.getByTestId(`reaction-${emoji}`)
	await expect(chip.locator('.dashboard-reactions__count')).toHaveText('1')
	await expect(chip).toHaveAttribute('aria-pressed', 'true')
	await expect(chip).toHaveClass(/dashboard-reactions__chip--mine/)
	expect((await summary()).mine).toContain(emoji)

	// A second click takes it back.
	const removed = page.waitForResponse(
		(r) =>
			r.url().includes(`/api/dashboards/${dashboard!.uuid}/reactions/`)
			&& r.request().method() === 'DELETE',
	)
	await chip.click()
	expect((await removed).status()).toBe(204)
	await expect(bar.getByTestId(`reaction-${emoji}`)).toHaveCount(0)
	await expect(bar.getByTestId(`reaction-add-${emoji}`)).toBeVisible()

	const after = await summary()
	expect(after.mine).not.toContain(emoji)
	expect(after.counts[emoji!] ?? 0).toBe(0)
})
