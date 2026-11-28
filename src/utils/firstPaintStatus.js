/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The page's one status line while its widgets load (first-paint-skeleton
 * task 7, REQ-FPS-005). The server prints it in the shell ("Loading
 * widgets", `role="status"`, beside More); the mounted page takes over that
 * same element, so a screen reader hears no second live region. It then
 * counts down the first-screen widgets still waiting ("Still loading 7
 * widgets"), changing its text at most once every 2 seconds, says "All
 * widgets loaded" once when the last one is there, and is emptied after.
 * Nothing is said per widget.
 *
 * A waiting widget is a card that carries `aria-busy="true"` (CnWidgetWrapper
 * sets it while the card waits in its placeholder). The first screen is the
 * grid items whose top edge lies inside the viewport when counting starts;
 * a widget below the fold is not counted, whenever it loads.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-loading-state-is-calm-and-accessible-req-fps-005
 */

import { translatePlural as n, translate as t } from '@nextcloud/l10n'
import { SHELL_ID } from './firstPaintHandover.js'

/** At most one text change per this many milliseconds. */
export const STATUS_INTERVAL = 2000

/** The class the status line carries on the mounted page. */
export const STATUS_CLASS = 'launchpad-status-line'

/**
 * Move the shell's status line into `slot` before the shell goes, so the
 * mounted page keeps the same element. Without a shell (or a shell without
 * one) a new status line is made there, reading "Loading widgets".
 *
 * @param {HTMLElement} slot Where the line lives on the mounted page.
 * @param {Document} [doc] The document.
 * @return {HTMLElement} The status line.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-loading-state-is-calm-and-accessible-req-fps-005
 */
export function adoptStatusLine(slot, doc = document) {
	const existing = slot.querySelector('[role="status"]')
	if (existing) {
		return existing
	}
	let node = doc.getElementById(SHELL_ID)?.querySelector('[role="status"]') || null
	if (!node) {
		node = doc.createElement('p')
		node.setAttribute('role', 'status')
		node.textContent = t('launchpad', 'Loading widgets')
	}
	node.classList.add(STATUS_CLASS)
	node.setAttribute('data-testid', 'launchpad-status-line')
	slot.appendChild(node)
	return node
}

/**
 * The text for a number of first-screen widgets still waiting.
 *
 * @param {number} waiting Widgets still waiting.
 * @return {string} The line.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-loading-state-is-calm-and-accessible-req-fps-005
 */
export function statusText(waiting) {
	if (waiting > 0) {
		return n('launchpad', 'Still loading %n widget', 'Still loading %n widgets', waiting)
	}
	return t('launchpad', 'All widgets loaded')
}

/**
 * Drive a status line from counts: a count goes on the line at most once per
 * `interval` (the newest count wins), 0 says "All widgets loaded" once and
 * one interval later the line is empty and the driver stops.
 *
 * @param {HTMLElement} node The status line.
 * @param {object} [options] Clock stand-ins for tests.
 * @param {number} [options.interval] Minimum time between two texts.
 * @param {() => number} [options.now] Current time in ms.
 * @param {(fn: () => void, ms: number) => unknown} [options.setTimer] setTimeout.
 * @param {(id: unknown) => void} [options.clearTimer] clearTimeout.
 * @return {{update: (waiting: number) => void, stop: () => void, done: () => boolean}} The driver.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-loading-state-is-calm-and-accessible-req-fps-005
 */
export function createStatusLine(node, {
	interval = STATUS_INTERVAL,
	now = () => Date.now(),
	setTimer = (fn, ms) => setTimeout(fn, ms),
	clearTimer = (id) => clearTimeout(id),
} = {}) {
	// The shell's "Loading widgets" counts as the first text.
	let lastChange = now()
	let pending = null
	let timer = null
	let finished = false

	const write = (text) => {
		if (node.textContent !== text) {
			node.textContent = text
		}
		lastChange = now()
	}

	const stop = () => {
		finished = true
		if (timer !== null) {
			clearTimer(timer)
			timer = null
		}
	}

	const flush = () => {
		timer = null
		if (finished || pending === null) {
			return
		}
		const waiting = pending
		pending = null
		write(statusText(waiting))
		if (waiting === 0) {
			finished = true
			timer = setTimer(() => {
				timer = null
				write('')
			}, interval)
		}
	}

	const update = (waiting) => {
		if (finished) {
			return
		}
		pending = Math.max(0, Number(waiting) || 0)
		if (timer !== null) {
			return
		}
		const wait = Math.max(0, lastChange + interval - now())
		if (wait === 0) {
			flush()
		} else {
			timer = setTimer(flush, wait)
		}
	}

	return { update, stop, done: () => finished }
}

/**
 * The grid items on the first screen: those whose top edge lies inside the
 * viewport.
 *
 * @param {HTMLElement} root The grid.
 * @param {number} viewportHeight The viewport's height.
 * @return {HTMLElement[]} The items.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-loading-state-is-calm-and-accessible-req-fps-005
 */
export function firstScreenItems(root, viewportHeight) {
	return [...root.querySelectorAll('.grid-stack-item')]
		.filter((item) => item.getBoundingClientRect().top < viewportHeight)
}

/**
 * Count the first-screen widgets still waiting on `node`, from now until the
 * last is there. Returns a function that stops counting.
 *
 * @param {object} options What to count and where to say it.
 * @param {HTMLElement} options.root The grid.
 * @param {HTMLElement} options.node The status line.
 * @param {number} [options.viewportHeight] The viewport's height.
 * @param {object} [options.clock] Clock stand-ins (see createStatusLine).
 * @param {typeof MutationObserver} [options.Observer] MutationObserver, or a stand-in.
 * @return {() => void} Stop counting.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-loading-state-is-calm-and-accessible-req-fps-005
 */
export function countWaitingWidgets({
	root,
	node,
	viewportHeight = globalThis.innerHeight || 0,
	clock = {},
	Observer = globalThis.MutationObserver,
}) {
	const line = createStatusLine(node, clock)
	const items = firstScreenItems(root, viewportHeight)
	const waiting = () => items.filter((item) => item.isConnected && item.querySelector('[aria-busy="true"]')).length
	let observer = null
	const stop = () => {
		observer?.disconnect()
		observer = null
		line.stop()
	}
	const recount = () => {
		line.update(waiting())
		if (line.done()) {
			observer?.disconnect()
			observer = null
		}
	}
	if (typeof Observer === 'function') {
		observer = new Observer(recount)
		observer.observe(root, { subtree: true, childList: true, attributes: true, attributeFilter: ['aria-busy'] })
	}
	recount()
	return stop
}
