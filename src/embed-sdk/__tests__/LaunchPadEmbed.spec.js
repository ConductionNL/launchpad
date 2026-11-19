/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @launchpad/embed-sdk: render creates the iframe and completes the
 * handshake, events reach every listener until removed, resize posts and
 * resolves, messages from any other window or origin are ignored, and two
 * embeds on one page do not hear each other (embedded-analytics task 34).
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-006--js-sdk-exposes-render-event-and-resize-apis
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import { embedUrl, LaunchPadEmbed } from '../src/LaunchPadEmbed.js'
import { decodeClaims, isFrom, MESSAGES, originOf, post } from '../src/postMessage.js'

const BASE = 'https://cloud.example.nl/index.php/apps/launchpad'
const ORIGIN = 'https://cloud.example.nl'
const CONFIG = { baseUrl: BASE, token: 'h.p.s', subjectType: 'widget', subjectId: '42', timeout: 200 }

/**
 * Deliver a message to the host page as if the given window sent it.
 *
 * @param {object} data The message.
 * @param {Window} source The sender.
 * @param {string} [origin] The sender's origin.
 */
function deliver(data, source, origin = ORIGIN) {
	window.dispatchEvent(new MessageEvent('message', { data, source, origin }))
}

/**
 * Render an embed and play the iframe's side of the handshake.
 *
 * @param {object} [config] The embed config.
 * @return {Promise<{embed: object, iframe: HTMLIFrameElement, posted: object}>}
 */
async function rendered(config = CONFIG) {
	const container = document.createElement('div')
	document.body.appendChild(container)
	const pending = LaunchPadEmbed.render(container, config)
	const iframe = container.querySelector('iframe')
	const posted = vi.spyOn(iframe.contentWindow, 'postMessage')
	iframe.dispatchEvent(new Event('load'))
	const [init, target] = posted.mock.calls[0]
	deliver({ type: MESSAGES.ready, nonce: init.nonce }, iframe.contentWindow)
	const embed = await pending
	return { embed, iframe, posted, init, target }
}

afterEach(() => {
	document.body.innerHTML = ''
	vi.restoreAllMocks()
})

describe('LaunchPadEmbed.render', () => {
	it('creates the iframe and hands the token over to the LaunchPad origin only', async () => {
		const { embed, iframe, init, target } = await rendered()

		expect(iframe.src).toBe(`${BASE}/embed/widget/42?token=h.p.s`)
		expect(iframe.title).toBe('LaunchPad')
		expect(iframe.getAttribute('referrerpolicy')).toBe('strict-origin-when-cross-origin')
		expect(init).toMatchObject({ type: MESSAGES.init, token: 'h.p.s' })
		expect(typeof init.nonce).toBe('string')
		expect(target).toBe(ORIGIN)
		expect(embed.iframe).toBe(iframe)
	})

	it('rejects and removes the iframe when the embed refuses the handshake', async () => {
		const container = document.body.appendChild(document.createElement('div'))
		const pending = LaunchPadEmbed.render(container, CONFIG)
		const iframe = container.querySelector('iframe')
		const posted = vi.spyOn(iframe.contentWindow, 'postMessage')
		iframe.dispatchEvent(new Event('load'))
		deliver({ type: MESSAGES.error, nonce: posted.mock.calls[0][0].nonce, error: 'origin_mismatch', message: 'Not here' }, iframe.contentWindow)

		await expect(pending).rejects.toMatchObject({ message: 'Not here', code: 'origin_mismatch' })
		expect(container.querySelector('iframe')).toBeNull()
	})

	it('rejects when the embed never answers', async () => {
		const container = document.body.appendChild(document.createElement('div'))
		const pending = LaunchPadEmbed.render(container, { ...CONFIG, timeout: 10 })
		container.querySelector('iframe').dispatchEvent(new Event('load'))

		await expect(pending).rejects.toThrow('did not answer launchpad-embed:init in time')
	})

	it('refuses an incomplete config before creating anything', async () => {
		const container = document.createElement('div')

		await expect(LaunchPadEmbed.render(container, { ...CONFIG, token: '' })).rejects.toThrow('config.token')
		await expect(LaunchPadEmbed.render(container, { ...CONFIG, subjectType: 'report' })).rejects.toThrow('subjectType')
		await expect(LaunchPadEmbed.render(null, CONFIG)).rejects.toThrow('container')
		expect(container.children).toHaveLength(0)
	})

	it('encodes the subject and passes the viewport on', () => {
		expect(embedUrl({ ...CONFIG, baseUrl: `${BASE}/`, subjectType: 'dashboard', subjectId: 'a b/c', viewport: 'small' }))
			.toBe(`${BASE}/embed/dashboard/a%20b%2Fc?token=h.p.s&viewport=small`)
	})
})

describe('an embed instance', () => {
	it('relays events to every listener until they are removed', async () => {
		const { embed, iframe } = await rendered()
		const first = vi.fn()
		const second = vi.fn()
		embed.on('filterApplied', first).on('filterApplied', second)

		deliver({ type: MESSAGES.event, event: 'filterApplied', payload: { gemeente: 'Zeist' } }, iframe.contentWindow)
		embed.off('filterApplied', first)
		deliver({ type: MESSAGES.event, event: 'filterApplied', payload: { gemeente: 'Utrecht' } }, iframe.contentWindow)
		embed.off('filterApplied')
		deliver({ type: MESSAGES.event, event: 'filterApplied', payload: {} }, iframe.contentWindow)

		expect(first.mock.calls).toEqual([[{ gemeente: 'Zeist' }]])
		expect(second.mock.calls).toEqual([[{ gemeente: 'Zeist' }], [{ gemeente: 'Utrecht' }]])
		expect(() => embed.on('export', 'not a function')).toThrow(TypeError)
	})

	it('ignores messages from another origin, another window or of another kind', async () => {
		const { embed, iframe } = await rendered()
		const handler = vi.fn()
		embed.on('drillDown', handler)

		deliver({ type: MESSAGES.event, event: 'drillDown', payload: 1 }, iframe.contentWindow, 'https://evil.example.com')
		deliver({ type: MESSAGES.event, event: 'drillDown', payload: 2 }, window)
		deliver({ type: 'other:event', event: 'drillDown', payload: 3 }, iframe.contentWindow)
		deliver('launchpad-embed:event', iframe.contentWindow)

		expect(handler).not.toHaveBeenCalled()
	})

	it('reports an unsolicited refusal as an error event', async () => {
		const { embed, iframe } = await rendered()
		const handler = vi.fn()
		embed.on('error', handler)

		deliver({ type: MESSAGES.error, error: 'token_revoked', message: 'Withdrawn' }, iframe.contentWindow)

		expect(handler).toHaveBeenCalledWith({ error: 'token_revoked', message: 'Withdrawn' })
	})

	it('posts the container size on resize and resolves with what the embed took', async () => {
		const { embed, iframe, posted } = await rendered()
		Object.defineProperty(iframe.parentElement, 'clientWidth', { value: 640, configurable: true })
		Object.defineProperty(iframe.parentElement, 'clientHeight', { value: 360, configurable: true })

		const resizing = embed.resize()
		const [message, target] = posted.mock.calls.at(-1)
		expect(message).toMatchObject({ type: MESSAGES.resize, width: 640, height: 360 })
		expect(target).toBe(ORIGIN)
		deliver({ type: MESSAGES.resized, nonce: message.nonce, width: 640, height: 412 }, iframe.contentWindow)

		await expect(resizing).resolves.toEqual({ width: 640, height: 412 })
	})

	it('keeps two embeds on one page apart', async () => {
		const one = await rendered()
		const two = await rendered({ ...CONFIG, subjectId: '43' })
		const heardByOne = vi.fn()
		const heardByTwo = vi.fn()
		one.embed.on('export', heardByOne)
		two.embed.on('export', heardByTwo)

		deliver({ type: MESSAGES.event, event: 'export', payload: 'two' }, two.iframe.contentWindow)

		expect(heardByOne).not.toHaveBeenCalled()
		expect(heardByTwo).toHaveBeenCalledWith('two')
	})

	it('rejects pending requests and stops listening once destroyed', async () => {
		const { embed, iframe } = await rendered()
		const handler = vi.fn()
		embed.on('export', handler)
		const resizing = embed.resize()

		embed.destroy()
		deliver({ type: MESSAGES.event, event: 'export', payload: 1 }, iframe.contentWindow)

		await expect(resizing).rejects.toThrow('destroyed')
		expect(handler).not.toHaveBeenCalled()
		expect(document.querySelector('iframe[src*="/embed/widget/42"]')).toBeNull()
	})
})

describe('the message helpers', () => {
	it('never post to every origin', () => {
		const target = { postMessage: vi.fn() }

		expect(() => post(target, '*', MESSAGES.event)).toThrow('explicit origin')
		post(target, ORIGIN, MESSAGES.event, { event: 'export' })
		expect(target.postMessage).toHaveBeenCalledWith({ event: 'export', type: MESSAGES.event }, ORIGIN)
	})

	it('read origins and unverified claims defensively', () => {
		const claims = btoa(JSON.stringify({ iss: 'launchpad', hostOrigins: ['https://www.zeist.nl'] })).replace(/=+$/, '')

		expect(originOf('https://WWW.Zeist.nl/woo/')).toBe('https://www.zeist.nl')
		expect(originOf('not a url')).toBe('')
		expect(decodeClaims(`h.${claims}.s`)).toEqual({ iss: 'launchpad', hostOrigins: ['https://www.zeist.nl'] })
		expect(decodeClaims('h.!!!.s')).toEqual({})
		expect(decodeClaims('only-one-part')).toEqual({})
		expect(isFrom({ data: null, source: window, origin: ORIGIN }, { origin: ORIGIN, source: window })).toBe(false)
	})
})
