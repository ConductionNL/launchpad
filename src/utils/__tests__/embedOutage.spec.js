/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The source line and the outage cause of an embedded page or report.
 *
 * @spec openspec/changes/archive/2026-11-24-embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */

import { describe, expect, it } from 'vitest'
import {
	clockTime,
	embedHost,
	embedSourceLine,
	isReport,
	outageCauseLine,
	ownCause,
} from '../embedOutage.js'

function t(_app, key, vars) {
	return key.replace(/\{(\w+)\}/g, (_, name) => (vars && name in vars ? vars[name] : `{${name}}`))
}

const report = {
	url: 'https://app.powerbi.com/reportEmbed?id=1',
	kind: 'report',
	connection: { account: 'Pieter Jansen' },
}

describe('embedSourceLine', () => {
	it('names the kind, the host and the signed-in account', () => {
		expect(embedSourceLine(report, t)).toBe('Embedded report · app.powerbi.com · Signed in as Pieter Jansen')
	})

	it('leaves the account out when the connection does not sign in', () => {
		expect(embedSourceLine({ url: 'https://example.com/a' }, t)).toBe('Embedded page · example.com')
		expect(embedSourceLine({ url: 'https://example.com/a', connection: { account: '  ' } }, t)).toBe('Embedded page · example.com')
	})

	it('is empty without a parseable URL', () => {
		expect(embedSourceLine({}, t)).toBe('')
		expect(embedSourceLine({ url: 'not a url' }, t)).toBe('')
		expect(embedSourceLine(null, t)).toBe('')
	})
})

describe('helpers', () => {
	it('reads the host and the kind', () => {
		expect(embedHost(' https://a.example.com:8443/x ')).toBe('a.example.com:8443')
		expect(embedHost(42)).toBe('')
		expect(isReport(report)).toBe(true)
		expect(isReport({ kind: 'page' })).toBe(false)
		expect(isReport(undefined)).toBe(false)
	})

	it('pads the clock time', () => {
		expect(clockTime(new Date(2026, 10, 24, 9, 5))).toBe('09:05')
	})

	it('words every own cause and nothing for an unknown one', () => {
		expect(ownCause('blocked', t)).toBe('the site refuses to be embedded')
		expect(ownCause('unreachable', t)).toBe('the site could not be reached')
		expect(ownCause('timeout', t)).toBe('the page did not finish loading')
		expect(ownCause('error', t)).toBe('a network error')
		expect(ownCause('unknown', t)).toBe('')
	})
})

describe('outageCauseLine', () => {
	const now = new Date(2026, 10, 24, 11, 0)

	it('takes the connection error and its time (token expired at 09:30)', () => {
		const content = { ...report, connection: { error: 'token expired', errorAt: new Date(2026, 10, 24, 9, 30).toISOString() } }
		expect(outageCauseLine({ content, reason: 'blocked', failedAt: now, now }, t))
			.toBe('Gave an error today at 09:30: token expired')
	})

	it('names the date when the connection failed on another day', () => {
		const at = new Date(2026, 10, 23, 16, 45)
		const content = { connection: { error: 'token expired', errorAt: at.toISOString() } }
		expect(outageCauseLine({ content, reason: 'unknown', failedAt: null, now }, t))
			.toBe(`Gave an error on ${at.toLocaleDateString()} at 16:45: token expired`)
	})

	it('gives the cause without a time when the connection has no valid time', () => {
		const content = { connection: { error: 'token expired', errorAt: 'yesterday' } }
		expect(outageCauseLine({ content, reason: 'unknown', failedAt: now, now }, t)).toBe('Gave an error: token expired')
	})

	it('falls back to what the widget found and when', () => {
		const failedAt = new Date(2026, 10, 24, 10, 12)
		expect(outageCauseLine({ content: {}, reason: 'timeout', failedAt, now }, t))
			.toBe('Gave an error today at 10:12: the page did not finish loading')
	})

	it('is empty when no cause is known', () => {
		expect(outageCauseLine({ content: {}, reason: 'unknown', failedAt: now, now }, t)).toBe('')
		expect(outageCauseLine({ content: { connection: { error: ' ' } }, reason: 'unknown', failedAt: now, now }, t)).toBe('')
	})
})
