/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The app's console boundary: `warn` and `error` always forward their
 * arguments verbatim, `debug` only when `launchpad:debug` is set to '1',
 * read per call, and stays silent when storage throws.
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { logger } from '../logger.js'

beforeEach(() => {
	vi.spyOn(console, 'debug').mockImplementation(() => {})
	vi.spyOn(console, 'warn').mockImplementation(() => {})
	vi.spyOn(console, 'error').mockImplementation(() => {})
	localStorage.clear()
})

afterEach(() => {
	vi.restoreAllMocks()
	vi.unstubAllGlobals()
	localStorage.clear()
})

describe('logger', () => {
	it('forwards warn and error arguments verbatim', () => {
		const err = new Error('x')
		logger.warn('Unknown internal action:', 'foo')
		logger.error('Failed:', err)

		expect(console.warn).toHaveBeenCalledWith('Unknown internal action:', 'foo')
		expect(console.error).toHaveBeenCalledWith('Failed:', err)
	})

	it('keeps debug silent by default', () => {
		logger.debug('[WidgetStore] trace')

		expect(console.debug).not.toHaveBeenCalled()
	})

	it('writes debug only while the key is exactly "1", re-read on every call', () => {
		localStorage.setItem('launchpad:debug', '1')
		logger.debug('a', 1)
		expect(console.debug).toHaveBeenCalledWith('a', 1)

		localStorage.setItem('launchpad:debug', 'true')
		logger.debug('b')
		localStorage.removeItem('launchpad:debug')
		logger.debug('c')

		expect(console.debug).toHaveBeenCalledTimes(1)
	})

	it('stays silent when storage throws', () => {
		vi.stubGlobal('localStorage', {
			getItem: () => {
				throw new Error('SecurityError')
			},
		})

		expect(() => logger.debug('x')).not.toThrow()
		expect(console.debug).not.toHaveBeenCalled()
	})
})
