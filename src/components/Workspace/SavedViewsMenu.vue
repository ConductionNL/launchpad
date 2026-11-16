<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="saved-views" data-testid="saved-views">
		<NcButton
			variant="tertiary"
			:aria-expanded="expanded ? 'true' : 'false'"
			aria-controls="launchpad-saved-views-list"
			data-testid="saved-views-toggle"
			@click="expanded = !expanded">
			<template #icon>
				<BookmarkOutline :size="20" />
			</template>
			{{ t('launchpad', 'Saved views') }}
		</NcButton>
		<NcButton
			v-if="canSave"
			variant="tertiary"
			data-testid="saved-views-save"
			@click="$emit('save')">
			<template #icon>
				<BookmarkPlusOutline :size="20" />
			</template>
			{{ t('launchpad', 'Save view') }}
		</NcButton>
		<div
			v-if="expanded"
			id="launchpad-saved-views-list"
			class="saved-views__list"
			data-testid="saved-views-list">
			<p v-if="mine.length === 0 && shared.length === 0" class="saved-views__empty">
				{{ t('launchpad', 'No saved views yet') }}
			</p>
			<section v-if="mine.length > 0" class="saved-views__section" data-testid="saved-views-mine">
				<h3 class="saved-views__heading">
					{{ t('launchpad', 'My views') }}
				</h3>
				<ul>
					<li v-for="view in mine" :key="view.id" class="saved-views__item">
						<button
							type="button"
							class="saved-views__apply"
							:title="view.description || null"
							data-testid="saved-view-apply"
							@click="apply(view)">
							{{ view.name }}
						</button>
						<span v-if="view.isShared" class="saved-views__badge">{{ t('launchpad', 'Shared') }}</span>
						<button
							type="button"
							class="saved-views__icon"
							:aria-label="t('launchpad', 'Edit {name}', { name: view.name })"
							data-testid="saved-view-edit"
							@click="$emit('edit', view)">
							<Pencil :size="16" />
						</button>
						<button
							type="button"
							class="saved-views__icon"
							:aria-label="view.isShared ? t('launchpad', 'Stop sharing {name}', { name: view.name }) : t('launchpad', 'Share {name}', { name: view.name })"
							data-testid="saved-view-share"
							@click="$emit('share', view, !view.isShared)">
							<AccountMultipleRemove v-if="view.isShared" :size="16" />
							<AccountMultiplePlus v-else :size="16" />
						</button>
						<button
							type="button"
							class="saved-views__icon"
							:aria-label="t('launchpad', 'Delete {name}', { name: view.name })"
							data-testid="saved-view-delete"
							@click="$emit('delete', view)">
							<Delete :size="16" />
						</button>
					</li>
				</ul>
			</section>
			<section v-if="shared.length > 0" class="saved-views__section" data-testid="saved-views-shared">
				<h3 class="saved-views__heading">
					{{ t('launchpad', 'Shared views') }}
				</h3>
				<ul>
					<li v-for="view in shared" :key="view.id" class="saved-views__item">
						<button
							type="button"
							class="saved-views__apply"
							:title="view.description || null"
							data-testid="saved-view-apply"
							@click="apply(view)">
							{{ view.name }}
						</button>
					</li>
				</ul>
			</section>
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import AccountMultiplePlus from 'vue-material-design-icons/AccountMultiplePlus.vue'
import AccountMultipleRemove from 'vue-material-design-icons/AccountMultipleRemove.vue'
import BookmarkOutline from 'vue-material-design-icons/BookmarkOutline.vue'
import BookmarkPlusOutline from 'vue-material-design-icons/BookmarkPlusOutline.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import { FLAG_PROP, LIST_PROP } from '../../constants/propDefinitions.js'

/**
 * SavedViewsMenu: the dashboard's saved views (drill-down-cross-widget-filter
 * REQ-CWF-009) in two sections, the user's own and the ones others shared.
 * Picking a view applies it; the creator's own views can be edited, shared
 * or unshared and deleted, a shared view of someone else only applied.
 * "Save view" shows when the user may edit the dashboard and some filter is
 * active. The component only emits; the dashboard does the requests.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
 */
export default {
	name: 'SavedViewsMenu',

	components: {
		AccountMultiplePlus,
		AccountMultipleRemove,
		BookmarkOutline,
		BookmarkPlusOutline,
		Delete,
		NcButton,
		Pencil,
	},

	props: {
		/** The user's own views. */
		mine: LIST_PROP,
		/** Views other users shared. */
		shared: LIST_PROP,
		/** Whether "Save view" is offered. */
		canSave: FLAG_PROP,
	},

	emits: ['apply', 'save', 'edit', 'share', 'delete'],

	data() {
		return { expanded: false }
	},

	methods: {
		t,

		/**
		 * Apply a view and fold the list.
		 *
		 * @param {object} view The view.
		 * @return {void}
		 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-12
		 */
		apply(view) {
			this.expanded = false
			this.$emit('apply', view)
		},
	},
}
</script>

<style scoped>
.saved-views {
	position: relative;
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
}

.saved-views__list {
	position: absolute;
	top: 100%;
	inset-inline-start: 0;
	z-index: 10;
	min-width: 280px;
	max-height: 50vh;
	overflow-y: auto;
	padding: 8px;
	background: var(--color-main-background);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large);
	box-shadow: 0 2px 8px var(--color-box-shadow);
}

.saved-views__list ul {
	margin: 0;
	padding: 0;
	list-style: none;
}

.saved-views__heading {
	margin: 8px 0 4px;
	font-size: var(--default-font-size);
	color: var(--color-text-maxcontrast);
}

.saved-views__empty {
	margin: 4px;
	color: var(--color-text-maxcontrast);
}

.saved-views__item {
	display: flex;
	align-items: center;
	gap: 4px;
}

.saved-views__apply {
	flex: 1;
	min-height: 34px;
	padding: 4px 8px;
	text-align: start;
	background: none;
	border: none;
	border-radius: var(--border-radius);
	color: var(--color-main-text);
	cursor: pointer;
}

.saved-views__apply:hover,
.saved-views__apply:focus-visible,
.saved-views__icon:hover,
.saved-views__icon:focus-visible {
	background: var(--color-background-hover);
}

.saved-views__badge {
	font-size: var(--font-size-small, 13px);
	color: var(--color-text-maxcontrast);
}

.saved-views__icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 34px;
	min-height: 34px;
	background: none;
	border: none;
	border-radius: var(--border-radius);
	color: var(--color-main-text);
	cursor: pointer;
}
</style>
