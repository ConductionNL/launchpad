/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * EditLockBanner: tells the person who is editing, that the lock was lost,
 * or that editing is not possible, and offers "Take over" to
 * administrators only (dashboard-locking REQ-LOCKUI-001..003).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import EditLockBanner from '../EditLockBanner.vue'

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
	NcButton: {
		name: 'NcButton',
		emits: ['click'],
		template:
			'<button class="nc-button" @click="$emit(\'click\')"><slot /></button>',
	},
}))

function mountBanner(props) {
	return mount(EditLockBanner, {
		props: { isAdmin: false, holderName: '', expiresIn: null, ...props },
	})
}

describe('EditLockBanner', () => {
	it('REQ-LOCKUI-001: names the colleague who is editing', () => {
		const wrapper = mountBanner({
			status: 'blocked',
			holderName: 'Sanne',
			expiresIn: 610,
		})
		expect(wrapper.text()).toContain('Sanne is editing this dashboard')
		expect(wrapper.text()).toContain('within 11 minutes')
		expect(wrapper.find('[role="status"]').exists()).toBe(true)
	})

	it('REQ-LOCKUI-002: says the lock was lost', () => {
		const wrapper = mountBanner({ status: 'lost' })
		expect(wrapper.text()).toContain(
			'Someone else is editing this dashboard now',
		)
	})

	it('says editing is not possible on 403 and on failure', () => {
		expect(mountBanner({ status: 'forbidden' }).text()).toContain(
			'You cannot edit this dashboard',
		)
		expect(mountBanner({ status: 'unavailable' }).text()).toContain(
			'could not be opened for editing',
		)
	})

	it('renders nothing while the lock is held or not asked for', () => {
		expect(
			mountBanner({ status: 'held' }).find('[role="status"]').exists(),
		).toBe(false)
		expect(
			mountBanner({ status: 'none' }).find('[role="status"]').exists(),
		).toBe(false)
	})

	it('REQ-LOCKUI-003: administrators get "Take over", others do not', async () => {
		const admin = mountBanner({
			status: 'blocked',
			holderName: 'Sanne',
			isAdmin: true,
		})
		const button = admin.find('[data-testid="edit-lock-take-over"]')
		expect(button.exists()).toBe(true)
		await button.trigger('click')
		expect(admin.emitted('takeOver')).toHaveLength(1)

		const user = mountBanner({
			status: 'blocked',
			holderName: 'Sanne',
			isAdmin: false,
		})
		expect(user.find('[data-testid="edit-lock-take-over"]').exists()).toBe(false)
	})
})
