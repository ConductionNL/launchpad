/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ProfileFieldsTab: one field is the Department of read confirmations;
 * marking another unmarks the first (REQ-ACK-010).
 *
 * @spec openspec/changes/archive/2026-11-24-read-receipts-dialog/specs/dashboard-acknowledgements/spec.md#requirement-the-read-confirmations-dialog-req-ack-010
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
const field = (key, department) => ({ key, label: key, type: 'text', source: 'self', ldapAttribute: '', searchable: false, shownInWidget: true, visibility: 'everyone', department })

describe('ProfileFieldsTab department', () => {
	beforeEach(() => {
		axios.get.mockReset()
		axios.put.mockReset()
	})

	it('keeps at most one field as the department and saves it', async () => {
		axios.get.mockResolvedValue({ data: { fields: [field('team', true), field('unit', false)] } })
		axios.put.mockImplementation(async (_url, body) => ({ data: { fields: body.fields } }))
		const wrapper = mount(ProfileFieldsTab, { global: { stubs } })
		await settle()

		const boxes = () => wrapper.findAll('[data-testid="profile-field-department"]')
		expect(boxes().map((box) => box.element.checked)).toEqual([true, false])

		await boxes()[1].setValue(true)
		expect(boxes().map((box) => box.element.checked)).toEqual([false, true])

		await boxes()[1].setValue(false)
		expect(boxes().map((box) => box.element.checked)).toEqual([false, false])

		await boxes()[0].setValue(true)
		await wrapper.find('form').trigger('submit')
		await settle()
		expect(axios.put.mock.calls[0][1].fields.map((f) => f.department)).toEqual([true, false])
	})
})
