/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Characterisation tests pinning `ConditionalVisibilityEditor.onRowSave`
 * (update, create, reload fallback, error path, busy flag) and
 * `VisibilityRuleRow.buildRuleConfig` for every rule type, so both can be
 * split into named steps without changing behaviour.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import ConditionalVisibilityEditor from '../ConditionalVisibilityEditor.vue'
import VisibilityRuleRow from '../VisibilityRuleRow.vue'
import { api } from '../../../services/api.js'
import { logger } from '../../../utils/logger.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (p) => `/index.php${p}`,
}))
vi.mock('../../../services/api.js', () => ({
	api: {
		getWidgetRules: vi.fn(),
		addWidgetRule: vi.fn(),
		updateRule: vi.fn(),
		deleteRule: vi.fn(),
		previewVisibility: vi.fn(),
	},
}))
vi.mock('../../../utils/logger.js', () => ({
	logger: { error: vi.fn(), warn: vi.fn(), info: vi.fn(), debug: vi.fn() },
}))

async function flush() {
	await new Promise((resolve) => setTimeout(resolve, 0))
}

async function mountEditor() {
	api.getWidgetRules.mockResolvedValue({ data: { rules: [] } })
	const wrapper = mount(ConditionalVisibilityEditor, {
		propsData: { placementId: 10 },
		shallow: true,
	})
	await flush()
	return wrapper
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	vi.clearAllMocks()
})

describe('ConditionalVisibilityEditor.onRowSave', () => {
	it('merges an unwrapped update into the row and emits ruleUpdated', async () => {
		const wrapper = await mountEditor()
		const row = { id: 5, ruleType: 'group', ruleConfig: {}, isInclude: true }
		wrapper.vm.rules.push(row)
		api.updateRule.mockResolvedValue({ data: { data: { isInclude: false } } })

		await wrapper.vm.onRowSave(wrapper.vm.rules[0], { isInclude: false })

		expect(api.updateRule).toHaveBeenCalledWith(5, { isInclude: false })
		expect(wrapper.vm.rules[0]).toMatchObject({ id: 5, ruleType: 'group', isInclude: false })
		expect(wrapper.emitted('ruleUpdated')).toHaveLength(1)
		expect(wrapper.emitted('ruleAdded')).toBeFalsy()
	})

	it('emits ruleUpdated even when the row left the list', async () => {
		const wrapper = await mountEditor()
		api.updateRule.mockResolvedValue({ data: null })
		await wrapper.vm.onRowSave({ id: 0 }, {})
		expect(api.updateRule).toHaveBeenCalledWith(0, {})
		expect(wrapper.vm.rules).toEqual([])
		expect(wrapper.emitted('ruleUpdated')).toHaveLength(1)
	})

	it('replaces a draft with the created rule and clears its local key', async () => {
		const wrapper = await mountEditor()
		wrapper.vm.rules.push({ id: null, _localKey: 'k1', ruleType: 'group' })
		api.addWidgetRule.mockResolvedValue({ data: { data: { id: 77, ruleType: 'group' } } })

		await wrapper.vm.onRowSave(wrapper.vm.rules[0], { ruleType: 'group' })

		expect(api.addWidgetRule).toHaveBeenCalledWith(10, { ruleType: 'group' })
		expect(wrapper.vm.rules[0]).toEqual({ id: 77, ruleType: 'group', _localKey: undefined })
		expect(wrapper.emitted('ruleAdded')).toHaveLength(1)
	})

	it('reloads when the created rule has no id', async () => {
		const wrapper = await mountEditor()
		wrapper.vm.rules.push({ _localKey: 'k1' })
		api.addWidgetRule.mockResolvedValue({ data: { ruleType: 'group' } })
		api.getWidgetRules.mockClear()

		await wrapper.vm.onRowSave(wrapper.vm.rules[0], {})

		expect(api.getWidgetRules).toHaveBeenCalledTimes(1)
		expect(wrapper.emitted('ruleAdded')).toHaveLength(1)
	})

	it('logs a failed save, emits nothing and clears the busy flag', async () => {
		const wrapper = await mountEditor()
		const error = new Error('nope')
		api.addWidgetRule.mockRejectedValue(error)
		const row = { id: undefined, _localKey: 'k2' }
		const busy = vi.spyOn(wrapper.vm, 'setRowBusy')

		await wrapper.vm.onRowSave(row, {})

		expect(logger.error).toHaveBeenCalledWith('Failed to save visibility rule:', error)
		expect(wrapper.emitted('ruleAdded')).toBeFalsy()
		expect(busy.mock.calls).toEqual([[row, true], [row, false]])
	})
})

function ruleConfigFor(ruleType, ruleConfig) {
	const wrapper = mount(VisibilityRuleRow, {
		propsData: { rule: { ruleType: 'group', ruleConfig: { groups: [] }, isInclude: true }, availableGroups: [] },
		shallow: true,
	})
	wrapper.vm.local.ruleType = ruleType
	wrapper.vm.local.ruleConfig = ruleConfig
	return wrapper.vm.buildRuleConfig()
}

describe('VisibilityRuleRow.buildRuleConfig', () => {
	it.each([
		['group', { groups: ['a'], extra: 1 }, { groups: ['a'] }],
		['group', {}, { groups: [] }],
		['time', { startTime: '08:00', endTime: '09:00', days: [] }, { startTime: '08:00', endTime: '09:00' }],
		['time', { startTime: '08:00', days: 'mon' }, { startTime: '08:00', endTime: undefined }],
		['time', { days: ['mon'] }, { startTime: undefined, endTime: undefined, days: ['mon'] }],
		['date', {}, {}],
		['date', { startDate: '', endDate: '2026-01-02' }, { endDate: '2026-01-02' }],
		['date', { startDate: '2026-01-01', endDate: '2026-01-02' }, { startDate: '2026-01-01', endDate: '2026-01-02' }],
		['attribute', { attribute: 'dept', value: 'x' }, { attribute: 'dept', operator: 'equals', value: 'x' }],
		['attribute', { attribute: 'dept', operator: 'contains', value: 'x' }, { attribute: 'dept', operator: 'contains', value: 'x' }],
		['unknown', { groups: ['a'] }, {}],
		['constructor', {}, {}],
	])('builds the %s config', (ruleType, ruleConfig, expected) => {
		expect(ruleConfigFor(ruleType, ruleConfig)).toEqual(expected)
	})

	it('copies the groups and days arrays', () => {
		const groups = ['a']
		const days = ['mon']
		expect(ruleConfigFor('group', { groups }).groups).not.toBe(groups)
		expect(ruleConfigFor('time', { days }).days).not.toBe(days)
	})
})

describe('ConditionalVisibilityEditor.load response shapes', () => {
	it.each([
		[{ rules: [{ id: 1, x: 1 }] }, [{ id: 1, x: 1, _localKey: undefined }]],
		[{ data: { rules: [{ id: 2 }] } }, [{ id: 2, _localKey: undefined }]],
		[{ data: { other: 1 } }, []],
		[[{ id: 3 }], [{ id: 3, _localKey: undefined }]],
		[null, []],
		[{ rules: 'x' }, []],
	])('reads %j', async (data, expected) => {
		api.getWidgetRules.mockResolvedValue({ data })
		const wrapper = mount(ConditionalVisibilityEditor, { propsData: { placementId: 10 }, shallow: true })
		await flush()
		expect(wrapper.vm.rules).toEqual(expected)
		expect(wrapper.vm.loading).toBe(false)
	})

	it('logs a failed load and empties the list; skips the call without a placement', async () => {
		const error = new Error('x')
		api.getWidgetRules.mockRejectedValue(error)
		const wrapper = mount(ConditionalVisibilityEditor, { propsData: { placementId: 10 }, shallow: true })
		await flush()
		expect(logger.error).toHaveBeenCalledWith('Failed to load visibility rules:', error)
		expect(wrapper.vm.rules).toEqual([])
		api.getWidgetRules.mockClear()
		await wrapper.setProps({ placementId: null })
		wrapper.vm.rules = [{ id: 1 }]
		await wrapper.vm.load()
		expect(api.getWidgetRules).not.toHaveBeenCalled()
		expect(wrapper.vm.rules).toEqual([])
	})
})

describe('VisibilityRuleRow.hasAtLeastOneValidDate', () => {
	it.each([
		[{}, false],
		[{ startDate: '', endDate: '' }, false],
		[{ startDate: '2026-01-01' }, true],
		[{ endDate: '2026-01-01' }, true],
		[{ startDate: '2026-1-1' }, false],
		[{ startDate: '2026-01-01', endDate: 'x' }, false],
		[{ startDate: 'x', endDate: '2026-01-01' }, false],
		[{ startDate: 5 }, false],
	])('judges %j', (ruleConfig, expected) => {
		const wrapper = mount(VisibilityRuleRow, {
			propsData: { rule: { ruleType: 'date', ruleConfig: {}, isInclude: true }, availableGroups: [] },
			shallow: true,
		})
		wrapper.vm.local.ruleConfig = ruleConfig
		expect(wrapper.vm.hasAtLeastOneValidDate()).toBe(expected)
	})
})
