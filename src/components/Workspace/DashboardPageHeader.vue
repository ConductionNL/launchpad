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
				<NcButton
					v-if="editAction"
					variant="secondary"
					data-testid="dashboard-page-header-action"
					@click="$emit(editAction.event)">
					<template #icon>
						<component :is="editAction.icon" :size="20" />
					</template>
					{{ editAction.label }}
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
import { getLanguage, t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import AccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
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
 * DashboardPageHeader: the page title row of a dashboard without a greeting
 * on the page ground (LpWerkplek, LpIntranet, LpAfdeling, LpManagement): the
 * dashboard name, the edit or add action, the docked "Meer" menu, and a line
 * saying what kind of dashboard it is and when it last changed.
 *
 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
 */
export default {
	name: 'DashboardPageHeader',

	components: { NcButton },

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

		/** The reader's permission level on the dashboard. */
		permissionLevel: {
			type: String,
			default: 'view_only',
		},

		/** Whether the dashboard is being edited. */
		isEditMode: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['edit', 'addWidget'],

	/** @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu */
	data() {
		return { dockClass: PAGE_HEADER_DOCK_CLASS }
	},

	computed: {
		/**
		 * The action beside "Meer": edit with full rights, add a widget with
		 * add-only rights, none while editing or for a read-only reader.
		 *
		 * @return {{label: string, icon: object, event: string}|null}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 */
		editAction() {
			if (this.isEditMode) {
				return null
			}
			if (this.permissionLevel === 'full') {
				return { label: t('launchpad', 'Edit'), icon: Pencil, event: 'edit' }
			}
			if (this.permissionLevel === 'add_only') {
				return { label: t('launchpad', 'Add widget'), icon: Plus, event: 'addWidget' }
			}
			return null
		},

		/**
		 * @return {string} The kind of dashboard for the chip, '' for a group dashboard.
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
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
		 */
		kindIcon() {
			return this.source === 'shared' ? ShareVariant : AccountOutline
		},

		/**
		 * The description and the last change, joined as one line.
		 *
		 * @return {string}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-009-no-navigation-panel-the-destinations-are-in-the-more-menu
		 */
		metaLine() {
			const parts = [
				(this.dashboard.description ?? '').trim(),
				lastChangedText(this.dashboard.updatedAt, new Date(), getLanguage().replace('_', '-')),
			].filter((part) => part !== '')
			return parts.join(' · ')
		},
	},
}
</script>

<style scoped>
.launchpad-page-header {
	display: flex;
	flex-direction: column;
	gap: 8px;
	padding: 16px 20px 8px;
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
