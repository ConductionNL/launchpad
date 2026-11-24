/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Download the dashboard as a PDF (REQ-DPD-003), rendered in the reader's own
 * browser: the page is printed as the reader sees it now, so the period, the
 * cross-widget filter and the reader's visibility rules are already applied.
 * Nothing is rendered on the server, stored or sent (decision 24 Nov 2026,
 * option (b)). The print styles live in styles/print.css.
 */

/** The class on <html> while the dashboard prints. */
export const PRINTING_CLASS = 'launchpad-printing'

/**
 * Open the browser's print dialog for the dashboard, with the dashboard's
 * name as the document title so the saved PDF carries it. The title and the
 * printing class are restored once the dialog closes.
 *
 * @param {string} name The dashboard name.
 * @param {object} [env] Injected for tests.
 * @param {Window} [env.win] The window to print.
 * @param {Document} [env.doc] Its document.
 * @return {boolean} Whether the print dialog was opened.
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-reader-can-download-the-dashboard-req-dpd-003
 */
export function printDashboard(name, { win = window, doc = document } = {}) {
	if (typeof win?.print !== 'function') {
		return false
	}
	const previousTitle = doc.title
	const title = (name ?? '').trim()
	if (title !== '') {
		doc.title = title
	}
	doc.documentElement.classList.add(PRINTING_CLASS)
	let restored = false
	const restore = () => {
		if (restored) {
			return
		}
		restored = true
		doc.title = previousTitle
		doc.documentElement.classList.remove(PRINTING_CLASS)
		win.removeEventListener?.('afterprint', restore)
	}
	win.addEventListener?.('afterprint', restore)
	try {
		win.print()
	} finally {
		// Browsers without afterprint return from print() once the dialog closes.
		if (typeof win.onafterprint === 'undefined') {
			restore()
		}
	}
	return true
}
