/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	CURSOR_IDLE_MS,
	hideCursorWhenIdle,
	IDLE_CLASS,
	KIOSK_CLASS,
	kioskFlag,
	startKioskView,
	withoutKiosk,
} from '../kioskMode.js'

afterEach(() => {
	vi.useRealTimers()
	document.documentElement.className = ''
})

describe('kioskFlag', () => {
	it('is on only for kiosk=1', () => {
		expect(kioskFlag('?kiosk=1')).toBe(true)
		expect(kioskFlag('?a=b&kiosk=1')).toBe(true)
		expect(kioskFlag('?kiosk=0')).toBe(false)
		expect(kioskFlag('?kiosk=true')).toBe(false)
		expect(kioskFlag('')).toBe(false)
		expect(kioskFlag(undefined)).toBe(false)
	})
})

describe('withoutKiosk', () => {
	it('drops the flag and keeps the rest of the address', () => {
		expect(withoutKiosk('https://nc.example/index.php/apps/launchpad/lobby?kiosk=1')).toBe(
			'https://nc.example/index.php/apps/launchpad/lobby',
		)
		expect(withoutKiosk('https://nc.example/apps/launchpad/x?a=1&kiosk=1#top')).toBe(
			'https://nc.example/apps/launchpad/x?a=1#top',
		)
	})
})

describe('hideCursorWhenIdle', () => {
	it('hides the cursor after the idle time and shows it on movement', () => {
		vi.useFakeTimers()
		const stop = hideCursorWhenIdle(document)
		const root = document.documentElement

		vi.advanceTimersByTime(CURSOR_IDLE_MS - 1)
		expect(root.classList.contains(IDLE_CLASS)).toBe(false)
		vi.advanceTimersByTime(1)
		expect(root.classList.contains(IDLE_CLASS)).toBe(true)

		document.dispatchEvent(new Event('mousemove'))
		expect(root.classList.contains(IDLE_CLASS)).toBe(false)

		stop()
		vi.advanceTimersByTime(CURSOR_IDLE_MS)
		expect(root.classList.contains(IDLE_CLASS)).toBe(false)
	})
})

describe('startKioskView', () => {
	it('marks the page, leaves for the normal route on Escape, and undoes it all', () => {
		const assign = vi.fn()
		const win = { document, location: { href: 'https://nc.example/apps/launchpad/lobby?kiosk=1', assign } }
		const stop = startKioskView(win)
		expect(document.documentElement.classList.contains(KIOSK_CLASS)).toBe(true)

		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }))
		expect(assign).not.toHaveBeenCalled()
		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
		expect(assign).toHaveBeenCalledWith('https://nc.example/apps/launchpad/lobby')

		stop()
		expect(document.documentElement.classList.contains(KIOSK_CLASS)).toBe(false)
		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
		expect(assign).toHaveBeenCalledTimes(1)
	})
})
