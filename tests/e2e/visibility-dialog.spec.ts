/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * "Who sees this widget?" as board LpZichtbaarheid draws it
 * (visibility-dialog-board-sync, REQ-CVUI-006 and REQ-CVUI-007): the dialog
 * opens from the widget's three-dot menu in edit mode, is titled with the
 * widget and the dashboard, offers "If all rules match" / "If one rule
 * matches" as choice cards, shows each rule as a card, and View as says in
 * one sentence whether a person sees the widget at a moment, and why.
 * Runs as the admin (the project's storage state); a person in a stamped
 * group stands in for Sanne de Vries in KCC.
 *
 * Written from the change's scenarios; it runs in the nightly against a
 * live instance.
 *
 * @e2e openspec/changes/archive/2026-11-24-visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'

import { expect, test } from '@playwright/test'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	addToGroup,
	adminContext,
	createGroup,
	createPrincipal,
	removeGroup,
	removePrincipal,
} from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const API = `${APP}/api`
const STAMP = `${Date.now()}`
const WIDGET_TEXT = `News ${STAMP}`

let admin: APIRequestContext
let dashboardId = 0
let placementId = 0
let group: string | null = null
let sanne: Principal | null = null

test.beforeAll(async () => {
	admin = await adminContext()

	group = await createGroup(admin, `kcc-${STAMP}`)
	sanne = await createPrincipal(admin, 'sanne')
	await addToGroup(admin, sanne.username, group)

	const created = await admin.post(`${API}/dashboard`, { data: { name: `E2E Who sees ${STAMP}` } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	dashboardId = Number(dash.id)

	const widget = await admin.post(`${API}/dashboard/${dashboardId}/widgets`, {
		data: {
			widgetId: 'text',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			customTitle: 'News',
			content: { text: WIDGET_TEXT, contentMode: 'markdown' },
		},
	})
	expect(widget.status(), await widget.text()).toBeLessThan(300)
	placementId = Number((await widget.json()).id)

	// The board's rules: group KCC and Monday to Friday 08:00 to 17:00. A
	// date rule that has started keeps the widget visible to the admin, so
	// it can be opened again on every run.
	for (const rule of [
		{ ruleType: 'group', ruleConfig: { groups: [group] }, isInclude: true },
		{
			ruleType: 'time',
			ruleConfig: { startTime: '08:00', endTime: '17:00', days: ['mon', 'tue', 'wed', 'thu', 'fri'] },
			isInclude: true,
		},
		{ ruleType: 'date', ruleConfig: { startDate: '2000-01-01' }, isInclude: true },
	]) {
		const res = await admin.post(`${API}/widgets/${placementId}/rules`, { data: rule })
		expect(res.status(), await res.text()).toBeLessThan(300)
	}

	const activate = await admin.post(`${API}/dashboards/default`, { data: { uuid: String(dash.uuid) } })
	expect(activate.status(), await activate.text()).toBeLessThan(300)
})

test.afterAll(async () => {
	if (dashboardId) {
		await admin.delete(`${API}/dashboard/${dashboardId}`)
	}
	await removePrincipal(admin, sanne)
	await removeGroup(admin, group)
	await admin.dispose()
})

/**
 * The News widget's cell on the seeded dashboard.
 *
 * @param page The page.
 * @return The cell.
 */
function newsCell(page: Page) {
	return page.locator(`[data-testid="widget-placement-${placementId}"]`)
}

/**
 * Open the dashboard in edit mode and choose "Who sees this widget?" from
 * the News widget's three-dot menu, by keyboard.
 *
 * @param page The page.
 */
async function openFromTheMenu(page: Page): Promise<void> {
	await page.goto(APP)
	await expect(page.getByText(WIDGET_TEXT).first()).toBeVisible({ timeout: 30_000 })
	if ((await page.locator('.launchpad-edit-mode').count()) === 0) {
		await enterEditMode(page)
	}
	const trigger = newsCell(page).locator('[data-testid="cn-widget-edit-cog"] button').first()
	await trigger.focus()
	await page.keyboard.press('Enter')
	await page.getByRole('menuitem', { name: 'Who sees this widget?' }).click()
	await expect(page.locator('[data-test="visibility-rules-modal"]')).toBeVisible({ timeout: 15_000 })
	await expect(page.locator('[data-test="add-rule"]')).toBeVisible({ timeout: 15_000 })
}

// @e2e conditional-visibility-editor::from-the-menu
test('the widget menu in edit mode opens the dialog for that widget', async ({ page }) => {
	await openFromTheMenu(page)

	await expect(page.locator('[data-test="visibility-rules-title"]')).toHaveText('Who sees this widget?')
	await expect(page.locator('[data-test="visibility-rules-subtitle"]')).toContainText(` · E2E Who sees ${STAMP}`)
	await expect(page.locator('[data-test="rule-match-all"]')).toHaveText('If all rules match')
	await expect(page.locator('[data-test="rule-match-any"]')).toHaveAttribute('aria-checked', 'true')

	const kinds = page.locator('[data-test="rule-card-kind"]')
	await expect(kinds).toHaveText(['Groups', 'Times', 'Period'])
	await expect(page.locator('[data-test="rule-card-value"]').nth(1)).toHaveText(/Mon–Fri 08:00–17:00/)
	await expect(page.locator('[data-test="rule-card-edit"]')).toHaveCount(3)
	await expect(page.locator('[data-test="rule-card-delete"]')).toHaveCount(3)
})

// @e2e conditional-visibility-editor::view-as-sanne
test('View as a person in the group on a Tuesday at 10:00 says they see the widget, and why', async ({ page }) => {
	await openFromTheMenu(page)

	// All rules must match, saved on the placement.
	const saved = page.waitForResponse((res) => res.url().includes(`/api/widgets/${placementId}`) && res.request().method() === 'PUT')
	await page.locator('[data-test="rule-match-all"]').click()
	expect((await saved).status()).toBe(200)
	await expect(page.locator('[data-test="rule-match-all"]')).toHaveAttribute('aria-checked', 'true')

	const person = page.locator('[data-test="preview-person"]').locator('input').first()
	await person.fill(sanne!.username)
	// NcSelect splits a long name into two flex parts, so the option's
	// accessible name carries a space mid-name; match its text instead.
	await page.getByRole('option').filter({ hasText: sanne!.username }).first().click()
	// NcTextField hands data-test to the <input> itself, not to a wrapper.
	await page.locator('input[data-test="preview-datetime"], [data-test="preview-datetime"] input').first().fill('2026-10-13T10:00')

	const previewed = page.waitForResponse((res) => res.url().includes('/api/visibility/preview'))
	await page.locator('[data-test="run-preview"]').click()
	const body = (await previewed).request().postDataJSON()
	expect(body.context.userId).toBe(sanne!.username)
	expect(body.match).toBe('all')

	await expect(page.locator('[data-test="preview-verdict-text"]')).toHaveText('Visible')
	await expect(page.locator('[data-test="visibility-sentence"]')).toHaveText(
		new RegExp(`^${sanne!.username} sees this widget, because ${sanne!.username} is in ${group}, .+ 10:00 is within the times and .+ is within the period\\.$`),
	)

	// Back to one rule, as the dashboard was.
	await page.locator('[data-test="rule-match-any"]').click()
	await expect(page.locator('[data-test="rule-match-any"]')).toHaveAttribute('aria-checked', 'true')
})

/**
 * Store how the News widget's rules combine, then preview its stored rules
 * as the person in the group on Saturday 17 October 10:00, outside the
 * Monday-to-Friday times, in the mode the placement now holds.
 *
 * @param mode `all` or `any`.
 * @return Whether the person sees the widget, and the mode read back.
 */
async function saturdayVerdict(mode: string): Promise<{ visible: boolean; stored: string }> {
	const saved = await admin.put(`${API}/widgets/${placementId}`, { data: { ruleMatch: mode } })
	expect(saved.status(), await saved.text()).toBe(200)
	const stored = String((await saved.json()).ruleMatch)

	const rules = await admin.get(`${API}/widgets/${placementId}/rules`)
	expect(rules.status(), await rules.text()).toBe(200)
	const preview = await admin.post(`${API}/visibility/preview`, {
		data: {
			rules: (await rules.json()).rules,
			context: { userId: sanne!.username, datetime: '2026-10-17T10:00:00' },
			match: stored,
			placementId,
		},
	})
	expect(preview.status(), await preview.text()).toBe(200)
	return { visible: (await preview.json()).visible === true, stored }
}

// @e2e conditional-visibility::all-rules-must-match
test('with all rules required, the person in the group does not see the widget on Saturday', async () => {
	const { visible, stored } = await saturdayVerdict('all')
	expect(stored).toBe('all')
	expect(visible, 'the time rule does not match on Saturday, so the widget is hidden').toBe(false)

	const refused = await admin.put(`${API}/widgets/${placementId}`, { data: { ruleMatch: 'most' } })
	expect(refused.status(), 'only any or all are accepted').toBe(400)
})

// @e2e conditional-visibility::one-rule-is-enough
test('with one rule enough, the person in the group sees the widget on Saturday', async () => {
	const { visible, stored } = await saturdayVerdict('any')
	expect(stored).toBe('any')
	expect(visible, 'the group rule matches, so the widget is visible').toBe(true)
})

// @e2e conditional-visibility-editor::viewing-as-another-person-needs-the-rules-editor
test('someone who may not edit the widget\'s rules cannot view it as another person', async () => {
	const refused = await sanne!.api.post(`${API}/visibility/preview`, {
		data: { rules: [], context: { userId: 'admin' }, placementId },
	})
	expect(refused.status(), await refused.text()).toBe(403)

	// CONTROL: viewing as yourself needs no such right.
	const own = await sanne!.api.post(`${API}/visibility/preview`, {
		data: { rules: [], context: { userId: sanne!.username } },
	})
	expect(own.status(), await own.text()).toBe(200)
})
