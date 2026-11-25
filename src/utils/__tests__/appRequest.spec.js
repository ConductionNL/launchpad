// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>

/**
 * Asking IT for a missing app from the Add tiles dialog (REQ-TILE-043).
 */

import { describe, expect, it, vi } from 'vitest'
import { sendAppRequest } from '../appRequest.js'

describe('sendAppRequest', () => {
	it('sends the trimmed name and purpose and says it was sent', async () => {
		const fn = vi.fn().mockResolvedValue({})
		expect(await sendAppRequest(fn, ' Kaartviewer ', ' percelen ')).toEqual({ failed: false, message: 'Your request was sent to IT' })
		expect(fn).toHaveBeenCalledWith({ appName: 'Kaartviewer', purpose: 'percelen' })
	})

	it('names the open-request limit', async () => {
		const fn = vi.fn().mockRejectedValue({ response: { data: { code: 'request_limit' } } })
		expect(await sendAppRequest(fn, 'x', '')).toEqual({ failed: true, message: 'You have 10 open requests. Wait for an answer first.' })
	})

	it('says when the request could not be sent', async () => {
		const fn = vi.fn().mockRejectedValue(new Error('offline'))
		expect(await sendAppRequest(fn, 'x', '')).toEqual({ failed: true, message: 'Your request could not be sent. Try again later.' })
	})
})
