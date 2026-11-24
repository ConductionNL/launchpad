/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The token-scoped live widget reads of a public link and a kiosk screen
 * (REQ-PSHR-011).
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { kioskWidgetReader, shareWidgetReader } from '../publicWidgetClient.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => '/index.php' + path,
}))

beforeEach(() => {
	axios.get.mockReset()
	axios.get.mockResolvedValue({ data: { widget: 'weather', data: { tempValue: 12 } } })
})

describe('publicWidgetClient', () => {
	it('reads a placement through the share token, with the password header', async () => {
		const read = shareWidgetReader('abc', 'secret')
		const result = await read(7)
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/s/abc/placements/7', { headers: { 'X-Share-Password': 'secret' } })
		expect(result).toEqual({ widget: 'weather', data: { tempValue: 12 } })
	})

	it('encodes the token', async () => {
		await shareWidgetReader('a/b c')(7)
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/s/a%2Fb%20c/placements/7', { headers: {} })
	})

	it('sends no password header for an open link', async () => {
		await shareWidgetReader('abc')(7)
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/s/abc/placements/7', { headers: {} })
	})

	it('reads a placement through the kiosk playlist token', async () => {
		await kioskWidgetReader('kiosk1')(9)
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/kiosk/kiosk1/placements/9', { headers: {} })
	})

	it('answers an empty object when the response has no body', async () => {
		axios.get.mockResolvedValue({})
		expect(await kioskWidgetReader('kiosk1')(9)).toEqual({})
	})

	it('lets a refused read reject, so the widget shows its failed state', async () => {
		axios.get.mockRejectedValue(new Error('404'))
		await expect(shareWidgetReader('abc')(7)).rejects.toThrow('404')
	})
})
