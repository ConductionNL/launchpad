/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * On a phone the workspace hides the widgets whose type is not ready for a
 * phone and names them for the notice; on a larger screen and in edit mode
 * nothing is hidden (launchpad-mobile-remote-access REQ-MRA-007). Asked
 * through the page's own computed, with the registry lookup replaced.
 *
 * @spec openspec/changes/archive/2026-11-24-phone-hidden-widgets-named/specs/launchpad-mobile-remote-access/spec.md#requirement-the-phone-notice-names-the-hidden-widgets-req-mra-007
 */

import { beforeAll, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('../../constants/widgetRegistry.js', async (importOriginal) => {
	const original = await importOriginal()
	const entries = {
		assistant: { displayName: 'Assistent', mobileReady: false },
		tile: { displayName: 'Tile', mobileReady: false },
		text: { displayName: 'Text', mobileReady: true },
	}
	return {
		...original,
		getWidgetTypeEntry: (type) => entries[type] ?? null,
		offeredForViewer: () => true,
	}
})

let Views

beforeAll(async () => {
	Views = (await import('../Views.vue')).default
})

const placements = [
	{ id: 1, widgetId: 'tile', customTitle: 'Snel beginnen (Word)' },
	{ id: 2, widgetId: 'assistant' },
	{ id: 3, widgetId: 'text', customTitle: 'Welkom' },
	{ id: 4, widgetId: 'assistant', isVisible: 0 },
]

function host(viewportWidth, isEditMode = false) {
	return { widgetPlacements: placements, isEditMode, unavailableSources: {}, integrationApps: [], viewportWidth }
}

describe('Views on a phone', () => {
	it('hides the widgets not ready for a phone and names them', () => {
		const vm = host(390)
		expect(Views.computed.shownPlacements.call(vm).map((p) => p.id)).toEqual([3])
		expect(Views.computed.phoneHiddenTitles.call(vm)).toEqual(['Snel beginnen (Word)', 'Assistent'])
	})

	it('hides nothing on a larger screen', () => {
		const vm = host(1280)
		expect(Views.computed.shownPlacements.call(vm).map((p) => p.id)).toEqual([1, 2, 3])
		expect(Views.computed.phoneHiddenTitles.call(vm)).toEqual([])
	})

	it('hides nothing in edit mode', () => {
		const vm = host(390, true)
		expect(Views.computed.shownPlacements.call(vm).map((p) => p.id)).toEqual([1, 2, 3])
		expect(Views.computed.phoneHiddenTitles.call(vm)).toEqual([])
	})

	it('follows the viewport width', () => {
		const vm = { viewportWidth: 1280 }
		Object.defineProperty(window, 'innerWidth', { configurable: true, writable: true, value: 390 })
		Views.methods.onViewportResize.call(vm)
		expect(vm.viewportWidth).toBe(390)
	})
})
