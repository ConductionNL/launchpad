<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div v-if="shown.length > 0" class="notice-region">
		<NoticeBanner
			v-for="notice in shown"
			:key="notice.uuid"
			:notice="notice"
			@dismiss="dismiss" />
	</div>
</template>

<script>
import NoticeBanner from './NoticeBanner.vue'
import {
	getDismissedNotices,
	listAnnouncements,
	saveDismissedNotices,
} from '../../services/announcements.js'
import { logger } from '../../utils/logger.js'

/**
 * NoticeRegion: the notices that target the reader, above the dashboard
 * grid on whichever dashboard they open (REQ-ANN-005). The server decides
 * targeting and the period; a dismissed notice is remembered per person.
 */
export default {
	name: 'NoticeRegion',

	components: { NoticeBanner },

	data() {
		return {
			notices: [],
			dismissed: [],
		}
	},

	computed: {
		/** @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005 */
		shown() {
			return this.notices.filter(
				(notice) =>
					!(
						notice.dismissible === true
						&& this.dismissed.includes(notice.uuid)
					),
			)
		},
	},

	/** @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005 */
	async mounted() {
		try {
			const [data, dismissed] = await Promise.all([
				listAnnouncements({ kind: 'notice' }),
				getDismissedNotices(),
			])
			this.notices = data.announcements || []
			this.dismissed = dismissed
		} catch (error) {
			logger.error('Loading notices failed', { error })
		}
	},

	methods: {
		/**
		 * Hide a dismissible notice for this person and remember it. Only
		 * uuids of notices still running are kept, so the list stays small.
		 *
		 * @param {string} uuid The notice.
		 * @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
		 */
		async dismiss(uuid) {
			const running = this.notices.map((notice) => notice.uuid)
			this.dismissed = [...new Set([...this.dismissed, uuid])].filter((id) =>
				running.includes(id),
			)
			try {
				await saveDismissedNotices(this.dismissed)
			} catch (error) {
				logger.error('Saving a dismissed notice failed', { error })
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
