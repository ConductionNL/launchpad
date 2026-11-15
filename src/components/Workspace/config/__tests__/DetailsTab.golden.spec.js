/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Characterisation tests pinning DetailsTab's stored-value conversion,
 * save payload per field type, load fallbacks and error messages, so the
 * methods can be split into named steps without changing behaviour.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DetailsTab from '../DetailsTab.vue'
import { api } from '../../../../services/api.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('../../../../services/api.js', () => ({
	api: {
		getMetadataFieldDefinitions: vi.fn(),
		getDashboardMetadata: vi.fn(),
		updateDashboardMetadata: vi.fn(),
	},
}))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcButton: { name: 'NcButton', template: '<button><slot /></button>' },
	NcCheckboxRadioSwitch: { name: 'NcCheckboxRadioSwitch', template: '<input>' },
	NcSelect: { name: 'NcSelect', template: '<select />' },
	NcTextField: { name: 'NcTextField', template: '<input>' },
}))

const FIELDS = [
	{ key: 'n', type: 'number' },
	{ key: 'm', type: 'multi-select' },
	{ key: 'b', type: 'boolean' },
	{ key: 's', type: 'select' },
	{ key: 'x' },
]

async function mountTab(fields = FIELDS, stored = {}) {
	api.getMetadataFieldDefinitions.mockResolvedValue({ data: { fields } })
	api.getDashboardMetadata.mockResolvedValue({ data: stored })
	const wrapper = mount(DetailsTab, { props: { dashboardUuid: 'u1' }, shallow: true })
	await flushPromises()
	return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('DetailsTab.fromStored', () => {
	it.each([
		[{ type: 'multi-select' }, undefined, []],
		[{ type: 'multi-select' }, null, []],
		[{ type: 'multi-select' }, '', []],
		[{ type: 'number' }, '', null],
		[{ type: 'boolean' }, null, null],
		[{ type: 'multi-select' }, '["a","b"]', ['a', 'b']],
		[{ type: 'multi-select' }, '{"a":1}', []],
		[{ type: 'multi-select' }, 'not json', []],
		[{ type: 'multi-select' }, ['c'], ['c']],
		[{ type: 'multi-select' }, 5, []],
		[{ type: 'boolean' }, true, true],
		[{ type: 'boolean' }, '1', true],
		[{ type: 'boolean' }, 1, true],
		[{ type: 'boolean' }, '0', false],
		[{ type: 'boolean' }, 'true', false],
		[{ type: 'number' }, 12, '12'],
		[{}, false, 'false'],
		[{ type: 'constructor' }, 'v', 'v'],
	])('converts %j %j', async (field, value, expected) => {
		const wrapper = await mountTab([], {})
		expect(wrapper.vm.fromStored(field, value)).toEqual(expected)
	})
})

describe('DetailsTab.load', () => {
	it('fills one value per field from the stored values', async () => {
		const wrapper = await mountTab(FIELDS, { n: '3', m: '["a"]', b: '1', s: 'HR' })
		expect(api.getDashboardMetadata).toHaveBeenCalledWith('u1')
		expect(wrapper.vm.values).toEqual({ n: '3', m: ['a'], b: true, s: 'HR', x: null })
		expect(wrapper.vm.loading).toBe(false)
		expect(wrapper.vm.error).toBe('')
	})

	it('treats malformed definitions and stored values as empty', async () => {
		api.getMetadataFieldDefinitions.mockResolvedValue({ data: { fields: 'x' } })
		api.getDashboardMetadata.mockResolvedValue({ data: 'x' })
		const wrapper = mount(DetailsTab, { props: { dashboardUuid: 'u1' }, shallow: true })
		await flushPromises()
		expect(wrapper.vm.fields).toEqual([])
		expect(wrapper.vm.values).toEqual({})

		api.getMetadataFieldDefinitions.mockResolvedValue({ data: { fields: [{ key: 'k', type: 'boolean' }] } })
		api.getDashboardMetadata.mockResolvedValue(null)
		await wrapper.vm.load()
		expect(wrapper.vm.values).toEqual({ k: null })
	})

	it('shows the load error and clears loading', async () => {
		api.getMetadataFieldDefinitions.mockRejectedValue(new Error('x'))
		api.getDashboardMetadata.mockResolvedValue({ data: {} })
		const wrapper = mount(DetailsTab, { props: { dashboardUuid: 'u1' }, shallow: true })
		await flushPromises()
		expect(wrapper.vm.error).toBe('The details could not be loaded.')
		expect(wrapper.vm.loading).toBe(false)
	})
})

describe('DetailsTab.save', () => {
	it('sends each value in its field type shape', async () => {
		const wrapper = await mountTab()
		api.updateDashboardMetadata.mockResolvedValue({})
		wrapper.vm.values = { n: '4.5', m: 'x', b: 'yes', s: undefined, x: 'free' }
		await wrapper.vm.save()
		expect(api.updateDashboardMetadata).toHaveBeenCalledWith('u1', { n: 4.5, m: [], b: false, s: '', x: 'free' })
		expect(wrapper.vm.saved).toBe(true)

		wrapper.vm.values = { n: null, m: ['a'], b: true, s: null, x: 0 }
		await wrapper.vm.save()
		expect(api.updateDashboardMetadata).toHaveBeenLastCalledWith('u1', { n: '', m: ['a'], b: true, s: '', x: 0 })

		wrapper.vm.values = { n: '', m: null, b: 1, s: 'HR', x: undefined }
		await wrapper.vm.save()
		expect(api.updateDashboardMetadata).toHaveBeenLastCalledWith('u1', { n: '', m: [], b: false, s: 'HR', x: '' })
	})

	it('shows the server message, or the fallback, when saving fails', async () => {
		const wrapper = await mountTab()
		api.updateDashboardMetadata.mockRejectedValueOnce({ response: { data: { message: 'Bad budget' } } })
		await wrapper.vm.save()
		expect(wrapper.vm.error).toBe('Bad budget')
		expect(wrapper.vm.saved).toBe(false)

		api.updateDashboardMetadata.mockRejectedValueOnce(new Error('x'))
		await wrapper.vm.save()
		expect(wrapper.vm.error).toBe('The details could not be saved.')
	})
})
