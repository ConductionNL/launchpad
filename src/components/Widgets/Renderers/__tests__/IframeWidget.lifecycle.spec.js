/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `IframeWidget.vue` (REQ-IFRAME-003/004) lifecycle:
 * aspect-ratio sizing, restarting the framable check when the URL changes
 * (a superseded verdict is ignored), clearing the load timer on unmount, and
 * ignoring a late `load` event once the embed has settled.
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import IframeWidget from '../IframeWidget.vue'
import { checkIframeFramable } from '../../../../services/iframeClient.js'

vi.mock('../../../../services/iframeClient.js', () => ({
	checkIframeFramable: vi.fn(() =>
		Promise.resolve({ framable: true, reason: 'ok' }),
	),
}))

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

function deferred() {
	let resolve
	const promise = new Promise((res) => {
		resolve = res
	})
	return { promise, resolve }
}

beforeEach(() => {
	checkIframeFramable.mockReset()
	checkIframeFramable.mockResolvedValue({ framable: true, reason: 'ok' })
	globalThis.t = (_app, key, vars) => {
		if (vars && typeof key === 'string') {
			return key.replace(/\{(\w+)\}/g, (_, name) =>
				Object.hasOwn(vars, name) ? vars[name] : `{${name}}`,
			)
		}
		return key
	}
})

afterEach(() => {
	vi.restoreAllMocks()
})

describe('IframeWidget — sizing', () => {
	it('sizes the wrapper by aspect ratio with an automatic height', () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: { url: 'https://a.example.com/', aspect: '16:9', height: 250 },
			},
		})
		expect(wrapper.attributes('style')).toContain('aspect-ratio: 16 / 9')
		expect(wrapper.element.style.height).toBe('auto')
	})

	it('uses the configured fixed height when no aspect ratio is set', () => {
		const wrapper = mount(IframeWidget, {
			propsData: { content: { url: 'https://a.example.com/', height: 250 } },
		})
		expect(wrapper.element.style.height).toBe('250px')
	})
})

describe('IframeWidget — REQ-IFRAME-003 re-check on URL change', () => {
	it('re-checks the new URL and ignores the superseded verdict for the old one', async () => {
		const first = deferred()
		checkIframeFramable.mockReturnValueOnce(first.promise)
		const wrapper = mount(IframeWidget, {
			propsData: { content: { url: 'https://old.example.com/', title: 'Old' } },
		})
		expect(checkIframeFramable).toHaveBeenCalledWith('https://old.example.com/')

		await wrapper.setProps({
			content: { url: 'https://new.example.com/', title: 'New' },
		})
		expect(checkIframeFramable).toHaveBeenLastCalledWith(
			'https://new.example.com/',
		)
		expect(checkIframeFramable).toHaveBeenCalledTimes(2)
		await flushPromises()
		expect(wrapper.find('iframe').attributes('src')).toBe(
			'https://new.example.com/',
		)

		// The old target's late "refuses framing" verdict must not flip the
		// new, framable embed into the fallback card.
		first.resolve({ framable: false, reason: 'x-frame-options' })
		await flushPromises()
		expect(wrapper.find('.iframe-widget__fallback').exists()).toBe(false)
		expect(wrapper.find('iframe').exists()).toBe(true)
	})

	it('switches to the not-configured fallback when the URL is cleared', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: { content: { url: 'https://a.example.com/' } },
		})
		await flushPromises()
		expect(wrapper.find('iframe').exists()).toBe(true)
		await wrapper.setProps({ content: { url: '   ' } })
		expect(wrapper.find('iframe').exists()).toBe(false)
		expect(wrapper.find('.iframe-widget__fallback').text()).toContain(
			'This embed has not been configured yet.',
		)
		expect(checkIframeFramable).toHaveBeenCalledTimes(1)
	})
})

describe('IframeWidget — REQ-IFRAME-004 timer and late load events', () => {
	it('clears the pending load-timeout timer on unmount', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: { url: 'https://a.example.com/' },
				loadTimeoutMs: 60000,
			},
		})
		await flushPromises()
		const timer = wrapper.vm.loadTimer
		expect(timer).not.toBeNull()
		const clearSpy = vi.spyOn(globalThis, 'clearTimeout')
		wrapper.unmount()
		expect(clearSpy).toHaveBeenCalledWith(timer)
	})

	it('ignores a blank-document load event once the embed is already ready', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: { content: { url: 'https://a.example.com/', title: 'A' } },
		})
		await flushPromises()
		const frame = wrapper.find('iframe')
		Object.defineProperty(frame.element, 'contentDocument', {
			configurable: true,
			get: () => null,
		})
		await frame.trigger('load')
		expect(frame.element.style.display).toBe('block')

		// The embedded page navigates to an empty same-origin document later;
		// that must not turn a working embed into the "blocked" fallback.
		Object.defineProperty(frame.element, 'contentDocument', {
			configurable: true,
			get: () => ({ body: { children: [], textContent: '' } }),
		})
		await frame.trigger('load')
		await flushPromises()
		expect(wrapper.find('.iframe-widget__fallback').exists()).toBe(false)
		expect(wrapper.find('iframe').element.style.display).toBe('block')
	})
})
