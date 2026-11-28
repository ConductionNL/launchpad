/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The hand-over from the server's first-paint shell to the mounted page
 * (first-paint-skeleton D4). The shell lives in `#launchpad-first-paint`,
 * beside the mount point `#workspace-vue`, not inside it, so mounting Vue
 * does not empty it. While the shell shows, the mount point is laid out but
 * hidden (and so out of the accessibility tree); once the grid has laid out,
 * the mount point becomes visible and the shell goes, in one animation frame,
 * so the page never shows both or neither. Without a shell (the template
 * printed none) both steps do nothing.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */

/** The shell's id, printed by templates/index.php. */
export const SHELL_ID = 'launchpad-first-paint'

/** The mount point's id. */
export const MOUNT_ID = 'workspace-vue'

/**
 * Hide the mount point while the shell shows. Call before mounting.
 *
 * @param {Document} [doc] The document.
 * @return {boolean} Whether a shell is showing.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */
export function holdMountWhileShellShows(doc = document) {
	const shell = doc.getElementById(SHELL_ID)
	const mount = doc.getElementById(MOUNT_ID)
	if (!shell || !mount) {
		return false
	}
	mount.style.visibility = 'hidden'
	return true
}

/**
 * Show the mounted page and remove the shell, in one animation frame, once
 * the browser has reported the shell's paint (see whenShellPaintReported).
 * Safe to call more than once; without a shell it only makes sure the
 * mount point is visible.
 *
 * @param {Document} [doc] The document.
 * @param {Function} [frame] requestAnimationFrame, or a stand-in.
 * @param {(shell: HTMLElement, done: () => void) => void} [whenReported] whenShellPaintReported, or a stand-in.
 * @return {boolean} Whether a shell was handed over.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */
export function handOver(doc = document, frame = globalThis.requestAnimationFrame?.bind(globalThis), whenReported = whenShellPaintReported) {
	const shell = doc.getElementById(SHELL_ID)
	const mount = doc.getElementById(MOUNT_ID)
	const swap = () => {
		if (mount) {
			mount.style.visibility = ''
		}
		doc.getElementById(SHELL_ID)?.remove()
	}
	if (!shell) {
		swap()
		return false
	}
	whenReported(shell, () => {
		if (typeof frame === 'function') {
			frame(swap)
		} else {
			swap()
		}
	})
	return true
}

/**
 * Call `done` once the browser has reported the shell's paint as a
 * largest-contentful-paint candidate, or after `cap` ms, or at once where
 * the browser reports no such paints.
 *
 * Chrome reports a paint only when the frame it was in has been presented,
 * and the page's boot holds the main thread right after the shell's first
 * frame. A shell removed before that report never counts: the page's first
 * useful content then read as the mounted header (~850 ms) although the
 * shell had painted at ~300 ms.
 *
 * @param {HTMLElement} shell The first-paint shell.
 * @param {() => void} done Called once.
 * @param {object} [options] Stand-ins.
 * @param {number} [options.cap] The longest wait in ms.
 * @param {typeof PerformanceObserver} [options.Observer] PerformanceObserver, or a stand-in.
 * @param {typeof setTimeout} [options.setTimer] setTimeout, or a stand-in.
 * @param {typeof clearTimeout} [options.clearTimer] clearTimeout, or a stand-in.
 * @return {void}
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */
export function whenShellPaintReported(shell, done, {
	cap = 500,
	Observer = globalThis.PerformanceObserver,
	setTimer = globalThis.setTimeout,
	clearTimer = globalThis.clearTimeout,
} = {}) {
	if (typeof Observer !== 'function' || !(Observer.supportedEntryTypes ?? []).includes('largest-contentful-paint')) {
		done()
		return
	}
	let finished = false
	let observer = null
	let timer = null
	const finish = () => {
		if (finished) {
			return
		}
		finished = true
		observer?.disconnect()
		if (timer !== null) {
			clearTimer(timer)
		}
		done()
	}
	observer = new Observer((list) => {
		if (list.getEntries().some((entry) => entry.element && shell.contains(entry.element))) {
			finish()
		}
	})
	observer.observe({ type: 'largest-contentful-paint', buffered: true })
	timer = setTimer(finish, cap)
}

/**
 * The manifest pages that render the dashboard view, whose grid hands over
 * (Views.vue). Every other page (Marketplace, Reports, Flows, Roadmap) has
 * no grid, so nothing would ever hand over and the shell would stay over it
 * until the 15 s 'could not load' line.
 *
 * @param {object} manifest The merged manifest.
 * @return {Set<string>} The page ids (route names) of the dashboard view.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */
export function workspacePageIds(manifest) {
	return new Set((manifest?.pages ?? [])
		.filter((page) => page?.component === 'WorkspaceApp')
		.map((page) => page.id))
}

/**
 * Hand over at once when the router lands on a page other than the
 * dashboard view; the dashboard view keeps its own hand-over.
 *
 * @param {{name?: string|symbol}} route The route landed on.
 * @param {Set<string>} workspacePages From {@link workspacePageIds}.
 * @param {Document} [doc] The document.
 * @param {(callback: () => void) => unknown} [frame] requestAnimationFrame, or a stand-in.
 * @return {boolean} Whether this call handed over.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */
export function handOverOffTheWorkspace(route, workspacePages, doc = document, frame = globalThis.requestAnimationFrame?.bind(globalThis)) {
	if (workspacePages.has(route?.name) || !doc.getElementById(SHELL_ID)) {
		return false
	}
	return handOver(doc, frame)
}
