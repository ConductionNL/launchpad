/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The iframe side of an embed (embedded-analytics tasks 14, 23): which
 * subject this page shows and with which token, the subject's data with the
 * token's scope and theme, and the conversation with the host page.
 *
 * The token comes from the page URL (`?token=`, the plain-iframe form) or
 * from the SDK's `init` message. A message is answered only when it comes
 * from the parent window at an origin the token lists, and everything is
 * posted to that origin only. The scope hides what the token does not allow
 * (`canRun`); the server refuses it regardless.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-006--js-sdk-exposes-render-event-and-resize-apis
 */

import { computed, ref } from 'vue'
import { decodeClaims, MESSAGES, post } from '../embed-sdk/src/postMessage.js'

/**
 * The subject this page shows, from `/embed/{subjectType}/{subjectId}`.
 *
 * @param {string} pathname The page path.
 * @return {{subjectType: string, subjectId: string, base: string}}
 */
export function subjectOf(pathname) {
	const match = /^(.*)\/embed\/(widget|dashboard)\/([^/]+)\/?$/.exec(String(pathname || ''))
	if (!match) {
		return { subjectType: '', subjectId: '', base: '' }
	}
	return { subjectType: match[2], subjectId: decodeURIComponent(match[3]), base: match[1] }
}

/**
 * The embed context of this page.
 *
 * @param {object} options The environment.
 * @param {Window} options.win The iframe's window.
 * @param {(url: string, token: string, params: object) => Promise<object>} options.load Fetches the subject.
 * @return {object} The context.
 */
export function useEmbedContext({ win, load }) {
	const subject = subjectOf(win.location.pathname)
	const token = ref(new URLSearchParams(win.location.search).get('token') || '')
	const viewport = new URLSearchParams(win.location.search).get('viewport') || ''
	const parentOrigin = ref('')
	const payload = ref(null)
	const error = ref(null)
	const loading = ref(false)
	let loaded = null

	const claims = computed(() => decodeClaims(token.value))
	const hostOrigins = computed(() => (Array.isArray(claims.value.hostOrigins) ? claims.value.hostOrigins : [])
		.map((origin) => String(origin).replace(/\/+$/, '').toLowerCase()))
	const scope = computed(() => payload.value?.scope || { mode: 'read', allowedActions: [], allowedFilters: [] })
	const themeCss = computed(() => payload.value?.themeCss || '')
	const filters = computed(() => payload.value?.filters || {})
	const placements = computed(() => (Array.isArray(payload.value?.placements) ? payload.value.placements : []))

	/**
	 * Whether the token lets the embed run an action (interact, export, drillDown).
	 *
	 * @param {string} action The action.
	 * @return {boolean}
	 */
	const canRun = (action) => scope.value.mode === 'read-with-interactions'
		&& Array.isArray(scope.value.allowedActions)
		&& scope.value.allowedActions.includes(action)

	/**
	 * Load the subject once per token.
	 *
	 * @return {Promise<void>}
	 */
	const fetchSubject = () => {
		if (!token.value || !subject.subjectType) {
			error.value = { error: 'missing_token', message: 'No token provided.' }
			return Promise.resolve()
		}
		if (loaded) {
			return loaded
		}
		loading.value = true
		const url = `${subject.base}/embed/${encodeURIComponent(subject.subjectType)}/${encodeURIComponent(subject.subjectId)}/data`
		loaded = load(url, token.value, viewport ? { viewport } : {})
			.then((data) => {
				payload.value = data
				error.value = null
			})
			.catch((failure) => {
				error.value = failure?.response?.data?.error
					? failure.response.data
					: { error: 'unavailable', message: 'The embed could not be loaded.' }
			})
			.finally(() => {
				loading.value = false
			})
		return loaded
	}

	/**
	 * Relay an event to the host page, once it has introduced itself.
	 *
	 * @param {string} eventType filterApplied, drillDown, export, …
	 * @param {unknown} eventPayload The event's payload.
	 * @return {boolean} Whether it was posted.
	 */
	const relay = (eventType, eventPayload) => {
		if (!parentOrigin.value) {
			return false
		}
		post(win.parent, parentOrigin.value, MESSAGES.event, { event: eventType, payload: eventPayload })
		return true
	}

	/**
	 * Answer the SDK: the init handshake and resize requests.
	 *
	 * @param {MessageEvent} event The message.
	 * @return {Promise<void>}
	 */
	const onMessage = async (event) => {
		const data = event?.data
		if (event?.source !== win.parent || win.parent === win || typeof data?.type !== 'string') {
			return
		}
		if (data.type === MESSAGES.init && !token.value && typeof data.token === 'string') {
			token.value = data.token
		}
		const origin = String(event.origin || '').toLowerCase()
		if (!hostOrigins.value.includes(origin)) {
			// Not one of the token's pages: no answer at all, so nothing leaks.
			return
		}
		parentOrigin.value = origin

		if (data.type === MESSAGES.init) {
			await fetchSubject()
			if (error.value) {
				post(win.parent, origin, MESSAGES.error, { nonce: data.nonce, ...error.value })
			} else {
				post(win.parent, origin, MESSAGES.ready, { nonce: data.nonce })
			}
		} else if (data.type === MESSAGES.resize) {
			const root = win.document.documentElement
			post(win.parent, origin, MESSAGES.resized, {
				nonce: data.nonce,
				width: Math.round(Number(data.width) || root.scrollWidth || 0),
				height: Math.round(root.scrollHeight || Number(data.height) || 0),
			})
		}
	}

	/**
	 * Start: listen for the host and load the subject when the URL has the token.
	 *
	 * @return {() => void} Stops listening.
	 */
	const start = () => {
		win.addEventListener('message', onMessage)
		if (token.value) {
			fetchSubject()
		}
		return () => win.removeEventListener('message', onMessage)
	}

	return {
		subject,
		token,
		claims,
		scope,
		themeCss,
		filters,
		placements,
		payload,
		error,
		loading,
		parentOrigin,
		canRun,
		relay,
		onMessage,
		fetchSubject,
		start,
	}
}
