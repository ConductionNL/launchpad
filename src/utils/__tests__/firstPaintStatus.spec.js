/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The page's one status line while its widgets load (first-paint-skeleton
 * task 7, REQ-FPS-005): the same element from the shell to the mounted page,
 * a countdown of the first-screen widgets at most once per 2 seconds, "All
 * widgets loaded" once, then empty; widgets below the fold are not counted.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-loading-state-is-calm-and-accessible-req-fps-005
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { SHELL_ID } from '../firstPaintHandover.js'
import { adoptStatusLine, countWaitingWidgets, createStatusLine, firstScreenItems, statusText } from '../firstPaintStatus.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text) => text,
	translatePlural: (_app, one, many, count) => (count === 1 ? one : many).replace('%n', String(count)),
}))

/**
 * A clock the tests move by hand.
 *
 * @return {{now: Function, setTimer: Function, clearTimer: Function, advance: Function}} The clock.
 */
function fakeClock() {
	let time = 0
	let timers = []
	let nextId = 1
	return {
		now: () => time,
		setTimer: (fn, ms) => {
			const id = nextId++
			timers.push({ id, at: time + ms, fn })
			return id
		},
		clearTimer: (id) => {
			timers = timers.filter((timer) => timer.id !== id)
		},
		advance(ms) {
			const until = time + ms
			for (;;) {
				const due = timers.filter((timer) => timer.at <= until).sort((a, b) => a.at - b.at)[0]
				if (!due) {
					break
				}
				timers = timers.filter((timer) => timer !== due)
				time = due.at
				due.fn()
			}
			time = until
		},
	}
}

/**
 * A grid item whose top edge sits at `top`, waiting or not.
 *
 * @param {number} top The item's top in px.
 * @param {boolean} busy Whether its card waits.
 * @return {HTMLElement} The item.
 */
function item(top, busy) {
	const el = document.createElement('div')
	el.className = 'grid-stack-item'
	el.innerHTML = `<div class="cn-widget-wrapper"${busy ? ' aria-busy="true"' : ''}></div>`
	el.getBoundingClientRect = () => ({ top, bottom: top + 100, left: 0, right: 100, width: 100, height: 100 })
	return el
}

beforeEach(() => {
	document.body.innerHTML = ''
})

describe('adoptStatusLine', () => {
	it('moves the shell\'s status element into the page: the same node, not a copy', () => {
		document.body.innerHTML = `<div id="${SHELL_ID}"><p class="lp-fp__s" role="status">Loading widgets</p></div><span id="slot"></span>`
		const shellNode = document.querySelector('[role="status"]')
		const slot = document.getElementById('slot')

		const node = adoptStatusLine(slot)

		expect(node).toBe(shellNode)
		expect(slot.contains(node)).toBe(true)
		expect(document.getElementById(SHELL_ID).querySelector('[role="status"]')).toBeNull()
		expect(document.querySelectorAll('[role="status"]')).toHaveLength(1)
		expect(node.textContent).toBe('Loading widgets')
	})

	it('makes one status line reading "Loading widgets" when there is no shell, and only one', () => {
		document.body.innerHTML = '<span id="slot"></span>'
		const slot = document.getElementById('slot')

		const node = adoptStatusLine(slot)
		const again = adoptStatusLine(slot)

		expect(again).toBe(node)
		expect(node.getAttribute('role')).toBe('status')
		expect(node.textContent).toBe('Loading widgets')
		expect(document.querySelectorAll('[role="status"]')).toHaveLength(1)
	})
})

describe('statusText', () => {
	it('counts the waiting widgets, then says all are loaded', () => {
		expect(statusText(7)).toBe('Still loading 7 widgets')
		expect(statusText(1)).toBe('Still loading 1 widget')
		expect(statusText(0)).toBe('All widgets loaded')
	})
})

describe('createStatusLine', () => {
	it('changes its text at most once every 2 seconds, the newest count winning', () => {
		const clock = fakeClock()
		const node = document.createElement('p')
		node.textContent = 'Loading widgets'
		const line = createStatusLine(node, clock)

		line.update(7)
		expect(node.textContent).toBe('Loading widgets')
		clock.advance(1000)
		line.update(5)
		expect(node.textContent).toBe('Loading widgets')
		clock.advance(1000)
		expect(node.textContent).toBe('Still loading 5 widgets')

		line.update(4)
		line.update(3)
		clock.advance(1999)
		expect(node.textContent).toBe('Still loading 5 widgets')
		clock.advance(1)
		expect(node.textContent).toBe('Still loading 3 widgets')
	})

	it('says "All widgets loaded" once and is empty one interval later', () => {
		const clock = fakeClock()
		const node = document.createElement('p')
		const line = createStatusLine(node, clock)
		const texts = []
		new MutationObserver(() => texts.push(node.textContent)).observe(node, { childList: true, characterData: true, subtree: true })

		clock.advance(2000)
		line.update(0)
		expect(node.textContent).toBe('All widgets loaded')
		expect(line.done()).toBe(true)
		line.update(2)
		clock.advance(2000)
		expect(node.textContent).toBe('')
		clock.advance(10000)
		expect(node.textContent).toBe('')
	})
})

describe('countWaitingWidgets', () => {
	it('counts only the first screen and finishes when the last first-screen widget is there', async () => {
		const clock = fakeClock()
		const root = document.createElement('div')
		const top1 = item(0, true)
		const top2 = item(300, true)
		const below = item(2000, true)
		root.append(top1, top2, below)
		document.body.append(root)
		const node = document.createElement('p')
		node.textContent = 'Loading widgets'

		expect(firstScreenItems(root, 900)).toEqual([top1, top2])

		countWaitingWidgets({ root, node, viewportHeight: 900, clock })
		clock.advance(2000)
		expect(node.textContent).toBe('Still loading 2 widgets')

		top1.firstElementChild.removeAttribute('aria-busy')
		await Promise.resolve()
		clock.advance(2000)
		expect(node.textContent).toBe('Still loading 1 widget')

		top2.firstElementChild.setAttribute('aria-busy', 'false')
		await Promise.resolve()
		clock.advance(2000)
		// The widget below the fold is still waiting; it is not counted.
		expect(node.textContent).toBe('All widgets loaded')
		clock.advance(2000)
		expect(node.textContent).toBe('')
	})

	it('stops counting when asked', async () => {
		const clock = fakeClock()
		const root = document.createElement('div')
		const top = item(0, true)
		root.append(top)
		const node = document.createElement('p')
		node.textContent = 'Loading widgets'

		const stop = countWaitingWidgets({ root, node, viewportHeight: 900, clock })
		stop()
		top.firstElementChild.removeAttribute('aria-busy')
		await Promise.resolve()
		clock.advance(10000)
		expect(node.textContent).toBe('Loading widgets')
	})
})
