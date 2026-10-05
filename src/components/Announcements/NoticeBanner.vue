<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section
		class="notice-banner"
		:class="`notice-banner--${level}`"
		:role="level === 'warning' ? 'alert' : 'status'"
		:aria-label="levelLabel">
		<component
			:is="levelIcon"
			class="notice-banner__icon"
			:size="20"
			aria-hidden="true" />
		<div class="notice-banner__text">
			<p class="notice-banner__title">
				<span class="notice-banner__level">{{ levelLabel }}:</span>
				{{ notice.title }}
			</p>
			<p v-if="notice.body" class="notice-banner__body">
				{{ notice.body }}
			</p>
		</div>
		<NcButton
			v-if="dismissible"
			variant="tertiary"
			:aria-label="t('launchpad', 'Close this notice')"
			@click="$emit('dismiss', notice.uuid)">
			<template #icon>
				<CloseIcon :size="20" />
			</template>
		</NcButton>
	</section>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import AlertOutlineIcon from 'vue-material-design-icons/AlertOutline.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'

/**
 * NoticeBanner: one notice above the dashboard grid (REQ-ANN-005). The level
 * shows as an icon and a word, never by colour alone. The editor's preview
 * renders this same component.
 */
export default {
	name: 'NoticeBanner',

	components: { NcButton, CloseIcon },

	props: {
		/** The notice as the API returns it. */
		notice: {
			type: Object,
			required: true,
		},

		/** Hide the close button (the editor preview). */
		hideDismiss: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['dismiss'],

	computed: {
		/** @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005 */
		level() {
			return this.notice.level === 'warning' ? 'warning' : 'info'
		},

		/** @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005 */
		levelLabel() {
			return this.level === 'warning'
				? t('launchpad', 'Warning')
				: t('launchpad', 'Information')
		},

		/** @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005 */
		levelIcon() {
			return this.level === 'warning'
				? AlertOutlineIcon
				: InformationOutlineIcon
		},

		/** @spec openspec/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005 */
		dismissible() {
			return !this.hideDismiss && this.notice.dismissible === true
		},
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.notice-banner {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	padding: 12px 16px;
	border-radius: var(--border-radius-large);
	border: 2px solid var(--color-border-dark);
	background-color: var(--color-main-background);
	color: var(--color-main-text);
}

.notice-banner--warning {
	border-color: var(--color-warning);
}

.notice-banner--info {
	border-color: var(--color-info, var(--color-primary-element));
}

.notice-banner__icon {
	flex-shrink: 0;
	margin-top: 2px;
}

.notice-banner__text {
	flex: 1;
	min-width: 0;
}

.notice-banner__title {
	margin: 0;
	font-weight: bold;
}

.notice-banner__level {
	margin-inline-end: 4px;
}

.notice-banner__body {
	margin: 4px 0 0;
	white-space: pre-line;
}
</style>
