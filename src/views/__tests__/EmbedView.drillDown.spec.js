/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A drill-down in an embed reaches the host page: a tile the visitor opens
 * is relayed as a `drillDown` event when the token's scope allows it, and
 * not at all when it does not (embedded-analytics REQ-EMB-006).
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-006--js-sdk-exposes-render-event-and-resize-apis
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import EmbedView from '../EmbedView.vue'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))

const context = vi.hoisted(() => ({ current: null }))
vi.mock('../../composables/useEmbedContext.js', () => ({ useEmbedContext: () => context.current }))

const placements = [{ id: 7, widgetId: 'tile-mail', tileType: 'app', gridX: 0, gridY: 0, tileTitle: 'Mail', tileLinkValue: '/apps/mail' }]

/**
 * An embed context with a loaded subject under the given scope.
 *
 * @param {object} scope The token's scope.
 * @return {object}
 */
function embedContext(scope) {
	const payload = ref({ scope, filters: {}, placements })
	const canRun = (action) => scope.mode === 'read-with-interactions' && scope.allowedActions.includes(action)
	return {
		loading: ref(false),
		error: ref(null),
		payload,
		placements: computed(() => payload.value.placements),
		scope: computed(() => scope),
		filters: computed(() => ({})),
		canRun,
		relay: vi.fn(() => true),
		start: () => () => {},
	}
}

/**
 * Mount the embed page and click its one tile.
 *
 * @param {object} scope The token's scope.
 * @return {Promise<{relay: Function, tile: object}>}
 */
async function clickTile(scope) {
	context.current = embedContext(scope)
	const wrapper = mount(EmbedView, { global: { stubs: { NcLoadingIcon: true, NcEmptyContent: true } } })
	const tile = wrapper.find('.public-share-view__tile')
	await tile.trigger('click')
	return { relay: context.current.relay, tile }
}

describe('EmbedView drill-down relay', () => {
	beforeEach(() => {
		context.current = null
	})

	it('relays the opened tile to the host page when the scope allows drillDown', async () => {
		const { relay, tile } = await clickTile({ mode: 'read-with-interactions', allowedActions: ['drillDown'], allowedFilters: [] })
		expect(relay).toHaveBeenCalledTimes(1)
		expect(relay).toHaveBeenCalledWith('drillDown', { placementId: 7, title: 'Mail', link: '/apps/mail', source: 'user-interaction' })
		expect(tile.attributes('href'), 'the tile stays a focusable control').toBe('#')
	})

	it('relays nothing under a read-only scope', async () => {
		const { relay, tile } = await clickTile({ mode: 'read', allowedActions: ['drillDown'], allowedFilters: [] })
		expect(tile.attributes('href')).toBeUndefined()
		expect(relay).not.toHaveBeenCalled()
	})

	it('relays nothing when interactions leave out drillDown', async () => {
		const { relay } = await clickTile({ mode: 'read-with-interactions', allowedActions: ['interact'], allowedFilters: [] })
		expect(relay).not.toHaveBeenCalled()
	})
})
