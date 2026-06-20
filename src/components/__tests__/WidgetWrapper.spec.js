/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `WidgetWrapper.vue` covering the shared edit cog
 * (shown only in edit mode) and the per-widget custom header colours
 * (styleConfig.headerStyle), which CnWidgetWrapper's own styleConfig does not
 * carry and are re-applied here via CSS variables.
 */

import { describe, it, expect, beforeEach } from 'vitest'
import { shallowMount } from '@vue/test-utils'
import WidgetWrapper from '../WidgetWrapper.vue'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

const mountWrapper = (placement, editMode = false) => shallowMount(WidgetWrapper, {
	propsData: { placement, widget: { title: 'Widget' }, editMode },
	mocks: { t: (_app, key) => key },
})

describe('WidgetWrapper — custom header style', () => {
	it('forwards styleConfig.headerStyle colours as CSS vars + flag class', () => {
		const wrapper = mountWrapper({
			id: 1,
			widgetId: 'data',
			showTitle: true,
			styleConfig: { headerStyle: { backgroundColor: '#123456', textColor: '#abcdef' } },
		})
		const cn = wrapper.find('.launchpad-widget__wrapper')
		expect(cn.classes()).toContain('launchpad-widget__wrapper--custom-header')
		const style = cn.attributes('style') || ''
		expect(style).toContain('--lp-header-bg: #123456')
		expect(style).toContain('--lp-header-color: #abcdef')
	})

	it('omits the flag class + vars when no headerStyle is set', () => {
		const wrapper = mountWrapper({ id: 2, widgetId: 'data', showTitle: true, styleConfig: {} })
		const cn = wrapper.find('.launchpad-widget__wrapper')
		expect(cn.classes()).not.toContain('launchpad-widget__wrapper--custom-header')
		expect(cn.attributes('style') || '').not.toContain('--lp-header')
	})
})

describe('WidgetWrapper — edit cog', () => {
	it('renders the shared cog only in edit mode', () => {
		const placement = { id: 3, widgetId: 'data', styleConfig: {} }
		expect(mountWrapper(placement, false).find('.launchpad-widget__cog').exists()).toBe(false)
		expect(mountWrapper(placement, true).find('.launchpad-widget__cog').exists()).toBe(true)
	})
})
