<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - DashboardEditBar: the bar over a dashboard in edit mode (board
  - LpBewerken). It names the dashboard being edited and holds the edit
  - actions: Columns 6, 8 or 12, Add widget, Dashboard settings and Done.
  - Its last element is where the page's More menu docks while the bar is
  - shown (`dock`), so the floating menu never covers Done.
-->
<template>
	<div
		class="launchpad-edit-bar"
		role="toolbar"
		:aria-label="t('launchpad', 'Edit dashboard')"
		data-testid="launchpad-edit-bar">
		<Pencil :size="20" class="launchpad-edit-bar__icon" />
		<strong class="launchpad-edit-bar__title">
			{{ t('launchpad', 'You are editing {name}', { name: dashboardName }) }}
		</strong>
		<span class="launchpad-edit-bar__note">
			{{ t('launchpad', 'Changes are saved immediately') }}
		</span>
		<span class="launchpad-edit-bar__spacer" />
		<span :id="columnsLabelId" class="launchpad-edit-bar__label">
			{{ t('launchpad', 'Columns') }}
		</span>
		<div
			class="launchpad-edit-bar__columns"
			role="group"
			:aria-labelledby="columnsLabelId"
			data-testid="launchpad-edit-bar-columns">
			<button
				v-for="option in columnOptions"
				:key="option"
				type="button"
				class="launchpad-edit-bar__column"
				:class="{ 'launchpad-edit-bar__column--on': option === columns }"
				:aria-pressed="option === columns ? 'true' : 'false'"
				:data-testid="`launchpad-edit-bar-columns-${option}`"
				@click="$emit('setColumns', option)">
				{{ option }}
			</button>
		</div>
		<button
			type="button"
			class="launchpad-edit-bar__button"
			data-testid="launchpad-edit-bar-add-widget"
			@click="$emit('addWidget')">
			<Plus :size="16" />
			{{ t('launchpad', 'Add widget') }}
		</button>
		<button
			v-if="canConfigure"
			type="button"
			class="launchpad-edit-bar__button"
			aria-haspopup="dialog"
			data-testid="launchpad-edit-bar-settings"
			@click="$emit('openSettings')">
			<Cog :size="16" />
			{{ t('launchpad', 'Dashboard settings') }}
		</button>
		<button
			type="button"
			class="launchpad-edit-bar__button launchpad-edit-bar__button--done"
			data-testid="launchpad-edit-bar-done"
			@click="$emit('done')">
			<Check :size="16" />
			{{ t('launchpad', 'Done') }}
		</button>
		<div ref="dock" class="launchpad-edit-bar__dock" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import Check from 'vue-material-design-icons/Check.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'

/** The column counts the grid offers. */
export const GRID_COLUMN_OPTIONS = [6, 8, 12]

let barCounter = 0

export default {
	name: 'DashboardEditBar',

	components: {
		Check,
		Cog,
		Pencil,
		Plus,
	},

	props: {
		/** The name of the dashboard being edited. */
		dashboardName: {
			type: String,
			default: '',
		},

		/** The dashboard's current number of grid columns. */
		columns: {
			type: Number,
			default: 12,
		},

		/**
		 * Whether this person may open the dashboard settings (the owner or
		 * an administrator).
		 */
		canConfigure: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['setColumns', 'addWidget', 'openSettings', 'done', 'dock'],

	data() {
		return {
			columnsLabelId: `launchpad-edit-bar-columns-${++barCounter}`,
			columnOptions: GRID_COLUMN_OPTIONS,
		}
	},

	mounted() {
		this.$emit('dock', this.$refs.dock)
	},

	beforeUnmount() {
		this.$emit('dock', null)
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
/* The bar runs from window edge to window edge right under the top bar, in
   the deep brand colour (LpBewerken; an NL Design theme names it its primary
   hover colour); it sits inside the dashboard's 20px
   inset and 8px top padding, hence the negative margins. */
.launchpad-edit-bar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 12px 16px;
	margin: -8px -20px 16px;
	padding: 12px 28px;
	background: var(--sc-primary-deep, var(--nldesign-color-primary-hover, var(--color-primary-element)));
	color: var(--color-primary-element-text);
}

.launchpad-edit-bar__icon {
	flex: none;
}

.launchpad-edit-bar__title {
	font-size: 16px;
	font-weight: 600;
}

.launchpad-edit-bar__note {
	font-size: 14px;
	opacity: 0.9;
}

.launchpad-edit-bar__spacer {
	flex: 1;
}

.launchpad-edit-bar__label {
	font-size: 14px;
	font-weight: 600;
}

.launchpad-edit-bar__columns {
	display: flex;
	gap: 2px;
	padding: 3px;
	border-radius: var(--border-radius-element, 8px);
	background: color-mix(in srgb, var(--color-primary-element-text) 16%, transparent);
}

.launchpad-edit-bar__column,
.launchpad-edit-bar__button {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	margin: 0;
	border-radius: var(--border-radius-element, 8px);
	font: inherit;
	font-size: 14px;
	font-weight: 600;
	cursor: pointer;
}

.launchpad-edit-bar__column {
	min-width: 38px;
	height: 32px;
	justify-content: center;
	padding: 0 10px;
	border: 0;
	background: transparent;
	color: var(--color-primary-element-text);
}

.launchpad-edit-bar__column--on {
	background: var(--color-primary-element-text);
	color: var(--color-primary-element);
}

.launchpad-edit-bar__button {
	height: 38px;
	padding: 0 14px;
	border: 1px solid var(--color-primary-element-text);
	background: transparent;
	color: var(--color-primary-element-text);
}

.launchpad-edit-bar__button--done {
	padding: 0 16px;
	border-color: transparent;
	background: var(--color-primary-element-text);
	color: var(--color-primary-element);
}

.launchpad-edit-bar__dock:empty {
	display: none;
}

.launchpad-edit-bar__column:focus-visible,
.launchpad-edit-bar__button:focus-visible {
	outline: 2px solid var(--color-primary-element-text);
	outline-offset: 2px;
}

@media (max-width: 640px) {
	.launchpad-edit-bar {
		padding: 12px 16px;
	}
}
</style>
