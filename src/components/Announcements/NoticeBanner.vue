<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		class="notice-banner"
		:class="`notice-banner--${level}`"
		:role="level === 'warning' ? 'alert' : 'status'"
		data-test="notice-banner">
		<span class="notice-banner__icon" aria-hidden="true">
			<AlertOutline v-if="level === 'warning'" :size="20" />
			<InformationOutline v-else :size="20" />
		</span>
		<div class="notice-banner__text">
			<span class="notice-banner__level hidden-visually">{{ levelLabel }}</span>
			<strong class="notice-banner__title">{{ notice.title }}</strong>
			<span v-if="notice.body" class="notice-banner__body">{{ notice.body }}</span>
		</div>
		<div v-if="!preview" class="notice-banner__actions">
			<NcButton
				variant="tertiary"
				:pressed="read"
				:aria-label="read ? t('launchpad', 'Read') : t('launchpad', 'Mark as read')"
				:title="read ? t('launchpad', 'Read') : t('launchpad', 'Mark as read')"
				data-test="notice-confirm"
				@click="$emit('confirm', notice.uuid, 'check')">
				<template #icon>
					<CheckCircle v-if="read" :size="20" />
					<CheckCircleOutline v-else :size="20" />
				</template>
			</NcButton>
			<NcButton
				variant="tertiary"
				:aria-label="t('launchpad', 'Close notice')"
				:title="t('launchpad', 'Close notice')"
				data-test="notice-dismiss"
				@click="$emit('confirm', notice.uuid, 'x')">
				<template #icon>
					<Close :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import CheckCircle from 'vue-material-design-icons/CheckCircle.vue'
import CheckCircleOutline from 'vue-material-design-icons/CheckCircleOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'

/**
 * NoticeBanner — one notice above the dashboard grid (REQ-ANN-005). The
 * level shows by icon and by text, not by colour alone. Every notice has the
 * read row (REQ-ANN-007): the check ("Read", filled once confirmed) and the
 * x; both emit `confirm` with the kind. A stored "may not be dismissed"
 * setting is ignored (announcements-managed-in-settings D4). The preview on
 * the Announcements tab renders this same component with `preview` set.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
 */
export default {
	name: 'NoticeBanner',

	components: { AlertOutline, CheckCircle, CheckCircleOutline, Close, InformationOutline, NcButton },

	props: {
		/** The notice, as the announcements API returns it. */
		notice: {
			type: Object,
			required: true,
		},

		/** Rendered in the editor's preview: no read row. */
		preview: {
			type: Boolean,
			default: false,
		},

		/** The reader confirmed it: the check is filled. */
		read: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['confirm'],

	computed: {
		/** @return {string} `warning` or `info`. */
		level() {
			return this.notice.level === 'warning' ? 'warning' : 'info'
		},

		/** @return {string} The level as text. */
		levelLabel() {
			return this.level === 'warning' ? t('launchpad', 'Warning') : t('launchpad', 'Information')
		},
	},

	methods: { t },
}
</script>

<style scoped>
/* LpIntranet: a full-width bar under the page header, title in bold and the
   body beside it. */
.notice-banner {
	display: flex;
	align-items: center;
	gap: 10px 16px;
	padding: 12px 28px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-size: 15px;
}

.notice-banner--warning {
	background: var(--color-primary-element-hover);
	color: var(--color-primary-element-text);
}

.notice-banner__icon {
	display: flex;
	flex: none;
}

.notice-banner__text {
	display: flex;
	flex: 1;
	flex-wrap: wrap;
	align-items: baseline;
	gap: 4px 16px;
	min-width: 0;
}

.notice-banner__title {
	font-weight: 600;
}

.notice-banner__body {
	flex: 1 1 260px;
}

.notice-banner__actions {
	display: flex;
	flex: none;
	gap: 4px;
}

.notice-banner--warning .notice-banner__body {
	color: var(--color-primary-element-light);
}

.notice-banner--warning :deep(.button-vue) {
	color: var(--color-primary-element-text);
}
</style>
