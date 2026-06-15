/**
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Vitest unit tests for the shared GraphQL client (REQ-SAW-004,
 * REQ-SAW-005). Verifies the single cross-app read chokepoint:
 * targets `/apps/<app>/graphql`, passes variables, maps a 404 to a
 * typed `not_installed` error, maps a timeout to `timeout`, and
 * surfaces GraphQL `errors[]`.
 */

import { describe, it, expect, beforeEach, vi } from 'vitest'
import axios from '@nextcloud/axios'
import { queryGraphql, GraphQLSourceError } from '../graphqlClient.js'

vi.mock('@nextcloud/axios', () => ({
	default: { post: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) => Object.entries(params).reduce(
		(acc, [key, value]) => acc.replace(`{${key}}`, value),
		path,
	),
}))

describe('graphqlClient (REQ-SAW-004 / REQ-SAW-005)', () => {
	beforeEach(() => {
		vi.clearAllMocks()
	})

	it('POSTs to /apps/<app>/graphql with query + variables', async () => {
		axios.post.mockResolvedValue({ data: { data: { transactions: [] } } })
		await queryGraphql({ app: 'financeq', query: 'query X { x }', variables: { period: 'quarter' } })

		expect(axios.post).toHaveBeenCalledTimes(1)
		const [url, body] = axios.post.mock.calls[0]
		expect(url).toBe('/apps/financeq/graphql')
		expect(body.query).toBe('query X { x }')
		expect(body.variables).toEqual({ period: 'quarter' })
	})

	it('returns the GraphQL data payload on success', async () => {
		axios.post.mockResolvedValue({ data: { data: { transactions: [{ totalAmount: 5 }] } } })
		const data = await queryGraphql({ app: 'financeq', query: 'q' })
		expect(data).toEqual({ transactions: [{ totalAmount: 5 }] })
	})

	it('maps a 404 to a not_installed GraphQLSourceError', async () => {
		axios.post.mockRejectedValue({ response: { status: 404 } })
		await expect(queryGraphql({ app: 'procest', query: 'q' })).rejects.toMatchObject({
			name: 'GraphQLSourceError',
			code: 'not_installed',
			app: 'procest',
		})
	})

	it('maps an ECONNABORTED timeout to a timeout error', async () => {
		axios.post.mockRejectedValue({ code: 'ECONNABORTED' })
		await expect(queryGraphql({ app: 'financeq', query: 'q' })).rejects.toMatchObject({ code: 'timeout' })
	})

	it('surfaces GraphQL errors[] as a graphql_errors error', async () => {
		axios.post.mockResolvedValue({ data: { errors: [{ message: 'boom' }] } })
		await expect(queryGraphql({ app: 'financeq', query: 'q' })).rejects.toBeInstanceOf(GraphQLSourceError)
	})

	it('rejects an empty app id', async () => {
		await expect(queryGraphql({ app: '', query: 'q' })).rejects.toMatchObject({ code: 'invalid_app' })
	})
})
