/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for `LazyMount.vue` and the widget wrapper's use of it
 * (widgets REQ-WDG-044): a widget's content below the fold mounts when it
 * comes into view, once the page has settled, or before printing; at once
 * when eager or without IntersectionObserver; and stays mounted.
 *
 * @spec openspec/changes/archive/2026-11-25-widgets-below-the-fold-load-on-view/specs/widgets/spec.md#requirement-a-widget-below-the-fold-loads-when-it-comes-into-view-req-wdg-044
 */

import { mount, shallowMount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import LazyMount, {
	IDLE_TIMEOUT_MS,
	LazyMountSupport,
	ROOT_MARGIN,
	SETTLE_DELAY_MS,
} from '../LazyMount.vue'
import WidgetWrapper, { isExportRender } from '../WidgetWrapper.vue'

/** A stand-in IntersectionObserver whose instances the test drives. */
class FakeObserver {
	static instances = []

	constructor(callback, options) {
		this.callback = callback
		this.options = options
		this.observed = []
		this.disconnected = false
		FakeObserver.instances.push(this)
	}

	observe(el) {
		this.observed.push(el)
	}

	disconnect() {
		this.disconnected = true
	}

	fire(isIntersecting) {
		this.callback([{ isIntersecting }])
	}
}

function mountLazy(props = {}) {
	return mount(LazyMount, {
		props,
		slots: { default: () => h('p', { class: 'content' }, 'content') },
	})
}

beforeEach(() => {
	FakeObserver.instances = []
	globalThis.t = (_app, key) => key
})

afterEach(() => {
	vi.restoreAllMocks()
	vi.useRealTimers()
})

describe('LazyMount', () => {
	it('mounts at once without IntersectionObserver', () => {
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(null)
		const wrapper = mountLazy()
		expect(wrapper.find('.content').exists()).toBe(true)
	})

	it('mounts at once when eager, without watching', () => {
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(FakeObserver)
		const wrapper = mountLazy({ eager: true })
		expect(wrapper.find('.content').exists()).toBe(true)
		expect(FakeObserver.instances).toHaveLength(0)
	})

	it('waits until the element comes within one viewport height', async () => {
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(FakeObserver)
		const wrapper = mountLazy()
		expect(wrapper.find('.content').exists()).toBe(false)
		expect(wrapper.attributes('data-mounted')).toBe('false')

		const observer = FakeObserver.instances[0]
		expect(observer.options).toEqual({ rootMargin: ROOT_MARGIN })
		expect(observer.observed).toEqual([wrapper.element])

		observer.fire(false)
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.content').exists()).toBe(false)

		observer.fire(true)
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.content').exists()).toBe(true)
		expect(observer.disconnected).toBe(true)
	})

	it('stays mounted once shown', async () => {
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(FakeObserver)
		const wrapper = mountLazy()
		FakeObserver.instances[0].fire(true)
		await wrapper.vm.$nextTick()
		FakeObserver.instances[0].fire(false)
		await wrapper.setProps({ eager: false })
		expect(wrapper.find('.content').exists()).toBe(true)
	})

	it('mounts once the page has settled, at the next idle moment', async () => {
		vi.useFakeTimers()
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(FakeObserver)
		const idle = vi.fn((cb) => {
			setTimeout(cb, 0)
			return 7
		})
		window.requestIdleCallback = idle
		window.cancelIdleCallback = vi.fn()
		const wrapper = mountLazy()

		vi.advanceTimersByTime(SETTLE_DELAY_MS - 1)
		await wrapper.vm.$nextTick()
		expect(idle).not.toHaveBeenCalled()
		expect(wrapper.find('.content').exists()).toBe(false)

		vi.advanceTimersByTime(1)
		expect(idle).toHaveBeenCalledWith(expect.any(Function), { timeout: IDLE_TIMEOUT_MS })
		vi.advanceTimersByTime(1)
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.content').exists()).toBe(true)
		delete window.requestIdleCallback
		delete window.cancelIdleCallback
	})

	it('mounts once the page has settled without requestIdleCallback', async () => {
		vi.useFakeTimers()
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(FakeObserver)
		const wrapper = mountLazy()
		vi.advanceTimersByTime(SETTLE_DELAY_MS)
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.content').exists()).toBe(true)
	})

	it('mounts before the page prints', async () => {
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(FakeObserver)
		const wrapper = mountLazy()
		window.dispatchEvent(new Event('beforeprint'))
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.content').exists()).toBe(true)
	})

	it('mounts when it becomes eager', async () => {
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(FakeObserver)
		const wrapper = mountLazy()
		await wrapper.setProps({ eager: true })
		expect(wrapper.find('.content').exists()).toBe(true)
		expect(FakeObserver.instances[0].disconnected).toBe(true)
	})

	it('stops watching when unmounted', () => {
		vi.useFakeTimers()
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(FakeObserver)
		const remove = vi.spyOn(window, 'removeEventListener')
		const wrapper = mountLazy()
		wrapper.unmount()
		expect(FakeObserver.instances[0].disconnected).toBe(true)
		expect(remove).toHaveBeenCalledWith('beforeprint', expect.any(Function))
		expect(vi.getTimerCount()).toBe(0)
	})
})

describe('WidgetWrapper mounts its content through LazyMount', () => {
	function mountWrapper(editMode) {
		return shallowMount(WidgetWrapper, {
			propsData: {
				placement: { id: 1, widgetId: 'data', showTitle: true },
				widget: { title: 'Widget' },
				editMode,
			},
			mocks: { t: (_app, key) => key },
			global: { stubs: { CnWidgetWrapper: { template: '<div><slot /></div>' } } },
		})
	}

	it('defers in view mode', () => {
		const lazy = mountWrapper(false).findComponent(LazyMount)
		expect(lazy.exists()).toBe(true)
		expect(lazy.props('eager')).toBe(false)
	})

	it('mounts at once in edit mode', () => {
		expect(mountWrapper(true).findComponent(LazyMount).props('eager')).toBe(true)
	})

	it('knows the headless export render by ?export=1', () => {
		expect(isExportRender({ search: '?export=1&subject=dashboard&id=4' })).toBe(true)
		expect(isExportRender({ search: '?export=0' })).toBe(false)
		expect(isExportRender({ search: '' })).toBe(false)
		expect(isExportRender(null)).toBe(false)
	})
})

describe('LazyMount rootMargin', () => {
	it('watches one viewport ahead by default, and only the visible area with rootMargin 0px', () => {
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(FakeObserver)
		vi.spyOn(LazyMountSupport, 'whenSettled').mockReturnValue(() => {})
		mountLazy()
		mountLazy({ rootMargin: '0px' })
		expect(FakeObserver.instances.map((observer) => observer.options.rootMargin)).toEqual([ROOT_MARGIN, '0px'])
	})
})
