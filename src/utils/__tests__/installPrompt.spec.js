/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * When the phone install banner shows, and the 30-day Not now
 * (platform-baseline REQ-PLB-009, board LpMobiel).
 *
 * @spec openspec/changes/archive/2026-11-24-phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	capturePrompt,
	clearPrompt,
	installPrompt,
	isStandalone,
	NOT_NOW_KEY,
	NOT_NOW_MS,
	readNotNowAt,
	shouldShowInstallBanner,
	writeNotNowAt,
} from '../installPrompt.js'

const NOW = Date.UTC(2026, 9, 5, 11, 0)
const PHONE = { width: 390, standalone: false, kiosk: false, isPublic: false, notNowAt: null, now: NOW }

afterEach(() => {
	clearPrompt()
})

describe('shouldShowInstallBanner', () => {
	it('shows on a phone in a browser tab', () => {
		expect(shouldShowInstallBanner(PHONE)).toBe(true)
	})

	it.each([
		[767, true],
		[768, false],
		[1440, false],
	])('at %i px shows: %s', (width, shown) => {
		expect(shouldShowInstallBanner({ ...PHONE, width })).toBe(shown)
	})

	it('hides once the page runs as an installed app', () => {
		expect(shouldShowInstallBanner({ ...PHONE, standalone: true })).toBe(false)
	})

	it('hides in the kiosk view and on a public page', () => {
		expect(shouldShowInstallBanner({ ...PHONE, kiosk: true })).toBe(false)
		expect(shouldShowInstallBanner({ ...PHONE, isPublic: true })).toBe(false)
	})

	it('stays hidden for 30 days after Not now, then shows again', () => {
		expect(shouldShowInstallBanner({ ...PHONE, notNowAt: NOW - NOT_NOW_MS + 1000 })).toBe(false)
		expect(shouldShowInstallBanner({ ...PHONE, notNowAt: NOW - NOT_NOW_MS })).toBe(true)
	})

	it('hides when the width is unknown', () => {
		expect(shouldShowInstallBanner({ ...PHONE, width: undefined })).toBe(false)
	})
})

describe('Not now storage', () => {
	it('writes and reads the moment', () => {
		const store = new Map()
		const storage = { getItem: (k) => store.get(k) ?? null, setItem: (k, v) => store.set(k, v) }
		writeNotNowAt(storage, NOW)
		expect(store.get(NOT_NOW_KEY)).toBe(String(NOW))
		expect(readNotNowAt(storage)).toBe(NOW)
	})

	it('reads never from an empty or garbled value', () => {
		expect(readNotNowAt({ getItem: () => null })).toBeNull()
		expect(readNotNowAt({ getItem: () => 'soon' })).toBeNull()
		expect(readNotNowAt(null)).toBeNull()
	})

	it('a blocked storage only means the banner shows', () => {
		const blocked = {
			getItem: () => { throw new Error('SecurityError') },
			setItem: () => { throw new Error('QuotaExceededError') },
		}
		expect(readNotNowAt(blocked)).toBeNull()
		expect(() => writeNotNowAt(blocked, NOW)).not.toThrow()
	})
})

describe('isStandalone', () => {
	it('reads the standalone display mode', () => {
		expect(isStandalone({ navigator: {}, matchMedia: () => ({ matches: true }) })).toBe(true)
		expect(isStandalone({ navigator: {}, matchMedia: () => ({ matches: false }) })).toBe(false)
	})

	it('reads Safari on iOS opened from the home screen', () => {
		expect(isStandalone({ navigator: { standalone: true } })).toBe(true)
	})

	it('is false without a window or when matchMedia throws', () => {
		expect(isStandalone(null)).toBe(false)
		expect(isStandalone({ navigator: {}, matchMedia: () => { throw new Error('x') } })).toBe(false)
	})
})

describe('the captured prompt', () => {
	it('keeps the beforeinstallprompt event and stops the browser bar', () => {
		const event = { preventDefault: vi.fn() }
		capturePrompt(event)
		expect(event.preventDefault).toHaveBeenCalled()
		expect(installPrompt()).toBe(event)
		clearPrompt()
		expect(installPrompt()).toBeNull()
	})

	it('is captured from the window event', () => {
		const event = new Event('beforeinstallprompt')
		window.dispatchEvent(event)
		expect(installPrompt()).toBe(event)
		expect(event.defaultPrevented).toBe(false)
	})
})
