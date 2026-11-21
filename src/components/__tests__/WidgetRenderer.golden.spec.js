/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Characterisation tests pinning WidgetRenderer's item mapping,
 * `initWidget` (tile, API and legacy paths, loading flags, error log) and
 * the legacy mount retry loop, run against the component options with a
 * stand-in `this`, so these can be split into named steps without
 * changing behaviour.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import WidgetRenderer from '../WidgetRenderer.vue'
import { widgetBridge } from '../../services/widgetBridge.js'
import { logger } from '../../utils/logger.js'

vi.mock('../../services/widgetBridge.js', () => ({
	widgetBridge: {
		hasWidgetCallback: vi.fn(),
		mountWidget: vi.fn(),
		getRegisteredWidgetIds: vi.fn(() => ['a', 'b']),
	},
}))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), error: vi.fn(), warn: vi.fn(), info: vi.fn() },
}))

/**
 * A stand-in component instance with every method bound to it.
 *
 * @param {object} state Data, computed values and overrides.
 * @return {object} the instance.
 */
function instance(state) {
	const ctx = {
		loading: false,
		$nextTick: (cb) => {
			if (cb) {
				cb()
			}
			return Promise.resolve()
		},
		$refs: {},
	}
	for (const [name, fn] of Object.entries(WidgetRenderer.methods)) {
		ctx[name] = fn.bind(ctx)
	}
	return Object.assign(ctx, {
		loadWidgetItems: vi.fn(() => Promise.resolve()),
		updateLocalWidgetItems: vi.fn(),
		setupAutoRefresh: vi.fn(),
		mountLegacyWidget: vi.fn(),
	}, state)
}

beforeEach(() => {
	vi.clearAllMocks()
})

afterEach(() => {
	vi.useRealTimers()
})

describe('WidgetRenderer.widgetItems', () => {
	it('maps API items onto the dashboard item props with stable keys', () => {
		const items = [
			{ sinceId: 's', id: 'i', link: '/l', iconUrl: '/i.png', title: 'T', subtitle: 'S', extra: 1 },
			{ targetUrl: '/t', avatarUrl: '/a', avatarUsername: 'u', overlayIconUrl: '/o', mainText: 'M', subText: 'X' },
			{ title: 'only title' },
			{},
			{ id: 0, sinceId: 0, link: '', title: '' },
		]
		const mapped = WidgetRenderer.computed.widgetItems.call({ widgetItemsData: { items }, widget: { id: 'w' } })
		expect(mapped).toEqual([
			{ ...items[0], id: 's-i-/l', targetUrl: '/l', avatarUrl: '/i.png', avatarUsername: '', overlayIconUrl: '', mainText: 'T', subText: 'S' },
			{ ...items[1], id: '--/t', targetUrl: '/t', avatarUrl: '/a', avatarUsername: 'u', overlayIconUrl: '/o', mainText: 'M', subText: 'X' },
			{ title: 'only title', id: '--only title', targetUrl: '', avatarUrl: '', avatarUsername: '', overlayIconUrl: '', mainText: 'only title', subText: '' },
			{ id: '--3', targetUrl: '', avatarUrl: '', avatarUsername: '', overlayIconUrl: '', mainText: '', subText: '' },
			{ sinceId: 0, link: '', title: '', id: '--4', targetUrl: '', avatarUrl: '', avatarUsername: '', overlayIconUrl: '', mainText: '', subText: '' },
		])
		expect(logger.debug).toHaveBeenCalledWith('[WidgetRenderer] widgetItems computed:', {
			widgetId: 'w',
			rawItems: items,
			itemsLength: 5,
			widgetItemsData: { items },
		})
	})

	it('reads missing items as an empty list', () => {
		expect(WidgetRenderer.computed.widgetItems.call({ widgetItemsData: {}, widget: null })).toEqual([])
	})
})

describe('WidgetRenderer.initWidget', () => {
	it('stops without a widget or for a tile', async () => {
		const none = instance({ widget: null, isTileWidget: false, isApiWidget: false, loading: true })
		await none.initWidget()
		expect(none.loading).toBe(false)
		expect(none.loadWidgetItems).not.toHaveBeenCalled()

		const tile = instance({ widget: null, isTileWidget: true, isApiWidget: false, loading: true })
		await tile.initWidget()
		expect(tile.loading).toBe(false)
		expect(tile.mountLegacyWidget).not.toHaveBeenCalled()
		expect(logger.debug).toHaveBeenCalledTimes(2)
	})

	it('loads an API widget, syncs it, arms refresh and clears loading', async () => {
		const widget = { id: 'w', reloadInterval: 30 }
		const ctx = instance({ widget, isTileWidget: false, isApiWidget: true, isApiWidgetV1: true, isApiWidgetV2: false })
		ctx.loadWidgetItems = vi.fn(() => {
			expect(ctx.loading).toBe(true)
			return Promise.resolve()
		})
		await ctx.initWidget()
		expect(ctx.loadWidgetItems).toHaveBeenCalledWith(['w'])
		expect(ctx.updateLocalWidgetItems).toHaveBeenCalledTimes(1)
		expect(ctx.setupAutoRefresh).toHaveBeenCalledWith(30)
		expect(ctx.loading).toBe(false)
		expect(logger.debug.mock.calls.map((c) => c[0])).toEqual([
			'[WidgetRenderer] initWidget called:',
			'[WidgetRenderer] Initializing widget:',
			'[WidgetRenderer] Detected as API widget',
		])
		expect(logger.debug.mock.calls[0][1]).toEqual({
			widgetId: 'w',
			isTileWidget: false,
			isApiWidget: true,
			isApiWidgetV1: true,
			isApiWidgetV2: false,
			itemApiVersions: undefined,
			fullWidget: widget,
		})
	})

	it('arms no refresh without a positive interval and logs a failed load', async () => {
		const ok = instance({ widget: { id: 'w', reloadInterval: 0 }, isTileWidget: false, isApiWidget: true })
		await ok.initWidget()
		expect(ok.setupAutoRefresh).not.toHaveBeenCalled()

		const error = new Error('x')
		const bad = instance({ widget: { id: 'w', reloadInterval: 5 }, isTileWidget: false, isApiWidget: true })
		bad.loadWidgetItems = vi.fn(() => Promise.reject(error))
		await bad.initWidget()
		expect(logger.error).toHaveBeenCalledWith('Failed to initialize widget:', error)
		expect(bad.updateLocalWidgetItems).not.toHaveBeenCalled()
		expect(bad.loading).toBe(false)
	})

	it('mounts a legacy widget after a tick and 50 ms, never touching loading', async () => {
		vi.useFakeTimers()
		const ctx = instance({ widget: { id: 'legacy' }, isTileWidget: false, isApiWidget: false, loading: 'untouched' })
		const done = ctx.initWidget()
		await Promise.resolve()
		await vi.advanceTimersByTimeAsync(49)
		expect(ctx.mountLegacyWidget).not.toHaveBeenCalled()
		await vi.advanceTimersByTimeAsync(1)
		await done
		expect(ctx.mountLegacyWidget).toHaveBeenCalledTimes(1)
		expect(ctx.loading).toBe('untouched')
		expect(logger.debug).toHaveBeenCalledWith('[WidgetRenderer] Legacy widget detected:', 'legacy')

		ctx.mountLegacyWidget = vi.fn(() => {
			throw new Error('mount')
		})
		const failing = ctx.initWidget()
		await vi.advanceTimersByTimeAsync(50)
		await failing
		expect(logger.error).toHaveBeenCalledWith('Failed to initialize widget:', new Error('mount'))
		expect(ctx.loading).toBe('untouched')
	})
})

describe('WidgetRenderer.mountLegacyWidget', () => {
	function legacyInstance() {
		const ctx = instance({ widget: { id: 'lw' } })
		ctx.mountLegacyWidget = WidgetRenderer.methods.mountLegacyWidget.bind(ctx)
		ctx.$refs = { legacyWidgetContainer: { innerHTML: '<b>x</b>' } }
		return ctx
	}

	it('logs and stops without a container', () => {
		const ctx = legacyInstance()
		ctx.$refs = {}
		ctx.mountLegacyWidget()
		expect(logger.error).toHaveBeenCalledWith('[WidgetRenderer] No legacyWidgetContainer ref found!')
		expect(widgetBridge.hasWidgetCallback).not.toHaveBeenCalled()
	})

	it('mounts at once when the callback is registered', () => {
		widgetBridge.hasWidgetCallback.mockReturnValue(true)
		const ctx = legacyInstance()
		ctx.mountLegacyWidget()
		expect(widgetBridge.mountWidget).toHaveBeenCalledWith('lw', ctx.$refs.legacyWidgetContainer, ctx.widget)
		expect(logger.debug.mock.calls.map((c) => c[0])).toEqual([
			'[WidgetRenderer] Mounting legacy widget:',
			'[WidgetRenderer] Mount attempt 1/20 for:',
			'[WidgetRenderer] Callback found! Mounting:',
			'[WidgetRenderer] After mountWidget, container innerHTML length:',
		])
		expect(logger.debug.mock.calls[3][1]).toBe(8)
	})

	it('retries with growing delays and gives up after 20 retries', async () => {
		vi.useFakeTimers()
		widgetBridge.hasWidgetCallback.mockReturnValue(false)
		const ctx = legacyInstance()
		ctx.mountLegacyWidget()
		const delays = [100, 200, 300, 400, 500, 600, 700, 800, 900, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000]
		for (const delay of delays) {
			await vi.advanceTimersByTimeAsync(delay - 1)
			const calls = widgetBridge.hasWidgetCallback.mock.calls.length
			await vi.advanceTimersByTimeAsync(1)
			expect(widgetBridge.hasWidgetCallback.mock.calls.length).toBe(calls + 1)
		}
		expect(widgetBridge.hasWidgetCallback).toHaveBeenCalledTimes(21)
		expect(logger.debug).toHaveBeenCalledWith('[WidgetRenderer] Mount attempt 21/20 for:', 'lw')
		expect(logger.debug).toHaveBeenCalledWith('[WidgetRenderer] Callback not found yet, retrying in 1000ms...')
		expect(logger.error).toHaveBeenCalledWith('[WidgetRenderer] Failed to mount widget after', 20, 'attempts:', 'lw')
		expect(logger.debug).toHaveBeenLastCalledWith('[WidgetRenderer] Available callbacks:', ['a', 'b'])
		await vi.advanceTimersByTimeAsync(5000)
		expect(widgetBridge.hasWidgetCallback).toHaveBeenCalledTimes(21)
		expect(widgetBridge.mountWidget).not.toHaveBeenCalled()
	})

	it('mounts on a later attempt once the callback appears', async () => {
		vi.useFakeTimers()
		widgetBridge.hasWidgetCallback.mockReturnValueOnce(false).mockReturnValueOnce(false).mockReturnValue(true)
		const ctx = legacyInstance()
		ctx.mountLegacyWidget()
		await vi.advanceTimersByTimeAsync(300)
		expect(widgetBridge.mountWidget).toHaveBeenCalledTimes(1)
		expect(logger.debug).toHaveBeenCalledWith('[WidgetRenderer] Mount attempt 3/20 for:', 'lw')
	})
})
