<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="announcements-tab" data-testid="announcements-tab">
		<!-- The form section replaces the list while one announcement is
		     written or edited (board LpAankondiging). -->
		<AnnouncementEditorModal
			v-if="editing"
			:key="editing.uuid || 'new'"
			inline
			:announcement="editing.uuid ? editing : null"
			@close="closeForm"
			@published="onSaved" />

		<CnSettingsSection
			v-else-if="receiptsOf"
			:name="t('launchpad', 'Seen and confirmed')"
			:description="receiptsOf.title"
			data-testid="admin-announcement-receipts">
			<p v-if="receipts === null" class="announcements-tab__hint">
				{{ t('launchpad', 'Loading…') }}
			</p>
			<template v-else-if="receipts.error">
				<p class="announcements-tab__hint">
					{{ receipts.error }}
				</p>
			</template>
			<template v-else>
				<p data-testid="announcement-receipts-counts">
					{{ t('launchpad', '{confirmed} confirmed, {pending} not yet confirmed', { confirmed: receipts.acknowledgedCount, pending: receipts.pendingCount }) }}
				</p>
				<ul class="announcements-tab__receipts">
					<li v-for="row in receipts.rows" :key="row.userId">
						<span>{{ row.userId }}</span>
						<span class="announcements-tab__status">{{ row.status === 'acknowledged' ? t('launchpad', 'Confirmed') : t('launchpad', 'Not confirmed') }}</span>
						<span v-if="row.acknowledgedAt" class="announcements-tab__time">{{ row.acknowledgedAt }}</span>
					</li>
				</ul>
			</template>
			<NcButton variant="tertiary" data-testid="announcement-receipts-back" @click="openList">
				{{ t('launchpad', 'Back to the announcements') }}
			</NcButton>
		</CnSettingsSection>

		<CnSettingsSection
			v-else
			:name="t('launchpad', 'Announcements')"
			:description="t('launchpad', 'News and maintenance notices for the people in your organisation.')"
			data-testid="admin-announcements">
			<NcButton variant="primary" data-testid="announcement-new" @click="openForm(null)">
				{{ t('launchpad', 'New announcement') }}
			</NcButton>
			<p v-if="error" class="announcements-tab__error" role="alert">
				{{ error }}
			</p>
			<p v-if="!loading && items.length === 0" class="announcements-tab__hint">
				{{ t('launchpad', 'No announcements yet.') }}
			</p>
			<table v-else class="announcements-tab__table">
				<thead>
					<tr>
						<th scope="col">
							{{ t('launchpad', 'Title') }}
						</th>
						<th scope="col">
							{{ t('launchpad', 'Kind') }}
						</th>
						<th scope="col">
							{{ t('launchpad', 'Status') }}
						</th>
						<th scope="col">
							{{ t('launchpad', 'Shown until') }}
						</th>
						<th scope="col">
							<span class="hidden-visually">{{ t('launchpad', 'Actions') }}</span>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="item in items" :key="item.uuid" data-testid="announcement-row">
						<td>{{ item.title }}</td>
						<td>{{ item.kind === 'notice' ? t('launchpad', 'Maintenance notice') : t('launchpad', 'News') }}</td>
						<td>{{ statusLabel(item) }}</td>
						<td>{{ formatTime(item.expiresAt) }}</td>
						<td class="announcements-tab__actions">
							<NcButton variant="tertiary" data-testid="announcement-edit" @click="openForm(item)">
								{{ t('launchpad', 'Edit') }}
							</NcButton>
							<NcButton
								v-if="item.requiresConfirmation"
								variant="tertiary"
								data-testid="announcement-receipts"
								@click="openReceipts(item)">
								{{ t('launchpad', 'Seen and confirmed') }}
							</NcButton>
							<NcButton
								v-if="announcementStatus(item, now()) === 'active'"
								variant="tertiary"
								data-testid="announcement-end"
								@click="endNow(item)">
								{{ t('launchpad', 'End now') }}
							</NcButton>
							<NcButton variant="tertiary" data-testid="announcement-delete" @click="remove(item)">
								{{ t('launchpad', 'Delete') }}
							</NcButton>
						</td>
					</tr>
				</tbody>
			</table>
		</CnSettingsSection>
	</div>
</template>

<script>
import { CnSettingsSection } from '@conduction/nextcloud-vue'
import { getCanonicalLocale } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import AnnouncementEditorModal from '../../../modals/AnnouncementEditorModal.vue'
import { announcementsApi } from '../../../services/announcementsApi.js'
import { logger } from '../../../utils/logger.js'

/**
 * The state of an announcement on the tab: draft, scheduled, active or ended.
 *
 * @param {object} item The announcement.
 * @param {Date} now The current moment.
 * @return {'draft'|'scheduled'|'active'|'ended'}
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
 */
export function announcementStatus(item, now) {
	if (item.status !== 'published') {
		return 'draft'
	}
	if (item.expiresAt && new Date(item.expiresAt).getTime() <= now.getTime()) {
		return 'ended'
	}
	if (item.publishAt && new Date(item.publishAt).getTime() > now.getTime()) {
		return 'scheduled'
	}
	return 'active'
}

/**
 * The tab's state in the URL fragment: `#announcements`,
 * `#announcements/<uuid>` (its form) or `#announcements/<uuid>/receipts`.
 *
 * @param {string} hash The fragment.
 * @return {{uuid: string, section: string}}
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
 */
export function announcementsFragment(hash) {
	const [tab, uuid = '', section = ''] = String(hash ?? '').replace(/^#/, '').split('/')
	if (tab !== 'announcements') {
		return { uuid: '', section: '' }
	}
	return { uuid, section: section === 'receipts' ? 'receipts' : (uuid ? 'edit' : '') }
}

/**
 * The Announcements tab of the admin settings page (board LpAankondiging,
 * admin-settings REQ-ADM-034): the one place where announcements are
 * managed. It lists every announcement, news and notices, draft, scheduled,
 * active and ended (REQ-ANN-008); New announcement and Edit open the form
 * with its live preview as a section of the tab (REQ-ANN-006); End now sets
 * the end time to now; Delete asks first; Seen and confirmed shows who
 * confirmed an announcement that asks for it (REQ-ANN-009). The open
 * announcement and section are in the URL fragment, so the widget's Manage
 * link opens it directly.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
 */
export default {
	name: 'AnnouncementsTab',

	components: {
		AnnouncementEditorModal,
		CnSettingsSection,
		NcButton,
	},

	data() {
		return {
			items: [],
			loading: true,
			error: '',
			editing: null,
			receiptsOf: null,
			receipts: null,
		}
	},

	/** @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008 */
	async mounted() {
		await this.load()
		this.followFragment()
	},

	methods: {
		announcementStatus,

		/**
		 * @return {Date} The current moment.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
		 */
		now() {
			return new Date()
		},

		/**
		 * Load every announcement.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
		 */
		async load() {
			this.loading = true
			this.error = ''
			try {
				this.items = await announcementsApi.manageList()
			} catch (error) {
				logger.error('Loading the announcements failed:', error)
				this.error = this.t('launchpad', 'The announcements could not be loaded.')
			} finally {
				this.loading = false
			}
		},

		/**
		 * Open the announcement or section the fragment names.
		 *
		 * @return {void}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
		 */
		followFragment() {
			const { uuid, section } = announcementsFragment(globalThis.location?.hash ?? '')
			const item = this.items.find((candidate) => candidate.uuid === uuid)
			if (!item) {
				return
			}
			if (section === 'receipts') {
				this.openReceipts(item)
			} else {
				this.openForm(item)
			}
		},

		/**
		 * Keep the tab's state in the fragment without a new history entry.
		 *
		 * @param {string} suffix '', `/<uuid>` or `/<uuid>/receipts`.
		 * @return {void}
		 */
		setFragment(suffix) {
			globalThis.history?.replaceState?.(null, '', `#announcements${suffix}`)
		},

		/**
		 * Open the form for a new (null) or a stored announcement.
		 *
		 * @param {object|null} item The announcement.
		 * @return {void}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 */
		openForm(item) {
			this.receiptsOf = null
			this.editing = item ? { ...item } : { uuid: '' }
			this.setFragment(item ? `/${item.uuid}` : '')
		},

		/**
		 * @return {void}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 */
		closeForm() {
			this.editing = null
			this.setFragment('')
		},

		/**
		 * @return {void}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
		 */
		openList() {
			this.receiptsOf = null
			this.receipts = null
			this.setFragment('')
		},

		/**
		 * After Save: back to the list, reloaded.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 */
		async onSaved() {
			this.closeForm()
			await this.load()
		},

		/**
		 * Show who confirmed one announcement (REQ-ANN-009).
		 *
		 * @param {object} item The announcement.
		 * @return {Promise<void>}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		async openReceipts(item) {
			this.editing = null
			this.receiptsOf = item
			this.receipts = null
			this.setFragment(`/${item.uuid}/receipts`)
			try {
				this.receipts = await announcementsApi.receipts(item.uuid)
			} catch (error) {
				logger.error('Loading the read confirmations failed:', error)
				this.receipts = { error: this.t('launchpad', 'The read confirmations could not be loaded.') }
			}
		},

		/**
		 * End an active announcement now: its end time becomes now, so it
		 * leaves every dashboard (REQ-ANN-006).
		 *
		 * @param {object} item The announcement.
		 * @return {Promise<void>}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 */
		async endNow(item) {
			try {
				await announcementsApi.update(item.uuid, { expiresAt: new Date().toISOString() })
				await this.load()
			} catch (error) {
				logger.error('Ending the announcement failed:', error)
				this.error = this.t('launchpad', 'The announcement could not be ended.')
			}
		},

		/**
		 * Delete an announcement, after asking (REQ-ANN-006).
		 *
		 * @param {object} item The announcement.
		 * @return {Promise<void>}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
		 */
		async remove(item) {
			const question = this.t('launchpad', 'Delete the announcement "{title}"?', { title: item.title })
			if (typeof globalThis.confirm === 'function' && !globalThis.confirm(question)) {
				return
			}
			try {
				await announcementsApi.remove(item.uuid)
				await this.load()
			} catch (error) {
				logger.error('Deleting the announcement failed:', error)
				this.error = this.t('launchpad', 'The announcement could not be deleted.')
			}
		},

		/**
		 * The status as the list names it.
		 *
		 * @param {object} item The announcement.
		 * @return {string}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
		 */
		statusLabel(item) {
			const labels = {
				draft: this.t('launchpad', 'Draft'),
				scheduled: this.t('launchpad', 'Scheduled'),
				active: this.t('launchpad', 'Active'),
				ended: this.t('launchpad', 'Ended'),
			}
			return labels[announcementStatus(item, this.now())]
		},

		/**
		 * A stored time in the reader's locale, or a dash.
		 *
		 * @param {string|null} iso The time.
		 * @return {string}
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-tab-lists-every-announcement-req-ann-008
		 */
		formatTime(iso) {
			if (!iso) {
				return '–'
			}
			const date = new Date(iso)
			return Number.isNaN(date.getTime())
				? '–'
				: date.toLocaleString(getCanonicalLocale(), { dateStyle: 'medium', timeStyle: 'short' })
		},
	},
}
</script>

<style scoped>
.announcements-tab__hint {
	color: var(--color-text-maxcontrast);
}

.announcements-tab__error {
	color: var(--color-error-text);
}

.announcements-tab__table {
	width: 100%;
	margin-top: 12px;
	border-collapse: collapse;
}

.announcements-tab__table th,
.announcements-tab__table td {
	padding: 6px 8px;
	text-align: start;
	border-bottom: 1px solid var(--color-border);
}

.announcements-tab__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.announcements-tab__receipts {
	list-style: none;
	padding: 0;
}

.announcements-tab__receipts li {
	display: flex;
	gap: 12px;
	padding: 4px 0;
}

.announcements-tab__status,
.announcements-tab__time {
	color: var(--color-text-maxcontrast);
}
</style>
