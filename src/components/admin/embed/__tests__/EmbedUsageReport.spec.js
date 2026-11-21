/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The usage report shows a token's aggregate use: the summary, a bar row per
 * date, site, screen size, browser and interaction, use after revocation,
 * and a CSV of the same aggregates only while telemetry is on
 * (embedded-analytics task 17).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-17
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import EmbedUsageReport from '../EmbedUsageReport.vue'

const embed = vi.hoisted(() => ({ tokenUsage: vi.fn() }))
vi.mock('../../../../services/embedApi.js', () => ({ embedApi: embed }))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`),
	n: (_app, one, many, count) => (count === 1 ? one : many).replace('%n', count),
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const REPORT = {
	tokenId: 't-1',
	days: 7,
	telemetryEnabled: true,
	summary: { pageViews: 12, lastActivity: '2026-05-22T14:30:00Z', usedAfterRevocation: 0 },
	byDate: [{ label: '2026-05-21', count: 4 }, { label: '2026-05-22', count: 8 }],
	byHostOrigin: [{ label: 'https://www.zeist.nl', count: 12 }],
	byViewport: { small: 2, medium: 0, large: 10, unknown: 0 },
	byBrowser: [{ label: 'Firefox 128', count: 12 }],
	interactions: { filterApplied: 3, drillDown: 1, export: 0 },
	rateLimited: 1,
}

function mountReport () {
  return mount(EmbedUsageReport, {
	props: { tokenId: 't-1', tokenName: 'Zeist WOO' },
	global: {
		stubs: {
			NcButton: { emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
			NcNoteCard: { props: ['type'], template: '<div class="note"><slot /></div>' },
			NcLoadingIcon: true,
		},
	},
})
}

describe('EmbedUsageReport', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('shows the summary and one bar row per aggregate, scaled to the largest', async () => {
		embed.tokenUsage.mockResolvedValue(REPORT)
		const wrapper = mountReport()
		await flushPromises()

		expect(embed.tokenUsage).toHaveBeenCalledWith('t-1')
		expect(wrapper.find('[data-test="embed-usage-pageviews"]').text()).toBe('12')
		expect(wrapper.find('[data-test="embed-usage-rate-limited"]').text()).toBe('1')
		const dates = wrapper.findAll('[data-test="embed-usage-by-date"] tr')
		expect(dates.map((row) => row.find('th').text())).toEqual(['2026-05-21', '2026-05-22'])
		expect(dates[0].find('.embed-usage-report__bar').attributes('style')).toContain('width: 50%')
		expect(dates[1].find('.embed-usage-report__bar').attributes('style')).toContain('width: 100%')
		expect(wrapper.findAll('[data-test="embed-usage-by-viewport"] th').map((cell) => cell.text())).toEqual(['Small', 'Medium', 'Large', 'Unknown'])
		expect(wrapper.find('[data-test="embed-usage-interactions"]').text()).toContain('Filter applied')
		expect(wrapper.find('[data-test="embed-usage-after-revocation"]').exists()).toBe(false)
		expect(wrapper.vm.csv().split('\n').slice(0, 3)).toEqual(['chart,label,count', '"by-date","2026-05-21",4', '"by-date","2026-05-22",8'])
	})

	it('warns about use after revocation, and offers no export while telemetry is off', async () => {
		embed.tokenUsage.mockResolvedValue({ ...REPORT, telemetryEnabled: false, summary: { ...REPORT.summary, usedAfterRevocation: 3 } })
		const wrapper = mountReport()
		await flushPromises()

		expect(wrapper.find('[data-test="embed-usage-after-revocation"]').text()).toBe('3 requests arrived after the token was revoked.')
		expect(wrapper.find('[data-test="embed-usage-disabled"]').exists()).toBe(true)
		expect(wrapper.find('[data-test="embed-usage-export"]').exists()).toBe(false)
	})

	it('says so when the report cannot be loaded', async () => {
		embed.tokenUsage.mockRejectedValue(new Error('503'))
		const wrapper = mountReport()
		await flushPromises()

		expect(wrapper.text()).toContain('The usage report could not be loaded.')
	})
})
