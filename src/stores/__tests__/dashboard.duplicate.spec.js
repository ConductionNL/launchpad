/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Duplicate in a widget's edit menu (REQ-WDG-050): the copy carries the
 * widget's type, size, content and look, goes below the other widgets and
 * joins the draft; on a dashboard without a draft it is added at once.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { duplicateFields, useDashboardStore } from '../dashboard.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, str) => str,
	translatePlural: (_app, sing, plur, n) => (n === 1 ? sing : plur),
}))
vi.mock('../../services/api.js', () => ({
	api: {
		updateDashboard: vi.fn(),
		updateGroupDashboard: vi.fn(),
		addWidget: vi.fn(),
		addTile: vi.fn(),
		removeWidget: vi.fn(),
		updateWidgetPlacement: vi.fn(),
	},
}))

const text = {
	id: 1,
	widgetId: 'text',
	gridX: 0,
	gridY: 0,
	gridWidth: 4,
	gridHeight: 2,
	customTitle: 'Welkom',
	showTitle: 1,
	content: { text: 'Hallo', format: { bold: true } },
	styleConfig: { backgroundColor: '#fff' },
	calculatedFields: [{ name: 'total' }],
	version: 3,
}

/**
 * A store on a dashboard with a text widget and a clock.
 *
 * @param {string} source The dashboard's source.
 * @return {object} The store.
 */
function storeOn(source = 'user') {
	const store = useDashboardStore()
	store.activeDashboard = { id: 7, uuid: 'd-7', source, gridColumns: 12 }
	store.widgetPlacements = [
		JSON.parse(JSON.stringify(text)),
		{ id: 2, widgetId: 'clock', gridX: 4, gridY: 0, gridWidth: 2, gridHeight: 3, version: 0 },
	]
	return store
}

describe('dashboard store — duplicate a widget', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('copies the fields but not the position or the calculated fields', () => {
		const fields = duplicateFields(text)
		expect(fields).toEqual({
			customTitle: 'Welkom',
			showTitle: 1,
			content: { text: 'Hallo', format: { bold: true } },
			styleConfig: { backgroundColor: '#fff' },
		})
		expect(fields.content).not.toBe(text.content)
		expect(fields.content.format).not.toBe(text.content.format)
	})

	it('adds the copy to the draft below the other widgets, sending nothing', async () => {
		const store = storeOn()
		store.beginDraft()
		const copy = await store.duplicatePlacement(1)

		expect(copy.id).toMatch(/^draft-/)
		expect(copy).toMatchObject({ widgetId: 'text', customTitle: 'Welkom', gridX: 0, gridY: 3, gridWidth: 4, gridHeight: 2 })
		expect(copy.calculatedFields).toBeUndefined()
		expect(store.widgetPlacements).toHaveLength(3)
		expect(store.draftChangeCount).toBe(1)
		for (const call of Object.values(api)) {
			expect(call).not.toHaveBeenCalled()
		}

		copy.content.text = 'Anders'
		expect(store.widgetPlacements[0].content.text).toBe('Hallo')
	})

	it('adds the copy at once without a draft and gives it its fields', async () => {
		const store = storeOn('group')
		expect(store.beginDraft()).toBe(false)
		api.addWidget.mockResolvedValue({ data: { id: 9, widgetId: 'text', gridX: 0, gridY: 3, gridWidth: 4, gridHeight: 2 } })
		api.updateWidgetPlacement.mockResolvedValue({ data: { id: 9, widgetId: 'text', customTitle: 'Welkom' } })

		const copy = await store.duplicatePlacement(1)

		expect(api.addWidget).toHaveBeenCalledWith(7, {
			widgetId: 'text',
			gridX: 0,
			gridY: 3,
			gridWidth: 4,
			gridHeight: 2,
			content: { text: 'Hallo', format: { bold: true } },
		})
		expect(api.updateWidgetPlacement).toHaveBeenCalledWith(9, {
			customTitle: 'Welkom',
			showTitle: 1,
			styleConfig: { backgroundColor: '#fff' },
		})
		expect(copy).toEqual({ id: 9, widgetId: 'text', customTitle: 'Welkom' })
		expect(store.widgetPlacements).toHaveLength(3)
	})

	it('says so when the copy cannot be added, and adds nothing', async () => {
		const store = storeOn('group')
		api.addWidget.mockRejectedValue(new Error('boom'))
		expect(await store.duplicatePlacement(1)).toBeNull()
		expect(store.widgetPlacements).toHaveLength(2)
	})

	it('copies nothing for an unknown widget', async () => {
		const store = storeOn()
		store.beginDraft()
		expect(await store.duplicatePlacement(42)).toBeNull()
		expect(store.widgetPlacements).toHaveLength(2)
	})
})
