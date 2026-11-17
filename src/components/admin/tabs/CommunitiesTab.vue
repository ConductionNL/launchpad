<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="communities-tab" data-testid="communities-tab">
		<h3>{{ t('launchpad', 'Pinned communities') }}</h3>
		<p class="communities-tab__intro">
			{{
				t(
					'launchpad',
					'The Communities widget lists the open Talk conversations each person may join. The conversations pinned here show first, marked as pinned by your organisation. Enter one Talk conversation token per line, at most 20. The token is the last part of the conversation link, after /call/.',
				)
			}}
		</p>

		<label class="communities-tab__label" for="launchpad-pinned-communities">
			{{ t('launchpad', 'Conversation tokens') }}
		</label>
		<textarea
			id="launchpad-pinned-communities"
			v-model="draft"
			class="communities-tab__tokens"
			rows="6"
			data-testid="communities-tokens"
			:aria-invalid="error !== '' ? 'true' : 'false'"
			aria-describedby="launchpad-pinned-communities-status" />

		<div class="communities-tab__actions">
			<NcButton
				variant="primary"
				:disabled="saving"
				data-testid="communities-save"
				@click="save">
				{{ t('launchpad', 'Save') }}
			</NcButton>
			<p
				id="launchpad-pinned-communities-status"
				class="communities-tab__status"
				:class="{ 'communities-tab__status--error': error !== '' }"
				role="status"
				aria-live="polite">
				{{ status }}
			</p>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import { api } from '../../../services/api.js'
import { logger } from '../../../utils/logger.js'

/** Most conversations an administrator may pin (engagement-communities D3). */
export const PINNED_COMMUNITIES_MAX = 20

/**
 * The tokens of the textarea: one per line, blanks and duplicates dropped.
 *
 * @param {string} text The textarea text.
 * @return {string[]} The tokens, in the order given.
 */
export function tokensOf(text) {
	return [
		...new Set(
			String(text || '')
				.split(/[\s,]+/)
				.map((token) => token.trim())
				.filter((token) => token !== ''),
		),
	]
}

/**
 * CommunitiesTab: the administrator's list of pinned Talk conversations
 * (openspec/changes/engagement-communities, REQ-COMM-003). The server
 * validates the tokens; its refusal is shown here.
 */
export default {
	name: 'CommunitiesTab',

	components: { NcButton },

	data() {
		return {
			draft: '',
			saving: false,
			error: '',
			saved: false,
		}
	},

	computed: {
		/**
		 * The line under the Save button: the refusal, or that it was saved.
		 *
		 * @return {string} The text.
		 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003
		 */
		status() {
			if (this.error !== '') {
				return this.error
			}
			return this.saved ? t('launchpad', 'Pinned communities saved.') : ''
		},
	},

	/** @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003 */
	async created() {
		try {
			const response = await api.getAdminSettings()
			const data = response?.data?.data ?? response?.data ?? {}
			const pinned = Array.isArray(data.pinnedCommunities) ? data.pinnedCommunities : []
			this.draft = pinned.join('\n')
		} catch (error) {
			logger.error('Failed to load the pinned communities:', error)
		}
	},

	methods: {
		t,

		/**
		 * Save the pinned tokens. Too many is refused here; the token shape is
		 * the server's to judge, and its message is shown.
		 *
		 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003
		 */
		async save() {
			const tokens = tokensOf(this.draft)
			this.saved = false
			if (tokens.length > PINNED_COMMUNITIES_MAX) {
				this.error = t('launchpad', 'At most {max} conversations can be pinned.', {
					max: PINNED_COMMUNITIES_MAX,
				})
				return
			}

			this.saving = true
			this.error = ''
			try {
				await api.updateAdminSettings({ pinnedCommunities: tokens })
				this.draft = tokens.join('\n')
				this.saved = true
			} catch (error) {
				this.error =
					error?.response?.data?.error
					|| t('launchpad', 'The pinned communities could not be saved.')
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.communities-tab {
	display: flex;
	flex-direction: column;
	gap: 8px;
	max-width: 720px;
}

.communities-tab__intro {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.communities-tab__label {
	font-weight: bold;
}

.communities-tab__tokens {
	width: 100%;
	font-family: var(--font-face-monospace, monospace);
	background: var(--color-main-background);
	color: var(--color-main-text);
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius);
}

.communities-tab__actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 12px;
}

.communities-tab__status {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.communities-tab__status--error {
	color: var(--color-error-text, var(--color-error));
}
</style>
