/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Where the More menu goes on a page other than the dashboard view
 * (runtime-shell REQ-SHELL-009, board LpStore): into the page header's own
 * action row, as its last button, when the page draws one; otherwise
 * App.vue keeps it at the top end of the content.
 */

/**
 * The action rows of the library's page headers: the store page's
 * (`CnStorePage`) and an index page's (`CnIndexPage`). A header drawn
 * visually hidden renders no action row, so it never matches.
 */
export const PAGE_HEADER_ACTIONS = [
	'.cn-page-header > .cn-store-page__header-actions',
	'.cn-page-header > .cn-index-page__header-buttons',
].join(', ')

/**
 * The page header action row the More menu joins, or null when the page
 * draws none.
 *
 * @param {ParentNode|null} root Where to look (the app's content).
 * @return {Element|null} The action row.
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
 */
export function findPageMenuTarget(root) {
	if (!root || typeof root.querySelector !== 'function') {
		return null
	}
	return root.querySelector(PAGE_HEADER_ACTIONS)
}

/**
 * Follow the page header action row as pages render and route: report the
 * row whenever it appears, changes or goes. Checks run at most once a frame.
 *
 * @param {Element|null} root Where to look (the app's content).
 * @param {(target: Element|null) => void} onChange Called with the new row.
 * @return {() => void} Stops following.
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
 */
export function watchPageMenuTarget(root, onChange) {
	if (!root || typeof MutationObserver === 'undefined') {
		return () => {}
	}
	const schedule = typeof requestAnimationFrame === 'function'
		? requestAnimationFrame
		: (fn) => setTimeout(fn, 0)
	const cancel = typeof cancelAnimationFrame === 'function'
		? cancelAnimationFrame
		: clearTimeout
	let current = findPageMenuTarget(root)
	let pending = null
	const check = () => {
		pending = null
		const next = findPageMenuTarget(root)
		if (next !== current) {
			current = next
			onChange(next)
		}
	}
	const observer = new MutationObserver(() => {
		if (pending === null) {
			pending = schedule(check)
		}
	})
	observer.observe(root, { childList: true, subtree: true })
	onChange(current)
	return () => {
		observer.disconnect()
		if (pending !== null) {
			cancel(pending)
			pending = null
		}
	}
}
