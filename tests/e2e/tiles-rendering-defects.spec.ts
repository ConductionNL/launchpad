/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Two tiles scenarios (openspec/specs/tiles/spec.md) that were cited only by
 * `test.fixme` in tiles-rendering.spec.ts. Gate-19 counted them through that
 * file's citation list, but no test of theirs ever executed. They now run.
 *
 * They sit in their own file, without serial mode, because
 * tiles-rendering.spec.ts runs serially: a red there would skip every test
 * after it. The setup is the same: a throwaway account owns one dashboard
 * with two tiles placed through `POST /api/dashboard/{id}/tile`.
 *
 * Decision (25 Nov 2026): both scenarios now follow the shipped tile chrome
 * and the tile form drawn on the board (REQ-TILE-040):
 *   - edit-button-visible-in-edit-mode: the top-right control is the shared
 *     CnWidgetEditCog, `aria-label="Tile menu"`; "Edit tile" is the item in
 *     the menu it opens (src/components/TileWidget.vue).
 *   - tile-editor: the form has Name, Icon, the background and text colours,
 *     the tile type and its address; `iconType` follows from the chosen icon
 *     (src/modals/TileEditor.vue). The save goes to
 *     `PUT /api/widgets/{placementId}`.
 *
 * Gate-19 @e2e traceability:
 *   @e2e tiles::edit-button-visible-in-edit-mode
 *   @e2e tiles::tile-editor
 *
 * @spec openspec/specs/tiles/spec.md
 */

import type { Browser, Locator, Page } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	openOwnDashboard,
	readPlacements,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'
import { API } from './support/principals.ts'

const STAMP = `${Date.now()}`
const EMOJI = `Bestanden ${STAMP}`
const CLASS = `Map ${STAMP}`

let own: OwnDashboard
const ids: Record<string, number> = {}

test.setTimeout(120_000)

test.beforeAll(async () => {
	own = await setUpOwnDashboard(
		'e2e-tile-render-red',
		`E2E tile rendering defects ${STAMP}`,
	)
	const tiles: Array<[string, Record<string, unknown>]> = [
		[
			EMOJI,
			{
				icon: '📁',
				iconType: 'emoji',
				bgColor: '#3b82f6',
				textColor: '#ffffff',
				linkType: 'app',
				linkValue: 'files',
				gridX: 0,
			},
		],
		[
			CLASS,
			{
				icon: 'icon-folder',
				iconType: 'class',
				bgColor: '#0f766e',
				textColor: '#ffffff',
				linkType: 'app',
				linkValue: 'files',
				gridX: 2,
			},
		],
	]
	for (const [title, data] of tiles) {
		const res = await own.owner.api.post(`${API}/dashboard/${own.id}/tile`, {
			data: { title, gridY: 0, ...data },
		})
		expect(res.status(), await res.text()).toBe(201)
		ids[title] = Number((await res.json()).id)
	}
})

test.afterAll(async () => {
	await tearDownOwnDashboard(own)
})

/**
 * The tile of a placement.
 *
 * @param page The page.
 * @param title The tile title.
 * @return The `.tile-widget` element.
 */
function tile(page: Page, title: string): Locator {
	return page.locator(`.grid-stack-item[gs-id="${ids[title]}"] .tile-widget`)
}

/**
 * Open the dashboard as its owner.
 *
 * @param browser The browser.
 * @return The context and page.
 */
async function open(browser: Browser) {
	const session = await openOwnDashboard(browser, own, Object.keys(ids).length)
	await expect(tile(session.page, EMOJI)).toBeVisible()
	return session
}

// @e2e tiles::edit-button-visible-in-edit-mode
test('in edit mode the tile menu button sits at the top-right of the tile and offers Edit tile', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		await enterEditMode(page)
		const t = tile(page, EMOJI)
		const button = t.getByRole('button', { name: 'Tile menu', exact: true })
		await expect(button).toBeVisible()
		await expect(button).toHaveAttribute('aria-label', 'Tile menu')
		const tileBox = await t.boundingBox()
		const box = await button.boundingBox()
		expect(
			tileBox!.x + tileBox!.width - (box!.x + box!.width),
		).toBeLessThanOrEqual(16)
		expect(box!.y - tileBox!.y).toBeLessThanOrEqual(16)
		await button.click()
		await expect(page.getByRole('menuitem', { name: 'Edit tile' })).toBeVisible()
	} finally {
		await context.close()
	}
})

// @e2e tiles::tile-editor
test('the tile editor has name, icon, colour, tile type and address fields and saves through the API', async ({
	browser,
}) => {
	const { context, page } = await open(browser)
	try {
		await enterEditMode(page)
		await tile(page, CLASS).getByRole('button', { name: 'Tile menu' }).click()
		await page.getByRole('menuitem', { name: 'Edit tile' }).click()
		const editor = page.getByRole('dialog', { name: 'Edit Tile' })
		await expect(editor.getByRole('textbox', { name: 'Name', exact: true })).toHaveValue(CLASS)
		await expect(editor.getByText('Icon', { exact: true }).first()).toBeVisible()
		await expect(editor.getByRole('button', { name: 'Choose an icon' })).toBeVisible()
		await expect(editor.getByRole('button', { name: 'Pick background color' })).toBeVisible()
		// The App or link tile form (app-link-tile-widget, board LpTegelBewerken):
		// the text colour is a swatch group, and the choice of what the tile
		// opens replaced the Tile type radios.
		await expect(editor.getByRole('radiogroup', { name: 'Text colour' })).toBeVisible()
		await expect(editor.getByRole('radiogroup', { name: 'What does the tile open?' })).toBeVisible()
		await expect(editor.getByRole('textbox', { name: 'Web address' })).toBeVisible()

		const renamed = `${CLASS} hernoemd`
		await editor.getByRole('textbox', { name: 'Name', exact: true }).fill(renamed)
		const saved = page.waitForResponse(
			(res) =>
				res.request().method() === 'PUT'
				&& res.url().includes(`/api/widgets/${ids[CLASS]}`),
		)
		await editor.getByRole('button', { name: 'Save' }).click()
		expect((await saved).status()).toBe(200)
		const stored = (await readPlacements(own.owner.api, own.id)).find(
			(p) => Number(p.id) === ids[CLASS],
		)!
		expect(stored.tileTitle).toBe(renamed)
	} finally {
		await context.close()
	}
})
