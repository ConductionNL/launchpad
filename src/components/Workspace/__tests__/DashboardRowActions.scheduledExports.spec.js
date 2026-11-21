/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard menu offers "Scheduled exports…" beside Share when the host
 * allows it, and asks the host to open the export list
 * (scheduled-exports task 19).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardRowActions from '../DashboardRowActions.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))

vi.mock('@nextcloud/vue', () => ({
	NcActions: {
		name: 'NcActions',
		template: '<div class="actions"><slot /></div>',
	},
	NcActionButton: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
}))

const dashboard = { id: 7, uuid: 'dash-uuid', name: 'Mijn week', isOwner: true }

/**
 * Mount the menu.
 *
 * @param {object} props Extra props.
 * @return {object} The wrapper.
 */
function mountMenu(props) {
	return mount(DashboardRowActions, {
		props: { dashboard, source: 'user', ...props },
	})
}

describe('DashboardRowActions scheduled exports', () => {
	it('shows the entry after Share and emits scheduledExports when allowed', async () => {
		const wrapper = mountMenu({ canShare: true, canScheduleExports: true })
		const entry = wrapper.find('[data-testid="cog-scheduled-exports"]')
		expect(entry.exists()).toBe(true)
		expect(entry.text()).toBe('Scheduled exports…')
		const ids = wrapper.findAll('button').map((b) => b.attributes('data-testid'))
		expect(ids.indexOf('cog-scheduled-exports')).toBe(
			ids.indexOf('cog-share') + 1,
		)

		await entry.trigger('click')
		expect(wrapper.emitted('scheduledExports')).toHaveLength(1)
	})

	it('hides the entry by default, so sidebar rows do not show it', () => {
		expect(
			mountMenu({}).find('[data-testid="cog-scheduled-exports"]').exists(),
		).toBe(false)
	})
})
