/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin embed API (embedded-analytics): embed tokens, tenant themes,
 * usage reports and the telemetry switch.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
 * @spec openspec/changes/embedded-analytics/tasks.md#task-30
 */

import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const baseUrl = generateUrl('/apps/launchpad')

const tokenUrl = (id = '') => `${baseUrl}/api/embed-tokens${id === '' ? '' : `/${encodeURIComponent(id)}`}`
const themeUrl = (id = '') => `${baseUrl}/api/tenant-themes${id === '' ? '' : `/${encodeURIComponent(id)}`}`

export const embedApi = {
	/**
	 * Whether embed usage telemetry is collected.
	 *
	 * @return {Promise<boolean>}
	 */
	async telemetryEnabled() {
		const { data } = await axios.get(`${baseUrl}/api/embed-settings/telemetry`)
		return data?.enabled !== false
	},

	/**
	 * Turn embed usage telemetry on or off; resolves with the stored state.
	 *
	 * @param {boolean} enabled Whether to collect.
	 * @return {Promise<boolean>}
	 */
	async setTelemetryEnabled(enabled) {
		const { data } = await axios.put(`${baseUrl}/api/embed-settings/telemetry`, { enabled })
		return data?.enabled !== false
	},

	/**
	 * Every embed token's metadata (never a JWT).
	 *
	 * @return {Promise<Array<object>>}
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
	 */
	async listTokens() {
		const { data } = await axios.get(tokenUrl())
		return Array.isArray(data?.results) ? data.results : []
	},

	/**
	 * Issue a token; the answer holds the JWT, shown this once.
	 *
	 * @param {object} token {name, description, subject, scope, hostOrigins, tenantThemeId, tokenExpiresAt, rateLimitPolicy}.
	 * @return {Promise<object>} {id, name, jwt, warning, createdAt}.
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
	 */
	async createToken(token) {
		const { data } = await axios.post(tokenUrl(), token)
		return data
	},

	/**
	 * Update a token's name, description or rate limit.
	 *
	 * @param {string} id The token id.
	 * @param {object} changes {name, description, rateLimitPolicy}.
	 * @return {Promise<object>} The token's metadata.
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
	 */
	async updateToken(id, changes) {
		const { data } = await axios.put(tokenUrl(id), changes)
		return data
	},

	/**
	 * Revoke a token.
	 *
	 * @param {string} id The token id.
	 * @param {string} reason One of the revocation reasons.
	 * @param {string} notes Optional notes.
	 * @return {Promise<object>} The token's metadata.
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-27
	 */
	async revokeToken(id, reason, notes = '') {
		const { data } = await axios.post(`${tokenUrl(id)}/revoke`, { reason, notes: notes === '' ? null : notes })
		return data
	},

	/**
	 * Clear a token's revocation.
	 *
	 * @param {string} id The token id.
	 * @return {Promise<object>} The token's metadata.
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-27
	 */
	async unrevokeToken(id) {
		const { data } = await axios.post(`${tokenUrl(id)}/unrevoke`)
		return data
	},

	/**
	 * Re-issue a token: the old one is revoked, the new JWT is shown once.
	 *
	 * @param {string} id The token id.
	 * @return {Promise<object>} {id, name, jwt, warning, createdAt}.
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
	 */
	async reissueToken(id) {
		const { data } = await axios.post(`${tokenUrl(id)}/re-issue`)
		return data
	},

	/**
	 * A token's usage over the last seven days, in aggregate.
	 *
	 * @param {string} id The token id.
	 * @return {Promise<object>}
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-17
	 */
	async tokenUsage(id) {
		const { data } = await axios.get(`${tokenUrl(id)}/usage`)
		return data
	},

	/**
	 * Every tenant theme, with how many tokens use it.
	 *
	 * @return {Promise<Array<object>>}
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
	 */
	async listThemes() {
		const { data } = await axios.get(themeUrl())
		return Array.isArray(data?.results) ? data.results : []
	},

	/**
	 * Create a tenant theme.
	 *
	 * @param {object} theme {name, cssVariables, logoUrl, customCss}.
	 * @return {Promise<object>} The stored theme.
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
	 */
	async createTheme(theme) {
		const { data } = await axios.post(themeUrl(), theme)
		return data
	},

	/**
	 * Replace a tenant theme.
	 *
	 * @param {string} id The theme id.
	 * @param {object} theme {name, cssVariables, logoUrl, customCss}.
	 * @return {Promise<object>} The stored theme.
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
	 */
	async updateTheme(id, theme) {
		const { data } = await axios.put(themeUrl(id), theme)
		return data
	},

	/**
	 * Delete a tenant theme; one that tokens use only with force.
	 *
	 * @param {string} id The theme id.
	 * @param {boolean} force Delete even when tokens use it.
	 * @return {Promise<object>} {deleted, tokenCount}.
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-16
	 */
	async deleteTheme(id, force = false) {
		const { data } = await axios.delete(themeUrl(id), { params: force ? { force: true } : {} })
		return data
	},
}
