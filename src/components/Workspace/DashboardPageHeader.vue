<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<header class="launchpad-page-header" data-testid="dashboard-page-header">
		<div class="launchpad-page-header__row">
			<h1 class="launchpad-page-header__title">
				{{ dashboard.name }}
			</h1>
			<div class="launchpad-page-header__actions">
				<!-- REQ-DPD-002: the period the dated widgets follow, shown only
				     when one of them names a period field. -->
				<CnSegmentedControl
					v-if="showsPeriod"
					:modelValue="period"
					:options="periodOptions"
					:ariaLabel="t('launchpad', 'Period')"
					size="compact"
					data-testid="dashboard-period"
					@update:modelValue="$emit('update:period', $event)" />
				<!-- REQ-DPD-003: everyone who can open the dashboard may download
				     it, view-only included; the header is not drawn in the kiosk
				     and public views, so neither offers it. -->
				<NcButton
					class="launchpad-print-hide"
					variant="secondary"
					data-testid="dashboard-download"
					@click="download">
					<template #icon>
						<Download :size="20" />
					</template>
					{{ t('launchpad', 'Download') }}
				</NcButton>
				<!-- The top-right controls teleport in here (headerActionsDock.js). -->
				<div :class="dockClass" />
			</div>
		</div>
		<p v-if="kindLabel || metaLine" class="launchpad-page-header__meta">
			<span v-if="kindLabel" class="launchpad-page-header__kind" data-testid="dashboard-page-header-kind">
				<component :is="kindIcon" :size="18" />
				{{ kindLabel }}
			</span>
			<span v-if="metaLine">{{ metaLine }}</span>
		</p>
	</header>
</template>

<script>
import { CnSegmentedControl } from '@conduction/nextcloud-vue'
import { getCanonicalLocale, t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import AccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import Download from 'vue-material-design-icons/Download.vue'
import ShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import { PAGE_HEADER_DOCK_CLASS } from '../../utils/headerActionsDock.js'
import { printDashboard } from '../../utils/printDashboard.js'

/** Milliseconds in a day. */
const DAY_MS = 86400000

/**
 * When the dashboard was last changed, as the boards word it: the time for
 * today, the weekday within the last week, the date before that.
 *
 * @param {string|null} updatedAt ISO date of the last change.
 * @param {Date} now The current moment.
 * @param {string} locale BCP 47 locale.
 * @return {string} The moment, or '' without a usable date.
 */
export function lastChangedText(updatedAt, now, locale) {
	const changed = new Date(updatedAt ?? '')
	if (Number.isNaN(changed.getTime())) {
		return ''
	}
	const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime()
	if (changed.getTime() >= startOfToday) {
		return t('launchpad', 'Last changed today {time}', {
			time: changed.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' }),
		})
	}
	if (changed.getTime() >= startOfToday - 6 * DAY_MS) {
		return t('launchpad', 'Last changed {when}', {
			when: changed.toLocaleDateString(locale, { weekday: 'long' }),
		})
	}
	return t('launchpad', 'Last changed {when}', {
		when: changed.toLocaleDateString(locale, { day: 'numeric', month: 'long' }),
	})
}

/**
 * What the reader may do on the dashboard, for the meta line: "view only"
 * or "you may add widgets"; nothing extra with full rights.
 *
 * @param {string} permissionLevel The reader's level: `full`, `add_only` or `view_only`.
 * @return {string} The words, or '' with full rights.
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
 */
export function rightsText(permissionLevel) {
	if (permissionLevel === 'view_only') {
		return t('launchpad', 'view only')
	}
	if (permissionLevel === 'add_only') {
		return t('launchpad', 'you may add widgets')
	}
	return ''
}

/**
 * DashboardPageHeader: the page title row of a dashboard without a greeting
 * on the page ground (LpWerkplek, LpIntranet, LpAfdeling, LpManagement): the
 * dashboard name, Download (REQ-DPD-003) beside the docked "Meer" menu, and a line
 * saying what kind of dashboard it is and when it last changed.
 *
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-reader-can-download-the-dashboard-req-dpd-003
 */
export default {
	name: 'DashboardPageHeader',

	components: { CnSegmentedControl, Download, NcButton },

	props: {
		/** The active dashboard (`name`, `description`, `updatedAt`). */
		dashboard: {
			type: Object,
			required: true,
		},

		/** Where the dashboard comes from: `user`, `group`, `default` or `shared`. */
		source: {
			type: String,
			default: 'user',
		},

		/** Whether this dashboard opens first for the reader. */
		isDefault: {
			type: Boolean,
			default: false,
		},

		/** The reader's rights on it: `full`, `add_only` or `view_only`. */
		permissionLevel: {
			type: String,
			default: 'full',
		},

		/** Whether a widget on the dashboard names a period field (REQ-DPD-002). */
		showsPeriod: {
			type: Boolean,
			default: false,
		},

		/** The chosen period: `month`, `quarter` or `year`. */
		period: {
			type: String,
			default: 'month',
		},
	},

	emits: ['update:period'],

	/** @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu */
	data() {
		return { dockClass: PAGE_HEADER_DOCK_CLASS }
	},

	computed: {
		/**
		 * The period choices, This month first.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
		 */
		periodOptions() {
			return [
				{ value: 'month', label: t('launchpad', 'This month') },
				{ value: 'quarter', label: t('launchpad', 'Quarter') },
				{ value: 'year', label: t('launchpad', 'Year') },
			]
		},

		/**
		 * @return {string} The kind of dashboard for the chip, '' for a group dashboard.
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
		 */
		kindLabel() {
			if (this.source === 'user') {
				return this.isDefault
					? t('launchpad', 'Personal dashboard · opens first')
					: t('launchpad', 'Personal dashboard')
			}
			if (this.source === 'shared') {
				return t('launchpad', 'Shared with you')
			}
			return ''
		},

		/**
		 * @return {object} The chip icon.
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
		 */
		kindIcon() {
			return this.source === 'shared' ? ShareVariant : AccountOutline
		},

		/**
		 * The description, what the reader may do and the last change,
		 * joined as one line.
		 *
		 * @return {string}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
		 */
		metaLine() {
			const parts = [
				(this.dashboard.description ?? '').trim(),
				rightsText(this.permissionLevel),
				lastChangedText(this.dashboard.updatedAt, new Date(), getCanonicalLocale()),
			].filter((part) => part !== '')
			return parts.join(' · ')
		},
	},

	methods: {
		t,

		/**
		 * Download the dashboard as a PDF: the reader's browser prints the
		 * page as it is now, with the period and filters applied.
		 *
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-reader-can-download-the-dashboard-req-dpd-003
		 */
		download() {
			printDashboard(this.dashboard.name)
		},
	},
}
</script>

<style scoped>
/* The title starts on the grid's 28px line (the container's 20px inset plus
   8px here) and the meta line sits 14px under it (LpWerkplek). */
.launchpad-page-header {
	display: flex;
	flex-direction: column;
	gap: 14px;
	padding: 16px 8px 8px;
}

.launchpad-page-header__row {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	align-items: flex-start;
	gap: 12px 24px;
}

.launchpad-page-header__title {
	flex: 1 1 320px;
	min-width: 0;
	margin: 0;
	font-size: 28px;
	font-weight: 700;
	line-height: 1.2;
	color: var(--color-main-text);
}

.launchpad-page-header__actions {
	flex: none;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 10px;
}

.launchpad-page-header__meta {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px 12px;
	margin: 0;
	font-size: 15px;
	color: var(--color-text-maxcontrast);
}

.launchpad-page-header__kind {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 3px 12px 3px 8px;
	border-radius: var(--border-radius-pill, 14px);
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
	font-size: 14px;
	font-weight: 600;
}
</style>
