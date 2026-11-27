/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * "Create embed token" is an edit-mode action (REQ-WDG-047, review 27 Nov
 * item 21): in view mode a widget shows no action menu unless it has an
 * action for the reader (Hide for me), and never the embed entry; in edit
 * mode the widget's edit menu holds it, linking to the admin page's Embeds
 * section with the widget pre-selected, disabled for an unsaved widget.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/widgets/spec.md#requirement-the-widget-action-menu-follows-the-mode-req-wdg-047
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import WidgetWrapper from '../WidgetWrapper.vue'

vi.mock('@conduction/nextcloud-vue', async (importOriginal) => ({
	...(await importOriginal()),
	CnWidgetWrapper: { name: 'CnWidgetWrapper', template: '<div><slot /></div>' },
	NcActions: { name: 'NcActions', template: '<div class="actions"><slot /></div>' },
	NcActionButton: { name: 'NcActionButton', props: ['disabled'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
}))
vi.mock('@nextcloud/vue', async (importOriginal) => ({
	...(await importOriginal()),
	NcActionLink: { name: 'NcActionLink', props: ['href'], template: '<a :href="href"><slot /></a>' },
}))

const href = '/index.php/apps/launchpad/admin/general?tab=embeds&embedType=widget&embedId=5&embedDashboard=7'
const stubs = {
	WidgetRenderer: true,
	AcknowledgementPrompt: true,
	EyeOff: true,
}

function mountWidget(props, placement = { id: 5, widgetId: 'text', isCompulsory: 0 }) {
	return mount(WidgetWrapper, {
		props: { placement, ...props },
		global: { stubs, mocks: { t: (_a, s) => s } },
	})
}

const entry = (wrapper) => wrapper.find('[data-testid="widget-create-embed-token"]')

describe('WidgetWrapper embed entry (REQ-WDG-047)', () => {
	it('is not offered in view mode, and a widget without reader actions shows no menu', () => {
		const wrapper = mountWidget({ embedHref: href })
		expect(entry(wrapper).exists()).toBe(false)
		expect(wrapper.find('.actions').exists()).toBe(false)
	})

	it('leaves Hide for me alone in the view-mode menu', () => {
		const wrapper = mountWidget({ embedHref: href, canHideForMe: true })
		expect(wrapper.findAll('.actions')).toHaveLength(1)
		expect(wrapper.find('[data-testid="widget-hide-for-me"]').exists()).toBe(true)
		expect(entry(wrapper).exists()).toBe(false)
	})

	it('sits in the edit menu in edit mode, linking to Embeds with the widget pre-selected', () => {
		const wrapper = mountWidget({ embedHref: href, editMode: true })
		const menu = wrapper.find('[data-testid="cn-widget-edit-cog"]')
		expect(menu.exists()).toBe(true)
		expect(menu.find('[data-testid="widget-create-embed-token"]').attributes('href')).toBe(href)
		expect(entry(wrapper).text()).toBe('Create embed token')
		expect(wrapper.find('[data-testid="cn-widget-edit-cog-edit"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="cn-widget-edit-cog-delete"]').exists()).toBe(true)
	})

	it('is left out of the edit menu for someone who may not create embed tokens', () => {
		const wrapper = mountWidget({ editMode: true })
		expect(wrapper.find('[data-testid="cn-widget-edit-cog"]').exists()).toBe(true)
		expect(entry(wrapper).exists()).toBe(false)
	})

	it('is disabled for a widget that is not saved yet', () => {
		const wrapper = mountWidget({ embedHref: href, editMode: true }, { widgetId: 'text', isCompulsory: 0 })
		expect(entry(wrapper).exists()).toBe(true)
		expect(entry(wrapper).attributes('href')).toBeUndefined()
		expect(entry(wrapper).attributes('disabled')).toBeDefined()
	})
})
