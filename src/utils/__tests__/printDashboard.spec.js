/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Download the dashboard: the reader's browser prints the page as it is now;
 * nothing goes to the server.
 *
 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-reader-can-download-the-dashboard-req-dpd-003
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { printDashboard, PRINTING_CLASS } from '../printDashboard.js'

afterEach(() => {
	document.title = ''
	document.documentElement.classList.remove(PRINTING_CLASS)
	vi.restoreAllMocks()
})

describe('printDashboard', () => {
	it('prints under the dashboard name and restores the title after the dialog closes', () => {
		document.title = 'Launchpad - Nextcloud'
		let seen = null
		vi.spyOn(window, 'print').mockImplementation(() => {
			seen = { title: document.title, printing: document.documentElement.classList.contains(PRINTING_CLASS) }
		})
		expect(printDashboard('  Managementinformatie ')).toBe(true)
		expect(seen).toEqual({ title: 'Managementinformatie', printing: true })
		window.dispatchEvent(new Event('afterprint'))
		expect(document.title).toBe('Launchpad - Nextcloud')
		expect(document.documentElement.classList.contains(PRINTING_CLASS)).toBe(false)
	})

	it('keeps the page title for a dashboard without a name', () => {
		document.title = 'Launchpad'
		vi.spyOn(window, 'print').mockImplementation(() => {
			expect(document.title).toBe('Launchpad')
		})
		printDashboard('')
		expect(window.print).toHaveBeenCalledTimes(1)
	})

	it('sends nothing to the server', () => {
		const fetchSpy = vi.spyOn(globalThis, 'fetch').mockImplementation(() => Promise.resolve(new Response('')))
		vi.spyOn(window, 'print').mockImplementation(() => {})
		printDashboard('Managementinformatie')
		expect(fetchSpy).not.toHaveBeenCalled()
	})

	it('restores at once when the browser has no afterprint event', () => {
		const doc = { title: 'Launchpad', documentElement: document.createElement('html') }
		const win = { print: vi.fn() }
		expect(printDashboard('Managementinformatie', { win, doc })).toBe(true)
		expect(win.print).toHaveBeenCalledTimes(1)
		expect(doc.title).toBe('Launchpad')
		expect(doc.documentElement.classList.contains(PRINTING_CLASS)).toBe(false)
	})

	it('does nothing where the browser cannot print', () => {
		expect(printDashboard('Managementinformatie', { win: {}, doc: document })).toBe(false)
	})
})
