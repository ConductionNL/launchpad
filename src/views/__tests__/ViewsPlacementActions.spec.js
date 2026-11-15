/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the placement-level methods of `Views.vue`: the render
 * key, widget lookup, tile projection, the widget context menu (edit through
 * the content editor or the style editor, remove, visibility rules, keyboard
 * move) and persisting a keyboard move with the placements it pushed, plus
 * the acknowledgement hooks.
 *
 * The methods run on a plain host object, as the sibling Views specs do, so
 * the grid, stores and modals are not mounted. The widget registry lookup and
 * the logger are doubled.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const logged = []
vi.mock('../../utils/logger.js', () => ({
	logger: { error: (...args) => logged.push(args), warn: vi.fn(), info: vi.fn(), debug: vi.fn() },
}))
const registry = {}
vi.mock('../../constants/widgetRegistry.js', async (importOriginal) => ({
	...(await importOriginal()),
	getWidgetTypeEntry: (type) => registry[type],
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views
let useDashboardStore

/**
 * A host carrying the given state, with every Views method bound to it.
 *
 * @param {object} state Fields the methods read
 * @return {object} The host
 */
function makeHost(state = {}) {
	const host = {
		availableWidgets: [],
		widgetPlacements: [],
		movePanelOpen: false,
		movePanelPlacement: null,
		isVisibilityModalOpen: false,
		visibilityPlacementId: null,
		ackReportOpen: false,
		ackReportKey: null,
		...state,
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		if (!(name in state)) {
			host[name] = fn.bind(host)
		}
	}
	return host
}

beforeEach(async () => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
	logged.length = 0
	for (const key of Object.keys(registry)) {
		delete registry[key]
	}
	Views = (await import('../Views.vue')).default;
	({ useDashboardStore } = await import('../../stores/dashboard.js'))
})

describe('Views placements', () => {
	it('keys a placement by its id as text', () => {
		expect(makeHost().placementItemKey({ id: 42, gridX: 3 })).toBe('42')
	})

	it('finds a registered widget by id, and nothing for an unknown one', () => {
		const host = makeHost({ availableWidgets: [{ id: 'notes', title: 'Notes' }, { id: 'mail' }] })

		expect(host.getWidget('notes')).toEqual({ id: 'notes', title: 'Notes' })
		expect(host.getWidget('calendar')).toBeUndefined()
	})

	it('projects a custom tile placement into tile data, and nothing for other placements', () => {
		const host = makeHost()
		const tile = {
			id: 9,
			tileType: 'custom',
			tileTitle: 'Intranet',
			tileIcon: 'mdi-home',
			tileIconType: 'mdi',
			tileBackgroundColor: '#123456',
			tileTextColor: '#ffffff',
			tileLinkType: 'url',
			tileLinkValue: 'https://intranet.example.nl',
		}

		expect(host.isTilePlacement(tile)).toBe(true)
		expect(host.getTileData(tile)).toEqual({
			id: 9,
			title: 'Intranet',
			icon: 'mdi-home',
			iconType: 'mdi',
			backgroundColor: '#123456',
			textColor: '#ffffff',
			linkType: 'url',
			linkValue: 'https://intranet.example.nl',
		})
		expect(host.isTilePlacement({ id: 1, widgetId: 'notes' })).toBe(false)
		expect(host.getTileData({ id: 1, tileType: 'preset' })).toBeNull()
	})
})

describe('Views widget context menu', () => {
	it('hands a right click to the grid manager', () => {
		const grid = { onWidgetRightClick: vi.fn() }
		const event = new MouseEvent('contextmenu')
		const placement = { id: 1 }

		makeHost({ grid }).onWidgetRightClick(event, placement)

		expect(grid.onWidgetRightClick).toHaveBeenCalledWith(event, placement)
	})

	it('resolves a widget type only for a registered type with a form', () => {
		registry.label = { form: {} }
		registry['nc-widget'] = { form: null }
		const host = makeHost()

		expect(host.resolveWidgetType({ widgetId: 'label' })).toBe('label')
		expect(host.resolveWidgetType({ widgetId: 'nc-widget' })).toBeNull()
		expect(host.resolveWidgetType({ widgetId: 'unknown' })).toBeNull()
		expect(host.resolveWidgetType({ widgetId: '' })).toBeNull()
		expect(host.resolveWidgetType({ widgetId: 7 })).toBeNull()
		expect(host.resolveWidgetType(null)).toBeNull()
	})

	it('edits a registry widget in the content editor with its type set', () => {
		registry.label = { form: {} }
		const openCustomWidgetEdit = vi.fn()
		const openStyleEditor = vi.fn()
		const host = makeHost({ openCustomWidgetEdit, openStyleEditor })

		host.handleContextMenuEdit({ id: 3, widgetId: 'label' })

		expect(openCustomWidgetEdit).toHaveBeenCalledWith({ id: 3, widgetId: 'label', type: 'label' })
		expect(openStyleEditor).not.toHaveBeenCalled()
	})

	it('edits any other widget in the style editor', () => {
		const openCustomWidgetEdit = vi.fn()
		const openStyleEditor = vi.fn()
		const host = makeHost({ openCustomWidgetEdit, openStyleEditor })
		const placement = { id: 4, widgetId: 'recommendations' }

		host.handleContextMenuEdit(placement)

		expect(openStyleEditor).toHaveBeenCalledWith(placement)
		expect(openCustomWidgetEdit).not.toHaveBeenCalled()
	})

	it('removes a placement by id, ignoring one without an id', async () => {
		const removeWidget = vi.fn().mockResolvedValue()
		const host = makeHost({ removeWidget })

		await host.handleContextMenuRemove({})
		await host.handleContextMenuRemove(null)
		expect(removeWidget).not.toHaveBeenCalled()

		await host.handleContextMenuRemove({ id: 5 })
		expect(removeWidget).toHaveBeenCalledWith(5)
		expect(logged).toEqual([])
	})

	it('logs a remove that fails instead of throwing', async () => {
		const failure = new Error('gone')
		const host = makeHost({ removeWidget: vi.fn().mockRejectedValue(failure) })

		await expect(host.handleContextMenuRemove({ id: 5 })).resolves.toBeUndefined()

		expect(logged).toEqual([['[Views] Failed to remove widget via context menu:', failure]])
	})

	it('opens and closes the visibility rules for a placement', () => {
		const host = makeHost()

		host.handleContextMenuVisibilityRules({})
		expect(host.isVisibilityModalOpen).toBe(false)

		host.handleContextMenuVisibilityRules({ id: 6 })
		expect([host.isVisibilityModalOpen, host.visibilityPlacementId]).toEqual([true, 6])

		host.closeVisibilityRules()
		expect([host.isVisibilityModalOpen, host.visibilityPlacementId]).toEqual([false, null])
	})

	it('opens and closes the keyboard move panel for a placement', () => {
		const host = makeHost()
		const placement = { id: 7, gridX: 0 }

		host.handleContextMenuMove(undefined)
		expect(host.movePanelOpen).toBe(false)

		host.handleContextMenuMove(placement)
		expect([host.movePanelOpen, host.movePanelPlacement]).toEqual([true, placement])

		host.closeMovePanel()
		expect([host.movePanelOpen, host.movePanelPlacement]).toEqual([false, null])
	})
})

describe('Views keyboard move', () => {
	const placements = [
		{ id: 1, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2 },
		{ id: 2, gridX: 4, gridY: 0, gridWidth: 4, gridHeight: 2 },
		{ id: 3, gridX: 0, gridY: 2, gridWidth: 4, gridHeight: 2 },
	]

	it('writes the moved rectangle and the pushed placements in one update, and closes the panel', async () => {
		const updatePlacements = vi.fn().mockResolvedValue()
		const host = makeHost({ widgetPlacements: placements, movePanelOpen: true, movePanelPlacement: placements[0], updatePlacements })

		await host.handleMoveSave({ gridX: 2, gridY: 1, gridWidth: 6, gridHeight: 3, pushed: [{ id: 3, gridY: 4 }] })

		expect(updatePlacements).toHaveBeenCalledWith([
			{ id: 1, gridX: 2, gridY: 1, gridWidth: 6, gridHeight: 3 },
			placements[1],
			{ id: 3, gridX: 0, gridY: 4, gridWidth: 4, gridHeight: 2 },
		])
		expect(updatePlacements.mock.calls[0][0][1]).toBe(placements[1])
		expect([host.movePanelOpen, host.movePanelPlacement]).toEqual([false, null])
	})

	it('accepts a move without pushed placements', async () => {
		const updatePlacements = vi.fn().mockResolvedValue()
		const host = makeHost({ widgetPlacements: placements, movePanelPlacement: placements[1], updatePlacements })

		await host.handleMoveSave({ gridX: 8, gridY: 0, gridWidth: 4, gridHeight: 2 })

		expect(updatePlacements.mock.calls[0][0][1]).toEqual({ id: 2, gridX: 8, gridY: 0, gridWidth: 4, gridHeight: 2 })
	})

	it('only closes the panel without a placement or a rectangle', async () => {
		const updatePlacements = vi.fn()
		for (const [placement, rect] of [[null, { gridX: 0 }], [placements[0], null]]) {
			const host = makeHost({ widgetPlacements: placements, movePanelOpen: true, movePanelPlacement: placement, updatePlacements })
			await host.handleMoveSave(rect)
			expect(host.movePanelOpen).toBe(false)
		}

		expect(updatePlacements).not.toHaveBeenCalled()
	})

	it('logs a move that cannot be saved', async () => {
		const failure = new Error('conflict')
		const host = makeHost({ widgetPlacements: placements, movePanelPlacement: placements[0], updatePlacements: vi.fn().mockRejectedValue(failure) })

		await host.handleMoveSave({ gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2 })

		expect(logged).toEqual([['[Views] Failed to persist keyboard move:', failure]])
	})
})

describe('Views acknowledgements', () => {
	it('refreshes the outstanding acknowledgements after one is given', () => {
		const store = useDashboardStore()
		const fetch = vi.spyOn(store, 'fetchPendingAcknowledgements').mockResolvedValue()

		makeHost().onWidgetAcknowledged()

		expect(fetch).toHaveBeenCalledTimes(1)
	})

	it('opens the read-receipt report for an announcement', () => {
		const host = makeHost()

		host.openAcknowledgementReport('welcome-2026')

		expect([host.ackReportOpen, host.ackReportKey]).toEqual([true, 'welcome-2026'])
	})
})
