/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */
import { afterEach, describe, expect, it } from 'vitest'
import { applyCustomCss, GLOBAL_STYLE_ID } from '../customCss.js'

describe('applyCustomCss (REQ-THM-003)', () => {
	afterEach(() => {
		document.head.innerHTML = ''
	})

	it('inserts the CSS as text, never as markup', () => {
		const css = '.launchpad-workspace p { content: "</style><img src=x onerror=alert(1)>"; }'
		const style = applyCustomCss(GLOBAL_STYLE_ID, css)

		expect(style.tagName).toBe('STYLE')
		expect(style.textContent).toBe(css)
		expect(style.children).toHaveLength(0)
		expect(document.head.querySelectorAll('img')).toHaveLength(0)
	})

	it('replaces the CSS in the same element', () => {
		applyCustomCss(GLOBAL_STYLE_ID, '.launchpad-workspace a { color: red; }')
		applyCustomCss(GLOBAL_STYLE_ID, '.launchpad-workspace a { color: blue; }')

		const styles = document.head.querySelectorAll(`#${GLOBAL_STYLE_ID}`)
		expect(styles).toHaveLength(1)
		expect(styles[0].textContent).toBe('.launchpad-workspace a { color: blue; }')
	})

	it('removes the element when the CSS is empty or missing', () => {
		applyCustomCss(GLOBAL_STYLE_ID, '.launchpad-workspace a { color: red; }')

		expect(applyCustomCss(GLOBAL_STYLE_ID, '  ')).toBeNull()
		expect(document.getElementById(GLOBAL_STYLE_ID)).toBeNull()
		expect(applyCustomCss(GLOBAL_STYLE_ID, null)).toBeNull()
	})

	it('does not take over an element of another kind with the same id', () => {
		const div = document.createElement('div')
		div.id = GLOBAL_STYLE_ID
		document.head.appendChild(div)

		const style = applyCustomCss(GLOBAL_STYLE_ID, '.launchpad-workspace a { color: red; }')

		expect(style.tagName).toBe('STYLE')
		expect(document.head.querySelectorAll(`#${GLOBAL_STYLE_ID}`)).toHaveLength(1)
	})

	// first-paint-skeleton D3: the shell prints the global custom CSS with this
	// id; the script reuses that element instead of adding a second copy.
	it('reuses the style element the first-paint shell printed', () => {
		document.body.innerHTML = `<div id="launchpad-first-paint"><style id="${GLOBAL_STYLE_ID}">.a{color:red}</style></div>`
		const printed = document.getElementById(GLOBAL_STYLE_ID)

		const style = applyCustomCss(GLOBAL_STYLE_ID, '.a{color:blue}')

		expect(style).toBe(printed)
		expect(style.textContent).toBe('.a{color:blue}')
		expect(document.querySelectorAll(`#${GLOBAL_STYLE_ID}`)).toHaveLength(1)
		document.body.innerHTML = ''
	})
})
