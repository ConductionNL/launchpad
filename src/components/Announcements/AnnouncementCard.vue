<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<article class="announcement-card" data-test="announcement-card">
		<header class="announcement-card__header">
			<span v-if="announcement.category" class="announcement-card__category">{{ announcement.category }}</span>
			<time v-if="announcement.publishAt" class="announcement-card__date" :datetime="announcement.publishAt">
				{{ formattedDate }}
			</time>
		</header>
		<h4 class="announcement-card__title">
			{{ announcement.title }}
		</h4>
		<p v-if="announcement.body" class="announcement-card__body">
			{{ announcement.body }}
		</p>
		<footer class="announcement-card__footer">
			<NcButton
				variant="tertiary"
				:pressed="reactions.liked"
				:disabled="preview"
				:aria-label="likeLabel"
				data-test="announcement-like"
				@click="$emit('toggleLike', announcement.uuid)">
				<template #icon>
					<ThumbUp v-if="reactions.liked" :size="18" />
					<ThumbUpOutline v-else :size="18" />
				</template>
				{{ reactions.likes }}
			</NcButton>
			<NcButton
				variant="tertiary"
				:disabled="preview"
				:aria-label="commentLabel"
				data-test="announcement-comments"
				@click="$emit('openComments', announcement.uuid)">
				<template #icon>
					<CommentOutline :size="18" />
				</template>
				{{ reactions.comments }}
			</NcButton>
		</footer>
		<slot />
	</article>
</template>

<script>
import { n, t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import CommentOutline from 'vue-material-design-icons/CommentOutline.vue'
import ThumbUp from 'vue-material-design-icons/ThumbUp.vue'
import ThumbUpOutline from 'vue-material-design-icons/ThumbUpOutline.vue'

/**
 * AnnouncementCard — one news announcement as readers see it in the
 * Announcements widget, with its like and comment counts (REQ-ANN-002). The
 * editor's preview renders this same component with `preview` set
 * (REQ-ANN-004).
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
 */
export default {
	name: 'AnnouncementCard',

	components: { CommentOutline, NcButton, ThumbUp, ThumbUpOutline },

	props: {
		/** The announcement, as the announcements API returns it. */
		announcement: {
			type: Object,
			required: true,
		},

		/** `{likes, comments, liked}` for this announcement. */
		reactions: {
			type: Object,
			default: () => ({ likes: 0, comments: 0, liked: false }),
		},

		/** Rendered in the editor's preview: the buttons are inert. */
		preview: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['toggleLike', 'openComments'],

	computed: {
		/** @return {string} The publish date in the reader's locale. */
		formattedDate() {
			const date = new Date(this.announcement.publishAt)
			return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
		},

		/** @return {string} */
		likeLabel() {
			return this.reactions.liked
				? t('launchpad', 'Unlike {title}', { title: this.announcement.title })
				: t('launchpad', 'Like {title}', { title: this.announcement.title })
		},

		/** @return {string} */
		commentLabel() {
			return n('launchpad', '{count} comment', '{count} comments', this.reactions.comments, { count: this.reactions.comments })
		},
	},
}
</script>

<style scoped>
.announcement-card {
	padding: 12px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	background: var(--color-main-background);
}

.announcement-card__header {
	display: flex;
	justify-content: space-between;
	gap: 8px;
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}

.announcement-card__category {
	font-weight: bold;
}

.announcement-card__title {
	margin: 4px 0;
}

.announcement-card__body {
	white-space: pre-line;
}

.announcement-card__footer {
	display: flex;
	gap: 4px;
}
</style>
