/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The look of one dashboard on the client (platform-dashboard-theme-and-css
 * REQ-THM-001/002): the same contrast rules as DashboardLook.php, and the
 * scoped style that draws a look on the dashboard region only.
 */
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

import { accentCheck, contrast, lookStyle, normaliseHex, textColorFor } from '../dashboardLook.js'

describe('dashboardLook', () => {
	it('normalises hex colours and refuses anything else', () => {
		expect(normaliseHex(' #1F5C3A ')).toBe('#1f5c3a')
		expect(normaliseHex('#abc')).toBe('#aabbcc')
		expect(normaliseHex('green')).toBeNull()
		expect(normaliseHex('url(x)')).toBeNull()
		expect(normaliseHex(null)).toBeNull()
	})

	it('measures WCAG contrast like the server', () => {
		expect(contrast('#000000', '#ffffff')).toBeCloseTo(21, 3)
		expect(contrast('#777777', '#ffffff')).toBeCloseTo(4.48, 2)
	})

	it('puts white text on a dark green background (REQ-THM-002)', () => {
		expect(textColorFor('#12301f')).toBe('#ffffff')
		expect(textColorFor('#f5f5dc')).toBe('#000000')
	})

	it('refuses a pale accent on the white instance background with the ratio the server gives', () => {
		expect(accentCheck('#f5f5dc', null)).toEqual({ ratio: 1.1, readable: false })
		expect(accentCheck('#1f5c3a', null).readable).toBe(true)
		expect(accentCheck('#ffffff', '#12301f').readable).toBe(true)
		expect(accentCheck(null, null)).toEqual({ ratio: null, readable: true })
	})

	it('draws a look as scoped variables, and nothing for a dashboard that follows the theme', () => {
		expect(lookStyle(null)).toEqual({})
		expect(lookStyle({ themeAccent: null, themeBackgroundColor: null, themeBackgroundResource: null })).toEqual({})

		expect(lookStyle({ themeAccent: '#1f5c3a', themeBackgroundColor: '#12301f', themeBackgroundResource: 'resource_a.b.jpg' })).toEqual({
			'--color-primary-element': '#1f5c3a',
			'--color-primary-element-text': '#ffffff',
			'background-color': '#12301f',
			color: '#ffffff',
			'--color-main-text': '#ffffff',
			'background-image': 'url("/index.php/apps/launchpad/resource/resource_a.b.jpg")',
			'background-size': 'cover',
			'background-position': 'center',
		})
	})

	it('never turns a stored value into CSS it was not meant to be', () => {
		const style = lookStyle({ themeAccent: 'red;}body{display:none', themeBackgroundResource: '../x.png") ; foo: url("y' })
		expect(style).toEqual({})
	})
})
