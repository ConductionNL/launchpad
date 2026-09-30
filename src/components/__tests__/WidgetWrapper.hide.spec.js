/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * In view mode a widget on a shared dashboard offers "Hide for me", except
 * a compulsory one (dashboards-personal-hide-ui REQ-PERSUI-001).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WidgetWrapper from '../WidgetWrapper.vue'

vi.mock('@conduction/nextcloud-vue', async (importOriginal) => ({
	...(await importOriginal()),
	CnWidgetWrapper: { name: 'CnWidgetWrapper', template: '<div><slot /></div>' },
	CnWidgetEditCog: {
		name: 'CnWidgetEditCog',
		template: '<div class="edit-cog" />',
	},
	NcActions: {
		name: 'NcActions',
		template: '<div class="actions"><slot /></div>',
	},
	NcActionButton: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot /></button>',
	},
}))

const stubs = { WidgetRenderer: true, AcknowledgementPrompt: true, EyeOff: true }
function mountWidget(props) {
	return mount(WidgetWrapper, {
		props: { placement: { id: 5, widgetId: 'text', isCompulsory: 0 }, ...props },
		global: { stubs, mocks: { t: (_a, s) => s } },
	})
}

describe('WidgetWrapper hide for me', () => {
	it('offers Hide for me in view mode and emits it', async () => {
		const wrapper = mountWidget({ canHideForMe: true })
		const button = wrapper.find('[data-testid="widget-hide-for-me"]')
		expect(button.exists()).toBe(true)
		await button.trigger('click')
		expect(wrapper.emitted('hideForMe')[0][0].id).toBe(5)
	})

	it('offers nothing for a compulsory widget, in edit mode, or when not allowed', () => {
		expect(
			mountWidget({
				canHideForMe: true,
				placement: { id: 5, widgetId: 'text', isCompulsory: 1 },
			})
				.find('[data-testid="widget-hide-for-me"]')
				.exists(),
		).toBe(false)
		expect(
			mountWidget({ canHideForMe: true, editMode: true })
				.find('[data-testid="widget-hide-for-me"]')
				.exists(),
		).toBe(false)
		expect(
			mountWidget({ canHideForMe: false })
				.find('[data-testid="widget-hide-for-me"]')
				.exists(),
		).toBe(false)
	})
})
