<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div v-if="visible.length > 0" class="notice-region" data-test="notice-region">
		<NoticeBanner
			v-for="notice in visible"
			:key="notice.uuid"
			:notice="notice"
			@dismiss="dismiss" />
	</div>
</template>

<script>
import NoticeBanner from './NoticeBanner.vue'
import { announcementsApi } from '../../services/announcementsApi.js'
import { logger } from '../../utils/logger.js'

/** How often the region re-checks end times, in milliseconds. */
export const TICK_MS = 60000

/**
 * NoticeBannerRegion — the notices that reach the reader, above the
 * dashboard grid whichever dashboard is open (REQ-ANN-005, D1). The server
 * already limits the list to the reader's notices inside their period; the
 * region also drops a notice once its end time passes while the page stays
 * open, and remembers dismissed notices in the reader's preferences. A
 * non-dismissible notice has no close button and stays until its end time.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
 */
export default {
	name: 'NoticeBannerRegion',

	components: { NoticeBanner },

	data() {
		return {
			notices: [],
			dismissed: [],
			now: Date.now(),
			timer: null,
		}
	},

	computed: {
		/** @return {Array<object>} The notices to show now. */
		visible() {
			return this.notices.filter((notice) => {
				const ends = Date.parse(notice.expiresAt ?? '')
				if (!Number.isNaN(ends) && ends <= this.now) {
					return false
				}
				return !(notice.dismissible && this.dismissed.includes(notice.uuid))
			})
		},
	},

	/** @spec openspec/changes/engagement-announcements/tasks.md#task-9 */
	created() {
		this.load()
		this.timer = setInterval(() => {
			this.now = Date.now()
		}, TICK_MS)
	},

	/** @spec openspec/changes/engagement-announcements/tasks.md#task-9 */
	beforeUnmount() {
		clearInterval(this.timer)
	},

	methods: {
		/** @spec openspec/changes/engagement-announcements/tasks.md#task-9 */
		async load() {
			try {
				const data = await announcementsApi.list()
				const all = Array.isArray(data.announcements) ? data.announcements : []
				this.notices = all.filter((item) => item.kind === 'notice')
			} catch (error) {
				logger.error('Failed to load notices:', error)
				this.notices = []
				return
			}
			try {
				this.dismissed = await announcementsApi.dismissedNotices()
			} catch (error) {
				logger.error('Failed to load dismissed notices:', error)
			}
		},

		/**
		 * @param {string} uuid The notice.
		 * @spec openspec/changes/engagement-announcements/tasks.md#task-9
		 */
		async dismiss(uuid) {
			const notice = this.notices.find((item) => item.uuid === uuid)
			if (!notice || !notice.dismissible) {
				return
			}
			// Keep only the dismissed notices that are still live, so the preference stays small.
			const live = new Set(this.notices.map((item) => item.uuid))
			this.dismissed = [...new Set([...this.dismissed.filter((id) => live.has(id)), uuid])]
			try {
				await announcementsApi.saveDismissedNotices(this.dismissed)
			} catch (error) {
				logger.error('Failed to save the dismissed notice:', error)
			}
		},
	},
}
</script>

<style scoped>
.notice-region {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin: 8px 16px 0;
}
</style>
