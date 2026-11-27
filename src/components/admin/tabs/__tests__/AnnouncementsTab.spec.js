/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Announcements tab: every announcement with its status, the form as a
 * section, End now, Delete, Seen and confirmed, and its state in the fragment
 * (announcements-managed-in-settings REQ-ANN-006, REQ-ANN-008, REQ-ANN-009).
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const api = vi.hoisted(() => ({
	manageList: vi.fn(),
	update: vi.fn(),
	remove: vi.fn(),
	receipts: vi.fn(),
	receiptCounts: vi.fn(),
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))
vi.mock('../../../../services/announcementsApi.js', () => ({ announcementsApi: api }))
vi.mock('../../../../modals/AnnouncementEditorModal.vue', () => ({ default: { name: 'AnnouncementEditorModal', render: () => null } }))

const { default: AnnouncementsTab, announcementStatus, announcementsFragment } = await import('../AnnouncementsTab.vue')

const t = (_app, text, vars = {}) => text.replace(/{(\w+)}/g, (_m, key) => vars[key] ?? `{${key}}`)

/**
 * A `this` for the tab's methods.
 *
 * @return {object}
 */
function context() {
	const ctx = { t, items: [], loading: false, error: '', editing: null, receiptsOf: null, receipts: null }
	for (const [name, method] of Object.entries(AnnouncementsTab.methods)) {
		ctx[name] = method.bind(ctx)
	}
	return ctx
}

describe('AnnouncementsTab', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('names each announcement draft, scheduled, active or ended', () => {
		const now = new Date('2026-11-27T12:00:00Z')
		expect(announcementStatus({ status: 'draft' }, now)).toBe('draft')
		expect(announcementStatus({ status: 'published', publishAt: '2026-12-01T08:00:00Z', expiresAt: '2026-12-05T08:00:00Z' }, now)).toBe('scheduled')
		expect(announcementStatus({ status: 'published', expiresAt: '2026-12-05T08:00:00Z' }, now)).toBe('active')
		expect(announcementStatus({ status: 'published', expiresAt: '2026-11-27T11:00:00Z' }, now)).toBe('ended')
	})

	it('reads its state from the fragment', () => {
		expect(announcementsFragment('#announcements')).toEqual({ uuid: '', section: '' })
		expect(announcementsFragment('#announcements/abc')).toEqual({ uuid: 'abc', section: 'edit' })
		expect(announcementsFragment('#announcements/abc/receipts')).toEqual({ uuid: 'abc', section: 'receipts' })
		expect(announcementsFragment('#sharing/abc')).toEqual({ uuid: '', section: '' })
	})

	it('loads every announcement for the list, with its seen and confirmed counts', async () => {
		api.manageList.mockResolvedValue([{ uuid: 'a', title: 'Onderhoud' }, { uuid: 'b', title: 'Nieuw' }])
		api.receiptCounts.mockResolvedValue({ a: { seen: 3, confirmed: 2 } })
		const ctx = context()
		await ctx.load()
		expect(api.receiptCounts).toHaveBeenCalledWith(['a', 'b'])
		expect(ctx.items).toEqual([
			{ uuid: 'a', title: 'Onderhoud', seenCount: 3, confirmedCount: 2 },
			{ uuid: 'b', title: 'Nieuw', seenCount: 0, confirmedCount: 0 },
		])
		api.receiptCounts.mockRejectedValue(new Error('500'))
		await ctx.load()
		expect(ctx.items).toHaveLength(2)
		expect(ctx.items[0].seenCount).toBeUndefined()
		api.manageList.mockRejectedValue(new Error('403'))
		await ctx.load()
		expect(ctx.error).toBe('The announcements could not be loaded.')
	})

	it('opens the form for a new and a stored announcement, with the fragment', () => {
		const replace = vi.spyOn(globalThis.history, 'replaceState')
		const ctx = context()
		ctx.openForm(null)
		expect(ctx.editing).toEqual({ uuid: '' })
		ctx.openForm({ uuid: 'abc', title: 'X' })
		expect(ctx.editing.uuid).toBe('abc')
		expect(replace).toHaveBeenLastCalledWith(null, '', '#announcements/abc')
		ctx.closeForm()
		expect(ctx.editing).toBeNull()
	})

	it('ends an announcement now and deletes one after asking', async () => {
		api.manageList.mockResolvedValue([])
		const ctx = context()
		await ctx.endNow({ uuid: 'abc' })
		expect(api.update).toHaveBeenCalledWith('abc', { expiresAt: expect.any(String) })

		globalThis.confirm = vi.fn(() => false)
		await ctx.remove({ uuid: 'abc', title: 'X' })
		expect(api.remove).not.toHaveBeenCalled()
		globalThis.confirm = vi.fn(() => true)
		await ctx.remove({ uuid: 'abc', title: 'X' })
		expect(api.remove).toHaveBeenCalledWith('abc')
	})

	it('shows who confirmed one announcement', async () => {
		api.receipts.mockResolvedValue({ acknowledgedCount: 1, pendingCount: 1, rows: [] })
		const ctx = context()
		await ctx.openReceipts({ uuid: 'abc', title: 'X' })
		expect(ctx.receiptsOf.uuid).toBe('abc')
		expect(ctx.receipts.acknowledgedCount).toBe(1)
		api.receipts.mockRejectedValue(new Error('400'))
		await ctx.openReceipts({ uuid: 'abc', title: 'X' })
		expect(ctx.receipts.error).toBeTruthy()
	})
})
