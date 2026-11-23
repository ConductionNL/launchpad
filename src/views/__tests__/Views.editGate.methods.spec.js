/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page's edit-mode gate (already editing, no dashboard,
 * editing accepted or refused) and what it guards: the widget picker and the
 * tile editor. Also the read-confirmation save without a placement, and
 * the dashboard config save (create, edit with tree fields, refusals the
 * form explains). Run against the real Views methods on a stand-in host;
 * only HTTP, stores and the logger are doubled.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

vi.mock('../../services/api.js', () => ({
	api: {
		updateDashboard: vi.fn(),
	},
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))

let Views

/**
 * A host carrying the real Views methods.
 *
 * @param {object} state Data and overrides.
 * @return {object} the host.
 */
function makeHost(state = {}) {
	const host = {
		activeDashboard: null,
		isEditMode: false,
		presence: { start: vi.fn() },
		isWidgetModalOpen: false,
		isTileEditorOpen: false,
		editingTile: null,
	}
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	return Object.assign(host, state)
}

beforeEach(async () => {
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views edit-mode gate', () => {
	it('is already open in edit mode without announcing the presence again', async () => {
		const host = makeHost({ isEditMode: true, activeDashboard: { uuid: 'd' } })
		expect(await host.enterEditMode()).toBe(true)
		expect(host.presence.start).not.toHaveBeenCalled()
	})

	it('stays closed without an active dashboard', async () => {
		const host = makeHost({ activeDashboard: {} })
		expect(await host.enterEditMode()).toBe(false)
		expect(host.presence.start).not.toHaveBeenCalled()
		expect(host.isEditMode).toBe(false)
	})

	it('opens the widget picker once the presence is accepted and reads the discovered apps', async () => {
		const host = makeHost({ activeDashboard: { id: 12 } })
		host.presence.start.mockResolvedValue(true)
		host.loadDiscoveredApps = vi.fn()

		await host.openWidgetModal()

		expect(host.presence.start).toHaveBeenCalledWith('12')
		expect(host.isEditMode).toBe(true)
		expect(host.isWidgetModalOpen).toBe(true)
		expect(host.loadDiscoveredApps).toHaveBeenCalledTimes(1)
	})

	it('keeps the widget picker closed when editing is refused', async () => {
		const host = makeHost({ activeDashboard: { uuid: 'd' } })
		host.presence.start.mockResolvedValue(false)
		host.loadDiscoveredApps = vi.fn()

		await host.openWidgetModal()

		expect(host.isWidgetModalOpen).toBe(false)
		expect(host.loadDiscoveredApps).not.toHaveBeenCalled()
	})

	it('opens the tile editor on the given tile, or a new one, in edit mode', async () => {
		const tile = { id: 3, title: 'Mail' }
		const host = makeHost({ isEditMode: true, activeDashboard: { uuid: 'd' } })

		await host.openTileEditor(tile)
		expect(host.editingTile).toBe(tile)
		expect(host.isTileEditorOpen).toBe(true)

		host.isTileEditorOpen = false
		await host.openTileEditor()
		expect(host.editingTile).toBeNull()
		expect(host.isTileEditorOpen).toBe(true)
	})

	it('keeps the tile editor closed when editing is refused', async () => {
		const host = makeHost({ activeDashboard: { uuid: 'd' } })
		host.presence.start.mockResolvedValue(false)

		await host.openTileEditor({ id: 3 })

		expect(host.presence.start).toHaveBeenCalledWith('d')
		expect(host.isTileEditorOpen).toBe(false)
		expect(host.editingTile).toBeNull()
	})
})

describe('Views read confirmation save', () => {
	it('does nothing when no placement was being configured', async () => {
		const host = makeHost({ readConfirmationPlacement: null })
		host.updateWidgetPlacement = vi.fn()
		host.onWidgetAcknowledged = vi.fn()

		await host.saveReadConfirmation({ requiresAcknowledgement: true })

		expect(host.updateWidgetPlacement).not.toHaveBeenCalled()
		expect(host.onWidgetAcknowledged).not.toHaveBeenCalled()
	})
})

describe('Views dashboard config save', () => {
	it('creates a dashboard when the form has no id, then closes', async () => {
		const host = makeHost({ configSaveError: 'old' })
		host.createDashboard = vi.fn().mockResolvedValue({})
		host.closeConfigModal = vi.fn()

		await host.saveDashboardConfig({ id: null, name: 'HR', description: 'People', icon: 'mdi' })

		expect(host.createDashboard).toHaveBeenCalledWith({ name: 'HR', description: 'People', icon: 'mdi' })
		expect(api.updateDashboard).not.toHaveBeenCalled()
		expect(host.closeConfigModal).toHaveBeenCalledTimes(1)
		expect(host.configSaveError).toBe('')
	})

	it('updates with the parent and web address name, reloads and closes', async () => {
		api.updateDashboard.mockResolvedValue({ data: {} })
		const host = makeHost({ configSaveError: '' })
		host.loadDashboards = vi.fn().mockResolvedValue()
		host.closeConfigModal = vi.fn()

		await host.saveDashboardConfig({ id: 5, name: 'HR', description: '', icon: 'x', parentUuid: '', slug: 'hr' })

		expect(api.updateDashboard).toHaveBeenCalledWith(5, { name: 'HR', description: '', icon: 'x', parentUuid: '', slug: 'hr' })
		expect(host.loadDashboards).toHaveBeenCalledTimes(1)
		expect(host.closeConfigModal).toHaveBeenCalledTimes(1)
	})

	it('explains a vanished parent, and falls back for an unknown refusal, keeping the form open', async () => {
		const host = makeHost({ configSaveError: '' })
		host.loadDashboards = vi.fn()
		host.closeConfigModal = vi.fn()

		const gone = Object.assign(new Error('404'), { response: { data: { message: 'Parent dashboard not found' } } })
		api.updateDashboard.mockRejectedValue(gone)
		await host.saveDashboardConfig({ id: 5, name: 'HR', description: '', icon: 'x', parentUuid: 'p' })
		expect(api.updateDashboard).toHaveBeenCalledWith(5, { name: 'HR', description: '', icon: 'x', parentUuid: 'p' })
		expect(logger.error).toHaveBeenCalledWith('Failed to save dashboard:', gone)
		expect(host.configSaveError).toBe('The chosen parent dashboard no longer exists.')

		api.updateDashboard.mockRejectedValue(new Error('offline'))
		await host.saveDashboardConfig({ id: 5, name: 'HR', description: '', icon: 'x' })
		expect(host.configSaveError).toBe('The dashboard could not be saved.')
		expect(host.closeConfigModal).not.toHaveBeenCalled()
	})
})
