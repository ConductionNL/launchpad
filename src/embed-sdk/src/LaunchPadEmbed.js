/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @launchpad/embed-sdk — embed a LaunchPad widget or dashboard in any page
 * (embedded-analytics tasks 19-22, REQ-EMB-006).
 *
 *   const embed = await LaunchPadEmbed.render(container, {
 *     baseUrl: 'https://cloud.example.nl/index.php/apps/launchpad',
 *     token, subjectType: 'widget', subjectId: '42',
 *   })
 *   embed.on('filterApplied', (payload) => …)
 *   await embed.resize()
 *
 * The iframe page needs the token to load, so its URL carries it; the
 * handshake then hands the token over by postMessage for the embed's own
 * requests and proves both ends: the SDK accepts messages only from the
 * iframe's window at the LaunchPad origin, and the iframe answers only a
 * parent whose origin the token lists.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-006--js-sdk-exposes-render-event-and-resize-apis
 */

import { isFrom, MESSAGES, originOf, post } from './postMessage.js'

const DEFAULT_TIMEOUT = 10000

/**
 * A random handshake nonce.
 *
 * @return {string}
 */
function nonce() {
	if (globalThis.crypto?.randomUUID) {
		return globalThis.crypto.randomUUID()
	}
	return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`
}

/**
 * One rendered embed: its iframe, its listeners and its resize.
 */
export class EmbedInstance {

	/**
	 * @param {HTMLIFrameElement} iframe The embed's iframe.
	 * @param {string} origin The LaunchPad origin.
	 * @param {Window} host The host page's window.
	 * @param {number} timeout Milliseconds to wait for an answer.
	 */
	constructor(iframe, origin, host, timeout) {
		this.iframe = iframe
		this.origin = origin
		this.host = host
		this.timeout = timeout
		this.listeners = new Map()
		this.pending = new Map()
		this.onMessage = this.receive.bind(this)
		host.addEventListener('message', this.onMessage)
	}

	/**
	 * Listen for an event the embed relays (filterApplied, drillDown, export, error, …).
	 *
	 * @param {string} eventType The event.
	 * @param {(payload: unknown) => void} handler Called with the event's payload.
	 * @return {EmbedInstance} This embed, for chaining.
	 */
	on(eventType, handler) {
		if (typeof handler !== 'function') {
			throw new TypeError('An embed event handler must be a function.')
		}
		if (!this.listeners.has(eventType)) {
			this.listeners.set(eventType, new Set())
		}
		this.listeners.get(eventType).add(handler)
		return this
	}

	/**
	 * Stop listening; without a handler, drop every listener of the event.
	 *
	 * @param {string} eventType The event.
	 * @param {(payload: unknown) => void} [handler] The handler given to on().
	 * @return {EmbedInstance} This embed, for chaining.
	 */
	off(eventType, handler) {
		if (handler === undefined) {
			this.listeners.delete(eventType)
		} else {
			this.listeners.get(eventType)?.delete(handler)
		}
		return this
	}

	/**
	 * Tell the embed its container's size and wait until it has re-measured.
	 *
	 * @return {Promise<{width: number, height: number}>} The size the embed took.
	 */
	resize() {
		const container = this.iframe.parentElement
		const width = Math.round(container?.clientWidth || this.iframe.clientWidth || 0)
		const height = Math.round(container?.clientHeight || this.iframe.clientHeight || 0)
		return this.request(MESSAGES.resize, MESSAGES.resized, { width, height })
	}

	/**
	 * Remove the iframe and every listener.
	 *
	 * @return {void}
	 */
	destroy() {
		this.host.removeEventListener('message', this.onMessage)
		this.listeners.clear()
		for (const { reject, timer } of this.pending.values()) {
			clearTimeout(timer)
			reject(new Error('The embed was destroyed.'))
		}
		this.pending.clear()
		this.iframe.remove()
	}

	/**
	 * Post a message and wait for its answer, matched by nonce.
	 *
	 * @param {string} type The message.
	 * @param {string} answer The answer's type.
	 * @param {object} payload The message's payload.
	 * @return {Promise<object>} The answer.
	 */
	request(type, answer, payload) {
		const id = nonce()
		return new Promise((resolve, reject) => {
			const timer = setTimeout(() => {
				this.pending.delete(id)
				reject(new Error(`The embed did not answer ${type} in time.`))
			}, this.timeout)
			this.pending.set(id, { answer, resolve, reject, timer })
			post(this.iframe.contentWindow, this.origin, type, { ...payload, nonce: id })
		})
	}

	/**
	 * Handle a message from the embed: an answer to a request, or an event.
	 *
	 * @param {MessageEvent} event The message.
	 * @return {void}
	 */
	receive(event) {
		if (!isFrom(event, { origin: this.origin, source: this.iframe.contentWindow })) {
			return
		}
		const data = event.data
		const waiting = data.nonce !== undefined ? this.pending.get(data.nonce) : undefined
		if (waiting && (data.type === waiting.answer || data.type === MESSAGES.error)) {
			clearTimeout(waiting.timer)
			this.pending.delete(data.nonce)
			if (data.type === MESSAGES.error) {
				waiting.reject(Object.assign(new Error(data.message || 'The embed refused.'), { code: data.error }))
			} else {
				const answer = { ...data }
				delete answer.type
				delete answer.nonce
				waiting.resolve(answer)
			}
			return
		}
		if (data.type === MESSAGES.event && typeof data.event === 'string') {
			this.emit(data.event, data.payload)
		} else if (data.type === MESSAGES.error) {
			this.emit('error', { error: data.error, message: data.message })
		}
	}

	/**
	 * Call every handler of an event; one failing handler does not stop the others.
	 *
	 * @param {string} eventType The event.
	 * @param {unknown} payload Its payload.
	 * @return {void}
	 */
	emit(eventType, payload) {
		for (const handler of [...(this.listeners.get(eventType) || [])]) {
			try {
				handler(payload)
			} catch (failure) {
				// A host handler's failure is the host's; report it without stopping the others.
				setTimeout(() => {
					throw failure
				})
			}
		}
	}

}

/**
 * The iframe URL of an embed.
 *
 * @param {object} config The embed config.
 * @return {string}
 */
export function embedUrl(config) {
	const base = String(config.baseUrl || '').replace(/\/+$/, '')
	const url = new URL(`${base}/embed/${encodeURIComponent(config.subjectType)}/${encodeURIComponent(config.subjectId)}`)
	url.searchParams.set('token', config.token)
	if (config.viewport) {
		url.searchParams.set('viewport', config.viewport)
	}
	return url.toString()
}

/**
 * Render an embed into a container: create the iframe, wait for it to load,
 * hand over the token and resolve once the embed says it is ready.
 *
 * @param {HTMLElement} container Where the iframe goes.
 * @param {{baseUrl: string, token: string, subjectType: string, subjectId: string, title?: string, viewport?: string, timeout?: number}} config The embed.
 * @param {Window} [host] The host page's window (for tests).
 * @return {Promise<EmbedInstance>}
 */
export function render(container, config, host = globalThis.window) {
	if (!container || typeof container.appendChild !== 'function') {
		return Promise.reject(new TypeError('LaunchPadEmbed.render needs a container element.'))
	}
	for (const key of ['baseUrl', 'token', 'subjectType', 'subjectId']) {
		if (!config?.[key]) {
			return Promise.reject(new TypeError(`LaunchPadEmbed.render needs config.${key}.`))
		}
	}
	if (!['widget', 'dashboard'].includes(config.subjectType)) {
		return Promise.reject(new TypeError('config.subjectType is "widget" or "dashboard".'))
	}

	const origin = originOf(config.baseUrl)
	const document = container.ownerDocument
	const iframe = document.createElement('iframe')
	iframe.src = embedUrl(config)
	iframe.title = config.title || 'LaunchPad'
	iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin')
	iframe.style.border = '0'
	iframe.style.width = '100%'
	iframe.style.height = '100%'

	const embed = new EmbedInstance(iframe, origin, host, config.timeout || DEFAULT_TIMEOUT)

	return new Promise((resolve, reject) => {
		iframe.addEventListener('load', () => {
			embed.request(MESSAGES.init, MESSAGES.ready, { token: config.token })
				.then(() => resolve(embed))
				.catch((failure) => {
					embed.destroy()
					reject(failure)
				})
		}, { once: true })
		container.appendChild(iframe)
	})
}

export const LaunchPadEmbed = Object.freeze({ render })

export default LaunchPadEmbed
