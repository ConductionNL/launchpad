<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="sharing-embed" data-test="sharing-embed">
		<h3 class="sharing-embed__title">
			{{ t('launchpad', 'Embed on your website') }}
		</h3>
		<p class="sharing-embed__hint">
			{{ t('launchpad', 'Show this dashboard, or one of its widgets, on another website with an embed token.') }}
		</p>

		<NcNoteCard v-if="issued" type="warning" data-test="sharing-embed-issued">
			<p>
				{{ t('launchpad', 'Token {name} has been created. Copy it now: it is not shown again.', { name: issued.name }) }}
			</p>
			<code class="sharing-embed__jwt" data-test="sharing-embed-jwt">{{ issued.jwt }}</code>
			<div class="sharing-embed__row">
				<NcButton data-test="sharing-embed-copy" @click="copy(issued.jwt, 'jwt')">
					{{ copied === 'jwt' ? t('launchpad', 'Copied') : t('launchpad', 'Copy') }}
				</NcButton>
				<NcButton variant="tertiary" data-test="sharing-embed-issued-done" @click="issued = null">
					{{ t('launchpad', 'Done') }}
				</NcButton>
			</div>
		</NcNoteCard>

		<!-- Withdraw asks for the reason, as the admin Embeds tab does. -->
		<RevokeEmbedTokenDialog
			v-if="revoking !== null"
			open
			:name="revoking.name"
			@confirm="revoke"
			@update:open="closeRevoke" />

		<NcNoteCard v-if="error !== ''" type="error" data-test="sharing-embed-error">
			{{ error }}
		</NcNoteCard>

		<EmbedTokenForm
			v-if="creating"
			:dashboards="[dashboardOption]"
			:themes="[]"
			:defaults="formDefaults"
			:saving="saving"
			@submit="create"
			@cancel="creating = false" />

		<template v-else>
			<NcLoadingIcon v-if="loading" />
			<p v-else-if="tokens.length === 0" class="sharing-embed__hint" data-test="sharing-embed-empty">
				{{ t('launchpad', 'No embed tokens for this dashboard yet.') }}
			</p>
			<table v-else class="sharing-embed__table" data-test="sharing-embed-table">
				<thead>
					<tr>
						<th scope="col">{{ t('launchpad', 'Name') }}</th>
						<th scope="col">{{ t('launchpad', 'Shows') }}</th>
						<th scope="col">{{ t('launchpad', 'Allowed website') }}</th>
						<th scope="col">{{ t('launchpad', 'State') }}</th>
						<th scope="col"><span class="hidden-visually">{{ t('launchpad', 'Actions') }}</span></th>
					</tr>
				</thead>
				<tbody>
					<tr
						v-for="token in tokens"
						:key="token.id"
						:class="{ 'sharing-embed__selected': token.id === selectedId }"
						:data-test="'sharing-embed-row-' + token.id"
						@click="selectedId = token.id">
						<td>{{ token.name }}</td>
						<td>{{ subjectLabel(token) }}</td>
						<td>{{ (token.hostOrigins || []).join(', ') }}</td>
						<td :data-test="'sharing-embed-state-' + token.id">
							{{ stateLabel(token) }}
						</td>
						<td>
							<NcButton
								v-if="!token.revokedAt"
								variant="tertiary"
								:aria-label="t('launchpad', 'Withdraw {name}', { name: token.name })"
								:data-test="'sharing-embed-withdraw-' + token.id"
								@click.stop="revoking = token">
								{{ t('launchpad', 'Withdraw') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>

			<div v-if="selected" class="sharing-embed__code" data-test="sharing-embed-code">
				<label class="sharing-embed__label" for="sharing-embed-code">
					{{ t('launchpad', 'Code for the website') }}
				</label>
				<textarea
					id="sharing-embed-code"
					readonly
					rows="3"
					:value="code"
					@focus="$event.target.select()" />
				<div class="sharing-embed__row">
					<NcButton variant="tertiary" data-test="sharing-embed-copy-code" @click="copy(code, 'code')">
						{{ copied === 'code' ? t('launchpad', 'Copied') : t('launchpad', 'Copy code') }}
					</NcButton>
				</div>
			</div>

			<div class="sharing-embed__row">
				<NcButton variant="secondary" data-test="sharing-embed-new" @click="creating = true">
					{{ t('launchpad', 'Create new embed token') }}
				</NcButton>
			</div>
		</template>

	</div>
</template>

<script>
import { NcButton, NcLoadingIcon, NcNoteCard } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import RevokeEmbedTokenDialog from '../../../dialogs/RevokeEmbedTokenDialog.vue'
import EmbedTokenForm from '../../admin/embed/EmbedTokenForm.vue'
import { NULLABLE_OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { api } from '../../../services/api.js'
import { embedApi, embedLoadError } from '../../../services/embedApi.js'
import { embedCode, tokensForDashboard, tokenStatus } from '../../../utils/embedToken.js'
import { logger } from '../../../utils/logger.js'

/**
 * SharingEmbedTokens — the "Embed on your website" part of a dashboard's
 * Sharing tab (REQ-EMB-011): the dashboard's embed tokens with what they
 * show, the allowed website, their state and Withdraw; the code for the
 * website of the selected token; and a new token, shown once. Withdrawing
 * uses the token revocation, which every render worker honours within 60
 * seconds (REQ-EMB-009).
 *
 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
 */
export default {
	name: 'SharingEmbedTokens',

	components: {
		EmbedTokenForm,
		NcButton,
		NcLoadingIcon,
		NcNoteCard,
		RevokeEmbedTokenDialog,
	},

	props: {
		/** The dashboard: {id, uuid, name}. */
		dashboard: NULLABLE_OBJECT_PROP,
	},

	data() {
		return {
			allTokens: [],
			placements: [],
			loading: true,
			saving: false,
			creating: false,
			selectedId: null,
			issued: null,
			copied: '',
			revoking: null,
			error: '',
		}
	},

	computed: {
		/**
		 * The tokens of this dashboard and its widgets.
		 *
		 * @return {Array<object>}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		tokens() {
			return tokensForDashboard(this.allTokens, this.dashboard?.uuid, this.placements.map((placement) => placement.id))
		},

		/**
		 * The token whose code is shown.
		 *
		 * @return {object|null}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		selected() {
			return this.tokens.find((token) => token.id === this.selectedId) ?? null
		},

		/**
		 * The code for the website of the selected token, with the JWT while it is at hand.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		code() {
			const jwt = this.issued?.id === this.selected?.id ? this.issued?.jwt : ''
			return embedCode(this.selected, this.appUrl(), jwt)
		},

		/**
		 * This dashboard as the one choice of the token form.
		 *
		 * @return {object}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		dashboardOption() {
			return { id: this.dashboard?.id, uuid: this.dashboard?.uuid, name: this.dashboard?.name }
		},

		/**
		 * A new token starts on this dashboard.
		 *
		 * @return {object}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		formDefaults() {
			return { subjectType: 'dashboard', subjectId: this.dashboard?.uuid ?? '', dashboardId: this.dashboard?.id ?? null }
		},
	},

	watch: {
		'dashboard.id': {
			immediate: true,
			/**
			 * Load the tokens of the dashboard that opened.
			 *
			 * @return {void}
			 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
			 */
			handler() {
				this.load()
			},
		},
	},

	methods: {
		t,

		/**
		 * Load every token and the dashboard's widget placements.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		async load() {
			if (!this.dashboard?.id) {
				this.loading = false
				return
			}
			this.loading = true
			this.error = ''
			const [tokens, detail] = await Promise.allSettled([
				embedApi.listTokens(),
				api.getDashboardById(this.dashboard.id),
			])
			if (tokens.status === 'fulfilled') {
				this.allTokens = tokens.value
			} else {
				this.allTokens = []
				this.error = embedLoadError(tokens.reason)
			}
			const placements = detail.status === 'fulfilled' ? detail.value?.data?.placements : null
			this.placements = Array.isArray(placements) ? placements : []
			this.loading = false
		},

		/**
		 * Issue a token from the form; its JWT and code are shown once.
		 *
		 * @param {object} payload The form's payload.
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		async create(payload) {
			this.saving = true
			this.error = ''
			try {
				const result = await embedApi.createToken(payload)
				this.issued = result
				this.copied = ''
				this.allTokens = await embedApi.listTokens()
				this.selectedId = result?.id ?? null
				this.creating = false
			} catch (error) {
				this.error = error?.response?.data?.message || t('launchpad', 'The embed token could not be saved.')
				logger.error('Failed to create embed token:', error)
			} finally {
				this.saving = false
			}
		},

		/**
		 * Withdraw the token in the dialog.
		 *
		 * @param {{reason: string, notes: string}} choice The reason and notes.
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		async revoke({ reason, notes }) {
			const token = this.revoking
			this.revoking = null
			this.error = ''
			try {
				const updated = await embedApi.revokeToken(token.id, reason, notes)
				this.allTokens = this.allTokens.map((item) => (item.id === updated.id ? updated : item))
			} catch (error) {
				this.error = error?.response?.data?.message || t('launchpad', 'The embed token could not be revoked.')
				logger.error('Failed to revoke embed token:', error)
			}
		},

		/**
		 * Close the Withdraw dialog without withdrawing.
		 *
		 * @param {boolean} open Whether the dialog stays open.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		closeRevoke(open) {
			if (!open) {
				this.revoking = null
			}
		},

		/**
		 * Copy a text to the clipboard.
		 *
		 * @param {string} text The text.
		 * @param {string} what Which copy button was used.
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		async copy(text, what) {
			try {
				await navigator.clipboard.writeText(text)
				this.copied = what
			} catch (error) {
				logger.error('Failed to copy:', error)
			}
		},

		/**
		 * The absolute address of the app.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		appUrl() {
			const origin = typeof window === 'undefined' ? '' : window.location.origin
			return origin + generateUrl('/apps/launchpad')
		},

		/**
		 * What a token shows: the whole dashboard or one widget.
		 *
		 * @param {object} token The token.
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		subjectLabel(token) {
			if (token?.subject?.type !== 'widget') {
				return t('launchpad', 'The whole dashboard')
			}
			const placement = this.placements.find((item) => String(item.id) === String(token.subject.id))
			const name = placement?.customTitle || placement?.tileTitle || placement?.widgetId || token.subject.id
			return t('launchpad', 'Widget {name}', { name })
		},

		/**
		 * A token's state: Active, Revoked with the date and reason, or Expired.
		 *
		 * @param {object} token The token.
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/embedded-analytics/spec.md#requirement-embed-tokens-on-the-sharing-tab-req-emb-011
		 */
		stateLabel(token) {
			const status = tokenStatus(token)
			if (status === 'revoked') {
				return t('launchpad', 'Revoked on {date} ({reason})', {
					date: String(token.revokedAt).slice(0, 10),
					reason: token.revocationReason || '',
				})
			}
			if (status === 'expired') {
				return t('launchpad', 'Expired')
			}
			return t('launchpad', 'Active')
		},
	},
}
</script>

<style scoped>
.sharing-embed {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 16px;
}

.sharing-embed__title {
	margin: 0;
	font-size: 1em;
	font-weight: bold;
}

.sharing-embed__hint {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.sharing-embed__row {
	display: flex;
	gap: 8px;
	flex-wrap: wrap;
}

.sharing-embed__jwt {
	display: block;
	overflow-wrap: anywhere;
	font-family: monospace;
}

.sharing-embed__table {
	width: 100%;
	border-collapse: collapse;
}

.sharing-embed__table th,
.sharing-embed__table td {
	padding: 4px 8px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}

.sharing-embed__table tbody tr {
	cursor: pointer;
}

.sharing-embed__selected {
	background: var(--color-primary-element-light);
}

.sharing-embed__code textarea {
	width: 100%;
	font-family: monospace;
}

.sharing-embed__label {
	font-weight: bold;
}
</style>
