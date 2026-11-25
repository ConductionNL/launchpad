/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What the workspace hands the widget picker (widget-picker-board-sync): the
 * role's allow-list for the grey cards, and an app tile from the picker
 * placed as one cell. Run against the real Views methods and computed
 * properties on a stand-in host.
 *
 * @spec openspec/changes/widget-picker-board-sync/specs/widgets/spec.md
 */

import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'

const state = { allowedWidgets: null }

vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))
vi.mock('../../utils/loadInitialState.js', async (importOriginal) => ({
	...(await importOriginal()),
	loadInitialState: () => ({ allowedWidgets: state.allowedWidgets }),
}))

let Views

beforeAll(async () => {
	Views = (await import('../Views.vue')).default
})

afterEach(() => {
	state.allowedWidgets = null
})

describe('Views and the widget picker', () => {
	it('hands the picker the role\'s allow-list, or null for no limit', () => {
		expect(Views.computed.pickerAllowedWidgets.call({})).toBeNull()
		state.allowedWidgets = ['clock', 'tile']
		expect(Views.computed.pickerAllowedWidgets.call({})).toEqual(['clock', 'tile'])
		state.allowedWidgets = 'garbage'
		expect(Views.computed.pickerAllowedWidgets.call({})).toBeNull()
	})

	it('places a catalogue app from the picker as a one-cell tile', async () => {
		const host = { addTileToDashboard: vi.fn().mockResolvedValue({ id: 9 }) }
		const app = { uuid: 'z1', name: 'Case system', icon: '/z.svg', iconType: 'url', linkType: 'url', linkValue: 'https://zaken.example' }
		await Views.methods.addCatalogueApp.call(host, app)
		expect(host.addTileToDashboard).toHaveBeenCalledWith(
			{ title: 'Case system', icon: '/z.svg', iconType: 'url', linkType: 'url', linkValue: 'https://zaken.example' },
			{ w: 1, h: 1 },
		)
	})
})
