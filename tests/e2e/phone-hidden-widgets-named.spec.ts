/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The phone notice names the hidden widgets (phone-hidden-widgets-named,
 * REQ-MRA-007): at 390 px a dashboard holding widgets that are not ready for a
 * phone hides them and says so above the grid, by count and by title, with
 * "Which?" explaining why and no "view desktop layout" link. At 1440 px the
 * same dashboard shows every widget and no notice. Runs as the admin (the
 * project's storage state) on a dashboard seeded for the run, removed
 * afterwards.
 *
 * A widget is hidden on a phone when its registry entry says
 * `mobileReady: false` (src/utils/phoneHiddenWidgets.js isPhoneReady); the
 * assistant widget (`ai-assistant`, the board's "Assistent") is marked so in
 * src/constants/widgetRegistry.js, which is why the test seeds two of them.
 *
 * Written from the change's scenarios; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/specs/launchpad-mobile-remote-access/spec.md
 */

import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Placement } from './support/widgetPlacements.ts'

import { expect, test } from '@playwright/test'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { adminApi, gridItem, LP_API, Seeds } from './support/widgetPlacements.ts'

const STAMP = Date.now().toString(36)
const QUICK_START = `Snel beginnen (Word) ${STAMP}`
const ASSISTANT = `Assistent ${STAMP}`

let seeds: Seeds
let dashboard: SeededDashboard
let hidden: Placement[] = []
let shown: Placement

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * Add a widget and give it its own title. The create call stores no title;
 * like the add-widget flow, the title follows in an update of the placement.
 *
 * @param title the placement's custom title.
 * @param data the widget fields.
 * @return the created placement.
 */
async function titled(title: string, data: Record<string, unknown>): Promise<Placement> {
	const placement = await seeds.widget(dashboard, data)
	const res = await seeds.api.put(`${LP_API}/widgets/${placement.id}`, {
		data: { customTitle: title },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	return { ...placement, customTitle: title }
}

test.beforeAll(async () => {
	seeds = new Seeds(await adminApi())
	dashboard = await seeds.dashboard(`E2E Mijn werkplek ${STAMP}`)
	await clearSeededWidgets(seeds.api, dashboard.id)
	hidden = [
		await titled(QUICK_START, {
			widgetId: 'ai-assistant',
			gridX: 0,
			gridY: 0,
			content: { agentUuid: '' },
		}),
		await titled(ASSISTANT, {
			widgetId: 'ai-assistant',
			gridX: 4,
			gridY: 0,
			content: { agentUuid: '' },
		}),
	]
	shown = await seeds.widget(dashboard, {
		widgetId: 'text',
		gridX: 0,
		gridY: 4,
		customTitle: `Welkom ${STAMP}`,
		content: { text: `Welkom ${STAMP}`, contentMode: 'markdown' },
	})
})

test.afterAll(async () => {
	await seeds?.cleanup()
})

// @e2e launchpad-mobile-remote-access::pieter-on-his-phone
test('at 390 px the notice names the widgets that are not phone-ready, with "Which?"', async ({
	page,
}) => {
	await page.setViewportSize({ width: 390, height: 844 })
	await page.goto('/index.php/apps/launchpad')
	await expect(
		page.locator(
			`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${dashboard.uuid}"]`,
		),
	).toBeVisible({ timeout: 30_000 })
	await expect(gridItem(page, shown.id)).toBeVisible({ timeout: 30_000 })

	const notice = page.getByTestId('phone-hidden-notice')
	await expect(notice).toBeVisible()
	const sentence = notice.getByTestId('phone-hidden-sentence')
	await expect(sentence).toContainText('2 widgets do not work on a phone and are hidden:')
	await expect(sentence).toContainText(QUICK_START)
	await expect(sentence).toContainText(ASSISTANT)

	// The named widgets are hidden, not stacked; the phone-ready one stays.
	for (const placement of hidden) {
		await expect(gridItem(page, placement.id)).toHaveCount(0)
	}

	// "Which?" explains why, on demand.
	const which = notice.getByTestId('phone-hidden-which')
	await expect(which).toHaveText('Which?')
	await expect(which).toHaveAttribute('aria-expanded', 'false')
	await expect(notice.getByTestId('phone-hidden-why')).toHaveCount(0)
	await which.click()
	await expect(which).toHaveAttribute('aria-expanded', 'true')
	await expect(notice.getByTestId('phone-hidden-why')).toHaveText(
		'Some widgets need a larger screen, so a phone hides them. On a tablet or computer you see them as usual.',
	)

	// No forced desktop layout on a phone.
	await expect(page.getByText(/desktop layout/i)).toHaveCount(0)

	// A large screen shows every widget and no notice.
	await page.setViewportSize({ width: 1440, height: 900 })
	await expect(page.getByTestId('phone-hidden-notice')).toHaveCount(0)
	for (const placement of hidden) {
		await expect(gridItem(page, placement.id)).toBeVisible({ timeout: 30_000 })
	}
})
