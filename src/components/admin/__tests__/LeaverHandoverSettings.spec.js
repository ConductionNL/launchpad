/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest tests for `LeaverHandoverSettings.vue`, the Clean up block of
 * Beheer ▸ Operations (dashboard-sharing REQ-SHARE-016, board LpBeheerOnderhoud).
 *
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const getAdminSettings = vi.fn()
const updateLeaverHandover = vi.fn()
const searchSharees = vi.fn()
vi.mock('../../../services/api.js', () => ({
	api: {
		getAdminSettings: (...args) => getAdminSettings(...args),
		updateLeaverHandover: (...args) => updateLeaverHandover(...args),
		searchSharees: (...args) => searchSharees(...args),
	},
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))

const stubs = {
	NcCheckboxRadioSwitch: {
		props: ['modelValue'],
		emits: ['update:modelValue'],
		template: '<label :data-test="$attrs[\'data-test\']" :data-checked="String(modelValue)" @click="$emit(\'update:modelValue\', !modelValue)"><slot /></label>',
	},
	NcSelect: {
		props: ['modelValue', 'options', 'inputLabel'],
		emits: ['search', 'update:modelValue'],
		template: '<div :data-test="$attrs[\'data-test\']" :data-label="inputLabel">{{ modelValue ? modelValue.displayName : \'\' }}</div>',
	},
}

let LeaverHandoverSettings

beforeEach(async () => {
	getAdminSettings.mockReset()
	updateLeaverHandover.mockReset()
	updateLeaverHandover.mockResolvedValue({})
	searchSharees.mockReset()
	LeaverHandoverSettings = (await import('../LeaverHandoverSettings.vue')).default
})

async function mountSettings(handover) {
	getAdminSettings.mockResolvedValue({ data: { leaverHandover: handover } })
	const wrapper = mount(LeaverHandoverSettings, { global: { stubs } })
	await flushPromises()
	return wrapper
}

describe('LeaverHandoverSettings (REQ-SHARE-016)', () => {
	it('shows the stored handover: the manager, otherwise the named person', async () => {
		const wrapper = await mountSettings({ manager: true, fallbackUid: 'femke' })

		expect(wrapper.text()).toContain('Dashboards of people who left go to')
		expect(wrapper.find('[data-test="leaver-handover-manager"]').attributes('data-checked')).toBe('true')
		const fallback = wrapper.find('[data-test="leaver-handover-fallback"]')
		expect(fallback.text()).toBe('femke')
		expect(fallback.attributes('data-label')).toBe('Otherwise')
	})

	it('reads no setting as no handover', async () => {
		const wrapper = await mountSettings(undefined)
		expect(wrapper.vm.manager).toBe(false)
		expect(wrapper.vm.fallback).toBeNull()
		expect(wrapper.find('[data-test="leaver-handover-fallback"]').attributes('data-label')).toBe('A person')
	})

	it('stores the manager switch and the chosen person', async () => {
		const wrapper = await mountSettings({ manager: false, fallbackUid: '' })

		await wrapper.find('[data-test="leaver-handover-manager"]').trigger('click')
		await flushPromises()
		expect(updateLeaverHandover).toHaveBeenLastCalledWith({ manager: true, fallbackUid: '' })

		wrapper.findComponent(stubs.NcSelect).vm.$emit('update:modelValue', { id: 'femke', displayName: 'Femke de Wit' })
		await flushPromises()
		expect(updateLeaverHandover).toHaveBeenLastCalledWith({ manager: true, fallbackUid: 'femke' })

		wrapper.findComponent(stubs.NcSelect).vm.$emit('update:modelValue', null)
		await flushPromises()
		expect(updateLeaverHandover).toHaveBeenLastCalledWith({ manager: true, fallbackUid: '' })
	})

	it('searches people from two characters and drops a stale answer', async () => {
		const wrapper = await mountSettings({})
		let resolveFirst
		searchSharees
			.mockReturnValueOnce(new Promise((resolve) => { resolveFirst = resolve }))
			.mockResolvedValueOnce({ data: { users: [{ id: 'femke', displayName: 'Femke de Wit' }] } })

		await wrapper.vm.onSearch('f')
		expect(searchSharees).not.toHaveBeenCalled()

		const first = wrapper.vm.onSearch('fe')
		await wrapper.vm.onSearch('fem')
		resolveFirst({ data: { users: [{ id: 'stale', displayName: 'Stale' }] } })
		await first

		expect(wrapper.vm.options).toEqual([{ id: 'femke', displayName: 'Femke de Wit' }])
	})
})
