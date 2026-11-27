/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A placement that asks its readers to confirm they read it wears the boards'
 * announcement frame (REQ-ACK-011, LpVandaag, LpIntranet): the wrapper marks
 * it launchpad-widget--announcement, confirmed or not.
 *
 * @spec openspec/specs/dashboard-acknowledgements/spec.md#requirement-a-placement-that-asks-for-confirmation-looks-like-an-announcement-req-ack-011
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
		props: { placement: { id: 7, widgetId: 'text', requiresAcknowledgement: 1 }, ...props },
		global: { stubs, mocks: { t: (_a, s) => s } },
	})
}

const ANNOUNCEMENT = 'launchpad-widget--announcement'

describe('WidgetWrapper announcement frame', () => {
	it('marks a placement that asks for a read confirmation', () => {
		expect(mountWidget({}).classes()).toContain(ANNOUNCEMENT)
		expect(mountWidget({ placement: { id: 8, widgetId: 'text', requiresAcknowledgement: '1' } }).classes()).toContain(ANNOUNCEMENT)
	})

	it('keeps the frame in edit mode, where the read gate is off', () => {
		expect(mountWidget({ editMode: true }).classes()).toContain(ANNOUNCEMENT)
	})

	it('leaves every other placement alone', () => {
		expect(mountWidget({ placement: { id: 9, widgetId: 'text', requiresAcknowledgement: 0 } }).classes()).not.toContain(ANNOUNCEMENT)
		expect(mountWidget({ placement: { id: 10, widgetId: 'text' } }).classes()).not.toContain(ANNOUNCEMENT)
	})
})

describe('the announcement frame in the board look', () => {
	it('draws the 4px primary bar, the 24px/20px inset and the 17px title', async () => {
		const { readFileSync } = await import('node:fs')
		const { join } = await import('node:path')
		const css = readFileSync(join(__dirname, '../../styles/workspace.css'), 'utf8')
		expect(css).toMatch(/launchpad-widget--announcement \.cn-widget-wrapper--nc-dashboard \{\s*box-shadow: inset 4px 0 0 var\(--color-primary-element\);/)
		expect(css).toMatch(/launchpad-widget--announcement[^{]*__header \{\s*padding: 16px 20px 8px 24px;/)
		expect(css).toMatch(/launchpad-widget--announcement[^{]*__title \{\s*font-size: 17px;/)
	})
})
