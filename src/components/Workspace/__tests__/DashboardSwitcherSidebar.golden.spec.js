/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Characterisation tests pinning the rendered sidebar body (comment nodes
 * left out) for several section combinations, and the events every row
 * action emits per section, so the four section blocks can be rendered
 * from one template without changing markup or behaviour.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import DashboardSwitcherSidebar from '../DashboardSwitcherSidebar.vue'

beforeEach(() => {
	globalThis.t = (_app, key) => key
})

const rowActionsStub = {
	name: 'DashboardRowActions',
	props: ['dashboard', 'source', 'canEdit', 'defaultUuid', 'isEditMode', 'activeDashboardId'],
	emits: ['toggleEdit', 'openConfig', 'addCustomWidget', 'delete', 'setDefault'],
	template: '<span class="row-actions-stub" :data-source="source" :data-can-edit="String(canEdit)" :data-default-uuid="defaultUuid" :data-edit-mode="String(isEditMode)" :data-active="activeDashboardId" />',
}

function mountSidebar(props = {}) {
	return mount(DashboardSwitcherSidebar, {
		propsData: {
			isOpen: true,
			groupName: 'Team',
			groupDashboards: [],
			userDashboards: [],
			activeDashboardId: null,
			allowUserDashboards: false,
			...props,
		},
		global: {
			stubs: {
				CnDashboardIcon: { props: ['name', 'size'], template: '<i class="icon" :data-name="name" />' },
				NcButton: { template: '<button class="nc-button"><slot name="icon" /><slot /></button>' },
				SidebarFooter: { template: '<footer />' },
				DetailFilter: { template: '<div class="filter" />' },
				DashboardRowActions: rowActionsStub,
			},
		},
	})
}

const rows = {
	group: { id: 1, uuid: 'ug', name: 'Team Board', icon: 'Star', source: 'group' },
	groupChild: { id: 2, uuid: 'ugc', name: 'Team Child', icon: null, source: 'group', parentUuid: 'ug' },
	default: { id: 3, uuid: 'ud', name: 'Org Default', icon: 'Home', source: 'default' },
	user: { id: 4, uuid: 'uu', name: 'My Notes', icon: null, source: 'user' },
	shared: { id: 5, uuid: 'us', name: 'Shared One', icon: 'Share', source: 'shared' },
}

function bodyHtml(wrapper) {
	return wrapper.find('.dashboard-switcher-sidebar__body').html().replace(/<!--[\s\S]*?-->/g, '')
}

describe('DashboardSwitcherSidebar body markup', () => {
	it.each([
		['all four sections', {
			groupDashboards: [rows.group, rows.groupChild, rows.default, rows.shared],
			userDashboards: [rows.user],
			allowUserDashboards: true,
			activeDashboardId: 4,
			defaultUuid: 'ud',
			canEdit: true,
		}],
		['default and shared only', { groupDashboards: [rows.default, rows.shared] }],
		['personal card only', { allowUserDashboards: true, dashboardQuotaReached: true }],
		['group and user without card', { groupDashboards: [rows.group], userDashboards: [rows.user] }],
		['nothing', {}],
	])('renders %s', async (name, props) => {
		const html = bodyHtml(mountSidebar(props))
		await expect(html).toMatchFileSnapshot(`./__snapshots__/DashboardSwitcherSidebar.golden.${name.replace(/ /g, '-')}.html`)
	})
})

describe('DashboardSwitcherSidebar row wiring per section', () => {
	it.each(['group', 'default', 'user', 'shared'])('wires the %s rows', async (source) => {
		const wrapper = mountSidebar({
			groupDashboards: [rows.group, rows.default, rows.shared],
			userDashboards: [rows.user],
			canEdit: true,
		})
		const li = wrapper.find(`li[data-source="${source}"]`)
		await li.trigger('click')
		await li.trigger('keydown', { key: 'Enter' })
		await li.trigger('keydown', { key: ' ' })
		const actions = wrapper.findAllComponents(rowActionsStub).find((c) => c.props('source') === source)
		for (const event of ['toggleEdit', 'openConfig', 'addCustomWidget', 'delete', 'setDefault']) {
			actions.vm.$emit(event)
		}
		await wrapper.vm.$nextTick()
		const emitted = Object.fromEntries(
			Object.entries(wrapper.emitted()).filter(([key]) => !['keydown', 'click'].includes(key)),
		)
		await expect(JSON.stringify(emitted, null, 1)).toMatchFileSnapshot(`./__snapshots__/DashboardSwitcherSidebar.golden.emits-${source}.json`)
	})
})
