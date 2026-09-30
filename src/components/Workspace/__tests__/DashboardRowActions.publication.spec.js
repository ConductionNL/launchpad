/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard menu offers "Publish", "Unpublish" and "Schedule..." only
 * when the host says the viewer manages the dashboard (owner or
 * administrator), REQ-SCHEDUI-001.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardRowActions from '../DashboardRowActions.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))

vi.mock('@nextcloud/vue', () => ({
	NcActions: {
		name: 'NcActions',
		emits: ['open'],
		template:
			'<div class="actions" @mouseenter="$emit(\'open\')"><slot /></div>',
	},
	NcActionButton: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
}))

const dashboard = { id: 7, uuid: 'dash-uuid', name: 'Mijn week', isOwner: true }
function mountMenu(props) {
	return mount(DashboardRowActions, {
		props: { dashboard, source: 'user', ...props },
		global: {
			stubs: {
				Cog: true,
				ContentSave: true,
				Pencil: true,
				Tune: true,
				ShapePolygonPlus: true,
				Star: true,
				StarCheck: true,
				ShareVariant: true,
				TrashCanOutline: true,
				History: true,
				Publish: true,
				PublishOff: true,
				CalendarClock: true,
			},
		},
	})
}

describe('DashboardRowActions publication', () => {
	it('offers publish, unpublish and schedule to a manager', async () => {
		const wrapper = mountMenu({ canPublish: true })
		for (const [testid, event] of [
			['cog-publish', 'publish'],
			['cog-unpublish', 'unpublish'],
			['cog-schedule', 'schedule'],
		]) {
			const entry = wrapper.find(`[data-testid="${testid}"]`)
			expect(entry.exists()).toBe(true)
			await entry.trigger('click')
			expect(wrapper.emitted(event)).toHaveLength(1)
		}
		expect(wrapper.find('[data-testid="cog-schedule"]').text()).toBe('Schedule…')
	})

	it('hides them from a reader', () => {
		const wrapper = mountMenu({})
		expect(wrapper.find('[data-testid="cog-publish"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="cog-unpublish"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="cog-schedule"]').exists()).toBe(false)
	})
})
