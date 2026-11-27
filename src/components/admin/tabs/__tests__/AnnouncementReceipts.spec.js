/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Seen and confirmed (board LpLeesbevestigingen, REQ-ANN-009): the figures,
 * the four filters, the status of a row, the CSV, and Send reminder once.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import AnnouncementReceipts, { filterReceipts, receiptsCsv, receiptStatus } from '../AnnouncementReceipts.vue'
import { announcementsApi } from '../../../../services/announcementsApi.js'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	getCanonicalLocale: () => 'en-GB',
}))
vi.mock('../../../../services/announcementsApi.js', () => ({ announcementsApi: { remind: vi.fn() } }))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const fill = (text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, key) => vars[key] ?? `{${key}}`)
const mocks = {
	t: (_app, text, vars) => fill(text, vars),
	n: (_app, one, many, count) => (count === 1 ? one : many).replace('%n', String(count)),
}
const NcButton = { props: ['disabled', 'variant', 'pressed'], emits: ['click'], template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' }

const ROWS = [
	{ userId: 'anna', displayName: 'Anna', seenAt: '2026-10-27T09:00:00Z', confirmedAt: '2026-10-27T09:15:00Z', confirmedVia: 'check', status: 'acknowledged' },
	{ userId: 'bart', displayName: 'Bart', seenAt: '2026-10-27T09:00:00Z', confirmedAt: '2026-10-27T09:20:00Z', confirmedVia: 'x', status: 'acknowledged' },
	{ userId: 'cor', displayName: '=cmd', seenAt: '2026-10-27T09:00:00Z', confirmedAt: null, confirmedVia: null, status: 'pending' },
	{ userId: 'dirk', displayName: 'Dirk', seenAt: null, confirmedAt: null, confirmedVia: null, status: 'pending' },
]
const REPORT = { audienceCount: 4, seenCount: 3, notSeenCount: 1, confirmedCount: 2, confirmedViaCheck: 1, confirmedViaX: 1, notConfirmedCount: 2, canRemind: true, departmentLabel: '', rows: ROWS }

function mountWith (report = REPORT, status = 'published') {
  return mount(AnnouncementReceipts, {
	props: { announcement: { uuid: 'u1', title: 'Woo', status }, report },
	global: { mocks, stubs: { NcButton } },
})
}

beforeEach(() => {
	vi.clearAllMocks()
})

describe('receipt helpers', () => {
	it('names the status of a row', () => {
		expect(ROWS.map(receiptStatus)).toEqual(['check', 'x', 'seen', 'not-seen'])
		expect(receiptStatus({ status: 'acknowledged' })).toBe('check')
	})

	it('filters everyone, confirmed, not confirmed and not seen', () => {
		const ids = (filter) => filterReceipts(ROWS, filter).map((row) => row.userId)
		expect(ids('all')).toEqual(['anna', 'bart', 'cor', 'dirk'])
		expect(ids('confirmed')).toEqual(['anna', 'bart'])
		expect(ids('not-confirmed')).toEqual(['cor', 'dirk'])
		expect(ids('not-seen')).toEqual(['dirk'])
	})

	it('writes every person to the CSV and neutralises a formula', () => {
		const csv = receiptsCsv(ROWS).trim().split('\r\n')
		expect(csv).toHaveLength(5)
		expect(csv[0]).toBe('"name","user","status","confirmed_via","seen_at","confirmed_at"')
		expect(csv[2]).toBe('"Bart","bart","confirmed","x","2026-10-27T09:00:00Z","2026-10-27T09:20:00Z"')
		expect(csv[3]).toBe('"\'=cmd","cor","seen","","2026-10-27T09:00:00Z",""')
		expect(csv[4]).toBe('"Dirk","dirk","not seen","","",""')
	})
})

describe('AnnouncementReceipts', () => {
	it('shows the figures and filters the list', async () => {
		const wrapper = mountWith()

		expect(wrapper.find('[data-testid="receipts-seen"]').text()).toBe('3')
		expect(wrapper.find('[data-testid="receipts-confirmed"]').text()).toBe('2')
		expect(wrapper.find('[data-testid="receipts-not-confirmed"]').text()).toBe('2')
		expect(wrapper.text()).toContain('1 with the check, 1 with the x')
		expect(wrapper.findAll('[data-testid="receipts-row"]')).toHaveLength(4)

		await wrapper.find('[data-testid="receipts-filter-not-seen"]').trigger('click')
		expect(wrapper.findAll('[data-testid="receipts-row"]').map((row) => row.text())).toEqual([expect.stringContaining('Dirk')])
	})

	it('sends one reminder and then disables the button', async () => {
		announcementsApi.remind.mockResolvedValue({ uuid: 'u1', sent: 2 })
		const wrapper = mountWith()

		await wrapper.find('[data-testid="receipts-remind"]').trigger('click')
		await flushPromises()

		expect(announcementsApi.remind).toHaveBeenCalledWith('u1')
		expect(wrapper.find('[data-testid="receipts-message"]').text()).toBe('Reminder sent to 2 people')
		expect(wrapper.find('[data-testid="receipts-remind"]').attributes('disabled')).toBeDefined()
	})

	it('shows the server message when the reminder is refused', async () => {
		announcementsApi.remind.mockRejectedValue({ response: { data: { message: 'A reminder was already sent in the last 24 hours' } } })
		const wrapper = mountWith()

		await wrapper.find('[data-testid="receipts-remind"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="receipts-message"]').text()).toBe('A reminder was already sent in the last 24 hours')
	})

	it('offers no reminder when it was sent today or for a draft', () => {
		expect(mountWith({ ...REPORT, canRemind: false }).find('[data-testid="receipts-remind"]').attributes('disabled')).toBeDefined()
		expect(mountWith(REPORT, 'draft').find('[data-testid="receipts-remind"]').attributes('disabled')).toBeDefined()
	})

	it('downloads the CSV', () => {
		const create = vi.fn(() => 'blob:x')
		const revoke = vi.fn()
		globalThis.URL.createObjectURL = create
		globalThis.URL.revokeObjectURL = revoke
		const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
		const wrapper = mountWith()

		wrapper.find('[data-testid="receipts-download"]').trigger('click')

		expect(create).toHaveBeenCalledTimes(1)
		expect(click).toHaveBeenCalledTimes(1)
		expect(revoke).toHaveBeenCalledWith('blob:x')
		click.mockRestore()
	})
})
