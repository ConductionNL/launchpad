/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The panel beside the tiles: open, replace, close, focus return and the
 * refused-framing fallback (launcher-tile-open-behaviour D3, D4).
 *
 * @spec openspec/changes/launcher-tile-open-behaviour/specs/tiles/spec.md
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const checkIframeFramable = vi.fn()

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_, k) => vars[k] ?? ''),
}))
vi.mock('@nextcloud/vue', () => ({
	NcButton: { name: 'NcButton', emits: ['click'], template: '<button v-bind="$attrs" @click="$emit(\'click\')"><slot name="icon" /><slot /></button>' },
}))
vi.mock('vue-material-design-icons/Close.vue', () => ({ default: { name: 'CloseIcon', template: '<i />' } }))
vi.mock('../../../services/iframeClient.js', () => ({ checkIframeFramable }))

const { default: TileSidePanel } = await import('../TileSidePanel.vue')
const { useTilePanelStore } = await import('../../../stores/tilePanel.js')

describe('TileSidePanel', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		checkIframeFramable.mockReset()
	})

	it('renders nothing while closed', () => {
		expect(mount(TileSidePanel).find('[data-test="tile-side-panel"]').exists()).toBe(false)
	})

	it('frames an address on this Nextcloud without asking, with the tile title and a new-tab link', async () => {
		const wrapper = mount(TileSidePanel, { attachTo: document.body })
		useTilePanelStore().open({ url: '/nc/apps/files', title: 'Files', sameOrigin: true })
		await flushPromises()

		const frame = wrapper.find('[data-test="tile-side-panel-frame"]')
		expect(frame.attributes('src')).toBe('/nc/apps/files')
		expect(frame.attributes('title')).toBe('Files')
		expect(wrapper.find('.tile-side-panel__external').attributes()).toMatchObject({ href: '/nc/apps/files', target: '_blank' })
		expect(checkIframeFramable).not.toHaveBeenCalled()
		expect(document.activeElement).toBe(wrapper.find('[data-test="tile-side-panel"]').element)
		wrapper.unmount()
	})

	it('asks the server before framing an outside address, and shows the fallback when refused', async () => {
		checkIframeFramable.mockResolvedValueOnce({ framable: false, reason: 'host_not_allowed' })
		const wrapper = mount(TileSidePanel)
		useTilePanelStore().open({ url: 'https://blocked.example', title: 'Blocked' })
		await flushPromises()

		expect(checkIframeFramable).toHaveBeenCalledWith('https://blocked.example')
		expect(wrapper.find('[data-test="tile-side-panel-frame"]').exists()).toBe(false)
		const fallback = wrapper.find('[data-test="tile-side-panel-fallback"]')
		expect(fallback.text()).toContain('Open in new tab')
		expect(fallback.find('a').attributes('href')).toBe('https://blocked.example')
	})

	it('frames an outside address the server allows, and a newer tile replaces it', async () => {
		checkIframeFramable.mockResolvedValue({ framable: true })
		const wrapper = mount(TileSidePanel)
		const panel = useTilePanelStore()
		panel.open({ url: 'https://ok.example', title: 'Ok' })
		await flushPromises()
		expect(wrapper.find('[data-test="tile-side-panel-frame"]').attributes('src')).toBe('https://ok.example')

		panel.open({ url: '/nc/apps/deck', title: 'Deck', sameOrigin: true })
		await flushPromises()
		expect(wrapper.findAll('iframe')).toHaveLength(1)
		expect(wrapper.find('iframe').attributes('src')).toBe('/nc/apps/deck')
	})

	it('closes on the close button and on Escape, returning focus to the tile', async () => {
		const opener = { focus: vi.fn() }
		const wrapper = mount(TileSidePanel)
		const panel = useTilePanelStore()

		panel.open({ url: '/nc/apps/files', title: 'Files', sameOrigin: true, opener })
		await flushPromises()
		await wrapper.find('[data-test="tile-side-panel-close"]').trigger('click')
		expect(panel.isOpen).toBe(false)

		panel.open({ url: '/nc/apps/files', title: 'Files', sameOrigin: true, opener })
		await flushPromises()
		await wrapper.find('[data-test="tile-side-panel"]').trigger('keydown', { key: 'Escape' })
		expect(panel.isOpen).toBe(false)
		expect(opener.focus).toHaveBeenCalledTimes(2)
	})
})
