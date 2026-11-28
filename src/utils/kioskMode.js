/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The `?kiosk=1` presentation flag (REQ-KIOSK-001): the dashboard full
 * screen, without chrome or edit affordances, the cursor hidden when idle,
 * and Escape back to the normal route. The flag changes no access check.
 */

/** How often a `?kiosk=1` view re-reads its widgets (REQ-KIOSK-004). */
export const KIOSK_REFRESH_MS = 300 * 1000

/** Idle time after which a kiosk screen hides the cursor. */
export const CURSOR_IDLE_MS = 10 * 1000

/** Class on `<html>` while kiosk mode is on. */
export const KIOSK_CLASS = 'launchpad-kiosk'

/** Class on `<html>` while the cursor is hidden. */
export const IDLE_CLASS = 'launchpad-kiosk--idle'

/**
 * Whether a query string asks for kiosk mode.
 *
 * @param {string} search A `location.search` string.
 * @return {boolean} True for `kiosk=1`.
 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
 */
export function kioskFlag(search) {
	return new URLSearchParams(search || '').get('kiosk') === '1'
}

// Read once at boot: the workspace rewrites the address after it mounts.
const BOOT_KIOSK = typeof window !== 'undefined' && kioskFlag(window.location.search)

/**
 * Whether this page was opened in kiosk mode.
 *
 * @return {boolean} True when the page was opened with `?kiosk=1`.
 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
 */
export function isKioskMode() {
	return BOOT_KIOSK
}

/**
 * The same address without the kiosk flag.
 *
 * @param {string} href An absolute URL.
 * @return {string} The URL with `kiosk` removed from its query.
 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
 */
export function withoutKiosk(href) {
	const url = new URL(href)
	url.searchParams.delete('kiosk')
	return url.toString()
}

/**
 * Hide the cursor after `idleMs` without pointer movement, and show it again
 * on the next movement.
 *
 * @param {Document} doc The document.
 * @param {number} [idleMs] The idle time.
 * @return {() => void} Stops watching and shows the cursor.
 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
 */
export function hideCursorWhenIdle(doc, idleMs = CURSOR_IDLE_MS) {
	const root = doc.documentElement
	let timer = null
	const arm = () => {
		root.classList.remove(IDLE_CLASS)
		clearTimeout(timer)
		timer = setTimeout(() => root.classList.add(IDLE_CLASS), idleMs)
	}
	doc.addEventListener('mousemove', arm)
	doc.addEventListener('pointermove', arm)
	arm()
	return () => {
		clearTimeout(timer)
		doc.removeEventListener('mousemove', arm)
		doc.removeEventListener('pointermove', arm)
		root.classList.remove(IDLE_CLASS)
	}
}

/**
 * Turn kiosk mode on for an authenticated dashboard view: mark `<html>`,
 * hide the cursor when idle, and leave for the normal route on Escape.
 *
 * @param {Window} win The window.
 * @return {() => void} Turns it off again.
 * @spec openspec/specs/dashboard-kiosk-mode/spec.md
 */
export function startKioskView(win) {
	const doc = win.document
	doc.documentElement.classList.add(KIOSK_CLASS)
	const stopCursor = hideCursorWhenIdle(doc)
	const onKey = (event) => {
		if (event.key === 'Escape') {
			win.location.assign(withoutKiosk(win.location.href))
		}
	}
	doc.addEventListener('keydown', onKey)
	return () => {
		stopCursor()
		doc.removeEventListener('keydown', onKey)
		doc.documentElement.classList.remove(KIOSK_CLASS)
	}
}
