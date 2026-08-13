/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `LiveTileWidgetForm.vue` (REQ-LIVETILE-002/004/005):
 * source-mode selection, refresh-interval clamping, URL validation,
 * connector-availability gating, and the assembled persisted content
 * shape. `liveTileClient.js` is mocked so these tests never perform a
 * real network call.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import LiveTileWidgetForm from '../LiveTileWidgetForm.vue'
import {
	fetchConnectorAvailability,
	validateLiveTileSource,
} from '../../../../services/liveTileClient.js'

vi.mock('../../../../services/liveTileClient.js', () => ({
	fetchConnectorAvailability: vi.fn(),
	validateLiveTileSource: vi.fn(),
}))

async function flushPromises() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

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
	fetchConnectorAvailability.mockReset()
	fetchConnectorAvailability.mockResolvedValue(false)
	validateLiveTileSource.mockReset()
	validateLiveTileSource.mockResolvedValue({ valid: true, errors: [] })
})

describe('LiveTileWidgetForm — REQ-LIVETILE-002 persisted shape', () => {
	it('assembles the full content shape with defaults', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'https://example.com/api',
					valueExpr: '$.count',
				},
			},
		})
		await flushPromises()
		expect(wrapper.vm.assembledContent).toMatchObject({
			sourceMode: 'url',
			url: 'https://example.com/api',
			valueExpr: '$.count',
			refresh: 300,
			format: { prefix: '', suffix: '', thousands: false },
			linkTarget: 'same-tab',
		})
	})

	it('pre-fills from editingWidget.content', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				editingWidget: {
					content: {
						label: 'Open tickets',
						sourceMode: 'url',
						url: 'https://example.com',
						valueExpr: '$.a',
						refresh: 60,
						format: { prefix: '€', suffix: '', thousands: true },
					},
				},
			},
		})
		await flushPromises()
		expect(wrapper.vm.label).toBe('Open tickets')
		expect(wrapper.vm.url).toBe('https://example.com')
		expect(wrapper.vm.refresh).toBe(60)
		expect(wrapper.vm.formatPrefix).toBe('€')
		expect(wrapper.vm.formatThousands).toBe(true)
	})

	it('emits update:content when a field changes', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: { value: { sourceMode: 'url', url: '', valueExpr: '' } },
		})
		await flushPromises()
		wrapper.vm.updateField('label', 'KPI')
		await wrapper.vm.$nextTick()
		const emitted = wrapper.emitted('update:content')
		expect(emitted[emitted.length - 1][0].label).toBe('KPI')
	})
})

describe('LiveTileWidgetForm — REQ-LIVETILE-002 refresh interval bounds', () => {
	it('clamps a refresh value below 30 seconds up to the 30s minimum', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'https://example.com',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		wrapper.vm.onRefreshChange('5')
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.assembledContent.refresh).toBe(30)
	})

	it('defaults an unset (zero/blank) refresh to 300 seconds', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'https://example.com',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		wrapper.vm.onRefreshChange('0')
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.assembledContent.refresh).toBe(300)
	})

	it('leaves a valid refresh value (>= 30) unclamped', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'https://example.com',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		wrapper.vm.onRefreshChange('120')
		await wrapper.vm.$nextTick()
		expect(wrapper.vm.assembledContent.refresh).toBe(120)
	})
})

describe('LiveTileWidgetForm — REQ-LIVETILE-002 URL validation', () => {
	it('requires a URL in url mode', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: { value: { sourceMode: 'url', url: '', valueExpr: '$.a' } },
		})
		await flushPromises()
		expect(wrapper.vm.validate()).toContain('URL is required')
	})

	it('rejects a non-http(s) URL', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'ftp://example.com/data',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		expect(wrapper.vm.validate()).toContain('Enter a valid http(s) URL.')
	})

	it('requires a value expression', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'https://example.com',
					valueExpr: '',
				},
			},
		})
		await flushPromises()
		expect(wrapper.vm.validate()).toContain('Value expression is required')
	})

	it('surfaces the async host allow-list error from checkUrlAllowed() on submit', async () => {
		validateLiveTileSource.mockResolvedValue({
			valid: false,
			errors: ['host_not_allowed'],
		})
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'https://blocked.example.com',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		await wrapper.vm.checkUrlAllowed()
		expect(wrapper.vm.validate()).toContain(
			'This host is not on the allow-list.',
		)
	})

	it('passes validation for a valid https URL + expression with no allow-list error', async () => {
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'https://example.com/api',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		expect(wrapper.vm.validate()).toEqual([])
	})
})

describe('LiveTileWidgetForm — REQ-LIVETILE-005 connector-mode gating', () => {
	it('offers only the "url" source-mode option when OpenConnector is absent', async () => {
		fetchConnectorAvailability.mockResolvedValue(false)
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'https://example.com',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		expect(wrapper.vm.sourceModeOptions.map((o) => o.value)).toEqual(['url'])
	})

	it('offers "connector" mode once the capability probe reports availability', async () => {
		fetchConnectorAvailability.mockResolvedValue(true)
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'url',
					url: 'https://example.com',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		expect(wrapper.vm.sourceModeOptions.map((o) => o.value)).toEqual([
			'url',
			'connector',
		])
	})

	it('falls back an existing connector-mode placement to url mode when OpenConnector is absent', async () => {
		fetchConnectorAvailability.mockResolvedValue(false)
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'connector',
					sourceId: 'src-1',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		expect(wrapper.vm.sourceMode).toBe('url')
	})

	it('fails validation for connector mode when OpenConnector is unavailable', async () => {
		fetchConnectorAvailability.mockResolvedValue(false)
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: {
					sourceMode: 'connector',
					sourceId: 'src-1',
					valueExpr: '$.a',
				},
			},
		})
		await flushPromises()
		// Component self-heals to url mode; force back to connector to
		// exercise the defensive validate() branch directly.
		wrapper.vm.sourceMode = 'connector'
		expect(wrapper.vm.validate()).toContain('OpenConnector is not available.')
	})

	it('requires a source id in connector mode', async () => {
		fetchConnectorAvailability.mockResolvedValue(true)
		const wrapper = mount(LiveTileWidgetForm, {
			propsData: {
				value: { sourceMode: 'connector', sourceId: '', valueExpr: '$.a' },
			},
		})
		await flushPromises()
		expect(wrapper.vm.validate()).toContain('Source id is required')
	})
})
