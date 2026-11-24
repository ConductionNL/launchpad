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
		<!-- REQ-ANN-007: the read confirmation, "I have read this" until the reader confirmed. -->
		<div v-if="announcement.requiresConfirmation" class="announcement-card__confirmation" data-test="announcement-confirmation">
			<p v-if="confirmBeforeText" class="announcement-card__deadline" data-test="announcement-confirm-before">
				{{ confirmBeforeText }}
			</p>
			<p v-if="confirmed" class="announcement-card__confirmed" data-test="announcement-confirmed">
				<CheckCircle :size="18" />
				{{ t('launchpad', 'You confirmed you have read this') }}
			</p>
			<NcButton
				v-else
				variant="primary"
				:disabled="preview"
				data-test="announcement-confirm-read"
				@click="$emit('confirmRead', announcement.uuid)">
				{{ t('launchpad', 'I have read this') }}
			</NcButton>
			<NcButton
				v-if="canReport"
				variant="tertiary"
				data-test="announcement-receipts"
				@click="$emit('openReceipts', announcement.uuid)">
				{{ t('launchpad', 'Read confirmations') }}
			</NcButton>
		</div>
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
import { getCanonicalLocale, n, t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import CommentOutline from 'vue-material-design-icons/CommentOutline.vue'
import ThumbUp from 'vue-material-design-icons/ThumbUp.vue'
import ThumbUpOutline from 'vue-material-design-icons/ThumbUpOutline.vue'

/**
 * "Confirm before 30 October" in the reader's language, or '' without a
 * deadline (REQ-ANN-007).
 *
 * @param {string|null} confirmBefore The deadline, ISO 8601.
 * @param {string} [locale] BCP 47 locale.
 * @return {string}
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
export function confirmBeforeLabel(confirmBefore, locale) {
	const date = confirmBefore ? new Date(confirmBefore) : null
	if (date === null || Number.isNaN(date.getTime())) {
		return ''
	}
	return t('launchpad', 'Confirm before {date}', { date: date.toLocaleDateString(locale, { day: 'numeric', month: 'long' }) })
}

/**
 * AnnouncementCard — one news announcement as readers see it in the
 * Announcements widget, with its like and comment counts (REQ-ANN-002). The
 * editor's preview renders this same component with `preview` set
 * (REQ-ANN-004). An announcement that asks for a read confirmation shows
 * "I have read this" until the reader confirmed, the date to confirm before,
 * and, for its author, Read confirmations (REQ-ANN-007).
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
export default {
	name: 'AnnouncementCard',

	components: { CheckCircle, CommentOutline, NcButton, ThumbUp, ThumbUpOutline },

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

		/** Whether the reader already confirmed they have read it (REQ-ANN-007). */
		confirmed: {
			type: Boolean,
			default: false,
		},

		/** Whether the reader may open its read confirmations: its author or an administrator. */
		canReport: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['toggleLike', 'openComments', 'confirmRead', 'openReceipts'],

	computed: {
		/** @return {string} The publish date in the reader's locale. */
		formattedDate() {
			const date = new Date(this.announcement.publishAt)
			return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
		},

		/**
		 * @return {string} "Confirm before 30 October", or '' without a deadline.
		 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
		 */
		confirmBeforeText() {
			return confirmBeforeLabel(this.announcement.confirmBefore, getCanonicalLocale())
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

	methods: {
		t,
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

.announcement-card__confirmation {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	margin: 8px 0;
}

.announcement-card__deadline {
	flex-basis: 100%;
	margin: 0;
	font-weight: bold;
}

.announcement-card__confirmed {
	display: flex;
	align-items: center;
	gap: 4px;
	margin: 0;
	color: var(--color-success-text, var(--color-success));
}

.announcement-card__footer {
	display: flex;
	gap: 4px;
}
</style>
