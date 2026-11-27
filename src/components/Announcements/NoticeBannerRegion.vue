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
			:read="confirmed.includes(notice.uuid)"
			@confirm="confirm" />
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
 * dashboard grid whichever dashboard is open (REQ-ANN-005). The server
 * already limits the list to the reader's notices inside their period; the
 * region also drops a notice once its end time passes while the page stays
 * open. Every notice has the check and the x (announcements-managed-in-settings
 * D4): the check confirms it and keeps it shown, marked read; the x confirms
 * it and hides it for this reader. Both are stored as the reader's receipt.
 * Once the notices are on screen they are recorded as seen, in one call after
 * render. Notices closed under the former per-user preference stay hidden.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
 */
export default {
	name: 'NoticeBannerRegion',
	components: { NoticeBanner },
	data() {
		return {
			notices: [],
			dismissed: [],
			confirmed: [],
			closed: [],
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
				return !this.closed.includes(notice.uuid) && !this.dismissed.includes(notice.uuid)
			})
		},
	},

	/** @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-8 */
	created() {
		this.load()
		this.timer = setInterval(() => {
			this.now = Date.now()
		}, TICK_MS)
	},

	/** @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-8 */
	beforeUnmount() {
		clearInterval(this.timer)
	},

	methods: {
		/** @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-8 */
		async load() {
			// All at once: the banner is often the largest paint of the page,
			// and asking one after the other put a round trip before it.
			const dismissed = announcementsApi.dismissedNotices().catch((error) => {
				logger.error('Failed to load dismissed notices:', error)
				return null
			})
			const state = announcementsApi.readState().catch((error) => {
				logger.error('Failed to load the read state of the notices:', error)
				return null
			})
			try {
				const data = await announcementsApi.list()
				const all = Array.isArray(data.announcements) ? data.announcements : []
				this.notices = all.filter((item) => item.kind === 'notice')
			} catch (error) {
				logger.error('Failed to load notices:', error)
				this.notices = []
				return
			}
			const [ids, read] = await Promise.all([dismissed, state])
			if (ids !== null) {
				this.dismissed = ids
			}
			if (read !== null) {
				this.confirmed = Array.isArray(read.confirmed) ? read.confirmed : []
				this.closed = Array.isArray(read.closed) ? read.closed : []
			}
			this.$nextTick(() => this.markSeen())
		},

		/**
		 * Record the notices on screen as seen, after render, without waiting.
		 *
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
		 */
		markSeen() {
			const uuids = this.visible.map((notice) => notice.uuid)
			if (uuids.length === 0) {
				return
			}
			announcementsApi.markSeen(uuids).catch((error) => {
				logger.error('Failed to record the notices as seen:', error)
			})
		},

		/**
		 * The check or the x on a notice: both confirm; the x also hides it.
		 *
		 * @param {string} uuid The notice.
		 * @param {string} via `check` or `x`.
		 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
		 */
		async confirm(uuid, via) {
			if (!this.notices.some((item) => item.uuid === uuid)) {
				return
			}
			if (!this.confirmed.includes(uuid)) {
				this.confirmed = [...this.confirmed, uuid]
			}
			if (via === 'x' && !this.closed.includes(uuid)) {
				this.closed = [...this.closed, uuid]
			}
			try {
				await announcementsApi.confirmRead(uuid, via)
			} catch (error) {
				logger.error('Failed to confirm the notice:', error)
			}
		},
	},
}
</script>

<style scoped>
.notice-region {
	display: flex;
	flex-direction: column;
}
</style>
