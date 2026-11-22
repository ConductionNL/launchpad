/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Publication tab of the dashboard settings (REQ-SCHEDUI-001): it shows
 * the status, publishes and unpublishes, schedules go-live and take-down
 * through the store and explains a refusal (DashboardService::ERR_*
 * messages). Only the HTTP client, toasts and l10n are mocked.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import PublicationTab, { scheduleRefusal, toIso } from '../PublicationTab.vue'
import { api } from '../../../../services/api.js'

vi.mock('../../../../services/api.js', () => ({
	api: {
		scheduleDashboard: vi.fn(),
		publishDashboard: vi.fn(),
		unpublishDashboard: vi.fn(),
	},
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@nextcloud/vue', () => ({
	NcButton: {
		props: ['disabled', 'variant'],
		emits: ['click'],
		render() {
			return h('button', { disabled: this.disabled, onClick: () => this.$emit('click') }, this.$slots.default?.())
		},
	},
}))

/**
 * @param {number} status HTTP status.
 * @param {string} message The server's message.
 * @return {Error} An axios-like error.
 */
function refusal(status, message) {
	return Object.assign(new Error('refused'), { response: { status, data: { message } } })
}

/**
 * @param {object} dashboard The dashboard.
 * @return {object} The wrapper.
 */
function mountTab(dashboard) {
	return mount(PublicationTab, { props: { dashboard } })
}

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
})

describe('PublicationTab', () => {
	it('shows the status and the one action that fits it', () => {
		const published = mountTab({ uuid: 'd', publicationStatus: 'published' })
		expect(published.find('[data-testid="publication-status"]').text()).toBe('Published')
		expect(published.find('[data-testid="publication-unpublish"]').exists()).toBe(true)
		expect(published.find('[data-testid="publication-publish"]').exists()).toBe(false)

		const draft = mountTab({ uuid: 'd', publicationStatus: 'draft' })
		expect(draft.find('[data-testid="publication-status"]').text()).toBe('Draft')
		expect(draft.find('[data-testid="publication-publish"]').exists()).toBe(true)

		const scheduled = mountTab({ uuid: 'd', publicationStatus: 'scheduled' })
		expect(scheduled.find('[data-testid="publication-status"]').text()).toBe('Scheduled')
		expect(scheduled.find('[data-testid="publication-publish"]').exists()).toBe(true)
	})

	it('publishes and unpublishes the dashboard by its uuid', async () => {
		api.publishDashboard.mockResolvedValue({ data: { dashboard: { uuid: 'd', publicationStatus: 'published' } } })
		api.unpublishDashboard.mockResolvedValue({ data: { dashboard: { uuid: 'd', publicationStatus: 'draft' } } })

		await mountTab({ uuid: 'd', publicationStatus: 'draft' }).find('[data-testid="publication-publish"]').trigger('click')
		await flushPromises()
		expect(api.publishDashboard).toHaveBeenCalledWith('d')

		await mountTab({ uuid: 'd', publicationStatus: 'published' }).find('[data-testid="publication-unpublish"]').trigger('click')
		await flushPromises()
		expect(api.unpublishDashboard).toHaveBeenCalledWith('d')
	})

	it('schedules go-live and take-down through the store and clears the fields', async () => {
		api.scheduleDashboard.mockResolvedValue({ data: { dashboard: { uuid: 'd', publicationStatus: 'scheduled' } } })
		const wrapper = mountTab({ uuid: 'd', publicationStatus: 'draft' })
		const save = wrapper.find('[data-testid="schedule-save"]')
		expect(save.attributes('disabled')).toBeDefined()

		await wrapper.find('[data-testid="schedule-go-live"]').setValue('2099-10-01T09:00')
		await wrapper.find('[data-testid="schedule-take-down"]').setValue('2099-10-02T17:00')
		expect(save.attributes('disabled')).toBeUndefined()
		await save.trigger('click')
		await flushPromises()

		expect(api.scheduleDashboard).toHaveBeenCalledWith(
			'd',
			new Date('2099-10-01T09:00').toISOString(),
			new Date('2099-10-02T17:00').toISOString(),
		)
		expect(wrapper.find('[data-testid="schedule-error"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="schedule-go-live"]').element.value).toBe('')
	})

	it('a past time is explained in the tab and the fields stay', async () => {
		api.scheduleDashboard.mockRejectedValue(refusal(400, 'publishAt must be a future timestamp'))
		const wrapper = mountTab({ uuid: 'd', publicationStatus: 'draft' })
		await wrapper.find('[data-testid="schedule-go-live"]').setValue('2020-01-01T00:00')
		await wrapper.find('[data-testid="schedule-save"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="schedule-error"]').text()).toBe('Choose a time in the future.')
		expect(wrapper.find('[data-testid="schedule-go-live"]').element.value).toBe('2020-01-01T00:00')
	})

	it('explains an end before the start, and any other refusal', () => {
		expect(scheduleRefusal('unpublishAt must be after publishAt')).toBe('The dashboard has to go live before it comes down.')
		expect(scheduleRefusal('')).toBe('The dashboard could not be scheduled.')
	})

	it('turns a local time into ISO, and an empty or unreadable one into null', () => {
		expect(toIso('')).toBeNull()
		expect(toIso('not a date')).toBeNull()
		expect(toIso('2026-10-01T09:00')).toBe(new Date('2026-10-01T09:00').toISOString())
	})
})
