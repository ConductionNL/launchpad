/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * CoEditorsBanner: in edit mode, who else edits the dashboard and since when,
 * with their initials (building-edit-a-dashboard-together, task 1.1).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import CoEditorsBanner, { clockTime, initialsOf } from '../CoEditorsBanner.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))

vi.mock('@conduction/nextcloud-vue', () => ({
	NcNoteCard: {
		name: 'NcNoteCard',
		props: ['type'],
		template: '<div class="note" :data-type="type"><slot /></div>',
	},
}))

describe('CoEditorsBanner', () => {
	it('says nothing while nobody else edits', () => {
		const wrapper = mount(CoEditorsBanner, { props: { editors: [] } })
		expect(wrapper.find('[data-testid="co-editors-banner"]').exists()).toBe(false)
	})

	it('names the colleague editing longest and since when, and lists every colleague by initials', () => {
		const since = '2026-11-14T10:42:00+01:00'
		const wrapper = mount(CoEditorsBanner, {
			props: {
				editors: [
					{ displayName: 'Marieke Jansen', since },
					{ displayName: 'sanne', since: '2026-11-14T10:50:00+01:00' },
				],
			},
		})
		expect(wrapper.find('p').text()).toBe(
			`Marieke Jansen is also editing this dashboard, since ${clockTime(since)}. Your changes are not overwritten.`,
		)
		expect(wrapper.text()).toContain('Who else is editing?')
		const chips = wrapper.findAll('[data-testid="co-editor"]')
		expect(chips.map((chip) => chip.text())).toEqual(['MJ', 'S'])
		expect(chips[0].attributes('aria-label')).toBe('Marieke Jansen')
	})
})

describe('initialsOf and clockTime', () => {
	it('takes the first letters of the first and last word', () => {
		expect(initialsOf('Marieke van der Jansen')).toBe('MJ')
		expect(initialsOf('  pieter  ')).toBe('P')
		expect(initialsOf('')).toBe('?')
		expect(initialsOf(null)).toBe('?')
	})

	it('reads an ISO time as a clock time, and keeps what it cannot read', () => {
		const time = clockTime('2026-11-14T10:42:00+01:00')
		expect(time).toMatch(/\d{1,2}.\d{2}/)
		expect(clockTime('not a date')).toBe('not a date')
	})
})
