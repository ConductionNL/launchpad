/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `useTileClickTracking` (REQ-TANLT-002,
 * REQ-TANLT-003). Verifies the hook fires exactly once per activation
 * and is suppressed when tracking is not active (analytics disabled
 * or the user opted out).
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import axios from '@nextcloud/axios'
import { useTileClickTracking, __resetTileClickTrackingForTest } from '../useTileClickTracking.js'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => path,
}))

describe('useTileClickTracking', () => {
	beforeEach(() => {
		vi.clearAllMocks()
		__resetTileClickTrackingForTest()
	})

	it('REQ-TANLT-002: fires exactly one POST /api/tile-click/{id} per activation when tracking is active', async () => {
		axios.get.mockResolvedValue({ data: { enabled: true } })
		axios.post.mockResolvedValue({ status: 204 })

		const { recordTileClick } = useTileClickTracking()
		recordTileClick(42)

		// Wait for the config fetch + record call microtasks to settle.
		await new Promise((resolve) => setTimeout(resolve, 0))
		await new Promise((resolve) => setTimeout(resolve, 0))

		expect(axios.post).toHaveBeenCalledTimes(1)
		expect(axios.post).toHaveBeenCalledWith('/apps/launchpad/api/tile-click/42', {})
	})

	it('REQ-TANLT-003: suppresses the record call when tracking is disabled (globally off or opted out)', async () => {
		axios.get.mockResolvedValue({ data: { enabled: false } })

		const { recordTileClick } = useTileClickTracking()
		recordTileClick(42)

		await new Promise((resolve) => setTimeout(resolve, 0))
		await new Promise((resolve) => setTimeout(resolve, 0))

		expect(axios.post).not.toHaveBeenCalled()
	})

	it('fails open (still records) when the config fetch itself errors', async () => {
		axios.get.mockRejectedValue(new Error('network error'))
		axios.post.mockResolvedValue({ status: 204 })

		const { recordTileClick } = useTileClickTracking()
		recordTileClick(7)

		await new Promise((resolve) => setTimeout(resolve, 0))
		await new Promise((resolve) => setTimeout(resolve, 0))

		expect(axios.post).toHaveBeenCalledTimes(1)
	})

	it('never throws when the record POST itself rejects', async () => {
		axios.get.mockResolvedValue({ data: { enabled: true } })
		axios.post.mockRejectedValue(new Error('boom'))
		const warnSpy = vi.spyOn(console, 'warn').mockImplementation(() => null)

		const { recordTileClick } = useTileClickTracking()
		expect(() => recordTileClick(1)).not.toThrow()

		await new Promise((resolve) => setTimeout(resolve, 0))
		await new Promise((resolve) => setTimeout(resolve, 0))

		expect(warnSpy).toHaveBeenCalled()
		warnSpy.mockRestore()
	})

	it('does nothing when placementId is missing/empty', async () => {
		const { recordTileClick } = useTileClickTracking()
		recordTileClick(null)
		recordTileClick(undefined)
		recordTileClick('')

		await new Promise((resolve) => setTimeout(resolve, 0))

		expect(axios.get).not.toHaveBeenCalled()
		expect(axios.post).not.toHaveBeenCalled()
	})

	it('shares one config fetch across two concurrent activations of different tiles', async () => {
		axios.get.mockResolvedValue({ data: { enabled: true } })
		axios.post.mockResolvedValue({ status: 204 })

		const { recordTileClick } = useTileClickTracking()
		recordTileClick(1)
		recordTileClick(2)

		await new Promise((resolve) => setTimeout(resolve, 0))
		await new Promise((resolve) => setTimeout(resolve, 0))

		expect(axios.get).toHaveBeenCalledTimes(1)
		expect(axios.post).toHaveBeenCalledTimes(2)
	})
})
