/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pure helpers of the embed-token admin UI (embedded-analytics): a token's
 * status, the host-origin check and CSP preview the server applies, and the
 * create/update payloads built from the form.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-18
 */

/** The scope modes a token may have. */
export const SCOPE_MODES = ['read', 'read-with-interactions']

/** The actions a read-with-interactions scope may allow. */
export const SCOPE_ACTIONS = ['interact', 'export', 'drillDown']

/** The reasons an admin may revoke a token for (`re-issued` is set by a re-issue). */
export const REVOKE_REASONS = ['compromised', 'no_longer_needed', 'host_decommissioned', 'other']

const ORIGIN_PATTERN = /^https?:\/\/(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?::[0-9]{1,5})?$/i

/**
 * Normalise an origin the way the server does: trimmed, no trailing slash, lower case.
 *
 * @param {string} origin The origin.
 * @return {string}
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */
export function normaliseOrigin(origin) {
	return String(origin ?? '').trim().replace(/\/+$/, '').toLowerCase()
}

/**
 * Why one origin is refused, or '' when it is accepted (the server's rule:
 * scheme and host, an optional port, no path, no wildcard).
 *
 * @param {string} origin The origin.
 * @return {'' | 'wildcard' | 'invalid'}
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
 */
export function originProblem(origin) {
	const value = String(origin ?? '').trim()
	if (value.includes('*')) {
		return 'wildcard'
	}
	return ORIGIN_PATTERN.test(value.replace(/\/+$/, '')) ? '' : 'invalid'
}

/**
 * The CSP header value a token with these origins is served with.
 *
 * @param {Array<string>} origins The host origins.
 * @return {string}
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
 */
export function cspPreview(origins) {
	const valid = [...new Set((origins ?? []).filter((origin) => originProblem(origin) === '').map(normaliseOrigin))]
	return valid.length === 0 ? "frame-ancestors 'none'" : `frame-ancestors ${valid.join(' ')}`
}

/**
 * A token's status at a moment: revoked wins over expired.
 *
 * @param {object} token The token's metadata.
 * @param {number} now Milliseconds since the epoch.
 * @return {'revoked' | 'expired' | 'active'}
 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
 */
export function tokenStatus(token, now = Date.now()) {
	if (token?.revokedAt) {
		return 'revoked'
	}
	const expires = token?.tokenExpiresAt ? Date.parse(token.tokenExpiresAt) : NaN
	return !Number.isNaN(expires) && expires <= now ? 'expired' : 'active'
}

/**
 * Lines of `dimension=value` as an object; lines without `=` or a name are skipped.
 *
 * @param {string} text The lines.
 * @return {object}
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */
export function parseFilterContext(text) {
	const context = {}
	for (const line of String(text ?? '').split('\n')) {
		const at = line.indexOf('=')
		const name = at > 0 ? line.slice(0, at).trim() : ''
		if (name !== '') {
			context[name] = line.slice(at + 1).trim()
		}
	}
	return context
}

/**
 * An object as lines of `dimension=value`.
 *
 * @param {object} context The filter context.
 * @return {string}
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */
export function formatFilterContext(context) {
	return Object.entries(context ?? {}).map(([name, value]) => `${name}=${value}`).join('\n')
}

/**
 * A comma- or newline-separated list as trimmed, distinct, non-empty items.
 *
 * @param {string} text The list.
 * @return {Array<string>}
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */
export function splitList(text) {
	return [...new Set(String(text ?? '').split(/[\n,]/).map((item) => item.trim()).filter((item) => item !== ''))]
}

/**
 * The rate-limit policy of the form, or null to use the organisation default.
 *
 * @param {string|number} requestsPerMinute Requests per minute ('' for the default).
 * @param {string|number} burstSize Burst size ('' for the default).
 * @return {object|null}
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */
export function rateLimitPolicy(requestsPerMinute, burstSize) {
	if (requestsPerMinute === '' || requestsPerMinute === null || requestsPerMinute === undefined) {
		return null
	}
	const perMinute = Number(requestsPerMinute)
	const burst = burstSize === '' || burstSize === null || burstSize === undefined ? perMinute : Number(burstSize)
	return { requestsPerMinute: perMinute, burstSize: burst }
}

/**
 * The create payload of a filled-in form.
 *
 * @param {object} form The form state.
 * @return {object}
 * @spec openspec/changes/embedded-analytics/tasks.md#task-18
 */
export function createPayload(form) {
	const filterContext = parseFilterContext(form.filterContext)
	const interactive = form.mode === 'read-with-interactions'
	const payload = {
		name: String(form.name ?? '').trim(),
		description: String(form.description ?? '').trim(),
		subject: {
			type: form.subjectType,
			id: String(form.subjectId ?? ''),
			...(Object.keys(filterContext).length > 0 ? { filterContext } : {}),
		},
		scope: {
			mode: form.mode,
			allowedFilters: interactive ? splitList(form.allowedFilters) : [],
			allowedActions: interactive ? SCOPE_ACTIONS.filter((action) => (form.allowedActions ?? []).includes(action)) : [],
		},
		hostOrigins: (form.hostOrigins ?? []).map(normaliseOrigin).filter((origin) => origin !== ''),
		tenantThemeId: form.tenantThemeId || null,
		tokenExpiresAt: form.tokenExpiresAt ? new Date(form.tokenExpiresAt).toISOString().replace(/\.\d{3}Z$/, 'Z') : null,
		rateLimitPolicy: rateLimitPolicy(form.requestsPerMinute, form.burstSize),
	}
	return payload
}

/**
 * The update payload of a form: only what may change after issue.
 *
 * @param {object} form The form state.
 * @return {object}
 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
 */
export function updatePayload(form) {
	return {
		name: String(form.name ?? '').trim(),
		description: String(form.description ?? '').trim(),
		rateLimitPolicy: rateLimitPolicy(form.requestsPerMinute, form.burstSize),
	}
}

/**
 * The form state of a stored token (edit), or of a new one.
 *
 * @param {object|null} token The token's metadata.
 * @param {object} defaults {subjectType, subjectId} for a new token.
 * @return {object}
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */
export function formFromToken(token = null, defaults = {}) {
	const policy = token?.rateLimitPolicy ?? null
	return {
		name: token?.name ?? '',
		description: token?.description ?? '',
		subjectType: token?.subject?.type ?? defaults.subjectType ?? 'dashboard',
		subjectId: token?.subject?.id ?? defaults.subjectId ?? '',
		dashboardId: defaults.dashboardId ?? null,
		filterContext: formatFilterContext(token?.subject?.filterContext),
		mode: token?.scope?.mode ?? 'read',
		allowedActions: [...(token?.scope?.allowedActions ?? [])],
		allowedFilters: (token?.scope?.allowedFilters ?? []).join(', '),
		hostOrigins: [...(token?.hostOrigins ?? [])],
		tenantThemeId: token?.tenantThemeId ?? null,
		tokenExpiresAt: token?.tokenExpiresAt ? token.tokenExpiresAt.slice(0, 10) : '',
		requestsPerMinute: policy?.requestsPerMinute ?? '',
		burstSize: policy?.burstSize ?? '',
	}
}

/**
 * Whether custom CSS holds only :root rules (the server's rule, checked
 * early so the admin sees it while typing).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
 *
 * @param {string} css The custom CSS.
 * @return {boolean}
 */
export function onlyRootRules(css) {
	const withoutComments = String(css ?? '').replace(/\/\*[\s\S]*?\*\//g, '')
	const selectors = [...withoutComments.matchAll(/([^{}]*)\{/g)].map((match) => match[1].trim())
	return selectors.every((selector) => selector === ':root')
}

/**
 * The subject a link into Beheer ▸ Embeds asks a new token for:
 * `?tab=embeds&embedType=dashboard|widget&embedId=<uuid or placement id>&embedDashboard=<dashboard id>`.
 *
 * @param {string} search The address's query string.
 * @return {object} {subjectType, subjectId, dashboardId}, or {} without a valid subject.
 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
 */
export function embedDefaultsFrom(search) {
	const params = new URLSearchParams(search)
	const type = params.get('embedType')
	const id = params.get('embedId') ?? ''
	if (!['dashboard', 'widget'].includes(type) || id === '') {
		return {}
	}
	const dashboard = Number.parseInt(params.get('embedDashboard') ?? '', 10)
	return { subjectType: type, subjectId: id, dashboardId: Number.isNaN(dashboard) ? null : dashboard }
}

/**
 * The query string of a link into Beheer ▸ Embeds that pre-selects a subject;
 * the inverse of `embedDefaultsFrom`.
 *
 * @param {string} subjectType 'dashboard' or 'widget'.
 * @param {string|number} subjectId The dashboard uuid or the widget placement id.
 * @param {string|number|null} dashboardId The numeric id of the dashboard holding the subject.
 * @return {string} The query string without a leading '?', or '' without a valid subject.
 * @spec openspec/changes/embedded-analytics/tasks.md#task-40
 */
export function embedAdminSearch(subjectType, subjectId, dashboardId = null) {
	const id = String(subjectId ?? '')
	if (!['dashboard', 'widget'].includes(subjectType) || id === '') {
		return ''
	}
	return new URLSearchParams({
		tab: 'embeds',
		embedType: subjectType,
		embedId: id,
		embedDashboard: String(dashboardId ?? ''),
	}).toString()
}

/**
 * The embed tokens of one dashboard: tokens for the dashboard itself and
 * tokens for one of its widget placements (REQ-EMB-011).
 *
 * @param {Array<object>} tokens Every token's metadata.
 * @param {string} dashboardUuid The dashboard's uuid.
 * @param {Array<string|number>} placementIds The ids of the dashboard's widget placements.
 * @return {Array<object>}
 * @spec openspec/changes/sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
 */
export function tokensForDashboard(tokens, dashboardUuid, placementIds = []) {
	const placements = new Set((placementIds ?? []).map((id) => String(id)))
	return (tokens ?? []).filter((token) => {
		const type = token?.subject?.type
		const id = String(token?.subject?.id ?? '')
		if (type === 'dashboard') {
			return id !== '' && id === String(dashboardUuid ?? '')
		}
		return type === 'widget' && placements.has(id)
	})
}

/**
 * Escape a value for an HTML attribute.
 *
 * @param {string} value The value.
 * @return {string}
 * @spec openspec/changes/sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
 */
function attribute(value) {
	return String(value ?? '')
		.replace(/&/g, '&amp;')
		.replace(/"/g, '&quot;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
}

/**
 * The code a website pastes to show a token's subject: an iframe on the
 * public embed route. The JWT is filled in only right after issue; later
 * the placeholder `YOUR_TOKEN` stands in, since the token is shown once.
 *
 * @param {object} token The token's metadata ({name, subject: {type, id}}).
 * @param {string} appUrl The absolute address of the app (`https://host/index.php/apps/launchpad`).
 * @param {string} [jwt] The issued JWT, when it is still at hand.
 * @return {string}
 * @spec openspec/changes/sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
 */
export function embedCode(token, appUrl, jwt = '') {
	const type = token?.subject?.type === 'widget' ? 'widget' : 'dashboard'
	const id = encodeURIComponent(String(token?.subject?.id ?? ''))
	const src = `${String(appUrl ?? '').replace(/\/+$/, '')}/embed/${type}/${id}?token=${encodeURIComponent(jwt || 'YOUR_TOKEN')}`
	return `<iframe src="${attribute(src)}" title="${attribute(token?.name)}" width="100%" height="480" style="border:0" loading="lazy"></iframe>`
}
