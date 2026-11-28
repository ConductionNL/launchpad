/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The hand-over from the first-paint shell (first-paint-skeleton D4): the
 * mount point is hidden while the shell shows, then shown and the shell
 * removed in one animation frame; nothing happens without a shell.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'
import { handOver, holdMountWhileShellShows, MOUNT_ID, settleFirstLayout, SHELL_ID } from '../firstPaintHandover.js'

/**
 * A page with the mount point and, when asked, the shell beside it.
 *
 * @param {boolean} withShell Whether the server printed a shell.
 * @return {void}
 */
function page(withShell) {
	document.body.innerHTML = `<div id="app-workspace">${withShell ? `<div id="${SHELL_ID}">shell</div>` : ''}<div id="${MOUNT_ID}"></div></div>`
}

beforeEach(() => {
	document.body.innerHTML = ''
})

describe('firstPaintHandover', () => {
	it('hides the mount point while the shell shows, then swaps in one frame', () => {
		page(true)
		expect(holdMountWhileShellShows()).toBe(true)
		const mount = document.getElementById(MOUNT_ID)
		expect(mount.style.visibility).toBe('hidden')

		const frames = []
		expect(handOver(document, (callback) => frames.push(callback))).toBe(true)
		// Nothing changes before the frame: the page never shows both or neither.
		expect(document.getElementById(SHELL_ID)).not.toBeNull()
		expect(mount.style.visibility).toBe('hidden')

		expect(frames).toHaveLength(1)
		frames[0]()
		expect(document.getElementById(SHELL_ID)).toBeNull()
		expect(mount.style.visibility).toBe('')
	})

	it('does nothing without a shell, and makes sure the page shows', () => {
		page(false)
		expect(holdMountWhileShellShows()).toBe(false)
		const frame = vi.fn()
		expect(handOver(document, frame)).toBe(false)
		expect(frame).not.toHaveBeenCalled()
		expect(document.getElementById(MOUNT_ID).style.visibility).toBe('')
	})

	it('can be called twice, and swaps at once without requestAnimationFrame', () => {
		page(true)
		holdMountWhileShellShows()
		expect(handOver(document, null)).toBe(true)
		expect(document.getElementById(SHELL_ID)).toBeNull()
		expect(handOver(document, null)).toBe(false)
		expect(document.getElementById(MOUNT_ID).style.visibility).toBe('')
	})
})

describe('settleFirstLayout (first-paint-skeleton REQ-FPS-003)', () => {
	it('switches the animation off at once and back on two frames later', () => {
		const calls = []
		const grid = { opts: { animate: true }, setAnimation: vi.fn((on) => calls.push(on)) }
		const frames = []
		expect(settleFirstLayout(grid, (fn) => frames.push(fn))).toBe(true)
		expect(calls).toEqual([false])
		frames.shift()()
		expect(calls).toEqual([false])
		frames.shift()()
		expect(calls).toEqual([false, true])
	})

	it('leaves a grid without animation off, and does nothing without a grid', () => {
		const grid = { opts: { animate: false }, setAnimation: vi.fn() }
		const frame = vi.fn()
		expect(settleFirstLayout(grid, frame)).toBe(true)
		expect(grid.setAnimation).toHaveBeenCalledTimes(1)
		expect(frame).not.toHaveBeenCalled()
		expect(settleFirstLayout(null, frame)).toBe(false)
		expect(settleFirstLayout({}, frame)).toBe(false)
	})

	it('does not switch a destroyed grid back on', () => {
		const grid = { opts: { animate: true }, setAnimation: vi.fn() }
		const frames = []
		settleFirstLayout(grid, (fn) => frames.push(fn))
		grid.opts = undefined
		frames.shift()()
		frames.shift()()
		expect(grid.setAnimation).toHaveBeenCalledTimes(1)
	})
})
