/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `IframeWidgetForm.vue` (REQ-IFRAME-002/004): URL /
 * title validation, the async host allow-list check, the assembled
 * persisted content shape, and sandbox-token toggling never being able to
 * grant `allow-top-navigation`. `iframeClient.js` is mocked so these tests
 * never perform a real network call.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import IframeWidgetForm from '../IframeWidgetForm.vue'
import { validateIframeUrl } from '../../../../services/iframeClient.js'

vi.mock('../../../../services/iframeClient.js', () => ({
	validateIframeUrl: vi.fn(),
}))

beforeEach(() => {
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
	validateIframeUrl.mockReset()
	validateIframeUrl.mockResolvedValue({ valid: true, errors: [] })
})

describe('IframeWidgetForm — REQ-IFRAME-001 persisted shape', () => {
	it('assembles the full content shape with defaults', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: {
				value: { url: 'https://status.example.com/board', title: 'Status' },
			},
		})
		expect(wrapper.vm.assembledContent).toMatchObject({
			url: 'https://status.example.com/board',
			title: 'Status',
			height: 400,
			aspect: 'none',
			sandbox: ['allow-scripts', 'allow-same-origin'],
		})
	})

	it('pre-fills from editingWidget.content', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: {
				editingWidget: {
					content: {
						url: 'https://a.example.com',
						title: 'A',
						height: 500,
						aspect: '16:9',
						sandbox: ['allow-forms'],
						allowListChecked: true,
					},
				},
			},
		})
		expect(wrapper.vm.url).toBe('https://a.example.com')
		expect(wrapper.vm.title).toBe('A')
		expect(wrapper.vm.height).toBe(500)
		expect(wrapper.vm.aspect).toBe('16:9')
		expect(wrapper.vm.sandbox).toEqual(['allow-forms'])
		expect(wrapper.vm.allowListChecked).toBe(true)
	})

	it('emits update:content when a field changes', async () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: { value: { url: '', title: '' } },
		})
		wrapper.vm.updateField('title', 'Board')
		await wrapper.vm.$nextTick()
		const emitted = wrapper.emitted('update:content')
		expect(emitted[emitted.length - 1][0].title).toBe('Board')
	})
})

describe('IframeWidgetForm — REQ-IFRAME-004 sandbox toggles never grant allow-top-navigation', () => {
	it('SANDBOX_TOKEN_OPTIONS never offers allow-top-navigation', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: { value: { url: '', title: '' } },
		})
		const values = wrapper.vm.SANDBOX_TOKEN_OPTIONS.map((option) => option.value)
		expect(values).not.toContain('allow-top-navigation')
		expect(values.some((v) => v.startsWith('allow-top-navigation'))).toBe(false)
	})

	it('toggleSandboxToken can add/remove a permitted token', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: { value: { url: '', title: '', sandbox: [] } },
		})
		wrapper.vm.toggleSandboxToken('allow-forms', true)
		expect(wrapper.vm.sandbox).toContain('allow-forms')
		wrapper.vm.toggleSandboxToken('allow-forms', false)
		expect(wrapper.vm.sandbox).not.toContain('allow-forms')
	})

	it('strips a forbidden token from a persisted sandbox list at load time (defence-in-depth)', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: {
				editingWidget: {
					content: {
						url: 'https://a.example.com',
						title: 'A',
						sandbox: ['allow-scripts', 'allow-top-navigation'],
					},
				},
			},
		})
		expect(wrapper.vm.sandbox).not.toContain('allow-top-navigation')
		expect(wrapper.vm.assembledContent.sandbox).not.toContain(
			'allow-top-navigation',
		)
	})
})

describe('IframeWidgetForm — REQ-IFRAME-002 URL + title validation', () => {
	it('requires a URL', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: { value: { url: '', title: 'Status' } },
		})
		expect(wrapper.vm.validate()).toContain('URL is required')
	})

	it('rejects a non-http(s) URL', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: { value: { url: 'ftp://example.com', title: 'Status' } },
		})
		expect(wrapper.vm.validate()).toContain('Enter a valid http(s) URL.')
	})

	it('requires a title (REQ-IFRAME-004 accessible frame title)', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: { value: { url: 'https://example.com', title: '' } },
		})
		expect(wrapper.vm.validate()).toContain('Title is required')
	})

	it('surfaces the async host allow-list error from checkUrlAllowed() on submit', async () => {
		validateIframeUrl.mockResolvedValue({
			valid: false,
			errors: ['host_not_allowed'],
		})
		const wrapper = mount(IframeWidgetForm, {
			propsData: {
				value: { url: 'https://blocked.example.com', title: 'Blocked' },
			},
		})
		await wrapper.vm.checkUrlAllowed()
		expect(wrapper.vm.validate()).toContain(
			'This host is not on the allow-list.',
		)
	})

	it('passes validation for a valid https URL + title with no allow-list error', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: {
				value: { url: 'https://status.example.com/board', title: 'Status' },
			},
		})
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('sets allowListChecked=true and clears the warning when the host is allowed', async () => {
		validateIframeUrl.mockResolvedValue({ valid: true, errors: [] })
		const wrapper = mount(IframeWidgetForm, {
			propsData: {
				value: { url: 'https://status.example.com/board', title: 'Status' },
			},
		})
		await wrapper.vm.checkUrlAllowed()
		expect(wrapper.vm.allowListChecked).toBe(true)
		expect(wrapper.vm.urlAllowListError).toBe('')
	})

	it('resets allowListChecked when the URL is edited again', async () => {
		validateIframeUrl.mockResolvedValue({ valid: true, errors: [] })
		const wrapper = mount(IframeWidgetForm, {
			propsData: {
				value: { url: 'https://status.example.com/board', title: 'Status' },
			},
		})
		await wrapper.vm.checkUrlAllowed()
		expect(wrapper.vm.allowListChecked).toBe(true)
		wrapper.vm.onUrlChange('https://other.example.com')
		expect(wrapper.vm.allowListChecked).toBe(false)
	})
})

describe('IframeWidgetForm — height clamping', () => {
	it('clamps a non-positive height to the 400px default', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: { value: { url: '', title: '' } },
		})
		wrapper.vm.onHeightChange('0')
		expect(wrapper.vm.assembledContent.height).toBe(400)
	})

	it('rounds a fractional height', () => {
		const wrapper = mount(IframeWidgetForm, {
			propsData: { value: { url: '', title: '' } },
		})
		wrapper.vm.onHeightChange('320.6')
		expect(wrapper.vm.assembledContent.height).toBe(321)
	})
})
