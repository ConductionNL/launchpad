/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Dashboards with a parent nest under it in the switcher, with an expand
 * control; with no parents the list is the flat list it was
 * (dashboard-tree-navigation REQ-TREEUI-001). Rows are dashboards as the
 * visible-dashboards endpoint serialises them (uuid, parentUuid, source).
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it } from 'vitest'
import DashboardSwitcherSidebar from '../DashboardSwitcherSidebar.vue'

beforeEach(() => {
	globalThis.t = (_app, key, vars = {}) =>
		key.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`)
})

const stubs = {
	CnDashboardIcon: { template: '<span />' },
	NcButton: { template: '<button><slot /></button>' },
	SidebarFooter: { template: '<footer />' },
	DashboardRowActions: { template: '<span />' },
}

function d(id, uuid, name, parentUuid = null) {
	return {
		id,
		uuid,
		name,
		parentUuid,
		source: 'group',
		icon: null,
	}
}

function mountSidebar(groupDashboards) {
	return mount(DashboardSwitcherSidebar, {
		props: {
			isOpen: true,
			groupName: 'HR team',
			groupDashboards,
			userDashboards: [],
			activeDashboardId: null,
			allowUserDashboards: false,
		},
		global: { stubs },
	})
}

describe('DashboardSwitcherSidebar tree', () => {
	it('REQ-TREEUI-001: children nest under their parent behind an expand control', async () => {
		const wrapper = mountSidebar([
			d(1, 'hr', 'HR'),
			d(2, 'onb', 'Onboarding', 'hr'),
			d(3, 'vac', 'Vacation rules', 'hr'),
			d(4, 'it', 'IT'),
		])
		const labels = () =>
			wrapper
				.findAll('[data-section="group"] .dashboard-switcher-sidebar__label')
				.map((n) => n.text())
		expect(labels()).toEqual(['HR', 'IT'])
		const expander = wrapper.find('[data-testid="tree-expand-hr"]')
		expect(expander.attributes('aria-expanded')).toBe('false')
		await expander.trigger('click')
		expect(labels()).toEqual(['HR', 'Onboarding', 'Vacation rules', 'IT'])
		expect(
			wrapper
				.find('[data-testid="tree-expand-hr"]')
				.attributes('aria-expanded'),
		).toBe('true')
		const onboarding = wrapper.findAll('[data-section="group"] li')[1]
		expect(onboarding.attributes('data-depth')).toBe('1')
		expect(wrapper.emitted('switch')).toBeUndefined()
	})

	it('REQ-TREEUI-001: without parents the list stays flat, with no expand controls', () => {
		const wrapper = mountSidebar([d(1, 'hr', 'HR'), d(4, 'it', 'IT')])
		expect(
			wrapper
				.findAll('[data-section="group"] .dashboard-switcher-sidebar__label')
				.map((n) => n.text()),
		).toEqual(['HR', 'IT'])
		expect(wrapper.find('[data-testid^="tree-expand-"]').exists()).toBe(false)
	})

	it('shows the active child even when its parent is collapsed', () => {
		const wrapper = mount(DashboardSwitcherSidebar, {
			props: {
				isOpen: true,
				groupName: 'HR team',
				groupDashboards: [d(1, 'hr', 'HR'), d(2, 'onb', 'Onboarding', 'hr')],
				userDashboards: [],
				activeDashboardId: 2,
				allowUserDashboards: false,
			},
			global: { stubs },
		})
		expect(
			wrapper
				.findAll('[data-section="group"] .dashboard-switcher-sidebar__label')
				.map((n) => n.text()),
		).toEqual(['HR', 'Onboarding'])
	})
})
