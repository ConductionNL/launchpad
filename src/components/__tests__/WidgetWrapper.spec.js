/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for `WidgetWrapper.vue` covering the shared edit cog
 * (shown only in edit mode) and that per-widget custom header colours
 * (styleConfig.headerStyle) are forwarded to CnWidgetWrapper via styleConfig
 * (CnWidgetWrapper applies them natively — no per-app CSS-var workaround).
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
	it('forwards styleConfig (incl headerStyle) to CnWidgetWrapper', () => {
		const headerStyle = { backgroundColor: '#123456', textColor: '#abcdef' }
		const wrapper = mountWrapper({
			id: 1,
			widgetId: 'data',
			showTitle: true,
			styleConfig: { headerStyle },
		})
		// The wrapper passes the placement's styleConfig straight through; the
		// shared CnWidgetWrapper applies headerStyle natively (no CSS-var hack).
		expect(wrapper.vm.styleConfig.headerStyle).toEqual(headerStyle)
		const cn = wrapper.find('.launchpad-widget__wrapper')
		expect(cn.attributes('style') || '').not.toContain('--lp-header')
		expect(cn.classes()).not.toContain('launchpad-widget__wrapper--custom-header')
	})

	it('defaults styleConfig to an empty object when none is set', () => {
		const wrapper = mountWrapper({ id: 2, widgetId: 'data', showTitle: true, styleConfig: {} })
		expect(wrapper.vm.styleConfig).toEqual({})
		const cn = wrapper.find('.launchpad-widget__wrapper')
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
