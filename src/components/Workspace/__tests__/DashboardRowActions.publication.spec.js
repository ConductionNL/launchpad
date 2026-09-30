/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A manager finds Publish or Unpublish, and Schedule…, in the dashboard
 * menu (sharing-dashboard-schedule-screen REQ-SCHEDUI-001).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardRowActions from '../DashboardRowActions.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@nextcloud/vue', () => ({
	NcActions: { name: 'NcActions', template: '<div><slot /></div>' },
	NcActionButton: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
}))

const stubs = {
	Cog: true,
	ContentSave: true,
	Pencil: true,
	Tune: true,
	ShapePolygonPlus: true,
	Star: true,
	StarCheck: true,
	ShareVariant: true,
	TrashCanOutline: true,
	CalendarClock: true,
	Publish: true,
	PublishOff: true,
}
function mountMenu(dashboard, canManagePublication) {
	return mount(DashboardRowActions, {
		props: { dashboard, source: 'user', canManagePublication },
		global: { stubs },
	})
}

describe('DashboardRowActions publication', () => {
	it('offers Publish and Schedule on a draft, and emits them', async () => {
		const wrapper = mountMenu(
			{ id: 1, uuid: 'd', isOwner: true, publicationStatus: 'draft' },
			true,
		)
		await wrapper.find('[data-testid="cog-publish"]').trigger('click')
		await wrapper.find('[data-testid="cog-schedule"]').trigger('click')
		expect(wrapper.emitted('publish')).toHaveLength(1)
		expect(wrapper.emitted('schedule')).toHaveLength(1)
		expect(wrapper.find('[data-testid="cog-unpublish"]').exists()).toBe(false)
	})

	it('offers Unpublish on a published dashboard', () => {
		const wrapper = mountMenu(
			{ id: 1, uuid: 'd', isOwner: true, publicationStatus: 'published' },
			true,
		)
		expect(wrapper.find('[data-testid="cog-unpublish"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="cog-publish"]').exists()).toBe(false)
	})

	it('offers nothing when the host does not allow it', () => {
		const wrapper = mountMenu(
			{ id: 1, uuid: 'd', isOwner: false, publicationStatus: 'draft' },
			false,
		)
		expect(wrapper.find('[data-testid="cog-schedule"]').exists()).toBe(false)
	})
})
