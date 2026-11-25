/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `AcknowledgementReportModal.vue`: the read
 * confirmations dialog (REQ-ACK-004/006/010) — the heading, the count over
 * the audience, the filters, the table with department and status, the
 * shown line, Download and Close.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AcknowledgementReportModal from '../AcknowledgementReportModal.vue'
import { announcementsApi } from '../../services/announcementsApi.js'
import { api } from '../../services/api.js'

vi.mock('../../services/api.js', () => ({
	api: {
		getAcknowledgementReport: vi.fn(),
		getAcknowledgementReportCsvUrl: vi.fn((key) => `/csv/${key}`),
	},
}))

vi.mock('../../services/announcementsApi.js', () => ({
	announcementsApi: { receipts: vi.fn() },
}))

const stubs = {
	NcModal: { props: ['name'], emits: ['close'], template: '<div class="nc-modal-stub" :data-name="name"><slot /></div>' },
	NcButton: {
		props: ['href', 'variant'],
		emits: ['click'],
		template: '<a v-if="href" :href="href" class="button"><slot /></a><button v-else class="button" :data-variant="variant" @click="$emit(\'click\')"><slot /></button>',
	},
	NcLoadingIcon: { template: '<span class="loading-stub" />' },
}

const REPORT = {
	deadline: '2026-10-30 17:00:00',
	overdue: false,
	departmentLabel: 'Afdeling',
	rows: [
		{ userId: 'sanne', displayName: 'Sanne de Vries', department: 'KCC', status: 'acknowledged', acknowledgedAt: '2026-10-13 10:00:00' },
		{ userId: 'pieter', displayName: 'Pieter Jansen', department: 'Burgerzaken', status: 'pending', acknowledgedAt: null },
		{ userId: 'kees', status: 'pending', acknowledgedAt: null },
	],
}

function mountModal(props = {}) {
	return mount(AcknowledgementReportModal, {
		props: { open: true, announcementKey: 'policy-2026', title: 'New procedure for Woo requests', dashboardName: 'Intranet Zuiddrecht', ...props },
		global: { stubs },
	})
}

function cells(wrapper, userId) {
	return wrapper.find(`[data-testid="ack-row-${userId}"]`).findAll('td').map((cell) => cell.text())
}

beforeEach(() => {
	globalThis.t = (_app, key, vars) => key.replace(/\{(\w+)\}/g, (_, name) => (vars && name in vars ? String(vars[name]) : `{${name}}`))
	api.getAcknowledgementReport.mockReset()
})

describe('AcknowledgementReportModal', () => {
	it('shows the heading, the count over the audience and every row', async () => {
		api.getAcknowledgementReport.mockResolvedValue({ data: REPORT })
		const wrapper = mountModal()
		expect(wrapper.find('.loading-stub').exists()).toBe(true)
		await flushPromises()

		expect(api.getAcknowledgementReport).toHaveBeenCalledWith('policy-2026')
		expect(wrapper.find('h2').text()).toBe('Read confirmations: New procedure for Woo requests')
		expect(wrapper.find('[data-testid="ack-report-eyebrow"]').text()).toBe('Announcement · Intranet Zuiddrecht')
		const summary = wrapper.find('[data-testid="ack-summary"]').text()
		expect(summary).toContain('1 of 3 confirmed')
		expect(summary).toContain('2 to go')
		expect(summary).toContain('deadline ')
		expect(wrapper.find('[data-testid="ack-bar"]').attributes('aria-valuenow')).toBe('33')
		expect(wrapper.find('thead').text()).toContain('Afdeling')

		expect(cells(wrapper, 'sanne')[0]).toBe('SV Sanne de Vries')
		expect(cells(wrapper, 'sanne')[1]).toBe('KCC')
		expect(cells(wrapper, 'sanne')[2]).toMatch(/^Confirmed .+/)
		expect(cells(wrapper, 'pieter')).toEqual(['PJ Pieter Jansen', 'Burgerzaken', 'Not yet'])
		expect(cells(wrapper, 'kees')).toEqual(['K kees', '—', 'Not yet'])
		expect(wrapper.find('[data-testid="ack-shown"]').text()).toMatch(/^3 of 3 shown\./)
		expect(wrapper.find('[data-testid="acknowledgement-report-csv"]').attributes('href')).toBe('/csv/policy-2026')
	})

	it('filters to who has not confirmed yet and to who has', async () => {
		api.getAcknowledgementReport.mockResolvedValue({ data: REPORT })
		const wrapper = mountModal()
		await flushPromises()

		await wrapper.find('[data-testid="ack-filter-pending"]').trigger('click')
		expect(wrapper.findAll('tbody tr').map((row) => row.attributes('data-testid'))).toEqual(['ack-row-pieter', 'ack-row-kees'])
		expect(wrapper.find('[data-testid="ack-filter-pending"]').attributes('data-variant')).toBe('primary')
		expect(wrapper.find('[data-testid="ack-shown"]').text()).toMatch(/^2 of 3 shown\./)

		await wrapper.find('[data-testid="ack-filter-confirmed"]').trigger('click')
		expect(wrapper.findAll('tbody tr')).toHaveLength(1)

		await wrapper.find('[data-testid="ack-filter-everyone"]').trigger('click')
		expect(wrapper.findAll('tbody tr')).toHaveLength(3)
	})

	it('falls back to plain headings and Department without a title or label', async () => {
		api.getAcknowledgementReport.mockResolvedValue({ data: { rows: [] } })
		const wrapper = mountModal({ title: '', dashboardName: '' })
		await flushPromises()
		expect(wrapper.find('h2').text()).toBe('Read confirmations')
		expect(wrapper.find('[data-testid="ack-report-eyebrow"]').text()).toBe('Announcement')
		expect(wrapper.find('thead').text()).toContain('Department')
		expect(wrapper.find('[data-testid="ack-summary"]').text()).toBe('0 of 0 confirmed · 0 to go')
	})

	it('shows an error and no report when loading fails, and closes', async () => {
		api.getAcknowledgementReport.mockRejectedValue(new Error('403'))
		const wrapper = mountModal()
		await flushPromises()
		expect(wrapper.find('[data-testid="acknowledgement-report-error"]').text()).toBe('Could not load the read-receipt report.')
		expect(wrapper.find('table').exists()).toBe(false)
		expect(wrapper.find('[data-testid="acknowledgement-report-csv"]').exists()).toBe(false)
		await wrapper.find('[data-testid="acknowledgement-report-close"]').trigger('click')
		expect(wrapper.emitted('close')).toHaveLength(1)
	})

	it('does not load while closed and reloads for another announcement', async () => {
		api.getAcknowledgementReport.mockResolvedValue({ data: REPORT })
		const wrapper = mountModal({ open: false })
		await flushPromises()
		expect(api.getAcknowledgementReport).not.toHaveBeenCalled()
		await wrapper.setProps({ open: true })
		await flushPromises()
		await wrapper.setProps({ announcementKey: 'other' })
		await flushPromises()
		expect(api.getAcknowledgementReport).toHaveBeenLastCalledWith('other')
	})

	// @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	it('reads an announcement\'s confirmations from the announcements API, without Download', async () => {
		announcementsApi.receipts.mockResolvedValue(REPORT)
		const wrapper = mountModal({ announcementKey: '', announcementUuid: 'u-1', dashboardName: '' })
		await flushPromises()

		expect(announcementsApi.receipts).toHaveBeenCalledWith('u-1')
		expect(api.getAcknowledgementReport).not.toHaveBeenCalled()
		expect(wrapper.find('[data-testid="ack-summary"]').text()).toContain('1 of 3 confirmed')
		expect(wrapper.find('[data-testid="acknowledgement-report-csv"]').exists()).toBe(false)

		announcementsApi.receipts.mockResolvedValue({ ...REPORT, rows: [] })
		await wrapper.setProps({ announcementUuid: 'u-2' })
		await flushPromises()
		expect(announcementsApi.receipts).toHaveBeenLastCalledWith('u-2')
	})
})
