/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `IframeWidget.vue` (REQ-IFRAME-001..004): sandbox
 * attributes, accessible title, the graceful-degradation fallback card on
 * load-timeout / blocked-frame detection, and the loading/generic-error
 * states. `loadTimeoutMs` is overridden to a small value so the timeout
 * path can be exercised without a real multi-second wait.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import IframeWidget from '../IframeWidget.vue'
import { checkIframeFramable } from '../../../../services/iframeClient.js'

// The widget asks the server whether a target may be framed before rendering
// the iframe (REQ-IFRAME-003); mock it so tests are deterministic and never
// make a real request. Defaults to framable so the existing render/timeout
// tests behave as before; the framing-refusal test overrides it.
vi.mock('../../../../services/iframeClient.js', () => ({
	checkIframeFramable: vi.fn(() =>
		Promise.resolve({ framable: true, reason: 'ok' }),
	),
}))

const TEST_TIMEOUT_MS = 20

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeEach(() => {
	checkIframeFramable.mockClear()
	checkIframeFramable.mockResolvedValue({ framable: true, reason: 'ok' })
	globalThis.t = (_app, key, vars) => {
		if (vars && typeof key === 'string') {
			return key.replace(/\{(\w+)\}/g, (_, name) =>
				Object.prototype.hasOwnProperty.call(vars, name)
					? vars[name]
					: `{${name}}`,
			)
		}
		return key
	}
})

describe('IframeWidget — REQ-IFRAME-004 sandbox attribute', () => {
	// The iframe is only rendered after the async server framable check resolves
	// (REQ-IFRAME-003), so these tests flush the mocked check before asserting on
	// the frame — the default mock resolves framable:true.
	it('always carries a sandbox attribute limited to the configured tokens', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: {
					url: 'https://status.example.com/board',
					title: 'Status',
					sandbox: ['allow-scripts', 'allow-forms'],
				},
			},
		})
		await flushPromises()
		const frame = wrapper.find('iframe')
		expect(frame.exists()).toBe(true)
		expect(frame.attributes('sandbox')).toBe('allow-scripts allow-forms')
	})

	it('NEVER includes allow-top-navigation, even if present in the persisted content (defence-in-depth)', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: {
					url: 'https://status.example.com/board',
					title: 'Status',
					sandbox: [
						'allow-scripts',
						'allow-top-navigation',
						'allow-top-navigation-by-user-activation',
					],
				},
			},
		})
		await flushPromises()
		const sandbox = wrapper.find('iframe').attributes('sandbox')
		expect(sandbox).not.toContain('allow-top-navigation')
	})

	it('renders an empty sandbox attribute (still present) when no tokens are configured', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: {
					url: 'https://status.example.com/board',
					title: 'Status',
					sandbox: [],
				},
			},
		})
		await flushPromises()
		const frame = wrapper.find('iframe')
		expect(frame.attributes('sandbox')).toBe('')
	})
})

describe('IframeWidget — REQ-IFRAME-004 accessible title', () => {
	it('exposes the configured title via the iframe title attribute', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: {
					url: 'https://status.example.com/board',
					title: 'Team status board',
					sandbox: [],
				},
			},
		})
		await flushPromises()
		expect(wrapper.find('iframe').attributes('title')).toBe('Team status board')
	})

	it('falls back to a generic title when none is configured', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: { url: 'https://status.example.com/board', sandbox: [] },
			},
		})
		await flushPromises()
		expect(wrapper.find('iframe').attributes('title')).toBe('Embedded page')
	})
})

describe('IframeWidget — REQ-IFRAME-004 loading state', () => {
	it('shows a loading indicator before the frame has loaded', () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: {
					url: 'https://status.example.com/board',
					title: 'Status',
				},
			},
		})
		expect(wrapper.find('.iframe-widget__state').exists()).toBe(true)
		expect(wrapper.find('.iframe-widget__fallback').exists()).toBe(false)
	})
})

describe('IframeWidget — REQ-IFRAME-003 graceful degradation', () => {
	it('renders the fallback card up front when the server reports the target refuses framing (X-Frame-Options / frame-ancestors)', async () => {
		// The browser cannot detect an XFO/frame-ancestors block on its own
		// (a blocked frame and a live cross-origin embed both leave
		// contentDocument null), so the server-side framable check is what
		// surfaces the fallback instead of a permanently blank frame.
		checkIframeFramable.mockResolvedValue({
			framable: false,
			reason: 'x_frame_options',
		})
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: { url: 'https://github.com/', title: 'GitHub' },
				loadTimeoutMs: TEST_TIMEOUT_MS,
			},
		})
		await flushPromises()
		const fallback = wrapper.find('.iframe-widget__fallback')
		expect(fallback.exists()).toBe(true)
		expect(fallback.find('svg').exists()).toBe(true)
		expect(fallback.text()).toContain('GitHub')
		// The blocked target must not be rendered as a frame at all.
		expect(wrapper.find('iframe').exists()).toBe(false)
		expect(checkIframeFramable).toHaveBeenCalledWith('https://github.com/')
	})

	it('does NOT render the iframe while the framable check is still pending (prevents the blocked-frame load event from masking the fallback)', async () => {
		// Regression: a live XFO-deny target fires a browser "refused to
		// connect" load event with a null contentDocument that onLoad cannot
		// tell apart from a real cross-origin embed. If the iframe existed
		// before the async check resolved, that premature load would flip the
		// state to 'ready' and hide the fallback. The frame must not exist yet.
		let resolveCheck
		checkIframeFramable.mockReturnValue(
			new Promise((resolve) => {
				resolveCheck = resolve
			}),
		)
		const wrapper = mount(IframeWidget, {
			propsData: { content: { url: 'https://github.com/', title: 'GitHub' } },
		})
		await flushPromises()
		expect(wrapper.find('iframe').exists()).toBe(false)
		expect(wrapper.find('.iframe-widget__state').exists()).toBe(true)
		// Once the server says "blocked", the fallback appears — still no frame.
		resolveCheck({ framable: false, reason: 'x_frame_options' })
		await flushPromises()
		expect(wrapper.find('iframe').exists()).toBe(false)
		expect(wrapper.find('.iframe-widget__fallback').exists()).toBe(true)
	})

	it('renders the fallback card (never a silent blank frame) when no load event fires within the timeout', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: {
					url: 'https://denied.example.com/',
					title: 'Denied target',
				},
				loadTimeoutMs: TEST_TIMEOUT_MS,
			},
		})
		await new Promise((resolve) => setTimeout(resolve, TEST_TIMEOUT_MS + 30))
		await flushPromises()
		const fallback = wrapper.find('.iframe-widget__fallback')
		expect(fallback.exists()).toBe(true)
		// Icon AND text, never colour alone.
		expect(fallback.find('svg').exists()).toBe(true)
		expect(fallback.text()).toContain('Denied target')
	})

	it('renders an "Open in new tab" link in the fallback that is focusable and points at the target URL', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: {
					url: 'https://denied.example.com/',
					title: 'Denied target',
				},
				loadTimeoutMs: TEST_TIMEOUT_MS,
			},
		})
		await new Promise((resolve) => setTimeout(resolve, TEST_TIMEOUT_MS + 30))
		await flushPromises()
		const link = wrapper.find('.iframe-widget__fallback-link')
		expect(link.exists()).toBe(true)
		expect(link.attributes('href')).toBe('https://denied.example.com/')
		expect(link.attributes('target')).toBe('_blank')
		expect(link.attributes('rel')).toContain('noopener')
		expect(link.attributes('aria-label')).toContain('new tab')
	})

	it('detects a blocked frame from a load event yielding an empty same-origin document', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: {
					url: 'https://denied.example.com/',
					title: 'Denied target',
				},
			},
		})
		// The iframe renders only after the framable check confirms framing.
		await flushPromises()
		const frame = wrapper.find('iframe')
		// Simulate the same-origin "blocked" placeholder document shape.
		Object.defineProperty(frame.element, 'contentDocument', {
			configurable: true,
			get: () => ({ body: { children: [], textContent: '' } }),
		})
		await frame.trigger('load')
		await flushPromises()
		expect(wrapper.find('.iframe-widget__fallback').exists()).toBe(true)
	})

	it('treats a load event with an inaccessible (cross-origin) document as SUCCESS, not a failure', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: {
				content: {
					url: 'https://allowed.example.com/',
					title: 'Allowed target',
				},
			},
		})
		// The iframe renders only after the framable check confirms framing.
		await flushPromises()
		const frame = wrapper.find('iframe')
		Object.defineProperty(frame.element, 'contentDocument', {
			configurable: true,
			get: () => {
				throw new DOMException(
					'Blocked a frame with origin from accessing a cross-origin frame.',
					'SecurityError',
				)
			},
		})
		await frame.trigger('load')
		await flushPromises()
		expect(wrapper.find('.iframe-widget__fallback').exists()).toBe(false)
		expect(wrapper.find('iframe').element.style.display).toBe('block')
	})

	it('renders the fallback card on a generic iframe error, never crashing', async () => {
		const wrapper = mount(IframeWidget, {
			propsData: { content: { url: 'not a url', title: 'Broken' } },
		})
		// The iframe renders only after the framable check confirms framing.
		await flushPromises()
		await wrapper.find('iframe').trigger('error')
		await flushPromises()
		expect(wrapper.find('.iframe-widget__fallback').exists()).toBe(true)
	})

	it('renders the "not configured" fallback (never an iframe) when no URL is set', async () => {
		const wrapper = mount(IframeWidget, { propsData: { content: {} } })
		await wrapper.vm.$nextTick()
		expect(wrapper.find('iframe').exists()).toBe(false)
		expect(wrapper.find('.iframe-widget__fallback').exists()).toBe(true)
	})
})
