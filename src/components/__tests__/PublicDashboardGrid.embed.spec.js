/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Inside an embed the public grid takes the token's context: the tenant
 * theme colours it, a tile links out of the embed only when the scope allows
 * `interact`; without a context it renders as before (embedded-analytics
 * task 13).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-13
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PublicDashboardGrid from '../PublicDashboardGrid.vue'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))

const placements = [{ id: 1, widgetId: 'tile-mail', tileType: 'app', gridX: 0, gridY: 0, tileTitle: 'Mail', tileLinkValue: '/apps/mail' }]

/**
 * The grid's root and its one tile.
 *
 * @param {object|undefined} embedContext The embed context, if any.
 * @return {{root: object, tile: object}}
 */
function render(embedContext) {
	const wrapper = mount(PublicDashboardGrid, { props: { placements, ...(embedContext === undefined ? {} : { embedContext }) } })
	return { root: wrapper.find('.public-dashboard-grid'), tile: wrapper.find('.public-share-view__tile') }
}

describe('PublicDashboardGrid in an embed', () => {
	it('renders as before without a context', () => {
		const { root, tile } = render()
		expect(root.classes()).not.toContain('public-dashboard-grid--embed')
		expect(root.attributes('data-embed-mode')).toBeUndefined()
		expect(tile.attributes('href')).toBeTruthy()
		expect(tile.attributes('target')).toBeUndefined()
	})

	it('drops tile links under a read-only scope', () => {
		const { root, tile } = render({ scope: { mode: 'read', allowedActions: ['interact'] }, filters: {} })
		expect(root.classes()).toContain('public-dashboard-grid--embed')
		expect(root.attributes('data-embed-mode')).toBe('read')
		expect(tile.attributes('href')).toBeUndefined()
		expect(tile.text()).toBe('Mail')
	})

	it('drops tile links when interactions leave out interact', () => {
		const { tile } = render({ scope: { mode: 'read-with-interactions', allowedActions: ['export'] } })
		expect(tile.attributes('href')).toBeUndefined()
	})

	it('opens a tile link outside the embed when the scope allows interact', () => {
		const { root, tile } = render({ scope: { mode: 'read-with-interactions', allowedActions: ['interact'] } })
		expect(root.attributes('data-embed-mode')).toBe('read-with-interactions')
		expect(tile.attributes('href')).toBe(render().tile.attributes('href'))
		expect(tile.attributes('target')).toBe('_blank')
		expect(tile.attributes('rel')).toBe('noopener noreferrer')
	})
})
