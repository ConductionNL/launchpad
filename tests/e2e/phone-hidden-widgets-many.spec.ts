/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Widgets hidden on a phone, by count (launchpad-mobile-remote-access,
 * REQ-MRA-003 and REQ-MRA-007), written for e2e-coverage-programme task 4.18.
 *
 * A widget type whose registry entry says `mobileReady: false` is hidden below
 * the phone breakpoint (src/utils/phoneHiddenWidgets.js isPhoneReady, applied
 * in Views.vue shownPlacements). Today only `ai-assistant` is marked so
 * (src/constants/widgetRegistry.js), so every "not ready" placement below is
 * an assistant with its own title, and every "ready" one is a text widget.
 *
 * Two dashboards, both seeded for the run as the admin (the project's storage
 * state) and removed afterwards:
 *   - "mixed": four phone-ready widgets and two that are not (REQ-MRA-003).
 *   - "many": five that are not ready and one that is (REQ-MRA-007, three
 *     named and the rest counted).
 *
 * The REQ-MRA-003 notice wording (`2 widgets hidden on mobile`, with a
 * "view desktop layout" link) was replaced by REQ-MRA-007, which says the
 * notice SHALL replace that link and words it "2 widgets do not work on a
 * phone and are hidden: …". The test asserts the count in the REQ-MRA-007
 * wording, the one the product ships; phone-hidden-widgets-named.spec.ts
 * asserts the link is gone.
 *
 * @e2e openspec/specs/launchpad-mobile-remote-access/spec.md
 */

import type { Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Placement } from './support/widgetPlacements.ts'

import { expect, test } from '@playwright/test'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { adminApi, gridItem, LP_API, Seeds } from './support/widgetPlacements.ts'

const STAMP = Date.now().toString(36)

let seeds: Seeds
let mixed: SeededDashboard
let many: SeededDashboard
let mixedReady: Placement[] = []
let mixedHidden: Placement[] = []
let manyReady: Placement
let manyHidden: Placement[] = []
const MANY_TITLES = ['Alfa', 'Bravo', 'Charlie', 'Delta', 'Echo'].map(
	(n) => `${n} ${STAMP}`,
)

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * Seed a dashboard with phone-ready text widgets and assistants that a
 * phone hides.
 *
 * @param name the dashboard name.
 * @param readyCount how many text widgets.
 * @param hiddenTitles one assistant per title.
 * @return the dashboard and its placements, split by readiness.
 */
async function seedBoard(
	name: string,
	readyCount: number,
	hiddenTitles: string[],
): Promise<{ dash: SeededDashboard; ready: Placement[]; hidden: Placement[] }> {
	const dash = await seeds.dashboard(name)
	await clearSeededWidgets(seeds.api, dash.id)
	const ready: Placement[] = []
	const hidden: Placement[] = []
	for (let i = 0; i < readyCount; i++) {
		ready.push(
			await seeds.widget(dash, {
				widgetId: 'text',
				gridX: (i % 3) * 4,
				gridY: Math.floor(i / 3) * 3,
				customTitle: `Ready ${i + 1} ${STAMP}`,
				content: { text: `Ready ${i + 1} ${STAMP}`, contentMode: 'markdown' },
			}),
		)
	}
	for (const [i, title] of hiddenTitles.entries()) {
		const placement = await seeds.widget(dash, {
			widgetId: 'ai-assistant',
			gridX: (i % 3) * 4,
			gridY: 6 + Math.floor(i / 3) * 3,
			content: { agentUuid: '' },
		})
		// The create call stores no title; like the add-widget flow, the
		// title follows in an update of the placement.
		const res = await seeds.api.put(`${LP_API}/widgets/${placement.id}`, {
			data: { customTitle: title },
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
		hidden.push({ ...placement, customTitle: title })
	}
	return { dash, ready, hidden }
}

/**
 * Open the workspace on a dashboard at a viewport width and wait for it.
 *
 * @param page the page.
 * @param dash the dashboard, made active first.
 * @param width the viewport width.
 * @param waitFor a placement that renders at every width.
 * @return nothing.
 */
async function openAt(
	page: Page,
	dash: SeededDashboard,
	width: number,
	waitFor: Placement,
): Promise<void> {
	await seeds.activate(dash)
	await page.setViewportSize({ width, height: 900 })
	await page.goto('/index.php/apps/launchpad')
	await expect(
		page.locator(
			`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${dash.uuid}"]`,
		),
	).toBeVisible({ timeout: 30_000 })
	await expect(gridItem(page, waitFor.id)).toBeVisible({ timeout: 30_000 })
}

test.beforeAll(async () => {
	seeds = new Seeds(await adminApi())
	const a = await seedBoard(`E2E phone mixed ${STAMP}`, 4, [
		`Hidden one ${STAMP}`,
		`Hidden two ${STAMP}`,
	])
	mixed = a.dash
	mixedReady = a.ready
	mixedHidden = a.hidden
	const b = await seedBoard(`E2E phone many ${STAMP}`, 1, MANY_TITLES)
	many = b.dash
	manyReady = b.ready[0]
	manyHidden = b.hidden
})

test.afterAll(async () => {
	await seeds?.cleanup()
})

// @e2e launchpad-mobile-remote-access::mobile-viewport-hides-non-ready-widgets
test('at 375 px only the four phone-ready widgets render and the notice counts the two hidden', async ({
	page,
}) => {
	await openAt(page, mixed, 375, mixedReady[0])

	for (const placement of mixedReady) {
		await expect(gridItem(page, placement.id)).toBeVisible()
	}
	// Hidden means not rendered at all, not stacked below the others.
	for (const placement of mixedHidden) {
		await expect(gridItem(page, placement.id)).toHaveCount(0)
	}
	await expect(page.locator('.launchpad-grid-item[data-placement-id]')).toHaveCount(4)

	const sentence = page.getByTestId('phone-hidden-sentence')
	await expect(sentence).toBeVisible()
	await expect(sentence).toContainText(/^2 widgets\b.*\bhidden\b/)
})

// @e2e launchpad-mobile-remote-access::desktop-viewport-renders-all
test('at 1280 px the same dashboard renders all six widgets and no phone notice', async ({
	page,
}) => {
	await openAt(page, mixed, 1280, mixedReady[0])

	for (const placement of [...mixedReady, ...mixedHidden]) {
		await expect(gridItem(page, placement.id)).toBeVisible({ timeout: 30_000 })
	}
	await expect(page.locator('.launchpad-grid-item[data-placement-id]')).toHaveCount(6)
	await expect(page.getByTestId('phone-hidden-notice')).toHaveCount(0)
})

// @e2e launchpad-mobile-remote-access::many-hidden-widgets
test('at 390 px with five widgets hidden the notice names three and says "and 2 more"', async ({
	page,
}) => {
	await openAt(page, many, 390, manyReady)

	for (const placement of manyHidden) {
		await expect(gridItem(page, placement.id)).toHaveCount(0)
	}

	const sentence = page.getByTestId('phone-hidden-sentence')
	await expect(sentence).toBeVisible()
	await expect(sentence).toContainText('5 widgets do not work on a phone and are hidden:')
	await expect(sentence).toContainText('and 2 more')

	// Exactly three of the five titles are named; the other two are only
	// counted. Which three follows placement order, so count rather than pin.
	const text = (await sentence.textContent()) ?? ''
	const named = MANY_TITLES.filter((title) => text.includes(title))
	expect(named, `named in "${text}"`).toHaveLength(3)
})
