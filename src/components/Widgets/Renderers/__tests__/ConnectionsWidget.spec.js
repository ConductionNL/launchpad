/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ConnectionsWidget and its form (dashboard-connections-widget REQ-LP-CONN-005).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key]),
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key]),
}))

const fetchConnectionRows = vi.fn()
vi.mock('../../../../services/dashboardConnections.js', async (importOriginal) => ({
	...(await importOriginal()),
	fetchConnectionRows: (...args) => fetchConnectionRows(...args),
}))

import ConnectionsWidget from '../ConnectionsWidget.vue'
import ConnectionsWidgetForm from '../ConnectionsWidgetForm.vue'
import { getWidgetTypeEntry, offeredForViewer } from '../../../../constants/widgetRegistry.js'

const MANAGEMENT = [
	{ id: 1, widgetId: 'livetile', content: {} },
	{ id: 2, widgetId: 'news', content: {} },
	{ id: 3, widgetId: 'connections', content: {} },
]

/**
 * Mount the widget.
 *
 * @param {object} integrationApps The apps enabled for the viewer.
 * @param {Array<object>} placements The dashboard's placements.
 * @return {Promise<object>} The wrapper.
 */
async function mountWidget(integrationApps = { integriq: true }, placements = MANAGEMENT) {
	const wrapper = mount(ConnectionsWidget, {
		props: { content: {}, placement: {} },
		global: { provide: { integrationApps, dashboardPlacements: ref(placements) } },
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	fetchConnectionRows.mockReset().mockResolvedValue({
		state: 'ok',
		rows: [
			{ key: 'live-tiles', title: 'Live tiles', status: 'configured', lastSuccessAt: '2026-11-25T10:44:00Z' },
			{ key: 'news-feeds', title: 'News feeds', status: 'error', lastErrorAt: '2026-11-25T09:30:00Z', statusMessage: 'feeds.example.nl answered 503' },
			{ key: 'weather', title: 'Weather provider', status: 'configured' },
		],
	})
})

describe('ConnectionsWidget', () => {
	it('lists the connections the management dashboard uses with their states, not the weather provider', async () => {
		const wrapper = await mountWidget()
		expect(fetchConnectionRows).toHaveBeenCalledTimes(1)
		const live = wrapper.get('[data-testid="connection-live-tiles"]')
		expect(live.text()).toContain('Live tiles')
		expect(live.get('[data-testid="connection-status"]').text()).toBe('Configured')
		expect(live.find('[data-testid="connection-error"]').exists()).toBe(false)
		const news = wrapper.get('[data-testid="connection-news-feeds"]')
		expect(news.get('[data-testid="connection-status"]').text()).toBe('Error')
		expect(news.get('[data-testid="connection-error"]').text()).toContain('feeds.example.nl answered 503')
		expect(wrapper.find('[data-testid="connection-weather"]').exists()).toBe(false)
	})

	it('says it needs the Integrations app without integriq, and reads nothing', async () => {
		const wrapper = await mountWidget({})
		expect(wrapper.get('[data-testid="connections-no-app"]').text()).toBe('Connection states need the Integrations app')
		expect(fetchConnectionRows).not.toHaveBeenCalled()
	})

	it('says so when no widget uses a connection', async () => {
		const wrapper = await mountWidget({ integriq: true }, [{ widgetId: 'text' }])
		expect(wrapper.find('[data-testid="connections-none"]').exists()).toBe(true)
	})

	it('says so when the states cannot be read', async () => {
		fetchConnectionRows.mockResolvedValue({ state: 'error', rows: [] })
		const wrapper = await mountWidget()
		expect(wrapper.find('[data-testid="connections-error"]').exists()).toBe(true)
	})
})

describe('the connections type', () => {
	it('is offered only with integriq', () => {
		const entry = getWidgetTypeEntry('connections')
		expect(entry.requiresApp).toBe('integriq')
		expect(offeredForViewer(entry, { integriq: true })).toBe(true)
		expect(offeredForViewer(entry, {})).toBe(false)
	})

	it('has a form without settings', async () => {
		const wrapper = mount(ConnectionsWidgetForm, { props: { editingWidget: null } })
		await flushPromises()
		expect(wrapper.emitted('update:content')[0]).toEqual([{}])
		expect(wrapper.vm.validate()).toEqual([])
	})
})
