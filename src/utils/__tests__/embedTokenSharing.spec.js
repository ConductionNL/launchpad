/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Sharing tab's embed helpers: which tokens belong to a dashboard and
 * the code a website pastes (REQ-EMB-011).
 *
 * @spec openspec/changes/sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
 */

import { describe, expect, it } from 'vitest'
import { embedCode, tokensForDashboard } from '../embedToken.js'

const TOKENS = [
	{ id: 'a', name: 'Whole', subject: { type: 'dashboard', id: 'dash-1' } },
	{ id: 'b', name: 'Widget here', subject: { type: 'widget', id: '7' } },
	{ id: 'c', name: 'Other dashboard', subject: { type: 'dashboard', id: 'dash-2' } },
	{ id: 'd', name: 'Widget elsewhere', subject: { type: 'widget', id: '99' } },
	{ id: 'e', name: 'No subject' },
]

describe('tokensForDashboard', () => {
	it('keeps the dashboard token and the tokens of its own widgets', () => {
		expect(tokensForDashboard(TOKENS, 'dash-1', [7, 8]).map((token) => token.id)).toEqual(['a', 'b'])
	})

	it('finds nothing without a uuid or placements', () => {
		expect(tokensForDashboard(TOKENS, '', [])).toEqual([])
		expect(tokensForDashboard(null, 'dash-1')).toEqual([])
	})
})

describe('embedCode', () => {
	it('is an iframe on the public embed route with the token', () => {
		const code = embedCode({ name: 'Website Zuiddrecht', subject: { type: 'widget', id: '7' } }, 'https://cloud.example/index.php/apps/launchpad/', 'jwt.value')
		expect(code).toBe('<iframe src="https://cloud.example/index.php/apps/launchpad/embed/widget/7?token=jwt.value" title="Website Zuiddrecht" width="100%" height="480" style="border:0" loading="lazy"></iframe>')
	})

	it('uses a placeholder once the token is no longer at hand, and escapes the name', () => {
		const code = embedCode({ name: 'A "quoted" <name>', subject: { type: 'dashboard', id: 'dash-1' } }, 'https://x')
		expect(code).toContain('src="https://x/embed/dashboard/dash-1?token=YOUR_TOKEN"')
		expect(code).toContain('title="A &quot;quoted&quot; &lt;name&gt;"')
	})
})
