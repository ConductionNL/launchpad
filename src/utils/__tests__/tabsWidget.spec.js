/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-1
 */

import { describe, expect, it } from 'vitest'
import { moveTab, newTab, normaliseTabs, tabHasWidgets } from '../tabsWidget.js'

describe('tabsWidget helpers', () => {
	it('reads a stored list safely', () => {
		expect(normaliseTabs(null)).toEqual([])
		expect(normaliseTabs([null, 'x', { title: 7 }, { id: 'k', title: 'K', placements: [{ id: 1 }] }])).toEqual([
			{ id: 'tab-1', title: '', placements: [] },
			{ id: 'k', title: 'K', placements: [{ id: 1 }] },
		])
	})

	it('makes a tab with an id not yet in the list', () => {
		const tab = newTab([{ id: 'a' }], 'New')
		expect(tab.title).toBe('New')
		expect(tab.placements).toEqual([])
		expect(tab.id).not.toBe('a')
	})

	it('moves a tab and leaves the ends alone', () => {
		const tabs = [{ id: 'a' }, { id: 'b' }, { id: 'c' }]
		expect(moveTab(tabs, 0, 1).map((tab) => tab.id)).toEqual(['b', 'a', 'c'])
		expect(moveTab(tabs, 0, -1).map((tab) => tab.id)).toEqual(['a', 'b', 'c'])
		expect(moveTab(tabs, 2, 1).map((tab) => tab.id)).toEqual(['a', 'b', 'c'])
	})

	it('knows whether a tab holds widgets', () => {
		expect(tabHasWidgets({ placements: [{}] })).toBe(true)
		expect(tabHasWidgets({ placements: [] })).toBe(false)
		expect(tabHasWidgets(undefined)).toBe(false)
	})
})
