<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section class="announcements-widget" data-test="announcements-widget">
		<div class="announcements-widget__toolbar">
			<NcSelect
				v-model="category"
				:options="categoryOptions"
				:inputLabel="t('launchpad', 'Category')"
				:reduce="(option) => option.value"
				label="label"
				:clearable="false"
				data-test="announcements-category" />
			<!-- REQ-ANN-010: the widget only shows; a manager manages on the
			     Announcements tab of the admin settings page. -->
			<NcButton
				v-if="canAuthor"
				variant="secondary"
				:href="manageUrl"
				data-test="announcements-manage">
				{{ t('launchpad', 'Manage') }}
			</NcButton>
		</div>

		<p v-if="error" class="announcements-widget__empty" role="alert">
			{{ error }}
		</p>
		<p v-else-if="!loading && shown.length === 0" class="announcements-widget__empty">
			{{ t('launchpad', 'No announcements') }}
		</p>

		<ul class="announcements-widget__list">
			<li v-for="item in shown" :key="item.uuid">
				<AnnouncementCard
					:announcement="item"
					:confirmed="readState.confirmed.includes(item.uuid)"
					:manageUrl="canAuthor ? manageUrlOf(item.uuid) : ''"
					:preview="preview"
					@confirm="confirm"
					@open="open" />
			</li>
		</ul>

		<AnnouncementModal
			:announcement="openItem"
			:reactions="openItem ? reactionsOf(openItem.uuid) : undefined"
			:followed="followed"
			:confirmed="openItem ? readState.confirmed.includes(openItem.uuid) : false"
			:manageUrl="openItem && canAuthor ? manageUrlOf(openItem.uuid) : ''"
			@close="openUuid = ''"
			@confirm="confirm"
			@update:reactions="setReactions"
			@update:followed="followed = $event" />
	</section>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton, NcSelect } from '@nextcloud/vue'
import AnnouncementModal from '../../../modals/AnnouncementModal.vue'
import AnnouncementCard from '../../Announcements/AnnouncementCard.vue'
import { announcementsApi } from '../../../services/announcementsApi.js'
import { adminPageUrl } from '../../../utils/adminPage.js'
import { logger } from '../../../utils/logger.js'

/**
 * The reader's receipt state of the list: the uuids seen, confirmed and
 * closed with the x (REQ-ANN-007, REQ-ANN-009).
 *
 * @param {object|undefined} state `{seen, confirmed, closed}` from the server.
 * @return {{seen: Array<string>, confirmed: Array<string>, closed: Array<string>}}
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
export function readConfirmationState(state) {
	const list = (value) => (Array.isArray(value) ? value.filter((uuid) => typeof uuid === 'string') : [])
	return { seen: list(state?.seen), confirmed: list(state?.confirmed), closed: list(state?.closed) }
}

/**
 * AnnouncementsWidget — the news announcements that reach the reader,
 * newest first, with a category filter (REQ-ANN-010). The widget only shows:
 * each item has the read row of REQ-ANN-007 (the check marks it read and
 * keeps it; the x confirms it and hides it for this reader) and Open, which
 * opens the announcement's own view with likes, comments and follow
 * (REQ-ANN-011). A manager gets Manage, which opens the Announcements tab of
 * the admin settings page, on the item or in general. The items on screen are
 * recorded as seen in one call after render (REQ-ANN-009). Notices are not
 * listed here; they show as banners above the grid (REQ-ANN-005).
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-widget-only-shows-req-ann-010
 */
export default {
	name: 'AnnouncementsWidget',

	components: { AnnouncementCard, AnnouncementModal, NcButton, NcSelect },

	props: {
		/**
		 * The widget is a miniature in Add widget (REQ-WDG-048): it draws
		 * its sample content and sends no request.
		 */
		preview: { type: Boolean, default: false },

		/** Placement content: `category` (initial filter) and `limit`. */
		content: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			loading: true,
			error: '',
			announcements: [],
			reactions: {},
			followed: [],
			canAuthor: false,
			category: this.content?.category ?? '',
			openUuid: '',
			readState: { seen: [], confirmed: [], closed: [] },
		}
	},

	computed: {
		/**
		 * The Announcements tab of the admin settings page (REQ-ANN-010).
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-widget-only-shows-req-ann-010
		 */
		manageUrl() {
			return adminPageUrl('announcements')
		},

		/**
		 * @return {Array<object>} News items, newest first, filtered, without the closed ones, limited.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-widget-only-shows-req-ann-010
		 */
		shown() {
			const limit = Number(this.content?.limit) > 0 ? Number(this.content.limit) : 10
			return this.announcements
				.filter((item) => item.kind !== 'notice')
				.filter((item) => !this.readState.closed.includes(item.uuid))
				.filter((item) => this.category === '' || item.category === this.category)
				.slice(0, limit)
		},

		/**
		 * @return {object|null} The announcement whose view is open.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		openItem() {
			return this.announcements.find((item) => item.uuid === this.openUuid) ?? null
		},

		/** @return {Array<string>} The categories of the announcements and the followed ones. */
		categories() {
			const all = new Set(this.followed)
			this.announcements.forEach((item) => item.category && all.add(item.category))
			return [...all].sort()
		},

		/** @return {Array<object>} */
		categoryOptions() {
			return [
				{ value: '', label: t('launchpad', 'All categories') },
				...this.categories.map((name) => ({ value: name, label: name })),
			]
		},
	},

	/**
	 * @spec openspec/changes/engagement-announcements/tasks.md#task-7
	 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md#requirement-a-widgets-miniature-is-the-widget-itself-scaled-down-req-wdg-048
	 */
	created() {
		if (this.preview) {
			// REQ-WDG-048: a miniature draws its sample state.
			Object.assign(this.$data, { loading: false, ...(this.content?.sampleData || {}) })
			return
		}
		this.load()
	},

	methods: {
		t,

		/**
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-widget-only-shows-req-ann-010
		 */
		async load() {
			this.loading = true
			try {
				// The read state is asked beside the list, never after it (REQ-ANN-007).
				const [data, readState] = await Promise.all([
					announcementsApi.list(),
					announcementsApi.readState().catch((error) => {
						logger.error('Failed to load the read confirmations:', error)
						return null
					}),
				])
				this.announcements = Array.isArray(data.announcements) ? data.announcements : []
				this.reactions = data.reactions ?? {}
				this.followed = Array.isArray(data.followedCategories) ? data.followedCategories : []
				this.canAuthor = data.canAuthor === true
				this.readState = readConfirmationState(readState)
				this.error = ''
			} catch (error) {
				logger.error('Failed to load announcements:', error)
				this.error = t('launchpad', 'Announcements could not be loaded')
			} finally {
				this.loading = false
			}
			this.$nextTick(() => this.markSeen())
		},

		/**
		 * Record the items on screen that were not seen yet, after render, without waiting.
		 *
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		markSeen() {
			const uuids = this.shown.map((item) => item.uuid).filter((uuid) => !this.readState.seen.includes(uuid))
			if (uuids.length === 0) {
				return
			}
			this.readState = { ...this.readState, seen: [...this.readState.seen, ...uuids] }
			announcementsApi.markSeen(uuids).catch((error) => {
				logger.error('Failed to record the announcements as seen:', error)
			})
		},

		/**
		 * @param {string} uuid The announcement.
		 * @return {string} The Announcements tab on this announcement.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-widget-only-shows-req-ann-010
		 */
		manageUrlOf(uuid) {
			return `${this.manageUrl}/${uuid}`
		},

		/**
		 * @param {string} uuid The announcement.
		 * @return {object} `{likes, comments, liked}`.
		 */
		reactionsOf(uuid) {
			return { likes: 0, comments: 0, liked: false, ...(this.reactions[uuid] ?? {}) }
		},

		/**
		 * @param {string} uuid The announcement.
		 * @param {object} state Its new `{likes, comments, liked}`.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		setReactions(uuid, state) {
			this.reactions = { ...this.reactions, [uuid]: state }
		},

		/**
		 * Open the announcement's own view.
		 *
		 * @param {string} uuid The announcement.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		open(uuid) {
			this.openUuid = uuid
		},

		/**
		 * The check or the x: both confirm; the check keeps the item, marked
		 * read; the x hides it for this reader (and closes its view).
		 *
		 * @param {string} uuid The announcement.
		 * @param {string} via `check` or `x`.
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
		 */
		async confirm(uuid, via = 'check') {
			const before = this.readState
			const confirmed = before.confirmed.includes(uuid) ? before.confirmed : [...before.confirmed, uuid]
			const closed = via === 'x' && !before.closed.includes(uuid) ? [...before.closed, uuid] : before.closed
			this.readState = { ...before, confirmed, closed }
			if (via === 'x' && this.openUuid === uuid) {
				this.openUuid = ''
			}
			try {
				await announcementsApi.confirmRead(uuid, via)
			} catch (error) {
				logger.error('Failed to confirm the announcement:', error)
				this.readState = before
				this.error = t('launchpad', 'Your read confirmation could not be saved')
			}
		},
	},
}
</script>

<style scoped>
.announcements-widget {
	display: flex;
	flex-direction: column;
	gap: 8px;
	height: 100%;
	overflow: auto;
}

.announcements-widget__toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
}

.announcements-widget__list {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.announcements-widget__empty {
	color: var(--color-text-maxcontrast);
}
</style>
