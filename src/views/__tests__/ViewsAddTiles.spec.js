/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Views' side of the App or link tile form (app-link-tile-widget
 * REQ-APPTILE-004, replacing the Add tiles dialog): the form opens with its
 * "Choose the app" lists loaded, places the chosen tiles with the form's
 * appearance and hands a bookmarks file to the bookmark import.
 *
 * @spec openspec/changes/archive/2026-11-27-app-link-tile-widget/specs/app-tile-widget/spec.md#requirement-the-tile-form-starts-with-app-or-link-req-apptile-004
 */
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

vi.mock('../../services/api.js', () => ({
	api: {
		catalogueApps: vi.fn(),
		discoverApps: vi.fn(),
		officeTilePack: vi.fn(),
		foundServices: vi.fn(),
	},
}))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))

let Views

function makeHost(state = {}) {
	const host = {
		widgetPlacements: [],
		discoveredApps: [],
		catalogueApps: [],
		officeTiles: [],
		isTileEditorOpen: false,
		editingTile: null,
		editingGroupTile: null,
		isBookmarkImportOpen: false,
		bookmarkInitialFile: null,
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
	Views = (await import('../Views.vue')).default
})

describe('Views Add tiles', () => {
	it('opens the dialog with the catalogue, the found apps and the office pack loaded', async () => {
		const office = [{ title: 'Word', linkType: 'create', linkValue: 'doc' }]
		api.discoverApps.mockResolvedValue({ data: { apps: [{ appId: 'grafana' }] } })
		api.catalogueApps.mockResolvedValue({ data: { apps: [{ name: 'Wiki' }] } })
		api.officeTilePack.mockResolvedValue({ data: { tiles: office } })
		const host = makeHost({ enterEditMode: vi.fn().mockResolvedValue(true) })

		await host.openAddTilesModal()

		expect(host.isTileEditorOpen).toBe(true)
		expect(host.editingTile).toBeNull()
		expect(host.discoveredApps).toEqual([{ appId: 'grafana' }])
		expect(host.catalogueApps).toEqual([{ name: 'Wiki' }])
		expect(host.officeTiles).toEqual(office)
	})

	it('leaves the Office section empty when the pack does not load', async () => {
		const error = new Error('down')
		api.officeTilePack.mockRejectedValue(error)
		const host = makeHost({ officeTiles: ['stale'] })

		await host.loadOfficeTiles()

		expect(host.officeTiles).toEqual([])
		expect(logger.warn).toHaveBeenCalledWith('[Views] Could not load the office tiles:', error)
	})

	it('opens nothing when edit mode cannot be entered', async () => {
		const host = makeHost({ enterEditMode: vi.fn().mockResolvedValue(false) })
		await host.openAddTilesModal()
		expect(host.isTileEditorOpen).toBe(false)
		expect(api.catalogueApps).not.toHaveBeenCalled()
	})

	it('places an app as an App or link tile in app mode and the rest as link tiles, in order', async () => {
		const calls = []
		const addWidgetToDashboard = vi.fn(async (payload) => calls.push(['widget', payload]))
		const addTileToDashboard = vi.fn(async (tile) => {
			calls.push(['tile', tile])
			return { id: 9 }
		})
		const updateWidgetPlacement = vi.fn(async (id, patch) => calls.push(['patch', id, patch]))
		const host = makeHost({ addWidgetToDashboard, addTileToDashboard, updateWidgetPlacement })
		const word = { title: 'Word', linkType: 'create', linkValue: 'doc' }

		await host.addChosenTiles([
			{ key: 'app:grafana', kind: 'app', tile: { appId: 'grafana' } },
			{ key: 'create:doc', kind: 'tile', tile: word },
		], { colourToken: '--sc-accent', textColour: 'black', showCount: false })

		expect(calls).toEqual([
			['widget', { type: 'tile', content: { mode: 'app', appId: 'grafana', colourToken: '--sc-accent', textColour: 'black', showCount: false, pinned: false } }],
			['tile', word],
			['patch', 9, { content: { mode: 'link', textColour: 'black', colourToken: '--sc-accent' } }],
		])
	})

	it('closes the form after adding the apps, and says so when adding fails', async () => {
		const addWidgetToDashboard = vi.fn().mockResolvedValue({})
		const host = makeHost({ addWidgetToDashboard, isTileEditorOpen: true })
		await host.addAppsFromForm({ choices: [{ kind: 'app', tile: { appId: 'files' } }], appearance: {} })
		expect(addWidgetToDashboard).toHaveBeenCalledTimes(1)
		expect(host.isTileEditorOpen).toBe(false)

		const failing = makeHost({ addWidgetToDashboard: vi.fn().mockRejectedValue(new Error('409')), isTileEditorOpen: true })
		await failing.addAppsFromForm({ choices: [{ kind: 'app', tile: { appId: 'files' } }] })
		expect(failing.isTileEditorOpen).toBe(true)
		expect(logger.error).toHaveBeenCalled()
	})

	it('stops and rejects when a placement fails, so the dialog stays open', async () => {
		const host = makeHost({ addTileToDashboard: vi.fn().mockRejectedValue(new Error('409')) })
		await expect(host.addChosenTiles([{ kind: 'tile', tile: { title: 'x' } }])).rejects.toThrow('409')
	})

	it('hands a bookmarks file to the bookmark import', () => {
		const host = makeHost({ isTileEditorOpen: true })
		const file = { name: 'bookmarks.html' }

		host.onAddTilesImportBookmarks(file)

		expect(host.isTileEditorOpen).toBe(false)
		expect(host.bookmarkInitialFile).toBe(file)
		expect(host.isBookmarkImportOpen).toBe(true)
	})

	// add-widget-dialog-with-miniatures REQ-WDG-040: the form inside Add widget.
	it('loads the form\'s lists when it opens inside Add widget', async () => {
		api.discoverApps.mockResolvedValue({ data: { apps: [{ appId: 'files' }] } })
		api.catalogueApps.mockResolvedValue({ data: { apps: [] } })
		api.officeTilePack.mockResolvedValue({ data: { tiles: [] } })
		const host = makeHost()
		await host.loadTileFormLists()
		expect(host.discoveredApps).toEqual([{ appId: 'files' }])
	})

	it('places a link tile saved inside Add widget and closes Add widget', async () => {
		const saveTile = vi.fn().mockResolvedValue(undefined)
		const host = makeHost({ saveTile, isWidgetModalOpen: true, editingTile: { id: 3 } })
		await host.saveTileFromPicker({ title: 'Intranet' })
		expect(host.editingTile).toBeNull()
		expect(saveTile).toHaveBeenCalledWith({ title: 'Intranet' })
		expect(host.isWidgetModalOpen).toBe(false)
	})

	it('closes Add widget after the apps are placed, and keeps it open when they are not', async () => {
		const ok = makeHost({ addAppsFromForm: vi.fn().mockResolvedValue(true), isWidgetModalOpen: true })
		await ok.addAppsFromPicker({ choices: [] })
		expect(ok.isWidgetModalOpen).toBe(false)

		const failed = makeHost({ addAppsFromForm: vi.fn().mockResolvedValue(false), isWidgetModalOpen: true })
		await failed.addAppsFromPicker({ choices: [] })
		expect(failed.isWidgetModalOpen).toBe(true)
	})
})
