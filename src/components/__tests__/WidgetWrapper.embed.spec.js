/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * In view mode an administrator's widget offers "Create embed token", a link
 * to Beheer ▸ Embeds with that widget pre-selected (embedded-analytics task 40).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WidgetWrapper from '../WidgetWrapper.vue'

vi.mock('@conduction/nextcloud-vue', async (importOriginal) => ({
	...(await importOriginal()),
	CnWidgetWrapper: { name: 'CnWidgetWrapper', template: '<div><slot /></div>' },
	CnWidgetEditCog: { name: 'CnWidgetEditCog', template: '<div class="edit-cog" />' },
	NcActions: { name: 'NcActions', template: '<div class="actions"><slot /></div>' },
	NcActionButton: { name: 'NcActionButton', emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
}))

const href = '/index.php/settings/admin/launchpad?tab=embeds&embedType=widget&embedId=5&embedDashboard=7'
const stubs = {
	WidgetRenderer: true,
	AcknowledgementPrompt: true,
	EyeOff: true,
	CodeTags: true,
	NcActionLink: { name: 'NcActionLink', props: ['href'], template: '<a :href="href"><slot /></a>' },
}

function mountWidget(props) {
	return mount(WidgetWrapper, {
		props: { placement: { id: 5, widgetId: 'text', isCompulsory: 0 }, ...props },
		global: { stubs, mocks: { t: (_a, s) => s } },
	})
}

const entry = (wrapper) => wrapper.find('[data-testid="widget-create-embed-token"]')

describe('WidgetWrapper embed entry', () => {
	it('links to Beheer ▸ Embeds with the widget pre-selected', () => {
		const wrapper = mountWidget({ embedHref: href })
		expect(entry(wrapper).exists()).toBe(true)
		expect(entry(wrapper).attributes('href')).toBe(href)
		expect(entry(wrapper).text()).toBe('Create embed token')
		expect(wrapper.find('[data-testid="widget-hide-for-me"]').exists()).toBe(false)
	})

	it('sits beside Hide for me in the same menu', () => {
		const wrapper = mountWidget({ embedHref: href, canHideForMe: true })
		expect(wrapper.findAll('.actions')).toHaveLength(1)
		expect(entry(wrapper).exists()).toBe(true)
		expect(wrapper.find('[data-testid="widget-hide-for-me"]').exists()).toBe(true)
	})

	it('offers nothing without a link or in edit mode', () => {
		expect(entry(mountWidget({})).exists()).toBe(false)
		expect(mountWidget({}).find('.actions').exists()).toBe(false)
		expect(entry(mountWidget({ embedHref: href, editMode: true })).exists()).toBe(false)
	})
})
