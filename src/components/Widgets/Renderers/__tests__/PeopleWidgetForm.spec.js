/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The people widget form offers "New colleagues" only when a profile field
 * is the start date (scenario "No start date field"), stores the selection
 * and the two windows, and keeps a window out of range invalid (REQ-PPL-014).
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PeopleWidgetForm from '../PeopleWidgetForm.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => String(vars[key])),
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnPeopleWidgetForm: {
		name: 'CnPeopleWidgetForm',
		props: ['editingWidget', 'value'],
		emits: ['update:content'],
		template: '<div class="cn-people-form" />',
		computed: {
			assembledContent() {
				return { layout: 'list', selectionMode: (this.editingWidget?.content || {}).selectionMode || 'filter', filters: [] }
			},
		},
		methods: {
			validate: () => [],
		},
	},
}))

/**
 * Mount the form once the start-date probe has answered.
 *
 * @param {boolean} configured Whether a field is the start date.
 * @param {object} [content] The stored placement content.
 * @return {Promise<object>} The wrapper.
 */
async function mounted(configured, content) {
	axios.get.mockResolvedValue({ data: { configured, users: [] } })
	const wrapper = mount(PeopleWidgetForm, { props: { editingWidget: content ? { content } : null } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	axios.get.mockReset()
})

describe('PeopleWidgetForm', () => {
	it('offers no New colleagues without a start date field', async () => {
		const wrapper = await mounted(false)

		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/people/new', { params: { since: 1, until: 0 } })
		expect(wrapper.find('[data-testid="people-selection"]').exists()).toBe(false)
		expect(wrapper.text()).not.toContain('New colleagues')
	})

	it('stores New colleagues with the default windows, and switching back stores a directory selection', async () => {
		const wrapper = await mounted(true)
		await wrapper.find('[data-testid="people-selection"]').setValue('new')

		expect(wrapper.emitted('update:content').at(-1)[0]).toEqual({
			layout: 'list', selectionMode: 'new', filters: [], newSinceDays: 30, newUntilDays: 14,
		})

		await wrapper.find('[data-testid="people-selection"]').setValue('people')
		expect(wrapper.emitted('update:content').at(-1)[0]).toEqual({ layout: 'list', selectionMode: 'filter', filters: [] })
	})

	it('keeps a window out of range invalid until it is corrected', async () => {
		const wrapper = await mounted(true, { selectionMode: 'new', newSinceDays: 10, newUntilDays: 7 })
		const since = wrapper.find('[data-testid="people-new-since"]')
		expect(since.element.value).toBe('10')

		await since.setValue('120')
		expect(wrapper.vm.validate()).toEqual(['Must be between 1 and 90'])
		expect(wrapper.emitted('update:content').at(-1)[0].newSinceDays).toBe(10)

		await since.setValue('45')
		expect(wrapper.vm.validate()).toEqual([])
		expect(wrapper.emitted('update:content').at(-1)[0]).toMatchObject({ selectionMode: 'new', newSinceDays: 45, newUntilDays: 7 })
	})

	it('keeps the choice on a New colleagues placement after the start date field is gone, and warns', async () => {
		const wrapper = await mounted(false, { selectionMode: 'new' })

		expect(wrapper.find('[data-testid="people-selection"]').exists()).toBe(true)
		expect(wrapper.text()).toContain('The administrator has not set a start date field')
	})
})
