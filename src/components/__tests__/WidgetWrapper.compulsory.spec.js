/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * In edit mode a compulsory widget carries a lock that says it cannot be
 * removed; in view mode, and on an ordinary widget, there is none
 * (permissions REQ-PERM-004 "Compulsory widget visual indicator").
 *
 * @spec openspec/specs/permissions/spec.md
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WidgetWrapper from '../WidgetWrapper.vue'

vi.mock('@conduction/nextcloud-vue', async (importOriginal) => ({
	...(await importOriginal()),
	CnWidgetWrapper: { name: 'CnWidgetWrapper', template: '<div><slot /></div>' },
	NcActions: { name: 'NcActions', template: '<div><slot /></div>' },
	NcActionButton: { name: 'NcActionButton', template: '<button><slot /></button>' },
}))

const stubs = { WidgetRenderer: true, AcknowledgementPrompt: true, WidgetEditMenu: true, LockOutline: true }

/**
 * Mount a widget.
 *
 * @param {object} props The props.
 * @return {object} The wrapper.
 */
function mountWidget(props) {
	return mount(WidgetWrapper, {
		props: { placement: { id: 7, widgetId: 'header', isCompulsory: 1 }, ...props },
		global: { stubs, mocks: { t: (_a, s) => s } },
	})
}

const lock = (wrapper) => wrapper.find('[data-testid="widget-compulsory-indicator"]')

describe('WidgetWrapper compulsory indicator', () => {
	it('shows a named lock on a compulsory widget in edit mode', () => {
		const indicator = lock(mountWidget({ editMode: true }))
		expect(indicator.exists()).toBe(true)
		expect(indicator.attributes('role')).toBe('img')
		expect(indicator.attributes('aria-label')).toBe('Required: this widget cannot be removed')
		expect(indicator.attributes('title')).toBe('Required: this widget cannot be removed')
	})

	it('shows nothing in view mode', () => {
		expect(lock(mountWidget({ editMode: false })).exists()).toBe(false)
	})

	it('shows nothing on a widget that is not compulsory, also when the flag comes as text', () => {
		expect(lock(mountWidget({ editMode: true, placement: { id: 8, widgetId: 'text', isCompulsory: 0 } })).exists()).toBe(false)
		expect(lock(mountWidget({ editMode: true, placement: { id: 9, widgetId: 'text', isCompulsory: '1' } })).exists()).toBe(true)
	})
})
