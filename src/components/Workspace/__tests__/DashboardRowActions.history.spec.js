/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard menu offers "Version history" only when the host says the
 * viewer may see it (owner or administrator, and versioning supported),
 * and asks the host to find out when the menu opens (REQ-VERSUI-001).
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
			},
		},
	})
}

describe('DashboardRowActions version history', () => {
	it('shows the entry and emits versionHistory when allowed', async () => {
		const wrapper = mountMenu({ canViewHistory: true })
		const entry = wrapper.find('[data-testid="cog-version-history"]')
		expect(entry.exists()).toBe(true)
		await entry.trigger('click')
		expect(wrapper.emitted('versionHistory')).toHaveLength(1)
	})

	it('hides the entry by default', () => {
		expect(
			mountMenu({}).find('[data-testid="cog-version-history"]').exists(),
		).toBe(false)
	})

	it('tells the host when the menu opens', async () => {
		const wrapper = mountMenu({})
		await wrapper.find('.actions').trigger('mouseenter')
		expect(wrapper.emitted('menuOpen')).toHaveLength(1)
	})
})
