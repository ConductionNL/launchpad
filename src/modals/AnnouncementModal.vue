<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcModal
		v-if="announcement"
		size="normal"
		:name="announcement.title"
		data-test="announcement-modal"
		@close="$emit('close')">
		<article class="announcement-modal">
			<header class="announcement-modal__header">
				<span v-if="announcement.category" class="announcement-modal__category">{{ announcement.category }}</span>
				<time v-if="announcement.publishAt" :datetime="announcement.publishAt">{{ formattedDate }}</time>
			</header>
			<h2 class="announcement-modal__title">
				{{ announcement.title }}
			</h2>
			<p v-if="announcement.body" class="announcement-modal__body" data-test="announcement-modal-body">
				{{ announcement.body }}
			</p>

			<!-- REQ-ANN-007: the read row, as in the widget. -->
			<div class="announcement-modal__read-row">
				<p v-if="announcement.requiresConfirmation && confirmBeforeText" class="announcement-modal__deadline">
					{{ confirmBeforeText }}
				</p>
				<NcButton
					:variant="confirmed ? 'secondary' : 'primary'"
					:pressed="confirmed"
					data-test="announcement-modal-check"
					@click="$emit('confirm', announcement.uuid, 'check')">
					<template #icon>
						<CheckCircle v-if="confirmed" :size="20" />
						<CheckCircleOutline v-else :size="20" />
					</template>
					{{ confirmed ? t('launchpad', 'Read') : t('launchpad', 'Mark as read') }}
				</NcButton>
				<NcButton variant="tertiary" data-test="announcement-modal-x" @click="$emit('confirm', announcement.uuid, 'x')">
					<template #icon>
						<Close :size="20" />
					</template>
					{{ t('launchpad', 'Close') }}
				</NcButton>
				<span class="announcement-modal__spacer" />
				<NcButton v-if="manageUrl" variant="tertiary" :href="manageUrl" data-test="announcement-modal-manage">
					{{ t('launchpad', 'Manage') }}
				</NcButton>
			</div>

			<!-- REQ-ANN-002, REQ-ANN-003: likes, comments and follow, when the author allowed them. -->
			<section v-if="announcement.allowComments" class="announcement-modal__social" data-test="announcement-modal-social">
				<div class="announcement-modal__actions">
					<NcButton
						variant="tertiary"
						:pressed="reactions.liked"
						:aria-label="reactions.liked ? t('launchpad', 'Unlike {title}', { title: announcement.title }) : t('launchpad', 'Like {title}', { title: announcement.title })"
						data-test="announcement-like"
						@click="toggleLike">
						<template #icon>
							<ThumbUp v-if="reactions.liked" :size="18" />
							<ThumbUpOutline v-else :size="18" />
						</template>
						{{ reactions.likes }}
					</NcButton>
					<span class="announcement-modal__count" data-test="announcement-comment-count">
						<CommentOutline :size="18" />
						{{ n('launchpad', '%n comment', '%n comments', reactions.comments) }}
					</span>
					<NcButton
						v-if="announcement.category"
						variant="secondary"
						data-test="announcements-follow"
						@click="toggleFollow">
						{{ isFollowed ? t('launchpad', 'Unfollow {category}', { category: announcement.category }) : t('launchpad', 'Follow {category}', { category: announcement.category }) }}
					</NcButton>
				</div>
				<ul class="announcement-modal__comments" data-test="announcement-comment-list">
					<li v-for="comment in comments" :key="comment.id">
						<strong>{{ comment.displayName }}</strong> {{ comment.message }}
					</li>
				</ul>
				<form class="announcement-modal__comment-form" @submit.prevent="sendComment">
					<NcTextField
						v-model="draftComment"
						:label="t('launchpad', 'Write a comment')"
						:maxlength="1000"
						data-test="announcement-comment-input" />
					<NcButton type="submit" variant="primary" :disabled="draftComment.trim() === ''">
						{{ t('launchpad', 'Send') }}
					</NcButton>
				</form>
			</section>
		</article>
	</NcModal>
</template>

<script>
import { getCanonicalLocale, n, t } from '@nextcloud/l10n'
import { NcButton, NcModal, NcTextField } from '@nextcloud/vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'
import CommentOutline from 'vue-material-design-icons/CommentOutline.vue'
import ThumbUp from 'vue-material-design-icons/ThumbUp.vue'
import ThumbUpOutline from 'vue-material-design-icons/ThumbUpOutline.vue'
import { confirmBeforeLabel } from '../components/Announcements/AnnouncementCard.vue'
import { announcementsApi } from '../services/announcementsApi.js'
import { logger } from '../utils/logger.js'

/**
 * AnnouncementModal — an announcement's own view (REQ-ANN-011), opened with
 * Open on a widget item: the full text, the read row (REQ-ANN-007) and, when
 * the author allowed them, likes, comments through Nextcloud's comments
 * service (REQ-ANN-002) and follow for its category (REQ-ANN-003); for a
 * manager also Manage. The check and the x emit `confirm` with the kind;
 * like, comment and follow are sent from here and reported with
 * `update:reactions` and `update:followed`.
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
 */
export default {
	name: 'AnnouncementModal',

	components: { CheckCircle, CheckCircleOutline, Close, CommentOutline, NcButton, NcModal, NcTextField, ThumbUp, ThumbUpOutline },

	props: {
		/** The announcement, or null when the view is closed. */
		announcement: {
			type: Object,
			default: null,
		},

		/** `{likes, comments, liked}`. */
		reactions: {
			type: Object,
			default: () => ({ likes: 0, comments: 0, liked: false }),
		},

		/** The categories the reader follows. */
		followed: {
			type: Array,
			default: () => [],
		},

		/** Whether the reader confirmed it. */
		confirmed: {
			type: Boolean,
			default: false,
		},

		/** For a manager: the Announcements tab on this announcement. */
		manageUrl: {
			type: String,
			default: '',
		},
	},

	emits: ['close', 'confirm', 'update:reactions', 'update:followed'],

	data() {
		return {
			comments: [],
			draftComment: '',
		}
	},

	computed: {
		/**
		 * @return {string} The publish date in the reader's locale.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		formattedDate() {
			const date = new Date(this.announcement?.publishAt ?? '')
			return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
		},

		/**
		 * @return {string} "Confirm before 30 October", or ''.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		confirmBeforeText() {
			return confirmBeforeLabel(this.announcement?.confirmBefore ?? null, getCanonicalLocale())
		},

		/**
		 * @return {boolean} Whether the reader follows the category.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		isFollowed() {
			return this.followed.includes(this.announcement?.category)
		},
	},

	watch: {
		/**
		 * Load the comments each time another announcement opens.
		 *
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		'announcement.uuid': {
			immediate: true,
			/** @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011 */
			handler() {
				this.comments = []
				this.draftComment = ''
				this.loadComments()
			},
		},
	},

	methods: {
		t,
		n,

		/**
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		async loadComments() {
			if (!this.announcement?.allowComments) {
				return
			}
			try {
				this.comments = await announcementsApi.comments(this.announcement.uuid)
			} catch (error) {
				logger.error('Failed to load comments:', error)
				this.comments = []
			}
		},

		/**
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		async toggleLike() {
			const uuid = this.announcement.uuid
			try {
				const state = this.reactions.liked ? await announcementsApi.unlike(uuid) : await announcementsApi.like(uuid)
				this.$emit('update:reactions', uuid, { ...this.reactions, ...state })
			} catch (error) {
				logger.error('Failed to change the like:', error)
			}
		},

		/**
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		async sendComment() {
			const message = this.draftComment.trim()
			if (message === '') {
				return
			}
			try {
				const comment = await announcementsApi.comment(this.announcement.uuid, message)
				this.comments = [...this.comments, comment]
				this.draftComment = ''
				this.$emit('update:reactions', this.announcement.uuid, { ...this.reactions, comments: (this.reactions.comments ?? 0) + 1 })
			} catch (error) {
				logger.error('Failed to send the comment:', error)
			}
		},

		/**
		 * @return {Promise<void>}
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-has-its-own-view-req-ann-011
		 */
		async toggleFollow() {
			const category = this.announcement.category
			try {
				const followed = this.isFollowed
					? await announcementsApi.unfollow(category)
					: await announcementsApi.follow(category)
				this.$emit('update:followed', followed)
			} catch (error) {
				logger.error('Failed to change the follow:', error)
			}
		},
	},
}
</script>

<style scoped>
.announcement-modal {
	display: flex;
	flex-direction: column;
	gap: 12px;
	padding: 20px;
}

.announcement-modal__header {
	display: flex;
	justify-content: space-between;
	gap: 8px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.announcement-modal__category {
	font-weight: bold;
}

.announcement-modal__title {
	margin: 0;
}

.announcement-modal__body {
	white-space: pre-line;
}

.announcement-modal__read-row,
.announcement-modal__actions {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
}

.announcement-modal__deadline {
	flex-basis: 100%;
	margin: 0;
	font-weight: bold;
}

.announcement-modal__spacer {
	flex: 1;
}

.announcement-modal__count {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.announcement-modal__comments {
	padding: 0;
	list-style: none;
}

.announcement-modal__comment-form {
	display: flex;
	align-items: flex-end;
	gap: 8px;
}
</style>
