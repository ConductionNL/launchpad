/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The card head carries what the renderer reports: the source line under the
 * title and the "Outage" tag (REQ-IFRAME-005).
 *
 * @spec openspec/changes/archive/2026-11-24-embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WidgetWrapper from '../WidgetWrapper.vue'

vi.mock('@conduction/nextcloud-vue', async (importOriginal) => ({
	...(await importOriginal()),
	CnWidgetWrapper: {
		name: 'CnWidgetWrapper',
		props: ['subtitle'],
		template: '<div><span class="subtitle">{{ subtitle }}</span><slot name="title-meta" /><slot /></div>',
	},
	CnWidgetEditCog: { name: 'CnWidgetEditCog', template: '<div />' },
	NcActions: { name: 'NcActions', template: '<div><slot /></div>' },
	NcActionButton: { name: 'NcActionButton', template: '<button><slot /></button>' },
}))

const RendererStub = { name: 'WidgetRenderer', emits: ['status', 'editRequest'], template: '<div />' }

function mountWidget() {
	return mount(WidgetWrapper, {
		props: { placement: { id: 5, widgetId: 'iframe', isCompulsory: 0 } },
		global: {
			stubs: { WidgetRenderer: RendererStub, AcknowledgementPrompt: true },
			mocks: { t: (_a, s) => s },
		},
	})
}

describe('WidgetWrapper card head status', () => {
	it('shows the source line and the Outage tag the renderer reports', async () => {
		const wrapper = mountWidget()
		expect(wrapper.find('[data-testid="widget-outage-tag"]').exists()).toBe(false)
		wrapper.findComponent(RendererStub).vm.$emit('status', { subtitle: 'Embedded report · app.powerbi.com', outage: true })
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.subtitle').text()).toBe('Embedded report · app.powerbi.com')
		expect(wrapper.find('[data-testid="widget-outage-tag"]').text()).toBe('Outage')
	})

	it('drops the tag when the outage ends and ignores a malformed status', async () => {
		const wrapper = mountWidget()
		const renderer = wrapper.findComponent(RendererStub)
		renderer.vm.$emit('status', { subtitle: 'x', outage: true })
		await wrapper.vm.$nextTick()
		renderer.vm.$emit('status', null)
		await wrapper.vm.$nextTick()
		expect(wrapper.find('[data-testid="widget-outage-tag"]').exists()).toBe(false)
		expect(wrapper.find('.subtitle').text()).toBe('')
	})
})
