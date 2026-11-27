/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Where the More menu goes on a page other than the dashboard view
 * (runtime-shell REQ-SHELL-009, board LpStore).
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { findPageMenuTarget, watchPageMenuTarget } from '../pageMenuTarget.js'

/**
 * A content root holding the given markup.
 *
 * @param {string} html The markup.
 * @return {HTMLElement} The root, attached to the document.
 */
function rootWith(html) {
	const root = document.createElement('div')
	root.innerHTML = html
	document.body.appendChild(root)
	return root
}

afterEach(() => {
	document.body.innerHTML = ''
	vi.useRealTimers()
})

describe('findPageMenuTarget', () => {
	it('finds the store page header action row', () => {
		const root = rootWith('<div class="cn-page-header"><div class="cn-page-header__text"></div><div class="cn-store-page__header-actions" id="row"></div></div>')
		expect(findPageMenuTarget(root)?.id).toBe('row')
	})

	it('finds an index page header button row', () => {
		const root = rootWith('<div class="cn-page-header"><div class="cn-index-page__header-buttons" id="row"></div></div>')
		expect(findPageMenuTarget(root)?.id).toBe('row')
	})

	it('answers null for a page without a header action row', () => {
		expect(findPageMenuTarget(rootWith('<div class="cn-page-header"><h1>Flows</h1></div>'))).toBeNull()
		expect(findPageMenuTarget(rootWith('<div class="cn-store-page__header-actions"></div>'))).toBeNull()
	})

	it('answers null without a root', () => {
		expect(findPageMenuTarget(null)).toBeNull()
	})
})

describe('watchPageMenuTarget', () => {
	it('reports the row at once, then when it appears and when it goes', async () => {
		const root = rootWith('<main></main>')
		const seen = []
		const stop = watchPageMenuTarget(root, (target) => seen.push(target?.id ?? null))
		expect(seen).toEqual([null])

		root.querySelector('main').innerHTML = '<div class="cn-page-header"><div class="cn-store-page__header-actions" id="store"></div></div>'
		await vi.waitFor(() => expect(seen).toEqual([null, 'store']))

		root.querySelector('main').innerHTML = '<div class="cn-page-header"><h1>Flows</h1></div>'
		await vi.waitFor(() => expect(seen).toEqual([null, 'store', null]))
		stop()
	})

	it('stays quiet while the row stays the same', async () => {
		const root = rootWith('<div class="cn-page-header"><div class="cn-store-page__header-actions" id="store"></div></div>')
		const onChange = vi.fn()
		const stop = watchPageMenuTarget(root, onChange)
		root.querySelector('#store').appendChild(document.createElement('button'))
		await new Promise((resolve) => setTimeout(resolve, 50))
		expect(onChange).toHaveBeenCalledTimes(1)
		stop()
	})

	it('reports nothing more once stopped', async () => {
		const root = rootWith('<main></main>')
		const onChange = vi.fn()
		const stop = watchPageMenuTarget(root, onChange)
		stop()
		root.querySelector('main').innerHTML = '<div class="cn-page-header"><div class="cn-store-page__header-actions"></div></div>'
		await new Promise((resolve) => setTimeout(resolve, 50))
		expect(onChange).toHaveBeenCalledTimes(1)
	})

	it('does nothing without a root', () => {
		const onChange = vi.fn()
		watchPageMenuTarget(null, onChange)()
		expect(onChange).not.toHaveBeenCalled()
	})
})
