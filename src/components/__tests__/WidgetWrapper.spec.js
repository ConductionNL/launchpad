/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `WidgetWrapper.vue` covering the shared edit cog
 * (shown only in edit mode) and that per-widget custom header colours
 * (styleConfig.headerStyle) are forwarded to CnWidgetWrapper via styleConfig
 * (CnWidgetWrapper applies them natively — no per-app CSS-var workaround).
 */

import { shallowMount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import WidgetWrapper from '../WidgetWrapper.vue'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

function mountWrapper(placement, editMode = false) {
	return shallowMount(WidgetWrapper, {
		propsData: { placement, widget: { title: 'Widget' }, editMode },
		mocks: { t: (_app, key) => key },
	})
}

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
		expect(cn.classes()).not.toContain(
			'launchpad-widget__wrapper--custom-header',
		)
	})

	it('defaults styleConfig to an empty object when none is set', () => {
		const wrapper = mountWrapper({
			id: 2,
			widgetId: 'data',
			showTitle: true,
			styleConfig: {},
		})
		expect(wrapper.vm.styleConfig).toEqual({})
		const cn = wrapper.find('.launchpad-widget__wrapper')
		expect(cn.attributes('style') || '').not.toContain('--lp-header')
	})
})

describe('WidgetWrapper — showHeader (showTitle round-trip)', () => {
	// `showTitle` persists to the DB as an integer/string, so the header
	// decision must treat 0 / '0' / false / '' as "off" — a strict `!== false`
	// check wrongly kept the header for a stored 0 (the generic "Widget" title).
	it.each([
		[false, false],
		[0, false],
		['0', false],
		['', false],
		[true, true],
		[1, true],
		['1', true],
		[undefined, true],
		[null, true],
	])('showTitle=%p → showHeader=%p', (showTitle, expected) => {
		const wrapper = mountWrapper({
			id: 9,
			widgetId: 'data',
			showTitle,
			styleConfig: {},
		})
		expect(wrapper.vm.showHeader).toBe(expected)
	})
})

describe('WidgetWrapper — edit cog', () => {
	it('renders the shared cog only in edit mode', () => {
		const placement = { id: 3, widgetId: 'data', styleConfig: {} }
		expect(
			mountWrapper(placement, false).find('.launchpad-widget__cog').exists(),
		).toBe(false)
		expect(
			mountWrapper(placement, true).find('.launchpad-widget__cog').exists(),
		).toBe(true)
	})
})
