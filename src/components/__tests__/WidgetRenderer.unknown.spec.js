/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit test for `WidgetRenderer.vue` (REQ-WDG-009): a placement of a
 * widget that is neither a registry type, a tile nor an installed Nextcloud
 * widget renders the "Widget not available" empty state, not an empty
 * legacy mount point.
 */

import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import WidgetRenderer from '../WidgetRenderer.vue'

vi.mock('@conduction/nextcloud-vue', () => ({
	NcDashboardWidget: { name: 'NcDashboardWidget', template: '<div />' },
	NcEmptyContent: { name: 'NcEmptyContent', props: ['description'], template: '<div class="empty">{{ description }}</div>' },
	NcLoadingIcon: { name: 'NcLoadingIcon', template: '<span />' },
}))
vi.mock('../../constants/widgetRegistry.js', () => ({ getWidgetTypeEntry: () => null }))
vi.mock('../../services/widgetDataAdapters.js', () => ({
	buildRendererExtraProps: () => ({}),
	buildWidgetDataProvide: () => ({}),
}))
vi.mock('../../services/widgetBridge.js', () => ({
	widgetBridge: { hasWidgetCallback: () => true, mountWidget: vi.fn() },
}))
vi.mock('../TileWidget.vue', () => ({ default: { name: 'TileWidget', template: '<div />' } }))
vi.mock('../Widgets/CalculatedFields/CalculatedValues.vue', () => ({ default: { name: 'CalculatedValues', template: '<div />' } }))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), error: vi.fn(), warn: vi.fn(), info: vi.fn() },
}))

beforeEach(() => {
	setActivePinia(createPinia())
	globalThis.t = (_app, key) => key
})

describe('WidgetRenderer with an uninstalled widget', () => {
	it('shows "Widget not available" and no legacy mount point', () => {
		const wrapper = mount(WidgetRenderer, {
			props: { widget: null, placement: { id: 3, widgetId: 'uninstalled_widget', content: {} } },
			global: { mocks: { t: (_app, key) => key } },
		})

		expect(wrapper.find('.empty').text()).toBe('Widget not available')
		expect(wrapper.find('.widget-renderer__legacy').exists()).toBe(false)
	})

	it('keeps the legacy mount point for an installed Nextcloud widget', () => {
		const wrapper = mount(WidgetRenderer, {
			props: { widget: { id: 'legacy', itemApiVersions: [] }, placement: { id: 4, widgetId: 'legacy', content: {} } },
			global: { mocks: { t: (_app, key) => key } },
		})

		expect(wrapper.find('.widget-renderer__legacy').exists()).toBe(true)
		expect(wrapper.find('.empty').exists()).toBe(false)
	})
})
