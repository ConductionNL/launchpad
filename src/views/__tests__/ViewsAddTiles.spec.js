/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Views' side of the Add tiles dialog (add-tiles-dialog REQ-TILE-043): it
 * opens with its lists loaded, places the chosen tiles and hands a bookmarks
 * file to the bookmark import.
 *
 * @spec openspec/changes/archive/2026-11-27-add-tiles-dialog/specs/tiles/spec.md
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
		isAddTilesOpen: false,
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
		const host = makeHost()

		await host.openAddTilesModal()

		expect(host.isAddTilesOpen).toBe(true)
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

	it('places a found app as an app tile and the rest as tiles, in order', async () => {
		const calls = []
		const addWidgetToDashboard = vi.fn(async (payload) => calls.push(['widget', payload]))
		const addTileToDashboard = vi.fn(async (tile) => calls.push(['tile', tile]))
		const host = makeHost({ addWidgetToDashboard, addTileToDashboard })
		const word = { title: 'Word', linkType: 'create', linkValue: 'doc' }

		await host.addChosenTiles([
			{ key: 'app:grafana', kind: 'app-tile', tile: { appId: 'grafana' } },
			{ key: 'create:doc', kind: 'tile', tile: word },
		])

		expect(calls).toEqual([
			['widget', { type: 'app-tile', content: { appId: 'grafana', colour: '', showCount: true } }],
			['tile', word],
		])
	})

	it('stops and rejects when a placement fails, so the dialog stays open', async () => {
		const host = makeHost({ addTileToDashboard: vi.fn().mockRejectedValue(new Error('409')) })
		await expect(host.addChosenTiles([{ kind: 'tile', tile: { title: 'x' } }])).rejects.toThrow('409')
	})

	it('hands a bookmarks file to the bookmark import', () => {
		const host = makeHost({ isAddTilesOpen: true })
		const file = { name: 'bookmarks.html' }

		host.onAddTilesImportBookmarks(file)

		expect(host.isAddTilesOpen).toBe(false)
		expect(host.bookmarkInitialFile).toBe(file)
		expect(host.isBookmarkImportOpen).toBe(true)
	})
})
