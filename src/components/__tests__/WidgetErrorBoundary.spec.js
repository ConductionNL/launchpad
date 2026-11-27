/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for widgets REQ-WDG-046: a widget's card shows a loading
 * state until its content mounts, and a widget that fails to show keeps the
 * failure inside its own card (with Retry) while its neighbours render.
 *
 * @spec openspec/specs/widgets/spec.md#requirement-every-widget-shows-its-own-loading-and-error-state-req-wdg-046
 */

import { mount, shallowMount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick } from 'vue'
import LazyMount, { LazyMountSupport } from '../LazyMount.vue'
import WidgetErrorBoundary, { isShowFailure } from '../WidgetErrorBoundary.vue'
import WidgetWrapper from '../WidgetWrapper.vue'
import { logger } from '../../utils/logger.js'

/** An IntersectionObserver that never reports. */
class SilentObserver {
	observe() {}
	disconnect() {}
}

/** A widget that throws while it renders, as long as `broken.on` holds. */
const broken = { on: true }
const Breaks = defineComponent({
	name: 'Breaks',
	render() {
		if (broken.on) {
			throw new Error('cannot render')
		}
		return h('p', { class: 'healed' }, 'healed')
	},
})

/** A widget whose click handler throws. */
const ClickBreaks = defineComponent({
	name: 'ClickBreaks',
	render() {
		return h('button', {
			class: 'clicker',
			onClick: () => {
				throw new Error('click failed')
			},
		}, 'click')
	},
})

/*
 * The library is a bare stub under vitest (see vitest.config.js), so the
 * empty state and the button render their slots here.
 */
const global = {
	mocks: { t: (_app, key) => key },
	config: { errorHandler: () => {} },
	stubs: {
		NcEmptyContent: { template: '<div><slot name="icon" /><slot name="action" /></div>' },
		NcButton: { emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
	},
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	broken.on = true
	vi.spyOn(logger, 'error').mockImplementation(() => {})
})

afterEach(() => {
	vi.restoreAllMocks()
})

describe('LazyMount leaves the waiting state to the card', () => {
	it('draws no box or status of its own while it waits', () => {
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(SilentObserver)
		vi.spyOn(LazyMountSupport, 'whenSettled').mockReturnValue(() => {})
		const wrapper = mount(LazyMount, {
			slots: { default: () => h('p', { class: 'content' }, 'content') },
			global,
		})

		expect(wrapper.find('[data-testid="widget-loading"]').exists()).toBe(false)
		expect(wrapper.find('[role="status"]').exists()).toBe(false)
		expect(wrapper.find('.content').exists()).toBe(false)
		expect(wrapper.emitted('shown')).toBeUndefined()
	})

	it('says it is shown once mounted', () => {
		vi.spyOn(LazyMountSupport, 'observer').mockReturnValue(null)
		const wrapper = mount(LazyMount, {
			slots: { default: () => h('p', { class: 'content' }, 'content') },
			global,
		})

		expect(wrapper.find('.content').exists()).toBe(true)
		expect(wrapper.emitted('shown')).toHaveLength(1)
	})
})

describe('WidgetErrorBoundary', () => {
	it('tells a failure to show from a failed event handler or watcher', () => {
		expect(isShowFailure('https://vuejs.org/error-reference/#runtime-0')).toBe(true)
		expect(isShowFailure('https://vuejs.org/error-reference/#runtime-1')).toBe(true)
		expect(isShowFailure('https://vuejs.org/error-reference/#runtime-m')).toBe(true)
		expect(isShowFailure('https://vuejs.org/error-reference/#runtime-bm')).toBe(true)
		expect(isShowFailure('https://vuejs.org/error-reference/#runtime-5')).toBe(false)
		expect(isShowFailure('https://vuejs.org/error-reference/#runtime-6')).toBe(false)
		expect(isShowFailure('https://vuejs.org/error-reference/#runtime-3')).toBe(false)
		expect(isShowFailure('render function')).toBe(true)
		expect(isShowFailure('setup function')).toBe(true)
		expect(isShowFailure('mounted hook')).toBe(true)
		expect(isShowFailure('component event handler')).toBe(false)
		expect(isShowFailure('watcher callback')).toBe(false)
		expect(isShowFailure(undefined)).toBe(false)
	})

	it('keeps a widget that fails to render inside its card, beside a working one', async () => {
		const page = mount({
			render: () => h('div', [
				h(WidgetErrorBoundary, null, { default: () => h(Breaks) }),
				h(WidgetErrorBoundary, null, { default: () => h('p', { class: 'neighbour' }, 'fine') }),
			]),
		}, { global })
		await nextTick()

		expect(page.find('[data-testid="widget-error"]').exists()).toBe(true)
		expect(page.findAll('[data-testid="widget-error"]')).toHaveLength(1)
		expect(page.find('.neighbour').exists()).toBe(true)
		expect(logger.error).toHaveBeenCalledOnce()
	})

	it('mounts the widget afresh on Retry', async () => {
		const wrapper = mount(WidgetErrorBoundary, {
			slots: { default: () => h(Breaks) },
			global,
		})
		await nextTick()
		expect(wrapper.attributes('data-failed')).toBe('true')

		broken.on = false
		await wrapper.find('[data-testid="widget-error-retry"]').trigger('click')
		await nextTick()

		expect(wrapper.attributes('data-failed')).toBe('false')
		expect(wrapper.find('.healed').exists()).toBe(true)
	})

	it('leaves a failed click to the app and keeps the widget', async () => {
		const errorHandler = vi.fn()
		const wrapper = mount(WidgetErrorBoundary, {
			slots: { default: () => h(ClickBreaks) },
			global: { ...global, config: { errorHandler } },
		})

		await wrapper.find('.clicker').trigger('click')

		expect(errorHandler).toHaveBeenCalledOnce()
		expect(wrapper.attributes('data-failed')).toBe('false')
		expect(wrapper.find('.clicker').exists()).toBe(true)
	})
})

describe('WidgetWrapper puts its content inside the boundary', () => {
	it('wraps the renderer in a WidgetErrorBoundary inside LazyMount', () => {
		const wrapper = shallowMount(WidgetWrapper, {
			propsData: {
				placement: { id: 1, widgetId: 'data', showTitle: true },
				widget: { title: 'Widget' },
				editMode: true,
			},
			mocks: { t: (_app, key) => key },
			global: { stubs: { CnWidgetWrapper: { template: '<div><slot /></div>' }, LazyMount: { template: '<div><slot /></div>' } } },
		})

		const boundary = wrapper.findComponent(WidgetErrorBoundary)
		expect(boundary.exists()).toBe(true)
		expect(wrapper.findComponent(LazyMount).findComponent(WidgetErrorBoundary).exists()).toBe(true)
	})
})
