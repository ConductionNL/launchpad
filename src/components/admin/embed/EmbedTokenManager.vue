<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section class="embed-token-manager" data-test="embed-token-manager">
		<h3>{{ t('launchpad', 'Embed tokens') }}</h3>

		<NcNoteCard v-if="issued" type="warning" data-test="embed-token-issued">
			<p>{{ t('launchpad', 'Copy this token now. It is shown only once and cannot be retrieved later; keep it safe.') }}</p>
			<code class="embed-token-manager__jwt" data-test="embed-token-jwt">{{ issued.jwt }}</code>
			<div class="embed-token-manager__row">
				<NcButton data-test="embed-token-copy" @click="copyJwt">
					{{ copied ? t('launchpad', 'Copied') : t('launchpad', 'Copy') }}
				</NcButton>
				<NcButton variant="tertiary" data-test="embed-token-issued-done" @click="issued = null">
					{{ t('launchpad', 'Done') }}
				</NcButton>
			</div>
		</NcNoteCard>

		<NcNoteCard v-if="error !== ''" type="error" data-test="embed-token-error">
			{{ error }}
		</NcNoteCard>

		<EmbedTokenForm
			v-if="view === 'create' || view === 'edit'"
			:key="view + (selected ? selected.id : '')"
			:token="view === 'edit' ? selected : null"
			:dashboards="dashboards"
			:themes="themes"
			:defaults="defaults"
			:saving="saving"
			@submit="save"
			@cancel="view = selected ? 'detail' : 'list'" />

		<div v-else-if="view === 'detail' && selected" class="embed-token-manager__detail" data-test="embed-token-detail">
			<NcButton variant="tertiary" data-test="embed-token-back" @click="backToList">
				{{ t('launchpad', 'Back to all tokens') }}
			</NcButton>
			<h4>{{ selected.name }}</h4>
			<dl class="embed-token-manager__facts">
				<dt>{{ t('launchpad', 'Status') }}</dt>
				<dd :data-test="'embed-token-status-' + statusOf(selected)">
					{{ statusLabel(selected) }}
				</dd>
				<dt>{{ t('launchpad', 'Embeds') }}</dt>
				<dd>{{ subjectLabel(selected) }}</dd>
				<dt>{{ t('launchpad', 'Scope') }}</dt>
				<dd>{{ scopeLabel(selected) }}</dd>
				<dt>{{ t('launchpad', 'Sites') }}</dt>
				<dd>{{ (selected.hostOrigins || []).join(', ') }}</dd>
				<dt>{{ t('launchpad', 'Expires') }}</dt>
				<dd>{{ selected.tokenExpiresAt || t('launchpad', 'Never') }}</dd>
				<dt>{{ t('launchpad', 'Created') }}</dt>
				<dd>{{ selected.createdAt }}</dd>
				<template v-if="selected.revokedAt">
					<dt>{{ t('launchpad', 'Revoked') }}</dt>
					<dd>{{ selected.revokedAt }} ({{ selected.revocationReason }})</dd>
				</template>
			</dl>
			<div class="embed-token-manager__row">
				<NcButton data-test="embed-token-edit" @click="view = 'edit'">
					{{ t('launchpad', 'Edit') }}
				</NcButton>
				<NcButton data-test="embed-token-reissue" :disabled="saving" @click="reissue(selected)">
					{{ t('launchpad', 'Re-issue') }}
				</NcButton>
				<NcButton
					v-if="!selected.revokedAt"
					variant="error"
					data-test="embed-token-revoke"
					@click="revoking = selected">
					{{ t('launchpad', 'Revoke') }}
				</NcButton>
				<NcButton
					v-else
					data-test="embed-token-unrevoke"
					:disabled="saving"
					@click="unrevoke(selected)">
					{{ t('launchpad', 'Restore') }}
				</NcButton>
			</div>
			<EmbedUsageReport :key="'usage-' + selected.id" :tokenId="selected.id" :tokenName="selected.name" />
		</div>

		<template v-else>
			<div class="embed-token-manager__row">
				<NcButton variant="primary" data-test="embed-token-new" @click="startCreate">
					{{ t('launchpad', 'New embed token') }}
				</NcButton>
			</div>
			<NcLoadingIcon v-if="loading" />
			<p v-else-if="tokens.length === 0" class="embed-token-manager__empty" data-test="embed-token-empty">
				{{ t('launchpad', 'No embed tokens yet.') }}
			</p>
			<table v-else class="embed-token-manager__table" data-test="embed-token-table">
				<thead>
					<tr>
						<th scope="col">{{ t('launchpad', 'Name') }}</th>
						<th scope="col">{{ t('launchpad', 'Status') }}</th>
						<th scope="col">{{ t('launchpad', 'Embeds') }}</th>
						<th scope="col">{{ t('launchpad', 'Created') }}</th>
						<th scope="col">{{ t('launchpad', 'Revoked') }}</th>
						<th scope="col"><span class="hidden-visually">{{ t('launchpad', 'Actions') }}</span></th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="token in tokens" :key="token.id" :data-test="'embed-token-row-' + token.id">
						<td>{{ token.name }}</td>
						<td>
							<span :class="'embed-token-manager__status--' + statusOf(token)">{{ statusLabel(token) }}</span>
						</td>
						<td>{{ subjectLabel(token) }}</td>
						<td>{{ token.createdAt }}</td>
						<td>{{ token.revokedAt || '' }}</td>
						<td>
							<NcButton
								variant="tertiary"
								:aria-label="t('launchpad', 'Open {name}', { name: token.name })"
								:data-test="'embed-token-open-' + token.id"
								@click="open(token)">
								{{ t('launchpad', 'Open') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
		</template>

		<RevokeEmbedTokenDialog
			v-if="revoking"
			:open="true"
			:name="revoking.name"
			@update:open="revoking = $event ? revoking : null"
			@confirm="revoke" />
	</section>
</template>

<script>
import { NcButton, NcLoadingIcon, NcNoteCard } from '@conduction/nextcloud-vue'
import { showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import RevokeEmbedTokenDialog from '../../../dialogs/RevokeEmbedTokenDialog.vue'
import EmbedTokenForm from './EmbedTokenForm.vue'
import EmbedUsageReport from './EmbedUsageReport.vue'
import { api } from '../../../services/api.js'
import { embedApi } from '../../../services/embedApi.js'
import { tokenStatus } from '../../../utils/embedToken.js'
import { logger } from '../../../utils/logger.js'

/**
 * EmbedTokenManager — Beheer ▸ Embeds: every embed token, issuing one (the
 * JWT shown once with a copy button), its details with edit, re-issue,
 * revoke and restore, and its usage report (embedded-analytics tasks 15 and
 * 27). Opened from a dashboard or widget with `defaults`, it starts on the
 * create form for that subject (task 40).
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
 * @spec openspec/changes/embedded-analytics/tasks.md#task-27
 */
export default {
	name: 'EmbedTokenManager',

	components: {
		EmbedTokenForm,
		EmbedUsageReport,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		RevokeEmbedTokenDialog,
	},

	props: {
		/** Subject to pre-select and open the create form for: {subjectType, subjectId, dashboardId}. */
		defaults: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			tokens: [],
			dashboards: [],
			themes: [],
			loading: true,
			saving: false,
			view: this.defaults?.subjectId ? 'create' : 'list',
			selected: null,
			issued: null,
			copied: false,
			revoking: null,
			error: '',
		}
	},

	/** @spec openspec/changes/embedded-analytics/tasks.md#task-15 */
	created() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Load the tokens, and the dashboards and themes the form offers.
		 *
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
		 */
		async load() {
			this.loading = true
			this.error = ''
			const [tokens, dashboards, themes] = await Promise.allSettled([
				embedApi.listTokens(),
				api.getVisibleDashboards(),
				embedApi.listThemes(),
			])
			if (tokens.status === 'fulfilled') {
				this.tokens = tokens.value
			} else {
				this.error = t('launchpad', 'Embed tokens could not be loaded.')
				logger.error('Failed to load embed tokens:', tokens.reason)
			}
			const dashboardList = dashboards.status === 'fulfilled' ? dashboards.value?.data : null
			this.dashboards = (Array.isArray(dashboardList) ? dashboardList : (dashboardList?.items ?? dashboardList?.results ?? []))
				.map((dashboard) => ({ id: dashboard.id, uuid: dashboard.uuid, name: dashboard.name }))
			this.themes = themes.status === 'fulfilled' ? themes.value.map((theme) => ({ id: theme.id, name: theme.name })) : []
			this.loading = false
		},

		/**
		 * Open the create form.
		 *
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		startCreate() {
			this.selected = null
			this.view = 'create'
		},

		/**
		 * Show a token's details.
		 *
		 * @param {object} token The token.
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		open(token) {
			this.selected = token
			this.view = 'detail'
		},

		/**
		 * Back to the list.
		 *
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		backToList() {
			this.selected = null
			this.view = 'list'
		},

		/**
		 * Create or update a token from the form.
		 *
		 * @param {object} payload The form's payload.
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
		 */
		async save(payload) {
			this.saving = true
			this.error = ''
			try {
				if (this.view === 'edit') {
					this.selected = await embedApi.updateToken(this.selected.id, payload)
					this.replace(this.selected)
					this.view = 'detail'
				} else {
					this.showIssued(await embedApi.createToken(payload))
					this.tokens = await embedApi.listTokens()
					this.view = 'list'
				}
			} catch (error) {
				this.error = this.messageOf(error, t('launchpad', 'The embed token could not be saved.'))
				logger.error('Failed to save embed token:', error)
			} finally {
				this.saving = false
			}
		},

		/**
		 * Re-issue a token: the old one is revoked, the new JWT shown once.
		 *
		 * @param {object} token The token.
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-15
		 */
		async reissue(token) {
			this.saving = true
			this.error = ''
			try {
				this.showIssued(await embedApi.reissueToken(token.id))
				this.tokens = await embedApi.listTokens()
				this.backToList()
			} catch (error) {
				this.error = this.messageOf(error, t('launchpad', 'The embed token could not be re-issued.'))
				logger.error('Failed to re-issue embed token:', error)
			} finally {
				this.saving = false
			}
		},

		/**
		 * Revoke the token in the dialog.
		 *
		 * @param {{reason: string, notes: string}} choice The reason and notes.
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-27
		 */
		async revoke({ reason, notes }) {
			const token = this.revoking
			this.revoking = null
			this.error = ''
			try {
				const updated = await embedApi.revokeToken(token.id, reason, notes)
				this.replace(updated)
				if (this.selected?.id === updated.id) {
					this.selected = updated
				}
				showSuccess(t('launchpad', 'Token revoked.'))
			} catch (error) {
				this.error = this.messageOf(error, t('launchpad', 'The embed token could not be revoked.'))
				logger.error('Failed to revoke embed token:', error)
			}
		},

		/**
		 * Clear a token's revocation.
		 *
		 * @param {object} token The token.
		 * @spec openspec/changes/embedded-analytics/tasks.md#task-27
		 */
		async unrevoke(token) {
			this.saving = true
			this.error = ''
			try {
				const updated = await embedApi.unrevokeToken(token.id)
				this.replace(updated)
				this.selected = updated
				showSuccess(t('launchpad', 'Token restored.'))
			} catch (error) {
				this.error = this.messageOf(error, t('launchpad', 'The embed token could not be restored.'))
				logger.error('Failed to restore embed token:', error)
			} finally {
				this.saving = false
			}
		},

		/**
		 * Show a freshly issued JWT.
		 *
		 * @param {object} result {jwt, ...}.
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		showIssued(result) {
			this.issued = result
			this.copied = false
		},

		/**
		 * Copy the issued JWT to the clipboard.
		 *
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		async copyJwt() {
			try {
				await navigator.clipboard.writeText(this.issued.jwt)
				this.copied = true
			} catch (error) {
				logger.error('Failed to copy the embed token:', error)
			}
		},

		/**
		 * Put an updated token in the list.
		 *
		 * @param {object} token The token's metadata.
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		replace(token) {
			this.tokens = this.tokens.map((item) => (item.id === token.id ? token : item))
		},

		/**
		 * The server's message of a refusal, or a fallback.
		 *
		 * @param {Error} error The failure.
		 * @param {string} fallback The fallback message.
		 * @return {string}
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		messageOf(error, fallback) {
			return error?.response?.data?.message || fallback
		},

		/**
		 * @param {object} token The token.
		 * @return {string}
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		statusOf(token) {
			return tokenStatus(token)
		},

		/**
		 * @param {object} token The token.
		 * @return {string}
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		statusLabel(token) {
			return {
				active: t('launchpad', 'Active'),
				expired: t('launchpad', 'Expired'),
				revoked: t('launchpad', 'Revoked'),
			}[tokenStatus(token)]
		},

		/**
		 * @param {object} token The token.
		 * @return {string}
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		subjectLabel(token) {
			const subject = token.subject ?? {}
			if (subject.type === 'widget') {
				return t('launchpad', 'Widget {id}', { id: subject.id })
			}
			const dashboard = this.dashboards.find((item) => item.uuid === subject.id)
			return t('launchpad', 'Dashboard {name}', { name: dashboard?.name ?? subject.id })
		},

		/**
		 * @param {object} token The token.
		 * @return {string}
		 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
		 */
		scopeLabel(token) {
			if (token.scope?.mode !== 'read-with-interactions') {
				return t('launchpad', 'Read only')
			}
			const actions = (token.scope.allowedActions ?? []).join(', ')
			return actions === '' ? t('launchpad', 'Read with interactions') : t('launchpad', 'Read with interactions: {actions}', { actions })
		},
	},
}
</script>

<style scoped>
.embed-token-manager {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.embed-token-manager__row {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.embed-token-manager__jwt {
	display: block;
	margin: 8px 0;
	word-break: break-all;
	user-select: all;
}

.embed-token-manager__table {
	width: 100%;
	border-collapse: collapse;
}

.embed-token-manager__table th,
.embed-token-manager__table td {
	text-align: start;
	padding: 6px 8px;
	border-bottom: 1px solid var(--color-border);
}

.embed-token-manager__status--active {
	color: var(--color-success-text);
}

.embed-token-manager__status--expired {
	color: var(--color-warning-text);
}

.embed-token-manager__status--revoked {
	color: var(--color-error-text);
}

.embed-token-manager__facts {
	display: grid;
	grid-template-columns: max-content 1fr;
	gap: 4px 16px;
}

.embed-token-manager__facts dt {
	font-weight: bold;
}

.embed-token-manager__empty {
	color: var(--color-text-maxcontrast);
}
</style>
