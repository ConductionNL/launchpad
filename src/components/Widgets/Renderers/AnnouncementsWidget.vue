<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="announcements-widget">
		<div class="announcements-widget__toolbar">
			<NcSelect
				v-if="categories.length > 0"
				v-model="category"
				:options="categoryOptions"
				:reduce="(option) => option.id"
				label="label"
				:inputLabel="t('launchpad', 'Category')"
				:clearable="false" />
			<NcButton
				v-if="canAuthor"
				variant="secondary"
				@click="editorOpen = true">
				{{ t('launchpad', 'New announcement') }}
			</NcButton>
		</div>

		<p v-if="loading" class="announcements-widget__state">
			{{ t('launchpad', 'Loading announcements…') }}
		</p>
		<p v-else-if="loadFailed" class="announcements-widget__state" role="alert">
			{{ t('launchpad', 'The announcements could not be loaded.') }}
		</p>
		<p v-else-if="visibleItems.length === 0" class="announcements-widget__state">
			{{ t('launchpad', 'No announcements for you yet.') }}
		</p>
		<div v-else class="announcements-widget__list">
			<AnnouncementCard
				v-for="item in visibleItems"
				:key="item.uuid"
				:item="item"
				:following="following.includes(item.category)"
				@like="toggleLike"
				@follow="toggleFollow"
				@commented="onCommented" />
		</div>

		<AnnouncementEditorModal
			v-if="editorOpen"
			@close="editorOpen = false"
			@published="load" />
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcSelect } from '@nextcloud/vue'
import AnnouncementEditorModal from '../../../modals/AnnouncementEditorModal.vue'
import AnnouncementCard from '../../Announcements/AnnouncementCard.vue'
import {
	followCategory,
	likeAnnouncement,
	listAnnouncements,
} from '../../../services/announcements.js'
import { logger } from '../../../utils/logger.js'

const ALL = ''
const DEFAULT_LIMIT = 5
const MAX_LIMIT = 20

/**
 * AnnouncementsWidget: the news items that target the reader, newest
 * first, with a category filter, like and comment counts and a follow
 * button per category (REQ-ANN-001..003). Authors get "New announcement".
 */
export default {
	name: 'AnnouncementsWidget',

	components: { AnnouncementCard, AnnouncementEditorModal, NcButton, NcSelect },

	props: {
		/** Persisted widget content: `{ limit }`. */
		content: {
			type: Object,
			default: () => ({}),
		},
	},

	data() {
		return {
			loading: true,
			loadFailed: false,
			items: [],
			following: [],
			canAuthor: false,
			category: ALL,
			editorOpen: false,
		}
	},

	computed: {
		/** @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001 */
		limit() {
			const limit = Number(this.content?.limit)
			if (!Number.isInteger(limit) || limit < 1) {
				return DEFAULT_LIMIT
			}
			return Math.min(limit, MAX_LIMIT)
		},

		/** @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001 */
		categories() {
			return [
				...new Set(this.items.map((item) => item.category).filter(Boolean)),
			].sort()
		},

		/** @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001 */
		categoryOptions() {
			return [
				{ id: ALL, label: t('launchpad', 'All categories') },
				...this.categories.map((category) => ({
					id: category,
					label: category,
				})),
			]
		},

		/** @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001 */
		visibleItems() {
			const filtered =
				this.category === ALL
					? this.items
					: this.items.filter((item) => item.category === this.category)
			return filtered.slice(0, this.limit)
		},
	},

	/** @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001 */
	mounted() {
		this.load()
	},

	methods: {
		t,

		/**
		 * Load the news items the reader sees.
		 *
		 * @spec openspec/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
		 */
		async load() {
			this.loading = true
			this.loadFailed = false
			try {
				const data = await listAnnouncements({ kind: 'news' })
				this.items = data.announcements || []
				this.following = data.following || []
				this.canAuthor = data.canAuthor === true
			} catch (error) {
				this.loadFailed = true
				logger.error('Loading announcements failed', { error })
			} finally {
				this.loading = false
			}
		},

		/**
		 * Like or take back the like, then show the fresh counts.
		 *
		 * @param {object} item The announcement.
		 * @spec openspec/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
		 */
		async toggleLike(item) {
			try {
				const fresh = await likeAnnouncement(
					item.uuid,
					item.likedByMe !== true,
				)
				this.replace(fresh)
			} catch (error) {
				logger.error('Liking an announcement failed', { error })
			}
		},

		/**
		 * Count a new comment on the card.
		 *
		 * @param {object} item The announcement.
		 * @spec openspec/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
		 */
		onCommented(item) {
			this.replace({ ...item, commentCount: (item.commentCount || 0) + 1 })
		},

		/**
		 * Follow or stop following a category.
		 *
		 * @param {string} category The category.
		 * @param {boolean} follow True to follow.
		 * @spec openspec/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
		 */
		async toggleFollow(category, follow) {
			try {
				this.following = await followCategory(category, follow)
			} catch (error) {
				logger.error('Following a category failed', { error })
			}
		},

		/**
		 * Swap one item for its fresh copy.
		 *
		 * @param {object} fresh The announcement.
		 * @spec openspec/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
		 */
		replace(fresh) {
			this.items = this.items.map((item) =>
				item.uuid === fresh.uuid ? fresh : item,
			)
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
	overflow-y: auto;
}

.announcements-widget__toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
}

.announcements-widget__state {
	margin: 0;
	color: var(--color-text-maxcontrast);
}
</style>
