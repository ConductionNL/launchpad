/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard menu has no "Version history" entry: the versions are the
 * tab Versions of the dashboard settings (REQ-VERSUI-001, board LpVersies).
 * The menu still tells the host when it opens.
 *
 * @spec openspec/changes/version-change-notes/specs/dashboard-versioning/spec.md#requirement-the-owner-sees-the-version-history-req-versui-001
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
			},
		},
	})
}

describe('DashboardRowActions version history', () => {
	it('offers no Version history entry, whatever the host passes', () => {
		for (const props of [{}, { canViewHistory: true }]) {
			const wrapper = mountMenu(props)
			expect(wrapper.find('[data-testid="cog-version-history"]').exists()).toBe(false)
			expect(wrapper.text()).not.toContain('Version history')
		}
	})

	it('tells the host when the menu opens', async () => {
		const wrapper = mountMenu({})
		await wrapper.find('.actions').trigger('mouseenter')
		expect(wrapper.emitted('menuOpen')).toHaveLength(1)
	})
})
