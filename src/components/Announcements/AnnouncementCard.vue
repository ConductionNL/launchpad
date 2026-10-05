<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<article class="announcement-card">
		<header class="announcement-card__header">
			<h4 class="announcement-card__title">
				{{ item.title }}
			</h4>
			<p class="announcement-card__meta">
				<span v-if="item.category" class="announcement-card__category">{{ item.category }}</span>
				<span v-if="publishedLabel">{{ publishedLabel }}</span>
			</p>
		</header>

		<p v-if="item.body" class="announcement-card__body">
			{{ item.body }}
		</p>

		<footer class="announcement-card__actions">
			<NcButton
				variant="tertiary"
				:disabled="preview"
				:pressed="item.likedByMe === true"
				:aria-label="t('launchpad', 'Like')"
				@click="$emit('like', item)">
				<template #icon>
					<ThumbUpIcon v-if="item.likedByMe" :size="18" />
					<ThumbUpOutlineIcon v-else :size="18" />
				</template>
				{{ item.likeCount || 0 }}
			</NcButton>
			<NcButton
				variant="tertiary"
				:disabled="preview"
				:aria-expanded="commentsOpen ? 'true' : 'false'"
				:aria-label="t('launchpad', 'Comments')"
				@click="toggleComments">
				<template #icon>
					<CommentOutlineIcon :size="18" />
				</template>
				{{ item.commentCount || 0 }}
			</NcButton>
			<NcButton
				v-if="item.category && !preview"
				variant="tertiary"
				@click="$emit('follow', item.category, !following)">
				{{ following
					? t('launchpad', 'Stop following {category}', { category: item.category })
					: t('launchpad', 'Follow {category}', { category: item.category }) }}
			</NcButton>
		</footer>

		<div v-if="commentsOpen" class="announcement-card__comments">
			<ul class="announcement-card__comment-list">
				<li v-for="comment in comments" :key="comment.id">
					<strong>{{ comment.displayName }}</strong>
					{{ comment.message }}
				</li>
			</ul>
			<form v-if="item.allowComments" class="announcement-card__comment-form" @submit.prevent="sendComment">
				<NcTextField
					v-model="draft"
					:label="t('launchpad', 'Write a comment')"
					:maxlength="1000" />
				<NcButton type="submit" :disabled="draft.trim() === ''">
					{{ t('launchpad', 'Send') }}
				</NcButton>
			</form>
		</div>
	</article>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcTextField } from '@nextcloud/vue'
import CommentOutlineIcon from 'vue-material-design-icons/CommentOutline.vue'
import ThumbUpIcon from 'vue-material-design-icons/ThumbUp.vue'
import ThumbUpOutlineIcon from 'vue-material-design-icons/ThumbUpOutline.vue'
import { commentOnAnnouncement, listAnnouncementComments } from '../../services/announcements.js'
import { logger } from '../../utils/logger.js'

/**
 * AnnouncementCard: one news item with its like and comment counts
 * (REQ-ANN-002) and a follow button for its category (REQ-ANN-003). The
 * editor's preview renders this same card with `preview` set.
 */
export default {
	name: 'AnnouncementCard',

	components: { NcButton, NcTextField, CommentOutlineIcon, ThumbUpIcon, ThumbUpOutlineIcon },

	props: {
		/** The announcement as the API returns it. */
		item: {
			type: Object,
			required: true,
		},

		/** Whether the reader follows the item's category. */
		following: {
			type: Boolean,
			default: false,
		},

		/** Render without actions (the editor preview). */
		preview: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['like', 'follow', 'commented'],

	data() {
		return {
			commentsOpen: false,
			comments: [],
			draft: '',
		}
	},

	computed: {
		/** @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001 */
		publishedLabel() {
			if (!this.item.publishAt) {
				return ''
			}
			const date = new Date(this.item.publishAt.replace(' ', 'T') + 'Z')
			return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
		},
	},

	methods: {
		t,

		/**
		 * Open or close the comments, loading them on open.
		 *
		 * @spec openspec/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
		 */
		async toggleComments() {
			this.commentsOpen = !this.commentsOpen
			if (!this.commentsOpen) {
				return
			}
			try {
				this.comments = await listAnnouncementComments(this.item.uuid)
			} catch (error) {
				logger.error('Loading announcement comments failed', { error })
			}
		},

		/**
		 * Send the comment and show it.
		 *
		 * @spec openspec/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
		 */
		async sendComment() {
			const message = this.draft.trim()
			if (message === '') {
				return
			}
			try {
				const comment = await commentOnAnnouncement(this.item.uuid, message)
				this.comments.push(comment)
				this.draft = ''
				this.$emit('commented', this.item)
			} catch (error) {
				logger.error('Sending an announcement comment failed', { error })
			}
		},
	},
}
</script>

<style scoped>
.announcement-card {
	display: flex;
	flex-direction: column;
	gap: 6px;
	padding: 8px 0;
	border-bottom: 1px solid var(--color-border);
}

.announcement-card__title {
	margin: 0;
	font-size: 1em;
	font-weight: bold;
}

.announcement-card__meta {
	display: flex;
	gap: 8px;
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.9em;
}

.announcement-card__category {
	padding: 0 6px;
	border-radius: var(--border-radius-pill);
	background-color: var(--color-background-dark);
	color: var(--color-main-text);
}

.announcement-card__body {
	margin: 0;
	white-space: pre-line;
}

.announcement-card__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
}

.announcement-card__comment-list {
	margin: 0 0 6px;
	padding: 0;
	list-style: none;
}

.announcement-card__comment-form {
	display: flex;
	align-items: flex-end;
	gap: 6px;
}
</style>
