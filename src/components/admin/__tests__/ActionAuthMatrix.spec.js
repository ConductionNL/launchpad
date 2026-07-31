/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `ActionAuthMatrix.vue` (ADR-023 action
 * authorization admin editor).
 *
 * REGRESSION GUARD: the matrix can only render columns for groups the
 * server can enumerate, and Nextcloud has no group that contains every
 * account. The shipped non-admin baseline is therefore expressed with the
 * synthetic `@all` sentinel — which means the editor MUST inject that
 * column itself. Without it the baseline is invisible: an admin cannot see
 * that ordinary users have access, and cannot revoke it.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import ActionAuthMatrix from '../ActionAuthMatrix.vue'
import { api } from '../../../services/api.js'

vi.mock('../../../services/api.js', () => ({
	api: {
		getActionMatrix: vi.fn(),
		updateActionMatrix: vi.fn(),
	},
}))

vi.mock('@nextcloud/dialogs', () => ({
	showError: vi.fn(),
	showSuccess: vi.fn(),
}))

const ncButtonStub = {
	name: 'NcButton',
	props: ['type', 'disabled'],
	emits: ['click'],
	template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
}

const checkboxStub = {
	name: 'NcCheckboxRadioSwitch',
	props: ['modelValue', 'disabled', 'ariaLabel'],
	emits: ['update:modelValue'],
	template: `
		<input
			type="checkbox"
			:checked="modelValue"
			:disabled="disabled"
			@change="$emit('update:modelValue', $event.target.checked)" />
	`,
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	api.getActionMatrix.mockReset()
	api.updateActionMatrix.mockReset().mockResolvedValue({ data: { matrix: {} } })
})

async function mountWith(payload) {
	api.getActionMatrix.mockResolvedValue({ data: payload })
	const wrapper = mount(ActionAuthMatrix, {
		stubs: {
			NcButton: ncButtonStub,
			NcCheckboxRadioSwitch: checkboxStub,
		},
	})
	// One tick for the mounted() promise, one for the re-render.
	await new Promise((resolve) => setTimeout(resolve, 0))
	await wrapper.vm.$nextTick()
	return wrapper
}

describe('ActionAuthMatrix', () => {
	const payload = {
		actions: ['dashboard.list', 'analytics.instance-summary'],
		groups: ['admin', 'editors'],
		matrix: {
			'dashboard.list': ['admin', '@all'],
			'analytics.instance-summary': ['admin'],
		},
	}

	it('renders an "@all" column even though the server never lists it as a group', async () => {
		const wrapper = await mountWith(payload)

		expect(wrapper.vm.displayGroups).toEqual(['admin', '@all', 'editors'])
	})

	it('labels the sentinel column readably instead of showing "@all" raw', async () => {
		const wrapper = await mountWith(payload)

		const headings = wrapper.findAll('.launchpad-admin__matrix-group').map((th) => th.text())
		expect(headings).toEqual(['admin', 'All logged-in users', 'editors'])
	})

	it('shows the sentinel as ticked for a baseline action and unticked for an admin-only one', async () => {
		const wrapper = await mountWith(payload)

		expect(wrapper.vm.isChecked('dashboard.list', '@all')).toBe(true)
		expect(wrapper.vm.isChecked('analytics.instance-summary', '@all')).toBe(false)
	})

	it('lets an admin revoke the baseline grant', async () => {
		const wrapper = await mountWith(payload)

		wrapper.vm.toggle('dashboard.list', '@all', false)

		expect(wrapper.vm.matrix['dashboard.list']).toEqual(['admin'])
	})

	it('persists the sentinel through a save round-trip', async () => {
		const wrapper = await mountWith(payload)

		await wrapper.vm.save()

		const sent = api.updateActionMatrix.mock.calls[0][0]
		expect(sent['dashboard.list']).toEqual(['admin', '@all'])
		expect(sent['analytics.instance-summary']).toEqual(['admin'])
	})

	it('does not duplicate the sentinel column if the server ever reports it', async () => {
		const wrapper = await mountWith({
			...payload,
			groups: ['admin', '@all', 'editors'],
		})

		expect(wrapper.vm.displayGroups).toEqual(['admin', '@all', 'editors'])
	})
})
