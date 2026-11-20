/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The iframe side of an embed: it loads its subject with the URL token, or
 * the SDK's, as a Bearer credential; it answers only the parent window at an
 * origin the token lists; it relays events and resize answers only there;
 * and it shows only the actions the token's scope allows.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-006--js-sdk-exposes-render-event-and-resize-apis
 */

import { describe, expect, it, vi } from 'vitest'
import { MESSAGES } from '../../embed-sdk/src/postMessage.js'
import { subjectOf, useEmbedContext } from '../useEmbedContext.js'

const HOST = 'https://www.zeist.nl'

/**
 * A token whose claims list the host origins.
 *
 * @param {Array<string>} hostOrigins The origins.
 * @return {string}
 */
function jwt(hostOrigins = [HOST]) {
	const claims = btoa(JSON.stringify({ iss: 'launchpad', jti: 'token-1', hostOrigins })).replace(/=+$/, '').replace(/\+/g, '-').replace(/\//g, '_')
	return `eyJhbGciOiJSUzI1NiJ9.${claims}.c2ln`
}

/**
 * A framed iframe window.
 *
 * @param {string} search The query string.
 * @return {object}
 */
function frame(search = '') {
	return {
		location: { pathname: '/index.php/apps/launchpad/embed/widget/42', search },
		parent: { postMessage: vi.fn() },
		addEventListener: vi.fn(),
		removeEventListener: vi.fn(),
		document: { documentElement: { scrollWidth: 600, scrollHeight: 420 } },
	}
}

const PAYLOAD = {
	subjectType: 'widget',
	scope: { mode: 'read-with-interactions', allowedActions: ['drillDown'], allowedFilters: ['status'] },
	filters: { gemeente: 'Zeist' },
	themeCss: ':root{--c-primary:#004f9f;}',
	placements: [{ id: 42 }],
}

describe('useEmbedContext', () => {
	it('reads the subject from the embed path', () => {
		expect(subjectOf('/index.php/apps/launchpad/embed/dashboard/dash%20uuid/')).toEqual({ subjectType: 'dashboard', subjectId: 'dash uuid', base: '/index.php/apps/launchpad' })
		expect(subjectOf('/apps/launchpad/embed/report/1')).toEqual({ subjectType: '', subjectId: '', base: '' })
	})

	it('loads the subject with the URL token as a Bearer credential, once', async () => {
		const win = frame(`?token=${jwt()}&viewport=small`)
		const load = vi.fn().mockResolvedValue(PAYLOAD)
		const context = useEmbedContext({ win, load })

		const stop = context.start()
		await context.fetchSubject()

		expect(load).toHaveBeenCalledTimes(1)
		expect(load).toHaveBeenCalledWith('/index.php/apps/launchpad/embed/widget/42/data', jwt(), { viewport: 'small' })
		expect(context.placements.value).toEqual([{ id: 42 }])
		expect(context.filters.value).toEqual({ gemeente: 'Zeist' })
		expect(context.themeCss.value).toBe(':root{--c-primary:#004f9f;}')
		expect(win.addEventListener).toHaveBeenCalledWith('message', context.onMessage)
		stop()
		expect(win.removeEventListener).toHaveBeenCalledWith('message', context.onMessage)
	})

	it('answers the handshake of a listed parent with ready, to that origin only', async () => {
		const win = frame(`?token=${jwt()}`)
		const context = useEmbedContext({ win, load: vi.fn().mockResolvedValue(PAYLOAD) })

		await context.onMessage({ source: win.parent, origin: 'https://WWW.zeist.nl', data: { type: MESSAGES.init, token: 'ignored', nonce: 'n1' } })

		expect(win.parent.postMessage).toHaveBeenCalledWith({ type: MESSAGES.ready, nonce: 'n1' }, HOST)
		expect(context.parentOrigin.value).toBe(HOST)
		expect(context.token.value).toBe(jwt())
	})

	it('takes the token from the handshake when the URL has none', async () => {
		const win = frame('')
		const load = vi.fn().mockResolvedValue(PAYLOAD)
		const context = useEmbedContext({ win, load })

		await context.onMessage({ source: win.parent, origin: HOST, data: { type: MESSAGES.init, token: jwt(), nonce: 'n2' } })

		expect(load).toHaveBeenCalledWith('/index.php/apps/launchpad/embed/widget/42/data', jwt(), {})
		expect(win.parent.postMessage).toHaveBeenCalledWith({ type: MESSAGES.ready, nonce: 'n2' }, HOST)
	})

	it('says nothing to a parent the token does not list, or to another window', async () => {
		const win = frame(`?token=${jwt()}`)
		const load = vi.fn().mockResolvedValue(PAYLOAD)
		const context = useEmbedContext({ win, load })

		await context.onMessage({ source: win.parent, origin: 'https://evil.example.com', data: { type: MESSAGES.init, nonce: 'n3' } })
		await context.onMessage({ source: {}, origin: HOST, data: { type: MESSAGES.init, nonce: 'n4' } })
		await context.onMessage({ source: win.parent, origin: HOST, data: 'launchpad-embed:init' })

		expect(win.parent.postMessage).not.toHaveBeenCalled()
		expect(context.parentOrigin.value).toBe('')
		expect(context.relay('export', {})).toBe(false)
	})

	it('passes the server refusal back on the handshake', async () => {
		const win = frame(`?token=${jwt()}`)
		const refusal = { response: { data: { error: 'token_revoked', message: 'The provided token has been revoked' } } }
		const context = useEmbedContext({ win, load: vi.fn().mockRejectedValue(refusal) })

		await context.onMessage({ source: win.parent, origin: HOST, data: { type: MESSAGES.init, nonce: 'n5' } })

		expect(win.parent.postMessage).toHaveBeenCalledWith(
			{ type: MESSAGES.error, nonce: 'n5', error: 'token_revoked', message: 'The provided token has been revoked' },
			HOST,
		)
		expect(context.error.value.error).toBe('token_revoked')
	})

	it('reports a missing token or an unreadable answer without a request', async () => {
		const load = vi.fn().mockRejectedValue(new Error('network'))
		const bare = useEmbedContext({ win: frame(''), load })
		await bare.fetchSubject()
		expect(load).not.toHaveBeenCalled()
		expect(bare.error.value.error).toBe('missing_token')

		const broken = useEmbedContext({ win: frame(`?token=${jwt()}`), load })
		await broken.fetchSubject()
		expect(broken.error.value).toEqual({ error: 'unavailable', message: 'The embed could not be loaded.' })
	})

	it('relays events and answers resize once the parent is known', async () => {
		const win = frame(`?token=${jwt()}`)
		const context = useEmbedContext({ win, load: vi.fn().mockResolvedValue(PAYLOAD) })
		await context.onMessage({ source: win.parent, origin: HOST, data: { type: MESSAGES.init, nonce: 'n6' } })

		expect(context.relay('drillDown', { status: 'open' })).toBe(true)
		await context.onMessage({ source: win.parent, origin: HOST, data: { type: MESSAGES.resize, nonce: 'r1', width: 640, height: 360 } })

		expect(win.parent.postMessage).toHaveBeenCalledWith({ type: MESSAGES.event, event: 'drillDown', payload: { status: 'open' } }, HOST)
		expect(win.parent.postMessage).toHaveBeenCalledWith({ type: MESSAGES.resized, nonce: 'r1', width: 640, height: 420 }, HOST)
	})

	it('allows only the actions the scope names', async () => {
		const context = useEmbedContext({ win: frame(`?token=${jwt()}`), load: vi.fn().mockResolvedValue(PAYLOAD) })
		expect(context.canRun('drillDown')).toBe(false)

		await context.fetchSubject()

		expect(context.canRun('drillDown')).toBe(true)
		expect(context.canRun('export')).toBe(false)
	})
})
