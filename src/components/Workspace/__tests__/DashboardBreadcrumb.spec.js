/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A child dashboard shows where it sits, from the server's breadcrumbs,
 * and never names an ancestor the viewer may not open
 * (dashboard-tree-navigation REQ-TREEUI-002). The response is
 * DashboardApiController::show's `breadcrumbs`.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardBreadcrumb from '../DashboardBreadcrumb.vue'
import { api } from '../../../services/api.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('../../../services/api.js', () => ({ api: { getDashboardById: vi.fn() } }))

beforeEach(() => vi.clearAllMocks())

describe('DashboardBreadcrumb', () => {
	it('REQ-TREEUI-002: reads "HR / Onboarding" with HR as a link', async () => {
		api.getDashboardById.mockResolvedValue({
			data: {
				breadcrumbs: [
					{ uuid: 'hr', name: 'HR', slug: 'hr', hidden: false },
					{
						uuid: 'onb',
						name: 'Onboarding',
						slug: 'onboarding',
						hidden: false,
					},
				],
			},
		})
		const wrapper = mount(DashboardBreadcrumb, {
			props: { dashboard: { id: 2, uuid: 'onb', parentUuid: 'hr' } },
		})
		await flushPromises()
		const nav = wrapper.find('nav')
		expect(nav.attributes('aria-label')).toBe('Dashboard location')
		expect(nav.text()).toMatch(/HR\s*\/\s*Onboarding/)
		await wrapper.find('button').trigger('click')
		expect(wrapper.emitted('navigate')[0]).toEqual(['hr'])
		expect(wrapper.find('[aria-current="page"]').text()).toBe('Onboarding')
	})

	it('REQ-TREEUI-002: a hidden ancestor shows as an ellipsis, not by name', async () => {
		api.getDashboardById.mockResolvedValue({
			data: {
				breadcrumbs: [
					{ uuid: null, name: null, slug: null, hidden: true },
					{
						uuid: 'onb',
						name: 'Onboarding',
						slug: 'onboarding',
						hidden: false,
					},
				],
			},
		})
		const wrapper = mount(DashboardBreadcrumb, {
			props: { dashboard: { id: 2, uuid: 'onb', parentUuid: 'secret' } },
		})
		await flushPromises()
		expect(wrapper.text()).toContain('…')
		expect(wrapper.find('button').exists()).toBe(false)
	})

	it('is absent for a top-level dashboard and asks nothing', async () => {
		const wrapper = mount(DashboardBreadcrumb, {
			props: { dashboard: { id: 1, uuid: 'hr', parentUuid: null } },
		})
		await flushPromises()
		expect(wrapper.find('nav').exists()).toBe(false)
		expect(api.getDashboardById).not.toHaveBeenCalled()
	})
})
