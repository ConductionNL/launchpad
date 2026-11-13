/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ContainerChild.vue` (REQ-CONT-003): a known
 * registry type mounts its renderer with the placement's content, an
 * unknown or missing type renders the "Unknown widget type" placeholder,
 * the data-source adapters it provides are scoped to its own placement id,
 * and Files receives its `apiBase` extra prop.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const axiosGet = vi.fn()
vi.mock('@nextcloud/axios', () => ({ default: { get: (...a) => axiosGet(...a) } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (p, params = {}) => `/index.php${p.replace(/\{(\w+)\}/g, (_m, k) => params[k])}`,
}))

const { default: ContainerChild } = await import('../ContainerChild.vue')

beforeEach(() => {
	globalThis.t = (_app, key, vars = {}) => key.replace(/\{(\w+)\}/g, (_m, k) => vars[k])
	axiosGet.mockReset()
})

describe('ContainerChild — registry dispatch', () => {
	it('mounts the registered renderer for a known type with the placement content', () => {
		const wrapper = mount(ContainerChild, {
			props: { placement: { id: 3, type: 'label', content: { text: 'Hello child' } } },
		})
		expect(wrapper.vm.childRenderer).not.toBeNull()
		expect(wrapper.vm.childContent).toEqual({ text: 'Hello child' })
		expect(wrapper.find('.container-child--unknown').exists()).toBe(false)
		expect(wrapper.find('.cn-label-widget').exists()).toBe(true)
	})

	it('passes an empty content object when the placement has none', () => {
		const wrapper = mount(ContainerChild, {
			props: { placement: { id: 3, type: 'label' } },
		})
		expect(wrapper.vm.childContent).toEqual({})
	})

	it('renders the unknown-type placeholder naming an unregistered type', () => {
		const wrapper = mount(ContainerChild, {
			props: { placement: { id: 3, type: 'no-such-widget-type' } },
		})
		expect(wrapper.vm.childRenderer).toBeNull()
		expect(wrapper.find('.container-child__missing').text()).toBe('Unknown widget type: no-such-widget-type')
	})

	it('treats a missing type as unknown without a registry lookup', () => {
		const wrapper = mount(ContainerChild, { props: { placement: { id: 3 } } })
		expect(wrapper.vm.registryEntry).toBeNull()
		expect(wrapper.vm.unknownLabel).toBe('Unknown widget type: ')
		expect(wrapper.find('.container-child__missing').exists()).toBe(true)
	})

	it('treats an empty or non-string type as unknown', () => {
		expect(mount(ContainerChild, { props: { placement: { type: '' } } }).vm.registryEntry).toBeNull()
		expect(mount(ContainerChild, { props: { placement: { type: 42 } } }).vm.registryEntry).toBeNull()
	})
})

describe('ContainerChild — extra renderer props', () => {
	it('gives the files renderer its apiBase', () => {
		const wrapper = mount(ContainerChild, {
			props: { placement: { id: 9, type: 'unknown-x' } },
		})
		expect(wrapper.vm.extraProps).toEqual({})
		const files = mount(ContainerChild, {
			props: { placement: { id: 9, type: 'files' } },
			shallow: true,
		})
		expect(files.vm.extraProps).toEqual({ apiBase: '/apps/launchpad' })
	})
})

describe('ContainerChild — provided data sources', () => {
	it('scopes the calendar source to its own placement id', async () => {
		axiosGet.mockResolvedValue({ data: { events: [{ id: 'e1' }], failures: 'bad' } })
		const wrapper = mount(ContainerChild, {
			props: { placement: { id: 77, type: 'label', content: {} } },
		})
		const provides = wrapper.vm.$.provides
		expect(Object.keys(provides)).toEqual(expect.arrayContaining([
			'cnPeopleSource', 'cnCalendarSource', 'cnSpendAnalyticsSource',
		]))
		const result = await provides.cnCalendarSource.fetchEvents({ from: 'a', to: 'b' })
		expect(axiosGet).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/widgets/calendar/77/events',
			{ params: { from: 'a', to: 'b' } },
		)
		expect(result).toEqual({ events: [{ id: 'e1' }], failures: [] })
	})

	it('reads the placement id at call time, so a changed placement is followed', async () => {
		axiosGet.mockResolvedValue({ data: {} })
		const wrapper = mount(ContainerChild, {
			props: { placement: { id: 1, type: 'label', content: {} } },
		})
		const source = wrapper.vm.$.provides.cnCalendarSource
		await wrapper.setProps({ placement: { id: 2, type: 'label', content: {} } })
		await source.fetchEvents({})
		expect(axiosGet.mock.calls[0][0]).toBe('/index.php/apps/launchpad/api/widgets/calendar/2/events')
	})
})
