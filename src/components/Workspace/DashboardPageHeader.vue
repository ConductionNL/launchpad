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
				<!-- REQ-DPD-003: Download lives in the More menu's This dashboard
				     group now (Ruben's review item 8); the header keeps only More. -->
				<!-- The top-right controls teleport in here (headerActionsDock.js). -->
				<div :class="dockClass" />
			</div>
		</div>
		<p v-if="kindLabel || metaLine" class="launchpad-page-header__meta">
			<span v-if="kindLabel" class="launchpad-page-header__kind" data-testid="dashboard-page-header-kind">
				<component :is="kindIcon" :size="18" />
				{{ kindLabel }}
			</span>
			<!-- The description is a flex item of its own, the same box the
			     first-paint shell prints, so taking the page over paints no
			     larger text than the shell did (its paint stays the first
			     useful content). Rights and last change follow in a second one. -->
			<span v-if="metaDescription" class="launchpad-page-header__description">{{ metaDescription }}</span>
			<span
				v-if="metaRest"
				class="launchpad-page-header__rest"
				:class="{ 'launchpad-page-header__rest--joined': metaDescription !== '' }">{{ metaDescription !== '' ? ' · ' + metaRest : metaRest }}</span>
		</p>
	</header>
</template>

<script>
import { CnSegmentedControl } from '@conduction/nextcloud-vue'
import { getCanonicalLocale, t } from '@nextcloud/l10n'
import AccountGroupOutline from 'vue-material-design-icons/AccountGroupOutline.vue'
import AccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import ShareVariant from 'vue-material-design-icons/ShareVariant.vue'
import { PAGE_HEADER_DOCK_CLASS } from '../../utils/headerActionsDock.js'

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
 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
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
 * dashboard name, the docked "Meer" menu (Download is in it, REQ-DPD-003), and a line
 * saying what kind of dashboard it is and when it last changed.
 *
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-reader-can-download-the-dashboard-req-dpd-003
 */
export default {
	name: 'DashboardPageHeader',

	components: { CnSegmentedControl },

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
		 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
		 */
		periodOptions() {
			return [
				{ value: 'month', label: t('launchpad', 'This month') },
				{ value: 'quarter', label: t('launchpad', 'Quarter') },
				{ value: 'year', label: t('launchpad', 'Year') },
			]
		},

		/**
		 * @return {string} The kind of dashboard for the chip: personal, the
		 *   group it belongs to, or shared; '' for the organisation-wide one.
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
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
			if (this.source === 'group' && this.groupName !== '') {
				return t('launchpad', 'Group dashboard of {group}', { group: this.groupName })
			}
			return ''
		},

		/**
		 * The group a group dashboard belongs to: its display name when the
		 * row carries one, else its group id; '' for the organisation-wide
		 * `default` sentinel.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
		 */
		groupName() {
			const id = String(this.dashboard.groupId ?? '').trim()
			if (id === '' || id === 'default') {
				return ''
			}
			const display = String(this.dashboard.groupDisplayName ?? '').trim()
			return display !== '' ? display : id
		},

		/**
		 * @return {object} The chip icon.
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
		 */
		kindIcon() {
			if (this.source === 'shared') {
				return ShareVariant
			}
			return this.source === 'group' ? AccountGroupOutline : AccountOutline
		},

		/**
		 * The description, what the reader may do and the last change,
		 * joined as one line.
		 *
		 * @return {string}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
		 */
		metaLine() {
			return [this.metaDescription, this.metaRest].filter((part) => part !== '').join(' · ')
		},

		/**
		 * The dashboard's description, the first part of the meta line.
		 *
		 * @return {string}
		 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md
		 */
		metaDescription() {
			return (this.dashboard.description ?? '').trim()
		},

		/**
		 * What the reader may do and the last change, the rest of the meta line.
		 *
		 * @return {string}
		 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-the-header-says-what-dashboard-this-is-and-what-the-reader-may-do-req-dpd-001
		 */
		metaRest() {
			return [
				rightsText(this.permissionLevel),
				lastChangedText(this.dashboard.updatedAt, new Date(), getCanonicalLocale()),
			].filter((part) => part !== '').join(' · ')
		},
	},

	methods: {
		t,
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

/* The rest follows the description as if on one line: the 12px gap less
   8px leaves the width of the space the joined line had before the dot. */
.launchpad-page-header__rest--joined {
	margin-inline-start: -8px;
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
