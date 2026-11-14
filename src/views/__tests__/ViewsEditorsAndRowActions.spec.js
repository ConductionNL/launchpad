/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the editor and row-action methods of `Views.vue`:
 * opening the config, share and create modals, the style editor round trip,
 * the tile editor (edit pre-fill, close, delete), the custom-widget modal,
 * server-app discovery, the upload and calendar transports, the row-cog
 * actions that switch first and then act, the sidebar create and delete
 * dialog, and publishing the active dashboard.
 *
 * The methods run on a plain host object, as the sibling Views specs do, so
 * the grid, stores and modals are not mounted. Only the HTTP layer, the
 * resource uploads and the logger are doubled; the dashboard store is the
 * real Pinia store with its two publish actions spied.
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { uploadDataUrl, uploadFile } from '../../services/resourceService.js'
import { logger } from '../../utils/logger.js'

vi.mock('../../services/api.js', () => ({
	api: {
		discoverApps: vi.fn(),
		getCalendarWidgetCalendars: vi.fn(),
	},
}))
vi.mock('../../services/resourceService.js', () => ({
	uploadDataUrl: vi.fn(),
	uploadFile: vi.fn(),
}))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views
let useDashboardStore

/**
 * A host carrying the given state, with every Views method bound to it
 * unless the state overrides it.
 *
 * @param {object} state Fields and overrides
 * @return {object} The host
 */
function makeHost(state = {}) {
	const host = {
		activeDashboard: null,
		widgetPlacements: [],
		permissionLevel: 'full',
		isConfigModalOpen: false,
		configModalMode: null,
		configModalInitialTab: null,
		configSaveError: 'stale',
		isStyleEditorOpen: false,
		editingPlacement: null,
		styleEditorWidget: null,
		isTileEditorOpen: false,
		editingTile: null,
		isCustomWidgetModalOpen: false,
		customWidgetPreselectedType: 'x',
		customWidgetEditing: 'x',
		discoveredApps: ['stale'],
		deleteTarget: null,
		deleteChildCount: 3,
		dashboards: [],
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
	Views = (await import('../Views.vue')).default;
	({ useDashboardStore } = await import('../../stores/dashboard.js'))
})

describe('Views config modal openers', () => {
	it('opens the config modal for the active dashboard on its general tab', () => {
		const host = makeHost()
		host.openConfigModal()
		expect(host).toMatchObject({ configModalMode: 'edit', configModalInitialTab: 'general', isConfigModalOpen: true })
	})

	it('opens the same modal on the sharing tab for the share action', () => {
		const host = makeHost()
		host.openShareDrawer()
		expect(host).toMatchObject({ configModalMode: 'edit', configModalInitialTab: 'sharing', isConfigModalOpen: true })
	})

	it('opens it in create mode, also through handleCreateDashboard', () => {
		const host = makeHost({ configModalInitialTab: 'sharing' })
		host.handleCreateDashboard()
		expect(host).toMatchObject({ configModalMode: 'create', isConfigModalOpen: true })
		expect(host.configModalInitialTab).toBe('sharing')
	})
})

describe('Views style editor', () => {
	it('adds a widget through the store action', async () => {
		const addWidgetToDashboard = vi.fn().mockResolvedValue(undefined)
		await makeHost({ addWidgetToDashboard }).addWidget('notes')
		expect(addWidgetToDashboard).toHaveBeenCalledWith('notes')
	})

	it('opens on a deep clone, so editing it leaves the live placement alone', () => {
		const placement = { id: 4, customTitle: 'Nieuws', styleConfig: { color: 'red' } }
		const host = makeHost()

		host.openStyleEditor(placement)
		host.styleEditorWidget.styleConfig.color = 'blue'

		expect(host.isStyleEditorOpen).toBe(true)
		expect(host.editingPlacement).toBe(placement)
		expect(host.styleEditorWidget).not.toBe(placement)
		expect(placement.styleConfig.color).toBe('red')
	})

	it('persists only the chrome and style of the saved clone, then closes', async () => {
		const updateWidgetPlacement = vi.fn().mockResolvedValue(undefined)
		const host = makeHost({ updateWidgetPlacement, editingPlacement: { id: 4 }, isStyleEditorOpen: true, styleEditorWidget: {} })

		await host.onStyleSaved({
			id: 999,
			widgetId: 'other',
			showTitle: false,
			customTitle: 'Agenda',
			customIcon: 'mdi-calendar',
			styleConfig: { backgroundColor: '#fff' },
		})

		expect(updateWidgetPlacement).toHaveBeenCalledWith(4, {
			showTitle: false,
			customTitle: 'Agenda',
			customIcon: 'mdi-calendar',
			styleConfig: { backgroundColor: '#fff' },
		})
		expect(host).toMatchObject({ isStyleEditorOpen: false, editingPlacement: null, styleEditorWidget: null })
	})

	it('saves nothing and stays open without a placement being edited', async () => {
		const updateWidgetPlacement = vi.fn()
		const host = makeHost({ updateWidgetPlacement, isStyleEditorOpen: true })

		await host.onStyleSaved({ showTitle: true })

		expect(updateWidgetPlacement).not.toHaveBeenCalled()
		expect(host.isStyleEditorOpen).toBe(true)
	})

	it('deletes the edited placement and closes, and does nothing without one', async () => {
		const removeWidget = vi.fn().mockResolvedValue(undefined)
		const host = makeHost({ removeWidget, editingPlacement: { id: 8 }, isStyleEditorOpen: true })

		await host.deleteWidget()
		expect(removeWidget).toHaveBeenCalledWith(8)
		expect(host.isStyleEditorOpen).toBe(false)

		removeWidget.mockClear()
		const idle = makeHost({ removeWidget, isStyleEditorOpen: true })
		await idle.deleteWidget()
		expect(removeWidget).not.toHaveBeenCalled()
		expect(idle.isStyleEditorOpen).toBe(true)
	})
})

describe('Views tile editor', () => {
	it('pre-fills the editor from a tile placement, health-ping config included', () => {
		const openTileEditor = vi.fn()
		makeHost({ openTileEditor }).openTileEditorForEdit({
			id: 12,
			tileTitle: 'Status',
			tileIcon: 'mdi-pulse',
			tileIconType: 'mdi',
			tileBackgroundColor: '#000',
			tileTextColor: '#fff',
			tileLinkType: 'url',
			tileLinkValue: 'https://status.example.nl',
			content: { healthPingEnabled: true, healthUrl: 'https://status.example.nl/health', expectedStatus: 204, pingInterval: 30 },
		})

		expect(openTileEditor).toHaveBeenCalledWith({
			id: 12,
			title: 'Status',
			icon: 'mdi-pulse',
			iconType: 'mdi',
			backgroundColor: '#000',
			textColor: '#fff',
			linkType: 'url',
			linkValue: 'https://status.example.nl',
			internalUrl: '',
			healthPingEnabled: true,
			healthUrl: 'https://status.example.nl/health',
			expectedStatus: 204,
			pingInterval: 30,
		})
	})

	it('defaults the health-ping fields for a tile without content', () => {
		const openTileEditor = vi.fn()
		makeHost({ openTileEditor }).openTileEditorForEdit({ id: 3, content: { healthPingEnabled: 'yes' } })

		expect(openTileEditor.mock.calls[0][0]).toMatchObject({
			id: 3,
			healthPingEnabled: false,
			healthUrl: '',
			expectedStatus: 200,
			pingInterval: 60,
		})

		openTileEditor.mockClear()
		makeHost({ openTileEditor }).openTileEditorForEdit({ id: 5 })
		expect(openTileEditor.mock.calls[0][0]).toMatchObject({ id: 5, healthPingEnabled: false, pingInterval: 60 })
	})

	it('closes and forgets the tile being edited', () => {
		const host = makeHost({ isTileEditorOpen: true, editingTile: { id: 1 } })
		host.closeTileEditor()
		expect(host).toMatchObject({ isTileEditorOpen: false, editingTile: null })
	})

	it('deletes the edited tile and closes, and does nothing for a new tile', async () => {
		const removeWidget = vi.fn().mockResolvedValue(undefined)
		const host = makeHost({ removeWidget, isTileEditorOpen: true, editingTile: { id: 21 } })

		await host.deleteTile()
		expect(removeWidget).toHaveBeenCalledWith(21)
		expect(host.isTileEditorOpen).toBe(false)

		removeWidget.mockClear()
		const fresh = makeHost({ removeWidget, isTileEditorOpen: true, editingTile: null })
		await fresh.deleteTile()
		expect(removeWidget).not.toHaveBeenCalled()
		expect(fresh.isTileEditorOpen).toBe(true)
	})
})

describe('Views custom widget modal', () => {
	it('opens in create mode with the preselected type once edit mode is entered', async () => {
		const host = makeHost({ enterEditMode: vi.fn().mockResolvedValue(true) })
		await host.openCustomWidgetModal('label')
		expect(host).toMatchObject({ isCustomWidgetModalOpen: true, customWidgetPreselectedType: 'label', customWidgetEditing: null })

		const picker = makeHost({ enterEditMode: vi.fn().mockResolvedValue(true) })
		await picker.openCustomWidgetModal()
		expect(picker.customWidgetPreselectedType).toBeNull()
	})

	it('stays closed when edit mode is refused', async () => {
		const host = makeHost({ enterEditMode: vi.fn().mockResolvedValue(false) })
		await host.openCustomWidgetModal('label')
		expect(host.isCustomWidgetModalOpen).toBe(false)
		expect(host.customWidgetPreselectedType).toBe('x')
	})

	it('opens in edit mode on an existing placement, and closes clean', () => {
		const placement = { id: 2, type: 'label' }
		const host = makeHost()

		host.openCustomWidgetEdit(placement)
		expect(host).toMatchObject({ isCustomWidgetModalOpen: true, customWidgetEditing: placement, customWidgetPreselectedType: null })

		host.closeCustomWidgetModal()
		expect(host).toMatchObject({ isCustomWidgetModalOpen: false, customWidgetEditing: null, customWidgetPreselectedType: null })
	})
})

describe('Views server-app discovery', () => {
	it('keeps the discovered apps the server answers', async () => {
		const apps = [{ appId: 'files', name: 'Files', href: '/apps/files/', icon: '/f.svg' }]
		api.discoverApps.mockResolvedValue({ data: { apps } })
		const host = makeHost()

		await host.loadDiscoveredApps()

		expect(host.discoveredApps).toEqual(apps)
	})

	it('reads a malformed answer as no apps', async () => {
		api.discoverApps.mockResolvedValue({ data: { apps: 'files' } })
		const host = makeHost()

		await host.loadDiscoveredApps()

		expect(host.discoveredApps).toEqual([])
		expect(logger.warn).not.toHaveBeenCalled()
	})

	it('leaves the section empty and warns when discovery fails', async () => {
		const error = new Error('offline')
		api.discoverApps.mockRejectedValue(error)
		const host = makeHost()

		await host.loadDiscoveredApps()

		expect(host.discoveredApps).toEqual([])
		expect(logger.warn).toHaveBeenCalledWith('[Views] Could not discover the server apps:', error)
	})

	it('places a discovered app as a tile, logging a failure instead of throwing', async () => {
		const addTileToDashboard = vi.fn().mockResolvedValue({ id: 1 })
		const host = makeHost({ addTileToDashboard })

		await host.addDiscoveredApp({ appId: 'deck', name: 'Deck', href: 'https://elders.example.nl/', icon: '/deck.svg' })
		expect(addTileToDashboard).toHaveBeenCalledWith({
			title: 'Deck',
			icon: '/deck.svg',
			iconType: 'url',
			linkType: 'url',
			linkValue: 'https://elders.example.nl/',
		})

		const error = new Error('quota')
		addTileToDashboard.mockRejectedValueOnce(error)
		await expect(host.addDiscoveredApp({ appId: 'deck', name: 'Deck', href: '/x', icon: '' })).resolves.toBeUndefined()
		expect(logger.error).toHaveBeenCalledWith('[Views] Failed to place a discovered app:', error)
	})
})

describe('Views upload and calendar transports', () => {
	it('uploads an icon as a data URL and answers its address', async () => {
		uploadDataUrl.mockResolvedValue('/resources/icon.png')
		const file = new File(['x'], 'icon.png')

		await expect(makeHost().iconUploadFn(file)).resolves.toBe('/resources/icon.png')
		expect(uploadDataUrl).toHaveBeenCalledWith(file)
	})

	it('uploads an image file and answers only its url', async () => {
		uploadFile.mockResolvedValue({ url: '/resources/foto.jpg', id: 9, size: 100 })
		const file = new File(['x'], 'foto.jpg')

		await expect(makeHost().imageFileUpload(file)).resolves.toEqual({ url: '/resources/foto.jpg' })
		expect(uploadFile).toHaveBeenCalledWith(file)
	})

	it('answers the user\'s calendars, or none for an empty answer', async () => {
		const calendars = [{ key: 'personal', name: 'Persoonlijk', color: '#00f' }]
		api.getCalendarWidgetCalendars.mockResolvedValueOnce({ data: { calendars } })
		await expect(makeHost().fetchCalendars()).resolves.toEqual(calendars)

		api.getCalendarWidgetCalendars.mockResolvedValueOnce({ data: {} })
		await expect(makeHost().fetchCalendars()).resolves.toEqual([])

		api.getCalendarWidgetCalendars.mockResolvedValueOnce(undefined)
		await expect(makeHost().fetchCalendars()).resolves.toEqual([])
	})
})

describe('Views row-cog actions', () => {
	it('switches to another row before toggling edit mode', async () => {
		const order = []
		const onSidebarSwitch = vi.fn(async () => order.push('switch'))
		const toggleEditMode = vi.fn(() => order.push('toggle'))
		const host = makeHost({ onSidebarSwitch, toggleEditMode, activeDashboard: { id: 1 } })

		await host.onRowToggleEdit({ id: 2 }, 'group')

		expect(onSidebarSwitch).toHaveBeenCalledWith(2, 'group')
		expect(order).toEqual(['switch', 'toggle'])
	})

	it('does not switch when the row is already active', async () => {
		const onSidebarSwitch = vi.fn()
		const host = makeHost({ onSidebarSwitch, activeDashboard: { id: 2 } })

		await host.onRowOpenConfig({ id: 2 }, 'user')

		expect(onSidebarSwitch).not.toHaveBeenCalled()
		expect(host).toMatchObject({ isConfigModalOpen: true, configModalInitialTab: 'general' })
	})

	it('switches without an active dashboard, then opens the custom widget picker', async () => {
		const onSidebarSwitch = vi.fn().mockResolvedValue(undefined)
		const openCustomWidgetModal = vi.fn()
		const host = makeHost({ onSidebarSwitch, openCustomWidgetModal })

		await host.onRowAddCustomWidget({ id: 7 }, 'default')

		expect(onSidebarSwitch).toHaveBeenCalledWith(7, 'default')
		expect(openCustomWidgetModal).toHaveBeenCalledWith()
	})
})

describe('Views sidebar create and delete dialog', () => {
	it('forks the active dashboard as a personal copy', async () => {
		const forkDashboard = vi.fn().mockResolvedValue({ id: 9 })
		await makeHost({ forkDashboard, activeDashboard: { uuid: 'u-1' } }).onSidebarCreateDashboard()
		expect(forkDashboard).toHaveBeenCalledWith('u-1', null)
	})

	it('forks nothing without an active dashboard, and swallows a refused fork', async () => {
		const forkDashboard = vi.fn().mockRejectedValue(new Error('403'))
		await makeHost({ forkDashboard }).onSidebarCreateDashboard()
		expect(forkDashboard).not.toHaveBeenCalled()

		await expect(makeHost({ forkDashboard, activeDashboard: { uuid: 'u-1' } }).onSidebarCreateDashboard()).resolves.toBeUndefined()
		expect(forkDashboard).toHaveBeenCalledTimes(1)
	})

	it('asks to delete the current dashboard with no child count', async () => {
		const dashboard = { id: 4, name: 'Team' }
		const host = makeHost()

		await host.deleteCurrentDashboard(dashboard)

		expect(host.deleteTarget).toBe(dashboard)
		expect(host.deleteChildCount).toBe(0)
	})

	it('clears the target when the dialog closes, and keeps it while open', () => {
		const host = makeHost({ deleteTarget: { id: 4 }, deleteChildCount: 2 })

		host.onDeleteDialog(true)
		expect(host.deleteTarget).toEqual({ id: 4 })
		expect(host.deleteChildCount).toBe(2)

		host.onDeleteDialog(false)
		expect(host.deleteTarget).toBeNull()
		expect(host.deleteChildCount).toBe(0)
	})
})

describe('Views publishing the active dashboard', () => {
	it('publishes and unpublishes the active dashboard by its uuid', async () => {
		const store = useDashboardStore()
		const publish = vi.spyOn(store, 'publishDashboard').mockResolvedValue(undefined)
		const unpublish = vi.spyOn(store, 'unpublishDashboard').mockResolvedValue(undefined)
		const host = makeHost({ activeDashboard: { id: 1, uuid: 'u-team' } })

		await host.onPublishActive()
		await host.onUnpublishActive()

		expect(publish).toHaveBeenCalledWith('u-team')
		expect(unpublish).toHaveBeenCalledWith('u-team')
	})
})
