<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="visible"
		class="dashboard-config__field sharing-screen"
		data-test="sharing-screen-link">
		<label class="dashboard-config__label">
			{{ t('launchpad', 'Show on a screen') }}
		</label>
		<p class="dashboard-config__hint">
			{{ t('launchpad', 'A screen link opens this dashboard as a kiosk screen: no app frame, no account, read only, and it refreshes itself. For the screen in the canteen or at the desk.') }}
		</p>

		<!-- A screen on the wall goes dark, so Revoke asks first. -->
		<CnConfirmDialog
			v-if="confirmingRevoke"
			:dialogTitle="t('launchpad', 'Revoke screen link')"
			:message="t('launchpad', 'Revoke the screen link of {name}? A screen showing it stops showing this dashboard.', { name: dashboardName })"
			:confirmLabel="t('launchpad', 'Revoke')"
			variant="error"
			@confirm="revoke"
			@close="confirmingRevoke = false" />

		<NcLoadingIcon v-if="loading" />
		<div v-else-if="link" class="sharing-screen__link" data-test="sharing-screen-link-row">
			<div class="sharing-screen__row">
			<input
				class="dashboard-config__public-url"
				type="text"
				readonly
				data-test="sharing-screen-link-url"
				:aria-label="t('launchpad', 'Screen link')"
				:value="link.url"
				@focus="$event.target.select()">
				<NcButton
					variant="secondary"
					data-test="sharing-screen-link-copy"
					@click="copy">
					<template #icon>
						<Check v-if="copied" :size="18" />
						<ContentCopy v-else :size="18" />
					</template>
					{{ copied ? t('launchpad', 'Copied') : t('launchpad', 'Copy') }}
				</NcButton>
				<NcButton
					variant="error"
					data-test="sharing-screen-link-revoke"
					@click="confirmingRevoke = true">
					{{ t('launchpad', 'Revoke') }}
				</NcButton>
			</div>
			<p class="dashboard-config__hint" data-test="sharing-screen-link-meta">
				{{ metaLine }}
			</p>
		</div>
		<template v-if="!loading">
			<p
				v-if="!policy.screenLinksAllowed"
				class="dashboard-config__hint"
				data-test="sharing-screen-link-off">
				{{ t('launchpad', 'New screen links are switched off.') }}
			</p>
			<NcButton
				v-else-if="!link"
				variant="secondary"
				data-test="sharing-screen-link-create"
				:disabled="busy"
				@click="create">
				<template #icon>
					<Monitor :size="20" />
				</template>
				{{ t('launchpad', 'Create a new screen link') }}
			</NcButton>
		</template>
		<p class="dashboard-config__hint sharing-screen__note">
			{{ t('launchpad', 'Revoke works within a minute on every screen that uses the link. Whoever has the link sees only this dashboard; a screen with several dashboards is set up by administration under Kiosk.') }}
		</p>
		<p class="hidden-visually" aria-live="polite">
			{{ copied ? t('launchpad', 'Link copied') : '' }}
		</p>
		<p v-if="error !== ''" class="dashboard-config__hint" role="alert" data-test="sharing-screen-link-error">
			{{ error }}
		</p>
	</div>
</template>

<script>
import { CnConfirmDialog, NcButton, NcLoadingIcon } from '@conduction/nextcloud-vue'
import { n, t } from '@nextcloud/l10n'
import Check from 'vue-material-design-icons/Check.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import Monitor from 'vue-material-design-icons/Monitor.vue'
import { NULLABLE_OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { screenLinkApi } from '../../../services/screenLinkApi.js'
import { readSharingPolicy } from '../../../utils/loadInitialState.js'
import { logger } from '../../../utils/logger.js'

/**
 * SharingScreenLink: the "Show on a screen" block on a dashboard's Sharing
 * tab (REQ-KIOSK-006, REQ-KIOSK-007). Creates, copies and revokes the
 * dashboard's screen link, a kiosk playlist of this one dashboard. With the
 * administrator's switch off it offers no new link, but keeps an existing
 * one with Revoke.
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-5
 */
export default {
	name: 'SharingScreenLink',

	components: {
		Check,
		CnConfirmDialog,
		ContentCopy,
		Monitor,
		NcButton,
		NcLoadingIcon,
	},

	props: {
		/** The dashboard whose Sharing tab this is. */
		dashboard: NULLABLE_OBJECT_PROP,
	},

	data() {
		return {
			policy: readSharingPolicy(),
			link: null,
			loading: false,
			busy: false,
			copied: false,
			confirmingRevoke: false,
			error: '',
		}
	},

	computed: {
		/**
		 * Shown to whoever may share; with the switch off only while a link exists.
		 *
		 * @return {boolean}
		 */
		visible() {
			if (!this.policy.mayShare || !this.dashboard?.uuid) {
				return false
			}
			return this.policy.screenLinksAllowed || this.loading || this.link !== null
		},

		/**
		 * The line under the link (board LpDelen): read only, how often it
		 * refreshes and when it was made.
		 *
		 * @return {string}
		 */
		metaLine() {
			const minutes = Math.max(1, Math.round((this.link?.refreshSeconds ?? 300) / 60))
			const created = this.link?.createdAt
				? new Date(this.link.createdAt.replace(' ', 'T')).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' })
				: ''
			const parts = [
				t('launchpad', 'Read only'),
				n('launchpad', 'refreshes every minute', 'refreshes every %n minutes', minutes),
			]
			if (created !== '') {
				parts.push(t('launchpad', 'created {date}', { date: created }))
			}
			return parts.join(' · ')
		},

		/** @return {string} The dashboard's name for the dialog. */
		dashboardName() {
			return this.dashboard?.name ?? ''
		},
	},

	watch: {
		'dashboard.uuid': {
			immediate: true,
			handler() {
				this.load()
			},
		},
	},

	methods: {
		t,

		/**
		 * Read the dashboard's active screen link.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-5
		 */
		async load() {
			if (!this.policy.mayShare || !this.dashboard?.uuid) {
				return
			}
			this.loading = true
			this.error = ''
			try {
				this.link = await screenLinkApi.get(this.dashboard.uuid)
			} catch (error) {
				logger.error('Screen link could not be read:', error)
				this.link = null
			} finally {
				this.loading = false
			}
		},

		/**
		 * Create the screen link (or get the existing one).
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-5
		 */
		async create() {
			this.busy = true
			this.error = ''
			try {
				this.link = await screenLinkApi.create(this.dashboard.uuid)
			} catch (error) {
				logger.error('Screen link could not be created:', error)
				this.error = t('launchpad', 'The screen link could not be created.')
			} finally {
				this.busy = false
			}
		},

		/**
		 * Revoke the screen link after the confirmation.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-5
		 */
		async revoke() {
			this.confirmingRevoke = false
			this.busy = true
			this.error = ''
			try {
				await screenLinkApi.revoke(this.dashboard.uuid)
				this.link = null
				this.copied = false
			} catch (error) {
				logger.error('Screen link could not be revoked:', error)
				this.error = t('launchpad', 'The screen link could not be revoked.')
			} finally {
				this.busy = false
			}
		},

		/**
		 * Copy the link to the clipboard.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-5
		 */
		async copy() {
			try {
				await navigator.clipboard.writeText(this.link.url)
				this.copied = true
			} catch (error) {
				logger.error('Clipboard write failed:', error)
			}
		},
	},
}
</script>

<style scoped>
.sharing-screen__row {
	display: flex;
	align-items: center;
	gap: 8px;
}

.sharing-screen__row .dashboard-config__public-url {
	flex: 1;
	min-width: 0;
}

.sharing-screen__note {
	margin-top: 8px;
}
</style>
