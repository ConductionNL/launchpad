/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace page's failure paths: a failed metadata-field or filter
 * read, the personal layer refusing "show again" or "reset", the schedule
 * refusals the dialog explains, a refused version-support probe, and
 * deleting a dashboard (no target, a plain failure). Run against the real
 * Views methods on a stand-in host; only HTTP, stores, dialogs and the
 * logger are doubled.
 */

import { showError } from '@nextcloud/dialogs'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { api } from '../../services/api.js'
import { logger } from '../../utils/logger.js'

vi.mock('../../services/api.js', () => ({
	api: {
		getMetadataFieldDefinitions: vi.fn(),
		getVisibleDashboards: vi.fn(),
		scheduleDashboard: vi.fn(),
		listVersions: vi.fn(),
		deleteDashboard: vi.fn(),
	},
}))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('../../utils/logger.js', () => ({
	logger: { debug: vi.fn(), info: vi.fn(), warn: vi.fn(), error: vi.fn() },
}))

let Views

/**
 * A host carrying the real Views methods.
 *
 * @param {object} state Data and overrides.
 * @return {object} the host.
 */
function makeHost(state = {}) {
	const host = { activeDashboard: null, versionSupport: {}, isAdmin: false }
	for (const [name, fn] of Object.entries(Views.methods)) {
		host[name] = fn.bind(host)
	}
	host.switchDashboard = vi.fn()
	return Object.assign(host, state)
}

/**
 * An error as axios rejects with it.
 *
 * @param {number} status HTTP status.
 * @param {object} data Response body.
 * @return {Error} the error.
 */
function httpError(status, data = {}) {
	return Object.assign(new Error(`HTTP ${status}`), { response: { status, data } })
}

beforeEach(async () => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
	Views = (await import('../Views.vue')).default
})

describe('Views metadata fields and filter', () => {
	it('keeps the fields from the server and empties them when the read fails', async () => {
		api.getMetadataFieldDefinitions.mockResolvedValue({ data: { fields: [{ key: 'team' }] } })
		const host = makeHost({ detailFields: [] })
		await host.loadDetailFields()
		expect(host.detailFields).toEqual([{ key: 'team' }])

		api.getMetadataFieldDefinitions.mockRejectedValue(new Error('down'))
		await host.loadDetailFields()
		expect(host.detailFields).toEqual([])
	})

	it('a failed filter read is logged and clears the filter', async () => {
		const error = new Error('down')
		api.getVisibleDashboards.mockRejectedValue(error)
		const host = makeHost({ detailFilterUuids: ['a'] })

		await host.onDetailFilter({ team: 'hr' })

		expect(api.getVisibleDashboards).toHaveBeenCalledWith({ team: 'hr' })
		expect(logger.error).toHaveBeenCalledWith('Failed to filter dashboards:', error)
		expect(host.detailFilterUuids).toBeNull()
	})
})

describe('Views personal layer refusals', () => {
	it('"Hide for me" does nothing without an active dashboard', async () => {
		const layer = { hide: vi.fn() }
		const host = makeHost({ activeDashboard: null, personalLayer: layer, availableWidgets: [] })

		await host.onHideForMe({ id: 9 })

		expect(layer.hide).not.toHaveBeenCalled()
		expect(host.switchDashboard).not.toHaveBeenCalled()
	})

	it('a refused "show again" says so and does not re-read', async () => {
		const layer = { showAgain: vi.fn().mockRejectedValue(new Error('no')) }
		const host = makeHost({ activeDashboard: { id: 4 }, personalLayer: layer })

		await host.onShowAgain(9)

		expect(layer.showAgain).toHaveBeenCalledWith(4, 9)
		expect(showError).toHaveBeenCalledWith('The widget could not be shown again.')
		expect(host.switchDashboard).not.toHaveBeenCalled()
	})

	it('a refused reset says so and does not re-read', async () => {
		const layer = { reset: vi.fn().mockRejectedValue(new Error('no')) }
		const host = makeHost({ activeDashboard: { id: 4 }, personalLayer: layer })

		await host.onResetPersonalView()

		expect(layer.reset).toHaveBeenCalledWith(4)
		expect(showError).toHaveBeenCalledWith('Your view could not be reset.')
		expect(host.switchDashboard).not.toHaveBeenCalled()
	})
})

describe('Views schedule refusals', () => {
	it('explains an end before the start, and any other refusal, keeping the dialog open', async () => {
		const host = makeHost({
			activeDashboard: { uuid: 'd', isOwner: true },
			scheduleDialogOpen: true,
			scheduleError: '',
		})

		api.scheduleDashboard.mockRejectedValue(
			httpError(400, { message: 'unpublishAt must be after publishAt' }),
		)
		await host.onScheduleActive({ publishAt: 'b', unpublishAt: 'a' })
		expect(api.scheduleDashboard).toHaveBeenCalledWith('d', 'b', 'a')
		expect(host.scheduleError).toBe('The dashboard has to go live before it comes down.')
		expect(host.scheduleDialogOpen).toBe(true)

		api.scheduleDashboard.mockRejectedValue(new Error('offline'))
		await host.onScheduleActive({ publishAt: 'b', unpublishAt: null })
		expect(host.scheduleError).toBe('The dashboard could not be scheduled.')
		expect(host.scheduleDialogOpen).toBe(true)
	})
})

describe('Views version support probe', () => {
	it('a refused version listing reads as not offered', async () => {
		api.listVersions.mockRejectedValue(httpError(403))
		const host = makeHost({ activeDashboard: { uuid: 'd', isOwner: true }, versionSupport: { other: true } })

		await host.checkVersionSupport()

		expect(api.listVersions).toHaveBeenCalledWith('d')
		expect(host.versionSupport).toEqual({ other: true, d: false })
	})
})

describe('Views delete dashboard', () => {
	it('does nothing without a confirmed target', async () => {
		const host = makeHost({ deleteTarget: null, deleteChildCount: 0 })

		await host.confirmDeleteDashboard()

		expect(api.deleteDashboard).not.toHaveBeenCalled()
	})

	it('a plain failure is logged and keeps the dialog on its target', async () => {
		const error = httpError(500)
		api.deleteDashboard.mockRejectedValue(error)
		const target = { id: 7 }
		const host = makeHost({ deleteTarget: target, deleteChildCount: 0 })
		host.loadDashboards = vi.fn()
		host.closeConfigModal = vi.fn()

		await host.confirmDeleteDashboard()

		expect(api.deleteDashboard).toHaveBeenCalledWith(7, false)
		expect(logger.error).toHaveBeenCalledWith('Failed to delete dashboard:', error)
		expect(host.deleteTarget).toBe(target)
		expect(host.deleteChildCount).toBe(0)
		expect(host.loadDashboards).not.toHaveBeenCalled()
	})
})
