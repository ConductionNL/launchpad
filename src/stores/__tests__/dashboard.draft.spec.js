/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard store's edit-mode draft: while it is open no widget or
 * dashboard request is sent, Save sends one, a refused Save keeps the draft,
 * and Cancel brings back what was stored (REQ-EDIT-001, -002, -004).
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */

import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { useDashboardStore } from '../dashboard.js'

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
		importBookmarks: vi.fn(),
	},
}))

/**
 * A store on a personal dashboard with two widgets.
 *
 * @param {string} source The dashboard's source.
 * @return {object} The store.
 */
function storeOn(source = 'user') {
	const store = useDashboardStore()
	store.activeDashboard = { id: 7, uuid: 'd-7', source, gridColumns: 12 }
	store.widgetPlacements = [
		{ id: 1, widgetId: 'text', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2, version: 2 },
		{ id: 2, widgetId: 'clock', gridX: 4, gridY: 0, gridWidth: 2, gridHeight: 2, version: 0 },
	]
	return store
}

describe('dashboard store — edit-mode draft', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('changes only the draft while it is open: move, columns, add, configure, remove', async () => {
		const store = storeOn()
		expect(store.beginDraft()).toBe(true)
		expect(store.draftChangeCount).toBe(0)

		await store.updatePlacements(store.widgetPlacements.map((p) => (p.id === 1 ? { ...p, gridY: 3 } : p)))
		await store.setGridColumns(8)
		const added = await store.addWidgetToDashboard('weather', { x: 0, y: 6, w: 4, h: 3 })
		await store.updateWidgetPlacement(added.id, { customTitle: 'Weer' })
		await store.removeWidgetFromDashboard(2)

		for (const call of Object.values(api)) {
			expect(call).not.toHaveBeenCalled()
		}
		expect(store.draftChangeCount).toBe(4)
		expect(store.isDraftDirty).toBe(true)
		expect(store.activeDashboard.gridColumns).toBe(8)
	})

	// @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
	it('saves the draft in one request and takes the stored placements back', async () => {
		const store = storeOn()
		store.beginDraft()
		await store.updatePlacements(store.widgetPlacements.map((p) => (p.id === 1 ? { ...p, gridY: 3 } : p)))
		api.updateDashboard.mockResolvedValue({ data: { data: { placements: [{ id: 1, gridY: 3, version: 3 }, { id: 2 }] } } })

		const result = await store.saveDraft()

		expect(result.saved).toBe(true)
		expect(api.updateDashboard).toHaveBeenCalledTimes(1)
		expect(api.updateDashboard).toHaveBeenCalledWith(7, {
			draft: true,
			gridColumns: 12,
			placements: [{ id: 1, gridY: 3, version: 2 }, { id: 2 }],
		})
		expect(store.draft).toBeNull()
		expect(store.widgetPlacements[0].version).toBe(3)
	})

	it('keeps the draft when the save is refused or a colleague was first', async () => {
		const store = storeOn()
		store.beginDraft()
		await store.removeWidgetFromDashboard(1)
		api.updateDashboard.mockRejectedValueOnce({ response: { status: 403, data: { message: 'This widget is required' } } })
		expect((await store.saveDraft()).saved).toBe(false)
		expect(store.draft).not.toBeNull()
		expect(store.draftChangeCount).toBe(1)

		api.updateDashboard.mockRejectedValueOnce({ response: { status: 409, data: { placements: [{ id: 2 }] } } })
		const result = await store.saveDraft()
		expect(result).toEqual({ saved: false, status: 409, conflicts: [{ id: 2 }] })
		expect(store.draft).not.toBeNull()
	})

	// @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-cancel-discards-the-draft-req-edit-004
	it('discards the draft back to what was stored, sending nothing', async () => {
		const store = storeOn()
		store.beginDraft()
		await store.removeWidgetFromDashboard(1)
		await store.setGridColumns(6)
		store.discardDraft()
		expect(store.widgetPlacements.map((p) => p.id)).toEqual([1, 2])
		expect(store.activeDashboard.gridColumns).toBe(12)
		expect(store.draft).toBeNull()
		expect(api.removeWidget).not.toHaveBeenCalled()
	})

	it('adds a tile to the draft with the tile fields a stored tile carries', async () => {
		const store = storeOn()
		store.beginDraft()
		const tile = await store.addTileToDashboard({ title: 'Word', icon: 'word.svg', iconType: 'url', linkType: 'url', linkValue: 'https://office.com' })
		expect(api.addTile).not.toHaveBeenCalled()
		expect(tile).toMatchObject({ widgetId: 'tile', tileType: 'custom', tileTitle: 'Word', tileIcon: 'word.svg', tileLinkType: 'url', tileLinkValue: 'https://office.com' })
		expect(store.draftChangeCount).toBe(1)
	})

	it('opens no draft on a group dashboard, which still saves each change', async () => {
		const store = storeOn('group')
		expect(store.beginDraft()).toBe(false)
		api.updateGroupDashboard.mockResolvedValue({})
		await store.setGridColumns(8)
		expect(api.updateGroupDashboard).toHaveBeenCalledTimes(1)
	})
})

// @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
describe('dashboard store — visibility rules in the draft', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('counts a widget whose rules changed and sends its rules with Save', async () => {
		const store = storeOn()
		store.beginDraft()
		store.setDraftRules(1, [{ id: null, ruleType: 'group', ruleConfig: { groups: ['hr'] }, isInclude: true }])
		expect(store.draftChangeCount).toBe(1)
		expect(store.isDraftDirty).toBe(true)

		api.updateDashboard.mockResolvedValue({ data: { data: { placements: store.widgetPlacements } } })
		await store.saveDraft()
		const body = api.updateDashboard.mock.calls[0][1]
		expect(body.placements.find((p) => p.id === 1).rules).toEqual([{ ruleType: 'group', ruleConfig: { groups: ['hr'] }, isInclude: true }])
		expect(body.placements.find((p) => p.id === 2).rules).toBeUndefined()
		expect(store.draft).toBeNull()
	})

	it('keeps no rules outside a draft', () => {
		const store = storeOn()
		store.setDraftRules(1, [])
		expect(store.draft).toBeNull()
		expect(store.draftChangeCount).toBe(0)
	})
})

// @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
describe('dashboard store — bookmark import in the draft', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.clearAllMocks()
	})

	it('asks only for the layout below the draft and adds it to the draft', async () => {
		const store = storeOn()
		store.beginDraft()
		api.importBookmarks.mockResolvedValue({ data: {
			placements: [
				{ widgetId: 'container', gridX: 0, gridY: 2, gridWidth: 4, gridHeight: 3, content: { title: 'Werk', placements: [] } },
				{ widgetId: 'tile', gridX: 0, gridY: 5, gridWidth: 2, gridHeight: 2, tileTitle: 'Mail', tileLinkType: 'url', tileLinkValue: 'https://mail.example' },
			],
			skipped: [{ title: 'Run' }],
		} })

		const result = await store.importBookmarks({ folders: [], bookmarks: [] })

		expect(api.importBookmarks).toHaveBeenCalledWith(7, { folders: [], bookmarks: [], draft: true, bottom: 2 })
		expect(result.placements.map((p) => p.id)).toEqual([expect.stringMatching(/^draft-/), expect.stringMatching(/^draft-/)])
		expect(result.skipped).toEqual([{ title: 'Run' }])
		expect(store.widgetPlacements).toHaveLength(4)
		expect(store.draftChangeCount).toBe(2)
	})

	it('creates at once without a draft', async () => {
		const store = storeOn('group')
		api.importBookmarks.mockResolvedValue({ data: { placements: [{ id: 30, widgetId: 'container' }], skipped: [] } })

		await store.importBookmarks({ folders: [], bookmarks: [] })

		expect(api.importBookmarks).toHaveBeenCalledWith(7, { folders: [], bookmarks: [] })
		expect(store.widgetPlacements.map((p) => p.id)).toEqual([1, 2, 30])
	})
})
