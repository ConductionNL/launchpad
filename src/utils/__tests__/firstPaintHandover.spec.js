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
import { handOver, handOverOffTheWorkspace, holdMountWhileShellShows, MOUNT_ID, SHELL_ID, workspacePageIds } from '../firstPaintHandover.js'

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

describe('handOverOffTheWorkspace', () => {
	const manifest = { pages: [
		{ id: 'Workspace', route: '/', component: 'WorkspaceApp' },
		{ id: 'dashboard-detail', route: '/dashboards/:id', component: 'WorkspaceApp' },
		{ id: 'Store', route: '/store', type: 'store' },
	] }
	const now = (fn) => fn()

	it('names the dashboard view pages', () => {
		expect([...workspacePageIds(manifest)]).toEqual(['Workspace', 'dashboard-detail'])
		expect(workspacePageIds(undefined).size).toBe(0)
	})

	it('removes the shell at once on a page without the grid', () => {
		page(true)
		holdMountWhileShellShows()
		expect(handOverOffTheWorkspace({ name: 'Store' }, workspacePageIds(manifest), document, now)).toBe(true)
		expect(document.getElementById(SHELL_ID)).toBeNull()
		expect(document.getElementById(MOUNT_ID).style.visibility).toBe('')
	})

	it('leaves the shell to the grid on the dashboard view', () => {
		page(true)
		holdMountWhileShellShows()
		expect(handOverOffTheWorkspace({ name: 'Workspace' }, workspacePageIds(manifest), document, now)).toBe(false)
		expect(document.getElementById(SHELL_ID)).not.toBeNull()
		expect(document.getElementById(MOUNT_ID).style.visibility).toBe('hidden')
	})

	it('does nothing without a shell', () => {
		page(false)
		expect(handOverOffTheWorkspace({ name: 'Store' }, workspacePageIds(manifest), document, now)).toBe(false)
	})
})

describe('whenShellPaintReported', () => {
	/**
	 * A PerformanceObserver stand-in that reports largest-contentful-paint.
	 *
	 * @return {Function} The class; its instances are in `.instances`.
	 */
	function lcpObserver() {
		class Observer {
			static supportedEntryTypes = ['largest-contentful-paint']

			static instances = []

			constructor(callback) {
				this.callback = callback
				this.disconnected = false
				Observer.instances.push(this)
			}

			observe(options) {
				this.options = options
			}

			disconnect() {
				this.disconnected = true
			}

			report(elements) {
				this.callback({ getEntries: () => elements.map((element) => ({ element })) })
			}
		}
		return Observer
	}

	it('waits until a paint inside the shell is reported, then hands over once', async () => {
		const { whenShellPaintReported } = await import('../firstPaintHandover.js')
		page(true)
		const shell = document.getElementById(SHELL_ID)
		const Observer = lcpObserver()
		const timers = []
		const done = vi.fn()
		whenShellPaintReported(shell, done, { Observer, setTimer: (fn, ms) => timers.push([fn, ms]), clearTimer: vi.fn() })
		const [observer] = Observer.instances
		expect(observer.options).toEqual({ type: 'largest-contentful-paint', buffered: true })
		expect(timers[0][1]).toBe(500)

		observer.report([document.body, null])
		expect(done).not.toHaveBeenCalled()
		observer.report([shell])
		expect(done).toHaveBeenCalledTimes(1)
		expect(observer.disconnected).toBe(true)
		timers[0][0]()
		expect(done).toHaveBeenCalledTimes(1)
	})

	it('hands over after the cap when no paint inside the shell is reported', async () => {
		const { whenShellPaintReported } = await import('../firstPaintHandover.js')
		page(true)
		const Observer = lcpObserver()
		const timers = []
		const done = vi.fn()
		whenShellPaintReported(document.getElementById(SHELL_ID), done, { Observer, setTimer: (fn, ms) => timers.push([fn, ms]), clearTimer: vi.fn() })
		timers[0][0]()
		expect(done).toHaveBeenCalledTimes(1)
		expect(Observer.instances[0].disconnected).toBe(true)
	})

	it('hands over at once where the browser reports no largest-contentful-paint', async () => {
		const { whenShellPaintReported } = await import('../firstPaintHandover.js')
		page(true)
		const done = vi.fn()
		whenShellPaintReported(document.getElementById(SHELL_ID), done, { Observer: class { static supportedEntryTypes = ['mark'] } })
		expect(done).toHaveBeenCalledTimes(1)
		whenShellPaintReported(document.getElementById(SHELL_ID), done, { Observer: undefined })
		expect(done).toHaveBeenCalledTimes(2)
	})

	it('lets handOver wait for the report before the swap frame', () => {
		page(true)
		holdMountWhileShellShows()
		const frames = []
		let report
		expect(handOver(document, (callback) => frames.push(callback), (shell, done) => { report = done })).toBe(true)
		expect(frames).toHaveLength(0)
		report()
		expect(frames).toHaveLength(1)
		frames[0]()
		expect(document.getElementById(SHELL_ID)).toBeNull()
	})
})
