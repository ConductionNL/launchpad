/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for adding and configuring a People widget
 * (openspec/specs/people-widget REQ-PPL-001 and REQ-PPL-002), written for
 * e2e-coverage-programme.
 *
 * The add and config scenarios drive the real Add Widget modal (sidebar →
 * personal-row cog → "Add widget" → "Custom widget" → type "People") and read the
 * placement back through the API, so "saved" means what the server kept, not
 * what the form showed. The modal adds to whichever personal dashboard the
 * sidebar lists first, so the dashboard id is taken from the add request
 * itself and the placement is removed afterwards.
 *
 * The unknown-sortBy scenario cannot be reached by picking from the form (it
 * only offers the two valid keys), so it seeds a placement that already holds
 * the bad value and opens it in the edit modal, which is where a stored value
 * meets the form's validation.
 *
 * Gate-19 @e2e traceability:
 *   @e2e people-widget::widget-can-be-added-to-a-dashboard
 *   @e2e people-widget::config-saved-to-placement
 *   @e2e people-widget::invalid-birthdaywindowdays-rejected
 *   @e2e people-widget::unknown-sortby-value-rejected
 *   @e2e people-widget::showfields-subset-validated
 *
 * @spec openspec/specs/people-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Person } from './support/peopleWidget.ts'

import { expect, test } from '@playwright/test'
import { closeSidebar, gotoLaunchPad, openAddWidgetModal } from './fixtures/widget-flow.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { addAndSaveDraft, enterEditMode } from './support/dashboardSwitcher.ts'
import {
	groupFilter,
	peopleWidget,
	personItem,
	provisionPerson,
	removeGroups,
	removePeople,
	seedPeopleDashboard,
	stampedGroup,
} from './support/peopleWidget.ts'
import { adminContext, API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const ADD_REQUEST = /\/apps\/launchpad\/api\/dashboard\/(\d+)\/widgets(\?|$)/

let admin: APIRequestContext
let group = ''
let fenna: Person | null = null

test.setTimeout(120_000)

test.beforeAll(async () => {
	test.setTimeout(180_000)
	admin = await adminContext()
	group = await stampedGroup(admin, 'e2e-ppl-config')
	fenna = await provisionPerson(admin, 'e2e-ppl-fenna', {
		displayName: 'Fenna de Wit',
		email: `fenna-${STAMP}@example.com`,
		groups: [group],
		self: { role: 'Beleidsadviseur', organisation: 'Gemeente Utrecht' },
	})
})

test.afterAll(async () => {
	await removePeople(admin, [fenna])
	await removeGroups(admin, [group].filter(Boolean))
	await admin.dispose()
})

/**
 * Open the Add Widget modal with the People type chosen.
 *
 * @param page the page, on the workspace.
 * @return the dialog.
 */
async function openPeopleForm(page: Page): Promise<Locator> {
	await openAddWidgetModal(page, 'people')
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog.getByLabel(/group filter/i)).toBeVisible({ timeout: 10_000 })
	return dialog
}

/**
 * The NcSelect in a dialog whose label reads `label`.
 *
 * @param page the page.
 * @param dialog the dialog.
 * @param label the select's label.
 * @return the select root.
 */
function selectLabelled(page: Page, dialog: Locator, label: RegExp): Locator {
	return dialog.locator('.v-select').filter({ has: page.getByText(label) }).first()
}

/**
 * Pick an option in an NcSelect.
 *
 * @param page the page.
 * @param select the select root.
 * @param option the option's label.
 * @return nothing.
 */
async function pick(page: Page, select: Locator, option: RegExp): Promise<void> {
	await select.locator('.vs__dropdown-toggle').click()
	await page.locator('.vs__dropdown-option').filter({ hasText: option }).first().click()
}

/**
 * Submit the modal and Save the edit-mode draft (REQ-EDIT-001/002): the new
 * widget is created by that Save.
 *
 * @param page the page.
 * @param dialog the dialog.
 * @return the dashboard id and the new placement's id.
 */
async function submitAdd(page: Page, dialog: Locator): Promise<{ dashboardId: number, placementId: number }> {
	const { dashboardId, placement } = await addAndSaveDraft(page, dialog)
	return { dashboardId, placementId: Number(placement.id) }
}

/**
 * Read a placement back from the server.
 *
 * @param added the dashboard id and placement id of the new widget.
 * @return the dashboard id and the placement as the server keeps it.
 */
async function readBack(added: { dashboardId: number, placementId: number }): Promise<{ dashboardId: number, placement: Record<string, unknown> }> {
	const { dashboardId, placementId } = added
	expect(placementId).toBeTruthy()

	const res = await admin.get(`${API}/dashboard/${dashboardId}`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const placements = (body.placements ?? body.data?.placements ?? []) as Array<Record<string, unknown>>
	const placement = placements.find((p) => Number(p.id) === placementId)
	expect(placement, `placement ${placementId} on dashboard ${dashboardId}`).toBeTruthy()
	return { dashboardId, placement: placement! }
}

/**
 * Remove a placement, tolerating one that is already gone.
 *
 * @param placement the placement, or null.
 * @return nothing.
 */
async function removePlacement(placement: Record<string, unknown> | null): Promise<void> {
	if (placement?.id) {
		await admin.delete(`${API}/widgets/${placement.id}`)
	}
}

test.describe('REQ-PPL-001 / REQ-PPL-002: adding and configuring', () => {
	// The modal is reached through a personal dashboard's row menu, so the
	// admin needs one; a cold instance has none.
	let own: SeededDashboard | null = null

	test.beforeAll(async () => {
		own = await seedActiveDashboard(admin, `E2E People add ${STAMP}`)
	})

	test.afterAll(async () => {
		await removeSeededDashboard(admin, own)
	})

	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e people-widget::widget-can-be-added-to-a-dashboard
	test('the widget picker offers People, and Add creates a `people` placement', async ({ page }) => {
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		const typeSelect = dialog.getByLabel(/widget type/i)
		await expect(typeSelect.locator('option').filter({ hasText: /^\s*People\s*$/ })).toHaveCount(1)
		await typeSelect.selectOption({ label: 'People' })

		let placement: Record<string, unknown> | null = null
		try {
			const response = await submitAdd(page, dialog)
			placement = (await readBack(response)).placement
			expect(placement.widgetId).toBe('people')
		} finally {
			await removePlacement(placement)
		}
	})

	// @e2e people-widget::config-saved-to-placement
	test('a card layout with a group filter is what the placement keeps', async ({ page }) => {
		const dialog = await openPeopleForm(page)
		await pick(page, selectLabelled(page, dialog, /^Layout$/), /^\s*Card\s*$/)
		await dialog.getByLabel(/group filter/i).fill(group)

		let placement: Record<string, unknown> | null = null
		try {
			const response = await submitAdd(page, dialog)
			placement = (await readBack(response)).placement
			const content = placement.content as Record<string, unknown>
			expect(content.layout).toBe('card')
			expect(content.filters).toEqual(groupFilter(group))
		} finally {
			await removePlacement(placement)
		}
	})

	// @e2e people-widget::invalid-birthdaywindowdays-rejected
	test('a birthday window of 50 shows "Must be between 0 and 30" and cannot be saved', async ({ page }) => {
		const dialog = await openPeopleForm(page)
		const posts: string[] = []
		page.on('request', (req) => {
			if (req.method() === 'POST' && ADD_REQUEST.test(req.url())) {
				posts.push(req.url())
			}
		})

		await dialog.getByLabel(/birthday window/i).fill('50')
		await expect(dialog.getByText('Must be between 0 and 30')).toBeVisible({ timeout: 5_000 })

		// The save MUST NOT proceed: the modal's submit gate is the Add button.
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeDisabled({ timeout: 5_000 })
		expect(posts).toHaveLength(0)

		await dialog.getByRole('button', { name: /cancel/i }).click()
		await expect(dialog).not.toBeVisible({ timeout: 5_000 })
		expect(posts).toHaveLength(0)
	})
})

test.describe('REQ-PPL-002: stored configuration', () => {
	let dashboard: SeededDashboard | null = null

	test.afterEach(async () => {
		await removeSeededDashboard(admin, dashboard)
		dashboard = null
	})

	// @e2e people-widget::unknown-sortby-value-rejected
	test('an unknown sortBy is refused with a message and the form falls back to Display name', async ({ page }) => {
		dashboard = await seedPeopleDashboard(admin, `E2E People sort ${STAMP}`, [
			{ content: { layout: 'grid', filters: groupFilter(group), sortBy: 'unknown-field' } },
		])
		await gotoLaunchPad(page)
		await expect(peopleWidget(page)).toBeVisible({ timeout: 20_000 })
		await enterEditMode(page)
		await closeSidebar(page)

		const cell = page.locator('.grid-stack-item').filter({ has: peopleWidget(page) }).first()
		await cell.locator('.cn-widget-wrapper__content').first().click({ button: 'right' })
		await expect(page.locator('[data-testid="widget-context-menu"]')).toBeVisible({ timeout: 5_000 })
		await page.locator('[data-testid="ctx-edit"]').click()

		const dialog = page.getByRole('dialog', { name: /(edit|add) widget/i }).first()
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		// Change something, so a save is being attempted rather than a no-op.
		await pick(page, selectLabelled(page, dialog, /^Layout$/), /^\s*Card\s*$/)

		const save = dialog.locator('[data-testid="add-widget-save"]')
		await expect(save).toBeDisabled({ timeout: 5_000 })
		// The rejection carries a message about the sort key, shown in the
		// dialog or on the button the person pressed.
		await expect
			.poll(async () => {
				const shown = await dialog.getByText(/sort/i).filter({ hasText: /invalid|unknown|not (allowed|valid)/i }).count()
				const title = (await save.getAttribute('title')) ?? ''
				return shown > 0 || /sort/i.test(title)
			}, { timeout: 5_000 })
			.toBe(true)

		// And the sort control falls back to the default key.
		await expect(selectLabelled(page, dialog, /^Sort by$/).locator('.vs__selected')).toHaveText(
			/Display name/,
			{ timeout: 5_000 },
		)
	})

	// @e2e people-widget::showfields-subset-validated
	test('showFields {displayName, email} shows exactly those two on a card', async ({ page }) => {
		dashboard = await seedPeopleDashboard(admin, `E2E People fields ${STAMP}`, [
			{
				content: {
					layout: 'card',
					filters: groupFilter(group),
					showFields: { displayName: true, email: true },
				},
			},
		])
		await gotoLaunchPad(page)
		const card = personItem(peopleWidget(page, 'card'), 'Fenna de Wit')
		await expect(card).toBeVisible({ timeout: 20_000 })

		await expect(card).toContainText('Fenna de Wit')
		await expect(card).toContainText(fenna!.email!)
		// Fenna HAS a role and an organisation; showFields leaves them out.
		await expect(card).not.toContainText('Beleidsadviseur')
		await expect(card).not.toContainText('Gemeente Utrecht')
	})
})
