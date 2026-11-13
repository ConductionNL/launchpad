/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the DashboardBulkOperations UI around confirmAction
 * (which the golden spec covers): loading the list, row and select-all
 * selection, the confirm modal per action, cancelling, and the result and
 * error panels.
 */

import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardBulkOperations from '../DashboardBulkOperations.vue'
import { api } from '../../../services/api.js'

vi.mock('../../../services/api.js', () => ({
	api: {
		getVisibleDashboards: vi.fn(),
		bulkDeleteDashboards: vi.fn(),
		bulkMoveDashboards: vi.fn(),
		bulkStatusDashboards: vi.fn(),
		bulkReindexDashboards: vi.fn(),
	},
}))

const DASHBOARDS = [
	{ uuid: 'a', name: 'Alpha', publicationStatus: 'draft' },
	{ uuid: 'b', name: 'Beta' },
]

/**
 * Let pending promises settle.
 */
async function settle() {
	await new Promise((resolve) => setTimeout(resolve, 0))
	await new Promise((resolve) => setTimeout(resolve, 0))
}

/**
 * Mount the widget and wait for the dashboard list.
 *
 * @return {Promise<object>} The wrapper.
 */
async function mountWidget() {
	const wrapper = mount(DashboardBulkOperations)
	await settle()
	return wrapper
}

beforeEach(() => {
	vi.clearAllMocks()
	api.getVisibleDashboards.mockResolvedValue({ data: DASHBOARDS })
})

describe('DashboardBulkOperations interaction', () => {
	it('lists the dashboards with their status, defaulting to published', async () => {
		const wrapper = await mountWidget()

		const rows = wrapper.findAll('[data-test="bulk-ops-table"] tbody tr')
		expect(rows).toHaveLength(2)
		expect(rows[0].text()).toContain('Alpha')
		expect(rows[0].text()).toContain('draft')
		expect(rows[1].text()).toContain('published')
	})

	it('accepts a { dashboards } envelope and an envelope without one', async () => {
		api.getVisibleDashboards.mockResolvedValueOnce({
			data: { dashboards: [{ uuid: 'z', name: 'Zed' }] },
		})
		const wrapper = await mountWidget()
		expect(wrapper.vm.dashboards).toEqual([{ uuid: 'z', name: 'Zed' }])

		api.getVisibleDashboards.mockResolvedValueOnce({ data: {} })
		await wrapper.vm.loadDashboards()
		expect(wrapper.vm.dashboards).toEqual([])
	})

	it('shows an error when the dashboards cannot be loaded', async () => {
		api.getVisibleDashboards.mockRejectedValueOnce(new Error('down'))
		const wrapper = await mountWidget()

		expect(wrapper.vm.loading).toBe(false)
		expect(wrapper.find('[data-test="bulk-ops-error"]').text()).toBe(
			'Failed to load dashboards',
		)
	})

	it('toggles single rows on and off and enables the action select', async () => {
		const wrapper = await mountWidget()
		const select = wrapper.find('[data-test="bulk-ops-action-select"]')
		expect(select.attributes('disabled')).toBeDefined()

		const boxes = wrapper.findAll('[data-test="bulk-ops-row-select"]')
		await boxes[1].trigger('change')
		expect(wrapper.vm.selectedUuids).toEqual(['b'])
		expect(wrapper.vm.someSelected).toBe(true)
		expect(wrapper.vm.allSelected).toBe(false)
		expect(select.attributes('disabled')).toBeUndefined()

		await boxes[0].trigger('change')
		expect(wrapper.vm.selectedUuids).toEqual(['b', 'a'])
		expect(wrapper.vm.allSelected).toBe(true)

		await boxes[1].trigger('change')
		expect(wrapper.vm.selectedUuids).toEqual(['a'])
	})

	it('select-all selects every row and clears them again', async () => {
		const wrapper = await mountWidget()
		const all = wrapper.find('[data-test="bulk-ops-select-all"]')

		await all.setValue(true)
		expect(wrapper.vm.selectedUuids).toEqual(['a', 'b'])

		await all.setValue(false)
		expect(wrapper.vm.selectedUuids).toEqual([])
	})

	it('allSelected is false while there are no dashboards', async () => {
		api.getVisibleDashboards.mockResolvedValueOnce({ data: [] })
		const wrapper = await mountWidget()

		expect(wrapper.vm.allSelected).toBe(false)
	})

	it('Apply stays disabled until an action is chosen', async () => {
		const wrapper = await mountWidget()
		const run = wrapper.find('[data-test="bulk-ops-run"]')

		await wrapper.setData({ selectedUuids: ['a'] })
		expect(run.attributes('disabled')).toBeDefined()

		await wrapper.find('[data-test="bulk-ops-action-select"]').setValue('reindex')
		expect(run.attributes('disabled')).toBeUndefined()
	})

	it.each([
		['delete', 'Delete dashboards?', 'About to delete {n} dashboards. This cannot be undone.'],
		['move', 'Move dashboards?', 'About to re-parent {n} dashboards.'],
		['status', 'Set publication status?', 'About to update the publication status of {n} dashboards.'],
		['reindex', 'Reindex dashboards for search?', 'About to reindex {n} dashboards for unified search.'],
	])('Apply opens the %s confirmation and clears earlier results', async (action, title, message) => {
		const wrapper = await mountWidget()
		await wrapper.setData({
			selectedUuids: ['a', 'b'],
			selectedAction: action,
			errorMessage: 'old',
			resultSummary: { dryRun: true, changed: 1, skipped: 0, errors: [] },
		})

		await wrapper.find('[data-test="bulk-ops-run"]').trigger('click')

		expect(wrapper.vm.modal).toEqual({ action, title, message })
		expect(wrapper.vm.errorMessage).toBe('')
		expect(wrapper.vm.resultSummary).toBeNull()
		const modal = wrapper.find('[data-test="bulk-ops-modal"]')
		expect(modal.find('h4').text()).toBe(title)
		expect(modal.find('[data-test="bulk-ops-parent-uuid"]').exists()).toBe(action === 'move')
		expect(modal.find('[data-test="bulk-ops-status-select"]').exists()).toBe(action === 'status')
	})

	it('shows the publish date field only for a scheduled status', async () => {
		const wrapper = await mountWidget()
		await wrapper.setData({
			selectedUuids: ['a'],
			modal: { action: 'status', title: 'T', message: 'M' },
		})
		expect(wrapper.find('[data-test="bulk-ops-publish-at"]').exists()).toBe(false)

		await wrapper.find('[data-test="bulk-ops-status-select"]').setValue('scheduled')

		expect(wrapper.find('[data-test="bulk-ops-publish-at"]').exists()).toBe(true)
	})

	it('Cancel closes the modal without calling the API', async () => {
		const wrapper = await mountWidget()
		await wrapper.setData({
			selectedUuids: ['a'],
			modal: { action: 'delete', title: 'T', message: 'M' },
		})

		const cancel = wrapper
			.findAll('[data-test="bulk-ops-modal"] button')
			.find((b) => b.text() === 'Cancel')
		await cancel.trigger('click')

		expect(wrapper.vm.modal).toBeNull()
		expect(wrapper.find('[data-test="bulk-ops-modal"]').exists()).toBe(false)
		expect(api.bulkDeleteDashboards).not.toHaveBeenCalled()
	})

	it('OK runs a dry-run move and shows the preview count', async () => {
		api.bulkMoveDashboards.mockResolvedValue({
			data: { dryRun: true, wouldMoveCount: 2 },
		})
		const wrapper = await mountWidget()
		await wrapper.setData({
			selectedUuids: ['a', 'b'],
			modal: { action: 'move', title: 'T', message: 'M' },
		})
		await wrapper.find('[data-test="bulk-ops-parent-uuid"]').setValue('  parent-1  ')
		await wrapper.find('[data-test="bulk-ops-dryrun"]').setValue(true)

		await wrapper.find('[data-test="bulk-ops-confirm"]').trigger('click')
		await settle()

		expect(api.bulkMoveDashboards).toHaveBeenCalledWith(['a', 'b'], 'parent-1', {
			dryRun: true,
		})
		const result = wrapper.find('[data-test="bulk-ops-result"]')
		expect(result.text()).toBe('PREVIEW: would change {count} dashboards.')
		expect(wrapper.vm.selectedUuids).toEqual(['a', 'b'])
	})

	it('lists per-dashboard errors of a real run', async () => {
		api.bulkDeleteDashboards.mockResolvedValue({
			data: {
				deletedCount: 1,
				skippedCount: 1,
				errors: [{ uuid: 'b', reason: 'locked' }],
			},
		})
		const wrapper = await mountWidget()
		await wrapper.setData({
			selectedUuids: ['a', 'b'],
			modal: { action: 'delete', title: 'T', message: 'M' },
		})

		await wrapper.find('[data-test="bulk-ops-confirm"]').trigger('click')
		await settle()

		const result = wrapper.find('[data-test="bulk-ops-result"]')
		expect(result.find('p').text()).toBe('Changed {count} dashboards. Skipped {skipped}.')
		expect(result.findAll('li').map((li) => li.text())).toEqual(['b: locked'])
		expect(wrapper.vm.resultSummary).toEqual({
			dryRun: false,
			changed: 1,
			skipped: 1,
			errors: [{ uuid: 'b', reason: 'locked' }],
		})
		expect(wrapper.vm.selectedUuids).toEqual([])
	})
})
