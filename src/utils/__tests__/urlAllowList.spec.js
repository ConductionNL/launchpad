/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The client-side URL checks of the iframe and live tile forms keep refusing
 * what they refused before: hosts the server's allow-list rejects, schemes
 * other than http(s) (`javascript:`, `data:`, `ftp:`), and malformed URLs.
 */

import { describe, expect, it, vi } from 'vitest'
import { allowListMessage, urlFieldErrors } from '../urlAllowList.js'

vi.mock('@nextcloud/l10n', () => ({ translate: (_app, text) => text }))

const INVALID = 'Enter a valid http(s) URL.'

describe('urlFieldErrors', () => {
	it.each([
		['javascript:alert(1)'],
		['JavaScript:alert(document.cookie)'],
		['data:text/html,<script>alert(1)</script>'],
		['data:image/svg+xml;base64,PHN2Zz4='],
		['ftp://files.example'],
		['file:///etc/passwd'],
		['vbscript:msgbox(1)'],
		['//ok.example'],
		['ok.example'],
		['https://'],
		['http:/one-slash.example'],
		['  javascript:alert(1)  '],
		['javascript:alert(1)//https://ok.example'],
		['data:text/html,https://ok.example'],
	])('refuses %s', (url) => {
		expect(urlFieldErrors(url, '')).toEqual([INVALID])
	})

	it.each([[''], ['   '], [null], [undefined], [42]])(
		'requires a URL (%s)',
		(url) => {
			expect(urlFieldErrors(url, '')).toEqual(['URL is required'])
		},
	)

	it.each([
		['https://ok.example'],
		['HTTP://OK.EXAMPLE/x'],
		['  https://ok.example  '],
	])('accepts the http(s) URL %s', (url) => {
		expect(urlFieldErrors(url, '')).toEqual([])
	})

	it('keeps refusing a URL the allow-list check rejected', () => {
		expect(
			urlFieldErrors('https://evil.example', 'Not on the allow-list'),
		).toEqual(['Not on the allow-list'])
		expect(
			urlFieldErrors('javascript:alert(1)', 'Not on the allow-list'),
		).toEqual([INVALID, 'Not on the allow-list'])
	})
})

describe('allowListMessage', () => {
	it('names a host the server does not allow', () => {
		expect(
			allowListMessage({ valid: false, errors: ['host_not_allowed'] }),
		).toBe('This host is not on the allow-list.')
		expect(
			allowListMessage({
				valid: false,
				errors: ['invalid_url', 'host_not_allowed'],
			}),
		).toBe('This host is not on the allow-list.')
	})

	it('names a URL the server finds malformed', () => {
		expect(allowListMessage({ valid: false, errors: ['invalid_url'] })).toBe(
			INVALID,
		)
	})

	it('raises nothing for an allowed URL', () => {
		expect(allowListMessage({ valid: true, errors: [] })).toBe('')
	})

	it('raises nothing unless the answer says valid is false', () => {
		expect(allowListMessage({ errors: ['host_not_allowed'] })).toBe('')
		expect(allowListMessage({ valid: null, errors: ['invalid_url'] })).toBe('')
	})
})
