/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `AcknowledgementReportModal.vue`: when the report is
 * loaded, the loading / error / report states, the per-row status, and the
 * CSV link.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AcknowledgementReportModal from '../AcknowledgementReportModal.vue'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: {
		getAcknowledgementReport: vi.fn(),
		getAcknowledgementReportCsvUrl: vi.fn((key) => `/csv/${key}`),
	},
}))

const stubs = {
	NcModal: { emits: ['close'], template: '<div class="nc-modal-stub"><slot /></div>' },
	NcButton: { template: '<button><slot /></button>' },
	NcLoadingIcon: { template: '<span class="loading-stub" />' },
}

const REPORT = {
	acknowledgedCount: 1,
	pendingCount: 1,
	overdue: true,
	rows: [
		{ userId: 'alice', status: 'acknowledged', acknowledgedAt: '2026-03-01 10:00' },
		{ userId: 'bob', status: 'pending', acknowledgedAt: null },
	],
}

function mountModal(props = {}) {
	return mount(AcknowledgementReportModal, {
		propsData: { open: true, announcementKey: 'policy-2026', ...props },
		stubs,
	})
}

beforeEach(() => {
	globalThis.t = (_app, key) => key
	api.getAcknowledgementReport.mockReset()
})

describe('AcknowledgementReportModal', () => {
	it('loads the report for the announcement on open and renders it', async () => {
		api.getAcknowledgementReport.mockResolvedValue({ data: REPORT })
		const wrapper = mountModal()

		expect(wrapper.find('.loading-stub').exists()).toBe(true)
		await flushPromises()

		expect(api.getAcknowledgementReport).toHaveBeenCalledWith('policy-2026')
		expect(wrapper.find('[data-testid="ack-count"]').text()).toBe('Acknowledged: 1')
		expect(wrapper.find('[data-testid="pending-count"]').text()).toBe('Pending: 1')
		expect(wrapper.find('[data-testid="overdue-flag"]').exists()).toBe(true)

		const alice = wrapper.find('[data-testid="ack-row-alice"]').findAll('td')
		expect(alice.map((c) => c.text())).toEqual(['alice', 'Acknowledged', '2026-03-01 10:00'])
		const bob = wrapper.find('[data-testid="ack-row-bob"]').findAll('td')
		expect(bob.map((c) => c.text())).toEqual(['bob', 'Pending', '—'])
		expect(bob[1].find('span').classes()).toContain('launchpad-ack-report__badge--pending')

		expect(wrapper.find('[data-testid="acknowledgement-report-csv"]').attributes('href')).toBe('/csv/policy-2026')
	})

	it('hides the overdue flag when the report is not overdue', async () => {
		api.getAcknowledgementReport.mockResolvedValue({ data: { ...REPORT, overdue: false } })
		const wrapper = mountModal()
		await flushPromises()
		expect(wrapper.find('[data-testid="overdue-flag"]').exists()).toBe(false)
	})

	it('shows an error and no report when loading fails', async () => {
		api.getAcknowledgementReport.mockRejectedValue(new Error('403'))
		const wrapper = mountModal()
		await flushPromises()

		expect(wrapper.find('[data-testid="acknowledgement-report-error"]').text()).toBe(
			'Could not load the read-receipt report.',
		)
		expect(wrapper.find('[data-testid="ack-count"]').exists()).toBe(false)
		expect(wrapper.vm.loading).toBe(false)
	})

	it('does not load while closed or without an announcement key', async () => {
		mountModal({ open: false })
		mountModal({ announcementKey: '' })
		await flushPromises()
		expect(api.getAcknowledgementReport).not.toHaveBeenCalled()
	})

	it('loads when opened later and reloads when the announcement changes', async () => {
		api.getAcknowledgementReport.mockResolvedValue({ data: REPORT })
		const wrapper = mountModal({ open: false })

		await wrapper.setProps({ open: true })
		await flushPromises()
		await wrapper.setProps({ announcementKey: 'other' })
		await flushPromises()

		expect(api.getAcknowledgementReport.mock.calls).toEqual([['policy-2026'], ['other']])
	})

	it('does not reload on a key change while closed', async () => {
		const wrapper = mountModal({ open: false })
		await wrapper.setProps({ announcementKey: 'other' })
		await flushPromises()
		expect(api.getAcknowledgementReport).not.toHaveBeenCalled()
	})

	it('clears an earlier error when it reloads', async () => {
		api.getAcknowledgementReport.mockRejectedValueOnce(new Error('x'))
		const wrapper = mountModal()
		await flushPromises()
		expect(wrapper.vm.error).not.toBe('')

		api.getAcknowledgementReport.mockResolvedValueOnce({ data: REPORT })
		await wrapper.setProps({ announcementKey: 'retry' })
		await flushPromises()

		expect(wrapper.vm.error).toBe('')
		expect(wrapper.vm.report).toEqual(REPORT)
	})
})
