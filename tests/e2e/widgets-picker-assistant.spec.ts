/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The assistant line of Add widget (REQ-WDG-045, widget-picker-board-sync):
 * shown only when the server has a text-to-text assistant, its proposal is
 * one of the offered cards, and Place it like this places it as Add widget
 * does.
 *
 * THE FIXTURE
 * ===========
 * Pieter (English) owns the empty dashboard "My workspace"; "Case system" is
 * an app-catalogue entry approved for his group, so it is one of his App
 * tiles. A test instance has no assistant provider, so the two answers the
 * picker reads (`GET /api/widget-picker/assistant`, `POST
 * /api/widget-picker/proposal`) are given by the browser route for the
 * scenario that needs one; the server's own endpoints are checked directly
 * (401 anonymous, the availability shape, 503 without a provider).
 *
 * Verified live 25 Nov 2026 on a sandbox Nextcloud 34.0.6 (sqlite, php -S) with
 * OpenRegister main: 4/4, and widgets-picker-board-sync.spec.ts 4/4 beside it.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::ask-the-assistant
 *   @e2e widgets::no-assistant
 *
 * @spec openspec/specs/widgets/spec.md#requirement-the-assistant-line-req-wdg-045
 */

import type { APIRequestContext, Browser, BrowserContext, Locator, Page } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, request as playwrightRequest, test } from '@playwright/test'
import { enterEditMode, saveEditDraft } from './support/dashboardSwitcher.ts'
import { setLanguage } from './support/orWidget.ts'
import { openOwnDashboard, readPlacements, setUpOwnDashboard, tearDownOwnDashboard } from './support/ownDashboard.ts'
import { addToGroup, adminContext, API, createGroup, removeGroup } from './support/principals.ts'

const STAMP = `${Date.now()}`
const DASHBOARD = `My workspace ${STAMP}`
const GROUP = `e2e-assistant-${STAMP}`
const CASE_SYSTEM = 'Case system'

let own: OwnDashboard
let admin: APIRequestContext
let catalogueUuid: string | null = null

test.setTimeout(120_000)

/**
 * Answer the picker's assistant requests in the browser.
 *
 * @param page The page.
 * @param available Whether the assistant is available.
 * @param proposal The proposal to answer, or null.
 * @return The bodies the picker sent to the proposal endpoint.
 */
async function fakeAssistant(page: Page, available: boolean, proposal: Record<string, unknown> | null): Promise<Array<Record<string, any>>> {
	const sent: Array<Record<string, any>> = []
	await page.route('**/apps/launchpad/api/widget-picker/assistant', (route) => route.fulfill({ json: { available } }))
	await page.route('**/apps/launchpad/api/widget-picker/proposal', async (route) => {
		sent.push(route.request().postDataJSON())
		await route.fulfill({ json: { proposal } })
	})
	return sent
}

/**
 * Open My workspace, enter edit mode and choose Add widget, after the
 * assistant answers are set.
 *
 * @param browser The browser.
 * @param available Whether the assistant is available.
 * @param proposal The proposal to answer.
 * @return The context, page, picker and the proposal requests.
 */
async function openPicker(
	browser: Browser,
	available: boolean,
	proposal: Record<string, unknown> | null,
): Promise<{ context: BrowserContext; page: Page; picker: Locator; sent: Array<Record<string, any>> }> {
	const count = (await readPlacements(own.owner.api, own.id)).length
	const { context, page } = await openOwnDashboard(browser, own, count)
	const sent = await fakeAssistant(page, available, proposal)
	await enterEditMode(page)
	await page.locator('.launchpad-edit-mode').getByRole('button', { name: 'Add widget', exact: true }).first().click()
	const picker = page.getByRole('dialog').filter({ hasText: 'Add widget' }).last()
	await expect(picker).toBeVisible({ timeout: 10_000 })
	return { context, page, picker, sent }
}

test.beforeAll(async () => {
	admin = await adminContext()
	own = await setUpOwnDashboard('e2e-assistant-pieter', DASHBOARD)
	await setLanguage(own.owner, 'en')
	await createGroup(admin, GROUP)
	await addToGroup(admin, own.owner.username, GROUP)
	const entry = await admin.post(`${API}/admin/catalogue`, {
		data: {
			name: CASE_SYSTEM,
			description: 'Cases for the front office',
			linkType: 'url',
			linkValue: 'https://zaken.e2e.invalid/',
			targetGroups: [GROUP],
		},
	})
	expect(entry.status(), await entry.text()).toBe(201)
	catalogueUuid = (await entry.json()).app.uuid
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
	if (admin) {
		if (catalogueUuid) {
			await admin.delete(`${API}/admin/catalogue/${catalogueUuid}`)
		}
		await removeGroup(admin, GROUP)
		await admin.dispose()
	}
})

test('the assistant endpoints answer signed-in users only, and a proposal needs an assistant', async ({ baseURL }) => {
	const anonymous = await playwrightRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] }, extraHTTPHeaders: { 'OCS-APIREQUEST': 'true' } })
	try {
		expect((await anonymous.get(`${API}/widget-picker/assistant`)).status()).toBe(401)
	} finally {
		await anonymous.dispose()
	}

	const availability = await own.owner.api.get(`${API}/widget-picker/assistant`)
	expect(availability.status(), await availability.text()).toBe(200)
	const { available } = await availability.json()
	expect(typeof available).toBe('boolean')

	expect((await own.owner.api.post(`${API}/widget-picker/proposal`, { data: { need: '  ', cards: [] } })).status()).toBe(400)
	if (available === false) {
		expect((await own.owner.api.post(`${API}/widget-picker/proposal`, { data: { need: 'notes', cards: [] } })).status()).toBe(503)
	}
})

// @e2e widgets::no-assistant
test('without an assistant the picker shows no assistant line', async ({ browser }) => {
	const { context, picker } = await openPicker(browser, false, null)
	try {
		await expect(picker.getByLabel('Search widgets')).toBeVisible()
		await expect(picker.getByTestId('widget-picker-assistant')).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e widgets::ask-the-assistant
test('asking the assistant proposes Case system and Place it like this opens the tile form with it ticked, which places a one-cell tile', async ({ browser }) => {
	const before = new Set((await readPlacements(own.owner.api, own.id)).map((p) => Number(p.id)))
	const key = `catalogue:${catalogueUuid}`
	const { context, page, picker, sent } = await openPicker(browser, true, { key, name: CASE_SYSTEM, reason: 'You handle cases there.' })
	try {
		const line = picker.getByTestId('widget-picker-assistant')
		await expect(line).toBeVisible()
		// The line sits above the search.
		const lineBox = await line.boundingBox()
		const searchBox = await picker.getByLabel('Search widgets').boundingBox()
		expect(lineBox!.y).toBeLessThan(searchBox!.y)

		await line.getByLabel('Describe what you need').fill('where I handle my cases')
		await line.getByRole('button', { name: 'Ask', exact: true }).click()

		const proposal = picker.getByTestId('widget-picker-assistant-proposal')
		await expect(proposal).toContainText(CASE_SYSTEM)
		await expect(proposal).toContainText('You handle cases there.')
		expect(sent).toHaveLength(1)
		expect(sent[0].need).toBe('where I handle my cases')
		expect(sent[0].cards.map((card: { key: string }) => card.key)).toContain(key)

		await proposal.getByRole('button', { name: 'Place it like this' }).click()
		// One tile type, one form (REQ-APPTILE-004): Place it like this opens
		// the App or link tile form on "An app" with Case system ticked.
		const form = picker.getByTestId('widget-picker-tile-form')
		await expect(form).toBeVisible()
		await expect(form.locator('[data-testid="tile-mode"] input[value="app"]')).toBeChecked()
		const ticked = form.locator('[data-testid^="tile-app-tick-"]:checked')
		await expect(ticked).toHaveCount(1)
		await expect(form.getByTestId('tile-app-row-catalogue').filter({ has: ticked })).toContainText(CASE_SYSTEM)
		await form.getByTestId('tile-editor-save').click()
		await expect(picker).toBeHidden({ timeout: 15_000 })
		// The tile joins the edit-mode draft; the edit bar's Save places it.
		expect(await saveEditDraft(page)).toBeLessThan(300)
		await expect.poll(async () => (await readPlacements(own.owner.api, own.id)).length, { timeout: 15_000 })
			.toBe(before.size + 1)
		const placed = (await readPlacements(own.owner.api, own.id)).find((p) => !before.has(Number(p.id)))!
		expect(String(placed.tileTitle ?? placed.customTitle ?? placed.content?.title ?? '')).toBe(CASE_SYSTEM)
		expect([Number(placed.gridWidth), Number(placed.gridHeight)], 'the tile is not one cell').toEqual([1, 1])
	} finally {
		await context.close()
	}
})

test('an answer that names no offered card says so and places nothing', async ({ browser }) => {
	const before = (await readPlacements(own.owner.api, own.id)).length
	const { context, picker } = await openPicker(browser, true, { key: 'widget:not-offered', reason: 'x' })
	try {
		const line = picker.getByTestId('widget-picker-assistant')
		await line.getByLabel('Describe what you need').fill('something else')
		await line.getByRole('button', { name: 'Ask', exact: true }).click()
		await expect(picker.getByTestId('widget-picker-assistant-message')).toHaveText('No widget fits; choose one below')
		await expect(picker.getByTestId('widget-picker-assistant-proposal')).toHaveCount(0)
		expect((await readPlacements(own.owner.api, own.id)).length).toBe(before)
	} finally {
		await context.close()
	}
})
