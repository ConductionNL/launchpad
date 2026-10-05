/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The attention feed's rules (openspec/specs/attention-feed REQ-ATT-003..005):
 * tokens, the one filter behind both the count and the link, the comparison,
 * the ranking, and what a failure becomes.
 *
 * The sources are the declarations the proposal asks dossiq, pipelinq,
 * decidiq and learniq to ship, in the shape AttentionSourceService returns.
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const get = vi.fn()
vi.mock('@nextcloud/axios', () => ({ default: { get: (...args) => get(...args) } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))

import {
	actionPath,
	checkSource,
	countPath,
	filterPairs,
	loadAttentionFeed,
	needsAttention,
	rank,
	resolveToken,
} from '../attentionFeed.js'

// Monday 5 October 2026, 14:30 local time.
const ctx = { userId: 'pieter', now: new Date(2026, 9, 5, 14, 30, 0) }

const dossiq = {
	appId: 'dossiq',
	appName: 'Dossiq',
	id: 'cases-past-deadline',
	title: 'Cases past their deadline',
	reason: '{value} of your cases are past their deadline or end today.',
	severity: 'error',
	source: {
		register: 'dossiq',
		schema: 'case',
		filter: {
			assignee: '@me',
			isFinalStatus: false,
			statusHiddenInLists: false,
			isDraft: false,
			'deadline[lt]': '@today+1d',
		},
	},
	op: 'gt',
	value: 0,
	action: { label: 'Open these cases', path: '/apps/dossiq/cases' },
}
const pipelinq = {
	appId: 'pipelinq',
	appName: 'Pipelinq',
	id: 'tickets-past-deadline',
	title: 'Tickets past their deadline',
	reason: '{value} of your tickets in progress are past their deadline or end today.',
	severity: 'error',
	source: {
		register: 'pipelinq',
		schema: 'ticket',
		filter: {
			assignee: '@me',
			status: 'in_progress',
			'slaDeadline[lt]': '@today+1d',
		},
	},
	op: 'gt',
	value: 0,
	action: { label: 'Open these tickets', path: '/apps/pipelinq/tickets' },
}
const decidiq = {
	appId: 'decidiq',
	appName: 'Decidiq',
	id: 'decisions-open-for-voting',
	title: 'Decisions wait for the vote',
	reason: '{value} decisions are open for voting.',
	severity: 'warning',
	source: {
		register: 'decidiq',
		schema: 'decision',
		filter: { lifecycle: 'voting' },
	},
	op: 'gt',
	value: 0,
	action: { label: 'Open these decisions', path: '/apps/decidiq/decisions' },
}

/** Split `a=b&c=d` into decoded pairs. */
function pairsOf(url) {
	const query = url.includes('?') ? url.slice(url.indexOf('?') + 1) : ''
	return query === ''
		? []
		: query.split('&').map((part) => part.split('=').map(decodeURIComponent))
}

/** Answer each OpenRegister count by register, or reject. */
function answerCounts(byRegister) {
	get.mockImplementation((url) => {
		if (url.endsWith('/api/attention/sources')) {
			return Promise.resolve({ data: byRegister.__sources })
		}
		const register = /\/api\/objects\/([^/]+)\//.exec(url)[1]
		const answer = byRegister[register]
		return answer instanceof Error
			? Promise.reject(answer)
			: Promise.resolve({ data: answer })
	})
}

beforeEach(() => {
	get.mockReset()
})

describe('resolveToken', () => {
	it('resolves every token the contract names', () => {
		expect(resolveToken('@me', ctx)).toBe('pieter')
		expect(resolveToken('@today', ctx)).toBe('2026-10-05')
		expect(resolveToken('@today+1d', ctx)).toBe('2026-10-06')
		expect(resolveToken('@today-7d', ctx)).toBe('2026-09-28')
		expect(resolveToken('@today+30d', ctx)).toBe('2026-11-04')
		expect(resolveToken('@monthStart', ctx)).toBe('2026-10-01')
		expect(resolveToken('@quarterStart', ctx)).toBe('2026-10-01')
		expect(resolveToken('@yearStart', ctx)).toBe('2026-01-01')
		expect(resolveToken('@now', ctx)).toBe(ctx.now.toISOString())
	})

	it('leaves everything that is not a token alone', () => {
		expect(resolveToken('in_progress', ctx)).toBe('in_progress')
		expect(resolveToken(false, ctx)).toBe(false)
		expect(resolveToken(3, ctx)).toBe(3)
		expect(resolveToken('mail@example.org', ctx)).toBe('mail@example.org')
	})

	it('throws on a token it does not know, and on @me without a user', () => {
		expect(() => resolveToken('@manager', ctx)).toThrow('unknown token @manager')
		expect(() => resolveToken('@today+1w', ctx)).toThrow('unknown token')
		expect(() => resolveToken('@me', { userId: '', now: ctx.now })).toThrow(
			'@me needs a signed-in user',
		)
	})
})

describe('the count and the link', () => {
	it('the count and the link carry the same filter', () => {
		const count = countPath(dossiq, ctx)
		const link = actionPath(dossiq)

		expect(count).toBe(
			'/apps/openregister/api/objects/dossiq/case'
				+ '?assignee=pieter&isFinalStatus=false&statusHiddenInLists=false&isDraft=false&deadline[lt]=2026-10-06&_limit=1',
		)
		expect(link).toBe(
			'/apps/dossiq/cases'
				+ '?assignee=@me&isFinalStatus=false&statusHiddenInLists=false&isDraft=false&deadline[lt]=@today%2B1d',
		)

		// The serialised addresses, compared: same keys in the same order,
		// and each link value resolves to the count's value. This is the
		// check all four app lanes had to add after a live defect.
		const countPairs = pairsOf(count).filter(([key]) => key !== '_limit')
		const linkPairs = pairsOf(link)
		expect(linkPairs.map(([key]) => key)).toEqual(countPairs.map(([key]) => key))
		expect(
			linkPairs.map(([, value]) => String(resolveToken(value, ctx))),
		).toEqual(countPairs.map(([, value]) => value))
	})

	it('holds for every declaration the apps are asked to ship', () => {
		for (const source of [dossiq, pipelinq, decidiq]) {
			const countPairs = pairsOf(countPath(source, ctx)).filter(
				([key]) => key !== '_limit',
			)
			const linkPairs = pairsOf(actionPath(source))
			expect(
				linkPairs.map(([key]) => key),
				source.appId,
			).toEqual(Object.keys(source.source.filter))
			expect(
				linkPairs.map(([, value]) => String(resolveToken(value, ctx))),
				source.appId,
			).toEqual(countPairs.map(([, value]) => value))
		}
	})

	it('writes a list as repeated key[] pairs in both', () => {
		const source = {
			...decidiq,
			source: {
				register: 'decidiq',
				schema: 'governance-commitment',
				filter: { lifecycle: ['open', 'in-execution'] },
			},
		}

		expect(filterPairs(source.source.filter)).toEqual([
			['lifecycle[]', 'open'],
			['lifecycle[]', 'in-execution'],
		])
		expect(countPath(source, ctx)).toContain(
			'?lifecycle[]=open&lifecycle[]=in-execution&_limit=1',
		)
		expect(actionPath(source)).toBe(
			'/apps/decidiq/decisions?lifecycle[]=open&lifecycle[]=in-execution',
		)
	})

	it('links to the bare path when there is no filter', () => {
		const source = {
			...decidiq,
			source: { register: 'decidiq', schema: 'decision', filter: {} },
		}

		expect(actionPath(source)).toBe('/apps/decidiq/decisions')
		expect(countPath(source, ctx)).toBe(
			'/apps/openregister/api/objects/decidiq/decision?_limit=1',
		)
	})

	it('encodes a value that could break out of the query', () => {
		const source = {
			...decidiq,
			source: { register: 'r', schema: 's', filter: { title: 'a&b=c #d' } },
		}

		expect(actionPath(source)).toBe(
			'/apps/decidiq/decisions?title=a%26b%3Dc%20%23d',
		)
		expect(pairsOf(countPath(source, ctx))).toEqual([
			['title', 'a&b=c #d'],
			['_limit', '1'],
		])
	})
})

describe('needsAttention', () => {
	it('compares with every operator', () => {
		expect(needsAttention(1, 'gt', 0)).toBe(true)
		expect(needsAttention(0, 'gt', 0)).toBe(false)
		expect(needsAttention(0, 'gte', 0)).toBe(true)
		expect(needsAttention(2, 'lt', 3)).toBe(true)
		expect(needsAttention(3, 'lt', 3)).toBe(false)
		expect(needsAttention(3, 'lte', 3)).toBe(true)
		expect(needsAttention(3, 'eq', 3)).toBe(true)
		expect(needsAttention(3, 'neq', 3)).toBe(false)
		expect(() => needsAttention(1, 'contains', 0)).toThrow('unknown operator')
	})
})

describe('rank', () => {
	it('ranks by severity, then count, then app', () => {
		const entry = (appName, severity, count, id = 'x') => ({
			appName,
			severity,
			count,
			id,
		})
		const ranked = rank([
			entry('Decidiq', 'warning', 2),
			entry('Learniq', 'info', 99),
			entry('Dossiq', 'error', 3),
			entry('Pipelinq', 'error', 5),
			entry('Alpha', 'warning', 2),
			entry('Alpha', 'warning', 2, 'a'),
		])

		expect(ranked.map((e) => `${e.appName}/${e.id}`)).toEqual([
			'Pipelinq/x',
			'Dossiq/x',
			'Alpha/a',
			'Alpha/x',
			'Decidiq/x',
			'Learniq/x',
		])
	})
})

describe('checkSource', () => {
	it('fills the count into the reason and links into the app', async () => {
		get.mockResolvedValue({ data: { total: 3, results: [{}] } })

		const entry = await checkSource(dossiq, ctx)

		expect(get).toHaveBeenCalledTimes(1)
		expect(get.mock.calls[0][0]).toBe(`/index.php${countPath(dossiq, ctx)}`)
		expect(entry).toMatchObject({
			key: 'dossiq/cases-past-deadline',
			failed: false,
			count: 3,
			attention: true,
			reason: '3 of your cases are past their deadline or end today.',
			actionLabel: 'Open these cases',
			href: `/index.php${actionPath(dossiq)}`,
		})
	})

	it('a zero is checked and needs no attention', async () => {
		get.mockResolvedValue({ data: { total: 0, results: [] } })

		expect(await checkSource(dossiq, ctx)).toMatchObject({
			failed: false,
			count: 0,
			attention: false,
		})
	})

	it.each([
		[
			'a server error',
			() => Promise.reject(new Error('Request failed with status code 500')),
		],
		[
			'no access',
			() => Promise.reject(new Error('Request failed with status code 403')),
		],
		[
			'an answer without a total',
			() => Promise.resolve({ data: { results: [] } }),
		],
		[
			'a total that is not a number',
			() => Promise.resolve({ data: { total: '3' } }),
		],
		['an empty answer', () => Promise.resolve({ data: null })],
	])('%s is a failure, never a zero', async (_name, answer) => {
		get.mockImplementation(answer)

		const entry = await checkSource(dossiq, ctx)

		expect(entry.failed).toBe(true)
		expect(entry).not.toHaveProperty('count')
		expect(entry).not.toHaveProperty('attention')
	})

	it('an unknown token fails the source and sends nothing', async () => {
		const source = {
			...dossiq,
			source: { ...dossiq.source, filter: { owner: '@manager' } },
		}

		const entry = await checkSource(source, ctx)

		expect(get).not.toHaveBeenCalled()
		expect(entry.failed).toBe(true)
		expect(entry.error).toContain('@manager')
	})
})

describe('loadAttentionFeed', () => {
	it('merges the apps, ranks them and uses the user the endpoint names', async () => {
		answerCounts({
			__sources: {
				userId: 'pieter',
				sources: [decidiq, dossiq, pipelinq],
				invalid: [],
			},
			dossiq: { total: 3 },
			pipelinq: { total: 5 },
			decidiq: { total: 2 },
		})

		const feed = await loadAttentionFeed({ now: ctx.now })

		expect(get.mock.calls[0][0]).toBe(
			'/index.php/apps/launchpad/api/attention/sources',
		)
		expect(feed.items.map((item) => item.appId)).toEqual([
			'pipelinq',
			'dossiq',
			'decidiq',
		])
		expect(feed.failedApps).toEqual([])
		expect(feed.sourceCount).toBe(3)
		const dossiqCall = get.mock.calls
			.map(([url]) => url)
			.find((url) => url.includes('/objects/dossiq/'))
		expect(dossiqCall).toContain('assignee=pieter')
	})

	it('names a failed app and an invalid declaration, and keeps the others', async () => {
		answerCounts({
			__sources: {
				userId: 'pieter',
				sources: [dossiq, pipelinq],
				invalid: [
					{
						appId: 'learniq',
						appName: 'Learniq',
						message: 'item "late": ...',
					},
				],
			},
			dossiq: new Error('Request failed with status code 500'),
			pipelinq: { total: 5 },
		})

		const feed = await loadAttentionFeed({ now: ctx.now })

		expect(feed.items.map((item) => item.appId)).toEqual(['pipelinq'])
		expect(feed.failedApps).toEqual(['Dossiq', 'Learniq'])
		expect(feed.sourceCount).toBe(3)
	})

	it('reports no sources as no sources', async () => {
		answerCounts({ __sources: { userId: 'pieter', sources: [], invalid: [] } })

		expect(await loadAttentionFeed({ now: ctx.now })).toEqual({
			items: [],
			failedApps: [],
			sourceCount: 0,
		})
	})

	it('rejects when the sources endpoint itself fails', async () => {
		get.mockRejectedValue(new Error('Request failed with status code 500'))

		await expect(loadAttentionFeed()).rejects.toThrow('500')
	})
})
