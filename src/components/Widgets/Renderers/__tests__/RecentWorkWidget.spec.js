/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * RecentWorkWidget: "Verder waar u was" (LpVandaag) lists the reader's own
 * recent work, one row per thing, named by the thing, with app and when.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import RecentWorkWidget, { recentWhen, recentWorkRows } from '../RecentWorkWidget.vue'

vi.mock('@nextcloud/l10n', () => ({
	getCanonicalLocale: () => 'en-GB',
	t: (_app, text) => text,
}))
vi.mock('@nextcloud/router', () => ({ generateOcsUrl: (path) => '/ocs/v2.php' + path }))
const get = vi.fn()
vi.mock('@nextcloud/axios', () => ({ default: { get: (...args) => get(...args) } }))

const NOW = new Date(2026, 9, 5, 15, 0)

describe('recentWorkRows', () => {
	it('names a row by the thing, keeps its newest activity and says app and when', () => {
		const rows = recentWorkRows([
			{ app: 'files', object_type: 'files', object_id: 7, object_name: '/Vergunningen/Advies welstand.pdf', link: '/f/7', datetime: new Date(2026, 9, 5, 9, 0).toISOString() },
			{ app: 'files', object_type: 'files', object_id: 7, object_name: '/Vergunningen/Advies welstand.pdf', link: '/f/7', datetime: new Date(2026, 9, 4, 9, 0).toISOString() },
			{ app: 'dav', object_type: 'calendar', object_id: 3, object_name: 'Spreekuur', link: '/c/3', datetime: new Date(2026, 9, 2, 9, 0).toISOString() },
			{ app: 'files', object_type: 'files', object_id: 8, object_name: '', link: '/f/8', datetime: NOW.toISOString() },
		], 6, NOW, 'en-GB')

		expect(rows.map((row) => row.title)).toEqual(['Advies welstand.pdf', 'Spreekuur'])
		expect(rows[0].meta).toBe('files · this morning')
		expect(rows[1].meta).toBe('calendar · Friday')
	})

	it('leaves out a record named only by its uuid, and other apps when apps are given', () => {
		const rows = recentWorkRows([
			{ app: 'openregister', object_type: 'object', object_id: 1, object_name: '83fc3a62-a06c-4da6-8112-70214b3cf607', link: '/o/1', datetime: NOW.toISOString() },
			{ app: 'openregister', object_type: 'object', object_id: 2, object_name: 'Klacht behandeling', link: '/o/2', datetime: NOW.toISOString() },
			{ app: 'files', object_type: 'files', object_id: 3, object_name: '/a.pdf', link: '/f/3', datetime: NOW.toISOString() },
		], 6, NOW, 'en-GB', ['files'])
		expect(rows.map((row) => row.title)).toEqual(['a.pdf'])
		expect(recentWorkRows([{ app: 'openregister', object_type: 'object', object_id: 1, object_name: '83fc3a62-a06c-4da6-8112-70214b3cf607', link: '/o/1', datetime: NOW.toISOString() }], 6, NOW, 'en-GB')).toEqual([])
	})

	it('leaves out what the reader deleted', () => {
		const rows = recentWorkRows([
			{ app: 'files', type: 'file_deleted', subject: 'You deleted a.pdf', object_type: 'files', object_id: 1, object_name: '/a.pdf', link: '/f/1', datetime: NOW.toISOString() },
			{ app: 'files', type: 'file_changed', object_type: 'files', object_id: 2, object_name: '/b.pdf', link: '/f/2', datetime: NOW.toISOString() },
		], 6, NOW, 'en-GB')
		expect(rows.map((row) => row.title)).toEqual(['b.pdf'])
	})

	it('stops at the limit', () => {
		const many = Array.from({ length: 9 }, (_, i) => ({ app: 'deck', object_type: 'card', object_id: i, object_name: 'Card ' + i, link: '/d/' + i, datetime: NOW.toISOString() }))
		expect(recentWorkRows(many, 3, NOW, 'en-GB')).toHaveLength(3)
	})

	it('words today by the part of the day and older days by date', () => {
		expect(recentWhen(new Date(2026, 9, 5, 16, 0).toISOString(), NOW, 'en-GB')).toBe('this afternoon')
		expect(recentWhen(new Date(2026, 8, 20, 16, 0).toISOString(), NOW, 'en-GB')).toMatch(/^20 Sep/)
	})
})

describe('RecentWorkWidget', () => {
	it('asks the Activity app for the reader\'s own activity and lists it', async () => {
		get.mockResolvedValue({ data: { ocs: { data: [{ app: 'files', object_type: 'files', object_id: 1, object_name: '/a.pdf', link: '/f/1', datetime: new Date().toISOString() }] } } })
		const wrapper = mount(RecentWorkWidget, { props: { content: {} } })
		await flushPromises()
		await flushPromises()

		expect(get.mock.calls[0][0]).toBe('/ocs/v2.php/apps/activity/api/v2/activity/self')
		expect(wrapper.findAll('[data-testid="recent-work-row"]')).toHaveLength(1)
	})

	it('says it needs the Activity app when that cannot answer', async () => {
		get.mockRejectedValue({ response: { status: 404 } })
		const wrapper = mount(RecentWorkWidget, { props: { content: {} } })
		await flushPromises()
		await flushPromises()

		expect(wrapper.find('[data-testid="recent-work-unavailable"]').exists()).toBe(true)
	})
})
