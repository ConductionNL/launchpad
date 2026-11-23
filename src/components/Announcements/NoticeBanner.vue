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
		<NcButton
			v-if="notice.dismissible && !preview"
			variant="tertiary"
			:aria-label="t('launchpad', 'Dismiss notice')"
			data-test="notice-dismiss"
			@click="$emit('dismiss', notice.uuid)">
			<template #icon>
				<Close :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import AlertOutline from 'vue-material-design-icons/AlertOutline.vue'
import Close from 'vue-material-design-icons/Close.vue'
import InformationOutline from 'vue-material-design-icons/InformationOutline.vue'

/**
 * NoticeBanner — one notice above the dashboard grid (REQ-ANN-005). The
 * level shows by icon and by text, not by colour alone; a dismissible notice
 * has a close button, a non-dismissible one none. The editor's preview
 * renders this same component with `preview` set.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
 */
export default {
	name: 'NoticeBanner',

	components: { AlertOutline, Close, InformationOutline, NcButton },

	props: {
		/** The notice, as the announcements API returns it. */
		notice: {
			type: Object,
			required: true,
		},

		/** Rendered in the editor's preview: no dismiss button. */
		preview: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['dismiss'],

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

.notice-banner--warning .notice-banner__body {
	color: var(--color-primary-element-light);
}

.notice-banner--warning :deep(.button-vue) {
	color: var(--color-primary-element-text);
}
</style>
