/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ScheduleDashboardDialog (REQ-SCHEDUI-001): go-live, take-down or both,
 * as ISO strings the schedule endpoint parses; the refusal is shown.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import ScheduleDashboardDialog from '../ScheduleDashboardDialog.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))
vi.mock('@conduction/nextcloud-vue', () => ({
	NcDialog: {
		name: 'NcDialog',
		props: ['open'],
		template: '<div v-if="open"><slot /><slot name="actions" /></div>',
	},
	NcButton: {
		name: 'NcButton',
		props: ['disabled'],
		emits: ['click'],
		template:
			'<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
	},
}))

describe('ScheduleDashboardDialog', () => {
	it('REQ-SCHEDUI-001: emits go-live and take-down as ISO strings', async () => {
		const wrapper = mount(ScheduleDashboardDialog, { props: { open: true } })
		await wrapper
			.find('[data-testid="schedule-go-live"]')
			.setValue('2026-10-01T09:00')
		await wrapper
			.find('[data-testid="schedule-take-down"]')
			.setValue('2026-10-02T17:00')
		await wrapper.find('[data-testid="schedule-save"]').trigger('click')
		const { publishAt, unpublishAt } = wrapper.emitted('save')[0][0]
		expect(new Date(publishAt).getTime()).toBe(
			new Date('2026-10-01T09:00').getTime(),
		)
		expect(new Date(unpublishAt).getTime()).toBe(
			new Date('2026-10-02T17:00').getTime(),
		)
	})

	it('REQ-SCHEDUI-002: a take-down alone is allowed', async () => {
		const wrapper = mount(ScheduleDashboardDialog, { props: { open: true } })
		await wrapper
			.find('[data-testid="schedule-take-down"]')
			.setValue('2026-10-02T17:00')
		await wrapper.find('[data-testid="schedule-save"]').trigger('click')
		expect(wrapper.emitted('save')[0][0].publishAt).toBeNull()
	})

	it('REQ-SCHEDUI-001: shows the refusal', () => {
		const wrapper = mount(ScheduleDashboardDialog, {
			props: { open: true, error: 'Choose a time in the future.' },
		})
		expect(wrapper.find('[data-testid="schedule-error"]').text()).toBe(
			'Choose a time in the future.',
		)
	})
})
