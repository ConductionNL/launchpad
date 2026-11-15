/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The message protocol between a host page and a LaunchPad embed iframe
 * (embedded-analytics REQ-EMB-006). Every message is a plain object whose
 * `type` starts with `launchpad-embed:`; both sides accept one only from the
 * window and origin they expect, and post only to a named origin, never `*`.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-006--js-sdk-exposes-render-event-and-resize-apis
 */

export const PREFIX = 'launchpad-embed:'

export const MESSAGES = Object.freeze({
	init: `${PREFIX}init`,
	ready: `${PREFIX}ready`,
	error: `${PREFIX}error`,
	event: `${PREFIX}event`,
	resize: `${PREFIX}resize`,
	resized: `${PREFIX}resized`,
})

/** The events an embed relays to its host. */
export const EVENTS = Object.freeze(['filterApplied', 'drillDown', 'export', 'error', 'resized'])

/**
 * The `scheme://host[:port]` of a URL, '' when it has none.
 *
 * @param {string} url The URL.
 * @param {string} [base] The base for a relative URL.
 * @return {string} The origin.
 */
export function originOf(url, base) {
	try {
		const parsed = new URL(url, base)
		if (parsed.origin === 'null') {
			return ''
		}
		return parsed.origin.toLowerCase()
	} catch {
		return ''
	}
}

/**
 * Whether a message event comes from the expected window and origin and
 * carries one of our types.
 *
 * @param {MessageEvent} event The event.
 * @param {{origin: string, source: object|null}} expected Who may send it.
 * @return {boolean}
 */
export function isFrom(event, expected) {
	return Boolean(event)
		&& typeof event.data === 'object'
		&& event.data !== null
		&& typeof event.data.type === 'string'
		&& event.data.type.startsWith(PREFIX)
		&& expected.source !== null
		&& event.source === expected.source
		&& typeof event.origin === 'string'
		&& event.origin.toLowerCase() === expected.origin
}

/**
 * Post one message to a window at a named origin.
 *
 * @param {Window} target The receiving window.
 * @param {string} origin Its origin; never '*'.
 * @param {string} type One of MESSAGES.
 * @param {object} [payload] The rest of the message.
 * @return {void}
 */
export function post(target, origin, type, payload = {}) {
	if (!target || !origin || origin === '*') {
		throw new Error('An embed message needs a target window and an explicit origin.')
	}
	target.postMessage({ ...payload, type }, origin)
}

/**
 * The claims of a JWT, unverified ({} when it is not one). The embed reads
 * `hostOrigins` from them to check its parent; the server verifies the
 * signature on every request.
 *
 * @param {string} jwt The token.
 * @return {object} The claims.
 */
export function decodeClaims(jwt) {
	const segments = String(jwt || '').split('.')
	if (segments.length !== 3) {
		return {}
	}
	try {
		const base64 = segments[1].replace(/-/g, '+').replace(/_/g, '/')
		const json = decodeURIComponent(
			Array.from(atob(base64), (char) => `%${char.charCodeAt(0).toString(16).padStart(2, '0')}`).join(''),
		)
		const claims = JSON.parse(json)
		return claims && typeof claims === 'object' ? claims : {}
	} catch {
		return {}
	}
}
