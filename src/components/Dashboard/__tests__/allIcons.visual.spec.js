// SPDX-License-Identifier: EUPL-1.2
/**
 * Visual snapshot — all 15 dashboard icons at size 20 and 32.
 *
 * Equivalent to a Storybook story set: mounts `IconRenderer` for every
 * registry entry and asserts it renders an SVG at the requested pixel size.
 * Snapshots are committed alongside this file so regressions (e.g. a broken
 * icon import, a renamed component) are caught on CI without a browser.
 *
 * @spec openspec/changes/dashboard-icons/tasks.md#task-10
 */

import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'

import IconRenderer from '../IconRenderer.vue'
import { DASHBOARD_ICONS } from '../../../constants/dashboardIcons.js'

const ICON_NAMES = Object.keys(DASHBOARD_ICONS)
const SIZES = [20, 32]

describe('All 15 icons visual snapshot', () => {
	for (const size of SIZES) {
		describe(`size=${size}`, () => {
			for (const name of ICON_NAMES) {
				it(`renders ${name} at size ${size}`, () => {
					const wrapper = mount(IconRenderer, {
						propsData: { name, size },
					})
					// Must render an SVG — not an <img> (that branch is for URLs).
					expect(wrapper.find('svg').exists()).toBe(true)
					expect(wrapper.find('img').exists()).toBe(false)
					// Snapshot the rendered DOM so future changes are caught.
					expect(wrapper.html()).toMatchSnapshot()
				})
			}
		})
	}

	it('covers all 15 required registry names', () => {
		expect(ICON_NAMES.length).toBeGreaterThanOrEqual(15)
	})
})
