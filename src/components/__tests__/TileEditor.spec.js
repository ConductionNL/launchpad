/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `TileEditor.vue` legacy-icon display. Existing tiles
 * store an MDI shortname (`link`) or key (`AlertCircle`) rather than the SVG
 * path the icon picker is indexed by; the editor must resolve those to a path
 * for the picker/preview while leaving the stored value untouched until the
 * user picks a new icon.
 */

import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { mdiLink, mdiAlertCircle } from '@mdi/js'
import TileEditor from '../TileEditor.vue'

function mountEditor(tile) {
	return mount(TileEditor, { propsData: { open: true, tile } })
}

describe('TileEditor legacy-icon display', () => {
	it('resolves a legacy lowercase MDI shortname to its path', () => {
		const wrapper = mountEditor({ id: 1, title: 'Link', icon: 'link', iconType: 'class' })
		expect(wrapper.vm.displayIcon).toBe(mdiLink)
	})

	it('resolves a legacy PascalCase MDI key to its path', () => {
		const wrapper = mountEditor({ id: 2, title: 'Alert', icon: 'AlertCircle', iconType: 'mdi' })
		expect(wrapper.vm.displayIcon).toBe(mdiAlertCircle)
	})

	it('passes an SVG path through unchanged', () => {
		const wrapper = mountEditor({ id: 3, title: 'Path', icon: mdiLink, iconType: 'svg' })
		expect(wrapper.vm.displayIcon).toBe(mdiLink)
	})

	it('passes a custom icon URL through unchanged', () => {
		const url = '/apps/nldesign/img/icons/Star.svg'
		const wrapper = mountEditor({ id: 4, title: 'Url', icon: url, iconType: 'url' })
		expect(wrapper.vm.displayIcon).toBe(url)
	})

	it('leaves the stored icon untouched (preserved until the user picks)', () => {
		const wrapper = mountEditor({ id: 5, title: 'Link', icon: 'link', iconType: 'class' })
		expect(wrapper.vm.form.icon).toBe('link')
		expect(wrapper.vm.form.iconType).toBe('class')
	})
})

describe('TileEditor onIcon / isUrlIcon', () => {
	it('onIcon stores an SVG path as iconType "svg"', () => {
		const wrapper = mountEditor({ id: 1, title: 'X', icon: '', iconType: 'svg' })
		wrapper.vm.onIcon(mdiLink)
		expect(wrapper.vm.form.icon).toBe(mdiLink)
		expect(wrapper.vm.form.iconType).toBe('svg')
	})

	it('onIcon stores a custom URL as iconType "url"', () => {
		const wrapper = mountEditor({ id: 2, title: 'X', icon: '', iconType: 'svg' })
		wrapper.vm.onIcon('/apps/nldesign/img/icons/Star.svg')
		expect(wrapper.vm.form.icon).toBe('/apps/nldesign/img/icons/Star.svg')
		expect(wrapper.vm.form.iconType).toBe('url')
	})

	it('isUrlIcon is true for a URL value and false for an SVG path', () => {
		const urlTile = mountEditor({ id: 3, title: 'X', icon: 'https://example.com/i.png', iconType: 'url' })
		expect(urlTile.vm.isUrlIcon).toBe(true)

		const pathTile = mountEditor({ id: 4, title: 'X', icon: mdiLink, iconType: 'svg' })
		expect(pathTile.vm.isUrlIcon).toBe(false)
	})
})
