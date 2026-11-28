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
		<!-- REQ-ANN-007, REQ-ANN-010: the read row (check and x), the deadline,
		     Open (the announcement's own view) and, for a manager, Manage. -->
		<footer class="announcement-card__footer" data-test="announcement-read-row">
			<p v-if="announcement.requiresConfirmation && confirmBeforeText" class="announcement-card__deadline" data-test="announcement-confirm-before">
				{{ confirmBeforeText }}
			</p>
			<NcButton
				variant="tertiary"
				:pressed="confirmed"
				:disabled="preview"
				:aria-label="confirmed ? t('launchpad', 'Read') : t('launchpad', 'Mark as read')"
				:title="confirmed ? t('launchpad', 'Read') : t('launchpad', 'Mark as read')"
				data-test="announcement-confirm-read"
				@click="$emit('confirm', announcement.uuid, 'check')">
				<template #icon>
					<CheckCircle v-if="confirmed" :size="20" class="announcement-card__check--read" />
					<CheckCircleOutline v-else :size="20" />
				</template>
			</NcButton>
			<NcButton
				variant="tertiary"
				:disabled="preview"
				:aria-label="t('launchpad', 'Close {title}', { title: announcement.title })"
				:title="t('launchpad', 'Close')"
				data-test="announcement-close"
				@click="$emit('confirm', announcement.uuid, 'x')">
				<template #icon>
					<Close :size="20" />
				</template>
			</NcButton>
			<span class="announcement-card__spacer" />
			<NcButton
				v-if="manageUrl"
				variant="tertiary"
				:href="manageUrl"
				data-test="announcement-manage">
				{{ t('launchpad', 'Manage') }}
			</NcButton>
			<NcButton
				variant="secondary"
				:disabled="preview"
				data-test="announcement-open"
				@click="$emit('open', announcement.uuid)">
				{{ t('launchpad', 'Open') }}
			</NcButton>
		</footer>
		<slot />
	</article>
</template>

<script>
import { getCanonicalLocale, t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'

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
 * Announcements widget (REQ-ANN-010): its category, date, title, a short
 * text and the read row of REQ-ANN-007: the check ("Read", filled once
 * confirmed) and the x, both emitting `confirm` with the kind; the date to
 * confirm before when the author asked for it; Open, which opens the
 * announcement's own view (REQ-ANN-011); and, for a manager, Manage, a link
 * to the Announcements tab on this announcement. No like, comment or follow
 * here: those live in the view. The tab's preview renders this same
 * component with `preview` set.
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-widget-only-shows-req-ann-010
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */
export default {
	name: 'AnnouncementCard',

	components: { CheckCircle, CheckCircleOutline, Close, NcButton },

	props: {
		/** The announcement, as the announcements API returns it. */
		announcement: {
			type: Object,
			required: true,
		},

		/** Rendered in the tab's preview: the buttons are inert. */
		preview: {
			type: Boolean,
			default: false,
		},

		/** Whether the reader confirmed it (REQ-ANN-007): the check is filled. */
		confirmed: {
			type: Boolean,
			default: false,
		},

		/** For a manager: the Announcements tab on this announcement; '' for a reader. */
		manageUrl: {
			type: String,
			default: '',
		},
	},

	emits: ['confirm', 'open'],

	computed: {
		/**
		 * @return {string} The publish date in the reader's locale.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-the-announcements-widget-only-shows-req-ann-010
		 */
		formattedDate() {
			const date = new Date(this.announcement.publishAt)
			return Number.isNaN(date.getTime()) ? '' : date.toLocaleDateString()
		},

		/**
		 * @return {string} "Confirm before 30 October", or '' without a deadline.
		 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
		 */
		confirmBeforeText() {
			return confirmBeforeLabel(this.announcement.confirmBefore, getCanonicalLocale())
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
	display: -webkit-box;
	overflow: hidden;
	-webkit-line-clamp: 3;
	-webkit-box-orient: vertical;
	white-space: pre-line;
}

.announcement-card__footer {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
	margin-top: 8px;
}

.announcement-card__deadline {
	flex-basis: 100%;
	margin: 0;
	font-weight: bold;
}

.announcement-card__check--read {
	color: var(--color-success-text, var(--color-success));
}

.announcement-card__spacer {
	flex: 1;
}
</style>
