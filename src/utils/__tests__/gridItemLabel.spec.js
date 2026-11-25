/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * grid-layout REQ-GRID-008 / REQ-GRID-KBD-001: the accessible name of a grid item.
 */

import { describe, expect, it, vi } from 'vitest'

vi.mock('@conduction/nextcloud-vue', () => ({
	dashboardWidgetRegistry: {
		text: { displayName: 'Text' },
	},
}))

const { gridItemLabel } = await import('../gridItemLabel.js')

const PLACEMENT = { id: 7, widgetId: 'text', customTitle: 'Team Announcements', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2 }

describe('gridItemLabel', () => {
	it('names the widget by its title outside edit mode', () => {
		expect(gridItemLabel(PLACEMENT, { editing: false, columns: 12, availableWidgets: [] }))
			.toBe('Widget: Team Announcements')
	})

	it('adds the position and size in edit mode', () => {
		expect(gridItemLabel({ ...PLACEMENT, gridX: 3, gridY: 5 }, { editing: true, columns: 12, availableWidgets: [] }))
			.toBe('Widget: Team Announcements, column 4 of 12, row 6, 4 columns wide, 2 rows tall')
	})

	it('falls back to the type name when the placement has no title', () => {
		expect(gridItemLabel({ ...PLACEMENT, customTitle: '' }, { editing: false, columns: 12, availableWidgets: [] }))
			.toBe('Widget: Text')
	})

	it('reads a missing rectangle as the top-left single cell', () => {
		expect(gridItemLabel({ widgetId: 'text', customTitle: 'Notes' }, { editing: true, columns: 6, availableWidgets: [] }))
			.toBe('Widget: Notes, column 1 of 6, row 1, 1 columns wide, 1 rows tall')
	})
})
