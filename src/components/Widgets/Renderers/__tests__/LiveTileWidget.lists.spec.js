/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the list, table and key-value displays of
 * `LiveTileWidget.vue` (REQ-LTC-001, REQ-LTC-003).
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import LiveTileWidget from '../LiveTileWidget.vue'
import { fetchLiveTileValue } from '../../../../services/liveTileClient.js'

vi.mock('../../../../services/liveTileClient.js', () => ({
	fetchLiveTileValue: vi.fn(),
}))

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	fetchLiveTileValue.mockReset()
})

function row(title, link, date) {
	return [
		{ label: 'Incident', role: 'title', value: title },
		{ label: 'Open', role: 'link', value: link },
		{ label: 'Reported', role: 'date', value: date },
	]
}

async function render(reading, content = {}) {
	fetchLiveTileValue.mockResolvedValue({ value: null, formatted: null, badge: null, fetchedAt: '2026-11-14T08:00:00Z', stale: false, ...reading })
	const wrapper = mount(LiveTileWidget, { propsData: { content, placement: { id: 7 } } })
	await flushPromises()
	return wrapper
}

describe('LiveTileWidget — lists, tables and pairs', () => {
	it('a list shows each title as the link text, opening in a new tab', async () => {
		const wrapper = await render({ display: 'list', items: [row('Printer kapot', 'https://topdesk.example/i/1', null), row('VPN traag', 'https://topdesk.example/i/2', null)] })
		const links = wrapper.findAll('ul.live-tile-widget__list li a')
		expect(links).toHaveLength(2)
		expect(links[0].text()).toBe('Printer kapot')
		expect(links[0].attributes('href')).toBe('https://topdesk.example/i/1')
		expect(links[0].attributes('target')).toBe('_blank')
		expect(links[0].attributes('rel')).toBe('noopener noreferrer')
		expect(wrapper.find('.live-tile-widget__value').exists()).toBe(false)
	})

	it('a link that is not https shows the title as plain text', async () => {
		const wrapper = await render({ display: 'list', items: [row('Script', 'javascript:alert(1)', null), row('Plain', 'http://example.org', null)] })
		expect(wrapper.findAll('ul.live-tile-widget__list a')).toHaveLength(0)
		expect(wrapper.findAll('.live-tile-widget__list-title').map((node) => node.text())).toEqual(['Script', 'Plain'])
	})

	it('a table has column headers from the mapping labels', async () => {
		const wrapper = await render({ display: 'table', items: [row('A', 'https://x.example/a', null)] })
		const headers = wrapper.findAll('th')
		expect(headers.map((node) => node.text())).toEqual(['Incident', 'Open', 'Reported'])
		expect(headers.every((node) => node.attributes('scope') === 'col')).toBe(true)
		expect(wrapper.find('td a').attributes('href')).toBe('https://x.example/a')
	})

	it('key-value pairs render as a description list', async () => {
		const wrapper = await render({ display: 'keyvalue', pairs: [{ label: 'Open', role: 'field', value: 4 }, { label: 'Team', role: 'field', value: 'Servicedesk' }] })
		expect(wrapper.findAll('dt').map((node) => node.text())).toEqual(['Open', 'Team'])
		expect(wrapper.findAll('dd').map((node) => node.text())).toEqual(['4', 'Servicedesk'])
	})

	it('a list tile with a click-through link does not nest links', async () => {
		const wrapper = await render({ display: 'list', items: [row('A', 'https://x.example/a', null)] }, { linkUrl: 'https://intranet.example' })
		expect(wrapper.element.tagName).toBe('DIV')
		expect(wrapper.findAll('a')).toHaveLength(1)
	})

	it('a single-value tile still renders its value', async () => {
		const wrapper = await render({ value: 12, formatted: '12' })
		expect(wrapper.find('.live-tile-widget__value').text()).toBe('12')
		expect(wrapper.find('ul').exists()).toBe(false)
	})
})
