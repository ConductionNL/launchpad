<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -
  - FeedTokenManager
  -
  - Self-contained card surface that lets an end user opt-in to the
  - per-user RSS / Atom feed (REQ-FEED-001, REQ-FEED-008), rotate the
  - token (REQ-FEED-002), or soft-revoke it (REQ-FEED-003). Wraps the
  - three /api/feed/token endpoints and exposes a copy-to-clipboard
  - action for the absolute feed URL returned by the backend.
  -->

<template>
	<div class="launchpad-feed-token">
		<h3 class="launchpad-feed-token__title">
			{{ t('launchpad', 'RSS / Atom feed') }}
		</h3>
		<p class="launchpad-feed-token__description">
			{{ t('launchpad', 'Your personal RSS feed of accessible dashboards.') }}
		</p>

		<div v-if="loading" class="launchpad-feed-token__loading">
			{{ t('launchpad', 'Loading…') }}
		</div>

		<div v-else-if="hasToken" class="launchpad-feed-token__active">
			<div class="launchpad-feed-token__url-row">
				<input
					ref="urlInput"
					type="text"
					readonly
					class="launchpad-feed-token__url"
					:value="feedUrl">
				<button
					type="button"
					class="launchpad-feed-token__copy"
					@click="copyUrl">
					{{ t('launchpad', 'Copy feed URL') }}
				</button>
			</div>
			<p class="launchpad-feed-token__warning">
				{{ t('launchpad', 'Treat this URL as a password — anyone with the link can read your dashboards.') }}
			</p>
			<div class="launchpad-feed-token__actions">
				<button type="button" @click="regenerate">
					{{ t('launchpad', 'Regenerate feed token') }}
				</button>
				<button type="button" class="launchpad-feed-token__revoke" @click="revoke">
					{{ t('launchpad', 'Revoke feed token') }}
				</button>
			</div>
		</div>

		<div v-else class="launchpad-feed-token__inactive">
			<p>{{ t('launchpad', 'No feed token issued yet.') }}</p>
			<button type="button" @click="enable">
				{{ t('launchpad', 'Generate feed token') }}
			</button>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { api } from '../services/api.js'

export default {
	name: 'FeedTokenManager',

	data() {
		return {
			loading: false,
			feedUrl: '',
			feedToken: '',
		}
	},

	computed: {
		hasToken() {
			return this.feedUrl !== '' && this.feedToken !== ''
		},
	},

	methods: {
		t,

		/** @spec openspec/specs/dashboard-rss-feeds/spec.md */
		async enable() {
			this.loading = true
			try {
				const { data } = await api.getFeedToken()
				this.applyToken(data)
			} catch (error) {
				showError(t('launchpad', 'Generate feed token'))
			} finally {
				this.loading = false
			}
		},

		/** @spec openspec/specs/dashboard-rss-feeds/spec.md */
		async regenerate() {
			this.loading = true
			try {
				const { data } = await api.regenerateFeedToken()
				this.applyToken(data)
			} catch (error) {
				showError(t('launchpad', 'Regenerate feed token'))
			} finally {
				this.loading = false
			}
		},

		/** @spec openspec/specs/dashboard-rss-feeds/spec.md */
		async revoke() {
			this.loading = true
			try {
				await api.revokeFeedToken()
				this.feedUrl = ''
				this.feedToken = ''
			} catch (error) {
				showError(t('launchpad', 'Revoke feed token'))
			} finally {
				this.loading = false
			}
		},

		/** @spec openspec/specs/dashboard-rss-feeds/spec.md */
		applyToken(payload) {
			if (!payload) {
				return
			}
			this.feedToken = payload.token || ''
			this.feedUrl = payload.url || ''
		},

		/** @spec openspec/specs/dashboard-rss-feeds/spec.md */
		async copyUrl() {
			if (!this.feedUrl) {
				return
			}
			try {
				await navigator.clipboard.writeText(this.feedUrl)
				showSuccess(t('launchpad', 'Feed URL copied to clipboard'))
			} catch (error) {
				if (this.$refs.urlInput) {
					this.$refs.urlInput.select()
				}
				showError(t('launchpad', 'Copy feed URL'))
			}
		},
	},
}
</script>

<style scoped>
.launchpad-feed-token {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 16px;
	border: 1px solid var(--color-border);
	border-radius: 8px;
}

.launchpad-feed-token__title {
	margin: 0;
	font-size: 16px;
	font-weight: 600;
}

.launchpad-feed-token__description {
	margin: 0;
	color: var(--color-text-maxcontrast);
}

.launchpad-feed-token__url-row {
	display: flex;
	gap: 8px;
}

.launchpad-feed-token__url {
	flex: 1;
	font-family: monospace;
	font-size: 12px;
	padding: 6px 8px;
}

.launchpad-feed-token__warning {
	color: var(--color-warning);
	margin: 0;
	font-size: 12px;
}

.launchpad-feed-token__actions {
	display: flex;
	gap: 8px;
}

.launchpad-feed-token__revoke {
	color: var(--color-error);
}
</style>
