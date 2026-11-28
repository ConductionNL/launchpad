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
import { handOver, handOverOffTheDashboard, holdMountWhileShellShows, MOUNT_ID, rendersDashboardView, SHELL_ID } from '../firstPaintHandover.js'

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

describe('hand-over off the dashboard view', () => {
	const manifest = {
		pages: [
			{ id: 'Workspace', route: '/', component: 'WorkspaceApp' },
			{ id: 'dashboard-detail', route: '/dashboards/:id', component: 'WorkspaceApp' },
			{ id: 'Store', route: '/store', type: 'store' },
		],
	}

	/**
	 * A router stand-in that records its afterEach hook.
	 *
	 * @return {{afterEach: Function, land: Function}} The stand-in.
	 */
	function fakeRouter() {
		let hook = null
		return {
			afterEach: (fn) => { hook = fn },
			land: (to) => hook(to),
		}
	}

	it('knows which pages render the dashboard view', () => {
		expect(rendersDashboardView({ name: 'Workspace' }, manifest)).toBe(true)
		expect(rendersDashboardView({ name: 'dashboard-detail' }, manifest)).toBe(true)
		expect(rendersDashboardView({ name: 'Store' }, manifest)).toBe(false)
		expect(rendersDashboardView({ name: undefined }, manifest)).toBe(false)
		expect(rendersDashboardView({ name: 'Workspace' }, null)).toBe(false)
	})

	it('a page without a grid shows itself and removes the shell when it is routed', () => {
		page(true)
		holdMountWhileShellShows()
		const router = fakeRouter()
		const raf = globalThis.requestAnimationFrame
		globalThis.requestAnimationFrame = (callback) => callback()
		try {
			handOverOffTheDashboard(router, () => manifest)
			router.land({ name: 'Store' })
		} finally {
			globalThis.requestAnimationFrame = raf
		}
		expect(document.getElementById(SHELL_ID)).toBeNull()
		expect(document.getElementById(MOUNT_ID).style.visibility).toBe('')
	})

	it('the dashboard view keeps the shell until its grid hands over', () => {
		page(true)
		holdMountWhileShellShows()
		const router = fakeRouter()
		handOverOffTheDashboard(router, () => manifest)
		router.land({ name: 'Workspace' })
		expect(document.getElementById(SHELL_ID)).not.toBeNull()
		expect(document.getElementById(MOUNT_ID).style.visibility).toBe('hidden')
	})
})
