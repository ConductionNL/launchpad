/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The embed-token admin helpers: the origin rule and CSP preview the server
 * applies, a token's status, and the payloads built from the form
 * (embedded-analytics tasks 15, 16, 18 and 40).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-18
 */

import { describe, expect, it } from 'vitest'
import {
	createPayload,
	cspPreview,
	embedDefaultsFrom,
	formFromToken,
	onlyRootRules,
	originProblem,
	parseFilterContext,
	rateLimitPolicy,
	tokenStatus,
	updatePayload,
} from '../embedToken.js'

describe('embed token helpers', () => {
	it.each([
		['https://www.zeist.nl', ''],
		['https://intranet.zeist.nl:8443/', ''],
		['http://localhost', ''],
		['https://*.zeist.nl', 'wildcard'],
		['*', 'wildcard'],
		['https://www.zeist.nl/woo', 'invalid'],
		['www.zeist.nl', 'invalid'],
		['ftp://zeist.nl', 'invalid'],
		['', 'invalid'],
	])('judges origin %s as "%s"', (origin, problem) => {
		expect(originProblem(origin)).toBe(problem)
	})

	it('previews the frame-ancestors header from the valid, normalised origins', () => {
		expect(cspPreview(['https://WWW.Zeist.nl/', 'https://www.zeist.nl', 'https://*.x.nl', 'https://a.nl']))
			.toBe('frame-ancestors https://www.zeist.nl https://a.nl')
		expect(cspPreview([])).toBe("frame-ancestors 'none'")
	})

	it('says revoked before expired, and active otherwise', () => {
		const now = Date.parse('2026-05-22T14:30:00Z')
		expect(tokenStatus({ revokedAt: '2026-05-01T00:00:00Z', tokenExpiresAt: '2026-05-02T00:00:00Z' }, now)).toBe('revoked')
		expect(tokenStatus({ tokenExpiresAt: '2026-05-22T14:30:00Z' }, now)).toBe('expired')
		expect(tokenStatus({ tokenExpiresAt: '2026-05-23T00:00:00Z' }, now)).toBe('active')
		expect(tokenStatus({ tokenExpiresAt: null }, now)).toBe('active')
	})

	it('reads pinned filters as dimension=value lines', () => {
		expect(parseFilterContext('status = open\n\n=x\nyear=2026\nnoequals')).toEqual({ status: 'open', year: '2026' })
	})

	it('uses the organisation default without a rate, and the rate as burst without a burst', () => {
		expect(rateLimitPolicy('', 10)).toBeNull()
		expect(rateLimitPolicy('600', '')).toEqual({ requestsPerMinute: 600, burstSize: 600 })
		expect(rateLimitPolicy(600, '60')).toEqual({ requestsPerMinute: 600, burstSize: 60 })
	})

	it('builds a read-only create payload without actions or filters', () => {
		const form = {
			...formFromToken(null, { subjectType: 'dashboard', subjectId: 'dash-uuid', dashboardId: 7 }),
			name: ' Zeist WOO ',
			filterContext: 'status=open',
			allowedActions: ['export'],
			allowedFilters: 'status',
			hostOrigins: ['https://WWW.Zeist.nl/'],
			tenantThemeId: 'th-1',
			tokenExpiresAt: '2026-12-31',
			requestsPerMinute: '600',
			burstSize: '60',
		}
		expect(createPayload(form)).toEqual({
			name: 'Zeist WOO',
			description: '',
			subject: { type: 'dashboard', id: 'dash-uuid', filterContext: { status: 'open' } },
			scope: { mode: 'read', allowedFilters: [], allowedActions: [] },
			hostOrigins: ['https://www.zeist.nl'],
			tenantThemeId: 'th-1',
			tokenExpiresAt: '2026-12-31T00:00:00Z',
			rateLimitPolicy: { requestsPerMinute: 600, burstSize: 60 },
		})
	})

	it('keeps the allowed actions and filters of an interactive scope, in canonical order', () => {
		const form = {
			...formFromToken(null, { subjectType: 'widget', subjectId: '42' }),
			name: 'Grafiek',
			mode: 'read-with-interactions',
			allowedActions: ['export', 'bogus', 'interact'],
			allowedFilters: 'status, year,status',
			hostOrigins: ['https://a.nl'],
		}
		const payload = createPayload(form)
		expect(payload.subject).toEqual({ type: 'widget', id: '42' })
		expect(payload.scope).toEqual({ mode: 'read-with-interactions', allowedFilters: ['status', 'year'], allowedActions: ['interact', 'export'] })
		expect(payload.tenantThemeId).toBeNull()
		expect(payload.tokenExpiresAt).toBeNull()
		expect(payload.rateLimitPolicy).toBeNull()
	})

	it('round-trips a stored token into the form, and updates only what may change', () => {
		const token = {
			name: 'Zeist',
			description: 'WOO',
			subject: { type: 'dashboard', id: 'd', filterContext: { status: 'open' } },
			scope: { mode: 'read-with-interactions', allowedActions: ['drillDown'], allowedFilters: ['status', 'year'] },
			hostOrigins: ['https://a.nl'],
			tokenExpiresAt: '2026-12-31T00:00:00Z',
			rateLimitPolicy: { requestsPerMinute: 100, burstSize: 10 },
		}
		const form = formFromToken(token)
		expect(form).toMatchObject({ filterContext: 'status=open', allowedFilters: 'status, year', tokenExpiresAt: '2026-12-31', requestsPerMinute: 100 })
		expect(updatePayload({ ...form, name: 'Zeist 2 ' })).toEqual({ name: 'Zeist 2', description: 'WOO', rateLimitPolicy: { requestsPerMinute: 100, burstSize: 10 } })
	})

	it('accepts only :root rules in custom CSS', () => {
		expect(onlyRootRules('')).toBe(true)
		expect(onlyRootRules(':root { --gap: 8px; }\n/* body { } */:root{--x:1}')).toBe(true)
		expect(onlyRootRules(':root { --gap: 8px; } body { color: red; }')).toBe(false)
		expect(onlyRootRules(':root, body { color: red; }')).toBe(false)
	})

	it('reads the subject of a link into Beheer ▸ Embeds', () => {
		expect(embedDefaultsFrom('?tab=embeds&embedType=dashboard&embedId=dash-uuid&embedDashboard=7'))
			.toEqual({ subjectType: 'dashboard', subjectId: 'dash-uuid', dashboardId: 7 })
		expect(embedDefaultsFrom('?embedType=widget&embedId=42')).toEqual({ subjectType: 'widget', subjectId: '42', dashboardId: null })
		expect(embedDefaultsFrom('?embedType=page&embedId=1')).toEqual({})
		expect(embedDefaultsFrom('?tab=embeds')).toEqual({})
	})
})
