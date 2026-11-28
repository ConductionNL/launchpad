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

// @spec openspec/changes/archive/2026-11-28-edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-050
describe('ContainerChild — the edit menu of a widget inside a well (REQ-WDG-050)', () => {
	/**
	 * A child mounted with the dashboard's child actions.
	 *
	 * @param {object} props The props.
	 * @param {object} overrides Child actions to replace.
	 * @return {object} The wrapper and the actions.
	 */
	function mountChild(props, overrides = {}) {
		const actions = {
			embedHref: vi.fn(() => '/settings/admin/launchpad?embedId=12.a#embeds'),
			asksFirst: vi.fn(() => false),
			openEmbed: vi.fn(),
			duplicate: vi.fn(),
			remove: vi.fn(),
			...overrides,
		}
		const wrapper = mount(ContainerChild, {
			props,
			global: { provide: { launchpadChildOwner: () => ({ id: 12 }), launchpadChildActions: actions } },
		})
		return { wrapper, actions }
	}

	it('shows no menu in view mode', () => {
		const { wrapper } = mountChild({ placement: { uuid: 'a', type: 'label' } })
		expect(wrapper.find('[data-testid="cn-widget-edit-cog"]').exists()).toBe(false)
	})

	it('offers Duplicate, Create embed token and Remove in edit mode, and no form for a non-tile', () => {
		const { wrapper, actions } = mountChild({ placement: { uuid: 'a', type: 'label' }, editMode: true })
		expect(wrapper.find('[data-testid="container-child-menu"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="cn-widget-edit-cog-edit"]').exists()).toBe(false)
		expect(actions.embedHref).toHaveBeenCalledWith({ id: 12 }, { uuid: 'a', type: 'label' })
		const menu = wrapper.findComponent({ name: 'WidgetEditMenu' })
		expect(menu.props('embedHref')).toBe('/settings/admin/launchpad?embedId=12.a#embeds')
		expect(menu.props('embedDisabled')).toBe(false)

		menu.vm.$emit('duplicate')
		menu.vm.$emit('remove')
		menu.vm.$emit('embed', 'x')
		expect(actions.duplicate).toHaveBeenCalledWith(12, 'a')
		expect(actions.remove).toHaveBeenCalledWith(12, 'a')
		expect(actions.openEmbed).toHaveBeenCalledWith('x')
	})

	it('disables the embed entry for a child that cannot be named yet, and asks first with changes', () => {
		const { wrapper } = mountChild({ placement: { type: 'label' }, editMode: true }, { embedHref: () => '', asksFirst: () => true })
		const menu = wrapper.findComponent({ name: 'WidgetEditMenu' })
		expect(menu.props('embedDisabled')).toBe(true)
		expect(menu.props('embedAsksFirst')).toBe(true)
	})

	it('keeps the tile form and its remove for a tile in a group', () => {
		const { wrapper, actions } = mountChild({ placement: { uuid: 'a', type: 'tile', content: { title: 'Mail' } }, editMode: true, canEditTile: true })
		expect(wrapper.find('[data-testid="container-child-tile-menu"]').exists()).toBe(true)
		const menu = wrapper.findComponent({ name: 'WidgetEditMenu' })
		expect(menu.props('hideEdit')).toBe(false)
		menu.vm.$emit('edit')
		menu.vm.$emit('remove')
		expect(wrapper.emitted('editTile')).toHaveLength(1)
		expect(wrapper.emitted('removeTile')).toHaveLength(1)
		expect(actions.remove).not.toHaveBeenCalled()
	})

	it('shows no menu without a dashboard that offers the child actions', () => {
		const wrapper = mount(ContainerChild, { props: { placement: { uuid: 'a', type: 'label' }, editMode: true } })
		expect(wrapper.find('[data-testid="container-child-menu"]').exists()).toBe(false)
	})

	it('offers Configure for a widget the widget form can edit, and opens it with the child', () => {
		const { wrapper, actions } = mountChild(
			{ placement: { uuid: 'a', type: 'label' }, editMode: true },
			{ canConfigure: vi.fn(() => true), configure: vi.fn() },
		)
		const menu = wrapper.findComponent({ name: 'WidgetEditMenu' })
		expect(menu.props('hideEdit')).toBe(false)
		menu.vm.$emit('edit')
		expect(actions.configure).toHaveBeenCalledWith(12, 'a')
		expect(wrapper.emitted('editTile')).toBeUndefined()
	})
})
