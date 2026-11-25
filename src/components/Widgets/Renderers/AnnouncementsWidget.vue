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
			<NcButton
				v-if="category"
				variant="secondary"
				data-test="announcements-follow"
				@click="toggleFollow(category)">
				{{ isFollowed(category) ? t('launchpad', 'Unfollow {category}', { category }) : t('launchpad', 'Follow {category}', { category }) }}
			</NcButton>
			<NcButton
				v-if="canAuthor"
				variant="primary"
				data-test="announcements-new"
				@click="editorOpen = true">
				{{ t('launchpad', 'New announcement') }}
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
					:reactions="reactionsOf(item.uuid)"
					:confirmed="readConfirmations.confirmed.includes(item.uuid)"
					:canReport="readConfirmations.receipts.includes(item.uuid)"
					@toggleLike="toggleLike"
					@openComments="openComments"
					@confirmRead="confirmRead"
					@openReceipts="openReceipts">
					<div v-if="openUuid === item.uuid" class="announcements-widget__comments" data-test="announcement-comment-list">
						<ul>
							<li v-for="comment in comments" :key="comment.id">
								<strong>{{ comment.displayName }}</strong> {{ comment.message }}
							</li>
						</ul>
						<form v-if="item.allowComments" class="announcements-widget__comment-form" @submit.prevent="sendComment(item.uuid)">
							<NcTextField
								v-model="draftComment"
								:label="t('launchpad', 'Write a comment')"
								:maxlength="1000"
								data-test="announcement-comment-input" />
							<NcButton type="submit" variant="primary" :disabled="draftComment.trim() === ''">
								{{ t('launchpad', 'Send') }}
							</NcButton>
						</form>
						<p v-else class="announcements-widget__empty">
							{{ t('launchpad', 'Comments are closed') }}
						</p>
					</div>
				</AnnouncementCard>
			</li>
		</ul>

		<AcknowledgementReportModal
			:open="receiptsItem !== null"
			:announcementUuid="receiptsItem ? receiptsItem.uuid : ''"
			:title="receiptsItem ? receiptsItem.title : ''"
			@close="receiptsItem = null" />

		<AnnouncementEditorModal
			v-if="editorOpen"
			@close="editorOpen = false"
			@published="onPublished" />
	</section>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton, NcSelect, NcTextField } from '@nextcloud/vue'
import AcknowledgementReportModal from '../../../modals/AcknowledgementReportModal.vue'
import AnnouncementEditorModal from '../../../modals/AnnouncementEditorModal.vue'
import AnnouncementCard from '../../Announcements/AnnouncementCard.vue'
import { announcementsApi } from '../../../services/announcementsApi.js'
import { logger } from '../../../utils/logger.js'

/**
 * The read confirmation state of the list: the uuids the reader confirmed
 * and the ones whose read confirmations the reader may open (REQ-ANN-007).
 *
 * @param {object|undefined} state `{confirmed, receipts}` from the server.
 * @return {{confirmed: Array<string>, receipts: Array<string>}}
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
export function readConfirmationState(state) {
	const list = (value) => (Array.isArray(value) ? value.filter((uuid) => typeof uuid === 'string') : [])
	return { confirmed: list(state?.confirmed), receipts: list(state?.receipts) }
}

/**
 * AnnouncementsWidget — the news announcements that reach the reader,
 * newest first, with a category filter, like and comment counts, comments,
 * and follow per category (REQ-ANN-001..003). Notices are not listed here;
 * they show as banners above the grid (REQ-ANN-005). Authors get a "New
 * announcement" button that opens the editor with its preview step. A
 * reader confirms "I have read this"; the author opens who confirmed
 * (REQ-ANN-007).
 *
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
 */
export default {
	name: 'AnnouncementsWidget',

	components: { AcknowledgementReportModal, AnnouncementCard, AnnouncementEditorModal, NcButton, NcSelect, NcTextField },

	props: {
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
			comments: [],
			draftComment: '',
			editorOpen: false,
			readConfirmations: { confirmed: [], receipts: [] },
			receiptsItem: null,
		}
	},

	computed: {
		/** @return {Array<object>} News items, newest first, filtered and limited. */
		shown() {
			const limit = Number(this.content?.limit) > 0 ? Number(this.content.limit) : 10
			return this.announcements
				.filter((item) => item.kind !== 'notice')
				.filter((item) => this.category === '' || item.category === this.category)
				.slice(0, limit)
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

	/** @spec openspec/changes/engagement-announcements/tasks.md#task-7 */
	created() {
		this.load()
	},

	methods: {
		t,

		/**
		 * @spec openspec/changes/engagement-announcements/tasks.md#task-7
		 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
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
				this.readConfirmations = readConfirmationState(readState)
				this.error = ''
			} catch (error) {
				logger.error('Failed to load announcements:', error)
				this.error = t('launchpad', 'Announcements could not be loaded')
			} finally {
				this.loading = false
			}
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
		 * @spec openspec/changes/engagement-announcements/tasks.md#task-7
		 */
		async toggleLike(uuid) {
			const current = this.reactionsOf(uuid)
			try {
				const state = current.liked ? await announcementsApi.unlike(uuid) : await announcementsApi.like(uuid)
				this.reactions = { ...this.reactions, [uuid]: { ...current, ...state } }
			} catch (error) {
				logger.error('Failed to change the like:', error)
			}
		},

		/**
		 * @param {string} uuid The announcement.
		 * @spec openspec/changes/engagement-announcements/tasks.md#task-7
		 */
		async openComments(uuid) {
			if (this.openUuid === uuid) {
				this.openUuid = ''
				return
			}
			this.openUuid = uuid
			this.draftComment = ''
			try {
				this.comments = await announcementsApi.comments(uuid)
			} catch (error) {
				logger.error('Failed to load comments:', error)
				this.comments = []
			}
		},

		/**
		 * @param {string} uuid The announcement.
		 * @spec openspec/changes/engagement-announcements/tasks.md#task-7
		 */
		async sendComment(uuid) {
			const message = this.draftComment.trim()
			if (message === '') {
				return
			}
			try {
				const comment = await announcementsApi.comment(uuid, message)
				this.comments = [...this.comments, comment]
				const current = this.reactionsOf(uuid)
				this.reactions = { ...this.reactions, [uuid]: { ...current, comments: current.comments + 1 } }
				this.draftComment = ''
			} catch (error) {
				logger.error('Failed to send the comment:', error)
			}
		},

		/**
		 * @param {string} category The category.
		 * @return {boolean}
		 */
		isFollowed(category) {
			return this.followed.includes(category)
		},

		/**
		 * @param {string} category The category.
		 * @spec openspec/changes/engagement-announcements/tasks.md#task-10
		 */
		async toggleFollow(category) {
			try {
				this.followed = this.isFollowed(category)
					? await announcementsApi.unfollow(category)
					: await announcementsApi.follow(category)
			} catch (error) {
				logger.error('Failed to change the follow:', error)
			}
		},

		/**
		 * "I have read this": confirm, then the card says so.
		 *
		 * @param {string} uuid The announcement.
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
		 */
		async confirmRead(uuid) {
			try {
				await announcementsApi.confirmRead(uuid)
				if (!this.readConfirmations.confirmed.includes(uuid)) {
					this.readConfirmations = { ...this.readConfirmations, confirmed: [...this.readConfirmations.confirmed, uuid] }
				}
			} catch (error) {
				logger.error('Failed to confirm the announcement:', error)
				this.error = t('launchpad', 'Your read confirmation could not be saved')
			}
		},

		/**
		 * Open the read confirmations of one announcement.
		 *
		 * @param {string} uuid The announcement.
		 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
		 */
		openReceipts(uuid) {
			this.receiptsItem = this.announcements.find((item) => item.uuid === uuid) ?? null
		},

		/** @spec openspec/changes/engagement-announcements/tasks.md#task-8 */
		onPublished() {
			this.editorOpen = false
			this.load()
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

.announcements-widget__comment-form {
	display: flex;
	align-items: flex-end;
	gap: 8px;
}
</style>
