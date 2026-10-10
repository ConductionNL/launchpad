/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Filtering a widget leaves its definition alone
 * (dashboards-and-who-may-see-them REQ-DWMS-008, task 6.3).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WidgetWrapper from '../WidgetWrapper.vue'

vi.mock('@conduction/nextcloud-vue', async (importOriginal) => ({
	...(await importOriginal()),
	CnWidgetWrapper: { name: 'CnWidgetWrapper', template: '<div><slot /></div>' },
	CnWidgetEditCog: { name: 'CnWidgetEditCog', template: '<div />' },
	NcActions: { name: 'NcActions', template: '<div><slot /></div>' },
	NcActionButton: { name: 'NcActionButton', template: '<button><slot /></button>' },
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template: '<input :value="modelValue" :aria-label="label" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
}))

// A stand-in for an object-list renderer: three rows from a saved search.
const CaseRows = {
	name: 'WidgetRenderer',
	props: ['widget', 'placement'],
	template: `<table><tbody>
		<tr><td>Bouwvergunning Kerkstraat</td></tr>
		<tr><td>Kapvergunning Dorpsplein</td></tr>
		<tr><td>Bouwvergunning Molenweg</td></tr>
	</tbody></table>`,
}

function placement() {
	return {
		id: 12,
		widgetId: 'object-list',
		isCompulsory: 0,
		content: { register: 'zaken', schema: 'zaak', search: { status: 'open' } },
	}
}

function mountWidget(props = {}) {
	return mount(WidgetWrapper, {
		props: { placement: placement(), ...props },
		global: {
			stubs: { WidgetRenderer: CaseRows, AcknowledgementPrompt: true, EyeOff: true },
			mocks: { t: (_a, s) => s },
		},
		attachTo: document.body,
	})
}

function shownRows(wrapper) {
	return wrapper.findAll('tbody tr')
		.filter((row) => !row.element.hasAttribute('data-lp-filtered-out'))
		.map((row) => row.text())
}

describe('WidgetWrapper in-widget filter', () => {
	it('filters the rendered rows and writes nothing back', async () => {
		const props = { placement: placement() }
		const before = JSON.parse(JSON.stringify(props.placement))
		const wrapper = mountWidget(props)

		await wrapper.find('[data-testid="widget-row-filter"]').setValue('molen')

		expect(shownRows(wrapper)).toEqual(['Bouwvergunning Molenweg'])
		// The definition is untouched and nothing was emitted to save.
		expect(props.placement).toEqual(before)
		expect(Object.keys(wrapper.emitted()).filter((name) => !['input', 'update:modelValue'].includes(name))).toEqual([])
		wrapper.unmount()
	})

	it('says so when nothing matches', async () => {
		const wrapper = mountWidget()

		await wrapper.find('[data-testid="widget-row-filter"]').setValue('sloop')

		expect(shownRows(wrapper)).toEqual([])
		expect(wrapper.find('[data-testid="widget-row-filter-empty"]').text()).toBe('No matches.')
		wrapper.unmount()
	})

	it('is gone after a reload', async () => {
		const first = mountWidget()
		await first.find('[data-testid="widget-row-filter"]').setValue('molen')
		first.unmount()

		// A reload mounts the widget again from the same placement.
		const second = mountWidget()

		expect(second.find('[data-testid="widget-row-filter"]').element.value).toBe('')
		expect(shownRows(second)).toHaveLength(3)
		second.unmount()
	})

	it('is not offered in edit mode or on a widget without rows', () => {
		expect(mountWidget({ editMode: true }).find('[data-testid="widget-row-filter"]').exists()).toBe(false)
		expect(
			mountWidget({ placement: { id: 3, widgetId: 'clock', isCompulsory: 0 } })
				.find('[data-testid="widget-row-filter"]').exists(),
		).toBe(false)
	})
})
