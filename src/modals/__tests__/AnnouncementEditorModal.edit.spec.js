/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The announcement form edits a stored announcement on the Announcements tab
 * (announcements-managed-in-settings REQ-ANN-006).
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
 */

import { describe, expect, it } from 'vitest'
import AnnouncementEditorModal, { formFromAnnouncement, toLocalInput } from '../AnnouncementEditorModal.vue'

describe('AnnouncementEditorModal editing', () => {
	it('turns a stored time into a datetime-local value and back', () => {
		const local = toLocalInput('2026-12-01T08:30:00Z')
		expect(local).toMatch(/^2026-12-0[12]T\d\d:30$/)
		expect(new Date(local).toISOString()).toBe('2026-12-01T08:30:00.000Z')
		expect(toLocalInput(null)).toBe('')
		expect(toLocalInput('nonsense')).toBe('')
	})

	it('fills the form from a stored announcement', () => {
		const form = formFromAnnouncement({
			kind: 'notice',
			title: 'Onderhoud',
			body: 'Zaterdag',
			category: null,
			targetGroups: ['Burgerzaken'],
			level: 'warning',
			allowComments: false,
			expiresAt: '2026-12-01T12:00:00Z',
			requiresConfirmation: 1,
			confirmBefore: '2026-11-30T22:59:00Z',
		})
		expect(form.kind).toBe('notice')
		expect(form.title).toBe('Onderhoud')
		expect(form.category).toBe('')
		expect(form.targetGroups).toEqual(['Burgerzaken'])
		expect(form.requiresConfirmation).toBe(true)
		expect(form.confirmBefore).toMatch(/^2026-1[12]-\d\d$/)
		expect(form.publishAt).toBe('')
	})

	it('starts from the stored announcement and names the heading', () => {
		const t = (_app, text) => text
		const stored = { uuid: 'abc', title: 'X', expiresAt: null }
		const data = AnnouncementEditorModal.data.call({ announcement: stored })
		expect(data.uuid).toBe('abc')
		expect(data.form.title).toBe('X')
		expect(AnnouncementEditorModal.data.call({ announcement: null }).uuid).toBe('')
		expect(AnnouncementEditorModal.computed.heading.call({ announcement: stored, t })).toBe('Edit announcement')
	})
})
