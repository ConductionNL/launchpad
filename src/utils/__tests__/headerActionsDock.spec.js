/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The top-right controls dock into a greeting row on the page ground and
 * float otherwise (LpStart; they covered "Startpagina aanpassen" floating).
 *
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-start-page-without-navigation-panel
 */

import { beforeEach, describe, expect, it } from 'vitest'
import { HEADER_ACTIONS_CLASS, resolveHeaderActionsTarget } from '../headerActionsDock.js'

/**
 * @param {string} html Inner HTML of the container.
 * @return {HTMLElement} The container, attached to the document.
 */
function container(html) {
	const el = document.createElement('div')
	el.className = 'launchpad-container'
	el.innerHTML = html
	document.body.appendChild(el)
	return el
}

const GROUND = '<div class="cn-header-widget cn-header-widget--plain cn-header-widget--ground"><div class="cn-header-widget__content"><h2>Goedemiddag</h2><a class="cn-header-widget__cta">Startpagina aanpassen</a></div></div>'

describe('resolveHeaderActionsTarget', () => {
	beforeEach(() => {
		document.body.innerHTML = ''
	})

	it('floats when there is no container', () => {
		expect(resolveHeaderActionsTarget(null, false)).toBeNull()
	})

	it('floats on a dashboard without a greeting on the page ground', () => {
		const el = container('<div class="cn-header-widget"><div class="cn-header-widget__content"></div></div>')
		expect(resolveHeaderActionsTarget(el, false)).toBeNull()
		expect(el.querySelector('.' + HEADER_ACTIONS_CLASS)).toBeNull()
	})

	it('docks into the greeting row, after the greeting, and reuses the same element', () => {
		const el = container(GROUND)
		const target = resolveHeaderActionsTarget(el, false)
		const header = el.querySelector('.cn-header-widget--ground')
		expect(target).not.toBeNull()
		expect(target.parentElement).toBe(header)
		expect(header.lastElementChild).toBe(target)
		expect(resolveHeaderActionsTarget(el, false)).toBe(target)
		expect(el.querySelectorAll('.' + HEADER_ACTIONS_CLASS)).toHaveLength(1)
	})

	it('floats while editing and removes the actions element', () => {
		const el = container(GROUND)
		resolveHeaderActionsTarget(el, false)
		expect(resolveHeaderActionsTarget(el, true)).toBeNull()
		expect(el.querySelector('.' + HEADER_ACTIONS_CLASS)).toBeNull()
	})

	it('moves to the new greeting row when another dashboard replaces it', () => {
		const el = container(GROUND)
		const first = resolveHeaderActionsTarget(el, false)
		el.innerHTML = GROUND
		const second = resolveHeaderActionsTarget(el, false)
		expect(second).not.toBe(first)
		expect(second.parentElement).toBe(el.querySelector('.cn-header-widget--ground'))
	})
})
