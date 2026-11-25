/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ProfileFieldsTab: a field can be a date, and one date field is the start
 * date of new colleagues; marking another unmarks the first (REQ-PEX-005).
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-a-profile-field-can-hold-a-start-date-req-pex-005
 */

import axios from '@nextcloud/axios'
import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ProfileFieldsTab from '../ProfileFieldsTab.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn(), put: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path) => path,
}))

const settle = () => new Promise((resolve) => setTimeout(resolve, 0))
const stubs = {
	NcButton: { emits: ['click'], template: '<button :type="$attrs.type || \'button\'" @click="$emit(\'click\')"><slot /></button>' },
}
const field = (key, type, startDate) => ({ key, label: key, type, source: 'self', ldapAttribute: '', searchable: false, shownInWidget: true, visibility: 'everyone', department: false, startDate })

describe('ProfileFieldsTab start date (REQ-PEX-005)', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.put.mockReset()
	})

	it('offers the Date type and the start-date mark only on date fields', async () => {
		axios.get.mockResolvedValue({ data: { fields: [field('joined', 'date', true), field('office', 'text', false)] } })
		const wrapper = mount(ProfileFieldsTab, { global: { stubs } })
		await settle()

		expect(wrapper.find('select option[value="date"]').exists()).toBe(true)
		expect(wrapper.findAll('[data-testid="profile-field-start-date"]')).toHaveLength(1)
	})

	it('keeps at most one date field as the start date and saves it', async () => {
		axios.get.mockResolvedValue({ data: { fields: [field('joined', 'date', true), field('started', 'date', false)] } })
		axios.put.mockImplementation(async (_url, body) => ({ data: { fields: body.fields } }))
		const wrapper = mount(ProfileFieldsTab, { global: { stubs } })
		await settle()

		const boxes = () => wrapper.findAll('[data-testid="profile-field-start-date"]')
		expect(boxes().map((box) => box.element.checked)).toEqual([true, false])

		await boxes()[1].setValue(true)
		expect(boxes().map((box) => box.element.checked)).toEqual([false, true])

		await wrapper.find('form').trigger('submit')
		await settle()
		expect(axios.put.mock.calls[0][1].fields.map((f) => f.startDate)).toEqual([false, true])
	})
})
