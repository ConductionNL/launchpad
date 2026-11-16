/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * An administrator's dashboard menu offers "Create embed token", which opens
 * Beheer ▸ Embeds with the dashboard pre-selected (embedded-analytics task 40).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
 */

import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import DashboardRowActions from '../DashboardRowActions.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/vue', () => ({
	NcActions: { name: 'NcActions', template: '<div class="actions"><slot /></div>' },
	NcActionButton: { name: 'NcActionButton', emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
}))

const dashboard = { id: 7, uuid: 'dash-uuid', name: 'Mijn week', isOwner: true }
const mountMenu = (props) => mount(DashboardRowActions, { props: { dashboard, source: 'user', ...props }, global: { stubs: { CodeTags: true } } })

describe('DashboardRowActions embed entry', () => {
	afterEach(() => {
		vi.unstubAllGlobals()
	})

	it('is hidden unless the host says the viewer may embed', () => {
		expect(mountMenu({}).find('[data-testid="cog-embed"]').exists()).toBe(false)
		expect(mountMenu({ canEmbed: true, dashboard: { ...dashboard, uuid: '' } }).find('[data-testid="cog-embed"]').exists()).toBe(false)
	})

	it('opens Beheer ▸ Embeds with the dashboard pre-selected', async () => {
		const assign = vi.fn()
		vi.stubGlobal('location', { assign })
		const wrapper = mountMenu({ canEmbed: true })

		expect(wrapper.find('[data-testid="cog-embed"]').text()).toBe('Create embed token')
		await wrapper.find('[data-testid="cog-embed"]').trigger('click')

		expect(assign).toHaveBeenCalledWith('/index.php/settings/admin/launchpad?tab=embeds&embedType=dashboard&embedId=dash-uuid&embedDashboard=7')
	})
})
