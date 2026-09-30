/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * PublicDashboardGrid, extracted from the public share page so the kiosk
 * player renders dashboards the same anonymous-safe way. Placements are
 * WidgetPlacement::jsonSerialize rows.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PublicDashboardGrid from '../PublicDashboardGrid.vue'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))

describe('PublicDashboardGrid', () => {
	it('renders tiles and static widgets, in reading order, and hides other widgets', () => {
		const wrapper = mount(PublicDashboardGrid, {
			props: {
				placements: [
					{
						id: 2,
						widgetId: 'text',
						gridX: 0,
						gridY: 1,
						gridWidth: 6,
						content: { text: 'Lunch at noon' },
					},
					{
						id: 1,
						widgetId: 'tile-mail',
						tileType: 'app',
						gridX: 0,
						gridY: 0,
						tileTitle: 'Mail',
						tileLinkValue: '/apps/mail',
					},
					{
						id: 3,
						widgetId: 'calendar',
						gridX: 6,
						gridY: 1,
						customTitle: 'Agenda',
					},
					{
						id: 4,
						widgetId: 'text',
						gridX: 0,
						gridY: 2,
						isVisible: 0,
						content: { text: 'hidden' },
					},
				],
			},
		})
		const cells = wrapper.findAll('.public-share-view__cell')
		expect(cells).toHaveLength(3)
		expect(cells[0].text()).toContain('Mail')
		expect(cells[1].text()).toContain('Lunch at noon')
		expect(cells[2].text()).toContain('only visible to signed-in users')
	})

	it('says so when nothing can be shown', () => {
		const wrapper = mount(PublicDashboardGrid, { props: { placements: [] } })
		expect(wrapper.text()).toContain('no publicly viewable content')
	})
})
