/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * VersionHistoryModal: lists versions newest first, saves a named version
 * and restores after confirmation (dashboard-versioning REQ-VERSUI-001..003).
 * Response shapes are DashboardVersionApiController's: `{versions,
 * modeSupported}` for the list, `{version}` (201) for a save, `{version,
 * snapshot}` for a restore; each version is DashboardVersion::jsonSerialize.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import VersionHistoryModal from '../VersionHistoryModal.vue'
import { api } from '../../services/api.js'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))

vi.mock('../../services/api.js', () => ({
	api: {
		listVersions: vi.fn(),
		createVersion: vi.fn(),
		restoreVersion: vi.fn(),
	},
}))

vi.mock('@conduction/nextcloud-vue', () => ({
	NcModal: {
		name: 'NcModal',
		props: ['name'],
		emits: ['close'],
		template: '<div class="modal"><slot /></div>',
	},
	NcDialog: {
		name: 'NcDialog',
		props: ['name', 'open'],
		template:
			'<div v-if="open" class="dialog"><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
	NcTextField: {
		name: 'NcTextField',
		props: ['modelValue', 'label'],
		emits: ['update:modelValue'],
		template:
			'<input :aria-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">',
	},
	NcLoadingIcon: { name: 'NcLoadingIcon', template: '<span class="loading" />' },
}))

function v(n, note = null, createdBy = 'sanne') {
	return {
		id: n,
		dashboardUuid: 'dash-uuid',
		versionNumber: n,
		createdBy,
		createdAt: `2026-09-2${n} 10:00:00`,
		note,
		sizeBytes: 120,
	}
}

function mountModal() {
	return mount(VersionHistoryModal, {
		props: { open: true, dashboardUuid: 'dash-uuid' },
	})
}

beforeEach(() => {
	vi.clearAllMocks()
})

describe('VersionHistoryModal', () => {
	it('REQ-VERSUI-001: lists versions newest first with author and note', async () => {
		api.listVersions.mockResolvedValue({
			data: {
				versions: [v(2), v(4, 'before the reorganisation'), v(1), v(3)],
				modeSupported: true,
			},
		})
		const wrapper = mountModal()
		await flushPromises()
		expect(api.listVersions).toHaveBeenCalledWith('dash-uuid')
		const rows = wrapper.findAll('[data-testid="version-row"]')
		expect(rows).toHaveLength(4)
		expect(rows[0].text()).toContain('4')
		expect(rows[0].text()).toContain('before the reorganisation')
		expect(rows[0].text()).toContain('sanne')
		expect(rows[3].text()).toContain('1')
	})

	it('REQ-VERSUI-002: saves a named version and puts it on top', async () => {
		api.listVersions.mockResolvedValue({
			data: { versions: [v(1)], modeSupported: true },
		})
		api.createVersion.mockResolvedValue({
			data: { version: v(2, 'before the reorganisation') },
		})
		const wrapper = mountModal()
		await flushPromises()
		await wrapper.find('input').setValue('before the reorganisation')
		await wrapper.find('[data-testid="version-save"]').trigger('click')
		await flushPromises()
		expect(api.createVersion).toHaveBeenCalledWith(
			'dash-uuid',
			'before the reorganisation',
		)
		const rows = wrapper.findAll('[data-testid="version-row"]')
		expect(rows).toHaveLength(2)
		expect(rows[0].text()).toContain('before the reorganisation')
	})

	it('REQ-VERSUI-003: restore asks first, restores, reloads the list and tells the page', async () => {
		api.listVersions.mockResolvedValueOnce({
			data: { versions: [v(2), v(1)], modeSupported: true },
		})
		api.restoreVersion.mockResolvedValue({
			data: { version: v(1), snapshot: '{}' },
		})
		api.listVersions.mockResolvedValueOnce({
			data: {
				versions: [v(3, 'pre-restore'), v(2), v(1)],
				modeSupported: true,
			},
		})
		const wrapper = mountModal()
		await flushPromises()
		await wrapper.findAll('[data-testid="version-restore"]')[1].trigger('click')
		expect(api.restoreVersion).not.toHaveBeenCalled()
		await wrapper
			.find('[data-testid="version-restore-confirm"]')
			.trigger('click')
		await flushPromises()
		expect(api.restoreVersion).toHaveBeenCalledWith('dash-uuid', 1)
		expect(wrapper.emitted('restored')).toHaveLength(1)
		expect(wrapper.findAll('[data-testid="version-row"]')[0].text()).toContain(
			'3',
		)
	})

	it('says so when versioning is not supported for the dashboard', async () => {
		api.listVersions.mockResolvedValue({
			data: { versions: [], modeSupported: false },
		})
		const wrapper = mountModal()
		await flushPromises()
		expect(wrapper.find('[data-testid="version-unsupported"]').exists()).toBe(
			true,
		)
		expect(wrapper.find('[data-testid="version-save"]').exists()).toBe(false)
	})

	it('shows the error when the list cannot be read', async () => {
		api.listVersions.mockRejectedValue(
			Object.assign(new Error('x'), {
				response: { status: 403, data: { error: 'forbidden' } },
			}),
		)
		const wrapper = mountModal()
		await flushPromises()
		expect(wrapper.find('[data-testid="version-error"]').exists()).toBe(true)
	})
})
