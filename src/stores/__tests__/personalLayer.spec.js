/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The personal-layer store reads, hides, shows again and resets through
 * PersonalLayerApiController (dashboards-personal-hide-ui). Shapes are the
 * controller's: GET answers `{id, dashboardId, overrides, hidden, updatedAt,
 * hasLayer, hiddenPlacements}`, a compulsory refusal is 403
 * `{error: 'placement_compulsory', placementId}`.
 */

import axios from '@nextcloud/axios'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { usePersonalLayerStore } from '../personalLayer.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

const url = '/index.php/apps/launchpad/api/dashboards/7/personal-layer'
function layer(hidden, hiddenPlacements = []) {
	return {
		data: {
			id: 3,
			dashboardId: 7,
			overrides: { 1: { sortOrder: 2 } },
			hidden,
			updatedAt: null,
			hasLayer: hidden.length > 0,
			hiddenPlacements,
		},
	}
}

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
})

describe('personal layer store', () => {
	it('loads the layer and the hidden widgets', async () => {
		axios.get.mockResolvedValue(
			layer([2], [{ id: 2, widgetId: 'weather', customTitle: 'Weather' }]),
		)
		const store = usePersonalLayerStore()
		await store.load(7)
		expect(axios.get).toHaveBeenCalledWith(url)
		expect(store.hidden).toEqual([2])
		expect(store.hiddenPlacements[0].customTitle).toBe('Weather')
	})

	it('REQ-PERSUI-001: hiding sends the whole set and keeps the overrides', async () => {
		axios.get.mockResolvedValue(layer([2]))
		axios.put.mockResolvedValue({ data: { saved: true } })
		const store = usePersonalLayerStore()
		await store.load(7)
		await store.hide(7, 5)
		expect(axios.put).toHaveBeenCalledWith(url, {
			overrides: { 1: { sortOrder: 2 } },
			hidden: [2, 5],
		})
	})

	it('REQ-PERSUI-001: a compulsory refusal names the placement', async () => {
		axios.get.mockResolvedValue(layer([]))
		axios.put.mockRejectedValue(
			Object.assign(new Error('x'), {
				response: {
					status: 403,
					data: { error: 'placement_compulsory', placementId: 9 },
				},
			}),
		)
		const store = usePersonalLayerStore()
		await store.load(7)
		await expect(store.hide(7, 9)).rejects.toMatchObject({
			compulsory: true,
			placementId: 9,
		})
	})

	it('REQ-PERSUI-002: show again removes one id; reset deletes the layer', async () => {
		axios.get.mockResolvedValue(layer([2, 5]))
		axios.put.mockResolvedValue({ data: { saved: true } })
		axios.delete.mockResolvedValue({ data: { reset: true } })
		const store = usePersonalLayerStore()
		await store.load(7)
		await store.showAgain(7, 2)
		expect(axios.put).toHaveBeenCalledWith(url, {
			overrides: { 1: { sortOrder: 2 } },
			hidden: [5],
		})
		await store.reset(7)
		expect(axios.delete).toHaveBeenCalledWith(url)
	})
})
