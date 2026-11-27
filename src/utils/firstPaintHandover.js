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
 * Show the mounted page and remove the shell, in one animation frame.
 * Safe to call more than once; without a shell it only makes sure the
 * mount point is visible.
 *
 * @param {Document} [doc] The document.
 * @param {Function} [frame] requestAnimationFrame, or a stand-in.
 * @return {boolean} Whether a shell was handed over.
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-page-takes-over-without-a-layout-shift-req-fps-003
 */
export function handOver(doc = document, frame = globalThis.requestAnimationFrame?.bind(globalThis)) {
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
	if (typeof frame === 'function') {
		frame(swap)
	} else {
		swap()
	}
	return true
}
