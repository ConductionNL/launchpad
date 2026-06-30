/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
