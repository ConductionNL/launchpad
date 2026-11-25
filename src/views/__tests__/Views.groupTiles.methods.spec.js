/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A tile inside a group edited with the tile form (tile-form-board-sync
 * REQ-TILE-041): the dashboard opens the form with the group's name and sort
 * order, writes the save into the group's children with the group's order in
 * one placement update, and deletes a group tile the same way. Run against the
 * real Views methods and computed values on a stand-in host.
 *
 * @spec openspec/changes/tile-form-board-sync/specs/tiles/spec.md#requirement-a-tiles-group-and-the-groups-order-req-tile-041
 */

import { showError } from '@nextcloud/dialogs'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))

let Views

const bookmark = { type: 'link', gridX: 0, content: { label: 'Intranet', url: 'https://intranet.example.org', actionType: 'external' } }
const caseSystem = { type: 'tile', gridX: 2, content: { title: 'Case system', linkType: 'url', linkValue: 'https://cases.example.org', healthPingEnabled: true } }

/**
 * A host carrying the real Views methods and the group computed values.
 *
 * @param {object} state Data and overrides.
 * @return {object} the host.
 */
function makeHost(state = {}) {
	const host = {
		isEditMode: true,
		activeDashboard: { uuid: 'd' },
		isTileEditorOpen: false,
		editingTile: null,
		editingGroupTile: null,
		widgetPlacements: [
			{ id: 9, widgetId: 'tile', tileType: 'custom' },
			{ id: 41, widgetId: 'container', content: { title: 'Work apps', sortBy: 'manual', placements: [bookmark, caseSystem] } },
		],
		updateWidgetPlacement: vi.fn().mockResolvedValue({}),
		removeWidget: vi.fn(),
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		if (!(name in host)) {
			host[name] = fn.bind(host)
		}
	}
	for (const name of ['editingGroup', 'editingGroupTitle', 'editingGroupSort']) {
		Object.defineProperty(host, name, { get: () => Views.computed[name].call(host) })
	}
	return Object.assign(host, state)
}

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views — a tile in a group', () => {
	it('provides the opener to the groups on the dashboard', async () => {
		const host = makeHost()
		const provided = Views.provide.call(host)
		await provided.openGroupTileEditor({ containerId: 41, index: 1 })
		expect(host.isTileEditorOpen).toBe(true)
	})

	it('opens the form on the tile with the group name and sort order', async () => {
		const host = makeHost()
		await host.openGroupTileEditor({ containerId: 41, index: 1 })
		expect(host.editingGroupTile).toEqual({ containerId: 41, index: 1 })
		expect(host.editingTile.title).toBe('Case system')
		expect(host.editingGroupTitle).toBe('Work apps')
		expect(host.editingGroupSort).toBe('manual')
	})

	it('opens nothing for a child that is not a tile, or a group that is gone', async () => {
		const host = makeHost()
		host.widgetPlacements[1].content.placements.push({ type: 'label', content: {} })
		await host.openGroupTileEditor({ containerId: 41, index: 2 })
		await host.openGroupTileEditor({ containerId: 99, index: 0 })
		expect(host.isTileEditorOpen).toBe(false)
		expect(host.editingGroupTitle).toBe('')
		expect(host.editingGroupSort).toBe('')
	})

	it('saves the tile into the group with the group sort order, in one update, keeping what the form does not edit', async () => {
		const host = makeHost()
		await host.openGroupTileEditor({ containerId: 41, index: 1 })
		await host.saveTile({ ...host.editingTile, title: 'Cases', healthPingEnabled: false, groupSortBy: 'last-used' })

		expect(host.updateWidgetPlacement).toHaveBeenCalledTimes(1)
		const [id, payload] = host.updateWidgetPlacement.mock.calls[0]
		expect(id).toBe(41)
		expect(payload.content.sortBy).toBe('last-used')
		expect(payload.content.title).toBe('Work apps')
		expect(payload.content.placements[0]).toBe(bookmark)
		expect(payload.content.placements[1].content).toMatchObject({ title: 'Cases', healthPingEnabled: true })
		expect(host.isTileEditorOpen).toBe(false)
		expect(host.editingGroupTile).toBeNull()
	})

	it('turns a saved bookmark link into a tile node', async () => {
		const host = makeHost()
		await host.openGroupTileEditor({ containerId: 41, index: 0 })
		await host.saveTile({ ...host.editingTile })
		const child = host.updateWidgetPlacement.mock.calls[0][1].content.placements[0]
		expect(child.type).toBe('tile')
		expect(child.content).toMatchObject({ title: 'Intranet', linkType: 'url', linkValue: 'https://intranet.example.org' })
		expect(child.content.url).toBeUndefined()
	})

	it('deletes a group tile from the form and from the tile menu, never the group', async () => {
		const host = makeHost()
		await host.openGroupTileEditor({ containerId: 41, index: 0 })
		await host.deleteTile()
		expect(host.removeWidget).not.toHaveBeenCalled()
		expect(host.updateWidgetPlacement.mock.calls[0][1].content.placements).toEqual([caseSystem])
		expect(host.isTileEditorOpen).toBe(false)

		await host.openGroupTileEditor({ containerId: 41, index: 1, remove: true })
		expect(host.updateWidgetPlacement.mock.calls[1][1].content.placements).toEqual([bookmark])
		expect(host.isTileEditorOpen).toBe(false)
	})

	it('keeps the form open and says so when the group cannot be saved', async () => {
		const host = makeHost()
		host.updateWidgetPlacement.mockRejectedValue(new Error('500'))
		await host.openGroupTileEditor({ containerId: 41, index: 1 })
		await host.saveTile({ ...host.editingTile })
		expect(showError).toHaveBeenCalledWith('The group could not be saved.')
		expect(host.isTileEditorOpen).toBe(true)
	})

	it('says the tile is gone when its group changed under the form', async () => {
		const host = makeHost()
		await host.openGroupTileEditor({ containerId: 41, index: 1 })
		host.widgetPlacements[1].content = { title: 'Work apps', placements: [bookmark] }
		await host.saveTile({ ...host.editingTile })
		expect(host.updateWidgetPlacement).not.toHaveBeenCalled()
		expect(showError).toHaveBeenCalledWith('This tile is no longer in its group. Reload the dashboard and try again.')
	})

	it('saves a tile on the dashboard itself as before', async () => {
		const host = makeHost()
		await host.openTileEditor({ id: 9, title: 'Mail' })
		await host.saveTile({ title: 'Mail', linkType: 'url', linkValue: '/apps/mail' })
		expect(host.updateWidgetPlacement).toHaveBeenCalledWith(9, expect.objectContaining({ tileTitle: 'Mail' }))
	})
})
