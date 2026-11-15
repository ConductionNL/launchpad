/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Behaviour tests for WidgetRenderer's dispatch computeds (registry entry,
 * full-bleed, renderer props, tile detection and tile data, API version
 * flags, empty message), its watchers and lifecycle hooks, the widget-store
 * sync and the auto-refresh timer. Like WidgetRenderer.golden.spec.js they
 * run the component options against a stand-in `this`; the item mapping,
 * initWidget and the legacy mount loop are pinned there.
 */

import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import WidgetRenderer from '../WidgetRenderer.vue'
import { useTileStore } from '../../stores/tiles.js'
import { useWidgetStore } from '../../stores/widgets.js'

vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), error: vi.fn(), warn: vi.fn(), info: vi.fn() },
}))

const { computed, watch, methods } = WidgetRenderer

/**
 * A stand-in instance with every method bound to it.
 *
 * @param {object} state Data, computed values and overrides.
 * @return {object} the instance.
 */
function instance(state = {}) {
	const ctx = { refreshInterval: null, itemsLoading: false, localWidgetItemsData: { items: [], loading: false } }
	for (const [name, fn] of Object.entries(methods)) {
		ctx[name] = fn.bind(ctx)
	}
	return Object.assign(ctx, state)
}

beforeEach(() => {
	setActivePinia(createPinia())
})

afterEach(() => {
	vi.useRealTimers()
	vi.restoreAllMocks()
})

describe('WidgetRenderer dispatch computeds', () => {
	it('reads the placement content as an object, empty when unset', () => {
		expect(computed.normalizedContent.call({ placement: { content: { a: 1 } } })).toEqual({ a: 1 })
		expect(computed.normalizedContent.call({ placement: { content: null } })).toEqual({})
		expect(computed.normalizedContent.call({ placement: undefined })).toEqual({})
	})

	it('finds a registry renderer only for a known, non-empty string widget id', () => {
		const entry = computed.registryEntry.call({ placement: { widgetId: 'label' } })
		expect(entry).not.toBeNull()
		expect(entry.renderer).toBeTruthy()

		expect(computed.registryEntry.call({ placement: { widgetId: 'no-such-widget' } })).toBeNull()
		expect(computed.registryEntry.call({ placement: { widgetId: '' } })).toBeNull()
		expect(computed.registryEntry.call({ placement: { widgetId: 42 } })).toBeNull()
		expect(computed.registryEntry.call({ placement: null })).toBeNull()
	})

	it('renders only header, image and divider edge-to-edge', () => {
		for (const widgetId of ['header', 'image', 'divider']) {
			expect(computed.isFullBleed.call({ placement: { widgetId } })).toBe(true)
		}
		expect(computed.isFullBleed.call({ placement: { widgetId: 'label' } })).toBe(false)
		expect(computed.isFullBleed.call({ placement: null })).toBe(false)
	})

	it('binds the files backend only onto the files renderer', () => {
		expect(computed.rendererProps.call({ placement: { widgetId: 'files' } })).toEqual({ apiBase: '/apps/launchpad' })
		expect(computed.rendererProps.call({ placement: { widgetId: 'label' } })).toEqual({})
	})

	it('treats a tile- widget id or a tileType as a tile', () => {
		expect(computed.isTileWidget.call({ placement: { widgetId: 'tile-3' } })).toBe(true)
		expect(computed.isTileWidget.call({ placement: { widgetId: 'mydash-tile', tileType: 'link' } })).toBe(true)
		expect(computed.isTileWidget.call({ placement: { widgetId: 'recommendations' } })).toBe(false)
		expect(computed.isTileWidget.call({ placement: { widgetId: null } })).toBe(false)
	})

	it('parses the tile id from the widget id, null for a non-tile', () => {
		expect(computed.tileId.call({ isTileWidget: true, placement: { widgetId: 'tile-17' } })).toBe(17)
		expect(computed.tileId.call({ isTileWidget: false, placement: { widgetId: 'tile-17' } })).toBeNull()
	})

	it('builds an inline tile from the placement', () => {
		const placement = {
			id: 9,
			tileType: 'link',
			tileTitle: 'Mail',
			tileIcon: 'mail',
			tileIconType: 'mdi',
			tileBackgroundColor: '#000',
			tileTextColor: '#fff',
			tileLinkType: 'url',
			tileLinkValue: 'https://example.org',
		}
		expect(computed.tileData.call({ isTileWidget: true, placement })).toEqual({
			id: 9,
			title: 'Mail',
			icon: 'mail',
			iconType: 'mdi',
			backgroundColor: '#000',
			textColor: '#fff',
			linkType: 'url',
			linkValue: 'https://example.org',
		})
	})

	it('resolves a referenced tile from the tile store, and none for a non-tile', () => {
		useTileStore().tiles = [{ id: 3, title: 'Three' }, { id: 4, title: 'Four' }]

		expect(computed.tileData.call({ isTileWidget: true, tileId: 4, placement: { widgetId: 'tile-4' } })).toEqual({ id: 4, title: 'Four' })
		expect(computed.tileData.call({ isTileWidget: true, tileId: 5, placement: { widgetId: 'tile-5' } })).toBeUndefined()
		expect(computed.tileData.call({ isTileWidget: false, placement: { widgetId: 'x' } })).toBeNull()
	})

	it('flags API widgets by their item API versions', () => {
		const v1 = { widget: { itemApiVersions: [1] } }
		const v2 = { widget: { itemApiVersions: [2] } }
		expect(computed.isApiWidgetV1.call(v1)).toBe(true)
		expect(computed.isApiWidgetV2.call(v1)).toBe(false)
		expect(computed.isApiWidgetV2.call(v2)).toBe(true)
		expect(computed.isApiWidgetV1.call({ widget: null })).toBeUndefined()

		expect(computed.isApiWidget.call({ isApiWidgetV1: false, isApiWidgetV2: true })).toBe(true)
		expect(computed.isApiWidget.call({ isApiWidgetV1: undefined, isApiWidgetV2: undefined })).toBeUndefined()
	})

	it('answers the local items data and its empty message', () => {
		const data = { items: [1], emptyContentMessage: 'Nothing here' }
		expect(computed.widgetItemsData.call({ localWidgetItemsData: data })).toBe(data)
		expect(computed.emptyContentMessage.call({ widgetItemsData: data })).toBe('Nothing here')
		expect(computed.emptyContentMessage.call({ widgetItemsData: { items: [] } })).toBe('')
	})
})

describe('WidgetRenderer watchers and lifecycle', () => {
	it('reinitialises on a new widget, or for a tile, but not when the widget goes away', () => {
		const ctx = instance({ isTileWidget: false, initWidget: vi.fn() })
		watch.widget.handler.call(ctx, { id: 'w' })
		expect(ctx.initWidget).toHaveBeenCalledTimes(1)

		watch.widget.handler.call(ctx, null)
		expect(ctx.initWidget).toHaveBeenCalledTimes(1)

		ctx.isTileWidget = true
		watch.widget.handler.call(ctx, null)
		expect(ctx.initWidget).toHaveBeenCalledTimes(2)
	})

	it('clears loading when a tile placement changes, and leaves it otherwise', () => {
		const tile = instance({ isTileWidget: true, loading: true, placement: {} })
		watch.placement.handler.call(tile)
		expect(tile.loading).toBe(false)

		const other = instance({ isTileWidget: false, loading: true, placement: {} })
		watch.placement.handler.call(other)
		expect(other.loading).toBe(true)
	})

	it('subscribes on mount and initialises a widget or tile, but never a registry widget', () => {
		const make = (state) => instance({ setupStoreSubscription: vi.fn(), initWidget: vi.fn(), ...state })

		const registry = make({ registryEntry: { renderer: {} }, widget: { id: 'w' }, isTileWidget: false })
		WidgetRenderer.mounted.call(registry)
		expect(registry.setupStoreSubscription).toHaveBeenCalledTimes(1)
		expect(registry.initWidget).not.toHaveBeenCalled()

		const api = make({ registryEntry: null, widget: { id: 'w' }, isTileWidget: false })
		WidgetRenderer.mounted.call(api)
		expect(api.initWidget).toHaveBeenCalledTimes(1)

		const tile = make({ registryEntry: null, widget: null, isTileWidget: true })
		WidgetRenderer.mounted.call(tile)
		expect(tile.initWidget).toHaveBeenCalledTimes(1)

		const nothing = make({ registryEntry: null, widget: null, isTileWidget: false })
		WidgetRenderer.mounted.call(nothing)
		expect(nothing.setupStoreSubscription).toHaveBeenCalledTimes(1)
		expect(nothing.initWidget).not.toHaveBeenCalled()
	})

	it('clears the refresh timer and unsubscribes before unmount', () => {
		const clear = vi.spyOn(globalThis, 'clearInterval')
		const ctx = instance({ refreshInterval: 1234, unsubscribe: vi.fn() })
		WidgetRenderer.beforeUnmount.call(ctx)
		expect(clear).toHaveBeenCalledWith(1234)
		expect(ctx.unsubscribe).toHaveBeenCalledTimes(1)

		clear.mockClear()
		WidgetRenderer.beforeUnmount.call(instance({ refreshInterval: null, unsubscribe: undefined }))
		expect(clear).not.toHaveBeenCalled()
	})
})

describe('WidgetRenderer widget-store sync', () => {
	it('copies its own widget items from the store on a store change, ignoring other widgets', async () => {
		const store = useWidgetStore()
		const ctx = instance({ widget: { id: 'w1' } })
		ctx.setupStoreSubscription()

		store.$patch((state) => {
			state.widgetItems.other = { items: [{ title: 'not mine' }], loading: false }
		})
		await nextTick()
		expect(ctx.localWidgetItemsData).toEqual({ items: [], loading: false })

		const data = { items: [{ title: 'mine' }], loading: false }
		store.$patch((state) => {
			state.widgetItems.w1 = data
		})
		await nextTick()
		expect(ctx.localWidgetItemsData).toEqual(data)
		expect(ctx.localWidgetItemsData).not.toBe(store.widgetItems.w1)

		ctx.unsubscribe()
		store.$patch((state) => {
			state.widgetItems.w1 = { items: [], loading: true }
		})
		await nextTick()
		expect(ctx.localWidgetItemsData).toEqual(data)
	})

	it('syncs its items from the store on request, only when the store has them', () => {
		const store = useWidgetStore()
		const ctx = instance({ widget: { id: 'w1' } })

		ctx.updateLocalWidgetItems()
		expect(ctx.localWidgetItemsData).toEqual({ items: [], loading: false })

		store.widgetItems.w1 = { items: [{ title: 'a' }], loading: false, emptyContentMessage: 'none' }
		ctx.updateLocalWidgetItems()
		expect(ctx.localWidgetItemsData).toEqual({ items: [{ title: 'a' }], loading: false, emptyContentMessage: 'none' })

		const noWidget = instance({ widget: null })
		noWidget.updateLocalWidgetItems()
		expect(noWidget.localWidgetItemsData).toEqual({ items: [], loading: false })
	})
})

describe('WidgetRenderer.setupAutoRefresh', () => {
	it('refreshes every interval, flagging itemsLoading until the refresh settles', async () => {
		vi.useFakeTimers()
		let settle
		const ctx = instance({
			widget: { id: 'w1' },
			refreshWidgetItems: vi.fn(() => new Promise((resolve) => {
				settle = resolve
			})),
		})

		ctx.setupAutoRefresh(30)
		await vi.advanceTimersByTimeAsync(29999)
		expect(ctx.refreshWidgetItems).not.toHaveBeenCalled()

		await vi.advanceTimersByTimeAsync(1)
		expect(ctx.refreshWidgetItems).toHaveBeenCalledWith('w1')
		expect(ctx.itemsLoading).toBe(true)

		settle()
		await vi.advanceTimersByTimeAsync(0)
		expect(ctx.itemsLoading).toBe(false)

		clearInterval(ctx.refreshInterval)
	})

	it('replaces an existing timer instead of stacking a second one', async () => {
		vi.useFakeTimers()
		const ctx = instance({ widget: { id: 'w1' }, refreshWidgetItems: vi.fn(() => Promise.resolve()) })

		ctx.setupAutoRefresh(10)
		const first = ctx.refreshInterval
		ctx.setupAutoRefresh(10)
		expect(ctx.refreshInterval).not.toBe(first)

		await vi.advanceTimersByTimeAsync(10000)
		expect(ctx.refreshWidgetItems).toHaveBeenCalledTimes(1)

		clearInterval(ctx.refreshInterval)
	})
})
