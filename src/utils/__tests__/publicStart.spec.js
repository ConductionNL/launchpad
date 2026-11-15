/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * sharing-public-start-page: the start page's address, the sign-in address
 * with `direct=1`, and which token the public page shows.
 *
 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md
 */

import { describe, expect, it } from 'vitest'
import { publicPageToken, signInUrl, startPageUrl } from '../publicStart.js'

const generateUrl = (path) => '/index.php' + path

describe('publicStart addresses', () => {
	it('sends the redirect to the fixed start page path', () => {
		expect(startPageUrl(generateUrl)).toBe('/index.php/apps/launchpad/start')
	})

	it('signs in through the login form with direct=1', () => {
		expect(signInUrl(generateUrl)).toBe('/index.php/login?direct=1')
	})
})

describe('publicPageToken', () => {
	it('shows the start page link when the server handed over its token', () => {
		expect(publicPageToken('/index.php/apps/launchpad/start', 'Bib1Noord')).toEqual({
			token: 'Bib1Noord',
			startPage: true,
		})
	})

	it('reads a share page token from the last path segment, trailing slash or not', () => {
		expect(publicPageToken('/index.php/apps/launchpad/s/abc123', '')).toEqual({ token: 'abc123', startPage: false })
		expect(publicPageToken('/index.php/apps/launchpad/s/abc123/', '')).toEqual({ token: 'abc123', startPage: false })
	})

	it('ignores a start token that is not a non-empty string', () => {
		expect(publicPageToken('/apps/launchpad/s/abc123', null)).toEqual({ token: 'abc123', startPage: false })
		expect(publicPageToken('/apps/launchpad/s/abc123', 42)).toEqual({ token: 'abc123', startPage: false })
	})

	it('answers an empty token for an empty path', () => {
		expect(publicPageToken('', '')).toEqual({ token: '', startPage: false })
	})
})
