/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard row's cog menu: which entries show for an owner, an editor
 * and a viewer, the edit/save and default/set-default labels, publish versus
 * unpublish, and the event each entry asks the host for.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardRowActions from '../DashboardRowActions.vue'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text }))

vi.mock('@nextcloud/vue', () => ({
	NcActions: {
		name: 'NcActions',
		emits: ['open'],
		template: '<div class="actions"><slot name="icon" /><slot /></div>',
	},
	NcActionButton: {
		name: 'NcActionButton',
		emits: ['click'],
		template: '<button @click="$emit(\'click\')"><slot name="icon" /><slot /></button>',
	},
}))

const icons = ['Cog', 'ContentSave', 'Pencil', 'Tune', 'ShapePolygonPlus', 'TrashCanOutline', 'Star', 'StarCheck', 'ShareVariant', 'CalendarClock', 'Publish', 'PublishOff', 'History']
const stubs = Object.fromEntries(icons.map((name) => [name, { template: `<i class="icon-${name}" />` }]))

/**
 * Mount the menu.
 *
 * @param {object} props Props on top of a group dashboard.
 * @return {object} The wrapper.
 */
function menu(props = {}) {
	return mount(DashboardRowActions, {
		props: { dashboard: { id: 7, uuid: 'u7', publicationStatus: 'draft' }, source: 'group', ...props },
		global: { stubs },
	})
}

const entries = (wrapper) => wrapper.findAll('button').map((b) => b.attributes('data-testid'))

describe('DashboardRowActions menu', () => {
	it('offers a viewer of a group dashboard only "Set as default"', () => {
		expect(entries(menu())).toEqual(['cog-set-default'])
	})

	it('offers the owner of a personal dashboard every entry the host allows', () => {
		const wrapper = menu({ source: 'user', canEdit: true, canShare: true, canManagePublication: true, canViewHistory: true })

		expect(entries(wrapper)).toEqual([
			'cog-edit-dashboard', 'cog-dashboard-config', 'cog-add-widget', 'cog-set-default',
			'cog-publish', 'cog-schedule', 'cog-version-history', 'cog-share', 'cog-delete',
		])
	})

	it('treats a group dashboard as owned only when it says so', () => {
		expect(entries(menu({ dashboard: { id: 1, isOwner: true } }))).toContain('cog-delete')
		expect(entries(menu({ dashboard: { id: 1, isOwner: 'yes' } }))).not.toContain('cog-delete')
	})

	it('shows "Save dashboard" only on the active row in edit mode', () => {
		const active = menu({ canEdit: true, isEditMode: true, activeDashboardId: 7 })
		expect(active.find('[data-testid="cog-edit-dashboard"]').text()).toBe('Save dashboard')
		expect(active.find('[data-testid="cog-edit-dashboard"] .icon-ContentSave').exists()).toBe(true)

		const other = menu({ canEdit: true, isEditMode: true, activeDashboardId: 8 })
		expect(other.find('[data-testid="cog-edit-dashboard"]').text()).toBe('Edit dashboard')
		expect(menu({ canEdit: true, isEditMode: true }).find('[data-testid="cog-edit-dashboard"]').text()).toBe('Edit dashboard')
	})

	it('marks the default dashboard', () => {
		expect(menu({ defaultUuid: 'u7' }).find('[data-testid="cog-set-default"]').text()).toBe('Default dashboard')
		expect(menu({ defaultUuid: 'other' }).find('[data-testid="cog-set-default"]').text()).toBe('Set as default')
		expect(menu({ dashboard: { id: 1 }, defaultUuid: '' }).find('[data-testid="cog-set-default"] .icon-Star').exists()).toBe(true)
	})

	it('offers unpublish for a published dashboard', () => {
		const wrapper = menu({ canManagePublication: true, dashboard: { id: 7, publicationStatus: 'published' } })

		expect(entries(wrapper)).toEqual(['cog-set-default', 'cog-unpublish', 'cog-schedule'])
	})

	it('asks the host for the action of each entry', async () => {
		const wrapper = menu({ source: 'user', canEdit: true, canShare: true, canManagePublication: true, canViewHistory: true })
		for (const button of wrapper.findAll('button')) {
			await button.trigger('click')
		}

		expect(Object.keys(wrapper.emitted())).toEqual(expect.arrayContaining([
			'toggleEdit', 'openConfig', 'addCustomWidget', 'setDefault', 'publish', 'schedule', 'versionHistory', 'share', 'delete',
		]))
		expect(wrapper.emitted('unpublish')).toBeUndefined()

		const published = menu({ canManagePublication: true, dashboard: { id: 7, publicationStatus: 'published' } })
		await published.find('[data-testid="cog-unpublish"]').trigger('click')
		expect(published.emitted('unpublish')).toHaveLength(1)
	})
})
