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

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { mdiLink, mdiAlertCircle } from '@mdi/js'
import TileEditor from '../TileEditor.vue'
import { validateHealthPingConfig } from '../../services/healthPingClient.js'

vi.mock('../../services/healthPingClient.js', () => ({
	validateHealthPingConfig: vi.fn(),
}))

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

describe('TileEditor health-ping config — REQ-HPING-001', () => {
	beforeEach(() => {
		validateHealthPingConfig.mockReset()
	})

	it('defaults health-ping fields to disabled with sane defaults', () => {
		const wrapper = mountEditor(null)
		expect(wrapper.vm.form.healthPingEnabled).toBe(false)
		expect(wrapper.vm.form.expectedStatus).toBe(200)
		expect(wrapper.vm.form.pingInterval).toBe(60)
	})

	it('seeds health-ping fields from an editing tile (loaded from placement.content)', () => {
		const wrapper = mountEditor({
			id: 9,
			title: 'Zaaksysteem',
			icon: mdiLink,
			iconType: 'svg',
			healthPingEnabled: true,
			healthUrl: 'https://zaaksysteem.example.com/health',
			expectedStatus: 200,
			pingInterval: 30,
		})
		expect(wrapper.vm.form.healthPingEnabled).toBe(true)
		expect(wrapper.vm.form.healthUrl).toBe('https://zaaksysteem.example.com/health')
		expect(wrapper.vm.form.pingInterval).toBe(30)
	})

	it('clampPingInterval raises a value below the 15s minimum', () => {
		const wrapper = mountEditor(null)
		expect(wrapper.vm.clampPingInterval(5)).toBe(15)
	})

	it('clampPingInterval defaults an unset (0/negative) value to 60s', () => {
		const wrapper = mountEditor(null)
		expect(wrapper.vm.clampPingInterval(0)).toBe(60)
		expect(wrapper.vm.clampPingInterval(-5)).toBe(60)
	})

	it('clampPingInterval leaves a value above the minimum unchanged', () => {
		const wrapper = mountEditor(null)
		expect(wrapper.vm.clampPingInterval(120)).toBe(120)
	})

	it('saveTile emits the clamped pingInterval and numeric expectedStatus', () => {
		const wrapper = mountEditor(null)
		wrapper.vm.form.healthPingEnabled = true
		wrapper.vm.form.healthUrl = 'https://example.com/health'
		wrapper.vm.form.expectedStatus = '200'
		wrapper.vm.form.pingInterval = 5
		wrapper.vm.saveTile()

		const emitted = wrapper.emitted('save')[0][0]
		expect(emitted.healthPingEnabled).toBe(true)
		expect(emitted.expectedStatus).toBe(200)
		expect(emitted.pingInterval).toBe(15)
	})

	it('onHealthPingToggle clears a stale validation error when disabling', () => {
		const wrapper = mountEditor(null)
		wrapper.vm.healthUrlError = 'This host is not on the allow-list.'
		wrapper.vm.onHealthPingToggle(false)
		expect(wrapper.vm.form.healthPingEnabled).toBe(false)
		expect(wrapper.vm.healthUrlError).toBe('')
	})

	it('checkHealthUrlAllowed surfaces the host_not_allowed error', async () => {
		validateHealthPingConfig.mockResolvedValue({ valid: false, errors: ['host_not_allowed'] })
		const wrapper = mountEditor(null)
		wrapper.vm.form.healthPingEnabled = true
		wrapper.vm.form.healthUrl = 'https://blocked.example.com'
		await wrapper.vm.checkHealthUrlAllowed()
		expect(wrapper.vm.healthUrlError).toBe('This host is not on the allow-list.')
	})

	it('checkHealthUrlAllowed clears the error for an allow-listed host', async () => {
		validateHealthPingConfig.mockResolvedValue({ valid: true, errors: [] })
		const wrapper = mountEditor(null)
		wrapper.vm.form.healthPingEnabled = true
		wrapper.vm.form.healthUrl = 'https://ok.example.com'
		await wrapper.vm.checkHealthUrlAllowed()
		expect(wrapper.vm.healthUrlError).toBe('')
	})

	it('checkHealthUrlAllowed is a no-op when ping is disabled', async () => {
		const wrapper = mountEditor(null)
		wrapper.vm.form.healthPingEnabled = false
		wrapper.vm.form.healthUrl = 'https://example.com'
		await wrapper.vm.checkHealthUrlAllowed()
		expect(validateHealthPingConfig).not.toHaveBeenCalled()
	})
})
