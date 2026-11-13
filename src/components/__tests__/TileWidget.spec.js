/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `TileWidget.vue`: where a tile links to, how its icon
 * renders, the edit cog and health badge it shows, the click it records and
 * the title-colour style it injects once per tile.
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const recordTileClick = vi.fn()

vi.mock('@conduction/nextcloud-vue', () => ({
	CnWidgetEditCog: {
		name: 'CnWidgetEditCog',
		emits: ['edit', 'remove'],
		template: '<div class="cog"><button class="cog-edit" @click="$emit(\'edit\')" /><button class="cog-remove" @click="$emit(\'remove\')" /></div>',
	},
	CnDashboardIcon: { name: 'CnDashboardIcon', props: ['name', 'size'], template: '<i class="mdi" :data-name="name" />' },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => '/nc' + path }))
vi.mock('../../composables/useTileClickTracking.js', () => ({
	useTileClickTracking: () => ({ recordTileClick }),
}))
vi.mock('../HealthPingBadge.vue', () => ({
	default: { name: 'HealthPingBadge', props: ['placementId', 'interval'], template: '<span class="badge" />' },
}))

const { default: TileWidget } = await import('../TileWidget.vue')

let nextId = 1

/**
 * A tile with a unique id, overridden by the given fields.
 *
 * @param {object} fields The fields to set
 * @return {object} The tile
 */
function tile(fields = {}) {
	return { id: nextId++, title: 'Deck', linkType: 'url', linkValue: 'https://example.org', iconType: 'emoji', icon: '📋', ...fields }
}

describe('TileWidget', () => {
	beforeEach(() => {
		recordTileClick.mockReset()
	})

	afterEach(() => {
		document.head.querySelectorAll('style[id^="tile-"]').forEach((el) => el.remove())
	})

	it('links an app tile through generateUrl in the same tab', () => {
		const link = mount(TileWidget, { props: { tile: tile({ linkType: 'app', linkValue: 'deck' }) } }).find('a')

		expect(link.attributes('href')).toBe('/nc/apps/deck')
		expect(link.attributes('target')).toBe('_self')
	})

	it('routes an internal absolute path through generateUrl', () => {
		const link = mount(TileWidget, { props: { tile: tile({ linkType: 'internal', linkValue: '/apps/text' }) } }).find('a')

		expect(link.attributes('href')).toBe('/nc/apps/text')
	})

	it('passes external, protocol-relative and mailto links through, opening urls in a new tab', () => {
		const external = mount(TileWidget, { props: { tile: tile() } }).find('a')
		expect(external.attributes('href')).toBe('https://example.org')
		expect(external.attributes('target')).toBe('_blank')
		expect(external.attributes('rel')).toBe('noopener noreferrer')

		expect(mount(TileWidget, { props: { tile: tile({ linkValue: '//cdn.example.org' }) } }).find('a').attributes('href'))
			.toBe('//cdn.example.org')
		expect(mount(TileWidget, { props: { tile: tile({ linkType: 'mail', linkValue: 'mailto:a@example.org' }) } }).find('a').attributes('href'))
			.toBe('mailto:a@example.org')
	})

	it('renders each icon type', () => {
		expect(mount(TileWidget, { props: { tile: tile({ iconType: 'svg', icon: 'M0 0h24' }) } }).find('path').attributes('d')).toBe('M0 0h24')
		expect(mount(TileWidget, { props: { tile: tile({ iconType: 'class', icon: 'icon-deck' }) } }).find('span.icon').classes()).toContain('icon-deck')
		expect(mount(TileWidget, { props: { tile: tile({ iconType: 'url', icon: '/img/a.png' }) } }).find('img').attributes('src')).toBe('/img/a.png')
		expect(mount(TileWidget, { props: { tile: tile() } }).find('.tile-widget__emoji').text()).toBe('📋')
		expect(mount(TileWidget, { props: { tile: tile({ iconType: 'mdi', icon: 'Calendar' }) } }).find('.mdi').attributes('data-name')).toBe('Calendar')
	})

	it('shows the edit cog only in edit mode and forwards its events', async () => {
		expect(mount(TileWidget, { props: { tile: tile() } }).find('.cog').exists()).toBe(false)

		const wrapper = mount(TileWidget, { props: { tile: tile(), editMode: true } })
		await wrapper.find('.cog-edit').trigger('click')
		await wrapper.find('.cog-remove').trigger('click')

		expect(wrapper.emitted('edit')).toHaveLength(1)
		expect(wrapper.emitted('remove')).toHaveLength(1)
	})

	it('shows the health badge only when a ping is configured', () => {
		expect(mount(TileWidget, { props: { tile: tile() } }).findComponent({ name: 'HealthPingBadge' }).exists()).toBe(false)

		const badge = mount(TileWidget, { props: { tile: tile(), healthPingEnabled: true, placementId: 12, pingInterval: 30 } })
			.findComponent({ name: 'HealthPingBadge' })

		expect(badge.props()).toEqual({ placementId: 12, interval: 30 })
	})

	it('records a click on the tile', async () => {
		const item = tile()
		const wrapper = mount(TileWidget, { props: { tile: item } })
		wrapper.find('a').element.addEventListener('click', (event) => event.preventDefault())

		await wrapper.find('a').trigger('click')

		expect(recordTileClick).toHaveBeenCalledWith(item.id)
	})

	it('injects one title-colour style per tile, defaulting to white', () => {
		const item = tile({ textColor: '#123456' })
		mount(TileWidget, { props: { tile: item } })
		mount(TileWidget, { props: { tile: item } })

		const styles = document.head.querySelectorAll(`#tile-${item.id}-style`)
		expect(styles).toHaveLength(1)
		expect(styles[0].textContent).toContain('color: #123456 !important')

		const plain = tile()
		mount(TileWidget, { props: { tile: plain } })
		expect(document.getElementById(`tile-${plain.id}-style`).textContent).toContain('color: #ffffff !important')
	})

	it('exposes its colours as CSS variables', () => {
		const style = mount(TileWidget, { props: { tile: tile({ backgroundColor: '#000000' }) } }).find('.tile-widget').attributes('style')

		expect(style).toContain('--tile-bg-color: #000000')
		expect(style).toContain('--tile-text-color: #ffffff')
	})
})
