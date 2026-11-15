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
			<strong class="notice-banner__level">{{ levelLabel }}</strong>
			<span class="notice-banner__title">{{ notice.title }}</span>
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
.notice-banner {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 10px 16px;
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
	color: var(--color-main-text);
}

.notice-banner--warning {
	background: var(--color-warning);
	color: var(--color-warning-text, var(--color-main-text));
}

.notice-banner__text {
	display: flex;
	flex: 1;
	flex-wrap: wrap;
	gap: 4px 8px;
}

.notice-banner__body {
	flex-basis: 100%;
}
</style>
