/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The phone install banner (board LpMobiel, REQ-PLB-009): when it shows, the
 * browser's own install prompt, and the 30-day "Not now" kept per device.
 * LaunchPad ships no manifest or service worker of its own (REQ-PLB-006); it
 * only offers what the browser already can.
 */

/** Screens narrower than this count as a phone. */
export const PHONE_MAX_WIDTH = 768

/** How long "Not now" hides the banner on a device. */
export const NOT_NOW_MS = 30 * 24 * 60 * 60 * 1000

/** Browser storage key for the moment "Not now" was chosen. */
export const NOT_NOW_KEY = 'launchpad.installBanner.notNowAt'

let capturedPrompt = null

/**
 * Keep the browser's install prompt for later. Browsers fire
 * `beforeinstallprompt` once, early; the banner opens it when Install is
 * chosen. Without this the browser shows its own mini-infobar instead.
 *
 * @param {Event} event The `beforeinstallprompt` event.
 * @return {void}
 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */
export function capturePrompt(event) {
	if (event && typeof event.preventDefault === 'function') {
		event.preventDefault()
	}
	capturedPrompt = event ?? null
}

/**
 * The captured install prompt, or null where the browser offers none
 * (Safari on iOS).
 *
 * @return {object|null} The prompt event.
 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */
export function installPrompt() {
	return capturedPrompt
}

/**
 * Forget the captured prompt: a prompt can be shown only once.
 *
 * @return {void}
 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */
export function clearPrompt() {
	capturedPrompt = null
}

/**
 * Whether the page already runs as an installed app.
 *
 * @param {Window} win The window.
 * @return {boolean} True in standalone display mode.
 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */
export function isStandalone(win) {
	if (!win) {
		return false
	}
	if (win.navigator && win.navigator.standalone === true) {
		return true
	}
	try {
		return typeof win.matchMedia === 'function' && win.matchMedia('(display-mode: standalone)').matches === true
	} catch {
		return false
	}
}

/**
 * When "Not now" was last chosen on this device, or null. A blocked or
 * broken storage reads as never, so the banner only shows again.
 *
 * @param {Storage|null} storage The browser storage.
 * @return {number|null} Milliseconds since the epoch.
 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */
export function readNotNowAt(storage) {
	try {
		const value = Number(storage?.getItem(NOT_NOW_KEY))
		return Number.isFinite(value) && value > 0 ? value : null
	} catch {
		return null
	}
}

/**
 * Remember "Not now" on this device. A blocked storage is ignored.
 *
 * @param {Storage|null} storage The browser storage.
 * @param {number} now Milliseconds since the epoch.
 * @return {void}
 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */
export function writeNotNowAt(storage, now) {
	try {
		storage?.setItem(NOT_NOW_KEY, String(now))
	} catch {
		// Blocked storage: the banner shows again next time, nothing breaks.
	}
}

/**
 * Whether the install banner shows: on a phone-width screen, in a browser
 * tab (not installed), outside the kiosk and public views, and not within
 * 30 days of "Not now".
 *
 * @param {object} state What the page knows.
 * @param {number} state.width The viewport width in px.
 * @param {boolean} state.standalone Whether the page runs as an installed app.
 * @param {boolean} state.kiosk Whether this is the kiosk view.
 * @param {boolean} state.isPublic Whether this is a public page.
 * @param {number|null} state.notNowAt When "Not now" was chosen, or null.
 * @param {number} state.now Milliseconds since the epoch.
 * @return {boolean} True when the banner shows.
 * @spec openspec/changes/phone-install-banner/specs/platform-baseline/spec.md#requirement-a-phone-offers-to-put-launchpad-on-the-home-screen-req-plb-009
 */
export function shouldShowInstallBanner({ width, standalone, kiosk, isPublic, notNowAt, now }) {
	if (!(Number(width) < PHONE_MAX_WIDTH) || standalone === true || kiosk === true || isPublic === true) {
		return false
	}
	return notNowAt === null || notNowAt === undefined || now - notNowAt >= NOT_NOW_MS
}

if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
	window.addEventListener('beforeinstallprompt', capturePrompt)
}
