/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * SavedViewsMenu and DashboardSavedViews: "My views" and "Shared views",
 * applying a view, saving the current filters only for an editor, and the
 * creator's edit, share and delete affordances
 * (drill-down-cross-widget-filter tasks 11-13; REQ-CWF-009).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import DashboardSavedViews from '../DashboardSavedViews.vue'
import SavedViewsMenu from '../SavedViewsMenu.vue'

vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) => text.replace(/{(\w+)}/g, (_match, key) => vars[key] ?? `{${key}}`),
}))

vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn() }))

vi.mock('@nextcloud/vue', () => ({
	NcButton: { name: 'NcButton', emits: ['click'], template: '<button @click="$emit(\'click\')"><slot /></button>' },
}))

vi.mock('@conduction/nextcloud-vue', () => ({
	NcModal: { name: 'NcModal', template: '<div><slot /></div>' },
	NcButton: { name: 'NcButton', template: '<button type="submit"><slot /></button>' },
	NcTextField: { name: 'NcTextField', props: ['modelValue'], emits: ['update:modelValue'], template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
	NcCheckboxRadioSwitch: { name: 'NcCheckboxRadioSwitch', template: '<label><slot /></label>' },
}))

const MINE = { id: 'v1', name: 'Q2 Trend', description: '', filters: [{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' }], isShared: false, createdBy: 'eva' }
const THEIRS = { id: 'v2', name: 'Team view', description: '', filters: [], isShared: true, createdBy: 'bob' }
const CLAUSE = { dimension: 'status', operator: 'eq', value: 'open', source: 'widgetClick', pinned: false }

describe('SavedViewsMenu', () => {
	it('lists the user\'s views with actions and shared views without', async () => {
		const menu = mount(SavedViewsMenu, { props: { mine: [MINE], shared: [THEIRS], canSave: false } })
		expect(menu.find('[data-testid="saved-views-list"]').exists()).toBe(false)

		await menu.find('[data-testid="saved-views-toggle"]').trigger('click')

		const mine = menu.find('[data-testid="saved-views-mine"]')
		const shared = menu.find('[data-testid="saved-views-shared"]')
		expect(mine.text()).toContain('Q2 Trend')
		expect(mine.find('[data-testid="saved-view-edit"]').exists()).toBe(true)
		expect(mine.find('[data-testid="saved-view-delete"]').exists()).toBe(true)
		expect(shared.text()).toContain('Team view')
		expect(shared.find('[data-testid="saved-view-edit"]').exists()).toBe(false)
		expect(shared.find('[data-testid="saved-view-delete"]').exists()).toBe(false)
		expect(menu.find('[data-testid="saved-views-save"]').exists()).toBe(false)
	})

	it('applying a view emits it and folds the list', async () => {
		const menu = mount(SavedViewsMenu, { props: { mine: [], shared: [THEIRS] } })
		await menu.find('[data-testid="saved-views-toggle"]').trigger('click')

		await menu.find('[data-testid="saved-view-apply"]').trigger('click')

		expect(menu.emitted('apply')).toEqual([[THEIRS]])
		expect(menu.find('[data-testid="saved-views-list"]').exists()).toBe(false)
	})

	it('share toggles the view\'s sharing', async () => {
		const menu = mount(SavedViewsMenu, { props: { mine: [MINE] } })
		await menu.find('[data-testid="saved-views-toggle"]').trigger('click')

		await menu.find('[data-testid="saved-view-share"]').trigger('click')

		expect(menu.emitted('share')).toEqual([[MINE, true]])
	})
})

/**
 * Mount the container over a fake service.
 *
 * @param {object} props Extra props.
 * @param {object} api The service.
 * @return {import('@vue/test-utils').VueWrapper}
 */
function container(props, api) {
	return mount(DashboardSavedViews, {
		props: { dashboardId: 'dash-1', userId: 'eva', canEdit: true, clauses: [CLAUSE], api, ...props },
	})
}

/**
 * A fake service holding views.
 *
 * @param {Array<object>} views The stored views.
 * @return {object}
 */
function fakeApi(views) {
	return {
		listSavedViews: vi.fn().mockResolvedValue(views),
		createSavedView: vi.fn().mockResolvedValue({ id: 'new' }),
		updateSavedView: vi.fn().mockResolvedValue({}),
		deleteSavedView: vi.fn().mockResolvedValue({}),
	}
}

describe('DashboardSavedViews', () => {
	it('loads the dashboard\'s views and splits them for the menu', async () => {
		const api = fakeApi([MINE, THEIRS])
		const wrapper = container({}, api)
		await flushPromises()

		expect(api.listSavedViews).toHaveBeenCalledWith('dash-1')
		const menu = wrapper.findComponent(SavedViewsMenu)
		expect(menu.props('mine')).toEqual([MINE])
		expect(menu.props('shared')).toEqual([THEIRS])
		expect(menu.props('canSave')).toBe(true)
	})

	it('offers saving only to an editor with active filters', async () => {
		const viewer = container({ canEdit: false }, fakeApi([]))
		const empty = container({ clauses: [] }, fakeApi([]))
		await flushPromises()

		expect(viewer.findComponent(SavedViewsMenu).props('canSave')).toBe(false)
		expect(empty.findComponent(SavedViewsMenu).props('canSave')).toBe(false)
	})

	it('saves the current clauses as a new view and reloads', async () => {
		const api = fakeApi([])
		const wrapper = container({}, api)
		await flushPromises()
		wrapper.findComponent(SavedViewsMenu).vm.$emit('save')
		await flushPromises()

		await wrapper.find('[data-testid="save-view-name"]').setValue('Open in Zeist')
		await wrapper.find('[data-testid="save-view-modal"]').trigger('submit')
		await flushPromises()

		expect(api.createSavedView).toHaveBeenCalledWith({
			name: 'Open in Zeist',
			description: '',
			isShared: false,
			dashboardId: 'dash-1',
			clauses: [CLAUSE],
			createdBy: 'eva',
		})
		expect(api.listSavedViews).toHaveBeenCalledTimes(2)
		expect(wrapper.find('[data-testid="save-view-modal"]').exists()).toBe(false)
	})

	it('shows a failed save in the modal', async () => {
		const api = fakeApi([])
		api.createSavedView.mockRejectedValue(new Error('403'))
		const wrapper = container({}, api)
		await flushPromises()
		wrapper.findComponent(SavedViewsMenu).vm.$emit('save')
		await flushPromises()

		await wrapper.find('[data-testid="save-view-name"]').setValue('X')
		await wrapper.find('[data-testid="save-view-modal"]').trigger('submit')
		await flushPromises()

		expect(wrapper.find('[data-testid="save-view-error"]').text()).toBe('The view could not be saved.')
	})

	it('edits, unshares and deletes only the user\'s own views', async () => {
		const api = fakeApi([MINE, THEIRS])
		const wrapper = container({}, api)
		await flushPromises()
		const menu = wrapper.findComponent(SavedViewsMenu)

		menu.vm.$emit('delete', THEIRS)
		menu.vm.$emit('share', THEIRS, false)
		menu.vm.$emit('edit', THEIRS)
		await flushPromises()
		expect(api.deleteSavedView).not.toHaveBeenCalled()
		expect(api.updateSavedView).not.toHaveBeenCalled()
		expect(wrapper.find('[data-testid="save-view-modal"]').exists()).toBe(false)

		menu.vm.$emit('share', MINE, true)
		menu.vm.$emit('delete', MINE)
		await flushPromises()
		expect(api.updateSavedView).toHaveBeenCalledWith('v1', { isShared: true })
		expect(api.deleteSavedView).toHaveBeenCalledWith('v1')

		menu.vm.$emit('edit', MINE)
		await flushPromises()
		expect(wrapper.find('[data-testid="save-view-name"]').element.value).toBe('Q2 Trend')
		await wrapper.find('[data-testid="save-view-name"]').setValue('Q3 Trend')
		await wrapper.find('[data-testid="save-view-modal"]').trigger('submit')
		await flushPromises()
		expect(api.updateSavedView).toHaveBeenLastCalledWith('v1', { name: 'Q3 Trend', description: '', isShared: false })
	})

	it('passes an applied view up', async () => {
		const wrapper = container({}, fakeApi([THEIRS]))
		await flushPromises()

		wrapper.findComponent(SavedViewsMenu).vm.$emit('apply', THEIRS)

		expect(wrapper.emitted('apply')).toEqual([[THEIRS]])
	})
})
