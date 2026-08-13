/**
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared runtime GraphQL client (REQ-SAW-004).
 *
 * launchpad widgets that surface cross-app data (spend analytics, meeting
 * calendar, compliance, …) consume sibling-app data live at render time.
 * Per `feedback_launchpad-no-or-dependency.md` + ADR-022, launchpad MUST
 * NOT acquire an install-time dependency on a sibling app, and per
 * REQ-SAW-004 the renderer source files MUST NOT issue `axios` / `fetch`
 * calls at sibling-app paths directly — every cross-app read routes
 * through this single chokepoint.
 *
 * This module is the *only* place that knows how to talk to a sibling
 * app's OR-mounted `/graphql` endpoint. It:
 *  - resolves the app id to its Nextcloud route (`/apps/<app>/graphql`),
 *  - issues a read-only `POST` with `{query, variables}`,
 *  - normalises a 404 (sibling app not installed) into a typed
 *    `GraphQLSourceError` with `code === 'not_installed'` so callers can
 *    render the empty-state contract (REQ-SAW-005) instead of a thrown
 *    500,
 *  - applies a 5 s timeout (non-functional perf requirement); on timeout
 *    it throws `code === 'timeout'` so the caller renders a partial /
 *    empty state, never a red error,
 *  - never caches: freshness is the sibling app's responsibility
 *    (REQ-SAW-004).
 *
 * Keep this module dependency-light — only `@nextcloud/axios` and
 * `@nextcloud/router`, mirroring `resourceService.js`. No store, no
 * Pinia, no app-level state.
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

/** Default per-operation timeout in milliseconds (non-functional perf req). */
export const DEFAULT_TIMEOUT_MS = 5000

/**
 * Typed error for a cross-app GraphQL read. Carries a stable machine
 * `code` so the widget renderer can branch on it without parsing a
 * human message — `not_installed` → empty-state naming the source,
 * `timeout` → partial/empty state, `graphql_errors` → the source
 * returned `errors[]`, `network_error` → transport failure.
 */
export class GraphQLSourceError extends Error {
	/**
	 * @param {string} code   Stable error enum.
	 * @param {string} app    The sibling app id the call targeted.
	 * @param {string} message Human-readable display message.
	 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
	 */
	constructor(code, app, message) {
		super(message)
		this.name = 'GraphQLSourceError'
		this.code = code
		this.app = app
	}
}

/**
 * Resolve a sibling app id to its OR-mounted GraphQL endpoint URL.
 *
 * @param {string} app the sibling app id (e.g. `financeq`, `procest`)
 * @return {string} the absolute Nextcloud route for the app's `/graphql`
 */
function resolveGraphqlUrl(app) {
	return generateUrl('/apps/{app}/graphql', { app })
}

/**
 * Issue a read-only GraphQL operation against a sibling app's
 * OR-mounted endpoint. Resolves to the `data` payload, or throws a
 * {@see GraphQLSourceError} the renderer can map to an empty-state.
 *
 * @param {object}  params              call parameters
 * @param {string}  params.app          sibling app id (e.g. `financeq`)
 * @param {string}  params.query        the GraphQL query document
 * @param {object} [params.variables]   query variables
 * @param {number} [params.timeoutMs]   per-operation timeout
 * @return {Promise<object>} the GraphQL `data` object
 * @spec openspec/specs/launchpad-spend-analytics-widget/spec.md
 */
export async function queryGraphql({
	app,
	query,
	variables = {},
	timeoutMs = DEFAULT_TIMEOUT_MS,
}) {
	if (typeof app !== 'string' || app.trim() === '') {
		throw new GraphQLSourceError(
			'invalid_app',
			String(app),
			'A sibling app id is required',
		)
	}

	const url = resolveGraphqlUrl(app)

	let response
	try {
		response = await axios.post(
			url,
			{ query, variables },
			{ timeout: timeoutMs },
		)
	} catch (err) {
		// A 404 means the sibling app's GraphQL route is absent — i.e. the
		// app is not installed. Map it to the empty-state contract code.
		const status = err?.response?.status
		if (status === 404) {
			throw new GraphQLSourceError(
				'not_installed',
				app,
				`${app} is not installed`,
			)
		}
		// axios surfaces request timeouts as ECONNABORTED.
		if (err?.code === 'ECONNABORTED') {
			throw new GraphQLSourceError(
				'timeout',
				app,
				`${app} did not respond in time`,
			)
		}
		throw new GraphQLSourceError(
			'network_error',
			app,
			err?.message || 'Network error',
		)
	}

	const body = response?.data
	if (body && Array.isArray(body.errors) && body.errors.length > 0) {
		const first = body.errors[0]?.message || 'GraphQL error'
		throw new GraphQLSourceError('graphql_errors', app, first)
	}

	return body?.data ?? {}
}
