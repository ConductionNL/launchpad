// SPDX-License-Identifier: EUPL-1.2
// Copyright (C) 2026 Conduction B.V.

/**
 * The source line and the outage state of an embedded page or report
 * (REQ-IFRAME-005).
 *
 * Pure: the translate function is passed in and the clock is an argument, so
 * every branch runs under vitest with nothing mocked.
 *
 * Content keys read (all optional):
 * - `kind`: `'report'` for an embedded report, anything else is a page.
 * - `connection`: what the connection behind the embed knows, when one signs
 *   in: `{account?: string, error?: string, errorAt?: string}` (ISO time).
 *
 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */

/**
 * The widget's own failure reasons that are an outage of the target. An
 * unconfigured embed or a host the administrator took off the allow-list is
 * not an outage: those keep their REQ-IFRAME-004 text.
 *
 * @type {string[]}
 */
export const OUTAGE_REASONS = ['blocked', 'unreachable', 'timeout', 'error', 'unknown']

/**
 * Whether the content describes an embedded report rather than a page.
 *
 * @param {object|null|undefined} content The placement content.
 * @return {boolean} True for `kind: 'report'`.
 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */
export function isReport(content) {
	return content?.kind === 'report'
}

/**
 * The host of an embed URL, or '' when it does not parse.
 *
 * @param {string} url The embed URL.
 * @return {string} The host, e.g. `app.powerbi.com`.
 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */
export function embedHost(url) {
	if (typeof url !== 'string' || url.trim() === '') {
		return ''
	}
	try {
		return new URL(url.trim()).host
	} catch {
		return ''
	}
}

/**
 * The signed-in account of the connection, or '' when it does not sign in.
 *
 * @param {object|null|undefined} content The placement content.
 * @return {string} The account name.
 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */
function connectionAccount(content) {
	const account = content?.connection?.account
	return typeof account === 'string' ? account.trim() : ''
}

/**
 * The source line under the title: kind, host and, when the connection signs
 * in, the account ("Embedded report · app.powerbi.com · Signed in as Pieter
 * Jansen"). '' when the embed has no URL.
 *
 * @param {object|null|undefined} content The placement content.
 * @param {function(string, string, object=): string} t The translate function.
 * @return {string} The source line.
 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */
export function embedSourceLine(content, t) {
	const host = embedHost(content?.url)
	if (host === '') {
		return ''
	}
	const parts = [
		isReport(content) ? t('launchpad', 'Embedded report') : t('launchpad', 'Embedded page'),
		host,
	]
	const account = connectionAccount(content)
	if (account !== '') {
		parts.push(t('launchpad', 'Signed in as {name}', { name: account }))
	}
	return parts.join(' · ')
}

/**
 * The cause the widget itself found, in words, or '' when it is unknown.
 *
 * @param {string} reason The widget's failure reason.
 * @param {function(string, string, object=): string} t The translate function.
 * @return {string} The cause.
 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */
export function ownCause(reason, t) {
	switch (reason) {
	case 'blocked':
		return t('launchpad', 'the site refuses to be embedded')
	case 'unreachable':
		return t('launchpad', 'the site could not be reached')
	case 'timeout':
		return t('launchpad', 'the page did not finish loading')
	case 'error':
		return t('launchpad', 'a network error')
	default:
		return ''
	}
}

/**
 * Two-digit clock time, e.g. `09:30`.
 *
 * @param {Date} date The moment.
 * @return {string} HH:MM in local time.
 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */
export function clockTime(date) {
	return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

/**
 * The cause and its time. The connection's own answer wins over what the
 * widget found, because it knows more ("token expired"). '' when no cause is
 * known: the card then says only that the report could not be loaded.
 *
 * @param {object} input The failure.
 * @param {object|null|undefined} input.content The placement content.
 * @param {string} input.reason The widget's failure reason.
 * @param {Date|null} input.failedAt When the widget saw the failure.
 * @param {Date} input.now The current time.
 * @param {function(string, string, object=): string} t The translate function.
 * @return {string} E.g. "Gave an error today at 09:30: token expired".
 * @spec openspec/changes/embed-outage-state/specs/iframe-embed-widget/spec.md#requirement-the-outage-state-of-an-embedded-report-req-iframe-005
 */
export function outageCauseLine({ content, reason, failedAt, now }, t) {
	let cause
	let at
	const connectionError = content?.connection?.error
	if (typeof connectionError === 'string' && connectionError.trim() !== '') {
		cause = connectionError.trim()
		const stamp = new Date(content.connection.errorAt ?? '')
		at = Number.isNaN(stamp.getTime()) ? null : stamp
	} else {
		cause = ownCause(reason, t)
		at = failedAt instanceof Date && !Number.isNaN(failedAt.getTime()) ? failedAt : null
	}
	if (cause === '') {
		return ''
	}
	if (at === null) {
		return t('launchpad', 'Gave an error: {cause}', { cause })
	}
	if (at.toDateString() === now.toDateString()) {
		return t('launchpad', 'Gave an error today at {time}: {cause}', { time: clockTime(at), cause })
	}
	return t('launchpad', 'Gave an error on {date} at {time}: {cause}', {
		date: at.toLocaleDateString(),
		time: clockTime(at),
		cause,
	})
}
